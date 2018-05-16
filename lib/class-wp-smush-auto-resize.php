<?php
/**
 * @package WP Smush
 * @subpackage AutoResize
 * @version 2.7
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2018, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmushAutoResize' ) ) {

	/**
	 * Class WpSmushAutoResize
	 *
	 * Reference: EWWW Optimizer.
	 */
	class WpSmushAutoResize {

		/**
		 * Is auto detection enabled.
		 *
		 * @var bool
		 */
		var $can_auto_detect = false;

		/**
		 * Can auto resize.
		 *
		 * @var bool
		 */
		var $can_auto_resize = false;

		/**
		 * These are the supported file extensions.
		 *
		 * @var array
		 */
		var $supported_extensions = array(
			'gif',
			'jpg',
			'jpeg',
			'png',
		);

		/**
		 * WpSmushAutoResize constructor.
		 */
		public function __construct() {

			// Set auto resize flag.
			add_action( 'init', array( $this, 'init_flags' ) );

			// Load js file that is required in public facing pages.
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_resize_assets' ) );

			// Add new admin bar item on front end.
			add_action( 'admin_bar_menu', array( $this, 'resize_detection_button' ), 99 );

			// Handle auto resize request.
			add_action( 'wp_ajax_smush_auto_resize', array( $this, 'set_smush_auto_resize' ) );

			// Update responsive image srcset if required.
			add_filter( 'wp_calculate_image_srcset', array( $this, 'update_image_srcset' ), 1001, 5 );

			// Set attachment ids to media library images.
			add_action( 'smush_images_from_content', array( $this, 'set_attachment_ids' ) );
		}

		/**
		 * Enqueque JS files required in public pages.
		 *
		 * Enque resize detection js and css files to public
		 * facing side of the site. Load only if auto detect
		 * is enabled.
		 *
		 * @return void
		 */
		public function enqueue_resize_assets() {

			if ( ! $this->can_auto_detect ) {
				return;
			}

			// Required scripts for front end.
			wp_enqueue_script(
				'smush-resize-detection',
				plugins_url( 'assets/shared-ui-2/js/resize-detection.min.js', __DIR__ ),
				array( 'jquery' ),
				null,
				true
			);

			// Required styles for front end.
			wp_enqueue_style(
				'smush-resize-detection',
				plugins_url( 'assets/shared-ui-2/css/resize-detection.min.css', __DIR__ )
			);

			// Define ajaxurl var.
			wp_localize_script( 'smush-resize-detection', 'wp_smush_resize_vars', array(
				'ajaxurl'    => admin_url( 'admin-ajax.php' ),
				'ajax_nonce' => wp_create_nonce( 'smush_resize_nonce' ),
			) );
		}

		/**
		 * Add a new link to admin bar to detect wrong sized images.
		 *
		 * Clicking on this link will show scaled images as highlighted.
		 *
		 * @return void
		 */
		public function resize_detection_button() {

			if ( ! $this->can_auto_detect ) {
				return;
			}

			global $wp_admin_bar;

			$wp_admin_bar->add_menu( array(
				'id'     => 'smush-resize-detection',
				'parent' => 'top-secondary',
				'title'  => __( 'Detect Images', 'wp-smushit' ),
			) );
		}

		/**
		 * Set attachment IDs of images as data.
		 *
		 * Get attachment ids from urls and set new data
		 * property to img.
		 * We can use WP_Query to find attachment ids of
		 * all images on current page content.
		 *
		 * @param array $images Current page images.
		 *
		 * @return void
		 */
		public function set_attachment_ids( $images ) {

			$dir = wp_upload_dir();
			$mata_values = $files = array();

			// Loop through each image.
			foreach ( $images as $key => $image ) {
				// Get the src value.
				$src = $image->getAttribute( 'src' );
				// No need to continue if attachment id is already set.
				if ( $image->getAttribute( 'data-attachment-id' ) ) {
					continue;
				}
				// Make sure this image is inside upload directory.
				if ( false === strpos( $src, $dir['baseurl'] . '/' ) ) {
					continue;
				}

				$file = basename( $src );
				// Add to files array.
				$files[ $file ] = $image;
				// Set meta query for current image.
				$mata_values[] = array(
					'value'   => $file,
					'compare' => 'LIKE',
					'key'     => '_wp_attachment_metadata',
				);
			}

			// Set meta relation as OR.
			$mata_values['relation'] = 'OR';
			// Set query arguments.
			$query_args = array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'fields'      => 'ids',
				'meta_query'  => array(
					'relation' => 'AND',
					$mata_values,
					array(
						'key'     => 'smush_auto_resize',
						'compare' => 'NOT EXISTS',
					)
				),
			);

			// Use WP_Query to find attachments.
			$query = new WP_Query( $query_args );
			if ( $query->have_posts() ) {
				foreach ( $query->posts as $post_id ) {
					// Get all metas of the attachment.
					$meta = wp_get_attachment_metadata( $post_id );
					// Get original file.
					$original_file = basename( $meta['file'] );
					// Get cropped versions of the attachment.
					$cropped_image_files = wp_list_pluck( $meta['sizes'], 'file' );
					$common              = array_intersect( array_keys( $files ), $cropped_image_files );
					// If current image is found in our files.
					if ( ! empty( $files[ $original_file ] ) ) {
						$files[ $original_file ]->setAttribute( 'data-attachment-id', $post_id );
					} elseif ( ! empty( $common ) ) {
						// If our file is found inside cropped versions of this attachment.
						$files[ $common[0] ]->setAttribute( 'data-attachment-id', $post_id );
					}
				}
			}
		}

		/**
		 * Handle auto resize ajax request for an attachment.
		 *
		 * @return json
		 */
		public function set_smush_auto_resize() {

			// We need attachment id.
			if ( ! isset( $_GET['attachment_id'] ) ) {
				wp_send_json_error( __( 'No attachment ID was provided.', 'wp-smushit' ) );
			}

			// Security check.
			check_ajax_referer( 'smush_resize_nonce', 'resize_nonce' );

			// Set new meta to mark this attachment can be auto resized.
			if ( update_post_meta( $_GET['attachment_id'], 'smush_auto_resize', true ) ) {
				wp_send_json_success();
			} else {
				wp_send_json_error( __( 'Unable to set auto resizing for this image.', 'wp-smushit' ) );
			}
		}

		/**
		 * Check if auto resize can be performed.
		 *
		 * Allow only if current user is admin and auto resize
		 * detection is enabled in settings.
		 *
		 * @return bool
		 */
		private function init_flags() {

			global $wpsmush_settings, $WpSmush, $wpsmush_cdn;

			// All these are members only feature.
			if ( ! $WpSmush->validate_install() ) {
				return;
			}

			// We need smush settings.
			$wpsmush_settings->init_settings();

			// Check if auto detection is enabled.
			$this->detection_enabled = (bool) $wpsmush_settings->settings['detection'];

			// Only required for admin users.
			if ( $this->detection_enabled && current_user_can( 'manage_options' ) ) {
				$this->can_auto_detect = true;
			}

			// @todo add other checks if required.
			if ( $wpsmush_cdn->cdn_active && $this->detection_enabled ) {
				$this->can_auto_resize = true;
			}
		}

		/**
		 * Filters an array of image srcset values, replacing each URL with resized CDN urls.
		 *
		 * Keep the existing srcset sizes if already added by WP, then calculate extra sizes
		 * if required.
		 *
		 * @param array $sources An array of image urls and widths.
		 * @param array $size_array Array of width and height values in pixels.
		 * @param string $image_src The src of the image.
		 * @param array $image_meta The image metadata.
		 * @param int $attachment_id Image attachment ID.
		 *
		 * @return array $sources
		 */
		public function update_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id = 0 ) {

			global $wpsmush_cdn;

			// Loop through each image.
			foreach ( $sources as $i => $source ) {

				$img_url = $source['url'];

				// @todo Validate image before continue.

				// Filter to skip a single image.
				if ( apply_filters( 'smush_auto_resize_skip', false, $img_url, $source ) ) {
					continue;
				}

				// Set https if required.
				if ( 0 === strpos( $img_url, '//' ) ) {
					$img_url = ( is_ssl() ? 'https:' : 'http:' ) . $img_url;
				}

				list( $width, $height ) = $this->get_size_from_file_name( $img_url );

				$args = array(
					'size' => $width . ',' . $height,
				);

				$sources[ $i ]['url'] = $wpsmush_cdn->generate_cdn_url( $source['url'], $args );
			}

			return $sources;
		}

		/**
		 * Try to determine height and width from strings WP appends to resized image filenames.
		 *
		 * @param string $src The image URL.
		 *
		 * @return array An array consisting of width and height.
		 */
		private function get_size_from_file_name( $src ) {

			$size = array();

			if ( preg_match( '#-(\d+)x(\d+)(@2x)?\.(?:' . implode( '|', $this->supported_extensions ) . '){1}(?:\?.+)?$#i', $src, $size ) ) {
				// Get size and width.
				$width  = (int) isset( $size[1] ) ? $size[1] : 0;
				$height = (int) isset( $size[2] ) ? $size[2] : 0;

				// Handle retina images.
				if ( strpos( $src, '@2x' ) ) {
					$width  = 2 * $width;
					$height = 2 * $height;
				}

				// Return width and height as array.
				if ( $width && $height ) {
					return array( $width, $height );
				}
			}

			return array( false, false );
		}
	}

	global $wpsmush_auto_resize;

	$wpsmush_auto_resize = new WpSmushAutoResize();
}