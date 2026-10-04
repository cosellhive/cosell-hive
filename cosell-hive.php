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
define( 'COSELL_HIVE_MIN_WP', '6.3' );
define( 'COSELL_HIVE_DB_VERSION', '1.0.0' );

/**
 * Initialize the plugin.
 *
 * @return Plugin|null
 */
function cosell_hive() {
	if ( ! class_exists( Plugin::class ) ) {
		return null;
	}

	return Plugin::init();
}

/**
 * Render a bootstrap failure notice.
 *
 * @return void
 */
function cosell_hive_bootstrap_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>' . esc_html__( 'CoSellHive could not load its files. Please reinstall the plugin.', 'cosell-hive' ) . '</p></div>';
}

/**
 * Guard the runtime environment before booting.
 *
 * @return bool
 */
function cosell_hive_environment_supported() {
	global $wp_version;

	if ( version_compare( PHP_VERSION, COSELL_HIVE_MIN_PHP, '<' ) ) {
		return false;
	}

	if ( isset( $wp_version ) && version_compare( $wp_version, COSELL_HIVE_MIN_WP, '<' ) ) {
		return false;
	}

	return true;
}

/**
 * Boot the plugin on plugins_loaded.
 *
 * @return void
 */
function cosell_hive_bootstrap() {
	if ( ! cosell_hive_environment_supported() ) {
		add_action( 'admin_notices', 'cosell_hive_bootstrap_notice' );
		return;
	}

	if ( ! class_exists( Plugin::class ) ) {
		add_action( 'admin_notices', 'cosell_hive_bootstrap_notice' );
		return;
	}

	cosell_hive();
}

/**
 * Render the network activation notice.
 *
 * @return void
 */
function cosell_hive_network_notice() {
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'CoSellHive must be activated individually on each site. Network-wide activation is not supported.', 'cosell-hive' ) . '</p></div>';
}

/**
 * Activation callback.
 *
 * @param bool $network_wide Whether the plugin is being network-activated.
 * @return void
 */
function cosell_hive_activate( $network_wide = false ) {
	if ( $network_wide ) {
		update_site_option( 'cosell_hive_network_activation_notice', 1 );
		return;
	}

	$installer = new Installer();
	$installer->activate();
}

/**
 * Deactivation callback.
 *
 * @return void
 */
function cosell_hive_deactivate() {
	$installer = new Installer();
	$installer->deactivate();
}

add_action( 'plugins_loaded', 'cosell_hive_bootstrap', 1 );

register_activation_hook( __FILE__, 'cosell_hive_activate' );
register_deactivation_hook( __FILE__, 'cosell_hive_deactivate' );
