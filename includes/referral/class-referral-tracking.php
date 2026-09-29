<?php
/**
 * Cookie + click attribution for referral codes.
 *
 * @package Ciwp
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables have no core API.

/**
 * Referral tracking.
 */
class Ciwp_Referral_Tracking {

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'capture' ), 5 );
		add_action( 'woocommerce_created_customer', array( $this, 'on_customer_created' ), 5, 1 );
		add_action( 'user_register', array( $this, 'on_user_register' ), 5, 1 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'stamp_order' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'stamp_order' ), 20, 1 );
	}

	/**
	 * Capture ?gp_ref= on the request and set a cookie.
	 *
	 * @return void
	 */
	public function capture() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$param = Ciwp_Settings::get_value( 'referral_param', 'gp_ref' );

		// Public share links cannot carry a nonce. The value is sanitized and ignored unless it matches a stored referral code.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET[ $param ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = strtoupper( sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) );
		if ( '' === $code ) {
			return;
		}

		$referrer_id = Ciwp_Referral_Program::find_referrer_by_code( $code );
		if ( $referrer_id <= 0 ) {
			return;
		}

		if ( is_user_logged_in() && (int) get_current_user_id() === $referrer_id ) {
			return;
		}

		$campaign    = Ciwp_Referral_Program::active_campaign();
		$cookie_days = $campaign ? (int) $campaign->cookie_days : (int) Ciwp_Settings::get_value( 'cookie_days', 30 );
		$expire      = time() + ( max( 1, $cookie_days ) * DAY_IN_SECONDS );

		if ( ! headers_sent() ) {
			setcookie( 'gp_ref', $code, $expire, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		}
		$_COOKIE['gp_ref'] = $code;

		self::log_click( $code, $campaign ? (int) $campaign->id : 0 );
		Ciwp_Referral_Program::upsert( $referrer_id, $code, 0, 'clicked' );
	}

	/**
	 * Log a click.
	 *
	 * @param string $code        Code.
	 * @param int    $campaign_id Campaign ID.
	 * @return void
	 */
	public static function log_click( $code, $campaign_id = 0 ) {
		global $wpdb;

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$wpdb->insert(
			esc_sql( Ciwp::table( 'referral_clicks' ) ),
			array(
				'code'        => $code,
				'campaign_id' => $campaign_id ? $campaign_id : null,
				'visitor_hash'=> hash( 'sha256', $ip . '|' . $ua . '|' . Ciwp::hash_secret() ),
				'landing_url' => esc_url_raw( home_url( add_query_arg( array() ) ) ),
				'created_at'  => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Attribute a new WooCommerce customer.
	 *
	 * @param int $customer_id User ID.
	 * @return void
	 */
	public function on_customer_created( $customer_id ) {
		self::attribute_user( (int) $customer_id );
	}

	/**
	 * Attribute a generic registration.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_user_register( $user_id ) {
		self::attribute_user( (int) $user_id );
	}

	/**
	 * Bind cookie code to a new user.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function attribute_user( $user_id ) {
		$code = self::cookie_code();
		if ( '' === $code ) {
			return;
		}

		$referrer_id = Ciwp_Referral_Program::find_referrer_by_code( $code );
		if ( $referrer_id <= 0 || $referrer_id === $user_id ) {
			return;
		}

		if ( Ciwp_Referral_Program::get_for_referee( $user_id ) ) {
			return;
		}

		Ciwp_Referral_Program::upsert( $referrer_id, $code, $user_id, 'signed_up' );
		update_user_meta( $user_id, 'gp_referred_by', $referrer_id );
		update_user_meta( $user_id, 'gp_referred_code', $code );

		Ciwp_Referral_Rewards::on_signup( $user_id, $referrer_id );
	}

	/**
	 * Stamp checkout order with the referral code.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public function stamp_order( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( $order->get_meta( '_gp_referral_code' ) ) {
			return;
		}

		$code = self::cookie_code();
		if ( '' === $code ) {
			$customer_id = (int) $order->get_customer_id();
			if ( $customer_id ) {
				$code = (string) get_user_meta( $customer_id, 'gp_referred_code', true );
			}
		}

		if ( '' !== $code ) {
			$order->update_meta_data( '_gp_referral_code', $code );
		}
	}

	/**
	 * Current cookie code.
	 *
	 * @return string
	 */
	public static function cookie_code() {
		if ( empty( $_COOKIE['gp_ref'] ) ) {
			return '';
		}

		return strtoupper( sanitize_text_field( wp_unslash( $_COOKIE['gp_ref'] ) ) );
	}
}
