<?php
/**
 * Uninstall handler.
 *
 * @package CoSellHive
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = array(
	'cosell_hive_version',
	'cosell_hive_db_version',
	'cosell_hive_settings',
	'cosell_hive_license',
	'cosell_hive_license_status',
	'cosell_hive_site_role',
	'cosell_hive_onboarded',
);

foreach ( $options as $option ) {
	delete_option( $option );
	delete_site_option( $option );
}
