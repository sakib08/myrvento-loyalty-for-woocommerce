<?php
/**
 * Product sales, underperformers, profitability.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables have no core API.

/**
 * Product intelligence.
 */
class GrowthPilot_Analytics_Products {

	/**
	 * Product report.
	 *
	 * @param array $range Range.
	 * @return array<string, mixed>
	 */
	public static function report( $range ) {
		$sold = self::sold( $range['from_sql'], $range['to_sql'] );

		usort(
			$sold,
			static function ( $a, $b ) {
				return $b['revenue'] <=> $a['revenue'];
			}
		);

		$best    = array_slice( $sold, 0, 10 );
		$worst   = array_slice( array_reverse( $sold ), 0, 10 );
		$unsold  = self::unsold( wp_list_pluck( $sold, 'product_id' ) );

		return array(
			'range'           => array( 'from' => $range['from'], 'to' => $range['to'] ),
			'best_selling'    => $best,
			'underperforming' => array_slice( array_merge( $unsold, $worst ), 0, 10 ),
			'profitability'   => array_slice( $sold, 0, 15 ),
		);
	}

	/**
	 * Sold products in a window.
	 *
	 * @param string $from From.
	 * @param string $to   To.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sold( $from, $to ) {
		global $wpdb;

		$table = esc_sql( GrowthPilot_Analytics_Query::products_table() );
		$stats = esc_sql( GrowthPilot_Analytics_Query::stats_table() );
		$paid  = "'" . implode( "','", array_map( 'esc_sql', GrowthPilot_Analytics_Query::paid_statuses() ) ) . "'";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Lookup tables and paid-status list are trusted.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.product_id,
					SUM(p.product_qty) AS units,
					SUM(p.product_net_revenue) AS revenue,
					SUM(p.product_gross_revenue) AS gross,
					COUNT(DISTINCT p.order_id) AS orders,
					COUNT(DISTINCT p.customer_id) AS buyers
				FROM {$table} p
				INNER JOIN {$stats} s ON s.order_id = p.order_id
				WHERE p.date_created BETWEEN %s AND %s
					AND s.status IN ({$paid})
				GROUP BY p.product_id
				ORDER BY revenue DESC
				LIMIT 100",
				$from,
				$to
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$out = array();
		foreach ( $rows ? $rows : array() as $row ) {
			$product = wc_get_product( (int) $row->product_id );
			if ( ! $product ) {
				continue;
			}

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Lookup table name is trusted.
			$repeat = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM (
						SELECT customer_id FROM {$table} WHERE product_id = %d AND customer_id > 0 GROUP BY customer_id HAVING COUNT(DISTINCT order_id) > 1
					) t",
					(int) $row->product_id
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$cost    = self::unit_cost( $product );
			$units   = (int) $row->units;
			$revenue = (float) $row->revenue;
			$cogs    = ( null === $cost ) ? null : $cost * $units;
			$profit  = ( null === $cogs ) ? null : $revenue - $cogs;
			$margin  = ( null === $profit || $revenue <= 0 ) ? null : round( ( $profit / $revenue ) * 100, 1 );

			$out[] = array(
				'product_id'   => (int) $row->product_id,
				'name'         => $product->get_name(),
				'units'        => $units,
				'revenue'      => round( $revenue, 2 ),
				'gross'        => round( (float) $row->gross, 2 ),
				'orders'       => (int) $row->orders,
				'buyers'       => (int) $row->buyers,
				'repeat_buyers'=> $repeat,
				'cost'         => $cogs,
				'profit'       => null === $profit ? null : round( $profit, 2 ),
				'margin'       => $margin,
			);
		}

		return $out;
	}

	/**
	 * Published products with no sales in the sold set.
	 *
	 * @param array<int, int> $sold_ids Sold IDs.
	 * @return array<int, array<string, mixed>>
	 */
	private static function unsold( $sold_ids ) {
		$products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 20,
				'return' => 'objects',
				'orderby'=> 'date',
			)
		);

		$sold = array_map( 'intval', $sold_ids );
		$out  = array();

		foreach ( $products as $product ) {
			if ( in_array( $product->get_id(), $sold, true ) ) {
				continue;
			}
			$out[] = array(
				'product_id'    => $product->get_id(),
				'name'          => $product->get_name(),
				'units'         => 0,
				'revenue'       => 0,
				'orders'        => 0,
				'buyers'        => 0,
				'repeat_buyers' => 0,
				'cost'          => null,
				'profit'        => null,
				'margin'        => null,
			);
			if ( count( $out ) >= 10 ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * Unit cost from common cost-of-goods meta keys.
	 *
	 * @param WC_Product $product Product.
	 * @return float|null
	 */
	private static function unit_cost( $product ) {
		foreach ( array( '_cogs', '_wc_cog_cost', '_alg_wc_cog_cost', '_cog_cost' ) as $key ) {
			$value = $product->get_meta( $key );
			if ( '' !== $value && false !== $value && is_numeric( $value ) ) {
				return (float) $value;
			}
		}
		return null;
	}
}
