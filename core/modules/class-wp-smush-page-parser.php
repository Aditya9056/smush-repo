<?php
/**
 * Smush page parser that is used by CDN and Lazy load modules.
 *
 * @since 3.2.2
 * @package WP_Smush
 */

/**
 * Class WP_Smush_Page_Parser
 */
class WP_Smush_Page_Parser {

	/**
	 * CDN module status.
	 *
	 * @var bool $cdn
	 */
	private $cdn = false;

	/**
	 * Lazy load module status.
	 *
	 * @var bool $lazy_load
	 */
	private $lazy_load = false;

	/**
	 * WP_Smush_Page_Parser constructor.
	 *
	 * @since 3.2.2
	 */
	public function __construct() {
		// Start an output buffer before any output starts.
		add_action(
			'template_redirect',
			function () {
				ob_start( array( $this, 'parse_page' ) );
			},
			1
		);
	}

	/**
	 * Enable parser for selected module.
	 *
	 * @since 3.2.2
	 * @param string $module  Module ID.
	 */
	public function enable( $module ) {
		if ( ! in_array( $module, array( 'cdn', 'lazy_load' ), true ) ) {
			return;
		}

		$this->$module = true;
	}

	/**
	 * Disable parser for selected module.
	 *
	 * @since 3.2.2
	 * @param string $module  Module ID.
	 */
	public function disable( $module ) {
		if ( ! in_array( $module, array( 'cdn', 'lazy_load' ), true ) ) {
			return;
		}

		$this->$module = false;
	}

	/**
	 * Process images from current buffer content.
	 *
	 * Use DOMDocument class to find all available images in current HTML content and set attachment ID attribute.
	 *
	 * @since 3.0
	 * @since 3.2.2  Moved from WP_Smush_CDN.
	 *
	 * @param string $content Current buffer content.
	 *
	 * @return string
	 */
	public function parse_page( $content ) {
		// Do not parse page if CDN and Lazy load modules are disabled.
		if ( ! $this->cdn && ! $this->lazy_load ) {
			return $content;
		}

		/**
		 * Internal filter to disable page parsing.
		 *
		 * Because the page parser module is universal, we need to make sure that all modules have the ability to skip
		 * parsing of certain pages. For example, lazy loading should skip if_preview() pages. In order to achieve this
		 * functionality, I've introduced this filter. Filter priority can be used to overwrite the $skip param.
		 *
		 * @since 3.2.2
		 *
		 * @param bool $skip  Skip status.
		 */
		if ( empty( $content ) || apply_filters( 'wp_smush_should_skip_parse', false ) ) {
			return $content;
		}

		$images = self::get_images_from_content( $content );

		if ( empty( $images ) ) {
			return $content;
		}

		foreach ( $images[0] as $key => $image ) {
			$img_src   = $images['img_url'][ $key ];
			$new_image = $image;

			// Then update the image with correct CDN links.
			if ( $this->cdn ) {
				$new_image = WP_Smush::get_instance()->core()->mod->cdn->parse_image( $img_src, $new_image );
			}

			// First prepare for lazy-loading, as that does not require any URL rewrites.
			if ( $this->lazy_load ) {
				$new_image = WP_Smush::get_instance()->core()->mod->lazy->parse_image( $img_src, $new_image );
			}

			$content = str_replace( $image, $new_image, $content );
		}

		return $content;
	}

	/**
	 * Get image tags from page content.
	 *
	 * @since 3.1.0
	 * @since 3.2.0  Moved to WP_Smush_Content from WP_Smush_CDN
	 * @since 3.2.2  Moved to WP_Smush_Page_Parser from WP_Smush_Content
	 *
	 * @param string $content  Page content.
	 *
	 * @return array
	 */
	public static function get_images_from_content( $content ) {
		$images = array();

		if ( preg_match_all( '/(?:<img[^>]*?\s+?src=["|\'](?P<img_url>[^\s]+?)["|\'].*?>){1}/is', $content, $images ) ) {
			foreach ( $images as $key => $unused ) {
				// Simplify the output as much as possible, mostly for confirming test results.
				if ( is_numeric( $key ) && $key > 0 ) {
					unset( $images[ $key ] );
				}
			}
		}

		return $images;
	}

}
