<?php
/**
 * @package SmushItPro
 * @subpackage Sender
 * @version 1.0
 * 
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 * 
 * @copyright (c) 2014, Incsub (http://incsub.com)
 */
if (!class_exists('WpSmProRequest')) {
	
	/**
	 * Forms and sends http post requests to service
	 * 
	 */

	class WpSmProRequest {

		/**
		 * Send a post request to the Smush service and present the response
		 *
		 * @param string $img_path
		 * @param int $attachment_id
		 * @param string $token
		 * @param string $size
		 *
		 * @return bool|string, Response returned from API
		 */
		public function _post($img_path = '', $attachment_id = 0, $token = false, $size ) {

			$data = false;

			//If $size is missing
			if( empty( $size ) ) {
				return __( 'Image size not specified', WP_SMPRO_DOMAIN );
			}
			// get the response of the post request
			$response = $this->_post_request($img_path, $attachment_id, $token, $size );

			// if there was no error
			if ($response && !is_wp_error($response)) {

				// if there was an http error
				if (empty($response['response']['code']) || $response['response']['code'] != 200) {
					//Give a error
					return __('Error in processing file', WP_SMPRO_DOMAIN);
				}

				// otherwise, we received a proper response from the service
				$data = json_decode(
					wp_remote_retrieve_body(
						$response
					)
				);
			}

			// presenting service response
			return $data;
		}

		/**
		 * Prepare and present the post data for our request
		 *
		 * @param int $attachment_id
		 * @param string $token
		 * @param string $size
		 *
		 * @return array The post data for the request
		 */
		private function _data($attachment_id = 0, $token = '', $size, $img_path ) {
			global $wp_sm_pro;
			// We can't do anything without these
			if (!$attachment_id || $token === '' || empty( $size ) ) {
				return array();
			}

			// default fields that we need to set
			$post_fields = array(
				'callback_url'  => '',
				'api_key'       => '',
				'token'         => '',
				'attachment_id' => 0,
				'progressive'   => true,
				'gif_to_png'    => true,
				'remove_meta'   => true,
				'size'          => '',
				'img_url'       => ''
			);

			// set values for the boolean fields
			foreach ($post_fields as $key => &$val) {
				if (!is_bool($key)) {
					continue;
				}

				$newval = get_option('wp_smushit_pro_' . $key, '');
				if (empty($newval) || $newval != 'on') {
					$post_fields[$key] = 0;
				}
			}

			// set up remaining fields
			$post_fields['callback_url'] = $wp_sm_pro->sender->form_callback_url();
			$post_fields['attachment_id'] = $attachment_id;
			$post_fields['api_key'] = $wp_sm_pro->sender->dev_api_key();
			$post_fields['token'] = $token;
			$post_fields['size'] = $size;
			$post_fields['img_url'] = $img_path;

			// presenting, the post data
			return $post_fields;
		}

		/**
		 * Create a file payload, as wp_remote_post doesn't have a file support
		 *
		 * @param $img_path
		 * @param $ID
		 * @param $boundary
		 * @param $token
		 * @param string $size
		 *
		 * @return string|boolean
		 */
		private function _payload($img_path, $ID, $token, $size ) {

			$payload = '';

			// get the post data
			$post_fields = $this->_data($ID, $token, $size, $img_path );

			return $post_fields;
		}

		/**
		 * Make the post request and present the response
		 *
		 * @param string $img_path
		 * @param img $attachment_id
		 * @param string $token
		 * @param string $size
		 *
		 * @return boolean|object either false or response
		 */
		private function _post_request($img_path = '', $attachment_id = 0, $token = false, $size ) {
			$req = WP_SMPRO_SERVICE_URL;

			if (defined(WP_SMPRO_DEBUG) && WP_SMPRO_DEBUG) {
				echo "DEBUG: Calling API: [" . $req . "]<br />";
			}

			//Get Image URL
			$img_url = wp_get_attachment_image_src( $attachment_id, $size );
			if( empty ( $img_url ) ) {
				error_log("No Image URL for $attachment_id $size");
				return;
			}

			$img_url = $img_url[0];

			$payload = $this->_payload($img_url, $attachment_id, $token, $size);
			if (empty($payload)) {
				return false;
			}

			$req_args = array(
			    'body' => $payload,
			    'user-agent' => WP_SMPRO_USER_AGENT,
			    'timeout' => WP_SMUSHIT_PRO_TIMEOUT,
			    //Remove this code
			    'sslverify' => false
			);
			
			// make the post request and return the response
			return wp_remote_post($req, $req_args);
		}

	}

}
