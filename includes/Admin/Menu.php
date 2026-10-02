<?php
/**
 * Admin menu shell.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers placeholder menu pages for React roots.
 */
class Menu {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register' ) );
	}

	/**
	 * Register menus.
	 *
	 * @return void
	 */
	public function register() {
		add_menu_page(
			esc_html__( 'CoSellHive', 'cosell-hive' ),
			esc_html__( 'CoSellHive', 'cosell-hive' ),
			'read',
			'cosell-hive',
			array( $this, 'render' ),
			'dashicons-store',
			56
		);

		$subs = array(
			'cosell-hive-feed'   => __( 'Marketplace', 'cosell-hive' ),
			'cosell-hive-links'  => __( 'My Links', 'cosell-hive' ),
			'cosell-hive-wallet' => __( 'Wallet', 'cosell-hive' ),
			'cosell-hive-review' => __( 'Approvals', 'cosell-hive' ),
			'cosell-hive-setup'  => __( 'Onboarding', 'cosell-hive' ),
		);

		foreach ( $subs as $slug => $label ) {
			add_submenu_page(
				'cosell-hive',
				esc_html( $label ),
				esc_html( $label ),
				'read',
				$slug,
				array( $this, 'render' )
			);
		}
	}

	/**
	 * Render a React root container.
	 *
	 * @return void
	 */
	public function render() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'cosell-hive'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		printf(
			'<div id="cosell-hive-root" data-page="%s"><p>%s</p></div>',
			esc_attr( $page ),
			esc_html__( 'Loading CoSellHive…', 'cosell-hive' )
		);
	}
}
