<?php
/**
 * Main plugin bootstrap.
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

require_once GROWTHPILOT_PATH . 'includes/class-settings.php';
require_once GROWTHPILOT_PATH . 'includes/class-installer.php';
require_once GROWTHPILOT_PATH . 'includes/class-admin.php';
require_once GROWTHPILOT_PATH . 'includes/class-cron.php';
require_once GROWTHPILOT_PATH . 'includes/class-frontend.php';
require_once GROWTHPILOT_PATH . 'includes/loyalty/class-points-ledger.php';
require_once GROWTHPILOT_PATH . 'includes/loyalty/class-points-rules.php';
require_once GROWTHPILOT_PATH . 'includes/loyalty/class-points-earner.php';
require_once GROWTHPILOT_PATH . 'includes/loyalty/class-vip-tiers.php';
require_once GROWTHPILOT_PATH . 'includes/loyalty/class-rewards.php';
require_once GROWTHPILOT_PATH . 'includes/loyalty/class-gamification.php';
require_once GROWTHPILOT_PATH . 'includes/referral/class-referral-program.php';
require_once GROWTHPILOT_PATH . 'includes/referral/class-referral-tracking.php';
require_once GROWTHPILOT_PATH . 'includes/referral/class-referral-rewards.php';
require_once GROWTHPILOT_PATH . 'includes/referral/class-share.php';
require_once GROWTHPILOT_PATH . 'includes/analytics/class-query.php';
require_once GROWTHPILOT_PATH . 'includes/analytics/class-tracker.php';
require_once GROWTHPILOT_PATH . 'includes/analytics/class-revenue.php';
require_once GROWTHPILOT_PATH . 'includes/analytics/class-customers.php';
require_once GROWTHPILOT_PATH . 'includes/analytics/class-products.php';
require_once GROWTHPILOT_PATH . 'includes/analytics/class-marketing.php';
require_once GROWTHPILOT_PATH . 'includes/sales/class-sales.php';
require_once GROWTHPILOT_PATH . 'includes/operations/class-operations.php';
require_once GROWTHPILOT_PATH . 'includes/class-dashboard.php';
require_once GROWTHPILOT_PATH . 'includes/ai/class-engine.php';
require_once GROWTHPILOT_PATH . 'includes/ai/class-predict.php';
require_once GROWTHPILOT_PATH . 'includes/ai/class-pricing.php';
require_once GROWTHPILOT_PATH . 'includes/ai/class-forecast.php';
require_once GROWTHPILOT_PATH . 'includes/ai/class-brain.php';
require_once GROWTHPILOT_PATH . 'includes/rest/class-rest.php';
require_once GROWTHPILOT_PATH . 'includes/rest/class-rest-points.php';
require_once GROWTHPILOT_PATH . 'includes/rest/class-rest-customers.php';
require_once GROWTHPILOT_PATH . 'includes/rest/class-rest-tiers.php';
require_once GROWTHPILOT_PATH . 'includes/rest/class-rest-rewards.php';
require_once GROWTHPILOT_PATH . 'includes/rest/class-rest-gamification.php';
require_once GROWTHPILOT_PATH . 'includes/rest/class-rest-referrals.php';
require_once GROWTHPILOT_PATH . 'includes/rest/class-rest-settings.php';
require_once GROWTHPILOT_PATH . 'includes/rest/class-rest-account.php';
require_once GROWTHPILOT_PATH . 'includes/rest/class-rest-catalog.php';
require_once GROWTHPILOT_PATH . 'includes/rest/class-rest-analytics.php';
require_once GROWTHPILOT_PATH . 'includes/rest/class-rest-ai.php';
require_once GROWTHPILOT_PATH . 'includes/rest/class-rest-growth.php';

/**
 * Core plugin class.
 */
final class GrowthPilot {

	/**
	 * Singleton instance.
	 *
	 * @var GrowthPilot|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return GrowthPilot
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		register_activation_hook( GROWTHPILOT_FILE, array( 'GrowthPilot_Installer', 'activate' ) );
		register_deactivation_hook( GROWTHPILOT_FILE, array( 'GrowthPilot_Installer', 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Load components after plugins are ready.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_woocommerce_notice' ) );
			return;
		}

		GrowthPilot_Installer::maybe_upgrade();

		new GrowthPilot_Admin();
		new GrowthPilot_REST();
		new GrowthPilot_Frontend();
		new GrowthPilot_Cron();
		new GrowthPilot_Points_Earner();
		new GrowthPilot_Referral_Tracking();
		new GrowthPilot_Gamification();
		new GrowthPilot_Analytics_Tracker();

		add_action(
			'growthpilot_points_changed',
			static function ( $customer_id ) {
				GrowthPilot_VIP_Tiers::evaluate( (int) $customer_id );
			},
			15,
			1
		);

		/**
		 * Later modules (CRM, affiliate, analytics) register here.
		 */
		do_action( 'growthpilot_register_modules' );
	}

	/**
	 * Admin notice when WooCommerce is missing.
	 *
	 * @return void
	 */
	public function missing_woocommerce_notice() {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'GrowthPilot by Ppros requires WooCommerce to be installed and active.', 'gp-ppros' );
		echo '</p></div>';
	}

	/**
	 * Prefixed custom table name.
	 *
	 * @param string $name Short table name without prefix (e.g. points_ledger).
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'gp_' . $name;
	}

	/**
	 * Decode JSON stored in a DB column.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, mixed>
	 */
	public static function decode( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}

		$decoded = json_decode( $value, true );
		return is_array( $decoded ) ? $decoded : array();
	}
}
