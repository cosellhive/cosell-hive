<?php
/**
 * Plugin Name:       CoSellHive
 * Description:       Turn any WooCommerce store into a node in a shared affiliate marketplace. Publish products with commission, let affiliates promote them, track and pay out — without touching fulfillment.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            CoSellHive
 * Author URI:        https://cosellhive.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
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

define( 'COSELL_HIVE_VERSION', '0.1.0' );
define( 'COSELL_HIVE_FILE', __FILE__ );
define( 'COSELL_HIVE_PATH', dirname( COSELL_HIVE_FILE ) );
define( 'COSELL_HIVE_INCLUDES', COSELL_HIVE_PATH . '/includes' );
define( 'COSELL_HIVE_MODULES', COSELL_HIVE_PATH . '/modules' );
define( 'COSELL_HIVE_URL', plugins_url( '', COSELL_HIVE_FILE ) );
define( 'COSELL_HIVE_ASSETS', COSELL_HIVE_URL . '/assets' );
define( 'COSELL_HIVE_MIN_PHP', '7.4' );
define( 'COSELL_HIVE_DB_VERSION', '0.1.0' );

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
