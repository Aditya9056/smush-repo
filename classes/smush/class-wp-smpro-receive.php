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
if ( ! class_exists( 'WpSmProReceive' ) ) {

	/**
	 * Receives call backs from service
	 */
	class WpSmProReceive {

		/**
		 * Constructor, hooks callback urls
		 */
		public function __construct() {

			// process callback from smush service
			add_action( 'wp_ajax_receive_smushed_image', array( $this, 'receive' ) );
			add_action( 'wp_ajax_nopriv_receive_smushed_image', array( $this, 'receive' ) );
			add_action( 'wp_ajax_wp_smpro_smush_status', array( $this, 'check_smush_status' ) );
		}

		/**
		 * Receive the callback and send data for further processing
		 */
		function receive() {
			global $log;
			// get the contents of the callback
			$body = file_get_contents( 'php://input' );
			$body = urldecode( $body );

			$data = json_decode( $body, true );

			// filter with default data
			$defaults = array(
				'request_id' => null,
				'token'      => null,
				'data'       => array()
			);

			$req_data = wp_parse_args( $data, $defaults );

			$request_id = $req_data['request_id'];

			//Update sent ids
			$current_requests = get_option( WP_SMPRO_PREFIX . "current-requests", array() );

			if ( ! empty( $req_data['error'] ) ) {
				$log->error( 'WpSmproReceive: receieve', 'Error from API' . json_encode( $req_data['error'] ) );

				if ( ! empty( $current_requests[ $request_id ] ) ) {
					unset( $current_requests[ $request_id ] );
					update_option( WP_SMPRO_PREFIX . "current-requests", $current_requests );
				}
				echo json_encode( array( 'status' => 1 ) );
				die();
			}

			if ( empty( $current_requests[ $request_id ] ) || $req_data['token'] != $current_requests[ $request_id ]['token'] ) {

				echo json_encode( array( 'status' => 1 ) );

				//Remove Smush Status for the id, as we are never going to get the callback again
				unset( $current_requests[ $request_id ] );

				update_option( WP_SMPRO_PREFIX . "current-requests", $current_requests );

				if ( empty( $current_requests[ $request_id ] ) ) {
					$log->error( 'WpSmProReceive: receive', "Smush receive error, sent id not set in current requests " . $request_id );
				} else {
					error_log( json_encode( $req_data ) );
					$log->error( 'WpSmProReceive: receive', "Smush receive error, Token Mismatch for request " . $request_id );
				}

				unset( $req_data );
				die();
			}

			$attachment_data = $req_data['data'];
			$is_single       = ! ( count( $current_requests[ $request_id ]['sent_ids'] ) > 1 );

			$insert = $this->save( $attachment_data, $current_requests[ $request_id ]['sent_ids'], $is_single );

			unset( $attachment_data );
			unset( $req_data );
			unset( $data );

			$updated = $this->update( $insert, $request_id );

			$this->notify( $updated );

			echo json_encode( array( 'status' => 1 ) );
			die();
		}


		private function save( $data, $sent_ids, $is_single ) {
			if ( empty( $data ) ) {
				return;
			}

			global $wpdb;

			$timestamp = time();
			//@todo: fix query, it inserts multiple rows for same meta key
			$sql = "INSERT INTO $wpdb->postmeta (post_id,meta_key,meta_value) VALUES ";
			foreach ( $data as $attachment_id => &$smush_data ) {
				if ( in_array( $attachment_id, $sent_ids ) ) {
					$smush_data['timestamp'] = $timestamp;
					$values[]                = "(" . $attachment_id . ", '" . WP_SMPRO_PREFIX . "smush-data', '" . maybe_serialize( $smush_data ) . "')";
				}
			}
			if ( ! empty( $values ) ) {
				$sql .= implode( ',', $values );

				$insert = $wpdb->query( $sql );

				if ( $is_single ) {
					global $wp_smpro;
					$wp_smpro->fetch->fetch( $attachment_id, true );
				}

				return $insert;
			} else {
				return false;
			}

		}

		private function update( $insert, $request_id ) {
			if ( $insert === false || empty( $request_id ) ) {
				return $insert;
			}

			$updated = update_option( WP_SMPRO_PREFIX . "bulk-received", 1 );

			//store in current requests array, against request id
			$current_requests = get_option( WP_SMPRO_PREFIX . "current-requests", array() );
			if ( ! empty( $current_requests[ $request_id ] ) ) {
				$current_requests[ $request_id ]['received'] = 1;
				update_option( WP_SMPRO_PREFIX . "current-requests", $current_requests );
			}

			//Enable admin notice if it was hidden
			update_option( 'hide_smush_notice', 0 );

			return $updated;
		}

		private function notify( $processed ) {
			global $log;

			if ( $processed === false ) {
				return;
			}

			$to = get_option( 'admin_email' );

			$subject = sprintf( __( "%s: Smush Pro bulk smushing completed", WP_SMPRO_DOMAIN ), get_option( 'blogname' ) );

			$message = array();

			$message[] = sprintf( __( 'A recent bulk smushing request on your site %s has been completed!', WP_SMPRO_DOMAIN ), home_url() );
			$message[] = sprintf( __( 'Visit %s to download the smushed images to your site.', WP_SMPRO_DOMAIN ), admin_url( 'upload.php?page=wp-smpro-admin' ) );

			$body      = implode( "\r\n", $message );
			$mail_sent = wp_mail( $to, $subject, $body );
			if ( ! $mail_sent ) {
				$log->error( 'WpSmproReceive: notify', 'Notification email could not be sent' );
			}

			return $mail_sent;
		}

		function check_smush_status() {

			global $log;

			$bulk_request = get_option( WP_SMPRO_PREFIX . "bulk-sent", array() );

			if ( empty( $bulk_request ) ) {
				$res = array(
					'status' => 'no_request',
					'check_status' => false
				);
				wp_send_json_error($res);
			}

			$current_requests = get_option( WP_SMPRO_PREFIX . "current-requests", array() );

			$sent_ids[ $bulk_request ]['sent_ids'] = ! empty( $current_requests[ $bulk_request ] ) ? $current_requests[ $bulk_request ]['sent_ids'] : '';

			//if there is no sent id or images are not smushed yet
			if ( empty( $sent_ids[ $bulk_request ] ) || empty( $current_requests[ $bulk_request ]['received'] ) ) {
				//Query Server for status
				$req_args = array(
					'user-agent' => WP_SMPRO_USER_AGENT,
					'referrer'   => WP_SMPRO_REFRER,
					'timeout'    => WP_SMPRO_TIMEOUT,
					'sslverify'  => false
				);
				$url      = add_query_arg( array( 'id' => $bulk_request ), WP_SMPRO_SERVICE_STATUS );
				// make the post request and return the response
				$response = wp_remote_get( $url, $req_args );
				if ( ! $response || is_wp_error( $response ) ) {
					$log->error( 'WpSmproReceive: check_smush_status', 'Error while querying request status from server.' );
				} else {
					$data          = array();
					$response_body = wp_remote_retrieve_body( $response );
					if ( ! empty( $response_body ) ) {
						$response_body = json_decode( $response_body );
						if ( ! empty( $response_body->message ) ) {
							if ( $response_body->message == 'queue' ) {
								$data['message'] = __( 'The smushing elfs are busy, You are $%d in queue.', WP_SMPRO_DOMAIN );
								$data['message'] = sprintf( $data['message'], $response_body->pending_requests );
								wp_send_json_error( $data );
							} elseif ( $response_body->message == 'processing' ) {
								if ( $response_body->count === 0 ) {
									$data['message'] = __( 'Woohooo, we are crunching the numbers for you and than it is all done.', WP_SMPRO_DOMAIN );
								} else {
									$processed         = __( 'Your smush request is being processed.', WP_SMPRO_DOMAIN );
									$remaining_message = $response_body->count == 1 ? __( ' %d image is remaining.', WP_SMPRO_DOMAIN ) : __( ' %d images are remaining.', WP_SMPRO_DOMAIN );
									$data['message']   = $processed . sprintf( $remaining_message, $response_body->count );

									unset( $processed );
									unset( $remaining_message );
								}
								wp_send_json_error( $data );
							}
						}
					}
				}
				wp_send_json_error();

			} else {
				wp_send_json_success( $sent_ids );
			}
			die( 1 );
		}

	}

}