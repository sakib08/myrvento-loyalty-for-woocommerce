<?php
/**
 * Settings REST.
 *
 * @package Myrvento
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings routes.
 */
class Myrvento_REST_Settings {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		register_rest_route(
			Myrvento_REST::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get' ),
					'permission_callback' => array( 'Myrvento_REST', 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save' ),
					'permission_callback' => array( 'Myrvento_REST', 'can_manage' ),
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
		return rest_ensure_response( Myrvento_Settings::for_admin() );
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

		return rest_ensure_response( Myrvento_Settings::update( $params ) );
	}
}
