<?php
/**
 * Symmetric encryption for payout PII.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AES-256-CBC with per-message IVs. The key derives from the site's
 * unique salts (never stored in the DB), so a DB leak alone exposes
 * nothing — but rotating salts orphans stored details by design.
 */
final class Crypto {

	/**
	 * Derive the site key.
	 *
	 * @return string 32-byte key (empty when salts are unset/default).
	 */
	public static function key() {
		if ( ! defined( 'AUTH_KEY' ) || ! defined( 'SECURE_AUTH_SALT' ) ) {
			return '';
		}

		$material = AUTH_KEY . SECURE_AUTH_SALT;

		if ( false !== strpos( $material, 'put your unique phrase here' ) ) {
			return '';
		}

		return hash_hkdf( 'sha256', $material, 32, 'cosell-hive-payouts' );
	}

	/**
	 * Encrypt plaintext to a storable string.
	 *
	 * @param string $plain Plaintext.
	 * @return string|\WP_Error Base64 payload or error when no key.
	 */
	public static function encrypt( $plain ) {
		$key = self::key();

		if ( '' === $key || ! function_exists( 'openssl_encrypt' ) ) {
			return new \WP_Error(
				'cosell_hive_no_crypto',
				__( 'Secure storage is unavailable: set unique salts in wp-config.php.', 'cosell-hive' )
			);
		}

		$iv = random_bytes( 16 );
		$ct = openssl_encrypt( $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $ct ) {
			return new \WP_Error(
				'cosell_hive_encrypt_failed',
				__( 'Could not secure payout details. Try again.', 'cosell-hive' )
			);
		}

		return base64_encode( $iv . $ct ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a payload from encrypt().
	 *
	 * @param string $payload Base64 payload.
	 * @return string|\WP_Error Plaintext or error.
	 */
	public static function decrypt( $payload ) {
		$key = self::key();

		if ( '' === $key || ! function_exists( 'openssl_decrypt' ) ) {
			return new \WP_Error(
				'cosell_hive_no_crypto',
				__( 'Secure storage is unavailable.', 'cosell-hive' )
			);
		}

		$raw = base64_decode( $payload, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $raw || strlen( $raw ) < 17 ) {
			return new \WP_Error(
				'cosell_hive_decrypt_failed',
				__( 'Stored payout details are unreadable.', 'cosell-hive' )
			);
		}

		$plain = openssl_decrypt( substr( $raw, 16 ), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );

		if ( false === $plain ) {
			return new \WP_Error(
				'cosell_hive_decrypt_failed',
				__( 'Stored payout details are unreadable.', 'cosell-hive' )
			);
		}

		return $plain;
	}

	/**
	 * Mask details for list display (e.g. ****1234).
	 *
	 * @param string $plain Plaintext details.
	 * @return string
	 */
	public static function mask( $plain ) {
		$plain = trim( $plain );

		if ( strlen( $plain ) <= 4 ) {
			return '****';
		}

		return '****' . substr( $plain, -4 );
	}
}
