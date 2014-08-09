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
		public $bulk;
		var $api_connected;

		/**
		 * Constructor
		 */
		public function __construct() {

			$this->bulk_data();

			// hook scripts and styles
			add_action( 'admin_init', array( $this, 'register' ) );

			// hook custom screen
			add_action( 'admin_menu', array( $this, 'screen' ) );

			// hook ajax call for checking smush status
			add_filter( 'heartbeat_received', array( $this, 'refresh_progress' ), 10, 3 );
			add_action( 'wp_ajax_wp_smpro_check', array( $this, 'check_status' ) );
                        
                        add_action( 'wp_ajax_wp_smpro_reset', array( $this, 'reset_count' ) );

			add_action( 'admin_footer-upload.php', array( $this, 'print_loader' ) );

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
			/* Register our script. */
			wp_register_script( 'wp-smpro-queue', WP_SMPRO_URL . 'assets/js/wp-smpro-queue.js', array( 'jquery' ), WP_SMPRO_VERSION );
			//@todo enqueue minified script if not debugging
			//wp_register_script( 'wp-smpro-queue-debug', trailingslashit(WP_SMPRO_DIR).'js/wp-smpro-queue.js' );
			wp_register_style( 'wp-smpro-queue', WP_SMPRO_URL . 'assets/css/wp-smpro-queue.css' );

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

		function localize() {
			$wp_smpro_msgs = array(
				'leave_screen' => __( 'You may leave this screen now, <strong>we will update your site with smushed images, automatically</strong>!', WP_SMPRO_DOMAIN ),
				'sent'         => __( 'Sent for Smushing', WP_SMPRO_DOMAIN ),
				'progress'     => __( 'Smushing in Progress', WP_SMPRO_DOMAIN ),
				'resmush'      => __( 'Re-smush', WP_SMPRO_DOMAIN ),
				'smush_now'    => __( 'Smush.it now!', WP_SMPRO_DOMAIN ),
				'done'         => __( 'All done!', WP_SMPRO_DOMAIN ),
				'smush_all'    => __( 'Smush all the images', WP_SMPRO_DOMAIN ),
                                'resmush_all'  => __( 'Resend unsmushed images', WP_SMPRO_DOMAIN ),
                                'refresh_screen'        => sprintf(
                                        __( 
                                                'New images were uploaded, please <a href="%s">refresh this page</a> to smush them properly.',
                                                WP_SMPRO_DOMAIN
                                        ),
                                        admin_url( 'upload.php?page=wp-smpro-admin' )
                                        ),
			);

			wp_localize_script( 'wp-smpro-queue', 'wp_smpro_msgs', $wp_smpro_msgs );
			wp_localize_script( 'wp-smpro-queue', 'wp_smpro_counts', $this->bulk );
		}

		/**
		 * Set up some data needed for the bulk ui
		 * @global type $wpdb
		 */
		function bulk_data() {

			$bulk                   = new WpSmProBulk();
			$this->bulk['sent']     = $bulk->data( 'sent' );
			$this->bulk['received'] = $bulk->data( 'received' );
                        $this->bulk['smushed']  = $bulk->data( 'smushed' );
		}
                
                function refresh_counts(){
                        $this->bulk_data();
                        return $this->bulk;
                }

		/**
		 * Display the ui
		 */
		function ui() {

			//Get dashboard API Key
			$wpmudev_apikey = get_site_option( 'wpmudev_apikey' );

			$class      = $this->api_connected ? ' connected' : ' not-connected';
			$text       = $this->api_connected ? __( 'API Connected', WP_SMPRO_DOMAIN ) : __( 'API not Connected', WP_SMPRO_DOMAIN );

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
					<?php printf( __( 'WP Smush.it Pro <span title="%s" class="api-status%s">%s</span>', WP_SMPRO_DOMAIN ), $text, $class, $text ); ?>
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
				$opt_name = 'wp_smpro_' . $name;
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
			$opt_name = 'wp_smpro_' . $key;

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
			$this->print_loader();
			?>
		<?php
		}
                
                function setup_button(){
                        if ( $this->bulk['smushed']['left'] === 0 ) {
				$button_text = __( 'All done!', WP_SMPRO_DOMAIN );
                                $button_class = "wp-smpro-finished";
                                $disabled     = ' disabled="disabled"';
			} else {
                                if($this->bulk['sent']['left'] > 0){
                                        $button_text = __( 'Smush all the images', WP_SMPRO_DOMAIN );
                                        $button_class = "wp-smpro-unstarted";
                                }else{
                                        $button_text = __( 'Resend unsmushed images', WP_SMPRO_DOMAIN );
                                        $button_class = "wp-smpro-resmush";
                                }
                                $button_class = "wp-smpro-unstarted";
                                $disabled     = $this->api_connected ? '' : ' disabled="disabled"';
			}
                        ?>
                        <button id="wp-smpro-begin" class="button button-primary <?php echo $button_class; ?>" <?php echo $disabled; ?>>
                                <span><?php echo $button_text ?></span>
                        </button>
                        <?php
                }

		function all_ui() {
			if ( $this->bulk['smushed']['total'] < 1 ) {
				printf(
                                        __( 
                                                '<p>Please <a href="%s">upload some images</a>.</p>',
                                                WP_SMPRO_DOMAIN 
                                                ),
                                        admin_url('media-new.php')
                                        );

				return;
			}
			
			?>
			<div id="all-bulk" class="wp-smpro-bulk-wrap">
				<?php
				if ( $this->bulk['smushed']['left'] === 0 ) {
					?>
					<p>
						<?php
						_e( 'All the images are already smushed', WP_SMPRO_DOMAIN );
						?>
					</p>
				<?php
				} else {
					?>
                                        <p>
						<?php
						_e( 'Please avoid uploading/deleting media files while bulk smushing is going on.', WP_SMPRO_DOMAIN );
						?>
					</p>
					<p>
						<?php
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
				$this->progress_ui();
                                $this->setup_button();
				?>
				
			</div>
		<?php
		}

		function print_loader() {
			?>
			<div id="wp-smpro-loader-wrap">
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

		function refresh_progress( $response, $data, $screen_id ) {
			if ( $screen_id != 'media_page_wp-smpro-admin' ) {
				return $response;
			}

			if ( empty( $data['wp-smpro-refresh-progress'] ) ) {
				return $response;
			}

			$this->refresh_counts;
			$response['wp-smpro-refresh-progress'] = $this->bulk;

			return $response;

		}
                
                function revise_counts(){
                        $query = array(
				'fields'         => 'ids',
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'post_mime_type' => array( 'image/jpeg', 'image/gif', 'image/png' ),
				'order'          => 'ASC',
				'posts_per_page' => - 1
			);

                        $meta_query          = array(
                                'relation' => 'AND',
                                array(
                                        'key'     => 'wp-smpro-is-received',
                                        'value' => 1
                                ),
                                array(
                                        'key'   => 'wp-smpro-is-smushed',
                                        'compare' =>  'NOT EXISTS'
                                )
                        );
                        $query['meta_query'] = $meta_query;


			$unsmushed = new WP_Query( $query );
                        
			foreach($unsmushed->posts as $unsmush){
                                delete_post_meta($unsmush, 'wp-smpro-is-received');
                                delete_post_meta($unsmush, 'wp-smpro-is-sent');        
                        }
                        
                        return $this->refresh_counts();
                }
                
                function reset_count(){
                        
                        echo json_encode($this->revise_counts());
                        
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
			$smush_meta_full = get_post_meta( $id, 'smush_meta_full', true );

			// if can't find, it's still awaited
			if ( empty( $smush_meta_full ) ) {
				$response['status'] = 1;
				$response['msg']    = __( 'Still waiting', WP_SMPRO_DOMAIN );
				echo json_encode( $response );
				die();
			}

			// otherwise, we've received the image
			$code            = ! empty( $smush_meta_full['status_code'] ) ? intval( $smush_meta_full['status_code'] ) : '';
			$response['msg'] = $smush_meta_full['status_msg'];
			if ( $code === 4 || $code === 6 ) {
				$response['status'] = 2;
				echo json_encode( $response );
				die();
			}

			if ( $code === 5 ) {
				// smush failed
				$response['status'] = -1;
				echo json_encode( $response );
				die();
			}

			// Not even that, we're still waiting
			$response['status'] = 1;
			$response['msg']    = __( 'Still waiting', WP_SMPRO_DOMAIN );
			echo json_encode( $response );
			die();
		}

		function progress_ui() {
                        $smushed     = $this->bulk['smushed'];
			$sent        = $this->bulk['sent'];
			$recd        = $this->bulk['received'];
                        $smushed_pc  = $smushed['done'] / $smushed['total'] * 100;
			$sent_pc     = $sent['done'] / $sent['total'] * 100;
			$recd_pc     = $recd['done'] / $recd['total'] * 100;
			$progress_ui = '
				<div id="progress-ui">
                                        <div id="wp-smpro-progress-wrap">
                                                <div id="wp-smpro-sent-progress" class="wp-smpro-progressbar"><div style="width:' . $sent_pc . '%"></div></div>
                                                <div id="wp-smpro-received-progress" class="wp-smpro-progressbar"><div style="width:' . $recd_pc . '%"></div></div>
                                                <div id="wp-smpro-smushed-progress" class="wp-smpro-progressbar"><div style="width:' . $smushed_pc . '%"></div></div>

                                        </div>
                                        <div id="wp-smpro-progress-status">

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
			echo $progress_ui;
		}

		/**
		 * Set Up API Status
		 */
		function set_api_status() {

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				// we don't want to slow it down
				return get_transient('api_connected');
			}

			if ( defined( 'WP_SMPRO_SERVICE_URL' ) ) {
				$api = wp_remote_get( WP_SMPRO_SERVICE_URL );
			}
			if ( empty( $api ) || is_wp_error( $api ) ) {
				set_transient( 'api_connected', false );
				return false;
			}
			set_transient( 'api_connected', true );
			return true;
		}

	}

}