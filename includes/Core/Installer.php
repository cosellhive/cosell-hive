<?php
/**
 * Activation / deactivation tasks.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Core;

use CoSellHive\Repository\ListingRepository;

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

		flush_rewrite_rules();
	}

	/**
	 * Run on deactivation.
	 *
	 * @return void
	 */
	public function deactivate() {
		wp_clear_scheduled_hook( 'cosell_hive_daily_heartbeat' );
		wp_clear_scheduled_hook( 'cosell_hive_daily_reconcile' );

		flush_rewrite_rules();
	}

	/**
	 * Register custom roles.
	 *
	 * @return void
	 */
	private function add_roles() {
		add_role(
			'ch_store',
			__( 'CoSellHive Store', 'cosell-hive' ),
			array(
				'read'              => true,
				'cosell_hive_store' => true,
			)
		);

		add_role(
			'ch_affiliate',
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
		if ( COSELL_HIVE_DB_VERSION !== get_option( 'cosell_hive_db_version' ) ) {
			$this->create_tables();
			update_option( 'cosell_hive_db_version', COSELL_HIVE_DB_VERSION );
		}
	}

	/**
	 * Create custom tables.
	 *
	 * @return void
	 */
	private function create_tables() {
		$repository = new ListingRepository();
		$repository->create_table();
	}

	/**
	 * Seed default options.
	 *
	 * @return void
	 */
	private function add_options() {
		add_option( 'cosell_hive_version', COSELL_HIVE_VERSION );
		add_option( 'cosell_hive_db_version', COSELL_HIVE_DB_VERSION );
		add_option( 'cosell_hive_settings', array() );
	}
}
