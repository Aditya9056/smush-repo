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

		function __construct() {

			//Filters the setting variable to add S3 setting title and description
			add_filter( 'wp_smush_settings', array( $this, 'register' ), 6 );

			//Filters the setting variable to add S3 setting in premium features
			add_filter( 'wp_smush_pro_settings', array( $this, 'add_setting' ), 6 );

		}

		/**
		 * Filters the setting variable to add S3 setting title and description
		 *
		 * @param $settings
		 *
		 * @return mixed
		 */
		function register( $settings ) {
			$settings['s3'] = array(
				'label' => esc_html__( 'Enable WP S3 Offload integration for remote files', 'wp-smushit' ),
				'desc'  => sprintf( esc_html__( 'Allows the access to optimise the images stored on S3, using the WP S3 Offload plugin, if the option %sRemove Files From Server%s is enabled. All other files are Smushed as per the Smush settings.', 'wp-smushit' ), "<b>", "</b>" )
			);

			return $settings;
		}

		/**
		 * Append S3 in pro feature list
		 *
		 * @param $pro_settings
		 * @return array
		 */
		function add_setting( $pro_settings ) {

			if ( ! isset( $pro_settings['s3'] ) ) {
				$pro_settings[] = 's3';
			}

			return $pro_settings;
		}

	}

	global $WpSmushS3;
	$WpSmushS3 = new WpSmushS3();

}