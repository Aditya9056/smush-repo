<?php

/**
 * @package WP Smush
 * @subpackage NextGen Gallery
 * @version 1.0
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2015, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmushNextGen' ) ) {


	class WpSmushNextGen {

		/**
		 * @var array Contains the total Stats, for displaying it on bulk page
		 */
		var $stats = array();

		var $is_nextgen_active = false;

		function __construct() {

			//Auto Smush image, if enabled, runs after Nextgen is finished uploading the image
			//Check if auto is enabled
			if ( $this->is_auto_smush_enabled() ) {
				add_action( 'ngg_added_new_image', array( &$this, 'auto_smush' ) );
			}

			//Handle Manual Smush request for Nextgen gallery images
			add_action( 'wp_ajax_smush_manual_nextgen', array( $this, 'manual_nextgen' ) );

		}

		/**
		 * If auto smush is set to true or not, default is true
		 *
		 * @return int|mixed|void
		 */
		function is_auto_smush_enabled() {
			$auto_smush = get_option( WP_SMUSH_PREFIX . 'auto' );

			//Keep the auto smush on by default
			if ( $auto_smush === false ) {
				$auto_smush = 1;
			}

			return $auto_smush;
		}

		/**
		 * Queries Nextgen table for a list of image ids
		 * @return mixed Array of ids
		 */
		function get_nextgen_attachments() {
			global $wpdb;

			//Query images from the nextgen table
			$images = $wpdb->get_col( "SELECT pid FROM $wpdb->nggpictures ORDER BY pid ASC" );

			//Return empty array, if there was error querying the images
			if ( empty( $images ) || is_wp_error( $images ) ) {
				$images = array();
			}

			return $images;
		}

		/**
		 * Get image mime type
		 *
		 * @param $file_path
		 *
		 * @return bool|string
		 */
		function get_file_type( $file_path ) {
			if ( empty( $file_path ) ) {
				return false;
			}
			if ( function_exists( 'exif_imagetype' ) ) {
				$image_type = exif_imagetype( $file_path );
				if ( ! empty( $image_type ) ) {
					$image_mime = image_type_to_mime_type( $image_type );
				}
			} else {
				$image_details = getimagesize( $file_path );
				$image_mime    = ! empty( $image_details ) && is_array( $image_details ) ? $image_details['mime'] : '';
			}

			return $image_mime;
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
		function resize_from_meta_data( $storage, $image ) {
			global $WpSmush;

			//Flag to check, if original size image needs to be smushed or not
			$smush_full = true;
			$errors     = new WP_Error();
			$stats      = array(
				"stats" => array_merge( $WpSmush->_get_size_signature(), array(
						'api_version' => - 1,
						'lossy'       => - 1
					)
				),
				'sizes' => array()
			);

			$size_before = $size_after = $compression = $total_time = $bytes_saved = 0;

			//File path and URL for original image
			// get the absolute path
			$attachment_file_path = $storage->get_image_abspath( $image, 'full' );

			// get an array of sizes available for the $image
			$sizes = $storage->get_image_sizes();

			// If images has other registered size, smush them first
			if ( ! empty( $sizes ) ) {

				foreach ( $sizes as $size ) {

					//if there is a large size, then we will set a flag to leave the original untouched
					if ( $size == 'large' ) {
						$smush_full = false;
					}
					//Skip Full Size image
					if ( $size == 'full' ) {
						continue;
					}

					// We take the original image. Get the absolute path using the storage object

					$attachment_file_path_size = $storage->get_image_abspath( $image, $size );

					//Store details for each size key
					$response = $WpSmush->do_smushit( $attachment_file_path_size, $image->pid, 'nextgen' );

					if ( is_wp_error( $response ) ) {
						return $response;
					}

					if ( ! empty( $response['data'] ) ) {
						$stats['sizes'][ $size ] = (object) $WpSmush->_array_fill_placeholders( $WpSmush->_get_size_signature(), (array) $response['data'] );
					}

					//Total Stats, store all data in bytes
					if ( isset( $response['data'] ) ) {
						list( $size_before, $size_after, $total_time, $compression, $bytes_saved )
							= $WpSmush->_update_stats_data( $response['data'], $size_before, $size_after, $total_time, $bytes_saved );
					} else {
						$errors->add( "image_size_error" . $size, sprintf( __( "Size '%s' not processed correctly", WP_SMUSH_DOMAIN ), $size ) );
					}

					if ( empty( $stats['stats']['api_version'] ) || $stats['stats']['api_version'] == - 1 ) {
						$stats['stats']['api_version'] = $response['data']->api_version;
						$stats['stats']['lossy']       = $response['data']->lossy;
					}
				}
			}

			//If original size is supposed to be smushed
			if ( $smush_full ) {

				$full_image_response = $WpSmush->do_smushit( $attachment_file_path, $image->pid, 'nextgen' );

				if ( is_wp_error( $full_image_response ) ) {
					return $full_image_response;
				}

				if ( ! empty( $full_image_response['data'] ) ) {
					$stats['sizes']['full'] = (object) $WpSmush->_array_fill_placeholders( $WpSmush->_get_size_signature(), (array) $full_image_response['data'] );
				} else {
					$errors->add( "image_size_error", __( "Size 'full' not processed correctly", WP_SMUSH_DOMAIN ) );
				}

				//Update stats
				if ( isset( $full_image_response['data'] ) ) {
					list( $size_before, $size_after, $total_time, $compression, $bytes_saved )
						= $WpSmush->_update_stats_data( $full_image_response['data'], $size_before, $size_after, $total_time, $bytes_saved );
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

				$existing_stats = ( ! empty( $image->meta_data ) && ! empty( $image->meta_data['wp_smush'] ) ) ? $image->meta_data['wp_smush'] : '';

				if ( ! empty( $existing_stats ) ) {
					//Update total bytes saved, and compression percent
					$stats['stats']['bytes']   = isset( $existing_stats['stats']['bytes'] ) ? $existing_stats['stats']['bytes'] + $stats['stats']['bytes'] : $stats['stats']['bytes'];
					$stats['stats']['percent'] = isset( $existing_stats['stats']['percent'] ) ? $existing_stats['stats']['percent'] + $stats['stats']['percent'] : $stats['stats']['percent'];

					//Update stats for each size
					if ( ! empty( $existing_stats['sizes'] ) && ! empty( $stats['sizes'] ) ) {

						foreach ( $existing_stats['sizes'] as $size_name => $size_stats ) {
							//if stats for a particular size doesn't exists
							if ( empty( $stats['sizes'][ $size_name ] ) ) {
								$stats['sizes'][ $size_name ] = $existing_stats['sizes'][ $size_name ];
							} else {
								//Update compression percent and bytes saved for each size
								$stats['sizes'][ $size_name ]->bytes   = $stats['sizes'][ $size_name ]->bytes + $existing_stats['sizes'][ $size_name ]->bytes;
								$stats['sizes'][ $size_name ]->percent = $stats['sizes'][ $size_name ]->percent + $existing_stats['sizes'][ $size_name ]->percent;
							}
						}
					}
				}
				$image->meta_data['wp_smush'] = $stats;
				nggdb::update_image_meta( $image->pid, $image->meta_data );

				//Allows To get the stats for each image, after the image is smushed
				do_action( 'wp_smush_nextgen_image_stats', $stats, $image->pid );
			}

			return $image->meta_data['wp_smush'];
		}

		/**
		 * Performs the actual smush process
		 *
		 * @usedby: `smush_manual_nextgen`, `auto_smush`
		 *
		 * @param $pid , NextGen Gallery Image id
		 * @param $storage
		 * @param $image , Nextgen gallery image object
		 * @param $return Whether to return the stats or not, false for auto smush
		 */
		function smush_image( $pid, $storage, $image, $return = true ) {
			global $wpsmushnextgenstats;
			$metadata = ! empty( $image ) ? $image->meta_data : '';

			if ( empty( $metadata ) ) {
				wp_send_json_error( array( 'error' => "missing_metadata" ) );
			}

			//smush the main image and its sizes
			$smush = $this->resize_from_meta_data( $storage, $image, $pid );

			if( !is_wp_error( $smush ) ) {
				$status = $wpsmushnextgenstats->show_stats( $pid, $smush, false, true );
			}

			//If we are suppose to send the stats, not required for auto smush
			if ( $return ) {
				/** Send stats **/
				if ( is_wp_error( $smush ) ) {
					/**
					 * @param WP_Error $smush
					 */
					wp_send_json_error( $smush->get_error_message() );
				} else {
					wp_send_json_success( $status );
				}
			}
		}

		/**
		 * Handles the smushing of each image and its registered sizes
		 * Calls the function to update the compression stats
		 */
		function manual_nextgen() {
			$pid   = ! empty( $_GET['attachment_id'] ) ? $_GET['attachment_id'] : '';
			$nonce = ! empty( $_GET['_wp_smush_nonce'] ) ? $_GET['_wp_smush_nonce'] : '';

			//Verify Nonce
			if ( ! wp_verify_nonce( $nonce, 'wp_smush_nextgen_' . $pid ) ) {
				wp_send_json_error( array( 'error' => 'nonce_verification_failed' ) );
			}

			//Check for media upload permission
			if ( ! current_user_can( 'upload_files' ) ) {
				wp_die( __( "You don't have permission to work with uploaded files.", WP_SMUSH_DOMAIN ) );
			}

			if ( empty( $pid ) ) {
				wp_die( __( 'No attachment ID was provided.', WP_SMUSH_DOMAIN ) );
			}

			//Get metadata For the image
			// Registry Object for NextGen Gallery
			$registry = C_Component_Registry::get_instance();

			//Gallery Storage Object
			$storage = $registry->get_utility( 'I_Gallery_Storage' );

			$image = $storage->object->_image_mapper->find( $pid );

			$this->smush_image( $pid, $storage, $image );

		}

		/**
		 * Process auto smush request for nextgen gallery images
		 *
		 * @param $image
		 */
		function auto_smush( $image ) {

			// creating the 'registry' object for working with nextgen
			$registry = C_Component_Registry::get_instance();
			// creating a database storage object from the 'registry' object
			$storage = $registry->get_utility( 'I_Gallery_Storage' );

			$image_id = $storage->object->_get_image_id( $image );

			$this->smush_image( $image_id, $storage, $image, false );

		}

	}//End of Class

}//End Of if class not exists