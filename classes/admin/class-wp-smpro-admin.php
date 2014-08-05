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
			add_action( 'wp_ajax_wp_smpro_check', array( $this, 'check_status' ) );

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
				'auto'        => __( 'Smush images on upload?', WP_SMPRO_DOMAIN ),
				'remove_meta' => __( 'Remove Exif data', WP_SMPRO_DOMAIN ),
				'progressive' => __( 'Allow progressive JPEGs', WP_SMPRO_DOMAIN ),
				'gif_to_png'  => __( 'Allow Gif to Png conversion', WP_SMPRO_DOMAIN ),
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
                }

		/**
		 * enqueue js and css
		 */
		function enqueue() {
			wp_enqueue_script( 'wp-smpro-queue' );
			wp_enqueue_style( 'wp-smpro-queue' );
		}
                
                function localize(){
                        $wp_smpro_msgs = array(
                            'leave_screen'      => __('You may leave this screen now, <strong>we will update your site with smushed images, automatically</strong>!', WP_SMPRO_DOMAIN),
                            'sent'              => __('Sent for Smushing', WP_SMPRO_DOMAIN),
                            'progress'          => __('Smushing in Progress', WP_SMPRO_DOMAIN),
                            'resmush'           => __('Re-smush', WP_SMPRO_DOMAIN),
                            'smush_now'         => __('Smush.it now!', WP_SMPRO_DOMAIN),
                            'done'              => __('All done!', WP_SMPRO_DOMAIN)
                        );
                        
                        wp_localize_script( 'wp-smpro-queue', 'wp_smpro_msgs', $wp_smpro_msgs );
                        wp_localize_script( 'wp-smpro-queue', 'wp_smpro_sent', $this->bulk['sent'] );
                        wp_localize_script( 'wp-smpro-queue', 'wp_smpro_received', $this->bulk['received'] );
                }
                
                /**
		 * Set up some data needed for the bulk ui
		 * @global type $wpdb
		 */
		function bulk_data() {

			$bulk = new WpSmProBulk();
                        $this->bulk['sent'] = $bulk->data('sent');
                        $this->bulk['received'] = $bulk->data('received');
                        
		}


		/**
		 * Display the ui
		 */
		function ui() {

			//Get dashboard API Key
			$wpmudev_apikey = get_site_option( 'wpmudev_apikey' );

			//Style for container if there is no API key
			$style = '';
			if ( empty( $wpmudev_apikey ) ) {

				$style = 'style="opacity: 0.4;"'; ?>

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
					<?php _e( 'WP Smush.it Pro', WP_SMPRO_DOMAIN ) ?>
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
				"<li><label><input type='checkbox' name='%1\$s' id='%1\$s' value='1' %2\$s>%3\$s</label></li>",
				esc_attr( $opt_name ),
				checked( $opt_val, true, false ),
				$text
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

		function all_ui() {
			if ( $this->bulk['sent']['total'] < 1 ) {
				_e( "<p>You don't appear to have uploaded any images yet.</p>", WP_SMPRO_DOMAIN );

				return;
			}

			if ( $this->bulk['received']['left'] === 0 ) {
				_e( 'All the images are already smushed', WP_SMPRO_DOMAIN );

				return;
			}
			?>
			<div id="all-bulk" class="wp-smpro-bulk-wrap">
				<p>
					<?php printf(
						__(
							'We have found <strong>%d unsmushed images</strong> in your media library.'
							. ' You can smush them all by clicking the button below.',
							WP_SMPRO_DOMAIN
						),
						$this->bulk['sent']['left']
					);
					?>
				</p>

				<p>
					<?php _e(
						'It may take some time for all images to be smushed.'
						. ' If you leave this screen,'
						. ' you can always start from where smushing was left off.',
						WP_SMPRO_DOMAIN
					);
					?>

				</p>

				<p>
					<?php printf(
						__(
							'Alternatively, you can smush images individually'
							. ' from your <a href="%s">Media Library</a>',
							WP_SMPRO_DOMAIN
						),
						admin_url( 'upload.php' )
					);
					?>
				</p>
				<?php 
				$this->progress_ui();
				?>
				<button id="wp-smpro-begin" class="button button-primary">
					<span>
						<?php _e( 'Smush all the images', WP_SMPRO_DOMAIN ); ?>
					</span>
				</button>
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
			$code            = !empty( $smush_meta_full['status_code'] ) ? intval( $smush_meta_full['status_code'] ) : '';
			$response['msg'] = $smush_meta_full['status_msg'];
			if ( $code === 4 || $code === 6 ) {
				$response['status'] = 2;
				echo json_encode( $response );
				die();
			}

			if ( $code === 5 ) {
				// smush failed
				$response['status'] = 0;
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
                        
                        $sent = $this->bulk['sent'];
                        $recd = $this->bulk['received'];
                        $sent_pc = $sent['done']/$sent['total']*100;
                        $recd_pc = $recd['done']/$recd['total']*100;
			$progress_ui = '
				<div id="progress-ui">
				<div id="wp-smpro-progress-wrap">
					<div id="wp-smpro-smush-progress" class="wp-smpro-progressbar"><div style="width:'.$sent_pc.'%"></div></div>
					<div id="wp-smpro-check-progress" class="wp-smpro-progressbar"><div style="width:'.$recd_pc.'%"></div></div>
				</div>
				<p id="smush-status">'.
				sprintf(
					__(
						'<span id="smush-sent-count">%d</span> of <span id="smush-total-count">%d</span> images'
						. ' have been sent for smushing',
						WP_SMPRO_DOMAIN
						),
					$sent['done'],
                                        $sent['total']
					).
				'</p>
				<p id="check-status">'.
				sprintf(
					__(
						'<span id="smush-received-count">%d</span> of <span id="smush-total-count">%d</span> images'
						. ' have been received',
						WP_SMPRO_DOMAIN
						),
					$recd['done'],
                                        $recd['total']
					).
				'</p>
				</div>
				';
			echo $progress_ui;
		}

	}

}