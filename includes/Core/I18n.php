<?php
/**
 * I18n loader.
 *
 * @package CoSellHive
 */

namespace CoSellHive\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads translations.
 */
class I18n {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'load' ) );
	}

	/**
	 * Load textdomain.
	 *
	 * @return void
	 */
	public function load() {
		load_plugin_textdomain( 'cosell-hive', false, dirname( plugin_basename( COSELL_HIVE_FILE ) ) . '/languages/' );
	}
}
