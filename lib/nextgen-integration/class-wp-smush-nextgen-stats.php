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

			//Update images list in cache
			add_action( 'wp_smush_nextgen_image_stats', array( $this, 'update_cache' ) );

			//Get the stats for single image, update the global stats
			add_action( 'wp_smush_nextgen_image_stats', array( $this, 'update_stats' ), '', 2 );
		}

		/**
		 * Refreshes the total image count when a new image is added to nextgen gallery
		 * Should be called only if image count need to be updated, use total_count(), otherwise
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
		 *
		 *
		 * @param bool $return_ids Whether to return the ids array, set to false by default
		 *
		 * @return int|mixed|void Returns the images ids or the count
		 */
		/**
		 * Returns the ngg images list(id and meta ) or count
		 *
		 * @param string $type Whether to return smushed images or unsmushed images
		 * @param bool|false $count Return count only
		 * @param bool|false $force_update true/false to update the cache or not
		 *
		 * @return bool|mixed Returns assoc array of image ids and meta or Image count
		 */
		function get_ngg_images( $type = 'smushed', $count = false, $force_update = false ) {

			global $wpdb;

			//Check type of images being queried
			if ( ! in_array( $type, array( 'smushed', 'unsmushed' ) ) ) {
				return false;
			}

			// Check for the  wp_smush_images_smushed in the 'nextgen' group.
			$images = wp_cache_get( 'wp_smush_images_' . $type, 'nextgen' );

			// If nothing is found, build the object.
			if ( !$images || $force_update ) {
				// Query Attachments for meta key
				$attachments = $wpdb->get_results( "SELECT pid, meta_data FROM $wpdb->nggpictures" );
				foreach ( $attachments as $attachment ) {
					//Check if it has `wp_smush` key
					if ( class_exists( 'Ngg_Serializable' ) ) {
						$serializer = new Ngg_Serializable();
						$meta       = $serializer->unserialize( $attachment->meta_data );
					} else {
						$meta = unserialize( $attachment->meta_data );
					}
					//Check meta for wp_smush
					if ( ! is_array( $meta ) || empty( $meta['wp_smush'] ) ) {
						$unsmushed_images[ $attachment->pid ] = $meta;
						continue;
					}
					$smushed_images[ $attachment->pid ] = $meta;
				}
				// In this case we don't need a timed cache expiration.
				wp_cache_set( 'wp_smush_images_unsmushed', $unsmushed_images, 'nextgen' );
				wp_cache_set( 'wp_smush_images_smushed', $smushed_images, 'nextgen' );
			}

			if ( $type == 'smushed' ) {
				$smushed_images = ! empty( $smushed_images ) ? $smushed_images : $images;

				return $count ? count( $smushed_images ) : $smushed_images;
			} else {

				$unsmushed_images = ! empty( $unsmushed_images ) ? $unsmushed_images : $images;
				return $count ? count( $unsmushed_images ) : $unsmushed_images;
			}
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
			global $WpSmush, $wpsmushnextgenadmin;
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
				$status_txt  = __( 'Error processing request', 'wp-smushit' );
				$show_button = true;
			} else {
				if ( $bytes == 0 || $percent == 0 ) {
					$status_txt = __( 'Already Optimized', 'wp-smushit' );
				} elseif ( ! empty( $percent ) && ! empty( $bytes_readable ) ) {
					$status_txt = sprintf( __( "Reduced by %s (  %01.1f%% )", 'wp-smushit' ), $bytes_readable, number_format_i18n( $percent, 2, '.', '' ) );
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
				$button_txt  = __( 'Super-Smush', 'wp-smushit' );
				$show_button = true;
			}
			if ( $text_only ) {
				return $status_txt;
			}

			//If show button is true for some reason, column html can print out the button for us
			$text = $wpsmushnextgenadmin->column_html( $pid, $status_txt, $button_txt, $show_button, true, $echo );
			if ( ! $echo ) {
				return $text;
			}
		}

		/**
		 * Updated the global smush stats for NextGen gallery
		 *
		 * @param $stats Compression stats fo respective image
		 *
		 */
		function update_stats( $image_id, $stats ) {
			global $WpSmush;

			$stats = ! empty( $stats['stats'] ) ? $stats['stats'] : '';

			$smush_stats = get_option( 'wp_smush_stats_nextgen', array() );

			if ( ! empty( $stats ) ) {
				//Compression Percentage
				$smush_stats['percent'] = ! empty( $smush_stats['percent'] ) ? ( $smush_stats['percent'] + $stats['percent'] ) : $stats['percent'];

				//Compression Bytes
				$smush_stats['bytes'] = ! empty( $smush_stats['bytes'] ) ? ( $smush_stats['bytes'] + $stats['bytes'] ) : $stats['bytes'];

				//Human Readable
				$smush_stats['human'] = !empty( $smush_stats['bytes'] ) ? $WpSmush->format_bytes( $smush_stats['bytes'] )  : '';

				//Size of images before the compression
				$smush_stats['size_before'] = ! empty( $smush_stats['size_before'] ) ? ( $smush_stats['size_before'] + $stats['size_before'] ) : $stats['size_before'];

				//Size of image after compression
				$smush_stats['size_after'] = ! empty( $smush_stats['size_after'] ) ? ( $smush_stats['size_after'] + $stats['size_after'] ) : $stats['size_after'];
			}
			update_option( 'wp_smush_stats_nextgen', $smush_stats );

			//Cahce the results, we don't need a timed cache expiration.
			wp_cache_set( 'wp_smush_stats_nextgen', $smush_stats, 'nextgen' );
		}

		/**
		 * Get the Nextgen Smush stats
		 * @return bool|mixed|void
		 */
		function get_smush_stats() {
			global $WpSmush;

			// Check for the  wp_smush_images_smushed in the 'nextgen' group.
			$smushed_stats = wp_cache_get( 'wp_smush_stats_nextgen', 'nextgen' );

			// If nothing is found, build the object.
			if ( false === $smushed_stats ) {
				// Check for the  wp_smush_images in the 'nextgen' group.
				$smushed_stats = get_option( 'wp_smush_stats_nextgen', array() );

				if ( ! is_wp_error( $smushed_stats ) ) {
					// In this case we don't need a timed cache expiration.
					wp_cache_set( 'wp_smush_stats_nextgen', $smushed_stats, 'nextgen' );
				}
			}
			if ( empty( $smushed_stats['bytes'] ) || $smushed_stats['bytes'] < 0 ) {
				$smushed_stats['bytes'] = 0;
			}

			if ( ! empty( $smushed_stats['size_before'] ) && $smushed_stats['size_before'] > 0 ) {
				$smushed_stats['percent'] = ( $smushed_stats['bytes'] / $smushed_stats['size_before'] ) * 100;
			}

			//Round off precentage
			$smushed_stats['percent'] = ! empty( $smushed_stats['percent'] ) ? round( $smushed_stats['percent'], 2 ) : 0;

			$smushed_stats['human'] = $WpSmush->format_bytes( $smushed_stats['bytes'] );

			return $smushed_stats;
		}

		/**
		 * Updates the cache for Smushed and Unsmushed images
		 */
		function update_cache() {
			$this->get_ngg_images('smushed', '', true );
		}

	}//End of Class

}//End Of if class not exists