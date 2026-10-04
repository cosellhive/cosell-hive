<?php
/**
 * Script / style wiring for React mount points.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues built assets per admin screen.
 */
class Assets {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue screen-specific bundles.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook_suffix ) {
		$map = array(
			'toplevel_page_cosell-hive'           => 'cosell-hive-dashboard',
			'cosell-hive_page_cosell-hive-feed'   => 'cosell-hive-feed',
			'cosell-hive_page_cosell-hive-links'  => 'cosell-hive-links',
			'cosell-hive_page_cosell-hive-wallet' => 'cosell-hive-wallet',
			'cosell-hive_page_cosell-hive-review' => 'cosell-hive-review',
			'cosell-hive_page_cosell-hive-setup'  => 'cosell-hive-setup',
		);

		if ( ! isset( $map[ $hook_suffix ] ) ) {
			return;
		}

		$handle    = $map[ $hook_suffix ];
		$asset_file = Plugin::path() . '/build/' . $handle . '.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array( 'wp-element', 'wp-api-fetch' ),
			'version'      => Plugin::VERSION,
		);

		wp_enqueue_script(
			$handle,
			Plugin::url() . '/build/' . $handle . '.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( $handle, 'cosell-hive' );

		wp_localize_script(
			$handle,
			'cosellHive',
			array(
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'restUrl' => esc_url_raw( rest_url( 'cosell-hive/v1' ) ),
			)
		);

		$css_file = Plugin::path() . '/assets/css/admin.css';

		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				$handle . '-styles',
				Plugin::url() . '/assets/css/admin.css',
				array(),
				filemtime( $css_file )
			);
		}
	}
}
