<?php
/**
 * Handles the optimisation for images hosted on S3
 *
 * @package WP Smush
 * @subpackage Admin
 * @since 2.6.3
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2017, Incsub (http://incsub.com)
 */

if ( ! class_exists( 'WpSmushRemote' ) ) {

	class WpSmushRemote {

		function __construct() {
			add_filter( 'wp_smush_image', array( $this, 'is_hosted_remotely' ), '', 2 );
		}

		/**
		 * Checks if the given file path is hosted on a remote server
		 *
		 * @param $smush
		 * @param $attachment_id
		 *
		 * @return bool
		 */
		function is_hosted_remotely( $smush, $attachment_id ) {
			if ( empty( $attachment_id ) ) {
				return $smush;
			}

			//Get the file path for the image
			$file_path = get_attached_file( $attachment_id );

			//Check if we got the file path or not
			if ( empty( $file_path ) ) {
				return $smush;
			}

			global $WpSmush;

			if ( strpos( $file_path, 's3' ) !== false && class_exists( 'Amazon_S3_And_CloudFront' ) && ! $WpSmush->validate_install() ) {
				add_filter( 'wp_smush_error', array( $this, 'show_s3_error' ) );

				return false;
			}

			return $smush;
		}

		/**
		 * Show message to free version users
		 *
		 * @return string
		 */
		function show_s3_error() {
			$error = esc_html__( "Seems like your files are hosted on Amazon S3, %sUpgrade to pro%s!", "wp-smushit" );

			return $error;
		}

	}

	//Class Object
	global $wpsmush_remote;
	$wpsmush_remote = new WpSmushRemote();
}