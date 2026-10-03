<?php
/**
 * Onboarding REST endpoints (mockup 07).
 *
 * @package CoSellHive
 */

namespace CoSellHive\Modules\Affiliate;

use CoSellHive\Core\Plugin;
use CoSellHive\License\LicenseClientInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * License activation + site-role selection.
 */
class OnboardingController {

	const NAMESPACE = 'cosell-hive/v1';
	const ROUTE     = '/onboarding';

	/**
	 * License client.
	 *
	 * @var LicenseClientInterface
	 */
	private $license;

	/**
	 * Main plugin instance (hub access for entitlements).
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
		$this->plugin  = $plugin;
		$this->license = $plugin->license;
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
	 * Register onboarding routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'can_onboard' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			self::ROUTE . '/activate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'activate' ),
				'permission_callback' => array( $this, 'can_onboard' ),
				'args'                => array(
					'key'  => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'role' => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Site admins only.
	 *
	 * @return bool
	 */
	public function can_onboard() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Current onboarding state.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_status() {
		$stored = get_option( 'cosell_hive_license', array() );

		return rest_ensure_response(
			array(
				'connected'    => is_array( $stored ) && isset( $stored['key'] ),
				'site_role'    => get_option( 'cosell_hive_site_role', '' ),
				'hub'          => '' !== get_option( 'cosell_hive_hub_site_id', '' ),
				'entitlements' => $this->plugin->hub->get_entitlements(),
			)
		);
	}

	/**
	 * Activate a license key + record the site role.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function activate( \WP_REST_Request $request ) {
		$key = substr( sanitize_text_field( $request->get_param( 'key' ) ), 0, 128 );

		$role = $request->get_param( 'role' );

		if ( ! in_array( $role, array( 'store', 'affiliate', '' ), true ) ) {
			$role = '';
		}

		$this->maybe_register_hub();

		if ( '' !== $key ) {
			$result = $this->license->activate( $key );

			if ( ! is_wp_error( $result ) && isset( $result['valid'] ) && $result['valid'] ) {
				update_option( 'cosell_hive_license', array( 'key' => $key ) );
			}
		}

		update_option( 'cosell_hive_site_role', $role );
		update_option( 'cosell_hive_onboarded', 1 );

		return rest_ensure_response(
			array(
				'connected'    => true,
				'site_role'    => $role,
				'hub'          => '' !== get_option( 'cosell_hive_hub_site_id', '' ),
				'entitlements' => $this->plugin->hub->get_entitlements(),
			)
		);
	}

	/**
	 * Register with the hub when a signup token is configured.
	 *
	 * Never breaks onboarding: failures are silent and the site
	 * continues with free-tier defaults.
	 *
	 * @return void
	 */
	private function maybe_register_hub() {
		/**
		 * Filter the hub signup token (distributed out of band).
		 * Empty = skip hub registration and use free-tier defaults.
		 *
		 * @param string $token Signup token.
		 */
		$token = apply_filters( 'cosell_hive_hub_signup_token', '' );

		if ( '' === $token || '' !== get_option( 'cosell_hive_hub_site_id', '' ) ) {
			return;
		}

		/**
		 * Filter the hub API base URL (no trailing /v1 — added here).
		 *
		 * @param string $base Base URL.
		 */
		$base = untrailingslashit( apply_filters( 'cosell_hive_hub_base', 'https://api.cosellhive.com' ) );

		$response = wp_remote_post(
			$base . '/v1/sites/register',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $token,
				),
				'body'    => wp_json_encode(
					array(
						'site_url' => home_url(),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || empty( $data['site_id'] ) || empty( $data['install_secret'] ) ) {
			return;
		}

		update_option( 'cosell_hive_hub_site_id', sanitize_text_field( $data['site_id'] ) );
		update_option( 'cosell_hive_hub_secret', sanitize_text_field( $data['install_secret'] ) );
	}
}
