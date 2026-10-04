<?php
/**
 * Plugin Name:       CoSellHive
 * Plugin URI:        https://cosellhive.com
 * Description:       Turn any WooCommerce store into a node in a shared affiliate marketplace. Publish products with commission, let affiliates promote them, track and pay out — without touching fulfillment.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      8.1
 * Author:            Tanmay Kirtania
 * Author URI:        https://jktanmay.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       cosell-hive
 * Domain Path:       /languages
 *
 * @package CoSellHive
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CoSellHive\Core\Plugin;

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

if ( ! class_exists( Plugin::class ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'CoSellHive could not load its files. Please reinstall the plugin.', 'cosell-hive' )
				. '</p></div>';
		}
	);
	return;
}

// Backward-compatibility aliases. The Plugin class constants are the source
// of truth; these globals stay so existing code keeps working. Do not add
// new COSELL_HIVE_* globals.
if ( ! defined( 'COSELL_HIVE_VERSION' ) ) {
	define( 'COSELL_HIVE_VERSION', Plugin::VERSION );
}
if ( ! defined( 'COSELL_HIVE_MIN_PHP' ) ) {
	define( 'COSELL_HIVE_MIN_PHP', Plugin::MIN_PHP );
}
if ( ! defined( 'COSELL_HIVE_MIN_WP' ) ) {
	define( 'COSELL_HIVE_MIN_WP', Plugin::MIN_WP );
}
if ( ! defined( 'COSELL_HIVE_DB_VERSION' ) ) {
	define( 'COSELL_HIVE_DB_VERSION', Plugin::DB_VERSION );
}
if ( ! defined( 'COSELL_HIVE_FILE' ) ) {
	define( 'COSELL_HIVE_FILE', __FILE__ );
}
if ( ! defined( 'COSELL_HIVE_PATH' ) ) {
	define( 'COSELL_HIVE_PATH', dirname( __FILE__ ) );
}
if ( ! defined( 'COSELL_HIVE_INCLUDES' ) ) {
	define( 'COSELL_HIVE_INCLUDES', COSELL_HIVE_PATH . '/includes' );
}
if ( ! defined( 'COSELL_HIVE_MODULES' ) ) {
	define( 'COSELL_HIVE_MODULES', COSELL_HIVE_PATH . '/modules' );
}
if ( ! defined( 'COSELL_HIVE_URL' ) ) {
	define( 'COSELL_HIVE_URL', plugins_url( '', __FILE__ ) );
}
if ( ! defined( 'COSELL_HIVE_ASSETS' ) ) {
	define( 'COSELL_HIVE_ASSETS', COSELL_HIVE_URL . '/assets' );
}

/**
 * Get the plugin instance.
 *
 * @return Plugin|null
 */
function cosell_hive() {
	if ( ! class_exists( Plugin::class ) ) {
		return null;
	}

	return Plugin::init();
}

Plugin::register( __FILE__ );
