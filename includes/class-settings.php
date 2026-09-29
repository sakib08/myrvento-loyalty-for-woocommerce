<?php
/**
 * Plugin settings.
 *
 * @package Myrvento
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings option helper.
 */
class Myrvento_Settings {

	const OPTION = 'myrvento_settings';

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'earn_order_status'          => 'completed',
			'points_name'                => __( 'Points', 'myrvento-loyalty-for-woocommerce' ),
			'cookie_days'                => 30,
			'expiration_days'            => 0,
			'downgrade_enabled'          => true,
			'downgrade_window_days'      => 365,
			'myaccount_loyalty_label'    => __( 'Loyalty', 'myrvento-loyalty-for-woocommerce' ),
			'myaccount_referrals_label'  => __( 'Referrals', 'myrvento-loyalty-for-woocommerce' ),
			'social_once'                => true,
			'referral_param'             => 'myrvento_ref',
			'ai_enabled'                 => true,
			'ai_llm_enabled'             => false,
		);
	}

	/**
	 * Get merged settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings = array_intersect_key( wp_parse_args( $stored, self::defaults() ), self::defaults() );

		if ( isset( $stored['ai_api_key'] ) || isset( $stored['ai_api_base'] ) || isset( $stored['ai_model'] ) ) {
			update_option( self::OPTION, $settings, false );
		}

		return $settings;
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get_value( $key, $default = null ) {
		$settings = self::get();

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		return $default;
	}

	/**
	 * Persist settings.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return array<string, mixed>
	 */
	public static function update( $settings ) {
		$merged = wp_parse_args( $settings, self::get() );
		$clean  = array();

		foreach ( self::defaults() as $key => $default ) {
			if ( ! array_key_exists( $key, $merged ) ) {
				$clean[ $key ] = $default;
				continue;
			}

			$value = $merged[ $key ];

			if ( is_bool( $default ) ) {
				$clean[ $key ] = (bool) $value;
			} elseif ( is_int( $default ) ) {
				$clean[ $key ] = (int) $value;
			} else {
				$clean[ $key ] = is_string( $value ) ? sanitize_text_field( $value ) : $default;
			}
		}

		update_option( self::OPTION, $clean, false );

		return self::for_admin();
	}

	/**
	 * Settings for the admin REST.
	 *
	 * @return array<string, mixed>
	 */
	public static function for_admin() {
		$settings                        = self::get();
		$settings['ai_client_available'] = function_exists( 'wp_ai_client_prompt' ) && function_exists( 'wp_supports_ai' ) && wp_supports_ai();
		return $settings;
	}
}
