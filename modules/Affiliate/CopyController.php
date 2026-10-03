<?php
/**
 * Marketing copy endpoint (wires the embed generator's button).
 *
 * @package CoSellHive
 */

namespace CoSellHive\Modules\Affiliate;

use CoSellHive\Core\Plugin;
use CoSellHive\Repository\ListingRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves `POST /cosell-hive/v1/copy` for live listings only.
 */
class CopyController {

	const NAMESPACE = 'cosell-hive/v1';
	const ROUTE     = '/copy';

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
	 * Register the copy route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'generate' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'listing_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'tone'       => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Generate copy for a live listing.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate( \WP_REST_Request $request ) {
		$repository = new ListingRepository();
		$row        = $repository->get( $request->get_param( 'listing_id' ) );

		if ( ! $row || 'live' !== $row->status ) {
			return new \WP_Error(
				'cosell_hive_not_available',
				__( 'This product is not available for promotion.', 'cosell-hive' ),
				array( 'status' => 404 )
			);
		}

		$ref  = '' !== $row->hub_listing_id ? $row->hub_listing_id : 'p-' . absint( $row->product_id );
		$tone = substr( $request->get_param( 'tone' ), 0, 60 );
		$copy = $this->plugin->hub->generate_copy( $ref, $tone );

		if ( empty( $copy['blurb'] ) ) {
			return new \WP_Error(
				'cosell_hive_copy_failed',
				__( 'Copy generation is unavailable right now.', 'cosell-hive' ),
				array( 'status' => 502 )
			);
		}

		return rest_ensure_response(
			array(
				'blurb'   => $copy['blurb'],
				'caption' => $copy['caption'],
			)
		);
	}
}
