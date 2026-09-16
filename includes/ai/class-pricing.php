<?php
/**
 * Dynamic pricing and discount optimization.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pricing AI.
 */
class GrowthPilot_AI_Pricing {

	/**
	 * Pricing report.
	 *
	 * @param bool $fresh Bypass cache.
	 * @return array<string, mixed>
	 */
	public static function report( $fresh = false ) {
		return GrowthPilot_AI_Engine::remember(
			'growthpilot_ai_pricing',
			static function () {
				return self::compute();
			},
			$fresh
		);
	}

	/**
	 * Compute recommendations.
	 *
	 * @return array<string, mixed>
	 */
	public static function compute() {
		$sales   = GrowthPilot_AI_Engine::product_sales();
		$monthly = GrowthPilot_AI_Engine::product_monthly();
		$days    = 365;
		$out     = array();
		$persist = array();

		foreach ( $sales ? $sales : array() as $row ) {
			$product = wc_get_product( (int) $row->product_id );
			if ( ! $product ) {
				continue;
			}

			$units     = max( 0, (int) $row->units );
			$revenue   = (float) $row->revenue;
			$discounts = (float) $row->discounts;
			$daily     = $units / $days;
			$regular   = (float) $product->get_regular_price();
			$sale      = $product->get_sale_price();
			$sale      = ( '' === $sale || false === $sale ) ? null : (float) $sale;
			$current   = (float) $product->get_price();
			$cost      = GrowthPilot_AI_Engine::unit_cost( $product );
			$stock     = $product->managing_stock() ? (float) $product->get_stock_quantity() : null;
			$cover     = ( null !== $stock && $daily > 0 ) ? $stock / $daily : null;
			$disc_rate = ( $revenue + $discounts ) > 0 ? ( $discounts / ( $revenue + $discounts ) ) * 100 : 0;
			$on_sale   = $product->is_on_sale();

			$action      = 'hold';
			$suggested   = $current;
			$discount_to = null;
			$reason      = __( 'Velocity and stock look balanced — keep the current price.', 'growthpilot' );

			if ( null !== $cover && $cover < 21 && $daily > 0 && $current > 0 ) {
				$lift        = $cover < 10 ? 0.12 : 0.06;
				$suggested   = round( $current * ( 1 + $lift ), 2 );
				$action      = 'raise';
				$reason      = sprintf(
					/* translators: days of cover */
					__( 'Demand is outrunning stock (~%s days of cover). A modest increase can slow sell-through.', 'growthpilot' ),
					number_format_i18n( $cover, 0 )
				);
			} elseif ( ( null !== $cover && $cover > 120 ) || ( $units < 3 && $current > 0 ) ) {
				$cut         = $on_sale ? 0.05 : 0.12;
				$suggested   = round( $current * ( 1 - $cut ), 2 );
				$action      = 'discount';
				$discount_to = $suggested;
				$reason      = __( 'Slow mover or excess cover — a targeted discount should clear inventory.', 'growthpilot' );
			} elseif ( $disc_rate > 18 && $current > 0 ) {
				$suggested   = round( $current * 1.04, 2 );
				$action      = 'tighten_discount';
				$reason      = sprintf(
					/* translators: discount rate */
					__( 'Coupons already take %s%% of revenue. Tighten promotions before they train customers to wait.', 'growthpilot' ),
					number_format_i18n( $disc_rate, 1 )
				);
			}

			if ( null !== $cost && $suggested < $cost ) {
				$suggested = round( $cost * 1.15, 2 );
				$reason   .= ' ' . __( 'Floor raised to protect cost of goods.', 'growthpilot' );
			}

			$max_off = null;
			if ( null !== $cost && $current > $cost ) {
				$max_off = round( ( ( $current - $cost ) / $current ) * 60, 1 );
			}

			$season = self::seasonal_hint( isset( $monthly[ (int) $row->product_id ] ) ? $monthly[ (int) $row->product_id ] : array() );

			$item = array(
				'product_id'        => (int) $row->product_id,
				'name'              => $product->get_name(),
				'units'             => $units,
				'revenue'           => round( $revenue, 2 ),
				'current_price'     => round( $current, 2 ),
				'regular_price'     => round( $regular, 2 ),
				'sale_price'        => $sale,
				'suggested_price'   => $suggested,
				'action'            => $action,
				'discount_percent'  => $discount_to && $current > 0 ? round( ( 1 - ( $discount_to / $current ) ) * 100, 1 ) : 0,
				'max_safe_discount' => $max_off,
				'days_of_cover'     => null === $cover ? null : round( $cover, 1 ),
				'discount_rate'     => round( $disc_rate, 1 ),
				'season'            => $season,
				'reason'            => $reason,
			);
			$out[]     = $item;
			$persist[] = array(
				'subject_type' => 'product',
				'subject_id'   => (int) $row->product_id,
				'score'        => $suggested,
				'confidence'   => 70,
				'payload'      => $item,
			);
		}

		usort(
			$out,
			static function ( $a, $b ) {
				$rank = array( 'raise' => 0, 'discount' => 1, 'tighten_discount' => 2, 'hold' => 3 );
				$d    = ( $rank[ $a['action'] ] ?? 9 ) <=> ( $rank[ $b['action'] ] ?? 9 );
				return 0 !== $d ? $d : ( $b['units'] <=> $a['units'] );
			}
		);

		GrowthPilot_AI_Engine::persist( 'price', $persist );

		$counts = array( 'raise' => 0, 'discount' => 0, 'tighten_discount' => 0, 'hold' => 0 );
		foreach ( $out as $row ) {
			if ( isset( $counts[ $row['action'] ] ) ) {
				++$counts[ $row['action'] ];
			}
		}

		return array(
			'engine'          => 'local',
			'generated_at'    => current_time( 'mysql' ),
			'summary'         => $counts,
			'recommendations' => $out,
		);
	}

	/**
	 * Whether next month is typically stronger than average.
	 *
	 * @param array<string, float> $by_month Units by YYYY-MM.
	 * @return string
	 */
	private static function seasonal_hint( $by_month ) {
		if ( count( $by_month ) < 3 ) {
			return 'insufficient';
		}
		$avg = array_sum( $by_month ) / count( $by_month );
		$next = gmdate( 'm', strtotime( 'first day of next month' ) );
		$same = array();
		foreach ( $by_month as $ym => $units ) {
			if ( substr( $ym, 5, 2 ) === $next ) {
				$same[] = $units;
			}
		}
		if ( ! $same || $avg <= 0 ) {
			return 'flat';
		}
		$idx = ( array_sum( $same ) / count( $same ) ) / $avg;
		if ( $idx >= 1.2 ) {
			return 'peak';
		}
		if ( $idx <= 0.8 ) {
			return 'trough';
		}
		return 'flat';
	}

	/**
	 * Apply a recommended sale or regular price.
	 *
	 * @param int        $product_id Product.
	 * @param float      $price      New price.
	 * @param string     $mode       sale|regular.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply( $product_id, $price, $mode = 'sale' ) {
		$product = wc_get_product( (int) $product_id );
		if ( ! $product ) {
			return new WP_Error( 'not_found', __( 'Product not found.', 'growthpilot' ) );
		}

		$price = round( (float) $price, 2 );
		if ( $price < 0 ) {
			return new WP_Error( 'invalid', __( 'Price must be zero or greater.', 'growthpilot' ) );
		}

		if ( 'regular' === $mode ) {
			$product->set_regular_price( (string) $price );
			$product->set_price( (string) $price );
		} else {
			$regular = (float) $product->get_regular_price();
			if ( $regular > 0 && $price >= $regular ) {
				$product->set_sale_price( '' );
				$product->set_price( (string) $regular );
			} else {
				$product->set_sale_price( (string) $price );
				$product->set_price( (string) $price );
			}
		}

		$product->save();
		self::report( true );

		return array(
			'product_id' => $product->get_id(),
			'name'       => $product->get_name(),
			'price'      => (float) $product->get_price(),
			'sale_price' => $product->get_sale_price(),
			'mode'       => $mode,
		);
	}
}
