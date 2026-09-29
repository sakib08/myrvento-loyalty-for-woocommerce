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
			''              => __( 'Dashboard', 'myrvento-loyalty-for-woocommerce' ),
			'-points'       => __( 'Points', 'myrvento-loyalty-for-woocommerce' ),
			'-customers'    => __( 'Customers', 'myrvento-loyalty-for-woocommerce' ),
			'-tiers'        => __( 'VIP Tiers', 'myrvento-loyalty-for-woocommerce' ),
			'-rewards'      => __( 'Rewards', 'myrvento-loyalty-for-woocommerce' ),
			'-gamification' => __( 'Gamification', 'myrvento-loyalty-for-woocommerce' ),
			'-referrals'    => __( 'Referrals', 'myrvento-loyalty-for-woocommerce' ),
			'-sales'        => __( 'Sales', 'myrvento-loyalty-for-woocommerce' ),
			'-operations'   => __( 'Operations', 'myrvento-loyalty-for-woocommerce' ),
			'-analytics'    => __( 'Analytics', 'myrvento-loyalty-for-woocommerce' ),
			'-revenue'      => __( 'Revenue', 'myrvento-loyalty-for-woocommerce' ),
			'-ai'           => __( 'AI', 'myrvento-loyalty-for-woocommerce' ),
			'-settings'     => __( 'Settings', 'myrvento-loyalty-for-woocommerce' ),
			'-help'         => __( 'Help', 'myrvento-loyalty-for-woocommerce' ),
		);

		add_menu_page(
			__( 'Myrvento Loyalty for WooCommerce', 'myrvento-loyalty-for-woocommerce' ),
			__( 'Myrvento Loyalty for WooCommerce', 'myrvento-loyalty-for-woocommerce' ),
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
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

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
					'pluginName'   => __( 'Myrvento Loyalty for WooCommerce', 'myrvento-loyalty-for-woocommerce' ),
					'tagline'      => __( 'Loyalty, sales, and revenue', 'myrvento-loyalty-for-woocommerce' ),
					'dashboard'    => __( 'Dashboard', 'myrvento-loyalty-for-woocommerce' ),
					'points'       => __( 'Points', 'myrvento-loyalty-for-woocommerce' ),
					'customers'    => __( 'Customers', 'myrvento-loyalty-for-woocommerce' ),
					'tiers'        => __( 'VIP Tiers', 'myrvento-loyalty-for-woocommerce' ),
					'rewards'      => __( 'Rewards', 'myrvento-loyalty-for-woocommerce' ),
					'gamification' => __( 'Gamification', 'myrvento-loyalty-for-woocommerce' ),
					'referrals'    => __( 'Referrals', 'myrvento-loyalty-for-woocommerce' ),
					'sales'        => __( 'Sales', 'myrvento-loyalty-for-woocommerce' ),
					'operations'   => __( 'Operations', 'myrvento-loyalty-for-woocommerce' ),
					'analytics'    => __( 'Analytics', 'myrvento-loyalty-for-woocommerce' ),
					'revenue'      => __( 'Revenue', 'myrvento-loyalty-for-woocommerce' ),
					'ai'           => __( 'AI', 'myrvento-loyalty-for-woocommerce' ),
					'settings'     => __( 'Settings', 'myrvento-loyalty-for-woocommerce' ),
					'help'         => __( 'Help', 'myrvento-loyalty-for-woocommerce' ),
					'saved'        => __( 'Saved successfully.', 'myrvento-loyalty-for-woocommerce' ),
					'saveError'    => __( 'Could not save. Please try again.', 'myrvento-loyalty-for-woocommerce' ),
				),
			)
		);
	}
}
