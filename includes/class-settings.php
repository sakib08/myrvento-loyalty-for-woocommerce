<?php
/**
 * Plugin settings.
 *
 * @package Ciwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings option helper.
 */
class Ciwp_Settings {

	const OPTION = 'ciwp_settings';

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'earn_order_status'          => 'completed',
			'points_name'                => __( 'Points', 'commerce-insights-woocommerce-by-ppros' ),
			'cookie_days'                => 30,
			'expiration_days'            => 0,
			'downgrade_enabled'          => true,
			'downgrade_window_days'      => 365,
			'myaccount_loyalty_label'    => __( 'Loyalty', 'commerce-insights-woocommerce-by-ppros' ),
			'myaccount_referrals_label'  => __( 'Referrals', 'commerce-insights-woocommerce-by-ppros' ),
			'social_once'                => true,
			'referral_param'             => 'gp_ref',
			'ai_enabled'                 => true,
			'ai_llm_enabled'             => false,
			'ai_api_key'                 => '',
			'ai_api_base'                => 'https://api.openai.com/v1', // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- Optional admin endpoint. Requires WordPress 6.0, before wp_ai_client_prompt().
			'ai_model'                   => 'gpt-4o-mini',
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

		return wp_parse_args( $stored, self::defaults() );
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
			if ( 'ai_api_key' === $key ) {
				continue;
			}

			if ( ! array_key_exists( $key, $merged ) ) {
				$clean[ $key ] = $default;
				continue;
			}

			$value = $merged[ $key ];

			if ( is_bool( $default ) ) {
				$clean[ $key ] = (bool) $value;
			} elseif ( is_int( $default ) ) {
				$clean[ $key ] = (int) $value;
			} elseif ( 'ai_api_base' === $key ) {
				$url            = esc_url_raw( (string) $value );
				$clean[ $key ] = $url ? untrailingslashit( $url ) : $default;
			} else {
				$clean[ $key ] = is_string( $value ) ? sanitize_text_field( $value ) : $default;
			}
		}

		$incoming = isset( $merged['ai_api_key'] ) ? (string) $merged['ai_api_key'] : '';
		if ( ! empty( $merged['ai_clear_key'] ) ) {
			$clean['ai_api_key'] = '';
		} elseif ( '' === $incoming || '********' === $incoming ) {
			$clean['ai_api_key'] = (string) self::get_value( 'ai_api_key', '' );
		} else {
			$clean['ai_api_key'] = sanitize_text_field( $incoming );
		}

		update_option( self::OPTION, $clean, false );

		return self::for_admin();
	}

	/**
	 * Settings for the admin REST (API key masked).
	 *
	 * @return array<string, mixed>
	 */
	public static function for_admin() {
		$settings                    = self::get();
		$settings['ai_api_key_set']  = '' !== (string) $settings['ai_api_key'];
		$settings['ai_api_key']      = $settings['ai_api_key_set'] ? '********' : '';
		return $settings;
	}
}
