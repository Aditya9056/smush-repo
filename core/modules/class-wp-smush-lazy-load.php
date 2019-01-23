<?php
/**
 * Lazy load images class: WP_Smush_Lazy_Load
 *
 * @since 3.2.0
 * @package WP_Smush
 */

/**
 * Class WP_Smush_Lazy_Load
 */
class WP_Smush_Lazy_Load extends WP_Smush_Content {

	/**
	 * Lazy-loading settings.
	 *
	 * @since 3.2.0
	 * @var array $settings
	 */
	//private $settings;

	/**
	 * Initialize module actions.
	 *
	 * @since 3.2.0
	 */
	public function init() {
		// Only run on front end and if lazy-loading is enabled.
		if ( is_admin() || ! $this->settings->get( 'lazy_load' ) ) {
			return;
		}

		//$this->settings = $this->settings->get_setting( WP_SMUSH_PREFIX . 'lazy_load' );

		// Enabled without settings? Don't think so... Exit.
		if ( ! $this->settings ) {
			return;
		}

		// Load js file that is required in public facing pages.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Allow lazy load attributes in img tag.
		add_filter( 'wp_kses_allowed_html', array( $this, 'add_lazy_load_attributes' ) );

		// Filter images.
		add_filter( 'the_content', array( $this, 'set_lazy_load_attributes' ), 100 );
		add_filter( 'post_thumbnail_html', array( $this, 'set_lazy_load_attributes' ), 100 );
		add_filter( 'get_avatar', array( $this, 'set_lazy_load_attributes' ), 100 );
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

		foreach ( $images[0] as $key => $image ) {
			$new_image = $image;

			$this->remove_attribute( $new_image, 'src' );
			$this->add_attribute( $new_image, 'data-src', $images['img_url'][ $key ] );

			// Change srcset to data-srcset attribute.
			$new_image = preg_replace( '/<img(.*?)(srcset=)(.*?)>/i', '<img$1data-$2$3>', $new_image );
			// Add .lazy-load class to image that already has a class.
			$new_image = preg_replace( '/<img(.*?)class=\"(.*?)\"(.*?)>/i', '<img$1class="$2 lazy-load"$3>', $new_image );
			// Add .lazy-load class to image that doesn't have a class.
			$new_image = preg_replace( '/<img(.*?)(?!\bclass\b)(.*?)/i', '<img$1 class="lazy-load"$2', $new_image );

			// Use noscript element in HTML to load elements normally when JavaScript is disabled in browser.
			$new_image .= '<noscript>' . $image . '</noscript>';

			$content = str_replace( $image, $new_image, $content );
		}

		return $content;
	}

}
