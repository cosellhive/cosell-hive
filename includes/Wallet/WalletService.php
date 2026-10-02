<?php
/**
 * Affiliate wallet balances (derived, never stored).
 *
 * @package CoSellHive
 */

namespace CoSellHive\Wallet;

use CoSellHive\Repository\CommissionRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Balances derive from `payable` rows only. Pending/holding rows are
 * shown as pending; post-payout refund adjustments reduce available.
 */
class WalletService {

	/**
	 * Get balances for an affiliate, in minor units.
	 *
	 * @param int $affiliate_id Affiliate user ID.
	 * @return array{pending_minor: int, payable_minor: int, available_minor: int, paid_minor: int}
	 */
	public function balances( $affiliate_id ) {
		global $wpdb;

		$affiliate_id = absint( $affiliate_id );
		$repository   = new CommissionRepository();
		$table        = $repository->table();

		$pending = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(amount_minor), 0) FROM ' . $table . ' WHERE affiliate_id = %d AND status IN (%s, %s, %s)', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$affiliate_id,
				CommissionRepository::STATUS_PENDING,
				CommissionRepository::STATUS_CONFIRMED,
				CommissionRepository::STATUS_HOLDING
			)
		);

		$payable = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(amount_minor), 0) FROM ' . $table . ' WHERE affiliate_id = %d AND status = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$affiliate_id,
				CommissionRepository::STATUS_PAYABLE
			)
		);

		$adjustments = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(adjustment_minor), 0) FROM ' . $table . ' WHERE affiliate_id = %d AND status = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$affiliate_id,
				CommissionRepository::STATUS_REVERSED
			)
		);

		$paid = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(amount_minor), 0) FROM ' . $table . ' WHERE affiliate_id = %d AND status = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$affiliate_id,
				CommissionRepository::STATUS_PAID
			)
		);

		$available = absint( $payable ) + absint( $adjustments );

		return array(
			'pending_minor'   => absint( $pending ),
			'payable_minor'   => absint( $payable ),
			'available_minor' => max( 0, $available ),
			'paid_minor'      => absint( $paid ),
		);
	}

	/**
	 * Payable commission rows, oldest first (FIFO consumption order).
	 *
	 * @param int $affiliate_id Affiliate user ID.
	 * @param int $limit        Max rows.
	 * @return array
	 */
	public function payable_rows( $affiliate_id, $limit = 200 ) {
		global $wpdb;

		$repository = new CommissionRepository();

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $repository->table() . ' WHERE affiliate_id = %d AND status = %s ORDER BY id ASC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				absint( $affiliate_id ),
				CommissionRepository::STATUS_PAYABLE,
				absint( $limit )
			)
		);
	}
}
