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
	 * WP_Smush_Common constructor.
	 */
	public function __construct() {
		// AJAX Thumbnail Rebuild integration.
		add_filter( 'wp_smush_media_image', array( $this, 'skip_images' ), 10, 2 );

		// Optimise WP retina 2x images.
		add_action( 'wr2x_retina_file_added', array( $this, 'smush_retina_image' ), 20, 3 );
	}

	/**
	 * AJAX Thumbnail Rebuild integration.
	 *
	 * If this is a thumbnail regeneration - only continue for selected thumbs
	 * (no need to regenerate everything else).
	 *
	 * @since 2.8.0
	 *
	 * @param string $smush_image  Image size.
	 * @param string $size_key     Thumbnail size.
	 *
	 * @return bool
	 */
	public function skip_images( $smush_image, $size_key ) {
		if ( empty( $_POST['regen'] ) || ! is_array( $_POST['regen'] ) ) { // Input var ok.
			return $smush_image;
		}

		$smush_sizes = wp_unslash( $_POST['regen'] ); // Input var ok.

		if ( in_array( $size_key, $smush_sizes, true ) ) {
			return $smush_image;
		}

		// Do not regenerate other thumbnails for regenerate action.
		return false;
	}

	/**
	 * Smush Retina images for WP Retina 2x, Update Stats.
	 *
	 * @param int    $id           Attachment ID.
	 * @param string $retina_file  Retina image.
	 * @param string $image_size   Image size.
	 */
	public function smush_retina_image( $id, $retina_file, $image_size ) {
		$smush = WP_Smush::get_instance()->core()->mod->smush;

		// Initialize attachment id and media type.
		$smush->attachment_id = $id;
		$smush->media_type    = 'wp';

		/**
		 * Allows to Enable/Disable WP Retina 2x Integration
		 */
		$smush_retina_images = apply_filters( 'smush_retina_images', true );

		// Check if Smush retina images is enabled.
		if ( ! $smush_retina_images ) {
			return;
		}
		// Check for Empty fields.
		if ( empty( $id ) || empty( $retina_file ) || empty( $image_size ) ) {
			return;
		}

		// Do not smush if auto smush is turned off.
		if ( ! $smush->is_auto_smush_enabled() ) {
			return;
		}

		/**
		 * Allows to skip a image from smushing
		 *
		 * @param bool , Smush image or not
		 * @$size string, Size of image being smushed
		 */
		$smush_image = apply_filters( 'wp_smush_media_image', true, $image_size );
		if ( ! $smush_image ) {
			return;
		}

		$stats = $smush->do_smushit( $retina_file );
		// If we squeezed out something, Update stats.
		if ( ! is_wp_error( $stats ) && ! empty( $stats['data'] ) && isset( $stats['data'] ) && $stats['data']->bytes_saved > 0 ) {
			$image_size = $image_size . '@2x';

			$this->update_smush_stats_single( $id, $stats, $image_size );
		}
	}

	/**
	 * Updates the smush stats for a single image size.
	 *
	 * @param int    $id           Attachment ID.
	 * @param array  $smush_stats  Smush stats.
	 * @param string $image_size   Image size.
	 *
	 * @return bool
	 */
	private function update_smush_stats_single( $id, $smush_stats, $image_size = '' ) {
		// Return, if we don't have image id or stats for it.
		if ( empty( $id ) || empty( $smush_stats ) || empty( $image_size ) ) {
			return false;
		}

		$smush = WP_Smush::get_instance()->core()->mod->smush;
		$data  = $smush_stats['data'];
		// Get existing Stats.
		$stats = get_post_meta( $id, WP_Smushit::$smushed_meta_key, true );

		// Update existing Stats.
		if ( ! empty( $stats ) ) {
			// Update stats for each size.
			if ( isset( $stats['sizes'] ) ) {
				// if stats for a particular size doesn't exists.
				if ( empty( $stats['sizes'][ $image_size ] ) ) {
					// Update size wise details.
					$stats['sizes'][ $image_size ] = (object) $smush->_array_fill_placeholders( $smush->_get_size_signature(), (array) $data );
				} else {
					// Update compression percent and bytes saved for each size.
					$stats['sizes'][ $image_size ]->bytes   = $stats['sizes'][ $image_size ]->bytes + $data->bytes_saved;
					$stats['sizes'][ $image_size ]->percent = $stats['sizes'][ $image_size ]->percent + $data->compression;
				}
			}
		} else {
			// Create new stats.
			$stats = array(
				'stats' => array_merge(
					$smush->_get_size_signature(), array(
						'api_version' => - 1,
						'lossy'       => - 1,
					)
				),
				'sizes' => array(),
			);

			$stats['stats']['api_version'] = $data->api_version;
			$stats['stats']['lossy']       = $data->lossy;
			$stats['stats']['keep_exif']   = ! empty( $data->keep_exif ) ? $data->keep_exif : 0;

			// Update size wise details.
			$stats['sizes'][ $image_size ] = (object) $smush->_array_fill_placeholders( $smush->_get_size_signature(), (array) $data );
		}

		// Calculate the total compression.
		$stats = $smush->total_compression( $stats );

		update_post_meta( $id, WP_Smushit::$smushed_meta_key, $stats );
	}

}
