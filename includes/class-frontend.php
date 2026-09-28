<?php
/**
 * Storefront — My Account endpoints, shortcodes, assets.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Frontend.
 */
class GrowthPilot_Frontend {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( 'GrowthPilot_Installer', 'register_endpoints' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu_items' ) );
		add_action( 'woocommerce_account_loyalty_endpoint', array( $this, 'render_loyalty' ) );
		add_action( 'woocommerce_account_referrals_endpoint', array( $this, 'render_referrals' ) );
		add_filter( 'the_title', array( $this, 'endpoint_title' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_shortcode( 'growthpilot_loyalty', array( $this, 'shortcode_loyalty' ) );
		add_shortcode( 'growthpilot_referral', array( $this, 'shortcode_referral' ) );
	}

	/**
	 * Add My Account menu items.
	 *
	 * @param array<string, string> $items Menu items.
	 * @return array<string, string>
	 */
	public function menu_items( $items ) {
		$settings = GrowthPilot_Settings::get();
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
		$settings = GrowthPilot_Settings::get();

		if ( isset( $wp->query_vars['loyalty'] ) ) {
			return $settings['myaccount_loyalty_label'];
		}

		if ( isset( $wp->query_vars['referrals'] ) ) {
			return $settings['myaccount_referrals_label'];
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

		$js  = GROWTHPILOT_PATH . 'assets/frontend/growthpilot.js';
		$css = GROWTHPILOT_PATH . 'assets/frontend/growthpilot.css';

		if ( file_exists( $css ) ) {
			wp_enqueue_style(
				'growthpilot',
				GROWTHPILOT_URL . 'assets/frontend/growthpilot.css',
				array(),
				filemtime( $css )
			);
		}

		if ( file_exists( $js ) ) {
			wp_enqueue_script(
				'growthpilot',
				GROWTHPILOT_URL . 'assets/frontend/growthpilot.js',
				array(),
				filemtime( $js ),
				true
			);

			wp_localize_script(
				'growthpilot',
				'growthPilotFrontend',
				array(
					'apiUrl' => rest_url( 'growthpilot/v1/' ),
					'nonce'  => wp_create_nonce( 'wp_rest' ),
					'i18n'   => array(
						'copied' => __( 'Copied!', 'gp-ppros' ),
						'copy'   => __( 'Copy link', 'gp-ppros' ),
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
		if ( $post instanceof WP_Post && ( has_shortcode( $post->post_content, 'growthpilot_loyalty' ) || has_shortcode( $post->post_content, 'growthpilot_referral' ) ) ) {
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
			return '<p>' . esc_html__( 'Please log in to view your loyalty account.', 'gp-ppros' ) . '</p>';
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
			return '<p>' . esc_html__( 'Please log in to view your referral link.', 'gp-ppros' ) . '</p>';
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
		$path = GROWTHPILOT_PATH . 'templates/myaccount/' . $file;
		if ( file_exists( $path ) ) {
			include $path;
		}
	}
}
