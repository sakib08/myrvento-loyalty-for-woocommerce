<?php
/**
 * Storefront — My Account endpoints, shortcodes, assets.
 *
 * @package Myrvento
 */

defined( 'ABSPATH' ) || exit;

/**
 * Frontend.
 */
class Myrvento_Frontend {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( 'Myrvento_Installer', 'register_endpoints' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu_items' ) );
		add_action( 'woocommerce_account_loyalty_endpoint', array( $this, 'render_loyalty' ) );
		add_action( 'woocommerce_account_referrals_endpoint', array( $this, 'render_referrals' ) );
		add_filter( 'the_title', array( $this, 'endpoint_title' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_shortcode( 'myrvento_loyalty', array( $this, 'shortcode_loyalty' ) );
		add_shortcode( 'myrvento_referral', array( $this, 'shortcode_referral' ) );
	}

	/**
	 * Add My Account menu items.
	 *
	 * @param array<string, string> $items Menu items.
	 * @return array<string, string>
	 */
	public function menu_items( $items ) {
		$settings = Myrvento_Settings::get();
		$new      = array();

		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'orders' === $key ) {
				$new['loyalty']   = $settings['myaccount_loyalty_label'];
				$new['referrals'] = $settings['myaccount_referrals_label'];
			}
		}

		if ( ! isset( $new['loyalty'] ) ) {
			$new['loyalty']   = $settings['myaccount_loyalty_label'];
			$new['referrals'] = $settings['myaccount_referrals_label'];
		}

		return $new;
	}

	/**
	 * Endpoint titles.
	 *
	 * @param string $title Title.
	 * @param int    $id    Post ID.
	 * @return string
	 */
	public function endpoint_title( $title, $id = 0 ) {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() || is_admin() ) {
			return $title;
		}

		global $wp;
		$settings = Myrvento_Settings::get();

		if ( isset( $wp->query_vars['loyalty'] ) ) {
			return esc_html( $settings['myaccount_loyalty_label'] );
		}

		if ( isset( $wp->query_vars['referrals'] ) ) {
			return esc_html( $settings['myaccount_referrals_label'] );
		}

		return $title;
	}

	/**
	 * Enqueue storefront assets.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! $this->should_enqueue() ) {
			return;
		}

		$js  = MYRVENTO_PATH . 'assets/frontend/myrvento.js';
		$css = MYRVENTO_PATH . 'assets/frontend/myrvento.css';

		if ( file_exists( $css ) ) {
			wp_enqueue_style(
				'myrvento',
				MYRVENTO_URL . 'assets/frontend/myrvento.css',
				array(),
				filemtime( $css )
			);
		}

		if ( file_exists( $js ) ) {
			wp_enqueue_script(
				'myrvento',
				MYRVENTO_URL . 'assets/frontend/myrvento.js',
				array(),
				filemtime( $js ),
				true
			);

			wp_localize_script(
				'myrvento',
				'myrventoFrontend',
				array(
					'apiUrl' => rest_url( 'myrvento/v1/' ),
					'nonce'  => wp_create_nonce( 'wp_rest' ),
					'i18n'   => array(
						'copied' => __( 'Copied!', 'myrvento-loyalty-for-woocommerce' ),
						'copy'   => __( 'Copy link', 'myrvento-loyalty-for-woocommerce' ),
					),
				)
			);
		}
	}

	/**
	 * Whether to load frontend assets.
	 *
	 * @return bool
	 */
	private function should_enqueue() {
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return true;
		}

		global $post;
		if ( $post instanceof WP_Post && ( has_shortcode( $post->post_content, 'myrvento_loyalty' ) || has_shortcode( $post->post_content, 'myrvento_referral' ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Loyalty endpoint.
	 *
	 * @return void
	 */
	public function render_loyalty() {
		$this->load_template( 'loyalty.php' );
	}

	/**
	 * Referrals endpoint.
	 *
	 * @return void
	 */
	public function render_referrals() {
		$this->load_template( 'referrals.php' );
	}

	/**
	 * Loyalty shortcode.
	 *
	 * @return string
	 */
	public function shortcode_loyalty() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your loyalty account.', 'myrvento-loyalty-for-woocommerce' ) . '</p>';
		}

		ob_start();
		$this->load_template( 'loyalty.php' );
		return (string) ob_get_clean();
	}

	/**
	 * Referral shortcode.
	 *
	 * @return string
	 */
	public function shortcode_referral() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your referral link.', 'myrvento-loyalty-for-woocommerce' ) . '</p>';
		}

		ob_start();
		$this->load_template( 'referrals.php' );
		return (string) ob_get_clean();
	}

	/**
	 * Load a My Account template.
	 *
	 * @param string $file File name.
	 * @return void
	 */
	private function load_template( $file ) {
		$path = MYRVENTO_PATH . 'templates/myaccount/' . $file;
		if ( file_exists( $path ) ) {
			include $path;
		}
	}
}
