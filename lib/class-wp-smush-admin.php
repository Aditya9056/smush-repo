<?php
/**
 * @package WP SmushIt
 * @subpackage Admin
 * @version 1.0
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2014, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmushitAdmin' ) ) {
	/**
	 * Show settings in Media settings and add column to media library
	 *
	 */

	/**
	 * Class WpSmushitAdmin
	 *
	 * @property int $remaining_count
	 * @property int $total_count
	 * @property int $smushed_count
	 * @property int $exceeding_items_count
	 */
	class WpSmushitAdmin extends WpSmush {

		/**
		 *
		 * @var array Settings
		 */
		public $settings;

		public $bulk;

		public $total_count;

		public $smushed_count;

		public $stats;

		public $max_free_bulk = 50; //this is enforced at api level too

		public $upgrade_url = 'https://premium.wpmudev.org/project/wp-smush-pro/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=WP%20Smush%20Upgrade';

		/**
		 * Constructor
		 */
		public function __construct() {

			// hook scripts and styles
			add_action( 'admin_init', array( $this, 'register' ) );

			// hook custom screen
			add_action( 'admin_menu', array( $this, 'screen' ) );

			//Handle Smush Bulk Ajax
			add_action( 'wp_ajax_wp_smushit_bulk', array( $this, 'process_smush_request' ) );


			//Handle Smush Single Ajax
			add_action( 'wp_ajax_wp_smushit_manual', array( $this, 'smush_single' ) );

			add_action( "admin_enqueue_scripts", array( $this, "admin_enqueue_scripts" ) );

			add_filter( 'plugin_action_links_' . WP_SMUSH_BASENAME, array(
				$this,
				'settings_link'
			) );
			add_filter( 'network_admin_plugin_action_links_' . WP_SMUSH_BASENAME, array(
				$this,
				'settings_link'
			) );
			//Attachment status, Grid view
			add_action( 'wp_ajax_attachment_status', array( $this, 'attachment_status' ) );

			// hook into admin footer to load a hidden html/css spinner
			add_action( 'admin_footer-upload.php', array( $this, 'print_loader' ) );

			$this->total_count   = $this->total_count();
			$this->smushed_count = $this->smushed_count();
			$this->stats         = $this->global_stats();

			$this->init_settings();

		}

		function __get($prop){

			if( method_exists("WpSmushitAdmin", $prop ) ){
				return $this->$prop();
			}

			$method_name = "get_" . $prop;
			if( method_exists("WpSmushitAdmin", $method_name  ) ){
				return $this->$method_name();
			}
		}

		/**
		 * Add Bulk option settings page
		 */
		function screen() {
			global $hook_suffix;
			$admin_page_suffix = add_media_page( 'Bulk WP Smush', 'WP Smush', 'edit_others_posts', 'wp-smush-bulk', array(
				$this,
				'ui'
			) );
			// enqueue js only on this screen
			add_action( 'admin_print_scripts-' . $admin_page_suffix, array( $this, 'enqueue' ) );

			// Enqueue js on media screen
			add_action( 'admin_print_scripts-upload.php', array( $this, 'enqueue' ) );
		}

		/**
		 * Register js and css
		 */
		function register() {
			global $WpSmush;
			// Register js for smush utton in grid view
			$current_blog_id       = get_current_blog_id();
			$meta_key              = $current_blog_id == 1 ? 'wp_media_library_mode' : 'wp_' . $current_blog_id . '_media_library_mode';
			$wp_media_library_mode = get_user_meta( get_current_user_id(), $meta_key, true );

			//Either request variable is not empty and grid mode is set, or if request empty then view is as per user choice, or no view is set
			if ( ( ! empty( $_REQUEST['mode'] ) && $_REQUEST['mode'] == 'grid' ) ||
			     ( empty( $_REQUEST['mode'] ) && $wp_media_library_mode != 'list' )
			) {
				wp_register_script( 'wp-smushit-admin-js', WP_SMUSH_URL . 'assets/js/wp-smushit-admin.js', array(
					'jquery',
					'media-views'
				), WP_SMUSH_VERSON );
			} else {
				wp_register_script( 'wp-smushit-admin-js', WP_SMUSH_URL . 'assets/js/wp-smushit-admin.js', array(
					'jquery',
					'underscore'
				), WP_SMUSH_VERSON );
			}


			/* Register Style. */
			wp_register_style( 'wp-smushit-admin-css', WP_SMUSH_URL . 'assets/css/wp-smushit-admin.css', array(), $WpSmush->version );

			// localize translatable strings for js
			$this->localize();

			wp_enqueue_script( 'wp-smushit-admin-media-js', WP_SMUSH_URL . 'assets/js/wp-smushit-admin-media.js', array( 'jquery' ), $WpSmush->version );

		}

		/**
		 * enqueue js and css
		 */
		function enqueue() {
			wp_enqueue_script( 'wp-smushit-admin-js' );
			wp_enqueue_style( 'wp-smushit-admin-css' );
		}


		function localize() {
			$bulk   = new WpSmushitBulk();
			$handle = 'wp-smushit-admin-js';

			if ( $this->is_premium() ||  $this->remaining_count <= $this->max_free_bulk ) {
				$bulk_now = __( 'Bulk Smush Now', WP_SMUSH_DOMAIN );
			} else {
				$bulk_now = sprintf( __( 'Bulk Smush %d Attachments', WP_SMUSH_DOMAIN ),  $this->max_free_bulk);
			}

			$wp_smush_msgs = array(
				'progress'             => __( 'Smushing in Progress', WP_SMUSH_DOMAIN ),
				'done'                 => __( 'All Done!', WP_SMUSH_DOMAIN ),
				'bulk_now'             => $bulk_now,
				'something_went_wrong' => __( 'Ops!... something went wrong', WP_SMUSH_DOMAIN ),
				'resmush'              => __( 'Re-smush', WP_SMUSH_DOMAIN ),
				'smush_it'             => __( 'Smush it', WP_SMUSH_DOMAIN ),
				'smush_now'            => __( 'Smush Now', WP_SMUSH_DOMAIN ),
				'sending'              => __( 'Sending ...', WP_SMUSH_DOMAIN ),
				"error_in_bulk"        => __( '{{errors}} image(s) were skipped due to an error.', WP_SMUSH_DOMAIN)
			);

			wp_localize_script( $handle, 'wp_smush_msgs', $wp_smush_msgs );

			//Localize smushit_ids variable, if there are fix number of ids
			$ids = ! empty( $_REQUEST['ids'] ) ? explode( ',', $_REQUEST['ids'] ) : $bulk->get_attachments();

			$data = array(
				'smushed'   => $this->get_smushed_image_ids(),
				'unsmushed' => $ids
			);

			wp_localize_script( 'wp-smushit-admin-js', 'wp_smushit_data', $data );

		}

		function admin_enqueue_scripts() {
			wp_enqueue_script( 'wp-smushit-admin-media-js' );
		}

		/**
		 * Translation ready settings
		 */
		function init_settings() {
			$this->settings = array(
				'auto'   => __( 'Auto-Smush images on upload', WP_SMUSH_DOMAIN ),
				'lossy'  => __( 'Super-Smush images', WP_SMUSH_DOMAIN ) . ' <small>(' . __( 'lossy optimization', WP_SMUSH_DOMAIN ) . ')</small>',
				'backup' => __( 'Backup original images', WP_SMUSH_DOMAIN ) . ' <small>(' . __( 'this will nearly double the size of your uploads directory', WP_SMUSH_DOMAIN ) . ')</small>'
			);
		}

		/**
		 * Display the ui
		 */
		function ui() {
			?>
			<div class="wrap">

				<h2>
					<?php
					if ( $this->is_premium() ) {
						_e( 'WP Smush Pro', WP_SMUSH_DOMAIN );
					} else {
						_e( 'WP Smush', WP_SMUSH_DOMAIN );
					} ?>
				</h2>

				<?php if ( $this->is_premium() ) { ?>
					<div class="wp-smpushit-features updated">
						<h3><?php _e( 'Thanks for using WP Smush Pro! You now can:', WP_SMUSH_DOMAIN ) ?></h3>
						<ol>
							<li><?php _e( '"Super-Smush" your images with our intelligent multi-pass lossy compression. Get &gt;60% average compression with almost no noticeable quality loss!', WP_SMUSH_DOMAIN ); ?></li>
							<li><?php _e( 'Get the best lossless compression. We try multiple methods to squeeze every last byte out of your images.', WP_SMUSH_DOMAIN ); ?></li>
							<li><?php _e( 'Smush images up to 8MB.', WP_SMUSH_DOMAIN ); ?></li>
							<li><?php _e( 'Bulk smush ALL your images with one click!', WP_SMUSH_DOMAIN ); ?></li>
							<li><?php _e( 'Keep a backup of your original un-smushed images in case you want to restore later.', WP_SMUSH_DOMAIN ); ?></li>
						</ol>
					</div>
				<?php } else { ?>
					<div class="wp-smpushit-features error">
						<h3><?php _e( 'Upgrade to WP Smush Pro to:', WP_SMUSH_DOMAIN ) ?></h3>
						<ol>
							<li><?php _e( '"Super-Smush" your images with our intelligent multi-pass lossy compression. Get &gt;60% average compression with almost no noticeable quality loss!', WP_SMUSH_DOMAIN ); ?></li>
							<li><?php _e( 'Get the best lossless compression. We try multiple methods to squeeze every last byte out of your images.', WP_SMUSH_DOMAIN ); ?></li>
							<li><?php _e( 'Smush images greater than 1MB.', WP_SMUSH_DOMAIN ); ?></li>
							<li><?php _e( 'Bulk smush ALL your images with one click! No more rate limiting.', WP_SMUSH_DOMAIN ); ?></li>
							<li><?php _e( 'Keep a backup of your original un-smushed images in case you want to restore later.', WP_SMUSH_DOMAIN ); ?></li>
							<li><?php _e( 'Access 24/7/365 support from <a href="https://premium.wpmudev.org/support/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=WP%20Smush%20Upgrade">the best WordPress support team on the planet</a>.', WP_SMUSH_DOMAIN ); ?></li>
							<li><?php _e( 'Download <a href="https://premium.wpmudev.org/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=WP%20Smush%20Upgrade">350+ other premium plugins and themes</a> included in your membership.', WP_SMUSH_DOMAIN ); ?></li>
						</ol>
						<p><a class="button-primary" href="<?php echo $this->upgrade_url; ?>"><?php _e( 'Upgrade Now &raquo;', WP_SMUSH_DOMAIN ); ?></a></p>

						<p><?php _e( 'Already upgraded to a WPMU DEV membership? Install and Login to our Dashboard plugin to enable Smush Pro features.', WP_SMUSH_DOMAIN ); ?></p>
						<p>
							<?php
							if ( ! class_exists( 'WPMUDEV_Dashboard' ) ) {
								if ( file_exists( WP_PLUGIN_DIR . '/wpmudev-updates/update-notifications.php' ) ) {
									$function = is_multisite() ? 'network_admin_url' : 'admin_url';
									$url      = wp_nonce_url( $function( 'plugins.php?action=activate&plugin=wpmudev-updates%2Fupdate-notifications.php' ), 'activate-plugin_wpmudev-updates/update-notifications.php' );
									?><a class="button-secondary"
									     href="<?php echo $url; ?>"><?php _e( 'Activate WPMU DEV Dashboard', WP_SMUSH_DOMAIN ); ?></a><?php
								} else { //dashboard not installed at all
									?><a class="button-secondary" target="_blank"
									     href="https://premium.wpmudev.org/project/wpmu-dev-dashboard/"><?php _e( 'Install WPMU DEV Dashboard', WP_SMUSH_DOMAIN ); ?></a><?php
								}
							}
							?>
						</p>
					</div>
				<?php } ?>


				<div class="wp-smpushit-container">
					<h3>
						<?php _e( 'Settings', WP_SMUSH_DOMAIN ) ?>
					</h3>
					<?php
					// display the options
					$this->options_ui();

					//Bulk Smushing
					$this->bulk_preview();
					?>
				</div>
			</div>
			<?php
			$this->print_loader();
		}

		/**
		 * Process and display the options form
		 */
		function options_ui() {

			// Save settings, if needed
			$this->process_options();

			?>
			<form action="" method="post">

				<ul id="wp-smush-options-wrap">
					<?php
					// display each setting
					foreach ( $this->settings as $name => $text ) {
						echo $this->render_checked( $name, $text );
					}
					?>
				</ul><?php
				// nonce
				wp_nonce_field( 'save_wp_smush_options', 'wp_smush_options_nonce' );
				?>
				<input type="submit" id="wp-smush-save-settings" class="button button-primary" value="<?php _e( 'Save Changes', WP_SMUSH_DOMAIN ); ?>">
			</form>
		<?php
		}

		/**
		 * Check if form is submitted and process it
		 *
		 * @return null
		 */
		function process_options() {
			// we aren't saving options
			if ( ! isset( $_POST['wp_smush_options_nonce'] ) ) {
				return;
			}
			// the nonce doesn't pan out
			if ( ! wp_verify_nonce( $_POST['wp_smush_options_nonce'], 'save_wp_smush_options' ) ) {
				return;
			}
			// var to temporarily assign the option value
			$setting = null;

			// process each setting and update options
			foreach ( $this->settings as $name => $text ) {
				// formulate the index of option
				$opt_name = WP_SMUSH_PREFIX . $name;

				// get the value to be saved
				$setting = isset( $_POST[ $opt_name ] ) ? 1 : 0;

				// update the new value
				update_option( $opt_name, $setting );

				// unset the var for next loop
				unset( $setting );
			}

		}

		/**
		 * Returns number of images of larger than 1Mb size
		 *
		 * @return int
		 */
		function get_exceeding_items_count(){
			$count = 0;
			$bulk = new WpSmushitBulk();
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
		 * Bulk Smushing UI
		 */
		function bulk_preview() {

			$exceed_mb = '';
			if ( ! $this->is_premium() ) {

				if ( $this->exceeding_items_count ) {
					$exceed_mb = sprintf(
						_n( "%d image is over 1MB so will be skipped using the free version of the plugin.",
							"%d images are over 1MB so will be skipped using the free version of the plugin.", $this->exceeding_items_count, WP_SMUSH_DOMAIN ),
						$this->exceeding_items_count
					);
				}
			}
			?>
			<hr>
			<div class="bulk-smush">
				<h3><?php _e( 'Smush in Bulk', WP_SMUSH_DOMAIN ) ?></h3>
				<?php

				if ( $this->remaining_count == 0  ) {
					?>
					<p><?php _e( "Congratulations, all your images are currently Smushed!", WP_SMUSH_DOMAIN ); ?></p>
					<?php
					$this->progress_ui();
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

						<?php if ( ! $this->is_premium() ) { ?>
							<p class="error">
								<?php printf( __( "Free accounts are limited to bulk smushing %d attachments per request. You will need to click to start a new bulk job after each %d attachments.", WP_SMUSH_DOMAIN ), $this->max_free_bulk, $this->max_free_bulk ); ?>
								<a href="<?php echo $this->upgrade_url; ?>"><?php _e( 'Remove limits &raquo;', WP_SMUSH_DOMAIN ); ?></a>
							</p>
						<?php } ?>


					</div>

					<!-- Bulk Smushing -->
					<?php wp_nonce_field( 'wp-smush-bulk', '_wpnonce' ); ?>
					<br/><?php
					$this->progress_ui();
					?>
					<p class="smush-final-log"></p>
					<?php
					$this->setup_button();
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
			</div>
		<?php
		}

		function print_loader() {
			?>
			<div class="wp-smush-loader-wrap hidden" >
				<div class="floatingCirclesG">
					<div class="f_circleG" id="frotateG_01">
					</div>
					<div class="f_circleG" id="frotateG_02">
					</div>
					<div class="f_circleG" id="frotateG_03">
					</div>
					<div class="f_circleG" id="frotateG_04">
					</div>
					<div class="f_circleG" id="frotateG_05">
					</div>
					<div class="f_circleG" id="frotateG_06">
					</div>
					<div class="f_circleG" id="frotateG_07">
					</div>
					<div class="f_circleG" id="frotateG_08">
					</div>
				</div>
			</div>
		<?php
		}

		/**
		 * Print out the progress bar
		 */
		function progress_ui() {

			// calculate %ages
			if ( $this->total_count > 0 ) //avoid divide by zero error with no attachments
				$smushed_pc = $this->smushed_count / $this->total_count * 100;
			else
				$smushed_pc = 0;

			$progress_ui = '<div id="progress-ui">';

			// display the progress bars
			$progress_ui .= '<div id="wp-smush-progress-wrap">
                                                <div id="wp-smush-fetched-progress" class="wp-smush-progressbar"><div style="width:' . $smushed_pc . '%"></div></div>
                                                <p id="wp-smush-compression">'
			                . __( "Reduced by ", WP_SMUSH_DOMAIN )
			                . '<span id="human">' . $this->stats['human'] . '</span> ( <span id="percent">' . number_format_i18n( $this->stats['percent'], 2, '.', '' ) . '</span>% )
                                                </p>
                                        </div>';

			// status divs to show completed count/ total count
			$progress_ui .= '<div id="wp-smush-progress-status">

                            <p id="fetched-status">' .
			                sprintf(
				                __(
					                '<span class="done-count">%d</span> of <span class="total-count">%d</span> total attachments have been smushed', WP_SMUSH_DOMAIN
				                ), $this->smushed_count, $this->total_count
			                ) .
			                '</p>
                                        </div>
				</div>';
			// print it out
			echo $progress_ui;
		}

		function aprogress_ui() {
			$bulk  = new WpSmushitBulk;
			$total = count( $bulk->get_attachments() );
			$total = $total ? $total : 1; ?>

			<div id="progress-ui">
				<div id="smush-status" style="margin: 0 0 5px;"><?php printf( __( 'Smushing <span id="smushed-count">1</span> of <span id="smushing-total">%d</span>', WP_SMUSH_DOMAIN ), $total ); ?></div>
				<div id="wp-smushit-progress-wrap">
					<div id="wp-smushit-smush-progress" class="wp-smushit-progressbar">
						<div></div>
					</div>
				</div>
			</div> <?php
		}

		/**
		 * Processes the Smush request and sends back the next id for smushing
		 */
		function process_smush_request() {

			global $WpSmush;

			$should_continue = true;
			$is_premium      = false;

			if ( empty( $_REQUEST['attachment_id'] ) ) {
				wp_send_json_error( 'missing id' );
			}

			//if not premium
			$is_premium = $WpSmush->is_premium();

			if ( ! $is_premium ) {
				//Free version bulk smush, check the transient counter value
				$should_continue = $this->check_bulk_limit();
			}

			//If the bulk smush needs to be stopped
			if ( ! $should_continue ) {
				wp_send_json_error(
					array(
						'error'    => 'bulk_request_image_limit_exceeded',
						'continue' => false
					)
				);
			}

			$attachment_id = $_REQUEST['attachment_id'];

			$original_meta = wp_get_attachment_metadata( $attachment_id, true );

			$smush = $WpSmush->resize_from_meta_data( $original_meta, $attachment_id, false );

			$stats = $this->global_stats();

			$stats['smushed'] = $this->smushed_count();
			$stats['total']   = $this->total_count;

			if( is_wp_error( $smush ) ) {
				wp_send_json_error( $stats );
			} else {
				wp_send_json_success( $stats );
			}
		}

		/**
		 * Smush single images
		 *
		 * @return mixed
		 */
		function smush_single() {
			if ( ! current_user_can( 'upload_files' ) ) {
				wp_die( __( "You don't have permission to work with uploaded files.", WP_SMUSH_DOMAIN ) );
			}

			if ( ! isset( $_GET['attachment_id'] ) ) {
				wp_die( __( 'No attachment ID was provided.', WP_SMUSH_DOMAIN ) );
			}

			global $WpSmush;

			$attachment_id = intval( $_GET['attachment_id'] );

			$original_meta = wp_get_attachment_metadata( $attachment_id );

			$smush = $WpSmush->resize_from_meta_data( $original_meta, $attachment_id );

			$status = $WpSmush->set_status( $attachment_id, false, true );

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

		/**
		 * Check bulk sent count, whether to allow further smushing or not
		 *
		 * @return bool
		 */
		function check_bulk_limit() {

			$transient_name = WP_SMUSH_PREFIX . 'bulk_sent_count';
			$bulk_sent_count = get_transient( $transient_name );

			//If bulk sent count is not set
			if ( false === $bulk_sent_count ) {

				//start transient at 0
				set_transient( $transient_name, 1, 60 );
				return true;

			} else if ( $bulk_sent_count < $this->max_free_bulk ) {

				//If lte $this->max_free_bulk images are sent, increment
				set_transient( $transient_name, $bulk_sent_count + 1, 60 );
				return true;

			} else { //Bulk sent count is set and greater than $this->max_free_bulk

				//clear it and return false to stop the process
				set_transient( $transient_name, 0, 60 );
				return false;

			}
		}

		/**
		 * The UI for bulk smushing
		 *
		 * @return null
		 */
		function all_ui( $send_ids ) {

			// if there are no images in the media library
			if ( $this->total_count < 1 ) {
				printf(
					__(
						'<p>Please <a href="%s">upload some images</a>.</p>', WP_SMUSH_DOMAIN
					), admin_url( 'media-new.php' )
				);

				// no need to print out the rest of the UI
				return;
			}

			// otherwise, start displaying the UI
			?>
			<div id="all-bulk" class="wp-smush-bulk-wrap">
				<?php
				// everything has been smushed, display a notice
				if ( $this->smushed_count === $this->total_count ) {
					?>
					<p>
						<?php
						_e( 'All your images are already smushed!', WP_SMUSH_DOMAIN );
						?>
					</p>
				<?php
				} else {
					$this->selected_ui( $send_ids, '' );
					// we have some smushing to do! :)
					// first some warnings
					?>
					<p>
						<?php
						// let the user know that there's an alternative
						printf( __( 'You can also smush images individually from your <a href="%s">Media Library</a>.', WP_SMUSH_DOMAIN ), admin_url( 'upload.php' ) );
						?>
					</p>
				<?php
				}

				// display the progress bar
				$this->progress_ui();

				// display the appropriate button
				$this->setup_button();

				?>
			</div>
		<?php
		}

		/**
		 * Total Image count
		 * @return int
		 */
		function total_count() {
			$query   = array(
				'fields'         => 'ids',
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'post_mime_type' => array( 'image/jpeg', 'image/gif', 'image/png' ),
				'order'          => 'ASC',
				'posts_per_page' => - 1
			);
			$results = new WP_Query( $query );
			$count   = ! empty( $results->post_count ) ? $results->post_count : 0;

			// send the count
			return $count;
		}

		/**
		 * Optimised images count
		 *
		 * @param bool $return_ids
		 *
		 * @return array|int
		 */
		function smushed_count( $return_ids = false ) {
			$query = array(
				'fields'         => 'ids',
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'post_mime_type' => array( 'image/jpeg', 'image/gif', 'image/png' ),
				'order'          => 'ASC',
				'posts_per_page' => - 1,
				'meta_key'       => 'wp-smpro-smush-data'
			);

			$results = new WP_Query( $query );
			if ( ! $return_ids ) {
				$count = ! empty( $results->post_count ) ? $results->post_count : 0;
			} else {
				return $results->posts;
			}

			// send the count
			return $count;
		}

		/**
		 * Returns remaining count
		 *
		 * @return int
		 */
		function remaining_count(){
			return $this->total_count - $this->smushed_count;
		}

		/**
		 * Display Thumbnails, if bulk action is choosen
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
							'<strong>%d of %d images</strong> were sent for smushing:',
							WP_SMUSH_DOMAIN
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
		 * Display the bulk smushing button
		 *
		 * @todo Add the API status here, next to the button
		 */
		function setup_button() {
			$button = $this->button_state();
			$disabled = !empty($button['disabled']) ? ' disabled="disabled"' : '';
			?>
			<button class="button button-primary<?php echo ' ' . $button['class']; ?>" name="smush-all" <?php echo $disabled; ?>>
				<span><?php echo $button['text'] ?></span>
			</button>
		<?php
		}

		function global_stats() {

			global $wpdb, $WpSmush;

			$sql = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key=%s";

			$global_data = $wpdb->get_col( $wpdb->prepare( $sql, "wp-smpro-smush-data" ) );

			$smush_data = array(
				'size_before' => 0,
				'size_after'  => 0,
				'percent'     => 0,
				'human'       => 0
			);

			if ( ! empty( $global_data ) ) {
				foreach ( $global_data as $data ) {
					$data = maybe_unserialize( $data );
					if ( ! empty( $data['stats'] ) ) {
						$smush_data['size_before'] += ! empty( $data['stats']['size_before'] ) ? (int) $data['stats']['size_before'] : 0;
						$smush_data['size_after'] += ! empty( $data['stats']['size_after'] ) ? (int) $data['stats']['size_after'] : 0;
					}
				}
			}

			$smush_data['bytes'] = $smush_data['size_before'] - $smush_data['size_after'];

			if ( $smush_data['bytes'] < 0 ) {
				$smush_data['bytes'] = 0;
			}

			if ( $smush_data['size_before'] > 0 ) {
				$smush_data['percent'] = ( $smush_data['bytes'] / $smush_data['size_before'] ) * 100;
			}

			//Round off precentage
			$smush_data['percent'] = round( $smush_data['percent'], 2 );

			$smush_data['human'] = $WpSmush->format_bytes( $smush_data['bytes'] );

			return $smush_data;
		}

		/**
		 * Returns Bulk smush button id and other details, as per if bulk request is already sent or not
		 *
		 * @return array
		 */

		private function button_state() {
			$button = array(
				'cancel' => false,
			);


			// if we have nothing left to smush
			// disable the buttons
			if ( $this->smushed_count === $this->total_count ) {
				$button['text']     = __( 'All Done!', WP_SMUSH_DOMAIN );
				$button['class']    = 'wp-smush-finished disabled wp-smush-finished';
				$button['disabled'] = 'disabled';

			} else if ( $this->is_premium() || $this->remaining_count <= $this->max_free_bulk ) { //if premium or under limit

				$button['text']  = __( 'Bulk Smush Now', WP_SMUSH_DOMAIN );
				$button['class'] = 'wp-smush-button wp-smush-send';

			} else { //if not premium and over limit
				$button['text']  = sprintf( __( 'Bulk Smush %d Attachments', WP_SMUSH_DOMAIN ),  $this->max_free_bulk );
				$button['class'] = 'wp-smush-button wp-smush-send';

			}

			return $button;
		}

		/**
		 * Render a checkbox
		 *
		 * @param string $key The setting's name
		 *
		 * @return string checkbox html
		 */
		function render_checked( $key, $text ) {
			// the key for options table
			$opt_name = WP_SMUSH_PREFIX . $key;

			// default value
			$opt_val = get_option( $opt_name, false );

			//If value is not set for auto smushing set it to 1
			if ( $key == 'auto' && $opt_val === false ) {
				$opt_val = 1;
			}

			//disable lossy for non-premium members
			$disabled = '';
			if ( ( 'lossy' == $key || 'backup' == $key ) && ! $this->is_premium() ) {
				$disabled = ' disabled';
				$opt_val = 0;
			}

			// return html
			return sprintf(
				"<li><label><input type='checkbox' name='%1\$s' id='%1\$s' value='1' %2\$s %3\$s>%4\$s</label></li>", esc_attr( $opt_name ), checked( $opt_val, 1, false ), $disabled, $text
			);
		}

		function get_smushed_image_ids() {
			$args  = array(
				'fields'         => 'ids',
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'post_mime_type' => array( 'image/jpeg', 'image/gif', 'image/png' ),
				'order'          => 'ASC',
				'posts_per_page' => - 1,
				'meta_query'     => array(
					array(
						'key'   => 'wp-is-smushed',
						'value' => '1',
					)
				),
			);
			$query = new WP_Query( $args );

			return $query->posts;
		}

		/**
		 * Get the smush button text for attachment
		 */
		function smush_status( $id ) {
			$response = trim( $this->set_status( $id, false ) );

			return $response;
		}

		/**
		 * Returns the image smush status, called by grid view ajax
		 */
		function attachment_status() {
			$id          = $_REQUEST['id'];
			$status_text = $this->smush_status( $id );
			wp_send_json_success( $status_text );
			die();
		}

		/**
		 * Adds a smushit pro settings link on plugin page
		 *
		 * @param $links
		 *
		 * @return array
		 */
		function settings_link( $links ) {

			$settings_page = admin_url( 'upload.php?page=wp-smush-bulk' );
			$settings      = '<a href="' . $settings_page . '">' . __( 'Settings', WP_SMUSH_DOMAIN ) . '</a>';

			array_unshift( $links, $settings );

			return $links;
		}
	}

	$wpsmushit_admin = new WpSmushitAdmin();
}
