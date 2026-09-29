<?php
/**
 * Badges, challenges, streaks, leaderboard.
 *
 * @package Ciwp
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables have no core API.

/**
 * Gamification.
 */
class Ciwp_Gamification {

	/**
	 * Listen for points and referrals.
	 */
	public function __construct() {
		add_action( 'ciwp_points_changed', array( $this, 'on_points_changed' ), 20, 1 );
		add_action( 'ciwp_referral_converted', array( $this, 'on_referral_converted' ), 20, 1 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status' ), 30, 4 );
	}

	/**
	 * Recheck badges after points move.
	 *
	 * @param int $customer_id Customer ID.
	 * @return void
	 */
	public function on_points_changed( $customer_id ) {
		self::check_badges( (int) $customer_id );
		self::tick_challenges( (int) $customer_id, 'points', 0 );
	}

	/**
	 * Referral milestone.
	 *
	 * @param int $referrer_id Referrer user ID.
	 * @return void
	 */
	public function on_referral_converted( $referrer_id ) {
		self::check_badges( (int) $referrer_id );
		self::tick_challenges( (int) $referrer_id, 'referrals', 1 );
	}

	/**
	 * Order-based badges and challenges.
	 *
	 * @param int      $order_id Order ID.
	 * @param string   $from     Old status.
	 * @param string   $to       New status.
	 * @param WC_Order $order    Order.
	 * @return void
	 */
	public function on_order_status( $order_id, $from, $to, $order ) {
		$earn = Ciwp_Settings::get_value( 'earn_order_status', 'completed' );
		if ( $to !== $earn || ! $order instanceof WC_Order ) {
			return;
		}

		$customer_id = (int) $order->get_customer_id();
		if ( $customer_id <= 0 ) {
			return;
		}

		self::check_badges( $customer_id );
		self::tick_challenges( $customer_id, 'orders', 1 );
		self::tick_challenges( $customer_id, 'spend', (int) floor( (float) $order->get_total() ) );
		self::tick_challenges( $customer_id, 'streak', (int) get_user_meta( $customer_id, 'gp_streak_count', true ) );
	}

	/**
	 * Award any newly unlocked badges.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array<int, int> Newly awarded badge IDs.
	 */
	public static function check_badges( $customer_id ) {
		static $checking = false;
		if ( $checking ) {
			return array();
		}
		$checking = true;

		global $wpdb;

		$badges = self::badges( true );
		if ( empty( $badges ) ) {
			$checking = false;
			return array();
		}

		$owned = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT badge_id FROM ' . esc_sql( Ciwp::table( 'customer_badges' ) ) . ' WHERE customer_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$customer_id
			)
		);
		$owned = array_map( 'intval', $owned ? $owned : array() );

		$metrics = self::metrics( $customer_id );
		$awarded = array();

		foreach ( $badges as $badge ) {
			if ( in_array( (int) $badge->id, $owned, true ) ) {
				continue;
			}

			$value = $metrics[ $badge->milestone_type ] ?? 0;
			if ( $value < (int) $badge->milestone_value ) {
				continue;
			}

			$wpdb->insert(
				esc_sql( Ciwp::table( 'customer_badges' ) ),
				array(
					'customer_id' => $customer_id,
					'badge_id'    => (int) $badge->id,
					'earned_at'   => current_time( 'mysql' ),
				)
			);

			if ( (int) $badge->points_bonus > 0 ) {
				Ciwp_Points_Ledger::credit(
					$customer_id,
					(int) $badge->points_bonus,
					'campaign',
					array(
						'source_id'   => (int) $badge->id,
						'description' => sprintf(
							/* translators: %s badge name */
							__( 'Badge bonus: %s', 'myrvento-loyalty-for-woocommerce' ),
							$badge->name
						),
						'no_expire'   => true,
					)
				);
			}

			$awarded[] = (int) $badge->id;
		}

		$checking = false;
		return $awarded;
	}

	/**
	 * Increment challenge progress.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $type        Challenge type.
	 * @param int    $delta       Increment (0 = recompute from metrics).
	 * @return void
	 */
	public static function tick_challenges( $customer_id, $type, $delta ) {
		global $wpdb;

		$now        = current_time( 'mysql' );
		$table      = esc_sql( Ciwp::table( 'challenges' ) );
		$challenges = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE enabled = 1 AND type = %s AND (starts_at IS NULL OR starts_at <= %s) AND (ends_at IS NULL OR ends_at >= %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$type,
				$now,
				$now
			)
		);

		if ( ! $challenges ) {
			return;
		}

		$progress_table = esc_sql( Ciwp::table( 'challenge_progress' ) );
		$metrics        = self::metrics( $customer_id );

		foreach ( $challenges as $challenge ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$progress_table} WHERE challenge_id = %d AND customer_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					(int) $challenge->id,
					$customer_id
				)
			);

			if ( $row && $row->completed_at ) {
				continue;
			}

			if ( in_array( $type, array( 'points', 'streak' ), true ) || 0 === (int) $delta ) {
				$progress = (int) ( $metrics[ $type ] ?? 0 );
			} else {
				$progress = ( $row ? (int) $row->progress : 0 ) + (int) $delta;
			}

			$completed = $progress >= (int) $challenge->target_value ? $now : null;

			if ( $row ) {
				$wpdb->update(
					$progress_table,
					array(
						'progress'     => $progress,
						'completed_at' => $completed,
					),
					array( 'id' => (int) $row->id )
				);
			} else {
				$wpdb->insert(
					$progress_table,
					array(
						'challenge_id' => (int) $challenge->id,
						'customer_id'  => $customer_id,
						'progress'     => $progress,
						'completed_at' => $completed,
					)
				);
			}

			if ( $completed && ( ! $row || ! $row->completed_at ) && (int) $challenge->points_reward > 0 ) {
				Ciwp_Points_Ledger::credit(
					$customer_id,
					(int) $challenge->points_reward,
					'campaign',
					array(
						'source_id'   => (int) $challenge->id,
						'description' => sprintf(
							/* translators: %s challenge name */
							__( 'Challenge completed: %s', 'myrvento-loyalty-for-woocommerce' ),
							$challenge->name
						),
					)
				);
			}
		}
	}

	/**
	 * Customer gamification metrics.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array<string, int>
	 */
	public static function metrics( $customer_id ) {
		global $wpdb;

		$metrics = Ciwp_VIP_Tiers::customer_metrics( $customer_id );
		$reviews = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} c INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID WHERE c.user_id = %d AND c.comment_approved = '1' AND p.post_type = 'product'",
				$customer_id
			)
		);

		$referrals = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . esc_sql( Ciwp::table( 'referrals' ) ) . " WHERE referrer_id = %d AND status IN ('converted','rewarded')", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$customer_id
			)
		);

		return array(
			'orders'    => (int) $metrics['order_count'],
			'spend'     => (int) floor( $metrics['spending'] ),
			'points'    => (int) $metrics['points'],
			'reviews'   => $reviews,
			'referrals' => $referrals,
			'streak'    => (int) get_user_meta( $customer_id, 'gp_streak_count', true ),
		);
	}

	/**
	 * Badge catalog.
	 *
	 * @param bool $enabled_only Enabled only.
	 * @return array<int, object>
	 */
	public static function badges( $enabled_only = false ) {
		global $wpdb;

		$table = esc_sql( Ciwp::table( 'badges' ) );
		$sql   = $enabled_only
			? "SELECT * FROM {$table} WHERE enabled = 1 ORDER BY milestone_value ASC, id ASC"
			: "SELECT * FROM {$table} ORDER BY milestone_value ASC, id ASC";

		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $rows ? $rows : array();
	}

	/**
	 * Save badge.
	 *
	 * @param array<string, mixed> $data Data.
	 * @param int                  $id   ID.
	 * @return int|WP_Error
	 */
	public static function save_badge( $data, $id = 0 ) {
		global $wpdb;

		$table = esc_sql( Ciwp::table( 'badges' ) );
		$name  = sanitize_text_field( $data['name'] ?? '' );
		if ( '' === $name ) {
			return new WP_Error( 'gp_badge_name', __( 'Badge name is required.', 'myrvento-loyalty-for-woocommerce' ) );
		}

		$row = array(
			'slug'            => sanitize_title( $data['slug'] ?? $name ),
			'name'            => $name,
			'description'     => sanitize_textarea_field( $data['description'] ?? '' ),
			'icon'            => sanitize_text_field( $data['icon'] ?? '' ),
			'milestone_type'  => sanitize_key( $data['milestone_type'] ?? 'orders' ),
			'milestone_value' => (int) ( $data['milestone_value'] ?? 1 ),
			'points_bonus'    => (int) ( $data['points_bonus'] ?? 0 ),
			'enabled'         => ! empty( $data['enabled'] ) ? 1 : 0,
		);

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $id ) );
			return (int) $id;
		}

		$wpdb->insert( $table, $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete badge.
	 *
	 * @param int $id Badge ID.
	 * @return bool
	 */
	public static function delete_badge( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( esc_sql( Ciwp::table( 'badges' ) ), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Challenges list.
	 *
	 * @return array<int, object>
	 */
	public static function challenges() {
		global $wpdb;

		$table = esc_sql( Ciwp::table( 'challenges' ) );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $rows ? $rows : array();
	}

	/**
	 * Save challenge.
	 *
	 * @param array<string, mixed> $data Data.
	 * @param int                  $id   ID.
	 * @return int|WP_Error
	 */
	public static function save_challenge( $data, $id = 0 ) {
		global $wpdb;

		$table = esc_sql( Ciwp::table( 'challenges' ) );
		$name  = sanitize_text_field( $data['name'] ?? '' );
		if ( '' === $name ) {
			return new WP_Error( 'gp_challenge_name', __( 'Challenge name is required.', 'myrvento-loyalty-for-woocommerce' ) );
		}

		$row = array(
			'name'          => $name,
			'description'   => sanitize_textarea_field( $data['description'] ?? '' ),
			'type'          => sanitize_key( $data['type'] ?? 'orders' ),
			'target_value'  => (int) ( $data['target_value'] ?? 1 ),
			'points_reward' => (int) ( $data['points_reward'] ?? 0 ),
			'starts_at'     => ! empty( $data['starts_at'] ) ? sanitize_text_field( $data['starts_at'] ) : null,
			'ends_at'       => ! empty( $data['ends_at'] ) ? sanitize_text_field( $data['ends_at'] ) : null,
			'enabled'       => ! empty( $data['enabled'] ) ? 1 : 0,
		);

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $id ) );
			return (int) $id;
		}

		$wpdb->insert( $table, $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete challenge.
	 *
	 * @param int $id Challenge ID.
	 * @return bool
	 */
	public static function delete_challenge( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( esc_sql( Ciwp::table( 'challenges' ) ), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Customer badges.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array<int, object>
	 */
	public static function customer_badges( $customer_id ) {
		global $wpdb;

		$sql = 'SELECT b.*, cb.earned_at FROM ' . esc_sql( Ciwp::table( 'customer_badges' ) ) . ' cb
			INNER JOIN ' . esc_sql( Ciwp::table( 'badges' ) ) . ' b ON b.id = cb.badge_id
			WHERE cb.customer_id = %d ORDER BY cb.earned_at DESC';

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, (int) $customer_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $rows ? $rows : array();
	}

	/**
	 * Customer challenge progress.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function customer_challenges( $customer_id ) {
		$challenges = self::challenges();
		$out        = array();
		global $wpdb;
		$table = esc_sql( Ciwp::table( 'challenge_progress' ) );

		foreach ( $challenges as $challenge ) {
			if ( ! $challenge->enabled ) {
				continue;
			}

			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE challenge_id = %d AND customer_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					(int) $challenge->id,
					$customer_id
				)
			);

			$out[] = array(
				'id'            => (int) $challenge->id,
				'name'          => $challenge->name,
				'description'   => $challenge->description,
				'type'          => $challenge->type,
				'target_value'  => (int) $challenge->target_value,
				'points_reward' => (int) $challenge->points_reward,
				'progress'      => $row ? (int) $row->progress : 0,
				'completed_at'  => $row ? $row->completed_at : null,
				'starts_at'     => $challenge->starts_at,
				'ends_at'       => $challenge->ends_at,
			);
		}

		return $out;
	}

	/**
	 * Leaderboard by lifetime earned.
	 *
	 * @param int $limit Limit.
	 * @return array<int, array<string, mixed>>
	 */
	public static function leaderboard( $limit = 20 ) {
		global $wpdb;

		$balances = esc_sql( Ciwp::table( 'points_balances' ) );
		$tiers    = esc_sql( Ciwp::table( 'vip_tiers' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from esc_sql( Ciwp::table() ).
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.customer_id, b.available, b.lifetime_earned, t.name AS tier_name, t.color AS tier_color
				FROM {$balances} b
				LEFT JOIN {$tiers} t ON t.id = b.tier_id
				ORDER BY b.lifetime_earned DESC, b.available DESC
				LIMIT %d",
				(int) $limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$out = array();
		$rank = 1;
		foreach ( $rows ? $rows : array() as $row ) {
			$user  = get_userdata( (int) $row->customer_id );
			$out[] = array(
				'rank'            => $rank++,
				'customer_id'     => (int) $row->customer_id,
				'name'            => $user ? $user->display_name : sprintf( '#%d', $row->customer_id ),
				'email'           => $user ? $user->user_email : '',
				'available'       => (int) $row->available,
				'lifetime_earned' => (int) $row->lifetime_earned,
				'tier_name'       => $row->tier_name,
				'tier_color'      => $row->tier_color,
			);
		}

		return $out;
	}
}
