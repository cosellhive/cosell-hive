<?php
/**
 * Product embed shortcode + Gutenberg block (server-rendered).
 *
 * @package CoSellHive
 */

namespace CoSellHive\Shortcodes;

use CoSellHive\Marketplace\ListingPresenter;
use CoSellHive\Repository\ListingRepository;
use CoSellHive\Tracking\Redirector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders `[cosell_product]` and the `cosell-hive/product-card` block.
 *
 * The affiliate ID is baked into the shortcode by the embed generator,
 * so static/cached pages still attribute correctly — the token itself
 * is minted at click time by the redirector.
 */
class ProductCard {

	/**
	 * Register shortcode, block, and frontend styles.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Register shortcodes on init (API not available earlier).
	 *
	 * @return void
	 */
	public function register_shortcodes() {
		add_shortcode( 'cosell_product', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Register the Gutenberg block (dynamic, server-rendered).
	 *
	 * @return void
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$asset_file = COSELL_HIVE_PATH . '/build/cosell-hive-product-card.asset.php';

		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;

			wp_register_script(
				'cosell-hive-product-card',
				COSELL_HIVE_URL . '/build/cosell-hive-product-card.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);
		}

		register_block_type(
			COSELL_HIVE_PATH . '/blocks/product-card',
			array(
				'editor_script'   => 'cosell-hive-product-card',
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Enqueue frontend styles when an embed is present.
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		if ( ! is_singular() ) {
			return;
		}

		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( has_shortcode( $post->post_content, 'cosell_product' ) || has_block( 'cosell-hive/product-card', $post ) ) {
			wp_enqueue_style(
				'cosell-hive-frontend',
				COSELL_HIVE_URL . '/assets/css/frontend.css',
				array(),
				COSELL_HIVE_VERSION
			);
		}
	}

	/**
	 * Shortcode callback.
	 *
	 * Usage: [cosell_product id="mock_42" affiliate="3" style="card"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'        => '',
				'affiliate' => 0,
				'style'     => 'card',
			),
			$atts,
			'cosell_product'
		);

		return $this->render( sanitize_text_field( $atts['id'] ), absint( $atts['affiliate'] ), sanitize_key( $atts['style'] ) );
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		$id        = isset( $attributes['listingId'] ) ? sanitize_text_field( $attributes['listingId'] ) : '';
		$style     = isset( $attributes['style'] ) ? sanitize_key( $attributes['style'] ) : 'card';
		$affiliate = isset( $attributes['affiliateId'] ) ? absint( $attributes['affiliateId'] ) : 0;

		return $this->render( $id, $affiliate, $style );
	}

	/**
	 * Render an embed for a live listing ref.
	 *
	 * @param string $ref          Listing ref (hub ID or product ID).
	 * @param int    $affiliate_id Affiliate user ID.
	 * @param string $style        card|text|banner.
	 * @return string
	 */
	public function render( $ref, $affiliate_id, $style = 'card' ) {
		$row = $this->find_live_row( $ref );

		if ( ! $row ) {
			return '<!-- cosell-hive: listing unavailable -->';
		}

		$presenter = new ListingPresenter();
		$item      = $presenter->present( $row );

		if ( null === $item ) {
			return '<!-- cosell-hive: listing unavailable -->';
		}

		$url = Redirector::build_url( $item['ref'], $affiliate_id );

		if ( 'text' === $style ) {
			return sprintf(
				'<a class="cosell-product-text" href="%s">%s</a>',
				esc_url( $url ),
				esc_html( $item['title'] )
			);
		}

		if ( 'banner' === $style ) {
			return sprintf(
				'<div class="cosell-product-banner"><a href="%s"><span class="cosell-product-banner-title">%s</span><span class="cosell-product-banner-meta">%s &middot; %s</span></a></div>',
				esc_url( $url ),
				esc_html( $item['title'] ),
				esc_html( $item['price_display'] ),
				esc_html( $item['commission']['label'] )
			);
		}

		$image = '' !== $item['image']
			? sprintf( '<img class="cosell-product-image" src="%s" alt="" loading="lazy" />', esc_url( $item['image'] ) )
			: '';

		return sprintf(
			'<div class="cosell-product-card">%s<div class="cosell-product-body"><p class="cosell-product-title">%s</p><p class="cosell-product-meta">%s &middot; %s</p><p><a class="cosell-product-button" href="%s">%s</a></p></div></div>',
			$image,
			esc_html( $item['title'] ),
			esc_html( $item['store'] ),
			esc_html( $item['price_display'] ),
			esc_url( $url ),
			esc_html( $item['commission']['label'] )
		);
	}

	/**
	 * Find a live row by hub ID or product ID.
	 *
	 * @param string $ref Listing ref.
	 * @return object|null
	 */
	private function find_live_row( $ref ) {
		$repository = new ListingRepository();

		if ( 0 === strpos( $ref, 'p-' ) || ctype_digit( $ref ) ) {
			$product_id = 0 === strpos( $ref, 'p-' ) ? absint( substr( $ref, 2 ) ) : absint( $ref );
			$row        = $repository->find_by_product_id( $product_id );
		} else {
			$row = $repository->find_by_hub_id( $ref );
		}

		return ( $row && 'live' === $row->status ) ? $row : null;
	}
}
