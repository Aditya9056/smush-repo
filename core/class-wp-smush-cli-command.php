<?php
/**
 * Class WP_Smush_Cli_Command
 *
 * @since 3.1
 * @package WP_Smush
 */

/**
 * Reduce image file sizes, improve performance and boost your SEO using the WPMU DEV Smush API.
 */
class WP_Smush_Cli_Command extends WP_CLI_Command {

	/**
	 * List unoptimized images.
	 *
	 * ## OPTIONS
	 *
	 * [<count>]
	 * : Limit number of images to get.
	 *
	 * ## EXAMPLES
	 *
	 * # Get all unoptimized images.
	 * $ wp smush list
	 *
	 * # Get the first 100 images that are not optimized.
	 * $ wp smush list 100
	 *
	 * @subcommand list
	 * @when after_wp_load
	 */
	public function _list( $args ) {
		if ( ! empty( $args ) ) {
			list( $count ) = $args;
		} else {
			$count = -1;
		}

		$response = WP_CLI::launch_self( 'post list', array( '--meta_compare=NOT EXISTS' ), array(
			'post_type'      => 'attachment',
			'fields'         => 'ID, guid, post_mime_type',
			'meta_key'       => 'wp-smpro-smush-data',
			'format'         => 'json',
			'posts_per_page' => (int) $count,
		), false, true );

		$images = json_decode( $response->stdout );

		WP_CLI::success( __( 'Unsmushed images:', 'wp-smushit' ) );
		WP_CLI\Utils\format_items( 'table', $images, array( 'ID', 'guid', 'post_mime_type' ) );
	}

	/**
	 * Optimize image.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : Optimize single image, batch or all images.
	 * ---
	 * default: all
	 * options:
	 *   - all
	 *   - single
	 *   - batch
	 * ---
	 *
	 * [--image=<ID>]
	 * : Attachment ID to compress.
	 * ---
	 * default: 0
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 * # Smush single image with ID = 10.
	 * $ wp smush compress single --image=10
	 */
	public function compress( $args, $assoc_args ) {
		$type  = $assoc_args['type'];
		$image = $assoc_args['image'];

		switch ( $type ) {
			case 'single':
				$msg = sprintf( __( 'Smushing image ID: %s', 'wp-smushit' ), absint( $image ) );
				$this->smush( $msg, array( $image ) );
				$this->_list( array() );
				break;
			case 'batch':
				var_dump( 'batch: ' . $image );
				break;
			case 'all':
			default:
				$this->smush_all( __( 'Smushing all images', 'wp-smushit' ) );
				break;
		}
	}

	/**
	 * Smush single image.
	 *
	 * @since 3.1
	 *
	 * @param string $msg     Message for progress bar status.
	 * @param array  $images  Attachment IDs.
	 */
	private function smush( $msg = '', $images = array() ) {
		$progress = \WP_CLI\Utils\make_progress_bar( $msg, count( $images ) + 1 );

		$unsmushed_attachments = WP_Smush::get_instance()->core()->mod->db->get_unsmushed_attachments();

		while ( $images ) {
			$progress->tick();

			$attachment_id = array_pop( $images );

			// Skip if already Smushed.
			if ( ! in_array( $attachment_id, $unsmushed_attachments ) ) {
				continue;
			}

			WP_Smush::get_instance()->core()->mod->smush->smush_single( $attachment_id, true );
		}

		$progress->tick();
		$progress->finish();
	}

	/**
	 * Smush all uncompressed images.
	 *
	 * @since 3.1
	 *
	 * @param string $msg  Message for progress bar status.
	 */
	private function smush_all( $msg ) {
		$attachments = WP_Smush::get_instance()->core()->mod->db->get_unsmushed_attachments();

		$progress = \WP_CLI\Utils\make_progress_bar( $msg, count( $attachments ) );

		foreach ( $attachments as $attachment_id ) {
			WP_Smush::get_instance()->core()->mod->smush->smush_single( $attachment_id, true );
			$progress->tick();
		}

		$progress->finish();
	}

}

WP_CLI::add_command( 'smush', 'WP_Smush_Cli_Command' );
