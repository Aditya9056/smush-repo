<?php
/**
 * CDN class: WP_Smush_CDN
 *
 * @package WP_Smush
 * @version 3.0
 */

/**
 * Class WP_Smush_CDN
 */
class WP_Smush_CDN extends WP_Smush_Module {

	/**
	 * Smush CDN base url.
	 *
	 * @var null|string
	 */
	private $cdn_base = null;

	/**
	 * Flag to check if CDN is active.
	 *
	 * @var bool
	 */
	private $cdn_active = false;

	/**
	 * WP_Smush_CDN constructor.
	 */
	public function init() {
		// Filters the setting variable to add module setting title and description.
		add_filter( 'wp_smush_settings', array( $this, 'register' ) );

		// Add settings descriptions to the meta box.
		add_action( 'smush_setting_column_right_inside', array( $this, 'settings_desc' ), 10, 2 );

		// Add setting names to appropriate group.
		add_action( 'wp_smush_cdn_settings', array( $this, 'add_settings' ) );

		// Set auto resize flag.
		add_action( 'wp', array( $this, 'init_flags' ) );

		// Add stats to stats box.
		if ( $this->settings->get( 'cdn' ) ) {
			add_action( 'stats_ui_after_resize_savings', array( $this, 'cdn_stats_ui' ), 20 );
		}

		// Set Smush API config.
		add_action( 'init', array( $this, 'set_cdn_url' ) );

		// Start an output buffer before any output starts.
		add_action( 'template_redirect', array( $this, 'process_buffer' ), 1 );

		// Add cdn url to dns prefetch.
		add_filter( 'wp_resource_hints', array( $this, 'dns_prefetch' ), 99, 2 );
	}

	/**
	 * Get CDN status.
	 *
	 * @since 3.0
	 */
	public function get_status() {
		return $this->cdn_active;
	}

	/**
	 * Add setting names to the appropriate group.
	 *
	 * @since 3.0
	 *
	 * @return array
	 */
	public function add_settings() {
		return array(
			'auto',
			'lossy',
			'strip_exif',
			'png_to_jpg',
			'webp',
		);
	}

	/**
	 * Add settings to settings array.
	 *
	 * @since 3.0
	 *
	 * @param array $settings  Current settings array.
	 *
	 * @return array
	 */
	public function register( $settings ) {
		return array_merge(
			$settings,
			array(
				'webp' => array(
					'label'       => __( 'Enable WebP conversion', 'wp-smushit' ),
					'short_label' => __( 'WebP conversion', 'wp-smushit' ),
					'desc'        => __( 'Smush can automatically convert and serve your images as WebP to compatible browsers.', 'wp-smushit' ),
				),
			)
		);
	}

	/**
	 * Show additional descriptions for settings.
	 *
	 * @since 3.0
	 *
	 * @param string $setting_key Setting key.
	 */
	public function settings_desc( $setting_key = '' ) {
		if ( empty( $setting_key ) || ! 'webp' !== $setting_key ) {
			return;
		}
		?>
		<span class="sui-description sui-toggle-description" id="<?php echo esc_attr( WP_SMUSH_PREFIX . $setting_key . '-desc' ); ?>">
			<?php
			switch ( $setting_key ) {
				case 'webp':
					esc_html_e(
						'Note: We’ll detect and serve WebP images to browsers that will accept them by checking
					Accept Headers, and gracefully fall back to normal PNGs or JPEGs for non-compatible
					browsers.',
						'wp-smushit'
					);
					break;
				case 'default':
					break;
			}
			?>
		</span>
		<?php
	}

	/**
	 * Add CDN stats to stats meta box.
	 *
	 * @since 3.0
	 */
	public function cdn_stats_ui() {
		?>
		<li class="smush-cdn-stats">
			<span class="sui-list-label"><?php esc_html_e( 'CDN', 'wp-smushit' ); ?></span>
			<span class="wp-smush-stats sui-list-detail">
				<i class="sui-icon-loader sui-loading sui-hidden" aria-hidden="true" title="<?php esc_attr_e( 'Updating Stats', 'wp-smushit' ); ?>"></i>
				<span class="wp-smush-cdn-stats">0 KB</span>
				<span class="wp-smush-stats-sep">/</span>
				<span class="wp-smush-cdn-usage">0% used</span>
				<div class="sui-circle-score" data-score="10"></div>
			</span>
		</li>
		<?php
	}





















	/**
	 * Initialize required flags.
	 *
	 * @return void
	 */
	public function init_flags() {
		// All these are members only feature.
		if ( ! WP_Smush::is_pro() ) {
			return;
		}

		$this->settings = WP_Smush_Settings::get_instance();

		if ( ! $this->settings->get( 'cdn' ) ) {
			return;
		}

		$this->cdn_active = true;
	}

	/**
	 * Set the API base for the member.
	 *
	 * @return void
	 */
	public function set_cdn_url() {
		$cdn = $this->settings->get_setting( WP_SMUSH_PREFIX . 'cdn_status' );

		// Site id to help mapping multisite installations.
		$site_id = get_current_blog_id();

		// This is member's custom cdn path.
		$this->cdn_base = trailingslashit( "https://{$cdn->endpoint_url}/{$site_id}" );
	}

	/**
	 * Generate CDN url from given image url.
	 *
	 * @param string $src Image url.
	 * @param array  $args Query parameters.
	 *
	 * @return string
	 */
	public function generate_cdn_url( $src, $args = array() ) {
		// Do not continue incase we try this when cdn is disabled.
		if ( ! $this->cdn_active ) {
			return $src;
		}

		// Parse url to get all parts.
		$url_parts = parse_url( $src );

		// If path not found, do not continue.
		if ( empty( $url_parts['path'] ) ) {
			return $src;
		}

		// Arguments for CDN.
		$pro_args = array(
			'lossy' => WP_Smush::get_instance()->core()->mod->smush->lossy_enabled ? 1 : 0,
			'strip' => WP_Smush::get_instance()->core()->mod->smush->keep_exif ? 0 : 1,
			'webp'  => 0,
		);

		$args = wp_parse_args( $pro_args, $args );

		// Replace base url with cdn base.
		$url = $this->cdn_base . ltrim( $url_parts['path'], '/' );

		// Now we need to add our CDN parameters for resizing.
		$url = add_query_arg( $args, $url );

		return $url;
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
		ob_start( array( $this, 'process_img_tags' ) );
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
	public function process_img_tags( $content ) {
		$content  = mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' );
		$document = new DOMDocument();
		libxml_use_internal_errors( true );
		$document->loadHTML( utf8_decode( $content ) );

		// Get images from current DOM elements.
		$images = $document->getElementsByTagName( 'img' );

		// If images found, set attachment ids.
		if ( ! empty( $images ) ) {
			/**
			 * Action hook to modify DOM images.
			 *
			 * Images are saved at the end of this function. So no need
			 * to return anything in this hook.
			 */
			do_action( 'smush_images_from_content', $images );

			$this->process_images( $images );
		}

		return $document->saveHTML();
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
	public function process_images( $images ) {
		$dir = wp_upload_dir();

		// Loop through each image.
		foreach ( $images as $key => $image ) {
			// Get the src value.
			$src = $image->getAttribute( 'src' );

			// Make sure this image is inside upload directory.
			if ( false === strpos( $src, $dir['baseurl'] . '/' ) ) {
				continue;
			}

			/**
			 * Filter to skip a single image from cdn.
			 *
			 * @param bool false Should skip?
			 * @param string $img_url Image url.
			 * @param array|bool $image Image object or false.
			 */
			if ( apply_filters( 'smush_skip_image_from_cdn', false, $src, $image ) ) {
				continue;
			}

			/**
			 * Filter hook to alter image src arguments before going through cdn.
			 *
			 * @param array $args Arguments.
			 * @param string $src Image src.
			 * @param object $image Image tag object.
			 */
			$args = apply_filters( 'smush_image_cdn_args', array(), $image );

			/**
			 * Filter hook to alter image src before going through cdn.
			 *
			 * @param string $src Image src.
			 * @param object $image Image tag object.
			 */
			$src = apply_filters( 'smush_image_src_before_cdn', $src, $image );

			// Do not continue if CDN is not active.
			if ( $this->cdn_active ) {

				// Generate cdn url from local url.
				$src = $this->generate_cdn_url( $src, $args );

				/**
				 * Filter hook to alter image src after replacing with CDN base.
				 *
				 * @param string $src Image src.
				 * @param object $image Image tag object.
				 */
				$src = apply_filters( 'smush_image_src_after_cdn', $src, $image );
			}

			// Update src with cdn url.
			$image->setAttribute( 'src', $src );
		}
	}

	/**
	 * Add CDN url to header for better speed.
	 *
	 * @param array  $urls URLs to print for resource hints.
	 * @param string $relation_type The relation type the URLs are printed.
	 *
	 * @return array
	 */
	public function dns_prefetch( $urls, $relation_type ) {
		// Add only if CDN active.
		if ( 'dns-prefetch' === $relation_type && $this->cdn_active && ! empty( $this->cdn_base ) ) {
			$urls[] = $this->cdn_base;
		}

		return $urls;
	}
}
