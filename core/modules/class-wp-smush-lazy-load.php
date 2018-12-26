<?php
/**
 * Lazy load images class: WP_Smush_Lazy_Load
 *
 * @since 3.2
 * @package WP_Smush
 */

/**
 * Class WP_Smush_Lazy_Load
 */
class WP_Smush_Lazy_Load extends WP_Smush_Content {

	/**
	 * Initialize module actions.
	 *
	 * @since 3.2.0
	 */
	public function init() {
		// Only run on front end.
		if ( is_admin() ) {
			return;
		}

		// Load js file that is required in public facing pages.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Allow lazy load attributes in img tag.
		add_filter( 'wp_kses_allowed_html', array( $this, 'add_lazy_load_attributes' ) );

		// Filter images.
		add_filter( 'the_content', array( $this, 'set_lazy_load_attributes' ), 100 );
	}

	/**
	 * Enqueue JS files required in public pages.
	 *
	 * @since 3.2.0
	 */
	public function enqueue_assets() {
		wp_enqueue_script(
			'smush-lazy-load',
			WP_SMUSH_URL . 'app/assets/js/smush-lazy-load.min.js',
			array(),
			WP_SMUSH_VERSION,
			true
		);
	}

	/**
	 * Make sure WordPress does not filter out img elements with lazy load attributes.
	 *
	 * @since 3.2.0
	 *
	 * @param array $allowedposttags
	 *
	 * @return mixed
	 */
	public function add_lazy_load_attributes( $allowedposttags ) {
		if ( ! isset( $allowedposttags['img'] ) ) {
			return $allowedposttags;
		}

		$smush_attributes = array(
			'data-src'    => true,
			'data-srcset' => true,
		);

		$img_attributes = array_merge( $allowedposttags['img'], $smush_attributes );

		$allowedposttags['img'] = $img_attributes;

		return $allowedposttags;
	}

	/**
	 * Process images from content and add appropriate lazy load attributes.
	 *
	 * @since 3.2.0
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function set_lazy_load_attributes( $content ) {
		// Don't lazy load for feeds, previews.
		if ( is_feed() || is_preview() ) {
			return $content;
		}

		// Avoid conflicts if attributes are set (another plugin, for example).
		if ( false !== strpos( $content, 'data-src' ) ) {
			return $content;
		}

		$images = $this->get_images_from_content( $content );

		if ( empty( $images ) ) {
			return $content;
		}

		// TODO: Use noscript element in HTML to load elements normally when JavaScript is disabled in browser.
		foreach ( $images[0] as $key => $image ) {
			$new_image = $image;
			$this->add_attribute( $new_image, 'data-src', $images['img_url'][ $key ] );
			$content = str_replace( $image, $new_image, $content );
		}

		return $content;
	}

}
