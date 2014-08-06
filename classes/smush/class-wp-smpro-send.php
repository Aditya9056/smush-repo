<?php

/**
 *
 * @package SmushItPro
 * @subpackage Sender
 * @version 1.0
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2014, Incsub (http://incsub.com)
 *
 */
if ( ! class_exists( 'WpSmProSend' ) ) {

	/**
	 * Sends Smush requests to service and processes response
	 */
	class WpSmProSend {

		/**
		 * Constructor.
		 *
		 * Hooks actions for smushing through admin or ajax
		 */
		function __construct() {

			// for ajax based smushing through bulk UI
			add_action( 'wp_ajax_wp_smpro_queue', array( $this, 'ajax_queue' ) );

			//Admin notice, if api is not accessible
//			add_action( 'init', array( $this, 'check_api_status' ) );

			if ( WP_SMPRO_AUTO ) {
				// add automatic smushing on upload
				add_filter( 'wp_generate_attachment_metadata', array( $this, 'auto_smush' ), 10, 2 );
			}
		}

		function auto_smush( $metadata, $attachment_id ) {

			//Send metadata and attachment id
			$this->add_meta_then_queue( intval( $attachment_id ), $metadata );

			return $metadata;
		}

		/**
		 * Returns the bool value of a variable
		 * @param $val
		 *
		 * @return bool
		 */
		function boolval($val) {
			return (bool) $val;
		}

		/**
		 * Processes the ajax smush request
		 *
		 * @todo Conditionally get next id, only for requests that need it
		 */
		function ajax_queue() {

			// check user permissions
			if ( ! current_user_can( 'upload_files' ) ) {
				wp_die( __( "You don't have permission to work with uploaded files.", WP_SMPRO_DOMAIN ) );
			}

			// get the attachment id from request
			$attachment_id = $_GET['attachment_id'];

			// do we need to fetch the next id
			$get_next = ! empty( $_GET['get_next'] ) ? $_GET['get_next'] : false;

			// check attachment id
			if ( ! isset( $attachment_id ) ) {
				wp_die( __( 'No attachment ID was provided.', WP_SMPRO_DOMAIN ) );
			}

			$response = array();

			// Send for further processing
			$response['status_code'] = $this->add_meta_then_queue( intval( $attachment_id ) );
                        
			// smush meta
			$smush_meta = get_post_meta( $attachment_id, 'smush_meta_full', true );
			
			// smush status msg
			$response['status_msg'] = ! empty( $smush_meta ) ? $smush_meta['status_msg'] : '';

			// we still fetch the next id if we are bulk smushing
			if ( $this->boolval( $get_next ) === true ) {
				// get the next id to send back
				$response['next'] = $this->get_next_id( intval( $attachment_id ) );

			}
			// print out the response
			echo json_encode( $response );

			// wp_ajax wants us to...
			die();
		}

		/**
		 * Gets the next id in queue
		 *
		 * @global object $wpdb WordPress database object
		 *
		 * @param int $id The current id
		 *
		 * @return int The next id
		 */
		function get_next_id( $id ) {
			global $wpdb;

			// the query folks
			/*
			 * We need attachments that either
			 * 	have the smush status meta as false, or
			 * 	do not have the meta set, at all
			 * So we do two joins
			 */
			$query = $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p "
			                         . "INNER JOIN {$wpdb->postmeta} pm ON "
			                         . "(p.ID = pm.post_id) "
			                         . "LEFT JOIN {$wpdb->postmeta} pmm ON "
			                         . "(p.ID = pmm.post_id AND pmm.meta_key = 'wp-smpro-is-sent') "
			                         . "WHERE p.post_type = 'attachment' "
			                         . "AND ("
			                         . "p.post_mime_type = 'image/jpeg' "
			                         . "OR p.post_mime_type = 'image/png' "
			                         . "OR p.post_mime_type = 'image/gif'"
			                         . ") "
			                         . "AND p.ID > %d "
			                         . "AND ( "
			                         . "("
			                         . "pm.meta_key = 'wp-smpro-is-sent' "
			                         . "AND CAST(pm.meta_value AS CHAR) = '0'"
			                         . ") "
			                         . "OR  pmm.post_id IS NULL"
			                         . ") "
			                         . "GROUP BY p.ID "
			                         . "ORDER BY p.post_date ASC LIMIT 0, 1", $id );

			// next id
			$next_id = $wpdb->get_var( $query );

			// return it
			return $next_id;
		}

		/**
		 * Gets the attachment meta and sends for smushing
		 *
		 * @param int $attachment_id
		 * @param array $metadata, default is empty
		 *
		 * @return bool|object True on success, WP_Error object on failure
		 */
		function add_meta_then_queue( $attachment_id, $metadata = '' ) {

			// check if it's an image
			if ( $attachment_id && wp_attachment_is_image( $attachment_id ) === false ) {
				return new WP_Error( 'invalid', __( 'Not a valid attachment', WP_SMPRO_DOMAIN ) );
			}

			// attachment path and url
			$attachment_file_path = get_attached_file( $attachment_id );
			$attachment_file_url  = wp_get_attachment_url( $attachment_id );

			// some debug info
			if ( defined( WP_SMPRO_DEBUG ) && WP_SMPRO_DEBUG ) {
				echo "DEBUG: attachment_file_path=[" . $attachment_file_path . "]<br />";
				echo "DEBUG: attachment_file_url=[" . $attachment_file_url . "]<br />";
			}
			// send for further processing
			$full_state = $this->prepare_and_send( $attachment_id, $attachment_file_path, $attachment_file_url );

			// we check the status of the full size to modify our meta
			if ( is_wp_error( $full_state ) ) {
				$smushed_status = 0;
			} else {
				$smushed_status = 1;
			}

			// update meta
			update_post_meta( $attachment_id, 'wp-smpro-is-sent', $smushed_status );

			//now see if other sizes should be smushed
			$this->check_send_sizes( $attachment_id, $attachment_file_path, $attachment_file_url, '', $metadata );

			return $smushed_status;

		}

		/**
		 * Prepares the attachment and all its sizes and sends for smushing
		 *
		 * @param array $meta The attachment metadata, with sizes, etc
		 * @param int $ID The attachment id
		 * @param boolean $force_resmush Force resmushing, inspite of previous status
		 *
		 * @return bool|object True on success, WP_Error object on failure
		 */
		function prepare_and_send( $ID = null, $attachment_file_path = '', $attachment_file_url = '', $force_resmush = true ) {


			// don't send if it's a static gif and user doesn't want png
			if ( ! $this->send_if_gif( $ID, $attachment_file_path ) ) {
				return new WP_Error( 'invalid', __( 'GIFs are not allowed by your settings', WP_SMPRO_DOMAIN ) );
			}
			// smush meta
			$smush_meta = get_post_meta( $ID, 'smush_meta_full', true );

			//Check if the image was previously smushed
			$previous_state = ! empty( $smush_meta ) ? $smush_meta['status_msg'] : '';

			// if we do send it for smushing
			if ( $force_resmush || $this->should_resend( $previous_state ) ) {
				$full_state = $this->send( $attachment_file_path, $attachment_file_url, $ID, 'full' );
			} else {
				$full_state = new WP_Error( 'already_sent', __( 'The attachment has been smushed already' ) );
			}

			return $full_state;

		}

		/**
		 * Send smush request for all Image sizes
		 * @param $attachment_id
		 * @param string $attachment_file_path
		 * @param string $attachment_file_url
		 * @param bool $force_resmush
		 * @param string $metadata, default is null
		 */
		function check_send_sizes( $attachment_id, $attachment_file_path = '', $attachment_file_url = '', $force_resmush = true, $metadata = '' ) {

			// get the attachment meta data
			$meta = ! empty ( $metadata ) ? $metadata : wp_get_attachment_metadata( $attachment_id );

			// no resized versions, so we can exit
			if ( ! isset( $meta['sizes'] ) ) {
				return;
			}

			// otherwise, send each size
			foreach ( $meta['sizes'] as $size_key => $size_data ) {
				if ( ! $force_resmush && $this->should_resend( @$meta['sizes'][ $size_key ]['wp_smushit'] ) === false ) {
					continue;
				}
				// we aren't concerned with what happens to the rest of the sizes, are we?
				$size_sent['size'] = $this->send_each_size( $attachment_file_path, $attachment_file_url, $attachment_id, $size_key, $size_data['file'] );
			}

			unset( $size_sent );

			return;
		}

		/**
		 * Process each image size
		 *
		 * @param string $file_path The image file path
		 * @param string $file_url The image url
		 * @param int $ID The attachment id
		 * @param string $size The size name, i.e., full, medium, thumbnail, etc
		 * @param string $file The filename
		 *
		 * @return bool|object True on success, WP_Error object on failure
		 */
		function send_each_size( $file_path, $file_url, $ID, $size, $file ) {

			error_log( $size );
			// We take the original image. The 'sizes' will all match the same URL and
			// path. So just get the dirname and rpelace the filename.
			$attachment_file_path_size = trailingslashit( dirname( $file_path ) ) . $file;

			$attachment_file_url_size = trailingslashit( dirname( $file_url ) ) . $file;

			if ( defined( WP_SMPRO_DEBUG ) && WP_SMPRO_DEBUG ) {
				echo "DEBUG: attachment_file_path_size=[" . $attachment_file_path_size . "]<br />";
				echo "DEBUG: attachment_file_url_size=[" . $attachment_file_url_size . "]<br />";
			}

			// send it
			return $this->send( $attachment_file_path_size, $attachment_file_url_size, $ID, $size );
		}

		/**
		 * Process an image with Smush.it Pro API
		 *
		 * @param string $img_path Image Path
		 * @param string $img_url Image URL
		 * @param int $ID Attachment id
		 * @param string $size The size name, i.e., full, medium, thumbnail, etc
		 *
		 * @return bool|object True on success, WP_Error object on failure
		 */
		function send( $img_path = '', $img_url = '', $ID = 0, $size = '' ) {
			global $wp_sm_pro;

			// try and make this data invalid
			$invalid = $this->invalidate( $img_path, $img_url );

			// it's invalid
			if ( is_wp_error( $invalid ) ) {
				return $invalid;
			}

			// data is fine
			// create nonce
			$token = wp_create_nonce( "smush_image_{$ID}_{$size}" );

			//instantiate the request class
			$requester = new WpSmProRequest();
			// send the post request and get the response data
			$data = $requester->_post( $img_path, $ID, $token, $size );

			// response is empty
			if ( empty( $data ) || is_wp_error( $data ) ) {
				//File was never processed, return the original meta
				return new WP_Error( 'response_failed', __( 'Response Failed', WP_SMPRO_DOMAIN ) );
			}

			$size_smush_meta = get_post_meta( $ID, "smush_meta_$size", true );

			//Check for error
			if ( $data->status_code === 0 ) {

				$size_smush_meta['status_msg'] = $data->status_msg;
				// update smush details
				update_post_meta( $ID, "smush_meta_$size", $size_smush_meta );

				return new WP_Error( 'smush_failed', $data->status_msg );
			}

			// all's fine, send response for processing
			return $this->process_response( $data );
		}

		/**
		 * Process the response from API
		 *
		 * @param object $data The data returned from service
		 *
		 * @return bool|object True on success, WP_Error object on failure
		 */
		function process_response( $data ) {
			global $wp_sm_pro;

			$status = false;

			//Get the returned file id and store it in meta
			$file_id          = isset( $data->file_id ) ? $data->file_id : '';
			$status_code      = isset( $data->status_code ) ? $data->status_code : '';
			$request_err_code = isset( $data->request_err_code ) ? $data->request_err_code : '';
			$attachment_id    = isset( $data->attachment_id ) ? $data->attachment_id : '';
			$image_size       = isset( $data->image_size ) ? $data->image_size : '';

			//Fetch old smush meta and update with the file id returned by API
			$image_size_smush_meta = get_post_meta( $attachment_id, "smush_meta_$image_size", true );

			$image_size_smush_meta = ! empty( $image_size_smush_meta ) ? $image_size_smush_meta : array();

			//If file id update
			if ( ! empty( $file_id ) ) {
				//Add file id, Status and Message
				$image_size_smush_meta['file_id']     = $file_id;
				$image_size_smush_meta['status_code'] = $status_code;
				$image_size_smush_meta['status_msg']  = $this->get_status_msg( $status_code, $request_err_code );
				$image_size_smush_meta['token']       = $data->token;

				$status = true;

			} else {
				$image_size_smush_meta['status_msg'] = __( 'Unable to process the image, please try again later', WP_SMPRO_DOMAIN );
				$status                              = new WP_Error( 'smush_failed', $image_size_smush_meta[ $image_size ]['status_msg'] );
			}

			// update smush info
			update_post_meta( $attachment_id, "smush_meta_$image_size", $image_size_smush_meta );

			return $status;
		}

		/**
		 * Form appropriate status message
		 *
		 * @global object $wp_sm_pro The plugin's global object
		 *
		 * @param int $status_code The status code returned from service
		 * @param int $request_err_code Additional request error code from service
		 *
		 * @return string The status message
		 */
		function get_status_msg( $status_code, $request_err_code ) {

			global $wp_sm_pro;

			$status_code = intval( $status_code );

			// get the status message for the status code
			$msg = $wp_sm_pro->status_msgs['smush_status'][ $status_code ];

			// if there was a request error, add the appropriate request error message
			if ( $status_code === 0 && $request_err_code !== '' ) {
				$msg .= ': ' . $wp_sm_pro->status_msgs['request_err_msg'][ intval( $request_err_code ) ];
			}

			return $msg;
		}

		/**
		 * Try and see if data is invalid
		 *
		 * @param string $img_path Image's file path
		 * @param string $file_url Image's file url
		 *
		 * @return bool|object True if valid, WP_Error object if invalid
		 */
		function invalidate( $img_path = '', $file_url = '' ) {
			if ( empty( $img_path ) ) {
				return new WP_Error( 'invalid', __( "File path is empty", WP_SMPRO_DOMAIN ) );
			}

			if ( empty( $file_url ) ) {
				return new WP_Error( 'invalid', __( "File URL is empty", WP_SMPRO_DOMAIN ) );
			}

			if ( ! file_exists( $img_path ) ) {
				return new WP_Error( 'invalid', __( "File does not exists", WP_SMPRO_DOMAIN ) );
			}

			// check that the file exists
			if ( ! file_exists( $img_path ) || ! is_file( $img_path ) ) {
				return new WP_Error( 'invalid',
					sprintf( __( "ERROR: Could not find <span class='code'>%s</span>", WP_SMPRO_DOMAIN ), $img_path ) );
			}

			// check that the file is writable
			if ( ! is_writable( dirname( $img_path ) ) ) {
				return new WP_Error( 'invalid',
					sprintf( __( "ERROR: <span class='code'>%s</span> is not writable", WP_SMPRO_DOMAIN ), dirname( $img_path ) ) );
			}

			$file_size = filesize( $img_path );
			if ( $file_size > WP_SMPRO_MAX_BYTES ) {
				return new WP_Error( 'invalid',
					sprintf( __( 'ERROR: <span style="color:#FF0000;">Skipped (%s) Unable to Smush due to 5mb size limits.</span>', WP_SMPRO_DOMAIN ), $this->format_bytes( $file_size ) ) );
			}

			return true;
		}

		/**
		 * WPMUDev API Key
		 *
		 * @return string
		 *
		 */
		function dev_api_key() {
			return get_site_option( 'wpmudev_apikey' );
		}

		/**
		 * Generate a callback URL for API
		 *
		 * @return mixed|void
		 */
		function form_callback_url() {
			$callback_url = admin_url( 'admin-ajax.php' );

			$callback_url = add_query_arg(
				array(
					'action' => 'receive_smushed_image'
				), $callback_url
			);

			return apply_filters( 'smushitpro_callback_url', $callback_url );
		}

		/**
		 * Check if we need to smush the image or not
		 *
		 * @param string $previous_status Previous smush status
		 *
		 * @return boolean True, if we need to smush and false, if not
		 */
		function should_resend( $previous_status ) {
			if ( ! $previous_status || empty( $previous_status ) ) {
				return true;
			}

			if (
				stripos( $previous_status, 'no savings' ) !== false
				|| stripos( $previous_status, 'reduced' ) !== false
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
		 *
		 * @return boolean true, if fine to send, false, if not
		 */
		function send_if_gif( $id, $path ) {
			$type = get_post_mime_type( $id );

			// not a gif, we can send
			if ( $type !== "image/gif" ) {
				return true;
			}

			// we will convert to png, send
			if ( WP_SMPRO_GIF_TO_PNG ) {
				return true;

			}

			// if it is animated, we'll send
			if ( $this->is_animated( $path ) ) {
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
		 *
		 * @return boolean whether the image is animated(more than 1 frame)
		 */
		function is_animated( $filename ) {

			if ( ! ( $fh = @fopen( $filename, 'rb' ) ) ) {
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
			while ( ! feof( $fh ) && $frames < 2 ) {
				$chunk = fread( $fh, 1024 * 100 ); //read 100kb at a time
				$frames += preg_match_all( '#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches );
			}

			fclose( $fh );

			return $frames > 1;
		}

		/**
		 * Return the filesize in a humanly readable format.
		 * Taken from http://www.php.net/manual/en/function.filesize.php#91477
		 *
		 * @param int $bytes Bytes
		 * @param int $precision The precision of rounding
		 *
		 * @return string formatted size
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
		 * Admin notice, If API is not reachable
		 */
		function server_not_found() {
			?>
			<div class="error">
				<p><?php _e( 'Sorry, we are having trouble reching WPMU servers, please try again later', WP_SMPRO_DOMAIN ); ?></p>
			</div> <?php
		}

		/**
		 * Check and add a admin notice If API is not accessible
		 */
		function check_api_status() {
			if ( empty ( $_REQUEST ) || empty ( $_REQUEST['api_status'] ) ) {
				return;
			}
			if ( $_REQUEST['api_status'] == 'error' ) {
				add_action( 'admin_notices', array( $this, 'server_not_found' ) );
			}

			return;
		}
	}

}