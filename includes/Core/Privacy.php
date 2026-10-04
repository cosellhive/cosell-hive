<?php
/**
 * Privacy: policy content, exporter, eraser.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Declares what the plugin stores and honors export/erase requests.
 *
 * Financial rows are anonymized (affiliate link zeroed), not deleted,
 * so store accounting stays intact while personal data is removed.
 */
class Privacy {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Add the suggested policy content.
	 *
	 * @return void
	 */
	public function add_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		wp_add_privacy_policy_content(
			'CoSellHive',
			$this->policy_html()
		);
	}

	/**
	 * Policy HTML.
	 *
	 * @return string
	 */
	private function policy_html() {
		$content = '<p>' . __( 'CoSellHive stores affiliate marketing data so commissions can be calculated and paid:', 'cosell-hive' ) . '</p>';
		$content .= '<ul>';
		$content .= '<li>' . __( 'Clicks: a tracking token, the promoted product, the affiliate account, and HMAC-hashed IP and user-agent values (never raw values), with a timestamp.', 'cosell-hive' ) . '</li>';
		$content .= '<li>' . __( 'Commissions: order reference, amounts, currency, and payout status per attributed order.', 'cosell-hive' ) . '</li>';
		$content .= '<li>' . __( 'Payouts: requested amounts, the chosen method, and account details stored encrypted — only masked values are ever displayed.', 'cosell-hive' ) . '</li>';
		$content .= '</ul>';
		$content .= '<p>' . __( 'Order attribution tokens are stored in order metadata and in a cookie named cosell_hive_token (purpose: remember the last clicked affiliate link; lifetime: the attribution window, default 30 days). Tracking can be disabled by the site owner with the cosell_hive_tracking_enabled filter for consent tools. License status and the site role (store or affiliate) are stored in site options.', 'cosell-hive' ) . '</p>';
		$content .= '<p>' . sprintf(
			/* translators: %s: privacy policy URL */
			__( 'When marketplace features are used, limited data is sent to the CoSellHive Hub as described in our privacy policy: %s.', 'cosell-hive' ),
			'https://cosellhive.com/privacy-policy'
		) . '</p>';

		return $content;
	}

	/**
	 * Register the exporter.
	 *
	 * @param array $exporters Existing exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['cosell-hive'] = array(
			'exporter_friendly_name' => __( 'CoSellHive data', 'cosell-hive' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array $erasers Existing erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['cosell-hive'] = array(
			'eraser_friendly_name' => __( 'CoSellHive data', 'cosell-hive' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Export an affiliate's rows (paginated).
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array
	 */
	public function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		global $wpdb;

		$limit  = 100;
		$offset = ( max( 1, absint( $page ) ) - 1 ) * $limit;

		$commissions = new \CoSellHive\Repository\CommissionRepository();
		$payouts     = new \CoSellHive\Repository\PayoutRepository();
		$clicks      = new \CoSellHive\Repository\ClickRepository();

		$data = array();

		$crows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT order_id, amount_minor, currency, status, created_at FROM ' . $commissions->table() . ' WHERE affiliate_id = %d ORDER BY id ASC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$user->ID,
				$limit,
				$offset
			),
			ARRAY_A
		);

		foreach ( (array) $crows as $row ) {
			$data[] = array(
				'group_id'    => 'cosell-hive-commissions',
				'group_label' => __( 'Commissions', 'cosell-hive' ),
				'item_id'     => 'commission-' . $row['order_id'],
				'data'        => array(
					array(
						'name'  => __( 'Order ID', 'cosell-hive' ),
						'value' => $row['order_id'],
					),
					array(
						'name'  => __( 'Amount', 'cosell-hive' ),
						'value' => $row['amount_minor'] . ' ' . $row['currency'],
					),
					array(
						'name'  => __( 'Status', 'cosell-hive' ),
						'value' => $row['status'],
					),
				),
			);
		}

		$prows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT amount_minor, method, status, requested_at FROM ' . $payouts->table() . ' WHERE affiliate_id = %d ORDER BY id ASC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$user->ID,
				$limit,
				$offset
			),
			ARRAY_A
		);

		foreach ( (array) $prows as $row ) {
			$data[] = array(
				'group_id'    => 'cosell-hive-payouts',
				'group_label' => __( 'Payouts', 'cosell-hive' ),
				'item_id'     => 'payout-' . $row['requested_at'],
				'data'        => array(
					array(
						'name'  => __( 'Amount', 'cosell-hive' ),
						'value' => $row['amount_minor'],
					),
					array(
						'name'  => __( 'Method', 'cosell-hive' ),
						'value' => $row['method'],
					),
					array(
						'name'  => __( 'Status', 'cosell-hive' ),
						'value' => $row['status'],
					),
				),
			);
		}

		$krows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT token, product_id, created_at FROM ' . $clicks->table() . ' WHERE affiliate_id = %d ORDER BY id ASC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$user->ID,
				$limit,
				$offset
			),
			ARRAY_A
		);

		foreach ( (array) $krows as $row ) {
			$data[] = array(
				'group_id'    => 'cosell-hive-clicks',
				'group_label' => __( 'Clicks', 'cosell-hive' ),
				'item_id'     => 'click-' . $row['token'],
				'data'        => array(
					array(
						'name'  => __( 'Product ID', 'cosell-hive' ),
						'value' => $row['product_id'],
					),
					array(
						'name'  => __( 'Clicked at', 'cosell-hive' ),
						'value' => $row['created_at'],
					),
				),
			);
		}

		$done = count( (array) $crows ) < $limit && count( (array) $prows ) < $limit && count( (array) $krows ) < $limit;

		return array(
			'data' => $data,
			'done' => $done,
		);
	}

	/**
	 * Anonymize an affiliate's rows in batches (amounts kept for accounting).
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number (unused; batches handled per call).
	 * @return array
	 */
	public function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		global $wpdb;

		$limit = 100;

		$commissions = new \CoSellHive\Repository\CommissionRepository();
		$payouts     = new \CoSellHive\Repository\PayoutRepository();
		$clicks      = new \CoSellHive\Repository\ClickRepository();

		$crows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, order_id FROM ' . $commissions->table() . ' WHERE affiliate_id = %d ORDER BY id ASC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$user->ID,
				$limit
			),
			ARRAY_A
		);

		$order_ids = array();

		foreach ( (array) $crows as $row ) {
			$wpdb->update(
				$commissions->table(),
				array( 'affiliate_id' => 0 ),
				array( 'id' => absint( $row['id'] ) ),
				array( '%d' ),
				array( '%d' )
			);

			$order_ids[] = absint( $row['order_id'] );
		}

		foreach ( $order_ids as $order_id ) {
			if ( function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( $order_id );

				if ( $order && method_exists( $order, 'delete_meta_data' ) ) {
					$order->delete_meta_data( '_cosell_hive_token' );
					$order->save();
				}
			}
		}

		$prows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id FROM ' . $payouts->table() . ' WHERE affiliate_id = %d ORDER BY id ASC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$user->ID,
				$limit
			),
			ARRAY_A
		);

		foreach ( (array) $prows as $row ) {
			$wpdb->update(
				$payouts->table(),
				array(
					'affiliate_id' => 0,
					'details_enc'  => '',
				),
				array( 'id' => absint( $row['id'] ) ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}

		$krows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id FROM ' . $clicks->table() . ' WHERE affiliate_id = %d ORDER BY id ASC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$user->ID,
				$limit
			),
			ARRAY_A
		);

		foreach ( (array) $krows as $row ) {
			$wpdb->update(
				$clicks->table(),
				array( 'affiliate_id' => 0 ),
				array( 'id' => absint( $row['id'] ) ),
				array( '%d' ),
				array( '%d' )
			);
		}

		$done = count( (array) $crows ) < $limit && count( (array) $prows ) < $limit && count( (array) $krows ) < $limit;

		return array(
			'items_removed'  => true,
			'items_retained' => true,
			'messages'       => array( __( 'Affiliate links anonymized; commission amounts retained for accounting.', 'cosell-hive' ) ),
			'done'           => $done,
		);
	}
}
