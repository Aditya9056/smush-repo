<?php
if ( ! class_exists( 'WpSmushitPro' ) ) {

	class WpSmushitPro {

		var $version = "0.1";

		/**
		 * Constructor
		 */
		function __construct() {

			$this->constants();
			$this->hooks();
                        $this->admin = new WpSmushItPro_Admin();
		}
                
                function constants(){
                    /**
			 * Constants
			 */
			/**
			 * TODO: Fix the URL
			 */
			define( 'SMUSHIT_PRO_SERVICE_URL', 'http://107.170.2.190:1203/upload/' );
                        
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
			
                        add_action( 'admin_action_wp_smushit_manual', array( &$this, 'smushit_manual' ) );	
			
                        // process callback from smush service
                        add_action( 'wp_ajax_process_smushed_image', array( &$this, 'process_smushed_image_callback' ) );
			add_action( 'wp_ajax_nopriv_process_smushed_image', array( &$this, 'process_smushed_image_callback' ) );
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

			$this->resize_from_meta_data( $original_meta, $attachment_ID );

			wp_redirect( preg_replace( '|[^a-z0-9-~+_.?#=&;,/:]|i', '', wp_get_referer() ) );
			exit();
		}

		/**
		 * Process an image with Smush.it Pro API
		 *
		 * @param string $img_path , Image Path
		 * @param string $file_url , Image URL
		 * @param $ID , Attachment ID
		 * @param $size , image size, default is full
		 *
		 * @return string, Message containing compression details
		 */
		function do_smushit( $img_path = '', $file_url = '', $ID, $size = 'full' ) {

			if ( empty( $img_path ) ) {
				return __( "File path is empty", WP_SMUSHIT_PRO_DOMAIN );
			}

			if ( empty( $file_url ) ) {
				return __( "File URL is empty", WP_SMUSHIT_PRO_DOMAIN );
			}

			if ( ! file_exists( $img_path ) ) {
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

			//Send nonce
			$token = wp_create_nonce( "smush_image_$ID" . "_$size" );

			//Send file to API
			$data = $this->_post( $file_url, $img_path, $ID, $token );

			//For testing purpose
//			error_log( json_encode( $data ) );
			if ( empty( $data ) ) {
				//Some code error
				return __( "Error processing file, no data recieved", WP_SMUSHIT_PRO_DOMAIN );
			}
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
				$smush_meta['smush_meta'][ $size ]['file_id']     = $file_id;
				$smush_meta['smush_meta'][ $size ]['status_code'] = $status_code;
				$smush_meta['smush_meta'][ $size ]['status_msg']  = $status_msg;
				$smush_meta['smush_meta'][ $size ]['token']       = $token;

				wp_update_attachment_metadata( $ID, $smush_meta );

				return $status_msg;
			} else {
				//Return a error
				return __( "Unable to process the image, please try again later", WP_SMUSHIT_PRO_DOMAIN );
			}
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
                
                function prepare_smush_request_data($attachment_id = 0, $token){
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
			$post_fields['token']= $token;
			


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
                
                function prepare_smush_request_payload($img_path, $ID, $boundary, $token){
                    
                    $post_fields = $this->prepare_smush_request_data($ID, $token);
                    
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
                    if ( $img_path ) {
                            $payload .= '--' . $boundary;
                            $payload .= "\r\n";
                            $payload .= 'Content-Disposition: form-data; name="' . 'upload' .
                                        '"; filename="' . basename( $img_path ) . '"' . "\r\n";
                            //        $payload .= 'Content-Type: image/jpeg' . "\r\n";
                            $payload .= "\r\n";
                            $payload .= file_get_contents( $img_path );
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

			//Check if the image was prviously smushed
			$previous_state = ! empty( $meta['smush_meta'] ) ? $meta['smush_meta']['full']['status_msg'] : '';

			if ( $force_resmush || $this->should_resmush( $previous_state ) ) {
				$this->do_smushit( $attachment_file_path, $attachment_file_url, $ID );
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
				$this->do_smushit( $attachment_file_path_size, $attachment_file_url_size, $ID, $size_key );
			}
		}

		/**
		 * Send image to Smush.it Pro API
		 *
		 * @param string $img_path
		 * @param string $ID
		 *
		 * @return bool|string, Response returned from API
		 */
                
		function _post( $img_path = '', $attachment_id = 0, $token = false ) {
                    
			$req = SMUSHIT_PRO_SERVICE_URL;

			$data = false;
			
                        if ( WP_SMUSHIT_PRO_DEBUG ) {
				echo "DEBUG: Calling API: [" . $req . "]<br />";
			}
                        
			                     
                        $boundary = wp_generate_password( 24 );
                        $headers  = array(
                                'content-type' => 'multipart/form-data; boundary=' . $boundary
                        );

                        $payload = $this->prepare_smush_request_payload($img_path, $attachment_id, $token, $boundary);
                        
                        $response = wp_remote_post( $req,
                                array(
                                        'headers'    => $headers,
                                        'body'       => $payload,
                                        'user-agent' => WP_SMUSHIT_PRO_UA,
                                        'timeout'    => WP_SMUSHIT_PRO_TIMEOUT,
                                        //Remove this code
                                        'sslverify'  => false
                                )
                        );

                        if ( $response && !is_wp_error( $response )) {
                                if ( empty( $response['response']['code'] ) || $response['response']['code'] != 200 ) {
                                        //Give a error
                                        return __( 'Error in processing file', WP_SMUSHIT_PRO_DOMAIN );
                                }
                                $data = json_decode( 
                                        wp_remote_retrieve_body(
                                                $response
                                                )
                                        );

                        }
			
			return $data;
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
		 * Download and Update the Image from Server corresponding to file id and URL
		 */
		function process_smushed_image_callback() {

			$body = @file_get_contents( 'php://input' );
			// get the json into an array
			$response = json_decode( $body, true );

			//Get file id from request
			$attachment_id  = ! empty( $response['attachment_id'] ) ? $response['attachment_id'] : '';
			$file_id        = ! empty( $response['file_id'] ) ? $response['file_id'] : '';
			$file_url       = ! empty( $response['file_url'] ) ? $response['file_url'] : '';
			$received_token = ! empty( $response['token'] ) ? $response['token'] : '';
			$status_code    = ! empty( $response['status_code'] ) ? $response ['status_code'] : '';
			$status_msg     = ! empty( $response['status_msg'] ) ? $response ['status_msg'] : '';

			if ( empty( $file_id ) || empty ( $attachment_id ) || empty( $received_token ) ) {
				//Response back to API, missing parameters

				header( "HTTP/1.0 406 Missing Parameters" );
				exit;

			}
			//If smushing wasn't succesfull
			if ( $status_code != 4 ) {
				//@todo update meta with suitable error
				header( "HTTP/1.0 200" );
				exit;
			}
			//Get Image sizes detail for media
			$metadata = wp_get_attachment_metadata( $attachment_id );

			$smush_meta = ! empty( $metadata['smush_meta'] ) ? $metadata['smush_meta'] : '';
			//Empty smush meta, probably some error on our end
			if ( empty( $smush_meta ) ) {
				//Response back to API, missing parameters
				header( "HTTP/1.0 406 No Smush Meta" );
				exit;
			}
			//Get the media from thumbnail file id
			foreach ( $smush_meta as $image_size => $image_details ) {

				//Skip the loop if file id is not the same
				if ( empty( $image_details['file_id'] ) || $image_details['file_id'] != $file_id ) {
					continue;
				}
				$size  = $image_size;
				$token = $image_details['token'];
				//Check for Nonce, corresponding to media id
				if ( $token != $received_token ) {
					error_log( "Nonce Verification failed for $attachment_id" );

					//Response back to API, missing parameters
					header( "HTTP/1.0 406 invalid token" );
					exit;
				}

				$attachment_file_path = get_attached_file( $attachment_id );
				//Modify path if callback is for thumbnail
				$attachment_file_path_size = trailingslashit( dirname( $attachment_file_path ) ) . $metadata['sizes'][ $image_size ]['file'];
				//We are done processing, end loop
				break;
			}

			//Loop
			//@Todo: Add option for user, Strict ssl use wp_safe_remote_get or download_url
			//Copied from download_url, as it does not provice to turn off strict ssl
			$temp_file = wp_tempnam( $file_url );
			if ( ! $temp_file ) {
				return new WP_Error( 'http_no_file', __( 'Could not create Temporary file.' ) );
			}

			$response = wp_remote_get( $file_url, array(
				'timeout'   => 300,
				'stream'    => true,
				'filename'  => $temp_file,
				'sslverify' => false
			) );

			if ( is_wp_error( $response ) ) {
				unlink( $temp_file );
				echo "<pre>";
				print_r( $response );
				echo "</pre>";
				echo "Unsafe URL";
				//Response back to API, missing parameters
				header( "HTTP/1.0 406 Unsafe URL" );
				exit;
			}

			if ( 200 != wp_remote_retrieve_response_code( $response ) ) {
				echo trim( wp_remote_retrieve_response_message( $response ) );
				unlink( $temp_file );
				header( "HTTP/1.0 406  " . trim( wp_remote_retrieve_response_message( $response ) ) );
			}

			$content_md5 = wp_remote_retrieve_header( $response, 'content-md5' );
			if ( $content_md5 ) {
				$md5_check = verify_file_md5( $temp_file, $content_md5 );
				if ( is_wp_error( $md5_check ) ) {
					unlink( $temp_file );
					echo "File check";
					//Response back to API, missing parameters
					header( "HTTP/1.0 406 URL authentication error" );
					exit;
				}
			}
			if ( is_wp_error( $temp_file ) ) {
				@unlink( $temp_file );
				echo "File path error";
				error_log( sprintf( __( "Error downloading file (%s)", WP_SMUSHIT_PRO_DOMAIN ), $temp_file->get_error_message() ) );

				header( "HTTP/1.0 406 File not downloaded" );
				exit;
			}

			if ( ! file_exists( $temp_file ) ) {
				error_log( sprintf( __( "Unable to locate downloaded file (%s)", WP_SMUSHIT_PRO_DOMAIN ), $temp_file ) );
				echo "Local server error";
				header( "HTTP/1.0 406 Downloaded file not found" );
				exit;
			}

			//Unlink the old file and replace it with new one
			@unlink( $attachment_file_path_size );
			$success = @rename( $temp_file, $attachment_file_path_size );
			if ( ! $success ) {
				copy( $temp_file, $attachment_file_path_size );
				unlink( $temp_file );
			}

			$savings_str = '';
			$compression = ! empty( $response['compression'] ) ? $response['compression'] : '';
			if ( ! empty ( $response['before_smush'] ) && ! empty( $response['after_smush'] ) ) {
				$savings_str = $response['before_smush'] - $response ['after_smush'] . 'Kb';
			}

			$results_msg                        = sprintf( __( "Reduced by %01.1f%% (%s)", WP_SMUSHIT_PRO_DOMAIN ),
				$compression,
				$savings_str );
			$smush_meta[ $size ]['status_code'] = $status_code;
			$smush_meta[ $size ]['status_msg']  = $results_msg;
			$metadata['smush_meta']             = $smush_meta;
			wp_update_attachment_metadata( $attachment_id, $metadata );
			//Response back to API, missing parameters
			header( "HTTP/1.0 200 file updated" );
			exit;
		}
	}

	$WpSmushitPro = new WpSmushitPro();
	global $WpSmushitPro;

}

