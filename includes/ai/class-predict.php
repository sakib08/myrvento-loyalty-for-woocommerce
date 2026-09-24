<?php
/**
 * Next purchase, churn, and high-value customer models.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Predictive AI.
 */
class GrowthPilot_AI_Predict {

	/**
	 * Full prediction report.
	 *
	 * @param bool $fresh Bypass cache.
	 * @return array<string, mixed>
	 */
	public static function report( $fresh = false ) {
		return GrowthPilot_AI_Engine::remember(
			'growthpilot_ai_predict',
			static function () {
				return self::compute();
			},
			$fresh
		);
	}

	/**
	 * Compute scores.
	 *
	 * @return array<string, mixed>
	 */
	public static function compute() {
		$features = GrowthPilot_AI_Engine::customer_features();
		$rows     = $features['rows'];
		$gap      = max( 14.0, (float) $features['median_gap'] );
		$max_rev  = max( 1.0, (float) $features['max_revenue'] );
		$max_ord  = max( 1, (int) $features['max_orders'] );
		$cutoff   = (float) $features['revenue_cutoff'];

		$ids    = array();
		foreach ( $rows as $row ) {
			$ids[] = (int) $row->customer_id;
		}
		$labels   = GrowthPilot_AI_Engine::customer_labels( $ids );
		$last_sku = GrowthPilot_AI_Engine::last_products( $ids );

		$churn       = array();
		$next        = array();
		$high_value  = array();
		$persist     = array();

		foreach ( $rows as $row ) {
			$cid        = (int) $row->customer_id;
			$orders     = (int) $row->orders;
			$revenue    = (float) $row->revenue;
			$days       = max( 0, (int) $row->days_since );
			$own_gap    = ( $orders >= 2 && (int) $row->span_days > 0 ) ? ( (int) $row->span_days / max( 1, $orders - 1 ) ) : $gap;
			$expected   = max( 14.0, (float) $own_gap );
			$ratio      = $days / $expected;
			$churn_p    = GrowthPilot_AI_Engine::logistic( 1.6 * ( $ratio - 1.35 ) );
			$churn_pct  = round( $churn_p * 100, 1 );
			$conf       = GrowthPilot_AI_Engine::clamp( 38 + ( $orders * 11 ), 40, 96 );

			$recency    = GrowthPilot_AI_Engine::clamp( 100 - ( $days / 1.8 ), 0, 100 );
			$frequency  = GrowthPilot_AI_Engine::clamp( ( $orders / $max_ord ) * 100, 0, 100 );
			$monetary   = GrowthPilot_AI_Engine::clamp( ( $revenue / $max_rev ) * 100, 0, 100 );
			$value      = round( ( 0.2 * $recency ) + ( 0.3 * $frequency ) + ( 0.5 * $monetary ), 1 );
			$span       = max( 30, (int) $row->span_days, $days );
			$pred_90    = round( ( $revenue / $span ) * 90, 2 );

			$next_ts    = strtotime( $row->last_order ) + ( (int) round( $expected ) * DAY_IN_SECONDS );
			$next_date  = gmdate( 'Y-m-d', $next_ts );
			$sku        = isset( $last_sku[ $cid ] ) ? $last_sku[ $cid ] : array( 'product_id' => 0, 'name' => '' );
			$label      = isset( $labels[ $cid ] ) ? $labels[ $cid ] : array( 'name' => '#' . $cid, 'email' => '' );

			$reason_churn = $days <= 60
				? __( 'Still inside a typical repurchase window.', 'gp_ppros' )
				: sprintf(
					/* translators: 1: days since order, 2: expected gap */
					__( 'No order in %1$d days; typical gap is %2$d days.', 'gp_ppros' ),
					$days,
					(int) round( $expected )
				);

			$base = array(
				'customer_id' => $cid,
				'name'        => $label['name'],
				'email'       => $label['email'],
				'orders'      => $orders,
				'revenue'     => round( $revenue, 2 ),
				'aov'         => round( (float) $row->aov, 2 ),
				'days_since'  => $days,
				'confidence'  => round( $conf, 0 ),
			);

			$churn_row = array_merge(
				$base,
				array(
					'churn_risk' => $churn_pct,
					'status'     => $churn_pct >= 70 ? 'high' : ( $churn_pct >= 40 ? 'watch' : 'healthy' ),
					'reason'     => $reason_churn,
				)
			);
			$churn[] = $churn_row;

			$next_row = array_merge(
				$base,
				array(
					'next_purchase_on' => $next_date,
					'days_until'       => (int) round( ( $next_ts - time() ) / DAY_IN_SECONDS ),
					'likely_product'   => $sku['name'],
					'product_id'       => $sku['product_id'],
					'reason'           => sprintf(
						/* translators: 1: product name, 2: expected days */
						__( 'Median repurchase every %2$d days; last basket featured %1$s.', 'gp_ppros' ),
						$sku['name'] ? $sku['name'] : __( 'their usual items', 'gp_ppros' ),
						(int) round( $expected )
					),
				)
			);
			$next[] = $next_row;

			$high_row = array_merge(
				$base,
				array(
					'value_score'     => $value,
					'predicted_90d'   => $pred_90,
					'is_high_value'   => $revenue >= $cutoff && $cutoff > 0,
					'reason'          => sprintf(
						/* translators: score */
						__( 'RFM value score %s (recency, frequency, spend).', 'gp_ppros' ),
						number_format_i18n( $value, 1 )
					),
				)
			);
			$high_value[] = $high_row;

			$persist[] = array( 'subject_type' => 'customer', 'subject_id' => $cid, 'score' => $churn_pct, 'confidence' => $conf, 'payload' => $churn_row );
		}

		usort(
			$churn,
			static function ( $a, $b ) {
				return $b['churn_risk'] <=> $a['churn_risk'];
			}
		);
		usort(
			$next,
			static function ( $a, $b ) {
				return $a['days_until'] <=> $b['days_until'];
			}
		);
		usort(
			$high_value,
			static function ( $a, $b ) {
				return $b['value_score'] <=> $a['value_score'];
			}
		);

		GrowthPilot_AI_Engine::persist( 'churn', $persist );

		$high_n  = 0;
		$watch_n = 0;
		foreach ( $churn as $row ) {
			if ( 'high' === $row['status'] ) {
				++$high_n;
			} elseif ( 'watch' === $row['status'] ) {
				++$watch_n;
			}
		}

		return array(
			'engine'          => 'local',
			'generated_at'    => current_time( 'mysql' ),
			'median_gap_days' => round( $gap, 1 ),
			'summary'         => array(
				'customers'        => count( $rows ),
				'high_churn'       => $high_n,
				'watch_churn'      => $watch_n,
				'high_value'       => count(
					array_filter(
						$high_value,
						static function ( $row ) {
							return ! empty( $row['is_high_value'] );
						}
					)
				),
			),
			'churn'           => array_slice( $churn, 0, 25 ),
			'next_purchase'   => array_slice( $next, 0, 25 ),
			'high_value'      => array_slice( $high_value, 0, 25 ),
		);
	}
}
