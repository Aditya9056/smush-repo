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
if ( ! class_exists( 'WpSmProBulk' ) ) {

	/**
	 * Methods for bulk processing
	 */
	class WpSmProBulk {
		/**
		 * The images that still need to be smushed
         *
		 * @param string $type The type of count needed (sent, received, smushed)
         * @param string $include The type of count (all, done, left)
		 * @return int count of images
		 */
		function image_count( $type = 'sent', $include = 'all' ) {

			$query = array(
				'fields'         => 'ids',
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'post_mime_type' => array( 'image/jpeg', 'image/gif', 'image/png' ),
				'order'          => 'ASC',
				'posts_per_page' => - 1
			);

			$metakey = "wp-smpro-is-$type";

			if ( $include != 'all' ) {
				$meta_query          = array(
					'relation' => 'OR',
					array(
						'key'     => $metakey,
						'compare' => 'NOT EXISTS'
					),
					array(
						'key'   => $metakey,
						'value' => 0
					)
				);
				$query['meta_query'] = $meta_query;
			}

			$results = new WP_Query( $query );
			$count   = ! empty ( $results->post_count ) ? $results->post_count : '';

			// send the count
			return $count;
		}

		/**
		 * The first id to start from
		 *
		 * @return int Attachmment id to start bulk smushing from
		 */
		function start_id() {
			$query = array(
				'fields'         => 'ids',
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'post_mime_type' => array( 'image/jpeg', 'image/gif', 'image/png' ),
				'order'          => 'ASC',
				'posts_per_page' => 1,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => 'wp-smpro-is-sent',
						'compare' => 'NOT EXISTS'
					),
					array(
						'key'   => 'wp-smpro-is-sent',
						'value' => 0
					)
				)

			);

			$results = new WP_Query( $query );
			$id      = ! empty ( $results->posts ) ? $results->posts[0] : '';

			return $id;
		}

		/**
		 * Return counts needed for the bulk ui
                *
                * @param string $type the type of data- sent, received or smushed
                * @return array the counts
		 */

		function data( $type = 'sent' ) {

			$data = array();

			// set up counts and start_id
			$data['total']    = (int) $this->image_count( $type, 'all' );
			$data['left']     = (int) $this->image_count( $type, 'left' );
			$data['done']     = $data['total'] - $data['left'];
                        
                        // include start id if asked for sent data
                        if('sent'===$type){
                                $data['start_id'] = $this->start_id();
                        }

			return $data;
		}
                
	}
}
