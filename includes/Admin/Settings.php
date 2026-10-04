<?php
/**
 * Settings screen (uninstall data removal).
 *
 * @package CoSellHive
 */

namespace CoSellHive\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the settings page and setting.
 */
class Settings {

	const OPTION = 'cosell_hive_settings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * Add the settings submenu.
	 *
	 * @return void
	 */
	public function register_page() {
		add_submenu_page(
			'cosell-hive',
			esc_html__( 'CoSellHive Settings', 'cosell-hive' ),
			esc_html__( 'Settings', 'cosell-hive' ),
			'manage_options',
			'cosell-hive-settings',
			array( $this, 'render' )
		);
	}

	/**
	 * Register the setting, section, and field.
	 *
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			'cosell-hive-settings',
			self::OPTION,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);

		add_settings_section(
			'cosell_hive_data',
			esc_html__( 'Data', 'cosell-hive' ),
			'__return_false',
			'cosell-hive-settings'
		);

		add_settings_field(
			'delete_data_on_uninstall',
			esc_html__( 'Delete data on uninstall', 'cosell-hive' ),
			array( $this, 'render_field' ),
			'cosell-hive-settings',
			'cosell_hive_data'
		);
	}

	/**
	 * Sanitize settings (preserve other keys, cast flag to bool).
	 *
	 * @param mixed $value Submitted values.
	 * @return array
	 */
	public function sanitize( $value ) {
		$settings = get_option( self::OPTION, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings['delete_data_on_uninstall'] = is_array( $value ) && ! empty( $value['delete_data_on_uninstall'] );

		return $settings;
	}

	/**
	 * Render the checkbox field.
	 *
	 * @return void
	 */
	public function render_field() {
		$settings = get_option( self::OPTION, array() );
		$checked  = is_array( $settings ) && ! empty( $settings['delete_data_on_uninstall'] );

		printf(
			'<label><input type="checkbox" name="%s[delete_data_on_uninstall]" value="1"%s /> %s</label><p class="description">%s</p>',
			esc_attr( self::OPTION ),
			checked( $checked, true, false ),
			esc_html__( 'Delete all CoSellHive data when the plugin is deleted.', 'cosell-hive' ),
			esc_html__( 'Warning: commission records, wallet data, and affiliate data will be permanently removed.', 'cosell-hive' )
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'CoSellHive Settings', 'cosell-hive' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'cosell-hive-settings' );
		do_settings_sections( 'cosell-hive-settings' );
		submit_button();
		echo '</form></div>';
	}
}
