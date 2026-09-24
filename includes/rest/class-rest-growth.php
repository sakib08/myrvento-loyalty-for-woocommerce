<?php
/**
 * Dashboard, sales, and operations REST.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Growth routes.
 */
class GrowthPilot_REST_Growth {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$ns = GrowthPilot_REST::NAMESPACE;

		foreach ( array( 'dashboard', 'sales', 'operations' ) as $route ) {
			register_rest_route(
				$ns,
				'/' . $route,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_' . $route ),
					'permission_callback' => array( 'GrowthPilot_REST', 'can_manage' ),
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
		return rest_ensure_response( GrowthPilot_Dashboard::report() );
	}

	/**
	 * Sales and conversion.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_sales() {
		return rest_ensure_response( GrowthPilot_Sales::report() );
	}

	/**
	 * WooCommerce operations.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_operations() {
		return rest_ensure_response( GrowthPilot_Operations::report() );
	}
}
