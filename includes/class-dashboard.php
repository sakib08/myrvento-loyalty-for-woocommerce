<?php
/**
 * Home dashboard: revenue, sales, orders, and forecast.
 *
 * @package Ciwp
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables have no core API.

/**
 * Dashboard summary.
 */
class Ciwp_Dashboard {

	/**
	 * Dashboard payload for the last 30 days.
	 *
	 * @return array<string, mixed>
	 */
	public static function report() {
		$range    = Ciwp_Analytics_Query::range( gmdate( 'Y-m-d', time() - ( 29 * DAY_IN_SECONDS ) ), gmdate( 'Y-m-d' ) );
		$overview = Ciwp_Analytics_Revenue::overview( $range );
		$churn    = Ciwp_Analytics_Customers::churn();
		$forecast = Ciwp_AI_Engine::enabled() ? Ciwp_AI_Forecast::report() : array();
		$store    = isset( $forecast['store'] ) ? $forecast['store'] : array();

		return array(
			'range'    => $overview['range'],
			'kpis'     => $overview['kpis'],
			'trend'    => $overview['trend'],
			'sales'    => array(
				'abandoned' => Ciwp_Sales::abandoned()['count'],
				'recovery'  => Ciwp_Sales::recovery()['count'],
			),
			'orders'   => Ciwp_Operations::orders(),
			'churn'    => array(
				'active'     => $churn['active'],
				'at_risk'    => $churn['at_risk'],
				'churned'    => $churn['churned'],
				'churn_rate' => $churn['churn_rate'],
			),
			'forecast' => array(
				'units_30'   => isset( $store['forecast_30_units'] ) ? $store['forecast_30_units'] : 0,
				'revenue_30' => isset( $store['forecast_30_revenue'] ) ? $store['forecast_30_revenue'] : 0,
			),
			'top'      => self::top_products( $range ),
		);
	}

	/**
	 * Five products by net revenue in the range.
	 *
	 * @param array $range Range.
	 * @return array<int, array<string, mixed>>
	 */
	private static function top_products( $range ) {
		global $wpdb;

		$table     = esc_sql( Ciwp_Analytics_Query::products_table() );
		$stats     = esc_sql( Ciwp_Analytics_Query::stats_table() );
		$statuses  = Ciwp_Analytics_Query::paid_statuses();
		$status_in = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Lookup tables are escaped. Paid statuses are %s placeholders.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.product_id, SUM( p.product_qty ) AS units, SUM( p.product_net_revenue ) AS revenue
				 FROM {$table} p
				 INNER JOIN {$stats} s ON s.order_id = p.order_id
				 WHERE p.date_created BETWEEN %s AND %s AND s.status IN ({$status_in})
				 GROUP BY p.product_id
				 ORDER BY revenue DESC
				 LIMIT 5",
				array_merge( array( $range['from_sql'], $range['to_sql'] ), $statuses )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$out = array();
		foreach ( $rows ? $rows : array() as $row ) {
			$product = wc_get_product( (int) $row->product_id );
			$out[]   = array(
				'name'    => $product ? $product->get_name() : ( '#' . $row->product_id ),
				'units'   => (float) $row->units,
				'revenue' => round( (float) $row->revenue, 2 ),
			);
		}

		return $out;
	}
}
