<?php
/**
 * Smush integration with various plugins: WP_Smush_Common class
 *
 * @package WP_Smush
 * @subpackage Admin
 * @since 2.8.0
 *
 * @author Anton Vanyukov <anton@incsub.com>
 *
 * @copyright (c) 2018, Incsub (http://incsub.com)
 */

/**
 * Singleton class WP_Smush_Common.
 *
 * @since 2.8.0
 */
class WP_Smush_Common {
	/**
	 * Class instance variable.
	 *
	 * @since 2.8.0
	 * @var null|WP_Smush_Common
	 */
	private static $_instance = null;

	/**
	 * WP_Smush_Common constructor.
	 */
	private function __construct() {
		/*
		add_filter( 'wp_smush_media_image', false, 'full' );
		add_filter( 'wp_smush_media_image', false, 'medium' );
		add_filter( 'wp_smush_media_image', false, 'thumb' );
		add_filter( 'wp_smush_media_image', false, 'thumbnail' );
		*/

		// Actions that run during AJAX requests.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			// AJAX Thumbnail Rebuild integration.
			add_action( 'admin_init', array( $this, 'disable_smush' ), 5 );
		}
	}

	/**
	 * Get class instance.
	 *
	 * @since 2.8.0
	 *
	 * @return null|WP_Smush_Common
	 */
	public static function get_instance() {
		if ( null !== self::$_instance ) {
			return self::$_instance;
		}

		return new self;
	}

	/**
	 * AJAX Thumbnail Rebuild integration.
	 *
	 * If this is a thumbnail regeneration - only continue for selected thumbs
	 * (no need to regenerate everything else).
	 *
	 * @since 2.8.0
	 */
	public function disable_smush() {
		// Check if this is the call from AJAX Thumbnail Rebuild plugin.
		if ( ! isset( $_POST['action'] ) || 'ajax_thumbnail_rebuild' !== $_POST['action'] || ! isset( $_POST['thumbnails'] ) ) { // Input var ok.
			return;
		}

		$image_sizes = get_intermediate_image_sizes();
		array_push( $image_sizes, 'full' );

		foreach ( $image_sizes as $size ) {
			if ( in_array( $size, wp_unslash( $_POST['thumbnails'] ), true ) ) { // Input var ok.
				continue;
			}

			add_filter( 'wp_smush_media_image', false, $size );
		}
	}

}
