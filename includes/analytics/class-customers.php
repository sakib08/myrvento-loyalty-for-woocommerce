<?php
/**
 * Cohorts, retention, churn, segment performance.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables have no core API.

/**
 * Customer intelligence.
 */
class GrowthPilot_Analytics_Customers {

	/**
	 * Customer analytics payload.
	 *
	 * @param array $range Range.
	 * @return array<string, mixed>
	 */
	public static function report( $range ) {
		return array(
			'range'      => array( 'from' => $range['from'], 'to' => $range['to'] ),
			'cohorts'    => self::cohorts(),
			'retention'  => self::retention(),
			'churn'      => self::churn(),
			'segments'   => self::segments(),
		);
	}

	/**
	 * Acquisition cohorts: revenue and repeat by first-order month.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function cohorts() {
		global $wpdb;

		$stats = esc_sql( GrowthPilot_Analytics_Query::stats_table() );
		$paid  = "'" . implode( "','", array_map( 'esc_sql', GrowthPilot_Analytics_Query::paid_statuses() ) ) . "'";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Lookup table and paid-status list are trusted.
		$first = $wpdb->get_results(
			"SELECT customer_id, MIN(date_created) AS first_order, DATE_FORMAT(MIN(date_created), '%Y-%m') AS cohort
			 FROM {$stats}
			 WHERE customer_id > 0 AND parent_id = 0 AND status IN ({$paid})
			 GROUP BY customer_id"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $first ) {
			return array();
		}

		$by_customer = array();
		foreach ( $first as $row ) {
			$by_customer[ (int) $row->customer_id ] = $row;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Lookup table and paid-status list are trusted.
		$orders = $wpdb->get_results(
			"SELECT customer_id, date_created, net_total
			 FROM {$stats}
			 WHERE customer_id > 0 AND parent_id = 0 AND status IN ({$paid})"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$cohorts = array();
		foreach ( $orders ? $orders : array() as $order ) {
			$cid = (int) $order->customer_id;
			if ( ! isset( $by_customer[ $cid ] ) ) {
				continue;
			}
			$cohort = $by_customer[ $cid ]->cohort;
			$first_t = strtotime( $by_customer[ $cid ]->first_order );
			$order_t = strtotime( $order->date_created );
			$month   = (int) floor( max( 0, $order_t - $first_t ) / ( 30 * DAY_IN_SECONDS ) );
			if ( $month > 5 ) {
				continue;
			}

			if ( ! isset( $cohorts[ $cohort ] ) ) {
				$cohorts[ $cohort ] = array(
					'cohort'    => $cohort,
					'customers' => array(),
					'revenue'   => 0.0,
					'months'    => array_fill( 0, 6, array( 'customers' => array(), 'revenue' => 0.0 ) ),
				);
			}

			$cohorts[ $cohort ]['customers'][ $cid ] = true;
			$cohorts[ $cohort ]['revenue']          += (float) $order->net_total;
			$cohorts[ $cohort ]['months'][ $month ]['customers'][ $cid ] = true;
			$cohorts[ $cohort ]['months'][ $month ]['revenue']          += (float) $order->net_total;
		}

		krsort( $cohorts );
		$out = array();
		$i   = 0;
		foreach ( $cohorts as $row ) {
			if ( $i++ >= 8 ) {
				break;
			}
			$size    = count( $row['customers'] );
			$months  = array();
			foreach ( $row['months'] as $offset => $cell ) {
				$active    = count( $cell['customers'] );
				$months[]  = array(
					'offset'     => $offset,
					'customers'  => $active,
					'revenue'    => round( $cell['revenue'], 2 ),
					'retention'  => $size ? round( ( $active / $size ) * 100, 1 ) : 0,
				);
			}

			$out[] = array(
				'cohort'     => $row['cohort'],
				'customers'  => $size,
				'revenue'    => round( $row['revenue'], 2 ),
				'value'      => $size ? round( $row['revenue'] / $size, 2 ) : 0,
				'months'     => $months,
			);
		}

		return $out;
	}

	/**
	 * Retention metrics.
	 *
	 * @return array<string, mixed>
	 */
	public static function retention() {
		global $wpdb;

		$stats = esc_sql( GrowthPilot_Analytics_Query::stats_table() );
		$paid  = "'" . implode( "','", array_map( 'esc_sql', GrowthPilot_Analytics_Query::paid_statuses() ) ) . "'";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Lookup table and paid-status list are trusted.
		$rows = $wpdb->get_results(
			"SELECT customer_id, COUNT(*) AS orders, MIN(date_created) AS first_order, MAX(date_created) AS last_order,
				TIMESTAMPDIFF(DAY, MIN(date_created), MAX(date_created)) AS span_days
			 FROM {$stats}
			 WHERE customer_id > 0 AND parent_id = 0 AND status IN ({$paid})
			 GROUP BY customer_id"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$total = count( $rows ? $rows : array() );
		$second = 0;
		$third  = 0;
		$gaps   = array();

		foreach ( $rows ? $rows : array() as $row ) {
			if ( (int) $row->orders >= 2 ) {
				++$second;
				if ( (int) $row->orders >= 2 && (int) $row->span_days > 0 ) {
					$gaps[] = (int) $row->span_days / max( 1, (int) $row->orders - 1 );
				}
			}
			if ( (int) $row->orders >= 3 ) {
				++$third;
			}
		}

		sort( $gaps );
		$median = 0;
		if ( $gaps ) {
			$mid    = (int) floor( count( $gaps ) / 2 );
			$median = $gaps[ $mid ];
		}

		return array(
			'customers'           => $total,
			'second_purchase_rate'=> $total ? round( ( $second / $total ) * 100, 1 ) : 0,
			'third_purchase_rate' => $total ? round( ( $third / $total ) * 100, 1 ) : 0,
			'repeat_purchase_rate'=> $total ? round( ( $second / $total ) * 100, 1 ) : 0,
			'median_days_between' => round( (float) $median, 1 ),
		);
	}

	/**
	 * Churn / at-risk.
	 *
	 * @return array<string, mixed>
	 */
	public static function churn() {
		global $wpdb;

		$stats = esc_sql( GrowthPilot_Analytics_Query::stats_table() );
		$paid  = "'" . implode( "','", array_map( 'esc_sql', GrowthPilot_Analytics_Query::paid_statuses() ) ) . "'";
		$now   = current_time( 'mysql' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Lookup table and paid-status list are trusted.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT customer_id, MAX(date_created) AS last_order, TIMESTAMPDIFF(DAY, MAX(date_created), %s) AS days_since
				 FROM {$stats}
				 WHERE customer_id > 0 AND parent_id = 0 AND status IN ({$paid})
				 GROUP BY customer_id",
				$now
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$active  = 0;
		$risk    = 0;
		$churned = 0;
		$winback = array();

		foreach ( $rows ? $rows : array() as $row ) {
			$days = (int) $row->days_since;
			if ( $days <= 60 ) {
				++$active;
			} elseif ( $days <= 180 ) {
				++$risk;
				if ( count( $winback ) < 8 ) {
					$user     = self::customer_label( (int) $row->customer_id );
					$winback[] = array(
						'customer_id' => (int) $row->customer_id,
						'name'        => $user['name'],
						'email'       => $user['email'],
						'last_order'  => $row->last_order,
						'days_since'  => $days,
						'reason'      => __( 'No purchase in 60+ days', 'gp-ppros' ),
					);
				}
			} else {
				++$churned;
			}
		}

		$total = $active + $risk + $churned;

		return array(
			'active'       => $active,
			'at_risk'      => $risk,
			'churned'      => $churned,
			'churn_rate'   => $total ? round( ( $churned / $total ) * 100, 1 ) : 0,
			'winback'      => $winback,
		);
	}

	/**
	 * Segment performance.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function segments() {
		global $wpdb;

		$stats = esc_sql( GrowthPilot_Analytics_Query::stats_table() );
		$paid  = "'" . implode( "','", array_map( 'esc_sql', GrowthPilot_Analytics_Query::paid_statuses() ) ) . "'";

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Lookup table and paid-status list are trusted.
		$customers = $wpdb->get_results(
			"SELECT customer_id,
				COUNT(*) AS orders,
				SUM(net_total) AS revenue,
				MAX(date_created) AS last_order,
				TIMESTAMPDIFF(DAY, MAX(date_created), UTC_TIMESTAMP()) AS days_since
			 FROM {$stats}
			 WHERE customer_id > 0 AND parent_id = 0 AND status IN ({$paid})
			 GROUP BY customer_id"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$coupons_table = esc_sql( GrowthPilot_Analytics_Query::coupons_table() );
		$stats_join    = esc_sql( GrowthPilot_Analytics_Query::stats_table() );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Lookup table names are trusted.
		$coupon_users  = $wpdb->get_col(
			"SELECT DISTINCT s.customer_id FROM {$coupons_table} c
			 INNER JOIN {$stats_join} s ON s.order_id = c.order_id
			 WHERE s.customer_id > 0"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$coupon_set   = array_flip( array_map( 'intval', $coupon_users ? $coupon_users : array() ) );

		$referred = $wpdb->get_col( 'SELECT DISTINCT referee_id FROM ' . esc_sql( GrowthPilot::table( 'referrals' ) ) . ' WHERE referee_id IS NOT NULL' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$ref_set  = array_flip( array_map( 'intval', $referred ? $referred : array() ) );

		$vip_ids = $wpdb->get_col( 'SELECT customer_id FROM ' . esc_sql( GrowthPilot::table( 'points_balances' ) ) . ' WHERE tier_id IS NOT NULL' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$vip_set = array_flip( array_map( 'intval', $vip_ids ? $vip_ids : array() ) );

		$revenues = array();
		foreach ( $customers ? $customers : array() as $row ) {
			$revenues[] = (float) $row->revenue;
		}
		rsort( $revenues );
		$cutoff = $revenues ? $revenues[ (int) floor( count( $revenues ) * 0.2 ) ] : 0;

		$buckets = array(
			'vip'          => array( 'VIP customers', 0, 0.0, 0 ),
			'high'         => array( 'High spenders', 0, 0.0, 0 ),
			'frequent'     => array( 'Frequent buyers', 0, 0.0, 0 ),
			'one_time'     => array( 'One-time buyers', 0, 0.0, 0 ),
			'coupon'       => array( 'Coupon users', 0, 0.0, 0 ),
			'referral'     => array( 'Referral customers', 0, 0.0, 0 ),
			'loyal'        => array( 'Loyal customers', 0, 0.0, 0 ),
			'at_risk'      => array( 'At-risk customers', 0, 0.0, 0 ),
		);

		foreach ( $customers ? $customers : array() as $row ) {
			$id    = (int) $row->customer_id;
			$rev   = (float) $row->revenue;
			$ords  = (int) $row->orders;
			$days  = (int) $row->days_since;
			self::bump_segment( $buckets, 'high', $rev >= $cutoff && $cutoff > 0, $rev, $ords );
			self::bump_segment( $buckets, 'frequent', $ords >= 3, $rev, $ords );
			self::bump_segment( $buckets, 'one_time', 1 === $ords, $rev, $ords );
			self::bump_segment( $buckets, 'coupon', isset( $coupon_set[ $id ] ), $rev, $ords );
			self::bump_segment( $buckets, 'referral', isset( $ref_set[ $id ] ), $rev, $ords );
			self::bump_segment( $buckets, 'vip', isset( $vip_set[ $id ] ), $rev, $ords );
			self::bump_segment( $buckets, 'loyal', $ords >= 3 && $days <= 90, $rev, $ords );
			self::bump_segment( $buckets, 'at_risk', $days > 60 && $days <= 180, $rev, $ords );
		}

		$out = array();
		foreach ( $buckets as $key => $row ) {
			$out[] = array(
				'id'       => $key,
				'name'     => $row[0],
				'customers'=> $row[1],
				'revenue'  => round( $row[2], 2 ),
				'orders'   => $row[3],
				'aov'      => $row[3] ? round( $row[2] / $row[3], 2 ) : 0,
			);
		}

		return $out;
	}

	/**
	 * Increment a segment bucket.
	 *
	 * @param array $buckets Buckets by ref.
	 * @param string $key    Key.
	 * @param bool   $match  Whether included.
	 * @param float  $rev    Revenue.
	 * @param int    $ords   Orders.
	 * @return void
	 */
	private static function bump_segment( &$buckets, $key, $match, $rev, $ords ) {
		if ( ! $match ) {
			return;
		}
		$buckets[ $key ][1]++;
		$buckets[ $key ][2] += $rev;
		$buckets[ $key ][3] += $ords;
	}

	/**
	 * Label a WooCommerce customer_lookup id.
	 *
	 * @param int $customer_id Lookup ID (not always WP user ID).
	 * @return array{name: string, email: string}
	 */
	private static function customer_label( $customer_id ) {
		global $wpdb;

		$table = esc_sql( GrowthPilot_Analytics_Query::customers_table() );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT first_name, last_name, email, user_id FROM {$table} WHERE customer_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$customer_id
			)
		);

		if ( ! $row ) {
			return array( 'name' => '#' . $customer_id, 'email' => '' );
		}

		$name = trim( $row->first_name . ' ' . $row->last_name );
		if ( '' === $name && $row->user_id ) {
			$user = get_userdata( (int) $row->user_id );
			$name = $user ? $user->display_name : $row->email;
		}

		return array(
			'name'  => $name ? $name : $row->email,
			'email' => (string) $row->email,
		);
	}
}
