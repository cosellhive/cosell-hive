<?php
/**
 * Commission ledger store.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local commission ledger. Money in minor units (integers), matching
 * the hub contract. One row per attributed order.
 */
class CommissionRepository {

	const STATUS_PENDING   = 'pending';
	const STATUS_CONFIRMED = 'confirmed';
	const STATUS_HOLDING   = 'holding';
	const STATUS_PAYABLE   = 'payable';
	const STATUS_PAID      = 'paid';
	const STATUS_REVERSED  = 'reversed';

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public function table() {
		global $wpdb;

		return $wpdb->prefix . 'cosell_commissions';
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
			click_id bigint(20) unsigned NOT NULL DEFAULT 0,
			order_id bigint(20) unsigned NOT NULL,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			affiliate_id bigint(20) unsigned NOT NULL DEFAULT 0,
			order_total_minor bigint(20) NOT NULL DEFAULT 0,
			amount_minor bigint(20) NOT NULL DEFAULT 0,
			adjustment_minor bigint(20) NOT NULL DEFAULT 0,
			currency varchar(10) NOT NULL DEFAULT \'\',
			status varchar(20) NOT NULL DEFAULT \'pending\',
			holding_until datetime DEFAULT NULL,
			hub_id varchar(64) NOT NULL DEFAULT \'\',
			note varchar(255) NOT NULL DEFAULT \'\',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_id (order_id),
			KEY affiliate_status (affiliate_id, status),
			KEY status_holding (status, holding_until)
		) ' . $charset . ';';

		dbDelta( $sql );
	}

	/**
	 * Insert a commission row.
	 *
	 * @param array $data Row data.
	 * @return int Row ID (0 on failure).
	 */
	public function insert( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			$this->table(),
			array(
				'click_id'          => absint( $data['click_id'] ),
				'order_id'          => absint( $data['order_id'] ),
				'product_id'        => absint( $data['product_id'] ),
				'affiliate_id'      => absint( $data['affiliate_id'] ),
				'order_total_minor' => absint( $data['order_total_minor'] ),
				'amount_minor'      => absint( $data['amount_minor'] ),
				'currency'          => sanitize_text_field( $data['currency'] ),
				'status'            => sanitize_key( $data['status'] ),
				'holding_until'     => isset( $data['holding_until'] ) ? sanitize_text_field( $data['holding_until'] ) : null,
				'hub_id'            => isset( $data['hub_id'] ) ? sanitize_text_field( $data['hub_id'] ) : '',
				'note'              => isset( $data['note'] ) ? sanitize_text_field( $data['note'] ) : '',
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? absint( $wpdb->insert_id ) : 0;
	}

	/**
	 * Find by order ID.
	 *
	 * @param int $order_id Order ID.
	 * @return object|null
	 */
	public function find_by_order( $order_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE order_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				absint( $order_id )
			)
		);
	}

	/**
	 * Update status (+ optional fields) on a row.
	 *
	 * @param int    $id     Row ID.
	 * @param string $status New status.
	 * @param array  $extra  Extra columns.
	 * @return bool
	 */
	public function set_status( $id, $status, array $extra = array() ) {
		global $wpdb;

		$data = array_merge(
			$extra,
			array(
				'status'     => sanitize_key( $status ),
				'updated_at' => current_time( 'mysql' ),
			)
		);

		$formats = array();
		foreach ( $data as $value ) {
			$formats[] = is_int( $value ) ? '%d' : '%s';
		}

		return false !== $wpdb->update(
			$this->table(),
			$data,
			array( 'id' => absint( $id ) ),
			$formats,
			array( '%d' )
		);
	}

	/**
	 * Rows in holding whose holding period has elapsed.
	 *
	 * @param int $limit Max rows.
	 * @return array
	 */
	public function due_for_payable( $limit = 100 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE status = %s AND holding_until IS NOT NULL AND holding_until <= %s LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				self::STATUS_HOLDING,
				current_time( 'mysql' ),
				absint( $limit )
			)
		);
	}

	/**
	 * Pending COD rows older than the expiry window.
	 *
	 * @param int $days Expiry window in days.
	 * @param int $limit Max rows.
	 * @return array
	 */
	public function expired_cod_pending( $days, $limit = 100 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE status = %s AND created_at <= %s LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				self::STATUS_PENDING,
				gmdate( 'Y-m-d H:i:s', time() - absint( $days ) * DAY_IN_SECONDS ),
				absint( $limit )
			)
		);
	}

	/**
	 * Per-product affiliate + sales stats for dashboards.
	 *
	 * @param int $product_id Product ID.
	 * @return array{affiliates: int, sales_30d: int}
	 */
	public function stats_for_product( $product_id ) {
		global $wpdb;

		$product_id = absint( $product_id );
		$since      = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );

		$affiliates = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT affiliate_id) FROM ' . $this->table() . ' WHERE product_id = %d AND status != %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$product_id,
				self::STATUS_REVERSED
			)
		);

		$sales = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $this->table() . ' WHERE product_id = %d AND status != %s AND created_at >= %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$product_id,
				self::STATUS_REVERSED,
				$since
			)
		);

		return array(
			'affiliates' => absint( $affiliates ),
			'sales_30d'  => absint( $sales ),
		);
	}

	/**
	 * Store-wide stats for the dashboard tiles.
	 *
	 * @return array{affiliates: int, paid_minor: int, gmv_month_minor: int}
	 */
	public function stats_for_store() {
		global $wpdb;

		$affiliates = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT affiliate_id) FROM ' . $this->table() . ' WHERE status != %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				self::STATUS_REVERSED
			)
		);

		$paid = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(amount_minor + adjustment_minor), 0) FROM ' . $this->table() . ' WHERE status = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				self::STATUS_PAID
			)
		);

		$gmv = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(order_total_minor), 0) FROM ' . $this->table() . ' WHERE status != %s AND created_at >= %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				self::STATUS_REVERSED,
				gmdate( 'Y-m-01 00:00:00' )
			)
		);

		return array(
			'affiliates'      => absint( $affiliates ),
			'paid_minor'      => absint( $paid ),
			'gmv_month_minor' => absint( $gmv ),
		);
	}
}
