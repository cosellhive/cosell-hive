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
}
