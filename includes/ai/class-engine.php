<?php
/**
 * Shared feature extraction and cache for on-store AI models.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables have no core API.

/**
 * AI engine helpers.
 */
class GrowthPilot_AI_Engine {

	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Flush computed snapshots.
	 *
	 * @return void
	 */
	public static function flush() {
		delete_transient( 'growthpilot_ai_predict' );
		delete_transient( 'growthpilot_ai_pricing' );
		delete_transient( 'growthpilot_ai_forecast' );
		delete_transient( 'growthpilot_ai_brain' );
		delete_transient( 'growthpilot_ai_brain_llm' );
	}

	/**
	 * Recompute every model (cron + refresh).
	 *
	 * @return array<string, mixed>
	 */
	public static function refresh_all() {
		self::flush();
		$predict  = GrowthPilot_AI_Predict::report( true );
		$pricing  = GrowthPilot_AI_Pricing::report( true );
		$forecast = GrowthPilot_AI_Forecast::report( true );
		$brain    = GrowthPilot_AI_Brain::report( true, false );
		update_option( 'growthpilot_ai_last_run', current_time( 'mysql' ), false );
		return compact( 'predict', 'pricing', 'forecast', 'brain' );
	}

	/**
	 * Cached payload helper.
	 *
	 * @param string   $key      Transient key.
	 * @param callable $builder  Builder.
	 * @param bool     $fresh    Skip cache.
	 * @return mixed
	 */
	public static function remember( $key, $builder, $fresh = false ) {
		if ( ! $fresh ) {
			$cached = get_transient( $key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$value = call_user_func( $builder );
		set_transient( $key, $value, self::CACHE_TTL );
		return $value;
	}

	/**
	 * Persist scored rows.
	 *
	 * @param string               $kind Kind.
	 * @param array<int, array>    $rows Rows with subject_type, subject_id, score, confidence, payload.
	 * @return void
	 */
	public static function persist( $kind, $rows ) {
		global $wpdb;

		$table = esc_sql( GrowthPilot::table( 'ai_predictions' ) );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $found !== $table ) {
			return;
		}

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE kind = %s", $kind ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$now = current_time( 'mysql' );
		foreach ( $rows as $row ) {
			$wpdb->insert(
				$table,
				array(
					'subject_type' => sanitize_key( $row['subject_type'] ),
					'subject_id'   => (int) $row['subject_id'],
					'kind'         => sanitize_key( $kind ),
					'score'        => (float) $row['score'],
					'confidence'   => (float) $row['confidence'],
					'payload'      => wp_json_encode( isset( $row['payload'] ) ? $row['payload'] : array() ),
					'generated_at' => $now,
				)
			);
		}
	}

	/**
	 * Customer order features from wc_order_stats.
	 *
	 * @return array{rows: array<int, object>, median_gap: float, revenue_cutoff: float}
	 */
	public static function customer_features() {
		global $wpdb;

		$stats     = esc_sql( GrowthPilot_Analytics_Query::stats_table() );
		$statuses  = GrowthPilot_Analytics_Query::paid_statuses();
		$status_in = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$now       = current_time( 'mysql' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Lookup table is escaped. Paid statuses are %s placeholders.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT customer_id,
					COUNT(*) AS orders,
					SUM(net_total) AS revenue,
					AVG(net_total) AS aov,
					MIN(date_created) AS first_order,
					MAX(date_created) AS last_order,
					TIMESTAMPDIFF(DAY, MIN(date_created), MAX(date_created)) AS span_days,
					TIMESTAMPDIFF(DAY, MAX(date_created), %s) AS days_since
				 FROM {$stats}
				 WHERE customer_id > 0 AND parent_id = 0 AND status IN ({$status_in})
				 GROUP BY customer_id
				 ORDER BY revenue DESC
				 LIMIT 250",
				array_merge( array( $now ), $statuses )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$rows = $rows ? $rows : array();
		$gaps = array();
		$revs = array();

		foreach ( $rows as $row ) {
			$revs[] = (float) $row->revenue;
			if ( (int) $row->orders >= 2 && (int) $row->span_days > 0 ) {
				$gaps[] = (float) $row->span_days / max( 1, (int) $row->orders - 1 );
			}
		}

		sort( $revs );
		$cutoff = 0.0;
		if ( $revs ) {
			$idx    = (int) floor( count( $revs ) * 0.8 );
			$cutoff = $revs[ min( $idx, count( $revs ) - 1 ) ];
		}

		return array(
			'rows'            => $rows,
			'median_gap'      => self::median( $gaps, 45 ),
			'revenue_cutoff'  => $cutoff,
			'max_revenue'     => $revs ? max( $revs ) : 0.0,
			'max_orders'      => $rows ? max( array_map( static function ( $row ) { return (int) $row->orders; }, $rows ) ) : 0,
		);
	}

	/**
	 * Last purchased product per customer.
	 *
	 * @param array<int, int> $customer_ids Lookup IDs.
	 * @return array<int, array{product_id:int, name:string}>
	 */
	public static function last_products( $customer_ids ) {
		global $wpdb;

		$customer_ids = array_values( array_filter( array_map( 'intval', $customer_ids ) ) );
		if ( ! $customer_ids ) {
			return array();
		}

		$table        = esc_sql( GrowthPilot_Analytics_Query::products_table() );
		$stats        = esc_sql( GrowthPilot_Analytics_Query::stats_table() );
		$statuses     = GrowthPilot_Analytics_Query::paid_statuses();
		$status_in    = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$placeholders = implode( ',', array_fill( 0, count( $customer_ids ), '%d' ) );

		$sql = "SELECT p.customer_id, p.product_id, p.date_created
			FROM {$table} p
			INNER JOIN {$stats} s ON s.order_id = p.order_id
			WHERE p.customer_id IN ({$placeholders}) AND s.status IN ({$status_in})
			ORDER BY p.date_created DESC";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Lookup tables are escaped. IN lists are generated placeholders.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $customer_ids, $statuses ) ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$out  = array();

		foreach ( $rows ? $rows : array() as $row ) {
			$cid = (int) $row->customer_id;
			if ( isset( $out[ $cid ] ) ) {
				continue;
			}
			$product = wc_get_product( (int) $row->product_id );
			$out[ $cid ] = array(
				'product_id' => (int) $row->product_id,
				'name'       => $product ? $product->get_name() : ( '#' . $row->product_id ),
			);
		}

		return $out;
	}

	/**
	 * Product sales last 365 days.
	 *
	 * @return array<int, object>
	 */
	public static function product_sales() {
		global $wpdb;

		$table     = esc_sql( GrowthPilot_Analytics_Query::products_table() );
		$stats     = esc_sql( GrowthPilot_Analytics_Query::stats_table() );
		$statuses  = GrowthPilot_Analytics_Query::paid_statuses();
		$status_in = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$from      = gmdate( 'Y-m-d 00:00:00', time() - ( 365 * DAY_IN_SECONDS ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Lookup tables are escaped. Paid statuses are %s placeholders.
		$sales = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.product_id,
					SUM(p.product_qty) AS units,
					SUM(p.product_net_revenue) AS revenue,
					SUM(p.coupon_amount) AS discounts,
					COUNT(DISTINCT p.order_id) AS orders,
					COUNT(DISTINCT p.customer_id) AS buyers
				 FROM {$table} p
				 INNER JOIN {$stats} s ON s.order_id = p.order_id
				 WHERE p.date_created >= %s AND s.status IN ({$status_in})
				 GROUP BY p.product_id
				 ORDER BY units DESC
				 LIMIT 120",
				array_merge( array( $from ), $statuses )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		return $sales;
	}

	/**
	 * Monthly units by product (up to 18 months).
	 *
	 * @return array<int, array<string, float>>
	 */
	public static function product_monthly() {
		global $wpdb;

		$table     = esc_sql( GrowthPilot_Analytics_Query::products_table() );
		$stats     = esc_sql( GrowthPilot_Analytics_Query::stats_table() );
		$statuses  = GrowthPilot_Analytics_Query::paid_statuses();
		$status_in = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$from      = gmdate( 'Y-m-01 00:00:00', strtotime( '-17 months' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Lookup tables are escaped. Paid statuses are %s placeholders.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.product_id, DATE_FORMAT(p.date_created, '%%Y-%%m') AS ym, SUM(p.product_qty) AS units
				 FROM {$table} p
				 INNER JOIN {$stats} s ON s.order_id = p.order_id
				 WHERE p.date_created >= %s AND s.status IN ({$status_in})
				 GROUP BY p.product_id, ym",
				array_merge( array( $from ), $statuses )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$out = array();
		foreach ( $rows ? $rows : array() as $row ) {
			$pid = (int) $row->product_id;
			if ( ! isset( $out[ $pid ] ) ) {
				$out[ $pid ] = array();
			}
			$out[ $pid ][ $row->ym ] = (float) $row->units;
		}

		return $out;
	}

	/**
	 * Store monthly net + units.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function store_monthly() {
		global $wpdb;

		$stats     = esc_sql( GrowthPilot_Analytics_Query::stats_table() );
		$statuses  = GrowthPilot_Analytics_Query::paid_statuses();
		$status_in = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$from      = gmdate( 'Y-m-01 00:00:00', strtotime( '-17 months' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Lookup table is escaped. Paid statuses are %s placeholders.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(date_created, '%%Y-%%m') AS ym,
					COALESCE(SUM(net_total), 0) AS net,
					COALESCE(SUM(CASE WHEN parent_id = 0 THEN num_items_sold ELSE 0 END), 0) AS units,
					COALESCE(SUM(CASE WHEN parent_id = 0 THEN 1 ELSE 0 END), 0) AS orders
				 FROM {$stats}
				 WHERE date_created >= %s AND ( status IN ({$status_in}) OR parent_id > 0 )
				 GROUP BY ym
				 ORDER BY ym ASC",
				array_merge( array( $from ), $statuses )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$out = array();
		foreach ( $rows ? $rows : array() as $row ) {
			$out[] = array(
				'month'  => $row->ym,
				'net'    => round( (float) $row->net, 2 ),
				'units'  => (int) $row->units,
				'orders' => (int) $row->orders,
			);
		}

		return $out;
	}

	/**
	 * Customer labels keyed by lookup id.
	 *
	 * @param array<int, int> $ids IDs.
	 * @return array<int, array{name:string,email:string}>
	 */
	public static function customer_labels( $ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return array();
		}

		$table        = esc_sql( GrowthPilot_Analytics_Query::customers_table() );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Lookup table and generated %d list.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT customer_id, first_name, last_name, email, user_id FROM {$table} WHERE customer_id IN ({$placeholders})",
				$ids
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$out = array();
		foreach ( $rows ? $rows : array() as $row ) {
			$name = trim( $row->first_name . ' ' . $row->last_name );
			if ( '' === $name && $row->user_id ) {
				$user = get_userdata( (int) $row->user_id );
				$name = $user ? $user->display_name : $row->email;
			}
			$out[ (int) $row->customer_id ] = array(
				'name'  => $name ? $name : ( $row->email ? $row->email : ( '#' . $row->customer_id ) ),
				'email' => (string) $row->email,
			);
		}

		return $out;
	}

	/**
	 * Unit cost from common COGS meta.
	 *
	 * @param WC_Product $product Product.
	 * @return float|null
	 */
	public static function unit_cost( $product ) {
		foreach ( array( '_cogs', '_wc_cog_cost', '_alg_wc_cog_cost', '_cog_cost' ) as $key ) {
			$value = $product->get_meta( $key );
			if ( '' !== $value && false !== $value && is_numeric( $value ) ) {
				return (float) $value;
			}
		}
		return null;
	}

	/**
	 * Logistic squash.
	 *
	 * @param float $x Input.
	 * @return float
	 */
	public static function logistic( $x ) {
		if ( $x < -20 ) {
			return 0.0;
		}
		if ( $x > 20 ) {
			return 1.0;
		}
		return 1 / ( 1 + exp( -$x ) );
	}

	/**
	 * Median with fallback.
	 *
	 * @param array<int, float> $values Values.
	 * @param float             $fallback Fallback.
	 * @return float
	 */
	public static function median( $values, $fallback = 0.0 ) {
		if ( ! $values ) {
			return (float) $fallback;
		}
		sort( $values );
		$mid = (int) floor( count( $values ) / 2 );
		return (float) $values[ $mid ];
	}

	/**
	 * Clamp.
	 *
	 * @param float $value Value.
	 * @param float $min   Min.
	 * @param float $max   Max.
	 * @return float
	 */
	public static function clamp( $value, $min, $max ) {
		return max( $min, min( $max, $value ) );
	}

	/**
	 * Whether local models are enabled.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) GrowthPilot_Settings::get_value( 'ai_enabled', true );
	}
}
