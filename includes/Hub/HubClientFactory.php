<?php
/**
 * Hub client factory — swap mock for REST without touching callers.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Hub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the configured hub adapter.
 */
class HubClientFactory {

	/**
	 * Create the hub client.
	 *
	 * The REST adapter is the default. The mock is reachable only in
	 * debug mode via an explicit constant.
	 *
	 * @return HubClientInterface
	 */
	public static function create() {
		/**
		 * Filter the hub client class.
		 *
		 * @param string $class Fully-qualified class name.
		 */
		$class = apply_filters( 'cosell_hive_hub_client_class', '' );

		if ( '' === $class ) {
			$class = RestHubClient::class;

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'COSELL_HIVE_USE_MOCK_HUB' ) && COSELL_HIVE_USE_MOCK_HUB ) {
				$class = MockHubClient::class;
			}
		}

		if ( ! class_exists( $class ) ) {
			$class = RestHubClient::class;
		}

		$client = new $class();

		if ( ! $client instanceof HubClientInterface ) {
			$client = new RestHubClient();
		}

		return $client;
	}
}
