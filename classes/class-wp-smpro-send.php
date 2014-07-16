<?php

if ( ! class_exists( 'WpSmProSend' ) ) {

	class WpSmProSend {

		function __construct() {

			add_action( 'admin_action_wp_smpro_queue', array( &$this, 'queue' ) );

			add_action( 'wp_ajax_wp_smpro_queue', array( &$this, 'ajax_queue' ) );


			if ( WP_SMPRO_AUTO ) {
				// add automatic smushing on upload
				add_filter( 'wp_generate_attachment_metadata', array( &$this, 'queue_on_upload' ), 10, 2 );
			}
		}

		function ajax_queue() {
			if ( ! current_user_can( 'upload_files' ) ) {
				wp_die( __( "You don't have permission to work with uploaded files.", WP_SMPRO_DOMAIN ) );
			}

			if ( ! isset( $_GET['attachment_ID'] ) ) {
				wp_die( __( 'No attachment ID was provided.', WP_SMPRO_DOMAIN ) );
			}

			$attachment_ID = intval( $_GET['attachment_ID'] );

			$this->process_meta_in_queue( $attachment_ID );

			$next_id = $this->get_next_id( $attachment_ID );

			echo $next_id;

			die();

		}


		function get_next_id( $id ) {
			global $wpdb;
			$query = "SELECT p.ID FROM {$wpdb->posts} p "
			         . "LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id) "
			         . "WHERE p.ID > $id AND (pm.metakey='wp-smpro-is-smushed' AND pm.metavalue=1) "
			         . "AND p.post_type='attachment' "
			         . "AND p.post_mime_type = 'image' LIMIT 1";

			$next_id = $wpdb->get_var( $wpdb->prepare( $query ) );

			return $next_id;
		}

		/**
		 * Manually process an image from the Media Library
		 */
		function queue() {
			if ( ! current_user_can( 'upload_files' ) ) {
				wp_die( __( "You don't have permission to work with uploaded files.", WP_SMPRO_DOMAIN ) );
			}

			if ( ! isset( $_GET['attachment_ID'] ) ) {
				wp_die( __( 'No attachment ID was provided.', WP_SMPRO_DOMAIN ) );
			}

			$attachment_ID = intval( $_GET['attachment_ID'] );

			$this->process_meta_in_queue( $attachment_ID );
//			wp_die( $attachment_ID );
			wp_redirect( preg_replace( '|[^a-z0-9-~+_.?#=&;,/:]|i', '', wp_get_referer() ) );

			exit();
		}

		function process_meta_in_queue( $attachment_ID ) {

			$original_meta = wp_get_attachment_metadata( $attachment_ID );

			$meta = $this->queue_on_upload( $original_meta, $attachment_ID );

			//Update attachemnt meta data
			wp_update_attachment_metadata( $attachment_ID, $meta );

		}


		/**
		 *
		 * @param type $meta
		 * @param type $ID
		 * @param type $force_resmush
		 *
		 * @return type
		 */
		function queue_on_upload( $meta, $ID = null, $force_resmush = true ) {
			if ( $ID && wp_attachment_is_image( $ID ) === false ) {
				return $meta;
			}

			$attachment_file_path = get_attached_file( $ID );
			$attachment_file_url  = wp_get_attachment_url( $ID );

			if ( defined( WP_SMPRO_DEBUG ) && WP_SMPRO_DEBUG ) {
				echo "DEBUG: attachment_file_path=[" . $attachment_file_path . "]<br />";
				echo "DEBUG: attachment_file_url=[" . $attachment_file_url . "]<br />";
			}

			//Check if the image was prviously smushed
			$previous_state = ! empty( $meta['smush_meta'] ) ? $meta['smush_meta']['full']['status_msg'] : '';

			if ( $force_resmush || $this->should_resend( $previous_state ) ) {
				$meta = $this->send( $attachment_file_path, $attachment_file_url, $ID, 'full', $meta );
			}

			// no resized versions, so we can exit
			if ( ! isset( $meta['sizes'] ) ) {
				return $meta;
			}

			foreach ( $meta['sizes'] as $size_key => $size_data ) {
				if ( ! $force_resmush && $this->should_resend( @$meta['sizes'][ $size_key ]['wp_smushit'] ) === false ) {
					continue;
				}

				$meta = $this->send_each_size( $attachment_file_path, $attachment_file_url, $ID, $size_key, $meta );
			}

			return $meta;
		}

		/**
		 * Adjust the count cache
		 */
		function recount( $by ) {

			$cache_key = 'wp-smpro-to-smush-count';
			// get the cached value
			$count = wp_cache_get( $cache_key );

			// if there's a count in the cache, increment
			if ( false != $count ) {
				if ( $by === 1 ) {
					wp_cache_incr( $cache_key );
				} else {
					wp_cache_decr( $cache_key );
				}

			}

			// otherwise, do nothing the manual process will fetch and set a count
		}

		/**
		 * Process the each thumbnail size
		 *
		 * @param type $file_path
		 * @param type $file_url
		 * @param type $ID
		 * @param type $size
		 * @param type $meta
		 *
		 * return $smush_meta
		 */
		function send_each_size( $file_path, $file_url, $ID, $size, $meta ) {

			// We take the original image. The 'sizes' will all match the same URL and
			// path. So just get the dirname and rpelace the filename.
			$attachment_file_path_size = trailingslashit( dirname( $file_path ) ) . $meta['sizes'][ $size ]['file'];

			$attachment_file_url_size = trailingslashit( dirname( $file_url ) ) . $meta['sizes'][ $size ]['file'];

			if ( defined( WP_SMPRO_DEBUG ) && WP_SMPRO_DEBUG ) {
				echo "DEBUG: attachment_file_path_size=[" . $attachment_file_path_size . "]<br />";
				echo "DEBUG: attachment_file_url_size=[" . $attachment_file_url_size . "]<br />";
			}


			return $this->send( $attachment_file_path_size, $attachment_file_url_size, $ID, $size, $meta );
		}


		/**
		 * Process an image with Smush.it Pro API
		 *
		 * @param string $img_path , Image Path
		 * @param string $img_url , Image URL
		 * @param $ID , Attachment ID
		 * @param $size , image size, default is full
		 *
		 * @return string, Message containing compression details
		 */
		function send( $img_path = '', $img_url = '', $ID = 0, $size = 'full', $smush_meta ) {

			$invalid = $this->invalidate( $img_path, $img_url );

			if ( $invalid ) {
				update_post_meta( $ID, 'wp-smpro-is-smushed', 1 );
				$this->recount( 1 );

				return $invalid;
			}

			//Send nonce
			$token = wp_create_nonce( "smush_image_{$ID}_{$size}" );

			//Send file to API

			$requester = new WpSmProRequest();
			$data      = $requester->_post( $img_path, $ID, $token );

			//For testing purpose
//			error_log( json_encode( $data ) );
			if ( empty( $data ) ) {
				//File was never processed, return the original meta
				return $smush_meta;
			}
			//If there is no previous smush_data
			if ( empty ( $smush_meta ['smush_meta'] ) ) {
				$smush_meta['smush_meta'] = array(
					$size => array()
				);
			}

			//Check for error
			if ( $data->status_code === 0 ) {

				$smush_meta['smush_meta'][ $size ]['status_msg'] = $data->status_msg;

				return $smush_meta;
			}

			$smush_meta = $this->process_response( $data, $ID, $size, $smush_meta );

			return $smush_meta;
		}

		/**
		 * Process the response from API
		 *
		 * @param $data
		 * @param $ID
		 * @param $smush_meta
		 *
		 * @return $smush_meta
		 */
		function process_response( $data, $ID, $size, $smush_meta ) {

			//Get the returned file id and store it in meta
			$file_id          = isset( $data->file_id ) ? $data->file_id : '';
			$status_code      = isset( $data->status_code ) ? $data->status_code : '';
			$request_err_code = isset( $data->request_err_code ) ? $data->request_err_code : '';

			//Fetch old smush meta and update with the file id returned by API
			if ( empty( $smush_meta ) ) {
				$smush_meta = wp_get_attachment_metadata( $ID );
			}

			//If file id update
			if ( ! empty( $file_id ) ) {
				//Add file id, Status and Message
				$smush_meta['smush_meta'][ $size ]['file_id']     = $file_id;
				$smush_meta['smush_meta'][ $size ]['status_code'] = $status_code;
				$smush_meta['smush_meta'][ $size ]['status_msg']  = $this->get_status_msg( $status_code, $request_err_code );
				$smush_meta['smush_meta'][ $size ]['token']       = $data->token;

				update_post_meta( $ID, 'wp-smpro-is-smushed', 1 );
				$this->recount( 1 );

			} else {
				//Return a error
				$smush_meta['smush_meta'][ $size ]['status_msg'] = "Unable to process the image, please try again later";
				update_post_meta( $ID, 'wp-smpro-is-smushed', 0 );
				$this->recount( - 1 );
			}

			return $smush_meta;
		}

		function get_status_msg( $status_code, $request_err_code ) {
			global $wp_sm_pro;
			$status_code = intval( $status_code );
			$msg         = $wp_sm_pro->status_msgs['smush_status'][ $status_code ];
			if ( $status_code === 0 && $request_err_code !== '' ) {
				$msg .= ': ' . $wp_sm_pro->status_msgs['request_err_msg'][ intval( $request_err_code ) ];
			}

			return $msg;
		}

		function invalidate( $img_path = '', $file_url = '' ) {
			if ( empty( $img_path ) ) {
				return __( "File path is empty", WP_SMPRO_DOMAIN );
			}

			if ( empty( $file_url ) ) {
				return __( "File URL is empty", WP_SMPRO_DOMAIN );
			}

			if ( ! file_exists( $img_path ) ) {
				return __( "File does not exists", WP_SMPRO_DOMAIN );
			}

			// check that the file exists
			if ( ! file_exists( $img_path ) || ! is_file( $img_path ) ) {
				return sprintf( __( "ERROR: Could not find <span class='code'>%s</span>", WP_SMPRO_DOMAIN ), $img_path );
			}

			// check that the file is writable
			if ( ! is_writable( dirname( $img_path ) ) ) {
				return sprintf( __( "ERROR: <span class='code'>%s</span> is not writable", WP_SMPRO_DOMAIN ), dirname( $img_path ) );
			}

			$file_size = filesize( $img_path );
			if ( $file_size > WP_SMPRO_MAX_BYTES ) {
				return sprintf( __( 'ERROR: <span style="color:#FF0000;">Skipped (%s) Unable to Smush due to 5mb size limits.</span>', WP_SMPRO_DOMAIN ), $this->format_bytes( $file_size ) );
			}

			return false;
		}

		/**
		 * WPMUDev API Key
		 * @return string
		 * @Todo, fetch from dashboard plugin, or allow a input
		 */
		function dev_api_key() {
			return '3f2750fe583d6909b2018462fb216a2c5d5d75a9';
		}

		/**
		 * Generate a callback URL for API
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
		 * @param $previous_status
		 *
		 * @return bool
		 */
		function should_resend( $previous_status ) {
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


	}

}

