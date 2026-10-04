<?php
/**
 * Main plugin class — singleton + service container (wp-erp pattern).
 *
 * @package CoSellHive
 */

namespace CoSellHive\Core;

use CoSellHive\Admin\Menu;
use CoSellHive\Admin\Settings;
use CoSellHive\Hub\HubClientFactory;
use CoSellHive\License\LicenseClientFactory;

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
		if ( function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( plugin_basename( COSELL_HIVE_FILE ) ) ) {
			add_action( 'network_admin_notices', 'cosell_hive_network_notice' );
			return;
		}

		if ( ! get_option( 'cosell_hive_db_version', false ) ) {
			if ( is_admin() ) {
				add_action( 'admin_notices', array( $this, 'missing_install_notice' ) );
			}

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
		$this->container['license'] = LicenseClientFactory::create();

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
			new Settings();
		}
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
		add_filter( 'plugin_action_links_' . plugin_basename( COSELL_HIVE_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Show a notice when the plugin is active but not installed per site.
	 *
	 * @return void
	 */
	public function missing_install_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'CoSellHive is active on this site but was not installed here. Please deactivate it and activate it individually on each site.', 'cosell-hive' )
		);
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
