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
		$content .= '<li>' . __( 'Clicks: a tracking token, the promoted product, the affiliate account, and one-way hashes of IP and user agent (never raw values), with a timestamp.', 'cosell-hive' ) . '</li>';
		$content .= '<li>' . __( 'Commissions: order reference, amounts, currency, and payout status per attributed order.', 'cosell-hive' ) . '</li>';
		$content .= '<li>' . __( 'Payouts: requested amounts, the chosen method, and account details stored encrypted — only masked values are ever displayed.', 'cosell-hive' ) . '</li>';
		$content .= '</ul>';
		$content .= '<p>' . __( 'Order attribution tokens are also stored in order metadata and a short-lived cookie (default 30 days). License status and the site role (store or affiliate) are stored in site options.', 'cosell-hive' ) . '</p>';

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
	 * Export an affiliate's rows.
	 *
	 * @param string $email Email address.
	 * @return array
	 */
	public function export( $email ) {
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		global $wpdb;

		$commissions = new \CoSellHive\Repository\CommissionRepository();
		$payouts     = new \CoSellHive\Repository\PayoutRepository();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT order_id, amount_minor, currency, status, created_at FROM ' . $commissions->table() . ' WHERE affiliate_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$user->ID
			),
			ARRAY_A
		);

		$data = array();

		foreach ( (array) $rows as $row ) {
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
				'SELECT amount_minor, method, status, requested_at FROM ' . $payouts->table() . ' WHERE affiliate_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$user->ID
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

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Anonymize an affiliate's rows (amounts kept for accounting).
	 *
	 * @param string $email Email address.
	 * @return array
	 */
	public function erase( $email ) {
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

		$commissions = new \CoSellHive\Repository\CommissionRepository();
		$payouts     = new \CoSellHive\Repository\PayoutRepository();
		$clicks      = new \CoSellHive\Repository\ClickRepository();

		$payout_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM ' . $payouts->table() . ' WHERE affiliate_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$user->ID
			)
		);

		foreach ( array( $commissions->table(), $payouts->table(), $clicks->table() ) as $table ) {
			$wpdb->update(
				$table,
				array( 'affiliate_id' => 0 ),
				array( 'affiliate_id' => $user->ID ),
				array( '%d' ),
				array( '%d' )
			);
		}

		foreach ( (array) $payout_ids as $payout_id ) {
			$wpdb->update(
				$payouts->table(),
				array( 'details_enc' => '' ),
				array( 'id' => absint( $payout_id ) ),
				array( '%s' ),
				array( '%d' )
			);
		}

		return array(
			'items_removed'  => true,
			'items_retained' => true,
			'messages'       => array( __( 'Affiliate links anonymized; commission amounts retained for accounting.', 'cosell-hive' ) ),
			'done'           => true,
		);
	}
}
