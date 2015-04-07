<?php

/**
 * @package SmushItPro
 * @subpackage Admin
 * @version 1.0
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2014, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmProCount' ) ) {

	/**
	 * Methods for bulk processing
	 */
	class WpSmProCount {

		public $counts = array(
			'total'   => 0,
			'sent'    => 0,
			'smushed' => 0
		);

		function init() {
			global $log;
			$this->counts = array(
				'total'   => (int) $this->total_count(),
				'sent'    => (int) $this->sent_count(),
				'smushed' => (int) $this->smushed_count(),
			);

			//If there is any problem with sent counts, check sent ids and update if image doesn't exists
			if ( $this->counts['sent'] > $this->counts['total'] ) {
				$sent_ids = get_option( WP_SMPRO_PREFIX . 'sent-ids', array() );
				foreach ( $sent_ids as $attachment_id ) {
					if ( ! get_post_status( $attachment_id ) ) {
						$pos = array_search( $attachment_id, $sent_ids );
						unset( $sent_ids[ $pos ] );
					}
				}
				//Update the sent ids
				update_option( WP_SMPRO_PREFIX . 'sent-ids', $sent_ids );
				$this->counts['sent'] = $this->sent_count();
			}

			//If there is any problem with Smushed counts, check posts for existence
			if ( $this->counts['smushed'] > $this->counts['total'] ) {
				$smushed = $this->smushed_count( true );
				if ( empty( $smushed ) ) {
					return;
				}
				foreach ( $smushed as $attachment_id ) {
					if ( ! get_post_status( $attachment_id ) ) {
						delete_post_meta( $attachment_id, 'wp-smpro-is-smushed' );
					}
				}
				$this->counts['smushed'] = $this->smushed_count();
			}

			//If sent counts is lesser than smushed count, return smushed count,
			//this might be due to manually removing smush data
			if ( $this->counts['sent'] < $this->counts['smushed'] ) {
				$this->counts['sent'] = $this->smushed_count();
				$log->notice( 'WpSmproCount -> init: Sent count are lesser than fetch count.' );
			}
		}

		function sent_count() {
			$sent_ids = get_option( WP_SMPRO_PREFIX . 'sent-ids', array() );

			return count( $sent_ids );
		}

		function total_count() {
			$query   = array(
				'fields'         => 'ids',
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'post_mime_type' => array( 'image/jpeg', 'image/gif', 'image/png' ),
				'order'          => 'ASC',
				'posts_per_page' => - 1
			);
			$results = new WP_Query( $query );
			$count   = ! empty( $results->post_count ) ? $results->post_count : 0;

			// send the count
			return $count;
		}

		function smushed_count( $return_ids = false ) {
			$query = array(
				'fields'         => 'ids',
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'post_mime_type' => array( 'image/jpeg', 'image/gif', 'image/png' ),
				'order'          => 'ASC',
				'posts_per_page' => - 1,
				'meta_key'       => "wp-smpro-is-smushed"
			);

			$results = new WP_Query( $query );
			if ( ! $return_ids ) {
				$count = ! empty( $results->post_count ) ? $results->post_count : 0;
			} else {
				return $results->posts;
			}

			// send the count
			return $count;
		}

	}

}
