<?php
/**
 * @package WP Smush
 * @subpackage S3
 * @version 2.6.4
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2017, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmushS3' ) ) {

	class WpSmushS3 {

		private $setup_error = '';

		function __construct() {
			$this->init();
		}

		function init() {

			global $WpSmush;

			//Filters the setting variable to add S3 setting title and description
			add_filter( 'wp_smush_settings', array( $this, 'register' ), 6 );

			//Filters the setting variable to add S3 setting in premium features
			add_filter( 'wp_smush_pro_settings', array( $this, 'add_setting' ), 6 );

			//return if not a pro user
			if ( ! $WpSmush->validate_install() ) {
				return;
			}
			$this->check_client();

		}

		/**
		 * Filters the setting variable to add S3 setting title and description
		 *
		 * @param $settings
		 *
		 * @return mixed
		 */
		function register( $settings ) {
			$plugin_url = esc_url( "https://wordpress.org/plugins/amazon-s3-and-cloudfront/");
			$settings['s3'] = array(
				'label' => esc_html__( 'Amazon S3 support', 'wp-smushit' ),
				'desc'  => sprintf( esc_html__( 'Optimise your images stored on Amazon S3. This feature uses the %sWP Offload S3%s plugin and is needed if the option %sRemove Files From Server%s is enabled. Images will be smushed as per your current settings.', 'wp-smushit' ), "<a href='". $plugin_url ."' target = '_blank'>", "</a>", "<b>", "</b>" )
			);

			return $settings;
		}

		/**
		 * Append S3 in pro feature list
		 *
		 * @param $pro_settings
		 *
		 * @return array
		 */
		function add_setting( $pro_settings ) {

			if ( ! isset( $pro_settings['s3'] ) ) {
				$pro_settings[] = 's3';
			}

			return $pro_settings;
		}

		/**
		 * Check if WP S3 Offload is configured properly or not
		 *
		 * If not, hook at the end of setting row to show a error message
		 */
		function check_client() {

			global $as3cf, $WpSmush, $wpsmush_settings;
			$show_error = false;
			//If S3 integration is not enabled, return
			$setting_m_key = WP_SMUSH_PREFIX . 's3';
			$setting_val   = $WpSmush->validate_install() ? $wpsmush_settings->get_setting( $setting_m_key, false ) : 0;

			if ( ! $setting_val ) {
				return;
			}

			//Check if plugin is setup or not
			//In case for some reason, we couldn't find the function
			if ( ! is_object( $as3cf ) || ! method_exists( $as3cf, 'is_plugin_setup' ) ) {
				$show_error        = true;
				$support_url       = esc_url( "https://premium.wpmudev.org/contact" );
				$this->setup_error = sprintf( esc_html__( "We are having trouble interacting with WP S3 Offload, make sure the plugin is activated. Or you can %sreport a bug%s.", "wp-smushit" ), '<a href="' . $support_url . '" target="_blank">', '</a>' );
			}

			//Plugin is not setup, or some information is missing
			if ( ! $as3cf->is_plugin_setup() ) {
				$show_error        = true;
				$configure_url     = $as3cf->get_plugin_page_url();
				$this->setup_error = sprintf( esc_html__( "It seems you haven't finished setting up WP S3 Offload yet, %sConfigure%s it now to enable Amazon S3 support.", "wp-smushit" ), "<a href='" . $configure_url . "' target='_blank'>", "</a>" );
			}
			//Return Early if we don't need to do anything
			if ( ! $show_error ) {
				return;
			}
			//Hook at the end of setting row to output a error div
			add_action( 'smush_setting_row_end', array( $this, 's3_setup_error' ) );

		}

		/**
		 * Prints the error message if any
		 *
		 * @return null
		 */
		function s3_setup_error( $setting_key ) {
			if ( empty( $this->setup_error ) || 's3' != $setting_key ) {
				return null;
			}
			echo "<div class='wp-smush-notice smush-s3-setup-error'><i class='dev-icon wdv-icon wdv-icon-fw wdv-icon-exclamation-sign'></i><p>$this->setup_error</p></div>";
		}

	}

	global $WpSmushS3;
	$WpSmushS3 = new WpSmushS3();

}