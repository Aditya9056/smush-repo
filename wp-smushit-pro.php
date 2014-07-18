<?php
/*
  Plugin Name: WP Smush.it Pro
  Plugin URI: http://premium.wpmudev.org/projects/wp-smushit-pro/
  Description: Reduce image file sizes and improve performance using the <a href="http://smush.it/">Smush.it</a> API within WordPress.
  Author: WPMU DEV
  Version: 1.0
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
/**
 * Main file.
 * 
 * With plugin meta data. Loads all the classes and functionality
 * 
 * @package SmushItPro
 * 
 * @version 1.0
 * 
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 * 
 * @copyright (c) 2014, Incsub (http://incsub.com)
 */
// include the files.php from core, needed to work with uploads
if (!function_exists('download_url')) {
	require_once( ABSPATH . 'wp-admin/includes/file.php' );
}

/**
 * The version for enqueueing , etc.
 */
define('WP_SMPRO_VERSION', '1.0');

/**
 * The plugin's path for easy access to files.
 */
define('WP_SMPRO_DIR', plugin_dir_path(__FILE__));

/**
 * The plugin's url for easy access to files.
 */
define('WP_SMPRO_URL', plugin_dir_url(__FILE__));

/**
 * The text domain for translation. 
 */
define('WP_SMPRO_DOMAIN', 'wp-smushit-pro');
//use hyphens instead of underscores for glotpress compatibility

// include the classes
require_once( WP_SMPRO_DIR . 'classes/class-wp-smpro-bulk.php' );
require_once( WP_SMPRO_DIR . 'classes/class-wp-smpro-receive.php' );
require_once( WP_SMPRO_DIR . 'classes/class-wp-smpro-request.php' );
require_once( WP_SMPRO_DIR . 'classes/class-wp-smpro-admin.php' );
require_once( WP_SMPRO_DIR . 'classes/class-wp-smpro-send.php' );
require_once( WP_SMPRO_DIR . 'classes/class-wp-smpro.php' );

// do we need this? It is too support versions earlier than 3.1
if (!function_exists('wp_basename')) {

	/**
	 * Introduced in WP 3.1... this is copied verbatim from wp-includes/formatting.php.
	 */
	function wp_basename($path, $suffix = '') {
		return urldecode(basename(str_replace('%2F', '/', urlencode($path)), $suffix));
	}

}

// instantiate our main class
$wp_sm_pro = new WpSmPro();

global $wp_sm_pro;

/*
 * Where's the haiku?
 */