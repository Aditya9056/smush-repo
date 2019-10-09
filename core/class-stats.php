<?php
/**
 * Class that is responsible for all stats calculations.
 *
 * @since 3.4.0
 * @package Smush\Core
 */

namespace Smush\Core;

use Smush\WP_Smush;
use WP_Query;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Stats
 */
class Stats {

	/**
	 * Stores the stats for all the images.
	 *
	 * @var array $stats
	 */
	public $stats;

	/**
	 * Smushed attachments from selected directories.
	 *
	 * @var array $dir_stats
	 */
	public $dir_stats;

	/**
	 * Protected init class, used in child methods instead of constructor.
	 *
	 * @since 3.4.0
	 */
	protected function init() {}

	/**
	 * Stats constructor.
	 */
	public function __construct() {
		// Recalculate resize savings.
		add_action(
			'wp_smush_image_resized',
			function() {
				return $this->get_savings( 'resize' );
			}
		);

		// Update Conversion savings.
		add_action(
			'wp_smush_png_jpg_converted',
			function() {
				return $this->get_savings( 'pngjpg' );
			}
		);

		// Send Smush stats for PRO members.
		add_filter( 'wpmudev_api_project_extra_data-912164', array( $this, 'send_smush_stats' ) );

		$this->init();
	}

	/**
	 * Return global stats.
	 *
	 * Stats sent
	 *  array( 'total_images','bytes', 'human', 'percent')
	 *
	 * @return array|bool|mixed
	 */
	public function send_smush_stats() {
		$stats = $this->global_stats();

		$required_stats = array( 'total_images', 'bytes', 'human', 'percent' );

		$stats = is_array( $stats ) ? array_intersect_key( $stats, array_flip( $required_stats ) ) : array();

		return $stats;
	}

	/**
	 * Get the savings from image resizing or PNG -> JPG conversion savings.
	 *
	 * @param string $type          Savings type. Accepts: resize, pngjpg.
	 * @param bool   $force_update  Force update to re-calculate all stats. Default: false.
	 * @param bool   $format        Format the bytes in readable format. Default: false.
	 * @param bool   $return_count  Return the resized image count. Default: false.
	 *
	 * @return int|array
	 */
	public function get_savings( $type, $force_update = true, $format = false, $return_count = false ) {
		$key       = WP_SMUSH_PREFIX . $type . '_savings';
		$key_count = WP_SMUSH_PREFIX . $type . '_count';

		if ( ! $force_update ) {
			$savings = wp_cache_get( $key, 'wp-smush' );
			if ( ! $return_count && $savings ) {
				return $savings;
			}

			$count = wp_cache_get( $key_count, 'wp-smush' );
			if ( $return_count && $count ) {
				return $count;
			}
		}

		// If savings or resize image count is not stored in db, recalculate.
		$count      = 0;
		$offset     = 0;
		$limit      = apply_filters( 'wp_smush_query_limit', 2000 );
		$query_next = true;
		$savings    = array(
			'bytes'       => 0,
			'size_before' => 0,
			'size_after'  => 0,
		);

		global $wpdb;

		while ( $query_next ) {
			$query_data = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key=%s LIMIT %d, %d",
					$key,
					$offset,
					$limit
				)
			); // Db call ok.

			if ( ! empty( $query_data ) ) {
				foreach ( $query_data as $data ) {
					// Skip resmush IDs.
					if ( ! empty( $this->resmush_ids ) && in_array( $data->post_id, $this->resmush_ids, true ) ) {
						continue;
					}

					$count++;

					if ( empty( $data ) ) {
						continue;
					}

					$meta = maybe_unserialize( $data->meta_value );

					// Resize mete already contains all the stats.
					if ( 'resize' === $type && ! empty( $meta ) && ! empty( $meta['bytes'] ) ) {
						$savings['bytes']       += $meta['bytes'];
						$savings['size_before'] += $meta['size_before'];
						$savings['size_after']  += $meta['size_after'];
					}

					// PNG - JPG conversion meta contains stats by attachment size.
					if ( 'pngjpg' === $type && is_array( $meta ) ) {
						foreach ( $meta as $size ) {
							$savings['bytes']       += isset( $size['bytes'] ) ? $size['bytes'] : 0;
							$savings['size_before'] += isset( $size['size_before'] ) ? $size['size_before'] : 0;
							$savings['size_after']  += isset( $size['size_after'] ) ? $size['size_after'] : 0;
						}
					}
				}
			}
			// Update the offset.
			$offset += $limit;

			// Compare the offset value to total images. Continue when there are results.
			$query_next = ( $this->total_count > $offset ) && $query_data;
		}

		if ( $format ) {
			$savings['bytes'] = size_format( $savings['bytes'], 1 );
		}

		wp_cache_set( $key, $savings, 'wp-smush' );
		wp_cache_set( $key_count, $count, 'wp-smush' );

		return $return_count ? $count : $savings;
	}

	/**
	 * Runs the expensive queries to get our global smush stats
	 *
	 * @param bool $force_update  Whether to force update the global stats or not.
	 */
	public function setup_global_stats( $force_update = false ) {
		// Set directory smush status.
		$this->dir_stats = Modules\Dir::should_continue() ? $this->mod->dir->total_stats() : array();

		// Setup Attachments and total count.
		$this->total_count( true );

		$this->stats = $this->global_stats( $force_update );

		if ( empty( $this->smushed_attachments ) ) {
			// Get smushed attachments.
			$this->smushed_attachments = $this->smushed_count( true, $force_update );
		}

		// Get supersmushed images count.
		if ( empty( $this->super_smushed ) ) {
			$this->super_smushed = $this->super_smushed_count();
		}

		// Set pro savings.
		$this->set_pro_savings();

		// Get skipped attachments.
		$this->skipped_attachments = $this->skipped_count( $force_update );
		$this->skipped_count       = count( $this->skipped_attachments );

		// Set smushed count.
		$this->smushed_count   = ! empty( $this->smushed_attachments ) ? count( $this->smushed_attachments ) : 0;
		$this->remaining_count = $this->remaining_count();
	}

	/**
	 * Returns/Updates the number of images Super Smushed.
	 *
	 * @param string $type media/nextgen, Type of images to get/set the super smushed count for.
	 *
	 * @param array  $attachments Optional, By default Media attachments will be fetched.
	 *
	 * @return array|mixed
	 *
	 * @todo Refactor Method, Separate Media Library and Nextgen, moreover nextgen functionality is broken
	 */
	public function super_smushed_count( $type = 'media', $attachments = array() ) {
		if ( 'media' === $type ) {
			$count = $this->get_super_smushed_attachments();
		} else {
			$key = 'wp-smush-super_smushed_nextgen';

			// Clear up the stats, if there are no images.
			if ( method_exists( 'Smush\\Core\\Integrations\\NextGen\\Stats', 'total_count' ) && 0 === Integrations\NextGen\Stats::total_count() ) {
				delete_option( $key );
			}

			// Flag to check if we need to re-evaluate the count.
			$reevaluate = false;

			$super_smushed = get_option( $key, false );

			// Check if need to revalidate.
			if ( ! $super_smushed || empty( $super_smushed ) || empty( $super_smushed['ids'] ) ) {
				$super_smushed = array(
					'ids' => array(),
				);

				$reevaluate = true;
			} else {
				$last_checked = $super_smushed['timestamp'];

				$diff = $last_checked - current_time( 'timestamp' );

				// Difference in hour.
				$diff_h = $diff / 3600;

				// if last checked was more than 1 hours.
				if ( $diff_h > 1 ) {
					$reevaluate = true;
				}
			}
			// Do not reevaluate stats if nextgen attachments are not provided.
			if ( 'nextgen' === $type && empty( $attachments ) && $reevaluate ) {
				$reevaluate = false;
			}

			// Need to scan all the image.
			if ( $reevaluate ) {
				// Get all the Smushed attachments ids
				// Note: Wrong Method called, it'll fetch media images and not NextGen images
				// Should be $attachments, in place of $super_smushed_images.
				$super_smushed_images = $this->get_super_smushed_attachments( true );

				if ( ! empty( $super_smushed_images ) && is_array( $super_smushed_images ) ) {
					// Iterate over all the attachments to check if it's already there in list, else add it.
					foreach ( $super_smushed_images as $id ) {
						if ( ! in_array( $id, $super_smushed['ids'] ) ) {
							$super_smushed['ids'][] = $id;
						}
					}
				}

				$super_smushed['timestamp'] = current_time( 'timestamp' );

				update_option( $key, $super_smushed, false );
			}

			$count = ! empty( $super_smushed['ids'] ) ? count( $super_smushed['ids'] ) : 0;
		}

		return $count;
	}

	/**
	 * Optimised images count or IDs.
	 *
	 * @param bool $return_ids Should return ids?.
	 * @param bool $force_update Should force update?.
	 *
	 * @return array|int
	 */
	public function smushed_count( $return_ids = false, $force_update = false ) {
		global $wpdb;

		// Don't query again, if the variable is already set.
		if ( ! $return_ids && ! empty( $this->smushed_count ) && $this->smushed_count > 0 ) {
			return $this->smushed_count;
		}

		// Key for cache.
		$key = $return_ids ? WP_SMUSH_PREFIX . 'smushed_ids' : WP_SMUSH_PREFIX . 'smushed_count';

		// If not forced to update, try to get from cache.
		if ( ! $force_update ) {
			// TODO: This is an issue. If not forcing the update, the cached version is never incremented during image Smush.
			$smushed_count = wp_cache_get( $key, 'wp-smush' );
			// Return the cache value if cache is set.
			if ( false !== $smushed_count && ! empty( $smushed_count ) ) {
				return $smushed_count;
			}
		}

		/**
		 * Allows to set a limit of mysql query
		 * Default value is 2000.
		 */
		$limit      = apply_filters( 'wp_smush_query_limit', 2000 );
		$offset     = 0;
		$query_next = true;

		$posts = array();

		// Remove the Filters added by WP Media Folder.
		do_action( 'wp_smush_remove_filters' );
		while ( $query_next && $results = $wpdb->get_col( $wpdb->prepare( "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key=%s ORDER BY `post_id` DESC LIMIT $offset, $limit", Modules\Smush::$smushed_meta_key ) ) ) {
			if ( ! is_wp_error( $results ) && count( $results ) > 0 ) {
				$posts = array_merge( $posts, $results );
			}
			// Update the offset.
			$offset += $limit;

			// Compare the Offset value to total images.
			if ( ! empty( $this->total_count ) && $this->total_count <= $offset ) {
				$query_next = false;
			}
		}

		// Remove resmush IDs from the list.
		if ( ! empty( $this->resmush_ids ) && is_array( $this->resmush_ids ) ) {
			$posts = array_diff( $posts, $this->resmush_ids );
		}

		// Set in cache.
		wp_cache_set( $key, $return_ids ? $posts : count( $posts ), 'wp-smush' );

		return $return_ids ? $posts : count( $posts );
	}

	/**
	 * Get the media attachment ID/count.
	 *
	 * @param bool $return_count  Return count.
	 * @param bool $force_update  Force update.
	 *
	 * @return array|bool|int|mixed
	 */
	public function get_media_attachments( $return_count = false, $force_update = false ) {
		global $wpdb;

		// Return results from cache.
		if ( ! $force_update ) {
			$posts = wp_cache_get( 'media_attachments', 'wp-smush' );
			$count = ! empty( $posts ) ? count( $posts ) : 0;

			// Return results only if we've got any.
			if ( $count ) {
				return $return_count ? $count : $posts;
			}
		}

		$posts = array();

		// Else Get it Fresh!!
		$offset = 0;
		$limit  = apply_filters( 'wp_smush_query_limit', 2000 );
		$mime   = implode( "', '", Core::$mime_types );
		// Remove the Filters added by WP Media Folder.
		do_action( 'wp_smush_remove_filters' );

		$get_posts = true;

		while ( $get_posts ) {
			$results = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_mime_type IN ('$mime') ORDER BY `ID` DESC LIMIT %d, %d",
					$offset,
					$limit
				)
			); // Db call ok.
			if ( ! empty( $results ) && is_array( $results ) && count( $results ) > 0 ) {

				// Get a filtered list of post ids.
				$posts = array_merge( $posts, $results );

				// Update the offset.
				$offset += $limit;
			} else {
				// If we didn't get any posts from query, set $get_posts to false.
				$get_posts = false;
			}
		}

		// Add the attachments to cache.
		wp_cache_add( 'media_attachments', $posts, 'wp-smush' );

		if ( $return_count ) {
			return count( $posts );
		}

		return $posts;
	}

	/**
	 * Fetch all the unsmushed attachments
	 *
	 * @return array $attachments
	 */
	public function get_unsmushed_attachments() {
		if ( isset( $_REQUEST['ids'] ) ) {
			return array_map( 'intval', explode( ',', $_REQUEST['ids'] ) );
		}

		// Check if we can get the unsmushed attachments from the other two variables.
		if ( ! empty( $this->attachments ) && ! empty( $this->smushed_attachments ) ) {
			$attachments = array_diff( $this->attachments, $this->smushed_attachments );

			// Remove skipped attachments.
			if ( ! empty( $this->smushed_attachments ) ) {
				$attachments = array_diff( $attachments, $this->skipped_attachments );
			}

			$attachments = ! empty( $attachments ) && is_array( $attachments ) ? array_slice( $attachments, 0, apply_filters( 'wp_smush_max_rows', 5000 ) ) : array();
		} else {
			$attachments = $this->get_attachments();
		}

		// Remove resmush list from unsmushed images.
		if ( ! empty( $this->resmush_ids ) && is_array( $this->resmush_ids ) ) {
			$attachments = array_diff( $attachments, $this->resmush_ids );
		}

		return $attachments;
	}

	/**
	 * Get the savings for the given set of attachments
	 *
	 * @param array $attachments  Array of attachment IDs.
	 *
	 * @return array Stats
	 *  array(
	 * 'size_before' => 0,
	 * 'size_after'    => 0,
	 * 'savings_resize' => 0,
	 * 'savings_conversion' => 0
	 *  )
	 */
	public function get_stats_for_attachments( $attachments = array() ) {
		$stats = array(
			'size_before'        => 0,
			'size_after'         => 0,
			'savings_resize'     => 0,
			'savings_conversion' => 0,
			'count_images'       => 0,
			'count_supersmushed' => 0,
			'count_smushed'      => 0,
			'count_resize'       => 0,
			'count_remaining'    => 0,
		);

		// If we don't have any attachments, return empty array.
		if ( empty( $attachments ) || ! is_array( $attachments ) ) {
			return $stats;
		}

		// Loop over all the attachments to get the cumulative savings.
		foreach ( $attachments as $attachment ) {
			$smush_stats        = get_post_meta( $attachment, Modules\Smush::$smushed_meta_key, true );
			$resize_savings     = get_post_meta( $attachment, WP_SMUSH_PREFIX . 'resize_savings', true );
			$conversion_savings = Helper::get_pngjpg_savings( $attachment );

			if ( ! empty( $smush_stats['stats'] ) ) {
				// Combine all the stats, and keep the resize and send conversion settings separately.
				$stats['size_before'] += ! empty( $smush_stats['stats']['size_before'] ) ? $smush_stats['stats']['size_before'] : 0;
				$stats['size_after']  += ! empty( $smush_stats['stats']['size_after'] ) ? $smush_stats['stats']['size_after'] : 0;
			}

			$stats['count_images']       += ! empty( $smush_stats['sizes'] ) && is_array( $smush_stats['sizes'] ) ? count( $smush_stats['sizes'] ) : 0;
			$stats['count_supersmushed'] += ! empty( $smush_stats['stats'] ) && $smush_stats['stats']['lossy'] ? 1 : 0;

			// Add resize saving stats.
			if ( ! empty( $resize_savings ) ) {
				// Add resize and conversion savings.
				$stats['savings_resize'] += ! empty( $resize_savings['bytes'] ) ? $resize_savings['bytes'] : 0;
				$stats['size_before']    += ! empty( $resize_savings['size_before'] ) ? $resize_savings['size_before'] : 0;
				$stats['size_after']     += ! empty( $resize_savings['size_after'] ) ? $resize_savings['size_after'] : 0;
				$stats['count_resize']   += 1;
			}

			// Add conversion saving stats.
			if ( ! empty( $conversion_savings ) ) {
				// Add resize and conversion savings.
				$stats['savings_conversion'] += ! empty( $conversion_savings['bytes'] ) ? $conversion_savings['bytes'] : 0;
				$stats['size_before']        += ! empty( $conversion_savings['size_before'] ) ? $conversion_savings['size_before'] : 0;
				$stats['size_after']         += ! empty( $conversion_savings['size_after'] ) ? $conversion_savings['size_after'] : 0;
			}
			$stats['count_smushed'] += 1;
		}

		return $stats;
	}

	/**
	 * Set pro savings stats if not premium user.
	 *
	 * For non-premium users, show expected average savings based
	 * on the free version savings.
	 */
	public function set_pro_savings() {
		// No need this already premium.
		if ( WP_Smush::is_pro() ) {
			return;
		}

		// Initialize.
		$this->stats['pro_savings'] = array(
			'percent' => 0,
			'savings' => 0,
		);

		// Default values.
		$savings       = $this->stats['percent'] > 0 ? $this->stats['percent'] : 0;
		$savings_bytes = $this->stats['human'] > 0 ? $this->stats['bytes'] : '0';
		$orig_diff     = 2.22058824;
		if ( ! empty( $savings ) && $savings > 49 ) {
			$orig_diff = 1.22054412;
		}
		// Calculate Pro savings.
		if ( ! empty( $savings ) ) {
			$savings       = $orig_diff * $savings;
			$savings_bytes = $orig_diff * $savings_bytes;
		}

		// Set pro savings in global stats.
		if ( $savings > 0 ) {
			$this->stats['pro_savings'] = array(
				'percent' => number_format_i18n( $savings, 1 ),
				'savings' => size_format( $savings_bytes, 1 ),
			);
		}
	}

	/**
	 * Get all the attachment meta, sum up the stats and return
	 *
	 * @param bool $force_update     Whether to forcefully update the cache.
	 *
	 * @return array|bool|mixed
	 */
	private function global_stats( $force_update = false ) {
		$stats = get_option( 'smush_global_stats' );
		// Remove id from global stats stored in db.
		if ( ! $force_update && $stats && ! empty( $stats ) && isset( $stats['size_before'] ) ) {
			if ( isset( $stats['id'] ) ) {
				unset( $stats['id'] );
			}

			return $stats;
		}

		global $wpdb;

		$smush_data = array(
			'size_before' => 0,
			'size_after'  => 0,
			'percent'     => 0,
			'human'       => 0,
			'bytes'       => 0,
		);

		/**
		 * Allows to set a limit of mysql query
		 * Default value is 2000
		 */
		$limit      = apply_filters( 'wp_smush_query_limit', 2000 );
		$offset     = 0;
		$query_next = true;

		$supersmushed_count         = 0;
		$smush_data['total_images'] = 0;

		while ( $query_next ) {
			$global_data = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key=%s LIMIT %d, %d",
					Modules\Smush::$smushed_meta_key,
					$offset,
					$limit
				)
			); // Db call ok; no-cache ok.
			if ( ! empty( $global_data ) ) {
				foreach ( $global_data as $data ) {
					// Skip attachment, if not in attachment list.
					if ( ! in_array( $data->post_id, $this->attachments, true ) ) {
						continue;
					}

					$smush_data['id'][] = $data->post_id;
					if ( ! empty( $data->meta_value ) ) {
						$meta = maybe_unserialize( $data->meta_value );
						if ( ! empty( $meta['stats'] ) ) {

							// Check for lossy compression.
							if ( true === $meta['stats']['lossy'] ) {
								$supersmushed_count++;
							}

							// If the image was optimised.
							if ( ! empty( $meta['stats'] ) && $meta['stats']['size_before'] >= $meta['stats']['size_after'] ) {
								// Total Image Smushed.
								$smush_data['total_images'] += ! empty( $meta['sizes'] ) ? count( $meta['sizes'] ) : 0;
								$smush_data['size_before']  += ! empty( $meta['stats']['size_before'] ) ? (int) $meta['stats']['size_before'] : 0;
								$smush_data['size_after']   += ! empty( $meta['stats']['size_after'] ) ? (int) $meta['stats']['size_after'] : 0;
							}
						}
					}
				}
			}

			$smush_data['bytes'] = $smush_data['size_before'] - $smush_data['size_after'];

			// Update the offset.
			$offset += $limit;

			// Compare the Offset value to total images.
			if ( ! empty( $this->total_count ) && $this->total_count <= $offset ) {
				$query_next = false;
			} elseif ( ! $global_data ) {
				// If we didn' got any results.
				$query_next = false;
			}
		}

		// Add directory smush image bytes.
		if ( ! empty( $this->dir_stats['bytes'] ) && $this->dir_stats['bytes'] > 0 ) {
			$smush_data['bytes'] += $this->dir_stats['bytes'];
		}
		// Add directory smush image total size.
		if ( ! empty( $this->dir_stats['orig_size'] ) && $this->dir_stats['orig_size'] > 0 ) {
			$smush_data['size_before'] += $this->dir_stats['orig_size'];
		}
		// Add directory smush saved size.
		if ( ! empty( $this->dir_stats['image_size'] ) && $this->dir_stats['image_size'] > 0 ) {
			$smush_data['size_after'] += $this->dir_stats['image_size'];
		}
		// Add directory smushed images.
		if ( ! empty( $this->dir_stats['optimised'] ) && $this->dir_stats['optimised'] > 0 ) {
			$smush_data['total_images'] += $this->dir_stats['optimised'];
		}

		// Resize Savings.
		$smush_data['resize_count']   = $this->get_savings( 'resize', false, false, true );
		$resize_savings               = $this->get_savings( 'resize', false );
		$smush_data['resize_savings'] = ! empty( $resize_savings['bytes'] ) ? $resize_savings['bytes'] : 0;

		// Conversion Savings.
		$conversion_savings               = $this->get_savings( 'pngjpg', false );
		$smush_data['conversion_savings'] = ! empty( $conversion_savings['bytes'] ) ? $conversion_savings['bytes'] : 0;

		if ( ! isset( $smush_data['bytes'] ) || $smush_data['bytes'] < 0 ) {
			$smush_data['bytes'] = 0;
		}

		// Add the resize savings to bytes.
		$smush_data['bytes']       += $smush_data['resize_savings'];
		$smush_data['size_before'] += $resize_savings['size_before'];
		$smush_data['size_after']  += $resize_savings['size_after'];

		// Add Conversion Savings.
		$smush_data['bytes']       += $smush_data['conversion_savings'];
		$smush_data['size_before'] += $conversion_savings['size_before'];
		$smush_data['size_after']  += $conversion_savings['size_after'];

		if ( $smush_data['size_before'] > 0 ) {
			$smush_data['percent'] = ( $smush_data['bytes'] / $smush_data['size_before'] ) * 100;
		}

		// Round off precentage.
		$smush_data['percent'] = round( $smush_data['percent'], 1 );

		$smush_data['human'] = size_format( $smush_data['bytes'], 1 );

		// Setup Smushed attachment IDs.
		$this->smushed_attachments = ! empty( $smush_data['id'] ) ? $smush_data['id'] : '';

		// Super Smushed attachment count.
		$this->super_smushed = $supersmushed_count;

		// Remove ids from stats.
		unset( $smush_data['id'] );

		// Update cache.
		update_option( 'smush_global_stats', $smush_data, false );

		return $smush_data;
	}

	/**
	 * Total image count.
	 *
	 * @param bool $force_update  Force update.
	 *
	 * @return bool|int|mixed
	 */
	private function total_count( $force_update = false ) {
		// Retrieve from Cache.
		if ( ! $force_update && $count = wp_cache_get( 'total_count', 'wp-smush' ) ) {
			if ( $count ) {
				return $count;
			}
		}

		// Set Attachment IDs, and total count.
		$posts = $this->get_media_attachments( '', $force_update );

		$this->attachments = $posts;

		// Get total count from attachments.
		$total_count = ! empty( $posts ) && is_array( $posts ) ? count( $posts ) : 0;

		// Set total count.
		$this->total_count = $total_count;

		wp_cache_add( 'total_count', $total_count, 'wp-smush' );

		// Send the count.
		return $total_count;
	}

	/**
	 * Get the attachments (either super smushed, or not smushed at all).
	 *
	 * @param string $smushed  Accepts: none, super. Default: none.
	 *
	 * @return array
	 */
	private function get_attachments( $smushed = 'none' ) {
		// Get all the attachments with wp-smush-lossy.
		$limit       = apply_filters( 'wp_smush_query_limit', 2000 );
		$get_posts   = true;
		$attachments = array();
		$args        = array(
			'fields'                 => array( 'ids', 'post_mime_type' ),
			'post_type'              => 'attachment',
			'post_status'            => 'any',
			'orderby'                => 'ID',
			'order'                  => 'DESC',
			'posts_per_page'         => $limit,
			'offset'                 => 0,
			'update_post_term_cache' => false,
			'no_found_rows'          => true,
			'meta_query'             => array(
				array(
					'key'     => Modules\Smush::$smushed_meta_key,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'wp-smush-ignore-bulk',
					'value'   => 'true',
					'compare' => 'NOT EXISTS',
				),
			),
		);

		if ( 'super' === $smushed ) {
			$args['meta_query'] = array(
				array(
					'key'   => 'wp-smush-lossy',
					'value' => 1,
				),
			);
		}

		// Loop over to get all the attachments.
		while ( $get_posts ) {
			// Remove the Filters added by WP Media Folder.
			do_action( 'wp_smush_remove_filters' );

			$query = new WP_Query( $args );

			if ( ! empty( $query->post_count ) && count( $query->posts ) > 0 ) {
				// Get a filtered list of post ids.
				$posts = $this->filter_by_mime( $query->posts );
				// Merge the results.
				$attachments = array_merge( $attachments, $posts );

				// Update the offset.
				$args['offset'] += $limit;
			} else {
				// If we didn't get any posts from query, set $get_posts to false.
				$get_posts = false;
			}

			// If we already got enough posts.
			if ( count( $attachments ) >= apply_filters( 'wp_smush_max_rows', 5000 ) ) {
				$get_posts = false;
			} elseif ( ! empty( $this->total_count ) && $this->total_count <= $args['offset'] ) {
				// If total Count is set, and it is alread lesser than offset, don't query.
				$get_posts = false;
			}
		}

		// Remove resmush IDs from the list.
		if ( ! empty( $this->resmush_ids ) && is_array( $this->resmush_ids ) ) {
			$attachments = array_diff( $attachments, $this->resmush_ids );
		}

		return $attachments;
	}

	/**
	 * Updates the meta for existing smushed images and retrieves the count of super smushed images.
	 *
	 * @param bool $return_ids  Whether to return IDs or just the count.
	 *
	 * @return array|int  Array of super smushed image IDs / number of super smushed images.
	 */
	private function get_super_smushed_attachments( $return_ids = false ) {
		$attachments = $this->get_attachments( 'super' );
		return $return_ids ? $attachments : count( $attachments );
	}

	/**
	 * Returns remaining count
	 *
	 * @return int
	 */
	private function remaining_count() {
		// Check if the resmush count is equal to remaining count.
		$resmush_count   = count( $this->resmush_ids );
		$remaining_count = $this->total_count - $this->smushed_count - $this->skipped_count;
		if ( $resmush_count > 0 && ( $resmush_count !== $this->smushed_count || 0 === $remaining_count ) ) {
			return $resmush_count + $remaining_count;
		}

		return $remaining_count;
	}

	/**
	 * Return the number of skipped attachments.
	 *
	 * @since 3.0
	 *
	 * @param bool $force  Force data refresh.
	 *
	 * @return array
	 */
	private function skipped_count( $force ) {
		if ( ! $force && $images = wp_cache_get( 'skipped_images', 'wp-smush' ) ) {
			return $images;
		}

		global $wpdb;
		$images = $wpdb->get_col( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='wp-smush-ignore-bulk'" ); // Db call ok.
		wp_cache_set( 'skipped_images', $images, 'wp-smush' );

		return $images;
	}

	/**
	 * Filter the Posts object as per mime type.
	 *
	 * @param array $posts Object of Posts.
	 *
	 * @return mixed array of post ids
	 */
	private function filter_by_mime( $posts ) {
		if ( empty( $posts ) ) {
			return $posts;
		}

		foreach ( $posts as $post_k => $post ) {
			if ( ! isset( $post->post_mime_type ) || ! in_array( $post->post_mime_type, Core::$mime_types, true ) ) {
				unset( $posts[ $post_k ] );
			} else {
				$posts[ $post_k ] = $post->ID;
			}
		}

		return $posts;
	}

}
