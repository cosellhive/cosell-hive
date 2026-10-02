<?php
/**
 * Shared marketplace listing presentation.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Marketplace;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CoSellHive\Modules\Store\ProductMeta;

/**
 * Turns queue rows into API-ready arrays. Single source of truth
 * for title/image/commission shape across queue, feed, and embeds.
 */
class ListingPresenter {

	/**
	 * Present one listing row.
	 *
	 * @param object $row Repository row.
	 * @return array|null
	 */
	public function present( $row ) {
		$product_id = absint( $row->product_id );

		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product || ! method_exists( $product, 'get_id' ) ) {
			return null;
		}

		$type  = get_post_meta( $product_id, ProductMeta::META_TYPE, true );
		$value = get_post_meta( $product_id, ProductMeta::META_VALUE, true );

		if ( ! in_array( $type, array( 'flat', 'percent' ), true ) ) {
			$type = 'percent';
		}

		$image_id = method_exists( $product, 'get_image_id' ) ? $product->get_image_id() : 0;
		$hub_id   = get_post_meta( $product_id, ProductMeta::META_HUB_ID, true );

		return array(
			'id'             => absint( $row->id ),
			'product_id'     => $product_id,
			'ref'            => '' !== $hub_id ? $hub_id : 'p-' . $product_id,
			'title'          => $product->get_name(),
			'image'          => $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '',
			'store'          => get_bloginfo( 'name' ),
			'price'          => $product->get_price(),
			'price_display'  => wp_strip_all_tags( wc_price( $product->get_price() ) ),
			'stock_quantity' => method_exists( $product, 'get_stock_quantity' ) ? $product->get_stock_quantity() : null,
			'commission'     => array(
				'type'  => $type,
				'value' => $value,
				'label' => $this->commission_label( $type, $value ),
			),
			'status'         => $row->status,
			'submitted'      => $row->submitted_at,
		);
	}

	/**
	 * Human-readable commission label.
	 *
	 * @param string $type  Commission type.
	 * @param string $value Commission value.
	 * @return string
	 */
	public function commission_label( $type, $value ) {
		if ( '' === $value || ! is_numeric( $value ) ) {
			return __( 'Not set', 'cosell-hive' );
		}

		if ( 'percent' === $type ) {
			/* translators: %s: commission percent */
			return sprintf( __( '%s%% commission', 'cosell-hive' ), $value );
		}

		/* translators: %s: flat commission amount */
		return sprintf( __( '%s flat', 'cosell-hive' ), $value );
	}
}
