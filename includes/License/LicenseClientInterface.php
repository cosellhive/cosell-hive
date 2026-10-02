<?php
/**
 * License client contract — provider-agnostic (Freemius/EDD/custom later).
 *
 * @package CoSellHive
 */

namespace CoSellHive\License;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for license providers.
 */
interface LicenseClientInterface {

	/**
	 * Activate a license key for this site.
	 *
	 * @param string $key License key.
	 * @return array
	 */
	public function activate( $key );

	/**
	 * Validate the stored license (heartbeat).
	 *
	 * @return array
	 */
	public function validate();

	/**
	 * Deactivate the current license.
	 *
	 * @return void
	 */
	public function deactivate();
}
