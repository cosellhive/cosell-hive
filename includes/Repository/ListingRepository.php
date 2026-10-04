<?php
/**
 * Local listing workflow store.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Repository;

use CoSellHive\Modules\Store\ProductMeta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists marketplace workflow state per product.
 *
 * This table is a local mirror of hub authority — approve/reject also
 * notifies the hub. All queries use $wpdb->prepare.
 */
class ListingRepository {

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public function table() {
		global $wpdb;

		return $wpdb->prefix . \CoSellHive\Core\Schema::TABLE_LISTINGS;
	}

	/**
	 * Create the table (dbDelta-safe, runs on activate + version upgrades).
	 *
	 * @return void
	 */
	public function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		$sql = 'CREATE TABLE ' . $this->table() . ' (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			hub_listing_id varchar(64) NOT NULL DEFAULT \'\',
			status varchar(20) NOT NULL DEFAULT \'pending\',
			submitted_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY product_id (product_id),
			KEY status (status)
		) ' . $charset . ';';

		dbDelta( $sql );
	}

	/**
	 * Insert or refresh the row for a product after a sync.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $status     Workflow status.
	 * @param string $hub_id     Hub listing ID.
	 * @return void
	 */
	public function upsert( $product_id, $status, $hub_id = '' ) {
		global $wpdb;

		$product_id = absint( $product_id );
		$now        = current_time( 'mysql' );

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . $this->table() . ' WHERE product_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$product_id
			)
		);

		if ( $existing ) {
			$data    = array(
				'status'     => sanitize_key( $status ),
				'updated_at' => $now,
			);
			$formats = array( '%s', '%s' );

			if ( '' !== $hub_id ) {
				$data['hub_listing_id'] = sanitize_text_field( $hub_id );
				$formats[]              = '%s';
			}

			$wpdb->update(
				$this->table(),
				$data,
				array( 'id' => absint( $existing ) ),
				$formats,
				array( '%d' )
			);

			return;
		}

		$wpdb->insert(
			$this->table(),
			array(
				'product_id'     => $product_id,
				'hub_listing_id' => sanitize_text_field( $hub_id ),
				'status'         => sanitize_key( $status ),
				'submitted_at'   => $now,
				'updated_at'     => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get one row by ID.
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
	 * Get the queue, newest first.
	 *
	 * @param string $status Filter status (empty = all).
	 * @param int    $limit  Max rows.
	 * @return array
	 */
	public function get_queue( $status = '', $limit = 50 ) {
		global $wpdb;

		if ( '' !== $status ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM ' . $this->table() . ' WHERE status = %s ORDER BY updated_at DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					sanitize_key( $status ),
					absint( $limit )
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' ORDER BY updated_at DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				absint( $limit )
			)
		);
	}

	/**
	 * Find a row by product ID.
	 *
	 * @param int $product_id Product ID.
	 * @return object|null
	 */
	public function find_by_product_id( $product_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE product_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				absint( $product_id )
			)
		);
	}

	/**
	 * Find a row by hub listing ID.
	 *
	 * @param string $hub_id Hub listing ID.
	 * @return object|null
	 */
	public function find_by_hub_id( $hub_id ) {
		global $wpdb;

		$hub_id = sanitize_text_field( $hub_id );

		if ( '' === $hub_id ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE hub_listing_id = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$hub_id
			)
		);
	}

	/**
	 * Count rows per status.
	 *
	 * @return array
	 */
	public function get_counts() {
		global $wpdb;

		$rows = $wpdb->get_results(
			'SELECT status, COUNT(*) AS c FROM ' . $this->table() . ' GROUP BY status', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		$counts = array(
			'pending'  => 0,
			'live'     => 0,
			'rejected' => 0,
			'paused'   => 0,
		);

		foreach ( $rows as $row ) {
			if ( isset( $counts[ $row['status'] ] ) ) {
				$counts[ $row['status'] ] = absint( $row['c'] );
			}
		}

		return $counts;
	}

	/**
	 * Set a row's decision status.
	 *
	 * @param int    $id     Row ID.
	 * @param string $status New status.
	 * @return bool
	 */
	public function set_status( $id, $status ) {
		global $wpdb;

		return false !== $wpdb->update(
			$this->table(),
			array(
				'status'     => sanitize_key( $status ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Average percent commission across live+pending listings in the same categories.
	 *
	 * Used for the V1 static anomaly flag (rate far above peers).
	 *
	 * @param int $product_id Subject product.
	 * @return array{average: float, sample: int}
	 */
	public function category_percent_average( $product_id ) {
		$terms = wp_get_post_terms( absint( $product_id ), 'product_cat', array( 'fields' => 'ids' ) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array(
				'average' => 0,
				'sample'  => 0,
			);
		}

		$query = new \WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'post__not_in'   => array( absint( $product_id ) ),
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => array_map( 'absint', $terms ),
					),
				),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => ProductMeta::META_ENABLED,
						'value' => 'yes',
					),
					array(
						'key'   => ProductMeta::META_TYPE,
						'value' => 'percent',
					),
				),
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$total = 0.0;
		$count = 0;

		foreach ( $query->posts as $id ) {
			$value = get_post_meta( $id, ProductMeta::META_VALUE, true );

			if ( is_numeric( $value ) ) {
				$total += (float) $value;
				++$count;
			}
		}

		return array(
			'average' => $count > 0 ? $total / $count : 0,
			'sample'  => $count,
		);
	}
}
