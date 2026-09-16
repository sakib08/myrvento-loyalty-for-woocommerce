<?php
/**
 * Revenue, AOV, LTV, repeat purchase, trends.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Revenue intelligence.
 */
class GrowthPilot_Analytics_Revenue {

	/**
	 * Overview KPIs + trend.
	 *
	 * @param array $range Range from Query::range().
	 * @return array<string, mixed>
	 */
	public static function overview( $range ) {
		$current  = self::totals( $range['from_sql'], $range['to_sql'] );
		$previous = self::totals(
			GrowthPilot_Analytics_Query::previous_range( $range )['from_sql'],
			GrowthPilot_Analytics_Query::previous_range( $range )['to_sql']
		);

		$kpis = array();
		$map  = array(
			'gross_revenue'       => array( 'Gross revenue', $current['gross'], $previous['gross'] ),
			'net_revenue'         => array( 'Net revenue', $current['net'], $previous['net'] ),
			'orders'              => array( 'Orders', $current['orders'], $previous['orders'] ),
			'aov'                 => array( 'Average order value', $current['aov'], $previous['aov'] ),
			'items_per_order'     => array( 'Items per order', $current['items_per_order'], $previous['items_per_order'] ),
			'refunds'             => array( 'Refunds', $current['refunds'], $previous['refunds'] ),
			'discounts'           => array( 'Discounts', $current['discounts'], $previous['discounts'] ),
			'taxes'               => array( 'Taxes', $current['tax'], $previous['tax'] ),
			'shipping'            => array( 'Shipping revenue', $current['shipping'], $previous['shipping'] ),
			'ltv'                 => array( 'Average customer LTV', $current['ltv'], $previous['ltv'] ),
			'repeat_rate'         => array( 'Repeat purchase rate', $current['repeat_rate'], $previous['repeat_rate'] ),
			'first_time_customers'=> array( 'First-time customers', $current['first_time'], $previous['first_time'] ),
			'returning_customers' => array( 'Returning customers', $current['returning'], $previous['returning'] ),
		);

		foreach ( $map as $key => $row ) {
			$kpis[ $key ] = array(
				'label'    => $row[0],
				'value'    => $row[1],
				'previous' => $row[2],
				'delta'    => GrowthPilot_Analytics_Query::delta( (float) $row[1], (float) $row[2] ),
			);
		}

		$interval = GrowthPilot_Analytics_Query::interval_for_days( $range['days'] );

		return array(
			'range'    => array( 'from' => $range['from'], 'to' => $range['to'] ),
			'kpis'     => $kpis,
			'trend'    => self::trend( $range['from_sql'], $range['to_sql'], $interval ),
			'interval' => $interval,
		);
	}

	/**
	 * Totals for a window.
	 *
	 * @param string $from From SQL datetime.
	 * @param string $to   To SQL datetime.
	 * @return array<string, float|int>
	 */
	public static function totals( $from, $to ) {
		global $wpdb;

		$stats = GrowthPilot_Analytics_Query::stats_table();
		$paid  = GrowthPilot_Analytics_Query::paid_in();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COALESCE(SUM(CASE WHEN parent_id = 0 THEN total_sales ELSE 0 END), 0) AS gross,
					COALESCE(SUM(net_total), 0) AS net,
					COALESCE(SUM(tax_total), 0) AS tax,
					COALESCE(SUM(shipping_total), 0) AS shipping,
					COALESCE(SUM(CASE WHEN parent_id = 0 THEN 1 ELSE 0 END), 0) AS orders,
					COALESCE(SUM(CASE WHEN parent_id = 0 THEN num_items_sold ELSE 0 END), 0) AS items,
					COALESCE(SUM(CASE WHEN parent_id > 0 OR status = 'wc-refunded' THEN 1 ELSE 0 END), 0) AS refunds,
					COALESCE(SUM(CASE WHEN returning_customer = 0 AND parent_id = 0 THEN 1 ELSE 0 END), 0) AS first_time,
					COALESCE(SUM(CASE WHEN returning_customer = 1 AND parent_id = 0 THEN 1 ELSE 0 END), 0) AS returning
				FROM {$stats}
				WHERE date_created BETWEEN %s AND %s
					AND ( status IN ({$paid}) OR parent_id > 0 )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$from,
				$to
			)
		);

		$orders = $row ? (int) $row->orders : 0;
		$net    = $row ? (float) $row->net : 0.0;
		$items  = $row ? (int) $row->items : 0;

		$discounts = (float) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(discount_amount), 0) FROM ' . GrowthPilot_Analytics_Query::coupons_table() . ' WHERE date_created BETWEEN %s AND %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$from,
				$to
			)
		);

		$ltv = (float) $wpdb->get_var(
			"SELECT COALESCE(AVG(lifetime), 0) FROM (
				SELECT customer_id, SUM(net_total) AS lifetime
				FROM {$stats}
				WHERE customer_id > 0 AND status IN ({$paid})
				GROUP BY customer_id
			) t" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$repeat = $wpdb->get_row(
			"SELECT
				COUNT(*) AS customers,
				SUM(CASE WHEN cnt >= 2 THEN 1 ELSE 0 END) AS repeats
			 FROM (
				SELECT customer_id, COUNT(*) AS cnt
				FROM {$stats}
				WHERE customer_id > 0 AND parent_id = 0 AND status IN ({$paid})
				GROUP BY customer_id
			 ) t" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$customers = $repeat ? (int) $repeat->customers : 0;
		$repeats   = $repeat ? (int) $repeat->repeats : 0;

		return array(
			'gross'           => $row ? (float) $row->gross : 0.0,
			'net'             => $net,
			'tax'             => $row ? (float) $row->tax : 0.0,
			'shipping'        => $row ? (float) $row->shipping : 0.0,
			'orders'          => $orders,
			'items'           => $items,
			'refunds'         => $row ? (int) $row->refunds : 0,
			'first_time'      => $row ? (int) $row->first_time : 0,
			'returning'       => $row ? (int) $row->returning : 0,
			'discounts'       => $discounts,
			'aov'             => $orders ? round( $net / $orders, 2 ) : 0.0,
			'items_per_order' => $orders ? round( $items / $orders, 2 ) : 0.0,
			'ltv'             => round( $ltv, 2 ),
			'repeat_rate'     => $customers ? round( ( $repeats / $customers ) * 100, 1 ) : 0.0,
		);
	}

	/**
	 * Revenue trend buckets.
	 *
	 * @param string $from     From.
	 * @param string $to       To.
	 * @param string $interval Interval.
	 * @return array<int, array<string, mixed>>
	 */
	public static function trend( $from, $to, $interval ) {
		global $wpdb;

		$stats  = GrowthPilot_Analytics_Query::stats_table();
		$paid   = GrowthPilot_Analytics_Query::paid_in();
		$bucket = GrowthPilot_Analytics_Query::bucket_sql( $interval, 'date_created' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$bucket} AS bucket,
					COALESCE(SUM(net_total), 0) AS net,
					COALESCE(SUM(CASE WHEN parent_id = 0 THEN total_sales ELSE 0 END), 0) AS gross,
					COALESCE(SUM(CASE WHEN parent_id = 0 THEN 1 ELSE 0 END), 0) AS orders
				FROM {$stats}
				WHERE date_created BETWEEN %s AND %s
					AND ( status IN ({$paid}) OR parent_id > 0 )
				GROUP BY bucket
				ORDER BY bucket ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$from,
				$to
			)
		);

		$out = array();
		foreach ( $rows ? $rows : array() as $row ) {
			$out[] = array(
				'date'   => $row->bucket,
				'net'    => (float) $row->net,
				'gross'  => (float) $row->gross,
				'orders' => (int) $row->orders,
			);
		}

		return $out;
	}
}
