<?php
/*
Plugin Name: WP Smush.it Pro
Plugin URI: http://premium.wpmudev.org/projects/wp-smushit-pro/
Description: Reduce image file sizes and improve performance using the <a href="http://smush.it/">Smush.it</a> API within WordPress.
Author: WPMU DEV
Version: 0.2
Author URI: http://premium.wpmudev.org/
Textdomain: wp-smushit-pro
WDP ID:
*/

/*
Copyright 2009-2014 Incsub (http://incsub.com)
Author - Saurabh Shukla & Umesh Kumar
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
                                                                      */

if ( ! function_exists( 'download_url' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/file.php' );
}
define( 'WP_SMPRO_VERSION', '0.2' );

// the plugin's path for easy access to files
define( 'WP_SMPRO_DIR', plugin_dir_path(__FILE__) );

// the plugin's url for easy access to files
define( 'WP_SMPRO_URL', plugin_dir_url(__FILE__) );

// the text domain for translation, use hyphen instead of underscores, since that's the way glotpress will create translations
define( 'WP_SMPRO_DOMAIN', 'wp-smushit-pro' );


require_once( WP_SMPRO_DIR . 'classes/class-wp-smpro-bulk.php' );
require_once( WP_SMPRO_DIR . 'classes/class-wp-smpro-receive.php' );
require_once( WP_SMPRO_DIR . 'classes/class-wp-smpro-request.php' );
require_once( WP_SMPRO_DIR . 'classes/class-wp-smpro-admin.php' );
require_once( WP_SMPRO_DIR . 'classes/class-wp-smpro-send.php' );
require_once( WP_SMPRO_DIR . 'classes/class-wp-smpro.php' );

if ( ! function_exists( 'wp_basename' ) ) {
	/**
	 * Introduced in WP 3.1... this is copied verbatim from wp-includes/formatting.php.
	 */
	function wp_basename( $path, $suffix = '' ) {
		return urldecode( basename( str_replace( '%2F', '/', urlencode( $path ) ), $suffix ) );
	}
}
// some constants


$wp_sm_pro = new WpSmPro();

global $wp_sm_pro;