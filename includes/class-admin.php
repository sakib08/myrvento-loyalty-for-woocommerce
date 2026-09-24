<?php
/**
 * WordPress admin — React mount.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin menus and assets.
 */
class GrowthPilot_Admin {

	const MENU_SLUG = 'growthpilot';

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
			''              => __( 'Dashboard', 'gp_ppros' ),
			'-points'       => __( 'Points', 'gp_ppros' ),
			'-customers'    => __( 'Customers', 'gp_ppros' ),
			'-tiers'        => __( 'VIP Tiers', 'gp_ppros' ),
			'-rewards'      => __( 'Rewards', 'gp_ppros' ),
			'-gamification' => __( 'Gamification', 'gp_ppros' ),
			'-referrals'    => __( 'Referrals', 'gp_ppros' ),
			'-sales'        => __( 'Sales', 'gp_ppros' ),
			'-operations'   => __( 'Operations', 'gp_ppros' ),
			'-analytics'    => __( 'Analytics', 'gp_ppros' ),
			'-revenue'      => __( 'Revenue', 'gp_ppros' ),
			'-ai'           => __( 'AI', 'gp_ppros' ),
			'-settings'     => __( 'Settings', 'gp_ppros' ),
			'-help'         => __( 'Help', 'gp_ppros' ),
		);

		add_menu_page(
			__( 'GrowthPilot by Ppros', 'gp_ppros' ),
			__( 'GrowthPilot by Ppros', 'gp_ppros' ),
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
		if ( ! $this->is_growthpilot_screen( $screen ) ) {
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

		if ( $screen && $this->is_growthpilot_screen( $screen ) ) {
			$classes .= ' growthpilot-admin-page';
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
		<div id="growthpilot-admin-shell" class="growthpilot-admin-shell">
			<div
				id="growthpilot-admin-root"
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
	private function is_growthpilot_screen( $screen ) {
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

		$js_path  = GROWTHPILOT_PATH . 'assets/admin/growthpilot-admin.js';
		$css_path = GROWTHPILOT_PATH . 'assets/admin/growthpilot-admin.css';

		if ( ! file_exists( $js_path ) ) {
			return;
		}

		$js_version  = filemtime( $js_path );
		$css_version = file_exists( $css_path ) ? filemtime( $css_path ) : GROWTHPILOT_VERSION;

		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'growthpilot-admin',
				GROWTHPILOT_URL . 'assets/admin/growthpilot-admin.css',
				array(),
				$css_version
			);

			wp_add_inline_style(
				'growthpilot-admin',
				'body.growthpilot-admin-page #wpbody-content { padding-bottom: 0; }
body.growthpilot-admin-page #wpbody-content > :not(.growthpilot-admin-shell) { display: none !important; }
body.growthpilot-admin-page .growthpilot-admin-shell { margin: 0; padding: 0; max-width: none; }
body.growthpilot-admin-page .woocommerce-layout__header,
body.growthpilot-admin-page .woo-nav-tab-wrapper,
body.growthpilot-admin-page #screen-meta,
body.growthpilot-admin-page #screen-meta-links { display: none !important; }'
			);
		}

		wp_enqueue_script(
			'growthpilot-admin',
			GROWTHPILOT_URL . 'assets/admin/growthpilot-admin.js',
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
			'growthpilot-admin',
			'growthPilotAdmin',
			array(
				'apiUrl'    => rest_url( 'growthpilot/v1/' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'version'   => GROWTHPILOT_VERSION,
				'page'      => $this->get_current_page(),
				'urls'      => $urls,
				'currency'  => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$',
				'i18n'      => array(
					'pluginName'   => __( 'GrowthPilot by Ppros', 'gp_ppros' ),
					'tagline'      => __( 'Loyalty, sales, and revenue', 'gp_ppros' ),
					'dashboard'    => __( 'Dashboard', 'gp_ppros' ),
					'points'       => __( 'Points', 'gp_ppros' ),
					'customers'    => __( 'Customers', 'gp_ppros' ),
					'tiers'        => __( 'VIP Tiers', 'gp_ppros' ),
					'rewards'      => __( 'Rewards', 'gp_ppros' ),
					'gamification' => __( 'Gamification', 'gp_ppros' ),
					'referrals'    => __( 'Referrals', 'gp_ppros' ),
					'sales'        => __( 'Sales', 'gp_ppros' ),
					'operations'   => __( 'Operations', 'gp_ppros' ),
					'analytics'    => __( 'Analytics', 'gp_ppros' ),
					'revenue'      => __( 'Revenue', 'gp_ppros' ),
					'ai'           => __( 'AI', 'gp_ppros' ),
					'settings'     => __( 'Settings', 'gp_ppros' ),
					'help'         => __( 'Help', 'gp_ppros' ),
					'saved'        => __( 'Saved successfully.', 'gp_ppros' ),
					'saveError'    => __( 'Could not save. Please try again.', 'gp_ppros' ),
				),
			)
		);
	}
}
