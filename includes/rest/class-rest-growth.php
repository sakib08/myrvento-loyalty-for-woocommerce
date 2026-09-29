<?php
/**
 * Dashboard, sales, and operations REST.
 *
 * @package Myrvento
 */

defined( 'ABSPATH' ) || exit;

/**
 * Growth routes.
 */
class Myrvento_REST_Growth {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		$ns = Myrvento_REST::NAMESPACE;

		foreach ( array( 'dashboard', 'sales', 'operations' ) as $route ) {
			register_rest_route(
				$ns,
				'/' . $route,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_' . $route ),
					'permission_callback' => array( 'Myrvento_REST', 'can_manage' ),
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
		return rest_ensure_response( Myrvento_Dashboard::report() );
	}

	/**
	 * Sales and conversion.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_sales() {
		return rest_ensure_response( Myrvento_Sales::report() );
	}

	/**
	 * WooCommerce operations.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_operations() {
		return rest_ensure_response( Myrvento_Operations::report() );
	}
}
