<?php

/**
 * @package SmushItPro
 * @subpackage Receive
 * @version 1.0
 * 
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 * 
 * @copyright (c) 2014, Incsub (http://incsub.com)
 */
if (!class_exists('WpSmProReceive')) {
	
	/**
	 * Receives call backs from service
	 */
	class WpSmProReceive {

		/**
		 * 
		 * @var array The default data array
		 */
		public $default_data = array(
		    'attachment_id' => '',
		    'file_id' => '',
		    'file_url' => '',
		    'token' => '',
		    'status_code' => '',
		    'request_err_code' => '',
		    'filename' => ''
		);

		/**
		 * Constructor, hooks callback urls
		 */
		public function __construct() {

			// process callback from smush service
			add_action('wp_ajax_receive_smushed_image', array($this, 'receive'));
			add_action('wp_ajax_nopriv_receive_smushed_image', array($this, 'receive'));
		}

		/**
		 * Receive the callback and send data for further processing
		 */
		function receive() {

			// get the contents of the callback
			$body = urldecode( file_get_contents('php://input') );

			// filter with default data
			$data = array();

			parse_str( $body, $data);

			// data is invalid
			if (
				empty($data['filename']) || empty($data['file_id']) || empty($data['status_code']) || empty($data['attachment_id'])
			) {

				// debug
				error_log("Missing Parameters for attachment[" . $data['attachment_id'] . "], file id[" . $data['file_id'] . "]");

				// respond to service api
				$this->callback_response();
				exit;
			}

			// all fine, process the data
			$this->process($data);
		}

		/**
		 * Process data received from service
		 * 
		 * @param array $data data received 
		 */
		function process($data = array()) {

			global $wp_sm_pro;

			// get the smush data
			$smush_meta = get_post_meta($data['attachment_id'], 'smush_meta', true);

			//Empty smush meta, probably some error on our end
			if (empty($smush_meta)) {

				error_log("No smush meta for File: " . $data['filename'] . ", Image Size: " . $data['image_size'] . ", attachment[" . $data['attachment_id'] . "], file id[" . $data['file_id'] . "]");
				$this->callback_response();
			}
			//Use the size obtained in callback
			$size = ! empty( $data['image_size'] ) ? $data['image_size'] : '';

			//Verify file id
			if( empty( $smush_meta[$size] ) || $smush_meta[$size]['file_id'] != $data['file_id'] ) {

				//@todo: Check whether to send response or not
				error_log("File id did not match File: " . $data['filename'] . ", Image Size: " . $data['image_size'] . ", attachment[" . $data['attachment_id'] . "], file id[" . $data['file_id'] . "]");
				$this->callback_response();

			}

			if ( empty( $size ) ) {
				// we have meta, figure out the sizes
				foreach ($smush_meta as $thumb_size => $thumb_details) {

					if ($thumb_details['file_id'] == $data['file_id']) {
						$size = $thumb_size;
						break;
					}
				}
			}

			// get token
			$token = $smush_meta[$size]['token'];

			//Check for Nonce, corresponding to media id
			if ($token != $data['token']) {

				error_log("Nonce Verification failed for File: " . $data['filename'] . ", Image Size: " . $data['image_size'] . ", attachment[" . $data['attachment_id'] . "], file id[" . $data['file_id'] . "]");
				$this->callback_response();
			}

			// get file path
			$attachment_file_path = get_attached_file($data['attachment_id']);

			//Modify path if callback is for another size
			$size_path = trailingslashit(dirname($attachment_file_path)) . $data['filename'];

			// no file with us :(
			if (empty($size_path)) {

				error_log("No file path for File: " . $data['filename'] . ", Image Size: " . $data['image_size'] . ", attachment[" . $data['attachment_id'] . "], file id[" . $data['file_id'] . "]");
				$this->callback_response();
			}

			//If smushing wasn't succesful
			if ($data['status_code'] != 4) {
				global $wp_sm_pro;

				//Update metadata
				$smush_meta[$size]['status_code'] = $data['status_code'];
				$smush_meta[$size]['status_msg'] = $wp_sm_pro->sender->get_status_msg($data['status_code'], $data['request_err_code']);

				update_post_meta($data['attachment_id'], 'smush_meta', $smush_meta);

				error_log("Smushing failed for File: " . $data['filename'] . ", Image Size: " . $data['image_size'] . ", attachment[" . $data['attachment_id'] . "], file id[" . $data['file_id'] . "]");
				$this->callback_response();
			}

			//Else replace image
			$this->fetch_replace($data, $size_path);

			// formulate status string
			$results_msg = $this->create_status_string(
				$data['compression'], $data['before_smush'], $data['after_smush']
			);

			// update smush details
			$smush_meta[$size]['status_code'] = $data['status_code'];
			$smush_meta[$size]['status_msg'] = $results_msg;

			update_post_meta($data['attachment_id'], 'smush_meta', $smush_meta);

			error_log("File updated for File: " . $data['filename'] . ", Image Size: " . $data['image_size'] . ", attachment[" . $data['attachment_id'] . "], file id[" . $data['file_id'] . "]");
			$this->callback_response();
		}

		/**
		 * Replace file with new file
		 * 
		 * @param array $data data received from service
		 * @param string $size_path file path for the size
		 */
		public function fetch_replace($data, $size_path) {
			//Loop
			//@Todo: Add option for user, Strict ssl use wp_safe_remote_get or download_url
			//Copied from download_url, as it does not provice to turn off strict ssl
			// create temp file
			$temp_file = wp_tempnam($data['file_url']);

			// if we couldn't create a temp file
			if (!$temp_file) {
				error_log("No temp file for attachment[" . $data['attachment_id'] . "], file id[" . $data['file_id'] . "]");
				$this->callback_response(false);
			}

			// fetch the file from service
			$response = wp_remote_get(
				$data['file_url'], array(
			    'timeout' => 300,
			    'stream' => true,
			    'filename' => $temp_file,
			    'sslverify' => false
				)
			);

			// erroneous response
			if (is_wp_error($response)) {

				// delete temp file
				unlink($temp_file);

				error_log("Call back from unsafe URL for attachment[" . $data['attachment_id'] . "], file id[" . $data['file_id'] . "]");
				$this->callback_response(false);
			}

			// bad response status, just log for debugging, don't send response
			if (200 != wp_remote_retrieve_response_code($response)) {

				unlink($temp_file);
				error_log("Bad callback status for attachment[" . $data['attachment_id'] . "], file id[" . $data['file_id'] . "]");
			}

			// md5 info
			$content_md5 = wp_remote_retrieve_header($response, 'content-md5');

			// if file is all good
			if ($content_md5) {
				// validate md5
				$md5_check = verify_file_md5($temp_file, $content_md5);

				// invalid file
				if (is_wp_error($md5_check)) {

					unlink($temp_file);
					error_log("File check failed for attachment[" . $data['attachment_id'] . "], file id[" . $data['file_id'] . "]");
					$this->callback_response(false);
				}
			}

			// temp file creation error
			if (is_wp_error($temp_file)) {
				@unlink($temp_file);

				error_log("File path error for attachment[" . $data['attachment_id'] . "], file id[" . $data['file_id'] . "]");
				error_log(sprintf(__("Error downloading file (%s)", WP_SMPRO_DOMAIN), $temp_file->get_error_message()));
				$this->callback_response(false);
			}

			// temp file missing?
			if (!file_exists($temp_file)) {
				error_log("Local server error for attachment[" . $data['attachment_id'] . "], file id[" . $data['file_id'] . "]");
				error_log(sprintf(__("Unable to locate downloaded file (%s)", WP_SMPRO_DOMAIN), $temp_file));
				$this->callback_response(false);
			}

			//Unlink the old file and replace it with new one
			@unlink($size_path);

			// rename temp file to our new name
			$success = @rename($temp_file, $size_path);

			// failed
			if (!$success) {
				copy($temp_file, $size_path);
				unlink($temp_file);
			}
		}
		
		/**
		 * Formulate success status string
		 * 
		 * @param string $compression Compression percent
		 * @param string $before_smush Size in bytes before smushing
		 * @param string $after_smush Size in bytes after smushing
		 * @return string Status message
		 */
		public function create_status_string($compression, $before_smush, $after_smush) {
			$savings_str = '';
			$compressed = !empty($compression) ? $compression : '';

			if (!empty($before_smush) && !empty($after_smush)) {
				$savings_str = number_format_i18n(
					(($before_smush - $after_smush) / 1024), 2);
			}
			if ($compressed == 0) {
				$results_msg = __('Optimised', WP_SMPRO_DOMAIN);
			} else {
				$results_msg = sprintf(__("Reduced by %01.1f%% (%s)", WP_SMPRO_DOMAIN), $compressed, $savings_str . 'Kb');
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
		public function callback_response($done = true) {
			echo json_encode(array('status' => (int) $done));
			header("HTTP/1.0 200");
			exit;
		}

	}

}