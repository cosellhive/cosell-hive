<?php
/**
 * Captures attribution at checkout and reports order events.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Tracking;

use CoSellHive\Commission\CommissionService;
use CoSellHive\Core\Plugin;
use CoSellHive\Repository\ClickRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Token chain: URL param → cookie fallback → validated against the
 * click ledger (attribution window) → order meta → hub report.
 */
class OrderReporter {

	const META_TOKEN  = '_cosell_hive_token';
	const COOKIE_NAME = 'cosell_hive_token';

	/**
	 * Main plugin instance (hub client access).
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Commission service.
	 *
	 * @var CommissionService
	 */
	private $commissions;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Main plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin      = $plugin;
		$this->commissions = new CommissionService();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp', array( $this, 'capture_token' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'write_order_meta' ), 10, 2 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_change' ), 10, 4 );
	}

	/**
	 * Capture `?ch_token=` into session + cookie (last-click wins).
	 *
	 * @return void
	 */
	public function capture_token() {
		if ( is_admin() ) {
			return;
		}

		if ( ! isset( $_GET['ch_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$token = substr( sanitize_text_field( wp_unslash( $_GET['ch_token'] ) ), 0, 128 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $token ) {
			return;
		}

		if ( function_exists( 'WC' ) && WC() && method_exists( WC(), 'session' ) && WC()->session ) {
			WC()->session->set( 'cosell_hive_token', $token );
		}

		$days = absint( cosell_hive_get_setting( 'attribution_days', 30 ) );

		setcookie(
			self::COOKIE_NAME,
			$token,
			array(
				'expires'  => time() + $days * DAY_IN_SECONDS,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Write the validated token to order meta + record the commission.
	 *
	 * @param object $order Order object.
	 * @param array  $data  Checkout data (unused).
	 * @return void
	 */
	public function write_order_meta( $order, $data ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) ) {
			return;
		}

		$token = $this->resolve_token();

		if ( '' === $token ) {
			return;
		}

		$clicks = new ClickRepository();
		$days   = absint( cosell_hive_get_setting( 'attribution_days', 30 ) );
		$click  = $clicks->find_valid( $token, $days );

		if ( ! $click ) {
			return;
		}

		$order->update_meta_data( self::META_TOKEN, $token );

		if ( method_exists( $order, 'get_id' ) ) {
			$this->commissions->record_order( $order->get_id(), $token );
		}
	}

	/**
	 * Report status changes to the hub and advance the commission.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 * @param object $order      Order object.
	 * @return void
	 */
	public function on_status_change( $order_id, $old_status, $new_status, $order ) {
		$order_id = absint( $order_id );

		if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
			return;
		}

		$token = $order->get_meta( self::META_TOKEN );

		if ( '' === $token ) {
			return;
		}

		$total = method_exists( $order, 'get_total' ) ? $order->get_total() : 0;

		$this->plugin->hub->report_order(
			array(
				'order_id'          => $order_id,
				'attribution_token' => $token,
				'status'            => $new_status,
				'amount_minor'      => (int) round( (float) $total * 100 ),
				'currency'          => method_exists( $order, 'get_currency' ) ? $order->get_currency() : '',
				'timestamp'         => time(),
			)
		);

		$this->commissions->handle_order_status( $order_id, $old_status, $new_status );
	}

	/**
	 * Resolve the active token: session first, cookie fallback.
	 *
	 * @return string
	 */
	private function resolve_token() {
		if ( function_exists( 'WC' ) && WC() && method_exists( WC(), 'session' ) && WC()->session ) {
			$token = WC()->session->get( 'cosell_hive_token' );

			if ( is_string( $token ) && '' !== $token ) {
				return substr( sanitize_text_field( $token ), 0, 128 );
			}
		}

		if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return substr( sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ), 0, 128 );
		}

		return '';
	}
}
