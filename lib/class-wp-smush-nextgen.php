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

		var $exceeding_items_count = 0;

		var $remaining_count = 0;

		function __construct() {

			//Update the number of columns
			add_filter( 'ngg_manage_images_number_of_columns', array(
				&$this,
				'wp_smush_manage_images_number_of_columns'
			) );

			//Auto Smush image, if enabled, runs after Nextgen is finished uploading the image
			//Check if auto is enabled
			$auto_smush = get_option( WP_SMUSH_PREFIX . 'auto' );

			//Keep the auto smush on by default
			if ( $auto_smush === false ) {
				$auto_smush = 1;
			}

			if ( $auto_smush ) {
				add_action( 'ngg_added_new_image', array( &$this, 'auto_smush' ) );
			}

			//Add a bulk smush option for NextGen gallery
			add_action( 'admin_menu', array( &$this, 'wp_smush_bulk_menu' ) );

			//Handle Manual Smush request for Nextgen gallery images
			add_action( 'wp_ajax_smush_manual_nextgen', array( $this, 'smush_manual_nextgen' ) );
		}

		/**
		 * Returns the status of NextGen Plugin
		 * We might have to extend the function in future
		 * @return bool
		 */
		private function is_plugin_active() {
			return is_plugin_active( 'nextgen-gallery/nggallery.php' );
		}

		/**
		 * Add a WP Smush page for bulk smush and settings related to Nextgen gallery
		 */
		function wp_smush_bulk_menu() {
			if ( defined( 'NGGFOLDER' ) ) {
				add_submenu_page( NGGFOLDER, esc_html__( 'WP Bulk Smush', WP_SMUSH_DOMAIN ), esc_html__( 'WP Smush', WP_SMUSH_DOMAIN ), 'NextGEN Manage gallery', 'wp-smush-nextgen-bulk', array(
					&$this,
					'wp_smush_bulk'
				) );
			}
		}

		/**
		 * Returns a column name for WP Smush
		 *
		 * @param $columns
		 *
		 * @return mixed
		 */
		function wp_smush_image_column_name( $columns ) {
			//Latest next gen takes string, while the earlier WP Smush plugin shows there use to be a array
			if ( is_array( $columns ) ) {
				$columns['wp_smush_image'] = esc_html__( 'WP Smush', WP_SMUSH_DOMAIN );
			} else {
				$columns = esc_html__( 'WP Smush', WP_SMUSH_DOMAIN );
			}

			return $columns;
		}

		/**
		 * Returns Smush option / Stats, depending if image is already smushed or not
		 *
		 * @param $column_name
		 * @param $id
		 */
		function wp_smush_column_options( $column_name, $id ) {

			//NExtGen Doesn't returns Column name, weird? yeah, right, it is proper because hook is called for the particular column
			if ( $column_name == 'wp_smush_image' || $column_name == '' ) {
				$supported_image = array( 'image/jpeg', 'image/gif', 'image/png', 'image/jpg' );

				// Registry Object for NextGen Gallery
				$registry = C_Component_Registry::get_instance();

				//Gallery Storage Object
				$storage = $registry->get_utility( 'I_Gallery_Storage' );

				//We'll get the image object in $id itself, else fetch it using Gallery Storage
				if ( is_object( $id ) ) {
					$image = $id;
				} else {
					// get an image object
					$image = $storage->object->_image_mapper->find( $id );
				}

				//Check if it is supported image format, get image type to do that
				// get the absolute path
				$file_path = $storage->get_image_abspath( $image, 'full' );

				//Get image type from file path
				$image_type = $this->get_file_type( $file_path );

				//If image type not supported
				if ( ! in_array( $image_type, $supported_image ) ) {
					return;
				}

				//Check Image metadata, if smushed, print the stats or super smush button
				if ( ! empty( $image->meta_data['wp_smush'] ) ) {
					//Echo the smush stats
					$this->show_stats( $image->pid, $image->meta_data['wp_smush'], $image_type, false, true );

					return;
				}

				//Print the status of image, if Not smushed
				echo $this->set_status( $image->pid, false, false );

			}
		}

		/**
		 * Bulk Smush Page
		 */
		function wp_smush_bulk() {

			global $wpsmushit_admin;
			?>
			<div class="wrap">
				<div id="icon-upload" class="icon32"></div><?php
				//Promotional Text
				$wpsmushit_admin->smush_promo_content();

				//Bulk Smush UI, calls progress UI, Super Smush UI
				$this->bulk_smush_ui(); ?>
			</div><?php

			return;
		}

		/**
		 * Increase the count of columns for Nextgen Gallery Manage page
		 *
		 * @param $count
		 *
		 * @return mixed
		 */
		function wp_smush_manage_images_number_of_columns( $count ) {
			$count ++;

			//Add column Heading
			add_filter( "ngg_manage_images_column_{$count}_header", array( &$this, 'wp_smush_image_column_name' ) );

			//Add Column data
			add_filter( "ngg_manage_images_column_{$count}_content", array(
				&$this,
				'wp_smush_column_options'
			), 10, 2 );

			return $count;
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

		function show_stats( $pid, $wp_smush_data = false, $image_type = '', $text_only = false, $echo = true ) {
			global $WpSmush;
			if ( empty( $wp_smush_data ) ) {
				return false;
			}
			$button_txt  = '';
			$show_button = false;

			$bytes          = isset( $wp_smush_data['stats']['bytes'] ) ? $wp_smush_data['stats']['bytes'] : 0;
			$bytes_readable = ! empty( $bytes ) ? $WpSmush->format_bytes( $bytes ) : '';
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
				$lossy    = ! empty( $wp_smush_data['stats']['lossy'] ) ? $wp_smush_data['stats']['lossy'] : '';
				$is_lossy = $lossy == 1 ? true : false;
			}

			//Check if Lossy enabled
			$opt_lossy     = WP_SMUSH_PREFIX . 'lossy';
			$opt_lossy_val = get_option( $opt_lossy, false );

			//Check if premium user, compression was lossless, and lossy compression is enabled
			if ( $WpSmush->is_pro() && ! $is_lossy && $opt_lossy_val && $image_type != 'image/gif' && ! empty( $image_type ) ) {
				// the button text
				$button_txt  = __( 'Super-Smush', WP_SMUSH_DOMAIN );
				$show_button = true;
			}
			if ( $text_only ) {
				return $status_txt;
			}

			//If show button is true for some reason, column html can print out the button for us
			$text = $this->column_html( $pid, $status_txt, $button_txt, $show_button, true, $echo );
			if ( ! $echo ) {
				return $text;
			}
		}
		//@todo: Two functions below are being repeated, see if they can be merged
		/**
		 * Set send button status
		 *
		 * @param $id
		 * @param bool $echo
		 * @param bool $text_only
		 *
		 * @return string|void
		 */
		function set_status( $pid, $echo = true, $text_only = false ) {
			$button_txt = '';

			// the status
			$status_txt = __( 'Not processed', WP_SMUSH_DOMAIN );

			// we need to show the smush button
			$show_button = true;

			// the button text
			$button_txt = __( 'Smush Now!', WP_SMUSH_DOMAIN );
			if ( $text_only ) {
				return $status_txt;
			}

			$text = $this->column_html( $pid, $status_txt, $button_txt, $show_button, false, $echo );
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
		function column_html( $pid, $status_txt = "", $button_txt = "", $show_button = true, $smushed = false, $echo = true ) {

			$class = $smushed ? '' : ' hidden';
			$html  = '<p class="smush-status' . $class . '">' . $status_txt . '</p>';
			$html .= wp_nonce_field( 'wp_smush_nextgen_' . $pid, '_wp_smush_nonce', '', false );
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
				<button  class="button button-primary wp-smush-nextgen-send" data-id="' . $pid . '">
	                <span>' . $button_txt . '</span>
				</button>';
				if ( ! $smushed ) {
					$class = ' unsmushed';
				} else {
					$class = ' smushed';
				}

				return '<div class="smush-wrap' . $class . '">' . $html . '</div>';
			} else {
				$html .= '<button class="button wp-smush-nextgen-send" data-id="' . $pid . '">
                    <span>' . $button_txt . '</span>
				</button>';
				echo $html;
			}
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
					$response = $WpSmush->do_smushit( $attachment_file_path_size );

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

				$full_image_response = $WpSmush->do_smushit( $attachment_file_path );

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
			$metadata = ! empty( $image ) ? $image->meta_data : '';

			if ( empty( $metadata ) ) {
				wp_send_json_error( array( 'error' => "missing_metadata" ) );
			}

			//smush the main image and its sizes
			$smush = $this->resize_from_meta_data( $storage, $image, $pid );

			$status = $this->show_stats( $pid, $smush, false, true );

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
		function smush_manual_nextgen() {
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

		/**
		 * Returns number of images of larger than 1Mb size
		 *
		 * @return int
		 */
		function get_exceeding_items_count() {
			$count       = 0;
			$bulk        = new WpSmushitBulk();
			$attachments = $bulk->get_attachments();
			//Check images bigger than 1Mb, used to display the count of images that can't be smushed
			foreach ( $attachments as $attachment ) {
				if ( file_exists( get_attached_file( $attachment ) ) ) {
					$size = filesize( get_attached_file( $attachment ) );
				}
				if ( empty( $size ) || ! ( ( $size / WP_SMUSH_MAX_BYTES ) > 1 ) ) {
					continue;
				}
				$count ++;
			}

			return $count;
		}

		/**
		 *
		 */
		function bulk_smush_ui() {
			$exceed_mb = '';
			if ( ! $this->is_pro() ) {
				//@todo: Get exceeding items count for free version

				if ( $this->exceeding_items_count && $this->exceeding_items_count !== 0 ) {
					$exceed_mb = sprintf(
						_n( "%d image is over 1MB so will be skipped using the free version of the plugin.",
							"%d images are over 1MB so will be skipped using the free version of the plugin.", $this->exceeding_items_count, WP_SMUSH_DOMAIN ),
						$this->exceeding_items_count
					);
				}
			}
			?>
			<div class="bulk-smush">
				<h3><?php _e( 'Smush in Bulk', WP_SMUSH_DOMAIN ) ?></h3>
				<?php

			if ( $this->remaining_count == 0 ) {
				?>
				<p><?php _e( "Congratulations, all your images are currently Smushed!", WP_SMUSH_DOMAIN ); ?></p>
<?php
//				$this->progress_ui();

				//Display Super smush bulk progress bar
//				$this->super_smush_bulk_ui();
			} else {
				?>
				<div class="smush-instructions">
					<h4 class="smush-remaining-images-notice"><?php printf( _n( "%d attachment in your media library has not been smushed.", "%d image attachments in your media library have not been smushed yet.", $this->remaining_count, WP_SMUSH_DOMAIN ), $this->remaining_count ); ?></h4>
					<?php if ( $exceed_mb ) { ?>
						<p class="error">
							<?php echo $exceed_mb; ?>
							<a href="<?php echo $this->upgrade_url; ?>"><?php _e( 'Remove size limit &raquo;', WP_SMUSH_DOMAIN ); ?></a>
						</p>

					<?php } ?>

					<p><?php _e( "Please be aware, smushing a large number of images can take a while depending on your server and network speed.
						<strong>You must keep this page open while the bulk smush is processing</strong>, but you can leave at any time and come back to continue where it left off.", WP_SMUSH_DOMAIN ); ?></p>

					<?php if ( ! $this->is_pro() ) { ?>
						<p class="error">
							<?php printf( __( "Free accounts are limited to bulk smushing %d attachments per request. You will need to click to start a new bulk job after each %d attachments.", WP_SMUSH_DOMAIN ), $this->max_free_bulk, $this->max_free_bulk ); ?>
							<a href="<?php echo $this->upgrade_url; ?>"><?php _e( 'Remove limits &raquo;', WP_SMUSH_DOMAIN ); ?></a>
						</p>
					<?php } ?>


				</div>

				<!-- Bulk Smushing -->
				<?php wp_nonce_field( 'wp-smush-bulk', '_wpnonce' ); ?>
				<br/><?php
//				$this->progress_ui();
				?>
				<p class="smush-final-log"></p>
<?php
//				$this->setup_button();
			}

			$auto_smush = get_site_option( WP_SMUSH_PREFIX . 'auto' );
			if ( ! $auto_smush && $this->remaining_count == 0 ) {
				?>
				<p><?php printf( __( 'When you <a href="%s">upload some images</a> they will be available to smush here.', WP_SMUSH_DOMAIN ), admin_url( 'media-new.php' ) ); ?></p>
			<?php
			} else { ?>
				<p>
				<?php
				// let the user know that there's an alternative
				printf( __( 'You can also smush images individually from your <a href="%s">Media Library</a>.', WP_SMUSH_DOMAIN ), admin_url( 'upload.php' ) );
				?>
				</p><?php
			}
			?>
			</div><?php
		}

	}//End of Class

}//End Of if class not exists