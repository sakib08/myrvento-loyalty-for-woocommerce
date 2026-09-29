<?php
/**
 * Plugin Name:       Myrvento Loyalty for WooCommerce
 * Plugin URI:        https://pluginpros.co
 * Description:       WooCommerce loyalty, sales, operations, analytics, revenue intelligence, and an on-store AI commerce brain.
 * Version:           0.1.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            sakibbd08
 * Author URI:        https://profiles.wordpress.org/sakibbd08/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       myrvento-loyalty-for-woocommerce
 *
 * @package Myrvento
 */

defined( 'ABSPATH' ) || exit;

define( 'MYRVENTO_VERSION', '0.1.1' );
define( 'MYRVENTO_DB_VERSION', '4' );
define( 'MYRVENTO_FILE', __FILE__ );
define( 'MYRVENTO_PATH', plugin_dir_path( __FILE__ ) );
define( 'MYRVENTO_URL', plugin_dir_url( __FILE__ ) );

require_once MYRVENTO_PATH . 'includes/class-myrvento.php';

/**
 * Bootstrap Myrvento Loyalty for WooCommerce.
 *
 * @return Myrvento
 */
function myrvento() {
	return Myrvento::instance();
}

myrvento();
