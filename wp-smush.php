<?php
/*
Plugin Name: WP Smush
Plugin URI: http://wordpress.org/extend/plugins/wp-smushit/
Description: Reduce image file sizes and improve performance using the <a href="http://smush.it/">Smush.it</a> API within WordPress.
Author: WPMU DEV
Version: 1.7.1.1
Author URI: http://premium.wpmudev.org/
Textdomain: wp_smushit
*/

/*
This plugin was originally developed by Alex Dunae.
http://dialect.ca/
*/

/*
Copyright 2007-2013 Incsub (http://incsub.com)

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

if ( ! class_exists( 'WpSmush' ) ) {

	class WpSmush {

		var $version = "1.7.1";

		/**
		 * Constructor
		 */
		function __construct() {

			/**
			 * Constants
			 */
			define( 'SMUSHIT_REQ_URL', 'https://smushpro.wpmudev.org/' );
			define( 'SMUSHIT_BASE_URL', 'https://smushpro.wpmudev.org/' );

			define( 'WP_SMUSHIT_DOMAIN', 'wp_smushit' );
			define( 'WP_SMUSHIT_UA', "WP Smush/{$this->version} (+http://wordpress.org/extend/plugins/wp-smushit/)" );
			define( 'WP_SMUSHIT_DIR', plugin_dir_path( __FILE__ ) );
			define( 'WP_SMUSHIT_URL', plugin_dir_url( __FILE__ ) );
			define( 'WP_SMUSHIT_MAX_BYTES', 1048576 );
			define( 'WP_SMUSH_PREMIUM_MAX_BYTES', 630000 );

			// The number of images (including generated sizes) that can return errors before abandoning all hope.
			// N.B. this doesn't work with the bulk uploader, since it creates a new HTTP request
			// for each image.  It does work with the bulk smusher, though.
			define( 'WP_SMUSHIT_ERRORS_BEFORE_QUITTING', 3 * count( get_intermediate_image_sizes() ) );

			define( 'WP_SMUSHIT_AUTO', intval( get_option( 'wp_smushit_smushit_auto', 0 ) ) );
			define( 'WP_SMUSHIT_TIMEOUT', intval( get_option( 'wp_smushit_smushit_timeout', 60 ) ) );
			define( 'WP_SMUSHIT_ENFORCE_SAME_URL', get_option( 'wp_smushit_smushit_enforce_same_url', 'on' ) );

			define( 'WP_SMUSHIT_DEBUG', get_option( 'wp_smushit_smushit_debug', '' ) );

			/*
			Each service has a setting specifying whether it should be used automatically on upload.
			Values are:
				-1  Don't use (until manually enabled via Media > Settings)
				0   Use automatically
				n   Any other number is a Unix timestamp indicating when the service can be used again
			*/

			define( 'WP_SMUSHIT_AUTO_OK', 0 );
			define( 'WP_SMUSHIT_AUTO_NEVER', - 1 );

			/**
			 * Hooks
			 */
			if ( WP_SMUSHIT_AUTO == WP_SMUSHIT_AUTO_OK ) {
				add_filter( 'wp_generate_attachment_metadata', array( &$this, 'resize_from_meta_data' ), 10, 2 );
			}
			add_filter( 'manage_media_columns', array( &$this, 'columns' ) );
			add_action( 'manage_media_custom_column', array( &$this, 'custom_column' ), 10, 2 );
			add_action( 'admin_init', array( &$this, 'admin_init' ) );
			add_action( 'admin_head-upload.php', array( &$this, 'add_bulk_actions_via_javascript' ) );
			add_action( 'admin_action_bulk_smushit', array( &$this, 'bulk_action_handler' ) );
		}

		function WpSmush() {
			$this->__construct();
		}

		function settings_cb() {
		}

		function admin_init() {
			load_plugin_textdomain( WP_SMUSHIT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
			wp_enqueue_script( 'common' );
		}

		/**
		 * Process an image with Smush.it.
		 *
		 * Returns an array of the $file $results.
		 *
		 * @param   string $file Full absolute path to the image file
		 * @param   string $file_url Optional full URL to the image file
		 *
		 * @returns array
		 */
		function do_smushit( $attachment_id, $file_path = '', $file_url = '' ) {
			global $log;

			if ( empty( $file_path ) ) {
				return __( "File path is empty", WP_SMUSHIT_DOMAIN );
			}

			if ( empty( $file_url ) ) {
				return __( "File URL is empty", WP_SMUSHIT_DOMAIN );
			}

			static $error_count = 0;

			if ( $error_count >= WP_SMUSHIT_ERRORS_BEFORE_QUITTING ) {
				return __( "Did not Smush.it due to previous errors", WP_SMUSHIT_DOMAIN );
			}

			// check that the file exists
			if ( ! file_exists( $file_path ) || ! is_file( $file_path ) ) {
				return sprintf( __( "ERROR: Could not find <span class='code'>%s</span>", WP_SMUSHIT_DOMAIN ), $file_path );
			}

			// check that the file is writable
			if ( ! is_writable( dirname( $file_path ) ) ) {
				return sprintf( __( "ERROR: <span class='code'>%s</span> is not writable", WP_SMUSHIT_DOMAIN ), dirname( $file_path ) );
			}

			$file_size = filesize( $file_path );

			//Check if premium user
			$api_key = $this->is_premium();

			$max_size = ! empty( $api_key ) ? WP_SMUSH_PREMIUM_MAX_BYTES : WP_SMUSHIT_MAX_BYTES;

			//Check if file exists
			if ( $file_size == 0 ) {
				return sprintf( __( 'ERROR: <span style="color:#FF0000;">Skipped (%s), image not found.</span>', WP_SMUSHIT_DOMAIN ), $this->format_bytes( $file_size ) );
			}
			//Check size limit
			if ( $file_size > $max_size ) {
				return sprintf( __( 'ERROR: <span style="color:#FF0000;">Skipped (%s), size limit exceeded.</span>', WP_SMUSHIT_DOMAIN ), $this->format_bytes( $file_size ) );
			}

			// File URL check disabled 2013-10-11 - The assumption here is the URL may not be the local site URL. The image may be served via a sub-domain pointed
			// to this same host or may be an external CDN. In either case the image would be the same. So we let the Yahoo! Smush.it service use the image URL with
			// the assumption the remote image and the local file are the same. Also with the assumption that the CDN service will somehow be updated when the image
			// is changed.
			if ( ( defined( 'WP_SMUSHIT_ENFORCE_SAME_URL' ) ) && ( WP_SMUSHIT_ENFORCE_SAME_URL == 'on' ) ) {
				$home_url      = str_replace( 'https://', 'http://', get_option( 'home' ) );
				$error_message = "<b>Home URL: </b>" . $home_url . " <br />";

				if ( stripos( $file_url, $home_url ) !== 0 ) {
					return sprintf( __( "ERROR: <span class='code'>%s</span> must be within the website home URL (<span class='code'>%s</span>)", WP_SMUSHIT_DOMAIN ),
						htmlentities( $file_url ), $home_url );
				}
			}

			/** Send image for smushing, and fetch the response */
			$response = $this->_post( $file_path, $file_size, $api_key );

			if ( false === $response ) {
				$error_count ++;

				return __( 'ERROR: posting to Smush.it', WP_SMUSHIT_DOMAIN );
			}
			//If there is no data
			if ( empty( $response['data'] ) ) {
				return __( 'Bad response from server', WP_SMUSHIT_DOMAIN );
			}

			//If there are no savings, or image returned is bigger in size
			if ( ( ! empty( $response['data']->bytes_saved ) && intval( $response['data']->bytes_saved ) <= 0 )
			     || empty( $response['data']->image )
			) {
				return __( 'No savings', WP_SMUSHIT_DOMAIN );
			}

			$tempfile = $file_path . ".tmp";

			//Add the file as tmp
			file_put_contents( $tempfile, $response['data']->image );

			//replace the file
			$success = @rename( $tempfile, $file_path );

			//if tempfile still exists, unlink it
			if ( file_exists( $tempfile ) ) {
				unlink( $tempfile );
			}

			//If file renaming was successfull
			if ( ! $success ) {
				copy( $tempfile, $file_path );
				unlink( $tempfile );
			}

			$savings_str = $this->format_bytes( $response['data']->bytes_saved, 1 );
			$savings_str = str_replace( ' ', '&nbsp;', $savings_str );

			$results_msg = sprintf( __( "Reduced by %01.1f%% (%s)", WP_SMUSHIT_DOMAIN ), $response['data']->compression, $savings_str );

			return $results_msg;
		}

		function should_resmush( $previous_status ) {
			if ( ! $previous_status || empty( $previous_status ) ) {
				return true;
			}

			if ( stripos( $previous_status, 'no savings' ) !== false || stripos( $previous_status, 'reduced' ) !== false ) {
				return false;
			}

			// otherwise an error
			return true;
		}


		/**
		 * Read the image paths from an attachment's meta data and process each image
		 * with wp_smushit().
		 *
		 * This method also adds a `wp_smushit` meta key for use in the media library.
		 *
		 * Called after `wp_generate_attachment_metadata` is completed.
		 */
		function resize_from_meta_data( $meta, $ID = null, $force_resmush = true ) {
			global $log;
			if ( $ID && wp_attachment_is_image( $ID ) === false ) {
				return $meta;
			}

			$attachment_file_path = get_attached_file( $ID );
			$attachment_file_url  = wp_get_attachment_url( $ID );

			if ( $force_resmush || $this->should_resmush( @$meta['wp_smushit'] ) ) {
				$meta['wp_smushit'] = $this->do_smushit( $ID, $attachment_file_path, $attachment_file_url );
			}

			// no resized versions, so we can exit
			if ( ! isset( $meta['sizes'] ) ) {
				return $meta;
			}
			foreach ( $meta['sizes'] as $size_key => $size_data ) {
				if ( ! $force_resmush && $this->should_resmush( @$meta['sizes'][ $size_key ]['wp_smushit'] ) === false ) {
					continue;
				}

				// We take the original image. The 'sizes' will all match the same URL and
				// path. So just get the dirname and rpelace the filename.
				$attachment_file_path_size = trailingslashit( dirname( $attachment_file_path ) ) . $size_data['file'];

				$attachment_file_url_size = trailingslashit( dirname( $attachment_file_url ) ) . $size_data['file'];

				$meta['sizes'][ $size_key ]['wp_smushit'] = $this->do_smushit( $ID, $attachment_file_path_size, $attachment_file_url_size );
			}

			return $meta;
		}

		/**
		 * Post an image to Smush.it.
		 *
		 * @param   string $file_url URL of the file to send to Smush.it
		 *
		 * @return  array  Returns array containing success status, and stats
		 */
		function _post( $file_path, $file_size, $api_key ) {
			global $log;

			$data = false;
			if ( function_exists( 'wp_remote_post' ) ) {
				$file      = @fopen( $file_path, 'r' );
				$file_data = fread( $file, $file_size );
				$headers   = array(
					'accept'       => 'application/json', // The API returns JSON
					'content-type' => 'application/binary', // Set content type to binary
				);

				//Check if premium member, add API key
				if ( ! empty( $api_key ) ) {
					$headers['api_key'] = $api_key;
				}

				//If premium check if user has allowed lossy optimisation
				if ( ! empty( $api_key ) ) {
					//Check if lossy compression allowed and add it to headers
					$headers['lossy'] = true;
				}

				$args   = array(
					'headers' => $headers,
					'body'    => $file_data,
					'timeout' => 10
				);
				$result = wp_remote_post( SMUSHIT_REQ_URL, $args );

				//Close file connection
				fclose( $file );
				unset( $file_data );

				if ( is_wp_error( $result ) ) {
					//Handle error
					$data['message'] = __( 'Error posting to server', WP_SMUSHIT_DOMAIN );
					$data['success'] = false;

					return $data;
				}
				$response = json_decode( $result['body'] );

				//If there is a response and image was successfully optimised
				if ( $response && $response->success == true ) {

					//If there is any savings
					if ( $response->data->bytes_saved > 0 ) {
						$image     = base64_decode( $response->data->image );
						$image_md5 = md5( $response->data->image );
						if ( $response->data->image_md5 != $image_md5 ) {
							//Handle error
							$data['message'] = __( 'Image data corrupted', WP_SMUSHIT_DOMAIN );
							$data['success'] = false;
						} else {
							$data['success']     = true;
							$data['data']        = $response->data;
							$data['data']->image = $image;
						}
					} else {
						//just return the data
						$data['success'] = true;
						$data['data']    = $response->data;
					}
				} else {
					//Server side error, get message from response
					$data['message'] = ! empty( $response->data->message ) ? $response->data->message : __( "Image couldn't be smushed", WP_SMUSHIT_DOMAIN );
					$data['success'] = false;
				}
			} else {
				wp_die( __( 'WP Smush requires WordPress 2.8 or greater', WP_SMUSHIT_DOMAIN ) );
			}

			return $data;
		}


		/**
		 * Print column header for Smush.it results in the media library using
		 * the `manage_media_columns` hook.
		 */
		function columns( $defaults ) {
			$defaults['smushit'] = 'WP Smush';

			return $defaults;
		}

		/**
		 * Return the filesize in a humanly readable format.
		 * Taken from http://www.php.net/manual/en/function.filesize.php#91477
		 */
		function format_bytes( $bytes, $precision = 2 ) {
			$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
			$bytes = max( $bytes, 0 );
			$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
			$pow   = min( $pow, count( $units ) - 1 );
			$bytes /= pow( 1024, $pow );

			return round( $bytes, $precision ) . ' ' . $units[ $pow ];
		}

		/**
		 * Print column data for Smush.it results in the media library using
		 * the `manage_media_custom_column` hook.
		 */
		function custom_column( $column_name, $id ) {
			if ( 'smushit' == $column_name ) {
				$data = wp_get_attachment_metadata( $id );
				if ( isset( $data['wp_smushit'] ) && ! empty( $data['wp_smushit'] ) ) {
					echo "<div class='smush-status'>" . $data['wp_smushit'] . "</div>";
					printf( "<button  class='button wp-smush-image' data-id='%d'>%s</button>", $id, __( 'Re-smush', WP_SMUSHIT_DOMAIN ) );
				} else {
					if ( wp_attachment_is_image( $id ) ) {
						echo "<div class='smush-status'>" . __( 'Not processed', WP_SMUSHIT_DOMAIN ) . "</div>";
						printf( "<button href='#' class='button button wp-smush-image' data-id='%d'>%s</button>", $id, __( 'Smush.it now!', WP_SMUSHIT_DOMAIN ) );
					}
				}
			}
		}


		// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/
		function add_bulk_actions_via_javascript() {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function ($) {
					$('select[name^="action"] option:last-child').before('<option value="bulk_smushit">Bulk Smush.it</option>');
				});
			</script>
		<?php
		}


		// Handles the bulk actions POST
		// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/
		function bulk_action_handler() {
			check_admin_referer( 'bulk-media' );

			if ( empty( $_REQUEST['media'] ) || ! is_array( $_REQUEST['media'] ) ) {
				return;
			}

			$ids = implode( ',', array_map( 'intval', $_REQUEST['media'] ) );

			// Can't use wp_nonce_url() as it escapes HTML entities
			$url = admin_url( 'upload.php' );
			$url = add_query_arg(
				array(
					'page'     => 'wp-smush-bulk',
					'goback'   => 1,
					'ids'      => $ids,
					'_wpnonce' => wp_create_nonce( 'wp-smush-bulk' )
				),
				$url
			);
			wp_redirect( $url );
			exit();
		}

		// default is 6hrs
		function temporarily_disable( $seconds = 21600 ) {
			update_option( 'wp_smushit_smushit_auto', time() + $seconds );
		}

		/**
		 * Check if user is premium member, check for api key
		 *
		 * @return mixed|string
		 */
		function is_premium() {

			$api_key = '';

			if ( defined( 'WPMUDEV_APIKEY' ) ) {
				$api_key = WPMUDEV_APIKEY;
			} else {
				$api_key = get_site_option( 'wpmudev_apikey' );
			}

			return $api_key;
		}

	}

	$WpSmush = new WpSmush();
	global $WpSmush;

}
//Include Admin class
require_once( WP_SMUSHIT_DIR . '/lib/class-wp-smush-bulk.php' );
require_once( WP_SMUSHIT_DIR . '/lib/class-wp-smush-admin.php' );
/**
 * Error Log
 */
require_once( WP_SMUSHIT_DIR . '/lib/error/class-wp-smush-errorlog.php' );
require_once( WP_SMUSHIT_DIR . '/lib/error/class-wp-smush-errorregistry.php' );

if ( ! function_exists( 'wp_basename' ) ) {
	/**
	 * Introduced in WP 3.1... this is copied verbatim from wp-includes/formatting.php.
	 */
	function wp_basename( $path, $suffix = '' ) {
		return urldecode( basename( str_replace( '%2F', '/', urlencode( $path ) ), $suffix ) );
	}
}