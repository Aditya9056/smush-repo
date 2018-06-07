<?php
/**
 * Smush integration with Rest API.
 *
 * @since 2.8
 *
 * @package WP Smush
 * @subpackage Admin
 *
 * @author Anton Vanyukov <anton@incsub.com>
 *
 * @copyright (c) 2018, Incsub (http://incsub.com)
 */

/**
 * Singleton class WpSmushRest for extending the WordPress REST API interface.
 *
 * @since 2.8
 */
class WP_Smush_Rest {
	/**
	 * Class instance variable.
	 *
	 * @var null|WP_Smush_Rest
	 */
	private static $_instance = null;

	/**
	 * WpSmushRest constructor.
	 */
	private function __construct() {}

	/**
	 * Get class instance.
	 *
	 * @return null|WP_Smush_Rest
	 */
	public static function get_instance() {
		if ( null !== self::$_instance ) {
			return self::$_instance;
		}

		return new self;
	}

	/**
	 * Register meta fields for images.
	 */
	public function register_metas() {
		add_action( 'rest_api_init', function () {
			register_rest_field( 'attachment', 'smush', array(
				'get_callback' => array( $this, 'register_image_stats' ),
				'update_callback' => function( $karma, $comment_obj ) {
					$ret = wp_update_comment( array(
						'comment_ID'    => $comment_obj->comment_ID,
						'comment_karma' => $karma,
					) );
					if ( false === $ret ) {
						return new WP_Error(
							'rest_comment_karma_failed',
							__( 'Failed to update comment karma.' ),
							array(
								'status' => 500,
							)
						);
					}
					return true;
				},
				'schema' => array(
					'description' => __( 'Comment karma.' ),
					'type'        => 'integer',
				),
			) );
		} );
	}

	/**
	 * Add image stats to the wp-json/wp/v2/media REST API endpoint.
	 *
	 * @see https://developer.wordpress.org/rest-api/reference/media/
	 *
	 * @param array $image  Image array.
	 *
	 * @return array|string|void
	 */
	public function register_image_stats( $image ) {
		/* @var WpSmush $WpSmush */
		global $WpSmush, $wpsmush_s3_compat;

		if ( get_option( 'smush-in-progress-' . $image['id'], false ) ) {
			$status_txt = __( 'Smushing in progress', 'wp-smushit' );
			return $status_txt;
		}

		$wp_smush_data = get_post_meta( $image['id'], $WpSmush->smushed_meta_key, true );

		if ( empty( $wp_smush_data ) ) {
			$status_txt = __( 'Not processed', 'wp-smushit' );
			return $status_txt;
		}

		$wp_resize_savings  = get_post_meta( $image['id'], WP_SMUSH_PREFIX . 'resize_savings', true );
		$conversion_savings = get_post_meta( $image['id'], WP_SMUSH_PREFIX . 'pngjpg_savings', true );

		$combined_stats = $WpSmush->combined_stats( $wp_smush_data, $wp_resize_savings );
		$combined_stats = $WpSmush->combine_conversion_stats( $combined_stats, $conversion_savings );

		// Remove Smush S3 hook, as it downloads the file again.
		remove_filter( 'as3cf_get_attached_file', array( $wpsmush_s3_compat, 'smush_download_file' ), 11, 4 );
		$attachment_data = wp_get_attachment_metadata( $image['id'] );

		$image_count    = count( $wp_smush_data['sizes'] );
		$bytes          = isset( $combined_stats['stats']['bytes'] ) ? $combined_stats['stats']['bytes'] : 0;
		$bytes_readable = ! empty( $bytes ) ? size_format( $bytes, 1 ) : '';
		$percent        = isset( $combined_stats['stats']['percent'] ) ? $combined_stats['stats']['percent'] : 0;
		$percent        = $percent < 0 ? 0 : $percent;

		if ( empty( $wp_resize_savings['bytes'] ) && isset( $wp_smush_data['stats']['size_before'] ) && 0 === $wp_smush_data['stats']['size_before'] && ! empty( $wp_smush_data['sizes'] ) ) {
			$status_txt = __( 'Already Optimized', 'wp-smushit' );
			return $status_txt;
		} else {
			if ( 0 === $bytes || 0 === $percent ) {
				$status_txt = __( 'Already Optimized', 'wp-smushit' );
				return $status_txt;
			} elseif ( ! empty( $percent ) && ! empty( $bytes_readable ) ) {
				$status_txt = $image_count > 1 ? sprintf( __( '%d images reduced ', 'wp-smushit' ), $image_count ) : __( 'Reduced ', 'wp-smushit' );

				$stats_percent = number_format_i18n( $percent, 2, '.', '' );
				$stats_percent = $stats_percent > 0 ? sprintf( '(  %01.1f%% )', $stats_percent ) : '';
				$status_txt .= sprintf( __( 'by %s %s', 'wp-smushit' ), $bytes_readable, $stats_percent );

				$file_path = get_attached_file( $image['id'] );
				$size      = file_exists( $file_path ) ? filesize( $file_path ) : 0;
				if ( $size > 0 ) {
					$size       = size_format( $size, 1 );
					$image_size = sprintf( __( '<br /> Image Size: %s', 'wp-smushit' ), $size );
					$status_txt .= $image_size;
				}

				// Detailed Stats: Show detailed stats if available.
				if ( ! empty( $wp_smush_data['sizes'] ) ) {
					// Detailed Stats Link.
					$links = sprintf( '<a href="#" class="wp-smush-action smush-stats-details wp-smush-title" tooltip="%s">%s [<span class="stats-toggle">+</span>]</a>', esc_html__( "Detailed stats for all the image sizes", "wp-smushit" ), esc_html__( "Smush stats", 'wp-smushit' ) );

					// Stats.
					$stats = $WpSmush->get_detailed_stats( $image['id'], $wp_smush_data, $attachment_data );
				}

				return $status_txt;
			}
		}

		// IF current compression is lossy.
		if ( ! empty( $wp_smush_data ) && ! empty( $wp_smush_data['stats'] ) ) {
			$lossy    = ! empty( $wp_smush_data['stats']['lossy'] ) ? $wp_smush_data['stats']['lossy'] : '';
			$is_lossy = $lossy == 1 ? true : false;
		}

		return array(
			'status'       => $status_txt,
			'stats'        => $stats,
			'show_warning' => intval( $this->show_warning() ),
		);
	}

}
