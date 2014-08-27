<?php
/**
 * @package SmushItPro
 * @subpackage Admin
 * @version 1.0
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2014, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmProAdmin' ) ) {

	/**
	 * Show settings in Media settings and add column to media library
	 *
	 */
	class WpSmProAdmin {

		/**
		 *
		 * @var array Settings
		 */
		public $settings;

		/**
		 *
		 * @var array Assorted counts for bulk smushing
		 */
		public $bulk;

		/**
		 *
		 * @var boolean API conectivity status
		 */
		public $api_connected;

		/**
		 * Constructor
		 */
		public function __construct() {

			// set up image counts for progressive smushing
			$this->bulk_data();

			// hook scripts and styles
			add_action( 'admin_init', array( $this, 'register' ) );

			// hook custom screen
			add_action( 'admin_menu', array( $this, 'screen' ) );

			// hook into Heartbeat API to check smush progress
			add_filter( 'heartbeat_received', array( $this, 'refresh_progress' ), 10, 3 );

			// hook ajax call for checking smush status
			add_action( 'wp_ajax_wp_smpro_check', array( $this, 'check_status' ) );

			// hook ajax call to reset counts
			add_action( 'wp_ajax_wp_smpro_reset', array( $this, 'reset_count' ) );

			// hook into admin footer to load a hidden html/css spinner
			add_action( 'admin_footer-upload.php', array( $this, 'print_spinner' ) );

			add_filter( 'plugin_action_links_' . WP_SMPRO_BASENAME, array(
				$this,
				'wp_smushit_pro_settings'
			) );
			add_filter( 'network_admin_plugin_action_links_' . WP_SMPRO_BASENAME, array(
				$this,
				'wp_smushit_pro_settings'
			) );

			// initialise translation ready settings and titles
			$this->init_settings();

			// instantiate Media Library mods
			$media_lib = new WpSmProMediaLibrary();
		}

		/**
		 * Translation ready settings
		 */
		function init_settings() {
			$this->settings = array(
				'auto'        => __( 'Auto-Smush images on upload', WP_SMPRO_DOMAIN ),
				'remove_meta' => __( 'Remove EXIF data from JPEGs', WP_SMPRO_DOMAIN ),
				'progressive' => __( 'Progressive optimization for JPEGs', WP_SMPRO_DOMAIN ),
				'gif_to_png'  => __( 'Convert GIF to PNG', WP_SMPRO_DOMAIN ),
			);
		}

		/**
		 * Add Bulk option settings page
		 */
		function screen() {
			global $admin_page_suffix;
			$admin_page_suffix = add_media_page( 'WP Smush.it Pro', 'WP Smush.it Pro', 'edit_others_posts', 'wp-smpro-admin', array(
				$this,
				'ui'
			) );

			// enqueue js only on this screen and media_screen
			add_action( 'admin_print_scripts-upload.php', array( $this, 'enqueue' ) );
			add_action( 'admin_print_scripts-' . $admin_page_suffix, array( $this, 'enqueue' ) );
		}

		/**
		 * Register js and css
		 */
		function register() {

			// register js
			wp_register_script( 'wp-smpro-queue', WP_SMPRO_URL . 'assets/js/wp-smpro-queue.js', array( 'jquery' ), WP_SMPRO_VERSION );

			// register css
			wp_register_style( 'wp-smpro-queue', WP_SMPRO_URL . 'assets/css/wp-smpro-queue.css', array(), WP_SMPRO_VERSION );

			// localize translatable strings for js
			$this->localize();

			//Set API Status
			$this->api_connected = $this->set_api_status();
		}

		/**
		 * enqueue js and css
		 */
		function enqueue() {
			wp_enqueue_script( 'wp-smpro-queue' );
			wp_enqueue_style( 'wp-smpro-queue' );
		}

		/**
		 * Localise translatable strings for JS use on bulk UI
		 * Also localise initial counts
		 */
		function localize() {
			$wp_smpro_msgs = array(
				'leave_screen'   => __( 'You may leave this screen now, <strong>we will update your site with smushed images, automatically</strong>!', WP_SMPRO_DOMAIN ),
				'throttled'      => __( 'You have reached the limit of 50 images at a time. Please try after some time.', WP_SMPRO_DOMAIN ),
                                'sent'           => __( 'Sent for Smushing', WP_SMPRO_DOMAIN ),
				'progress'       => __( 'Smushing in Progress', WP_SMPRO_DOMAIN ),
				'resmush'        => __( 'Re-smush', WP_SMPRO_DOMAIN ),
				'smush_now'      => __( 'Smush.it now!', WP_SMPRO_DOMAIN ),
				'done'           => __( 'All done!', WP_SMPRO_DOMAIN ),
				'smush_all'      => __( 'Smush all images', WP_SMPRO_DOMAIN ),
				'resmush_all'    => __( 'Resend unsmushed images', WP_SMPRO_DOMAIN ),
                                'no_leave'       => __( 'Please <strong>do not leave the screen</strong> till all the images have been sent for smushing.', WP_SMPRO_DOMAIN ),
				'refresh_screen' => sprintf(
					__(
						'New images were uploaded, please <a href="%s">refresh this page</a> to smush them properly.',
						WP_SMPRO_DOMAIN
					),
					admin_url( 'upload.php?page=wp-smpro-admin' )
				),
			);

			wp_localize_script( 'wp-smpro-queue', 'wp_smpro_msgs', $wp_smpro_msgs );

			// localise counts
			wp_localize_script( 'wp-smpro-queue', 'wp_smpro_counts', $this->bulk );
		}

		/**
		 * Set up some data needed for the bulk ui
		 */

		function bulk_data() {

			$bulk                   = new WpSmProBulk();
			$this->bulk['sent']     = $bulk->data( 'sent' );
			$this->bulk['received'] = $bulk->data( 'received' );
			$this->bulk['smushed']  = $bulk->data( 'smushed' );
                        $this->bulk['stats']    = get_option('wp-smpro-global-stats', array());
		}

		/**
		 * Refresh all the counts
		 * @return array the counts
		 */
		function refresh_counts() {
			$this->bulk_data();

			return $this->bulk;
		}

		/**
		 * Display the ui
		 */
		function ui() {

			//Get dashboard API Key
			$wpmudev_apikey = get_site_option( 'wpmudev_apikey' );

			$class = $this->api_connected ? ' connected' : ' not-connected';
			$text  = $this->api_connected ? __( 'API Connected', WP_SMPRO_DOMAIN ) : __( 'API Not Connected', WP_SMPRO_DOMAIN );

			//Style for container if there is no API key
			$style = '';
			if ( empty( $wpmudev_apikey ) ) {

				$style = 'style="opacity: 0.4;"';
				?>

				<script type="text/javascript">

					jQuery(document).ready(function () {

						//Disable all inputs
						jQuery('.wp-smpro-container input').attr('disabled', 'disabled');

					});

				</script> <?php
			}
			?>
			<div class="wrap">
				<div id="icon-upload" class="icon32"><br/></div>

				<h2>
					<?php printf( __( 'WP Smush.it Pro <span title="%s" class="api-status%s">%s</span><span class="api-status-text">%s</span>', WP_SMPRO_DOMAIN ), $text, $class, $text, $text ); ?>
				</h2>

				<div class="wp-smpro-container" <?php echo $style; ?>>
					<h3>
						<?php _e( 'Settings', WP_SMPRO_DOMAIN ) ?>
					</h3>
					<?php
					// display the options
					$this->options_ui();
					?>

					<hr>
					<h3>
						<?php _e( 'Smush in Bulk', WP_SMPRO_DOMAIN ) ?>
					</h3>

					<?php
					// display the bulk ui
					$this->bulk_ui();
					?>
				</div>
			</div>
		<?php
		}

		/**
		 * Process and display the options form
		 */
		function options_ui() {

			// process options, if needed
			$this->process_options();
			?>
			<form action="" method="post">
				<ul id="wp-smpro-options-wrap">
					<?php
					// display each setting
					foreach ( $this->settings as $name => $text ) {
						echo $this->render_checked( $name, $text );
					}
					?>
				</ul>
				<?php
				// nonce
				wp_nonce_field( 'save_wp_smpro_options', 'wp_smpro_options_nonce' );
				?>
				<input type="submit" id="wp-smpro-save-settings" class="button button-primary" value="<?php _e( 'Save Changes', WP_SMPRO_DOMAIN ); ?>">
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
			if ( ! isset( $_POST['wp_smpro_options_nonce'] ) ) {
				return;
			}
			// the nonce doesn't pan out
			if ( ! wp_verify_nonce( $_POST['wp_smpro_options_nonce'], 'save_wp_smpro_options' ) ) {
				return;
			}

			// var to temporarily assign the option value
			$setting = null;

			// process each setting and update options
			foreach ( $this->settings as $name => $text ) {
				// formulate the index of option
				$opt_name = WP_SMPRO_PREFIX . $name;
				// get the value to be saved
				$setting = isset( $_POST[ $opt_name ] ) ? true : false;
				// update the new value
				update_option( $opt_name, $setting );
				// unset the var for next loop
				unset( $setting );
			}
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
			$opt_name = WP_SMPRO_PREFIX . $key;

			// the defined constant
			$const_name = strtoupper( $opt_name );

			// default value
			$opt_val = intval( get_option( $opt_name, constant( $const_name ) ) );

			// return html
			return sprintf(
				"<li><label><input type='checkbox' name='%1\$s' id='%1\$s' value='1' %2\$s>%3\$s</label></li>", esc_attr( $opt_name ), checked( $opt_val, true, false ), $text
			);
		}

		/**
		 * Display the bulk smushing ui
		 */
		function bulk_ui() {
			// set up some variables and print out some js vars
			$this->all_ui();
			// print the spinner for UI use
			$this->print_spinner();
			?>
		<?php
		}


		/**
		 * The UI for bulk smushing
		 *
		 * @return null
		 */
		function all_ui() {

			// if there are no images in the media library
			if ( $this->bulk['smushed']['total'] < 1 ) {
				printf(
					__(
						'<p>Please <a href="%s">upload some images</a>.</p>',
						WP_SMPRO_DOMAIN
					),
					admin_url( 'media-new.php' )
				);

				// no need to print out the rest of the UI
				return;
			}

			// otherwise, start displaying the UI
			?>
			<div id="all-bulk" class="wp-smpro-bulk-wrap">
				<?php
				// everything has been smushed, display a notice
				if ( $this->bulk['smushed']['left'] === 0 ) {
					?>
					<p>
						<?php
						_e( 'All the images are already smushed', WP_SMPRO_DOMAIN );
						?>
					</p>
				<?php
				} else {
					// we have some smushing to do! :)

					// first some warnings
					?>
                                        <div class="smush-notices">
                                                <h3>
                                                        <?php
                                                        _e( 'Your website <em>may</em> get a little slow while bulk smushing is in progress.', WP_SMPRO_DOMAIN );
                                                        ?> 
                                                </h3>
                                                <p>
                                                        <?php
                                                        _e( 'This depends on various things like your server resources, '
                                                                . 'number and size of attachments, and the different sizes '
                                                                . 'for each attachment (thumbnail, medium, large, etc). To prevent your site from getting too slow:', WP_SMPRO_DOMAIN );
                                                        ?>
                                                </p>
                                                <ol>
                                                        <li>
                                                                <?php
                                                                _e( 'Avoid updating themes or plugins or running any other maintenance task while bulk smushing is going on.', WP_SMPRO_DOMAIN );
                                                                ?>   
                                                        </li>
                                                        <li>
                                                                <?php
                                                                _e( 'Avoid uploading/deleting media files while bulk smushing is going on.', WP_SMPRO_DOMAIN );
                                                                ?>   
                                                        </li>  
                                                </ol>
                                                <button class="button button-secondary"><?php _e('Got it!', WP_SMPRO_DOMAIN); ?></button>
                                        </div>
					<p>
						<?php
						// let the user know that there's an alternative
						printf(
							__(
								'You can also smush images individually'
								. ' from your <a href="%s">Media Library</a>.', WP_SMPRO_DOMAIN
							), admin_url( 'upload.php' )
						);
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
		 * Print out a hidden html/css spinner to be used by the UI
		 */
		function print_spinner() {
			?>
			<div id="wp-smpro-spinner-wrap">
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
			// set up the counts
			$smushed = $this->bulk['smushed'];
			$sent    = $this->bulk['sent'];
			$recd    = $this->bulk['received'];
                        $stats = array(
                            'compressed_percent'=> 0,
                            'compressed_human'=> '0KB'
                            );
                        $stats = wp_parse_args($this->bulk['stats'], $stats);
			// calculate %ages
			$smushed_pc = $smushed['done'] / $smushed['total'] * 100;
			$sent_pc    = $sent['done'] / $sent['total'] * 100;
			$recd_pc    = $recd['done'] / $recd['total'] * 100;

			$progress_ui = '<div id="progress-ui">';

			// display the progress bars
			$progress_ui .= '<div id="wp-smpro-progress-wrap">
                                                <div id="wp-smpro-sent-progress" class="wp-smpro-progressbar"><div style="width:' . $sent_pc . '%"></div></div>
                                                <div id="wp-smpro-received-progress" class="wp-smpro-progressbar"><div style="width:' . $recd_pc . '%"></div></div>
                                                <div id="wp-smpro-smushed-progress" class="wp-smpro-progressbar"><div style="width:' . $smushed_pc . '%"></div></div>
                                                <p id="wp-smpro-compression">'
                                                        . __( "Reduced by ", WP_SMPRO_DOMAIN )
                                                        . '<span id="percent">'.$stats['compressed_percent'].'</span>% (<span id="kb">'.$stats['compressed_human'].'</span>)
                                                </p>
                                        </div>';
                                                
			// status divs to show completed count/ total count
			$progress_ui .= '<div id="wp-smpro-progress-status">

                                                <p id="sent-status">' .
			                sprintf(
				                __(
					                '<span class="done-count">%d</span> of <span class="total-count">%d</span> images'
					                . ' have been sent for smushing', WP_SMPRO_DOMAIN
				                ), $sent['done'], $sent['total']
			                ) .
			                '</p>
                                                <p id="received-status">' .
			                sprintf(
				                __(
					                '<span class="done-count">%d</span> of <span class="total-count">%d</span> images'
					                . ' have been received', WP_SMPRO_DOMAIN
				                ), $recd['done'], $recd['total']
			                ) .
			                '</p>
                                                <p id="smushed-status">' .
			                sprintf(
				                __(
					                '<span class="done-count">%d</span> of <span class="total-count">%d</span> images'
					                . ' have been smushed', WP_SMPRO_DOMAIN
				                ), $smushed['done'], $smushed['total']
			                ) .
			                '</p>
                                        </div>
				</div>';
			// print it out
			echo $progress_ui;
		}

		/**
		 * Display the bulk smushing button
		 *
		 * @todo Add the API status here, next to the button
		 */
		function setup_button() {
                        $cancel = false;
			// if we have nothing left to smush
			// disable the button
			if ( $this->bulk['smushed']['left'] === 0 ) {
				$button_text  = __( 'All done!', WP_SMPRO_DOMAIN );
				$button_class = "wp-smpro-finished";
				$disabled     = ' disabled="disabled"';
                                $cancel = ' disabled="disabled"';
			} else {
				// we still have some images to send to the API
				// we check received because that is the only useful flag
				if ( $this->bulk['received']['left'] > 0 ) {
					$button_text  = __( 'Smush all images', WP_SMPRO_DOMAIN );
					$button_class = "wp-smpro-unstarted";
				} else {
					// everything has been sent to the API
					$button_text  = __( 'Resend unsmushed images', WP_SMPRO_DOMAIN );
					$button_class = "wp-smpro-resmush";
				}
				$button_class = "wp-smpro-unstarted";

				// disable the button, if API is not connected
				$disabled = $this->api_connected ? '' : ' disabled="disabled"';
                                $cancel = '';
			}
			?>
			<button id="wp-smpro-begin" class="button button-primary <?php echo $button_class; ?>" <?php echo $disabled; ?>>
				<span><?php echo $button_text ?></span>
			</button>
                        <button id="wp-smpro-cancel" class="button button-secondary" <?php echo $cancel; ?>>
				<span><?php _e( 'Cancel', WP_SMPRO_DOMAIN ); ?></span>
			</button>
                        <?php
                        
		}

		/**
		 * Filter Hearbeat response, Refresh the progress counts on Heartbeat received
		 *
		 * @param array $response The unfiltered response about to be sent by the Heartbeat API
		 * @param object|array $data The data received by the Heatbeat API
		 * @param screen $screen_id The screen we're on
		 *
		 * @return array The filtered response sent by the Heartbeat API
		 */
		function refresh_progress( $response, $data, $screen_id ) {
			// if it isn't our screen, get out
			if ( $screen_id != 'media_page_wp-smpro-admin' ) {
				return $response;
			}
			// if we didn't request this, get out
			if ( empty( $data['wp-smpro-refresh-progress'] ) ) {
				return $response;
			}

			// refresh the counts
			$this->refresh_counts();

			// add the new counts in the response.
			$response['wp-smpro-refresh-progress'] = $this->bulk;
                        $response['wp-smpro-is-throttled'] = intval(get_option('wp_smpro_is_throttled', 0));

			// return the filtered response
			return $response;

		}

		/**
		 * Revise the counts
		 *
		 * @return array the new counts
		 */
		function revise_counts() {
			// prepare the query
			$query = array(
				'fields'         => 'ids',
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'post_mime_type' => array( 'image/jpeg', 'image/gif', 'image/png' ),
				'order'          => 'ASC',
				'posts_per_page' => - 1
			);

			// get images that have been received, but not smushed
			$meta_query          = array(
				'relation' => 'AND',
				array(
					'key'   => 'wp-smpro-is-received',
					'value' => 1
				),
				array(
					'key'     => 'wp-smpro-is-smushed',
					'compare' => 'NOT EXISTS'
				)
			);
			$query['meta_query'] = $meta_query;

			// query
			$unsmushed = new WP_Query( $query );

			// for the unsmushed images
			foreach ( $unsmushed->posts as $unsmush ) {
				// reset the received and
				delete_post_meta( $unsmush, 'wp-smpro-is-received' );
				// sent meta, so that they can be requeued
				delete_post_meta( $unsmush, 'wp-smpro-is-sent' );
			}

			// refresh the counts, for queuing afresh
			return $this->refresh_counts();
		}

		/**
		 * Print out the new counts for ajax requests
		 */
		function reset_count() {

			echo json_encode( $this->revise_counts() );

			die();
		}

		/**
		 * Check current smush status of sent attachments
		 */
		function check_status() {

			// the attachment id
			$id = $_GET['attachment_id'];

			$response = array();
			// send 0, means unknown error
			if ( empty( $id ) || $id <= 0 ) {
				$response['status'] = 0;
				$response['msg']    = __( 'ID error', WP_SMPRO_DOMAIN );
				echo json_encode( $response );
				die();
			}
			// otherwise, get smush details
                        $is_smushed = get_post_meta( $id, "wp-smpro-is-smushed" , true );

			// otherwise, we've received the image
                        
			if ( $is_smushed ) {
				$response['status'] = 2;
                                $stats = get_post_meta($id, 'wp-smpro-smush-stats',true);
                                if ( $stats['compressed_bytes'] == 0 ) {
                                        $status_txt = __( 'Already Optimized', WP_SMPRO_DOMAIN );
                                } else {
                                        $status_txt = sprintf( __( "Reduced by %01.1f%% (%s)", WP_SMPRO_DOMAIN ), $stats['compressed_percent'], $stats['compressed_human'] );
                                }
                                $response['msg'] = $status_txt;
				echo json_encode( $response );
				die();
			}


			// Not even that, we're still waiting
			$response['status'] = 1;
			$response['msg']    = __( 'Still waiting', WP_SMPRO_DOMAIN );
			echo json_encode( $response );
			die();
                }


		/**
		 * Set Up API Status
		 */
		function set_api_status() {

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				// we don't want to slow it down
				return get_transient( 'api_connected' );
			}

			if ( defined( 'WP_SMPRO_SERVICE_URL' ) ) {
				$api = wp_remote_get( WP_SMPRO_SERVICE_URL, array( 'sslverify' => false, 'timeout'  => 20 ) );
			}

			if ( empty( $api ) || is_wp_error( $api ) ) {
				set_transient( 'api_connected', false );

				return false;
			}
			set_transient( 'api_connected', true );

			return true;
		}

		/**
		 * Adds a smushit pro settings link on plugin page
		 *
		 * @param $links
		 *
		 * @return array
		 */
		function wp_smushit_pro_settings( $links ) {

			$settings_page = admin_url( 'upload.php?page=wp-smpro-admin' );
			$settings      = '<a href="' . $settings_page . '">Settings</a>';

			array_unshift( $links, $settings );

			return $links;
		}

	}

}