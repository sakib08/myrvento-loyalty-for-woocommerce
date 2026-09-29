<?php
/**
 * Earn rules (purchase rate, product/category, review, signup, etc.).
 *
 * @package Myrvento
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables have no core API.

/**
 * Point rules repository.
 */
class Myrvento_Points_Rules {

	/**
	 * All rules, optionally filtered.
	 *
	 * @param array<string, mixed> $args Filters.
	 * @return array<int, object>
	 */
	public static function all( $args = array() ) {
		global $wpdb;

		$table   = esc_sql( Myrvento::table( 'point_rules' ) );
		$source  = ! empty( $args['source'] ) ? (string) $args['source'] : '';
		$enabled = isset( $args['enabled'] ) ? (int) $args['enabled'] : -1;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is escaped.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ( %s = '' OR source = %s ) AND ( %d = -1 OR enabled = %d ) ORDER BY sort_order ASC, id ASC",
				$source,
				$source,
				$enabled,
				$enabled
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $rows ? $rows : array();
	}

	/**
	 * Single rule.
	 *
	 * @param int $id Rule ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = esc_sql( Myrvento::table( 'point_rules' ) );

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $table . ' WHERE id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				(int) $id
			)
		);
	}

	/**
	 * First enabled rule for a source (global, no object).
	 *
	 * @param string $source Source key.
	 * @return object|null
	 */
	public static function get_global( $source ) {
		global $wpdb;

		$table = esc_sql( Myrvento::table( 'point_rules' ) );

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $table . ' WHERE source = %s AND enabled = 1 AND (object_id IS NULL OR object_id = 0) ORDER BY sort_order ASC, id ASC LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$source
			)
		);
	}

	/**
	 * Insert or update a rule.
	 *
	 * @param array<string, mixed> $data Rule data.
	 * @param int                  $id   Optional ID.
	 * @return int|WP_Error
	 */
	public static function save( $data, $id = 0 ) {
		global $wpdb;

		$table = esc_sql( Myrvento::table( 'point_rules' ) );

		$row = array(
			'name'        => sanitize_text_field( $data['name'] ?? '' ),
			'source'      => sanitize_key( $data['source'] ?? 'purchase' ),
			'enabled'     => ! empty( $data['enabled'] ) ? 1 : 0,
			'points'      => (int) ( $data['points'] ?? 0 ),
			'rate'        => (float) ( $data['rate'] ?? 0 ),
			'object_id'   => ! empty( $data['object_id'] ) ? (int) $data['object_id'] : null,
			'object_type' => ! empty( $data['object_type'] ) ? sanitize_key( $data['object_type'] ) : null,
			'config'      => ! empty( $data['config'] ) ? wp_json_encode( $data['config'] ) : null,
			'sort_order'  => (int) ( $data['sort_order'] ?? 0 ),
		);

		if ( '' === $row['name'] ) {
			return new WP_Error( 'myrvento_rule_name', __( 'Rule name is required.', 'myrvento-loyalty-for-woocommerce' ) );
		}

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $id ) );
			return (int) $id;
		}

		$wpdb->insert( $table, $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete a rule.
	 *
	 * @param int $id Rule ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		return (bool) $wpdb->delete( esc_sql( Myrvento::table( 'point_rules' ) ), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Calculate points for a WooCommerce order.
	 *
	 * @param WC_Order $order Order.
	 * @return array{points: int, breakdown: array<int, array<string, mixed>>}
	 */
	public static function calculate_for_order( $order ) {
		$points     = 0;
		$breakdown  = array();
		$now        = current_time( 'timestamp' );
		$subtotal   = (float) $order->get_subtotal();
		$purchase   = self::get_global( 'purchase' );

		if ( $purchase ) {
			$base = (int) floor( $subtotal * (float) $purchase->rate ) + (int) $purchase->points;
			if ( $base > 0 ) {
				$points     += $base;
				$breakdown[] = array(
					'source' => 'purchase',
					'points' => $base,
					'label'  => $purchase->name,
				);
			}
		}

		foreach ( $order->get_items() as $item ) {
			$product_id = (int) $item->get_product_id();
			$qty        = max( 1, (int) $item->get_quantity() );

			$product_rules = self::object_rules( 'product', $product_id );
			foreach ( $product_rules as $rule ) {
				$extra        = ( (int) $rule->points + (int) floor( (float) $item->get_subtotal() * (float) $rule->rate ) );
				$points      += $extra;
				$breakdown[]  = array(
					'source' => 'product',
					'points' => $extra,
					'label'  => $rule->name,
					'object' => $product_id,
				);
			}

			$product = $item->get_product();
			$cats    = array();
			if ( $product ) {
				$cats = wc_get_product_term_ids( $product_id, 'product_cat' );
			}

			foreach ( $cats as $cat_id ) {
				$cat_rules = self::object_rules( 'category', (int) $cat_id );
				foreach ( $cat_rules as $rule ) {
					$extra        = (int) $rule->points * $qty + (int) floor( (float) $item->get_subtotal() * (float) $rule->rate );
					$points      += $extra;
					$breakdown[]  = array(
						'source' => 'category',
						'points' => $extra,
						'label'  => $rule->name,
						'object' => (int) $cat_id,
					);
				}
			}
		}

		$campaigns = self::all( array( 'source' => 'campaign', 'enabled' => 1 ) );
		foreach ( $campaigns as $rule ) {
			$config = Myrvento::decode( $rule->config );
			$start  = ! empty( $config['starts_at'] ) ? strtotime( $config['starts_at'] ) : 0;
			$end    = ! empty( $config['ends_at'] ) ? strtotime( $config['ends_at'] ) : 0;

			if ( ( $start && $now < $start ) || ( $end && $now > $end ) ) {
				continue;
			}

			$extra = (int) $rule->points + (int) floor( $subtotal * (float) $rule->rate );
			if ( $extra > 0 ) {
				$points     += $extra;
				$breakdown[] = array(
					'source' => 'campaign',
					'points' => $extra,
					'label'  => $rule->name,
				);
			}
		}

		return array(
			'points'     => max( 0, $points ),
			'breakdown'  => $breakdown,
		);
	}

	/**
	 * Enabled object-specific rules.
	 *
	 * @param string $object_type product|category.
	 * @param int    $object_id   Object ID.
	 * @return array<int, object>
	 */
	private static function object_rules( $object_type, $object_id ) {
		global $wpdb;

		$table = esc_sql( Myrvento::table( 'point_rules' ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $table . ' WHERE enabled = 1 AND object_type = %s AND object_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$object_type,
				(int) $object_id
			)
		);

		return $rows ? $rows : array();
	}
}
