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
use CoSellHive\Core\Installer;

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

define( 'COSELL_HIVE_VERSION', '1.0.0' );
define( 'COSELL_HIVE_FILE', __FILE__ );
define( 'COSELL_HIVE_PATH', dirname( COSELL_HIVE_FILE ) );
define( 'COSELL_HIVE_INCLUDES', COSELL_HIVE_PATH . '/includes' );
define( 'COSELL_HIVE_MODULES', COSELL_HIVE_PATH . '/modules' );
define( 'COSELL_HIVE_URL', plugins_url( '', COSELL_HIVE_FILE ) );
define( 'COSELL_HIVE_ASSETS', COSELL_HIVE_URL . '/assets' );
define( 'COSELL_HIVE_MIN_PHP', '8.1' );
define( 'COSELL_HIVE_DB_VERSION', '1.0.0' );

/**
 * Initialize the plugin.
 *
 * @return Plugin
 */
function cosell_hive() {
	return Plugin::init();
}

add_action(
	'plugins_loaded',
	function () {
		cosell_hive();
	},
	1
);

register_activation_hook(
	__FILE__,
	function () {
		$installer = new Installer();
		$installer->activate();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		$installer = new Installer();
		$installer->deactivate();
	}
);
