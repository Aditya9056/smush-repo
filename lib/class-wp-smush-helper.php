<?php
/**
 * @package WP Smush
 * @subpackage Admin
 * @version 1.0
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2017, Incsub (http://incsub.com)
 */


if ( ! class_exists( 'WpSmushHelper' ) ) {

	class WpSmushHelper {

		function __construct() {
			$this->init();
		}

		function init() {


		}


		/**
		 * Check if file is on S3 and download the file
		 *
		 * @param $attachment_id
		 *
		 * @return bool
		 */
		function maybe_get_remote_file( $attachment_id ) {

			global $wpsmush_s3;
			//See if we can get it using S3
			$file = $wpsmush_s3->download_file( $attachment_id );
			if( $file ) {
				return $file;
			}
		}
	}

	global $wpsmush_helper;
	$wpsmush_helper = new WpSmushHelper();

}