<?php
/**
 * Commission state machine (PRD §5.2).
 *
 * @package CoSellHive
 */

namespace CoSellHive\Commission;

use CoSellHive\Modules\Store\ProductMeta;
use CoSellHive\Repository\ClickRepository;
use CoSellHive\Repository\CommissionRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records attributed orders and advances them through
 * pending → confirmed → holding → payable → paid (→ reversed).
 *
 * V1 money math (documented, hub will own this at scale):
 * base = attributed product's line subtotal; flat = once per order,
 * percent = base × rate, capped per order. Post-payout refunds leave
 * a negative adjustment for the Phase 5 wallet to consume.
 */
class CommissionService {

	/**
	 * Record an attributed order as pending.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $token    Attribution token.
	 * @return int Commission row ID (0 when unattributable).
	 */
	public function record_order( $order_id, $token ) {
		$order_id = absint( $order_id );

		if ( ! function_exists( 'wc_get_order' ) ) {
			return 0;
		}

		$clicks = new ClickRepository();
		$click  = $clicks->find( $token );

		if ( ! $click ) {
			return 0;
		}

		$repository = new CommissionRepository();

		if ( $repository->find_by_order( $order_id ) ) {
			return 0;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return 0;
		}

		$amount = $this->compute_amount( $order, absint( $click->product_id ) );

		return $repository->insert(
			array(
				'click_id'          => absint( $click->id ),
				'order_id'          => $order_id,
				'product_id'        => absint( $click->product_id ),
				'affiliate_id'      => absint( $click->affiliate_id ),
				'order_total_minor' => $this->to_minor( $order->get_total() ),
				'amount_minor'      => $amount,
				'currency'          => method_exists( $order, 'get_currency' ) ? $order->get_currency() : '',
				'status'            => CommissionRepository::STATUS_PENDING,
			)
		);
	}

	/**
	 * Advance a commission on an order status change.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $old_status Old order status.
	 * @param string $new_status New order status.
	 * @return void
	 */
	public function handle_order_status( $order_id, $old_status, $new_status ) {
		$repository = new CommissionRepository();
		$row        = $repository->find_by_order( absint( $order_id ) );

		if ( ! $row ) {
			return;
		}

		if ( in_array( $row->status, array( CommissionRepository::STATUS_PAID, CommissionRepository::STATUS_REVERSED ), true ) ) {
			if ( CommissionRepository::STATUS_PAID === $row->status && $this->is_reversal( $new_status ) ) {
				// Post-payout refund: carry a negative balance forward, never claw back.
				$repository->set_status(
					$row->id,
					CommissionRepository::STATUS_REVERSED,
					array(
						'adjustment_minor' => -1 * absint( $row->amount_minor ),
						'note'             => 'refunded after payout',
					)
				);
			}

			return;
		}

		if ( $this->is_reversal( $new_status ) ) {
			$repository->set_status( $row->id, CommissionRepository::STATUS_REVERSED, array( 'note' => 'order ' . $new_status ) );

			return;
		}

		if ( CommissionRepository::STATUS_PENDING !== $row->status ) {
			return;
		}

		if ( $this->is_cod_order( $order_id ) && 'completed' !== $new_status ) {
			// COD stays pending until delivered.
			return;
		}

		if ( in_array( $new_status, array( 'processing', 'completed', 'on-hold' ), true ) ) {
			$holding_days = absint( cosell_hive_get_setting( 'holding_days', 7 ) );

			$repository->set_status(
				$row->id,
				CommissionRepository::STATUS_HOLDING,
				array(
					'holding_until' => gmdate( 'Y-m-d H:i:s', time() + $holding_days * DAY_IN_SECONDS ),
					'note'          => 'confirmed via ' . $new_status,
				)
			);
		}
	}

	/**
	 * Move elapsed holding rows to payable. Called by the daily cron.
	 *
	 * @return int Rows advanced.
	 */
	public function advance_due() {
		$repository = new CommissionRepository();
		$rows       = $repository->due_for_payable( 200 );
		$count      = 0;

		foreach ( $rows as $row ) {
			if ( $repository->set_status( $row->id, CommissionRepository::STATUS_PAYABLE ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Expire COD pendings older than the window. Called by the daily cron.
	 *
	 * @return int Rows expired.
	 */
	public function expire_stale_cod() {
		$days       = absint( cosell_hive_get_setting( 'cod_expiry_days', 30 ) );
		$repository = new CommissionRepository();
		$rows       = $repository->expired_cod_pending( $days, 200 );
		$count      = 0;

		foreach ( $rows as $row ) {
			if ( $repository->set_status( $row->id, CommissionRepository::STATUS_REVERSED, array( 'note' => 'COD confirmation window elapsed' ) ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Mark a payable commission paid (Phase 5 payouts call this).
	 *
	 * @param int $id Row ID.
	 * @return bool
	 */
	public function mark_paid( $id ) {
		$repository = new CommissionRepository();

		return $repository->set_status( absint( $id ), CommissionRepository::STATUS_PAID );
	}

	/**
	 * Compute the commission in minor units.
	 *
	 * @param object $order      Woo order.
	 * @param int    $product_id Attributed product ID.
	 * @return int
	 */
	private function compute_amount( $order, $product_id ) {
		$type  = get_post_meta( $product_id, ProductMeta::META_TYPE, true );
		$value = get_post_meta( $product_id, ProductMeta::META_VALUE, true );
		$cap   = get_post_meta( $product_id, ProductMeta::META_CAP, true );

		if ( ! in_array( $type, array( 'flat', 'percent' ), true ) || ! is_numeric( $value ) ) {
			return 0;
		}

		if ( 'flat' === $type ) {
			$amount = $this->to_minor( $value );
		} else {
			$amount = (int) round( $this->line_subtotal_minor( $order, $product_id ) * ( (float) $value / 100 ) );
		}

		if ( '' !== $cap && is_numeric( $cap ) ) {
			$amount = min( $amount, $this->to_minor( $cap ) );
		}

		return max( 0, $amount );
	}

	/**
	 * Subtotal of the attributed product's line, in minor units.
	 *
	 * @param object $order      Woo order.
	 * @param int    $product_id Product ID.
	 * @return int
	 */
	private function line_subtotal_minor( $order, $product_id ) {
		if ( ! method_exists( $order, 'get_items' ) ) {
			return 0;
		}

		foreach ( $order->get_items() as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
				continue;
			}

			$match = absint( $item->get_product_id() ) === absint( $product_id );

			if ( ! $match && method_exists( $item, 'get_variation_id' ) ) {
				$match = absint( $item->get_variation_id() ) === absint( $product_id );
			}

			if ( $match && method_exists( $item, 'get_subtotal' ) ) {
				return $this->to_minor( $item->get_subtotal() );
			}
		}

		return 0;
	}

	/**
	 * Decimal amount to minor units (assumes 2dp currency).
	 *
	 * @param mixed $amount Decimal amount.
	 * @return int
	 */
	private function to_minor( $amount ) {
		return (int) round( (float) $amount * 100 );
	}

	/**
	 * Check whether an order is cash-on-delivery.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	private function is_cod_order( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return false;
		}

		$order = wc_get_order( $order_id );

		return $order && method_exists( $order, 'get_payment_method' ) && 'cod' === $order->get_payment_method();
	}

	/**
	 * Order statuses that reverse a commission.
	 *
	 * @param string $status New order status.
	 * @return bool
	 */
	private function is_reversal( $status ) {
		return in_array( $status, array( 'cancelled', 'refunded', 'failed' ), true );
	}
}
