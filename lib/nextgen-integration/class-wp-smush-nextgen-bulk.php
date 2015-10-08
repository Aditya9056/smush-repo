<?php
if ( ! class_exists( 'WPSmushNextGenBulk' ) ) {
	class WPSmushNextGenBulk extends WpSmushNextGen {

		function __construct() {
			add_action( 'wp_ajax_wp_smushit_nextgen_bulk', array( $this, 'smush_bulk' ) );
		}

		function smush_bulk() {

			global $WpSmush;

			if ( empty( $_GET['attachment_id'] ) ) {
				wp_send_json_error( 'missing id' );
			}

			$atchmnt_id = sanitize_key( $_GET['attachment_id'] );

			$this->smush_image( $atchmnt_id );

			$stats['smushed'] = $this->smushed_count;
			$stats['total']   = $this->total_count;

			if ( is_wp_error( $smush ) ) {
				wp_send_json_error( $stats );
			} else {
				wp_send_json_success( $stats );
			}
		}

	}
}