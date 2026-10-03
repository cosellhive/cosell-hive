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
			'status'         => isset( $payload['status'] ) ? sanitize_key( $payload['status'] ) : 'pending',
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

	/**
	 * Rank live listings (mock: no ranking, caller keeps local order).
	 *
	 * @param string $niche Niche description.
	 * @param int    $limit Max items.
	 * @return array
	 */
	public function rank_feed( $niche, $limit = 20 ) {
		return array();
	}

	/**
	 * Semantic search (mock: no results, caller falls back to local search).
	 *
	 * @param string $query Search text.
	 * @param int    $limit Max items.
	 * @return array
	 */
	public function search_feed( $query, $limit = 20 ) {
		return array();
	}

	/**
	 * Generate marketing copy (mock: clearly-labeled template).
	 *
	 * @param string $ref  Listing ref.
	 * @param string $tone Optional tone.
	 * @return array
	 */
	public function generate_copy( $ref, $tone = '' ) {
		return array(
			'blurb'   => __( 'Mock copy: connect the hub for AI-written blurbs tailored to your audience.', 'cosell-hive' ),
			'caption' => __( 'Mock caption: hub-generated social text appears here.', 'cosell-hive' ),
		);
	}
}
