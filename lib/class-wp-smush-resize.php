<?php
/**
 * @package WP Smush
 * @subpackage Admin
 * @version 2.3
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2016, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmushResize' ) ) {

	/**
	 * Class WpSmushResize
	 */
	class WpSmushResize {

		/**
		 * @var int Specified width for resizing images
		 *
		 */
		private $max_w = 0;

		/**
		 * @var int Specified Height for resizing images
		 *
		 */
		private $max_h = 0;

		/**
		 * @var bool If resizing is enabled or not
		 */
		private $resize_enabled = false;

		function __construct() {
			/**
			 * Initialize class variables, after all stuff has been loaded
			 */
			add_action( 'wp_loaded', array( $this, 'initialize' ) );

			/**
			 * Resize images For the Latest uploads, Hook at the earliest
			 */
			add_filter( 'wp_handle_upload', array( $this, 'auto_resize' ), 10, 2 );

		}

		/**
		 * Get the settings for resizing
		 */
		function initialize() {
			//If resizing is enabled
			$this->resize_enabled = get_option( WP_SMUSH_PREFIX . 'resize' );

			$resize_sizes = get_option( WP_SMUSH_PREFIX . 'resize_sizes', array() );

			//Resize width and Height
			$this->max_w = ! empty( $resize_sizes['width'] ) ? $resize_sizes['width'] : 0;
			$this->max_h = ! empty( $resize_sizes['height'] ) ? $resize_sizes['height'] : 0;
		}

		/**
		 * Check whether Image should be resized or not
		 *
		 * @param string $params
		 * @param string $action
		 *
		 * @return bool
		 */
		private function should_resize( $params = '', $action = '' ) {

			//If resizing not enabled, or if both max width and height is set to 0, return
			if ( ! $this->resize_enabled || ( $this->max_w == 0 && $this->max_h == 0 ) ) {
				return false;
			}

			//If action is specified, and it's a sideload
			if ( ! empty( $action ) && 'sideload' == $action ) {
				return false;
			}

			if ( ! empty( $params ) && is_array( $params ) ) {

				global $wpsmushit_admin;

				if ( ! empty( $params['file'] ) ) {

					// Skip: if "noresize" is included in the filename, Thanks to Imsanity
					if ( strpos( $params['file'], 'noresize' ) !== false ) {
						return false;
					}

					//If file doesn't exists, return
					if ( ! file_exists( $params['file'] ) ) {
						return false;
					}

				}

				//If type of upload doesn't matches the criteria return
				if ( ! empty( $params['type'] ) && ! in_array( $params['type'], $wpsmushit_admin->mime_types ) ) {
					return false;
				}

			}

			return true;
		}

		/**
		 * Handles the Auto resizing of new uploaded images
		 *
		 * @param array $upload
		 * @param string $action
		 *
		 * @return array $upload
		 */
		function auto_resize( $upload, $action ) {

			if ( empty( $upload['file'] ) || empty( $upload['type'] ) ) {
				return $upload;
			}

			//Check if the image should be resized or not
			if ( ! $this->should_resize( $upload, $action ) ) {
				return $upload;
			}

			//Good to go
			$file_path = $upload['file'];

			$resize = $this->perform_resize( $file_path );

			//If resize wasn't successful
			if ( ! $resize ) {
				return $upload;
			}

			//Else Replace the Original file with resized file
			$this->replcae_original_image( $upload, $resize );

			return $upload;

		}

		/**
		 * Generates the new image for specified width and height,
		 * Checks if the size of generated image is greater,
		 *
		 * @param $file_path Original File path
		 *
		 * @return bool, If the image generation was succesfull
		 */
		function perform_resize( $file_path ) {
			$data = image_make_intermediate_size( $file_path, $this->max_w, $this->max_h );

			//If the image wasn't resized
			if ( empty( $data['file'] ) || is_wp_error( $data ) ) {
				return false;
			}

			//Check if file size is lesser than original image
			$resize_path = path_join( dirname( $file_path ), $data['file'] );
			if ( ! file_exists( $resize_path ) ) {
				return false;
			}

			$data['file_path'] = $resize_path;

			$original_file_size = filesize( $file_path );
			$file_size          = filesize( $resize_path );
			if ( $file_size > $original_file_size ) {
				@unlink( $resize_path );

				return false;
			}

			return $data;
		}

		/**
		 * Replace the original file with resized file
		 * @param $upload
		 * @param $resized
		 */
		function replcae_original_image( $upload, $resized ) {
			@copy( $resized['file_path'], $upload['file'] );
			unlink( $resized['file_path'] );
		}
	}

	/**
	 * Initialise class
	 */
	global $wpsmush_resize;
	$wpsmush_resize = new WpSmushResize();
}