<?php
/**
 * Referral rewards — always credited through the loyalty ledger.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables have no core API.

/**
 * Referral → points.
 */
class GrowthPilot_Referral_Rewards {

	/**
	 * Referee signup bonus (to the new customer).
	 *
	 * @param int $referee_id  New customer.
	 * @param int $referrer_id Referrer.
	 * @return void
	 */
	public static function on_signup( $referee_id, $referrer_id ) {
		$campaign = GrowthPilot_Referral_Program::active_campaign();
		if ( ! $campaign || (int) $campaign->referee_signup_points <= 0 ) {
			return;
		}

		if ( get_user_meta( $referee_id, '_gp_referral_signup_points', true ) ) {
			return;
		}

		$result = GrowthPilot_Points_Ledger::credit(
			$referee_id,
			(int) $campaign->referee_signup_points,
			'referral',
			array(
				'source_id'   => $referrer_id,
				'description' => __( 'Referral welcome bonus', 'gp-ppros' ),
			)
		);

		if ( ! is_wp_error( $result ) ) {
			update_user_meta( $referee_id, '_gp_referral_signup_points', $result );
		}
	}

	/**
	 * First-order and recurring rewards for the referrer.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public static function on_order( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$referee_id = (int) $order->get_customer_id();
		if ( $referee_id <= 0 ) {
			return;
		}

		$code = (string) $order->get_meta( '_gp_referral_code' );
		if ( '' === $code ) {
			$code = (string) get_user_meta( $referee_id, 'gp_referred_code', true );
		}

		$row = GrowthPilot_Referral_Program::get_for_referee( $referee_id );

		if ( ! $row && '' !== $code ) {
			$referrer_id = GrowthPilot_Referral_Program::find_referrer_by_code( $code );
			if ( $referrer_id && $referrer_id !== $referee_id ) {
				$id  = GrowthPilot_Referral_Program::upsert( $referrer_id, $code, $referee_id, 'signed_up' );
				$row = null;
				global $wpdb;
				$table = esc_sql( GrowthPilot::table( 'referrals' ) );
				$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}

		if ( ! $row ) {
			return;
		}

		$referrer_id = (int) $row->referrer_id;
		if ( $referrer_id === $referee_id ) {
			return;
		}

		$campaign = $row->campaign_id
			? GrowthPilot_Referral_Program::get_campaign( (int) $row->campaign_id )
			: GrowthPilot_Referral_Program::active_campaign();

		if ( ! $campaign ) {
			return;
		}

		$is_first = empty( $row->first_order_rewarded );

		if ( $is_first && (int) $campaign->first_order_points > 0 ) {
			if ( $order->get_meta( '_gp_referral_first_awarded' ) ) {
				return;
			}

			$result = GrowthPilot_Points_Ledger::credit(
				$referrer_id,
				(int) $campaign->first_order_points,
				'referral',
				array(
					'order_id'    => $order->get_id(),
					'source_id'   => (int) $row->id,
					'description' => sprintf(
						/* translators: %s order number */
						__( 'Referral first-order bonus (order %s)', 'gp-ppros' ),
						$order->get_order_number()
					),
				)
			);

			if ( ! is_wp_error( $result ) ) {
				global $wpdb;
				$wpdb->update(
					esc_sql( GrowthPilot::table( 'referrals' ) ),
					array(
						'status'               => 'rewarded',
						'attributed_order_id'  => $order->get_id(),
						'first_order_rewarded' => 1,
						'converted_at'         => current_time( 'mysql' ),
					),
					array( 'id' => (int) $row->id )
				);
				$order->update_meta_data( '_gp_referral_first_awarded', $result );
				$order->save();
				do_action( 'growthpilot_referral_converted', $referrer_id, $referee_id, $order->get_id() );
			}

			return;
		}

		if ( ! $is_first && (int) $campaign->recurring_points > 0 ) {
			if ( $order->get_meta( '_gp_referral_recurring_awarded' ) ) {
				return;
			}

			$result = GrowthPilot_Points_Ledger::credit(
				$referrer_id,
				(int) $campaign->recurring_points,
				'referral',
				array(
					'order_id'    => $order->get_id(),
					'source_id'   => (int) $row->id,
					'description' => sprintf(
						/* translators: %s order number */
						__( 'Referral recurring bonus (order %s)', 'gp-ppros' ),
						$order->get_order_number()
					),
				)
			);

			if ( ! is_wp_error( $result ) ) {
				global $wpdb;
				$wpdb->query(
					$wpdb->prepare(
						'UPDATE ' . esc_sql( GrowthPilot::table( 'referrals' ) ) . ' SET recurring_orders_count = recurring_orders_count + 1, status = %s WHERE id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						'rewarded',
						(int) $row->id
					)
				);
				$order->update_meta_data( '_gp_referral_recurring_awarded', $result );
				$order->save();
			}
		}
	}
}
