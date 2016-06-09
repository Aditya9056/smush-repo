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
		 * Check if Imagick is available or not
		 *
		 * @return bool True/False Whether Imagick is available or not
		 *
		 */
		function supports_imagick() {
			if ( ! class_exists( 'Imagick' ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Check if GD is loaded
		 *
		 * @return bool True/False Whether GD is available or not
		 *
		 */
		function supports_GD() {
			if ( ! function_exists( 'gd_info' ) ) {
				return false;
			}

			return true;
		}

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

			$transparent = '';

			//Try to get transparency using Imagick
			if( $this->supports_imagick() ) {
				try {
					$im = new Imagick( $file );

					return $im->getImageAlphaChannel();
				}
				catch (Exception $e ){
					error_log( "Imagick: Error in checking PNG transparency " . $e->getMessage() );
				}
			}else {
				//Simple check
				//Src: http://camendesign.com/code/uth1_is-png-32bit
				if ( ord( file_get_contents( $file, false, null, 25, 1 ) ) & 4 ) {
					return true;
				}
				//Src: http://www.jonefox.com/blog/2011/04/15/how-to-detect-transparency-in-png-images/
				$contents = file_get_contents( $file );
				if ( stripos( $contents, 'PLTE' ) !== false && stripos( $contents, 'tRNS' ) !== false ) {
					return true;
				}

				//If both the conditions failed, that means not transparent
				return false;

			}

			//If Imagick is installed, and the code exited due to some error
			//Src: StackOverflow
			if ( empty( $transparent ) && $this->supports_GD() ) {
				//Check for transparency using GD
				$i       = imagecreatefrompng( $file );
				$palette = ( imagecolortransparent( $i ) < 0 );
				if ( $palette ) {
					return true;
				}
			}

			return false;

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

			$should_convert = false;

			//Get the Transparency conversion settings
			$convert_transparent = get_site_option( WP_SMUSH_PREFIX . 'png_to_jpg', false );

			//If we are suppose to convert transaprent images, skip is transparent check
			if ( $convert_transparent ) {
				$should_convert = true;
			} else {
				/** Transparency Check */
				$is_transparent = $this->is_transparent( $id, $file );
				if ( $is_transparent ) {
					$should_convert = false;
				}else{
					//If image is not transparent
					$should_convert = true;
				}
			}

			return $should_convert;
		}

		function convert_to_jpg( $id, $file ) {

			$editor = wp_get_image_editor( $file );

			if( is_wp_error( $editor ) ) {
				//Use custom method maybe
				return;
			}

			$file_name = basename( $file );
			$new_image_info = $editor->save( $file_name , 'image/jpeg' );
//			if( is_wp_error(  ))

//			if( $this->supports_imagick() ) {
//				$im = new Imagick( $file );
//				$im->newImage( $width, $height, "white" );
//				$im->compositeimage( $image, Imagick::COMPOSITE_OVER, 0, 0 );
//				$im->setImageFormat( 'jpg' );
//				$im->writeImage( 'image.jpg' );
//			}
//
//			//If Imagick isn't installed / failed to convert the image
//			if( $this->supports_GD() ) {
//
//			}
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
	$WpSmushPngtoJpg = new WpSmushPngtoJpg();
}