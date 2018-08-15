<?php
/**
 * Smush class for storing all Ajax related functionality: WP_Smush_Ajax class
 *
 * @package WP_Smush
 * @subpackage Admin
 * @since 2.9.0
 *
 * @copyright (c) 2018, Incsub (http://incsub.com)
 */

/**
 * Class WP_Smush_Ajax for storing all Ajax related functionality.
 *
 * @since 2.9.0
 */
class WP_Smush_Ajax {

	/**
	 * WP_Smush_Ajax constructor.
	 */
	public function __construct() {
		// Handle Ajax request for directory smush stats (stats meta box).
		add_action( 'wp_ajax_get_dir_smush_stats', array( $this, 'get_dir_smush_stats' ) );

		// Handle Skip Quick Setup action.
		add_action( 'wp_ajax_skip_smush_setup', array( $this, 'skip_smush_setup' ) );

		// Ajax request for quick Setup.
		add_action( 'wp_ajax_smush_setup', array( $this, 'smush_setup' ) );
	}

	/**
	 * Ajax action to save settings from quick setup.
	 */
	public function smush_setup() {
		check_ajax_referer( 'smush_quick_setup', '_wpnonce' );

		$quick_settings = array();
		// Get the settings from $_POST.
		if ( ! empty( $_POST['smush_settings'] ) && is_array( $_POST['smush_settings'] ) ) {
			$quick_settings = $_POST['smush_settings'];
		}

		// Check the last settings stored in db.
		$settings = WP_Smush_Settings::get_setting( WP_SMUSH_PREFIX . 'last_settings', array() );
		$settings = maybe_unserialize( $settings );

		// Available settings for free/pro version.
		$exclude = array(
			'networkwide',
			'backup',
			'png_to_jpg',
			'nextgen',
			's3',
		);

		$core = WP_Smush::get_instance()->core();

		foreach ( $core->settings as $name => $values ) {
			// Update only specified settings.
			if ( in_array( $name, $exclude, true ) ) {
				continue;
			}

			// Skip premium features if not a member.
			if ( ! in_array( $name, $core->basic_features, true ) && ! WP_Smush::is_pro() ) {
				continue;
			}

			// Update value in settings.
			if ( in_array( WP_SMUSH_PREFIX . $name, $quick_settings, true ) ) {
				$settings[ $name ] = 1;
			} else {
				$settings[ $name ] = 0;
			}
		}

		// Update resize width and height settings if set.
		$resize_sizes['width']  = isset( $_POST['wp-smush-resize_width'] ) ? intval( $_POST['wp-smush-resize_width'] ) : 0;
		$resize_sizes['height'] = isset( $_POST['wp-smush-resize_height'] ) ? intval( $_POST['wp-smush-resize_height'] ) : 0;

		// @todo: Improve the hardcoded 500 value
		$resize_sizes['width']  = $resize_sizes['width'] > 0 && $resize_sizes['width'] < 500 ? 500 : $resize_sizes['width'];
		$resize_sizes['height'] = $resize_sizes['height'] > 0 && $resize_sizes['height'] < 500 ? 500 : $resize_sizes['height'];

		// Update the resize sizes.
		WP_Smush_Settings::update_setting( WP_SMUSH_PREFIX . 'resize_sizes', $resize_sizes );
		WP_Smush_Settings::update_setting( WP_SMUSH_PREFIX . 'last_settings', $settings );

		update_site_option( 'skip-smush-setup', 1 );

		wp_send_json_success();
	}

	/**
	 * Process ajax action for skipping Smush setup.
	 */
	public function skip_smush_setup() {
		check_ajax_referer( 'smush_quick_setup', '_wpnonce' );
		update_site_option( 'skip-smush-setup', 1 );
		wp_send_json_success();
	}

	/**
	 * Returns Directory Smush stats and Cumulative stats
	 */
	public function get_dir_smush_stats() {
		$result = array();

		// Store the Total/Smushed count.
		$stats = WP_Smush::get_instance()->core()->dir->total_stats();

		$result['dir_smush'] = $stats;

		// Cumulative Stats.
		$result['combined_stats'] = WP_Smush::get_instance()->core()->dir->combine_stats( $stats );

		// Store the stats in options table.
		update_option( 'dir_smush_stats', $result, false );

		// Send ajax response.
		wp_send_json_success( $result );
	}

}
