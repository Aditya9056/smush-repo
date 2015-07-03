<?php

/**
 * Handles all the stats related functions
 *
 * @package WP Smush
 * @subpackage NextGen Gallery
 * @version 1.0
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2015, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmushNextGenStats' ) ) {

	class WpSmushNextGenStats extends WpSmushNextGen {

		/**
		 * @var array Contains the total Stats, for displaying it on bulk page
		 */
		var $stats = array();

		function __construct() {

			//Update Total Image count
			add_action( 'ngg_added_new_image', array( $this, 'image_count' ), 10 );

			//Update smushed images list
			add_action( 'wp_smush_completed_nextgen', array( $this, 'update_smushed_count' ) );

			//Get the stats for single image, update the global stats
			add_action( 'wp_smush_nextgen_image_stats', array( $this, 'update_stats' ) );
		}

		/**
		 * Refreshes the total image count when a new image is added to nextgen gallery
		 *
		 */
		function image_count() {
			// Force the cache refresh for top-commented posts.
			$this->total_count( $force_refresh = true );
		}

		/**
		 * Get the images id for nextgen gallery
		 *
		 * @param bool $force_refresh Optional. Whether to force the cache to be refreshed.
		 * Default false.
		 *
		 * @return int|WP_Error Total Image count,
		 * WP_Error object otherwise.
		 */
		function total_count( $force_refresh = false, $return_ids = false ) {
			// Check for the  wp_smush_images in the 'nextgen' group.
			$attachment_ids = wp_cache_get( 'wp_smush_images', 'nextgen' );

			// If nothing is found, build the object.
			if ( true === $force_refresh || false === $attachment_ids ) {
				//Get the nextgen image ids
				$attachment_ids = $this->get_nextgen_attachments();

				if ( ! is_wp_error( $attachment_ids ) ) {
					// In this case we don't need a timed cache expiration.
					wp_cache_set( 'wp_smush_images', $attachment_ids, 'nextgen' );
				}
			}

			return $return_ids ? $attachment_ids : count( $attachment_ids );
		}

		/**
		 * Stores the smushed image ids for NextGen Gallery
		 */
		function update_smushed_count( $image_id ) {

			// Check for the  wp_smush_images in the 'nextgen' group.
			$smushed_images = get_option( 'wp_smush_images_smushed', array() );


			if ( is_array( $smushed_images ) && ! in_array( $image_id, $smushed_images ) ) {
				$smushed_images[] = $image_id;
			}
			$smushed_images = array( $image_id );

			update_option( 'wp_smush_images_smushed', $smushed_images );

		}

		/**
		 * Returns the smushed images list or count
		 *
		 * @param bool $return_ids Whether to return the ids array, set to false by default
		 *
		 * @return int|mixed|void Returns the images ids or the count
		 */
		function get_smushed_images_count( $return_ids = false ) {
			// Check for the  wp_smush_images in the 'nextgen' group.
			$smushed_images = get_option( 'wp_smush_images_smushed', array() );

			return $return_ids ? $smushed_images : count( $smushed_images );
		}

		/**
		 * Display the smush stats for the image
		 *
		 * @param $pid Image Id stored in nextgen table
		 * @param bool $wp_smush_data Stats, stored after smushing the image
		 * @param string $image_type Used for determining if not gif, to show the Super Smush button
		 * @param bool $text_only Return only text instead of button (Useful for Ajax)
		 * @param bool $echo Whether to echo the stats or not
		 *
		 * @return bool|null|string|void
		 */
		function show_stats( $pid, $wp_smush_data = false, $image_type = '', $text_only = false, $echo = true ) {
			global $WpSmush;
			if ( empty( $wp_smush_data ) ) {
				return false;
			}
			$button_txt  = '';
			$show_button = false;

			$bytes          = isset( $wp_smush_data['stats']['bytes'] ) ? $wp_smush_data['stats']['bytes'] : 0;
			$bytes_readable = ! empty( $bytes ) ? $WpSmush->format_bytes( $bytes ) : '';
			$percent        = isset( $wp_smush_data['stats']['percent'] ) ? $wp_smush_data['stats']['percent'] : 0;
			$percent        = $percent < 0 ? 0 : $percent;

			if ( isset( $wp_smush_data['stats']['size_before'] ) && $wp_smush_data['stats']['size_before'] == 0 ) {
				$status_txt  = __( 'Error processing request', WP_SMUSH_DOMAIN );
				$show_button = true;
			} else {
				if ( $bytes == 0 || $percent == 0 ) {
					$status_txt = __( 'Already Optimized', WP_SMUSH_DOMAIN );
				} elseif ( ! empty( $percent ) && ! empty( $bytes_readable ) ) {
					$status_txt = sprintf( __( "Reduced by %s (  %01.1f%% )", WP_SMUSH_DOMAIN ), $bytes_readable, number_format_i18n( $percent, 2, '.', '' ) );
				}
			}

			//IF current compression is lossy
			if ( ! empty( $wp_smush_data ) && ! empty( $wp_smush_data['stats'] ) ) {
				$lossy    = ! empty( $wp_smush_data['stats']['lossy'] ) ? $wp_smush_data['stats']['lossy'] : '';
				$is_lossy = $lossy == 1 ? true : false;
			}

			//Check if Lossy enabled
			$opt_lossy     = WP_SMUSH_PREFIX . 'lossy';
			$opt_lossy_val = get_option( $opt_lossy, false );

			//Check if premium user, compression was lossless, and lossy compression is enabled
			if ( $WpSmush->is_pro() && ! $is_lossy && $opt_lossy_val && $image_type != 'image/gif' && ! empty( $image_type ) ) {
				// the button text
				$button_txt  = __( 'Super-Smush', WP_SMUSH_DOMAIN );
				$show_button = true;
			}
			if ( $text_only ) {
				return $status_txt;
			}

			//If show button is true for some reason, column html can print out the button for us
			$text = $this->column_html( $pid, $status_txt, $button_txt, $show_button, true, $echo );
			if ( ! $echo ) {
				return $text;
			}
		}

		/**
		 * Returns number of images of larger than 1Mb size
		 *
		 * @return int
		 */
		function get_exceeding_items_count() {
			$count       = 0;
			$bulk        = new WpSmushitBulk();
			$attachments = $bulk->get_attachments();
			//Check images bigger than 1Mb, used to display the count of images that can't be smushed
			foreach ( $attachments as $attachment ) {
				if ( file_exists( get_attached_file( $attachment ) ) ) {
					$size = filesize( get_attached_file( $attachment ) );
				}
				if ( empty( $size ) || ! ( ( $size / WP_SMUSH_MAX_BYTES ) > 1 ) ) {
					continue;
				}
				$count ++;
			}

			return $count;
		}

		/**
		 * Updated the global smush stats for NextGen gallery
		 *
		 * @param $stats Compression stats fo respective image
		 *
		 */
		function update_stats( $stats ) {

			$stats = $stats['stats'];

			$smush_stats = get_option( 'wp_smush_stats_nextgen', array() );

			if ( ! empty( $stats['stats'] ) && ! empty( $stats ) ) {
				//Compression Percentage
				$smush_stats['percent'] = ! empty( $smush_stats['percent'] ) ? ( $smush_stats['percent'] + $stats['percent'] ) : $stats['percent'];

				//Compression Bytes
				$smush_stats['bytes'] = ! empty( $smush_stats['bytes'] ) ? ( $smush_stats['bytes'] + $stats['bytes'] ) : $stats['bytes'];

				//Size of images before the compression
				$smush_stats['size_before'] = ! empty( $smush_stats['size_before'] ) ? ( $smush_stats['size_before'] + $stats['size_before'] ) : $stats['size_before'];

				//Size of image after compression
				$smush_stats['size_after'] = ! empty( $smush_stats['size_after'] ) ? ( $smush_stats['size_after'] + $stats['size_after'] ) : $stats['size_after'];
			}
			update_option( 'wp_smush_stats_nextgen', $smush_stats );
		}

	}//End of Class

}//End Of if class not exists