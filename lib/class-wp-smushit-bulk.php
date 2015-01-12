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
		 * Fetch all the attachments
		 * @return array $attachments
		 */
		function get_attachments() {
			if ( ! isset( $_REQUEST['ids'] ) ) {
				$attachments = get_posts( array(
					'numberposts'    => - 1,
					'post_type'      => 'attachment',
					'post_mime_type' => 'image',
					'fields'         => 'ids'
				) );
			} else {
				return explode( ',', $_REQUEST['ids'] );
			}

			return $attachments;
		}

	}
}
