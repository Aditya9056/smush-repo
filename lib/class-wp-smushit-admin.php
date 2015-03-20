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
	class WpSmushitAdmin {

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

			// hook scripts and styles
			add_action( 'admin_init', array( $this, 'register' ) );

			// hook custom screen
			add_action( 'admin_menu', array( $this, 'screen' ) );

			add_action( 'admin_footer-upload.php', array( $this, 'print_loader' ) );

			//Handle Smush Ajax
			add_action( 'wp_ajax_wp_smushit_bulk', array( $this, 'process_smush_request' ) );


			add_action( 'admin_notices', array( $this, 'depreciated_warning' ) );
		}

		/**
		 * Add Bulk option settings page
		 */
		function screen() {
			$admin_page_suffix = add_media_page( 'Bulk WP Smush.it', 'WP Smush.it', 'edit_others_posts', 'wp-smushit-bulk', array(
				$this,
				'ui'
			) );
			//Register Debug page only if WP_SMPRO_DEBUG is defined and true
			if ( defined( 'WP_SMUSHIT_DEBUG' ) && WP_SMUSHIT_DEBUG ) {
				add_media_page( 'WP Smush.it Error Log', 'Error Log', 'edit_others_posts', 'wp-smushit-errorlog', array(
					$this,
					'create_admin_error_log_page'
				) );
			}
			// enqueue js only on this screen
			add_action( 'admin_print_scripts-' . $admin_page_suffix, array( $this, 'enqueue' ) );
		}

		/**
		 * Register js and css
		 */
		function register() {
			global $WpSmushit;
			/* Register our script. */
			wp_register_script( 'wp-smushit-admin-js', WP_SMUSHIT_URL . 'assets/js/wp-smushit-admin.js', array( 'jquery' ), $WpSmushit->version );

			/* Register Style. */
			wp_register_style( 'wp-smushit-admin-css', WP_SMUSHIT_URL . 'assets/css/wp-smushit-admin.css', array(), $WpSmushit->version );

			// localize translatable strings for js
			$this->localize();
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

			$wp_smushit_msgs = array(
				'progress' => __( 'Smushing in Progress', WP_SMUSHIT_DOMAIN ),
				'done'     => __( 'All done!', WP_SMUSHIT_DOMAIN )
			);

			wp_localize_script( $handle, 'wp_smushit_msgs', $wp_smushit_msgs );

			//Localize smushit_ids variable, if there are fix number of ids
			$ids = ! empty( $_REQUEST['ids'] ) ? explode( ',', $_REQUEST['ids'] ) : $bulk->get_attachments();
			wp_localize_script( 'wp-smushit-admin-js', 'wp_smushit_ids', $ids );

		}

		/**
		 * Display the ui
		 */
		function ui() {
			?>
			<div class="wrap">
				<div id="icon-upload" class="icon32"><br/></div>

				<h2>
					<?php _e( 'WP Smush.it', WP_SMUSHIT_DOMAIN ) ?>
				</h2>

				<h3><span class="dashicons dashicons-megaphone" style="color:red"></span> Urgent Smush.it Notice</h3>
				<div class="error">
					<p>Yahoo appears to be either discontinuing or not supporting their free Smush.it service - bah!</p>
					<p>So, WPMU DEV is looking into how we can provide a free service to you that replaces this... but it's going to take some time.</p>
					<p>So, in the interim, we're providing a (very temporary) <strong>90% discount on any new WPMU DEV membership</strong> to WP Smush.It users so you can use our dedicated <a href="https://premium.wpmudev.org/project/wp-smush-pro/">Smush Pro</a> servers - <a href="https://premium.wpmudev.org/?coupon=SMUSHEMERGENCY#pricing">click here to take that up</a> (and please don't share it around).</p>
					<p>We will update the plugin as soon as we have it in place. Thanks, WPMU DEV</p>
				</div>

				<div class="wp-smpushit-container">
					<h3>
						<?php _e( 'Settings', WP_SMUSHIT_DOMAIN ) ?>
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
		}

		/**
		 * Process and display the options form
		 */
		function options_ui() {

			// Save settings, if needed
			$this->process_options();

			?>
			<form action="" method="post"><?php

				//Auto Smushing
				$auto     = 'wp_smushit_smushit_auto';
				$auto_val = intval( get_option( $auto, WP_SMUSHIT_AUTO_OK ) );
				$disabled = sprintf( __( 'Temporarily disabled until %s', WP_SMUSHIT_DOMAIN ), date( 'M j, Y \a\t H:i', $auto_val ) );

				//Timeout
				$timeout     = 'wp_smushit_smushit_timeout';
				$timeout_val = intval( get_option( $timeout, WP_SMUSHIT_AUTO_OK ) );

				//Enforce Same URL
				$enforce_same_url     = 'wp_smushit_smushit_enforce_same_url';
				$enforce_same_url_val = get_option( $enforce_same_url, WP_SMUSHIT_ENFORCE_SAME_URL );

				//Debug
				$smushit_debug     = 'wp_smushit_smushit_debug';
				$smushit_debug_val = get_option( $smushit_debug, WP_SMUSHIT_DEBUG );
				?>
				<table class="form-table">
					<tbody>
					<tr>
						<th><label><?php echo __( 'Smush images on upload', WP_SMUSHIT_DOMAIN ); ?></label></th>
						<td>
							<select name='<?php echo $auto; ?>' id='<?php echo $auto; ?>'>
								<option value='<?php echo WP_SMUSHIT_AUTO_OK; ?>' <?php selected( WP_SMUSHIT_AUTO_OK, $auto_val ); ?>><?php echo __( 'Automatically process on upload', WP_SMUSHIT_DOMAIN ); ?></option>
								<option value='<?php echo WP_SMUSHIT_AUTO_NEVER; ?>' <?php selected( WP_SMUSHIT_AUTO_NEVER, $auto_val ); ?>><?php echo __( 'Do not process on upload', WP_SMUSHIT_DOMAIN ); ?></option> <?php

								if ( $auto_val > 0 ) {
									?>
									<option value="<?php echo $auto_val; ?>" selected="selected"><?php echo $disabled; ?></option><?php
								} ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php _e( 'API Timeout', WP_SMUSHIT_DOMAIN ); ?></th>
						<td>
							<input type='text' name='<?php echo esc_attr( $timeout ); ?>' id='<?php echo esc_attr( $timeout ); ?>' value='<?php echo intval( get_option( $timeout, 60 ) ); ?>' size="2">
						</td>
					</tr>
					<tr>
						<th><?php _e( 'Enforce home URL', WP_SMUSHIT_DOMAIN ); ?></th>
						<td>
							<input type="checkbox" name="<?php echo $enforce_same_url ?>" <?php echo checked( $enforce_same_url_val, 'on' ); ?>/> <?php
							echo '<strong>' . get_option( 'home' ) . '</strong><br />' . __( 'By default the plugin will enforce that the image URL is the same domain as the home. If you are using a sub-domain pointed to this same host or an external Content Delivery Network (CDN) you want to unset this option.', WP_SMUSHIT_DOMAIN ); ?>
						</td>
					</tr>
					<tr>
						<th><?php _e( 'Smushit Debug', WP_SMUSHIT_DOMAIN ); ?></th>
						<td>
							<input type="checkbox" name="<?php echo $smushit_debug ?>" <?php echo checked( $smushit_debug_val, 'on' ); ?>/>
							<?php _e( 'If you are having trouble with the plugin enable this option can reveal some information about your system needed for support.', WP_SMUSHIT_DOMAIN ); ?>
						</td>
					</tr>
					</tbody>
				</table><?php
				// nonce
				wp_nonce_field( 'save_wp_smushit_options', 'wp_smushit_options_nonce' );
				?>
				<input type="submit" id="wp-smushit-save-settings" class="button button-primary" value="<?php _e( 'Save Changes', WP_SMUSHIT_DOMAIN ); ?>">
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
			if ( ! isset( $_POST['wp_smushit_options_nonce'] ) ) {
				return;
			}
			echo "here";
			// the nonce doesn't pan out
			if ( ! wp_verify_nonce( $_POST['wp_smushit_options_nonce'], 'save_wp_smushit_options' ) ) {
				return;
			}
			echo "there";

			//Array of options
			$smushit_settings = array(
				'wp_smushit_smushit_auto',
				'wp_smushit_smushit_timeout',
				'wp_smushit_smushit_enforce_same_url',
				'wp_smushit_smushit_debug'
			);
			//Save All the options
			foreach ( $smushit_settings as $setting ) {
				if ( empty( $_POST[ $setting ] ) ) {
					update_option( $setting, '' );
					continue;
				}
				update_option( $setting, $_POST[ $setting ] );
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
				$exceed_mb = sprintf( __( " %d of those images %s <b>over 1Mb</b> and <b>can not be compressed using the free version of the plugin.</b>", WP_SMUSHIT_DOMAIN ), $count, $text );
			}
			$media_lib = get_admin_url( '', 'upload.php' );
			?>
			<div class="wrap">
				<div id="icon-upload" class="icon32"><br/></div>
				<h3><?php _e( 'Smush in Bulk', WP_SMUSHIT_DOMAIN ) ?></h3>
				<?php

				if ( sizeof( $attachments ) < 1 ) {
					_e( "<p>You don't appear to have uploaded any images yet.</p>", WP_SMUSHIT_DOMAIN );
				} else {
					if ( ! isset( $_POST['smush-all'] ) && ! $auto_start ) { // instructions page
						$upgrade_link = "<a href='http://premium.wpmudev.org/project/wp-smush-pro'>" . __( "Upgrade to WP Smush PRO", WP_SMUSHIT_DOMAIN ) . "</a>";
						_e( "<h4>WP Smush.it uses Yahoo! Smush.it API. As such, there are a few restrictions:</h4>", WP_SMUSHIT_DOMAIN );
						?>
						<ol>
							<li><?php printf( __( "Each image MUST be less than 1Mb in size.  %s and use our servers for images upto 5Mb.", WP_SMUSHIT_DOMAIN ), $upgrade_link ); ?></li>
							<li><?php printf( __( "Images MUST be accessible via a non-https URL. The Yahoo! Smush.it service will not handle https:// image URLs. %s to allow https URLs", WP_SMUSHIT_DOMAIN ), $upgrade_link ); ?></li>
							<li><?php printf( __( "Smushing images in bulk can sometimes cause time-outs. %s and use our reliable server to prevent time-outs.", WP_SMUSHIT_DOMAIN ), $upgrade_link ); ?></li>
						</ol>
						<div class="smushit-pro-update-link" style="background: white; float: left; font-size: 18px; line-height: 1.4; margin: 0 0 10px; padding: 13px;">
							<?php _e( "<strong>WP Smush PRO</strong> allows you to smush images up to 5Mb.<br /> Fast, reliable & time-out free. <a href='http://premium.wpmudev.org/projects/wp-smush-pro'>Find Out more &raquo;</a>", WP_SMUSHIT_DOMAIN ); ?>
						</div>

						<hr style="clear: left;"/>

						<style type="text/css">
							.smush-instructions p {
								line-height: 1.2;
								margin: 0 0 4px;
							}
						</style>
						<div class="smush-instructions" style="line-height: 1;">
							<?php printf( __( "<p>We found %d images in your media library. %s </p>", WP_SMUSHIT_DOMAIN ), sizeof( $attachments ), $exceed_mb ); ?>

							<?php _e( "<p><b style='color: red;'>Please beware</b>, <b>smushing a large number of images can take a long time.</b></p>", WP_SMUSHIT_DOMAIN ); ?>

							<?php _e( "<p><b>You can not leave this page, until all images have been received back, and you see a success message.</b></p>", WP_SMUSHIT_DOMAIN ); ?>
							<br/>
							<?php printf( __( "Click below to smush all your images. Alternatively, you can smush your images individually or as a bulk action from your <a href='%s'>Media Library</a>", WP_SMUSHIT_DOMAIN ), $media_lib ); ?>
						</div>

						<!--					Bulk Smushing-->
						<?php wp_nonce_field( 'wp-smushit-bulk', '_wpnonce' ); ?>
						<br/>
						<?php $this->progress_ui(); ?>
						<button type="submit" class="button-primary action" name="smush-all"><?php _e( 'Bulk Smush all my images', WP_SMUSHIT_DOMAIN ) ?></button>
						<?php _e( "<p><em>N.B. If your server <tt>gzip</tt>s content you may not see the progress updates as your files are processed.</em></p>", WP_SMUSHIT_DOMAIN ); ?>
						<?php
						if ( WP_SMUSHIT_DEBUG ) {
							_e( "<p>DEBUG mode is currently enabled. To disable uncheck the smushit debug option.</p>", WP_SMUSHIT_DOMAIN );
						}
					}
				}
				?>
			</div>
		<?php
		}

		function print_loader() {
			?>
			<div id="wp-smushit-loader-wrap">
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

		function progress_ui() {
			$bulk  = new WpSmushitBulk;
			$total = count( $bulk->get_attachments() );
			$total = $total ? $total : 1; ?>

			<div id="progress-ui">
				<div id="smush-status" style="margin: 0 0 5px;"><?php printf( __( 'Smushing <span id="smushed-count">1</span> of <span id="smushing-total">%d</span>', WP_SMUSHIT_DOMAIN ), $total ); ?></div>
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

			global $WpSmushit;

			if ( empty( $_REQUEST['attachment_id'] ) ) {
				wp_send_json_error( 'missing id' );
			}

			$attachment_id = $_REQUEST['attachment_id'];

			$original_meta = wp_get_attachment_metadata( $attachment_id, true );

			$meta = $WpSmushit->resize_from_meta_data( $original_meta, $attachment_id, false );

			wp_update_attachment_metadata( $attachment_id, $meta );

			wp_send_json_success( 'processed' );
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
					echo '<div class="error"><p>' . __( 'Nonce verification failed', WP_SMUSHIT_DOMAIN ) . '</p></div>';
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
			require_once( WP_SMUSHIT_DIR . '/lib/error_log.php' );
		}

		function depreciated_warning() {
			if ( ! current_user_can('edit_others_posts') ) return;

			if ( isset( $_GET['page']) && 'wp-smushit-bulk' == $_GET['page'] ) {
				return;
			}

			if ( isset( $_GET['dismiss_smush_warning'] ) ) {
				update_option('dismiss_smush_warning', 1);
			}

			if ( get_option('dismiss_smush_warning') ) return;
			?>
			<div class="error">
				<a href="<?php echo admin_url('index.php'); ?>?dismiss_smush_warning=1" style="float:right;margin-top: 10px;text-decoration: none;"><span class="dashicons dashicons-dismiss" style="color:gray;"></span>Dismiss</a>
				<h3><span class="dashicons dashicons-megaphone" style="color:red"></span> Urgent Smush.it Notice</h3>
				<p>Yahoo appears to be either discontinuing or not supporting their free Smush.it service - bah!</p>
				<p>So, WPMU DEV is looking into how we can provide a free service to you that replaces this... but it's going to take some time.</p>
				<p>So, in the interim, we're providing a (very temporary) <strong>90% discount on any new WPMU DEV membership</strong> to WP Smush.It users so you can use our dedicated <a href="https://premium.wpmudev.org/project/wp-smush-pro/">Smush Pro</a> servers - <a href="https://premium.wpmudev.org/?coupon=SMUSHEMERGENCY#pricing">click here to take that up</a> (and please don't share it around).</p>
				<p>We will update the plugin as soon as we have it in place. Thanks, WPMU DEV</p>
			</div>
		<?php
		}
	}

//Add js variables for smushing
	$wpsmushit_admin = new WpSmushitAdmin();
}