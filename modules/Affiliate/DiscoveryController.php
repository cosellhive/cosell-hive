<?php
/**
 * AI discovery endpoints: fit-ranked recommendations + NL search.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Modules\Affiliate;

use CoSellHive\Core\Plugin;
use CoSellHive\Marketplace\ListingPresenter;
use CoSellHive\Repository\ListingRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Proxies hub AI ranking/search, falling back to local behavior
 * when the hub is unreachable (mock client returns empty).
 */
class DiscoveryController {

	const NAMESPACE = 'cosell-hive/v1';

	/**
	 * Main plugin instance (hub client access).
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Main plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register discovery routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/recommendations',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'recommend' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'niche' => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/nl-search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'nl_search' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'q' => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Rank live listings for a niche, presented in hub order.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function recommend( \WP_REST_Request $request ) {
		$niche = substr( $request->get_param( 'niche' ), 0, 200 );
		$rank  = $this->plugin->hub->rank_feed( $niche, 20 );

		if ( empty( $rank ) ) {
			return rest_ensure_response(
				array(
					'items'  => array(),
					'source' => 'none',
				)
			);
		}

		$by_ref = $this->presented_by_ref();
		$items  = array();

		foreach ( $rank as $entry ) {
			if ( ! isset( $entry['hub_listing_id'] ) || ! isset( $by_ref[ $entry['hub_listing_id'] ] ) ) {
				continue;
			}

			$items[] = $by_ref[ $entry['hub_listing_id'] ];
		}

		return rest_ensure_response(
			array(
				'items'  => $items,
				'source' => 'hub',
			)
		);
	}

	/**
	 * Semantic search with local keyword fallback.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function nl_search( \WP_REST_Request $request ) {
		$query = substr( $request->get_param( 'q' ), 0, 200 );
		$hits  = $this->plugin->hub->search_feed( $query, 20 );

		if ( ! empty( $hits ) ) {
			$by_ref = $this->presented_by_ref();
			$items  = array();

			foreach ( $hits as $hit ) {
				if ( ! isset( $hit['hub_listing_id'] ) || ! isset( $by_ref[ $hit['hub_listing_id'] ] ) ) {
					continue;
				}

				$items[] = $by_ref[ $hit['hub_listing_id'] ];
			}

			return rest_ensure_response(
				array(
					'items'  => $items,
					'source' => 'hub',
				)
			);
		}

		return rest_ensure_response(
			array(
				'items'  => $this->local_search( $query ),
				'source' => 'local',
			)
		);
	}

	/**
	 * Present all live listings keyed by hub ref.
	 *
	 * @return array
	 */
	private function presented_by_ref() {
		$repository = new ListingRepository();
		$presenter  = new ListingPresenter();
		$by_ref     = array();

		foreach ( $repository->get_queue( 'live', 200 ) as $row ) {
			$item = $presenter->present( $row );

			if ( null !== $item ) {
				$by_ref[ $item['ref'] ] = $item;
			}
		}

		return $by_ref;
	}

	/**
	 * Local keyword fallback over live product titles.
	 *
	 * @param string $query Search text.
	 * @return array
	 */
	private function local_search( $query ) {
		if ( '' === $query || ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$repository = new ListingRepository();
		$live_ids   = array();

		foreach ( $repository->get_queue( 'live', 200 ) as $row ) {
			$live_ids[] = absint( $row->product_id );
		}

		if ( empty( $live_ids ) ) {
			return array();
		}

		$products = wc_get_products(
			array(
				'limit'   => 20,
				'status'  => 'publish',
				'include' => $live_ids,
				's'       => $query,
			)
		);

		$presenter = new ListingPresenter();
		$items     = array();

		foreach ( $products as $product ) {
			if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
				continue;
			}

			$row = $repository->find_by_product_id( $product->get_id() );

			if ( ! $row ) {
				continue;
			}

			$item = $presenter->present( $row );

			if ( null !== $item ) {
				$items[] = $item;
			}
		}

		return $items;
	}
}
