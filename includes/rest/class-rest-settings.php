<?php
/**
 * Settings REST.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings routes.
 */
class GrowthPilot_REST_Settings {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		register_rest_route(
			GrowthPilot_REST::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get' ),
					'permission_callback' => array( 'GrowthPilot_REST', 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save' ),
					'permission_callback' => array( 'GrowthPilot_REST', 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Get settings.
	 *
	 * @return WP_REST_Response
	 */
	public static function get() {
		return rest_ensure_response( GrowthPilot_Settings::for_admin() );
	}

	/**
	 * Save settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function save( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		return rest_ensure_response( GrowthPilot_Settings::update( $params ) );
	}
}
