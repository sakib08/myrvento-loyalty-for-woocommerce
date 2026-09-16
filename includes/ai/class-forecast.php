<?php
/**
 * Seasonal demand and inventory forecasting.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Demand forecasting.
 */
class GrowthPilot_AI_Forecast {

	/**
	 * Forecast report.
	 *
	 * @param bool $fresh Bypass cache.
	 * @return array<string, mixed>
	 */
	public static function report( $fresh = false ) {
		return GrowthPilot_AI_Engine::remember(
			'growthpilot_ai_forecast',
			static function () {
				return self::compute();
			},
			$fresh
		);
	}

	/**
	 * Compute seasonal + inventory forecasts.
	 *
	 * @return array<string, mixed>
	 */
	public static function compute() {
		$monthly = GrowthPilot_AI_Engine::store_monthly();
		$sales   = GrowthPilot_AI_Engine::product_sales();
		$by_sku  = GrowthPilot_AI_Engine::product_monthly();

		$seasonal = self::seasonal_curve( $monthly );
		$next_m   = gmdate( 'n' ) % 12 + 1;
		$next_idx = isset( $seasonal['by_month'][ $next_m ] ) ? $seasonal['by_month'][ $next_m ] : 1.0;

		$recent_units = 0;
		$recent_net   = 0.0;
		$tail         = array_slice( $monthly, -3 );
		foreach ( $tail as $row ) {
			$recent_units += (int) $row['units'];
			$recent_net   += (float) $row['net'];
		}
		$months_n     = max( 1, count( $tail ) );
		$run_units    = $recent_units / $months_n;
		$run_net      = $recent_net / $months_n;
		$forecast_30  = round( $run_units * $next_idx, 1 );
		$forecast_90  = round( $run_units * 3 * $next_idx, 1 );
		$revenue_30   = round( $run_net * $next_idx, 2 );

		$inventory = array();
		$persist   = array();
		$stockout  = 0;
		$overstock = 0;

		foreach ( $sales ? $sales : array() as $row ) {
			$product = wc_get_product( (int) $row->product_id );
			if ( ! $product ) {
				continue;
			}

			$units_year = (float) $row->units;
			$daily      = $units_year / 365;
			$sku_months = isset( $by_sku[ (int) $row->product_id ] ) ? $by_sku[ (int) $row->product_id ] : array();
			$idx        = self::next_month_index( $sku_months, $next_idx );
			$next_30    = round( $daily * 30 * $idx, 1 );
			$next_90    = round( $daily * 90 * $idx, 1 );
			$managed    = $product->managing_stock();
			$stock      = $managed ? (int) $product->get_stock_quantity() : null;
			$cover      = ( $managed && $daily > 0 ) ? $stock / max( 0.01, $daily ) : null;
			$reorder    = ( $managed && null !== $stock ) ? max( 0, (int) ceil( $next_90 - $stock ) ) : null;

			$status = 'ok';
			if ( $managed && null !== $stock ) {
				if ( $next_30 > $stock ) {
					$status = 'stockout';
					++$stockout;
				} elseif ( $cover !== null && $cover > 150 ) {
					$status = 'overstock';
					++$overstock;
				}
			} elseif ( ! $managed ) {
				$status = 'untracked';
			}

			$item = array(
				'product_id'     => (int) $row->product_id,
				'name'           => $product->get_name(),
				'units_365'      => (int) $units_year,
				'forecast_30'    => $next_30,
				'forecast_90'    => $next_90,
				'seasonal_index' => round( $idx, 2 ),
				'stock'          => $stock,
				'days_of_cover'  => null === $cover ? null : round( $cover, 1 ),
				'reorder_qty'    => $reorder,
				'status'         => $status,
			);
			$inventory[] = $item;
			$persist[]   = array(
				'subject_type' => 'product',
				'subject_id'   => (int) $row->product_id,
				'score'        => $next_30,
				'confidence'   => count( $sku_months ) >= 6 ? 78 : 52,
				'payload'      => $item,
			);
		}

		usort(
			$inventory,
			static function ( $a, $b ) {
				$rank = array( 'stockout' => 0, 'overstock' => 1, 'ok' => 2, 'untracked' => 3 );
				$d    = ( $rank[ $a['status'] ] ?? 9 ) <=> ( $rank[ $b['status'] ] ?? 9 );
				return 0 !== $d ? $d : ( $b['forecast_30'] <=> $a['forecast_30'] );
			}
		);

		GrowthPilot_AI_Engine::persist( 'demand', $persist );

		$peak = array();
		if ( $monthly ) {
			foreach ( $seasonal['by_month'] as $month => $idx ) {
				$peak[] = array(
					'month' => (int) $month,
					'label' => gmdate( 'M', mktime( 0, 0, 0, (int) $month, 1 ) ),
					'index' => round( (float) $idx, 2 ),
				);
			}
		}

		return array(
			'engine'       => 'local',
			'generated_at' => current_time( 'mysql' ),
			'store'        => array(
				'forecast_30_units'   => $forecast_30,
				'forecast_90_units'   => $forecast_90,
				'forecast_30_revenue' => $revenue_30,
				'next_month_index'    => round( $next_idx, 2 ),
				'confidence'          => count( $monthly ) >= 6 ? 74 : 48,
			),
			'seasonal'     => array(
				'curve'   => $peak,
				'history' => $monthly,
				'note'    => count( $monthly ) < 6
					? __( 'Fewer than 6 months of sales — treat seasonality as directional.', 'growthpilot' )
					: __( 'Index 1.0 is an average month. Peaks above 1.2 usually need extra stock.', 'growthpilot' ),
			),
			'summary'      => array(
				'stockout_risk' => $stockout,
				'overstock'     => $overstock,
				'skus'          => count( $inventory ),
			),
			'inventory'    => array_slice( $inventory, 0, 40 ),
		);
	}

	/**
	 * Average seasonal index by calendar month (1–12).
	 *
	 * @param array<int, array<string, mixed>> $monthly Store months.
	 * @return array{by_month: array<int, float>, average: float}
	 */
	private static function seasonal_curve( $monthly ) {
		$bucket = array_fill( 1, 12, array() );
		foreach ( $monthly as $row ) {
			$m = (int) substr( $row['month'], 5, 2 );
			if ( $m >= 1 && $m <= 12 ) {
				$bucket[ $m ][] = (float) $row['units'];
			}
		}

		$avgs = array();
		$sum  = 0.0;
		$n    = 0;
		foreach ( $bucket as $m => $vals ) {
			$avgs[ $m ] = $vals ? array_sum( $vals ) / count( $vals ) : 0.0;
			$sum       += $avgs[ $m ];
			if ( $vals ) {
				++$n;
			}
		}
		$mean = $n ? ( $sum / 12 ) : 1.0;
		if ( $mean <= 0 ) {
			$mean = 1.0;
		}

		$index = array();
		foreach ( $avgs as $m => $avg ) {
			$index[ $m ] = $avg > 0 ? $avg / $mean : 1.0;
		}

		return array(
			'by_month' => $index,
			'average'  => $mean,
		);
	}

	/**
	 * SKU seasonal index for next calendar month.
	 *
	 * @param array<string, float> $by_month SKU months.
	 * @param float                $fallback Store index.
	 * @return float
	 */
	private static function next_month_index( $by_month, $fallback ) {
		if ( count( $by_month ) < 3 ) {
			return (float) $fallback;
		}
		$avg  = array_sum( $by_month ) / count( $by_month );
		$next = gmdate( 'm', strtotime( 'first day of next month' ) );
		$hit  = array();
		foreach ( $by_month as $ym => $units ) {
			if ( substr( $ym, 5, 2 ) === $next ) {
				$hit[] = $units;
			}
		}
		if ( ! $hit || $avg <= 0 ) {
			return (float) $fallback;
		}
		return ( array_sum( $hit ) / count( $hit ) ) / $avg;
	}
}
