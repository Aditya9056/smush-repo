<?php
/*
Plugin Name: WP Smush
Plugin URI: http://wordpress.org/extend/plugins/wp-smushit/
Description: Reduce image file sizes and improve performance using the <a href="https://premium.wpmudev.org/">WPMU DEV</a> Smush API within WordPress.
Author: WPMU DEV
Version: 2.0
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

		const VALIDITY_KEY = "wp-smush-valid";
		const API_SERVER  = 'https://premium.wpmudev.org/wdp-un.php?action=smushit_check';

		/**
		 * Constructor
		 */
		function __construct() {

			/**
			 * Constants
			 */
			define( 'SMUSHIT_REQ_URL', 'https://smushpro.wpmudev.org/1.0/' );
			define( 'SMUSHIT_BASE_URL', 'https://smushpro.wpmudev.org/1.0/' );

			define( 'WP_SMUSHIT_DOMAIN', 'wp_smushit' );
			define( 'WP_SMUSHIT_UA', "WP Smush/{$this->version}; " . network_site_url() );
			define( 'WP_SMUSHIT_DIR', plugin_dir_path( __FILE__ ) );
			define( 'WP_SMUSHIT_URL', plugin_dir_url( __FILE__ ) );
			define( 'WP_SMUSHIT_MAX_BYTES', 1000000 );
			define( 'WP_SMUSH_PREMIUM_MAX_BYTES', 8000000 );

			// The number of images (including generated sizes) that can return errors before abandoning all hope.
			// N.B. this doesn't work with the bulk uploader, since it creates a new HTTP request
			// for each image.  It does work with the bulk smusher, though.
			define( 'WP_SMUSHIT_ERRORS_BEFORE_QUITTING', 3 * count( get_intermediate_image_sizes() ) );

			define( 'WP_SMUSHIT_AUTO', intval( get_option( 'wp_smushit_smushit_auto', 0 ) ) );
			define( 'WP_SMUSHIT_TIMEOUT', intval( get_option( 'wp_smushit_smushit_timeout', 60 ) ) );

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
		 * Process an image with Smush.
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
				return __( "Did not Smush due to previous errors", WP_SMUSHIT_DOMAIN );
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
			$api_key = $this->_get_api_key();

			$max_size = ! empty( $api_key ) ? WP_SMUSH_PREMIUM_MAX_BYTES : WP_SMUSHIT_MAX_BYTES;

			//Check if file exists
			if ( $file_size == 0 ) {
				return sprintf( __( 'ERROR: <span style="color:#FF0000;">Skipped (%s), image not found.</span>', WP_SMUSHIT_DOMAIN ), $this->format_bytes( $file_size ) );
			}
			//Check size limit
			if ( $file_size > $max_size ) {
				return sprintf( __( 'ERROR: <span style="color:#FF0000;">Skipped (%s), size limit exceeded.</span>', WP_SMUSHIT_DOMAIN ), $this->format_bytes( $file_size ) );
			}

			/** Send image for smushing, and fetch the response */
			$response = $this->_post( $file_path, $file_size, $api_key );

			if ( false === $response ) {
				$error_count ++;
				return __( 'ERROR: posting to Smush', WP_SMUSHIT_DOMAIN );
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

			//If file renaming was successful
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

			//Flag to check, if full image needs to be smushed or not
			$smush_full = true;

			if ( $ID && wp_attachment_is_image( $ID ) === false ) {
				return $meta;
			}

			//File path and URL for original image
			$attachment_file_path = get_attached_file( $ID );
			$attachment_file_url  = wp_get_attachment_url( $ID );

			// If images has other registered size, smush them first
			if ( ! empty( $meta['sizes'] ) ) {

				foreach ( $meta['sizes'] as $size_key => $size_data ) {

					if ( $size_key == 'large' ) {
						//if large size is registered for image, skip the full
						$smush_full = false;
					}
					//Check if size is already smushed
					if ( ! $force_resmush && $this->should_resmush( @$meta['sizes'][ $size_key ]['wp_smushit'] ) === false ) {
						continue;
					}

					// We take the original image. The 'sizes' will all match the same URL and
					// path. So just get the dirname and rpelace the filename.
					$attachment_file_path_size = trailingslashit( dirname( $attachment_file_path ) ) . $size_data['file'];

					$attachment_file_url_size = trailingslashit( dirname( $attachment_file_url ) ) . $size_data['file'];

					$meta['sizes'][ $size_key ]['wp_smushit'] = $this->do_smushit( $ID, $attachment_file_path_size, $attachment_file_url_size );
				}
			}

			if ( $smush_full && ( $force_resmush || $this->should_resmush( @$meta['wp_smushit'] ) ) ) {
				$meta['wp_smushit'] = $this->do_smushit( $ID, $attachment_file_path, $attachment_file_url );
			}

			//Set smush status for all the images
			update_post_meta( $ID, 'wp-is-smushed', 1 );

			return $meta;
		}

		/**
		 * Post an image to Smush.
		 *
		 * @param   string $file_url URL of the file to send to Smush
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
					$headers['apikey'] = $api_key;
				}

				//If premium check if user has allowed lossy optimisation
				if ( ! empty( $api_key ) ) {
					//TODO Check if lossy compression allowed and add it to headers
					$headers['lossy'] = 'true';
				} else {
					$headers['lossy'] = 'false';
				}

				$args   = array(
					'headers' => $headers,
					'body'    => $file_data,
					'timeout' => 30
				);
				$result = wp_remote_post( SMUSHIT_REQ_URL, $args );

				//Close file connection
				fclose( $file );
				unset( $file_data );//free memory

				if ( is_wp_error( $result ) ) {
					//Handle error
					$data['message'] = sprintf( __( 'Error posting to server: %s', WP_SMUSHIT_DOMAIN ), $result->get_error_message() );
					$data['success'] = false;
					unset( $result ); //free memory
					return $data;
				} else if ( '200' != wp_remote_retrieve_response_code( $result ) ) {
					//Handle error
					$data['message'] = sprintf( __( 'Error posting to server: %s %s', WP_SMUSHIT_DOMAIN ), wp_remote_retrieve_response_code( $result ), wp_remote_retrieve_response_message( $result ) );
					$data['success'] = false;
					unset( $result ); //free memory
					return $data;
				}

				//If there is a response and image was successfully optimised
				$response = json_decode( $result['body'] );
				if ( $response && $response->success == true ) {

					//If there is any savings
					if ( $response->data->bytes_saved > 0 ) {
						$image     = base64_decode( $response->data->image ); //base64_decode is necessary to send binary img over JSON, no security problems here!
						$image_md5 = md5( $response->data->image );
						if ( $response->data->image_md5 != $image_md5 ) {
							//Handle error
							$data['message'] = __( 'Image data corrupted during download, try again.', WP_SMUSHIT_DOMAIN );
							$data['success'] = false;
							unset( $image );//free memory
						} else {
							$data['success']     = true;
							$data['data']        = $response->data;
							$data['data']->image = $image;
							unset( $image );//free memory
						}
					} else {
						//just return the data
						$data['success'] = true;
						$data['data']    = $response->data;
					}
				} else {
					//Server side error, get message from response
					$data['message'] = ! empty( $response->data ) ? $response->data : __( "Image couldn't be smushed", WP_SMUSHIT_DOMAIN );
					$data['success'] = false;
				}
			} else {
				wp_die( __( 'WP Smush requires WordPress 2.8 or greater', WP_SMUSHIT_DOMAIN ) );
			}

			unset( $result );//free memory
			unset( $response );//free memory
			return $data;
		}


		/**
		 * Print column header for Smush results in the media library using
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
		 * Print column data for Smush results in the media library using
		 * the `manage_media_custom_column` hook.
		 */
		function custom_column( $column_name, $id ) {
			if ( 'smushit' == $column_name ) {
				$data = wp_get_attachment_metadata( $id );

				$is_smushed        = $this->is_smushed( $id, $data );
				$compression_stats = $this->get_smush_stats( $id, $data );

				if ( $is_smushed ) {
					echo "<p class='wp-smush-status-title'>" . __( 'Compression stats:', WP_SMUSHIT_DOMAIN ) . "</p>";
					echo "<div class='smush-status'>";
					echo $compression_stats;
					echo "</div>";
					printf( "<button  class='button wp-smush-image' data-id='%d'>%s</button>", $id, __( 'Re-smush', WP_SMUSHIT_DOMAIN ) );
				} else {
					if ( wp_attachment_is_image( $id ) ) {
						echo "<div class='smush-status'>" . __( 'Not processed', WP_SMUSHIT_DOMAIN ) . "</div>";
						printf( "<button href='#' class='button button wp-smush-image' data-id='%d'>%s</button>", $id, __( 'Smush now!', WP_SMUSHIT_DOMAIN ) );
					}
				}
			}
		}


		// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/
		function add_bulk_actions_via_javascript() {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function ($) {
					$('select[name^="action"] option:last-child').before('<option value="bulk_smushit">Bulk Smush</option>');
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

		/**
		 * Check if user is premium member, check for api key
		 *
		 * @return mixed|string
		 */
		function is_premium() {

			//no api key set, always false
			$api_key = $this->_get_api_key();
			if ( empty( $api_key ) ) return false;

			if ( false === ( $valid = get_site_transient( self::VALIDITY_KEY ) ) ) {
				// call api
				$url = self::API_SERVER . '&key=' . urlencode( $api_key );

				$request = wp_remote_get($url, array(
					"timeout" => 3
					)
				);

				if ( ! is_wp_error( $request ) && '200' == wp_remote_retrieve_response_code( $request ) ) {
					$result = json_decode( wp_remote_retrieve_body( $request ) );
					if ( $result && $result->success ) {
						$valid = true;
						set_site_transient( self::VALIDITY_KEY, 1, 12 * HOUR_IN_SECONDS );
					} else {
						$valid = false;
						set_site_transient( self::VALIDITY_KEY, 0, 30 * MINUTE_IN_SECONDS ); //cache failure much shorter
					}

				} else {
					$valid = false;
					set_site_transient( self::VALIDITY_KEY, 0, 5 * MINUTE_IN_SECONDS ); //cache network failure even shorter, we don't want a request every pageload
				}

			}

			return (bool) $valid;
		}

		/**
		 * Returns api key
		 *
		 * @return mixed
		 */
		private function _get_api_key() {
			if ( defined( 'WPMUDEV_APIKEY' ) ) {
				$api_key = WPMUDEV_APIKEY;
			} else {
				$api_key = get_site_option( 'wpmudev_apikey' );
			}
			return $api_key;
		}

		/**
		 * Check if image is already smushed
		 */
		function is_smushed( $id, $data ) {

			//For new images
			$wp_is_smushed = get_post_meta( $id, 'wp-is-smushed', true );

			//Not smushed, backward compatibility, check attachment metadata
			if ( ! $wp_is_smushed ) {
				if ( isset( $data['wp_smushit'] ) && ! empty( $data['wp_smushit'] ) ) {
					$wp_is_smushed = true;
				}
			}

			return $wp_is_smushed;
		}

		/**
		 * Return the stats for each image size
		 *
		 * @param $id
		 * @param $meta
		 */
		function get_smush_stats( $id, $meta ) {

			$compression = "";
			//stats for full size image if any

			if ( ! empty( $meta['wp_smushit'] ) ) {
				$compression .= "<b>Full:</b> " . $meta['wp_smushit'];
			}
			//Add the stats for each size
			if ( ! empty( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $size_name => $size_meta ) {
					if ( ! empty( $size_meta['wp_smushit'] ) ) {
						$compression .= "<br><b>" . ucfirst( $size_name ) . "</b>: " . $size_meta['wp_smushit'];
					}
				}
			}

			return $compression;
		}


		/**
		 * Returns percent saved from the api call response
		 *
		 * @param string $message
		 *
		 * @return string|bool
		 */
		function get_saved_percentage( $message ){
			if ( preg_match('/\d+(\.\d+)?%/', $message, $matches) )
				return isset( $matches[0] ) ? $matches[0] : false;

			return false;
		}

		/**
		 * Returns size saved from the api call response
		 *
		 * @param string $message
		 *
		 * @return string|bool
		 */
		function get_saved_size( $message ) {
			if ( preg_match( '/\((.*)\)/', $message, $matches ) )
				return isset( $matches[1] ) ? $matches[1] : false;

			return false;
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
