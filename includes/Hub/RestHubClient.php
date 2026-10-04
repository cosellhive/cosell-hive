<?php
/**
 * REST hub client — talks to staging/production over signed HTTP.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Hub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Same interface as the mock; HMAC-signed, site-identified requests.
 * Used automatically once the site registers (see factory).
 */
class RestHubClient implements HubClientInterface {

	/**
	 * Get the hub base URL.
	 *
	 * @return string
	 */
	private function base() {
		/**
		 * Filter the hub API base URL (no trailing /v1 — added here).
		 *
		 * @param string $base Base URL.
		 */
		return untrailingslashit( apply_filters( 'cosell_hive_hub_base', 'https://api.cosellhive.com' ) );
	}

	/**
	 * Get the stored site credentials.
	 *
	 * @return array{site_id: string, secret: string}
	 */
	private function credentials() {
		return array(
			'site_id' => get_option( 'cosell_hive_hub_site_id', '' ),
			'secret'  => get_option( 'cosell_hive_hub_secret', '' ),
		);
	}

	/**
	 * POST a signed JSON request.
	 *
	 * @param string $path Endpoint path under /v1.
	 * @param array  $body Payload.
	 * @return array Decoded response (empty on transport failure).
	 */
	private function post( $path, array $body ) {
		$creds = $this->credentials();

		if ( '' === $creds['site_id'] || '' === $creds['secret'] ) {
			return array();
		}

		$raw     = wp_json_encode( $body );
		$headers = Signature::headers( $raw, $creds['secret'] );

		$response = wp_remote_post(
			$this->base() . '/v1' . $path,
			array(
				'timeout' => 15,
				'sslverify' => true,
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-Site-Id'    => $creds['site_id'],
					'X-Signature'  => $headers['signature'],
					'X-Timestamp'  => $headers['timestamp'],
				),
				'body'    => $raw,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Upsert a listing in the central catalog.
	 *
	 * @param array $payload Listing payload.
	 * @return array
	 */
	public function upsert_listing( array $payload ) {
		$result = $this->post( '/listings/upsert', $this->to_minor_payload( $payload ) );

		return array(
			'hub_listing_id' => isset( $result['hub_listing_id'] ) ? sanitize_text_field( $result['hub_listing_id'] ) : '',
			'status'         => isset( $result['status'] ) ? sanitize_key( $result['status'] ) : 'pending',
		);
	}

	/**
	 * Log a click and return a server-issued token.
	 *
	 * @param array $payload Click payload.
	 * @return array
	 */
	public function track_click( array $payload ) {
		$result = $this->post(
			'/clicks',
			array(
				'listing_id'       => isset( $payload['product_id'] ) ? absint( $payload['product_id'] ) : 0,
				'affiliate_site_id' => isset( $payload['affiliate_id'] ) ? absint( $payload['affiliate_id'] ) : 0,
			)
		);

		return array(
			'token' => isset( $result['token'] ) ? sanitize_text_field( $result['token'] ) : '',
		);
	}

	/**
	 * Report an order event.
	 *
	 * @param array $payload Order payload.
	 * @return array
	 */
	public function report_order( array $payload ) {
		$result = $this->post(
			'/events/order',
			array(
				'order_id'          => isset( $payload['order_id'] ) ? absint( $payload['order_id'] ) : 0,
				'attribution_token' => isset( $payload['attribution_token'] ) ? sanitize_text_field( $payload['attribution_token'] ) : '',
				'status'            => isset( $payload['status'] ) ? sanitize_key( $payload['status'] ) : '',
			'amount_minor'      => isset( $payload['amount_minor'] ) ? absint( $payload['amount_minor'] ) : 0,
			'currency'          => isset( $payload['currency'] ) ? sanitize_text_field( $payload['currency'] ) : '',
			'timestamp'         => time(),
			'is_cod'            => ! empty( $payload['is_cod'] ),
			'buyer_ref'         => isset( $payload['buyer_ref'] ) ? sanitize_text_field( $payload['buyer_ref'] ) : '',
		)
	);

		return array(
			'acknowledged' => ! empty( $result['acknowledged'] ),
		);
	}

	/**
	 * Fetch entitlements for the current site.
	 *
	 * @return array
	 */
	public function get_entitlements() {
		$creds = $this->credentials();

		if ( '' === $creds['site_id'] || '' === $creds['secret'] ) {
			return ( new MockHubClient() )->get_entitlements();
		}

		$headers = Signature::headers( '', $creds['secret'] );

		$response = wp_remote_get(
			$this->base() . '/v1/me/entitlements',
			array(
				'timeout' => 15,
				'sslverify' => true,
				'headers' => array(
					'X-Site-Id'   => $creds['site_id'],
					'X-Signature' => $headers['signature'],
					'X-Timestamp' => $headers['timestamp'],
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return ( new MockHubClient() )->get_entitlements();
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || ! isset( $data['tier'] ) ) {
			return ( new MockHubClient() )->get_entitlements();
		}

		return $data;
	}

	/**
	 * Rank live listings for a niche.
	 *
	 * @param string $niche Niche description.
	 * @param int    $limit Max items.
	 * @return array
	 */
	public function rank_feed( $niche, $limit = 20 ) {
		$result = $this->post(
			'/feed/rank',
			array(
				'niche' => substr( sanitize_text_field( $niche ), 0, 200 ),
				'limit' => absint( $limit ),
			)
		);

		return isset( $result['items'] ) && is_array( $result['items'] ) ? $result['items'] : array();
	}

	/**
	 * Semantic search over live listings.
	 *
	 * @param string $query Search text.
	 * @param int    $limit Max items.
	 * @return array
	 */
	public function search_feed( $query, $limit = 20 ) {
		$creds = $this->credentials();

		if ( '' === $creds['site_id'] || '' === $creds['secret'] ) {
			return array();
		}

		$headers = Signature::headers( '', $creds['secret'] );

		$response = wp_remote_get(
			add_query_arg(
				array(
					'q'     => substr( sanitize_text_field( $query ), 0, 200 ),
					'limit' => absint( $limit ),
				),
				$this->base() . '/v1/feed/search'
			),
			array(
				'timeout' => 15,
				'sslverify' => true,
				'headers' => array(
					'X-Site-Id'   => $creds['site_id'],
					'X-Signature' => $headers['signature'],
					'X-Timestamp' => $headers['timestamp'],
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();
	}

	/**
	 * Generate marketing copy for a hub listing ref.
	 *
	 * @param string $ref  Listing ref.
	 * @param string $tone Optional tone.
	 * @return array
	 */
	public function generate_copy( $ref, $tone = '' ) {
		$result = $this->post(
			'/copy/generate',
			array(
				'listing_id' => sanitize_text_field( $ref ),
				'tone'       => substr( sanitize_text_field( $tone ), 0, 60 ),
			)
		);

		if ( empty( $result['blurb'] ) ) {
			return ( new MockHubClient() )->generate_copy( $ref, $tone );
		}

		return array(
			'blurb'   => sanitize_text_field( $result['blurb'] ),
			'caption' => sanitize_text_field( $result['caption'] ),
		);
	}

	/**
	 * Convert the plugin's decimal payload to hub minor units.
	 *
	 * @param array $payload Plugin payload.
	 * @return array
	 */
	private function to_minor_payload( array $payload ) {
		$type = isset( $payload['commission_type'] ) ? $payload['commission_type'] : 'percent';

		return array(
			'product_id'             => isset( $payload['product_id'] ) ? absint( $payload['product_id'] ) : 0,
			'title'                  => isset( $payload['title'] ) ? sanitize_text_field( $payload['title'] ) : '',
			'price_minor'            => isset( $payload['price'] ) ? (int) round( (float) $payload['price'] * 100 ) : 0,
			'currency'               => isset( $payload['currency'] ) ? sanitize_text_field( $payload['currency'] ) : '',
			'stock_quantity'         => isset( $payload['stock_quantity'] ) ? absint( $payload['stock_quantity'] ) : null,
			'in_stock'               => ! empty( $payload['in_stock'] ),
			'commission_type'        => in_array( $type, array( 'flat', 'percent' ), true ) ? $type : 'percent',
			'commission_value_minor' => isset( $payload['commission_value'] ) ? (int) round( (float) $payload['commission_value'] * 100 ) : 0,
			'commission_cap_minor'   => isset( $payload['commission_cap'] ) && '' !== $payload['commission_cap'] ? (int) round( (float) $payload['commission_cap'] * 100 ) : null,
			'status'                 => isset( $payload['status'] ) ? sanitize_key( $payload['status'] ) : 'pending',
		);
	}
}
