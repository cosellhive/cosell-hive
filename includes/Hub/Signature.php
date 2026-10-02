<?php
/**
 * Hub request signing (PRD §10.2).
 *
 * @package CoSellHive
 */

namespace CoSellHive\Hub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HMAC-SHA256 signing for plugin → hub webhooks.
 *
 * Used by the REST hub client (Track B). The mock ignores signatures;
 * this class exists so signing is implemented once, correctly.
 */
final class Signature {

	/**
	 * Replay window in seconds.
	 *
	 * @var int
	 */
	const SKEW = 300;

	/**
	 * Sign a request body.
	 *
	 * @param string $body   Raw request body.
	 * @param string $secret Install secret.
	 * @return string
	 */
	public static function sign( $body, $secret ) {
		return hash_hmac( 'sha256', $body, $secret );
	}

	/**
	 * Build the auth headers for a body.
	 *
	 * @param string $body   Raw request body.
	 * @param string $secret Install secret.
	 * @return array{timestamp: int, signature: string}
	 */
	public static function headers( $body, $secret ) {
		$timestamp = time();

		return array(
			'timestamp' => $timestamp,
			'signature' => hash_hmac( 'sha256', $timestamp . '.' . $body, $secret ),
		);
	}

	/**
	 * Verify a signature over `timestamp.body`.
	 *
	 * @param int    $timestamp Received timestamp.
	 * @param string $body      Raw request body.
	 * @param string $secret    Install secret.
	 * @param string $signature Received signature.
	 * @return bool
	 */
	public static function verify( $timestamp, $body, $secret, $signature ) {
		// Empty bodies (GET, bodiless POSTs) are legitimate — only null is rejected.
		if ( null === $body || '' === $secret || '' === $signature ) {
			return false;
		}

		if ( ! self::timestamp_valid( $timestamp ) ) {
			return false;
		}

		return hash_equals( hash_hmac( 'sha256', absint( $timestamp ) . '.' . $body, $secret ), $signature );
	}

	/**
	 * Check a timestamp is within the replay window.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return bool
	 */
	public static function timestamp_valid( $timestamp ) {
		return abs( time() - absint( $timestamp ) ) <= self::SKEW;
	}
}
