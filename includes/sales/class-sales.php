<?php
/**
 * Abandoned carts, upsell, cross-sell, and win-back recovery.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables have no core API.

/**
 * Sales and conversion.
 */
class GrowthPilot_Sales {

	/**
	 * Full sales report.
	 *
	 * @return array<string, mixed>
	 */
	public static function report() {
		$pairs = self::pairs();

		return array(
			'abandoned' => self::abandoned(),
			'upsell'    => $pairs['upsell'],
			'cross_sell'=> $pairs['cross_sell'],
			'recovery'  => self::recovery(),
		);
	}

	/**
	 * Sessions that added to cart or reached checkout without a purchase (30 days).
	 *
	 * @return array<string, mixed>
	 */
	public static function abandoned() {
		global $wpdb;

		$table = esc_sql( GrowthPilot::table( 'analytics_events' ) );
		$since = gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from esc_sql( GrowthPilot::table() ).
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT session_id,
					MAX(created_at) AS last_at,
					MAX(customer_id) AS customer_id,
					SUBSTRING_INDEX( GROUP_CONCAT( CASE WHEN product_id > 0 THEN product_id END ORDER BY created_at DESC ), ',', 1 ) AS product_id,
					SUM( event_type = 'add_to_cart' ) AS adds,
					SUM( event_type = 'checkout' ) AS checkouts,
					SUM( event_type = 'purchase' ) AS purchases
				 FROM {$table}
				 WHERE created_at >= %s
				 GROUP BY session_id
				 HAVING ( adds + checkouts ) > 0 AND purchases = 0
				 ORDER BY last_at DESC
				 LIMIT 40",
				$since
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$items = array();
		$value = 0.0;

		foreach ( $rows ? $rows : array() as $row ) {
			$product = (int) $row->product_id ? wc_get_product( (int) $row->product_id ) : null;
			$price   = $product ? (float) $product->get_price() : 0.0;
			$user    = (int) $row->customer_id ? get_userdata( (int) $row->customer_id ) : null;
			$value  += $price;

			$items[] = array(
				'session_id' => $row->session_id,
				'stage'      => (int) $row->checkouts > 0 ? 'checkout' : 'cart',
				'last_at'    => $row->last_at,
				'customer'   => $user ? $user->display_name : __( 'Guest', 'gp-ppros' ),
				'email'      => $user ? $user->user_email : '',
				'product'    => $product ? $product->get_name() : __( 'Unknown product', 'gp-ppros' ),
				'value'      => round( $price, 2 ),
			);
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from esc_sql( GrowthPilot::table() ).
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM (
					SELECT session_id
					FROM {$table}
					WHERE created_at >= %s
					GROUP BY session_id
					HAVING SUM( event_type = 'add_to_cart' ) + SUM( event_type = 'checkout' ) > 0
						AND SUM( event_type = 'purchase' ) = 0
				) abandoned",
				$since
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array(
			'count' => $total,
			'value' => round( $value, 2 ),
			'items' => $items,
		);
	}

	/**
	 * Co-purchased pairs, split into upsell (higher price) and cross-sell.
	 *
	 * @return array{upsell: array<string, mixed>, cross_sell: array<string, mixed>}
	 */
	private static function pairs() {
		global $wpdb;

		$products  = esc_sql( GrowthPilot_Analytics_Query::products_table() );
		$stats     = esc_sql( GrowthPilot_Analytics_Query::stats_table() );
		$statuses  = GrowthPilot_Analytics_Query::paid_statuses();
		$status_in = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$from      = gmdate( 'Y-m-d 00:00:00', time() - ( 365 * DAY_IN_SECONDS ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Lookup tables are escaped. Paid statuses are %s placeholders.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.product_id AS product_id, b.product_id AS related_id, COUNT( DISTINCT a.order_id ) AS orders
				 FROM {$products} a
				 INNER JOIN {$products} b ON b.order_id = a.order_id AND b.product_id <> a.product_id
				 INNER JOIN {$stats} s ON s.order_id = a.order_id
				 WHERE s.status IN ({$status_in}) AND s.parent_id = 0 AND a.date_created >= %s
				 GROUP BY a.product_id, b.product_id
				 ORDER BY orders DESC
				 LIMIT 60",
				array_merge( $statuses, array( $from ) )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$upsell     = array();
		$cross_sell = array();
		$seen       = array();

		foreach ( $rows ? $rows : array() as $row ) {
			$key = min( (int) $row->product_id, (int) $row->related_id ) . ':' . max( (int) $row->product_id, (int) $row->related_id );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;

			$left  = wc_get_product( (int) $row->product_id );
			$right = wc_get_product( (int) $row->related_id );
			if ( ! $left || ! $right ) {
				continue;
			}

			$left_price  = (float) $left->get_price();
			$right_price = (float) $right->get_price();
			$item        = array(
				'orders'  => (int) $row->orders,
				'product' => $left_price >= $right_price ? $right->get_name() : $left->get_name(),
				'related' => $left_price >= $right_price ? $left->get_name() : $right->get_name(),
				'lift'    => round( abs( $left_price - $right_price ), 2 ),
			);

			if ( abs( $left_price - $right_price ) >= 1 ) {
				$upsell[] = $item;
			} else {
				$cross_sell[] = $item;
			}
		}

		return array(
			'upsell'     => array(
				'linked' => self::linked( '_upsell_ids' ),
				'pairs'  => array_slice( $upsell, 0, 15 ),
			),
			'cross_sell' => array(
				'linked' => self::linked( '_crosssell_ids' ),
				'pairs'  => array_slice( $cross_sell, 0, 15 ),
			),
		);
	}

	/**
	 * Products that already have WooCommerce upsell or cross-sell IDs.
	 *
	 * @param string $meta_key _upsell_ids|_crosssell_ids.
	 * @return array<int, array<string, mixed>>
	 */
	private static function linked( $meta_key ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> '' AND meta_value <> 'a:0:{}' LIMIT 20",
				$meta_key
			)
		);

		$out = array();
		foreach ( $rows ? $rows : array() as $row ) {
			$product = wc_get_product( (int) $row->post_id );
			$ids     = maybe_unserialize( $row->meta_value );
			if ( ! $product || ! is_array( $ids ) || ! $ids ) {
				continue;
			}

			$names = array();
			foreach ( array_slice( $ids, 0, 4 ) as $id ) {
				$related = wc_get_product( (int) $id );
				if ( $related ) {
					$names[] = $related->get_name();
				}
			}

			$out[] = array(
				'product' => $product->get_name(),
				'related' => $names,
			);
		}

		return $out;
	}

	/**
	 * Customers quiet for 60+ days, highest spend first.
	 *
	 * @return array<string, mixed>
	 */
	public static function recovery() {
		global $wpdb;

		$stats     = esc_sql( GrowthPilot_Analytics_Query::stats_table() );
		$statuses  = GrowthPilot_Analytics_Query::paid_statuses();
		$status_in = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$now       = current_time( 'mysql' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Lookup table is escaped. Paid statuses are %s placeholders.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT customer_id,
					SUM( net_total ) AS revenue,
					COUNT(*) AS orders,
					MAX( date_created ) AS last_order,
					TIMESTAMPDIFF( DAY, MAX( date_created ), %s ) AS days_since
				 FROM {$stats}
				 WHERE customer_id > 0 AND parent_id = 0 AND status IN ({$status_in})
				 GROUP BY customer_id
				 HAVING days_since >= 60
				 ORDER BY revenue DESC
				 LIMIT 25",
				array_merge( array( $now ), $statuses )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$labels = GrowthPilot_AI_Engine::customer_labels( wp_list_pluck( $rows ? $rows : array(), 'customer_id' ) );
		$items  = array();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Lookup table is escaped. Paid statuses are %s placeholders.
		$total  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM (
					SELECT customer_id
					FROM {$stats}
					WHERE customer_id > 0 AND parent_id = 0 AND status IN ({$status_in})
					GROUP BY customer_id
					HAVING TIMESTAMPDIFF( DAY, MAX( date_created ), %s ) >= 60
				) quiet",
				array_merge( $statuses, array( $now ) )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		foreach ( $rows ? $rows : array() as $row ) {
			$id      = (int) $row->customer_id;
			$label   = isset( $labels[ $id ] ) ? $labels[ $id ] : array( 'name' => '#' . $id, 'email' => '' );
			$items[] = array(
				'customer_id' => $id,
				'name'        => $label['name'],
				'email'       => $label['email'],
				'revenue'     => round( (float) $row->revenue, 2 ),
				'orders'      => (int) $row->orders,
				'last_order'  => $row->last_order,
				'days_since'  => (int) $row->days_since,
				'action'      => (int) $row->days_since > 180 ? __( 'Win-back offer', 'gp-ppros' ) : __( 'Loyalty reminder', 'gp-ppros' ),
			);
		}

		return array(
			'count' => $total,
			'items' => $items,
		);
	}
}
