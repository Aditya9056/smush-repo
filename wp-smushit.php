<?php
/*
Plugin Name: WP Smush.it Pro
Plugin URI: http://premium.wpmudev.org/projects/wp-smushit-pro/
Description: Reduce image file sizes and improve performance using the <a href="http://smush.it/">Smush.it</a> API within WordPress.
Author: WPMU DEV
Version: 0.1
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

require_once( __DIR__ . '/wp-smushit-pro-admin.php' );
require_once( __DIR__ . '/wp-smushit-pro.php' );

$WpSmushitPro = new WpSmushitPro();
global $WpSmushitPro;

if ( ! function_exists( 'wp_basename' ) ) {
	/**
	 * Introduced in WP 3.1... this is copied verbatim from wp-includes/formatting.php.
	 */
	function wp_basename( $path, $suffix = '' ) {
		return urldecode( basename( str_replace( '%2F', '/', urlencode( $path ) ), $suffix ) );
	}
}
