<?php
/**
 * License client factory — production adapter by default.
 *
 * @package CoSellHive
 */

namespace CoSellHive\License;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the configured license adapter.
 */
class LicenseClientFactory {

	/**
	 * Create the license client.
	 *
	 * @return LicenseClientInterface
	 */
	public static function create() {
		$class = RestLicenseClient::class;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'COSELL_HIVE_USE_STUB_LICENSE' ) && COSELL_HIVE_USE_STUB_LICENSE ) {
			$class = StubLicenseClient::class;
		}

		if ( ! class_exists( $class ) ) {
			$class = RestLicenseClient::class;
		}

		$client = new $class();

		if ( ! $client instanceof LicenseClientInterface ) {
			$client = new RestLicenseClient();
		}

		return $client;
	}
}
