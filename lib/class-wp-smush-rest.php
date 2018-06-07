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
	 * Will add the stats from wp-smpro-smush-data image meta key to the media REST API endpoint.
	 * If image is Smushed, the stats from the meta can be queried, if the not - the status of Smushing
	 * will be displayed as a string in the API.
	 *
	 * @see https://developer.wordpress.org/rest-api/reference/media/
	 *
	 * @param array $image  Image array.
	 *
	 * @return array|string
	 */
	public function register_image_stats( $image ) {
		/* @var WP_Smush $wp_smush */
		global $wp_smush;

		if ( get_option( 'smush-in-progress-' . $image['id'], false ) ) {
			$status_txt = __( 'Smushing in progress', 'wp-smushit' );
			return $status_txt;
		}

		$wp_smush_data = get_post_meta( $image['id'], $wp_smush->smushed_meta_key, true );

		if ( empty( $wp_smush_data ) ) {
			$status_txt = __( 'Not processed', 'wp-smushit' );
			return $status_txt;
		}

		$wp_resize_savings  = get_post_meta( $image['id'], WP_SMUSH_PREFIX . 'resize_savings', true );
		$conversion_savings = get_post_meta( $image['id'], WP_SMUSH_PREFIX . 'pngjpg_savings', true );

		$combined_stats = $wp_smush->combined_stats( $wp_smush_data, $wp_resize_savings );
		$combined_stats = $wp_smush->combine_conversion_stats( $combined_stats, $conversion_savings );

		return $combined_stats;
	}

}
