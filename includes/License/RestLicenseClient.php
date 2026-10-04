<?php
/**
 * REST license client — talks to the CoSellHive Hub licensing endpoints.
 *
 * @package CoSellHive
 */

namespace CoSellHive\License;

use CoSellHive\Hub\Signature;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Production license adapter.
 */
class RestLicenseClient implements LicenseClientInterface {

	/**
	 * Get the stored site credentials.
	 *
	 * @return array{site_id: string, secret: string}
	 */
	private function credentials() {
		return array(
			'site_id' => get_option( 'cosell_hive_hub_site_id', '' ),
			'secret'  => get_option( 'cosell_hive_hub_secret', '' ),
		);
	}

	/**
	 * Get the hub base URL.
	 *
	 * @return string
	 */
	private function base() {
		return untrailingslashit( apply_filters( 'cosell_hive_hub_base', 'https://api.cosellhive.com' ) );
	}

	/**
	 * Activate a license key.
	 *
	 * @param string $key License key.
	 * @return array|\WP_Error
	 */
	public function activate( $key ) {
		$creds = $this->credentials();

		if ( '' === $creds['site_id'] || '' === $creds['secret'] ) {
			return new \WP_Error(
				'cosell_hive_no_credentials',
				__( 'Connect to the CoSellHive Hub before activating a license.', 'cosell-hive' )
			);
		}

		$body = wp_json_encode(
			array(
				'key'      => sanitize_text_field( $key ),
				'site_url' => home_url(),
			)
		);

		$headers = Signature::headers( $body, $creds['secret'] );

		$response = wp_remote_post(
			$this->base() . '/v1/license/activate',
			array(
				'timeout' => 15,
				'sslverify' => true,
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-Site-Id'    => $creds['site_id'],
					'X-Signature'  => $headers['signature'],
					'X-Timestamp'  => $headers['timestamp'],
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || ! isset( $data['valid'] ) ) {
			return new \WP_Error(
				'cosell_hive_license_failed',
				__( 'License activation failed. Please try again.', 'cosell-hive' )
			);
		}

		return array(
			'valid' => (bool) $data['valid'],
			'tier'  => isset( $data['tier'] ) ? sanitize_key( $data['tier'] ) : 'free',
		);
	}

	/**
	 * Validate the stored license.
	 *
	 * @return array|\WP_Error
	 */
	public function validate() {
		$creds = $this->credentials();

		if ( '' === $creds['site_id'] || '' === $creds['secret'] ) {
			return new \WP_Error(
				'cosell_hive_no_credentials',
				__( 'Connect to the CoSellHive Hub before validating a license.', 'cosell-hive' )
			);
		}

		$headers = Signature::headers( '', $creds['secret'] );

		$response = wp_remote_post(
			$this->base() . '/v1/license/validate',
			array(
				'timeout' => 15,
				'sslverify' => true,
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-Site-Id'    => $creds['site_id'],
					'X-Signature'  => $headers['signature'],
					'X-Timestamp'  => $headers['timestamp'],
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || ! isset( $data['valid'] ) ) {
			return new \WP_Error(
				'cosell_hive_license_failed',
				__( 'License validation failed.', 'cosell-hive' )
			);
		}

		return array(
			'valid' => (bool) $data['valid'],
			'tier'  => isset( $data['tier'] ) ? sanitize_key( $data['tier'] ) : 'free',
		);
	}

	/**
	 * Deactivate the current license.
	 *
	 * @return void
	 */
	public function deactivate() {
		delete_option( 'cosell_hive_license' );
	}
}
