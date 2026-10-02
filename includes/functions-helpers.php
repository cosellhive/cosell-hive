<?php
/**
 * Global helpers.
 *
 * @package CoSellHive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get a plugin setting with a filterable default.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function cosell_hive_get_setting( $key, $default = '' ) {
	$settings = get_option( 'cosell_hive_settings', array() );

	if ( is_array( $settings ) && array_key_exists( $key, $settings ) ) {
		$value = $settings[ $key ];
	} else {
		$value = $default;
	}

	/**
	 * Filter any CoSellHive setting.
	 *
	 * @param mixed  $value Setting value.
	 * @param string $key   Setting key.
	 */
	return apply_filters( 'cosell_hive_setting_' . $key, $value, $key );
}
