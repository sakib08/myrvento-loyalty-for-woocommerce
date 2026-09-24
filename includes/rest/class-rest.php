<?php
/**
 * REST API bootstrap.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST registrar.
 */
class GrowthPilot_REST {

	const NAMESPACE = 'growthpilot/v1';

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
		GrowthPilot_REST_Settings::register();
		GrowthPilot_REST_Points::register();
		GrowthPilot_REST_Customers::register();
		GrowthPilot_REST_Tiers::register();
		GrowthPilot_REST_Rewards::register();
		GrowthPilot_REST_Gamification::register();
		GrowthPilot_REST_Referrals::register();
		GrowthPilot_REST_Account::register();
		GrowthPilot_REST_Catalog::register();
		GrowthPilot_REST_Analytics::register();
		GrowthPilot_REST_AI::register();
		GrowthPilot_REST_Growth::register();
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
