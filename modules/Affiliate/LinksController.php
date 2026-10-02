<?php
/**
 * Tracked-link minting endpoint.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Modules\Affiliate;

use CoSellHive\Core\Plugin;
use CoSellHive\Repository\ListingRepository;
use CoSellHive\Tracking\Redirector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves `POST /cosell-hive/v1/links` — one token per listing per affiliate.
 */
class LinksController {

	const NAMESPACE = 'cosell-hive/v1';
	const ROUTE     = '/links';

	/**
	 * Main plugin instance (hub client access).
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Main plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
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
	 * Register the links route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_link' ),
				'permission_callback' => array( $this, 'can_create' ),
				'args'                => array(
					'listing_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Any logged-in user promoting as an affiliate.
	 *
	 * @return bool
	 */
	public function can_create() {
		return is_user_logged_in();
	}

	/**
	 * Mint a tracked link for a live listing.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_link( \WP_REST_Request $request ) {
		$repository = new ListingRepository();
		$row        = $repository->get( $request->get_param( 'listing_id' ) );

		if ( ! $row || 'live' !== $row->status ) {
			return new \WP_Error(
				'cosell_hive_not_available',
				__( 'This product is not available for promotion.', 'cosell-hive' ),
				array( 'status' => 404 )
			);
		}

		$affiliate_id = get_current_user_id();
		$ref          = '' !== $row->hub_listing_id ? $row->hub_listing_id : 'p-' . absint( $row->product_id );

		$response = $this->plugin->hub->track_click(
			array(
				'listing_id'   => absint( $row->product_id ),
				'affiliate_id' => $affiliate_id,
			)
		);

		$token = isset( $response['token'] ) ? sanitize_text_field( $response['token'] ) : '';

		return rest_ensure_response(
			array(
				'token'        => $token,
				'url'          => Redirector::build_url( $ref, $affiliate_id ),
				'ref'          => $ref,
				'affiliate_id' => $affiliate_id,
			)
		);
	}
}
