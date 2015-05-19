<?php
/*
Plugin Name: WP Smush
Plugin URI: http://wordpress.org/extend/plugins/wp-smushit/
Description: Reduce image file sizes, improve performance and boost your SEO using the free <a href="https://premium.wpmudev.org/">WPMU DEV</a> WordPress Smush API.
Author: WPMU DEV
Version: 2.0.6.1
Author URI: http://premium.wpmudev.org/
Textdomain: wp_smush
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


/**
 * Constants
 */
$prefix          = 'WP_SMUSH_';
$version         = '2.0.6.1';
$smush_constatns = array(
	'VERSON'            => $version,
	'BASENAME'          => plugin_basename( __FILE__ ),
	'API'               => 'https://smushpro.wpmudev.org/1.0/',
	'DOMAIN'            => 'wp_smush',
	'UA'                => 'WP Smush/' . $version . '; ' . network_home_url(),
	'DIR'               => plugin_dir_path( __FILE__ ),
	'URL'               => plugin_dir_url( __FILE__ ),
	'MAX_BYTES'         => 1000000,
	'PREMIUM_MAX_BYTES' => 32000000,
	'PREFIX'            => 'wp-smush-',
	'TIMEOUT'           => 30

);

foreach ( $smush_constatns as $const_name => $constant_val ) {
	if ( ! defined( $prefix . $const_name ) ) {
		define( $prefix . $const_name, $constant_val );
	}
}

require_once WP_SMUSH_DIR . "lib/class-wp-smush-migrate.php";

if ( ! class_exists( 'WpSmush' ) ) {

	class WpSmush {

		var $version = WP_SMUSH_VERSON;

		var $is_pro;

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
		const SMUSHED_META_KEY = 'wp-smpro-smush-data';

		/**
		 * Meta key to save migrated version
		 *
		 */
		const MIGRATED_VERSION = "wp-smush-migrated-version";

		/**
		 * Constructor
		 */
		function __construct() {
			/**
			 * Hooks
			 */
			//Check if auto is enabled
			$auto_smush = get_option( WP_SMUSH_PREFIX . 'auto' );

			//Keep the uto smush on by default
			if ( $auto_smush === false ) {
				$auto_smush = 1;
			}

			if ( $auto_smush ) {
				add_filter( 'wp_generate_attachment_metadata', array(
					$this,
					'filter_generate_attachment_metadata'
				), 10, 2 );
			}
			add_filter( 'manage_media_columns', array( $this, 'columns' ) );
			add_action( 'manage_media_custom_column', array( $this, 'custom_column' ), 10, 2 );
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( "admin_init", array( $this, "migrate" ) );

		}

		function admin_init() {
			load_plugin_textdomain( WP_SMUSH_DOMAIN, false, dirname( WP_SMUSH_BASENAME ) . '/languages/' );
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
		function do_smushit( $file_path = '', $file_url = '' ) {
			$errors = new WP_Error();
			if ( empty( $file_path ) ) {
				$errors->add( "empty_path", __( "File path is empty", WP_SMUSH_DOMAIN ) );
			}

			if ( empty( $file_url ) ) {
				$errors->add( "empty_url", __( "File URL is empty", WP_SMUSH_DOMAIN ) );
			}

			// check that the file exists
			if ( ! file_exists( $file_path ) || ! is_file( $file_path ) ) {
				$errors->add( "file_not_found", sprintf( __( "Could not find %s", WP_SMUSH_DOMAIN ), $file_path ) );
			}

			// check that the file is writable
			if ( ! is_writable( dirname( $file_path ) ) ) {
				$errors->add( "not_writable", sprintf( __( "%s is not writable", WP_SMUSH_DOMAIN ), dirname( $file_path ) ) );
			}

			$file_size = file_exists( $file_path ) ? filesize( $file_path ) : 0;

			//Check if premium user
			$max_size = $this->is_pro() ? WP_SMUSH_PREMIUM_MAX_BYTES : WP_SMUSH_MAX_BYTES;

			//Check if file exists
			if ( $file_size == 0 ) {
				$errors->add( "image_not_found", sprintf( __( 'Skipped (%s), image not found.', WP_SMUSH_DOMAIN ), $this->format_bytes( $file_size ) ) );
			}

			//Check size limit
			if ( $file_size > $max_size ) {
				$errors->add( "size_limit", sprintf( __( 'Skipped (%s), size limit exceeded.', WP_SMUSH_DOMAIN ), $this->format_bytes( $file_size ) ) );
			}

			if ( count( $errors->get_error_messages() ) ) {
				return $errors;
			}

			/** Send image for smushing, and fetch the response */
			$response = $this->_post( $file_path, $file_size );

			if ( ! $response['success'] ) {
				$errors->add( "false_response", $response['message'] );
			}
			//If there is no data
			if ( empty( $response['data'] ) ) {
				$errors->add( "no_data", __( 'Unknown API error', WP_SMUSH_DOMAIN ) );
			}

			if ( count( $errors->get_error_messages() ) ) {
				return $errors;
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

			//handle backups if enabled
			$backup = get_option( WP_SMUSH_PREFIX . 'backup' );
			if ( $backup && $this->is_pro() ) {
				$path        = pathinfo( $file_path );
				$backup_name = trailingslashit( $path['dirname'] ) . $path['filename'] . ".bak." . $path['extension'];
				@copy( $file_path, $backup_name );
			}

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

		/**
		 * Fills $placeholder array with values from $data array
		 *
		 * @param array $placeholders
		 * @param array $data
		 *
		 * @return array
		 */
		private function _array_fill_placeholders( array $placeholders, array $data ) {
			$placeholders['percent']     = $data['compression'];
			$placeholders['bytes']       = $data['bytes_saved'];
			$placeholders['size_before'] = $data['before_size'];
			$placeholders['size_after']  = $data['after_size'];
			$placeholders['time']        = $data['time'];

			return $placeholders;
		}

		/**
		 * Returns signature for single size of the smush api message to be saved to db;
		 *
		 * @return array
		 */
		private function _get_size_signature() {
			return array(
				'percent'     => - 1,
				'bytes'       => - 1,
				'size_before' => - 1,
				'size_after'  => - 1,
				'time'        => - 1
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
		 *
		 * @return mixed
		 */
		function resize_from_meta_data( $meta, $ID = null ) {

			//Flag to check, if original size image needs to be smushed or not
			$smush_full = true;
			$errors     = new WP_Error();
			$stats      = array(
				"stats" => array_merge( $this->_get_size_signature(), array(
						'api_version' => - 1,
						'lossy'       => - 1
					)
				),
				'sizes' => array()
			);

			$size_before = $size_after = $compression = $total_time = $bytes_saved = 0;

			if ( $ID && wp_attachment_is_image( $ID ) === false ) {
				return $meta;
			}

			//File path and URL for original image
			$attachment_file_path = get_attached_file( $ID );
			$attachment_file_url  = wp_get_attachment_url( $ID );

			// If images has other registered size, smush them first
			if ( ! empty( $meta['sizes'] ) ) {

				foreach ( $meta['sizes'] as $size_key => $size_data ) {

					//if there is a large size, then we will set a flag to leave the original untouched
					if ( $size_key == 'large' ) {
						$smush_full = false;
					}

					// We take the original image. The 'sizes' will all match the same URL and
					// path. So just get the dirname and replace the filename.

					$attachment_file_path_size = trailingslashit( dirname( $attachment_file_path ) ) . $size_data['file'];
					$attachment_file_url_size  = trailingslashit( dirname( $attachment_file_url ) ) . $size_data['file'];

					//Store details for each size key
					$response = $this->do_smushit( $attachment_file_path_size, $attachment_file_url_size );

					if ( is_wp_error( $response ) ) {
						return $response;
					}

					if ( ! empty( $response['data'] ) ) {
						$stats['sizes'][ $size_key ] = (object) $this->_array_fill_placeholders( $this->_get_size_signature(), (array) $response['data'] );
					}

					//Total Stats, store all data in bytes
					if ( isset( $response['data'] ) ) {
						list( $size_before, $size_after, $total_time, $compression, $bytes_saved )
							= $this->_update_stats_data( $response['data'], $size_before, $size_after, $total_time, $bytes_saved );
					} else {
						$errors->add( "image_size_error" . $size_key, sprintf( __( "Size '%s' not processed correctly", WP_SMUSH_DOMAIN ), $size_key ) );
					}

					if ( empty( $stats['stats']['api_version'] ) || $stats['stats']['api_version'] == - 1 ) {
						$stats['stats']['api_version'] = $response['data']->api_version;
						$stats['stats']['lossy']       = $response['data']->lossy;
					}
				}
			}

			//If original size is supposed to be smushed
			if ( $smush_full ) {

				$full_image_response = $this->do_smushit( $attachment_file_path, $attachment_file_url );

				if ( is_wp_error( $full_image_response ) ) {
					return $full_image_response;
				}

				if ( ! empty( $full_image_response['data'] ) ) {
					$stats['sizes']['full'] = (object) $this->_array_fill_placeholders( $this->_get_size_signature(), (array) $full_image_response['data'] );
				} else {
					$errors->add( "image_size_error", __( "Size 'full' not processed correctly", WP_SMUSH_DOMAIN ) );
				}

				//Update stats
				if ( isset( $full_image_response['data'] ) ) {
					list( $size_before, $size_after, $total_time, $compression, $bytes_saved )
						= $this->_update_stats_data( $full_image_response['data'], $size_before, $size_after, $total_time, $bytes_saved );
				} else {
					$errors->add( "image_size_error", __( "Size 'full' not processed correctly", WP_SMUSH_DOMAIN ) );
				}

				//Api version and lossy, for some images, full image i skipped and for other images only full exists
				//so have to add code again
				if ( empty( $stats['stats']['api_version'] ) || $stats['stats']['api_version'] == - 1 ) {
					$stats['stats']['api_version'] = $full_image_response['data']->api_version;
					$stats['stats']['lossy']       = $full_image_response['data']->lossy;
				}


			}

			$has_errors = (bool) count( $errors->get_error_messages() );

			list( $stats['stats']['size_before'], $stats['stats']['size_after'], $stats['stats']['time'], $stats['stats']['percent'], $stats['stats']['bytes'] ) =
				array( $size_before, $size_after, $total_time, $compression, $bytes_saved );

			//Set smush status for all the images, store it in wp-smpro-smush-data
			if ( ! $has_errors ) {

				$existing_stats = get_post_meta( $ID, self::SMUSHED_META_KEY, true );

				if ( ! empty( $existing_stats ) ) {
					//Update total bytes saved, and compression percent
					$stats['stats']['bytes']   = isset( $existing_stats['stats']['bytes'] ) ? $existing_stats['stats']['bytes'] + $stats['stats']['bytes'] : $stats['stats']['bytes'];
					$stats['stats']['percent'] = isset( $existing_stats['stats']['percent'] ) ? $existing_stats['stats']['percent'] + $stats['stats']['percent'] : $stats['stats']['percent'];

					//Update stats for each size
					if ( ! empty( $existing_stats['sizes'] ) && ! empty( $stats['sizes'] ) ) {

						foreach ( $existing_stats['sizes'] as $size_name => $size_stats ) {
							//if stats for a particular size doesn't exists
							if ( empty( $stats['sizes'][$size_name] ) ) {
								$stats['sizes'][$size_name] = $existing_stats['sizes'][$size_name];
							} else {
								//Update compression percent and bytes saved for each size
								$stats['sizes'][$size_name]->bytes   = $stats['sizes'][$size_name]->bytes + $existing_stats['sizes'][$size_name]->bytes;
								$stats['sizes'][$size_name]->percent = $stats['sizes'][$size_name]->percent + $existing_stats['sizes'][$size_name]->percent;
							}
						}
					}
				}
				update_post_meta( $ID, self::SMUSHED_META_KEY, $stats );
			}

			return $meta;
		}

		/**
		 * Read the image paths from an attachment's meta data and process each image
		 * with wp_smushit()
		 *
		 * Filters  wp_generate_attachment_metadata
		 *
		 * @uses WpSmush::resize_from_meta_data
		 *
		 * @param $meta
		 * @param null $ID
		 *
		 * @return mixed
		 */
		function filter_generate_attachment_metadata( $meta, $ID = null ) {
			$this->resize_from_meta_data( $meta, $ID );

			return $meta;
		}


		/**
		 * Posts an image to Smush.
		 *
		 * @param $file_path path of file to send to Smush
		 * @param $file_size
		 *
		 * @return bool|array array containing success status, and stats
		 */
		function _post( $file_path, $file_size ) {

			$data = false;

			$file      = @fopen( $file_path, 'r' );
			$file_data = fread( $file, $file_size );
			$headers   = array(
				'accept'       => 'application/json', // The API returns JSON
				'content-type' => 'application/binary', // Set content type to binary
			);

			//Check if premium member, add API key
			$api_key = $this->_get_api_key();
			if ( ! empty( $api_key ) ) {
				$headers['apikey'] = $api_key;
			}

			//Check if lossy compression allowed and add it to headers
			$lossy = get_option( WP_SMUSH_PREFIX . 'lossy' );

			if ( $lossy && $this->is_pro() ) {
				$headers['lossy'] = 'true';
			} else {
				$headers['lossy'] = 'false';
			}

			$args   = array(
				'headers'    => $headers,
				'body'       => $file_data,
				'timeout'    => WP_SMUSH_TIMEOUT,
				'user-agent' => WP_SMUSH_UA
			);
			$result = wp_remote_post( WP_SMUSH_API, $args );

			//Close file connection
			fclose( $file );
			unset( $file_data );//free memory
			if ( is_wp_error( $result ) ) {
				//Handle error
				$data['message'] = sprintf( __( 'Error posting to API: %s', WP_SMUSH_DOMAIN ), $result->get_error_message() );
				$data['success'] = false;
				unset( $result ); //free memory
				return $data;
			} else if ( '200' != wp_remote_retrieve_response_code( $result ) ) {
				//Handle error
				$data['message'] = sprintf( __( 'Error posting to API: %s %s', WP_SMUSH_DOMAIN ), wp_remote_retrieve_response_code( $result ), wp_remote_retrieve_response_message( $result ) );
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
						$data['message'] = __( 'Smush data corrupted, try again.', WP_SMUSH_DOMAIN );
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
				$data['message'] = ! empty( $response->data ) ? $response->data : __( "Image couldn't be smushed", WP_SMUSH_DOMAIN );
				$data['success'] = false;
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

		/**
		 * Check if user is premium member, check for api key
		 *
		 * @return mixed|string
		 */
		function is_pro() {

			if ( isset( $this->is_pro ) ) return $this->is_pro;

			//no api key set, always false
			$api_key = $this->_get_api_key();
			if ( empty( $api_key ) ) {
				return false;
			}

			$key = "wp-smush-premium-" . substr( $api_key, -10, 10); //add last 10 chars of apikey to transient key in case it changes
			if ( false === ( $valid = get_site_transient( $key ) ) ) {
				// call api
				$url = self::API_SERVER . '&key=' . urlencode( $api_key );

				$request = wp_remote_get( $url, array(
						"user-agent" => WP_SMUSH_UA,
						"timeout" => 3
					)
				);

				if ( ! is_wp_error( $request ) && '200' == wp_remote_retrieve_response_code( $request ) ) {
					$result = json_decode( wp_remote_retrieve_body( $request ) );
					if ( $result && $result->success ) {
						$valid = true;
						set_site_transient( $key, 1, 12 * HOUR_IN_SECONDS );
					} else {
						$valid = false;
						set_site_transient( $key, 0, 30 * MINUTE_IN_SECONDS ); //cache failure much shorter
					}

				} else {
					$valid = false;
					set_site_transient( $key, 0, 5 * MINUTE_IN_SECONDS ); //cache network failure even shorter, we don't want a request every pageload
				}

			}

			$this->is_pro = (bool) $valid;
			return $this->is_pro;
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
		function is_smushed( $id, $data = null ) {

			//For new images
			$wp_is_smushed = get_post_meta( $id, 'wp-is-smushed', true );

			//Not smushed, backward compatibility, check attachment metadata
			if ( ! $wp_is_smushed && $data !== null ) {
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
			$status_txt  = $button_txt = '';
			$show_button = false;

			//Stats are not received properly, otherwise
			wp_cache_delete( $id, 'post_meta' );

			$wp_smush_data = get_post_meta( $id, self::SMUSHED_META_KEY, true );
			// if the image is smushed
			if ( ! empty( $wp_smush_data ) ) {

				$bytes          = isset( $wp_smush_data['stats']['bytes'] ) ? $wp_smush_data['stats']['bytes'] : 0;
				$bytes_readable = ! empty( $bytes ) ? $this->format_bytes( $bytes ) : '';
				$percent        = isset( $wp_smush_data['stats']['percent'] ) ? $wp_smush_data['stats']['percent'] : 0;
				$percent        = $percent < 0 ? 0 : $percent;

				if ( isset( $wp_smush_data['stats']['size_before'] ) && $wp_smush_data['stats']['size_before'] == 0 ) {
					$status_txt  = __( 'Error processing request', WP_SMUSH_DOMAIN );
					$show_button = true;
				} else {
					if ( $bytes == 0 || $percent == 0 ) {
						$status_txt = __( 'Already Optimized', WP_SMUSH_DOMAIN );
					} elseif ( ! empty( $percent ) && ! empty( $bytes_readable ) ) {
						$status_txt = sprintf( __( "Reduced by %s (  %01.1f%% )", WP_SMUSH_DOMAIN ), $bytes_readable, number_format_i18n( $percent, 2, '.', '' ) );
					}
				}

				//IF current compression is lossy
				if ( ! empty( $wp_smush_data ) && ! empty( $wp_smush_data['stats'] ) ) {
					$lossy    = !empty( $wp_smush_data['stats']['lossy'] ) ? $wp_smush_data['stats']['lossy'] : '';
					$is_lossy = $lossy == 1 ? true : false;
				}

				//Check if Lossy enabled
				$opt_lossy     = WP_SMUSH_PREFIX . 'lossy';
				$opt_lossy_val = get_option( $opt_lossy, false );

				//Check image type
				$image_type = get_post_mime_type( $id );

				//Check if premium user, compression was lossless, and lossy compression is enabled
				if ( $this->is_pro() && ! $is_lossy && $opt_lossy_val && $image_type != 'image/gif' ) {
					// the button text
					$button_txt  = __( 'Super-Smush', WP_SMUSH_DOMAIN );
					$show_button = true;
				}
			} else {

				// the status
				$status_txt = __( 'Not processed', WP_SMUSH_DOMAIN );

				// we need to show the smush button
				$show_button = true;

				// the button text
				$button_txt = __( 'Smush Now!', WP_SMUSH_DOMAIN );
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

			$class = $smushed ? '' : ' hidden';
			$html  = '
			<p class="smush-status' . $class . '">' . $status_txt . '</p>';
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
				<button  class="button button-primary wp-smush-send" data-id="' . $id . '">
	                <span>' . $button_txt . '</span>
				</button>';
				if ( ! $smushed ) {
					$class = ' unsmushed';
				} else {
					$class = ' smushed';
				}

				return '<div class="smush-wrap' . $class . '">' . $html . '</div>';
			} else {
				$html .= '<button class="button wp-smush-send" data-id="' . $id . '">
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
		function migrate() {

			if ( ! version_compare( $this->version, "1.7.1", "lte" ) ) {
				return;
			}

			$migrated_version = get_option( self::MIGRATED_VERSION );

			if ( $migrated_version === $this->version ) {
				return;
			}

			global $wpdb;

			$q       = $wpdb->prepare( "SELECT * FROM `" . $wpdb->postmeta . "` WHERE `meta_key`=%s AND `meta_value` LIKE %s ", "_wp_attachment_metadata", "%wp_smushit%" );
			$results = $wpdb->get_results( $q );

			if ( count( $results ) < 1 ) {
				return;
			}

			$migrator = new WpSmushMigrate();
			foreach ( $results as $attachment_meta ) {
				$migrated_message = $migrator->migrate_api_message( maybe_unserialize( $attachment_meta->meta_value ) );
				if ( $migrated_message !== array() ) {
					update_post_meta( $attachment_meta->post_id, self::SMUSHED_META_KEY, $migrated_message );
				}
			}

			update_option( self::MIGRATED_VERSION, $this->version );

		}

		/**
		 * @param Object $response_data
		 * @param $size_before
		 * @param $size_after
		 * @param $total_time
		 * @param $bytes_saved
		 *
		 * @return array
		 */
		private function _update_stats_data( $response_data, $size_before, $size_after, $total_time, $bytes_saved ) {
			$size_before += ! empty( $response_data->before_size ) ? (int) $response_data->before_size : 0;
			$size_after += ( ! empty( $response_data->after_size ) && $response_data->after_size > 0 ) ? (int) $response_data->after_size : (int) $response_data->before_size;
			$total_time += ! empty( $response_data->time ) ? (float) $response_data->time : 0;
			$bytes_saved += ( ! empty( $response_data->bytes_saved ) && $response_data->bytes_saved > 0 ) ? $response_data->bytes_saved : 0;
			$compression = ( $bytes_saved > 0 && $size_before > 0 ) ? ( ( $bytes_saved / $size_before ) * 100 ) : 0;

			return array( $size_before, $size_after, $total_time, $compression, $bytes_saved );
		}
	}

	global $WpSmush;
	$WpSmush = new WpSmush();

}

//Include Admin classes
require_once( WP_SMUSH_DIR . '/lib/class-wp-smush-bulk.php' );
require_once( WP_SMUSH_DIR . '/lib/class-wp-smush-admin.php' );
//include_once( WP_SMUSH_DIR . '/extras/dash-notice/wpmudev-dash-notification.php' );

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