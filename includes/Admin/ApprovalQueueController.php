<?php
/**
 * Marketplace approval queue REST endpoints.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Admin;

use CoSellHive\Hub\HubClientInterface;
use CoSellHive\Marketplace\ListingPresenter;
use CoSellHive\Modules\Store\ProductMeta;
use CoSellHive\Repository\ListingRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves the admin approval queue (mockup 05).
 *
 * Local rows mirror hub authority: every decision also notifies the hub.
 */
class ApprovalQueueController {

	const NAMESPACE = 'cosell-hive/v1';

	/**
	 * Hub client.
	 *
	 * @var HubClientInterface
	 */
	private $hub;

	/**
	 * Constructor.
	 *
	 * @param HubClientInterface $hub Hub client.
	 */
	public function __construct( HubClientInterface $hub ) {
		$this->hub = $hub;
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register queue routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/listings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_queue' ),
				'permission_callback' => array( $this, 'can_moderate' ),
				'args'                => array(
					'status' => array(
						'default'           => 'pending',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/listings/(?P<id>\d+)/approve',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'approve' ),
				'permission_callback' => array( $this, 'can_moderate' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/listings/(?P<id>\d+)/reject',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reject' ),
				'permission_callback' => array( $this, 'can_moderate' ),
			)
		);
	}

	/**
	 * Marketplace ops gate.
	 *
	 * @return bool
	 */
	public function can_moderate() {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Get the queue with product enrichment + anomaly flags.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_queue( \WP_REST_Request $request ) {
		$repository = new ListingRepository();
		$status     = $request->get_param( 'status' );

		if ( ! in_array( $status, array( '', 'pending', 'live', 'rejected', 'paused' ), true ) ) {
			$status = 'pending';
		}

		$rows  = $repository->get_queue( $status );
		$items = array();

		foreach ( $rows as $row ) {
			$item = $this->enrich( $row );

			if ( null !== $item ) {
				$items[] = $item;
			}
		}

		return rest_ensure_response(
			array(
				'counts' => $repository->get_counts(),
				'items'  => $items,
			)
		);
	}

	/**
	 * Approve a listing.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function approve( \WP_REST_Request $request ) {
		return $this->decide( $request, 'live' );
	}

	/**
	 * Reject a listing.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function reject( \WP_REST_Request $request ) {
		return $this->decide( $request, 'rejected' );
	}

	/**
	 * Apply a decision locally and notify the hub.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string           $status  Decision status.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function decide( \WP_REST_Request $request, $status ) {
		$repository = new ListingRepository();
		$row        = $repository->get( $request->get_param( 'id' ) );

		if ( ! $row ) {
			return new \WP_Error(
				'cosell_hive_not_found',
				__( 'Listing not found.', 'cosell-hive' ),
				array( 'status' => 404 )
			);
		}

		if ( ! in_array( $row->status, array( 'pending', 'paused' ), true ) ) {
			return new \WP_Error(
				'cosell_hive_already_decided',
				__( 'This listing already has a decision.', 'cosell-hive' ),
				array( 'status' => 409 )
			);
		}

		$reason = $request->get_param( 'reason' );
		$reason = is_string( $reason ) ? sanitize_textarea_field( $reason ) : '';

		$repository->set_status( $row->id, $status );
		update_post_meta( $row->product_id, ProductMeta::META_STATUS, $status );

		if ( '' !== $reason ) {
			update_post_meta( $row->product_id, '_cosell_hive_review_note', $reason );
		}

		$this->hub->upsert_listing(
			array(
				'product_id' => absint( $row->product_id ),
				'status'     => $status,
				'reason'     => $reason,
			)
		);

		return rest_ensure_response(
			array(
				'id'     => absint( $row->id ),
				'status' => $status,
			)
		);
	}

	/**
	 * Enrich a queue row with product data + anomaly flags.
	 *
	 * @param object $row Queue row.
	 * @return array|null
	 */
	private function enrich( $row ) {
		$presenter = new ListingPresenter();
		$item      = $presenter->present( $row );

		if ( null === $item ) {
			return null;
		}

		$item['flags'] = $this->anomaly_flags( $item['product_id'], $item['commission']['type'], $item['commission']['value'] );

		return $item;
	}

	/**
	 * V1 static anomaly flag: percent rate far above same-category peers.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $type       Commission type.
	 * @param string $value      Commission value.
	 * @return array
	 */
	private function anomaly_flags( $product_id, $type, $value ) {
		if ( 'percent' !== $type || ! is_numeric( $value ) ) {
			return array();
		}

		$repository = new ListingRepository();
		$peers      = $repository->category_percent_average( $product_id );

		if ( $peers['sample'] < 2 || $peers['average'] <= 0 ) {
			return array();
		}

		$ratio = (float) $value / $peers['average'];

		if ( $ratio < 2 ) {
			return array();
		}

		return array(
			array(
				'type'    => 'high_commission',
				/* translators: 1: rate multiple, 2: category average percent */
				'message' => sprintf( __( 'Commission rate is %1$sx the category average (%2$s%%) — verify before approving', 'cosell-hive' ), number_format_i18n( $ratio, 1 ), number_format_i18n( $peers['average'], 0 ) ),
			),
		);
	}
}
