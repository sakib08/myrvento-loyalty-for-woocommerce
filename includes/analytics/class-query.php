<?php
/**
 * Shared analytics query helpers (HPOS lookup tables).
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Date range and WooCommerce stats access.
 */
class GrowthPilot_Analytics_Query {

	/**
	 * Normalize a from/to range.
	 *
	 * @param string $from From date Y-m-d.
	 * @param string $to   To date Y-m-d.
	 * @return array{from: string, to: string, from_sql: string, to_sql: string, days: int}
	 */
	public static function range( $from = '', $to = '' ) {
		$to_ts = strtotime( $to ? $to : 'today' );
		if ( ! $to_ts ) {
			$to_ts = time();
		}

		$from_ts = strtotime( $from ? $from : '-29 days' );
		if ( ! $from_ts ) {
			$from_ts = $to_ts - ( 29 * DAY_IN_SECONDS );
		}

		if ( $from_ts > $to_ts ) {
			$tmp     = $from_ts;
			$from_ts = $to_ts;
			$to_ts   = $tmp;
		}

		$from_sql = gmdate( 'Y-m-d 00:00:00', $from_ts );
		$to_sql   = gmdate( 'Y-m-d 23:59:59', $to_ts );

		return array(
			'from'     => gmdate( 'Y-m-d', $from_ts ),
			'to'       => gmdate( 'Y-m-d', $to_ts ),
			'from_sql' => $from_sql,
			'to_sql'   => $to_sql,
			'days'     => max( 1, (int) ceil( ( $to_ts - $from_ts ) / DAY_IN_SECONDS ) + 1 ),
		);
	}

	/**
	 * Previous period of the same length (for trend deltas).
	 *
	 * @param array $range Range from self::range().
	 * @return array{from_sql: string, to_sql: string}
	 */
	public static function previous_range( $range ) {
		$days    = (int) $range['days'];
		$from_ts = strtotime( $range['from_sql'] ) - ( $days * DAY_IN_SECONDS );
		$to_ts   = strtotime( $range['from_sql'] ) - 1;

		return array(
			'from_sql' => gmdate( 'Y-m-d 00:00:00', $from_ts ),
			'to_sql'   => gmdate( 'Y-m-d 23:59:59', $to_ts ),
		);
	}

	/**
	 * Paid order statuses as stored in wc_order_stats (wc- prefix).
	 *
	 * @return array<int, string>
	 */
	public static function paid_statuses() {
		$paid = function_exists( 'wc_get_is_paid_statuses' ) ? wc_get_is_paid_statuses() : array( 'processing', 'completed' );
		$out  = array();
		foreach ( $paid as $status ) {
			$out[] = 0 === strpos( $status, 'wc-' ) ? $status : 'wc-' . $status;
		}
		return apply_filters( 'growthpilot_analytics_paid_statuses', $out );
	}

	/**
	 * SQL IN list of paid statuses (already quoted).
	 *
	 * @return string
	 */
	public static function paid_in() {
		return "'" . implode( "','", array_map( 'esc_sql', self::paid_statuses() ) ) . "'";
	}

	/**
	 * wc_order_stats table.
	 *
	 * @return string
	 */
	public static function stats_table() {
		global $wpdb;
		return $wpdb->prefix . 'wc_order_stats';
	}

	/**
	 * wc_order_product_lookup table.
	 *
	 * @return string
	 */
	public static function products_table() {
		global $wpdb;
		return $wpdb->prefix . 'wc_order_product_lookup';
	}

	/**
	 * wc_customer_lookup table.
	 *
	 * @return string
	 */
	public static function customers_table() {
		global $wpdb;
		return $wpdb->prefix . 'wc_customer_lookup';
	}

	/**
	 * Coupon lookup table.
	 *
	 * @return string
	 */
	public static function coupons_table() {
		global $wpdb;
		return $wpdb->prefix . 'wc_order_coupon_lookup';
	}

	/**
	 * Bucket expression for a trend interval.
	 *
	 * @param string $interval day|week|month.
	 * @param string $column   Datetime column.
	 * @return string
	 */
	public static function bucket_sql( $interval, $column = 'date_created' ) {
		if ( 'week' === $interval ) {
			return "DATE( DATE_SUB( {$column}, INTERVAL WEEKDAY({$column}) DAY ) )";
		}
		if ( 'month' === $interval ) {
			return "DATE_FORMAT( {$column}, '%Y-%m-01' )";
		}
		return "DATE( {$column} )";
	}

	/**
	 * Choose interval from range length.
	 *
	 * @param int $days Days.
	 * @return string
	 */
	public static function interval_for_days( $days ) {
		if ( $days <= 90 ) {
			return 'day';
		}
		if ( $days <= 400 ) {
			return 'week';
		}
		return 'month';
	}

	/**
	 * Delta percent between current and previous.
	 *
	 * @param float $current  Current.
	 * @param float $previous Previous.
	 * @return float|null
	 */
	public static function delta( $current, $previous ) {
		if ( abs( $previous ) < 0.00001 ) {
			return ( abs( $current ) < 0.00001 ) ? 0.0 : null;
		}
		return round( ( ( $current - $previous ) / abs( $previous ) ) * 100, 1 );
	}
}
