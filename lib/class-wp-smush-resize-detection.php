<?php
/**
 * @package WP Smush
 * @subpackage CDN
 * @version 2.7
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2018, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmushResizeDetection' ) ) {

	class WpSmushResizeDetection {

		/**
		 * Is auto detection and resize enabled.
		 *
		 * @var bool
		 */
		protected $detection_enabled = false;

		/**
		 * Can auto resize.
		 *
		 * @var bool
		 */
		protected $can_auto_resize = false;

		/**
		 * WpSmushResizeDetection constructor.
		 */
		public function __construct() {

			// Set auto resize flag.
			add_action( 'wp', array( $this, 'can_auto_resize' ) );

			// Load js file that is required in public facing pages.
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_resize_assets' ) );

			// Add new admin bar item on front end.
			add_action( 'admin_bar_menu', array( $this, 'resize_detection_button' ), 99 );

			// Start an output buffer before any output starts.
			add_action( 'template_redirect', array( $this, 'process_buffer' ), 1 );

			// Handle auto resize request.
			add_action( 'wp_ajax_smush_auto_resize', array( $this, 'smush_auto_resize' ) );
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

			if ( ! $this->can_auto_resize ) {
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
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
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

			if ( ! $this->can_auto_resize ) {
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
					'value' => $file,
					'compare' => 'LIKE',
					'key' => '_wp_attachment_metadata',
				);
			}

			// Set meta relation as OR.
			$mata_values['relation'] = 'OR';
			// Set query arguments.
			$query_args = array(
				'post_type' => 'attachment',
				'post_status' => 'inherit',
				'fields' => 'ids',
				'meta_query' => array(
					'relation' => 'AND',
					$mata_values,
					array(
						'key' => 'smush_auto_resize',
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
					$common = array_intersect( array_keys( $files ), $cropped_image_files );
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
		 * Starts an output buffer and register the callback function.
		 *
		 * Register callback function that adds attachment ids of images
		 * those are from media library and has an attachment id.
		 *
		 * @uses ob_start()
		 *
		 * @return void
		 */
		public function process_buffer() {

			ob_start( array( $this, 'process_images' ) );
		}

		/**
		 * Process images from current buffer content.
		 *
		 * Use DOMDocument class to find all available images
		 * in current HTML content and set attachmet id attribute.
		 *
		 * @param string $content Current buffer content.
		 *
		 * @return string
		 */
		public function process_images( $content ) {

			if ( ! $this->can_auto_resize ) {
				return $content;
			}

			$content = mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' );
			$document = new \DOMDocument();
			libxml_use_internal_errors( true );
			$document->loadHTML( utf8_decode( $content ) );

			// Get images from current DOM elements.
			$images = $document->getElementsByTagName( 'img' );
			// If images found, set attachment ids.
			if ( ! empty( $images ) ) {
				$this->set_attachment_ids( $images );
			}

			return $document->saveHTML();
		}

		/**
		 * Handle auto resize ajax request for an attachment.
		 *
		 * @return json
		 */
		public function smush_auto_resize() {

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
		public function can_auto_resize() {

			global $wpsmush_settings;

			// We need smush settings.
			$wpsmush_settings->init_settings();

			$this->detection_enabled = (bool) $wpsmush_settings->settings['detection'];

			// Only required for admin users and when auto detection is required..
			if ( $this->detection_enabled && current_user_can( 'manage_options' ) ) {
				$this->can_auto_resize = true;
			}

			return $this->can_auto_resize;
		}
	}

	global $wpsmush_resize_detection;

	$wpsmush_resize_detection = new WpSmushResizeDetection();
}