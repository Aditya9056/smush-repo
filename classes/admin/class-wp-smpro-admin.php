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
		public $counts;

		/**
		 *
		 * @var boolean API conectivity status
		 */
		public $api_connected;

		/**
		 * Constructor
		 */
		public function __construct() {

			// hook scripts and styles
			add_action( 'admin_init', array( $this, 'register' ) );

			// hook admin notice
			add_action( 'admin_notices', array( $this, 'admin_notice' ) );

			// hook custom screen
			add_action( 'admin_menu', array( $this, 'screen' ) );

			// hook ajax call for checking smush status
			add_action( 'wp_ajax_wp_smpro_hide', array( $this, 'hide_notice' ) );

			// hook ajax call to reset counts
			add_action( 'wp_ajax_wp_smpro_reset', array( $this, 'reset_count' ) );

			// hook into admin footer to load a hidden html/css spinner
			add_action( 'admin_footer-upload.php', array( $this, 'print_spinner' ) );

			// Dismiss Smush Notice
			add_action( 'wp_ajax_dismiss_smush_notice', array( $this, 'dismiss_smush_notice' ) );

			add_filter( 'plugin_action_links_' . WP_SMPRO_BASENAME, array(
				$this,
				'settings_link'
			) );
			add_filter( 'network_admin_plugin_action_links_' . WP_SMPRO_BASENAME, array(
				$this,
				'settings_link'
			) );

			// initialise translation ready settings and titles
			$this->init_settings();

			// instantiate Media Library mods
			$media_lib = new WpSmProMediaLibrary();

			//On deleting images update sent ids
			add_action( 'delete_attachment', array( $this, 'update_sent_ids' ) );
		}

		/**
		 * Translation ready settings
		 */
		function init_settings() {
			$this->settings = array(
				'auto'        => __( 'Auto-Smush images on upload', WP_SMPRO_DOMAIN ),
				'remove_meta' => __( 'Remove EXIF data from JPEGs', WP_SMPRO_DOMAIN ),
				'progressive' => __( 'Progressive optimization for JPEGs', WP_SMPRO_DOMAIN ),
				'debug_mode' => __( 'Enable debug mode', WP_SMPRO_DOMAIN ),
//				'gif_to_png'  => __( 'Convert GIF to PNG', WP_SMPRO_DOMAIN ),
			);
		}

		/**
		 * Add Bulk option settings page
		 */
		function screen() {
			global $admin_page_suffix;
			$admin_page_suffix = add_media_page( 'WP Smush Pro', 'WP Smush Pro', 'edit_others_posts', 'wp-smpro-admin', array(
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

			// set up image counts for progressive smushing
			$this->setup_counts();

			// register js
			if( !empty( $_REQUEST['mode'] ) && $_REQUEST['mode'] == 'grid') {
				wp_register_script( 'wp-smpro', WP_SMPRO_URL . 'assets/js/wp-smpro.js', array(
						'jquery',
						'media-views'
					), WP_SMPRO_VERSION );
			}else{
				wp_register_script( 'wp-smpro', WP_SMPRO_URL . 'assets/js/wp-smpro.js', array(
					'jquery', 'underscore'
				), WP_SMPRO_VERSION );
			}
			wp_register_script( 'wp-smpro-queue', WP_SMPRO_URL . 'assets/js/wp-smpro-queue.js', array( 'wp-smpro' ), WP_SMPRO_VERSION );

			// register css
			wp_register_style( 'wp-smpro-queue', WP_SMPRO_URL . 'assets/css/wp-smpro-queue.css', array(), WP_SMPRO_VERSION );

			// localize translatable strings for js
			$this->localize();
		}

		/**
		 * enqueue js and css
		 */
		function enqueue() {
			global $current_screen, $admin_page_suffix;
			wp_enqueue_script( 'wp-smpro-queue' );
			wp_enqueue_style( 'wp-smpro-queue' );
			//Set API status on bulk page load only
			if ( $current_screen->id == $admin_page_suffix ) {
				$this->set_api_status();
			}
		}

		/**
		 * Localise translatable strings for JS use on bulk UI
		 * Also localise initial counts
		 */
		function localize() {
			$wp_smpro_msgs = array(
				'fetch'     => __( 'Fetch smushed images', WP_SMPRO_DOMAIN ),
				'sending'   => __( 'Sending &hellip;', WP_SMPRO_DOMAIN ),
				'send_fail' => __( 'Sending failed. Please try again later', WP_SMPRO_DOMAIN ),
				'sent'      => __( 'Smushing in progress', WP_SMPRO_DOMAIN ),
				'at_api'    => __( 'Currently Smushing &hellip;', WP_SMPRO_DOMAIN ),
				'fetching'  => __( 'Fetching smushed images', WP_SMPRO_DOMAIN ),
				'resmush'   => __( 'Re-smush', WP_SMPRO_DOMAIN ),
				'smush_now' => __( 'Smush now!', WP_SMPRO_DOMAIN ),
				'bulk_smush_now' => __( 'Send Smush Request', WP_SMPRO_DOMAIN ),
				'done'      => __( 'All done!', WP_SMPRO_DOMAIN ),
				'timeout'      => __( 'It is taking too long for the requests, Ajax timed out.', WP_SMPRO_DOMAIN ),
				'no_leave'  => __( 'Images are being fetched from the API. If you leave this screen, the fetching will pause until you return again.', WP_SMPRO_DOMAIN ),
			);

			wp_localize_script( 'wp-smpro-queue', 'wp_smpro_msgs', $wp_smpro_msgs );

			// localise counts
			wp_localize_script( 'wp-smpro-queue', 'wp_smpro_counts', $this->counts );

			$current_bulk_request = get_option( WP_SMPRO_PREFIX . "bulk-sent" );
			$current_requests     = get_option( WP_SMPRO_PREFIX . "current-requests", array() );

			$sent_ids = array();
			foreach( $current_requests as $request_id => $request ){
				if( !empty($request['received']) && $request['received'] == 1 ) {
					$sent_ids[ $request_id ]['sent_ids'] = $request['sent_ids'];
				}
			}

			global $wp_locale;
			$locale = array(
				'decimal'       => $wp_locale->number_format['decimal_point'],
				'thousands_sep' => $wp_locale->number_format['thousands_sep']
			);


			wp_localize_script( 'wp-smpro-queue', 'wp_smpro_locale', $locale );

			// localise counts
			wp_localize_script( 'wp-smpro-queue', 'wp_smpro_sent_ids', $sent_ids );
		}

		function admin_notice() {
			if ( boolval( get_option( WP_SMPRO_PREFIX . 'bulk-received', 0 ) ) && ! get_site_option( 'hide_smush_notice' ) ) {
				$message   = array();
				$message[] = sprintf( __( 'A recent bulk smushing request has been completed!', WP_SMPRO_DOMAIN ), get_option( 'siteurl' ) );
				if ( ! isset( $_GET['page'] ) || 'wp-smpro-admin' != $_GET['page'] ) { //if not on smush page
					$message[] = sprintf( __( 'Visit <strong><a href="%s">Media &raquo; WP Smush Pro</a></strong> to download the smushed images to your site.', WP_SMPRO_DOMAIN ), admin_url( 'upload.php?page=wp-smpro-admin' ) );
				}
				?>
				<style type="text/css">
					.bulk-smush-notice {
						position: relative;
					}

					.dismiss-smush-notice {
						color: red;
						cursor: pointer;
						font-size: 15px;
						margin: 0 !important;
						position: absolute;
						right: 10px;
						top: 0;
					}
				</style>
				<script type="text/javascript">
					jQuery('document').ready(function () {
						jQuery('body').on('click', '.dismiss-smush-notice', function (e) {
							e.preventDefault();
							$this = jQuery(this);
							jQuery.ajax({
								'url': ajaxurl,
								'type': 'POST',
								'data': {action: 'dismiss_smush_notice'},
								'success': function () {
									$this.parent().remove();
								}
							});
						});
					});
				</script>
				<div class="updated bulk-smush-notice">
					<p>
						<?php echo implode( '</p><p>', $message ); ?>
					</p>
					<a class="dismiss-smush-notice" title="<?php _e( 'Dismiss notice', WP_SMPRO_DOMAIN ); ?>" href="#">x</a>
				</div>

			<?php
			}
		}

		/**
		 * Set up some data needed for the bulk ui
		 */
		function setup_counts() {

			$counts = new WpSmProCount();
			$counts->init();

			$this->counts          = $counts->counts;
			$this->counts['stats'] = get_option( 'wp-smpro-global-stats', array() );
			$this->counts          = array_merge( $this->counts, $this->global_stats() );
		}

		/**
		 * Refresh all the counts
		 * @return array the counts
		 */
		function refresh_counts() {
			$this->setup_counts();

			return $this->counts;
		}

		function hide_notice() {
			update_option( WP_SMPRO_PREFIX . 'hide-notice', 1 );
			die();
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
			//Check if there are input ids in URL
			if ( ! empty( $_REQUEST['ids'] ) ) {
				$current_bulk_request = get_option( WP_SMPRO_PREFIX . "bulk-sent" );
				if( !empty( $current_bulk_request ) ) { ?>
					<div class="error"><p><?php _e( 'Bulk smush failed, as another bulk request is already being processed.' ); ?></p></div><?php
				}else {
					if ( $this->api_connected ) {
						global $wp_smpro;

						$ids = $_REQUEST['ids'];
						$ids = explode( ',', $ids );
						if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'wp-smpro-admin' ) ) {
							?>
							<div class="error"><?php _e( 'Nonce verification failed' ); ?></div><?php
						} else {
							$wp_smpro->sender->send_request( $ids );
							// Reset Counts
							$this->setup_counts();
						}
					} else {
						//display a error, images were not sent for smushing
						?>
						<div class="error"><p><?php _e( 'Images not sent for smushing as API is unreachable.' ); ?></p>
						</div><?php
					}
				}
			}
			?>
			<div class="wrap">
				<div id="icon-upload" class="icon32"><br/></div>

				<h2>
					<?php printf( __( 'WP Smush Pro <span title="%s" class="api-status%s">%s</span><span class="api-status-text">%s</span>', WP_SMPRO_DOMAIN ), $text, $class, $text, $text ); ?>
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

					<div class="bulk-smush">
							<img src="<?php echo WP_SMPRO_URL; ?>assets/images/bulk-smush-instructions.png" alt="<?php _e('Bulk Smushing Instructions', WP_SMPRO_DOMAIN ); ?>" />
<!--						<h4>--><?php //_e( 'Here is how bulk smushing works:', WP_SMPRO_DOMAIN ); ?><!--</h4>-->
<!--						<ol>-->
<!--							<li>--><?php //_e( "Click to send up to 100 attachments at a time to our API server.", WP_SMPRO_DOMAIN ); ?><!--</li>-->
<!--							<li>--><?php //_e( "We'll queue your bulk smush job for processing by the order it's received. Normal single smush requests are given priority over bulk smushing.", WP_SMPRO_DOMAIN ); ?><!--</li>-->
<!--							<li>--><?php //_e( "Depending upon the size of the queue, your job, and image sizes, it may take anywhere from a few minutes to a few days to complete your bulk job.", WP_SMPRO_DOMAIN ); ?><!--</li>-->
<!--							<li>--><?php //_e( "When ready, you'll be notified via email and an admin notice that your job is ready to be fetched.", WP_SMPRO_DOMAIN ); ?><!--</li>-->
<!--							<li>--><?php //_e( "You can then start fetching the images via the button on this page. The progress bar will continually update displaying the number of images fetched and total savings in terms of data.", WP_SMPRO_DOMAIN ); ?><!--</li>-->
<!--							<li>--><?php //_e( "If you have to navigate away from this page during the fetching process, you can always return to resume fetching where you left off. You have 30 days to complete the fetch.", WP_SMPRO_DOMAIN ); ?><!--</li>-->
<!--						</ol>-->
					</div>

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
				$setting = isset( $_POST[ $opt_name ] ) ? 1 : 0;
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
			$const_name = strtoupper( 'WP_SMPRO_' . $key );

			// default value
			$opt_val = intval( get_option( $opt_name, constant( $const_name ) ) );

			// return html
			return sprintf(
				"<li><label><input type='checkbox' name='%1\$s' id='%1\$s' value='1' %2\$s>%3\$s</label></li>", esc_attr( $opt_name ), checked( $opt_val, 1, false ), $text
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
			if ( $this->counts['total'] < 1 ) {
				printf(
					__(
						'<p>Please <a href="%s">upload some images</a>.</p>', WP_SMPRO_DOMAIN
					), admin_url( 'media-new.php' )
				);

				// no need to print out the rest of the UI
				return;
			}

			// otherwise, start displaying the UI
			?>
			<div id="all-bulk" class="wp-smpro-bulk-wrap">
				<?php
				// everything has been smushed, display a notice
				if ( $this->counts['smushed'] === $this->counts['total'] ) {
					?>
					<p>
						<?php
						_e( 'All your images are already smushed!', WP_SMPRO_DOMAIN );
						?>
					</p>
				<?php
				} else {
					// we have some smushing to do! :)
					// first some warnings
					?>
					<p>
						<?php
						// let the user know that there's an alternative
						printf(
							__( 'You can also smush images individually from your <a href="%s">Media Library</a>.', WP_SMPRO_DOMAIN
							), admin_url( 'upload.php' )
						);
						?>
					</p>
				<?php
				}

				// display the progress bar
				$this->progress_ui();

				$this->show_notice();
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

			// calculate %ages
			$smushed_pc = $this->counts['smushed'] / $this->counts['total'] * 100;
			$sent_pc    = $this->counts['sent'] / $this->counts['total'] * 100;


			$progress_ui = '<div id="progress-ui">';

			// display the progress bars
			$progress_ui .= '<div id="wp-smpro-progress-wrap">
                                                <div id="wp-smpro-sent-progress" class="wp-smpro-progressbar"><div style="width:' . $sent_pc . '%"></div></div>
                                                <div id="wp-smpro-fetched-progress" class="wp-smpro-progressbar"><div style="width:' . $smushed_pc . '%"></div></div>
                                                <p id="wp-smpro-compression">'
			                . __( "Reduced by ", WP_SMPRO_DOMAIN )
			                . '<span id="percent">' . number_format_i18n( $this->counts['percent'], 2, '.', '' ) . '</span>% (<span id="human">' . $this->counts['human'] . '</span>)
                                                </p>
                                        </div>';

			// status divs to show completed count/ total count
			$progress_ui .= '<div id="wp-smpro-progress-status">

                                                <p id="sent-status">' .
			                sprintf(
				                __(
					                '<span class="done-count">%d</span> of <span class="total-count">%d</span> attachments have been sent for smushing', WP_SMPRO_DOMAIN
				                ), $this->counts['sent'], $this->counts['total']
			                ) .
			                '</p>
                                                
                                                <p id="fetched-status">' .
			                sprintf(
				                __(
					                '<span class="done-count">%d</span> of <span class="total-count">%d</span> smushed attachments have been fetched', WP_SMPRO_DOMAIN
				                ), $this->counts['smushed'], $this->counts['total']
			                ) .
			                '</p>
                                        </div>
				</div>';
			// print it out
			echo $progress_ui;
		}

		function show_notice() {
			$hide = intval( get_option( WP_SMPRO_PREFIX . 'hide-notice', 0 ) );
			if ( $hide ) {
				return;
			}
			?>
			<div class="smush-notices" id="fetch-notice">
				<h3>
					<?php
					_e( 'NOTE: Your website <em>might</em> get a little slow while the images are being fetched.', WP_SMPRO_DOMAIN );
					?>
				</h3>

				<p>
					<?php
					_e( 'This depends on various things like your server resources, number and size of attachments, and the different sizes for each attachment (thumbnail, medium, large, etc).', WP_SMPRO_DOMAIN );
					?>
				</p>
				<button class="button button-primary accept-slow-notice"><?php _e( 'Got it!', WP_SMPRO_DOMAIN ); ?></button>
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
		<button id="<?php echo $button['id']; ?>" class="button button-primary" <?php echo $button['disabled']; ?>>
			<span><?php echo $button['text'] ?></span>
			</button><?php
			if ( $button['id'] == 'wp-smpro-fetch' ) {
				//show cancel button only for fetching
				?>
			<button id="wp-smpro-cancel" class="button button-secondary disabled" <?php echo $button['cancel']; ?>>
				<span><?php _e( 'Cancel', WP_SMPRO_DOMAIN ); ?></span>
				</button><?php
			}
		}

		private function button_state() {
			$button = array(
				'cancel' => false,
			);
			// otherwise we have something to smush
			// check if we are awaiting a bulk request's smush response
			$is_bulk_sent = boolval( get_option( WP_SMPRO_PREFIX . "bulk-sent", 0 ) );

			// check if we have received this bulk request's callback
			$is_bulk_received = boolval( get_option( WP_SMPRO_PREFIX . "bulk-received", 0 ) );

			// if we have nothing left to smush
			// disable the buttons
			if ( $this->counts['smushed'] === $this->counts['total'] ) {
				$button['text']     = __( 'All done!', WP_SMPRO_DOMAIN );
				$button['id']       = "wp-smpro-finished";
				$button['disabled'] = ' disabled="disabled"';
				$button['cancel']   = ' disabled="disabled"';

				return $button;
			} elseif ( $this->counts['sent'] === $this->counts['total'] && ! $is_bulk_sent ) {
				$button['text']     = __( 'Smushing in progress', WP_SMPRO_DOMAIN );
				$button['id']       = "wp-smpro-waiting";
				$button['disabled'] = ' disabled="disabled"';
				$button['cancel']   = ' disabled="disabled"';

				return $button;
			}

			// a bulk request has been sent but not received
			if ( $is_bulk_sent && ! $is_bulk_received ) {
				$button['text']     = __( 'Smushing in progress', WP_SMPRO_DOMAIN );
				$button['id']       = "wp-smpro-waiting";
				$button['disabled'] = ' disabled="disabled"';
				$button['cancel']   = ' disabled="disabled"';

				return $button;
			}

			// no bulk request awaited
			if ( ! $is_bulk_sent && ! $is_bulk_received ) {
				if ( ! $this->api_connected ) {
					$button['text']     = __( 'Service unavailable!', WP_SMPRO_DOMAIN );
					$button['disabled'] = ' disabled="disabled"';
					$button['cancel']   = ' disabled="disabled"';
				} else {
					$button['text'] = __( 'Send Smush Request', WP_SMPRO_DOMAIN );

					$button['disabled'] = false;
					$button['cancel']   = ' disabled="disabled"';
				}
				$button['id'] = "wp-smpro-send";

				return $button;
			}

			// bulk request has been smushed and callback was received
			if ( $is_bulk_received ) {
				// if API not connected
				if ( ! $this->api_connected ) {
					$button['text']     = __( 'Service unavailable!', WP_SMPRO_DOMAIN );
					$button['disabled'] = ' disabled="disabled"';
					$button['cancel']   = ' disabled="disabled"';
				} else {
					$button['text']     = __( 'Fetch smushed images', WP_SMPRO_DOMAIN );
					$button['disabled'] = false;
					$button['cancel']   = false;
				}

				$button['id'] = "wp-smpro-fetch";

				return $button;
			}
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
			$is_smushed = get_post_meta( $id, "wp-smpro-is-smushed", true );

			// otherwise, we've received the image

			if ( $is_smushed ) {
				$response['status'] = 2;
				$stats              = get_post_meta( $id, 'wp-smpro-smush-stats', true );
				if ( $stats['compressed_bytes'] == 0 ) {
					$status_txt = __( 'Already Optimized', WP_SMPRO_DOMAIN );
				} else {
					$status_txt = sprintf( __( "Reduced by %01.1f%% (%s)", WP_SMPRO_DOMAIN ), number_format_i18n( $stats['compressed_percent'], 2, '.', '' ), $stats['compressed_human'] );
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
				$api = wp_remote_get( WP_SMPRO_SERVICE_STATUS, array( 'sslverify' => false, 'timeout' => 10 ) );
			}
			if ( empty( $api ) || is_wp_error( $api ) ) {
				set_transient( 'api_connected', false );

				return false;
			}
			$this->api_connected = true;
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
		function settings_link( $links ) {

			$settings_page = admin_url( 'upload.php?page=wp-smpro-admin' );
			$settings      = '<a href="' . $settings_page . '">Settings</a>';

			array_unshift( $links, $settings );

			return $links;
		}

		function global_stats() {

			global $wpdb, $wp_smpro;

			$sql = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key=%s";

			$global_data = $wpdb->get_col( $wpdb->prepare( $sql, WP_SMPRO_PREFIX . "smush-data" ) );

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

			$smush_data['bytes']   = $smush_data['size_before'] - $smush_data['size_after'];
			$smush_data['percent'] = 0;
			if ( $smush_data['size_before'] > 0 ) {
				$smush_data['percent'] = ( $smush_data['bytes'] / $smush_data['size_before'] ) * 100;
			}

			$smush_data['human'] = $wp_smpro->format_bytes( $smush_data['bytes'] );

			return $smush_data;
		}

		/**
		 * Update Sent ids
		 */
		function update_sent_ids( $attachment_id ) {
			$sent_ids = get_site_option( WP_SMPRO_PREFIX . 'sent-ids', false );

			if ( empty( $sent_ids ) ) {
				return;
			}
			// Search
			$pos = array_search( $attachment_id, $sent_ids );
			if ( ! $pos ) {
				return;
			}
			unset( $sent_ids[ $pos ] );
			update_site_option( WP_SMPRO_PREFIX . 'sent-ids', $sent_ids );

			if ( empty( $sent_ids ) ) {
				remove_action( 'admin_notices', array( $this, 'admin_notice' ) );
				//No media, remove bulk meta
				delete_option( WP_SMPRO_PREFIX . "bulk-sent" );
				delete_option( WP_SMPRO_PREFIX . "bulk-received" );
			}

			return;
		}

		/**
		 * Remove the smush notice untill next bulk request
		 */
		function dismiss_smush_notice() {
			update_site_option( 'hide_smush_notice', 1 );
			wp_send_json_success();
		}

	}

}