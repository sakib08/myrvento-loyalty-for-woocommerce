<?php
/**
 * Award points from WooCommerce, reviews, signup, birthday, social.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Points earner.
 */
class GrowthPilot_Points_Earner {

	/**
	 * Hook into WooCommerce and WordPress.
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status' ), 20, 4 );
		add_action( 'woocommerce_created_customer', array( $this, 'on_customer_created' ), 20, 1 );
		add_action( 'user_register', array( $this, 'on_user_register' ), 20, 1 );
		add_action( 'comment_unapproved_to_approved', array( $this, 'on_review_approved' ), 20, 1 );
		add_action( 'comment_post', array( $this, 'on_comment_post' ), 20, 3 );
	}

	/**
	 * Award or revoke on order status change.
	 *
	 * @param int      $order_id Order ID.
	 * @param string   $from     Old status.
	 * @param string   $to       New status.
	 * @param WC_Order $order    Order.
	 * @return void
	 */
	public function on_order_status( $order_id, $from, $to, $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		$earn_status = GrowthPilot_Settings::get_value( 'earn_order_status', 'completed' );

		if ( $to === $earn_status ) {
			$this->award_order( $order );
			GrowthPilot_Referral_Rewards::on_order( $order );
			GrowthPilot_VIP_Tiers::evaluate( (int) $order->get_customer_id() );
			return;
		}

		$revoke_statuses = array( 'refunded', 'cancelled', 'failed' );
		if ( in_array( $to, $revoke_statuses, true ) && $from === $earn_status ) {
			$this->revoke_order( $order );
		}
	}

	/**
	 * Award purchase + first-purchase points.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public function award_order( $order ) {
		$customer_id = (int) $order->get_customer_id();
		if ( $customer_id <= 0 ) {
			return;
		}

		if ( $order->get_meta( '_gp_points_awarded' ) ) {
			return;
		}

		$calc   = GrowthPilot_Points_Rules::calculate_for_order( $order );
		$points = (int) $calc['points'];

		if ( $points > 0 ) {
			$result = GrowthPilot_Points_Ledger::credit(
				$customer_id,
				$points,
				'purchase',
				array(
					'order_id'    => $order->get_id(),
					'source_id'   => $order->get_id(),
					'description' => sprintf(
						/* translators: %s order number */
						__( 'Purchase — order %s', 'growthpilot' ),
						$order->get_order_number()
					),
					'meta'        => array( 'breakdown' => $calc['breakdown'] ),
				)
			);

			if ( ! is_wp_error( $result ) ) {
				$order->update_meta_data( '_gp_points_awarded', $result );
				$order->update_meta_data( '_gp_points_amount', $points );
				$order->save();
			}
		}

		$this->maybe_first_purchase( $order, $customer_id );
		$this->update_streak( $customer_id, $order );
	}

	/**
	 * First-purchase bonus if this is the customer's first qualifying order.
	 *
	 * @param WC_Order $order       Order.
	 * @param int      $customer_id Customer ID.
	 * @return void
	 */
	private function maybe_first_purchase( $order, $customer_id ) {
		$rule = GrowthPilot_Points_Rules::get_global( 'first_purchase' );
		if ( ! $rule || (int) $rule->points <= 0 ) {
			return;
		}

		if ( get_user_meta( $customer_id, '_gp_first_purchase_bonus', true ) ) {
			return;
		}

		$prior = wc_get_orders(
			array(
				'customer_id' => $customer_id,
				'status'      => array( 'wc-completed', 'wc-processing' ),
				'limit'       => 2,
				'exclude'     => array( $order->get_id() ),
				'return'      => 'ids',
			)
		);

		if ( ! empty( $prior ) ) {
			return;
		}

		$result = GrowthPilot_Points_Ledger::credit(
			$customer_id,
			(int) $rule->points,
			'first_purchase',
			array(
				'order_id'    => $order->get_id(),
				'source_id'   => $order->get_id(),
				'description' => __( 'First purchase bonus', 'growthpilot' ),
			)
		);

		if ( ! is_wp_error( $result ) ) {
			update_user_meta( $customer_id, '_gp_first_purchase_bonus', $result );
		}
	}

	/**
	 * Revoke remaining points from an awarded order.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public function revoke_order( $order ) {
		$customer_id = (int) $order->get_customer_id();
		if ( $customer_id <= 0 ) {
			return;
		}

		$awarded = (int) $order->get_meta( '_gp_points_amount' );
		if ( $awarded <= 0 || $order->get_meta( '_gp_points_revoked' ) ) {
			return;
		}

		$available = GrowthPilot_Points_Ledger::available( $customer_id );
		$revoke    = min( $awarded, $available );

		if ( $revoke > 0 ) {
			$result = GrowthPilot_Points_Ledger::debit(
				$customer_id,
				$revoke,
				'purchase',
				array(
					'type'        => 'revoke',
					'order_id'    => $order->get_id(),
					'source_id'   => $order->get_id(),
					'description' => sprintf(
						/* translators: %s order number */
						__( 'Revoked — order %s refunded/cancelled', 'growthpilot' ),
						$order->get_order_number()
					),
				)
			);

			if ( ! is_wp_error( $result ) ) {
				$order->update_meta_data( '_gp_points_revoked', $result );
				$order->save();
			}
		}
	}

	/**
	 * Welcome points for new WooCommerce customers.
	 *
	 * @param int $customer_id User ID.
	 * @return void
	 */
	public function on_customer_created( $customer_id ) {
		$this->award_signup( (int) $customer_id );
	}

	/**
	 * Welcome points for generic WP registration that later shops.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_user_register( $user_id ) {
		$this->award_signup( (int) $user_id );
	}

	/**
	 * Award signup/welcome points once.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function award_signup( $user_id ) {
		if ( $user_id <= 0 || get_user_meta( $user_id, '_gp_signup_points', true ) ) {
			return;
		}

		$rule = GrowthPilot_Points_Rules::get_global( 'signup' );
		if ( ! $rule || (int) $rule->points <= 0 ) {
			return;
		}

		$result = GrowthPilot_Points_Ledger::credit(
			$user_id,
			(int) $rule->points,
			'signup',
			array(
				'source_id'   => $user_id,
				'description' => __( 'Welcome points', 'growthpilot' ),
			)
		);

		if ( ! is_wp_error( $result ) ) {
			update_user_meta( $user_id, '_gp_signup_points', $result );
		}
	}

	/**
	 * Award when a product review is posted already-approved.
	 *
	 * @param int        $comment_id Comment ID.
	 * @param int|string $approved   Approval state.
	 * @param array      $data       Comment data.
	 * @return void
	 */
	public function on_comment_post( $comment_id, $approved, $data ) {
		if ( 1 !== (int) $approved && '1' !== (string) $approved ) {
			return;
		}

		$comment = get_comment( $comment_id );
		if ( $comment ) {
			$this->award_review( $comment );
		}
	}

	/**
	 * Award when a review is approved later.
	 *
	 * @param WP_Comment $comment Comment.
	 * @return void
	 */
	public function on_review_approved( $comment ) {
		$this->award_review( $comment );
	}

	/**
	 * Review reward.
	 *
	 * @param WP_Comment $comment Comment.
	 * @return void
	 */
	private function award_review( $comment ) {
		if ( ! $comment || 'product' !== get_post_type( $comment->comment_post_ID ) ) {
			return;
		}

		$user_id = (int) $comment->user_id;
		if ( $user_id <= 0 ) {
			return;
		}

		if ( get_comment_meta( $comment->comment_ID, '_gp_review_points', true ) ) {
			return;
		}

		$rule = GrowthPilot_Points_Rules::get_global( 'review' );
		if ( ! $rule || (int) $rule->points <= 0 ) {
			return;
		}

		$result = GrowthPilot_Points_Ledger::credit(
			$user_id,
			(int) $rule->points,
			'review',
			array(
				'source_id'   => (int) $comment->comment_ID,
				'description' => __( 'Product review reward', 'growthpilot' ),
			)
		);

		if ( ! is_wp_error( $result ) ) {
			update_comment_meta( $comment->comment_ID, '_gp_review_points', $result );
		}
	}

	/**
	 * Birthday rewards for customers whose birthday is today.
	 *
	 * @return int Awarded count.
	 */
	public static function award_birthdays() {
		$rule = GrowthPilot_Points_Rules::get_global( 'birthday' );
		if ( ! $rule || (int) $rule->points <= 0 ) {
			return 0;
		}

		$today = wp_date( 'm-d' );
		$year  = wp_date( 'Y' );
		$users = get_users(
			array(
				'meta_key'   => 'gp_birthday',
				'meta_value' => $today,
				'fields'     => 'ID',
				'number'     => 500,
			)
		);

		$count = 0;
		foreach ( $users as $user_id ) {
			$key = '_gp_birthday_' . $year;
			if ( get_user_meta( $user_id, $key, true ) ) {
				continue;
			}

			$result = GrowthPilot_Points_Ledger::credit(
				(int) $user_id,
				(int) $rule->points,
				'birthday',
				array(
					'source_id'   => (int) $year,
					'description' => __( 'Birthday reward', 'growthpilot' ),
				)
			);

			if ( ! is_wp_error( $result ) ) {
				update_user_meta( $user_id, $key, $result );
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Social sharing reward.
	 *
	 * @param int    $user_id User ID.
	 * @param string $channel Channel key.
	 * @return int|WP_Error
	 */
	public static function award_social( $user_id, $channel ) {
		$rule = GrowthPilot_Points_Rules::get_global( 'social' );
		if ( ! $rule || (int) $rule->points <= 0 ) {
			return new WP_Error( 'gp_social_disabled', __( 'Social sharing rewards are disabled.', 'growthpilot' ) );
		}

		$once = (bool) GrowthPilot_Settings::get_value( 'social_once', true );

		if ( $once && get_user_meta( $user_id, '_gp_social_share', true ) ) {
			return new WP_Error( 'gp_social_once', __( 'Social sharing points were already awarded.', 'growthpilot' ) );
		}

		$meta_key = '_gp_social_share_' . sanitize_key( $channel );
		if ( ! $once && get_user_meta( $user_id, $meta_key, true ) ) {
			return new WP_Error( 'gp_social_channel', __( 'This channel was already rewarded.', 'growthpilot' ) );
		}

		$result = GrowthPilot_Points_Ledger::credit(
			$user_id,
			(int) $rule->points,
			'social',
			array(
				'description' => sprintf(
					/* translators: %s share channel */
					__( 'Social share (%s)', 'growthpilot' ),
					sanitize_key( $channel )
				),
				'meta'        => array( 'channel' => $channel ),
			)
		);

		if ( ! is_wp_error( $result ) ) {
			update_user_meta( $user_id, '_gp_social_share', $result );
			update_user_meta( $user_id, $meta_key, $result );
		}

		return $result;
	}

	/**
	 * Update purchase streak user meta.
	 *
	 * @param int      $customer_id Customer ID.
	 * @param WC_Order $order       Order.
	 * @return void
	 */
	private function update_streak( $customer_id, $order ) {
		$today      = wp_date( 'Y-m-d', $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time() );
		$last       = get_user_meta( $customer_id, 'gp_streak_last_date', true );
		$count      = (int) get_user_meta( $customer_id, 'gp_streak_count', true );

		if ( $last === $today ) {
			return;
		}

		if ( $last ) {
			$yesterday = wp_date( 'Y-m-d', strtotime( $today . ' -1 day' ) );
			$count     = ( $last === $yesterday ) ? $count + 1 : 1;
		} else {
			$count = 1;
		}

		update_user_meta( $customer_id, 'gp_streak_count', $count );
		update_user_meta( $customer_id, 'gp_streak_last_date', $today );
	}
}
