<?php
/**
 * Receives call backs from service
 * 
 * @package SmushItPro/Receive
 * 
 * @version 1.0
 * 
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 * 
 * @copyright (c) 2014, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmProReceive' ) ) {

	/**
	 * Receives the call back and fetches the files from the smush service
	 *
	 * @author Saurabh Shukla <contact.saurabhshukla@gmail.com>
	 */
	class WpSmProReceive {

		/**
		 *
		 * @var type
		 */
		public $options = array(
			'attachment_id'    => '',
			'file_id'          => '',
			'file_url'         => '',
			'token'            => '',
			'status_code'      => '',
			'request_err_code' => '',
			'filename'         => ''
		);

		public function __construct() {
			// process callback from smush service
			add_action( 'wp_ajax_receive_smushed_image', array( &$this, 'receive' ) );
			add_action( 'wp_ajax_nopriv_receive_smushed_image', array( &$this, 'receive' ) );
		}

		/**
		 * Download and Update the Image from Server corresponding to file id and URL
		 */
		function receive() {

			$body = @file_get_contents( 'php://input' );
			// get the json into an array
			$response = json_decode( $body, true );

			$options = wp_parse_args( $response, $this->options );

			if ( empty ( $options['filename'] ) || empty ( $options['file_id'] ) || empty ( $options['status_code'] ) || empty ( $options['attachment_id'] ) ) {
				error_log("Missing Parameters for attachment[".$options['attachment_id']."], file id[".$options['file_id']."]");
				//Response back to API, missing parameters
                                $this->callback_response();
				exit;
			}

			$this->process( $options );
		}

		/**
		 *
		 * @param type $options
		 */
		function process( $options = array() ) {
			global $wp_sm_pro;
			//Get Image sizes detail for media
			$smush_meta = get_post_meta( $options['attachment_id'], 'smush_meta', true );
			$metadata = wp_get_attachment_metadata( $options['attachment_id'] );

			//Empty smush meta, probably some error on our end
			if ( empty( $smush_meta ) ) {
                                error_log("No metadata for attachment[".$options['attachment_id']."], file id[".$options['file_id']."]");
				$this->callback_response();
				//Response back to API, missing parameters
				exit;
			}
			foreach ( $smush_meta as $thumb_size => $thumb_details ){

				if ( $thumb_details['file_id'] == $options['file_id'] ) {
					$size = $thumb_size;
					break;
				}

			}

			$token = $smush_meta[ $size ]['token'];
			//Check for Nonce, corresponding to media id
			if ( $token != $options['token'] ) {
				error_log("Nonce Verification failed for attachment[".$options['attachment_id']."], file id[".$options['file_id']."]");
                                $this->callback_response();
				//Response back to API, missing parameters
				exit;
			}

			$attachment_file_path = get_attached_file( $options['attachment_id'] );
			//Modify path if callback is for thumbnail
			$attachment_file_size_path = trailingslashit( dirname( $attachment_file_path ) ) . $options['filename'];

			if ( empty( $attachment_file_size_path ) ) {
                                error_log("No file path for attachment[".$options['attachment_id']."], file id[".$options['file_id']."]");
                                $this->callback_response();
				exit;
			}

			//If smushing wasn't succesfull
			if ( $options['status_code'] != 4 ) {
				global $wp_sm_pro;

				//Update metadata
				$smush_meta[ $size ]['status_code'] = $options['status_code'];
				$smush_meta[ $size ]['status_msg']  = $wp_sm_pro->sender->get_status_msg( $options['status_code'], $options['request_err_code'] );

				update_post_meta ( $options['attachment_id'], 'smush_meta', $smush_meta );
                                error_log("Smushing failed for attachment[".$options['attachment_id']."], file id[".$options['file_id']."]");
				$this->callback_response();
				//@todo update meta with suitable error
				exit;
			}
			//Else replace image
			$fetched = $this->fetch( $options, $attachment_file_size_path );

			$results_msg = $this->create_status_string(
				$options['compression'], $options['before_smush'], $options['after_smush']
			);


			$smush_meta[ $size ]['status_code'] = $options['status_code'];
			$smush_meta[ $size ]['status_msg']  = $results_msg;

			update_post_meta ( $options['attachment_id'], 'smush_meta', $smush_meta );
                        error_log("File updated for attachment[".$options['attachment_id']."], file id[".$options['file_id']."]");
                        $this->callback_response();
			exit;
		}

		public function fetch( $options, $attachment_file_size_path ) {
			//Loop
			//@Todo: Add option for user, Strict ssl use wp_safe_remote_get or download_url
			//Copied from download_url, as it does not provice to turn off strict ssl
			$temp_file = wp_tempnam( $options['file_url'] );

			if ( ! $temp_file ) {
				//For Debugging on node
                                error_log("No temp file for attachment[" . $options['attachment_id'] . "], file id[" . $options['file_id'] . "]");
                                $this->callback_response(false);
                                //Response back to API, missing parameters
				exit;
			}

			$response = wp_remote_get(
				$options['file_url'], array(
					'timeout'   => 300,
					'stream'    => true,
					'filename'  => $temp_file,
					'sslverify' => false
				)
			);

			if ( is_wp_error( $response ) ) {
				unlink( $temp_file );

				error_log("Call back from unsafe URL for attachment[".$options['attachment_id']."], file id[".$options['file_id']."]");
				$this->callback_response(false);

				//Response back to API, missing parameters
				exit;
			}

			if ( 200 != wp_remote_retrieve_response_code( $response ) ) {

				unlink( $temp_file );
                                error_log("Bad callback status for attachment[".$options['attachment_id']."], file id[".$options['file_id']."]");
			}

			$content_md5 = wp_remote_retrieve_header( $response, 'content-md5' );
                        
			if ( $content_md5 ) {
				$md5_check = verify_file_md5( $temp_file, $content_md5 );
				if ( is_wp_error( $md5_check ) ) {
					unlink( $temp_file );
					error_log("File check failed for attachment[" . $options['attachment_id'] . "], file id[" . $options['file_id'] . "]");
                                        $this->callback_response(false);
					exit;
				}
			}

			if ( is_wp_error( $temp_file ) ) {
				@unlink( $temp_file );

				error_log("File path error for attachment[" . $options['attachment_id'] . "], file id[" . $options['file_id'] . "]");
                                error_log( sprintf( __( "Error downloading file (%s)", WP_SMPRO_DOMAIN ), $temp_file->get_error_message() ) );
                                $this->callback_response(false);
				exit;
			}

			if ( ! file_exists( $temp_file ) ) {
                                error_log("Local server error for attachment[" . $options['attachment_id'] . "], file id[" . $options['file_id'] . "]");
				error_log( sprintf( __( "Unable to locate downloaded file (%s)", WP_SMPRO_DOMAIN ), $temp_file ) );
                                $this->callback_response(false);
				exit;
			}

			//Unlink the old file and replace it with new one
			@unlink( $attachment_file_size_path );

			$success = @rename( $temp_file, $attachment_file_size_path );

			if ( ! $success ) {
				copy( $temp_file, $attachment_file_size_path );
				unlink( $temp_file );
			}
                    }

		public function create_status_string( $compression, $before_smush, $after_smush ) {
			$savings_str = '';
			$compressed  = ! empty( $compression ) ? $compression : '';

			if ( ! empty( $before_smush ) && ! empty( $after_smush ) ) {
				$savings_str = number_format_i18n(
                                        (($before_smush - $after_smush)/1024),
                                2);
			}
			if ( $compressed == 0 ) {
				$results_msg = __( 'Optimised', WP_SMPRO_DOMAIN );
			} else {
				$results_msg = sprintf( __( "Reduced by %01.1f%% (%s)", WP_SMPRO_DOMAIN ), $compressed, $savings_str . 'Kb' );
			}

			return $results_msg;
		}
                
                /**
                 * Respond to service callback
                 * If this response doesn't go to service,
                 * Or a status 0 is sent,
                 * service will resend callback
                 * assuming server is busy or down or something
                 * 
                 * @param boolean $done Is callback done?
                 */
                public function callback_response($done=true){
                        echo json_encode(array('status' => (int)$done));
                        header( "HTTP/1.0 200" );
                }

	}

}