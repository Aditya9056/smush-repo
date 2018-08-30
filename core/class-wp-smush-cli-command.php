<?php
/**
 * Class WP_Smush_Cli_Command
 *
 * @since 3.0
 * @package WP_Smush
 */

/**
 * Reduce image file sizes, improve performance and boost your SEO using the WPMU DEV Smush API.
 */
class WP_Smush_Cli_Command extends WP_CLI_Command {

	/**
	 * Prints a greeting.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : The name of the person to greet.
	 *
	 * [--type=<type>]
	 * : Whether or not to greet the person with success or error.
	 * ---
	 * default: success
	 * options:
	 *   - success
	 *   - error
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp example hello Newman
	 *
	 * @when after_wp_load
	 */
	public function hello( $args, $assoc_args ) {
		list( $name ) = $args;

		// Print the message with type.
		$type = $assoc_args['type'];
		WP_CLI::$type( "Hello, $name!" );
	}

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
		list( $count ) = $args;

		$response = WP_CLI::launch_self( 'post list', array( '--meta_compare=NOT EXISTS' ), array(
			'post_type'      => 'attachment',
			'fields'         => 'ID, guid, post_mime_type',
			'meta_key'       => 'wp-smpro-smush-data',
			'format'         => 'json',
			'posts_per_page' => (int) $count,
		), false, true );

		$images = json_decode( $response->stdout );

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
				break;
			case 'batch':
				break;
			case 'all':
			default:
				break;
		}
	}

}

WP_CLI::add_command( 'smush', 'WP_Smush_Cli_Command' );
