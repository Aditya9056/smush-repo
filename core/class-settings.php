<?php
/**
 * Settings class.
 *
 * @since 3.2.2  Refactored to support custom sub site configurations.
 * @package WP_Smush\Core
 */

namespace WP_Smush\Core;

/**
 * Class Settings
 *
 * @package WP_Smush\Core
 */
class Settings {

	/**
	 * Settings array.
	 *
	 * @var array $settings
	 */
	private $settings = array();

	/**
	 * Available modules.
	 *
	 * @var array $modules
	 */
	private $modules = array( 'bulk', 'integrations', 'lazy_load', 'cdn', 'tools', 'settings' );

	/**
	 * Bulk Smush options.
	 *
	 * @var array $bulk_fields
	 */
	private $bulk_fields = array(
		'auto'       => true,  // works with CDN.
		'lossy'      => false, // works with CDN.
		'strip_exif' => true,  // works with CDN.
		'resize'     => false,
		'original'   => false,
		'backup'     => false,
		'png_to_jpg' => false, // works with CDN.
	);

	/**
	 * Integration options.
	 *
	 * @var array $integrations_fields
	 */
	private $integrations_fields = array(
		'nextgen'    => false,
		's3'         => false,
		'gutenberg'  => false,
		'js_builder' => false,
	);

	/**
	 * Lazy load options.
	 *
	 * @var array $lazy_load_fields
	 */
	private $lazy_load_fields = array(
		'enabled'         => false,
		'format'          => array(
			'jpeg' => true,
			'png'  => true,
			'gif'  => true,
			'svg'  => true,
		),
		'output'          => array(
			'content'    => true,
			'widgets'    => true,
			'thumbnails' => true,
			'gravatars'  => true,
		),
		'animation'       => array(
			'selected'    => 'fadein', // Accepts: fadein, spinner, placeholder, false.
			'fadein'      => array(
				'duration' => 400,
				'delay'    => 0,
			),
			'spinner'     => array(
				'selected' => 1,
				'custom'   => array(),
			),
			'placeholder' => array(
				'selected' => 1,
				'custom'   => array(),
				'color'    => '#F3F3F3',
			),
		),
		'include'         => array(
			'frontpage' => true,
			'home'      => true,
			'page'      => true,
			'single'    => true,
			'archive'   => true,
			'category'  => true,
			'tag'       => true,
		),
		'exclude-pages'   => array(),
		'exclude-classes' => array(),
		'footer'          => true,
	);

	/**
	 * CDN options.
	 *
	 * @var array $cdn_fields
	 */
	private $cdn_fields = array(
		'enabled'           => false,
		'auto_resize'       => false,
		'webp'              => true,
		'background_images' => true,
	);

	/**
	 * Tools options.
	 *
	 * @var array $tools_fields
	 */
	private $tools_fields = array(
		'detection' => false,
	);

	/**
	 * Settings module options.
	 *
	 * @var array $settings_fields
	 */
	private $settings_fields = array(
		'usage'             => true,
		'accessible_colors' => false,
		'keep_data'         => true,
	);

	/**
	 * Global options.
	 *
	 * These options are stored/fetched via get_site_option().
	 *
	 * @var array $global_fields
	 */
	private $global_fields = array(
		'networkwide'  => false,
		'install-type' => 'new', // Accepts: new, existing.
	);

	/**
	 * Settings constructor.
	 */
	public function __construct() {
		// See if we've got serialised settings stored already.
		//$settings = $this->get_settings();


		$access = $this->is_global();

		if ( true === $access ) {
			return get_site_option( WP_SMUSH_PREFIX . 'settings', $this->get_defaults() );
		}

		if ( false === $access ) {
			//array_intersect_key()
			// TODO: make sure the missing stuff is taken from site_option setting.
			return get_option( WP_SMUSH_PREFIX . 'settings', array() );
		}

		if ( is_array( $access ) ) {

		}

		/*
		// In network admin it's possible to see only UNSELECTED modules.
		if ( is_network_admin() ) {
			return ! in_array( $module, $access, true );
		}

		// Vice versa for sub sites.
		return in_array( $module, $access, true );
		*/







		/*
		if ( empty( $settings ) ) {
			$this->set_setting( WP_SMUSH_PREFIX . 'settings', $this->settings );
		}

		// Store it in class variable.
		if ( ! empty( $settings ) && is_array( $settings ) ) {
			// Merge with the existing settings.
			$this->settings = array_merge( $this->settings, $settings );
		}
		*/
	}

	/**
	 * Getter method for bulk settings option keys.
	 *
	 * @since 3.2.2
	 * @return array
	 */
	public function get_bulk_fields() {
		return array_keys( $this->bulk_fields );
	}

	/**
	 * Getter method for integration option keys.
	 *
	 * @since 3.2.2
	 * @return array
	 */
	public function get_integrations_fields() {
		return array_keys( $this->integrations_fields );
	}

	/**
	 * Getter method for lazy load option keys.
	 *
	 * @since 3.2.2
	 * @return array
	 */
	public function get_lazy_load_fields() {
		return array_keys( $this->lazy_load_fields );
	}

	/**
	 * Getter method for CDN option keys.
	 *
	 * @since 3.2.2
	 * @return array
	 */
	public function get_cdn_fields() {
		return array_keys( $this->cdn_fields );
	}

	/**
	 * Getter method for tools option keys.
	 *
	 * @since 3.2.2
	 * @return array
	 */
	public function get_tools_fields() {
		return array_keys( $this->tools_fields );
	}

	/**
	 * Getter method for settings option keys.
	 *
	 * @since 3.2.2
	 * @return array
	 */
	public function get_settings_fields() {
		return array_keys( $this->settings_fields );
	}

	/**
	 * Getter method for global option keys.
	 *
	 * @since 3.2.2
	 * @return array
	 */
	public function get_global_fields() {
		return array_keys( $this->global_fields );
	}

	/**
	 * Generate an array with default settings.
	 *
	 * @return array
	 */
	private function get_defaults() {
		$settings = array();

		foreach ( $this->modules as $module ) {
			$settings[ $module ] = $this->{$module . '_fields'};
		}

		return $settings;
	}


	private function is_available() {

	}













	/**
	 * Fetch the settings, based on WordPress install type (single/multisite) or access control settings.
	 *
	 * @since 3.2.2  Added $module parameter.
	 *
	 * @param string $name     Setting name to fetch.
	 * @param string $module   Setting is part of this module.
	 * @param bool   $default  Default setting vale.
	 *
	 * @return bool|mixed
	 */
	public function get_setting( $name = '', $module = 'bulk', $default = false ) {
		if ( empty( $name ) ) {
			return false;
		}

		$access = $this->is_network_wide();

		// Subsite control is disabled.
		if ( false === $access ) {
			return get_option( $name, $default );
		}

		// Network admins can modify all the modules.
		if ( true === $access ) {
			return get_site_option( $name, $default );
		}

		// Just in case there's some weird error, and it's not an array.
		if ( ! is_array( $access ) ) {
			return false;
		}

		// In network admin it's possible to see only UNSELECTED modules.
		if ( is_network_admin() ) {
			return ! in_array( $module, $access, true );
		}

		// Vice versa for sub sites.
		return in_array( $module, $access, true );
	}


	/**
	 * $network_enabled = FALSE
	 * All access is global.
	 *
	 * TRUE
	 * Each subsite can override settings.
	 *
	 * ARRAY
	 */
	private function is_global() {
		if ( ! is_multisite() ) {
			return true;
		}

		// Get directly from db.
		$access = get_site_option( WP_SMUSH_PREFIX . 'networkwide' );
		if ( isset( $access ) && false === (bool) $access ) {
			return true;
		}

		if ( true === $access ) {
			return false;
		}

		// Partial enabled.
		return $access;
	}

}
