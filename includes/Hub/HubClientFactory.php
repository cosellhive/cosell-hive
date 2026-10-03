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
	 * REST wins automatically once the site registers (site ID + secret
	 * stored by onboarding); otherwise the mock keeps local dev working.
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
			$site_id = get_option( 'cosell_hive_hub_site_id', '' );
			$secret  = get_option( 'cosell_hive_hub_secret', '' );
			$class   = ( '' !== $site_id && '' !== $secret ) ? RestHubClient::class : MockHubClient::class;
		}

		if ( ! class_exists( $class ) ) {
			$class = MockHubClient::class;
		}

		$client = new $class();

		if ( ! $client instanceof HubClientInterface ) {
			$client = new MockHubClient();
		}

		return $client;
	}
}
