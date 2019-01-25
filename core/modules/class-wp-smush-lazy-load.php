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
	private $options;

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

		$this->options = $this->settings->get_setting( WP_SMUSH_PREFIX . 'lazy_load' );

		// Enabled without settings? Don't think so... Exit.
		if ( ! $this->options ) {
			return;
		}

		// Load js file that is required in public facing pages.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Allow lazy load attributes in img tag.
		add_filter( 'wp_kses_allowed_html', array( $this, 'add_lazy_load_attributes' ) );

		// Filter images.
		if ( isset( $this->options['output']['content'] ) && $this->options['output']['content'] ) {
			add_filter( 'the_content', array( $this, 'set_lazy_load_attributes' ), 100 );
		}
		if ( isset( $this->options['output']['thumbnails'] ) && $this->options['output']['thumbnails'] ) {
			add_filter( 'post_thumbnail_html', array( $this, 'set_lazy_load_attributes' ), 100 );
		}
		if ( isset( $this->options['output']['gravatars'] ) && $this->options['output']['gravatars'] ) {
			add_filter( 'get_avatar', array( $this, 'set_lazy_load_attributes' ), 100 );
		}
		if ( isset( $this->options['output']['widgets'] ) && $this->options['output']['widgets'] ) {
			add_filter( 'widget_text', array( $this, 'set_lazy_load_attributes' ), 100 );
		}
	}

	/**
	 * Enqueue JS files required in public pages.
	 *
	 * @since 3.2.0
	 */
	public function enqueue_assets() {
		$in_footer = isset( $this->options['footer'] ) ? $this->options['footer'] : true;

		wp_enqueue_script(
			'smush-lazy-load',
			WP_SMUSH_URL . 'app/assets/js/smush-lazy-load.min.js',
			array(),
			WP_SMUSH_VERSION,
			$in_footer
		);

// //wp.local/wp-content/plugins/wp-smushit/app/assets/images/loading.gif
// http://wp.local/wp-content/plugins/a3-lazy-load/assets/css/loading.gif
		$styles = '
			.lazy-hidden {
				background-color: #ffffff;
				background-image: url("http://wp.local/wp-content/plugins/a3-lazy-load/assets/css/loading.gif");
				background-repeat: no-repeat;
				background-position: 50% 50%;
				/*background: #fff url("http://wp.local/wp-content/plugins/a3-lazy-load/assets/css/loading.gif") no-repeat 50% 50%;*/
			}
			figure.wp-block-image img.lazy-hidden {
				min-width: 150px;
			}
		';
		wp_add_inline_style(
			'dashicons',
			$styles
		);
	}

	/**
	 * Make sure WordPress does not filter out img elements with lazy load attributes.
	 *
	 * @since 3.2.0
	 *
	 * @param array $allowedposttags  Allowed post tags.
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
	 * @param string $content  Page/block content.
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
			/**
			 * Check if some image formats are excluded.
			 */
			if ( in_array( false, $this->options['format'], true ) ) {
				$ext = strtolower( pathinfo( $images['img_url'][ $key ], PATHINFO_EXTENSION ) );
				$ext = 'jpg' === $ext ? 'jpeg' : $ext;

				if ( isset( $this->options['format'][ $ext ] ) && ! $this->options['format'][ $ext ] ) {
					continue;
				}
			}

			$new_image = $image;

			$this->remove_attribute( $new_image, 'src' );
			$this->add_attribute( $new_image, 'data-src', $images['img_url'][ $key ] );

			// Change srcset to data-srcset attribute.
			$new_image = preg_replace( '/<img(.*?)(srcset=)(.*?)>/i', '<img$1data-$2$3>', $new_image );
			// Add .lazy-load class to image that already has a class.
			$new_image = preg_replace( '/<img(.*?)class=\"(.*?)\"(.*?)>/i', '<img$1class="$2 lazy-load lazy-hidden"$3>', $new_image );
			// Add .lazy-load class to image that doesn't have a class.
			$new_image = preg_replace( '/<img(.*?)(?!\bclass\b)(.*?)/i', '<img$1 class="lazy-load lazy-hidden"$2', $new_image );

			// Use noscript element in HTML to load elements normally when JavaScript is disabled in browser.
			if ( isset( $this->options['noscript'] ) && $this->options['noscript'] ) {
				$new_image .= '<noscript>' . $image . '</noscript>';
			}

			$content = str_replace( $image, $new_image, $content );
		}

		return $content;
	}

}
