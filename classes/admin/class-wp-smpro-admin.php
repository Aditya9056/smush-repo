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
		 * @var boolean API connectivity status
		 */
		public $api_connected;

		var $media_lib;

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
			add_action( 'wp_ajax_smush_api_status', array( $this, 'smush_api_status' ) );

			// hook ajax call for checking smush status
			add_action( 'wp_ajax_wp_smpro_hide', array( $this, 'hide_notice' ) );

			// hook ajax call to reset counts
			add_action( 'wp_ajax_wp_smpro_reset', array( $this, 'reset_count' ) );

			// hook ajax call to reset counts
			add_action( 'wp_ajax_wp_smpro_reset_bulk_request', array( $this, 'reset_bulk_request' ) );

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
			$this->media_lib = new WpSmProMediaLibrary();

			//On deleting images update sent ids
			add_action( 'delete_attachment', array( $this, 'update_sent_ids' ) );

			//Attachment status
			add_action( 'wp_ajax_attachment_status', array( $this, 'attachment_status' ) );
		}

		/**
		 * Translation ready settings
		 */
		function init_settings() {
			$this->settings = array(
				'auto'        => __( 'Auto-Smush images on upload', WP_SMPRO_DOMAIN ),
				'remove_meta' => __( 'Remove EXIF data from JPEGs', WP_SMPRO_DOMAIN ),
				'progressive' => __( 'Progressive optimization for JPEGs', WP_SMPRO_DOMAIN ),
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
			//Register Debug page only if WP_SMPRO_DEBUG is defined and true
			if ( defined( 'WP_SMPRO_DEBUG' ) && WP_SMPRO_DEBUG ) {
				add_media_page( 'WP Smpro Error Log', 'Error Log', 'edit_others_posts', 'wp-smpro-debug', array(
					$this,
					'create_admin_error_log_page'
				) );
			}

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
			$current_blog_id       = get_current_blog_id();
			$meta_key              = $current_blog_id == 1 ? 'wp_media_library_mode' : 'wp_' . $current_blog_id . '_media_library_mode';
			$wp_media_library_mode = get_user_meta( get_current_user_id(), $meta_key, true );
			//Either request variable is not empty and grid mode is set, or if request empty then view is as per user choice, or no view is set
			if ( ( ! empty( $_REQUEST['mode'] ) && $_REQUEST['mode'] == 'grid' ) ||
			     ( empty( $_REQUEST['mode'] ) && $wp_media_library_mode != 'list' )
			) {
				wp_register_script( 'wp-smpro', WP_SMPRO_URL . 'assets/js/wp-smpro.js', array(
					'jquery',
					'media-views'
				), WP_SMPRO_VERSION );
			} else {
				wp_register_script( 'wp-smpro', WP_SMPRO_URL . 'assets/js/wp-smpro.js', array(
					'jquery',
					'underscore'
				), WP_SMPRO_VERSION );
			}
			wp_register_script( 'wp-smpro-queue', WP_SMPRO_URL . 'assets/js/wp-smpro-queue.js', array( 'wp-smpro' ), WP_SMPRO_VERSION );
			wp_register_script( 'wp-smpro-alert', WP_SMPRO_URL . 'assets/js/sweet-alert.js', array( 'wp-smpro' ), WP_SMPRO_VERSION );

			// register css
			wp_register_style( 'wp-smpro-style', WP_SMPRO_URL . 'assets/css/wp-smpro-queue.css', array(), WP_SMPRO_VERSION );
			wp_register_style( 'wp-smpro-alert-style', WP_SMPRO_URL . 'assets/css/wp-smpro-alert.css', array(), WP_SMPRO_VERSION );

			// localize translatable strings for js
			$this->localize();
		}

		/**
		 * enqueue js and css
		 */
		function enqueue() {
			global $current_screen, $admin_page_suffix;
			wp_enqueue_script( 'wp-smpro-queue' );
			wp_enqueue_script( 'wp-smpro-alert' );

			wp_enqueue_style( 'wp-smpro-style' );
			wp_enqueue_style( 'wp-smpro-alert-style' );

		}

		/**
		 * Localise translatable strings for JS use on bulk UI
		 * Also localise initial counts
		 */
		function localize() {
			$wp_smpro_msgs = array(
				'fetch'           => __( 'Fetch smushed images', WP_SMPRO_DOMAIN ),
				'sending'         => __( 'Sending &hellip;', WP_SMPRO_DOMAIN ),
				'send_fail'       => __( 'Sending failed. Please try again later', WP_SMPRO_DOMAIN ),
				'sent'            => __( 'Smushing in progress', WP_SMPRO_DOMAIN ),
				'at_api'          => __( 'Image sent for smushing &hellip;', WP_SMPRO_DOMAIN ),
				'fetching'        => __( 'Fetching smushed images', WP_SMPRO_DOMAIN ),
				'resmush'         => __( 'Re-smush', WP_SMPRO_DOMAIN ),
				'smush_now'       => __( 'Smush now!', WP_SMPRO_DOMAIN ),
				'bulk_smush_now'  => __( 'Send Smush Request', WP_SMPRO_DOMAIN ),
				'done'            => __( 'All done!', WP_SMPRO_DOMAIN ),
				'timeout'         => __( 'Ajax request error.', WP_SMPRO_DOMAIN ),
				'smush_email'     => __( 'You will receive an email, once images are smushed and ready to fetch', WP_SMPRO_DOMAIN ),
				'smush_completed' => __( 'Images have been smushed, you can fetch them by clicking on "Fetch smushed images" button', WP_SMPRO_DOMAIN ),
				'no_leave'        => __( 'Images are being fetched from the API. If you leave this screen, the fetching will pause until you return again.', WP_SMPRO_DOMAIN ),
				'confirm'         => __( 'You really want to remove the bulk request?', WP_SMPRO_DOMAIN ),
				'confirm_button'  => __( 'Just do it', WP_SMPRO_DOMAIN ),
				'reset_success'   => __( 'Make sure to send another bulk smush', WP_SMPRO_DOMAIN )
			);

			wp_localize_script( 'wp-smpro-queue', 'wp_smpro_msgs', $wp_smpro_msgs );

			// localise counts
			wp_localize_script( 'wp-smpro-queue', 'wp_smpro_counts', $this->counts );

			$sent_request_id = get_option( WP_SMPRO_PREFIX . "bulk-sent", 0 );

			$sent_request = ! empty( $sent_request_id ) ? array( 'sent' => true ) : array( 'sent' => false );

			$current_requests = get_option( WP_SMPRO_PREFIX . "current-requests", array() );

			$sent_ids = array();
			$count    = 0;
			//Check if current request is empty, or if bulk request is set, check if sent ids are not empty for
			//th bulk request in current requestß
			if ( empty( $current_requests ) ||
			     ( $sent_request_id &&
			       ( empty( $current_requests[ $sent_request_id ] ) || empty( $current_requests[ $sent_request_id ]['sent_ids'] ) )
			     )
			) {
				delete_option( WP_SMPRO_PREFIX . "bulk-sent" );
				$sent_request = array( 'sent' => false );
			}
			foreach ( $current_requests as $request_id => $request ) {
				if ( empty( $current_requests[ $request_id ]['sent_ids'] ) ) {
					unset( $current_requests[ $request_id ] );
				}
				if ( ! empty( $request['received'] ) && $request['received'] == 1 && ! empty( $request['sent_ids'] ) ) {
					$sent_ids[ $request_id ]['sent_ids'] = $request['sent_ids'];

					//Used to decide whether localize or not sent request variable, if we already received smush completion, we need
					//not to poll on page refresh
					$sent_request = array( 'sent' => false );
				}

				if ( ! empty( $request['sent_ids'] ) ) {
					$count = count( $request['sent_ids'] );
				}
			}
			update_option( WP_SMPRO_PREFIX . "current-requests", $current_requests );

			global $wp_locale;
			$locale = array(
				'decimal'       => $wp_locale->number_format['decimal_point'],
				'thousands_sep' => $wp_locale->number_format['thousands_sep']
			);


			wp_localize_script( 'wp-smpro-queue', 'wp_smpro_locale', $locale );

			// localise counts
			wp_localize_script( 'wp-smpro-queue', 'wp_smpro_sent_ids', $sent_ids );

			// Bulk request sent
			wp_localize_script( 'wp-smpro-queue', 'wp_smpro_request_sent', $sent_request );

			$this->setPollInterval( $count );

		}

		function setPollInterval( $count, $return = false ) {
			//Set Interval for po
			//lling
			if ( $count <= 5 ) {
				//Poll every 30 seconds
				$interval = 30000;
			} else {
				//Poll every minute
				$interval = MINUTE_IN_SECONDS * 1000;
			}
			if ( $return ) {
				return $interval;
			}
			// Bulk request sent
			wp_localize_script( 'wp-smpro-queue', 'wp_smpro_poll_interval', array( 'interval' => $interval ) );
		}

		function admin_notice() {
			if ( boolval( get_option( WP_SMPRO_PREFIX . 'bulk-received', 0 ) ) && ! get_option( 'hide_smush_notice' ) ) {
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

			$class = $this->api_connected ? ' connected' : ' waiting';
			$text  = $this->api_connected ? __( 'API Connected', WP_SMPRO_DOMAIN ) : __( 'API Not Connected', WP_SMPRO_DOMAIN );

			//Style for container if there is no API key
			$style = ''; ?>
			<!--			Check API status-->
			<script type="text/javascript">
				jQuery(document).ready(function () {
					jQuery.ajax({
						type: "GET",
						url: ajaxurl + '?action=smush_api_status'
					}).done(function (response) {
						// button id
						var button_id = jQuery('.wp-smush-button').attr('id');

						if (typeof response.success !== 'undefined' && response.success) {
							//API Connected
							jQuery('.api-status').removeClass('waiting').addClass('connected').attr('title', '<?php echo __('API Connected', WP_SMPRO_DOMAIN ); ?>');
							jQuery('.api-status-text').html('<?php echo __('API Connected', WP_SMPRO_DOMAIN ); ?>');

							//enable if send or fetch button
							if ('wp-smpro-send' == button_id || 'wp-smpro-fetch' == button_id) {
								jQuery('#' + button_id).removeAttr('disabled');
							}
						} else {
							/* API not connected */
							jQuery('.api-status').removeClass('waiting').addClass('not-connected');
							jQuery('.api-status-text').html('<?php echo __('API Not Connected', WP_SMPRO_DOMAIN ); ?>');
							/* Disable button */
							jQuery('#' + button_id).html('<?php echo __('Service unavailable!', WP_SMPRO_DOMAIN ); ?>');

						}
					});
				});
			</script>
			<?php
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
			$send_ids = $received_ids = '';
			//Check if there are input ids in URL
			if ( ! empty( $_REQUEST['ids'] ) ) {

				$current_bulk_request = get_option( WP_SMPRO_PREFIX . "bulk-sent", false );

				if ( ! empty( $current_bulk_request ) ) {
					?>
					<div class="error">
					<p><?php _e( 'Bulk smush failed, as another bulk request is already being processed.' ); ?></p>
					</div><?php
				} else {
					if ( $this->set_api_status() ) {
						global $wp_smpro;

						$ids      = $_REQUEST['ids'];
						$send_ids = $received_ids = explode( ',', $ids );

						//Check if already smushed
						foreach ( $send_ids as $index => $id ) {
							$is_smushed = get_post_meta( $id, WP_SMPRO_PREFIX . 'is-smushed', true );
							if ( $is_smushed ) {
								unset( $send_ids[ $index ] );
							}
						}

						if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'wp-smpro-admin' ) ) {
							?>
							<div class="error"><?php _e( 'Nonce verification failed' ); ?></div><?php
						} else {
							if ( ! empty( $send_ids ) ) {
								$wp_smpro->sender->send_request( $send_ids );
								$this->setup_counts();
							}
						}
					} else {
						//display a error, images were not sent for smushing
						?>
						<div class="error">
						<p><?php _e( 'Images not sent for smushing as API is unreachable.' ); ?></p>
						</div><?php
					}
					//Query again, as images were sent recently
					$current_bulk_request = get_option( WP_SMPRO_PREFIX . "bulk-sent", 0 );
					$sent                 = ! empty( $current_bulk_request ) ? array( 'sent' => true ) : array( 'sent' => false );

					// Bulk request sent
					?>
					<script type="text/javascript">
						/* <![CDATA[ */
						wp_smpro_request_sent = <?php echo json_encode( $sent ); ?>;
						wp_smpro_poll_interval = <?php echo json_encode( array('interval' => $this->setPollInterval( count( $send_ids ), true ) ) ); ?>;
						wp_smpro_counts = <?php echo json_encode($this->counts); ?>;
						/* ]]> */
					</script> <?php
				}
			}
			?>
			<div class="wrap">
				<div id="icon-upload" class="icon32"><br/></div>

				<h2><?php printf( __( 'WP Smush Pro <span title="%s" class="api-status%s">%s</span><span class="api-status-text">%s</span>', WP_SMPRO_DOMAIN ), $text, $class, $text, __( "Checking API status..." ) ); ?></h2>

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
						<h4><?php _e( 'Send images', WP_SMPRO_DOMAIN ); ?></h4>
						<ul class="bulk-instruction-send"><?php
							for ( $i = 1; $i < 4; $i ++ ) {
								?>
								<li>
								<img src="<?php echo WP_SMPRO_URL . 'assets/images/step-' . $i . '.png'; ?>" alt="<?php _e( 'Bulk Smushing Send Instruction', WP_SMPRO_DOMAIN ); ?>">
								</li><?php
							}
							?>
						</ul>
						<p><?php _e( 'Wait for your images to be sent to our server to be smushed. Once sent you may leave the page, we will email you when smushed images are ready to be fetched back.' ); ?></p>

						<h4><?php _e( 'Fetch images', WP_SMPRO_DOMAIN ); ?></h4>
						<ul class="bulk-instruction-fetch"><?php
							for ( $i = 4; $i < 6; $i ++ ) {
								?>
								<li>
								<img src="<?php echo WP_SMPRO_URL . 'assets/images/step-' . $i . '.png'; ?>" alt="<?php _e( 'Bulk Smushing Send Instruction', WP_SMPRO_DOMAIN ); ?>">
								</li><?php
							}
							?>
						</ul>
						<p><?php _e( 'Once you receive an email, you can click a link in there or go to WP Smush Pro settings page to fetch smushed images.' ); ?></p>
					</div>

					<?php
					// display the bulk ui
					$this->bulk_ui( $send_ids, $received_ids );
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
			// default value
			$notify_at = get_option( WP_SMPRO_PREFIX . 'notify-at' );
			$notify_at = ! empty( $notify_at ) ? $notify_at : get_option( 'admin_email' );
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
				<p>
					<label><?php _e( '<b>Get notifications at:</b> ', WP_SMPRO_DOMAIN ); ?>
						<input type="text" name="<?php echo WP_SMPRO_PREFIX . 'notify-at'; ?>" id="<?php echo WP_SMPRO_PREFIX . 'notify-at'; ?>" value="<?php echo $notify_at; ?>" size="50"/>
						<br/>
						<small><?php _e( '* If left empty, it uses admin email address for the site', WP_SMPRO_DOMAIN ); ?></small>
					</label>
				</p>
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
			//Process email address
			if ( isset( $_POST[ WP_SMPRO_PREFIX . 'notify-at' ] ) ) {
				$email = ! empty( $_POST[ WP_SMPRO_PREFIX . 'notify-at' ] ) ? $_POST[ WP_SMPRO_PREFIX . 'notify-at' ] : '';

				if ( ! empty( $email ) ) {
					//Validate
					if ( filter_var( $_POST[ WP_SMPRO_PREFIX . 'notify-at' ], FILTER_VALIDATE_EMAIL ) ) {
						//save option
						update_option( WP_SMPRO_PREFIX . 'notify-at', $_POST[ WP_SMPRO_PREFIX . 'notify-at' ] );
					}else{
						?>
						<div class="error">
							<p><?php _e( 'Invalid email address.', WP_SMPRO_DOMAIN ); ?></p>
						</div><?php
					}
				} else {
					//Update null
					update_option( WP_SMPRO_PREFIX . 'notify-at', '' );
				}
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
		function bulk_ui( $send_ids, $received_ids ) {
			// set up some variables and print out some js vars
			$this->all_ui( $send_ids, $received_ids );
			?>
		<?php
		}

		/**
		 * The UI for bulk smushing
		 *
		 * @return null
		 */
		function all_ui( $send_ids, $received_ids ) {

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
					$this->selected_ui( $send_ids, $received_ids );
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
			                . '<span id="human">' . $this->counts['human'] . '</span>( <span id="percent">' . number_format_i18n( $this->counts['percent'], 2, '.', '' ) . '</span>% )
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
					                '<span class="done-count">%d</span> of <span class="sent-count">%d</span> sent attachments have been fetched', WP_SMPRO_DOMAIN
				                ), $this->counts['smushed'], $this->counts['sent']
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
			$request_status = get_option( WP_SMPRO_PREFIX . 'request_status' );
			$button         = $this->button_state( $request_status );
			$reset_button   = '';
			//Don't show a reset button if request is ready to fetch or it is not yet sent
			if ( $button['id'] !== 'wp-smpro-fetch' && $button['id'] !== 'wp-smpro-send' && $button['id'] != 'wp-smpro-finished' ) {
				$reset_button = $this->reset_bulk_button();
			}
			?>
			<div class="smush-notices update checking-status">
				<p><?php
					_e( 'Checking smush progress...', WP_SMPRO_DOMAIN );
					$this->print_spinner(); ?>
			</div>
			<button id="<?php echo $button['id']; ?>" class="button button-primary wp-smush-button" <?php echo $button['disabled']; ?>>
				<span><?php echo $button['text'] ?></span>
			</button>
			<?php
			if ( ! empty( $reset_button ) ) {
				echo $reset_button;
			}
			if ( $button['id'] == 'wp-smpro-fetch' ) {
				//show cancel button only for fetching
				?>
			<button id="wp-smpro-cancel" class="button button-secondary disabled" <?php echo $button['cancel']; ?>>
				<span><?php _e( 'Cancel', WP_SMPRO_DOMAIN ); ?></span>
				</button><?php
			}
		}

		/**
		 * Returns Bulk smush button id and other details, as per if bulk request is already sent or not
		 *
		 * @param $request_status
		 *
		 * @return array
		 */
		private function button_state( $request_status ) {
			$button = array(
				'cancel' => false,
			);
			//Check for sent ids
			$current_requests = get_option( WP_SMPRO_PREFIX . "current-requests", array() );

			// otherwise we have something to smush
			// check if we are awaiting a bulk request's smush response
			$is_bulk_sent = boolval( get_option( WP_SMPRO_PREFIX . "bulk-sent", 0 ) );

			// check if we have received this bulk request's callback
			$is_bulk_received = boolval( get_option( WP_SMPRO_PREFIX . "bulk-received", 0 ) );

			//check if current_requests and bulk sent exists
			if ( empty( $current_requests ) && $is_bulk_received ) {

				update_option( WP_SMPRO_PREFIX . 'hide-notice', 1 );

				//probably someone tempered with data, cleanup
				error_log( "Inconsistent data, Current requests was empty, while bulk received was set" );

				update_option( WP_SMPRO_PREFIX . "bulk-received", 0 );
				$is_bulk_received = false;
			}
			// if we have nothing left to smush
			// disable the buttons
			if ( $this->counts['smushed'] === $this->counts['total'] ) {
				$button['text']     = __( 'All done!', WP_SMPRO_DOMAIN );
				$button['id']       = "wp-smpro-finished";
				$button['disabled'] = ' disabled="disabled"';
				$button['cancel']   = ' disabled="disabled"';

				return $button;
			} elseif ( $this->counts['sent'] === $this->counts['total'] && $is_bulk_sent && ! $is_bulk_received ) {
				$button['text']     = __( 'Smushing in progress', WP_SMPRO_DOMAIN );
				$button['id']       = "wp-smpro-waiting";
				$button['disabled'] = ' disabled="disabled"';
				$button['cancel']   = ' disabled="disabled"';

				return $button;
			} elseif ( $this->counts['sent'] === $this->counts['total'] && ! $is_bulk_sent ) {
				$button['text']     = __( 'Send Smush Request', WP_SMPRO_DOMAIN );
				$button['disabled'] = ' disabled="disabled"';
				$button['id']       = "wp-smpro-send";

				return $button;
			}

			// a bulk request has been sent but not received
			if ( $is_bulk_sent && ! $is_bulk_received ) {
				if ( $request_status == 'not_reachable' ) {
					$button['text'] = __( 'Request failed', WP_SMPRO_DOMAIN );
				} else {
					$button['text'] = __( 'Smushing in progress', WP_SMPRO_DOMAIN );
				}
				$button['id']       = "wp-smpro-waiting";
				$button['disabled'] = ' disabled="disabled"';
				$button['cancel']   = ' disabled="disabled"';

				return $button;
			}

			// no bulk request awaited
			if ( ! $is_bulk_sent && ! $is_bulk_received ) {

				$button['text'] = __( 'Send Smush Request', WP_SMPRO_DOMAIN );

				$button['disabled'] = $this->api_connected ? false : ' disabled="disabled"';
				$button['cancel']   = ' disabled="disabled"';
				$button['id']       = "wp-smpro-send";

				return $button;
			}

			// bulk request has been smushed and callback was received
			if ( $is_bulk_received && ! empty( $current_requests ) ) {
				// if API not connected

				$button['text']     = __( 'Fetch smushed images', WP_SMPRO_DOMAIN );
				$button['disabled'] = $this->api_connected ? false : ' disabled="disabled"';
				$button['cancel']   = false;
				$button['id']       = "wp-smpro-fetch";

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
		 * Set Up API Status
		 */
		function set_api_status() {
			global $log;

			//If we don't have api key
			if ( ! get_site_option( 'wpmudev_apikey', false ) ) {
				update_option( 'api_connected', false );

				return false;
			}

			if ( defined( 'WP_SMPRO_SERVICE_STATUS' ) ) {
				$req_args = array(
					'user-agent' => WP_SMPRO_USER_AGENT,
					'referrer'   => WP_SMPRO_REFRER,
					'timeout'    => WP_SMPRO_TIMEOUT,
					'sslverify'  => false
				);
				$api      = wp_remote_get( WP_SMPRO_SERVICE_STATUS, $req_args );
			}
			if ( empty( $api ) || is_wp_error( $api ) || $api['response']['code'] != 200 ) {
				update_option( 'api_connected', false );
				if ( is_wp_error( $api ) ) {
					$message = ! empty( $api ) ? json_encode( $api ) : __( 'No response from API', WP_SMPRO_DOMAIN );
					$log->error( 'WpSmProAdmin: set_api_status', $message );
				}

				return false;
			}
			$this->api_connected = true;
			update_option( 'api_connected', true );

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

			$smush_data['bytes'] = $smush_data['size_before'] - $smush_data['size_after'];

			if ( $smush_data['bytes'] < 0 ) {
				$smush_data['bytes'] = 0;
			}

			if ( $smush_data['size_before'] > 0 ) {
				$smush_data['percent'] = ( $smush_data['bytes'] / $smush_data['size_before'] ) * 100;
			}

			$smush_data['human'] = $wp_smpro->format_bytes( $smush_data['bytes'] );

			return $smush_data;
		}

		/**
		 * Update attachment ids sent for smushing
		 */
		function update_sent_ids( $attachment_id ) {

			$sent_ids = get_option( WP_SMPRO_PREFIX . 'sent-ids', false );

			if ( empty( $sent_ids ) ) {
				return;
			}
			// Search
			$pos = array_search( $attachment_id, $sent_ids );
			if ( ! $pos ) {
				return;
			}
			unset( $sent_ids[ $pos ] );
			update_option( WP_SMPRO_PREFIX . 'sent-ids', $sent_ids );

			//Update Current requests
			$current_requests = get_option( WP_SMPRO_PREFIX . 'current-requests', array() );
			if ( ! empty( $current_requests ) ) {
				foreach ( $current_requests as $req_id => $request ) {
					$index = array_search( $attachment_id, $request['sent_ids'] );
					if ( $index ) {
						unset( $current_requests[ $req_id ]['sent_ids'][ $index ] );
						if ( empty( $current_requests[ $req_id ]['sent_ids'] ) ) {
							unset( $current_requests[ $req_id ] );
						}
						update_option( WP_SMPRO_PREFIX . 'current-requests', $current_requests );
					}
				}
			}
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
			update_option( 'hide_smush_notice', 1 );
			wp_send_json_success();
		}

		/**
		 * Display Thumbnails, if bulk action is choosen
		 */
		function selected_ui( $send_ids, $received_ids ) {
			if ( empty( $received_ids ) ) {
				return;
			}

			?>
			<div id="select-bulk" class="wp-smpro-bulk-wrap">
				<p>
					<?php
					printf(
						__(
							'<strong>%d of %d images</strong> were sent for smushing:',
							WP_SMPRO_DOMAIN
						),
						count( $send_ids ), count( $received_ids )
					);
					?>
				</p>
				<ul id="wp-smpro-selected-images">
					<?php
					foreach ( $received_ids as $attachment_id ) {
						$this->attachment_ui( $attachment_id );
					}
					?>
				</ul>
			</div>
		<?php
		}

		function attachment_ui( $attachment_id ) {
			$type         = get_post_mime_type( $attachment_id );
			$ext_arr      = explode( '/', $type );
			$ext          = $ext_arr[1];
			$title        = get_the_title( $attachment_id );
			$title_length = 12;
			if ( mb_strlen( $title ) > $title_length ) {
				$title = mb_substr( $title, 0, $title_length - 1 ) . '&hellip;';
			}

			$src = wp_get_attachment_thumb_url( $attachment_id );

			$status_msg = $this->media_lib->set_status( $attachment_id, false, true );
			$is_smushed = get_post_meta( $attachment_id, WP_SMPRO_PREFIX . 'is-smushed' );
			$class      = $is_smushed ? ' class="smush-done"' : '';

			?>
			<li id="wp-smpro-img-<?php echo $attachment_id; ?>"<?php echo $class; ?>>
				<div class="img-wrap">
					<div class="img-status"></div>
					<img class="img-thumb" src="<?php echo $src; ?>">
					<span class="img-type"><?php echo $ext; ?></span>
				</div>
				<div class="img-descr">
					<p class="img-meta">
						<?php echo $attachment_id; ?> - <?php echo $title; ?>
					</p>

					<p class="img-smush-status"><?php echo $status_msg; ?></p>
				</div>
			</li>
		<?php

		}

		/**
		 * Creates Admin Debug info page.
		 *
		 * @access private.
		 */
		function create_admin_error_log_page() {
			global $log;
			if ( ! empty( $_GET['action'] ) && 'purge' == @$_GET['action'] ) {
				//Check Nonce
				if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'purge_log' ) ) {
					echo '<div class="error"><p>' . __( 'Nonce verification failed', WP_SMPRO_DOMAIN ) . '</p></div>';
				} else {
					$log->purge_errors();
					$log->purge_notices();
				}
			}
			$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'error';
			?>
			<div class="wrap">
				<?php $this->error_log_tabs(); ?>
				<?php $this->error_log_tabs_content( $tab ); ?>
			</div>
		<?php
		}

		/**
		 * Create required tabs
		 */
		function error_log_tabs() {
			$current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'error';

			$tabs = array(
				'error'    => __( 'Error Log', WP_SMPRO_DOMAIN ),
				'requests' => __( 'Smush Requests', WP_SMPRO_DOMAIN ),
				'notice'   => __( 'Notice', WP_SMPRO_DOMAIN )
			);
			echo '<h2 class="nav-tab-wrapper">';
			foreach ( $tabs as $tab_key => $tab_caption ) {
				$active = $current_tab == $tab_key ? 'nav-tab-active' : '';
				echo '<a class="nav-tab ' . $active . '" href="?page=wp-smpro-debug&tab=' . $tab_key . '">' . $tab_caption . '</a>';
			}
			echo '</h2>';
		}

		/**
		 * Echo the content of respective tab
		 *
		 * @param string $current
		 */
		function error_log_tabs_content( $current = 'error' ) {
			$tabs = array(
				'error'    => __( 'Error Log', WP_SMPRO_DOMAIN ),
				'requests' => __( 'Smush Requests', WP_SMPRO_DOMAIN ),
				'notice'   => __( 'Notice', WP_SMPRO_DOMAIN )
			);
			switch ( $current ) {
				case 'error' :
					require_once( WP_SMPRO_DIR . 'lib/forms/error_log.php' );
					break;
				case 'notice' :
					require_once( WP_SMPRO_DIR . 'lib/forms/notice_log.php' );
					break;
				case 'requests';
					$this->get_smush_requests();
					break;
			}
		}

		/**
		 * Get all the current sent Single requests for the website
		 */
		function get_current_single_requests() {

			global $wp_version;
			if ( $wp_version < '3.9' ) {
				$meta_query =
					array(
						'relation' => 'AND',
						array(
							'key'     => WP_SMPRO_PREFIX . 'is-smushed',
							'value'   => 'smush',
							'compare' => 'NOT EXISTS'
						),
						array(
							'key'     => WP_SMPRO_PREFIX . 'request-id',
							'compare' => 'EXISTS'

						)
					);
			} else {
				$meta_query =
					array(
						'relation' => 'AND',
						array(
							'key'     => WP_SMPRO_PREFIX . 'is-smushed',
							'compare' => 'NOT EXISTS'
						),
						array(
							'key'     => WP_SMPRO_PREFIX . 'request-id',
							'compare' => 'EXISTS'

						)
					);
			}

			//Query all the posts which are not smushed "wp-smpro-is-smushed" but sent for smushing "wp-smpro-request-id"
			$args         = array(
				'fields'         => 'ids',
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'post_mime_type' => array( 'image/jpeg', 'image/gif', 'image/png' ),
				'order'          => 'ASC',
				'posts_per_page' => - 1,
				'meta_query'     => $meta_query
			);
			$sent_singles = new WP_Query( $args );
			if ( ! empty( $sent_singles->posts ) ) {
				return $sent_singles->posts;
			} else {
				return array();
			}
		}

		/**
		 * Get all the Bulk and Single smush requests
		 */
		function get_smush_requests() {

			//get thee bulk requests
			$bulk_request    = get_option( WP_SMPRO_PREFIX . "bulk-sent" );
			$single_requests = $this->get_current_single_requests(); ?>
			<h3><?php _e( 'Bulk Request' ); ?></h3><?php

			if ( empty( $bulk_request ) ) { ?>
				<p><?php _e( 'Yayyy! No pending bulk requests.', WP_SMPRO_DOMAIN ); ?></p><?php
			} else {
				$current_requests = get_option( WP_SMPRO_PREFIX . 'current-requests' );
				$current_bulk_req = ! empty( $current_requests[ $bulk_request ] ) ? $current_requests[ $bulk_request ] : '';
				$date             = '';
				if ( ! empty( $current_bulk_req ) ) {
					$timestamp = ! empty( $current_bulk_req['timestamp'] ) ? $current_bulk_req['timestamp'] : '';
					$date      = date( 'Y-m-d H:i:s e', (int) $timestamp );
					$received  = ! empty( $current_bulk_req['received'] ) ? $current_bulk_req['received'] : false;
					$status    = ( $received && $received == 1 ) ? 'Completed' : 'Pending';
				} ?>

				<table class="widefat">
				<thead>
				<tr>
					<th><?php _e( 'Request id', WP_SMPRO_DOMAIN ) ?></th>
					<th><?php _e( 'Date', WP_SMPRO_DOMAIN ) ?></th>
					<th><?php _e( 'Status', WP_SMPRO_DOMAIN ) ?></th>
				</tr>
				</thead>
				<tfoot>
				<tr>
					<th><?php _e( 'Request id', WP_SMPRO_DOMAIN ) ?></th>
					<th><?php _e( 'Date', WP_SMPRO_DOMAIN ) ?></th>
					<th><?php _e( 'Status', WP_SMPRO_DOMAIN ) ?></th>
				</tr>
				</tfoot>
				<tbody>
				<tr>
					<td><?php echo $bulk_request; ?></td>
					<td><?php echo $date; ?></td>
					<td><?php echo $status; ?></td>
				</tr>
				</tbody>
				</table><?php

			} ?>
			<h3><?php _e( 'Single requests', WP_SMPRO_DOMAIN ); ?></h3><?php
			if ( empty( $single_requests ) ) { ?>
				<p><?php _e( "Yayyy! No pending single request." ); ?></p><?php
			} else {
				?>
				<table class="widefat">
				<thead>
				<tr>
					<th><?php _e( 'Request id', WP_SMPRO_DOMAIN ) ?></th>
					<th><?php _e( 'Date', WP_SMPRO_DOMAIN ) ?></th>
					<th><?php _e( 'Status', WP_SMPRO_DOMAIN ) ?></th>
				</tr>
				</thead>
				<tfoot>
				<tr>
					<th><?php _e( 'Request id', WP_SMPRO_DOMAIN ) ?></th>
					<th><?php _e( 'Date', WP_SMPRO_DOMAIN ) ?></th>
					<th><?php _e( 'Status', WP_SMPRO_DOMAIN ) ?></th>
				</tr>
				</tfoot>
				<tbody><?php
				foreach ( $single_requests as $attachment_id ) {
					//get request id
					$request_id = get_post_meta( $attachment_id, WP_SMPRO_PREFIX . 'request-id', true );
					//if request id exists
					if ( ! empty( $request_id ) ) {
						//get request details
						$request   = get_post_meta( $attachment_id, WP_SMPRO_PREFIX . 'request-' . $request_id, true );
						$timestamp = ! empty( $request['timestamp'] ) ? $request['timestamp'] : '';
						$date      = date( 'Y-m-d H:i:s e', (int) $timestamp );
						$status    = 'Pending'; ?>
						<tr>
						<td><?php echo $request_id; ?></td>
						<td><?php echo $date; ?></td>
						<td><?php echo $status; ?></td>
						</tr><?php
					}
				} ?>
				</tbody><?php
			}
		}

		function attachment_status() {
			$id          = $_REQUEST['id'];
			$status_text = $this->media_lib->smush_status( $id );
			wp_send_json_success( $status_text );
			die();
		}

		/**
		 * Send a POST request to smush API, to remove a bulk request
		 *
		 * @param $request_id
		 * @param $nonce
		 */
		function request_API_bulk_reset( $request_id, $token ) {
			global $wp_smpro;
			//missing details, do not send a request
			if ( empty( $request_id ) || empty( $token ) ) {
				return false;
			}

			return $wp_smpro->sender->reset_bulk( $request_id, $token );

		}

		/**
		 * Ajax handler for resetting Bulk request
		 */
		function reset_bulk_request() {
			//Verify nonce, send error if it fails
			if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'reset_bulk_request' ) ) {
				wp_send_json_error( 'Nonce verification failed' );
			}

			$reset_done = $this->resetBulkRequest();
			if ( $reset_done && empty( $reset_done['error'] ) ) {
				wp_send_json_success( __( "The current bulk request was removed for you, you can send a new one anytime! Reloading page in <span id='counter'>5</span>s..", WP_SMPRO_DOMAIN ) );
			} else {
				$message = ! empty( $reset_done['error'] ) ? $reset_done['error'] : __( 'Ah crap! Some error occurred while resetting the request for you. Reload the page and try to reset it again.' );
				wp_send_json_error( $message );
			}
			die( 1 );

		}

		/**
		 * Delete the sent ids from current requests and sent ids for the recent bulk request
		 */
		function resetBulkRequest() {
			global $wp_smpro;
			$sent_ids_diff = array();
			$message       = array();
			$bulk_request  = get_option( WP_SMPRO_PREFIX . "bulk-sent", array() );

			$current_requests = get_option( WP_SMPRO_PREFIX . "current-requests", array() );

			if ( empty( $bulk_request ) || empty( $current_requests ) ) {
				$message['error'] = __( "There is no pending bulk request.", WP_SMPRO_DOMAIN );

				return $message;
			}
			$sent_ids[ $bulk_request ]['sent_ids'] = ! empty( $current_requests[ $bulk_request ] ) ? $current_requests[ $bulk_request ]['sent_ids'] : '';

			$sent_ids_list = get_option( WP_SMPRO_PREFIX . 'sent-ids', array() );

			if ( is_array( $sent_ids[ $bulk_request ]['sent_ids'] ) && is_array( $sent_ids_list ) && count( $sent_ids ) > 0 ) {
				$sent_ids_diff = array_diff( $sent_ids_list, $sent_ids[ $bulk_request ]['sent_ids'] );
			}

			//send a request to API, to reset the request from there too, as we don't want to waste the resources
			$response = $wp_smpro->sender->reset_bulk( $bulk_request, $current_requests[ $bulk_request ]['token'] );

			//Server is down or other issue
			if ( is_wp_error( $response ) || $response['api']['response']['code'] !== 200 ) {
				return false;
			} else {

				$response_body = wp_remote_retrieve_body( $response['api'] );
				$response_body = json_decode( $response_body );
				//if response has body
				if ( ! empty( $response_body ) ) {
					//Check if bulk request was not removed
					if ( ! $response_body->success ) {
						return false;
					}
				} else {
					return false;
				}
			}
			//Update sent ids
			update_option( WP_SMPRO_PREFIX . 'sent-ids', $sent_ids_diff );

			//Update current request
			unset( $current_requests[ $bulk_request ] );
			$current_requests_updated = update_option( WP_SMPRO_PREFIX . "current-requests", $current_requests );

			//update bulk sent
			$bulk_sent_updated = update_option( WP_SMPRO_PREFIX . "bulk-sent", array() );

			if ( $current_requests_updated && $bulk_sent_updated ) {
				return true;
			}

			return false;
		}

		/**
		 * Creates a reset button for bulk request
		 */
		function reset_bulk_button() {
			$reset_nonce = wp_nonce_field( 'reset_bulk_request', 'wp-smpro-reset-nonce', '', false );
			//Check URL for show_smush arg
			$class        = ( ! empty( $_REQUEST[ WP_SMPRO_PREFIX . 'allow_reset' ] ) && $_REQUEST[ WP_SMPRO_PREFIX . 'allow_reset' ] == 'true' ) ? '' : ' class="hide"';
			$reset_button = '<a href="#" id="wp-smpro-reset-bulk"' . $class . '>' . __( 'Reset bulk request', WP_SMPRO_PREFIX ) . '</a>';

			return $reset_button . $reset_nonce;
		}

		/**
		 * Ajax Callback to check API status
		 */
		function smush_api_status() {
			$this->api_connected = $this->set_api_status();
			if ( $this->api_connected ) {
				wp_send_json_success();
			} else {
				wp_send_json_error();
			}
			die();
		}

	}

}