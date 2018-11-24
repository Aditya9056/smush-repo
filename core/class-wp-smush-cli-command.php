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
	 * # Smush all images.
	 * $ wp smush compress
	 *
	 * # Smush single image with ID = 10.
	 * $ wp smush compress --type=single --image=10
	 *
	 * # Smush first 5 images.
	 * $ wp smush compress --type=batch --image=5
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
				$msg = sprintf( __( 'Smushing first %d images', 'wp-smushit' ), absint( $image ) );
				$this->smush_all( $msg, $image );
				break;
			case 'all':
			default:
				$this->smush_all( __( 'Smushing all images', 'wp-smushit' ) );
				break;
		}
	}

	/**
	 * Restore image.
	 *
	 * ## OPTIONS
	 *
	 * [--id=<ID>]
	 * : Attachment ID to restore.
	 * ---
	 * default: all
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 * # Restore all images that have backups.
	 * $ wp smush restore
	 *
	 * # Restore single image with ID = 10.
	 * $ wp smush restore --id=10
	 */
	public function restore( $args, $assoc_args ) {
		$id = $assoc_args['id'];

		if ( 'all' === $id ) {
			$this->restore_all();
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
		WP_CLI::success( __( 'Image compressed', 'wp-smushit' ) );
	}

	/**
	 * Smush all uncompressed images.
	 *
	 * @since 3.1
	 *
	 * @param string $msg    Message for progress bar status.
	 * @param int    $batch  Compress only this number of images.
	 */
	private function smush_all( $msg, $batch = 0 ) {
		$attachments = WP_Smush::get_instance()->core()->mod->db->get_unsmushed_attachments();

		if ( $batch > 0 ) {
			$attachments = array_slice( $attachments, 0, $batch );
		}

		$progress = \WP_CLI\Utils\make_progress_bar( $msg, count( $attachments ) );

		foreach ( $attachments as $attachment_id ) {
			WP_Smush::get_instance()->core()->mod->smush->smush_single( $attachment_id, true );
			$progress->tick();
		}

		$progress->finish();
		WP_CLI::success( __( 'All images compressed', 'wp-smushit' ) );
	}

	/**
	 * Restore all images.
	 *
	 * @since 3.1
	 */
	private function restore_all() {
		$core = WP_Smush::get_instance()->core();

		$attachments = ! empty( $core->smushed_attachments ) ? $core->smushed_attachments : $core->mod->db->smushed_count( true );

		if ( empty( $attachments ) ) {
			WP_CLI::success( __( 'No images available to restore', 'wp-smushit' ) );
			return;
		}

		$progress = \WP_CLI\Utils\make_progress_bar( __( 'Restoring images', 'wp-smushit' ), count( $attachments ) );

		foreach ( $attachments as $attachment_id ) {
			$core->mod->backup->restore_image( $attachment_id, false );
			$progress->tick();
		}

		$progress->finish();
		WP_CLI::success( __( 'All images restored', 'wp-smushit' ) );
	}

}

WP_CLI::add_command( 'smush', 'WP_Smush_Cli_Command' );
