<?php
/**
 * Commerce Brain — action cards from local models, optional LLM narration.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * AI Commerce Brain.
 */
class GrowthPilot_AI_Brain {

	/**
	 * Brain report.
	 *
	 * @param bool $fresh Bypass cache.
	 * @param bool $use_llm Attempt LLM narration.
	 * @return array<string, mixed>
	 */
	public static function report( $fresh = false, $use_llm = true ) {
		$local = GrowthPilot_AI_Engine::remember(
			'growthpilot_ai_brain',
			static function () {
				return self::local_insights();
			},
			$fresh
		);

		$llm = null;
		if ( $use_llm && self::llm_enabled() ) {
			$llm = GrowthPilot_AI_Engine::remember(
				'growthpilot_ai_brain_llm',
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
				'configured' => '' !== (string) GrowthPilot_Settings::get_value( 'ai_api_key', '' ),
				'model'      => (string) GrowthPilot_Settings::get_value( 'ai_model', 'gpt-4o-mini' ),
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
		$predict  = GrowthPilot_AI_Predict::report();
		$pricing  = GrowthPilot_AI_Pricing::report();
		$forecast = GrowthPilot_AI_Forecast::report();

		$insights = array();

		$high = isset( $predict['summary']['high_churn'] ) ? (int) $predict['summary']['high_churn'] : 0;
		if ( $high > 0 ) {
			$top = isset( $predict['churn'][0]['name'] ) ? $predict['churn'][0]['name'] : '';
			$insights[] = array(
				'id'       => 'churn',
				'severity' => 'warn',
				'title'    => sprintf(
					/* translators: count */
					_n( '%s customer is at high churn risk', '%s customers are at high churn risk', $high, 'gp-ppros' ),
					number_format_i18n( $high )
				),
				'body'     => $top
					? sprintf(
						/* translators: customer name */
						__( '%s is the highest risk. Send a win-back offer or loyalty bonus before the repurchase window closes.', 'gp-ppros' ),
						$top
					)
					: __( 'Reach at-risk buyers with a points bonus or a personal coupon.', 'gp-ppros' ),
				'action'   => __( 'Open win-back list', 'gp-ppros' ),
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
					_n( '%s predicted high-value customer', '%s predicted high-value customers', $hv, 'gp-ppros' ),
					number_format_i18n( $hv )
				),
				'body'     => __( 'Protect these buyers with VIP perks and avoid training them on deep discounts.', 'gp-ppros' ),
				'action'   => __( 'Review high-value list', 'gp-ppros' ),
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
					_n( '%s customer is likely to buy in the next 14 days', '%s customers are likely to buy in the next 14 days', count( $soon ), 'gp-ppros' ),
					number_format_i18n( count( $soon ) )
				),
				'body'     => __( 'Time replenishment reminders and points multipliers to land just before the predicted date.', 'gp-ppros' ),
				'action'   => __( 'See next-purchase dates', 'gp-ppros' ),
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
					_n( '%s product can support a price increase', '%s products can support a price increase', $raise, 'gp-ppros' ),
					number_format_i18n( $raise )
				),
				'body'     => __( 'Demand is outrunning stock. Raising price slightly stretches cover without a full restock delay.', 'gp-ppros' ),
				'action'   => __( 'Review price raises', 'gp-ppros' ),
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
					_n( '%s SKU needs a clearance-style discount', '%s SKUs need clearance-style discounts', $disc, 'gp-ppros' ),
					number_format_i18n( $disc )
				),
				'body'     => __( 'Slow movers and excess cover. Discount is capped when cost-of-goods meta is present.', 'gp-ppros' ),
				'action'   => __( 'Optimize discounts', 'gp-ppros' ),
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
					_n( '%s product is forecasted to stock out in 30 days', '%s products are forecasted to stock out in 30 days', $stockout, 'gp-ppros' ),
					number_format_i18n( $stockout )
				),
				'body'     => __( 'Reorder quantities use the next-90-day forecast times the seasonal index for next month.', 'gp-ppros' ),
				'action'   => __( 'Open inventory forecast', 'gp-ppros' ),
				'tab'      => 'forecast',
			);
		}

		if ( ! $insights ) {
			$insights[] = array(
				'id'       => 'empty',
				'severity' => 'info',
				'title'    => __( 'Commerce Brain is ready', 'gp-ppros' ),
				'body'     => __( 'Predictions need paid WooCommerce orders. As soon as sales land, churn, next purchase, pricing, and inventory forecasts fill in automatically — no API key required.', 'gp-ppros' ),
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
	 * Whether LLM narration is turned on and keyed.
	 *
	 * @return bool
	 */
	public static function llm_enabled() {
		return (bool) GrowthPilot_Settings::get_value( 'ai_llm_enabled', false )
			&& '' !== (string) GrowthPilot_Settings::get_value( 'ai_api_key', '' );
	}

	/**
	 * Ask an OpenAI-compatible model to rewrite insights.
	 *
	 * @param array<string, mixed> $local Local payload.
	 * @return array<string, mixed>
	 */
	public static function narrate( $local ) {
		$key  = (string) GrowthPilot_Settings::get_value( 'ai_api_key', '' );
		$base = untrailingslashit( (string) GrowthPilot_Settings::get_value( 'ai_api_base', 'https://api.openai.com/v1' ) ); // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- Optional admin endpoint. Requires WordPress 6.0, before wp_ai_client_prompt().
		$model = (string) GrowthPilot_Settings::get_value( 'ai_model', 'gpt-4o-mini' );

		if ( '' === $key ) {
			return array( 'error' => __( 'No API key saved.', 'gp-ppros' ) );
		}

		$compact = wp_json_encode( $local );
		$body    = array(
			'model'       => $model,
			'temperature' => 0.2,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => 'You are GrowthPilot Commerce Brain for a WooCommerce merchant. Return ONLY a JSON array of 3 to 6 objects with keys: id, severity (info|warn|action), title, body, action, tab (predictions|pricing|forecast). Be specific and operational. Do not invent numbers that are not in the input.',
				),
				array(
					'role'    => 'user',
					'content' => $compact,
				),
			),
		);

		$response = wp_remote_post(
			$base . '/chat/completions',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code >= 400 ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : sprintf( 'HTTP %d', $code );
			return array( 'error' => $msg );
		}

		$content = isset( $data['choices'][0]['message']['content'] ) ? $data['choices'][0]['message']['content'] : '';
		$content = preg_replace( '/^```json\s*|\s*```$/', '', trim( $content ) );
		$parsed  = json_decode( $content, true );

		if ( ! is_array( $parsed ) ) {
			return array( 'error' => __( 'The model did not return JSON insights.', 'gp-ppros' ) );
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
