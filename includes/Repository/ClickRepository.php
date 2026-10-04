<?php
/**
 * Click ledger store.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local click log. The hub keeps the authoritative log; this copy
 * exists so attribution (token → affiliate/product) resolves locally
 * at checkout without a network round-trip. No raw IPs — hashes only.
 */
class ClickRepository {

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public function table() {
		global $wpdb;

		return $wpdb->prefix . \CoSellHive\Core\Schema::TABLE_CLICKS;
	}

	/**
	 * Create the table (dbDelta-safe).
	 *
	 * @return void
	 */
	public function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		$sql = 'CREATE TABLE ' . $this->table() . ' (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token varchar(128) NOT NULL,
			product_id bigint(20) unsigned NOT NULL,
			affiliate_id bigint(20) unsigned NOT NULL DEFAULT 0,
			ip_hash varchar(64) NOT NULL DEFAULT \'\',
			ua_hash varchar(64) NOT NULL DEFAULT \'\',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY product_id (product_id),
			KEY affiliate_id (affiliate_id)
		) ' . $charset . ';';

		dbDelta( $sql );
	}

	/**
	 * Record a click. Duplicate tokens (replays) are ignored.
	 *
	 * @param string $token        Server-issued token.
	 * @param int    $product_id   Product ID.
	 * @param int    $affiliate_id Affiliate user ID.
	 * @return int Click ID (0 on failure).
	 */
	public function record( $token, $product_id, $affiliate_id ) {
		global $wpdb;

		$token = substr( sanitize_text_field( $token ), 0, 128 );

		if ( '' === $token ) {
			return 0;
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$inserted = $wpdb->insert(
			$this->table(),
			array(
				'token'        => $token,
				'product_id'   => absint( $product_id ),
				'affiliate_id' => absint( $affiliate_id ),
				'ip_hash'      => '' !== $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) : '',
				'ua_hash'      => '' !== $ua ? hash_hmac( 'sha256', $ua, wp_salt( 'auth' ) ) : '',
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			$existing = $this->find( $token );

			return $existing ? absint( $existing->id ) : 0;
		}

		return absint( $wpdb->insert_id );
	}

	/**
	 * Find a click by token.
	 *
	 * @param string $token Token.
	 * @return object|null
	 */
	public function find( $token ) {
		global $wpdb;

		$token = substr( sanitize_text_field( $token ), 0, 128 );

		if ( '' === $token ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE token = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$token
			)
		);
	}

	/**
	 * Find a click by token within the attribution window.
	 *
	 * @param string $token Token.
	 * @param int    $days  Attribution window in days.
	 * @return object|null
	 */
	public function find_valid( $token, $days ) {
		$click = $this->find( $token );

		if ( ! $click ) {
			return null;
		}

		$created = strtotime( $click->created_at . ' UTC' );

		if ( ! $created || ( time() - $created ) > absint( $days ) * DAY_IN_SECONDS ) {
			return null;
		}

		return $click;
	}
}
