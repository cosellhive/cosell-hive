<?php
/**
 * Canonical schema identifiers.
 *
 * Single source of truth for table suffixes, cron hooks, and option/meta
 * prefixes. `uninstall.php` requires this file directly (no autoloader).
 *
 * @package CoSellHive
 */

namespace CoSellHive\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema identifiers shared by repositories and uninstall.
 */
final class Schema {

	/**
	 * Custom table suffixes (without `$wpdb->prefix`).
	 *
	 * @var array
	 */
	const TABLE_SUFFIXES = array(
		'cosell_listings',
		'cosell_clicks',
		'cosell_commissions',
		'cosell_payouts',
	);

	const TABLE_LISTINGS    = 'cosell_listings';
	const TABLE_CLICKS      = 'cosell_clicks';
	const TABLE_COMMISSIONS = 'cosell_commissions';
	const TABLE_PAYOUTS     = 'cosell_payouts';

	/**
	 * Cron hooks registered by the plugin.
	 *
	 * @var array
	 */
	const CRON_HOOKS = array(
		'cosell_hive_daily_maintenance',
		'cosell_hive_daily_heartbeat',
	);

	/**
	 * Option name prefix for LIKE cleanup.
	 *
	 * @var string
	 */
	const OPTION_PREFIX = 'cosell_hive_';

	/**
	 * Meta key prefix for LIKE cleanup (post/order/user meta).
	 *
	 * @var string
	 */
	const META_PREFIX = '_cosell_hive_';
}
