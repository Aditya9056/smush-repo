<?php
/**
 * Smush data structure class.
 *
 * Provides a list of return values for common functionality.
 *
 * @package Helpers
 */

namespace Helpers;

/**
 * Class Smush
 */
class Smush {

	/**
	 * Covers the wp-smpro-smush-data post meta value.
	 */
	public static function smush_single_attachment() {
		$medium = $large = $thumbnail = $medium_large = $size1536 = $size2048 = $post_thumbnail = new \stdClass();

		$medium->percent     = 0;
		$medium->bytes       = 0;
		$medium->size_before = 11330;
		$medium->size_after  = 11330;
		$medium->time        = 0.18;

		$large->percent     = 1.55;
		$large->bytes       = 1241;
		$large->size_before = 80293;
		$large->size_after  = 79052;
		$large->time        = 0.05;

		$thumbnail->percent     = 0;
		$thumbnail->bytes       = 0;
		$thumbnail->size_before = 6705;
		$thumbnail->size_after  = 6705;
		$thumbnail->time        = 0.01;

		$medium_large->percent     = 1.17;
		$medium_large->bytes       = 561;
		$medium_large->size_before = 48071;
		$medium_large->size_after  = 47510;
		$medium_large->time        = 0.03;

		$size1536->percent     = 2.36;
		$size1536->bytes       = 4085;
		$size1536->size_before = 172994;
		$size1536->size_after  = 168909;
		$size1536->time        = 0.06;

		$size2048->percent     = 2.74;
		$size2048->bytes       = 8455;
		$size2048->size_before = 308772;
		$size2048->size_after  = 300317;
		$size2048->time        = 0.11;

		$post_thumbnail->percent     = 2.27;
		$post_thumbnail->bytes       = 4088;
		$post_thumbnail->size_before = 180103;
		$post_thumbnail->size_after  = 176015;
		$post_thumbnail->time        = 0.08;

		return [
			'stats' => [
				'percent'     => 2.280184295308,
				'bytes'       => 18430,
				'size_before' => 808268,
				'size_after'  => 789838,
				'time'        => 0.52,
				'api_version' => 1.0,
				'lossy'       => false,
				'keep_exif'   => true,
			],
			'sizes' => [
				'medium'         => $medium,
				'large'          => $large,
				'thumbnail'      => $thumbnail,
				'medium_large'   => $medium_large,
				'1536x1536'      => $size1536,
				'2048x2048'      => $size2048,
				'post-thumbnail' => $post_thumbnail,
			],
		];
	}

}
