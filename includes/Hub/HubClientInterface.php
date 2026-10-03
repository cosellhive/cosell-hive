<?php
/**
 * Hub client contract — hub owns the ledger, plugin only reports.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Hub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for hub adapters (mock now, REST later).
 */
interface HubClientInterface {

	/**
	 * Upsert a listing in the central catalog.
	 *
	 * @param array $payload Listing payload.
	 * @return array
	 */
	public function upsert_listing( array $payload );

	/**
	 * Log a click and return a server-issued token.
	 *
	 * @param array $payload Click payload.
	 * @return array
	 */
	public function track_click( array $payload );

	/**
	 * Report an order event.
	 *
	 * @param array $payload Order payload.
	 * @return array
	 */
	public function report_order( array $payload );

	/**
	 * Fetch entitlements for the current site.
	 *
	 * @return array
	 */
	public function get_entitlements();

	/**
	 * Rank live listings for a niche. Returns id+rank only.
	 *
	 * @param string $niche Niche description.
	 * @param int    $limit Max items.
	 * @return array
	 */
	public function rank_feed( $niche, $limit = 20 );

	/**
	 * Semantic search over live listings.
	 *
	 * @param string $query Search text.
	 * @param int    $limit Max items.
	 * @return array
	 */
	public function search_feed( $query, $limit = 20 );

	/**
	 * Generate marketing copy for a hub listing ref.
	 *
	 * @param string $ref   Listing ref.
	 * @param string $tone  Optional tone.
	 * @return array{blurb: string, caption: string}
	 */
	public function generate_copy( $ref, $tone = '' );
}
