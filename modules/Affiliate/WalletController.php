<?php
/**
 * Wallet + payout REST endpoints.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Modules\Affiliate;

use CoSellHive\Repository\PayoutRepository;
use CoSellHive\Security\Crypto;
use CoSellHive\Wallet\PayoutService;
use CoSellHive\Wallet\WalletService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves the affiliate wallet (mockup 04) and the admin payout queue.
 */
class WalletController {

	const NAMESPACE = 'cosell-hive/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register wallet routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/wallet',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_wallet' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/payouts',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_payouts' ),
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'status' => array(
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'request_payout' ),
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'amount_minor' => array(
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'method'       => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
						'details'      => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		foreach ( array( 'approve', 'reject', 'mark-paid' ) as $action ) {
			register_rest_route(
				self::NAMESPACE,
				'/payouts/(?P<id>\d+)/' . $action,
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'decide_payout' ),
					'permission_callback' => array( $this, 'can_manage_payouts' ),
					'args'                => array(
						'note' => array(
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				)
			);
		}
	}

	/**
	 * Admin-only gate for payout decisions.
	 *
	 * @return bool
	 */
	public function can_manage_payouts() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get the current user's wallet.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_wallet() {
		$affiliate_id = get_current_user_id();
		$wallet       = new WalletService();
		$balances     = $wallet->balances( $affiliate_id );
		$service      = new PayoutService();

		return rest_ensure_response(
			array(
				'balances' => array(
					'pending'   => $balances['pending_minor'] / 100,
					'payable'   => $balances['payable_minor'] / 100,
					'available' => $balances['available_minor'] / 100,
					'paid'      => $balances['paid_minor'] / 100,
				),
				'methods'  => $service->methods(),
				'minimum'  => absint( cosell_hive_get_setting( 'payout_minimum_minor', 0 ) ) / 100,
			)
		);
	}

	/**
	 * List payouts: own history, or the admin queue.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_payouts( \WP_REST_Request $request ) {
		$repository = new PayoutRepository();

		if ( current_user_can( 'manage_options' ) && 'mine' !== $request->get_param( 'scope' ) ) {
			$rows  = $repository->queue( $request->get_param( 'status' ) );
			$items = array();

			foreach ( $rows as $row ) {
				$user = get_user_by( 'id', absint( $row->affiliate_id ) );

				$items[] = array(
					'id'           => absint( $row->id ),
					'affiliate'    => $user ? $user->display_name : __( '(deleted user)', 'cosell-hive' ),
					'amount'       => absint( $row->amount_minor ) / 100,
					'method'       => $row->method,
					'details_mask' => $this->masked( $row->details_enc ),
					'status'       => $row->status,
					'note'         => $row->note,
					'requested_at' => $row->requested_at,
				);
			}

			return rest_ensure_response( array( 'items' => $items ) );
		}

		$rows  = $repository->history_for( get_current_user_id() );
		$items = array();

		foreach ( $rows as $row ) {
			$items[] = array(
				'id'           => absint( $row->id ),
				'amount'       => absint( $row->amount_minor ) / 100,
				'method'       => $row->method,
				'details_mask' => $this->masked( $row->details_enc ),
				'status'       => $row->status,
				'note'         => $row->note,
				'requested_at' => $row->requested_at,
			);
		}

		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * Request a payout for the current user.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function request_payout( \WP_REST_Request $request ) {
		$service = new PayoutService();
		$id      = $service->request(
			get_current_user_id(),
			$request->get_param( 'amount_minor' ),
			$request->get_param( 'method' ),
			$request->get_param( 'details' )
		);

		if ( is_wp_error( $id ) ) {
			$id->add_data( array( 'status' => 400 ) );

			return $id;
		}

		return rest_ensure_response( array( 'id' => absint( $id ) ) );
	}

	/**
	 * Approve / reject / mark-paid (admin).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function decide_payout( \WP_REST_Request $request ) {
		$route  = $request->get_route();
		$action = 1 === preg_match( '/\/(approve|reject|mark-paid)$/', $route, $m ) ? $m[1] : '';
		$admin  = get_current_user_id();

		$service = new PayoutService();

		if ( 'approve' === $action ) {
			$result = $service->approve( $request->get_param( 'id' ), $admin );
		} elseif ( 'reject' === $action ) {
			$result = $service->reject( $request->get_param( 'id' ), $admin, $request->get_param( 'note' ) );
		} else {
			$result = $service->mark_paid( $request->get_param( 'id' ), $admin );

			if ( ! is_wp_error( $result ) ) {
				return rest_ensure_response( array( 'consumed_minor' => absint( $result ) ) );
			}
		}

		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );

			return $result;
		}

		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * Decrypt + mask stored details for display.
	 *
	 * @param string $enc Encrypted payload.
	 * @return string
	 */
	private function masked( $enc ) {
		$plain = Crypto::decrypt( $enc );

		if ( is_wp_error( $plain ) ) {
			return __( '(unreadable)', 'cosell-hive' );
		}

		return Crypto::mask( $plain );
	}
}
