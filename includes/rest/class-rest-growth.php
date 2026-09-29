<?php
/**
 * Dashboard, sales, and operations REST.
 *
 * @package Ciwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Growth routes.
 */
class Ciwp_REST_Growth {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$ns = Ciwp_REST::NAMESPACE;

		foreach ( array( 'dashboard', 'sales', 'operations' ) as $route ) {
			register_rest_route(
				$ns,
				'/' . $route,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_' . $route ),
					'permission_callback' => array( 'Ciwp_REST', 'can_manage' ),
				)
			);
		}
	}

	/**
	 * Dashboard.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_dashboard() {
		return rest_ensure_response( Ciwp_Dashboard::report() );
	}

	/**
	 * Sales and conversion.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_sales() {
		return rest_ensure_response( Ciwp_Sales::report() );
	}

	/**
	 * WooCommerce operations.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_operations() {
		return rest_ensure_response( Ciwp_Operations::report() );
	}
}
