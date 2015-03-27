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
		var $api_connected;

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
			$this->api_connected = get_option( 'api_connected' );
		}

		function auto_smush( $metadata, $attachment_id ) {
			global $log;

			$allowed_images = array( 'image/jpeg', 'image/jpg', 'image/png', 'image/gif' );

			//check image type before sending
			if ( ! in_array( get_post_mime_type( $attachment_id ), $allowed_images ) ) {
				return $metadata;
			}

			//Check API Status
			if ( $this->api_connected ) {

				//Send metadata and attachment id
				$sent = $this->send_request( $attachment_id, $metadata );
				if ( ! $sent ) {
					$log->error( 'WpSmproSend: auto_smush', 'Auto Smush request not sent' );
				}
			} else {
				$log->error( 'WpSmproSend: auto_smush', 'API not connected' );
			}

			return $metadata;
		}

		/**
		 * Processes the ajax smush request
		 *
		 *
		 */
		function ajax_send() {
			global $log;

			// check user permissions
			if ( ! current_user_can( 'upload_files' ) ) {
				wp_die( __( "You don't have permission to work with uploaded files.", WP_SMPRO_DOMAIN ) );
			}

			$response = array();

			//Check API Status
			if ( ! $this->api_connected ) {
				$response['error'] = __( 'Oh Snap! Seems like Smush Pro server is not reachable, you can try back in some time.', WP_SMPRO_DOMAIN );
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
			$response = $this->send_request( $attachment_id );

			if ( ! empty( $response['error'] ) ) {
				echo json_encode( $response );
				die();
			}
			if ( empty( $response['updated_count'] ) || ! $response['updated_count'] ) {
				$response['error'] = __( 'Failed to update smush data for attachment meta', WP_SMPRO_DOMAIN );
				$log->error( 'WpSmproSend: ajax_send', 'Sent ids count not updated.' );

				echo json_encode( $response );
				die();
			}
			// default value
			$notify_at                             = get_option( WP_SMPRO_PREFIX . 'notify-at' );
			$notify_at                             = ! empty( $notify_at ) ? $notify_at : get_option( 'admin_email' );
			$status_message                        = $attachment_id === false ? sprintf( __( "%d attachments were sent for smushing. You'll be notified by email at %s once bulk smushing has been completed.", WP_SMPRO_DOMAIN ), $response['updated_count'], $notify_at ) : __( "Image sent for smushing.", WP_SMPRO_DOMAIN );
			$response['success']['status_code']    = 1;
			$response['success']['count']          = $response['updated_count'];
			$response['success']['sent_count']     = count( get_option( WP_SMPRO_PREFIX . 'sent-ids', '' ) ); //Fetch from site option
			$response['success']['status_message'] = $status_message;
			if ( $response['cdn'] ) {
				$response['success']['status_message'] .= __( '<br /> It looks like you are using a CDN service, please whitelist the Smush server IP: 66.135.55.161, for succesful smushing.' );
			}
			unset( $response['api'] );

			echo json_encode( $response );

			unset( $response );
			// wp_ajax wants us to...
			die();
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
			global $log, $wp_version;

			//Check for single smush limit

			if ( $attachment_id && count( $attachment_id ) == 1 ) {
				if ( $wp_version < '3.9' ) {
					$meta_query =
						array(
							'relation' => 'AND',
							array(
								'key'     => WP_SMPRO_PREFIX . 'is-smushed',
								'value'   => 'smush',
								'compare' => 'NOT EXISTS'
							),
							array(
								'key'     => WP_SMPRO_PREFIX . 'request-id',
								'compare' => 'EXISTS'

							)
						);
				} else {
					$meta_query =
						array(
							'relation' => 'AND',
							array(
								'key'     => WP_SMPRO_PREFIX . 'is-smushed',
								'compare' => 'NOT EXISTS'
							),
							array(
								'key'     => WP_SMPRO_PREFIX . 'request-id',
								'compare' => 'EXISTS'

							)
						);
				}
				//Check for existing single requests
				$args             = array(
					'fields'         => 'ids',
					'post_type'      => 'attachment',
					'post_status'    => 'any',
					'post_mime_type' => array( 'image/jpeg', 'image/gif', 'image/png' ),
					'order'          => 'ASC',
					'posts_per_page' => - 1,
					'meta_query'     => $meta_query
				);
				$existing_singles = new WP_Query( $args );
				if ( ! empty( $existing_singles->posts ) && count( $existing_singles->posts ) >= 1000 ) {
					//Don't send
					$response['error'] = __( 'Heya, there are around 1000 images being smushed already, you gotta wait until they finish up !!', WP_SMPRO_DOMAIN );

					return $response;
				}
			}
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
			$response = $this->form_request_data( $attachment_id, $metadata );

			if ( ! empty( $response['error'] ) ) {
				$log->error( 'WpSmproSend: send_request', 'Request not sent - ' . json_encode( $response ) );

				return $response;
			}
			// get the token out
			$token = $response['request_data']->token;

			// get the sent ids, for our processing
			$sent_ids = ! empty( $response['request_data']->sent_ids ) ? $response['request_data']->sent_ids : '';

			// remove them from the request object
			unset( $response['request_data']->sent_ids );

			//If we have any ids to send
			if ( ! empty( $sent_ids ) ) {
				// post the request and get the response
				$response = $this->_post( $response['request_data'] );
			}

			// destroy the large request_data from memory
			unset( $request_data );

			// if thre was an error, return it
			if ( ! empty( $response['error'] ) ) {
				//Error logged in _post function, so we don't need that here
				return $response;
			}

			// process the response
			$response = $this->process_response( $response, $token, $sent_ids );

			if ( ! empty( $response['updated'] ) && $response['updated'] ) {
				$updated_count = count( $sent_ids );
			}

			// destroy all vars that we don't need
			unset( $token, $sent_ids );
			$response['updated_count'] = $updated_count;

			// return the success status of update option
			// otherwise all this was a waste since we won't know how to process further
			return $response;
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

			// otherwise, we received a proper response from the service
			$data            = json_decode(
				wp_remote_retrieve_body(
					$response['api']
				)
			);
			$response['cdn'] = false;
			if ( ! empty( $data->server ) ) {
				//check for cloudflare or CDN or MaxCDN in server
				if ( stripos( $data->server, 'cloudflare' ) !== false || strpos( $data->server, 'cdn' ) !== false || strpos( $data->server, 'maxcdn' ) !== false ) {
					$response['cdn'] = true;
				}
			}
			// request was successfully received
			if ( $data->success ) {
				// get the unique request id issued by smush service
				$request_id = $data->request_id;

				$updated = $this->update_options( $request_id, $token, $sent_ids );
//				$updated = true;
			} else {
				global $log;
				$updated = false;

				//Check for existing bulk request
				if ( ! empty( $data->message ) && 'pending_request' == $data->message ) {
					$response['error'] = __( 'Hey there, it looks like there is a pending bulk request for this site, you can send a new one once the current request is completed.', WP_SMPRO_DOMAIN );
				} else {
					$log->error( 'WpSmProSend: process_response', 'Request error ' . json_encode( $data ) );
					$response['error'] = ! empty( $data->message ) ? $data->message : __( 'Attachment data missing, request not processed by API', WP_SMPRO_DOMAIN );
				}
			}

			$response['updated'] = $updated;

			return $response;
		}

		/**
		 * Updates the sent-ids, bulk-sent and current requests parameters
		 *
		 * @param type $request_id
		 * @param type $token
		 * @param type $sent_ids
		 *
		 * @return boolean
		 */
		private function update_options( $request_id, $token, $sent_ids ) {
			global $log;
			// update the sent ids array
			$sent_update = $this->update_sent_ids( $sent_ids );
//			$sent_update = true;
			if ( ! $sent_update ) {
				$log->error( 'WpSmproSend: update_options', 'Sent-ids not updated' );
			}
			// if sent ids were updated, proceed further
			if ( $sent_update ) {

				// save the sent_ids for this request
				$this->update_bulk_status( $sent_ids, $request_id );

				if ( is_array( $sent_ids ) && count( $sent_ids ) > 1 ) {
					$current_requests = get_option( WP_SMPRO_PREFIX . 'current-requests', array() );

					$current_requests[ $request_id ] = array(
						'token'     => $token,
						'sent_ids'  => $sent_ids,
						'timestamp' => time()
					);

					/* If random smush server is assigned, sotre a parameter, to allow compatibility with
					current smush api, while fetching images */
					if ( get_site_option( WP_SMPRO_PREFIX . 'smush_server', false ) ) {
						$current_requests[ $request_id ]['smush_server_assigned'] = true;
					}

					$updated = boolval( update_option( WP_SMPRO_PREFIX . 'current-requests', $current_requests ) );

					unset( $current_requests );
				} else {
					$smush_sent = array(
						'token'     => $token,
						'sent_ids'  => $sent_ids[0],
						'timestamp' => time(),
					);

					/* If random smush server is assigned, sotre a parameter, to allow compatibility with
					current smush api, while fetching images */
					if ( get_site_option( WP_SMPRO_PREFIX . 'smush_server', false ) ) {
						$smush_sent['smush_server_assigned'] = true;
					}

					//used in media library for showing button again
					update_post_meta( $sent_ids[0], WP_SMPRO_PREFIX . 'request-id', $request_id );
					update_post_meta( $sent_ids[0], WP_SMPRO_PREFIX . 'is-smushed', 0 );

					$updated = boolval( update_post_meta( $sent_ids[0], WP_SMPRO_PREFIX . 'request-' . $request_id, $smush_sent ) );
				}
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
			if ( ! array( $sent_ids ) ) {
				$sent_ids = explode( ',', $sent_ids );
			}

			// get the array of ids sent previously
			$prev_sent_ids = get_option( WP_SMPRO_PREFIX . 'sent-ids', array() );
			if ( is_array( $sent_ids ) && ! empty( $prev_sent_ids ) ) {
				// merge the newest sent ids with the existing ones
				$sent_ids = array_merge( $prev_sent_ids, $sent_ids );
			}

			if ( is_array( $sent_ids ) ) {
				// update the sent ids
				$update = update_option( WP_SMPRO_PREFIX . 'sent-ids', $sent_ids );

//				$update = true;

				return boolval( $update );
			} else {
				error_log( "String provided instead of array in WpSmproSend: update_sent_ids" );
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

			//Response
			$response = array();

			// add the callback url
			$request_data->callback_url = $this->form_callback_url();

			// get the upload url prefix (http://domain.com/wp-content/uploads or similar)
			$path_base                = wp_upload_dir();
			$request_data->url_prefix = $path_base['baseurl'];

			// add the API key
			$request_data->api_key = $this->dev_api_key();

			// add a token
			$request_data->token = wp_create_nonce( WP_SMPRO_PREFIX . "request" );

			// add the smushing options
			$request_data = $this->add_options( $request_data );

			// add data for all the attachments
			$request_data = $this->add_attachment_data( $request_data, $attachment_id, $path_base['basedir'], $metadata );

			unset( $path_base );

			if ( empty( $request_data ) ) {
				$response['error'] = __( "We couldn't find any attachment data to send.", WP_SMPRO_DOMAIN );
			} elseif ( ! empty( $request_data->error ) ) {
				$response['error'] = $request_data->error;
			} else {
				$response['request_data'] = $request_data;
			}

			// return the formed request data
			return $response;
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
			global $log;
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
				$log->error( 'WpSmproSend: add_attachment_data', 'Attachment data not found for the attachment: ' . $attachment_id );

				return $request_data;
			}

			$attachments_count = count( $attachments );
			// loop
			foreach ( $attachments as $key => &$attachment ) {
				$image_details = $file_size = '';
				//assume it is not animated
				$anim = false;

				$file = get_attached_file( $attachment->attachment_id );

				//Check if file exists
				if ( file_exists( $file ) ) {
					$image_details = getimagesize( $file );
					$file_size     = filesize( $file );
				} else {
					if ( count( $attachments ) == 1 ) {
						$log->error( "WpSmpro_Send: add_attachment_data", __( "File not found", WP_SMPRO_DOMAIN ) );
						$request_data->error = __( "Image file not found", WP_SMPRO_DOMAIN );

						return $request_data;
					} else {
						$log->error( "WpSmpro_Send: add_attachment_data", "File not found " . $attachment->attachment_id );
						unset( $attachments[ $key ] );
						continue;
					}
				}

				//If there are no image details, Skip attachment
				if ( empty( $image_details ) ) {
					if ( $attachments_count == 1 ) {
						$log->error( "WpSmpro_Send: add_attachment_data", "Not a image file" . $attachment->attachment_id );
						$request_data->error = __( "Not a image file", WP_SMPRO_DOMAIN );

						return $request_data;
					} else {
						$log->error( "WpSmpro_Send: add_attachment_data", "Not a image file" . $attachment->attachment_id );
						unset( $attachments[ $key ] );
						continue;
					}
				}
				//if there is no metadata check size
				if ( empty( $attachment->metadata ) ) {
					if ( count( $attachments ) == 1 ) {
						$log->error( "WpSmpro_Send: add_attachment_data", "Image metadata not found." . $attachment->attachment_id );
						$request_data->error = __( "Image metadata not found", WP_SMPRO_DOMAIN );

						return $request_data;
					} else {
						$log->error( "WpSmpro_Send: add_attachment_data", "Image metadata not found" . $attachment->attachment_id );
						unset( $attachments[ $key ] );
						continue;
					}
				} else {
					// get the attachment data in the format we need
					$attachment = $this->format_attachment_data( $attachment, $anim, $pathprefix, $file_size );

					// add this id to the list of sent ids
					$sent_ids[] = $attachment->attachment_id;
				}

				unset( $file, $image_details );
			}

			// update the sent ids
			$request_data->sent_ids = $sent_ids;
			unset( $sent_ids );

			if ( ! empty( $attachments ) ) {
				// add the formatted attachment data to the request data
				$request_data->data = $attachments;
			} else {
				$log->error( "WpSmpro_Send: add_attachment_data", "WpSmpro_Send: no attachments to smush " );

				return $request_data;
			}
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

			$allowed_images = "( 'image/jpeg', 'image/jpg', 'image/png', 'image/gif' )";

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
			           // to check if attachment isn't already smushed, Single smush Check
			           . " LEFT JOIN $wpdb->postmeta as ms"
			           . " ON (p.ID= ms.post_id AND ms.meta_key='" . WP_SMPRO_PREFIX . "request-id')"
			           . " WHERE"
			           . " p.post_type='attachment'"
			           . " AND p.post_mime_type IN " . $allowed_images
			           . " AND ( m . meta_value = '0' OR m . post_id IS null)"
			           . $where_id_clause
			           . " ORDER BY p . post_date DESC"
			           // get only 100 at a time
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
				$clause .= " IN( $id_list )";
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
		private function existing_clause( $id ) {
			global $log;
			// get all the sent ids
			$sent_ids = get_option( WP_SMPRO_PREFIX . 'sent-ids', array() );

			// we don't have any, no clause required
			if ( empty( $sent_ids ) ) {
				return;
			}

			// otherwise, create the clause
			$id_list = implode( ',', $sent_ids );

			if ( ! empty( $id ) && ! empty( $sent_ids ) ) {
				if ( ! is_array( $id ) ) {
					if ( in_array( $id, $sent_ids ) ) {
						$log->error( 'WpSmproSend: existing_clause', 'Id already sent for smushing - ' . $id_list );
					}
				} else {
					$common_ids = array_intersect( $id, $sent_ids );
					if ( ! empty( $common_ids ) ) {
						$log->error( 'WpSmproSend: existing_clause', 'Ids already sent for smushing - ' . implode( ',', $id ) );
					}
				}
			}

			unset( $sent_ids );

			$clause = " AND p . ID NOT IN( $id_list )";
			unset( $id_list );

			return $clause;
		}

		/**
		 * Formats the database result for each attachment
		 *
		 * @param object $row the databse row for each attachment
		 * @param boolean $anim whether the attachment is an animated gif
		 *
		 * @return stdClass the formatted row
		 */
		private function format_attachment_data( $row, $anim = false, $pathprefix, $file_size ) {
			global $log;

			$request_item = new stdClass();

			$request_item->attachment_id = $row->attachment_id;

			$metadata = maybe_unserialize( $row->metadata );

			$full_size_array = pathinfo( $row->metapath );

			$request_item->path_prefix = $full_size_array['dirname'];

			$full_image = $full_size_array['basename'];

			$filenames = array();
			if ( ! empty( $metadata['sizes'] ) ) {
				// check large
				foreach ( $metadata['sizes'] as $size_key => $size_data ) {
					$f_path = $pathprefix . '/' . trailingslashit( $request_item->path_prefix ) . $size_data['file'];
					if ( file_exists( $f_path ) ) {
						$size = filesize( $f_path );
						if ( ! empty( $file_size ) && $size < 5000000 ) {
							$filenames[ $size_key ] = $size_data['file'];
						} else {
							$log->error( 'Wp_Smpro_Send: format_attachmnet_data', 'File size exceeded the limit for image size: ' . $size_key . ', for attachment ' . $row->attachment_id );
						}
					}
				}
			}

			// if there's no large size, the full is the large size
			// if it is an animated gif, we'll send the full size
			if ( ! isset( $filenames['large'] ) ) {
				if ( ! empty( $file_size ) && $file_size < 5000000 ) {
					$filenames['full'] = $full_image;
				} else {
					$log->error( 'Wp_Smpro_Send: format_attachmnet_data', 'File size exceeded the limit for attachment ' . $row->attachment_id );
				}
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
			global $log;

			// send a post request and get response
			$response = $this->_post_request( $request_data );

			// validate response
			if ( ! $response['api'] || is_wp_error( $response['api'] ) ) {
				unset( $request_data );
				$response['error'] = __( 'Sorry, we were unable to send the request, as the site was not able to communicate with Smush Pro server.', WP_SMPRO_DOMAIN );
				$log->error( 'WpSmproSend: _post', 'Request sending failed' . json_encode( $response['api'] ) );

				return $response;
			}

			// if there was an http error
			if ( empty( $response['api']['response']['code'] ) || $response['api']['response']['code'] != 200 ) {

				$log->error( 'WpSmProSend::_post', @json_encode( $response ) );

				$response_code = $response['api']['response']['code'];
				unset( $response, $request_data );

				if ( $response_code == 413 ) {

					$response['error'] = __( "Request Entity Too Large (HTTP Error 413). Try sending lesser number of images, you can use define( 'WP_SMPRO_REQUEST_LIMIT', 500 ) to send less number of images", WP_SMPRO_DOMAIN );

				} else {
					$response['error'] = __( 'Something went wrong, smush request rejected by server.', WP_SMPRO_DOMAIN );
				}

				return $response;
			}


			unset( $request_data );

			// presenting service response
			return $response;
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

			$request_data = json_encode( $request_data );

			$req_args = array(
				'body'       => array(
					'json' => $request_data
				),
				'user-agent' => WP_SMPRO_USER_AGENT,
				'timeout'    => WP_SMPRO_TIMEOUT,
				'sslverify'  => false
			);

			// make the post request and return the response
			$response['api'] = wp_remote_post( WP_SMPRO_SERVICE_URL, $req_args );

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
			if ( defined( 'WPMUDEV_APIKEY' ) ) {
				$wpmudev_apikey = WPMUDEV_APIKEY;
			} else {
				$wpmudev_apikey = get_site_option( 'wpmudev_apikey', false );
			}

			return $wpmudev_apikey;
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
			if ( $type !== "image / gif" ) {
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

		/**
		 * Send a request to reset the bulk request
		 */
		function reset_bulk( $request_id, $token, $bulk_request_details ) {
			$request_data               = array();
			$request_data['api_key']    = $this->dev_api_key();
			$request_data['token']      = $token;
			$request_data['request_id'] = $request_id;

			$request_data = json_encode( $request_data );

			$req_args = array(
				'body'       => array(
					'json' => $request_data
				),
				'user-agent' => WP_SMPRO_USER_AGENT,
				'timeout'    => WP_SMPRO_TIMEOUT,
				'sslverify'  => false
			);
			//Reset URL, check if the request contains smush server assigned, and decide URL on bais of that
			$reset_url = WP_SMPRO_RESET_URL;

			//If smush server assigned is set use the new server url for request status, otherwise old url
			if ( ! empty ( $bulk_request_details ) && empty( $bulk_request_details['smush_server_assigned'] ) ) {
				$reset_url = 'https://smush.wpmudev.org/reset/';
			}


			// make the post request and return the response
			$response['api'] = wp_remote_post( $reset_url, $req_args );

			return $response;
		}
	}

}