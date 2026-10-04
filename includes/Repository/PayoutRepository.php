<?php
/**
 * Payout request store.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manual payout flow (V1): requested → approved|rejected → paid.
 * Method details are stored encrypted; PII never hits the DB in clear.
 */
class PayoutRepository {

	const STATUS_REQUESTED = 'requested';
	const STATUS_APPROVED  = 'approved';
	const STATUS_PAID      = 'paid';
	const STATUS_REJECTED  = 'rejected';

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public function table() {
		global $wpdb;

		return $wpdb->prefix . \CoSellHive\Core\Schema::TABLE_PAYOUTS;
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
			affiliate_id bigint(20) unsigned NOT NULL,
			amount_minor bigint(20) unsigned NOT NULL,
			consumed_minor bigint(20) unsigned NOT NULL DEFAULT 0,
			method varchar(30) NOT NULL DEFAULT \'\',
			details_enc text NOT NULL,
			status varchar(20) NOT NULL DEFAULT \'requested\',
			note varchar(255) NOT NULL DEFAULT \'\',
			requested_at datetime NOT NULL,
			decided_at datetime DEFAULT NULL,
			decided_by bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY affiliate_status (affiliate_id, status),
			KEY status (status)
		) ' . $charset . ';';

		dbDelta( $sql );
	}

	/**
	 * Insert a payout request.
	 *
	 * @param array $data Row data.
	 * @return int Row ID (0 on failure).
	 */
	public function insert( array $data ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			$this->table(),
			array(
				'affiliate_id' => absint( $data['affiliate_id'] ),
				'amount_minor' => absint( $data['amount_minor'] ),
				'method'       => sanitize_key( $data['method'] ),
				'details_enc'  => $data['details_enc'],
				'status'       => self::STATUS_REQUESTED,
				'requested_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? absint( $wpdb->insert_id ) : 0;
	}

	/**
	 * Get one row.
	 *
	 * @param int $id Row ID.
	 * @return object|null
	 */
	public function get( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				absint( $id )
			)
		);
	}

	/**
	 * Affiliate's own history, newest first.
	 *
	 * @param int $affiliate_id Affiliate user ID.
	 * @param int $limit        Max rows.
	 * @return array
	 */
	public function history_for( $affiliate_id, $limit = 50 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE affiliate_id = %d ORDER BY requested_at DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				absint( $affiliate_id ),
				absint( $limit )
			)
		);
	}

	/**
	 * Admin queue across affiliates.
	 *
	 * @param string $status Filter status (empty = all).
	 * @param int    $limit  Max rows.
	 * @return array
	 */
	public function queue( $status = '', $limit = 50 ) {
		global $wpdb;

		if ( '' !== $status ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM ' . $this->table() . ' WHERE status = %s ORDER BY requested_at DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					sanitize_key( $status ),
					absint( $limit )
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' ORDER BY requested_at DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				absint( $limit )
			)
		);
	}

	/**
	 * Update decision fields.
	 *
	 * @param int    $id         Row ID.
	 * @param string $status     New status.
	 * @param int    $decided_by Admin user ID.
	 * @param string $note       Optional note.
	 * @param int    $consumed   Consumed minor units (for paid).
	 * @return bool
	 */
	public function decide( $id, $status, $decided_by, $note = '', $consumed = 0 ) {
		global $wpdb;

		return false !== $wpdb->update(
			$this->table(),
			array(
				'status'         => sanitize_key( $status ),
				'decided_by'     => absint( $decided_by ),
				'decided_at'     => current_time( 'mysql' ),
				'note'           => sanitize_text_field( $note ),
				'consumed_minor' => absint( $consumed ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%d', '%s', '%s', '%d' ),
			array( '%d' )
		);
	}
}
