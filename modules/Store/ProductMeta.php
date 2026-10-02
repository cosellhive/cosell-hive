<?php
/**
 * WooCommerce product fields for marketplace publishing.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Modules\Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and saves the CoSellHive product data tab.
 */
class ProductMeta {

	const META_ENABLED = '_cosell_hive_enabled';
	const META_TYPE    = '_cosell_hive_commission_type';
	const META_VALUE   = '_cosell_hive_commission_value';
	const META_CAP     = '_cosell_hive_commission_cap';
	const META_STATUS  = '_cosell_hive_status';
	const META_HUB_ID  = '_cosell_hive_hub_id';

	/**
	 * Sync service (notified after meta saves).
	 *
	 * @var SyncService
	 */
	private $sync;

	/**
	 * Constructor.
	 *
	 * @param SyncService $sync Sync service.
	 */
	public function __construct( SyncService $sync ) {
		$this->sync = $sync;
	}

	/**
	 * Register Woo hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save' ), 20, 1 );
		add_action( 'admin_notices', array( $this, 'maybe_render_error_notice' ) );
	}

	/**
	 * Add the CoSellHive tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_tab( $tabs ) {
		$tabs['cosell_hive'] = array(
			'label'    => __( 'CoSellHive', 'cosell-hive' ),
			'target'   => 'cosell_hive_product_data',
			'class'    => array(),
			'priority' => 80,
		);

		return $tabs;
	}

	/**
	 * Render the tab panel.
	 *
	 * @return void
	 */
	public function render_panel() {
		global $post;

		$product_id = $post instanceof \WP_Post ? $post->ID : 0;
		$enabled    = 'yes' === get_post_meta( $product_id, self::META_ENABLED, true );
		$type       = get_post_meta( $product_id, self::META_TYPE, true );
		$value      = get_post_meta( $product_id, self::META_VALUE, true );
		$cap        = get_post_meta( $product_id, self::META_CAP, true );
		$status     = get_post_meta( $product_id, self::META_STATUS, true );
		$hub_id     = get_post_meta( $product_id, self::META_HUB_ID, true );

		if ( ! in_array( $type, array( 'flat', 'percent' ), true ) ) {
			$type = 'percent';
		}

		echo '<div id="cosell_hive_product_data" class="panel woocommerce_options_panel">';

		woocommerce_wp_checkbox(
			array(
				'id'          => self::META_ENABLED,
				'value'       => $enabled ? 'yes' : 'no',
				'label'       => __( 'Available for affiliation', 'cosell-hive' ),
				'description' => __( 'Publish this product to the CoSellHive marketplace for review.', 'cosell-hive' ),
			)
		);

		woocommerce_wp_select(
			array(
				'id'      => self::META_TYPE,
				'value'   => $type,
				'label'   => __( 'Commission type', 'cosell-hive' ),
				'options' => array(
					'percent' => __( 'Percentage', 'cosell-hive' ),
					'flat'    => __( 'Flat amount', 'cosell-hive' ),
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => self::META_VALUE,
				'value'             => $value,
				'label'             => __( 'Commission rate', 'cosell-hive' ),
				'description'       => __( 'Percent (0–100) or flat amount in store currency.', 'cosell-hive' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => 'any',
					'min'  => '0',
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => self::META_CAP,
				'value'             => $cap,
				'label'             => __( 'Cap per order (optional)', 'cosell-hive' ),
				'description'       => __( 'Leave empty for no cap.', 'cosell-hive' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => 'any',
					'min'  => '0',
				),
			)
		);

		if ( '' !== $status ) {
			echo '<p class="form-field"><label>' . esc_html__( 'Marketplace status', 'cosell-hive' ) . '</label><span>' . esc_html( $status ) . '</span></p>';
		}

		if ( '' !== $hub_id ) {
			echo '<p class="form-field"><label>' . esc_html__( 'Marketplace ID', 'cosell-hive' ) . '</label><span>' . esc_html( $hub_id ) . '</span></p>';
		}

		echo '</div>';
	}

	/**
	 * Save tab fields.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function save( $product_id ) {
		$product_id = absint( $product_id );

		if ( ! current_user_can( 'edit_product', $product_id ) ) {
			return;
		}

		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}

		$enabled = isset( $_POST[ self::META_ENABLED ] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$type    = isset( $_POST[ self::META_TYPE ] ) ? sanitize_key( wp_unslash( $_POST[ self::META_TYPE ] ) ) : 'percent'; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! in_array( $type, array( 'flat', 'percent' ), true ) ) {
			$type = 'percent';
		}

		$value = isset( $_POST[ self::META_VALUE ] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST[ self::META_VALUE ] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$cap   = isset( $_POST[ self::META_CAP ] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST[ self::META_CAP ] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( 'yes' === $enabled && ! $this->is_valid_value( $type, $value ) ) {
			set_transient(
				$this->error_transient_key(),
				'percent' === $type
					? __( 'CoSellHive: commission percent must be between 0 and 100. Previous value kept.', 'cosell-hive' )
					: __( 'CoSellHive: flat commission must be zero or greater. Previous value kept.', 'cosell-hive' ),
				MINUTE_IN_SECONDS
			);

			update_post_meta( $product_id, self::META_ENABLED, 'yes' );
			update_post_meta( $product_id, self::META_TYPE, $type );
			$this->sync->sync( $product_id );

			return;
		}

		update_post_meta( $product_id, self::META_ENABLED, $enabled );
		update_post_meta( $product_id, self::META_TYPE, $type );
		update_post_meta( $product_id, self::META_VALUE, $value );
		update_post_meta( $product_id, self::META_CAP, $cap );

		$this->sync->sync( $product_id );
	}

	/**
	 * Validate a commission value for its type.
	 *
	 * @param string $type  Commission type.
	 * @param string $value Raw value.
	 * @return bool
	 */
	private function is_valid_value( $type, $value ) {
		if ( '' === $value || ! is_numeric( $value ) ) {
			return false;
		}

		$number = (float) $value;

		if ( 'percent' === $type ) {
			return $number >= 0 && $number <= 100;
		}

		return $number >= 0;
	}

	/**
	 * Transient key for save-error notices.
	 *
	 * @return string
	 */
	private function error_transient_key() {
		return 'cosell_hive_meta_error_' . get_current_user_id();
	}

	/**
	 * Render a save-error notice if one is pending.
	 *
	 * @return void
	 */
	public function maybe_render_error_notice() {
		$message = get_transient( $this->error_transient_key() );

		if ( false === $message ) {
			return;
		}

		delete_transient( $this->error_transient_key() );

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( $message )
		);
	}
}
