<?php
/**
 * REST API bootstrap.
 *
 * @package Myrvento
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST registrar.
 */
class Myrvento_REST {

	const NAMESPACE = 'myrvento/v1';

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
		Myrvento_REST_Settings::register();
		Myrvento_REST_Points::register();
		Myrvento_REST_Customers::register();
		Myrvento_REST_Tiers::register();
		Myrvento_REST_Rewards::register();
		Myrvento_REST_Gamification::register();
		Myrvento_REST_Referrals::register();
		Myrvento_REST_Account::register();
		Myrvento_REST_Catalog::register();
		Myrvento_REST_Analytics::register();
		Myrvento_REST_AI::register();
		Myrvento_REST_Growth::register();
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
	 * Logged-in customer. Account routes only read or change that user's own records.
	 *
	 * Cookie-authenticated REST requests must also send the wp_rest nonce.
	 *
	 * @return bool
	 */
	public static function can_account() {
		return is_user_logged_in() && current_user_can( 'read' );
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
