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
	const VERSION = '1.0.0';

	/**
	 * Minimum PHP version.
	 *
	 * @var string
	 */
	const MIN_PHP = '8.1';

	/**
	 * Minimum WordPress version.
	 *
	 * @var string
	 */
	const MIN_WP = '6.3';

	/**
	 * Database schema version.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Text domain.
	 *
	 * @var string
	 */
	const TEXT_DOMAIN = 'cosell-hive';

	/**
	 * Absolute path of the main plugin file.
	 *
	 * @var string
	 */
	private static $file = '';

	/**
	 * Plugin directory path, without trailing slash.
	 *
	 * @var string
	 */
	private static $path = '';

	/**
	 * Plugin directory URL, without trailing slash.
	 *
	 * @var string
	 */
	private static $url = '';

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version = self::VERSION;

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
	 * Register the plugin's lifecycle hooks.
	 *
	 * Called once from the bootstrap file. Stores the file, path and URL the
	 * whole plugin derives from, and wires booting plus the activation and
	 * deactivation callbacks. Everything the plugin hooks into WordPress is
	 * registered here, not in the bootstrap file.
	 *
	 * @param string $plugin_file Absolute path of the main plugin file.
	 * @return void
	 */
	public static function register( $plugin_file ) {
		self::$file = $plugin_file;
		self::$path = dirname( $plugin_file );
		self::$url  = plugins_url( '', $plugin_file );

		add_action( 'plugins_loaded', array( __CLASS__, 'boot' ), 1 );
		register_activation_hook( $plugin_file, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( $plugin_file, array( __CLASS__, 'deactivate' ) );
	}

	/**
	 * Boot on plugins_loaded: check the environment, then initialize.
	 *
	 * Failures render an admin notice and skip setup.
	 *
	 * @return Plugin|null Instance when supported, null otherwise.
	 */
	public static function boot() {
		if ( ! self::is_supported_environment() ) {
			add_action( 'admin_notices', array( \CoSellHive\Notice::class, 'files_missing' ) );
			return null;
		}

		return self::init();
	}

	/**
	 * Activation callback registered in register().
	 *
	 * @param bool $network_wide Whether the plugin is being network-activated.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( $network_wide ) {
			update_site_option( 'cosell_hive_network_activation_notice', 1 );
			return;
		}

		$installer = new Installer();
		$installer->activate();
	}

	/**
	 * Deactivation callback registered in register().
	 *
	 * @return void
	 */
	public static function deactivate() {
		$installer = new Installer();
		$installer->deactivate();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin|null Instance once booted, null otherwise.
	 */
	public static function init() {
		if ( ! isset( self::$instance ) || ! ( self::$instance instanceof Plugin ) ) {
			self::$instance = new self();
			self::$instance->setup();
		}

		return self::$instance;
	}

	/**
	 * Absolute path of the main plugin file.
	 *
	 * @return string
	 */
	public static function file() {
		return self::$file;
	}

	/**
	 * Plugin directory path, without trailing slash.
	 *
	 * @return string
	 */
	public static function path() {
		return self::$path;
	}

	/**
	 * Plugin directory URL, without trailing slash.
	 *
	 * @return string
	 */
	public static function url() {
		return self::$url;
	}

	/**
	 * includes/ directory path.
	 *
	 * @return string
	 */
	public static function includes_dir() {
		return self::$path . '/includes';
	}

	/**
	 * modules/ directory path.
	 *
	 * @return string
	 */
	public static function modules_dir() {
		return self::$path . '/modules';
	}

	/**
	 * assets/ directory URL.
	 *
	 * @return string
	 */
	public static function assets_url() {
		return self::$url . '/assets';
	}

	/**
	 * Wire up the plugin.
	 *
	 * @return void
	 */
	private function setup() {
		if ( function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( plugin_basename( self::$file ) ) ) {
			add_action( 'network_admin_notices', array( \CoSellHive\Notice::class, 'network_activation' ) );
			return;
		}

		if ( ! get_option( 'cosell_hive_db_version', false ) ) {
			if ( is_admin() ) {
				add_action( 'admin_notices', array( \CoSellHive\Notice::class, 'missing_install' ) );
			}

			return;
		}

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
		return version_compare( PHP_VERSION, self::MIN_PHP, '>=' );
	}

	/**
	 * Whether the running environment meets both requirements.
	 *
	 * @return bool
	 */
	public static function is_supported_environment() {
		global $wp_version;

		if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
			return false;
		}

		if ( isset( $wp_version ) && version_compare( $wp_version, self::MIN_WP, '<' ) ) {
			return false;
		}

		return true;
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
		add_filter( 'plugin_action_links_' . plugin_basename( self::$file ), array( $this, 'plugin_action_links' ) );
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
