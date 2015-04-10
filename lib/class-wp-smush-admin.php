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
	class WpSmushitAdmin  extends WpSmush{

		/**
		 *
		 * @var array Settings
		 */
		public $settings;

		public $bulk;

		public $total_count;

		public $smushed_count;

		public $stats;

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

			$this->total_count   = $this->total_count();
			$this->smushed_count = $this->smushed_count();
			$this->stats         = $this->global_stats();

			$this->init_settings();

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
			//Register Debug page only if WP_SMUSH_DEBUG is defined and true
			if ( defined( 'WP_SMUSHIT_DEBUG' ) && WP_SMUSHIT_DEBUG ) {
				add_media_page( 'WP Smush Error Log', 'Error Log', 'edit_others_posts', 'wp-smushit-errorlog', array(
					$this,
					'create_admin_error_log_page'
				) );
			}
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
			/* Register our script. */
			wp_register_script( 'wp-smushit-admin-js', WP_SMUSH_URL . 'assets/js/wp-smushit-admin.js', array( 'jquery' ), $WpSmush->version );


			/* Register Style. */
			wp_register_style( 'wp-smushit-admin-css', WP_SMUSH_URL . 'assets/css/wp-smushit-admin.css', array(), $WpSmush->version );
			wp_register_style( 'wp-smushit-sweet-alert', WP_SMUSH_URL . 'assets/css/sweet-alert.css' );

			// localize translatable strings for js
			$this->localize();

			wp_enqueue_script( 'wp-smushit-admin-media-js', WP_SMUSH_URL . 'assets/js/wp-smushit-admin-media.js', array( 'jquery' ), $WpSmush->version );
			wp_enqueue_script( 'wp-smushit-admin-sweetalert-js', WP_SMUSH_URL . 'assets/js/sweet-alert.min.js', array( 'jquery' ) );

		}

		/**
		 * enqueue js and css
		 */
		function enqueue() {
			wp_enqueue_script( 'wp-smushit-admin-js' );
			wp_enqueue_style( 'wp-smushit-admin-css' );
			wp_enqueue_style( 'wp-smushit-sweet-alert' );
		}

		function localize() {
			$bulk   = new WpSmushitBulk();
			$handle = 'wp-smushit-admin-js';

			$wp_smushit_msgs = array(
				'progress'             => __( 'Smushing in Progress', WP_SMUSH_DOMAIN ),
				'done'                 => __( 'All done!', WP_SMUSH_DOMAIN ),
				'something_went_wrong' => __( 'Ops!... something went wrong', WP_SMUSH_DOMAIN ),
				'resmush'              => __( 'Re-smush', WP_SMUSH_DOMAIN ),
				'smush_it'              => __( 'Smush it', WP_SMUSH_DOMAIN ),
				'smush_now'              => __( 'Smush Now', WP_SMUSH_DOMAIN ),
				'sending'              => __( 'Sending ...', WP_SMUSH_DOMAIN )
			);

			wp_localize_script( $handle, 'wp_smushit_msgs', $wp_smushit_msgs );

			//Localize smushit_ids variable, if there are fix number of ids
			$ids = ! empty( $_REQUEST['ids'] ) ? explode( ',', $_REQUEST['ids'] ) : $bulk->get_attachments();

			$data = array(
				'smushed' => $this->get_smushed_image_ids(),
				'unsmushed' => $ids
			);

			wp_localize_script( 'wp-smushit-admin-js', 'wp_smushit_data', $data );

		}

		function admin_enqueue_scripts() {
			wp_enqueue_script( 'wp-smushit-admin-media-js' );
			wp_enqueue_script( 'wp-smushit-admin-sweetalert-js' );
		}

		/**
		 * Translation ready settings
		 */
		function init_settings() {
			$this->settings = array(
				'auto'  => __( 'Auto-Smush images on upload', WP_SMUSH_DOMAIN ),
				'lossy' => __( 'Allow lossy optimization', WP_SMUSH_DOMAIN )
			);
		}

		/**
		 * Display the ui
		 */
		function ui() {
			?>
			<div class="wrap">
				<div id="icon-upload" class="icon32"><br/></div>

				<h2>
					<?php _e( 'WP Smush', WP_SMUSH_DOMAIN ) ?>
				</h2>

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
				update_site_option( $opt_name, $setting );

				// unset the var for next loop
				unset( $setting );
			}

		}

		/**
		 * Bulk Smushing UI
		 */
		function bulk_preview() {

			$bulk = new WpSmushitBulk();
			if ( function_exists( 'apache_setenv' ) ) {
				@apache_setenv( 'no-gzip', 1 );
			}
			@ini_set( 'output_buffering', 'on' );
			@ini_set( 'zlib.output_compression', 0 );
			@ini_set( 'implicit_flush', 1 );

			$attachments = null;
			$auto_start  = false;

			$attachments = $bulk->get_attachments();
			$count       = 0;
			//Check images bigger than 1Mb, used to display the count of images that can't be smushed
			foreach ( $attachments as $attachment ) {
				if ( file_exists( get_attached_file( $attachment ) ) ) {
					$size = filesize( get_attached_file( $attachment ) );
				}
				if ( empty( $size ) || ! ( ( $size / 1048576 ) > 1 ) ) {
					continue;
				}
				$count ++;
			}
			$exceed_mb = '';
			$text      = $count > 1 ? 'are' : 'is';
			if ( $count ) {
				$exceed_mb = sprintf( __( " %d of those images %s <b>over 1Mb</b> and <b>can not be compressed using the free version of the plugin.</b>", WP_SMUSH_DOMAIN ), $count, $text );
			}
			$media_lib = get_admin_url( '', 'upload.php' );
			?>
			<div class="wrap">
				<div id="icon-upload" class="icon32"><br/></div>
				<h3><?php _e( 'Smush in Bulk', WP_SMUSH_DOMAIN ) ?></h3>
				<?php

				if ( $this->total_count < 1 ) {
					_e( "<p>You don't appear to have uploaded any images yet.</p>", WP_SMUSH_DOMAIN );
				} else {
					if ( ! isset( $_POST['smush-all'] ) && ! $auto_start ) { // instructions page ?>

						<hr style="clear: left;"/>

						<style type="text/css">
							.smush-instructions p {
								line-height: 1.2;
								margin: 0 0 4px;
							}
						</style>
						<div class="smush-instructions" style="line-height: 1;">
							<?php printf( __( "<p>We found %d images in your media library. %s </p>", WP_SMUSH_DOMAIN ), sizeof( $attachments ), $exceed_mb ); ?>

							<?php _e( "<p><b style='color: red;'>Please beware</b>, <b>smushing a large number of images can take a long time.</b></p>", WP_SMUSH_DOMAIN ); ?>

							<?php _e( "<p><b>You can not leave this page, until all images have been received back, and you see a success message.</b></p>", WP_SMUSH_DOMAIN ); ?>
							<br/>
						</div>

						<!-- Bulk Smushing -->
						<?php wp_nonce_field( 'wp-smush-bulk', '_wpnonce' ); ?>
						<br/><?php
						$this->progress_ui();
						$this->setup_button();
						_e( "<p><em>N.B. If your server <tt>gzip</tt>s content you may not see the progress updates as your files are processed.</em></p>", WP_SMUSH_DOMAIN );
						if ( WP_SMUSHIT_DEBUG ) {
							_e( "<p>DEBUG mode is currently enabled. To disable uncheck the smushit debug option.</p>", WP_SMUSH_DOMAIN );
						}
					}
				}
				?>
			</div>
		<?php
		}

		function print_loader() {
			?>
			<div id="wp-smush-loader-wrap" class="hidden">
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
			$smushed_pc = $this->smushed_count / $this->total_count * 100;

			$progress_ui = '<div id="progress-ui">';

			// display the progress bars
			$progress_ui .= '<div id="wp-smush-progress-wrap">
                                                <div id="wp-smush-fetched-progress" class="wp-smush-progressbar"><div style="width:' . $smushed_pc . '%"></div></div>
                                                <p id="wp-smush-compression">'
			                . __( "Reduced by ", WP_SMUSH_DOMAIN )
			                . '<span id="human">' . $this->stats['human'] . '</span>( <span id="percent">' . number_format_i18n( $this->stats['percent'], 2, '.', '' ) . '</span>% )
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
				//Free version bulk smush, check the transient counter calue
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

			$WpSmush->resize_from_meta_data( $original_meta, $attachment_id, false );

			$stats = $this->global_stats();

			$stats['smushed'] = $this->smushed_count();
			$stats['total']   = $this->total_count;

			wp_send_json_success( $stats );
		}

		/**
		 * Creates Admin Error Log info page.
		 *
		 * @access private.
		 */
		function create_admin_error_log_page() {
			global $log;
			if ( ! empty( $_GET['action'] ) && 'purge' == @$_GET['action'] ) {
				//Check Nonce
				if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'purge_log' ) ) {
					echo '<div class="error"><p>' . __( 'Nonce verification failed', WP_SMUSH_DOMAIN ) . '</p></div>';
				} else {
					$log->purge_errors();
					$log->purge_notices();
				}
			}
			$errors  = $log->get_all_errors();
			$notices = $log->get_all_notices();
			/**
			 * Error Log Form
			 */
			require_once( WP_SMUSH_DIR . '/lib/error_log.php' );
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

			$WpSmush->resize_from_meta_data( $original_meta, $attachment_id );

			$status = $WpSmush->set_status( $attachment_id, false, true );

			/** Send stats **/
			wp_send_json_success( $status );
		}

		/**
		 * Check bulk sent count, whether to allow further smushing or not
		 *
		 * @return bool
		 */
		function check_bulk_limit() {

			$bulk_sent_count = get_transient( 'bulk_sent_count' );

			//If bulk sent count is not set
			if ( empty( $bulk_sent_count ) ) {

				set_transient( $bulk_sent_count, 1, 120 );

			} elseif ( $bulk_sent_count < 50 ) {

				//If less than 50 images are sent
				set_transient( $bulk_sent_count, $bulk_sent_count + 1, 120 );

			} else {

				//Bulk sent count is set and greater than 50
				return false;

			}

			return true;
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
			?>
			<button id="<?php echo $button['id']; ?>" class="button button-primary wp-smush-button" name="smush-all">
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
						$smush_data['size_before'] += ! empty( $data['stats']['before_size'] ) ? (int) $data['stats']['before_size'] : 0;
						$smush_data['size_after'] += ! empty( $data['stats']['after_size'] ) ? (int) $data['stats']['after_size'] : 0;
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
		 * @param $request_status
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
				$button['text']   = __( 'All done!', WP_SMUSH_DOMAIN );
				$button['id']     = "wp-smush-finished";
				$button['cancel'] = ' disabled="disabled"';

			} else {

				$button['text']   = __( 'Bulk Smush all my images', WP_SMUSH_DOMAIN );
				$button['cancel'] = ' disabled="disabled"';
				$button['id']     = "wp-smush-send";

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
			$opt_val = intval( get_site_option( $opt_name, false ) );

			// return html
			return sprintf(
				"<li><label><input type='checkbox' name='%1\$s' id='%1\$s' value='1' %2\$s>%3\$s</label></li>", esc_attr( $opt_name ), checked( $opt_val, 1, false ), $text
			);
		}

		function get_smushed_image_ids(){
			$args = array(
				'fields'         => 'ids',
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'post_mime_type' => array( 'image/jpeg', 'image/gif', 'image/png' ),
				'order'          => 'ASC',
				'posts_per_page' => - 1,
				'meta_query'     => array(
					array(
						'key'     => 'wp-is-smushed',
						'value'   => '1',
					)
				),
			);
			$query = new WP_Query( $args );
			return $query->posts;
		}
	}

//Add js variables for smushing
	$wpsmushit_admin = new WpSmushitAdmin();
}