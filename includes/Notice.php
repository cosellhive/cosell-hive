<?php
/**
 * Admin notices: environment, bootstrap and activation states.
 *
 * @package CoSellHive
 */

namespace CoSellHive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns every admin notice the plugin renders.
 *
 * Extracted from the bootstrap file and the Plugin class so notice copy
 * lives beside each other. Hook names, capabilities and text domain are
 * unchanged from when these were standalone functions.
 */
final class Notice {

	/**
	 * Render a bootstrap failure notice.
	 *
	 * @return void
	 */
	public static function files_missing() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>' . esc_html__( 'CoSellHive could not load its files. Please reinstall the plugin.', 'cosell-hive' ) . '</p></div>';
	}

	/**
	 * Render the network activation notice.
	 *
	 * @return void
	 */
	public static function network_activation() {
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'CoSellHive must be activated individually on each site. Network-wide activation is not supported.', 'cosell-hive' ) . '</p></div>';
	}

	/**
	 * Render the "active but not installed" notice.
	 *
	 * @return void
	 */
	public static function missing_install() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'CoSellHive is active on this site but was not installed here. Please deactivate it and activate it individually on each site.', 'cosell-hive' )
		);
	}
}
