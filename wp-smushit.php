<?php
/*
Plugin Name: WP Smush.it Pro
Plugin URI: http://premium.wpmudev.org/projects/wp-smushit-pro/
Description: Reduce image file sizes and improve performance using the <a href="http://smush.it/">Smush.it</a> API within WordPress.
Author: WPMU DEV
Version: 0.1
Author URI: http://premium.wpmudev.org/
Textdomain: wp-smushit-pro
WDP ID:
*/

/*
Copyright 2009-2014 Incsub (http://incsub.com)
Author - Saurabh Shukla & Umesh Kumar
    This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
                                                                      */

if ( ! function_exists( 'download_url' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/file.php' );
}

if ( ! class_exists( 'WpSmushitPro' ) ) {

	class WpSmushitPro {

		var $version = "0.1";

		/**
		 * Constructor
		 */
		function __construct() {

			$this->constants();
			$this->hooks();
		}
                
                function constants(){
                    /**
			 * Constants
			 */
			/**
			 * TODO: Fix the URL
			 */
			define( 'SMUSHIT_PRO_REQ_URL', 'http://107.170.2.190:1203/upload/' );
                        
			define( 'WP_SMUSHIT_PRO_DOMAIN', 'wp-smushit-pro' );

			//Updae this after confirmation
			define( 'WP_SMUSHIT_PRO_UA', "WP Smush.it/{$this->version} (+http://wordpress.org/extend/plugins/wp-smushit/)" );
			define( 'WP_SMUSHIT_PRO_PLUGIN_DIR', dirname( plugin_basename( __FILE__ ) ) );

			//Image Limit 5MB
			define( 'WP_SMUSHIT_PRO_MAX_BYTES', 5*1024*1024 );

                        // since we are going to send 1 request per image in any scenario, this will be deprecated
                        // 
                        // The number of images (including generated sizes) that can return errors before abandoning all hope.
			// N.B. this doesn't work with the bulk uploader, since it creates a new HTTP request
			// for each image.  It does work with the bulk smusher, though.
			define( 'WP_SMUSHIT_PRO_ERRORS_BEFORE_QUITTING', 3 * count( get_intermediate_image_sizes() ) );

			//Set default values
			define( 'WP_SMUSHIT_PRO_AUTO', intval( get_option( 'wp_smushit_pro_smushit_auto', 0 ) ) );
			define( 'WP_SMUSHIT_PRO_TIMEOUT', intval( get_option( 'wp_smushit_pro_smushit_timeout', 60 ) ) );
			
                        define( 'WP_SMUSH_PRO_REMOVE_EXIF', intval( get_option( 'wp_smushit_pro_remove_exif', true ) ) );

			define( 'WP_SMUSHIT_PRO_ENFORCE_SAME_URL', get_option( 'wp_smushit_pro_smushit_enforce_same_url', 'on' ) );

			if ( 
                                ! isset( $_GET['action'] )
                                || $_GET['action'] != "wp_smushit_pro_manual"
                                || (defined('WP_DEBUG') && WP_DEBUG===true)
                                ){
				define( 'WP_SMUSHIT_PRO_DEBUG', get_option( 'wp_smushit_pro_smushit_debug', '' ) );
			} else {
				define( 'WP_SMUSHIT_PRO_DEBUG', '' );
			}

			/*
			Each service has a setting specifying whether it should be used automatically on upload.
			Values are:
				-1  Don't use (until manually enabled via Media > Settings)
				0   Use automatically
				n   Any other number is a Unix timestamp indicating when the service can be used again
			*/

			define( 'WP_SMUSHIT_PRO_AUTO_OK', 0 );
			define( 'WP_SMUSHIT_PRO_AUTO_NEVER', - 1 );

                }
                
                
                function hooks(){
			if ( WP_SMUSHIT_PRO_AUTO == WP_SMUSHIT_PRO_AUTO_OK ) {
				add_filter( 'wp_generate_attachment_metadata', array( &$this, 'resize_from_meta_data' ), 10, 2 );
			}
                        
                        // add extra columns for smushing to media lists
			add_filter( 'manage_media_columns', array( &$this, 'columns' ) );
			add_action( 'manage_media_custom_column', array( &$this, 'custom_column' ), 10, 2 );
			
                        // add the admin option screens
                        add_action( 'admin_init', array( &$this, 'admin_init' ) );
			add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
			
                        add_action( 'admin_action_wp_smushit_manual', array( &$this, 'smushit_manual' ) );
			add_action( 'admin_head-upload.php', array( &$this, 'add_bulk_actions_via_javascript' ) );
			add_action( 'admin_action_bulk_smushit', array( &$this, 'bulk_action_handler' ) );
			add_action( 'admin_init', array( &$this, 'register_settings' ) );
			
                        // process callback from smush service
                        add_action( 'wp_ajax_process_smushed_image', array( &$this, 'process_smushed_image_callback' ) );
			add_action( 'wp_ajax_nopriv_process_smushed_image', array( &$this, 'process_smushed_image_callback' ) );
                }
		/**
		 * Plugin setting functions
		 */
		function register_settings() {

			add_settings_section( 'wp_smushit_pro_settings', 'WP Smush.it Pro', array(
				&$this,
				'settings_cb'
			), 'media' );

			add_settings_field( 'wp_smushit_pro_smushit_auto', __( 'Smush images on upload?', WP_SMUSHIT_PRO_DOMAIN ),
				array( &$this, 'render_auto_opts' ), 'media', 'wp_smushit_pro_settings' );

			add_settings_field( 'wp_smushit_pro_smushit_timeout', __( 'Timeout (in seconds)', WP_SMUSHIT_PRO_DOMAIN ),
				array( &$this, 'render_timeout_opts' ), 'media', 'wp_smushit_pro_settings' );

			add_settings_field( 'wp_smushit_pro_smushit_debug', __( 'Enable debug processing', WP_SMUSHIT_PRO_DOMAIN ),
				array( &$this, 'render_debug_opts' ), 'media', 'wp_smushit_pro_settings' );

			add_settings_field( 'wp_smushit_pro_remove_exif', __( 'Remove Exif data', WP_SMUSHIT_PRO_DOMAIN ),
				array( &$this, 'render_exif_opts' ), 'media', 'wp_smushit_pro_settings' );

			add_settings_field( 'wp_smushit_pro_progressive_jpeg', __( 'Allow progressive JPEGs', WP_SMUSHIT_PRO_DOMAIN ),
				array( &$this, 'render_progressive_jpeg_opts' ), 'media', 'wp_smushit_pro_settings' );

			add_settings_field( 'wp_smushit_pro_gif_to_png', __( 'Allow Gif to Png conversion', WP_SMUSHIT_PRO_DOMAIN ),
				array( &$this, 'render_gif_to_png' ), 'media', 'wp_smushit_pro_settings' );

			register_setting( 'media', 'wp_smushit_pro_smushit_auto' );
			register_setting( 'media', 'wp_smushit_pro_smushit_timeout' );
			register_setting( 'media', 'wp_smushit_pro_smushit_debug' );
			/**
			 * Option to remove exif data of an image
			 */
			register_setting( 'media', 'wp_smushit_pro_remove_exif' );
			/**
			 * Allow Progressive JPEG
			 */
			register_setting( 'media', 'wp_smushit_pro_progressive_jpeg' );
			/**
			 * Allow GIF to PNG for single frame
			 */
			register_setting( 'media', 'wp_smushit_pro_gif_to_png' );
		}

		function settings_cb() {
		}

		/**
		 * Allows user to choose whether to automatically smush images or not
		 */
		function render_auto_opts() {
			$key = 'wp_smushit_pro_smushit_auto';
			$val = intval( get_option( $key, WP_SMUSHIT_PRO_AUTO_OK ) );
			printf( "<select name='%1\$s' id='%1\$s'>", esc_attr( $key ) );
			echo '<option value=' . WP_SMUSHIT_PRO_AUTO_OK . ' ' . selected( WP_SMUSHIT_PRO_AUTO_OK, $val ) . '>' . __( 'Automatically process on upload', WP_SMUSHIT_PRO_DOMAIN ) . '</option>';
			echo '<option value=' . WP_SMUSHIT_PRO_AUTO_NEVER . ' ' . selected( WP_SMUSHIT_PRO_AUTO_NEVER, $val ) . '>' . __( 'Do not process on upload', WP_SMUSHIT_PRO_DOMAIN ) . '</option>';

			if ( $val > 0 ) {
				printf( '<option value="%d" selected="selected">', $val ) .
				printf( __( 'Temporarily disabled until %s', WP_SMUSHIT_PRO_DOMAIN ), date( 'M j, Y \a\t H:i', $val ) ) . '</option>';
			}
			echo '</select>';
		}

		/**
		 * Maximum Time out for Smush it
		 *
		 * @param $key
		 */
		function render_timeout_opts( $key ) {
			$key = 'wp_smushit_smushit_timeout';
			$val = intval( get_option( $key, WP_SMUSHIT_PRO_AUTO_OK ) );
			printf( "<input type='text' name='%1\$s' id='%1\$s' value='%2\%d'>", esc_attr( $key ), intval( get_option( $key, 60 ) ) );
		}

		/**
		 * Display an option to allow Smushit debugging
		 */
		function render_debug_opts() {
			$key = 'wp_smushit_smushit_debug';
			$val = get_option( $key, WP_SMUSHIT_PRO_DEBUG );
			?><input type="checkbox" name="<?php echo $key ?>" <?php if ( $val ) {
				echo ' checked="checked" ';
			} ?>/> <?php _e( 'If you are having trouble with the plugin enable this option can reveal some information about your system needed for support.', WP_SMUSHIT_PRO_DOMAIN );
		}

		/**
		 * Adds a setting field, Keep exif data or not
		 */
		function render_exif_opts() {
			$key = 'wp_smushit_pro_remove_exif';
			$val = get_option( $key, WP_SMUSH_PRO_REMOVE_EXIF ); ?>
			<input type="checkbox" name="wp_smushit_pro_remove_exif" <?php checked( $val, 'on', true ); ?> /><?php
		}

		/**
		 * Adds a setting field, Keep exif data or not
		 */
		function render_progressive_jpeg_opts() {
			$key = 'wp_smushit_pro_progressive_jpeg';
			$val = get_option( $key, true ); ?>
			<input type="checkbox" name="wp_smushit_pro_progressive_jpeg" <?php checked( $val, 'on', true ); ?> /><?php
		}

		/**
		 * Adds a setting field, Allow GIF to PNG conversion for single frame images
		 */
		function render_gif_to_png() {
			$key = 'wp_smushit_pro_gif_to_png';
			$val = get_option( $key, true ); ?>
			<input type="checkbox" name="wp_smushit_pro_gif_to_png" <?php checked( $val, 'on', true ); ?> /><?php
		}

		// default is 6hrs
		function temporarily_disable( $seconds = 21600 ) {
			update_option( 'wp_smushit_pro_smushit_auto', time() + $seconds );
		}

		function admin_init() {
			load_plugin_textdomain( WP_SMUSHIT_PRO_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
			wp_enqueue_script( 'common' );
		}

		/**
		 * Add Bulk option settings page
		 */
		function admin_menu() {
			add_media_page( 'Bulk Smush.it', 'Bulk Smush.it', 'edit_others_posts', 'wp-smushit-bulk', array(
				&$this,
				'bulk_preview'
			) );
		}

		/**
		 * Allows user to Bulk Smush the images
		 */
		function bulk_preview() {
			if ( function_exists( 'apache_setenv' ) ) {
				@apache_setenv( 'no-gzip', 1 );
			}
			@ini_set( 'output_buffering', 'on' );
			@ini_set( 'zlib.output_compression', 0 );
			@ini_set( 'implicit_flush', 1 );

			$attachments = null;
			$auto_start  = false;

			if ( isset( $_REQUEST['ids'] ) ) {
				$attachments = get_posts( array(
					'numberposts'    => - 1,
					'include'        => explode( ',', $_REQUEST['ids'] ),
					'post_type'      => 'attachment',
					'post_mime_type' => 'image'
				) );
				$auto_start  = true;
			} else {
				$attachments = get_posts( array(
					'numberposts'    => - 1,
					'post_type'      => 'attachment',
					'post_mime_type' => 'image'
				) );
			}
			?>
			<div class="wrap">
				<div id="icon-upload" class="icon32"><br/></div>
				<h2><?php _e( 'Bulk WP Smush.it Pro', WP_SMUSHIT_PRO_DOMAIN ) ?></h2>
				<?php

				if ( sizeof( $attachments ) < 1 ) {
					_e( "<p>You don't appear to have uploaded any images yet.</p>", WP_SMUSHIT_PRO_DOMAIN );
				} else {
					if ( empty( $_POST ) && ! $auto_start ) { // instructions page

						_e( "<p>This tool will run all of the images in your media library through the WP Smush.it web service. Any image already processed will not be reprocessed. Any new images or unsuccessful attempts will be processed.</p>", WP_SMUSHIT_PRO_DOMAIN );
						_e( "<p>As part of the Yahoo! Smush.it API this plugin wil provide a URL to each of your images to be processed. The Yahoo! service will download the image via the URL. The Yahoo Smush.it service will then return a URL to this plugin of the new version of the image. This image will be downloaded and replace the original image on your server.</p>", WP_SMUSHIT_PRO_DOMAIN ); ?>
						<hr/>
						<?php
						$attachment_count = sizeof( $attachments );
						$time             = $attachment_count * 3 / 60;
						printf( __( "<p>We found %d images in your media library. Be forewarned, <strong>it will take <em>at least</em> %f minutes</strong> to process all these images if they have never been smushed before.</p>", WP_SMUSHIT_PRO_DOMAIN ), $attachment_count, round( $time, 2 ) ); ?>
						<form method="post" action="">
							<?php wp_nonce_field( 'wp-smushit-bulk', '_wpnonce' ); ?>
							<button type="submit" class="button-secondary action"><?php _e( 'Run all my images through WP Smush.it Pro right now', WP_SMUSHIT_PRO_DOMAIN ) ?></button>
							<?php _e( "<p><em>N.B. If your server <tt>gzip</tt>s content you may not see the progress updates as your files are processed.</em></p>", WP_SMUSHIT_PRO_DOMAIN ); ?>
							<?php
							if ( WP_SMUSHIT_PRO_DEBUG ) {
								_e( "<p>DEBUG mode is currently enabled. To disable see the Settings > Media page.</p>", WP_SMUSHIT_PRO_DOMAIN );
							}
							?>
						</form>
					<?php
					} else { // run the script

						if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'wp-smushit-bulk' ) || ! current_user_can( 'edit_others_posts' ) ) {
							wp_die( __( 'Cheatin&#8217; uh?' ) );
						}


						@ob_implicit_flush( true );
						@ob_end_flush();
						foreach ( $attachments as $attachment ) {
							printf( __( "<p>Processing <strong>%s</strong>&hellip;<br />", WP_SMUSHIT_PRO_DOMAIN ), esc_html( $attachment->post_name ) );
							$original_meta = wp_get_attachment_metadata( $attachment->ID, true );

							$meta = $this->resize_from_meta_data( $original_meta, $attachment->ID, false );
							printf( "&mdash; [original] %d x %d: ", intval( $meta['width'] ), intval( $meta['height'] ) );

							if ( ( isset( $original_meta['wp_smushit'] ) )
							     && ( $original_meta['wp_smushit'] == $meta['wp_smushit'] )
							     && ( stripos( $meta['wp_smushit'], 'Smush.it error' ) === false )
							) {
								if ( ( stripos( $meta['wp_smushit'], '<a' ) === false )
								     && ( stripos( $meta['wp_smushit'], __( 'No savings', WP_SMUSHIT_PRO_DOMAIN ) ) === false )
								) {
									echo $meta['wp_smushit'] . ' ' . __( '<strong>already smushed</strong>', WP_SMUSHIT_PRO_DOMAIN );
								} else {
									echo $meta['wp_smushit'];
								}
							} else {
								echo $meta['wp_smushit'];
							}
							echo '<br />';

							if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
								foreach ( $meta['sizes'] as $size_name => $size ) {
									printf( "&mdash; [%s] %d x %d: ", $size_name, intval( $size['width'] ), intval( $size['height'] ) );
									if ( $original_meta['sizes'][ $size_name ]['wp_smushit'] == $size['wp_smushit'] && stripos( $meta['sizes'][ $size_name ]['wp_smushit'], 'Smush.it error' ) === false ) {
										echo $size['wp_smushit'] . ' ' . __( '<strong>already smushed</strong>', WP_SMUSHIT_PRO_DOMAIN );
									} else {
										echo $size['wp_smushit'];
									}
									echo '<br />';
								}
							}
							echo "</p>";

							wp_update_attachment_metadata( $attachment->ID, $meta );

							// rate limiting is good manners, let's be nice to Yahoo!
							sleep( 0.5 );
							@ob_flush();
							flush();
						}
						_e( '<hr /></p>Smush.it finished processing.</p>', WP_SMUSHIT_PRO_DOMAIN );
					}
				}
				?>
			</div>
		<?php
		}

		/**
		 * Manually process an image from the Media Library
		 */
		function smushit_manual() {
			if ( ! current_user_can( 'upload_files' ) ) {
				wp_die( __( "You don't have permission to work with uploaded files.", WP_SMUSHIT_PRO_DOMAIN ) );
			}

			if ( ! isset( $_GET['attachment_ID'] ) ) {
				wp_die( __( 'No attachment ID was provided.', WP_SMUSHIT_PRO_DOMAIN ) );
			}

			$attachment_ID = intval( $_GET['attachment_ID'] );

			$original_meta = wp_get_attachment_metadata( $attachment_ID );

			$new_meta = $this->resize_from_meta_data( $original_meta, $attachment_ID );
			wp_update_attachment_metadata( $attachment_ID, $new_meta );

			wp_redirect( preg_replace( '|[^a-z0-9-~+_.?#=&;,/:]|i', '', wp_get_referer() ) );
			exit();
		}

		/**
		 * Process an image with Smush.it Pro API
		 *
		 * @param string $file_path , Image Path
		 * @param string $file_url , Image URL
		 * @param $ID , Attachment ID
		 * @param $size , image size, default is full
		 *
		 * @return string, Message containing compression details
		 */
		function do_smushit( $file_path = '', $file_url = '', $ID, $size = 'full' ) {

			if ( empty( $file_path ) ) {
				return __( "File path is empty", WP_SMUSHIT_PRO_DOMAIN );
			}

			if ( empty( $file_url ) ) {
				return __( "File URL is empty", WP_SMUSHIT_PRO_DOMAIN );
			}

			if ( ! file_exists( $file_path ) ) {
				return __( "File does not exists", WP_SMUSHIT_PRO_DOMAIN );
			}
			
                        //deprecating
                        //static $error_count = 0;
                        
                        // deprecating

//			if ( $error_count >= WP_SMUSHIT_PRO_ERRORS_BEFORE_QUITTING ) {
//				return __( "Did not Smush.it due to previous errors", WP_SMUSHIT_PRO_DOMAIN );
//			}

			// check that the file exists
			if ( ! file_exists( $file_path ) || ! is_file( $file_path ) ) {
				return sprintf( __( "ERROR: Could not find <span class='code'>%s</span>", WP_SMUSHIT_PRO_DOMAIN ), $file_path );
			}

			// check that the file is writable
			if ( ! is_writable( dirname( $file_path ) ) ) {
				return sprintf( __( "ERROR: <span class='code'>%s</span> is not writable", WP_SMUSHIT_PRO_DOMAIN ), dirname( $file_path ) );
			}

			$file_size = filesize( $file_path );
			if ( $file_size > WP_SMUSHIT_PRO_MAX_BYTES ) {
				return sprintf( __( 'ERROR: <span style="color:#FF0000;">Skipped (%s) Unable to Smush due to Yahoo 1mb size limits. See <a href="http://developer.yahoo.com/yslow/smushit/faq.html#faq_restrict">FAQ</a></span>', WP_SMUSHIT_PRO_DOMAIN ), $this->format_bytes( $file_size ) );
			}

			//Send file to API
			$data = $this->_post( $file_url, $file_path, $ID );

			//Check for error
			if ( $data->status_code === 0 ) {
				return $data->status_message;
			}
			//Get the returned file id and store it in meta
			$file_id     = isset( $data->file_id ) ? $data->file_id : '';
			$status_code = isset( $data->status_code ) ? $data->status_code : '';
			$status_msg  = isset ( $data->status_msg ) ? $data->status_msg : '';

			//If file id update
			if ( ! empty( $file_id ) ) {
				//Fetch old smush meta and update with the file id returned by API
				$smush_meta = wp_get_attachment_metadata( $ID );

				//Add file id, Status and Message
				$smush_meta['smush_meta'][ $size ]['file_id']        = $file_id;
				$smush_meta['smush_meta'][ $size ]['status_code'] = $status_code;
				$smush_meta['smush_meta'][ $size ]['status_msg']  = $status_msg;

				wp_update_attachment_metadata( $ID, $smush_meta );
//				$meta_data = wp_get_attachment_metadata( $ID );
			} else {
				//Return a error
				return __( "Unable to process the image, please try again later", WP_SMUSHIT_PRO_DOMAIN );
			}

			return $status_msg;
		}
                
                function dev_api_key(){
                    return '3f2750fe583d6909b2018462fb216a2c5d5d75a9';
                }
                
                function form_callback_url(){
                    $callback_url = admin_url( 'admin-ajax.php' );
                    
                    $callback_url = add_query_arg(
                            array(
                                'action' => 'process_smushed_image'
                                ),
                            $callback_url
			);
                    
                    return apply_filters( 'smushitpro_callback_url', $callback_url );
                }
                
                function prepare_smush_request_data($attachment_id = 0){
                    if(!$attachment_id){
                        return false;
                    }
                    //Callback URL
			$post_fields = array(
                            'callback_url'  => '',
                            'api_key'       => '',
                            'token'         => '',
                            'attachment_id' => 0,
                            'progressive'   => true,
                            'gif_to_png'    => true,
                            'remove_meta'   => true
                        );

			$post_fields['callback_url'] = $this->form_callback_url();
                        

			// Get API Key for user
			$post_fields['api_key'] = $this->dev_api_key();

			// Generate Nonce
			$post_fields['token']= wp_create_nonce( "smush_image_$ID" );
			


			//Allow Progressive JPEGs
			$key               = 'wp_smushit_pro_progressive_jpeg';
			$progressive_jpegs = get_option( 'wp_smushit_pro_progressive_jpeg', '' );

			if ( ! empty( $progressive_jpegs ) && $progressive_jpegs == 'on' ) {
				$post_fields['progressive'] = 1;
			}

			//Check GIF Settings
			$key        = 'wp_smushit_pro_gif_to_png';
			$gif_to_png = get_option( $key, '' );

			if ( ! empty( $gif_to_png ) && $gif_to_png == 'on' ) {
				$post_fields['gif_to_png'] = 1;
			}

			//Check Exif settings
			$key         = 'wp_smushit_pro_remove_exif';
			$remove_exif = get_option( $key, '' );

			if ( ! empty( $remove_exif ) && $remove_exif == 'on' ) {
				$post_fields['remove_exif'] = 1;
			}

			//Attachment ID, makes it easy to get it back in callback
			$post_fields['media_id'] = $attachment_id;

			return $post_fields;
                    
                }
                
                function prepare_smush_request_payload($file_path, $ID, $boundary){
                    
                    $post_fields = $this->prepare_smush_request_data($ID);
                    
                    $payload = '';

                    // First, add the standard POST fields:
                    foreach ( $post_fields as $name => $value ) {
                            $payload .= '--' . $boundary;
                            $payload .= "\r\n";
                            $payload .= 'Content-Disposition: form-data; name="' . $name .
                                        '"' . "\r\n\r\n";
                            $payload .= $value;
                            $payload .= "\r\n";
                    }
                    // Upload the file
                    if ( $file_path ) {
                            $payload .= '--' . $boundary;
                            $payload .= "\r\n";
                            $payload .= 'Content-Disposition: form-data; name="' . 'upload' .
                                        '"; filename="' . basename( $file_path ) . '"' . "\r\n";
                            //        $payload .= 'Content-Type: image/jpeg' . "\r\n";
                            $payload .= "\r\n";
                            $payload .= file_get_contents( $file_path );
                            $payload .= "\r\n";
                    }
                    

                    $payload .= '--' . $boundary . '--';

                    return $payload;
                }

		function should_resmush( $previous_status ) {
			if ( ! $previous_status || empty( $previous_status ) ) {
				return true;
			}

			if ( stripos( $previous_status, 'no savings' ) !== false || stripos( $previous_status, 'reduced' ) !== false ) {
				return false;
			}

			// otherwise an error
			return true;
		}

		/**
		 * Read the image paths from an attachment's meta data and process each image
		 * with wp_smushit().
		 *
		 * This method also adds a `wp_smushit` meta key for use in the media library.
		 *
		 * Called after `wp_generate_attachment_metadata` is completed.
		 */
		function resize_from_meta_data( $meta, $ID = null, $force_resmush = true ) {
			if ( $ID && wp_attachment_is_image( $ID ) === false ) {
				return $meta;
			}

			$attachment_file_path = get_attached_file( $ID );
			if ( WP_SMUSHIT_PRO_DEBUG ) {
				echo "DEBUG: attachment_file_path=[" . $attachment_file_path . "]<br />";
			}
			$attachment_file_url = wp_get_attachment_url( $ID );
			if ( WP_SMUSHIT_PRO_DEBUG ) {
				echo "DEBUG: attachment_file_url=[" . $attachment_file_url . "]<br />";
			}

			if ( $force_resmush || $this->should_resmush( @$meta['wp_smushit'] ) ) {
				$meta['wp_smushit'] = $this->do_smushit( $attachment_file_path, $attachment_file_url, $ID );
			}

			// no resized versions, so we can exit
			if ( ! isset( $meta['sizes'] ) ) {
				return $meta;
			}

			foreach ( $meta['sizes'] as $size_key => $size_data ) {
				if ( ! $force_resmush && $this->should_resmush( @$meta['sizes'][ $size_key ]['wp_smushit'] ) === false ) {
					continue;
				}

				// We take the original image. The 'sizes' will all match the same URL and
				// path. So just get the dirname and rpelace the filename.
				$attachment_file_path_size = trailingslashit( dirname( $attachment_file_path ) ) . $size_data['file'];
				if ( WP_SMUSHIT_PRO_DEBUG ) {
					echo "DEBUG: attachment_file_path_size=[" . $attachment_file_path_size . "]<br />";
				}

				$attachment_file_url_size = trailingslashit( dirname( $attachment_file_url ) ) . $size_data['file'];
				if ( WP_SMUSHIT_PRO_DEBUG ) {
					echo "DEBUG: attachment_file_url_size=[" . $attachment_file_url_size . "]<br />";
				}
				$meta['sizes'][ $size_key ]['wp_smushit'] = $this->do_smushit( $attachment_file_path_size, $attachment_file_url_size, $ID, $size_key );

				//echo "size_key[". $size_key ."] wp_smushit<pre>"; print_r($meta['sizes'][$size_key]['wp_smushit']); echo "</pre>";
			}

			//echo "meta<pre>"; print_r($meta); echo "</pre>";
			return $meta;
		}

		/**
		 * Send image to Smush.it Pro API
		 *
		 * @param string $file_url
		 * @param string $file_path
		 * @param string $ID
		 *
		 * @return bool|string, Response returned from API
		 */
		function _post( $file_url = '', $file_path = '', $ID = '' ) {
                    
			$req = SMUSHIT_PRO_REQ_URL;

			$data = false;
			
                        if ( WP_SMUSHIT_PRO_DEBUG ) {
				echo "DEBUG: Calling API: [" . $req . "]<br />";
			}
                        
			if ( function_exists( 'wp_remote_post' ) ) {
				$local_file = $file_path;

				$boundary = wp_generate_password( 24 );
				$headers  = array(
					'content-type' => 'multipart/form-data; boundary=' . $boundary
				);

				$payload = $this->prepare_smush_request_payload($file_path, $ID, $boundary);
				$response = wp_remote_post( $req,
					array(
						'headers'    => $headers,
						'body'       => $payload,
						'user-agent' => WP_SMUSHIT_PRO_UA,
						'timeout'    => WP_SMUSHIT_PRO_TIMEOUT
					)
				);
				
                                if ( $response && !is_wp_error( $response )) {
					$data = json_decode( 
                                                wp_remote_retrieve_body(
                                                        $response
                                                        )
                                                );
					if ( empty( $response['response']['code'] ) || $response['response']['code'] != 200 ) {
						//Give a error
						return __( 'Error in processing file', WP_SMUSHIT_PRO_DOMAIN );
					}

				}
			} else {
				wp_die( __( 'WP Smush.it Pro requires WordPress 2.8 or greater', WP_SMUSHIT_PRO_DOMAIN ) );
			}

			return $data;
		}


		/**
		 * Print column header for Smush.it results in the media library using
		 * the `manage_media_columns` hook.
		 */
		function columns( $defaults ) {
			$defaults['smushit'] = 'Smush.it';

			return $defaults;
		}

		/**
		 * Return the filesize in a humanly readable format.
		 * Taken from http://www.php.net/manual/en/function.filesize.php#91477
		 */
		function format_bytes( $bytes, $precision = 2 ) {
			$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
			$bytes = max( $bytes, 0 );
			$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
			$pow   = min( $pow, count( $units ) - 1 );
			$bytes /= pow( 1024, $pow );

			return round( $bytes, $precision ) . ' ' . $units[ $pow ];
		}

		/**
		 * Print column data for Smush.it results in the media library using
		 * the `manage_media_custom_column` hook.
		 */
		function custom_column( $column_name, $id ) {
			if ( 'smushit' == $column_name ) {
				$data = wp_get_attachment_metadata( $id );
				echo "<pre>";
				print_r( $data );
				echo "</pre>";
				if ( isset( $data['smush_meta'] ) && ! empty( $data['smush_meta']['full'] ) ) {
					print $data['smush_meta']['full']['status_msg'];
					printf( "<br><a href=\"admin.php?action=wp_smushit_manual&amp;attachment_ID=%d\">%s</a>",
						$id,
						__( 'Re-smush', WP_SMUSHIT_PRO_DOMAIN ) );
				} else {
					if ( wp_attachment_is_image( $id ) ) {
						print __( 'Not processed', WP_SMUSHIT_PRO_DOMAIN );
						printf( "<br><a href=\"admin.php?action=wp_smushit_manual&amp;attachment_ID=%d\">%s</a>",
							$id,
							__( 'Smush.it now!', WP_SMUSHIT_PRO_DOMAIN ) );
					}
				}
			}
		}


		// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/
		function add_bulk_actions_via_javascript() {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function ($) {
					$('select[name^="action"] option:last-child').before('<option value="bulk_smushit">Bulk Smush.it</option>');
				});
			</script>
		<?php
		}


		// Handles the bulk actions POST
		// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/
		function bulk_action_handler() {
			check_admin_referer( 'bulk-media' );

			if ( empty( $_REQUEST['media'] ) || ! is_array( $_REQUEST['media'] ) ) {
				return;
			}

			$ids = implode( ',', array_map( 'intval', $_REQUEST['media'] ) );

			// Can't use wp_nonce_url() as it escapes HTML entities
			wp_redirect( add_query_arg( '_wpnonce', wp_create_nonce( 'wp-smushit-bulk' ), admin_url( 'upload.php?page=wp-smushit-bulk&goback=1&ids=' . $ids ) ) );
			exit();
		}

		/**
		 * Download and Update the Image from Server corresponding to file id and URL
		 */
		function process_smushed_image_callback() {

			$response = ! empty( $_POST['response'] ) ? $_POST['response'] : '';
			$response = json_decode( $response );
			echo "<pre>";
			print_r( $_REQUEST);
			echo "</pre>";exit;
			//Get file id from request
			$media_id    = ! empty( $response['media_id'] ) ? $response['media_id'] : '';
			$file_id     = ! empty( $response['file_id'] ) ? $response['file_id'] : '';
			$file_url    = ! empty( $response['file_url'] ) ? $response['file_url'] : '';
			$token       = ! empty( $response['token'] ) ? $response['token'] : '';
			$status_code = ! empty( $response['status_code'] ) ? $response ['status_code'] : '';
			$status_msg  = ! empty( $response['status_msg'] ) ? $response ['status_msg'] : '';

			if ( empty( $file_url ) || empty( $file_id ) || empty ( $media_id ) || empty( $token ) ) {
				//Response back to API
				http_response_code( 406 );

				return 'missing parameters';
			}

			//Check for Nonce, corresponding to media id
			if ( ! wp_verify_nonce( $_POST['token'], "smush_caimage_$media_id" ) ) {
				error_log( "Nonce Verification failed for $media_id" );

				return false;
			}

			//Get Image sizes detail for media
			$metadata = wp_get_attachment_metadata( $media_id );
			$sizes    = $metadata['sizes'];

			//Check for media and file id association
			$smush_meta       = get_post_meta( $media_id, 'smush_meta', true );
			$smush_image_size = ! empty( $smush_meta[ $file_id ] ) ? $smush_meta[ $file_id ]['size'] : '';

			if ( empty( $smush_image_size ) ) {
				//no choice to update an image
				return;
			}

			//Get the image details from metadata array for smushed image size
			$image_thumbnail      = ! empty( $sizes[ $smush_image_size ] ) ? $sizes[ $smush_image_size ] : '';
			$image_thumbnail_file = $image_thumbnail['file'];
			$attachment_file_path = get_attached_file( $media_id );
			echo "<pre>";
			error_log( $attachment_file_path );
			echo "</pre>";
			exit;

			//Loop
			$temp_file = download_url( $file_url );

			if ( is_wp_error( $temp_file ) ) {
				@unlink( $temp_file );

				return sprintf( __( "Error downloading file (%s)", WP_SMUSHIT_PRO_DOMAIN ), $temp_file->get_error_message() );
			}

			if ( ! file_exists( $temp_file ) ) {
				return sprintf( __( "Unable to locate downloaded file (%s)", WP_SMUSHIT_PRO_DOMAIN ), $temp_file );
			}

			//Unlink the old file and replace it with new one
			@unlink( $file_path );
			$success = @rename( $temp_file, $file_path );
			if ( ! $success ) {
				copy( $temp_file, $file_path );
				unlink( $temp_file );
			}

			$savings     = intval( $data->src_size ) - intval( $data->dest_size );
			$savings_str = $this->format_bytes( $savings, 1 );
			$savings_str = str_replace( ' ', '&nbsp;', $savings_str );

			$results_msg = sprintf( __( "Reduced by %01.1f%% (%s)", WP_SMUSHIT_PRO_DOMAIN ),
				$data->percent,
				$savings_str );
		}
	}

	$WpSmushitPro = new WpSmushitPro();
	global $WpSmushitPro;

}

if ( ! function_exists( 'wp_basename' ) ) {
	/**
	 * Introduced in WP 3.1... this is copied verbatim from wp-includes/formatting.php.
	 */
	function wp_basename( $path, $suffix = '' ) {
		return urldecode( basename( str_replace( '%2F', '/', urlencode( $path ) ), $suffix ) );
	}
}