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
		 * Return unfiltered file path
		 *
		 * @param $attachment_id
		 *
		 * @return bool
		 */
		function get_attached_file( $attachment_id ) {
			if ( empty( $attachment_id ) ) {
				return false;
			}

			$file_path = get_attached_file( $attachment_id );
			if ( ! empty( $file_path ) && strpos( $file_path, 's3' ) !== false ) {
				$file_path = get_attached_file( $attachment_id, true );
			}

			return $file_path;
		}
	}

	global $wpsmush_helper;
	$wpsmush_helper = new WpSmushHelper();

}