<?php
/**
 * Stub license client — always valid locally for Phase 0/V1 pilot.
 *
 * @package CoSellHive
 */

namespace CoSellHive\License;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local stub; real provider plugs in via the interface.
 */
class StubLicenseClient implements LicenseClientInterface {

	/**
	 * Activate a license key (stub).
	 *
	 * @param string $key License key.
	 * @return array
	 */
	public function activate( $key ) {
		update_option( 'cosell_hive_license', array( 'key' => sanitize_text_field( $key ) ) );

		return array(
			'valid' => true,
			'tier'  => 'free',
		);
	}

	/**
	 * Validate stored license (stub).
	 *
	 * @return array
	 */
	public function validate() {
		return array(
			'valid' => true,
			'tier'  => 'free',
		);
	}

	/**
	 * Deactivate (stub).
	 *
	 * @return void
	 */
	public function deactivate() {
		delete_option( 'cosell_hive_license' );
	}
}
