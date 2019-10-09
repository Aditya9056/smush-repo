<?php
/**
 * Class that is responsible for all stats calculations.
 *
 * @since 3.4.0
 * @package Smush\Core
 */

namespace Smush\Core;

use Smush\WP_Smush;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Stats
 */
class Stats {

	/**
	 * Database module.
	 *
	 * @since 3.3.0
	 * @var Modules\DB
	 */
	protected $database;

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
	 *
	 * @param Modules\DB $db  Database module.
	 */
	public function __construct( Modules\DB $db ) {
		$this->database = $db;

		$this->init();

		// Send Smush stats for PRO members.
		add_filter( 'wpmudev_api_project_extra_data-912164', array( $this, 'send_smush_stats' ) );
	}

	/**
	 * Getter for database module.
	 *
	 * @since 3.3.0
	 *
	 * @return Modules\DB
	 */
	public function db() {
		return $this->database;
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
	 * Get all the attachment meta, sum up the stats and return
	 *
	 * @param bool $force_update     Whether to forcefully update the cache.
	 *
	 * @return array|bool|mixed
	 */
	public function global_stats( $force_update = false ) {
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
		$smush_data['resize_count']   = $this->database->resize_savings( false, false, true );
		$resize_savings               = $this->database->resize_savings( false );
		$smush_data['resize_savings'] = ! empty( $resize_savings['bytes'] ) ? $resize_savings['bytes'] : 0;

		// Conversion Savings.
		$conversion_savings               = $this->database->conversion_savings( false );
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
	 * Runs the expensive queries to get our global smush stats
	 *
	 * @param bool $force_update  Whether to force update the global stats or not.
	 */
	public function setup_global_stats( $force_update = false ) {
		// Set directory smush status.
		$this->dir_stats = Modules\Dir::should_continue() ? $this->mod->dir->total_stats() : array();

		// Setup Attachments and total count.
		$this->database->total_count( true );

		$this->stats = $this->global_stats( $force_update );

		if ( empty( $this->smushed_attachments ) ) {
			// Get smushed attachments.
			$this->smushed_attachments = $this->database->smushed_count( true, $force_update );
		}

		// Get supersmushed images count.
		if ( empty( $this->super_smushed ) ) {
			$this->super_smushed = $this->database->super_smushed_count();
		}

		// Set pro savings.
		$this->set_pro_savings();

		// Get skipped attachments.
		$this->skipped_attachments = $this->database->skipped_count( $force_update );
		$this->skipped_count       = count( $this->skipped_attachments );

		// Set smushed count.
		$this->smushed_count   = ! empty( $this->smushed_attachments ) ? count( $this->smushed_attachments ) : 0;
		$this->remaining_count = $this->remaining_count();
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

}
