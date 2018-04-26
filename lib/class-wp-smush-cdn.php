<?php
/**
 * @package WP Smush
 * @subpackage CDN
 * @version 2.7
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2018, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmushCDN' ) ) {

	class WpSmushCDN {

		/**
		 * WpSmushCDN constructor.
		 */
		public function __construct() {

			// Hook into CDN settings section.
			add_action( 'smush_cdn_settings_ui', array( $this, 'ui' ) );
		}

		public function ui() {

			global $wpsmush_bulkui;

			echo '<div class="sui-box" id="wp-smush-cdn-wrap-box">';

			// Container header.
			$wpsmush_bulkui->container_header( esc_html__( 'CDN', 'wp-smushit' ) );

			echo '<div class="sui-box-body"></div>';

			echo '</div>';
		}
	}

	global $wpsmush_cdn;
	$wpsmush_cdn = new WpSmushCDN();

}