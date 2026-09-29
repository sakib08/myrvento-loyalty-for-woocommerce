<?php
/**
 * REST API bootstrap.
 *
 * @package Ciwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST registrar.
 */
class Ciwp_REST {

	const NAMESPACE = 'ciwp/v1';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		Ciwp_REST_Settings::register();
		Ciwp_REST_Points::register();
		Ciwp_REST_Customers::register();
		Ciwp_REST_Tiers::register();
		Ciwp_REST_Rewards::register();
		Ciwp_REST_Gamification::register();
		Ciwp_REST_Referrals::register();
		Ciwp_REST_Account::register();
		Ciwp_REST_Catalog::register();
		Ciwp_REST_Analytics::register();
		Ciwp_REST_AI::register();
		Ciwp_REST_Growth::register();
	}

	/**
	 * Admin capability.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Logged-in customer.
	 *
	 * @return bool
	 */
	public static function can_account() {
		return is_user_logged_in();
	}

	/**
	 * Error helper.
	 *
	 * @param WP_Error $error Error.
	 * @return WP_REST_Response
	 */
	public static function error( $error ) {
		return new WP_REST_Response(
			array(
				'code'    => $error->get_error_code(),
				'message' => $error->get_error_message(),
			),
			400
		);
	}
}
