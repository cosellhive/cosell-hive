<?php
/**
 * Uninstall handler.
 *
 * Self-contained: does not use the plugin bootstrap or autoloader.
 * Always clears transients and cron. Full data removal only when the
 * `delete_data_on_uninstall` setting is on (read before deleting options).
 *
 * @package CoSellHive
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$schema_file = __DIR__ . '/includes/Core/Schema.php';

if ( file_exists( $schema_file ) ) {
	require_once $schema_file;
}

$suffixes = array( 'cosell_listings', 'cosell_clicks', 'cosell_commissions', 'cosell_payouts' );

if ( class_exists( '\CoSellHive\Core\Schema' ) ) {
	$suffixes = \CoSellHive\Core\Schema::TABLE_SUFFIXES;
}

$cron_hooks = array( 'cosell_hive_daily_maintenance', 'cosell_hive_daily_heartbeat' );

if ( class_exists( '\CoSellHive\Core\Schema' ) ) {
	$cron_hooks = \CoSellHive\Core\Schema::CRON_HOOKS;
}

/**
 * Run uninstall for one site.
 *
 * @param array $suffixes   Table suffixes.
 * @param array $cron_hooks Cron hooks.
 * @return void
 */
function cosell_hive_uninstall_site( $suffixes, $cron_hooks ) {
	global $wpdb;

	foreach ( $cron_hooks as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}

	$like = '%' . $wpdb->esc_like( '_transient_cosell_hive_' ) . '%';
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
			$like,
			'%' . $wpdb->esc_like( '_transient_timeout_cosell_hive_' ) . '%'
		)
	);

	$settings = get_option( 'cosell_hive_settings', array() );
	$remove   = is_array( $settings ) && ! empty( $settings['delete_data_on_uninstall'] );

	if ( ! $remove ) {
		return;
	}

	foreach ( $suffixes as $suffix ) {
		$table = $wpdb->prefix . $suffix;
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
			$wpdb->esc_like( 'cosell_hive_' ) . '%'
		)
	);

	$meta_like = $wpdb->esc_like( '_cosell_hive_' ) . '%';

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
			$meta_like
		)
	);

	$orders_meta = $wpdb->prefix . 'wc_orders_meta';

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $orders_meta ) ) === $orders_meta ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$orders_meta}` WHERE meta_key LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
				$meta_like
			)
		);
	}

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
			$meta_like
		)
	);

	remove_role( 'cs_hive_store' );
	remove_role( 'cs_hive_affiliate' );
	remove_role( 'ch_store' );
	remove_role( 'ch_affiliate' );
}

if ( function_exists( 'is_multisite' ) && is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $sites as $blog_id ) {
		switch_to_blog( $blog_id );
		cosell_hive_uninstall_site( $suffixes, $cron_hooks );
		restore_current_blog();
	}

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
			$wpdb->esc_like( 'cosell_hive_' ) . '%'
		)
	);

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
			$wpdb->esc_like( '_site_transient_cosell_hive_' ) . '%',
			$wpdb->esc_like( '_site_transient_timeout_cosell_hive_' ) . '%'
		)
	);
} else {
	cosell_hive_uninstall_site( $suffixes, $cron_hooks );
}
