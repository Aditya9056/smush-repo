<?php
/**
 * Smush class for storing all Ajax related functionality: WP_Smush_Ajax class
 *
 * @package WP_Smush
 * @subpackage Admin
 * @since 2.9.0
 *
 * @copyright (c) 2018, Incsub (http://incsub.com)
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class WP_Smush_Ajax for storing all Ajax related functionality.
 *
 * @since 2.9.0
 */
class WP_Smush_Ajax extends WP_Smush_Module {

	/**
	 * WP_Smush_Ajax constructor.
	 */
	public function init() {
		/**
		 * QUICK SETUP
		 */
		// Handle skip quick setup action.
		add_action( 'wp_ajax_skip_smush_setup', array( $this, 'skip_smush_setup' ) );
		// Ajax request for quick setup.
		add_action( 'wp_ajax_smush_setup', array( $this, 'smush_setup' ) );

		/**
		 * NOTICES
		 */
		// Handle the smush pro dismiss features notice ajax.
		add_action( 'wp_ajax_dismiss_upgrade_notice', array( $this, 'dismiss_upgrade_notice' ) );
		// Handle the smush pro dismiss features notice ajax.
		add_action( 'wp_ajax_dismiss_welcome_notice', array( $this, 'dismiss_welcome_notice' ) );
		// Handle the smush pro dismiss features notice ajax.
		add_action( 'wp_ajax_dismiss_update_info', array( $this, 'dismiss_update_info' ) );
		// Handle ajax request to dismiss the s3 warning.
		add_action( 'wp_ajax_dismiss_s3support_alert', array( $this, 'dismiss_s3support_alert' ) );
		// Hide PageSpeed suggestion.
		add_action( 'wp_ajax_hide_pagespeed_suggestion', array( $this, 'hide_pagespeed_suggestion' ) );
		// Hide API message.
		add_action( 'wp_ajax_hide_api_message', array( $this, 'hide_api_message' ) );
		add_action( 'wp_ajax_smush_show_warning', array( $this, 'show_warning_ajax' ) );

		/**
		 * SMUSH
		 */
		// Handle Smush Single Ajax.
		add_action( 'wp_ajax_wp_smushit_manual', array( $this, 'smush_manual' ) );
		// Handle resmush operation.
		add_action( 'wp_ajax_smush_resmush_image', array( $this, 'resmush_image' ) );
		// Scan images as per the latest settings.
		add_action( 'wp_ajax_scan_for_resmush', array( $this, 'scan_images' ) );
		// Delete ReSmush list.
		add_action( 'wp_ajax_delete_resmush_list', array( $this, 'delete_resmush_list' ), '', 2 );
		// Send smush stats.
		add_action( 'wp_ajax_get_stats', array( $this, 'get_stats' ) );

		/**
		 * BULK SMUSH
		 */
		// Ignore image from bulk Smush.
		add_action( 'wp_ajax_ignore_bulk_image', array( $this, 'ignore_bulk_image' ) );
		// Handle Smush Bulk Ajax.
		add_action( 'wp_ajax_wp_smushit_bulk', array( $this, 'process_smush_request' ) );
		// Remove from skip list.
		add_action( 'wp_ajax_remove_from_skip_list', array( $this, 'remove_from_skip_list' ) );

		/**
		 * DIRECTORY SMUSH
		 */
		// Handle Ajax request for directory smush stats (stats meta box).
		add_action( 'wp_ajax_get_dir_smush_stats', array( $this, 'get_dir_smush_stats' ) );

		/**
		 * CDN
		 */
		// Toggle CDN.
		add_action( 'wp_ajax_smush_toggle_cdn', array( $this, 'toggle_cdn' ) );
		// Update stats box and CDN status.
		add_action( 'wp_ajax_get_cdn_stats', array( $this, 'get_cdn_stats' ) );

		/**
		 * Lazy loading
		 */
		add_action( 'wp_ajax_smush_toggle_lazy_load', array( $this, 'smush_toggle_lazy_load' ) );
	}

	/***************************************
	 *
	 * QUICK SETUP
	 */

	/**
	 * Process ajax action for skipping Smush setup.
	 */
	public function skip_smush_setup() {
		check_ajax_referer( 'smush_quick_setup' );
		update_option( 'skip-smush-setup', true );
		wp_send_json_success();
	}

	/**
	 * Ajax action to save settings from quick setup.
	 */
	public function smush_setup() {
		check_ajax_referer( 'smush_quick_setup', '_wpnonce' );

		$quick_settings = array();
		// Get the settings from $_POST.
		if ( ! empty( $_POST['smush_settings'] ) ) {
			$quick_settings = json_decode( wp_unslash( $_POST['smush_settings'] ) );
		}

		// Check the last settings stored in db.
		$settings = $this->settings->get();

		// Available settings for free/pro version.
		$available = array( 'auto', 'lossy', 'strip_exif', 'original', 'usage' );

		foreach ( WP_Smush::get_instance()->core()->settings as $name => $values ) {
			// Update only specified settings.
			if ( ! in_array( $name, $available, true ) ) {
				continue;
			}

			// Skip premium features if not a member.
			if ( ! in_array( $name, WP_Smush_Core::$basic_features, true ) && ! WP_Smush::is_pro() ) {
				continue;
			}

			// Update value in settings.
			$settings[ $name ] = (bool) $quick_settings->{$name};

			// If Smush originals is selected, enable backups.
			if ( 'original' === $name && $settings[ $name ] && WP_Smush::is_pro() ) {
				$settings['backup'] = true;
			}
		}

		// Update the resize sizes.
		$this->settings->set_setting( WP_SMUSH_PREFIX . 'settings', $settings );

		update_option( 'skip-smush-setup', true );

		wp_send_json_success();
	}

	/***************************************
	 *
	 * NOTICES
	 */

	/**
	 * Store a key/value to hide the smush features on bulk page
	 *
	 * @param bool $ajax  Does this come from an AJAX request.
	 */
	public function dismiss_upgrade_notice( $ajax = true ) {
		update_site_option( 'wp-smush-hide_upgrade_notice', 1 );
		// No Need to send json response for other requests.
		if ( $ajax ) {
			wp_send_json_success();
		}
	}

	/**
	 * Store a key/value to hide the smush features on bulk page
	 */
	public function dismiss_welcome_notice() {
		update_site_option( 'wp-smush-hide_smush_welcome', 1 );
		wp_send_json_success();
	}

	/**
	 * Remove the Update info
	 *
	 * @param bool $remove_notice  Remove notice.
	 */
	public function dismiss_update_info( $remove_notice = false ) {
		WP_Smush::get_instance()->core()->mod->smush->dismiss_update_info( $remove_notice );
	}

	/**
	 * Hide S3 support alert by setting a flag.
	 */
	public function dismiss_s3support_alert() {
		// Just set a flag.
		update_site_option( 'wp-smush-hide_s3support_alert', 1 );
		wp_send_json_success();
	}

	/**
	 * Store user preference for PageSpeed suggestions.
	 */
	public function hide_pagespeed_suggestion() {
		update_site_option( WP_SMUSH_PREFIX . 'hide_pagespeed_suggestion', true );
		wp_send_json_success();
	}

	/**
	 * Hide API Message
	 */
	public function hide_api_message() {
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
	 * Send JSON response whether to show or not the warning
	 */
	public function show_warning_ajax() {
		$show = WP_Smush::get_instance()->core()->mod->smush->show_warning();
		wp_send_json( intval( $show ) );
	}

	/***************************************
	 *
	 * SMUSH
	 */

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

		$core = WP_Smush::get_instance()->core();

		/**
		 * Filter: wp_smush_image.
		 *
		 * Whether to smush the given attachment ID or not.
		 *
		 * @param bool $status         Smush all attachments by default.
		 * @param int  $attachment_id  Attachment ID.
		 */
		if ( ! apply_filters( 'wp_smush_image', true, $attachment_id ) ) {
			$error = WP_Smush_Helper::filter_error( esc_html__( 'Attachment Skipped - Check `wp_smush_image` filter.', 'wp-smushit' ), $attachment_id );
			wp_send_json_error(
				array(
					'error_msg'    => sprintf( '<p class="wp-smush-error-message">%s</p>', $error ),
					'show_warning' => intval( $core->mod->smush->show_warning() ),
				)
			);
		}

		// Pass on the attachment id to smush single function.
		$core->mod->smush->smush_single( $attachment_id );
	}

	/**
	 * Resmush the image
	 *
	 * @uses smush_single()
	 */
	public function resmush_image() {
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

		$smushed = WP_Smush::get_instance()->core()->mod->smush->smush_single( $image_id, true );

		// If any of the image is restored, we count it as success.
		if ( ! empty( $smushed['status'] ) ) {
			// Send button content.
			wp_send_json_success(
				array(
					'button' => $smushed['status'] . $smushed['stats'],
				)
			);
		}

		// Send error Message.
		if ( ! empty( $smushed['error'] ) ) {
			wp_send_json_error(
				array(
					'message' => '<div class="wp-smush-error">' . $smushed['error'] . '</div>',
				)
			);
		}
	}

	/**
	 * Scans all the smushed attachments to check if they need to be resmushed as per the
	 * current settings, as user might have changed one of the configurations "Lossy", "Keep Original", "Preserve Exif"
	 *
	 * @todo: Needs some refactoring big time
	 */
	public function scan_images() {
		check_ajax_referer( 'save_wp_smush_options', 'wp_smush_options_nonce' );

		$resmush_list = array();

		// Scanning for NextGen or Media Library.
		$type = isset( $_REQUEST['type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['type'] ) ) : '';

		$core = WP_Smush::get_instance()->core();

		// Save settings only if networkwide settings are disabled.
		if ( ( ! is_multisite() || ! $this->settings->is_network_enabled() ) && ( ! isset( $_REQUEST['process_settings'] ) || 'false' != $_REQUEST['process_settings'] ) ) {
			// Save Settings.
			$this->settings->save( false );
		}

		// If there aren't any images in the library, return the notice.
		if ( 0 == $core->mod->db->get_media_attachments( true ) && 'nextgen' !== $type ) {
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
					'super_smush' => WP_Smush::is_pro() && $this->settings->get( 'lossy' ),
				)
			);
		}

		// Default Notice, to be displayed at the top of page. Show a message, at the top.
		$message = esc_html__( 'Yay! All images are optimized as per your current settings.', 'wp-smushit' );
		$resp    = '<div class="sui-notice-top sui-notice-success sui-can-dismiss">
					<div class="sui-notice-content">
						<p>' . $message . '</p>
					</div>
					<span class="sui-notice-dismiss">
						<a role="button" href="#" aria-label="' . __( 'Dismiss', 'wp-smushit' ) . '" class="sui-icon-check"></a>
					</span>
				</div>';

		// If a user manually runs smush check.
		$return_ui = isset( $_REQUEST['get_ui'] ) && 'true' == $_REQUEST['get_ui'] ? true : false;

		/**
		 * Logic: If none of the required settings is on, don't need to resmush any of the images
		 * We need at least one of these settings to be on, to check if any of the image needs resmush.
		 */

		// Initialize Media Library Stats.
		if ( 'nextgen' !== $type && empty( $core->remaining_count ) ) {
			// Force update to clear caches.
			$core->setup_global_stats( true );
		}

		// Initialize NextGen Stats.
		if ( 'nextgen' === $type && is_object( $core->nextgen->ng_admin ) && empty( $core->nextgen->ng_admin->remaining_count ) ) {
			$core->nextgen->ng_admin->setup_image_counts();
		}

		$key = 'nextgen' === $type ? 'wp-smush-nextgen-resmush-list' : 'wp-smush-resmush-list';

		$remaining_count = 'nextgen' === $type ? $core->nextgen->ng_admin->remaining_count : $core->remaining_count;

		if ( 0 === (int) $remaining_count && ( ! WP_Smush::is_pro() || ! $this->settings->get( 'lossy' ) ) && ( ! $this->settings->get( 'original' ) || ! WP_Smush::is_pro() ) && ! $this->settings->get( 'strip_exif' ) ) {
			delete_option( $key );
			delete_site_option( WP_SMUSH_PREFIX . 'run_recheck' );
			wp_send_json_success( array( 'notice' => $resp ) );
		}

		// Set to empty by default.
		$ajax_response = '';

		// Get Smushed Attachments.
		if ( 'nextgen' !== $type ) {
			// Get list of Smushed images.
			$attachments = ! empty( $core->smushed_attachments ) ? $core->smushed_attachments : $core->mod->db->smushed_count( true );
		} else {
			// Get smushed attachments list from nextgen class, We get the meta as well.
			$attachments = $core->nextgen->ng_stats->get_ngg_images();
		}

		$image_count = $super_smushed_count = $smushed_count = $resized_count = 0;
		// Check if any of the smushed image needs to be resmushed.
		if ( ! empty( $attachments ) && is_array( $attachments ) ) {
			$stats = array(
				'size_before'        => 0,
				'size_after'         => 0,
				'savings_resize'     => 0,
				'savings_conversion' => 0,
			);

			// Initialize resize class.
			$core->mod->resize->initialize();

			foreach ( $attachments as $attachment_k => $attachment ) {
				// Skip if already in resmush list.
				if ( ! empty( $core->resmush_ids ) && in_array( $attachment, $core->resmush_ids ) ) {
					continue;
				}
				$should_resmush = false;

				// For NextGen we get the metadata in the attachment data itself.
				if ( is_array( $attachment ) && ! empty( $attachment['wp_smush'] ) ) {
					$smush_data = $attachment['wp_smush'];
				} else {
					// Check the current settings, and smush data for the image.
					$smush_data = get_post_meta( $attachment, WP_Smushit::$smushed_meta_key, true );
				}

				// If the image is already smushed.
				if ( is_array( $smush_data ) && ! empty( $smush_data['stats'] ) ) {

					// If we need to optmise losslessly, add to resmush list.
					$smush_lossy = WP_Smush::is_pro() && $this->settings->get( 'lossy' ) && ! $smush_data['stats']['lossy'];

					// If we need to strip exif, put it in resmush list.
					$strip_exif = $this->settings->get( 'strip_exif' ) && isset( $smush_data['stats']['keep_exif'] ) && ( 1 == $smush_data['stats']['keep_exif'] );

					// If Original image needs to be smushed.
					$smush_original = $this->settings->get( 'original' ) && WP_Smush::is_pro() && empty( $smush_data['sizes']['full'] );

					if ( $smush_lossy || $strip_exif || $smush_original ) {
						$should_resmush = true;
					}

					// If Image needs to be resized.
					if ( ! $should_resmush ) {
						$should_resmush = $core->mod->resize->should_resize( $attachment );
					}

					// If image can be converted.
					if ( ! $should_resmush ) {
						$should_resmush = $core->mod->png2jpg->can_be_converted( $attachment );
					}

					// If the image needs to be resmushed add it to the list.
					if ( $should_resmush ) {
						$resmush_list[] = 'nextgen' === $type ? $attachment_k : $attachment;
					}

					/**
					 * Calculate stats during re-check images action.
					 */
					if ( 'nextgen' !== $type ) {
						$resize_savings     = get_post_meta( $attachment, WP_SMUSH_PREFIX . 'resize_savings', true );
						$conversion_savings = WP_Smush_Helper::get_pngjpg_savings( $attachment );

						// Increase the smushed count.
						$smushed_count ++;
						// Get the resized image count.
						if ( ! empty( $resize_savings ) ) {
							$resized_count ++;
						}

						// Get the image count.
						$image_count += ( ! empty( $smush_data['sizes'] ) && is_array( $smush_data['sizes'] ) ) ? count( $smush_data['sizes'] ) : 0;

						// If the image is in resmush list, and it was super smushed earlier.
						$super_smushed_count += ( $smush_data['stats']['lossy'] ) ? 1 : 0;

						// Add to the stats.
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
			}// End of Foreach Loop

			// Store the resmush list in Options table.
			update_option( $key, $resmush_list, false );
		}

		// Get updated stats for Nextgen.
		if ( 'nextgen' === $type ) {
			// Reinitialize Nextgen stats.
			$core->nextgen->ng_admin->setup_image_counts();
			// Image count, Smushed Count, Supersmushed Count, Savings.
			$stats               = $core->nextgen->ng_stats->get_smush_stats();
			$image_count         = $core->nextgen->ng_admin->image_count;
			$smushed_count       = $core->nextgen->ng_admin->smushed_count;
			$super_smushed_count = $core->nextgen->ng_admin->super_smushed;
		}

		// Delete resmush list if empty.
		if ( empty( $resmush_list ) ) {
			delete_option( $key );
		}

		$resmush_count = $count = count( $resmush_list );
		$count        += 'nextgen' == $type ? $core->nextgen->ng_admin->remaining_count : $core->remaining_count;

		// Return the Remsmush list and UI to be appended to Bulk Smush UI.
		if ( $return_ui ) {
			if ( 'nextgen' !== $type ) {
				// Set the variables.
				$core->resmush_ids = $resmush_list;
			} else {
				// To avoid the php warning.
				$core->nextgen->ng_admin->resmush_ids = $resmush_list;
			}

			if ( $resmush_count ) {
				$ajax_response = WP_Smush::get_instance()->admin()->bulk_resmush_content( $count, false );
			}
		}

		if ( ! empty( $count ) ) {
			/* translators: %1$d - number of images, %2$s - opening a tag, %3$s - closing a tag */
			$message = sprintf( esc_html__( 'Image check complete, you have %1$d images that need smushing. %2$sBulk smush now!%3$s', 'wp-smushit' ), $count, '<a href="#" class="wp-smush-trigger-bulk">', '</a>' );
			$resp    = '<div class="sui-notice-top sui-notice-warning sui-can-dismiss">
					<div class="sui-notice-content">
						<p>' . $message . '</p>
					</div>
					<span class="sui-notice-dismiss">
						<a role="button" href="#" aria-label="' . __( 'Dismiss', 'wp-smushit' ) . '" class="sui-icon-check" id="bulk-smush-top-notice-close"></a>
					</span>
				</div>';
		}

		// Directory Smush Stats
		// Include directory smush stats if not requested for NextGen.
		if ( 'nextgen' !== $type ) {
			// Append the directory smush stats.
			$dir_smush_stats = get_option( 'dir_smush_stats' );
			if ( ! empty( $dir_smush_stats ) && is_array( $dir_smush_stats ) ) {
				if ( ! empty( $dir_smush_stats['dir_smush'] ) && ! empty( $dir_smush_stats['optimised'] ) ) {
					$dir_smush_stats = $dir_smush_stats['dir_smush'];
					$image_count    += $dir_smush_stats['optimised'];
				}

				// Add directory smush stats if not empty.
				if ( ! empty( $dir_smush_stats['image_size'] ) && ! empty( $dir_smush_stats['orig_size'] ) ) {
					$stats['size_before'] += $dir_smush_stats['orig_size'];
					$stats['size_after']  += $dir_smush_stats['image_size'];
				}
			}
		}

		// If there is a Ajax response return it, else return null.
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

		// Include the count.
		if ( ! empty( $count ) && $count ) {
			$return['count'] = $count;
		}

		$return['notice']      = $resp;
		$return['super_smush'] = WP_Smush::is_pro() && $this->settings->get( 'lossy' );
		if ( WP_Smush::is_pro() && $this->settings->get( 'lossy' ) && 'nextgen' === $type ) {
			$ss_count                    = $core->mod->db->super_smushed_count( 'nextgen', $core->nextgen->ng_stats->get_ngg_images( 'smushed' ) );
			$return['super_smush_stats'] = sprintf( '<strong><span class="smushed-count">%d</span>/%d</strong>', $ss_count, $core->nextgen->ng_admin->total_count );
		}

		delete_site_option( WP_SMUSH_PREFIX . 'run_recheck' );
		wp_send_json_success( $return );
	}

	/**
	 * Delete the resmush list for Nextgen or the Media Library
	 *
	 * Return Stats in ajax response
	 */
	public function delete_resmush_list() {
		$stats = array();

		$key = ! empty( $_POST['type'] ) && 'nextgen' === $_POST['type'] ? 'wp-smush-nextgen-resmush-list' : 'wp-smush-resmush-list';

		// For media Library.
		if ( 'nextgen' !== $_POST['type'] ) {
			$resmush_list = get_option( $key );
			if ( ! empty( $resmush_list ) && is_array( $resmush_list ) ) {
				$stats = WP_Smush::get_instance()->core()->mod->db->get_stats_for_attachments( $resmush_list );
			}
		} else {
			// For Nextgen. Get the stats (get the re-Smush IDs).
			$resmush_ids = get_option( 'wp-smush-nextgen-resmush-list', array() );

			$stats = WP_Smush::get_instance()->core()->nextgen->ng_stats->get_stats_for_ids( $resmush_ids );

			$stats['count_images'] = WP_Smush::get_instance()->core()->nextgen->ng_admin->get_image_count( $resmush_ids, false );
		}

		// Delete the resmush list.
		delete_option( $key );
		wp_send_json_success( array( 'stats' => $stats ) );
	}

	/**
	 * Return Latest stats.
	 */
	public function get_stats() {
		$core = WP_Smush::get_instance()->core();

		if ( empty( $core->stats ) ) {
			$core->setup_global_stats( true );
		}

		$stats = array(
			'count_images'       => ! empty( $core->stats ) && isset( $core->stats['total_images'] ) ? $core->stats['total_images'] : 0,
			'count_resize'       => ! empty( $core->stats ) && isset( $core->stats['resize_count'] ) ? $core->stats['resize_count'] : 0,
			'count_smushed'      => $core->smushed_count,
			'count_supersmushed' => $core->super_smushed,
			'count_total'        => $core->total_count,
			'savings_bytes'      => ! empty( $core->stats ) && isset( $core->stats['bytes'] ) ? $core->stats['bytes'] : 0,
			'savings_conversion' => ! empty( $core->stats ) && isset( $core->stats['conversion_savings'] ) ? $core->stats['conversion_savings'] : 0,
			'savings_resize'     => ! empty( $core->stats ) && isset( $core->stats['resize_savings'] ) ? $core->stats['resize_savings'] : 0,
			'size_before'        => ! empty( $core->stats ) && isset( $core->stats['size_before'] ) ? $core->stats['size_before'] : 0,
			'size_after'         => ! empty( $core->stats ) && isset( $core->stats['size_after'] ) ? $core->stats['size_after'] : 0,
		);

		wp_send_json_success( $stats );
	}

	/***************************************
	 *
	 * BULK SMUSH
	 */

	/**
	 * Ignore image from bulk Smush.
	 *
	 * @since 1.9.0
	 */
	public function ignore_bulk_image() {
		if ( ! isset( $_POST['id'] ) ) {
			wp_send_json_error();
		}

		$id = absint( $_POST['id'] );
		update_post_meta( $id, 'wp-smush-ignore-bulk', 'true' );

		wp_send_json_success();
	}

	/**
	 * Bulk Smushing Handler.
	 *
	 * Processes the Smush request and sends back the next id for smushing.
	 */
	public function process_smush_request() {
		// Turn off errors for ajax result.
		@error_reporting( 0 );

		$smush = WP_Smush::get_instance()->core()->mod->smush;

		if ( empty( $_REQUEST['attachment_id'] ) ) {
			wp_send_json_error(
				array(
					'error'         => 'missing_id',
					'error_message' => WP_Smush_Helper::filter_error( esc_html__( 'No attachment ID was received.', 'wp-smushit' ) ),
					'file_name'     => 'undefined',
					'show_warning'  => intval( $smush->show_warning() ),
				)
			);
		}

		// If the bulk smush needs to be stopped.
		if ( ! WP_Smush::is_pro() && ! WP_Smush_Core::check_bulk_limit() ) {
			$upgrade_url = add_query_arg(
				array(
					'utm_source'   => 'smush',
					'utm_medium'   => 'plugin',
					'utm_campaign' => 'smush_bulksmush_limit_reached_notice',
				),
				esc_url( 'https://premium.wpmudev.org/project/wp-smush-pro/' )
			);

			wp_send_json_error(
				array(
					'error'         => 'limit_exceeded',
					'error_message' => sprintf(
						esc_html__( "You've reached the %1\$d attachment limit for bulk smushing in the free version. %2\$sUpgrade to Pro%3\$s to smush unlimited images, or click resume to smush another %1\$d attachments.", 'wp-smushit' ),
						WP_Smush_Core::$max_free_bulk,
						'<a href="' . esc_url( $upgrade_url ) . '" target="_blank">',
						'</a>'
					),
					'continue'      => false,
				)
			);
		}

		$attachment_id = (int) $_REQUEST['attachment_id'];
		$original_meta = wp_get_attachment_metadata( $attachment_id, true );

		/**
		 * This is often not set when images are imported to the database, without properly adding the meta values.
		 * Causes PHP Warning: Illegal string offset 'file' message.
		 */
		if ( ! isset( $original_meta['file'] ) ) {
			wp_send_json_error(
				array(
					'error'         => 'no_file_meta',
					'error_message' => WP_Smush_Helper::filter_error( esc_html__( 'No file data found in image meta.', 'wp-smushit' ) ),
					'file_name'     => printf(
						/* translators: %d - attachment ID */
						esc_html__( 'undefined (attachment ID: %d)', 'wp-smushit' ),
						(int) $attachment_id
					),
				)
			);
		}

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
					'error_message' => WP_Smush_Helper::filter_error( esc_html__( 'Skipped with wp_smush_image filter', 'wp-smushit' ) ),
					'show_warning'  => intval( $smush->show_warning() ),
					'file_name'     => WP_Smush_Helper::get_image_media_link( $attachment_id, $file_name ),
					'thumbnail'     => wp_get_attachment_image( $attachment_id ),
				)
			);
		}

		// Get the file path for backup.
		$attachment_file_path = WP_Smush_Helper::get_attached_file( $attachment_id );

		// Download if not exists.
		do_action( 'smush_file_exists', $attachment_file_path, $attachment_id );

		$smush->check_animated_status( $attachment_file_path, $attachment_id );

		WP_Smush::get_instance()->core()->mod->backup->create_backup( $attachment_file_path, '', $attachment_id );

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
				$updated_meta  = $smush->resize_image( $attachment_id, $original_meta );
				$original_meta = ! empty( $updated_meta ) ? $updated_meta : $original_meta;
			}

			$original_meta = WP_Smush::get_instance()->core()->mod->png2jpg->png_to_jpg( $attachment_id, $original_meta );

			$smush_response = $smush->resize_from_meta_data( $original_meta, $attachment_id );
			wp_update_attachment_metadata( $attachment_id, $original_meta );
		}

		// Delete transient.
		delete_option( 'smush-in-progress-' . $attachment_id );

		$smush_data         = get_post_meta( $attachment_id, WP_Smushit::$smushed_meta_key, true );
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

		if ( isset( $smush_response ) && is_wp_error( $smush_response ) ) {
			$error_message = $smush_response->get_error_message();

			// Check for timeout error and suggest to filter timeout.
			if ( strpos( $error_message, 'timed out' ) ) {
				$error         = 'timeout';
				$error_message = esc_html__( "Timeout error. You can increase the request timeout to make sure Smush has enough time to process larger files. `define('WP_SMUSH_API_TIMEOUT', 150);`", 'wp-smushit' );
			}

			$error = isset( $error ) ? $error : 'other';

			if ( ! empty( $error_message ) ) {
				// Used internally to modify the error message.
				$error_message = WP_Smush_Helper::filter_error( $error_message, $attachment_id, $error );
			}

			wp_send_json_error(
				array(
					'stats'         => $stats,
					'error'         => $error,
					'error_message' => $error_message,
					'show_warning'  => intval( $smush->show_warning() ),
					'error_class'   => isset( $error_class ) ? $error_class : '',
					'file_name'     => WP_Smush_Helper::get_image_media_link( $attachment_id, $file_name ),
				)
			);
		}

		// Check if a resmush request, update the resmush list.
		if ( ! empty( $_REQUEST['is_bulk_resmush'] ) && 'false' !== $_REQUEST['is_bulk_resmush'] && $_REQUEST['is_bulk_resmush'] ) {
			$smush->update_resmush_list( $attachment_id );
		}

		// Runs after a image is successfully smushed.
		do_action( 'image_smushed', $attachment_id, $stats );

		// Update the bulk Limit count.
		WP_Smush_Core::update_smush_count();

		// Send ajax response.
		wp_send_json_success(
			array(
				'stats'        => $stats,
				'show_warning' => intval( $smush->show_warning() ),
			)
		);
	}

	/**
	 * Remove the image meta that is making the image skip bulk smush.
	 *
	 * @since 3.0
	 */
	public function remove_from_skip_list() {
		wp_verify_nonce( 'wp-smush-remove-skipped' );

		if ( ! isset( $_POST['id'] ) ) {
			wp_send_json_error();
		}

		delete_post_meta( absint( $_POST['id'] ), 'wp-smush-ignore-bulk' );

		wp_send_json_success();
	}

	/***************************************
	 *
	 * DIRECTORY SMUSH
	 */

	/**
	 * Returns Directory Smush stats and Cumulative stats
	 */
	public function get_dir_smush_stats() {
		$result = array();

		// Store the Total/Smushed count.
		$stats = WP_Smush::get_instance()->core()->mod->dir->total_stats();

		$result['dir_smush'] = $stats;

		// Cumulative Stats.
		$result['combined_stats'] = WP_Smush::get_instance()->core()->mod->dir->combine_stats( $stats );

		// Store the stats in options table.
		update_option( 'dir_smush_stats', $result, false );

		// Send ajax response.
		wp_send_json_success( $result );
	}

	/***************************************
	 *
	 * CDN
	 *
	 * @since 3.0
	 */

	/**
	 * Toggle CDN.
	 *
	 * Handles "Get Started" button press on the disabled CDN meta box.
	 * Handles "Deactivate" button press on the CDN meta box.
	 * Refreshes page on success.
	 *
	 * @since 3.0
	 */
	public function toggle_cdn() {
		check_ajax_referer( 'save_wp_smush_options' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'User can not modify options', 'wp-smushit' ),
				),
				403
			);
		}

		$param = isset( $_POST['param'] ) ? sanitize_text_field( wp_unslash( $_POST['param'] ) ) : '';

		$this->settings->set( 'cdn', 'true' === $param );

		if ( 'true' === $param ) {
			// Maybe here is not the place for this. Check CDN settings on page load.
			$status = $this->settings->get_setting( WP_SMUSH_PREFIX . 'cdn_status' );
			$smush  = WP_Smush::get_instance();

			if ( ! $status ) {
				$status = $smush->api()->check();
				$data   = $smush->core()->mod->cdn->process_cdn_status( $status );
				$this->settings->set_setting( WP_SMUSH_PREFIX . 'cdn_status', $data );
			}

			$smush->core()->mod->cdn->schedule_cron();

			// Clear HB page cache.
			do_action( 'wphb_clear_page_cache' );
		} else {
			// Remove CDN settings if disabling.
			$this->settings->delete_setting( WP_SMUSH_PREFIX . 'cdn_status' );

			WP_Smush::get_instance()->core()->mod->cdn->unschedule_cron();
		}

		wp_send_json_success();
	}

	/**
	 * Refresh the stats in CDN meta box and update CDN status on page refresh.
	 *
	 * @since 3.0
	 */
	public function get_cdn_stats() {
		$current_status = $this->settings->get_setting( WP_SMUSH_PREFIX . 'cdn_status' );

		$smush = WP_Smush::get_instance();

		if ( isset( $current_status->cdn_enabling ) && $current_status->cdn_enabling ) {
			$status = $smush->api()->enable();
		} else {
			$status = $smush->api()->check();
		}

		$data = $smush->core()->mod->cdn->process_cdn_status( $status );
		$this->settings->set_setting( WP_SMUSH_PREFIX . 'cdn_status', $data );

		// At this point we already know that $status->data is valid.
		wp_send_json_success( $data );
	}

	/***************************************
	 *
	 * Lazy loading
	 *
	 * @since 3.2.0
	 */

	/**
	 * Toggle lazy loading module.
	 *
	 * Handles "Activate" button press on the disabled lazy loading meta box.
	 * Handles "Deactivate" button press on the lazy loading meta box.
	 * Refreshes page on success.
	 *
	 * @since 3.2.0
	 */
	public function smush_toggle_lazy_load() {
		check_ajax_referer( 'save_wp_smush_options' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'User can not modify options', 'wp-smushit' ),
				),
				403
			);
		}

		$param = isset( $_POST['param'] ) ? sanitize_text_field( wp_unslash( $_POST['param'] ) ) : false;

		if ( 'true' === $param ) {
			$settings = $this->settings->get_setting( WP_SMUSH_PREFIX . 'lazy_load' );

			// No settings, during init - set defaults.
			if ( ! $settings ) {
				$this->settings->init_lazy_load_defaults();
			}
		}

		$this->settings->set( 'lazy_load', 'true' === $param );

		wp_send_json_success();
	}

}
