<?php
/**
 * Unified points ledger — the only writer of customer balances.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables have no core API.

/**
 * Points ledger.
 */
class GrowthPilot_Points_Ledger {

	/**
	 * Credit points (earn or positive adjustment).
	 *
	 * @param int                  $customer_id Customer user ID.
	 * @param int                  $amount      Positive points.
	 * @param string               $source      Earn source key.
	 * @param array<string, mixed> $args        Extra fields.
	 * @return int|WP_Error Ledger ID.
	 */
	public static function credit( $customer_id, $amount, $source, $args = array() ) {
		$amount = (int) $amount;

		if ( $amount <= 0 ) {
			return new WP_Error( 'gp_invalid_amount', __( 'Points amount must be greater than zero.', 'gp-ppros' ) );
		}

		return self::write(
			(int) $customer_id,
			$amount,
			isset( $args['type'] ) ? $args['type'] : 'earn',
			$source,
			$args
		);
	}

	/**
	 * Debit points (redeem, expire, revoke, negative adjust).
	 *
	 * @param int                  $customer_id Customer user ID.
	 * @param int                  $amount      Positive points to subtract.
	 * @param string               $source      Source key.
	 * @param array<string, mixed> $args        Extra fields.
	 * @return int|WP_Error Ledger ID.
	 */
	public static function debit( $customer_id, $amount, $source, $args = array() ) {
		$amount = (int) $amount;

		if ( $amount <= 0 ) {
			return new WP_Error( 'gp_invalid_amount', __( 'Points amount must be greater than zero.', 'gp-ppros' ) );
		}

		$type = isset( $args['type'] ) ? $args['type'] : 'redeem';

		return self::write( (int) $customer_id, 0 - $amount, $type, $source, $args );
	}

	/**
	 * Insert a ledger row and update the denormalized balance.
	 *
	 * @param int                  $customer_id Customer ID.
	 * @param int                  $amount      Signed amount.
	 * @param string               $type        earn|redeem|expire|adjust|revoke.
	 * @param string               $source      Source key.
	 * @param array<string, mixed> $args        Extra.
	 * @return int|WP_Error
	 */
	private static function write( $customer_id, $amount, $type, $source, $args ) {
		global $wpdb;

		$customer_id = (int) $customer_id;
		if ( $customer_id <= 0 ) {
			return new WP_Error( 'gp_invalid_customer', __( 'Invalid customer.', 'gp-ppros' ) );
		}

		$ledger   = esc_sql( GrowthPilot::table( 'points_ledger' ) );
		$balances = esc_sql( GrowthPilot::table( 'points_balances' ) );

		$wpdb->query( 'START TRANSACTION' );

		$balance = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$balances} WHERE customer_id = %d FOR UPDATE", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$customer_id
			)
		);

		if ( ! $balance ) {
			$wpdb->insert(
				$balances,
				array(
					'customer_id'      => $customer_id,
					'available'        => 0,
					'pending'          => 0,
					'lifetime_earned'  => 0,
					'lifetime_redeemed'=> 0,
					'lifetime_expired' => 0,
					'updated_at'       => current_time( 'mysql' ),
				)
			);
			$balance = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$balances} WHERE customer_id = %d FOR UPDATE", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$customer_id
				)
			);
		}

		$available = (int) $balance->available;

		if ( $amount < 0 && $available < abs( $amount ) && empty( $args['allow_negative'] ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'gp_insufficient_points', __( 'Not enough points.', 'gp-ppros' ) );
		}

		if ( $amount < 0 && empty( $args['skip_consume'] ) ) {
			self::consume_lots( $customer_id, abs( $amount ) );
		}

		$new_available = $available + $amount;
		$earned        = (int) $balance->lifetime_earned;
		$redeemed      = (int) $balance->lifetime_redeemed;
		$expired       = (int) $balance->lifetime_expired;

		if ( $amount > 0 && 'earn' === $type ) {
			$earned += $amount;
		} elseif ( $amount < 0 && 'redeem' === $type ) {
			$redeemed += abs( $amount );
		} elseif ( $amount < 0 && 'expire' === $type ) {
			$expired += abs( $amount );
		} elseif ( $amount > 0 && 'adjust' === $type ) {
			$earned += $amount;
		} elseif ( $amount < 0 && in_array( $type, array( 'adjust', 'revoke' ), true ) ) {
			$redeemed += abs( $amount );
		}

		$expires_at = null;
		$remaining  = 0;

		if ( $amount > 0 ) {
			$remaining = $amount;
			$days      = (int) GrowthPilot_Settings::get_value( 'expiration_days', 0 );
			if ( $days > 0 && empty( $args['no_expire'] ) ) {
				$expires_at = gmdate( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) );
				if ( function_exists( 'wp_date' ) ) {
					$expires_at = wp_date( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) );
				} else {
					$expires_at = date_i18n( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) );
				}
			}
		}

		if ( ! empty( $args['expires_at'] ) ) {
			$expires_at = $args['expires_at'];
		}

		$wpdb->insert(
			$ledger,
			array(
				'customer_id'   => $customer_id,
				'amount'        => $amount,
				'remaining'     => $remaining,
				'balance_after' => $new_available,
				'type'          => $type,
				'source'        => sanitize_key( $source ),
				'source_id'     => isset( $args['source_id'] ) ? (int) $args['source_id'] : null,
				'order_id'      => isset( $args['order_id'] ) ? (int) $args['order_id'] : null,
				'description'   => isset( $args['description'] ) ? sanitize_text_field( $args['description'] ) : '',
				'expires_at'    => $expires_at,
				'created_at'    => current_time( 'mysql' ),
				'created_by'    => isset( $args['created_by'] ) ? (int) $args['created_by'] : get_current_user_id(),
				'meta'          => ! empty( $args['meta'] ) ? wp_json_encode( $args['meta'] ) : null,
			)
		);

		$ledger_id = (int) $wpdb->insert_id;

		$wpdb->update(
			$balances,
			array(
				'available'         => $new_available,
				'lifetime_earned'   => $earned,
				'lifetime_redeemed' => $redeemed,
				'lifetime_expired'  => $expired,
				'updated_at'        => current_time( 'mysql' ),
			),
			array( 'customer_id' => $customer_id ),
			array( '%d', '%d', '%d', '%d', '%s' ),
			array( '%d' )
		);

		$wpdb->query( 'COMMIT' );

		/**
		 * Fires after a ledger write.
		 *
		 * @param int    $customer_id Customer ID.
		 * @param int    $ledger_id   New row ID.
		 * @param int    $amount      Signed amount.
		 * @param string $source      Source key.
		 */
		do_action( 'growthpilot_points_changed', $customer_id, $ledger_id, $amount, $source );

		return $ledger_id;
	}

	/**
	 * FIFO consume remaining on oldest earn lots.
	 *
	 * @param int $customer_id Customer ID.
	 * @param int $amount      Points to consume.
	 * @return void
	 */
	private static function consume_lots( $customer_id, $amount ) {
		global $wpdb;

		$ledger = esc_sql( GrowthPilot::table( 'points_ledger' ) );
		$left   = $amount;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, remaining FROM {$ledger} WHERE customer_id = %d AND remaining > 0 ORDER BY created_at ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$customer_id
			)
		);

		foreach ( $rows as $row ) {
			if ( $left <= 0 ) {
				break;
			}

			$take = min( (int) $row->remaining, $left );
			$wpdb->update(
				$ledger,
				array( 'remaining' => (int) $row->remaining - $take ),
				array( 'id' => (int) $row->id ),
				array( '%d' ),
				array( '%d' )
			);
			$left -= $take;
		}
	}

	/**
	 * Expire lots past expires_at.
	 *
	 * @return int Number of customers affected.
	 */
	public static function expire_due_points() {
		global $wpdb;

		$ledger = esc_sql( GrowthPilot::table( 'points_ledger' ) );
		$now    = current_time( 'mysql' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, customer_id, remaining FROM {$ledger} WHERE remaining > 0 AND expires_at IS NOT NULL AND expires_at <= %s ORDER BY expires_at ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now
			)
		);

		$affected = array();

		foreach ( $rows as $row ) {
			$wpdb->update(
				$ledger,
				array( 'remaining' => 0 ),
				array( 'id' => (int) $row->id ),
				array( '%d' ),
				array( '%d' )
			);

			$result = self::debit(
				(int) $row->customer_id,
				(int) $row->remaining,
				'expiration',
				array(
					'type'         => 'expire',
					'source_id'    => (int) $row->id,
					'description'  => __( 'Points expired', 'gp-ppros' ),
					'no_expire'    => true,
					'skip_consume' => true,
				)
			);

			if ( ! is_wp_error( $result ) ) {
				$affected[ (int) $row->customer_id ] = true;
			}
		}

		return count( $affected );
	}

	/**
	 * Get balance row.
	 *
	 * @param int $customer_id Customer ID.
	 * @return object|null
	 */
	public static function get_balance( $customer_id ) {
		global $wpdb;

		$balances = esc_sql( GrowthPilot::table( 'points_balances' ) );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$balances} WHERE customer_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $customer_id
			)
		);

		if ( $row ) {
			return $row;
		}

		return (object) array(
			'customer_id'       => (int) $customer_id,
			'available'         => 0,
			'pending'           => 0,
			'lifetime_earned'   => 0,
			'lifetime_redeemed' => 0,
			'lifetime_expired'  => 0,
			'tier_id'           => null,
			'tier_evaluated_at' => null,
		);
	}

	/**
	 * Available points.
	 *
	 * @param int $customer_id Customer ID.
	 * @return int
	 */
	public static function available( $customer_id ) {
		return (int) self::get_balance( $customer_id )->available;
	}

	/**
	 * Ledger history.
	 *
	 * @param int                  $customer_id Customer ID.
	 * @param array<string, mixed> $args        page, per_page.
	 * @return array{items: array<int, object>, total: int}
	 */
	public static function get_history( $customer_id, $args = array() ) {
		global $wpdb;

		$ledger   = esc_sql( GrowthPilot::table( 'points_ledger' ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$ledger} WHERE customer_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $customer_id
			)
		);

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$ledger} WHERE customer_id = %d ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $customer_id,
				$per_page,
				$offset
			)
		);

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Whether this source+source_id was already credited (idempotency).
	 *
	 * @param int    $customer_id Customer ID.
	 * @param string $source      Source.
	 * @param int    $source_id   Source ID.
	 * @return bool
	 */
	public static function already_awarded( $customer_id, $source, $source_id ) {
		global $wpdb;

		$ledger = esc_sql( GrowthPilot::table( 'points_ledger' ) );

		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$ledger} WHERE customer_id = %d AND source = %s AND source_id = %d AND type = 'earn' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $customer_id,
				$source,
				(int) $source_id
			)
		);

		return ! empty( $found );
	}
}
