<?php
/**
 * Local mock hub — lets V1 develop without the central service.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Hub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * In-memory/local mock implementation.
 */
class MockHubClient implements HubClientInterface {

	/**
	 * Upsert a listing (mock: echo back with local id).
	 *
	 * @param array $payload Listing payload.
	 * @return array
	 */
	public function upsert_listing( array $payload ) {
		return array(
			'hub_listing_id' => 'mock_' . ( isset( $payload['product_id'] ) ? absint( $payload['product_id'] ) : 0 ),
			'status'         => 'pending',
		);
	}

	/**
	 * Track a click (mock: random token).
	 *
	 * @param array $payload Click payload.
	 * @return array
	 */
	public function track_click( array $payload ) {
		return array(
			'token' => 'mock_' . wp_generate_password( 12, false ),
		);
	}

	/**
	 * Report an order (mock: acknowledged).
	 *
	 * @param array $payload Order payload.
	 * @return array
	 */
	public function report_order( array $payload ) {
		return array(
			'acknowledged' => true,
		);
	}

	/**
	 * Fetch entitlements (mock: free tier, generous for pilot).
	 *
	 * @return array
	 */
	public function get_entitlements() {
		return array(
			'tier'                 => 'free',
			'listed_product_limit' => 10,
			'ai_copy_credits'      => 0,
			'analytics_depth'      => 'basic',
		);
	}
}
