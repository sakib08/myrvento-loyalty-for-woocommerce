<?php
/**
 * AI REST.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * AI routes.
 */
class GrowthPilot_REST_AI {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$ns = GrowthPilot_REST::NAMESPACE;

		foreach ( array( 'brain', 'predictions', 'pricing', 'forecast' ) as $report ) {
			register_rest_route(
				$ns,
				'/ai/' . $report,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_' . $report ),
					'permission_callback' => array( 'GrowthPilot_REST', 'can_manage' ),
				)
			);
		}

		register_rest_route(
			$ns,
			'/ai/refresh',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'refresh' ),
				'permission_callback' => array( 'GrowthPilot_REST', 'can_manage' ),
			)
		);

		register_rest_route(
			$ns,
			'/ai/narrate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'narrate' ),
				'permission_callback' => array( 'GrowthPilot_REST', 'can_manage' ),
			)
		);

		register_rest_route(
			$ns,
			'/ai/pricing/apply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'apply_price' ),
				'permission_callback' => array( 'GrowthPilot_REST', 'can_manage' ),
			)
		);
	}

	/**
	 * Brain.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_brain() {
		return rest_ensure_response( GrowthPilot_AI_Brain::report() );
	}

	/**
	 * Predictions.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_predictions() {
		return rest_ensure_response( GrowthPilot_AI_Predict::report() );
	}

	/**
	 * Pricing.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_pricing() {
		return rest_ensure_response( GrowthPilot_AI_Pricing::report() );
	}

	/**
	 * Forecast.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_forecast() {
		return rest_ensure_response( GrowthPilot_AI_Forecast::report() );
	}

	/**
	 * Rebuild caches.
	 *
	 * @return WP_REST_Response
	 */
	public static function refresh() {
		return rest_ensure_response( GrowthPilot_AI_Engine::refresh_all() );
	}

	/**
	 * Force LLM narration.
	 *
	 * @return WP_REST_Response
	 */
	public static function narrate() {
		delete_transient( 'growthpilot_ai_brain_llm' );
		return rest_ensure_response( GrowthPilot_AI_Brain::report( true, true ) );
	}

	/**
	 * Apply a suggested price.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function apply_price( $request ) {
		$result = GrowthPilot_AI_Pricing::apply(
			(int) $request->get_param( 'product_id' ),
			(float) $request->get_param( 'price' ),
			sanitize_key( (string) $request->get_param( 'mode' ) ) ? sanitize_key( (string) $request->get_param( 'mode' ) ) : 'sale'
		);

		if ( is_wp_error( $result ) ) {
			return GrowthPilot_REST::error( $result );
		}

		return rest_ensure_response( $result );
	}
}
