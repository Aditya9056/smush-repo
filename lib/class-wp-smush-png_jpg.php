<?php
/**
 * @package WP Smush
 * 
 * @version 2.4
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2016, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmushPngtoJpg' ) ) {

	class WpSmushPngtoJpg {
		/**
		 * Checks if the Given PNG file is transparent or not
		 *
		 * @param string $id Attachment id
		 * @param string $file
		 *
		 * @return bool|int
		 */
		function is_transparent( $id = '', $file = '' ) {
			//No attachment id/ file path, return
			if( empty( $id ) && empty( $file ) ) {
				return false;
			}

			if( empty( $file ) ) {
				$file = get_attached_file( $id );
			}

			//Check if File exists
			if ( empty( $file ) || ! file_exists( $file ) ) {
				return false;
			}

			$im  = new Imagick( $file );
			return $im->getImageAlphaChannel();
		}
		/**
		 * Check whether to convert the PNG to JPG or not
		 * @param $id Attachment ID
		 * @param $file File path for the attachment
		 *
		 * @return bool Whether to convert the PNG or not
		 *
		 */
		function should_convert( $id, $file ) {
			/** Transparency Check */
			$is_transparent = $this->is_transparent( $id, $file );
			if ( $is_transparent ) {
				//Get the Transparency conversion settings
				$convert_transparent = get_site_option( WP_SMUSH_PREFIX . 'png_to_jpg', false );
				if ( ! $convert_transparent ) {
					return false;
				}
			}

			return true;
		}

		function convert_to_jpg( $id, $file ) {

			$white=new Imagick();
			$white->newImage($width, $height, "white");
			$white->compositeimage($image, Imagick::COMPOSITE_OVER, 0, 0);
			$white->setImageFormat('jpg');
			$white->writeImage('image.jpg');
		}

		function png_to_jpg( $id ) {
			$file = get_attached_file( $id );

			/* Return If not PNG */

			//Get image mime type
			$mime = get_post_mime_type( $id );
			if( 'image/png' != $mime ) {
				return null;
			}

			/** Return if Imagick is not available **/
			if ( ! class_exists( 'Imagick' ) || ! method_exists( 'Imagick', 'getImageAlphaChannel' ) ) {
				return null;
			}

			/** Whether to convert to jpg or not **/
			$should_convert = $this->should_convert( $id, $file );

			/**
			 * Filter whether to convert the PNG to JPG or not
			 *
			 * @since 2.4
			 *
			 * @param bool $should_convert Current choice for image conversion
			 *
			 * @param int $id Attachment id
			 *
			 * @param string $file File path for the image
			 *
			 */
			if( ! $should_convert = apply_filters( 'wp_smush_convert_to_jpg', $should_convert, $id, $file ) ) {
				return null;
			}

			$result = $this->convert_to_jpg( $id, $file );
			echo "<pre>";
			print_r( $result );
			echo "</pre>";

		}
	}
	global $WpSmushPngtoJpg;
	$WpSmushPngtoJpg = new $WpSmushPngtoJpg();
}