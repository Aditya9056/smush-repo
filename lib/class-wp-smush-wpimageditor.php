<?php

/**
 * @package WP Smush
 * @subpackage WP Image Editor
 * @version 1.0
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2015, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmush_WPImageEditorGD' ) && class_exists('WP_Image_Editor_GD') ) {
	class WpSmush_WPImageEditorGD extends WP_Image_Editor_GD {
		public function save( $filename = null, $mime_type = null ) {
			//Call the parent save function and get the data returned
			$image = parent::save( $filename, $mime_type );
			//Check for WP Error
			if( !is_wp_error( $image ) ) {
				global $WpSmush;

				//Try to get image id
				$image_id = !empty( $_REQUEST['postid'] ) ? absint( (int ) $_REQUEST['postid'] ) : '';

				//Check for auto smush
				if( $WpSmush->is_auto_smush_enabled() ) {

				}else{
					//Delete metadata for image id, to allow manual smushing

				}
			}
		}
	}
}
if ( ! class_exists( 'WpSmush_WPImageEditorImagick' ) && class_exists('WP_Image_Editor_Imagick') ) {
	class WpSmush_WPImageEditorImagick extends WP_Image_Editor_Imagick {
		public function save( $destfilename = null, $mime_type = null ) {
			//Call the parent save function and get the data returned
			$image = parent::save( $destfilename, $mime_type );
			//Check for WP Error
			if( !is_wp_error( $image ) ) {
				global $WpSmush;
				echo "<pre>Filename parent class";
				print_r( $destfilename );
				echo "</pre>";
				echo "<pre>Image Object parent class";
				print_r( $this->image );
				echo "</pre>";
				//Check for auto smush
				if( $WpSmush->is_auto_smush_enabled() ) {

				}else{
					//Delete metadata for image id, to allow manual smushing

				}
			}
		}
	}
}