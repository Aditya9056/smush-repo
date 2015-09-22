<?php
/*
Plugin Name: WP Smush Pro
Plugin URI: http://premium.wpmudev.org/projects/wp-smush-pro/
Description: Reduce image file sizes, improve performance and boost your SEO using the free <a href="https://premium.wpmudev.org/">WPMU DEV</a> WordPress Smush API.
Author: WPMU DEV
Version: 2.0.4
Author URI: http://premium.wpmudev.org/
Textdomain: wp_smush
WDP ID: 912164
*/

/*
  Copyright 2009-2015 Incsub (http://incsub.com)
  Author - Aaron Edwards, Sam Najian, Umesh Kumar
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
//Include main class
require_once( WP_SMUSH_DIR . 'lib/class-wp-smush.php' );

require_once( WP_SMUSH_DIR . 'wpmudev-dashboard-notification/wpmudev-dash-notification.php' );

//register items for the dashboard plugin
global $wpmudev_notices;
$wpmudev_notices[] = array(
	'id'      => 912164,
	'name'    => 'WP Smush Pro',
	'screens' => array(
		'media_page_wp-smush-bulk',
		'upload'
	)
);