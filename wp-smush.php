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
Copyright 2007-2015 Incsub (http://incsub.com)

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


/**
 * Constants
 */
define( 'WP_SMUSH_API', 'https://smushpro.wpmudev.org/1.0/' );
define( 'WP_SMUSH_VERSON',  "2.0" );

define( 'WP_SMUSH_DOMAIN', 'wp_smush' );
define( 'WP_SMUSH_UA', 'WP Smush PRO/' . WP_SMUSH_VERSON . '(' . '+' . get_site_url() . ')' );;
define( 'WP_SMUSH_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_SMUSH_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_SMUSH_MAX_BYTES', 1000000 );
define( 'WP_SMUSH_PREMIUM_MAX_BYTES', 8000000 );
define( 'WP_SMUSH_PREFIX', 'wp-smush-' );

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

require_once WP_SMUSH_DIR ."/lib/class-wp-smush-migrate.php";


if ( ! class_exists( 'WpSmush' ) ) {

	class WpSmush {

		var $version = WP_SMUSH_VERSON;

		/**
		 * Meta key for api validity
		 *
		 */
		const VALIDITY_KEY = "wp-smush-valid";

		/**
		 * Api server url to check api key validity
		 *
		 */
		const API_SERVER = 'https://premium.wpmudev.org/wdp-un.php?action=smushit_check';

		/**
		 * Meta key to save smush result to db
		 *
		 *
		 */
		const SMUSHED_META_KEY = 'wp-smush-data';

		/**
		 * Meta key to save migrated version
		 *
		 */
		const MIGRATED_VERSION = "wp-smush-migrated-version";


		/**
		 * Instance of WpSmushMigrate class
		 *
		 * @var WpSmushMigrate  $_migrator
		 */
		private $_migrator;

		/**
		 * Constructor
		 */
		function __construct() {
			$this->_migrator = new WpSmushMigrate();

			/**
			 * Hooks
			 */
			if ( WP_SMUSHIT_AUTO == WP_SMUSHIT_AUTO_OK ) {
				add_filter( 'wp_generate_attachment_metadata', array( $this, 'resize_from_meta_data' ), 10, 2 );
			}
			add_filter( 'manage_media_columns', array( $this, 'columns' ) );
			add_action( 'manage_media_custom_column', array( $this, 'custom_column' ), 10, 2 );
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'admin_head-upload.php', array( $this, 'add_bulk_actions_via_javascript' ) );
			add_action( 'admin_action_bulk_smushit', array( $this, 'bulk_action_handler' ) );
			add_action("admin_init", array( $this, "migrate" ));

		}

		function WpSmush() {
			$this->__construct();
		}

		function settings_cb() {
		}

		function admin_init() {
			load_plugin_textdomain( WP_SMUSH_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
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
				return __( "File path is empty", WP_SMUSH_DOMAIN );
			}

			if ( empty( $file_url ) ) {
				return __( "File URL is empty", WP_SMUSH_DOMAIN );
			}

			static $error_count = 0;

			if ( $error_count >= WP_SMUSHIT_ERRORS_BEFORE_QUITTING ) {
				return __( "Did not Smush due to previous errors", WP_SMUSH_DOMAIN );
			}

			// check that the file exists
			if ( ! file_exists( $file_path ) || ! is_file( $file_path ) ) {
				return sprintf( __( "ERROR: Could not find <span class='code'>%s</span>", WP_SMUSH_DOMAIN ), $file_path );
			}

			// check that the file is writable
			if ( ! is_writable( dirname( $file_path ) ) ) {
				return sprintf( __( "ERROR: <span class='code'>%s</span> is not writable", WP_SMUSH_DOMAIN ), dirname( $file_path ) );
			}

			$file_size = filesize( $file_path );

			//Check if premium user
			$api_key = $this->_get_api_key();

			$max_size = ! empty( $api_key ) ? WP_SMUSH_PREMIUM_MAX_BYTES : WP_SMUSH_MAX_BYTES;

			//Check if file exists
			if ( $file_size == 0 ) {
				return sprintf( __( 'ERROR: <span style="color:#FF0000;">Skipped (%s), image not found.</span>', WP_SMUSH_DOMAIN ), $this->format_bytes( $file_size ) );
			}
			//Check size limit
			if ( $file_size > $max_size ) {
				return sprintf( __( 'ERROR: <span style="color:#FF0000;">Skipped (%s), size limit exceeded.</span>', WP_SMUSH_DOMAIN ), $this->format_bytes( $file_size ) );
			}

			/** Send image for smushing, and fetch the response */
			$response = $this->_post( $file_path, $file_size, $api_key );

			if ( false === $response ) {
				$error_count ++;

				return __( 'ERROR: posting to Smush', WP_SMUSH_DOMAIN );
			}
			//If there is no data
			if ( empty( $response['data'] ) ) {
				return __( 'Bad response from server', WP_SMUSH_DOMAIN );
			}

			//If there are no savings, or image returned is bigger in size
			if ( ( ! empty( $response['data']->bytes_saved ) && intval( $response['data']->bytes_saved ) <= 0 )
			     || empty( $response['data']->image )
			) {
				return $response;
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

			return $response;
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
		 * Fills $placeholder array with values from $data array without creating new keys
		 *
		 * @param array $placeholders
		 * @param array $data
		 *
		 * @return array
		 */
		private function _array_fill_placeholders(array $placeholders, array $data){
			return array_merge($placeholders, array_intersect_key($data, $placeholders));
		}

		/**
		 * Returns signature for single size of the smush api message to be saved to db;
		 *
		 * @return array
		 */
		private function _get_size_signature(){
			return array(
				'compression' => -1,
				'bytes_saved' => -1,
				'before_size' => -1,
				'after_size' => -1,
				'time' => -1
			);
		}

		/**
		 * Read the image paths from an attachment's meta data and process each image
		 * with wp_smushit().
		 *
		 * This method also adds a `wp_smushit` meta key for use in the media library.
		 * Called after `wp_generate_attachment_metadata` is completed.
		 *
		 * @param $meta
		 * @param null $ID
		 * @param bool $force_resmush
		 *
		 * @return mixed
		 */
		function resize_from_meta_data( $meta, $ID = null, $force_resmush = true ) {

			//Flag to check, if full image needs to be smushed or not
			$smush_full = true;
			$stats = array(
				"stats" => array_merge( $this->_get_size_signature(), array(
						'api_version' => -1,
						'lossy' => -1
					)
				),
				'sizes' => array()
			);

			$size_before    = $size_after = $compression = $total_time = $bytes_saved = 0;

			if ( $ID && wp_attachment_is_image( $ID ) === false ) return $meta;

			//File path and URL for original image
			$attachment_file_path = get_attached_file( $ID );
			$attachment_file_url  = wp_get_attachment_url( $ID );

			// If images has other registered size, smush them first
			if ( ! empty( $meta['sizes'] ) ) {

				foreach ( $meta['sizes'] as $size_key => $size_data ) {

					if ( $size_key == 'large' ) $smush_full = false;

					//Check if size is already smushed
					if ( ! $force_resmush && $this->should_resmush( @$meta['sizes'][ $size_key ]['wp_smushit'] ) === false ) continue;

					// We take the original image. The 'sizes' will all match the same URL and
					// path. So just get the dirname and rpelace the filename.

					$attachment_file_path_size = trailingslashit( dirname( $attachment_file_path ) ) . $size_data['file'];
					$attachment_file_url_size = trailingslashit( dirname( $attachment_file_url ) ) . $size_data['file'];

					//Store details for each size key
					$response = $this->do_smushit( $ID, $attachment_file_path_size, $attachment_file_url_size );

					if ( ! empty( $response['data'] ) ) {
						$stats['sizes'][ $size_key ] = (object) $this->_array_fill_placeholders( $this->_get_size_signature(), (array) $response['data'] );
					}

					//Total Stats, store all data in bytes
					list( $size_before, $size_after, $total_time, $compression, $bytes_saved )
						= $this->_update_stats_data( $response['data'], $size_before, $size_after, $total_time, $compression, $bytes_saved );

					if ( empty( $stats['stats']['api_version'] ) ) {
						$stats['stats']['api_version'] = $response['data']->api_version;
						$stats['stats']['lossy']       = $response['data']->lossy;
					}
				}
			}

			if ( $smush_full && ( $force_resmush || $this->should_resmush( $meta['wp_smushit'] ) ) ) {

				$full_image_response = $this->do_smushit( $ID, $attachment_file_path, $attachment_file_url );
				if ( ! empty( $full_image_response['data'] ) ) {
					$stats['sizes']['full'] = (object) $this->_array_fill_placeholders( $this->_get_size_signature(), (array) $full_image_response['data'] );
				}

				if( isset( $full_image_response['data'] ) )
				list( $stats['stats']['before_size'], $stats['stats']['after_size'], $stats['stats']['time'], $stats['stats']['compression'], 	$stats['stats']['bytes_saved'] )
					= $this->_update_stats_data( $full_image_response['data'], $size_before, $size_after, $total_time, $compression, $bytes_saved );

			}

			//Set smush status for all the images
			update_post_meta( $ID, WP_SMUSH_PREFIX . 'data', $stats );

			//return stats
			return $stats['stats'];
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

				//Check if lossy compression allowed and add it to headers
				$lossy = get_site_option( 'wp_smushit_lossy' ); //TODO this setting does not exist at the moment
				if ( $lossy && $this->is_premium() ) {
					$headers['lossy'] = 'true';
				} else {
					$headers['lossy'] = 'false';
				}

				$args   = array(
					'headers' => $headers,
					'body'    => $file_data,
					'timeout' => 30
				);
				$result = wp_remote_post( WP_SMUSH_API, $args );

				//Close file connection
				fclose( $file );
				unset( $file_data );//free memory

				if ( is_wp_error( $result ) ) {
					//Handle error
					$data['message'] = sprintf( __( 'Error posting to server: %s', WP_SMUSH_DOMAIN ), $result->get_error_message() );
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
							$data['message'] = __( 'Image data corrupted during download, try again.', WP_SMUSH_DOMAIN );
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
					$data['message'] = ! empty( $response->data->message ) ? $response->data->message : __( "Image couldn't be smushed", WP_SMUSH_DOMAIN );
					$data['success'] = false;
				}
			} else {
				wp_die( __( 'WP Smush requires WordPress 2.8 or greater', WP_SMUSH_DOMAIN ) );
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
				$this->set_status( $id );
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
			if ( empty( $api_key ) ) {
				return false;
			}

			if ( false === ( $valid = get_site_transient( self::VALIDITY_KEY ) ) ) {
				// call api
				$url = self::API_SERVER . '&key=' . urlencode( $api_key );

				$request = wp_remote_get( $url, array(
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
		 * Checks if image is already smushed
		 *
		 * @param int $id
		 * @param array $data
		 *
		 * @return bool|mixed
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
		 * Returns size saved from the api call response
		 *
		 * @param string $message
		 *
		 * @return string|bool
		 */
		function get_saved_size( $message ) {
			if ( preg_match( '/\((.*)\)/', $message, $matches ) ) {
				return isset( $matches[1] ) ? $matches[1] : false;
			}

			return false;
		}

		/**
		 * Set send button status
		 *
		 * @param $id
		 * @param bool $echo
		 * @param bool $text_only
		 *
		 * @return string|void
		 */
		function set_status( $id, $echo = true, $text_only = false ) {
			$status_txt    = $button_txt = '';
			$show_button   = false;
			$wp_smush_data = get_post_meta( $id, WP_SMUSH_PREFIX . "data", true );

			// if the image is smushed
			if ( ! empty( $wp_smush_data ) ) {

				$bytes          = isset( $wp_smush_data['stats']['bytes_saved'] ) ? $wp_smush_data['stats']['bytes_saved'] : 0;
				$bytes_readable = ! empty( $bytes ) ? $this->format_bytes( $bytes ) : '';
				$percent        = isset( $wp_smush_data['stats']['compression'] ) ? $wp_smush_data['stats']['compression'] : 0;
				$percent        = $percent < 0 ? 0 : $percent;

				if ( $bytes == 0 || $percent == 0 ) {
					$status_txt = __( 'Already Optimized', WP_SMUSH_DOMAIN );
				} elseif ( ! empty( $percent ) && ! empty( $bytes_readable ) ) {
					$status_txt = sprintf( __( "Reduced by %s (  %01.1f%% )", WP_SMUSH_DOMAIN ), $bytes_readable, number_format_i18n( $percent, 2, '.', '' ) );
				}

				// the button text
				$button_txt = __( 'Re-smush', WP_SMUSH_DOMAIN );
			} else {

				// the status
				$status_txt = __( 'Not processed', WP_SMUSH_DOMAIN );

				// we need to show the smush button
				$show_button = true;

				// the button text
				$button_txt = __( 'Smush now!', WP_SMUSH_DOMAIN );
			}
			if ( $text_only ) {
				return $status_txt;
			}
			$text = $this->column_html( $id, $status_txt, $button_txt, $show_button, $wp_smush_data, $echo );
			if ( ! $echo ) {
				return $text;
			}
		}

		/**
		 * Print the column html
		 *
		 * @param string $id Media id
		 * @param string $status_txt Status text
		 * @param string $button_txt Button label
		 * @param boolean $show_button Whether to shoe the button
		 *
		 * @return null
		 */
		function column_html( $id, $status_txt = "", $button_txt = "", $show_button = true, $smushed = false, $echo = true ) {
			$allowed_images = array( 'image/jpeg', 'image/jpg', 'image/png', 'image/gif' );
			// don't proceed if attachment is not image, or if image is not a jpg, png or gif
			if ( ! wp_attachment_is_image( $id ) || ! in_array( get_post_mime_type( $id ), $allowed_images ) ) {
				return;
			}
			$html = '
			<p class="smush-status">' . $status_txt . '</p>';
			// if we aren't showing the button
			if ( ! $show_button ) {
				if ( $echo ) {
					echo $html;

					return;
				} else {
					if ( ! $smushed ) {
						$class = ' currently-smushing';
					} else {
						$class = ' smushed';
					}

					return '<div class="smush-wrap' . $class . '">' . $html . '</div>';
				}
			}
			if ( ! $echo ) {
				$html .= '
				<button id="wp-smush-send" class="button button-primary" data-id="' . $id . '">
	                <span>' . $button_txt . '</span>
				</button>';
				if ( ! $smushed ) {
					$class = ' unsmushed';
				} else {
					$class = ' smushed';
				}

				return '<div class="smush-wrap' . $class . '">' . $html . '</div>';
			} else {
				$html .= '<button id="wp-smush-send" class="button" data-id="' . $id . '">
                    <span>' . $button_txt . '</span>
				</button>';
				echo $html;
			}
		}

		/**
		 * Migrates smushit api message to the latest structure
		 *
		 *
		 * @return void
		 */
		function migrate(){

			if( !version_compare( $this->version, "1.7.1", "lte" ) ) return;

			$migrated_version = get_option( self::MIGRATED_VERSION );

			if( $migrated_version === $this->version ) return;

			global $wpdb;

			$q = $wpdb->prepare( "SELECT * FROM `" . $wpdb->postmeta . "` WHERE `meta_key`=%s AND `meta_value` LIKE %s ", "_wp_attachment_metadata", "%wp_smushit%"   );
			$results = $wpdb->get_results( $q );

			if( count( $results ) < 1 ) return;

			$migrator = new WpSmushMigrate();
			foreach( $results as $attachment_meta){
				$migrated_message = $this->_migrator->migrate_api_message( maybe_unserialize( $attachment_meta->meta_value ) );
				if( $migrated_message !== array() ){
					update_post_meta( $attachment_meta->post_id, self::SMUSHED_META_KEY,  $migrated_message);
				}
			}

			update_option( self::MIGRATED_VERSION , $this->version);

		}

		/**
		 * @param Object $response_data
		 * @param $size_before
		 * @param $size_after
		 * @param $total_time
		 * @param $compression
		 * @param $bytes_saved
		 *
		 * @return array
		 */
		private function _update_stats_data( $response_data, $size_before, $size_after, $total_time, $compression, $bytes_saved ) {
			$size_before += ! empty( $response_data->before_size ) ? (int) $response_data->before_size : 0;
			$size_after += ( ! empty( $response_data->after_size ) && $response_data->after_size > 0 ) ? (int) $response_data->after_size : (int) $response_data->before_size;
			$total_time += ! empty( $response_data->time ) ? (float) $response_data->time : 0;
			$compression += ( ! empty( $response_data->compression ) && $response_data->compression > 0 ) ? (float) $response_data->compression : 0;
			$bytes_saved += ( ! empty( $response_data->bytes_saved ) && $response_data->bytes_saved > 0 ) ? (float) $response_data->bytes_saved : 0;

			return array( $size_before, $size_after, $total_time, $compression, $bytes_saved );
		}
	}

	$WpSmush = new WpSmush();
	global $WpSmush;

}
//Include Admin class
require_once( WP_SMUSH_DIR . '/lib/class-wp-smush-bulk.php' );
require_once( WP_SMUSH_DIR . '/lib/class-wp-smush-admin.php' );
/**
 * Error Log
 */
require_once( WP_SMUSH_DIR . '/lib/error/class-wp-smush-errorlog.php' );
require_once( WP_SMUSH_DIR . '/lib/error/class-wp-smush-errorregistry.php' );

if ( ! function_exists( 'wp_basename' ) ) {
	/**
	 * Introduced in WP 3.1... this is copied verbatim from wp-includes/formatting.php.
	 */
	function wp_basename( $path, $suffix = '' ) {
		return urldecode( basename( str_replace( '%2F', '/', urlencode( $path ) ), $suffix ) );
	}
}


