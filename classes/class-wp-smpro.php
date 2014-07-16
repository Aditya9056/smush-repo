<?php

/**
 * The main controller, that calls and instantiates everything else
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 * @copyright (c) 2014, Incsub
 */
if ( ! class_exists( 'WpSmPro' ) ) {

	class WpSmPro {

		/**
		 * Status Messages
		 * @var array
		 */
		public $status_msgs = array();

		public $smush_settings = array(
			'auto'        => false,
			'remove_exif' => true,
			'progressive' => true,
			'gif_to_png'  => true,
		);

		public function __construct() {

			// setup some smushing settings
			$this->init_settings();

			// define some constants
			$this->constants();

			// initialise status messages
			$this->init_status_messages();

			// instantiate the sender
			$this->sender = new WpSmProSend();

			// instantiate the receiver
			$this->receiver = new WpSmProReceive();

			$this->admin = new WpSmProAdmin();

			load_plugin_textdomain( WP_SMPRO_DOMAIN, false, WP_SMPRO_DIR . '/languages/' );
		}

		function constants() {
			/**
			 * TODO: Fix the URL
			 */
			if ( ! defined( 'WP_SMPRO_SERVICE_URL' ) ) {

				// the service url, can be changed if we provide an alternate url, for eg, for self hosted, in future
				define( 'WP_SMPRO_SERVICE_URL', 'https://107.170.2.190:1203/upload/' );
			}

			// the user agent for the request
			define( 'WP_SMPRO_USER_AGENT', 'WP Smush.it/' . WP_SMPRO_VERSION . '} (' . '+' . get_site_url() . ')' );


			//Image Limit 5MB
			// @todo, fetch limit from API, instead
			define( 'WP_SMPRO_MAX_BYTES', 5 * 1024 * 1024 );

			//Time out for API request
			define( 'WP_SMUSHIT_PRO_TIMEOUT', 60 );


			if ( ! defined( 'WP_SMPRO_EFFICIENT' ) ) {
				// constant to decide whether to remove extra data
				define( 'WP_SMPRO_EFFICIENT', false );
			}

			// set up constants based on the settings, useful for debugging
			foreach ( $this->smush_settings as $key => $value ) {
				if ( 'auto' === $key ) {
					continue;
				}

				$const_name = 'WP_SMPRO_' . strtoupper( $key );

				if ( WP_SMPRO_EFFICIENT ) {
					define( $const_name, true );
				} else {
					if ( ! defined( $const_name ) ) {
						$option_name = strtolower( $const_name );

						define( $const_name, get_option( 'wp_smpro_' . $key, $val ) );
					}
				}

			}

			if ( ! defined( 'WP_SMPRO_AUTO' ) ) {
				define( 'WP_SMPRO_AUTO', $this->smush_settings['auto'] );
			}

			// deprecating, this should be default and not an option, whenever we add it
			// define('WP_SMPRO_ENFORCE_SAME_URL', get_option('wp_smushit_pro_smushit_enforce_same_url', 'on'));
			
			// are we debugging, here?
			if (defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
				define( 'WP_SMPRO_DEBUG', true ); // removing from options
			} else {
				define( 'WP_SMPRO_DEBUG', false );
			}
		}

		/**
		 * Initialise some translation ready status messages
		 */
		function init_status_messages() {
			$smush_status = array(
				0 => __( 'Request failed', WP_SMPRO_DOMAIN ),
				1 => __( 'File successfully received', WP_SMPRO_DOMAIN ),
				2 => __( 'File is in the queue', WP_SMPRO_DOMAIN ),
				3 => __( 'File is being smushed', WP_SMPRO_DOMAIN ),
				4 => __( 'Smushing successful and ready for download', WP_SMPRO_DOMAIN ),
				5 => __( 'Smushing failed due to error', WP_SMPRO_DOMAIN ),
				6 => __( 'Useless smushing', WP_SMPRO_DOMAIN )

			);

			$request_err_msg = array(
				0 => __( 'No file received', WP_SMPRO_DOMAIN ),
				1 => __( 'Callback url not provided', WP_SMPRO_DOMAIN ),
				2 => __( 'Token not provided', WP_SMPRO_DOMAIN ),
				3 => __( 'Invalid API key', WP_SMPRO_DOMAIN ),
				4 => __( 'The file type is not supported', WP_SMPRO_DOMAIN ),
				5 => __( 'Upload failed', WP_SMPRO_DOMAIN ),
				6 => __( 'File larger than allowed limit', WP_SMPRO_DOMAIN )
			);

			$this->status_msgs = array(
				'smush_status'    => $smush_status,
				'request_err_msg' => $request_err_msg,
			);
		}

	}

}