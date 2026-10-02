<?php
/**
 * Store dashboard REST endpoint.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Modules\Store;

use CoSellHive\Repository\CommissionRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves `GET /cosell-hive/v1/dashboard` for the React dashboard.
 */
class DashboardController {

	const NAMESPACE = 'cosell-hive/v1';
	const ROUTE     = '/dashboard';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the dashboard route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_dashboard' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);
	}

	/**
	 * Capability gate.
	 *
	 * @return bool
	 */
	public function can_view() {
		return current_user_can( 'cosell_hive_store' )
			|| current_user_can( 'manage_woocommerce' )
			|| current_user_can( 'manage_options' );
	}

	/**
	 * Build the dashboard payload from listings + commission ledger.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_dashboard() {
		$listings = $this->get_listings();

		$active = array_filter(
			$listings,
			function ( $listing ) {
				return 'live' === $listing['status'];
			}
		);

		$ledger = new CommissionRepository();
		$store  = $ledger->stats_for_store();

		return rest_ensure_response(
			array(
				'stats'    => array(
					'active_listings'   => count( $active ),
					'active_affiliates' => $store['affiliates'],
					'gmv_this_month'    => $store['gmv_month_minor'] / 100,
					'commission_paid'   => $store['paid_minor'] / 100,
				),
				'listings' => array_values( $listings ),
			)
		);
	}

	/**
	 * Query products opted into the marketplace.
	 *
	 * @return array
	 */
	private function get_listings() {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$products = wc_get_products(
			array(
				'limit'      => 100,
				'status'     => 'any',
				'meta_key'   => ProductMeta::META_ENABLED, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		$listings = array();

		foreach ( $products as $product ) {
			if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
				continue;
			}

			$product_id = $product->get_id();
			$type       = get_post_meta( $product_id, ProductMeta::META_TYPE, true );

			if ( ! in_array( $type, array( 'flat', 'percent' ), true ) ) {
				$type = 'percent';
			}

			$ledger = new CommissionRepository();
			$stats  = $ledger->stats_for_product( $product_id );

			$listings[] = array(
				'id'             => $product_id,
				'title'          => $product->get_name(),
				'price'          => $product->get_price(),
				'stock_quantity' => $product->get_stock_quantity(),
				'commission'     => array(
					'type'  => $type,
					'value' => get_post_meta( $product_id, ProductMeta::META_VALUE, true ),
					'cap'   => get_post_meta( $product_id, ProductMeta::META_CAP, true ),
					'label' => $this->commission_label( $type, get_post_meta( $product_id, ProductMeta::META_VALUE, true ) ),
				),
				'status'         => get_post_meta( $product_id, ProductMeta::META_STATUS, true ),
				'hub_id'         => get_post_meta( $product_id, ProductMeta::META_HUB_ID, true ),
				'affiliates'     => $stats['affiliates'],
				'sales_30d'      => $stats['sales_30d'],
			);
		}

		return $listings;
	}

	/**
	 * Human-readable commission label.
	 *
	 * @param string $type  Commission type.
	 * @param string $value Commission value.
	 * @return string
	 */
	private function commission_label( $type, $value ) {
		if ( '' === $value || ! is_numeric( $value ) ) {
			return __( 'Not set', 'cosell-hive' );
		}

		if ( 'percent' === $type ) {
			/* translators: %s: commission percent */
			return sprintf( __( '%s%%', 'cosell-hive' ), $value );
		}

		/* translators: %s: flat commission amount */
		return sprintf( __( '%s flat', 'cosell-hive' ), $value );
	}
}
