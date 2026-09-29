<?php
/**
 * Myrvento Brain — action cards from local models, optional LLM narration.
 *
 * @package Ciwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Myrvento Brain.
 */
class Ciwp_AI_Brain {

	/**
	 * Brain report.
	 *
	 * @param bool $fresh Bypass cache.
	 * @param bool $use_llm Attempt LLM narration.
	 * @return array<string, mixed>
	 */
	public static function report( $fresh = false, $use_llm = true ) {
		$local = Ciwp_AI_Engine::remember(
			'ciwp_ai_brain',
			static function () {
				return self::local_insights();
			},
			$fresh
		);

		$llm = null;
		if ( $use_llm && self::llm_enabled() ) {
			$llm = Ciwp_AI_Engine::remember(
				'ciwp_ai_brain_llm',
				static function () use ( $local ) {
					return self::narrate( $local );
				},
				$fresh
			);
		}

		return array(
			'engine'       => ( $llm && empty( $llm['error'] ) ) ? 'hybrid' : 'local',
			'generated_at' => current_time( 'mysql' ),
			'llm'          => array(
				'enabled'    => self::llm_enabled(),
				'configured' => self::llm_available(),
				'model'      => '',
				'error'      => ( $llm && ! empty( $llm['error'] ) ) ? $llm['error'] : '',
			),
			'kpis'         => $local['kpis'],
			'insights'     => ( $llm && ! empty( $llm['insights'] ) ) ? $llm['insights'] : $local['insights'],
			'local'        => $local['insights'],
		);
	}

	/**
	 * Rule-based action cards from the three models.
	 *
	 * @return array<string, mixed>
	 */
	public static function local_insights() {
		$predict  = Ciwp_AI_Predict::report();
		$pricing  = Ciwp_AI_Pricing::report();
		$forecast = Ciwp_AI_Forecast::report();

		$insights = array();

		$high = isset( $predict['summary']['high_churn'] ) ? (int) $predict['summary']['high_churn'] : 0;
		if ( $high > 0 ) {
			$top = isset( $predict['churn'][0]['name'] ) ? $predict['churn'][0]['name'] : '';
			$insights[] = array(
				'id'       => 'churn',
				'severity' => 'warn',
				'title'    => sprintf(
					/* translators: count */
					_n( '%s customer is at high churn risk', '%s customers are at high churn risk', $high, 'myrvento-loyalty-for-woocommerce' ),
					number_format_i18n( $high )
				),
				'body'     => $top
					? sprintf(
						/* translators: customer name */
						__( '%s is the highest risk. Send a win-back offer or loyalty bonus before the repurchase window closes.', 'myrvento-loyalty-for-woocommerce' ),
						$top
					)
					: __( 'Reach at-risk buyers with a points bonus or a personal coupon.', 'myrvento-loyalty-for-woocommerce' ),
				'action'   => __( 'Open win-back list', 'myrvento-loyalty-for-woocommerce' ),
				'tab'      => 'predictions',
			);
		}

		$hv = isset( $predict['summary']['high_value'] ) ? (int) $predict['summary']['high_value'] : 0;
		if ( $hv > 0 ) {
			$insights[] = array(
				'id'       => 'high_value',
				'severity' => 'info',
				'title'    => sprintf(
					/* translators: count */
					_n( '%s predicted high-value customer', '%s predicted high-value customers', $hv, 'myrvento-loyalty-for-woocommerce' ),
					number_format_i18n( $hv )
				),
				'body'     => __( 'Protect these buyers with VIP perks and avoid training them on deep discounts.', 'myrvento-loyalty-for-woocommerce' ),
				'action'   => __( 'Review high-value list', 'myrvento-loyalty-for-woocommerce' ),
				'tab'      => 'predictions',
			);
		}

		$soon = array();
		foreach ( isset( $predict['next_purchase'] ) ? $predict['next_purchase'] : array() as $row ) {
			if ( (int) $row['days_until'] >= 0 && (int) $row['days_until'] <= 14 ) {
				$soon[] = $row;
			}
		}
		if ( $soon ) {
			$insights[] = array(
				'id'       => 'next_purchase',
				'severity' => 'info',
				'title'    => sprintf(
					/* translators: count */
					_n( '%s customer is likely to buy in the next 14 days', '%s customers are likely to buy in the next 14 days', count( $soon ), 'myrvento-loyalty-for-woocommerce' ),
					number_format_i18n( count( $soon ) )
				),
				'body'     => __( 'Time replenishment reminders and points multipliers to land just before the predicted date.', 'myrvento-loyalty-for-woocommerce' ),
				'action'   => __( 'See next-purchase dates', 'myrvento-loyalty-for-woocommerce' ),
				'tab'      => 'predictions',
			);
		}

		$raise = isset( $pricing['summary']['raise'] ) ? (int) $pricing['summary']['raise'] : 0;
		if ( $raise > 0 ) {
			$insights[] = array(
				'id'       => 'raise',
				'severity' => 'action',
				'title'    => sprintf(
					/* translators: count */
					_n( '%s product can support a price increase', '%s products can support a price increase', $raise, 'myrvento-loyalty-for-woocommerce' ),
					number_format_i18n( $raise )
				),
				'body'     => __( 'Demand is outrunning stock. Raising price slightly stretches cover without a full restock delay.', 'myrvento-loyalty-for-woocommerce' ),
				'action'   => __( 'Review price raises', 'myrvento-loyalty-for-woocommerce' ),
				'tab'      => 'pricing',
			);
		}

		$disc = isset( $pricing['summary']['discount'] ) ? (int) $pricing['summary']['discount'] : 0;
		if ( $disc > 0 ) {
			$insights[] = array(
				'id'       => 'discount',
				'severity' => 'action',
				'title'    => sprintf(
					/* translators: count */
					_n( '%s SKU needs a clearance-style discount', '%s SKUs need clearance-style discounts', $disc, 'myrvento-loyalty-for-woocommerce' ),
					number_format_i18n( $disc )
				),
				'body'     => __( 'Slow movers and excess cover. Discount is capped when cost-of-goods meta is present.', 'myrvento-loyalty-for-woocommerce' ),
				'action'   => __( 'Optimize discounts', 'myrvento-loyalty-for-woocommerce' ),
				'tab'      => 'pricing',
			);
		}

		$stockout = isset( $forecast['summary']['stockout_risk'] ) ? (int) $forecast['summary']['stockout_risk'] : 0;
		if ( $stockout > 0 ) {
			$insights[] = array(
				'id'       => 'stockout',
				'severity' => 'warn',
				'title'    => sprintf(
					/* translators: count */
					_n( '%s product is forecasted to stock out in 30 days', '%s products are forecasted to stock out in 30 days', $stockout, 'myrvento-loyalty-for-woocommerce' ),
					number_format_i18n( $stockout )
				),
				'body'     => __( 'Reorder quantities use the next-90-day forecast times the seasonal index for next month.', 'myrvento-loyalty-for-woocommerce' ),
				'action'   => __( 'Open inventory forecast', 'myrvento-loyalty-for-woocommerce' ),
				'tab'      => 'forecast',
			);
		}

		if ( ! $insights ) {
			$insights[] = array(
				'id'       => 'empty',
				'severity' => 'info',
				'title'    => __( 'Myrvento Brain is ready', 'myrvento-loyalty-for-woocommerce' ),
				'body'     => __( 'Predictions need paid WooCommerce orders. As soon as sales land, churn, next purchase, pricing, and inventory forecasts fill in automatically — no API key required.', 'myrvento-loyalty-for-woocommerce' ),
				'action'   => '',
				'tab'      => 'predictions',
			);
		}

		return array(
			'kpis'     => array(
				'customers'    => isset( $predict['summary']['customers'] ) ? (int) $predict['summary']['customers'] : 0,
				'high_churn'   => $high,
				'high_value'   => $hv,
				'price_moves'  => $raise + $disc,
				'stockout'     => $stockout,
				'forecast_30'  => isset( $forecast['store']['forecast_30_units'] ) ? $forecast['store']['forecast_30_units'] : 0,
			),
			'insights' => $insights,
		);
	}

	/**
	 * Whether the site can run narration through the WordPress AI Client.
	 *
	 * @return bool
	 */
	public static function llm_available() {
		return function_exists( 'wp_ai_client_prompt' ) && function_exists( 'wp_supports_ai' ) && wp_supports_ai();
	}

	/**
	 * Whether LLM narration is turned on and the WordPress AI Client is available.
	 *
	 * @return bool
	 */
	public static function llm_enabled() {
		return (bool) Ciwp_Settings::get_value( 'ai_llm_enabled', false ) && self::llm_available();
	}

	/**
	 * Ask the site's WordPress AI provider to rewrite insights.
	 *
	 * @param array<string, mixed> $local Local payload.
	 * @return array<string, mixed>
	 */
	public static function narrate( $local ) {
		if ( ! self::llm_available() ) {
			return array(
				'error' => __( 'WordPress AI is not available. Connect a provider in WordPress settings. On-store models still run without it.', 'myrvento-loyalty-for-woocommerce' ),
			);
		}

		$compact = wp_json_encode( $local );
		$result  = wp_ai_client_prompt( is_string( $compact ) ? $compact : '' )
			->using_system_instruction( 'You are Myrvento Loyalty for WooCommerce, writing action cards for a WooCommerce merchant. Return ONLY a JSON array of 3 to 6 objects with keys: id, severity (info|warn|action), title, body, action, tab (predictions|pricing|forecast). Be specific and operational. Do not invent numbers that are not in the input.' )
			->using_temperature( 0.2 )
			->generate_text();

		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}

		$content = preg_replace( '/^```json\s*|\s*```$/', '', trim( (string) $result ) );
		$parsed  = json_decode( $content, true );

		if ( ! is_array( $parsed ) ) {
			return array( 'error' => __( 'The model did not return JSON insights.', 'myrvento-loyalty-for-woocommerce' ) );
		}

		$clean = array();
		foreach ( $parsed as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean[] = array(
				'id'       => sanitize_key( isset( $row['id'] ) ? $row['id'] : 'llm' ),
				'severity' => in_array( $row['severity'] ?? '', array( 'info', 'warn', 'action' ), true ) ? $row['severity'] : 'info',
				'title'    => sanitize_text_field( isset( $row['title'] ) ? $row['title'] : '' ),
				'body'     => sanitize_textarea_field( isset( $row['body'] ) ? $row['body'] : '' ),
				'action'   => sanitize_text_field( isset( $row['action'] ) ? $row['action'] : '' ),
				'tab'      => in_array( $row['tab'] ?? '', array( 'predictions', 'pricing', 'forecast' ), true ) ? $row['tab'] : 'predictions',
			);
		}

		return array( 'insights' => $clean );
	}
}
