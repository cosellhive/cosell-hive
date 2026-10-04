<?php
/**
 * Activation / deactivation tasks.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Core;

use CoSellHive\Repository\ListingRepository;
use CoSellHive\Repository\ClickRepository;
use CoSellHive\Repository\CommissionRepository;
use CoSellHive\Repository\PayoutRepository;
use CoSellHive\Tracking\Reconciler;
use CoSellHive\License\Heartbeat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles install-time setup.
 */
class Installer {

	/**
	 * Run on activation.
	 *
	 * @return void
	 */
	public function activate() {
		$this->add_roles();
		$this->add_options();
		$this->create_tables();

		Reconciler::schedule();
		Heartbeat::schedule();

		flush_rewrite_rules();
	}

	/**
	 * Run on deactivation.
	 *
	 * @return void
	 */
	public function deactivate() {
		wp_clear_scheduled_hook( Reconciler::CRON_HOOK );
		wp_clear_scheduled_hook( Heartbeat::CRON_HOOK );

		flush_rewrite_rules();
	}

	/**
	 * Register custom roles.
	 *
	 * @return void
	 */
	private function add_roles() {
		add_role(
			'cs_hive_store',
			__( 'CoSellHive Store', 'cosell-hive' ),
			array(
				'read'              => true,
				'cosell_hive_store' => true,
			)
		);

		add_role(
			'cs_hive_affiliate',
			__( 'CoSellHive Affiliate', 'cosell-hive' ),
			array(
				'read'                  => true,
				'cosell_hive_affiliate' => true,
			)
		);
	}

	/**
	 * Register the upgrade check for existing installs.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
	}

	/**
	 * Run schema upgrades when the DB version lags.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		if ( Plugin::DB_VERSION !== get_option( 'cosell_hive_db_version' ) ) {
			$this->create_tables();
			$this->migrate_legacy_prefixes();
			update_option( 'cosell_hive_db_version', Plugin::DB_VERSION );
		}
	}

	/**
	 * Migrate legacy `ch_` roles/capabilities/options to `cs_hive_`.
	 *
	 * Idempotent; safe to run on every DB version bump.
	 *
	 * @return void
	 */
	public function migrate_legacy_prefixes() {
		$this->add_roles();

		$roles = array(
			'ch_store'     => 'cs_hive_store',
			'ch_affiliate' => 'cs_hive_affiliate',
		);

		foreach ( $roles as $old => $new ) {
			foreach ( get_users( array( 'role' => $old ) ) as $user ) {
				if ( ! $user instanceof \WP_User ) {
					continue;
				}

				$user->add_role( $new );
				$user->remove_role( $old );
			}
		}

		remove_role( 'ch_store' );
		remove_role( 'ch_affiliate' );
	}

	/**
	 * Create custom tables.
	 *
	 * @return void
	 */
	private function create_tables() {
		$listings = new ListingRepository();
		$listings->create_table();

		$clicks = new ClickRepository();
		$clicks->create_table();

		$commissions = new CommissionRepository();
		$commissions->create_table();

		$payouts = new PayoutRepository();
		$payouts->create_table();
	}

	/**
	 * Seed default options.
	 *
	 * @return void
	 */
	private function add_options() {
		add_option( 'cosell_hive_version', Plugin::VERSION );
		add_option( 'cosell_hive_db_version', Plugin::DB_VERSION );
		add_option( 'cosell_hive_settings', array() );
	}
}
