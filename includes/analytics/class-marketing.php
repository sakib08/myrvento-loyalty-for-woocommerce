<?php
/**
 * Campaign ROI, email, funnel, attribution.
 *
 * @package Myrvento
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables have no core API.

/**
 * Marketing intelligence.
 */
class Myrvento_Analytics_Marketing {

	/**
	 * Marketing report.
	 *
	 * @param array $range Range.
	 * @return array<string, mixed>
	 */
	public static function report( $range ) {
		return array(
			'range'       => array( 'from' => $range['from'], 'to' => $range['to'] ),
			'funnel'      => self::funnel( $range ),
			'attribution' => self::attribution( $range ),
			'campaigns'   => self::campaigns( $range ),
			'coupons'     => self::coupons( $range ),
			'email'       => self::email( $range ),
		);
	}

	/**
	 * Funnel counts and conversion.
	 *
	 * @param array $range Range.
	 * @return array<int, array<string, mixed>>
	 */
	public static function funnel( $range ) {
		global $wpdb;

		$table = esc_sql( Myrvento::table( 'analytics_events' ) );
		$steps = array(
			'visit'        => __( 'Visitors', 'myrvento-loyalty-for-woocommerce' ),
			'product_view' => __( 'Product views', 'myrvento-loyalty-for-woocommerce' ),
			'add_to_cart'  => __( 'Add to cart', 'myrvento-loyalty-for-woocommerce' ),
			'checkout'     => __( 'Checkout', 'myrvento-loyalty-for-woocommerce' ),
			'purchase'     => __( 'Purchase', 'myrvento-loyalty-for-woocommerce' ),
		);

		$out      = array();
		$previous = 0;

		foreach ( $steps as $type => $label ) {
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT session_id) FROM {$table} WHERE event_type = %s AND created_at BETWEEN %s AND %s AND (touch IS NULL OR touch = 'last')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$type,
					$range['from_sql'],
					$range['to_sql']
				)
			);

			$from_prev = 0;
			if ( $previous > 0 ) {
				$from_prev = round( ( $count / $previous ) * 100, 1 );
			} elseif ( $count > 0 ) {
				$from_prev = 100;
			}
			$out[]     = array(
				'step'       => $type,
				'label'      => $label,
				'count'      => $count,
				'from_prev'  => $from_prev,
			);
			$previous = $count > 0 ? $count : $previous;
		}

		$checkout = 0;
		$purchase = 0;
		foreach ( $out as $step ) {
			if ( 'checkout' === $step['step'] ) {
				$checkout = $step['count'];
			}
			if ( 'purchase' === $step['step'] ) {
				$purchase = $step['count'];
			}
		}

		$abandoned = max( 0, $checkout - $purchase );

		return array(
			'steps'               => $out,
			'abandoned_checkout'  => $abandoned,
			'overall_conversion'  => ( $out[0]['count'] > 0 ) ? round( ( $purchase / $out[0]['count'] ) * 100, 2 ) : 0,
		);
	}

	/**
	 * First-touch vs last-touch attribution.
	 *
	 * @param array $range Range.
	 * @return array<string, mixed>
	 */
	public static function attribution( $range ) {
		global $wpdb;

		$build = static function ( $touch ) use ( $wpdb, $range ) {
			$table = esc_sql( Myrvento::table( 'analytics_events' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from esc_sql( Myrvento::table() ).
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT COALESCE(NULLIF(utm_source, ''), channel, 'direct') AS source,
						COUNT(DISTINCT order_id) AS orders
					 FROM {$table}
					 WHERE event_type = 'purchase' AND touch = %s AND created_at BETWEEN %s AND %s AND order_id IS NOT NULL
					 GROUP BY source
					 ORDER BY orders DESC
					 LIMIT 12",
					$touch,
					$range['from_sql'],
					$range['to_sql']
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$out = array();
			foreach ( $rows ? $rows : array() as $row ) {
				$out[] = array(
					'source' => $row->source,
					'orders' => (int) $row->orders,
				);
			}
			return $out;
		};

		$table = esc_sql( Myrvento::table( 'analytics_events' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from esc_sql( Myrvento::table() ).
		$multi = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COALESCE(NULLIF(utm_source, ''), channel, 'direct') AS source,
					COUNT(*) AS touches
				 FROM {$table}
				 WHERE created_at BETWEEN %s AND %s AND event_type IN ('visit','product_view','add_to_cart','checkout','purchase')
				 GROUP BY source
				 ORDER BY touches DESC
				 LIMIT 12",
				$range['from_sql'],
				$range['to_sql']
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$multi_out = array();
		foreach ( $multi ? $multi : array() as $row ) {
			$multi_out[] = array(
				'source'  => $row->source,
				'touches' => (int) $row->touches,
			);
		}

		$referral_orders = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . esc_sql( Myrvento::table( 'referrals' ) ) . ' WHERE attributed_order_id IS NOT NULL AND converted_at BETWEEN %s AND %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$range['from_sql'],
				$range['to_sql']
			)
		);

		return array(
			'first_touch' => $build( 'first' ),
			'last_touch'  => $build( 'last' ),
			'multi_touch' => $multi_out,
			'referral'    => $referral_orders,
		);
	}

	/**
	 * Referral campaign ROI (revenue vs points issued).
	 *
	 * @param array $range Range.
	 * @return array<int, array<string, mixed>>
	 */
	public static function campaigns( $range ) {
		global $wpdb;

		$campaigns = Myrvento_Referral_Program::campaigns();
		$ledger    = esc_sql( Myrvento::table( 'points_ledger' ) );
		$stats     = esc_sql( Myrvento_Analytics_Query::stats_table() );
		$statuses  = Myrvento_Analytics_Query::paid_statuses();
		$status_in = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$out       = array();

		foreach ( $campaigns as $campaign ) {
			$referrals = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT attributed_order_id FROM ' . esc_sql( Myrvento::table( 'referrals' ) ) . ' WHERE campaign_id = %d AND attributed_order_id IS NOT NULL', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					(int) $campaign->id
				)
			);

			$order_ids = array();
			foreach ( $referrals ? $referrals : array() as $row ) {
				$order_ids[] = (int) $row->attributed_order_id;
			}

			$revenue = 0.0;
			$orders  = 0;
			if ( $order_ids ) {
				$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
				$params       = array_merge( $order_ids, $statuses, array( $range['from_sql'], $range['to_sql'] ) );
				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Lookup table is escaped. IN lists are generated placeholders.
				$sql = "SELECT COALESCE(SUM(net_total), 0) AS revenue, COUNT(*) AS orders FROM {$stats} WHERE order_id IN ({$placeholders}) AND status IN ({$status_in}) AND date_created BETWEEN %s AND %s";
				$row = $wpdb->get_row( $wpdb->prepare( $sql, $params ) );
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
				$revenue      = $row ? (float) $row->revenue : 0.0;
				$orders       = $row ? (int) $row->orders : 0;
			}

			$points = 0;
			if ( $order_ids ) {
				$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
				$params       = array_merge( $order_ids, array( $range['from_sql'], $range['to_sql'] ) );
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Ledger table and generated %d list.
				$points = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COALESCE(SUM(amount), 0) FROM {$ledger} WHERE source = 'referral' AND type = 'earn' AND order_id IN ({$placeholders}) AND created_at BETWEEN %s AND %s",
						$params
					)
				);
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			}

			$signups = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM ' . esc_sql( Myrvento::table( 'referrals' ) ) . ' WHERE campaign_id = %d AND referee_id IS NOT NULL AND created_at BETWEEN %s AND %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					(int) $campaign->id,
					$range['from_sql'],
					$range['to_sql']
				)
			);
			$points += (int) $campaign->referee_signup_points * $signups;

			$clicks = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM ' . esc_sql( Myrvento::table( 'referral_clicks' ) ) . ' WHERE campaign_id = %d AND created_at BETWEEN %s AND %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					(int) $campaign->id,
					$range['from_sql'],
					$range['to_sql']
				)
			);

			$out[] = array(
				'id'               => (int) $campaign->id,
				'name'             => $campaign->name,
				'clicks'           => $clicks,
				'orders'           => $orders,
				'revenue'          => round( $revenue, 2 ),
				'points_issued'    => $points,
				'conversion'       => $clicks ? round( ( $orders / $clicks ) * 100, 2 ) : 0,
				'revenue_per_order'=> $orders ? round( $revenue / $orders, 2 ) : 0,
			);
		}

		return $out;
	}

	/**
	 * Coupon attribution.
	 *
	 * @param array $range Range.
	 * @return array<int, array<string, mixed>>
	 */
	public static function coupons( $range ) {
		global $wpdb;

		$table = esc_sql( Myrvento_Analytics_Query::coupons_table() );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Coupon lookup table name is trusted.
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT coupon_id, COUNT(DISTINCT order_id) AS orders, SUM(discount_amount) AS discount
				 FROM {$table}
				 WHERE date_created BETWEEN %s AND %s
				 GROUP BY coupon_id
				 ORDER BY discount DESC
				 LIMIT 15",
				$range['from_sql'],
				$range['to_sql']
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$out = array();
		foreach ( $rows ? $rows : array() as $row ) {
			$coupon = new WC_Coupon( (int) $row->coupon_id );
			$out[]  = array(
				'coupon_id' => (int) $row->coupon_id,
				'code'      => $coupon->get_code() ? $coupon->get_code() : '#' . $row->coupon_id,
				'orders'    => (int) $row->orders,
				'discount'  => round( (float) $row->discount, 2 ),
			);
		}

		return $out;
	}

	/**
	 * Email performance.
	 *
	 * @param array $range Range.
	 * @return array<string, mixed>
	 */
	public static function email( $range ) {
		global $wpdb;

		$table = esc_sql( Myrvento::table( 'email_stats' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from esc_sql( Myrvento::table() ).
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT email_key, email_title,
					SUM(sent) AS sent,
					SUM(opened) AS opened,
					SUM(clicked) AS clicked,
					SUM(converted) AS converted,
					SUM(revenue) AS revenue
				 FROM {$table}
				 WHERE stat_date BETWEEN %s AND %s
				 GROUP BY email_key, email_title
				 ORDER BY sent DESC",
				$range['from'],
				$range['to']
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$out     = array();
		$sent    = 0;
		$opened  = 0;
		$clicked = 0;

		foreach ( $rows ? $rows : array() as $row ) {
			$s       = (int) $row->sent;
			$o       = (int) $row->opened;
			$c       = (int) $row->clicked;
			$sent   += $s;
			$opened += $o;
			$clicked += $c;
			$out[]   = array(
				'key'         => $row->email_key,
				'title'       => $row->email_title ? $row->email_title : $row->email_key,
				'sent'        => $s,
				'opened'      => $o,
				'clicked'     => $c,
				'converted'   => (int) $row->converted,
				'revenue'     => round( (float) $row->revenue, 2 ),
				'open_rate'   => $s ? round( ( $o / $s ) * 100, 1 ) : 0,
				'click_rate'  => $s ? round( ( $c / $s ) * 100, 1 ) : 0,
			);
		}

		return array(
			'totals' => array(
				'sent'       => $sent,
				'opened'     => $opened,
				'clicked'    => $clicked,
				'open_rate'  => $sent ? round( ( $opened / $sent ) * 100, 1 ) : 0,
				'click_rate' => $sent ? round( ( $clicked / $sent ) * 100, 1 ) : 0,
			),
			'emails' => $out,
		);
	}
}
