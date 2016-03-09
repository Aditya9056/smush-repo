<?php
if ( ! class_exists( 'WPSmushNextGenBulk' ) ) {
	class WPSmushNextGenBulk extends WpSmushNextGen {

		function __construct() {
			add_action( 'wp_ajax_wp_smushit_nextgen_bulk', array( $this, 'smush_bulk' ) );
		}

		function smush_bulk() {

			global $wpsmushnextgenstats, $wpsmushit_admin;

			$stats = array();

			if ( empty( $_GET['attachment_id'] ) ) {
				wp_send_json_error( 'missing id' );
			}

			$atchmnt_id = absint( (int) $_GET['attachment_id'] );

			$smush  = $this->smush_image( $atchmnt_id, '', false );

			$stats = $wpsmushnextgenstats->get_smush_stats();

			$stats['smushed'] = $wpsmushnextgenstats->get_ngg_images('smushed', true );
			$stats['total']   = $wpsmushnextgenstats->total_count();

			if ( is_wp_error( $smush ) ) {
				$error = $smush->get_error_message();
				//Check for timeout error and suggest to filter timeout
				if( strpos( $error, 'timed out') ) {
					$msg = esc_html__( "Smush request timed out, You can try setting a higher value ( > 60 ) for `WP_SMUSH_API_TIMEOUT`.", "wp-smushit" );
				}
				wp_send_json_error( array( 'stats' => $stats, 'error_msg' => $msg ) );
			} else {
				//Check if a resmush request, update the resmush list
				if( !empty( $_REQUEST['is_bulk_resmush']) && $_REQUEST['is_bulk_resmush'] ) {
					$wpsmushit_admin->update_resmush_list( $atchmnt_id, 'wp-smush-nextgen-resmush-list' );
				}
				wp_send_json_success( array( 'stats' => $stats ) );
			}
		}

	}
}