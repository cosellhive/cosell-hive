<?php
/**
 * Affiliate discovery feed endpoint.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Modules\Affiliate;

use CoSellHive\Marketplace\ListingPresenter;
use CoSellHive\Modules\Store\ProductMeta;
use CoSellHive\Repository\ListingRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves `GET /cosell-hive/v1/feed` — approved listings only.
 */
class FeedController {

	const NAMESPACE = 'cosell-hive/v1';
	const ROUTE     = '/feed';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the feed route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_feed' ),
				'permission_callback' => array( $this, 'can_browse' ),
				'args'                => array(
					'search'         => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'category'       => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'min_commission' => array(
						'default'           => '',
						'sanitize_callback' => array( $this, 'sanitize_number' ),
					),
				),
			)
		);
	}

	/**
	 * Logged-in affiliates (and store admins previewing).
	 *
	 * @return bool
	 */
	public function can_browse() {
		return is_user_logged_in();
	}

	/**
	 * Sanitize an optional numeric filter.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_number( $value ) {
		$value = is_string( $value ) ? sanitize_text_field( $value ) : '';

		return is_numeric( $value ) ? $value : '';
	}

	/**
	 * Build the feed: live listings, newest first.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_feed( \WP_REST_Request $request ) {
		$repository = new ListingRepository();
		$rows       = $repository->get_queue( 'live', 100 );

		$ids = array();
		foreach ( $rows as $row ) {
			$ids[] = absint( $row->product_id );
		}

		if ( empty( $ids ) || ! function_exists( 'wc_get_products' ) ) {
			return rest_ensure_response( array( 'items' => array() ) );
		}

		$args = array(
			'limit'   => 100,
			'status'  => 'publish',
			'include' => $ids,
		);

		$search = $request->get_param( 'search' );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$category = $request->get_param( 'category' );
		if ( '' !== $category ) {
			$args['category'] = array( $category );
		}

		$products = wc_get_products( $args );
		$by_id    = array();
		foreach ( $rows as $row ) {
			$by_id[ absint( $row->product_id ) ] = $row;
		}

		$min     = $request->get_param( 'min_commission' );
		$present = new ListingPresenter();
		$items   = array();

		foreach ( $products as $product ) {
			if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
				continue;
			}

			$row = isset( $by_id[ $product->get_id() ] ) ? $by_id[ $product->get_id() ] : null;

			if ( ! $row ) {
				continue;
			}

			$item = $present->present( $row );

			if ( null === $item ) {
				continue;
			}

			if ( '' !== $min ) {
				$value = get_post_meta( $item['product_id'], ProductMeta::META_VALUE, true );

				if ( ! is_numeric( $value ) || (float) $value < (float) $min ) {
					continue;
				}
			}

			$items[] = $item;
		}

		return rest_ensure_response( array( 'items' => $items ) );
	}
}
