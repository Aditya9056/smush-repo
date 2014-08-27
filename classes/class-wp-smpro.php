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
			'auto'        => 0,
			// remove exif & other meta from jpg
			'remove_meta' => 0,
			// progressive optimisation for jpg
			'progressive' => 1,
			// convert static gifs to png
			'gif_to_png'  => 1,
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

			add_action( 'delete_attachment', array( $this, 'delete_image' ) );

			// instantiate the sender
			$this->sender = new WpSmProSend();

			// instantiate the receiver
			$this->receiver = new WpSmProReceive();

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
				define( 'WP_SMPRO_SERVICE_URL', 'https://107.170.2.190:1203/upload/' );
			}

			/**
			 * The user agent for the request
			 */
			define( 'WP_SMPRO_USER_AGENT', 'WP Smush.it PRO/' . WP_SMPRO_VERSION . '} ('
			                               . '+' . get_site_url() . ')'
			);


			/**
			 * Image Limit 5MB
			 */
			define( 'WP_SMPRO_MAX_BYTES', 5 * 1024 * 1024 );

			/**
			 * Time out for API request
			 */
			define( 'WP_SMUSHIT_PRO_TIMEOUT', 60 );


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
					$option_name = WP_SMPRO_PREFIX.strtolower( $key );
					define( $const_name, get_option( $option_name, $value ) );
				}
			}

			// are we debugging, here?
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
				define( 'WP_SMPRO_DEBUG', true ); // removing from options
			} else {
				define( 'WP_SMPRO_DEBUG', false );
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
		 * Sets the attachment's smushing status
		 *
		 * @param int $attachment_id The attachment id
		 * @param string $status_type The status type: sent, received or smushed
		 * @param string $size The image size we are setting this for
		 * @param string $state The state received from smushing processes
		 */
		public function set_status( $attachment_id, $status_type, $size, $state = 0 ) {
			// set the transient for the size
			set_transient( "wp-smpro-{$status_type}-{$attachment_id}-{$size}", $state );
		}

		/**
		 * Check the current status for all the sizes of an attachment and set post meta
		 *
		 * @param int $attachment_id The attachment id
		 * @param string $status_type The status type: sent, received or smushed
		 *
		 * @return int 1 or 0
		 */
		public function check_status( $attachment_id, $status_type ) {

			$status = array();


			$sizes = $this->get_sizes( $attachment_id );

			// get the status for each size
			foreach ( $sizes as $size ) {
				// if the transient is not set or doesn't have a value, it'll return false
				$status[ $size ] = intval( get_transient( "wp-smpro-{$status_type}-{$attachment_id}-{$size}" ) );
				error_log( $size . ' : ' . $status[ $size ] . ' : ' . $status_type );
			}

			// if none of the sizes is false (all were processed successfully)
			if ( ! in_array( 0, $status ) ) {
				// delete all the transients
				foreach ( $sizes as $size ) {
					delete_transient( "wp-smpro-{$status_type}-{$attachment_id}-{$size}" );
				}
				// update meta
				update_post_meta( $attachment_id, "wp-smpro-is-{$status_type}", 1 );

				if ( $status_type === 'smushed' ) {
					$this->update_stats( $attachment_id );
				}

				return 1;
			}

			// otherwise all the sizes would be processed again.
			return 0;
		}


		/**
		 * Wrapper to call both set_status and check_status consecutively
		 *
		 * @todo: cross-check with attachment metadata for sizes that haven't been generated by wp
		 *
		 * @param int $attachment_id The attachment id
		 * @param string $status_type The status type: sent, received or smushed
		 * @param string $size The image size we are setting this for
		 * @param string $state The state received from smushing processes
		 *
		 * @return @return int 1 or 0
		 */
		public function set_check_status( $attachment_id, $status_type, $size, $state = 0 ) {
			$this->set_status( $attachment_id, $status_type, $size, $state );
			$return_state = $this->check_status( $attachment_id, $status_type );

			return $return_state;

		}

		/**
		 *
		 * @param type $attachment_id
		 */
		public function update_stats( $attachment_id ) {
			$stats      = array();
			$statistics = array();

			$sizes = $this->get_sizes( $attachment_id );

			foreach ( $sizes as $size ) {
				$smush_meta = null;
				$status     = 0;

				$smush_meta = get_post_meta( $attachment_id, "smush_meta_$size", true );


				$status = intval( $smush_meta['status_code'] );

				$stats['before_smush'][] = "";
				$stats['after_smush'][]  = "";

				if ( $status === 4 ) {
					$stats['before_smush'][] = $smush_meta['before_smush'];
					$stats['after_smush'][]  = $smush_meta['after_smush'];
				}
			}

			$statistics['before_smush'] = array_sum( $stats['before_smush'] );
			$statistics['after_smush']  = array_sum( $stats['after_smush'] );
			$statistics                 = $this->calculate_compression( $statistics );

			update_post_meta( $attachment_id, 'wp-smpro-smush-stats', $statistics );
			$this->update_global_stats( $statistics, true );

		}

		public function update_global_stats( $stats, $increment = true ) {
			$global_stats = get_option( 'wp-smpro-global-stats', array() );
			if ( empty( $global_stats ) ) {
				$global_stats = $this->global_compression();
			}
			if ( $increment === false ) {
				foreach ( $stats as $key => $value ) {
					$stats[ $key ] = - (int) $value;
				}
			}

			$statistics['before_smush'] = $global_stats['before_smush'] + $stats['before_smush'];
			$statistics['after_smush']  = $global_stats['after_smush'] + $stats['after_smush'];

			$statistics = $this->calculate_compression( $statistics );

			update_option( 'wp-smpro-global-stats', $statistics );
		}

		public function global_compression() {
			$query = array(
				'fields'         => 'ids',
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'post_mime_type' => array( 'image/jpeg', 'image/gif', 'image/png' ),
				'order'          => 'ASC',
				'posts_per_page' => - 1
			);


			$query['meta_query'] = array(
				array(
					'key'   => "wp-smpro-is-smushed",
					'value' => 1
				)
			);


			$results = new WP_Query( $query );


			$stats = array();

			foreach ( $results->posts as $post ) {
				$smush_stats             = get_post_meta( $post, 'wp-smpro-smush-stats', true );
				$stats['before_smush'][] = $smush_stats['before_smush'];
				$stats['after_smush'][]  = $smush_stats['after_smush'];
			}
			$statistics = array();

			$statistics['before_smush'] = array_sum( $stats['before_smush'] );
			$statistics['after_smush']  = array_sum( $stats['after_smush'] );

			$statistics = $this->calculate_compression( $statistics );

			return $statistics;

		}

		function calculate_compression( $statistics ) {
			if ( empty( $statistics['before_smush'] ) || empty( $statistics['after_smush'] ) ) {
				$statistics['compressed_bytes']   = 0;
				$statistics['compressed_percent'] = 0;
				$statistics['compressed_human']   = 0;
				return $statistics;
			}
			$statistics['compressed_bytes']   = $statistics['before_smush'] - $statistics['after_smush'];
			$statistics['compressed_percent'] = number_format_i18n( ( $statistics['compressed_bytes'] / $statistics['before_smush'] ) * 100, 2 );
			$formatted                        = $this->format_bytes( $statistics['compressed_bytes'], 2 );
			$statistics['compressed_human']   = $formatted['size'] . $formatted['unit'];

			return $statistics;
		}

		public function delete_image( $id ) {
			if ( ! wp_attachment_is_image( $id ) ) {
				return;
			}
			$smush_stats = get_post_meta( $id, 'wp-smpro-smush-stats', true );
			if ( empty( $smush_stats ) || ! is_array( $smush_stats ) ) {
				return;
			}
			$this->update_global_stats( $smush_stats, false );
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
		public function format_bytes( $bytes, $precision = 2 ) {
			$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
			$bytes = max( $bytes, 0 );
			$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
			$pow   = min( $pow, count( $units ) - 1 );
			$bytes /= pow( 1024, $pow );

			$formatted['size'] = number_format_i18n( round( $bytes, $precision ), $precision );
			$formatted['unit'] = $units[ $pow ];

			return $formatted;
		}

	}

}
