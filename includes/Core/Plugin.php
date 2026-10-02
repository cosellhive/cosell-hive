<?php
/**
 * Main plugin class — singleton + service container (wp-erp pattern).
 *
 * @package CoSellHive
 */

namespace CoSellHive\Core;

use CoSellHive\Admin\Menu;
use CoSellHive\Hub\HubClientFactory;
use CoSellHive\License\StubLicenseClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Final singleton plugin bootstrap.
 */
final class Plugin {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version = COSELL_HIVE_VERSION;

	/**
	 * Minimum PHP version.
	 *
	 * @var string
	 */
	private $min_php = COSELL_HIVE_MIN_PHP;

	/**
	 * Service container.
	 *
	 * @var array
	 */
	private $container = array();

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function init() {
		if ( ! isset( self::$instance ) || ! ( self::$instance instanceof Plugin ) ) {
			self::$instance = new self();
			self::$instance->setup();
		}

		return self::$instance;
	}

	/**
	 * Wire up the plugin.
	 *
	 * @return void
	 */
	private function setup() {
		register_activation_hook( COSELL_HIVE_FILE, array( $this, 'auto_deactivate' ) );

		if ( ! $this->is_supported_php() ) {
			return;
		}

		$this->includes();
		$this->instantiate();
		$this->load_modules();
		$this->init_actions();

		/**
		 * Fires once CoSellHive is loaded.
		 */
		do_action( 'cosell_hive_loaded' );
	}

	/**
	 * Magic getter for container services.
	 *
	 * @param string $prop Property name.
	 * @return mixed
	 */
	public function __get( $prop ) {
		if ( array_key_exists( $prop, $this->container ) ) {
			return $this->container[ $prop ];
		}

		return null;
	}

	/**
	 * Magic isset for container services.
	 *
	 * @param string $prop Property name.
	 * @return bool
	 */
	public function __isset( $prop ) {
		return isset( $this->container[ $prop ] );
	}

	/**
	 * Check PHP version support.
	 *
	 * @return bool
	 */
	public function is_supported_php() {
		return version_compare( PHP_VERSION, $this->min_php, '>=' );
	}

	/**
	 * Deactivate on unsupported PHP.
	 *
	 * @return void
	 */
	public function auto_deactivate() {
		if ( $this->is_supported_php() ) {
			return;
		}

		deactivate_plugins( plugin_basename( COSELL_HIVE_FILE ) );

		wp_die(
			esc_html__( 'CoSellHive requires PHP 7.4 or greater.', 'cosell-hive' ),
			esc_html__( 'Plugin Activation Error', 'cosell-hive' ),
			array(
				'response'  => 200,
				'back_link' => true,
			)
		);
	}

	/**
	 * Include required files.
	 *
	 * @return void
	 */
	private function includes() {
		require_once COSELL_HIVE_INCLUDES . '/functions-helpers.php';
	}

	/**
	 * Instantiate services.
	 *
	 * @return void
	 */
	private function instantiate() {
		$this->container['hub']     = HubClientFactory::create();
		$this->container['license'] = new StubLicenseClient();

		$installer = new Installer();
		$installer->register();

		$approvals = new \CoSellHive\Admin\ApprovalQueueController( $this->container['hub'] );
		$approvals->register();

		$heartbeat = new \CoSellHive\License\Heartbeat( $this );
		$heartbeat->register();

		$privacy = new Privacy();
		$privacy->register();

		if ( is_admin() ) {
			new Menu();
			new Assets();
		}

		new I18n();
	}

	/**
	 * Load feature modules.
	 *
	 * Mirrors wp-erp's load_module(): each module self-registers on construct.
	 *
	 * @return void
	 */
	public function load_modules() {
		new \CoSellHive\Modules\Store\Module( $this );
		new \CoSellHive\Modules\Affiliate\Module( $this );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	private function init_actions() {
		add_action( 'init', array( $this, 'localization_setup' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( COSELL_HIVE_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Load textdomain.
	 *
	 * @return void
	 */
	public function localization_setup() {
		load_plugin_textdomain( 'cosell-hive', false, dirname( plugin_basename( COSELL_HIVE_FILE ) ) . '/languages/' );
	}

	/**
	 * Add settings link on plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=cosell-hive-onboarding' ) ) . '">' . esc_html__( 'Onboarding', 'cosell-hive' ) . '</a>';
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=cosell-hive-settings' ) ) . '">' . esc_html__( 'Settings', 'cosell-hive' ) . '</a>';

		return $links;
	}
}
