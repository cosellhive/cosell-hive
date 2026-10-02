<?php
/**
 * Manual payout flow (V1): request → approve/reject → paid.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Wallet;

use CoSellHive\Commission\CommissionService;
use CoSellHive\Repository\CommissionRepository;
use CoSellHive\Repository\PayoutRepository;
use CoSellHive\Security\Crypto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payouts consume `payable` rows FIFO. Rows are indivisible: a row is
 * only marked paid when the remaining payout amount covers it fully,
 * so small dust can stay payable — the hub ledger will do exact
 * split accounting later.
 */
class PayoutService {

	/**
	 * Allowed payout methods (filterable).
	 *
	 * @return array
	 */
	public function methods() {
		/**
		 * Filter the available payout methods.
		 *
		 * @param array $methods Method slugs.
		 */
		return apply_filters( 'cosell_hive_payout_methods', array( 'bank', 'bkash', 'nagad', 'paypal' ) );
	}

	/**
	 * Request a payout.
	 *
	 * @param int    $affiliate_id Affiliate user ID.
	 * @param int    $amount_minor Amount in minor units.
	 * @param string $method       Method slug.
	 * @param string $details      Method details (account identifier).
	 * @return int|\WP_Error Payout ID or error.
	 */
	public function request( $affiliate_id, $amount_minor, $method, $details ) {
		$affiliate_id = absint( $affiliate_id );
		$amount_minor = absint( $amount_minor );
		$method       = sanitize_key( $method );
		$details      = substr( sanitize_text_field( $details ), 0, 500 );

		if ( ! in_array( $method, $this->methods(), true ) ) {
			return new \WP_Error(
				'cosell_hive_bad_method',
				__( 'Unknown payout method.', 'cosell-hive' )
			);
		}

		if ( '' === $details ) {
			return new \WP_Error(
				'cosell_hive_missing_details',
				__( 'Add your payout account details first.', 'cosell-hive' )
			);
		}

		$wallet    = new WalletService();
		$balances  = $wallet->balances( $affiliate_id );
		$minimum   = absint( cosell_hive_get_setting( 'payout_minimum_minor', 0 ) );

		if ( $amount_minor < 1 || $amount_minor > $balances['available_minor'] ) {
			return new \WP_Error(
				'cosell_hive_bad_amount',
				__( 'Amount must be between 1 and your available balance.', 'cosell-hive' )
			);
		}

		if ( $amount_minor < $minimum ) {
			return new \WP_Error(
				'cosell_hive_below_minimum',
				/* translators: %s: minimum payout amount in minor units */
				sprintf( __( 'Minimum payout is %s.', 'cosell-hive' ), $minimum )
			);
		}

		$enc = Crypto::encrypt( $details );

		if ( is_wp_error( $enc ) ) {
			return $enc;
		}

		$repository = new PayoutRepository();

		return $repository->insert(
			array(
				'affiliate_id' => $affiliate_id,
				'amount_minor' => $amount_minor,
				'method'       => $method,
				'details_enc'  => $enc,
			)
		);
	}

	/**
	 * Approve a request.
	 *
	 * @param int $id       Payout ID.
	 * @param int $admin_id Admin user ID.
	 * @return bool|\WP_Error
	 */
	public function approve( $id, $admin_id ) {
		return $this->transition( $id, PayoutRepository::STATUS_REQUESTED, PayoutRepository::STATUS_APPROVED, $admin_id );
	}

	/**
	 * Reject a request.
	 *
	 * @param int    $id       Payout ID.
	 * @param int    $admin_id Admin user ID.
	 * @param string $note     Reason.
	 * @return bool|\WP_Error
	 */
	public function reject( $id, $admin_id, $note = '' ) {
		$repository = new PayoutRepository();
		$result     = $this->transition( $id, PayoutRepository::STATUS_REQUESTED, PayoutRepository::STATUS_REJECTED, $admin_id );

		if ( true === $result && '' !== $note ) {
			$repository->decide( $id, PayoutRepository::STATUS_REJECTED, $admin_id, $note );
		}

		return $result;
	}

	/**
	 * Mark an approved payout paid and consume payable rows FIFO.
	 *
	 * @param int $id       Payout ID.
	 * @param int $admin_id Admin user ID.
	 * @return int|\WP_Error Consumed minor units or error.
	 */
	public function mark_paid( $id, $admin_id ) {
		$repository = new PayoutRepository();
		$payout     = $repository->get( $id );

		if ( ! $payout ) {
			return new \WP_Error(
				'cosell_hive_not_found',
				__( 'Payout not found.', 'cosell-hive' )
			);
		}

		if ( PayoutRepository::STATUS_APPROVED !== $payout->status ) {
			return new \WP_Error(
				'cosell_hive_bad_state',
				__( 'Only approved payouts can be marked paid.', 'cosell-hive' )
			);
		}

		$wallet    = new WalletService();
		$service   = new CommissionService();
		$remaining = absint( $payout->amount_minor );
		$consumed  = 0;

		foreach ( $wallet->payable_rows( absint( $payout->affiliate_id ) ) as $row ) {
			$amount = absint( $row->amount_minor );

			if ( $amount < 1 || $amount > $remaining ) {
				continue;
			}

			if ( $service->mark_paid( $row->id ) ) {
				$remaining -= $amount;
				$consumed  += $amount;
			}

			if ( $remaining < 1 ) {
				break;
			}
		}

		$note = 0 === $consumed
			? 'no payable rows to consume'
			: ( $remaining > 0 ? 'partial: dust remainder stays payable' : 'fully consumed' );

		$repository->decide( $payout->id, PayoutRepository::STATUS_PAID, $admin_id, $note, $consumed );

		return $consumed;
	}

	/**
	 * Guarded status transition for approve/reject.
	 *
	 * @param int    $id       Payout ID.
	 * @param string $from     Expected status.
	 * @param string $to       New status.
	 * @param int    $admin_id Admin user ID.
	 * @return bool|\WP_Error
	 */
	private function transition( $id, $from, $to, $admin_id ) {
		$repository = new PayoutRepository();
		$payout     = $repository->get( $id );

		if ( ! $payout ) {
			return new \WP_Error(
				'cosell_hive_not_found',
				__( 'Payout not found.', 'cosell-hive' )
			);
		}

		if ( $from !== $payout->status ) {
			return new \WP_Error(
				'cosell_hive_bad_state',
				__( 'This payout is no longer awaiting a decision.', 'cosell-hive' )
			);
		}

		return $repository->decide( $payout->id, $to, $admin_id );
	}
}
