<?php

/**
 * Sends Smush requests to service and processes response
 * 
 * @package SmushItPro/Sender
 * 
 * @version 1.0
 * 
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 * 
 * @copyright (c) 2014, Incsub (http://incsub.com)
 * 
 * @todo Send size name in the request and make the service send it in response
 * @todo Filter for gifs based on gif_to_png setting. No need to send static gifs
 */
if (!class_exists('WpSmProSend')) {

	class WpSmProSend {

		/**
		 * Constructor.
		 * 
		 * Hooks actions for smushing through admin or ajax
		 */
		function __construct() {

			// for manual individual smushing through media library
			add_action('admin_action_wp_smpro_queue', array(&$this, 'queue'));

			// for ajax based smushing through bulk UI
			add_action('wp_ajax_wp_smpro_queue', array(&$this, 'ajax_queue'));


			if (WP_SMPRO_AUTO) {
				// add automatic smushing on upload
				add_filter('wp_generate_attachment_metadata', array(&$this, 'prepare_and_send'), 10, 2);
			}
		}

		/**
		 * Processes the ajax smush request from bulk ui
		 * 
		 * @todo Conditionally get next id, only for requests that need it 
		 */
		function ajax_queue() {

			// check user permissions
			if (!current_user_can('upload_files')) {
				wp_die(__("You don't have permission to work with uploaded files.", WP_SMPRO_DOMAIN));
			}

			// get the attachment id from request
			$attachment_id = $_GET['attachment_id'];

			// check attachment id
			if (!isset($attachment_id)) {
				wp_die(__('No attachment ID was provided.', WP_SMPRO_DOMAIN));
			}

			// Send for further processing
			$this->add_meta_then_queue(intval($attachment_id));

			// get the next id to send back
			$next_id = $this->get_next_id(intval($attachment_id));

			// print it in the response
			echo $next_id;

			// wp_ajax wants us to...
			die();
		}

		/**
		 * Gets the next id in queue
		 * 
		 * @global object $wpdb WordPress database object
		 * @param int $id The current id
		 * @return int The next id
		 */
		function get_next_id($id) {
			global $wpdb;

			// the query folks
			/*
			 * We need attachments that either
			 * 	have the smush status meta as false, or
			 * 	do not have the meta set, at all
			 * So we do two joins
			 */
			$query = "SELECT p.ID FROM {$wpdb->posts} p "
				. "INNER JOIN {$wpdb->postmeta} pm ON "
				. "(p.ID = pm.post_id) "
				. "LEFT JOIN {$wpdb->postmeta} pmm ON "
				. "(p.ID = pmm.post_id AND pmm.meta_key = 'wp-smpro-is-smushed') "
				. "WHERE p.post_type = 'attachment' "
				. "AND ("
				. "p.post_mime_type = 'image/jpeg' "
				. "OR p.post_mime_type = 'image/png' "
				. "OR p.post_mime_type = 'image/gif'"
				. ") "
				. "AND p.ID>{$id} "
				. "AND ( "
				. "("
				. "pm.meta_key = 'wp-smpro-is-smushed' "
				. "AND CAST(pm.meta_value AS CHAR) = '0'"
				. ") "
				. "OR  pmm.post_id IS NULL"
				. ") "
				. "GROUP BY p.ID "
				. "ORDER BY p.post_date ASC LIMIT 0, 1";

			// next id
			$next_id = $wpdb->get_var($query);

			// return it
			return $next_id;
		}

		/**
		 * Manually process an image from the Media Library
		 */
		function queue() {

			// check user permissions
			if (!current_user_can('upload_files')) {
				wp_die(__("You don't have permission to work with uploaded files.", WP_SMPRO_DOMAIN));
			}

			// get the attachment id from request
			$attachment_id = $_GET['attachment_id'];

			// check attachment id
			if (!isset($attachment_id)) {
				wp_die(__('No attachment ID was provided.', WP_SMPRO_DOMAIN));
			}

			// Send for further processing
			$this->add_meta_then_queue(intval($attachment_id));

			// redirect to media library
			wp_redirect(preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', wp_get_referer()));

			exit();
		}

		/**
		 * Gets the attachment meta and sends for smushing
		 * 
		 * @param int $attachment_id
		 */
		function add_meta_then_queue($attachment_id) {
			// get the attachment meta data
			$metadata = wp_get_attachment_metadata($attachment_id);
			// send for further processing
			$this->prepare_and_send($metadata, $attachment_id);
		}

		/**
		 * Prepares the attachment and all its sizes and sends for smushing
		 * 
		 * @param array $meta The attachment metadata, with sizes, etc
		 * @param int $ID The attachment id
		 * @param boolean $force_resmush Force resmushing, inspite of previous status 
		 * @return array The metadata
		 */
		function prepare_and_send($meta, $ID = null, $force_resmush = true) {

			// check if it's an image
			if ($ID && wp_attachment_is_image($ID) === false) {
				return $meta;
			}

			
			// attachment path and url
			$attachment_file_path = get_attached_file($ID);
			$attachment_file_url = wp_get_attachment_url($ID);
			
			

			// some debug info
			if (defined(WP_SMPRO_DEBUG) && WP_SMPRO_DEBUG) {
				echo "DEBUG: attachment_file_path=[" . $attachment_file_path . "]<br />";
				echo "DEBUG: attachment_file_url=[" . $attachment_file_url . "]<br />";
			}
			
			// don't send if it's a static gif and user doesn't want png
			if(!$this->send_if_gif($ID, $attachment_file_path)){
				return $meta;
			}
			// smush meta
			$smush_meta = get_post_meta($ID, 'smush_meta', true);
			
			//Check if the image was previously smushed
			$previous_state = !empty($smush_meta) ? $smush_meta['full']['status_msg'] : '';

			// if we do send it for smushing
			if ($force_resmush || $this->should_resend($previous_state)) {
				$this->send($attachment_file_path, $attachment_file_url, $ID, 'full');
			}

			// no resized versions, so we can exit
			if (!isset($meta['sizes'])) {
				return $meta;
			}

			// otherwise, send each size
			foreach ($meta['sizes'] as $size_key => $size_data) {
				if (!$force_resmush && $this->should_resend(@$meta['sizes'][$size_key]['wp_smushit']) === false) {
					continue;
				}

				$this->send_each_size($attachment_file_path, $attachment_file_url, $ID, $size_key, $size_data['file']);
			}

			return $meta;
		}

		/**
		 * Adjust the count cache
		 * 
		 * @param int $by Whether to increase or decrease the count
		 */
		function recount($by) {

			$cache_key = 'wp-smpro-to-smush-count';
			// get the cached value
			$count = wp_cache_get($cache_key);

			// if there's a count in the cache, increment
			if (false != $count) {
				if ($by === 1) {
					wp_cache_incr($cache_key);
				} else {
					wp_cache_decr($cache_key);
				}
			}

			// otherwise, do nothing the manual process will fetch and set a count
		}

		/**
		 * Process each image size
		 *  
		 * @param string $file_path The image file path
		 * @param string $file_url The image url
		 * @param int $ID The attachment id
		 * @param string $size The size name, i.e., full, medium, thumbnail, etc
		 * @param string $file The filename
		 * @return array smush meta returned from send
		 */
		function send_each_size($file_path, $file_url, $ID, $size, $file) {

			// We take the original image. The 'sizes' will all match the same URL and
			// path. So just get the dirname and rpelace the filename.
			$attachment_file_path_size = trailingslashit(dirname($file_path)) . $file;

			$attachment_file_url_size = trailingslashit(dirname($file_url)) . $file;

			if (defined(WP_SMPRO_DEBUG) && WP_SMPRO_DEBUG) {
				echo "DEBUG: attachment_file_path_size=[" . $attachment_file_path_size . "]<br />";
				echo "DEBUG: attachment_file_url_size=[" . $attachment_file_url_size . "]<br />";
			}

			// send it
			return $this->send($attachment_file_path_size, $attachment_file_url_size, $ID, $size);
		}

		/**
		 * Process an image with Smush.it Pro API
		 *
		 * @param string $img_path Image Path
		 * @param string $img_url Image URL
		 * @param int $ID Attachment id
		 * @param string $size The size name, i.e., full, medium, thumbnail, etc
		 *
		 * @return string, Message containing compression details
		 * @todo	better empty response handling,
		 * 		so we know API is unavailable
		 * 		on bulk smushing, especially
		 */
		function send($img_path = '', $img_url = '', $ID = 0, $size = '') {

			// try and make this data invalid
			$invalid = $this->invalidate($img_path, $img_url);

			// it's invalid
			if ($invalid) {
				update_post_meta($ID, 'wp-smpro-is-smushed', 1);
				$this->recount(1);

				return $invalid;
			}

			// data is fine
			// create nonce
			$token = wp_create_nonce("smush_image_{$ID}_{$size}");

			//instantiate the request class
			$requester = new WpSmProRequest();
			// send the post request and get the response data
			$data = $requester->_post($img_path, $ID, $token, $size);

			// response is empty
			if (empty($data)) {
				//File was never processed, return the original meta
				return;
			}
			//If there is no previous smush_data
			if (empty($smush_meta [$size])) {
				$smush_meta[$size] = array();
			}

			//Check for error
			if ($data->status_code === 0) {

				$smush_meta[$size]['status_msg'] = $data->status_msg;
				// update smush details
				update_post_meta($ID, 'smush_meta', $smush_meta);
			}

			// all's fine, send response for processing
			$this->process_response($data, $size);
		}

		/**
		 * Process the response from API
		 * 
		 * @param object $data The data returned from service
		 * @param string $size The size: full, medium, etc of the image
		 */
		function process_response($data, $size) {

			//Get the returned file id and store it in meta
			$file_id = isset($data->file_id) ? $data->file_id : '';
			$status_code = isset($data->status_code) ? $data->status_code : '';
			$request_err_code = isset($data->request_err_code) ? $data->request_err_code : '';
			$attachment_id = isset($data->attachment_id) ? $data->attachment_id : '';

			//Fetch old smush meta and update with the file id returned by API
			$smush_meta = get_post_meta($attachment_id, 'smush_meta', true);

			if (empty($smush_meta[$size])) {
				$smush_meta[$size] = array();
			}

			//If file id update
			if (!empty($file_id)) {
				//Add file id, Status and Message
				$smush_meta[$size]['file_id'] = $file_id;
				$smush_meta[$size]['status_code'] = $status_code;
				$smush_meta[$size]['status_msg'] = $this->get_status_msg($status_code, $request_err_code);
				$smush_meta[$size]['token'] = $data->token;

				// only for one size, otherwise counter will go haywire
				if ($size === 'full') {
					// update meta
					update_post_meta($attachment_id, 'wp-smpro-is-smushed', 1);
					// increase count cache for smushed attachments
					$this->recount(1);
				}
			} else {
				// failed, decrease count cache
				$smush_meta[$size]['status_msg'] = "Unable to process the image, please try again later";
				if ($size === 'full') {
					update_post_meta($attachment_id, 'wp-smpro-is-smushed', 0);
					$this->recount(- 1);
				}
			}

			// update smush info
			update_post_meta($attachment_id, 'smush_meta', $smush_meta);
		}
		
		/**
		 * Form appropriate status message
		 * 
		 * @global object $wp_sm_pro The plugin's global object
		 * @param int $status_code The status code returned from service
		 * @param int $request_err_code Additional request error code from service
		 * @return string The status message
		 */
		function get_status_msg($status_code, $request_err_code) {
			
			global $wp_sm_pro;

			$status_code = intval($status_code);
			
			// get the status message for the status code
			$msg = $wp_sm_pro->status_msgs['smush_status'][$status_code];
			
			// if there was a request error, add the appropriate request error message
			if ($status_code === 0 && $request_err_code !== '') {
				$msg .= ': ' . $wp_sm_pro->status_msgs['request_err_msg'][intval($request_err_code)];
			}

			return $msg;
		}
		
		/**
		 * Try and see if data is invalid
		 * 
		 * @param string $img_path Image's file path
		 * @param string $file_url Image's file url
		 * @return string|boolean Error message, if invalid, false if valid
		 */
		function invalidate($img_path = '', $file_url = '') {
			if (empty($img_path)) {
				return __("File path is empty", WP_SMPRO_DOMAIN);
			}

			if (empty($file_url)) {
				return __("File URL is empty", WP_SMPRO_DOMAIN);
			}

			if (!file_exists($img_path)) {
				return __("File does not exists", WP_SMPRO_DOMAIN);
			}

			// check that the file exists
			if (!file_exists($img_path) || !is_file($img_path)) {
				return sprintf(__("ERROR: Could not find <span class='code'>%s</span>", WP_SMPRO_DOMAIN), $img_path);
			}

			// check that the file is writable
			if (!is_writable(dirname($img_path))) {
				return sprintf(__("ERROR: <span class='code'>%s</span> is not writable", WP_SMPRO_DOMAIN), dirname($img_path));
			}

			$file_size = filesize($img_path);
			if ($file_size > WP_SMPRO_MAX_BYTES) {
				return sprintf(__('ERROR: <span style="color:#FF0000;">Skipped (%s) Unable to Smush due to 5mb size limits.</span>', WP_SMPRO_DOMAIN), $this->format_bytes($file_size));
			}

			return false;
		}

		/**
		 * WPMUDev API Key
		 * 
		 * @return string
		 * @Todo, fetch from dashboard plugin, or allow a input
		 */
		function dev_api_key() {
			return '3f2750fe583d6909b2018462fb216a2c5d5d75a9';
		}

		/**
		 * Generate a callback URL for API
		 * 
		 * @return mixed|void
		 */
		function form_callback_url() {
			$callback_url = admin_url('admin-ajax.php');

			$callback_url = add_query_arg(
				array(
			    'action' => 'receive_smushed_image'
				), $callback_url
			);

			return apply_filters('smushitpro_callback_url', $callback_url);
		}

		/**
		 * Check if we need to smush the image or not
		 *
		 * @param string $previous_status Previous smush status
		 *
		 * @return boolean True, if we need to smush and false, if not
		 */
		function should_resend($previous_status) {
			if (!$previous_status || empty($previous_status)) {
				return true;
			}

			if (
				stripos($previous_status, 'no savings') !== false 
				|| stripos($previous_status, 'reduced') !== false
				) {
				return false;
			}

			// otherwise an error
			return true;
		}
		
		/**
		 * Checks if a static gif should be sent for smushing
		 * 
		 * @param int $id attachment id
		 * @param string $path the attachment file path
		 * @return boolean true, if fine to send, false, if not
		 */
		function send_if_gif($id, $path){
			$type = get_post_mime_type($id);
			
			// not a gif, we can send
			if($type!=="image/gif"){
				return true;
			}
			
			// we will convert to png, send
			if(WP_SMPRO_GIF_TO_PNG){
				return true;
				
			}
			
			// if it is animated, we'll send
			if($this->is_animated($path)){
				return true;
			}
			// static gif that user doesn't want to convert to png
			return false;
		}
		
		/**
		 * Checks if a gif is animated.
		 * (http://php.net/manual/en/function.imagecreatefromgif.php#104473)
		 * 
		 * We don't send static gifs to service if gif_to_png is false
		 * 
		 * @param string $filename full filename with path
		 * @return boolean whether the image is animated(more than 1 frame)
		 */
		function is_animated($filename) {
			
			if (!($fh = @fopen($filename, 'rb'))){
				return false;
			}
			
			$frames = 0;
			
			/*
			 * an animated gif contains multiple "frames",
			 * each frame has a header made up of:
			 * * a static 4-byte sequence (\x00\x21\xF9\x04)
			 * * 4 variable bytes
			 * * a static 2-byte sequence (\x00\x2C)
			 * * (some variants may use \x00\x21 ?) Adobe :|
			 * We read through the file til we reach the end,
			 * or we've found at least 2 frame headers
			 * 
			 */
			while (!feof($fh) && $frames < 2) {
				$chunk = fread($fh, 1024 * 100); //read 100kb at a time
				$frames += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
			}

			fclose($fh);
			return $frames > 1;
		}

		/**
		 * Return the filesize in a humanly readable format.
		 * Taken from http://www.php.net/manual/en/function.filesize.php#91477
		 * 
		 * @param int $bytes Bytes
		 * @param int $precision The precision of rounding
		 * @return string formatted size
		 */
		function format_bytes($bytes, $precision = 2) {
			$units = array('B', 'KB', 'MB', 'GB', 'TB');
			$bytes = max($bytes, 0);
			$pow = floor(( $bytes ? log($bytes) : 0 ) / log(1024));
			$pow = min($pow, count($units) - 1);
			$bytes /= pow(1024, $pow);

			return round($bytes, $precision) . ' ' . $units[$pow];
		}

	}

}