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
			if ( empty( $id ) && empty( $file ) ) {
				return false;
			}

			if ( empty( $file ) ) {
				$file = get_attached_file( $id );
			}

			//Check if File exists
			if ( empty( $file ) || ! file_exists( $file ) ) {
				return false;
			}

			$transparent = '';

			//Try to get transparency using Imagick
			if ( $this->supports_imagick() ) {
				try {
					$im = new Imagick( $file );

					return $im->getImageAlphaChannel();
				} catch ( Exception $e ) {
					error_log( "Imagick: Error in checking PNG transparency " . $e->getMessage() );
				}
			} else {
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
		 *
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
				} else {
					//If image is not transparent
					$should_convert = true;
				}
			}

			return $should_convert;
		}

		/**
		 * Update the image URL, MIME Type, Attached File, file path in Meta, URL in post content
		 *
		 * @param $id Attachment ID
		 * @param $o_file Original File Path
		 * @param $n_file New File Path
		 * @param $meta Attachment Meta
		 * @param $size_k Image Size
		 *
		 * @return mixed Attachment Meta with updated file path
		 *
		 */
		function update_image_path( $id, $o_file, $n_file, $meta, $size_k ) {

			//Upload Directory
			$upload_dir = wp_upload_dir();

			//Upload Path
			$upload_path = trailingslashit( $upload_dir['basedir'] );

			//Get the File name without ext
			$new_file = substr( $o_file, 0, - 3 );

			// change extension
			$new_ext = substr( $n_file, - 3 );

			//Updated File name
			$new_file = $new_file . $new_ext;

			//Current URL for image
			$o_url = wp_get_attachment_url( $id );

			//Update URL for image size
			if( 'full' != $size_k ) {
				$base_url = dirname( $o_url );
				$o_url    = $base_url . '/' . basename( $o_file );
			}

			//Update File path, Attached File, GUID
			$meta = empty( $meta ) ? wp_get_attachment_metadata( $id ) : $meta;

			//Update File Path, Attached file, Mime Type for Image
			if ( 'full' == $size_k ) {
				if( ! empty( $meta ) ) {
					$new_file = str_replace( $upload_path, '', $new_file );
					$meta['file'] = $new_file;
				}
				//Update Attached File
				update_attached_file( $id, $meta['file'] );

				//Update Mime type
				wp_update_post( array(
						'ID'             => $id,
						'post_mime_type' => 'image/jpg'
					)
				);
			}else{
				$meta['sizes'][$size_k]['file'] = basename( $new_file ) ;
				$meta['sizes'][$size_k]['mime-type'] = 'image/jpg';
			}

			if( 'full' == $size_k ) {
				//Get the updated image URL
				$n_url = wp_get_attachment_url( $id );
			}else{
				$n_url = dirname( $o_url ) . '/' . basename( $new_file );
			}

			//Update In Post Content
			global $wpdb;
			$query = $wpdb->prepare( "UPDATE $wpdb->posts SET post_content = REPLACE(post_content, '%s', '%s');", $o_url, $n_url );
			$wpdb->query( $query );

			return $meta;
		}

		function update_stats( $id = '', $savings = '' ) {
			if ( empty( $id ) || empty( $savings ) ) {
				return false;
			}

		}

		/**
		 * Perform the conversion process, using WordPress Image Editor API
		 *
		 * @param $id Attachment Id
		 * @param $file Attachment File path
		 * @param $meta Attachment meta
		 * @param $size Image size, default empty for full image
		 *
		 * @return array
		 */
		function convert_to_jpg( $id = '', $file = '', $meta = '', $size = 'full' ) {

			$result = array(
				'meta'    => $meta,
				'savings' => ''
			);

			//Flag: Whether the image was converted or not
			if ( 'full' == $size ) {
				$result['converted'] = false;
			}

			//If any of the values is not set
			if ( empty( $id ) || empty( $file ) || empty( $meta ) ) {
				return $result;
			}

			$editor = wp_get_image_editor( $file );

			if ( is_wp_error( $editor ) ) {
				//Use custom method maybe
				return $result;
			}

			//Save PNG as JPG
			$new_image_info = $editor->save( $file, 'image/jpeg' );

			//If image editor was unable to save the image, return
			if ( is_wp_error( $new_image_info ) ) {
				return $result;
			}

			//Get the file size of original image
			$o_file_size = filesize( $file );

			$n_file      = path_join( dirname( $file ), $new_image_info['file'] );
			$n_file_size = filesize( $n_file );

			//If there aren't any savings return
			if ( $n_file_size >= $o_file_size ) {
				//Delete the JPG image and return
				@unlink( $n_file );

				return $result;
			}

			//Get the savings
			$savings = $o_file_size - $n_file_size;

			//@todo: Update the code below as per the image size
			//Update the File Details
			$meta = $this->update_image_path( $id, $file, $n_file, $meta, $size );

			//Store Stats
			$savings = array(
				'bytes'       => $savings,
				'size_before' => $o_file_size,
				'size_after'  => $n_file_size
			);

			$result['meta']    = $meta;
			$result['savings'] = $savings;

			if ( 'full' == $size ) {
				$result['converted'] = true;
			}

			return $result;
		}

		/**
		 * Convert a PNG to JPG Lossless conversion, if we have any savings
		 *
		 * @param $id
		 *
		 * @param $meta
		 */
		function png_to_jpg( $id, $meta ) {
			$file = get_attached_file( $id );

			/* Return If not PNG */

			//Get image mime type
			$mime = get_post_mime_type( $id );
			if ( 'image/png' != $mime ) {
				return $meta;
			}

			/** Return if Imagick is not available **/
			if ( ! class_exists( 'Imagick' ) || ! method_exists( 'Imagick', 'getImageAlphaChannel' ) ) {
				return $meta;
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
			 * @param string $size Image size beign converted
			 *
			 */
			if ( ! $should_convert = apply_filters( 'wp_smush_convert_to_jpg', $should_convert, $id, $file, 'full' ) ) {
				return $meta;
			}

			//Perform the conversion, and update path
			$result = $this->convert_to_jpg( $id, $file, $meta );

			$savings = $result['savings'];

			//If original image was converted and other sizes are there for the image, Convert all other image sizes
			if ( $result['converted'] ) {
				if ( ! empty( $meta['sizes'] ) ) {
					foreach ( $meta['sizes'] as $size_k => $data ) {
						//Perform the conversion
						if ( ! $should_convert = apply_filters( 'wp_smush_convert_to_jpg', $should_convert, $id, $file, $size_k ) ) {
							continue;
						}
						$s_file = path_join( dirname( $file ), $data['file'] );

						//Perform the conversion, and update path
						$result = $this->convert_to_jpg( $id, $s_file, $result['meta'], $size_k );

						//Add all the stats
						array_walk_recursive($result['savings'], function($item, $key) use (&$savings){
							$savings[$key] = isset($savings[$key]) ?  $item + $savings[$key] : $item;
						});
					}
				}

				//Update the Final Stats

				/**
				 * Do action, if the PNG to JPG conversion was successful
				 */
				do_action( 'wp_smush_png_jpg_converted', $id, $meta, $savings );
			}

			return $result['meta'];

		}
	}

	global $WpSmushPngtoJpg;
	$WpSmushPngtoJpg = new WpSmushPngtoJpg();
}