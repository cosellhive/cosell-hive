<?php
/**
 * Pushes product state to the hub catalog and enforces auto-delist rules.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Modules\Store;

use CoSellHive\Core\Plugin;
use CoSellHive\Repository\ListingRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin reporter: local meta is never authoritative, the hub is.
 */
class SyncService {

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
	 * Register sync triggers.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'woocommerce_product_set_stock', array( $this, 'on_stock_change' ) );
		add_action( 'woocommerce_product_status_changed', array( $this, 'on_status_change' ), 10, 1 );
	}

	/**
	 * Handle stock changes.
	 *
	 * @param object $product Product object.
	 * @return void
	 */
	public function on_stock_change( $product ) {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return;
		}

		$this->sync( $product->get_id() );
	}

	/**
	 * Handle product status transitions.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function on_status_change( $product_id ) {
		$this->sync( absint( $product_id ) );
	}

	/**
	 * Sync one product to the hub.
	 *
	 * Disabled products collapse to `draft` and are not reported.
	 * Enabled products that are unpublished or out of stock are
	 * reported as `paused` (auto-delist) instead of `pending`.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function sync( $product_id ) {
		$product_id = absint( $product_id );

		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		if ( 'yes' !== get_post_meta( $product_id, ProductMeta::META_ENABLED, true ) ) {
			update_post_meta( $product_id, ProductMeta::META_STATUS, 'draft' );

			$repository = new ListingRepository();
			$repository->upsert( $product_id, 'draft' );

			return;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			update_post_meta( $product_id, ProductMeta::META_STATUS, 'draft' );

			$repository = new ListingRepository();
			$repository->upsert( $product_id, 'draft' );

			return;
		}

		$paused = 'publish' !== $product->get_status() || ! $product->is_in_stock();

		$payload = array(
			'product_id'       => $product_id,
			'title'            => $product->get_name(),
			'price'            => $product->get_price(),
			'currency'         => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'stock_quantity'   => $product->get_stock_quantity(),
			'in_stock'         => $product->is_in_stock(),
			'commission_type'  => get_post_meta( $product_id, ProductMeta::META_TYPE, true ),
			'commission_value' => get_post_meta( $product_id, ProductMeta::META_VALUE, true ),
			'commission_cap'   => get_post_meta( $product_id, ProductMeta::META_CAP, true ),
			'status'           => $paused ? 'paused' : 'pending',
		);

		$response = $this->plugin->hub->upsert_listing( $payload );

		$hub_id = isset( $response['hub_listing_id'] ) ? sanitize_text_field( $response['hub_listing_id'] ) : '';
		$status = $paused ? 'paused' : 'pending';

		if ( '' !== $hub_id ) {
			update_post_meta( $product_id, ProductMeta::META_HUB_ID, $hub_id );
		}

		update_post_meta( $product_id, ProductMeta::META_STATUS, $status );

		$repository = new ListingRepository();
		$repository->upsert( $product_id, $status, $hub_id );
	}
}
