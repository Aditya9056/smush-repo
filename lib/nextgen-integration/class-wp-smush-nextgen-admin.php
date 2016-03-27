<?php

/**
 * Adds the Bulk Page and Smush Column to NextGen Gallery
 *
 * @package WP Smush
 * @subpackage NextGen Gallery
 * @version 1.0
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2016, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmushNextGenAdmin' ) ) {

	class WpSmushNextGenAdmin extends WpSmushNextGen {

		var $total_count = 0;
		var $smushed_count = 0;
		var $remaining_count = 0;
		var $super_smushed = 0;
		var $bulk_page_handle;

		//Stores all lossless smushed ids
		public $resmush_ids = array();

		function __construct() {

			//Update the number of columns
			add_filter( 'ngg_manage_images_number_of_columns', array(
				&$this,
				'wp_smush_manage_images_number_of_columns'
			) );

			//Add a bulk smush option for NextGen gallery
			add_action( 'admin_menu', array( &$this, 'wp_smush_bulk_menu' ) );

			//Localize variables for NextGen Manage gallery page
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

			//Update resmush list, if a NextGen image is deleted
			add_action( 'ngg_delete_picture', array( $this, 'update_resmush_list' ) );

			//Update Stats, if a NextGen image is deleted
			add_action( 'ngg_delete_picture', array( $this, 'update_nextgen_stats' ) );

		}

		/**
		 * Enqueue Scripts on Manage Gallery page
		 */
		function enqueue() {
			$current_screen = get_current_screen();
			if ( ! empty( $current_screen ) && $current_screen->base == 'nggallery-manage-images' ) {
				$this->localize();
			}
		}

		/**
		 * Add a WP Smush page for bulk smush and settings related to Nextgen gallery
		 */
		function wp_smush_bulk_menu() {
			if ( defined( 'NGGFOLDER' ) ) {
				$this->bulk_page_handle = add_submenu_page( NGGFOLDER, esc_html__( 'Bulk WP Smush', 'wp-smushit' ), esc_html__( 'WP Smush', 'wp-smushit' ), 'NextGEN Manage gallery', 'wp-smush-nextgen-bulk', array(
					&$this,
					'wp_smush_bulk'
				) );
				// Enqueue js on Post screen (Edit screen for media )
				add_action( 'admin_print_scripts-' . $this->bulk_page_handle, array( $this, 'localize' ) );
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
				$columns['wp_smush_image'] = esc_html__( 'WP Smush', 'wp-smushit' );
			} else {
				$columns = esc_html__( 'WP Smush', 'wp-smushit' );
			}

			return $columns;
		}

		/**
		 * Returns Smush option / Stats, depending if image is already smushed or not
		 *
		 * @param $column_name
		 * @param $id
		 */
		function wp_smush_column_options( $column_name, $id, $echo = false ) {
			global $wpsmushnextgenstats;

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
				if ( ! $image_type || ! in_array( $image_type, $supported_image ) ) {
					return;
				}

				//Check Image metadata, if smushed, print the stats or super smush button
				if ( ! empty( $image->meta_data['wp_smush'] ) ) {
					//Echo the smush stats
					return $wpsmushnextgenstats->show_stats( $image->pid, $image->meta_data['wp_smush'], $image_type, false, $echo );
				}

				//Print the status of image, if Not smushed
				return $this->set_status( $image->pid, $echo, false );

			}
		}

		/**
		 * Localize Translations And Stats
		 */
		function localize() {
			global $wpsmushnextgenstats;
			$handle = 'wp-smushit-admin-js';

			$bulk_now = __( 'Bulk Smush Now', 'wp-smushit' );

			$wp_smush_msgs = array(
				'progress'             => __( 'Smushing in Progress', 'wp-smushit' ),
				'done'                 => __( 'All Done!', 'wp-smushit' ),
				'bulk_now'             => $bulk_now,
				'something_went_wrong' => __( 'Ops!... something went wrong', 'wp-smushit' ),
				'resmush'              => __( 'Super-Smush', 'wp-smushit' ),
				'smush_it'             => __( 'Smush it', 'wp-smushit' ),
				'smush_now'            => __( 'Smush Now', 'wp-smushit' ),
				'sending'              => __( 'Sending ...', 'wp-smushit' ),
				"error_in_bulk"        => __( '{{errors}} image(s) were skipped due to an error.', 'wp-smushit' ),
				"all_resmushed"        => __( 'All images are fully optimised.', 'wp-smushit' ),
				'restore'              => esc_html__( "Restoring image..", "wp-smushit" ),
				'smushing'             => esc_html__( "Smushing image..", "wp-smushit" ),
			);

			wp_localize_script( $handle, 'wp_smush_msgs', $wp_smush_msgs );

			//Get the unsmushed ids, used for localized stats as well as normal localization
			$unsmushed = $wpsmushnextgenstats->get_ngg_images( 'unsmushed' );
			$unsmushed = ( ! empty( $unsmushed ) && is_array( $unsmushed ) ) ? array_keys( $unsmushed ) : '';

			$smushed = $wpsmushnextgenstats->get_ngg_images();
			$smushed = ( ! empty( $smushed ) && is_array( $smushed ) ) ? array_keys( $smushed ) : '';

			if ( ! empty( $_REQUEST['ids'] ) ) {
				//Sanitize the ids and assign it to a variable
				$this->ids = array_map( 'intval', explode( ',', $_REQUEST['ids'] ) );
			} else {
				$this->ids = $unsmushed;
			}
			//If premium, Super smush allowed, all images are smushed, localize lossless smushed ids for bulk compression
			if ( $resmush_ids = get_option( 'wp-smush-nextgen-resmush-list' ) ) {

				$this->resmush_ids = $resmush_ids;
			}

			//Array of all smushed, unsmushed and lossless ids
			$data = array(
				'smushed'   => $smushed,
				'unsmushed' => $unsmushed,
				'resmush'   => $this->resmush_ids
			);

			wp_localize_script( 'wp-smushit-admin-js', 'wp_smushit_data', $data );

		}

		/**
		 * Bulk Smush Page
		 */
		function wp_smush_bulk() {
			//Bulk Smush UI, calls progress UI, Super Smush UI
			$this->bulk_smush_ui();

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
		 * Set send button status
		 *
		 * @param $id
		 * @param bool $echo
		 * @param bool $text_only
		 *
		 * @return string|void
		 */
		function set_status( $pid, $echo = true, $text_only = false ) {
			global $WpSmush;

			// the status
			$status_txt = __( 'Not processed', 'wp-smushit' );

			// we need to show the smush button
			$show_button = true;

			// the button text
			$button_txt = __( 'Smush Now!', 'wp-smushit' );
			if ( $text_only ) {
				return $status_txt;
			}

			//If we are not showing smush button, append progree bar, else it is already there
			if ( ! $show_button ) {
				$status_txt .= $WpSmush->progress_bar();
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
		function column_html( $pid, $status_txt = "", $button_txt = "", $show_button = true, $smushed = false, $echo = true, $wrapper = false ) {
			global $WpSmush;

			$class = $smushed ? '' : ' hidden';
			$html  = '<p class="smush-status' . $class . '">' . $status_txt . '</p>';
			$html .= wp_nonce_field( 'wp_smush_nextgen', '_wp_smush_nonce', '', false );
			// if we aren't showing the button
			if ( ! $show_button ) {
				if ( $echo ) {
					echo $html . $WpSmush->progress_bar();

					return;
				} else {
					if ( ! $smushed ) {
						$class = ' currently-smushing';
					} else {
						$class = ' smushed';
					}

					return $wrapper ? '<div class="smush-wrap' . $class . '">' . $html . '</div>' : $html;
				}
			}
			if ( ! $echo ) {
				$html .= '
				<button  class="button wp-smush-nextgen-send" data-id="' . $pid . '">
	                <span>' . $button_txt . '</span>
				</button>';
				if ( ! $smushed ) {
					$class = ' unsmushed';
				} else {
					$class = ' smushed';
				}
				$html .= $WpSmush->progress_bar();
				$html = $wrapper ? '<div class="smush-wrap' . $class . '">' . $html . '</div>' : $html;

				return $html;
			} else {
				$html .= '<button class="button wp-smush-nextgen-send" data-id="' . $pid . '">
                    <span>' . $button_txt . '</span>
				</button>';
				echo $html . $WpSmush->progress_bar();
			}
		}

		/**
		 * Print out the progress bar
		 */
		function progress_ui() {
			global $WpSmush, $wpsmushnextgenstats;
			$this->stats = $wpsmushnextgenstats->get_smush_stats();

			// calculate %ages, avoid divide by zero error with no attachments
			if ( $this->total_count > 0 ) {
				$smushed_pc = $this->smushed_count / $this->total_count * 100;
			} else {
				$smushed_pc = 0;
			}
			$bytes   = ! empty( $this->stats['bytes'] ) ? $this->stats['bytes'] : 0;
			$human   = ! empty( $this->stats['human'] ) ? $this->stats['human'] : $WpSmush->format_bytes( $bytes );
			$percent = ! empty( $this->stats['percent'] ) ? number_format_i18n( $this->stats['percent'], 2, '.', '' ) : '';

			$progress_ui = '<div id="progress-ui">';

			// display the progress bars
			$progress_ui .= '<div id="wp-smush-progress-wrap">
                                                <div id="wp-smush-fetched-progress" class="wp-smush-progressbar"><div style="width:' . $smushed_pc . '%"></div></div>
                                                <p id="wp-smush-compression">'
			                . __( "Reduced by ", 'wp-smushit' )
			                . '<span id="human">' . $human . '</span> ( <span id="percent">' . $percent . '</span>% )
                                                </p>
                                        </div>';

			// status divs to show completed count/ total count
			$progress_ui .= '<div id="wp-smush-progress-status">

                            <p id="fetched-status">' .
			                sprintf(
				                __(
					                '<span class="done-count">%d</span> of <span class="total-count">%d</span> total attachments have been smushed', 'wp-smushit'
				                ), $this->smushed_count, $this->total_count
			                ) .
			                '</p>
                                        </div>
				</div>';
			// print it out
			echo $progress_ui;
			wp_nonce_field( 'wp_smush_nextgen', '_wp_smush_nonce', '', true );
		}

		/**
		 * Returns Bulk smush button id and other details, as per if bulk request is already sent or not
		 *
		 * @return array
		 */

		private function button_state( $resmush ) {
			$button = array(
				'cancel' => false,
			);

			$button['text'] = __( 'Bulk Smush Now', 'wp-smushit' );

			//If not resmush and All the images are already smushed
			if ( ! $resmush && $this->smushed_count === $this->total_count ) {
				$button['text']     = __( 'All Done!', 'wp-smushit' );
				$button['class']    = 'wp-smush-finished disabled wp-smush-finished';
				$button['disabled'] = 'disabled';
			} elseif ( $resmush ) {
				$button['class'] = 'wp-smush-button wp-smush-resmush wp-smush-nextgen-bulk';
			} else {
				$button['class'] = 'wp-smush-button wp-smush-nextgen-bulk';
			}

			return $button;
		}

		/**
		 * Display the bulk smushing button
		 *
		 * @param bool $resmush
		 * @param bool $return Whether to echo the button content or echo it
		 *
		 * @return string If return is set to true, return the button content,
		 * else echo it
		 *
		 */
		function setup_button( $resmush = false, $return = false ) {
			$button   = $this->button_state( $resmush );
			$disabled = ! empty( $button['disabled'] ) ? ' disabled="disabled"' : '';
			$content  = '<button class="button button-primary ' . $button['class'] . '" name="smush-all-nextgen" ' . $disabled . '>
				<span>' . $button['text'] . '</span>
			</button>';
			if ( $return ) {
				return $content;
			}
			echo $content;
		}

		/**
		 *
		 */
		function bulk_smush_ui() {
			global $wpsmushnextgenstats;

			$bulk_ui = new WpSmushBulkUi();

			//Set the counts
			$this->total_count     = $wpsmushnextgenstats->total_count();
			$this->smushed_count   = $wpsmushnextgenstats->get_ngg_images( 'smushed', true );
			$this->remaining_count = $wpsmushnextgenstats->get_ngg_images( 'unsmushed', true );
			//Page Header
			$bulk_ui->smush_page_header(); ?>
			<!-- Bulk Smush Progress Bar -->
			<div class="wp-smushit-container-left col-two-third float-l"><?php
				//Bulk Smush Container
				$this->bulk_smush_container( $bulk_ui );
				//Bulk Re Smush Container
				$bulk_ui->bulk_re_smush_container();
				?>
			</div>

			<!-- Stats -->
			<div class="wp-smushit-container-right col-third float-l"><?php
				//Stats
				$this->smush_stats_container( $bulk_ui ); ?>
			</div><!-- End Of Smushit Container right --><?php
			$this->get_nextgen_attachments(); ?>
			</div><?php
			$bulk_ui->smush_page_footer();
		}

		/**
		 * Adds progress bar for ReSmush bulk, if there are any images, that needs to be resmushed
		 */
		function resmush_bulk_ui( $return = false ) {
			global $WpSmush;
			//Check if we need to show it as per the curent settings
			if ( ! $WpSmush->smush_original && $WpSmush->keep_exif && ! $WpSmush->lossy_enabled ) {
				return;
			}

			$count = count( $this->resmush_ids );

			$ss_progress_ui = '<div class="wp-resmush-wrapper"><h4>' . esc_html__( 'Re-Smush Images', 'wp-smushit' ) . '</h4>';
			$ss_progress_ui .= '<p>' . sprintf( esc_html__( 'We found %d attachments that were previously optimised. With the current settings they can be further smushed for more savings.', 'wp-smushit' ), $count ) . '</p>';
			$ss_progress_ui .= '<div id="progress-ui" class="super-smush">';

			// display the progress bars
			$ss_progress_ui .= '<div id="wp-smush-ss-progress-wrap">
			<div id="wp-smush-ss-progress" class="wp-smush-progressbar"><div style="width:0%"></div></div>
			<p id="wp-smush-compression">'
			                   . sprintf(
				                   _n( '<span class="remaining-count">%d</span> attachment left to Re-Smush',
					                   '<span class="remaining-count">%d</span> attachments left to Re-Smush',
					                   $count,
					                   'wp-smushit' ), $count, $count )
			                   . '</p>
                </div>
                </div><!-- End of progress ui -->';

			$ss_progress_ui .= $this->setup_button( true, true ) . '</div>';
			//If need to return the content
			if ( $return ) {
				return $ss_progress_ui;
			}

			echo $ss_progress_ui;
		}

		/**
		 * Outputs the Content for Bulk Smush Div
		 */
		function bulk_smush_content( $bulk_ui ) {
			global $wpsmushit_admin;
			$all_done = $this->smushed_count == $this->total_count;

			//If there are no images in Media Library
			if ( 0 >= $this->total_count ) { ?>
				<span class="wp-smush-no-image tc">
					<img src="<?php echo WP_SMUSH_URL . 'assets/images/upload-images.png'; ?>"
						alt="<?php esc_html_e( "No attachments found - Upload some images", "wp-smushit" ); ?>">
		        </span>
				<p class="wp-smush-no-images-content tc"><?php printf( esc_html__( "We haven’t found any images in your %sgallery%s yet, so there’s no smushing to be done! Once you upload images, reload this page and start playing!", "wp-smushit" ), '<a href="' . esc_url( admin_url('admin.php?page=ngg_addgallery') ) . '">', '</a>' ); ?></p>
				<span class="wp-smush-upload-images tc">
					<a class="button button-cta" href="<?php echo esc_url( admin_url('admin.php?page=ngg_addgallery') ); ?>"><?php esc_html_e( "UPLOAD IMAGES", "wp-smushit" ); ?></a>
				</span><?php
			} else { ?>
				<!-- Hide All done div if there are images pending -->
				<div class="wp-smush-notice wp-smush-all-done<?php echo $all_done ? '' : ' hidden' ?>">
					<i class="dev-icon dev-icon-tick"></i><?php esc_html_e( "You have 0 attachments that need smushing, awesome!", "wp-smushit" ); ?>
				</div>
				<div class="wp-smush-bulk-wrapper <?php echo $all_done ? ' hidden' : ''; ?>"><?php
				//If all the images in media library are smushed
				//Button Text
				$button_content = esc_html__( "BULK SMUSH NOW", "wp-smushit" );
				?>
				<div class="wp-smush-notice wp-smush-remaining">
					<i class="dev-icon">
						<img src="<?php echo WP_SMUSH_URL . 'assets/images/icon-gzip.svg'; ?>" width="14px">
					</i><?php printf( esc_html__( "%s, you have %s%d images%s that needs smushing!", "wp-smushit" ), $wpsmushit_admin->get_user_name(), '<strong>', $this->remaining_count, '</strong>' ); ?>
				</div>
				<hr class="wp-smush-sep">
				<div class="smush-final-log notice notice-warning inline hidden"></div>
				<button type="button" class="wp-smush-button wp-smush-nextgen-bulk"><?php echo $button_content; ?></button><?php

				//Enable Super Smush
				if ( ! $wpsmushit_admin->lossy_enabled ) { ?>
					<p class="wp-smush-enable-lossy"><?php esc_html_e( "Enable Super-smush in the Settings area to get even more savings with almost no noticeable quality loss.", "wp-smushit" ); ?></p><?php
				} ?>
				</div><?php
				$bulk_ui->progress_bar( $this );
			}
		}

		/**
		 * Bulk Smush UI and Progress bar
		 */
		function bulk_smush_container( $bulk_ui ) {
			$smush_individual_msg = sprintf( esc_html__( "Smush individual images via your %sManage Galleries%s section", "wp-smushit" ), '<a href="' . esc_url( admin_url() . 'admin.php?page=nggallery-manage-gallery' ) . '" title="' . esc_html__( 'Manage Galleries', 'wp-smushit' ) . '">', '</a>' );
			$bulk_ui->container_header( 'bulk-smush-wrapper', esc_html__( "BULK SMUSH", "wp-smushit" ), $smush_individual_msg ); ?>
			<div class="box-container"><?php
				$this->bulk_smush_content( $bulk_ui ); ?>
			</div><?php
			echo "</section>";
		}

		/**
		 * Outputs the Smush stats for the site
		 */
		function smush_stats_container( $bulk_ui ) {
			global $wpsmushnextgenstats;

			//NextGen Stats
			$this->stats = $wpsmushnextgenstats->get_smush_stats();

			$bulk_ui->container_header( 'smush-stats-wrapper', esc_html__( "STATS", "wp-smushit" ), '' ); ?>

			<div class="box-content">
				<div class="row smush-total-reduction-percent">
					<span class="float-l wp-smush-stats-label">
						<strong><?php esc_html_e( "TOTAL % REDUCTIONS", "wp-smushit" ); ?></strong>
					</span>
					<span class="float-r wp-smush-stats-wrap">
						<strong>
							<span class="wp-smush-stats"><?php echo $this->stats['percent'] > 0 ? number_format_i18n( $this->stats['percent'], 2, '.', '' ) : 0; ?></span>%
						</strong>
					</span>
				</div>
				<hr>
				<div class="row smush-total-reduction-bytes">
					<span class="float-l wp-smush-stats-label">
						<strong><?php esc_html_e( "TOTAL SIZE REDUCTIONS", "wp-smushit" ); ?></strong>
					</span>
					<span class="float-r wp-smush-stats">
						<strong><?php echo $this->stats['human'] > 0 ? $this->stats['human'] : "0MB"; ?></strong>
					</span>
				</div>
				<hr>
				<div class="row smush-attachments">
					<span class="float-l wp-smush-stats-label">
						<strong><?php esc_html_e( "ATTACHMENTS SMUSHED", "wp-smushit" ); ?></strong>
					</span>
					<span class="float-r wp-smush-stats">
						<strong>
							<span class="smushed-count"><?php echo intval( $this->smushed_count ) . '</span>/' . $this->total_count; ?>
						</strong>
					</span>
				</div>
				<hr>
				<div class="row super-smush-attachments">
					<span class="float-l wp-smush-stats-label">
						<strong><?php esc_html_e( "ATTACHMENTS SUPER-SMUSHED", "wp-smushit" ); ?></strong>
					</span>
					<span class="float-r wp-smush-stats">
						<strong>
							<span class="smushed-count"><?php echo intval( $this->super_smushed ) . '</span>/' . $this->total_count; ?>
						</strong>
					</span>
				</div><?php
				/**
				 * Allows you to output any content within the stats box at the end
				 */
				do_action( 'wp_smush_after_stats' );
				?>
			</div><?php
			echo "</section>";
		}
		/**
		 * Updates the resmush list for NextGen gallery, remove the given id
		 *
		 * @param $attachment_id
		 */
		function update_resmush_list( $attachment_id ) {
			global $wpsmushit_admin;
			$wpsmushit_admin->update_resmush_list( $attachment_id, 'wp-smush-nextgen-resmush-list' );
		}

		/**
		 * Fetch the stats for the given attachment id, and subtract them from Global stats
		 * @param $attachment_id
		 */
		function update_nextgen_stats( $attachment_id ) {
			global $WpSmush;

			if ( empty( $attachment_id ) ) {
				return false;
			}

			$image_id = absint( (int) $attachment_id );

			//Get the absolute path for original image
			$image = $this->get_nextgen_image_from_id( $image_id );

			//Image Meta data
			$metadata = ! empty( $image ) ? $image->meta_data : '';

			$smush_stats = ! empty( $metadata['wp_smush'] ) ? $metadata['wp_smush'] : '';

			if ( empty( $smush_stats ) ) {
				return false;
			}

			$nextgen_stats = get_option( 'wp_smush_stats_nextgen', false );
			if ( ! $nextgen_stats ) {
				return false;
			}

			if ( ! empty( $nextgen_stats['size_before'] ) && ! empty( $nextgen_stats['size_after'] ) && $nextgen_stats['size_before'] > 0 && $nextgen_stats['size_after'] > 0  && $nextgen_stats['size_before'] > $smush_stats['stats']['size_before'] ) {
				$nextgen_stats['size_before'] = $nextgen_stats['size_before'] - $smush_stats['stats']['size_before'];
				$nextgen_stats['size_after']  = $nextgen_stats['size_after'] - $smush_stats['stats']['size_after'];
				$nextgen_stats['bytes']       = $nextgen_stats['size_before'] - $nextgen_stats['size_after'];
				$nextgen_stats['percent']     = ( $nextgen_stats['bytes'] / $nextgen_stats['size_before'] ) * 100;
				$nextgen_stats['human']       = $WpSmush->format_bytes( $nextgen_stats['bytes'] );
			}

			//Update Stats
			update_option( 'wp_smush_stats_nextgen', $nextgen_stats );

		}

	}//End of Class

}//End Of if class not exists