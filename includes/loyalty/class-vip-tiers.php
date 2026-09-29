<?php
/**
 * VIP tiers — spending, order-count, or points based.
 *
 * @package Myrvento
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables have no core API.

/**
 * VIP tier engine.
 */
class Myrvento_VIP_Tiers {

	/**
	 * All tiers ordered by sort_order.
	 *
	 * @return array<int, object>
	 */
	public static function all() {
		global $wpdb;

		$table = esc_sql( Myrvento::table( 'vip_tiers' ) );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $rows ? $rows : array();
	}

	/**
	 * Get one tier.
	 *
	 * @param int $id Tier ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = esc_sql( Myrvento::table( 'vip_tiers' ) );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $id
			)
		);
	}

	/**
	 * Save a tier.
	 *
	 * @param array<string, mixed> $data Data.
	 * @param int                  $id   Optional ID.
	 * @return int|WP_Error
	 */
	public static function save( $data, $id = 0 ) {
		global $wpdb;

		$table = esc_sql( Myrvento::table( 'vip_tiers' ) );
		$slug  = sanitize_title( $data['slug'] ?? $data['name'] ?? '' );

		if ( '' === $slug ) {
			return new WP_Error( 'myrvento_tier_slug', __( 'Tier name is required.', 'myrvento-loyalty-for-woocommerce' ) );
		}

		$benefits = $data['benefits'] ?? array();
		if ( is_string( $benefits ) ) {
			$benefits = array_filter( array_map( 'trim', explode( "\n", $benefits ) ) );
		}

		$row = array(
			'slug'            => $slug,
			'name'            => sanitize_text_field( $data['name'] ?? $slug ),
			'color'           => sanitize_hex_color( $data['color'] ?? '#64748b' ) ?: '#64748b',
			'qualifier_type'  => sanitize_key( $data['qualifier_type'] ?? 'spending' ),
			'qualifier_value' => (float) ( $data['qualifier_value'] ?? 0 ),
			'sort_order'      => (int) ( $data['sort_order'] ?? 0 ),
			'benefits'        => wp_json_encode( array_values( (array) $benefits ) ),
			'is_default'      => ! empty( $data['is_default'] ) ? 1 : 0,
		);

		if ( $row['is_default'] ) {
			$wpdb->query( "UPDATE {$table} SET is_default = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $id ) );
			return (int) $id;
		}

		$wpdb->insert( $table, $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete a tier.
	 *
	 * @param int $id Tier ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		return (bool) $wpdb->delete( esc_sql( Myrvento::table( 'vip_tiers' ) ), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Default / lowest tier.
	 *
	 * @return object|null
	 */
	public static function get_default() {
		global $wpdb;

		$table = esc_sql( Myrvento::table( 'vip_tiers' ) );
		$row   = $wpdb->get_row( "SELECT * FROM {$table} WHERE is_default = 1 ORDER BY sort_order ASC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $row ) {
			return $row;
		}

		return $wpdb->get_row( "SELECT * FROM {$table} ORDER BY sort_order ASC, qualifier_value ASC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Evaluate and assign the correct tier for a customer.
	 *
	 * @param int  $customer_id Customer ID.
	 * @param bool $manual      Skip downgrade window.
	 * @return object|null Assigned tier.
	 */
	public static function evaluate( $customer_id, $manual = false ) {
		$customer_id = (int) $customer_id;
		if ( $customer_id <= 0 ) {
			return null;
		}

		$tiers = self::all();
		if ( empty( $tiers ) ) {
			return null;
		}

		$metrics = self::customer_metrics( $customer_id );
		$matched = null;

		foreach ( $tiers as $tier ) {
			$value = 0;
			if ( 'order_count' === $tier->qualifier_type ) {
				$value = $metrics['order_count'];
			} elseif ( 'points' === $tier->qualifier_type ) {
				$value = $metrics['points'];
			} else {
				$value = $metrics['spending'];
			}

			if ( $value >= (float) $tier->qualifier_value ) {
				$matched = $tier;
			}
		}

		if ( ! $matched ) {
			$matched = self::get_default();
		}

		if ( ! $matched ) {
			return null;
		}

		$balance     = Myrvento_Points_Ledger::get_balance( $customer_id );
		$current_id  = (int) $balance->tier_id;
		$new_id      = (int) $matched->id;

		if ( $current_id === $new_id ) {
			self::touch_balance_tier( $customer_id, $new_id );
			return $matched;
		}

		$current = $current_id ? self::get( $current_id ) : null;
		$is_down = $current && (int) $current->sort_order > (int) $matched->sort_order;

		if ( $is_down && ! $manual ) {
			$enabled = (bool) Myrvento_Settings::get_value( 'downgrade_enabled', true );
			$window  = (int) Myrvento_Settings::get_value( 'downgrade_window_days', 365 );
			$last    = $balance->tier_evaluated_at ? strtotime( $balance->tier_evaluated_at ) : 0;

			if ( ! $enabled ) {
				return $current;
			}

			if ( $last && ( time() - $last ) < ( $window * DAY_IN_SECONDS ) ) {
				return $current;
			}
		}

		self::assign( $customer_id, $new_id, $current_id, $is_down ? 'downgrade' : 'upgrade' );

		return $matched;
	}

	/**
	 * Assign a tier and log history.
	 *
	 * @param int    $customer_id Customer ID.
	 * @param int    $tier_id     New tier.
	 * @param int    $previous    Previous tier ID.
	 * @param string $reason      upgrade|downgrade|manual|auto.
	 * @return void
	 */
	public static function assign( $customer_id, $tier_id, $previous = 0, $reason = 'auto' ) {
		global $wpdb;

		self::touch_balance_tier( $customer_id, $tier_id );

		$wpdb->insert(
			esc_sql( Myrvento::table( 'customer_tiers' ) ),
			array(
				'customer_id'      => (int) $customer_id,
				'tier_id'          => (int) $tier_id,
				'previous_tier_id' => $previous ? (int) $previous : null,
				'reason'           => sanitize_key( $reason ),
				'assigned_at'      => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Re-evaluate every customer with a balance row.
	 *
	 * @return int
	 */
	public static function evaluate_all() {
		global $wpdb;

		$balances = esc_sql( Myrvento::table( 'points_balances' ) );
		$ids      = $wpdb->get_col( "SELECT customer_id FROM {$balances}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count    = 0;

		foreach ( $ids as $id ) {
			self::evaluate( (int) $id );
			++$count;
		}

		return $count;
	}

	/**
	 * Customer qualifier metrics.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array{spending: float, order_count: int, points: int}
	 */
	public static function customer_metrics( $customer_id ) {
		$orders = wc_get_orders(
			array(
				'customer_id' => (int) $customer_id,
				'status'      => array( 'wc-completed' ),
				'limit'       => -1,
				'return'      => 'objects',
			)
		);

		$spend = 0.0;
		$count = 0;

		foreach ( $orders as $order ) {
			$spend += (float) $order->get_total();
			++$count;
		}

		$balance = Myrvento_Points_Ledger::get_balance( $customer_id );

		return array(
			'spending'    => $spend,
			'order_count' => $count,
			'points'      => (int) $balance->lifetime_earned,
		);
	}

	/**
	 * Persist current tier on the balance row.
	 *
	 * @param int $customer_id Customer ID.
	 * @param int $tier_id     Tier ID.
	 * @return void
	 */
	private static function touch_balance_tier( $customer_id, $tier_id ) {
		global $wpdb;

		$table = esc_sql( Myrvento::table( 'points_balances' ) );
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT customer_id FROM {$table} WHERE customer_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $customer_id
			)
		);

		if ( ! $exists ) {
			$wpdb->insert(
				$table,
				array(
					'customer_id'       => (int) $customer_id,
					'available'         => 0,
					'updated_at'        => current_time( 'mysql' ),
					'tier_id'           => (int) $tier_id,
					'tier_evaluated_at' => current_time( 'mysql' ),
				)
			);
			return;
		}

		$wpdb->update(
			$table,
			array(
				'tier_id'           => (int) $tier_id,
				'tier_evaluated_at' => current_time( 'mysql' ),
			),
			array( 'customer_id' => (int) $customer_id )
		);
	}

	/**
	 * Format a tier for REST/templates.
	 *
	 * @param object|null $tier Tier row.
	 * @return array<string, mixed>|null
	 */
	public static function to_array( $tier ) {
		if ( ! $tier ) {
			return null;
		}

		return array(
			'id'              => (int) $tier->id,
			'slug'            => $tier->slug,
			'name'            => $tier->name,
			'color'           => $tier->color,
			'qualifier_type'  => $tier->qualifier_type,
			'qualifier_value' => (float) $tier->qualifier_value,
			'sort_order'      => (int) $tier->sort_order,
			'benefits'        => Myrvento::decode( $tier->benefits ),
			'is_default'      => (bool) $tier->is_default,
		);
	}
}
