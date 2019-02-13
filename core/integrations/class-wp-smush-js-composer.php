<?php
/**
 * Smush integration with WPBakery Page Builder: WP_Smush_JS_Composer class
 *
 * @package WP_Smush
 * @subpackage Admin/Integrations
 * @since 3.2.1
 *
 * @author Anton Vanyukov <anton@incsub.com>
 *
 * @copyright (c) 2018, Incsub (http://incsub.com)
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class WP_Smush_JS_Composer for WPBakery Page Builder integration.
 *
 * @since 3.2.1
 */
class WP_Smush_JS_Composer extends WP_Smush_Integration {

	/**
	 * WP_Smush_JS_Composer constructor.
	 *
	 * @since 3.2.1
	 */
	public function __construct() {
		$this->module   = 'js_builder';
		$this->class    = 'free';
		$this->priority = 10;

		$this->check_for_js_builder();

		// Hook at the end of setting row to output a error div.
		add_action( 'smush_setting_column_right_inside', array( $this, 'additional_notice' ) );

		parent::__construct();

		if ( $this->settings->get( 'js_builder' ) ) {
			add_filter( 'image_make_intermediate_size', array( $this, 'process_image_resize' ) );
		}
	}

	/**************************************
	 *
	 * OVERWRITE PARENT CLASS FUNCTIONALITY
	 */

	/**
	 * Filters the setting variable to add NextGen setting title and description
	 *
	 * @since 3.2.1
	 *
	 * @param array $settings Settings.
	 *
	 * @return mixed
	 */
	public function register( $settings ) {
		$settings[ $this->module ] = array(
			'label'       => esc_html__( 'Enable WPBakery Page Builder integration', 'wp-smushit' ),
			'short_label' => esc_html__( 'WPBakery Page Builder', 'wp-smushit' ),
			'desc'        => esc_html__( 'Allow smushing images resized in WPBakery Page Builder editor.', 'wp-smushit' ),
		);

		return $settings;
	}

	/**
	 * Show additional notice if the required plugins are not installed.
	 *
	 * @since 3.2.1
	 *
	 * @param string $name  Setting name.
	 */
	public function additional_notice( $name ) {
		if ( 'js_builder' === $name && ! $this->enabled ) {
			?>
			<div class="sui-notice sui-notice-sm">
				<p>
					<?php
					esc_html_e( 'To use this feature you need to install and activate WPBakery Page Builder.', 'wp-smushit' );
					?>
				</p>
			</div>
			<?php
		}
	}

	/**************************************
	 *
	 * PUBLIC CLASSES
	 */

	/**
	 * Check if the file source is a registered attachment and if not - Smush it.
	 *
	 * TODO: with little adjustments this can be used for all page builders.
	 *
	 * @since 3.2.1
	 *
	 * @param string $image_src  Image src.
	 */
	public function process_image_resize( $image_src ) {
		$vc_editable = filter_input( INPUT_GET, 'vc_editable', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		$vc_action   = filter_input( INPUT_POST, 'action', FILTER_SANITIZE_STRING );

		if ( ! $vc_editable || 'vc_load_shortcode' !== $vc_action ) {
			return;
		}

		// Save the original image source.
		$vc_image = $image_src;

		// Remove the [width]x[height] params from URL.
		$size = array();
		if ( preg_match( '/(\d+)x(\d+)\.(?:' . implode( '|', array( 'gif', 'jpg', 'jpeg', 'png' ) ) . '){1}$/i', $image_src, $size ) ) {
			$image_src = str_replace( '-' . $size[1] . 'x' . $size[2], '', $image_src );
		}

		// Convert image src to URL.
		$upload_dir = wp_get_upload_dir();
		$image_url  = str_replace( $upload_dir['path'], $upload_dir['url'], $image_src );

		// Try to get the attachment ID.
		$attachment_id = attachment_url_to_postid( $image_url );

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		$image = image_get_intermediate_size( $attachment_id, array( $size[1], $size[2] ) );

		if ( $image ) {
			return;
		}

		// Smush image. TODO: should we update the stats?
		$smush_results = WP_Smush::get_instance()->core()->mod->smush->do_smushit( $vc_image );
	}

	/**************************************
	 *
	 * PRIVATE CLASSES
	 */

	/**
	 * Should only be active when WPBakery Page Builder is installed.
	 *
	 * @since 3.2.1
	 */
	private function check_for_js_builder() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$this->enabled = defined( 'WPB_VC_VERSION' ) && is_plugin_active( 'js_composer/js_composer.php' );
	}

}
