<?php

/**
 *
 * @package SmushItPro
 *
 * @version 1.0
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2014, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmPro' ) ) {

	/**
	 * The main controller. Calls and instantiates all other functionality.
	 */
	class WpSmPro {

		/**
		 * Status Messages for display
		 *
		 * @var array
		 */
		public $status_msgs = array();

		/**
		 *
		 * @var array Settings for smushing
		 */
		public $smush_settings = array(
			// auto smush on upload
			'auto'        => 1,
			// remove exif & other meta from jpg
			'remove_meta' => 1,
			// progressive optimisation for jpg
			'progressive' => 1,
			//Debug Mode
			'debug_mode' => 1,
			// convert static gifs to png
			'gif_to_png'  => 0,
		);


		/**
		 * Constructor.
		 *
		 * Initialises parameters and classes for smushing
		 */
		public function __construct() {
			// define some constants
			$this->constants();

			// initialise status messages
			$this->init_status_messages();

			// instantiate the sender
			$this->sender = new WpSmProSend();

			// instantiate the receiver
			$this->receiver = new WpSmProReceive();

			$this->fetch = new WpSmproFetch();

			$this->admin = new WpSmProAdmin();

			// load translations
			load_plugin_textdomain(
				WP_SMPRO_DOMAIN, false, WP_SMPRO_DIR . '/languages/'
			);
		}

		/**
		 * Defines some constants.
		 *
		 * @todo fetch limit from API, instead
		 */
		private function constants() {

			if ( ! defined( 'WP_SMPRO_SERVICE_URL' ) ) {
				/**
				 * The service url.
				 *
				 * Can be changed to an alternate url,
				 * for eg, for self hosted, in future
				 */
				define( 'WP_SMPRO_SERVICE_URL', 'https://smush.wpmudev.org/upload/' );
			}
			if ( ! defined( 'WP_SMPRO_SERVICE_STATUS' ) ) {
				define( 'WP_SMPRO_SERVICE_STATUS', 'https://smush.wpmudev.org/status' );
			}

			/**
			 * The user agent for the request
			 */
			define( 'WP_SMPRO_USER_AGENT', 'WP Smush PRO/' . WP_SMPRO_VERSION . '('
			                               . '+' . get_site_url() . ')'
			);


			/**
			 * Image Limit 5MB
			 */
			define( 'WP_SMPRO_MAX_BYTES', 5 * 1024 * 1024 );

			/**
			 * Time out for API request
			 */
			define( 'WP_SMPRO_TIMEOUT', 60 );


			if ( ! defined( 'WP_SMPRO_EFFICIENT' ) ) {
				/**
				 * constant to decide whether to remove extra data
				 */
				define( 'WP_SMPRO_EFFICIENT', false );
			}

			// sacrifice cleverness for readability. this code needs to change
			// set up constants based on the settings, useful for debugging
			foreach ( $this->smush_settings as $key => $value ) {

				// the name
				$const_name = 'WP_SMPRO_' . strtoupper( $key );

				// all the settings are true, in efficient mode
				if ( WP_SMPRO_EFFICIENT ) {
					define( $const_name, 1 );
					continue;
				}

				// inefficient mode, set them up from options
				if ( ! defined( $const_name ) ) {
					$option_name = WP_SMPRO_PREFIX . strtolower( $key );
					define( $const_name, get_option( $option_name, $value ) );
				}
			}

			// are we debugging, here?
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
				define( 'WP_SMPRO_DEBUG', true ); // removing from options
			} else {
				define( 'WP_SMPRO_DEBUG', false );
			}

			if ( ! defined( 'WP_SMPRO_REQUEST_LIMIT' ) ) {
				define( 'WP_SMPRO_REQUEST_LIMIT', 100 );
			}
		}

		/**
		 * Add all the available sizes to global variable
		 */
		private function get_sizes( $attachment_id ) {
			$meta = wp_get_attachment_metadata( $attachment_id );
			if ( isset( $meta['sizes'] ) ) {
				$sizes = $meta['sizes'];
				foreach ( $sizes as $key => $data ) {
					$size_array[] = $key;
				}
			}


			$size_array[] = 'full';

			return $size_array;
		}

		/**
		 * Initialise some translation ready status messages
		 */
		private function init_status_messages() {

			// smush status messages for codes from service
			$smush_status = array(
				0 => __( 'Request failed', WP_SMPRO_DOMAIN ),
				1 => __( 'File is being processed by API', WP_SMPRO_DOMAIN ),
				2 => __( 'File is in the queue', WP_SMPRO_DOMAIN ),
				3 => __( 'File is being smushed', WP_SMPRO_DOMAIN ),
				4 => __( 'Smushing successful and ready for download', WP_SMPRO_DOMAIN ),
				5 => __( 'Smushing failed due to error', WP_SMPRO_DOMAIN ),
				6 => __( 'Already optimized', WP_SMPRO_DOMAIN )
			);

			// additional request error messages
			$request_err_msg = array(
				0 => __( 'No file received', WP_SMPRO_DOMAIN ),
				1 => __( 'Callback url not provided', WP_SMPRO_DOMAIN ),
				2 => __( 'Token not provided', WP_SMPRO_DOMAIN ),
				3 => __( 'Invalid API key', WP_SMPRO_DOMAIN ),
				4 => __( 'The file type is not supported', WP_SMPRO_DOMAIN ),
				5 => __( 'Upload failed', WP_SMPRO_DOMAIN ),
				6 => __( 'File larger than allowed limit', WP_SMPRO_DOMAIN )
			);

			// set up the property
			$this->status_msgs = array(
				'smush_status'    => $smush_status,
				'request_err_msg' => $request_err_msg,
			);
		}


		/**
		 * Return the filesize in a humanly readable format.
		 * Taken from http://www.php.net/manual/en/function.filesize.php#91477
		 *
		 * @param int $bytes Bytes
		 * @param int $precision The precision of rounding
		 *
		 * @return string formatted size
		 */
		public function format_bytes( $bytes, $return = 'string', $precision = 2 ) {
			$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
			$bytes = max( $bytes, 0 );
			$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
			$pow   = min( $pow, count( $units ) - 1 );
			$bytes /= pow( 1024, $pow );

			$formatted['size'] = number_format_i18n( round( $bytes, $precision ), $precision );
			$formatted['unit'] = $units[ $pow ];
			if ( 'array' === $return ) {
				return $formatted;
			} else {
				return $formatted['size'] . ' ' . $formatted['unit'];
			}

		}

	}

}
