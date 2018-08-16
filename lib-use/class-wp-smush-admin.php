<?php
/**
 * Smush admin functionality: WpSmushitAdmin class
 *
 * @package WP_Smush
 * @subpackage Admin
 * @version 1.0
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2016, Incsub (http://incsub.com)
 */

if ( ! class_exists( 'WpSmushitAdmin' ) ) {
	/**
	 * Class WpSmushitAdmin
	 *
	 * @property int $remaining_count
	 * @property int $total_count
	 * @property int $smushed_count
	 */
	class WpSmushitAdmin extends WP_Smush_Main {
		public $bulk;

		/**
		 * Stores the headers returned by the latest API call.
		 *
		 * @var string $api_headers
		 */
		public $api_headers = array();

		/**
		 * WpSmushitAdmin constructor.
		 */
		public function __construct() {

			// Hook custom screen.
			add_action( 'admin_menu', array( $this, 'screen' ) );

			// Network Settings Page.
			add_action( 'network_admin_menu', array( $this, 'screen' ) );

			// Ignore image from bulk Smush.
			add_action( 'wp_ajax_ignore_bulk_image', array( $this, 'ignore_bulk_image' ) );

			// Handle Smush Bulk Ajax.
			add_action( 'wp_ajax_wp_smushit_bulk', array( $this, 'process_smush_request' ) );

			// Handle Smush Single Ajax.
			add_action( 'wp_ajax_wp_smushit_manual', array( $this, 'smush_manual' ) );

			// Handle resmush operation.
			add_action( 'wp_ajax_smush_resmush_image', array( $this, 'resmush_image' ) );

			// Scan images as per the latest settings.
			add_action( 'wp_ajax_scan_for_resmush', array( $this, 'scan_images' ) );





			// Handle the smush pro dismiss features notice ajax.
			add_action( 'wp_ajax_dismiss_upgrade_notice', array( $this, 'dismiss_upgrade_notice' ) );

			// Handle the smush pro dismiss features notice ajax.
			add_action( 'wp_ajax_dismiss_welcome_notice', array( $this, 'dismiss_welcome_notice' ) );

			// Handle the smush pro dismiss features notice ajax.
			add_action( 'wp_ajax_dismiss_update_info', array( $this, 'dismiss_update_info' ) );

			// Handle ajax request to dismiss the s3 warning.
			add_action( 'wp_ajax_dismiss_s3support_alert', array( $this, 'dismiss_s3support_alert' ) );

			// Update the Super Smush count, after the smushing.
			add_action( 'wp_smush_image_optimised', array( $this, 'update_lists' ), '', 2 );

			// Delete ReSmush list.
			add_action( 'wp_ajax_delete_resmush_list', array( $this, 'delete_resmush_list' ), '', 2 );



			/**
			 * Prints a membership validation issue notice in Media Library
			 */
			add_action( 'admin_notices', array( $this, 'media_library_membership_notice' ) );

			/**
			 * Hide Pagespeed Suggestion
			 */
			add_action( 'wp_ajax_hide_pagespeed_suggestion', array( $this, 'hide_pagespeed_suggestion' ) );

			/**
			 * Hide API Message
			 */
			add_action( 'wp_ajax_hide_api_message', array( $this, 'hide_api_message' ) );

			add_filter( 'wp_prepare_attachment_for_js', array( $this, 'smush_send_status' ), 99, 3 );

			// Send smush stats.
			add_action( 'wp_ajax_get_stats', array( $this, 'get_stats' ) );

			// Load js and css on pages with Media Uploader - WP Enqueue Media.
			add_action( 'wp_enqueue_media', array( $this, 'enqueue' ) );

			// Admin pointer for new Smush installation.
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_pointer' ) );

			// Smush image filter from Media Library.
			add_filter( 'ajax_query_attachments_args', array( $this, 'filter_media_query' ) );
		}



		/**
		 * Add Bulk option settings page.
		 */
		function screen() {
			global $wpsmush_bulkui;

			$cap   = is_multisite() ? 'manage_network_options' : 'manage_options';
			$title = $this->validate_install() ? esc_html__( 'Smush Pro', 'wp-smushit' ) : esc_html__( 'Smush', 'wp-smushit' );
			add_menu_page(
				$title, $title, $cap, 'smush', array(
					$wpsmush_bulkui,
					'ui',
				), $this->get_menu_icon()
			);

			// For Nextgen gallery Pages, check later in enqueue function.
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		}

		/**
		 * Bulk Smushing Handler.
		 *
		 * Processes the Smush request and sends back the next id for smushing.
		 */
		public function process_smush_request() {
			global $wp_smush, $wpsmush_helper;

			// Turn off errors for ajax result.
			@error_reporting( 0 );

			if ( empty( $_REQUEST['attachment_id'] ) ) {
				wp_send_json_error(
					array(
						'error'         => 'missing_id',
						'error_message' => $this->filter_error( esc_html__( 'No attachment ID was received', 'wp-smushit' ) ),
						'file_name'     => 'undefined',
						'show_warning'  => intval( $this->show_warning() ),
					)
				);
			}

			// If the bulk smush needs to be stopped.
			if ( ! $this->validate_install() && ! $this->check_bulk_limit() ) {
				wp_send_json_error(
					array(
						'error'         => 'limit_exceeded',
						'error_message' => sprintf( esc_html__( "You've reached the %1\$d attachment limit for bulk smushing in the free version. Upgrade to Pro to smush unlimited images, or click resume to smush another %2\$d attachments.", 'wp-smushit' ), $this->max_free_bulk, $this->max_free_bulk ),
						'continue'      => false,
					)
				);
			}

			$attachment_id = (int) $_REQUEST['attachment_id'];
			$original_meta = wp_get_attachment_metadata( $attachment_id, true );

			// Try to get the file name from path.
			$file_name = explode( '/', $original_meta['file'] );

			if ( is_array( $file_name ) ) {
				$file_name = array_pop( $file_name );
			} else {
				$file_name = $original_meta['file'];
			}

			/**
			 * Filter: wp_smush_image
			 *
			 * Whether to smush the given attachment id or not
			 *
			 * @param bool $skip           Whether to Smush image or not.
			 * @param int  $attachment_id  Attachment ID of the image being processed.
			 */
			if ( ! apply_filters( 'wp_smush_image', true, $attachment_id ) ) {
				wp_send_json_error(
					array(
						'error'         => 'skipped',
						'error_message' => $this->filter_error( esc_html__( 'Skipped with wp_smush_image filter', 'wp-smushit' ) ),
						'show_warning'  => intval( $this->show_warning() ),
						'file_name'     => WP_Smush_Helper::get_image_media_link( $attachment_id, $file_name ),
						'thumbnail'     => wp_get_attachment_image( $attachment_id ),
					)
				);
			}

			/**
			 * Get the file path for backup.
			 *
			 * @var WpSmushHelper $wpsmush_helper
			 */
			$attachment_file_path = WP_Smush_Helper::get_attached_file( $attachment_id );

			// Download if not exists.
			do_action( 'smush_file_exists', $attachment_file_path, $attachment_id );

			WP_Smush::get_instance()->core()->backup->create_backup( $attachment_file_path, '', $attachment_id );

			// Proceed only if Smushing Transient is not set for the given attachment id.
			if ( ! get_option( 'smush-in-progress-' . $attachment_id, false ) ) {
				// Set a transient to avoid multiple request.
				update_option( 'smush-in-progress-' . $attachment_id, true );

				/**
				 * Resize the dimensions of the image.
				 *
				 * Filter whether the existing image should be resized or not
				 *
				 * @since 2.3
				 *
				 * @param bool $should_resize Set to True by default.
				 * @param int  $attachment_id Image Attachment ID.
				 */
				if ( $should_resize = apply_filters( 'wp_smush_resize_media_image', true, $attachment_id ) ) {
					$updated_meta  = $this->resize_image( $attachment_id, $original_meta );
					$original_meta = ! empty( $updated_meta ) ? $updated_meta : $original_meta;
				}

				$original_meta = WP_Smush::get_instance()->core()->png2jpg->png_to_jpg( $attachment_id, $original_meta );

				$smush = $wp_smush->resize_from_meta_data( $original_meta, $attachment_id );
				wp_update_attachment_metadata( $attachment_id, $original_meta );
			}

			// Delete transient.
			delete_option( 'smush-in-progress-' . $attachment_id );

			$smush_data         = get_post_meta( $attachment_id, $this->smushed_meta_key, true );
			$resize_savings     = get_post_meta( $attachment_id, WP_SMUSH_PREFIX . 'resize_savings', true );
			$conversion_savings = WP_Smush_Helper::get_pngjpg_savings( $attachment_id );

			$stats = array(
				'count'              => ! empty( $smush_data['sizes'] ) ? count( $smush_data['sizes'] ) : 0,
				'size_before'        => ! empty( $smush_data['stats'] ) ? $smush_data['stats']['size_before'] : 0,
				'size_after'         => ! empty( $smush_data['stats'] ) ? $smush_data['stats']['size_after'] : 0,
				'savings_resize'     => $resize_savings > 0 ? $resize_savings : 0,
				'savings_conversion' => $conversion_savings['bytes'] > 0 ? $conversion_savings : 0,
				'is_lossy'           => ! empty( $smush_data ['stats'] ) ? $smush_data['stats']['lossy'] : false,
			);

			if ( isset( $smush ) && is_wp_error( $smush ) ) {
				$error_message = $smush->get_error_message();

				// Check for timeout error and suggest to filter timeout.
				if ( strpos( $error_message, 'timed out' ) ) {
					$error         = 'timeout';
					$error_message = esc_html__( "Timeout error. You can increase the request timeout to make sure Smush has enough time to process larger files. `define('WP_SMUSH_API_TIMEOUT', 150);`", 'wp-smushit' );
				}

				$error = isset( $error ) ? $error : 'other';

				if ( ! empty( $error_message ) ) {
					// Used internally to modify the error message.
					$error_message = $this->filter_error( $error_message, $attachment_id, $error );
				}

				wp_send_json_error(
					array(
						'stats'         => $stats,
						'error'         => $error,
						'error_message' => $error_message,
						'show_warning'  => intval( $this->show_warning() ),
						'error_class'   => isset( $error_class ) ? $error_class : '',
						'file_name'     => WP_Smush_Helper::get_image_media_link( $attachment_id, $file_name ),
					)
				);
			}

			// Check if a resmush request, update the resmush list.
			if ( ! empty( $_REQUEST['is_bulk_resmush'] ) && 'false' !== $_REQUEST['is_bulk_resmush'] && $_REQUEST['is_bulk_resmush'] ) {
				$this->update_resmush_list( $attachment_id );
			}

			// Runs after a image is successfully smushed.
			do_action( 'image_smushed', $attachment_id, $stats );

			// Update the bulk Limit count.
			$this->update_smush_count();

			// Send ajax response.
			wp_send_json_success(
				array(
					'stats'        => $stats,
					'show_warning' => intval( $this->show_warning() ),
				)
			);
		}

		/**
		 * Handle the Ajax request for smushing single image
		 *
		 * @uses smush_single()
		 */
		public function smush_manual() {
			// Turn off errors for ajax result.
			@error_reporting( 0 );

			if ( ! current_user_can( 'upload_files' ) ) {
				wp_die( esc_html__( "You don't have permission to work with uploaded files.", 'wp-smushit' ) );
			}

			if ( ! isset( $_GET['attachment_id'] ) ) {
				wp_die( esc_html__( 'No attachment ID was provided.', 'wp-smushit' ) );
			}

			$attachment_id = intval( $_GET['attachment_id'] );

			/**
			 * Filter: wp_smush_image.
			 *
			 * Whether to smush the given attachment ID or not.
			 *
			 * @param bool $status         Smush all attachments by default.
			 * @param int  $attachment_id  Attachment ID.
			 */
			if ( ! apply_filters( 'wp_smush_image', true, $attachment_id ) ) {
				$error = $this->filter_error( esc_html__( 'Attachment Skipped - Check `wp_smush_image` filter.', 'wp-smushit' ), $attachment_id );
				wp_send_json_error(
					array(
						'error_msg'    => sprintf( '<p class="wp-smush-error-message">%s</p>', $error ),
						'show_warning' => intval( $this->show_warning() ),
					)
				);
			}

			$this->initialise();
			// Pass on the attachment id to smush single function.
			$this->smush_single( $attachment_id );
		}

		/**
		 * Smush single images
		 *
		 * @param int  $attachment_id  Attachment ID.
		 * @param bool $return         Return/echo the stats.
		 *
		 * @return array|string
		 */
		public function smush_single( $attachment_id, $return = false ) {
			// If the smushing option is already set, return the status.
			if ( get_option( "smush-in-progress-{$attachment_id}", false ) || get_option( "wp-smush-restore-{$attachment_id}", false ) ) {
				// Get the button status.
				$status = $this->set_status( $attachment_id, false, true );
				if ( $return ) {
					return $status;
				}

				wp_send_json_success( $status );
			}

			// Set a transient to avoid multiple request.
			update_option( "smush-in-progress-{$attachment_id}", true );

			$attachment_id = absint( (int) ( $attachment_id ) );

			// Get the file path for backup.
			$attachment_file_path = WP_Smush_Helper::get_attached_file( $attachment_id );

			// Download file if not exists.
			do_action( 'smush_file_exists', $attachment_file_path, $attachment_id );

			// Take backup.
			WP_Smush::get_instance()->core()->backup->create_backup( $attachment_file_path, '', $attachment_id );

			// Get the image metadata from $_POST.
			$original_meta = ! empty( $_POST['metadata'] ) ? $this->format_meta_from_post( $_POST['metadata'] ) : '';

			$original_meta = empty( $original_meta ) ? wp_get_attachment_metadata( $attachment_id ) : $original_meta;

			// Send image for resizing, if enabled resize first before any other operation.
			$updated_meta = $this->resize_image( $attachment_id, $original_meta );

			// Convert PNGs to JPG.
			$updated_meta = WP_Smush::get_instance()->core()->png2jpg->png_to_jpg( $attachment_id, $updated_meta );

			$original_meta = ! empty( $updated_meta ) ? $updated_meta : $original_meta;

			// Smush the image.
			$smush = $this->resize_from_meta_data( $original_meta, $attachment_id );

			// Update the details, after smushing, so that latest image is used in hook.
			wp_update_attachment_metadata( $attachment_id, $original_meta );

			// Get the button status.
			$status = $this->set_status( $attachment_id, false, true );

			// Delete the transient after attachment meta is updated.
			delete_option( 'smush-in-progress-' . $attachment_id );

			// Send Json response if we are not suppose to return the results.
			if ( is_wp_error( $smush ) ) {
				if ( $return ) {
					return array( 'error' => $smush->get_error_message() );
				}

				wp_send_json_error(
					array(
						'error_msg'    => '<p class="wp-smush-error-message">' . $smush->get_error_message() . '</p>',
						'show_warning' => intval( $this->show_warning() ),
					)
				);
			}

			$this->update_resmush_list( $attachment_id );
			if ( $return ) {
				return $status;
			}

			wp_send_json_success( $status );
		}

		/**
		 * Format meta data from $_POST request.
		 *
		 * Post request in WordPress will convert all values
		 * to string. Make sure image height and width are int.
		 * This is required only when Async requests are used.
		 * See - https://wordpress.org/support/topic/smushit-overwrites-image-meta-crop-sizes-as-string-instead-of-int/
		 *
		 * @since 2.8.0
		 *
		 * @param array $meta Meta data of attachment.
		 *
		 * @return array
		 */
		public function format_meta_from_post( $meta = array() ) {

			// Do not continue in case meta is empty.
			if ( empty( $meta ) ) {
				return $meta;
			}

			// If meta data is array proceed.
			if ( is_array( $meta ) ) {

				// Walk through each items and format.
				array_walk_recursive( $meta, array( $this, 'format_attachment_meta_item' ) );
			}

			return $meta;
		}

		/**
		 * If current item is width or height, make sure it is int.
		 *
		 * @since 2.8.0
		 *
		 * @param mixed  $value Meta item value.
		 * @param string $key Meta item key.
		 */
		public function format_attachment_meta_item( &$value, $key ) {

			if ( 'height' === $key || 'width' === $key ) {
				$value = (int) $value;
			}

			/**
			 * Allows to format single item in meta.
			 *
			 * This filter will be used only for Async, post requests.
			 *
			 * @param mixed $value Meta item value.
			 * @param string $key Meta item key.
			 */
			$value = apply_filters( 'wp_smush_format_attachment_meta_item', $value, $key );
		}






		/**
		 * Display Thumbnails, if bulk action is choosen
		 *
		 * @Note: Not in use right now, Will use it in future for Media Bulk action
		 */
		function selected_ui( $send_ids, $received_ids ) {
			if ( empty( $received_ids ) ) {
				return;
			}

			?>
			<div id="select-bulk" class="wp-smush-bulk-wrap">
				<p>
					<?php
					printf(
						__(
							'<strong>%1$d of %2$d images</strong> were sent for smushing:',
							'wp-smushit'
						),
						count( $send_ids ), count( $received_ids )
					);
					?>
				</p>
				<ul id="wp-smush-selected-images">
					<?php
					foreach ( $received_ids as $attachment_id ) {
						$this->attachment_ui( $attachment_id );
					}
					?>
				</ul>
			</div>
			<?php
		}

		/**
		 * Get the smush button text for attachment
		 *
		 * @param $id Attachment ID for which the Status has to be set
		 *
		 * @return string
		 */
		function smush_status( $id ) {
			global $wp_smush;

			// Show Temporary Status, For Async Optimisation, No Good workaround
			if ( ! get_option( "wp-smush-restore-$id", false ) && ! empty( $_POST['action'] ) && 'upload-attachment' == $_POST['action'] && $wp_smush->is_auto_smush_enabled() ) {
				// the status
				$status_txt = '<p class="smush-status">' . __( 'Smushing in progress..', 'wp-smushit' ) . '</p>';

				// we need to show the smush button
				$show_button = false;

				// the button text
				$button_txt = __( 'Smush Now!', 'wp-smushit' );

				return $this->column_html( $id, $status_txt, $button_txt, $show_button, true, false, true );
			}
			// Else Return the normal status
			$response = trim( $this->set_status( $id, false ) );

			return $response;
		}

		/**
		 * Get the smushed attachments from the database, except gif
		 *
		 * @global object $wpdb
		 *
		 * @return object query results
		 */
		function get_smushed_attachments() {

			global $wpdb;

			$allowed_images = "( 'image/jpeg', 'image/jpg', 'image/x-citrix-jpeg', 'image/png', 'image/x-png' )";

			$limit      = $this->query_limit();
			$offset     = 0;
			$query_next = true;

			while ( $query_next ) {
				// get the attachment id, smush data
				$sql = 'SELECT p.ID as attachment_id, p.post_mime_type as type, ms.meta_value as smush_data'
						   . " FROM $wpdb->posts as p"
						   . " LEFT JOIN $wpdb->postmeta as ms"
						   . " ON (p.ID= ms.post_id AND ms.meta_key='wp-smpro-smush-data')"
						   . ' WHERE'
						   . " p.post_type='attachment'"
						   . ' AND p.post_mime_type IN ' . $allowed_images
						   . ' ORDER BY p . ID DESC'
						   // add a limit
						   . ' LIMIT ' . $limit;
				$results = $wpdb->get_results( $sql );

				// Update the offset
				$offset += $limit;
				if ( ! empty( $this->total_count ) && $this->total_count <= $offset ) {
					$query_next = false;
				} elseif ( ! $results || empty( $results ) ) {
					$query_next = false;
				}
			}

			return $results;
		}

		/**
		 * Store a key/value to hide the smush features on bulk page
		 */
		function dismiss_welcome_notice() {
			update_site_option( 'wp-smush-hide_smush_welcome', 1 );
			wp_send_json_success();
		}

		/**
		 * Store a key/value to hide the smush features on bulk page
		 */
		function dismiss_upgrade_notice( $ajax = true ) {
			update_site_option( 'wp-smush-hide_upgrade_notice', 1 );
			// No Need to send json response for other requests
			if ( $ajax ) {
				wp_send_json_success();
			}
		}

		/**
		 * Remove the Update info
		 *
		 * @param bool $remove_notice
		 */
		function dismiss_update_info( $remove_notice = false ) {

			// From URL arg
			if ( isset( $_GET['dismiss_smush_update_info'] ) && 1 == $_GET['dismiss_smush_update_info'] ) {
				$remove_notice = true;
			}

			// From Ajax
			if ( ! empty( $_REQUEST['action'] ) && 'dismiss_update_info' == $_REQUEST['action'] ) {
				$remove_notice = true;
			}

			// Update Db
			if ( $remove_notice ) {
				update_site_option( 'wp-smush-hide_update_info', 1 );
			}

		}

		/**
		 * Hide S3 support alert by setting a flag.
		 */
		function dismiss_s3support_alert() {

			// Just set a flag.
			update_site_option( 'wp-smush-hide_s3support_alert', 1 );

			wp_send_json_success();
		}

		/**
		 * Resmush the image
		 *
		 * @uses smush_single()
		 */
		function resmush_image() {
			// Check empty fields.
			if ( empty( $_POST['attachment_id'] ) || empty( $_POST['_nonce'] ) ) {
				wp_send_json_error(
					array(
						'error'   => 'empty_fields',
						'message' => '<div class="wp-smush-error">' . esc_html__( 'Image not smushed, fields empty.', 'wp-smushit' ) . '</div>',
					)
				);
			}

			// Check nonce.
			if ( ! wp_verify_nonce( $_POST['_nonce'], 'wp-smush-resmush-' . $_POST['attachment_id'] ) ) {
				wp_send_json_error(
					array(
						'error'   => 'empty_fields',
						'message' => '<div class="wp-smush-error">' . esc_html__( "Image couldn't be smushed as the nonce verification failed, try reloading the page.", 'wp-smushit' ) . '</div>',
					)
				);
			}

			$image_id = intval( $_POST['attachment_id'] );

			$smushed = $this->smush_single( $image_id, true );

			// If any of the image is restored, we count it as success.
			if ( ! empty( $smushed['status'] ) ) {
				// Send button content.
				wp_send_json_success( array(
					'button' => $smushed['status'] . $smushed['stats'],
				) );
			}

			// Send error Message.
			if ( ! empty( $smushed['error'] ) ) {
				wp_send_json_error( array(
					'message' => '<div class="wp-smush-error">' . $smushed['error'] . '</div>',
				) );
			}
		}

		/**
		 * Scans all the smushed attachments to check if they need to be resmushed as per the
		 * current settings, as user might have changed one of the configurations "Lossy", "Keep Original", "Preserve Exif"
		 *
		 * @todo: Needs some refactoring big time
		 */
		function scan_images() {
			check_ajax_referer( 'save_wp_smush_options', 'wp_smush_options_nonce' );

			$resmush_list = array();

			// Scanning for NextGen or Media Library
			$type = isset( $_REQUEST['type'] ) ? sanitize_text_field( $_REQUEST['type'] ) : '';

			// Save settings only if networkwide settings are disabled
			if ( ( ! is_multisite() || ! $wpsmush_settings->is_network_enabled() ) && ( ! isset( $_REQUEST['process_settings'] ) || 'false' != $_REQUEST['process_settings'] ) ) {
				// Save Settings
				$wpsmush_settings->process_options();
			}

			// If there aren't any images in the library, return the notice
			if ( 0 == $wpsmush_db->get_media_attachments( true ) && 'nextgen' != $type ) {
				$notice = esc_html__( 'We haven’t found any images in your media library yet so there’s no smushing to be done! Once you upload images, reload this page and start playing!', 'wp-smushit' );
				$resp   = '<div class="sui-notice-top sui-notice-success sui-can-dismiss">
						<div class="sui-notice-content">
							<p>' . $notice . '</p>
						</div>
						<span class="sui-notice-dismiss">
							<a role="button" href="#" aria-label="' . __( 'Dismiss', 'wp-smushit' ) . '" class="sui-icon-check"></a>
						</span>
					</div>';

				delete_site_option( WP_SMUSH_PREFIX . 'run_recheck' );
				wp_send_json_success(
					array(
						'notice'      => $resp,
						'super_smush' => $wp_smush->lossy_enabled,
					)
				);

			}

			// Default Notice, to be displayed at the top of page
			// Show a message, at the top
			$message = esc_html__( 'Yay! All images are optimized as per your current settings.', 'wp-smushit' );
			$resp    = '<div class="sui-notice-top sui-notice-success sui-can-dismiss">
						<div class="sui-notice-content">
							<p>' . $message . '</p>
						</div>
						<span class="sui-notice-dismiss">
							<a role="button" href="#" aria-label="' . __( 'Dismiss', 'wp-smushit' ) . '" class="sui-icon-check"></a>
						</span>
					</div>';

			// If a user manually runs smush check
			$return_ui = isset( $_REQUEST['get_ui'] ) && 'true' == $_REQUEST['get_ui'] ? true : false;

			// Update the variables
			$wp_smush->initialise();

			// Logic: If none of the required settings is on, don't need to resmush any of the images
			// We need at least one of these settings to be on, to check if any of the image needs resmush
			// Allow to smush Upfront images as well
			$upfront_active = class_exists( 'Upfront' );

			// Initialize Media Library Stats
			if ( 'nextgen' != $type && empty( $this->remaining_count ) ) {
				$this->setup_global_stats();
			}

			// Intialize NextGen Stats
			if ( 'nextgen' == $type && is_object( $wpsmushnextgenadmin ) && empty( $wpsmushnextgenadmin->remaining_count ) ) {
				$wpsmushnextgenadmin->setup_image_counts();
			}

			$key = 'nextgen' == $type ? 'wp-smush-nextgen-resmush-list' : 'wp-smush-resmush-list';

			$remaining_count = 'nextgen' == $type ? $wpsmushnextgenadmin->remaining_count : $this->remaining_count;

			if ( 0 == $remaining_count && ! $wp_smush->lossy_enabled && ! $wp_smush->smush_original && $wp_smush->keep_exif && ! $upfront_active ) {
				delete_option( $key );
				delete_site_option( WP_SMUSH_PREFIX . 'run_recheck' );
				wp_send_json_success( array( 'notice' => $resp ) );
			}

			// Set to empty by default
			$ajax_response = '';

			// Get Smushed Attachments
			if ( 'nextgen' != $type ) {

				// Get list of Smushed images
				$attachments = ! empty( $this->smushed_attachments ) ? $this->smushed_attachments : $wpsmush_db->smushed_count( true );
			} else {
				global $wpsmushnextgenstats;

				// Get smushed attachments list from nextgen class, We get the meta as well
				$attachments = $wpsmushnextgenstats->get_ngg_images();

			}

			$image_count = $super_smushed_count = $smushed_count = $resized_count = 0;
			// Check if any of the smushed image needs to be resmushed
			if ( ! empty( $attachments ) && is_array( $attachments ) ) {
				$stats = array(
					'size_before'        => 0,
					'size_after'         => 0,
					'savings_resize'     => 0,
					'savings_conversion' => 0,
				);

				// Initialize resize class.
				WP_Smush::get_instance()->core()->resize->initialize();

				foreach ( $attachments as $attachment_k => $attachment ) {

					// Skip if already in resmush list
					if ( ! empty( $wpsmushit_admin->resmush_ids ) && in_array( $attachment, $wpsmushit_admin->resmush_ids ) ) {
						continue;
					}
					$should_resmush = false;

					// For NextGen we get the metadata in the attachment data itself
					if ( is_array( $attachment ) && ! empty( $attachment['wp_smush'] ) ) {
						$smush_data = $attachment['wp_smush'];
					} else {
						// Check the current settings, and smush data for the image
						$smush_data = get_post_meta( $attachment, $this->smushed_meta_key, true );
					}

					// If the image is already smushed
					if ( is_array( $smush_data ) && ! empty( $smush_data['stats'] ) ) {

						// If we need to optmise losslessly, add to resmush list
						$smush_lossy = $wp_smush->lossy_enabled && ! $smush_data['stats']['lossy'];

						// If we need to strip exif, put it in resmush list
						$strip_exif = ! $wp_smush->keep_exif && isset( $smush_data['stats']['keep_exif'] ) && ( 1 == $smush_data['stats']['keep_exif'] );

						// If Original image needs to be smushed
						$smush_original = $wp_smush->smush_original && empty( $smush_data['sizes']['full'] );

						if ( $smush_lossy || $strip_exif || $smush_original ) {
							$should_resmush = true;
						}

						// If Image needs to be resized
						if ( ! $should_resmush ) {
							$should_resmush = WP_Smush::get_instance()->core()->resize->should_resize( $attachment );
						}

						// If image can be converted
						if ( ! $should_resmush ) {
							$should_resmush = WP_Smush::get_instance()->core()->png2jpg->can_be_converted( $attachment );
						}

						// If the image needs to be resmushed add it to the list
						if ( $should_resmush ) {
							$resmush_list[] = 'nextgen' == $type ? $attachment_k : $attachment;
							continue;
						} else {
							if ( 'nextgen' != $type ) {
								$resize_savings     = get_post_meta( $attachment, WP_SMUSH_PREFIX . 'resize_savings', true );
								$conversion_savings = WP_Smush_Helper::get_pngjpg_savings( $attachment );

								// Increase the smushed count
								$smushed_count++;
								// Get the resized image count
								if ( ! empty( $resize_savings ) ) {
									$resized_count++;
								}

								// Get the image count
								$image_count += ( ! empty( $smush_data['sizes'] ) && is_array( $smush_data['sizes'] ) ) ? sizeof( $smush_data['sizes'] ) : 0;

								// If the image is in resmush list, and it was super smushed earlier
								$super_smushed_count += ( $smush_data['stats']['lossy'] ) ? 1 : 0;

								// Add to the stats
								$stats['size_before'] += ! empty( $smush_data['stats'] ) ? $smush_data['stats']['size_before'] : 0;
								$stats['size_before'] += ! empty( $resize_savings['size_before'] ) ? $resize_savings['size_before'] : 0;
								$stats['size_before'] += ! empty( $conversion_savings['size_before'] ) ? $conversion_savings['size_before'] : 0;

								$stats['size_after'] += ! empty( $smush_data['stats'] ) ? $smush_data['stats']['size_after'] : 0;
								$stats['size_after'] += ! empty( $resize_savings['size_after'] ) ? $resize_savings['size_after'] : 0;
								$stats['size_after'] += ! empty( $conversion_savings['size_after'] ) ? $conversion_savings['size_after'] : 0;

								$stats['savings_resize']     += ! empty( $resize_savings ) ? $resize_savings['bytes'] : 0;
								$stats['savings_conversion'] += ! empty( $conversion_savings ) ? $conversion_savings['bytes'] : 0;
							}
						}
					}
				}// End of Foreach Loop

				// Check for Upfront images that needs to be smushed
				if ( $upfront_active && 'nextgen' != $type ) {
					$resmush_list = $this->get_upfront_resmush_list( $resmush_list );
				}//End Of Upfront loop

				// Store the resmush list in Options table
				update_option( $key, $resmush_list, false );
			}
			// Get updated stats for Nextgen
			if ( 'nextgen' == $type ) {
				// Reinitialize Nextgen stats
				$wpsmushnextgenadmin->setup_image_counts();
				// Image count, Smushed Count, Supersmushed Count, Savings
				$stats               = $wpsmushnextgenstats->get_smush_stats();
				$image_count         = $wpsmushnextgenadmin->image_count;
				$smushed_count       = $wpsmushnextgenadmin->smushed_count;
				$super_smushed_count = $wpsmushnextgenadmin->super_smushed;
			}

			// Delete resmush list if empty
			if ( empty( $resmush_list ) ) {
				// Delete the resmush list
				delete_option( $key );
			}

			$resmush_count = $count = count( $resmush_list );
			$count        += 'nextgen' == $type ? $wpsmushnextgenadmin->remaining_count : $this->remaining_count;

			// Return the Remsmush list and UI to be appended to Bulk Smush UI
			if ( $return_ui ) {
				if ( 'nextgen' != $type ) {
					// Set the variables
					$this->resmush_ids = $resmush_list;

				} else {
					// To avoid the php warning
					$wpsmushnextgenadmin->resmush_ids = $resmush_list;
				}

				if ( $resmush_count ) {
					$ajax_response = $wpsmush_bulkui->bulk_resmush_content( $count, true );
				}
			}

			if ( ! empty( $count ) ) {
				$message = sprintf( esc_html__( 'Image check complete, you have %1$d images that need smushing. %2$sBulk smush now!%3$s', 'wp-smushit' ), $count, '<a href="#" class="wp-smush-trigger-bulk">', '</a>' );
				$resp    = '<div class="sui-notice-top sui-notice-warning sui-can-dismiss">
						<div class="sui-notice-content">
							<p>' . $message . '</p>
						</div>
						<span class="sui-notice-dismiss">
							<a role="button" href="#" aria-label="' . __( 'Dismiss', 'wp-smushit' ) . '" class="sui-icon-check"></a>
						</span>
					</div>';
			}

			// Directory Smush Stats
			// Include directory smush stats if not requested for nextgen
			if ( 'nextgen' != $type ) {
				// Append the directory smush stats
				$dir_smush_stats = get_option( 'dir_smush_stats' );
				if ( ! empty( $dir_smush_stats ) && is_array( $dir_smush_stats ) ) {

					if ( ! empty( $dir_smush_stats['dir_smush'] ) && ! empty( $dir_smush_stats['optimised'] ) ) {
						$dir_smush_stats = $dir_smush_stats['dir_smush'];
						$image_count    += $dir_smush_stats['optimised'];
					}

					// Add directory smush stats if not empty
					if ( ! empty( $dir_smush_stats['image_size'] ) && ! empty( $dir_smush_stats['orig_size'] ) ) {
						$stats['size_before'] += $dir_smush_stats['orig_size'];
						$stats['size_after']  += $dir_smush_stats['image_size'];
					}
				}
			}

			// If there is a Ajax response return it, else return null
			$return = ! empty( $ajax_response ) ? array(
				'resmush_ids'        => $resmush_list,
				'content'            => $ajax_response,
				'count_image'        => $image_count,
				'count_supersmushed' => $super_smushed_count,
				'count_smushed'      => $smushed_count,
				'count_resize'       => $resized_count,
				'size_before'        => $stats['size_before'],
				'size_after'         => $stats['size_after'],
				'savings_resize'     => ! empty( $stats['savings_resize'] ) ? $stats['savings_resize'] : 0,
				'savings_conversion' => ! empty( $stats['savings_conversion'] ) ? $stats['savings_conversion'] : 0,
			) : array();

			// Include the count
			if ( ! empty( $count ) && $count ) {
				$return['count'] = $count;
			}

			$return['notice']      = $resp;
			$return['super_smush'] = $wp_smush->lossy_enabled;
			if ( $wp_smush->lossy_enabled && 'nextgen' == $type ) {
				$ss_count                    = $wpsmush_db->super_smushed_count( 'nextgen', $wpsmushnextgenstats->get_ngg_images( 'smushed' ) );
				$return['super_smush_stats'] = sprintf( '<strong><span class="smushed-count">%d</span>/%d</strong>', $ss_count, $wpsmushnextgenadmin->total_count );
			}

			delete_site_option( WP_SMUSH_PREFIX . 'run_recheck' );
			wp_send_json_success( $return );

		}

		/**
		 * Remove the given attachment id from resmush list and updates it to db
		 *
		 * @param $attachment_id
		 * @param string        $mkey
		 */
		function update_resmush_list( $attachment_id, $mkey = 'wp-smush-resmush-list' ) {
			$resmush_list = get_option( $mkey );

			// If there are any items in the resmush list, Unset the Key
			if ( ! empty( $resmush_list ) && count( $resmush_list ) > 0 ) {
				$key = array_search( $attachment_id, $resmush_list );
				if ( $resmush_list ) {
					unset( $resmush_list[ $key ] );
				}
				$resmush_list = array_values( $resmush_list );
			}

			// If Resmush List is empty
			if ( empty( $resmush_list ) || 0 == count( $resmush_list ) ) {
				// Delete resmush list
				delete_option( $mkey );
			} else {
				update_option( $mkey, $resmush_list, false );
			}
		}

		/**
		 * Format Numbers to short form 1000 -> 1k
		 *
		 * @param $number
		 *
		 * @return string
		 */
		function format_number( $number ) {
			if ( $number >= 1000 ) {
				return $number / 1000 . 'k';   // NB: you will want to round this
			} else {
				return $number;
			}
		}

		/**
		 * Add/Remove image id from Super Smushed images count
		 *
		 * @param int    $id Image id
		 *
		 * @param string $op_type Add/remove, whether to add the image id or remove it from the list
		 *
		 * @return bool Whether the Super Smushed option was update or not
		 */
		function update_super_smush_count( $id, $op_type = 'add', $key = 'wp-smush-super_smushed' ) {

			// Get the existing count
			$super_smushed = get_option( $key, false );

			// Initialize if it doesn't exists
			if ( ! $super_smushed || empty( $super_smushed['ids'] ) ) {
				$super_smushed = array(
					'ids' => array(),
				);
			}

			// Insert the id, if not in there already
			if ( 'add' == $op_type && ! in_array( $id, $super_smushed['ids'] ) ) {

				$super_smushed['ids'][] = $id;

			} elseif ( 'remove' == $op_type && false !== ( $k = array_search( $id, $super_smushed['ids'] ) ) ) {

				// Else remove the id from the list
				unset( $super_smushed['ids'][ $k ] );

				// Reset all the indexes
				$super_smushed['ids'] = array_values( $super_smushed['ids'] );

			}

			// Add the timestamp
			$super_smushed['timestamp'] = current_time( 'timestamp' );

			update_option( $key, $super_smushed, false );

			// Update to database
			return true;
		}

		/**
		 * Checks if the image compression is lossy, stores the image id in options table
		 *
		 * @param int    $id Image Id
		 *
		 * @param array  $stats Compression Stats
		 *
		 * @param string $key Meta Key for storing the Super Smushed ids (Optional for Media Library)
		 *                    Need To be specified for NextGen
		 *
		 * @return bool
		 */
		function update_lists( $id, $stats, $key = '' ) {
			// If Stats are empty or the image id is not provided, return
			if ( empty( $stats ) || empty( $id ) || empty( $stats['stats'] ) ) {
				return false;
			}

			// Update Super Smush count
			if ( isset( $stats['stats']['lossy'] ) && 1 == $stats['stats']['lossy'] ) {
				if ( empty( $key ) ) {
					update_post_meta( $id, 'wp-smush-lossy', 1 );
				} else {
					$this->update_super_smush_count( $id, 'add', $key );
				}
			}

			// Check and update re-smush list for media gallery
			if ( ! empty( $this->resmush_ids ) && in_array( $id, $this->resmush_ids ) ) {
				$this->update_resmush_list( $id );
			}

		}

		/**
		 * Delete the resmush list for Nextgen or the Media Library
		 *
		 * Return Stats in ajax response
		 */
		public function delete_resmush_list() {
			global $wpsmush_db, $wpsmushnextgenstats, $wpsmushnextgenadmin;
			$stats = array();

			$key = ! empty( $_POST['type'] ) && 'nextgen' == $_POST['type'] ? 'wp-smush-nextgen-resmush-list' : 'wp-smush-resmush-list';

			// For media Library.
			if ( 'nextgen' != $_POST['type'] ) {
				$resmush_list = get_option( $key );
				if ( ! empty( $resmush_list ) && is_array( $resmush_list ) ) {
					$stats = $wpsmush_db->get_stats_for_attachments( $resmush_list );
				}
			} else {
				// For Nextgen. Get the stats (get the re-Smush IDs).
				$resmush_ids = get_option( 'wp-smush-nextgen-resmush-list', array() );
				$stats       = $wpsmushnextgenstats->get_stats_for_ids( $resmush_ids );

				$stats['count_images'] = $wpsmushnextgenadmin->get_image_count( $resmush_ids, false );
			}

			// Delete the resmush list.
			delete_option( $key );
			wp_send_json_success( array( 'stats' => $stats ) );
		}

		/**
		 * Allows to bulk restore the images, if there is any backup for them
		 */
		function bulk_restore() {
			$smushed_attachments = ! empty( $this->smushed_attachments ) ? $this->smushed_attachments : $wpsmush_db->smushed_count( true );
			foreach ( $smushed_attachments as $attachment ) {
				WP_Smush::get_instance()->core()->backup->restore_image( $attachment->attachment_id, false );
			}
		}



		/**
		 * Perform the resize operation for the image
		 *
		 * @param $attachment_id
		 *
		 * @param $meta
		 *
		 * @return mixed
		 */
		function resize_image( $attachment_id, $meta ) {
			if ( empty( $attachment_id ) || empty( $meta ) ) {
				return $meta;
			}

			return WP_Smush::get_instance()->core()->resize->auto_resize( $attachment_id, $meta );
		}

		/**
		 * Show Update info in admin Notice
		 */
		function smush_updated() {
			// @todo: Update Smush Update Notice for next release
			// Make sure to not display this message for next release
			$plugin_data = get_plugin_data( WP_SMUSH_DIR . 'wp-smush.php', false, false );
			$version     = ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : '';

			// If Versions Do not match
			if ( empty( $version ) || $version != WP_SMUSH_VERSION ) {
				return true;
			}

			// Do not display it for other users
			if ( ! is_super_admin() || ! current_user_can( 'manage_options' ) ) {
				return true;
			}

			// If dismissed, Delete the option on Plugin Activation, For alter releases
			if ( 1 == get_site_option( 'wp-smush-hide_update_info' ) ) {
				return true;
			}

			// Get Plugin dir, Return if it's WP Smush Pro installation
			if ( ! defined( 'WP_SMUSH_DIR' ) && strpos( WP_SMUSH_DIR, 'wp-smush-pro' ) !== false ) {
				return true;
			}

			// Do not display the notice on Bulk Smush Screen
			global $current_screen;
			if ( ! empty( $current_screen->base ) && ( 'toplevel_page_smush' == $current_screen->base || 'toplevel_page_smush-network' == $current_screen->base || 'gallery_page_wp-smush-nextgen-bulk' == $current_screen->base || 'toplevel_page_smush-network' == $current_screen->base ) ) {
				return true;
			}

			$upgrade_url   = add_query_arg(
				array(
					'utm_source'   => 'smush',
					'utm_medium'   => 'plugin',
					'utm_campaign' => 'smush_async_upgrade_notice',
				),
				$this->upgrade_url
			);
			$settings_link = is_multisite() && is_network_admin() ? network_admin_url( 'admin.php?page=smush' ) : menu_page_url( 'smush', false );

			$settings_link = '<a href="' . $settings_link . '" title="' . esc_html__( 'Review your setting now.', 'wp-smushit' ) . '">';
			$upgrade_link  = '<a href="' . esc_url( $upgrade_url ) . '" title="' . esc_html__( 'Smush Pro', 'wp-smushit' ) . '">';
			$message_s     = sprintf( esc_html__( "Welcome to the newest version of Smush! In this update we've added the ability to bulk smush images in directories outside your uploads folder.", 'wp-smushit' ), WP_SMUSH_VERSION, '<strong>', '</strong>' );

			// Message for network admin
			$message_s .= is_multisite() ? sprintf( esc_html__( ' And as a multisite user, you can manage %1$sSmush settings%2$s globally across all sites!', 'wp-smushit' ), $settings_link, '</a>' ) : '';

			// Upgrade link for free users
			$message_s .= ! $this->validate_install() ? sprintf( esc_html__( ' %1$sFind out more here >>%2$s', 'wp-smushit' ), $upgrade_link, '</a>' ) : '';
			?>
			<div class="notice notice-info is-dismissible wp-smush-update-info">
			<p><?php echo $message_s; ?></p>
			</div>
			<?php
		}

		/**
		 * Check whether to skip a specific image size or not
		 *
		 * @param string $size Registered image size
		 *
		 * @return bool true/false Whether to skip the image size or not
		 */
		function skip_image_size( $size = '' ) {
			global $wpsmush_settings;

			// No image size specified, Don't skip
			if ( empty( $size ) ) {
				return false;
			}

			$image_sizes = $wpsmush_settings->get_setting( WP_SMUSH_PREFIX . 'image_sizes' );

			// If Images sizes aren't set, don't skip any of the image size
			if ( false === $image_sizes ) {
				return false;
			}

			// Check if the size is in the smush list
			if ( is_array( $image_sizes ) && ! in_array( $size, $image_sizes ) ) {
				return true;
			}

		}

		/**
		 * Prints the Membership Validation issue notice
		 */
		function media_library_membership_notice() {
			global $wpsmush_bulkui;

			// No need to print it for free version
			if ( ! $this->validate_install() ) {
				return;
			}
			// Show it on Media Library page only
			$screen    = get_current_screen();
			$screen_id = ! empty( $screen ) ? $screen->id : '';
			// Do not show notice anywhere else
			if ( empty( $screen ) || 'upload' != $screen_id ) {
				return;
			}

			echo $wpsmush_bulkui->get_user_validation_message( false );
		}

		/**
		 * Allows to filter the error message sent to the user
		 *
		 * @param string $error
		 * @param string $attachment_id
		 *
		 * @return mixed|null|string|void
		 */
		function filter_error( $error = '', $attachment_id = '' ) {
			if ( empty( $error ) ) {
				return null;
			}
			/**
			 * Used internally to modify the error message
			 */
			$error = apply_filters( 'wp_smush_error', $error, $attachment_id );

			return $error;
		}

		/**
		 * Store user preference for Pagespeed suggestions
		 */
		function hide_pagespeed_suggestion() {
			update_site_option( WP_SMUSH_PREFIX . 'hide_pagespeed_suggestion', true );
			wp_send_json_success();
		}

		/**
		 * Hide API Message
		 */
		function hide_api_message() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$api_message = get_site_option( WP_SMUSH_PREFIX . 'api_message', array() );
			if ( ! empty( $api_message ) && is_array( $api_message ) ) {
				$api_message[ key( $api_message ) ]['status'] = 'hide';
			}
			update_site_option( WP_SMUSH_PREFIX . 'api_message', true );
			wp_send_json_success();
		}

		/**
		 * Load media assets.
		 */
		public function extend_media_modal() {
			if ( wp_script_is( 'smush-backbone-extension', 'enqueued' ) ) {
				return;
			}

			wp_enqueue_script(
				'smush-backbone-extension', WP_SMUSH_URL . 'app/assets/js/media.min.js', array(
					'jquery',
					'media-editor', // Used in image filters
					'media-views',
					'media-grid',
					'wp-util',
					'wp-api',
				), WP_SMUSH_VERSION, true
			);

			wp_localize_script(
				'smush-backbone-extension', 'smush_vars', array(
					'strings' => array(
						'stats_label'  => esc_html__( 'Smush', 'wp-smushit' ),
						'filter_all'   => esc_html__( 'Smush: All images', 'wp-smushit' ),
						'filter_excl'  => esc_html__( 'Smush: Bulk ignored', 'wp-smushit' ),
					),
					'nonce'   => array(
						'get_smush_status' => wp_create_nonce( 'get-smush-status' ),
					)
				)
			);
		}

		/**
		 * Send smush status for attachment
		 *
		 * @param $response
		 * @param $attachment
		 *
		 * @return mixed
		 */
		function smush_send_status( $response, $attachment ) {
			if ( ! isset( $attachment->ID ) ) {
				return $response;
			}

			// Validate nonce
			$status            = $this->smush_status( $attachment->ID );
			$response['smush'] = $status;

			return $response;
		}

		/**
		 * Return Latest stats
		 */
		function get_stats() {
			if ( empty( $this->stats ) ) {
				$this->setup_global_stats( true );
			}
			$stats = array(
				'count_images'       => ! empty( $this->stats ) && isset( $this->stats['total_images'] ) ? $this->stats['total_images'] : 0,
				'count_resize'       => ! empty( $this->stats ) && isset( $this->stats['resize_count'] ) ? $this->stats['resize_count'] : 0,
				'count_smushed'      => $this->smushed_count,
				'count_supersmushed' => $this->super_smushed,
				'count_total'        => $this->total_count,
				'savings_bytes'      => ! empty( $this->stats ) && isset( $this->stats['bytes'] ) ? $this->stats['bytes'] : 0,
				'savings_conversion' => ! empty( $this->stats ) && isset( $this->stats['conversion_savings'] ) ? $this->stats['conversion_savings'] : 0,
				'savings_resize'     => ! empty( $this->stats ) && isset( $this->stats['resize_savings'] ) ? $this->stats['resize_savings'] : 0,
				'size_before'        => ! empty( $this->stats ) && isset( $this->stats['size_before'] ) ? $this->stats['size_before'] : 0,
				'size_after'         => ! empty( $this->stats ) && isset( $this->stats['size_after'] ) ? $this->stats['size_after'] : 0,
			);
			wp_send_json_success( $stats );
		}

		/**
		 * Smush icon svg image
		 *
		 * @return string
		 */
		private function get_menu_icon() {
			ob_start();
			?>
			<svg width="16px" height="16px" viewBox="0 0 16 16" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
				<g id="Symbols" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
					<g id="WP-/-Menu---Free" transform="translate(-12.000000, -428.000000)" fill="#FFFFFF;">
						<path d="M26.9310561,432.026782 C27.2629305,432.598346 27.5228884,433.217017 27.7109375,433.882812 C27.9036468,434.565108 28,435.27083 28,436 C28,437.104172 27.7916687,438.14062 27.375,439.109375 C26.9479145,440.07813 26.3750036,440.924476 25.65625,441.648438 C24.9374964,442.372399 24.0937548,442.942706 23.125,443.359375 C22.1562452,443.78646 21.1197972,444 20.015625,444 L26.9310562,432.026782 L26.9310561,432.026782 Z M26.9310561,432.026782 C26.9228316,432.012617 26.9145629,431.998482 26.90625,431.984375 L26.9375,432.015625 L26.9310562,432.026782 L26.9310561,432.026782 Z M16.625,433.171875 L23.375,433.171875 L20,439.03125 L16.625,433.171875 Z M14.046875,430.671875 L14.046875,430.65625 C14.4114602,430.249998 14.8177061,429.88021 15.265625,429.546875 C15.7031272,429.223957 16.1744766,428.945314 16.6796875,428.710938 C17.1848984,428.476561 17.7187472,428.296876 18.28125,428.171875 C18.8333361,428.046874 19.406247,427.984375 20,427.984375 C20.593753,427.984375 21.1666639,428.046874 21.71875,428.171875 C22.2812528,428.296876 22.8151016,428.476561 23.3203125,428.710938 C23.8255234,428.945314 24.3020811,429.223957 24.75,429.546875 C25.1875022,429.88021 25.5937481,430.255206 25.96875,430.671875 L14.046875,430.671875 Z M13.0625,432.03125 L19.984375,444 C18.8802028,444 17.8437548,443.78646 16.875,443.359375 C15.9062452,442.942706 15.0625036,442.372399 14.34375,441.648438 C13.6249964,440.924476 13.0572937,440.07813 12.640625,439.109375 C12.2239563,438.14062 12.015625,437.104172 12.015625,436 C12.015625,435.27083 12.1067699,434.567712 12.2890625,433.890625 C12.4713551,433.213538 12.729165,432.593753 13.0625,432.03125 Z" id="icon-smush"></path>
					</g>
				</g>
			</svg>
			<?php
			$svg = ob_get_clean();

			return 'data:image/svg+xml;base64,' . base64_encode( $svg );
		}

		/**
		 * Checks if upfront images needs to be resmushed
		 *
		 * @param $resmush_list
		 *
		 * @return array Returns the list of image ids that needs to be re-smushed
		 */
		function get_upfront_resmush_list( $resmush_list ) {
			global $wpsmush_db;
			$upfront_attachments = $wpsmush_db->get_upfront_images( $resmush_list );
			if ( ! empty( $upfront_attachments ) && is_array( $upfront_attachments ) ) {
				foreach ( $upfront_attachments as $u_attachment_id ) {
					if ( ! in_array( $u_attachment_id, $resmush_list ) ) {
						// Check if not smushed
						$upfront_images = get_post_meta( $u_attachment_id, 'upfront_used_image_sizes', true );
						if ( ! empty( $upfront_images ) && is_array( $upfront_images ) ) {
							// Iterate over all the images
							foreach ( $upfront_images as $image ) {
								// If any of the element image is not smushed, add the id to resmush list
								// and skip to next image
								if ( empty( $image['is_smushed'] ) || 1 != $image['is_smushed'] ) {
									$resmush_list[] = $u_attachment_id;
									break;
								}
							}
						}
					}
				}
			}

			return $resmush_list;
		}

		/**
		 * Add custom admin pointer using wp-pointer.
		 *
		 * We have removed activation redirect to Smush settings
		 * in new version to avoid interrupting bulk activations.
		 * Show a pointer notice to Smush settings menu on new
		 * activations.
		 *
		 * @since 2.9
		 */
		public function admin_pointer() {

			// Get dismissed pointers meta.
			$dismissed_pointers = get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true );

			// Explod them by comma.
			$dismissed_pointers = explode( ',', (string) $dismissed_pointers );

			// If smush pointer is not found in dismissed pointers, show.
			if ( in_array( 'smush_pointer', $dismissed_pointers ) ) {
				return;
			}

			// We had a flag in old versions for activation redirect. Check that also.
			if ( get_site_option( 'wp-smush-skip-redirect' ) ) {
				return;
			}

			// Enqueue wp-pointer styles and scripts.
			wp_enqueue_style( 'wp-pointer' );
			wp_enqueue_script( 'wp-pointer' );

			// Register our custom pointer.
			add_action( 'admin_print_footer_scripts', array( $this, 'register_admin_pointer' ) );
		}

		/**
		 * Register smush custom pointer to wp-pointer.
		 *
		 * Use wordpress dismiss-wp-pointer action on pointer
		 * dismissal to store dismissal flag in meta via ajax.
		 *
		 * @since 2.9
		 */
		public function register_admin_pointer() {
			// Pointer content.
			$content = '<h3>' . __( 'Get Optimized', 'wp-smushit' ) . '</h3>';
			$content .= '<p>' . __( 'Resize, compress and optimize your images here.', 'wp-smushit' ) . '</p>';
			?>

			<script type="text/javascript">
				//<![CDATA[
				jQuery( document ).ready( function( $ ) {
					// jQuery selector to point the message to.
					$( '#toplevel_page_smush' ).pointer({
						content: '<?php echo $content; ?>',
						position: {
							edge: 'left',
							align: 'center'
						},
						close: function() {
							$.post( ajaxurl, {
								pointer: 'smush_pointer',
								action: 'dismiss-wp-pointer'
							});
						}
					}).pointer( 'open' );
				});
				//]]>
			</script>
			<?php
		}

		/**
		 * Ignore image from bulk Smush.
		 *
		 * @since 1.9.0
		 */
		public function ignore_bulk_image() {
			if ( ! isset ( $_POST['id'] ) ) {
				wp_send_json_error();
			}

			$id = absint( $_POST['id'] );
			update_post_meta( $id, 'wp-smush-ignore-bulk', 'true' );

			wp_send_json_success();
		}

		/**
		 * Add our filter to the media query filter in Media Library.
		 *
		 * @since 2.9.0
		 *
		 * @see wp_ajax_query_attachments()
		 *
		 * @param array $query
		 *
		 * @return mixed
		 */
		public function filter_media_query( $query ) {
			if ( isset( $_POST['query']['stats'] ) && 'null' === $_POST['query']['stats'] ) {
				$query['meta_query'] = array(
					array(
						'key'     => 'wp-smush-ignore-bulk',
						'value'   => 'true',
						'compare' => 'EXISTS',
					),
				);
			}

			return $query;
		}

	}

	global $wpsmushit_admin;
	$wpsmushit_admin = new WpSmushitAdmin();
}
