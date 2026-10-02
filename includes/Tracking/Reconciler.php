<?php
/**
 * Daily maintenance: advance holdings, expire COD, backfill misses.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Tracking;

use CoSellHive\Commission\CommissionService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs on `cosell_hive_daily_maintenance`. The cross-store mismatch
 * ladder (notice → pause → suspend) lives in the hub reconciler;
 * this worker keeps the local ledger consistent and self-heals rows
 * missed by the live status hooks.
 */
class Reconciler {

	const CRON_HOOK = 'cosell_hive_daily_maintenance';

	/**
	 * Register the cron hook.
	 *
	 * @return void
	 */
	public function register() {
		add_action( self::CRON_HOOK, array( $this, 'run' ) );
	}

	/**
	 * Schedule the daily run (idempotent).
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Run all maintenance passes.
	 *
	 * @return array{advanced: int, expired: int, backfilled: int}
	 */
	public function run() {
		$service = new CommissionService();

		$result = array(
			'advanced'   => $service->advance_due(),
			'expired'    => $service->expire_stale_cod(),
			'backfilled' => $this->backfill_missing(),
		);

		/**
		 * Fires after the daily maintenance run. The hub adapter observes
		 * this for cross-store mismatch accounting.
		 *
		 * @param array $result Pass counts.
		 */
		do_action( 'cosell_hive_reconcile_run', $result );

		return $result;
	}

	/**
	 * Create commission rows for token-bearing orders the live hooks missed.
	 *
	 * @return int Rows created.
	 */
	private function backfill_missing() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}

		$orders = wc_get_orders(
			array(
				'limit'        => 50,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'meta_key'     => OrderReporter::META_TOKEN, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare' => 'EXISTS',
			)
		);

		$service = new CommissionService();
		$count   = 0;

		foreach ( $orders as $order ) {
			if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) || ! method_exists( $order, 'get_meta' ) ) {
				continue;
			}

			$token = $order->get_meta( OrderReporter::META_TOKEN );

			if ( '' === $token ) {
				continue;
			}

			$id = $service->record_order( $order->get_id(), $token );

			if ( $id > 0 ) {
				// Bring the fresh row up to speed with the order's current state.
				$status = method_exists( $order, 'get_status' ) ? $order->get_status() : '';
				$service->handle_order_status( $order->get_id(), '', $status );
				++$count;
			}
		}

		return $count;
	}
}
