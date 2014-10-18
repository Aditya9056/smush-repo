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

			$data = wp_parse_args( $data, $defaults );

			$request_id = $data['request_id'];

			if ( ! empty( $data['error'] ) ) {
				$log->error('WpSmproReceive: receieve', 'Error from API' . json_encode( $data['error']  ) );

				//Update sent ids
				$current_requests = get_site_option( WP_SMPRO_PREFIX . "current-requests", array() );
				if ( ! empty( $current_requests[ $request_id ] ) ) {
					unset( $current_requests[ $request_id ] );
					update_site_option( WP_SMPRO_PREFIX . "current-requests", $current_requests );
				}
				echo json_encode( array( 'status' => 1 ) );
				die();
			}

			$current_requests = get_site_option( WP_SMPRO_PREFIX . "current-requests", array() );

			if ( empty( $current_requests[ $request_id ] ) || $data['token'] != $current_requests[ $request_id ]['token'] ) {
				unset( $data );
				echo json_encode( array( 'status' => 1 ) );
				$log->error( 'WpSmProReceive: receive', "Smush receive error, Token Mismatch" );
				die();
			}

			$attachment_data = $data['data'];
			$is_single       = ! ( count( $current_requests[ $request_id ]['sent_ids'] ) > 1 );

			$insert = $this->save( $attachment_data, $current_requests[ $request_id ]['sent_ids'], $is_single );

			unset( $attachment_data );
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

			$updated = update_site_option( WP_SMPRO_PREFIX . "bulk-received", 1 );

			//store in current requests array, against request id
			$current_requests = get_site_option( WP_SMPRO_PREFIX . "current-requests", array() );
			if ( ! empty( $current_requests[ $request_id ] ) ) {
				$current_requests[ $request_id ]['received'] = 1;
				update_site_option( WP_SMPRO_PREFIX . "current-requests", $current_requests );
			}

			//Enable admin notice if it was hidden
			update_site_option( 'hide_smush_notice', 0 );

			return $updated;
		}

		private function notify( $processed ) {
			global $log;

			if ( $processed === false ) {
				return;
			}

			$to = get_site_option( 'admin_email' );

			$subject = sprintf( __( "%s: Smush Pro bulk smushing completed", WP_SMPRO_DOMAIN ), get_option( 'blogname' ) );

			$message = array();

			$message[] = sprintf( __( 'A recent bulk smushing request on your site %s has been completed!', WP_SMPRO_DOMAIN ), home_url() );
			$message[] = sprintf( __( 'Visit %s to download the smushed images to your site.', WP_SMPRO_DOMAIN ), admin_url( 'upload.php?page=wp-smpro-admin' ) );

			$body = implode( "\r\n", $message );
			$mail_sent = wp_mail( $to, $subject, $body );
			if ( !$mail_sent ){
				$log->error('WpSmproReceive: notify', 'Notification email could not be sent');
			}
			return $mail_sent;
		}

		function check_smush_status() {

			$bulk_request = get_site_option( WP_SMPRO_PREFIX . "bulk-sent", array(), false );
			if ( empty( $bulk_request ) ) {
				wp_send_json_error();
			}

			$current_requests = get_site_option( WP_SMPRO_PREFIX . "current-requests", array(), false );

			$sent_ids[ $bulk_request ]['sent_ids'] = ! empty( $current_requests[ $bulk_request ] ) ? $current_requests[ $bulk_request ]['sent_ids'] : '';

			//if there is no sent id or images are not smushed yet
			if ( empty( $sent_ids[ $bulk_request ] ) || empty( $current_requests[ $bulk_request ]['received'] ) ) {
				wp_send_json_error();
			} else {
				wp_send_json_success( $sent_ids );
			}
			die(1);
		}

	}

}