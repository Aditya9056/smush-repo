<?php

/**
 * @package WP SmushIt
 * @subpackage Admin
 * @version 1.0
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2014, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmushitBulk' ) ) {

	/**
	 * Methods for bulk processing
	 */
	class WpSmushitBulk {

		/**
		 * Fetch all the unsmushed attachments
		 * @return array $attachments
		 */
		function get_attachments() {
			if ( ! isset( $_REQUEST['ids'] ) ) {
				$args            = array(
					'fields'         => 'ids',
					'post_type'      => 'attachment',
					'post_status'    => 'any',
					'post_mime_type' => array( 'image/jpeg', 'image/gif', 'image/png' ),
					'orderby'        => 'ID',
					'order'          => 'DESC',
					'posts_per_page' => - 1,
					'meta_query'     => array(
						array(
							'key'     => 'wp-smpro-smush-data',
							'compare' => 'NOT EXISTS'
						)
					)
				);
				$query           = new WP_Query( $args );
				$unsmushed_posts = $query->posts;
			} else {
				return explode( ',', $_REQUEST['ids'] );
			}

			return $unsmushed_posts;
		}

	}
}
