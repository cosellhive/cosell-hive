<?php
/**
 * License heartbeat — daily validation that degrades gracefully.
 *
 * @package CoSellHive
 */

namespace CoSellHive\License;

use CoSellHive\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks in with the license provider daily. A failed check surfaces
 * an admin notice and limits premium UI — it never touches checkout,
 * tracking, or payouts, so a transient outage can't punish anyone.
 */
class Heartbeat {

	const CRON_HOOK = 'cosell_hive_daily_heartbeat';

	/**
	 * Main plugin instance (license client access).
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Main plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( self::CRON_HOOK, array( $this, 'run' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_notice' ) );
	}

	/**
	 * Schedule the daily run (idempotent).
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Validate the stored license and stash the outcome.
	 *
	 * @return void
	 */
	public function run() {
		$stored = get_option( 'cosell_hive_license', array() );

		if ( ! is_array( $stored ) || ! isset( $stored['key'] ) ) {
			return;
		}

		$result = $this->plugin->license->validate();

		update_option(
			'cosell_hive_license_status',
			array(
				'valid'       => isset( $result['valid'] ) ? (bool) $result['valid'] : false,
				'checked_at'  => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Render a notice when the last check failed.
	 *
	 * @return void
	 */
	public function maybe_render_notice() {
		$status = get_option( 'cosell_hive_license_status', array() );

		if ( ! is_array( $status ) || ! isset( $status['valid'] ) || $status['valid'] ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'CoSellHive: license validation failed. Premium features are limited until the license is renewed — tracking and payouts keep working.', 'cosell-hive' )
		);
	}
}
