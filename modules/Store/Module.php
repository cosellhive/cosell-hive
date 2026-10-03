<?php
/**
 * Store module — product publishing, sync, and dashboard.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Modules\Store;

use CoSellHive\Core\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the store-side feature set. Woo-dependent parts self-guard
 * so affiliate-only sites (no WooCommerce) still load cleanly.
 */
class Module {

	/**
	 * Main plugin instance (service container access).
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Product meta handler.
	 *
	 * @var ProductMeta
	 */
	private $product_meta;

	/**
	 * Catalog sync service.
	 *
	 * @var SyncService
	 */
	private $sync;

	/**
	 * Dashboard REST controller.
	 *
	 * @var DashboardController
	 */
	private $dashboard;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Main plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin       = $plugin;
		$this->sync         = new SyncService( $plugin );
		$this->product_meta = new ProductMeta( $this->sync );
		$this->dashboard    = new DashboardController();

		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

		$reporter = new \CoSellHive\Tracking\OrderReporter( $plugin );
		$reporter->register();

		$reconciler = new \CoSellHive\Tracking\Reconciler();
		$reconciler->register();

		add_action( 'admin_notices', array( $this, 'maybe_render_woocommerce_notice' ) );

		if ( $this->is_woo_active() ) {
			$this->product_meta->register();
			$this->sync->register();
		}

		$this->dashboard->register();
	}

	/**
	 * Check whether WooCommerce is available.
	 *
	 * @return bool
	 */
	public function is_woo_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}

	/**
	 * Show a clear notice when store features are unavailable.
	 *
	 * @return void
	 */
	public function maybe_render_woocommerce_notice() {
		if ( $this->is_woo_active() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%s</p></div>',
			esc_html__( 'CoSellHive store features require WooCommerce. Affiliate browsing, links, and wallet features continue to work without it.', 'cosell-hive' )
		);
	}

	/**
	 * Declare HPOS compatibility.
	 *
	 * @return void
	 */
	public function declare_hpos_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', COSELL_HIVE_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', COSELL_HIVE_FILE, true );
		}
	}
}
