<?php
/**
 * @package WP Smush
 * @subpackage Admin
 * @since 2.5
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2016, Incsub (http://incsub.com)
 */
require_once 'wp-async-task.php';

if ( ! class_exists( 'WpSmushAsync' ) ) {

	class WpSmushAsync extends WP_Async_Task {

		/**
		 * Whenever a attachment metadata is updated
		 *
		 * @var string
		 */
		protected $action = 'wp_generate_attachment_metadata';

		/**
		 * Prepare data for the asynchronous request
		 *
		 * @throws Exception If for any reason the request should not happen
		 *
		 * @param array $data An array of data sent to the hook
		 *
		 * @return array
		 */
		protected function prepare_data( $data ) {
			//We don't have the data, bail out
			if ( empty( $data ) ) {
				return $data;
			}

			//Return a associative array
			$image_meta             = array();
			$image_meta['metadata'] = ! empty( $data[0] ) ? $data[0] : '';
			$image_meta['id']       = ! empty( $data[1] ) ? $data[1] : '';

			return $image_meta;
		}

		/**
		 * Run the async task action
		 * @todo: Add a check for image
		 * @todo: See if auto smush is enabled or not
		 * @todo: Check if async is enabled or not
		 */
		protected function run_action() {

			$metadata = ! empty( $_POST['metadata'] ) ? $_POST['metadata'] : '';
			$id       = ! empty( $_POST['id'] ) ? $_POST['id'] : '';

			//Get metadata from $_POST
			if ( ! empty( $metadata ) ) {
				// Allow the Asynchronous task to run
				do_action( "wp_async_$this->action", $id );
			}
		}

	}
}