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
			add_action( 'wp_ajax_wp_smpro_send', array( $this, 'ajax_send' ) );

			if ( WP_SMPRO_AUTO ) {
				// add automatic smushing on upload
				add_filter( 'wp_generate_attachment_metadata', array( $this, 'auto_smush' ), 10, 2 );
			}
		}

		function auto_smush( $metadata, $attachment_id ) {

			global $wp_smpro;

			//Check API Status
			if ( $wp_smpro->admin->api_connected ) {

				//Send metadata and attachment id
				$sent = $this->send_request( $attachment_id, $metadata );
			}

			return $metadata;
		}

		/**
		 * Processes the ajax smush request
		 *
		 *
		 */
		function ajax_send() {
			global $wp_smpro;

			// check user permissions
			if ( ! current_user_can( 'upload_files' ) ) {
				wp_die( __( "You don't have permission to work with uploaded files.", WP_SMPRO_DOMAIN ) );
			}

			$response = array();

			//Check API Status
			if ( ! $wp_smpro->admin->api_connected ) {
				$response['status_code']    = 0;
				$response['status_message'] = __( "API not available", WP_SMPRO_DOMAIN );
				// print out the response
				echo json_encode( $response );

				// wp_ajax wants us to...
				die();
			}
			$attachment_id = ! empty( $_GET['attachment_id'] ) ? $_GET['attachment_id'] : '';

			// force attachment id to false if it isn't there
			if ( empty( $attachment_id ) ) {
				$attachment_id = false;
			}
			$sent = $this->send_request( $attachment_id );

			if ( ! $sent ) {
				$response['status_code']    = 0;
				$response['status_message'] = __( 'Sending failed. Please try again later', WP_SMPRO_DOMAIN );

				echo json_encode( $response );
				die();
			}

			if ( is_wp_error( $sent ) ) {
				echo json_encode( $this->wperror_tojs( $sent ) );
				die();
			}
			$response['status_code']    = 1;
			$response['count']          = $sent;
			$response['sent_count']     = count( get_site_option( WP_SMPRO_PREFIX . 'sent-ids', '', false ) ); //Fetch from site option
			$response['status_message'] = sprintf( __( '%d were sent for smushing', WP_SMPRO_DOMAIN ), $response['count'] );
			echo json_encode( $response );
			// wp_ajax wants us to...
			die();
		}

		function wperror_tojs( $wp_err ) {

			if ( ! is_wp_error( $wp_err ) ) {
				return array();
			}
			foreach ( $wp_err->errors as $err ) {
				$response['status_code']    = 0;
				$response['status_message'] = $err[0];
			}

			return $response;

		}

		/**
		 * Send a request for smushing
		 *
		 * @param bool|array|int $attachment_id attachment id or an array of attachment ids or false(for bulk smushing)
		 * @param string $metadata
		 *
		 * @return bool whether request was successful
		 */
		function send_request( $attachment_id = false, $metadata = '' ) {
			$updated_count = '';
			/*
			 * {
			 *      'api_key': '',
			 *      'url_prefix': 'http://somesite.com/wp-content/uploads',
			 *      'token': 2wde456gd,
			 *      'callback_url': 'http://somesite.com/admin-ajax.php?action=wp-smpro-received'
			 *      'progressive': 1,
			 * 	'remove_meta': 1,
			 *      'data': {
			 *                      {
			 *                              'attachment_id': 1254,
			 *                              'path_prefix': '14/08/',
			 *                              'files': {
			 *                                              'large' : 'file-720X480.jpg',
			 *                                              'medium' : 'file-270X230.jpg',
			 *                                              'thumbnail': 'file-150X150.jpg'
			 *                                        }
			 *                      },
			 *                      {
			 *                              'attachment_id': 1255,
			 *                              'path_prefix': '14/08/',
			 *                              'files': {
			 *                                              'full' : 'file2.jpg', // no large image
			 *                                              'medium' : 'file2-270X230.jpg',
			 *                                              'thumbnail': 'file2-150X150.jpg'
			 *                                        }
			 *                      },
			 *                      ... and so on
			 *      }
			 * }
			 */
			// formulate the request data as shown in the comment above
			$request_data = $this->form_request_data( $attachment_id, $metadata );

			// get the token out
			$token = $request_data->token;

			// get the sent ids, for our processing
			$sent_ids = $request_data->sent_ids;
			// remove them from the request object
			unset( $request_data->sent_ids );
			// post the request and get the response
			$response = $this->_post( $request_data );

			// if thre was an error, return it
			if ( is_wp_error( $response ) ) {
				// destroy the large request_data from memory
				unset( $request_data );

				return $response;
			}

			// destroy the large request_data from memory
			unset( $request_data );

			// process the response
			$updated = $this->process_response( $response, $token, $sent_ids );

			if ( $updated ) {
				$updated_count = count( $sent_ids );
			}

			// destroy all vars that we don't need
			unset( $token, $response, $sent_ids );

			// return the success status of update option
			// otherwise all this was a waste since we won't know how to process further
			return $updated_count;
		}

		/**
		 *
		 * @param type $response
		 * @param type $token
		 * @param type $sent_ids
		 *
		 * @return boolean
		 */
		private function process_response( $response, $token, $sent_ids ) {
			// request was successfully received
			if ( $response->success ) {
				// get the unique request id issued by smush service
				$request_id = $response->request_id;

				$updated = $this->update_options( $request_id, $token, $sent_ids );
//				$updated = true;
			} else {
				$updated = false;
			}

			return $updated;
		}

		/**
		 *
		 * @param type $request_id
		 * @param type $token
		 * @param type $sent_ids
		 *
		 * @return boolean
		 */
		private function update_options( $request_id, $token, $sent_ids ) {
			// update the sent ids array
			$sent_update = $this->update_sent_ids( $sent_ids );

			// if sent ids were updated, proceed further
			if ( $sent_update ) {

				// save the sent_ids for this request
				$this->update_bulk_status( $sent_ids, $request_id );

				$current_requests = get_option( WP_SMPRO_PREFIX . 'current-requests', array() );

				$current_requests[ $request_id ]['token'] = $token;

				$current_requests[ $request_id ]['sent_ids'] = $sent_ids;

				$current_requests[ $request_id ]['timestamp'] = time();

				$updated = boolval( update_option( WP_SMPRO_PREFIX . 'current-requests', $current_requests ) );
			} else {
				//otherwise the remaining process will break
				$updated = false;
			}

			unset( $sent_update );

			return $updated;
		}

		/**
		 *
		 * @param type $sent_ids
		 *
		 * @return type
		 */
		private function update_bulk_status( $sent_ids = false, $request_id = '' ) {
			if ( ! is_array( $sent_ids ) ) {
				return;
			}
			$is_bulk = ( count( $sent_ids ) > 1 );

			if ( ! $is_bulk ) {
				unset( $is_bulk );

				return;
			}

			unset( $is_bulk );
			// save that a bulk request has been sent for this site and is expected back
			update_option( WP_SMPRO_PREFIX . "bulk-sent", $request_id );
		}

		/**
		 * Updates the sent ids array in options table
		 *
		 * @param array $sent_ids The sent ids from the current request
		 *
		 * @return boolean Whether the update was successful
		 */
		private function update_sent_ids( $sent_ids ) {
			// get the array of ids sent previously
			$prev_sent_ids = get_site_option( WP_SMPRO_PREFIX . 'sent-ids', array(), false );

			// merge the newest sent ids with the existing ones

			$sent_ids = array_merge( $prev_sent_ids, $sent_ids );

			if ( is_array( $sent_ids ) ) {
				// update the sent ids
                $update = update_site_option(WP_SMPRO_PREFIX.'sent-ids', $sent_ids);
//				$update = true;

				return boolval( $update );
			}

			return false;
		}

		/**
		 * Formulate the request data
		 *
		 * @param int|array|bool $attachment_id The attachment id, or array of attachment ids or false
		 *
		 * @return object The request data
		 */
		private function form_request_data( $attachment_id, $metadata = '' ) {

			// instantiate
			$request_data = new stdClass();

			// add the callback url
			$request_data->callback_url = $this->form_callback_url();

			// get the upload url prefix (http://domain.com/wp-content/uploads or similar)
			$path_base                = wp_upload_dir();
			$request_data->url_prefix = $path_base['baseurl'];

			// add the API key
			$request_data->api_key = get_site_option( 'wpmudev_apikey' );

			// add a token
			$request_data->token = wp_create_nonce( WP_SMPRO_PREFIX . "request" );

			// add the smushing options
			$request_data = $this->add_options( $request_data );

			// add data for all the attachments
			$request_data = $this->add_attachment_data( $request_data, $attachment_id, $path_base['basedir'], $metadata );

			unset( $path_base );

			// return the formed request data
			return $request_data;
		}

		/**
		 * Add smush options to the request data
		 *
		 * @param object $request_data The existing request data
		 *
		 * @return object request data with options
		 */
		private function add_options( $request_data ) {
			global $wp_smpro;
			$options = $wp_smpro->smush_settings;

			// set values for the boolean fields
			foreach ( $options as $key => $val ) {
				if ( $key === 'auto' ) {
					continue;
				}
				$value = get_option( WP_SMPRO_PREFIX . $key, $val );

				$request_data->{$key} = isset( $value ) ? $value : $val;

			}

			unset( $options );

			return $request_data;
		}

		/**
		 * Add data for all attachments to the request data
		 *
		 * @param object $request_data The request data
		 * @param int|bool|array $attachment_id The attachment id, or array of attachment ids or false
		 * @param string $pathprefix path to the uploads dir
		 *
		 * @return object the request data with attachment data
		 */
		private function add_attachment_data( $request_data, $attachment_id, $pathprefix, $metadata = '' ) {
			$sent_ids = array();
			if ( ! empty( $metadata ) ) {
				$attachments[0]['type']          = get_post_mime_type( $attachment_id );
				$attachments[0]['attachment_id'] = $attachment_id;
				$attachments[0]['metadata']      = maybe_serialize( $metadata );
				$attachments[0]['metapath']      = $metadata['file'];
				$attachments                     = json_decode( json_encode( $attachments ), false );
			}
			// get all the attachment data from the db
			$attachments = ! empty( $attachments ) ? $attachments : $this->get_attachments( $attachment_id );

			//If there are no atachments, return
			if ( empty( $attachments ) ) {
				return $request_data;
			}

			// loop
			foreach ( $attachments as $key => &$attachment ) {
				//assume it is not animated
				$anim = false;
				// get the attachment data in the format we need
				$attachment = $this->format_attachment_data( $attachment, $anim, $pathprefix );

				// add this id to the list of sent ids
				$sent_ids[] = $attachment->attachment_id;
			}

			// update the sent ids
			$request_data->sent_ids = $sent_ids;
			unset( $sent_ids );

			// add the formatted attachment data to the request data
			$request_data->data = $attachments;
			unset( $attachments );

			return $request_data;
		}

		/**
		 * Get the attachments from the database for smushing
		 *
		 * @global object $wpdb
		 *
		 * @param int|bool|array $attachment_id
		 *
		 * @return object query results
		 */
		function get_attachments( $attachment_id = false ) {

			global $wpdb;
			// figure if we need to get data for specific ids
			$where_id_clause = $this->where_id_clause( $attachment_id );

			// so that we don't include the ids already sent
			$existing_clause = $this->existing_clause();

			// get the attachment id, attachment metadata and full size's path
			$sql     = "SELECT p.ID as attachment_id, p.post_mime_type as type, md.meta_value as metadata, mp.meta_value as metapath"
			           . " FROM $wpdb->posts as p"
			           // for attachment metadata
			           . " LEFT JOIN $wpdb->postmeta as md"
			           . " ON (p.ID= md.post_id AND md.meta_key='_wp_attachment_metadata')"
			           // for full size's path
			           . " LEFT JOIN $wpdb->postmeta as mp"
			           . " ON (p.ID= mp.post_id AND mp.meta_key='_wp_attached_file')"
			           // to check if attachment isn't already smushed
			           . " LEFT JOIN $wpdb->postmeta as m"
			           . " ON (p.ID= m.post_id AND m.meta_key='" . WP_SMPRO_PREFIX . "is-smushed')"
			           . " WHERE"
			           . " p.post_type='attachment'"
			           . " AND p.post_mime_type LIKE '%image/%'"
			           . " AND (m.meta_value='0' OR m.post_id IS NULL)"
			           . $where_id_clause
			           . $existing_clause
			           . " ORDER BY p.post_date ASC"
			           // get only 1000 at a time
			           . " LIMIT " . WP_SMPRO_REQUEST_LIMIT;
			$results = $wpdb->get_results( $sql );
			unset( $sql, $where_id_clause );

			return $results;
		}

		/**
		 * Creates an IN clause if we need to smush a single or specif ids
		 *
		 * @param int|bool|array $id attachment id
		 *
		 * @return string|null the IN clause
		 */
		private function where_id_clause( $id = false ) {

			// no id means we're bulk smushing; no clause required
			if ( empty( $id ) || $id === false ) {
				return;
			}

			// otherwise, we have specific ids
			$clause = ' AND p.ID';

			// if we have an array of ids
			if ( is_array( $id ) ) {
				$id_list = implode( ',', $id );
				$clause .= " IN ($id_list)";
				unset( $id_list );

				return $clause;
			}

			// if there's just one id
			if ( is_numeric( $id ) ) {
				$clause .= '=' . (int) $id;

				return $clause;
			}
		}

		/**
		 * Creates a NOT IN clause for ids that have already been sent
		 *
		 * @return string|null the NOT IN clause
		 */
		private function existing_clause() {

			// get all the sent ids
			$sent_ids = get_site_option( WP_SMPRO_PREFIX . 'sent-ids', array() );

			// we don't have any, no clause required
			if ( empty( $sent_ids ) ) {
				return;
			}

			// otherwise, create the clause
			$id_list = implode( ',', $sent_ids );
			unset( $sent_ids );

			$clause = " AND p.ID NOT IN ($id_list)";
			unset( $id_list );

			return $clause;
		}

		/**
		 * Formats the database result for each attachment
		 *
		 * @param object $row the databse row for each attachment
		 * @param boolean $anim whether the attachment is an animated gif
		 *
		 * @return \stdClass the formatted row
		 */
		private function format_attachment_data( $row, $anim = false, $path_prefix ) {

			$request_item = new stdClass();

			$request_item->attachment_id = $row->attachment_id;

			$metadata = maybe_unserialize( $row->metadata );

			$full_size_array = pathinfo( $row->metapath );

			$request_item->path_prefix = $full_size_array['dirname'];

			$full_image = $full_size_array['basename'];

			$filenames = array();

			// check large
			foreach ( $metadata['sizes'] as $size_key => $size_data ) {
				if ( !$this->send_if_gif( $size_data['mime-type'] ) ) {

					// check if this gif is animated
					$anim = $this->is_animated( $path_prefix . '/' . $request_item->path_prefix . '/' . $size_data['file'] );

					// if it is not animated, we don't send it
					if ( ! $anim ) {
						continue;
					}
				}
				$filenames[$size_key] = $size_data['file'];
			}

			// if there's no large size, the full is the large size
			// if it is an animated gif, we'll send the full size
			if ( ! isset( $filenames['large'] ) ) {
				$filenames['full'] = $full_image;
			}

			// not sending full size, otherwise

			$request_item->files = $filenames;

			unset( $filenames, $metadata, $full_size_array, $full_image );

			return $request_item;
		}

		/**
		 * Post the request data and return the response body
		 *
		 * @param object $request_data
		 *
		 * @return \WP_Error
		 */
		private function _post( $request_data ) {

			// send a post request and get response
			$response = $this->_post_request( $request_data );

			// validate response
			if ( ! $response || is_wp_error( $response ) ) {
				unset( $response, $request_data );

				return new WP_Error( 'failed', __( 'Request failed', WP_SMPRO_DOMAIN ) );
			}


			// if there was an http error
			if ( empty( $response['response']['code'] ) || $response['response']['code'] != 200 ) {
				unset( $response, $request_data );

				//Give a error
				return new WP_Error( 'failed', __( 'Service unavailable', WP_SMPRO_DOMAIN ) );
			}

			// otherwise, we received a proper response from the service
			$data = json_decode(
				wp_remote_retrieve_body(
					$response
				)
			);


			unset( $response, $request_data );

			// presenting service response
			return $data;
		}

		/**
		 * Make a post request to smush api and return the response
		 *
		 * @param type $request_data
		 *
		 * @return boolean|array false or the response
		 */
		private function _post_request( $request_data ) {

			if ( empty( $request_data ) ) {
				return false;
			}


			if ( defined( WP_SMPRO_DEBUG ) && WP_SMPRO_DEBUG ) {
				echo "DEBUG: Calling API: [" . $request_data . "]<br />";
			}
			$req_args = array(
				'body'       => array(
					'json' => json_encode( $request_data )
				),
				'user-agent' => WP_SMPRO_USER_AGENT,
				'timeout'    => WP_SMPRO_TIMEOUT,
				'sslverify'  => false
			);

			// make the post request and return the response
			$response = wp_remote_post( WP_SMPRO_SERVICE_URL, $req_args );

			return $response;
		}

		/**
		 * Form appropriate status message
		 *
		 * @global object $wp_smpro The plugin's global object
		 *
		 * @param int $status_code The status code returned from service
		 * @param int $request_err_code Additional request error code from service
		 *
		 * @return string The status message
		 */
		function get_status_msg( $status_code, $request_err_code ) {

			global $wp_smpro;

			$status_code = intval( $status_code );

			// get the status message for the status code
			$msg = $wp_smpro->status_msgs['smush_status'][ $status_code ];

			// if there was a request error, add the appropriate request error message
			if ( $status_code === 0 && $request_err_code !== '' ) {
				$msg .= ': ' . $wp_smpro->status_msgs['request_err_msg'][ intval( $request_err_code ) ];
			}

			return $msg;
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

			/**
			 * Filters the callback url for smushing
			 *
			 * @param string $callback_url the original callback url
			 */

			return apply_filters( 'smushitpro_callback_url', $callback_url );
		}

		/**
		 * Checks if a static gif should be sent for smushing
		 *
		 * @param int $attachment The attachment row from custom query
		 *
		 * @return boolean true, if fine to send, false, if not
		 */
		function send_if_gif( $type = '' ) {
			// not a gif, we can send
			if ( $type !== "image/gif" ) {
				return true;
			}

			// we will convert to png, send
			if ( WP_SMPRO_GIF_TO_PNG ) {
				return true;
			}

			// gif that user doesn't want to convert to png
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

	}

}