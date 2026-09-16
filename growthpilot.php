<?php
/**
 * Plugin Name:       GrowthPilot
 * Plugin URI:        https://pluginpros.co
 * Description:       WooCommerce loyalty, referrals, analytics, and an on-store AI commerce brain.
 * Version:           0.3.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            sakibbd08
 * Author URI:        https://profiles.wordpress.org/sakibbd08/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       growthpilot
 * Domain Path:       /languages
 *
 * @package GrowthPilot
 */

defined( 'ABSPATH' ) || exit;

define( 'GROWTHPILOT_VERSION', '0.3.0' );
define( 'GROWTHPILOT_DB_VERSION', '3' );
define( 'GROWTHPILOT_FILE', __FILE__ );
define( 'GROWTHPILOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'GROWTHPILOT_URL', plugin_dir_url( __FILE__ ) );

require_once GROWTHPILOT_PATH . 'includes/class-growthpilot.php';

/**
 * Bootstrap GrowthPilot.
 *
 * @return GrowthPilot
 */
function growthpilot() {
	return GrowthPilot::instance();
}

growthpilot();
