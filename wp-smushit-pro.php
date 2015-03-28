<?php
/*
Plugin Name: WP Smush Pro
Plugin URI: http://premium.wpmudev.org/projects/wp-smush-pro/
Description: Reduce image file sizes and improve performance using the premium WPMU DEV smushing API within WordPress.
Author: WPMU DEV
Version: 1.1
Author URI: http://premium.wpmudev.org/
Textdomain: wp-smushit-pro
WDP ID: 912164
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
 * @version 1.0.2
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2014, Incsub (http://incsub.com)
 */
// include the files.php from core, needed to work with uploads
if ( ! function_exists( 'download_url' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/file.php' );
}

/**
 * The version for enqueueing , etc.
 */
define( 'WP_SMPRO_VERSION', '1.1' );

/**
 * The plugin's path for easy access to files.
 */
define( 'WP_SMPRO_DIR', plugin_dir_path( __FILE__ ) );

/**
 * The plugin's url for easy access to files.
 */
define( 'WP_SMPRO_URL', plugin_dir_url( __FILE__ ) );

/**
 * The text domain for translation.
 */
define( 'WP_SMPRO_DOMAIN', 'wp-smush-pro' );

/**
 * Prefix to use for the meta keys and options
 */
define( 'WP_SMPRO_PREFIX', 'wp-smpro-' );

/**
 * Plugin base name
 */
define( 'WP_SMPRO_BASENAME', plugin_basename( __FILE__ ) );
//use hyphens instead of underscores for glotpress compatibility

// include the classes
require_once( WP_SMPRO_DIR . 'classes/admin/class-wp-smpro-media-library.php' );
require_once( WP_SMPRO_DIR . 'classes/smush/class-wp-smpro-receive.php' );
require_once( WP_SMPRO_DIR . 'classes/admin/class-wp-smpro-count.php' );
require_once( WP_SMPRO_DIR . 'classes/admin/class-wp-smpro-admin.php' );
require_once( WP_SMPRO_DIR . 'classes/smush/class-wp-smpro-fetch.php' );
require_once( WP_SMPRO_DIR . 'classes/smush/class-wp-smpro-send.php' );
/**
 * Error Log
 */
require_once( WP_SMPRO_DIR . 'classes/error/class-wp-smpro-errorlog.php' );
require_once( WP_SMPRO_DIR . 'classes/error/class-wp-smpro-errorregistry.php' );

require_once( WP_SMPRO_DIR . 'classes/class-wp-smpro.php' );

//load dashboard notice
if ( is_admin() && file_exists( WP_SMPRO_DIR . 'wpmudev-dashboard-notification/wpmudev-dash-notification.php' ) ) {
	// Dashboard notification
	global $wpmudev_notices, $current_screen;
	if ( ! is_array( $wpmudev_notices ) ) {
		$wpmudev_notices = array();
	}
	//@todo: Update plugin id
	$wpmudev_notices[] = array(
		'id'      => 9999,
		'name'    => 'WP Smush Pro',
		'screens' => array(
			'media_page_wp-smpro-admin',
			'upload'
		)
	);
	require_once( WP_SMPRO_DIR . 'wpmudev-dashboard-notification/wpmudev-dash-notification.php' );
}

// do we need this? It is too support versions earlier than 3.1
if ( ! function_exists( 'wp_basename' ) ) {

	/**
	 * Introduced in WP 3.1... this is copied verbatim from wp-includes/formatting.php.
	 */
	function wp_basename( $path, $suffix = '' ) {
		return urldecode( basename( str_replace( '%2F', '/', urlencode( $path ) ), $suffix ) );
	}

}
//WPMU Dev Dashboard installation notice
add_action( 'network_admin_notices', 'wp_smpro_notice' );
add_action( 'admin_notices', 'wp_smpro_notice' );

function wp_smpro_notice() {
	global $WPMUDEV_Dashboard_Notice3, $admin_page_suffix;
	//WPMU API Key
	if ( defined( 'WPMUDEV_APIKEY' ) ) {
		$wpmudev_apikey = WPMUDEV_APIKEY;
	} else {
		$wpmudev_apikey = get_site_option( 'wpmudev_apikey' );
	}

	$plugin_path = WP_PLUGIN_DIR . '/wpmudev-updates/update-notifications.php';

	if ( method_exists( $WPMUDEV_Dashboard_Notice3, 'auto_install_url' ) ) {
		$url = $WPMUDEV_Dashboard_Notice3->auto_install_url();
	} else {
		$url = "http://premium.wpmudev.org/project/wpmu-dev-dashboard/";
	}

	$current_screen = get_current_screen();
	$current_screen = ! empty( $current_screen ) ? $current_screen : '';
	//If there is no WPMU API Key and Dashboard plugin is deactivated, ask for Dashboard plugin
	if ( empty( $wpmudev_apikey ) && ! is_plugin_active( 'wpmudev-updates/update-notifications.php' ) ) {
		//Don't show the notice on smush pro page, as we have dash notification to take care of it
		if ( ( $current_screen->id !== $admin_page_suffix ) && $current_screen->id != 'upload' ) {
			?>
			<div class="error smushit-pro-status">
				<?php
				if ( file_exists( $plugin_path ) ) {
					wp_smpro_script();
					$nonce = wp_create_nonce( 'activate_wpmudev-updates' );
					?>
					<p>
						<strong><?php _e( 'WP Smush PRO:', WP_SMPRO_DOMAIN ) ?></strong> <?php printf(
							__( '<a href="#" onclick="%s">Click here</a> to activate WPMU DEV Dashboard.', WP_SMPRO_DOMAIN ),
							"wp_smpro_activate_plugin('smushit_pro_activate_plugin','$nonce'" ); ?>
					</p>
				<?php
				} else {
					?>
					<p>
						<strong><?php _e( 'WP Smush Pro requires the WPMU DEV Dashboard plugin.', WP_SMPRO_DOMAIN ) ?></strong> <?php _e( 'Please install the <a href="' . $url . '" >WPMU DEV Dashboard plugin</a> to use WP Smush PRO.', WP_SMPRO_DOMAIN ); ?>
					</p>
				<?php
				}
				?>
			</div>
		<?php
		}
	} elseif ( empty( $wpmudev_apikey ) ) {
		//User haven't logged in to Dashboard plugin
		$dashboard_url = is_multisite() ? network_admin_url( 'admin.php?page=wpmudev' ) : admin_url( 'admin.php?page=wpmudev' );
		?>
		<div class="error smushit-pro-status">
		<p>
			<strong><?php _e( 'WP Smush PRO:', WP_SMPRO_DOMAIN ) ?></strong> <?php printf( __( '<a href="%s">Login to WPMU DEV Dashboard</a> to start using WP Smush PRO.', WP_SMPRO_DOMAIN ), $dashboard_url ); ?>
		</p>
		</div><?php
	}
}

//Handle plugin activation request
add_action( 'wp_ajax_smushit_pro_activate_plugin', 'smushit_pro_activate_plugin' );

function smushit_pro_activate_plugin() {

	//Validate nonce
	$nonce = ! empty( $_REQUEST['_ajax_nonce'] ) ? $_REQUEST['_ajax_nonce'] : '';
	if ( ! wp_verify_nonce( $nonce, 'activate_wpmudev-updates' ) ) {
		wp_send_json_error( __( 'ERROR: nonce verification failed', WP_SMPRO_DOMAIN ) );
	}

	//Activate plugin
	$path            = WP_PLUGIN_DIR . '/wpmudev-updates/update-notifications.php';
	$activate_result = activate_plugin( $path );

	if ( is_wp_error( $activate_result ) ) {
		wp_send_json_error( sprintf( __( 'ERROR: Failed to activate plugin: %s', WP_SMPRO_DOMAIN ), $activate_result->get_error_message() ) );
	}
	wp_send_json_success( __( 'SUCCESS: plugin activated', WP_SMPRO_DOMAIN ) );
}

/**
 * Output the required javascript for Plugin activation
 */
function wp_smpro_script() {
	?>
	<script type="text/javascript">
		function wp_smpro_activate_plugin(action, smpro_nonce) {
			jQuery('.smushit-pro-status').removeClass('error');
			jQuery('.smushit-pro-status').addClass('updated');
			jQuery('.smushit-pro-status p').html('<strong>Smushit Pro:</strong> Activating WPMU DEV Dashboard...');
			var param = {
				action: action,
				_ajax_nonce: smpro_nonce
			};
			jQuery.post(ajaxurl, param, function (data) {
//				data = jQuery.parseJSON( data );
				if (data.success == true) {
					jQuery('.smushit-pro-status p').html('<strong>Smushit Pro:</strong> WPMU DEV Dashbaord activated.');
					location.reload();
				} else {
					jQuery('.smushit-pro-status p').html('<strong>Smushit Pro:</strong> There is some problem. Please try again.');
				}

			});
		}
	</script><?php
}

/**
 * The list of current api servers
 */
function wp_smpro_servers() {
	$server_list  = array(
		'https://smush1.wpmudev.com/',
		'https://smush2.wpmudev.com/',
		'https://smush3.wpmudev.com/',
		'https://smush4.wpmudev.com/',
		'https://smush5.wpmudev.com/',
		'https://smush6.wpmudev.com/',
		'https://smush7.wpmudev.com/',
		'https://smush8.wpmudev.com/',
		'https://smush9.wpmudev.com/',
		'https://smush10.wpmudev.com/'
	);

	return $server_list;
}

if ( ! function_exists( 'boolval' ) ) {
	/**
	 * Returns the bool value of a variable <PHP5.5
	 *
	 * @param $val
	 *
	 * @return bool
	 */
	function boolval( $val ) {
		return (bool) $val;
	}
}
// instantiate our main class
$wp_smpro = new WpSmPro();

global $wp_smpro;