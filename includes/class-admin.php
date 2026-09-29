<?php
/**
 * WordPress admin — React mount.
 *
 * @package Ciwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin menus and assets.
 */
class Ciwp_Admin {

	const MENU_SLUG = 'ciwp';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'current_screen', array( $this, 'setup_admin_screen' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register admin pages.
	 *
	 * @return void
	 */
	public function register_menus() {
		$pages = array(
			''              => __( 'Dashboard', 'commerce-insights-woocommerce-by-ppros' ),
			'-points'       => __( 'Points', 'commerce-insights-woocommerce-by-ppros' ),
			'-customers'    => __( 'Customers', 'commerce-insights-woocommerce-by-ppros' ),
			'-tiers'        => __( 'VIP Tiers', 'commerce-insights-woocommerce-by-ppros' ),
			'-rewards'      => __( 'Rewards', 'commerce-insights-woocommerce-by-ppros' ),
			'-gamification' => __( 'Gamification', 'commerce-insights-woocommerce-by-ppros' ),
			'-referrals'    => __( 'Referrals', 'commerce-insights-woocommerce-by-ppros' ),
			'-sales'        => __( 'Sales', 'commerce-insights-woocommerce-by-ppros' ),
			'-operations'   => __( 'Operations', 'commerce-insights-woocommerce-by-ppros' ),
			'-analytics'    => __( 'Analytics', 'commerce-insights-woocommerce-by-ppros' ),
			'-revenue'      => __( 'Revenue', 'commerce-insights-woocommerce-by-ppros' ),
			'-ai'           => __( 'AI', 'commerce-insights-woocommerce-by-ppros' ),
			'-settings'     => __( 'Settings', 'commerce-insights-woocommerce-by-ppros' ),
			'-help'         => __( 'Help', 'commerce-insights-woocommerce-by-ppros' ),
		);

		add_menu_page(
			__( 'Commerce Insights for WooCommerce by Ppros', 'commerce-insights-woocommerce-by-ppros' ),
			__( 'Commerce Insights for WooCommerce by Ppros', 'commerce-insights-woocommerce-by-ppros' ),
			'manage_woocommerce',
			self::MENU_SLUG,
			array( $this, 'render_admin_page' ),
			'dashicons-chart-line',
			56
		);

		foreach ( $pages as $suffix => $label ) {
			$slug = self::MENU_SLUG . $suffix;
			add_submenu_page(
				self::MENU_SLUG,
				$label,
				$label,
				'manage_woocommerce',
				$slug,
				array( $this, 'render_admin_page' )
			);
		}
	}

	/**
	 * Strip competing admin notices on our screens.
	 *
	 * @param WP_Screen $screen Current screen.
	 * @return void
	 */
	public function setup_admin_screen( $screen ) {
		if ( ! $this->is_ciwp_screen( $screen ) ) {
			return;
		}

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
	}

	/**
	 * Body class for scoped CSS.
	 *
	 * @param string $classes Body classes.
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen && $this->is_ciwp_screen( $screen ) ) {
			$classes .= ' ciwp-admin-page';
		}

		return $classes;
	}

	/**
	 * React mount point.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		$page = $this->get_current_page();
		?>
		<div id="ciwp-admin-shell" class="ciwp-admin-shell">
			<div
				id="ciwp-admin-root"
				data-page="<?php echo esc_attr( $page ); ?>"
			></div>
		</div>
		<?php
	}

	/**
	 * Map WP menu slug to React page id.
	 *
	 * @return string
	 */
	private function get_current_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : self::MENU_SLUG;

		$map = array(
			self::MENU_SLUG . '-points'       => 'points',
			self::MENU_SLUG . '-customers'    => 'customers',
			self::MENU_SLUG . '-tiers'        => 'tiers',
			self::MENU_SLUG . '-rewards'      => 'rewards',
			self::MENU_SLUG . '-gamification' => 'gamification',
			self::MENU_SLUG . '-referrals'    => 'referrals',
			self::MENU_SLUG . '-sales'        => 'sales',
			self::MENU_SLUG . '-operations'   => 'operations',
			self::MENU_SLUG . '-analytics'    => 'analytics',
			self::MENU_SLUG . '-revenue'      => 'revenue',
			self::MENU_SLUG . '-ai'           => 'ai',
			self::MENU_SLUG . '-settings'     => 'settings',
			self::MENU_SLUG . '-help'         => 'help',
		);

		return $map[ $page ] ?? 'dashboard';
	}

	/**
	 * Whether the WP screen belongs to this plugin.
	 *
	 * @param WP_Screen $screen Screen.
	 * @return bool
	 */
	private function is_ciwp_screen( $screen ) {
		return $screen && false !== strpos( $screen->id, self::MENU_SLUG );
	}

	/**
	 * Enqueue React admin bundle.
	 *
	 * @param string $hook Admin hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}

		$js_path  = CIWP_PATH . 'assets/admin/ciwp-admin.js';
		$css_path = CIWP_PATH . 'assets/admin/ciwp-admin.css';

		if ( ! file_exists( $js_path ) ) {
			return;
		}

		$js_version  = filemtime( $js_path );
		$css_version = file_exists( $css_path ) ? filemtime( $css_path ) : CIWP_VERSION;

		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'ciwp-admin',
				CIWP_URL . 'assets/admin/ciwp-admin.css',
				array(),
				$css_version
			);

			wp_add_inline_style(
				'ciwp-admin',
				'body.ciwp-admin-page #wpbody-content { padding-bottom: 0; }
body.ciwp-admin-page #wpbody-content > :not(.ciwp-admin-shell) { display: none !important; }
body.ciwp-admin-page .ciwp-admin-shell { margin: 0; padding: 0; max-width: none; }
body.ciwp-admin-page .woocommerce-layout__header,
body.ciwp-admin-page .woo-nav-tab-wrapper,
body.ciwp-admin-page #screen-meta,
body.ciwp-admin-page #screen-meta-links { display: none !important; }'
			);
		}

		wp_enqueue_script(
			'ciwp-admin',
			CIWP_URL . 'assets/admin/ciwp-admin.js',
			array(),
			$js_version,
			true
		);

		$urls = array();
		foreach ( array( '', '-points', '-customers', '-tiers', '-rewards', '-gamification', '-referrals', '-sales', '-operations', '-analytics', '-revenue', '-ai', '-settings', '-help' ) as $suffix ) {
			$key          = '' === $suffix ? 'dashboard' : ltrim( $suffix, '-' );
			$urls[ $key ] = admin_url( 'admin.php?page=' . self::MENU_SLUG . $suffix );
		}

		wp_localize_script(
			'ciwp-admin',
			'ciwpAdmin',
			array(
				'apiUrl'    => rest_url( 'ciwp/v1/' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'version'   => CIWP_VERSION,
				'page'      => $this->get_current_page(),
				'urls'      => $urls,
				'currency'  => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$',
				'i18n'      => array(
					'pluginName'   => __( 'Commerce Insights for WooCommerce by Ppros', 'commerce-insights-woocommerce-by-ppros' ),
					'tagline'      => __( 'Loyalty, sales, and revenue', 'commerce-insights-woocommerce-by-ppros' ),
					'dashboard'    => __( 'Dashboard', 'commerce-insights-woocommerce-by-ppros' ),
					'points'       => __( 'Points', 'commerce-insights-woocommerce-by-ppros' ),
					'customers'    => __( 'Customers', 'commerce-insights-woocommerce-by-ppros' ),
					'tiers'        => __( 'VIP Tiers', 'commerce-insights-woocommerce-by-ppros' ),
					'rewards'      => __( 'Rewards', 'commerce-insights-woocommerce-by-ppros' ),
					'gamification' => __( 'Gamification', 'commerce-insights-woocommerce-by-ppros' ),
					'referrals'    => __( 'Referrals', 'commerce-insights-woocommerce-by-ppros' ),
					'sales'        => __( 'Sales', 'commerce-insights-woocommerce-by-ppros' ),
					'operations'   => __( 'Operations', 'commerce-insights-woocommerce-by-ppros' ),
					'analytics'    => __( 'Analytics', 'commerce-insights-woocommerce-by-ppros' ),
					'revenue'      => __( 'Revenue', 'commerce-insights-woocommerce-by-ppros' ),
					'ai'           => __( 'AI', 'commerce-insights-woocommerce-by-ppros' ),
					'settings'     => __( 'Settings', 'commerce-insights-woocommerce-by-ppros' ),
					'help'         => __( 'Help', 'commerce-insights-woocommerce-by-ppros' ),
					'saved'        => __( 'Saved successfully.', 'commerce-insights-woocommerce-by-ppros' ),
					'saveError'    => __( 'Could not save. Please try again.', 'commerce-insights-woocommerce-by-ppros' ),
				),
			)
		);
	}
}
