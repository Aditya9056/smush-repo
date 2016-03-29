<?php
/**
 * @package WP Smush
 * @subpackage Admin
 * @version 1.0
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2016, Incsub (http://incsub.com)
 */
//Include Bulk UI
require_once WP_SMUSH_DIR . 'lib/class-wp-smush-bulk-ui.php';

if ( ! class_exists( 'WpSmushitAdmin' ) ) {
	/**
	 * Show settings in Media settings and add column to media library
	 *
	 */

	/**
	 * Class WpSmushitAdmin
	 *
	 * @property int $remaining_count
	 * @property int $total_count
	 * @property int $smushed_count
	 * @property int $exceeding_items_count
	 */
	class WpSmushitAdmin extends WpSmush {

		/**
		 * @var array Settings
		 */
		public $settings;

		public $bulk;

		/**
		 * @var Total count of Attachments for Smushing
		 */
		public $total_count;

		/**
		 * @var Smushed attachments out of total attachments
		 */
		public $smushed_count;

		/**
		 * @var Smushed attachments out of total attachments
		 */
		public $super_smushed;

		/**
		 * @array Stores the stats for all the images
		 */
		public $stats;

		public $bulk_ui = '';

		/**
		 * @var int Limit for allowed number of images per bulk request
		 */
		private $max_free_bulk = 50; //this is enforced at api level too

		public $upgrade_url = 'https://premium.wpmudev.org/project/wp-smush-pro/?utm_source=wordpress.org&utm_medium=plugin&utm_campaign=WP%20Smush%20Upgrade';

		//Stores unsmushed ids
		private $ids = '';

		//Stores all lossless smushed ids
		public $resmush_ids = array();

		/**
		 * @var int Number of attachments exceeding free limit
		 */
		public $exceeding_items_count = 0;

		/**
		 * @var If the plugin is a pro version or not
		 */
		private $is_pro_user;

		private $attachments = '';

		/**
		 * Constructor
		 */
		public function __construct() {

			// Save Settings, Process Option, Need to process it early, so the pages are loaded accordingly, nextgen gallery integration is loaded at same action
			add_action( 'plugins_loaded', array( $this, 'process_options' ), 16 );

			// hook scripts and styles
			add_action( 'admin_init', array( $this, 'register' ) );

			// hook custom screen
			add_action( 'admin_menu', array( $this, 'screen' ) );

			//Handle Smush Bulk Ajax
			add_action( 'wp_ajax_wp_smushit_bulk', array( $this, 'process_smush_request' ) );

			//Handle Smush Single Ajax
			add_action( 'wp_ajax_wp_smushit_manual', array( $this, 'smush_manual' ) );

			//Handle Restore operation
			add_action( 'wp_ajax_smush_restore_image', array( $this, 'restore_image' ) );

			//Handle Restore operation
			add_action( 'wp_ajax_smush_resmush_image', array( $this, 'resmush_image' ) );

			//Handle Restore operation
			add_action( 'wp_ajax_scan_for_resmush', array( $this, 'scan_images' ) );

			add_filter( 'plugin_action_links_' . WP_SMUSH_BASENAME, array(
				$this,
				'settings_link'
			) );
			add_filter( 'network_admin_plugin_action_links_' . WP_SMUSH_BASENAME, array(
				$this,
				'settings_link'
			) );
			//Attachment status, Grid view
			add_filter( 'attachment_fields_to_edit', array( $this, 'filter_attachment_fields_to_edit' ), 10, 2 );

			/// Smush Upgrade
			add_action( 'admin_notices', array( $this, 'smush_upgrade' ) );

			//Handle the smush pro dismiss features notice ajax
			add_action( 'wp_ajax_dismiss_smush_notice', array( $this, 'dismiss_smush_notice' ) );

			$this->bulk_ui = new WpSmushBulkUi();
		}

		/**
		 * Adds smush button and status to attachment modal and edit page if it's an image
		 *
		 *
		 * @param array $form_fields
		 * @param WP_Post $post
		 *
		 * @return array $form_fields
		 */
		function filter_attachment_fields_to_edit( $form_fields, $post ) {
			if ( ! wp_attachment_is_image( $post->ID ) ) {
				return $form_fields;
			}
			$form_fields['wp_smush'] = array(
				'label'         => __( 'WP Smush', 'wp-smushit' ),
				'input'         => 'html',
				'html'          => $this->smush_status( $post->ID ),
				'show_in_edit'  => true,
				'show_in_modal' => true,
			);

			return $form_fields;
		}

		/**
		 * Add Bulk option settings page
		 */
		function screen() {
			global $admin_page_suffix;
			$admin_page_suffix = add_media_page( 'Bulk WP Smush', 'WP Smush', 'edit_others_posts', 'wp-smush-bulk', array(
				$this->bulk_ui,
				'ui'
			) );

			//For Nextgen gallery Pages, check later in enqueue function
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		}

		/**
		 * Register js and css
		 */
		function register() {

			global $WpSmush;

			// Register js for smush utton in grid view
			$current_blog_id       = get_current_blog_id();
			$meta_key              = $current_blog_id == 1 ? 'wp_media_library_mode' : 'wp_' . $current_blog_id . '_media_library_mode';
			$wp_media_library_mode = get_user_meta( get_current_user_id(), $meta_key, true );

			//Either request variable is not empty and grid mode is set, or if request empty then view is as per user choice, or no view is set
			if ( ( ! empty( $_REQUEST['mode'] ) && $_REQUEST['mode'] == 'grid' ) ||
			     ( empty( $_REQUEST['mode'] ) && $wp_media_library_mode != 'list' )
			) {
				wp_register_script( 'wp-smushit-admin-js', WP_SMUSH_URL . 'assets/js/wp-smushit-admin.js', array(
					'jquery',
					'media-views'
				), WP_SMUSH_VERSION );
			} else {
				wp_register_script( 'wp-smushit-admin-js', WP_SMUSH_URL . 'assets/js/wp-smushit-admin.js', array(
					'jquery',
					'underscore'
				), WP_SMUSH_VERSION );
			}
			wp_register_script( 'wp-smushit-admin-media-js', WP_SMUSH_URL . 'assets/js/wp-smushit-admin-media.js', array( 'jquery' ), $WpSmush->version );


			/* Register Style. */
			wp_register_style( 'wp-smushit-admin-css', WP_SMUSH_URL . 'assets/css/wp-smushit-admin.css', array(), $WpSmush->version );
			wp_register_style( 'jquery-ui', WP_SMUSH_URL . 'assets/css/jquery-ui.css', array() );

		}

		/**
		 * enqueue js and css
		 */
		function enqueue() {
			global $pagenow;
			$current_screen = get_current_screen();
			$current_page   = $current_screen->base;

			/**
			 * Allows to disable enqueuing smush files on a particular page
			 */
			$enqueue_smush = apply_filters( 'wp_smush_enqueue', true );

			//Do not enqueue, unless it is one of the required screen
			if ( ! $enqueue_smush || ( $current_page != 'nggallery-manage-images' && $current_page != 'gallery_page_wp-smush-nextgen-bulk' && $pagenow != 'post.php' && $pagenow != 'upload.php' ) ) {
				return;
			}

			wp_enqueue_script( 'wp-smushit-admin-js' );
			wp_enqueue_script( 'jquery-ui-tooltip');

			//Style
			wp_enqueue_style( 'wp-smushit-admin-css' );
			wp_enqueue_style( 'jquery-ui' );

			//If class method exists, load shared UI
			if ( ( 'media_page_wp-smush-bulk' == $current_page || 'gallery_page_wp-smush-nextgen-bulk' == $current_page ) && class_exists( 'WDEV_Plugin_Ui' ) ) {
				if ( method_exists( 'WDEV_Plugin_Ui', 'load' ) ) {
					WDEV_Plugin_Ui::load( WP_SMUSH_URL . '/assets/shared-ui/', false );
				}
			}

			// localize translatable strings for js
			$this->localize();
		}

		/**
		 * Localize Translations
		 */
		function localize() {
			global $pagenow;
			if ( ! isset( $pagenow ) || ! in_array( $pagenow, array( "post.php", "upload.php", "post-new.php" ) ) ) {
				return;
			}

			$bulk   = new WpSmushitBulk();
			$handle = 'wp-smushit-admin-js';

			//Setup Total Attachments count
			$this->total_count = $this->total_count();
			$this->setup_global_stats();

			if ( $this->is_pro_user || $this->remaining_count <= $this->max_free_bulk ) {
				$bulk_now = __( 'Bulk Smush Now', 'wp-smushit' );
			} else {
				$bulk_now = sprintf( __( 'Bulk Smush %d Attachments', 'wp-smushit' ), $this->max_free_bulk );
			}

			$wp_smush_msgs = array(
				'progress'             => __( 'Smushing in Progress', 'wp-smushit' ),
				'done'                 => __( 'All Done!', 'wp-smushit' ),
				'bulk_now'             => $bulk_now,
				'something_went_wrong' => __( 'Ops!... something went wrong', 'wp-smushit' ),
				'resmush'              => __( 'Super-Smush', 'wp-smushit' ),
				'smush_it'             => __( 'Smush it', 'wp-smushit' ),
				'smush_now'            => __( 'Smush Now', 'wp-smushit' ),
				'sending'              => __( 'Sending ...', 'wp-smushit' ),
				'error_in_bulk'        => __( '{{errors}} image(s) were skipped due to an error.', 'wp-smushit' ),
				'all_resmushed'        => __( 'All images are fully optimised.', 'wp-smushit' ),
				'restore'              => esc_html__( "Restoring image..", "wp-smushit" ),
				'smushing'             => esc_html__( "Smushing image..", "wp-smushit" ),

			);

			wp_localize_script( $handle, 'wp_smush_msgs', $wp_smush_msgs );

			$this->attachments = $bulk->get_attachments();

			//Localize smushit_ids variable, if there are fix number of ids
			$this->ids = ! empty( $_REQUEST['ids'] ) ? array_map( 'intval', explode( ',', $_REQUEST['ids'] ) ) : $this->attachments;

			//If premium, And we have a resmush list already, localize those ids
			if ( $resmush_ids = get_option( "wp-smush-resmush-list" ) ) {
				//get the attachments, and get lossless count
				$this->resmush_ids = $resmush_ids;
			}

			//Array of all smushed, unsmushed and lossless ids
			$data = array(
				'unsmushed' => $this->ids,
				'resmush'   => $this->resmush_ids,
				'timeout'   => WP_SMUSH_TIMEOUT * 1000, //Convert it into ms
			);

			wp_localize_script( 'wp-smushit-admin-js', 'wp_smushit_data', $data );

		}

		/**
		 * Translation ready settings
		 */
		function init_settings() {

			$this->settings = array(
				'auto'      => array(
					'label' => esc_html__( 'Automatically Smush my images on upload', 'wp-smushit' ),
					'desc'  => esc_html__( 'When you upload images to your media library, we’ll automatically compress them instantly.', 'wp-smushit' )
				),
				'keep_exif' => array(
					'label' => esc_html__( 'Preserve Image EXIF', 'wp-smushit' ),
					'desc'  => esc_html__( 'EXIF data stores image information like ISO speed, shutter speed, camera model, dates, focal length and much more.', 'wp-smushit' )
				),
				'original'  => array(
					'label' => esc_html__( 'Include my original full-size images', 'wp-smushit' ),
					'desc'  => esc_html__( 'By default we smush thumbnail, medium and large files. This will smush your original uploads too.', 'wp-smushit' )
				),
				'lossy'     => array(
					'label' => esc_html__( 'Super-smush  my images', 'wp-smushit' ),
					'desc'  => esc_html__( 'Reduce your images further with our intelligent multi-pass lossy compression.', 'wp-smushit' )
				),
				'backup'    => array(
					'label' => esc_html__( 'Backup my originals', 'wp-smushit' ),
					'desc'  => esc_html__( 'We’ll keep your original images you upload, as well as your newly compressed images. This will significantly increase your uploads folder size - nearly double.', 'wp-smushit' )
				),
				'nextgen'   => array(
					'label' => esc_html__( 'Enable NextGen Gallery integration', 'wp-smushit' ),
					'desc'  => esc_html__( 'Allow smushing images directly through NextGen Gallery settings.', 'wp-smushit' )
				)
			);
		}

		/**
		 * Runs the expensive queries to get our global smush stats
		 *
		 * @param bool $force_update Whether to Force update the Global Stats or not
		 *
		 */
		function setup_global_stats( $force_update = false ) {
			$this->smushed_count = $this->smushed_count();
			$this->remaining_count = $this->total_count - $this->smushed_count;
			$this->stats         = $this->global_stats( $force_update );
		}

		/**
		 * Check if form is submitted and process it
		 *
		 * @return null
		 */
		function process_options() {
			if ( ! is_admin() ) {
				return;
			}

			$this->is_pro_user = $this->is_pro();

			$this->init_settings();

			//If refresh is set in URL
			if ( isset( $_GET['refresh'] ) && $_GET['refresh'] ) {
				$this->refresh_status();
			}

			// we aren't saving options
			if ( ! isset( $_POST['wp_smush_options_nonce'] ) ) {
				return;
			}
			// the nonce doesn't pan out
			if ( ! wp_verify_nonce( $_POST['wp_smush_options_nonce'], 'save_wp_smush_options' ) ) {
				return;
			}
			// var to temporarily assign the option value
			$setting = null;

			//Save option for resmush UI
			$possible_options = array(
				'keep_exif',
				'original',
				'lossy'
			);
			//Whether to Show resmush UI on settings page or not
			$show_resmush_saved = false;

			//Store Option Name and their values in an array
			$settings = array();

			// process each setting and update options
			foreach ( $this->settings as $name => $text ) {
				// formulate the index of option
				$opt_name = WP_SMUSH_PREFIX . $name;

				// get the value to be saved
				$setting = isset( $_POST[ $opt_name ] ) ? 1 : 0;

				$settings[ $opt_name ] = $setting;
				// update the new value
				$updated = update_option( $opt_name, $setting );

				//Save or delete option to show resmush UI, depending upon the current settings
				if ( in_array( $name, $possible_options ) && $updated ) {
					//If preserve exif is turned off, Or if Lossy/Smush original is turned on, and the setting isn't saved recently
					if ( ( 'keep_exif' == $name && ! $setting ) ||
					     ( in_array( $name, array(
								'original',
								'lossy'
							) ) && $setting
					     ) && ! $show_resmush_saved
					) {
						$show_resmush_saved = update_option( 'wp_smush_show_resmush', 1 );
						update_option( 'wp_smush_show_resmush_nextgen', 1 );
					}
				}

				// unset the var for next loop
				unset( $setting );
			}

			//Delete Show Resmush option
			if ( isset( $_POST['wp-smush-keep_exif'] ) && ! isset( $_POST['wp-smush-original'] ) && ! isset( $_POST['wp-smush-lossy'] ) ) {
				delete_option('wp_smush_show_resmush');
				delete_option('wp_smush_show_resmush_nextgen');
			}

		}

		/**
		 * Returns number of images of larger than 1Mb size
		 *
		 * @param bool $force_update Whether to Force update the Global Stats or not
		 *
		 * @return int
		 */
		function get_exceeding_items_count( $force_update = false ) {
			$count = wp_cache_get( 'exceeding_items', 'wp_smush' );
			if ( ! $count || $force_update ) {
				$count = 0;
				//Check images bigger than 1Mb, used to display the count of images that can't be smushed
				foreach ( $this->attachments as $attachment ) {
					if ( file_exists( get_attached_file( $attachment ) ) ) {
						$size = filesize( get_attached_file( $attachment ) );
					}
					if ( empty( $size ) || ! ( ( $size / WP_SMUSH_MAX_BYTES ) > 1 ) ) {
						continue;
					}
					$count ++;
				}
				wp_cache_set( 'exceeding_items', $count, 'wp_smush', 3000 );
			}

			return $count;
		}

		/**
		 * Processes the Smush request and sends back the next id for smushing
		 */
		function process_smush_request() {

			global $WpSmush;

			// turn off errors for ajax result
			@error_reporting( 0 );

			$should_continue = true;

			if ( empty( $_REQUEST['attachment_id'] ) ) {
				wp_send_json_error( 'missing id' );
			}

			if ( ! $this->is_pro_user ) {
				//Free version bulk smush, check the transient counter value
				$should_continue = $this->check_bulk_limit();
			}

			//If the bulk smush needs to be stopped
			if ( ! $should_continue ) {
				wp_send_json_error(
					array(
						'error'    => 'bulk_request_image_limit_exceeded',
						'continue' => false
					)
				);
			}

			$attachment_id = (int) ( $_REQUEST['attachment_id'] );

			$original_meta = wp_get_attachment_metadata( $attachment_id, true );

			$smush = $WpSmush->resize_from_meta_data( $original_meta, $attachment_id );

			//Get the updated Global Stats
			$this->setup_global_stats( true );

			$stats = $this->stats;

			$stats['smushed'] = $this->smushed_count;
			$stats['total']   = $this->total_count();

			if ( is_wp_error( $smush ) ) {
				$error = $smush->get_error_message();
				//Check for timeout error and suggest to filter timeout
				if ( strpos( $error, 'timed out' ) ) {
					$error = esc_html__( "Smush request timed out, You can try setting a higher value for `WP_SMUSH_API_TIMEOUT`.", "wp-smushit" );
				}
				wp_send_json_error( array( 'stats' => $stats, 'error_msg' => $error ) );
			} else {
				//Check if a resmush request, update the resmush list
				if ( ! empty( $_REQUEST['is_bulk_resmush'] ) && 'false' != $_REQUEST['is_bulk_resmush'] && $_REQUEST['is_bulk_resmush'] ) {
					$this->update_resmush_list( $attachment_id );
				}
				wp_send_json_success( array( 'stats' => $stats ) );
			}
		}

		/**
		 * Handle the Ajax request for smushing single image
		 *
		 * @uses smush_single()
		 */
		function smush_manual() {
			// turn off errors for ajax result
			@error_reporting( 0 );

			if ( ! current_user_can( 'upload_files' ) ) {
				wp_die( __( "You don't have permission to work with uploaded files.", 'wp-smushit' ) );
			}

			if ( ! isset( $_GET['attachment_id'] ) ) {
				wp_die( __( 'No attachment ID was provided.', 'wp-smushit' ) );
			}

			//Pass on the attachment id to smush single function
			$this->smush_single( $_GET['attachment_id'] );
		}

		/**
		 * Smush single images
		 * @param $attachment_id
		 * @param bool $return Return/Echo the stats
		 *
		 * @return array|string|void
		 */
		function smush_single( $attachment_id, $return = false ) {

			global $WpSmush;

			$attachment_id = absint( (int) ( $attachment_id ) );

			$original_meta = wp_get_attachment_metadata( $attachment_id );

			//Smush the image
			$smush = $WpSmush->resize_from_meta_data( $original_meta, $attachment_id );

			//Get the button status
			$status = $WpSmush->set_status( $attachment_id, false, true );

			//Send Json response if we are not suppose to return the results

			/** Send stats **/
			if ( is_wp_error( $smush ) ) {
				if ( $return ) {
					return array( 'error' => $smush->get_error_message() );
				} else {
					wp_send_json_error( $smush->get_error_message() );
				}
			} else {
				if ( $return ) {
					return $status;
				} else {
					wp_send_json_success( $status );
				}
			}
		}

		/**
		 * Check bulk sent count, whether to allow further smushing or not
		 *
		 * @return bool
		 */
		function check_bulk_limit() {

			$transient_name  = WP_SMUSH_PREFIX . 'bulk_sent_count';
			$bulk_sent_count = get_transient( $transient_name );

			//If bulk sent count is not set
			if ( false === $bulk_sent_count ) {

				//start transient at 0
				set_transient( $transient_name, 1, 60 );

				return true;

			} else if ( $bulk_sent_count < $this->max_free_bulk ) {

				//If lte $this->max_free_bulk images are sent, increment
				set_transient( $transient_name, $bulk_sent_count + 1, 60 );

				return true;

			} else { //Bulk sent count is set and greater than $this->max_free_bulk

				//clear it and return false to stop the process
				set_transient( $transient_name, 0, 60 );

				return false;

			}
		}

		/**
		 * Total Image count
		 * @return int
		 */
		function total_count() {

			//Don't query again, if the variable is already set
			if ( ! empty( $this->total_count ) && $this->total_count > 0 ) {
				return $this->total_count;
			}

			$query = array(
				'fields'         => 'ids',
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'post_mime_type' => array( 'image/jpeg', 'image/gif', 'image/png' ),
				'order'          => 'ASC',
				'posts_per_page' => - 1,
				'no_found_rows'  => true
			);
			//Remove the Filters added by WP Media Folder
			$this->remove_wmf_filters();

			$results = new WP_Query( $query );
			$count   = ! empty( $results->post_count ) ? $results->post_count : 0;

			// send the count
			return $count;
		}

		/**
		 * Optimised images count
		 *
		 * @param bool $return_ids
		 *
		 * @return array|int
		 */
		function smushed_count( $return_ids = false ) {

			//Don't query again, if the variable is already set
			if ( ! empty( $this->smushed_count ) && $this->smushed_count > 0 ) {
				return $this->smushed_count;
			}

			$query = array(
				'fields'         => 'ids',
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'post_mime_type' => array( 'image/jpeg', 'image/gif', 'image/png' ),
				'order'          => 'ASC',
				'posts_per_page' => - 1,
				'meta_key'       => 'wp-smpro-smush-data',
				'no_found_rows'  => true
			);

			//Remove the Filters added by WP Media Folder
			$this->remove_wmf_filters();

			$results = new WP_Query( $query );

			if ( ! is_wp_error( $results ) && $results->post_count > 0 ) {
				if ( ! $return_ids ) {
					//return Post Count
					return $results->post_count;
				} else {
					//Return post ids
					return $results->posts;
				}
			} else {
				return false;
			}
		}

		/**
		 * Returns remaining count
		 *
		 * @return int
		 */
		function remaining_count() {
			return $this->total_count - $this->smushed_count;
		}

		/**
		 * Display Thumbnails, if bulk action is choosen
		 *
		 * @Note: Not in use right now, Will use it in future for Media Bulk action
		 *
		 */
		function selected_ui( $send_ids, $received_ids ) {
			if ( empty( $received_ids ) ) {
				return;
			}

			?>
			<div id="select-bulk" class="wp-smush-bulk-wrap">
				<p>
					<?php
					printf(
						__(
							'<strong>%d of %d images</strong> were sent for smushing:',
							'wp-smushit'
						),
						count( $send_ids ), count( $received_ids )
					);
					?>
				</p>
				<ul id="wp-smush-selected-images">
					<?php
					foreach ( $received_ids as $attachment_id ) {
						$this->attachment_ui( $attachment_id );
					}
					?>
				</ul>
			</div>
			<?php
		}

		/**
		 * Display the bulk smushing button
		 *
		 * @param bool $resmush
		 *
		 * @param bool $return Whether to return the button content or print it
		 *
		 * @return Returns or Echo the content
		 */
		function setup_button( $resmush = false, $return = false ) {
			$button   = $this->button_state( $resmush );
			$disabled = ! empty( $button['disabled'] ) ? ' disabled="disabled"' : '';
			$content  = '<button class="button button-primary ' . $button['class'] . '"
			        name="smush-all" ' . $disabled . '>
				<span>' . $button['text'] . '</span>
			</button>';

			//If We need to return the content
			if ( $return ) {
				return $content;
			}

			echo $content;
		}

		/**
		 * Get all the attachment meta, sum up the stats and return
		 *
		 * @param bool $force_update , Whether to forcefully update the Cache
		 *
		 * @return array|bool|mixed
		 */
		function global_stats( $force_update = false ) {

			if ( ! $force_update && $stats = wp_cache_get( 'global_stats', 'wp_smush' ) ) {
				if ( ! empty( $stats ) ) {
					return $stats;
				}
			}

			global $wpdb, $WpSmush;

			$smush_data = array(
				'size_before' => 0,
				'size_after'  => 0,
				'percent'     => 0,
				'human'       => 0
			);

			/**
			 * Allows to set a limit of mysql query
			 * Default value is 2000
			 */
			$limit      = apply_filters( 'wp_smush_media_query_limit', 2000 );
			$limit      = intval( $limit );
			$offset     = 0;
			$query_next = true;

			while ( $query_next ) {

				$global_data = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key=%s LIMIT $offset, $limit", "wp-smpro-smush-data" ) );

				if ( ! empty( $global_data ) ) {
					$smush_data['count'] = 0;
					foreach ( $global_data as $data ) {
						$data = maybe_unserialize( $data );
						if ( ! empty( $data['stats'] ) ) {
							$smush_data['count'] += 1;
							$smush_data['size_before'] += ! empty( $data['stats']['size_before'] ) ? (int) $data['stats']['size_before'] : 0;
							$smush_data['size_after'] += ! empty( $data['stats']['size_after'] ) ? (int) $data['stats']['size_after'] : 0;
						}
					}
				}

				$smush_data['bytes'] = $smush_data['size_before'] - $smush_data['size_after'];

				//Update the offset
				$offset += $limit;

				//Compare the Offset value to total images
				if ( ! empty( $this->total_count ) && $this->total_count < $offset ) {
					$query_next = false;
				} elseif ( ! $global_data ) {
					//If we didn' got any results
					$query_next = false;
				}

			}

			if ( ! isset( $smush_data['bytes'] ) || $smush_data['bytes'] < 0 ) {
				$smush_data['bytes'] = 0;
			}

			if ( $smush_data['size_before'] > 0 ) {
				$smush_data['percent'] = ( $smush_data['bytes'] / $smush_data['size_before'] ) * 100;
			}

			//Round off precentage
			$smush_data['percent'] = round( $smush_data['percent'], 2 );

			$smush_data['human'] = $WpSmush->format_bytes( $smush_data['bytes'] );

			//Update Cache
			wp_cache_set( 'smush_global_stats', $smush_data, '', DAY_IN_SECONDS );

			return $smush_data;
		}

		/**
		 * Returns Bulk smush button id and other details, as per if bulk request is already sent or not
		 * @param $resmush
		 *
		 * @return array
		 */

		private function button_state( $resmush ) {
			$button = array(
				'cancel' => false,
			);
			if ( $this->is_pro_user && $resmush ) {

				$button['text']  = __( 'Bulk Smush Now', 'wp-smushit' );
				$button['class'] = 'wp-smush-button wp-smush-resmush wp-smush-all';

			} else {

				// if we have nothing left to smush, disable the buttons
				if ( $this->smushed_count === $this->total_count ) {
					$button['text']     = __( 'All Done!', 'wp-smushit' );
					$button['class']    = 'wp-smush-finished disabled wp-smush-finished';
					$button['disabled'] = 'disabled';

				} else if ( $this->is_pro_user || $this->remaining_count <= $this->max_free_bulk ) { //if premium or under limit

					$button['text']  = __( 'Bulk Smush Now', 'wp-smushit' );
					$button['class'] = 'wp-smush-button';

				} else { //if not premium and over limit
					$button['text']  = sprintf( __( 'Bulk Smush %d Attachments', 'wp-smushit' ), $this->max_free_bulk );
					$button['class'] = 'wp-smush-button';

				}
			}

			return $button;
		}

		/**
		 * Get the smush button text for attachment
		 *
		 * @param $id Attachment ID for which the Status has to be set
		 *
		 * @return string
		 */
		function smush_status( $id ) {
			$response = trim( $this->set_status( $id, false ) );

			return $response;
		}


		/**
		 * Adds a smushit pro settings link on plugin page
		 *
		 * @param $links
		 *
		 * @return array
		 */
		function settings_link( $links ) {

			$settings_page = admin_url( 'upload.php?page=wp-smush-bulk' );
			$settings      = '<a href="' . $settings_page . '">' . __( 'Settings', 'wp-smushit' ) . '</a>';

			//Added a fix for weird warning in multisite, "array_unshift() expects parameter 1 to be array, null given"
			if ( ! empty( $links ) ) {
				array_unshift( $links, $settings );
			} else {
				$links = array( $settings );
			}

			return $links;
		}

		/**
		 * Shows Notice for free users, displays a discount coupon
		 */
		function smush_upgrade() {

			if ( ! current_user_can( 'edit_others_posts' ) || ! is_super_admin() ) {
				return;
			}

			if ( isset( $_GET['page'] ) && 'wp-smush-bulk' == $_GET['page'] ) {
				return;
			}

			if ( isset( $_GET['dismiss_smush_upgrade'] ) ) {
				update_option( 'dismiss_smush_upgrade', 1 );
			}

			if ( get_option( 'dismiss_smush_upgrade' ) || $this->is_pro_user ) {
				return;
			} ?>
			<div class="updated">
			<a href="<?php echo admin_url( 'index.php' ); ?>?dismiss_smush_upgrade=1"
			   style="float:right;margin-top: 10px;text-decoration: none;"><span class="dashicons dashicons-dismiss"
			                                                                     style="color:gray;"></span>Dismiss</a>

			<h3><span class="dashicons dashicons-megaphone" style="color:red"></span> Happy Smushing!</h3>

			<p>Welcome to the all new WP Smush, now running on the WPMU DEV Smush infrastructure!</p>

			<p>That means that you can continue smushing your images for free, now with added https support, speed,
				and reliability... enjoy!</p>

			<p>And now, if you'd like to upgrade to the WP Smush Pro plugin you can smush images up to 32MB in size,
				get 'Super Smushing' of, on average, 2&times; more reduction than lossless, backup all non smushed
				images and bulk smush an
				unlimited number of images at once.
				<a href="https://premium.wpmudev.org/?coupon=SMUSH50OFF#pricing"> Click here to upgrade with a 50%
					discount</a>.</p>
			</div><?php
		}

		/**
		 * Get the smushed attachments from the database, except gif
		 *
		 * @global object $wpdb
		 *
		 * @return object query results
		 */
		function get_smushed_attachments() {

			global $wpdb;

			$allowed_images = "( 'image/jpeg', 'image/jpg', 'image/png' )";

			$limit      = apply_filters( 'wp_smush_media_query_limit', 2000 );
			$limit      = intval( $limit );
			$offset     = 0;
			$query_next = true;

			while ( $query_next ) {
				// get the attachment id, smush data
				$sql     = "SELECT p.ID as attachment_id, p.post_mime_type as type, ms.meta_value as smush_data"
				           . " FROM $wpdb->posts as p"
				           . " LEFT JOIN $wpdb->postmeta as ms"
				           . " ON (p.ID= ms.post_id AND ms.meta_key='wp-smpro-smush-data')"
				           . " WHERE"
				           . " p.post_type='attachment'"
				           . " AND p.post_mime_type IN " . $allowed_images
				           . " ORDER BY p . ID DESC"
				           // add a limit
				           . " LIMIT " . $limit;
				$results = $wpdb->get_results( $sql );

				//Update the offset
				$offset += $limit;
				if ( $this->total_count() && $this->total_count() < $offset ) {
					$query_next = false;
				} else if ( ! $results || empty( $results ) ) {
					$query_next = false;
				}
			}

			return $results;
		}

		/**
		 * Returns the ids and meta which are losslessly compressed
		 *
		 * @return array
		 */
		function get_lossy_attachments( $attachments = '', $return_count = true ) {

			$lossy_attachments = array();
			$count = 0;

			if( empty( $attachments ) ) {
				//Fetch all the smushed attachment ids
				$attachments = $this->get_smushed_attachments();
			}

			//If we dont' have any attachments
			if ( empty( $attachments ) || 0 == count( $attachments ) ) {
				return 0;
			}

			//Check if image is lossless or lossy
			foreach ( $attachments as $attachment ) {

				//Check meta for lossy value
				$smush_data = ! empty( $attachment->smush_data ) ? maybe_unserialize( $attachment->smush_data ) : '';
				//For Nextgen Gallery images
				if( empty( $smush_data ) ) {
					$smush_data = ! empty( $attachment['wp_smush'] ) ? $attachment['wp_smush'] : '';
				}

				//Return if not smushed
				if ( empty( $smush_data ) ) {
					continue;
				}

				//if stats not set or lossy is not set for attachment, return
				if ( empty( $smush_data['stats'] ) || ! isset( $smush_data['stats']['lossy'] ) ) {
					continue;
				}

				//Add to array if lossy is not 1
				if ( $smush_data['stats']['lossy'] == 1 ) {
					$count ++;
					if ( ! empty( $attachment->attachment_id ) ) {
						$lossy_attachments[] = $attachment->attachment_id;
					}
				}
			}
			unset( $attachments );

			if( $return_count ) {
				return $count;
			}

			return $lossy_attachments;
		}

		/**
		 * Delete Site Option, stored for api status
		 */
		function refresh_status() {

			delete_site_option( 'wp_smush_api_auth' );
		}

		/**
		 * Remove any pre_get_posts_filters added by WP Media Folder plugin
		 */
		function remove_wmf_filters() {
			//remove any filters added b WP media Folder plugin to get the all attachments
			if ( class_exists( 'Wp_Media_Folder' ) ) {
				global $wp_media_folder;
				if ( is_object( $wp_media_folder ) ) {
					remove_filter( 'pre_get_posts', array( $wp_media_folder, 'wpmf_pre_get_posts1' ) );
					remove_filter( 'pre_get_posts', array( $wp_media_folder, 'wpmf_pre_get_posts' ), 0, 1 );
				}
			}
		}

		/**
		 * Store a key/value to hide the smush features on bulk page
		 */
		function dismiss_smush_notice() {
			update_option( 'hide_smush_welcome', 1 );
			wp_send_json_success();
		}

		/**
		 * Restore the image and its sizes from backup
		 */
		function restore_image() {

			//Check Empty fields
			if ( empty( $_POST['attachment_id'] ) || empty( $_POST['_nonce'] ) ) {
				wp_send_json_error( array(
					'error'   => 'empty_fields',
					'message' => esc_html__( "Error in processing restore action, Fields empty.", "wp-smushit" )
				) );
			}
			//Check Nonce
			if ( ! wp_verify_nonce( $_POST['_nonce'], "wp-smush-restore-" . $_POST['attachment_id'] ) ) {
				wp_send_json_error( array(
					'error'   => 'empty_fields',
					'message' => esc_html__( "Image not restored, Nonce verification failed.", "wp-smushit" )
				) );
			}

			//Store the restore success/failure for all the sizes
			$restored = array();

			//Process Now
			$image_id = absint( (int) $_POST['attachment_id'] );
			//Restore Full size -> get other image sizes -> restore other images

			//Get the Original Path
			$file_path = get_attached_file( $image_id );

			//Get the backup path
			$backup_name = $this->get_image_backup_path( $file_path );

			//If file exists, corresponding to our backup path
			if ( file_exists( $backup_name ) ) {
				//Restore
				$restored[] = @copy( $backup_name, $file_path );

				//Delete the backup
				@unlink( $backup_name );
			} elseif ( file_exists( $file_path . '_backup' ) ) {
				//Restore from other backups
				$restored[] = @copy( $file_path . '_backup', $file_path );
			}

			//Get other sizes and restore
			//Get attachment data
			$attachment_data = wp_get_attachment_metadata( $image_id );

			//Get the sizes
			$sizes = ! empty( $attachment_data['sizes'] ) ? $attachment_data['sizes'] : '';

			//Loop on images to restore them
			foreach ( $sizes as $size ) {
				//Get the file path
				if ( empty( $size['file'] ) ) {
					continue;
				}

				//Image Path and Backup path
				$image_size_path  = path_join( dirname( $file_path ), $size['file'] );
				$image_bckup_path = $this->get_image_backup_path( $image_size_path );

				//Restore
				if ( file_exists( $image_bckup_path ) ) {
					$restored[] = @copy( $image_bckup_path, $image_size_path );
					//Delete the backup
					@unlink( $image_bckup_path );
				} elseif ( file_exists( $image_size_path . '_backup' ) ) {
					$restored[] = @copy( $image_size_path . '_backup', $image_size_path );
				}
			}
			//If any of the image is restored, we count it as success
			if ( in_array( true, $restored ) ) {

				//Remove the Meta, And send json success
				delete_post_meta( $image_id, $this->smushed_meta_key );

				//Get the Button html without wrapper
				$button_html = $this->set_status( $image_id, false, false, false );

				wp_send_json_success( array( 'button' => $button_html ) );
			}
			wp_send_json_error( array( 'message' => '<div class="wp-smush-error">' . __( "Unable to restore image", "wp-smushit" ) . '</div>' ) );
		}

		/**
		 * Restore the image and its sizes from backup
		 *
		 * @uses smush_single()
		 *
		 */
		function resmush_image() {

			//Check Empty fields
			if ( empty( $_POST['attachment_id'] ) || empty( $_POST['_nonce'] ) ) {
				wp_send_json_error( array(
					'error'   => 'empty_fields',
					'message' => '<div class="wp-smush-error">' . esc_html__( "Image not smushed, fields empty.", "wp-smushit" ) . '</div>'
				) );
			}
			//Check Nonce
			if ( ! wp_verify_nonce( $_POST['_nonce'], "wp-smush-resmush-" . $_POST['attachment_id'] ) ) {
				wp_send_json_error( array(
					'error'   => 'empty_fields',
					'message' => '<div class="wp-smush-error">' . esc_html__( "Image couldn't be smushed as the nonce verification failed, try reloading the page.", "wp-smushit" ) . '</div>'
				) );
			}

			$image_id = intval( $_POST['attachment_id'] );

			$smushed = $this->smush_single( $image_id, true );

			//If any of the image is restored, we count it as success
			if ( ! empty( $smushed['status'] ) ) {

				//Send button content
				wp_send_json_success( array( 'button' => $smushed['status'] . $smushed['stats'] ) );

			} elseif ( ! empty( $smushed['error'] ) ) {

				//Send Error Message
				wp_send_json_error( array( 'message' => '<div class="wp-smush-error">' . __( "Unable to smush image", "wp-smushit" ) . '</div>' ) );

			}
		}

		/**
		 * Scans all the smushed attachments to check if they need to be resmushed as per the
		 * current settings, as user might have changed one of the configurations "Lossy", "Keep Original", "Preserve Exif"
		 */
		function scan_images() {

			global $WpSmush, $wpsmushnextgenadmin;

			check_ajax_referer( 'smush-scan-images', 'nonce' );

			$resmush_list = array();

			//Scanning for NextGen or Media Library
			$type = isset( $_REQUEST['type'] ) ? sanitize_text_field( $_REQUEST['type'] ) : '';

			//Default response
			$ajax_response = '<div class="wp-smush-notice wp-smush-all-done">
					<i class="dev-icon dev-icon-tick"></i>' . esc_html__( "Hurray! All images are optimised as per your current settings.", "wp-smushit" ) . '</div>';

			//Logic: If none of the required settings is on, don't need to resmush any of the images
			//We need at least one of these settings to be on, to check if any of the image needs resmush
			//Allow to smush Upfront images as well
			$upfront_active = class_exists( 'Upfront' );
			if ( ! $WpSmush->lossy_enabled && ! $WpSmush->smush_original && $WpSmush->keep_exif && ! $upfront_active ) {
				$response = array( "content" => $ajax_response );
				wp_send_json_success( $response );
			}

			if ( 'nextgen' != $type ) {

				//Get list of Smushed images
				$attachments = $this->smushed_count( true );
			} else {
				global $wpsmushnextgenstats;

				//Get smushed attachments list from nextgen class, We get the meta as well
				$attachments = $wpsmushnextgenstats->get_ngg_images();

			}

			//Check if any of the smushed image needs to be resmushed
			if ( ! empty( $attachments ) && is_array( $attachments ) ) {
				foreach ( $attachments as $attachment_k => $attachment ) {

					//For NextGen we get the metadata in the attachment data itself
					if ( ! empty( $attachment['wp_smush'] ) ) {
						$smush_data = $attachment['wp_smush'];
					} else {
						//Check the current settings, and smush data for the image
						$smush_data = get_post_meta( $attachment, $this->smushed_meta_key, true );
					}

					if ( ! empty( $smush_data['stats'] ) ) {

						//If we need to optmise losslessly, add to resmush list
						$smush_lossy = $WpSmush->lossy_enabled && ! $smush_data['stats']['lossy'];

						//If we need to strip exif, put it in resmush list
						$strip_exif = ! $WpSmush->keep_exif && isset( $smush_data['stats']['keep_exif'] ) && ( 1 == $smush_data['stats']['keep_exif'] );

						//If Original image needs to be smushed
						$smush_original = $WpSmush->smush_original && empty( $smush_data['sizes']['full'] );

						if ( $smush_lossy || $strip_exif || $smush_original ) {
							$resmush_list[] = 'nextgen' == $type ? $attachment_k : $attachment;
							continue;
						}
					}
				}

				//Check for Upfront images that needs to be smushed
				if ( $upfront_active && 'nextgen' != $type ) {
					$upfront_attachments = $this->get_upfront_images( $resmush_list );
					if ( ! empty( $upfront_attachments ) && is_array( $upfront_attachments ) ) {
						foreach ( $upfront_attachments as $u_attachment_id ) {
							if ( ! in_array( $u_attachment_id, $resmush_list ) ) {
								//Check if not smushed
								$upfront_images = get_post_meta( $u_attachment_id, 'upfront_used_image_sizes', true );
								if ( ! empty( $upfront_images ) && is_array( $upfront_images ) ) {
									//Iterate over all the images
									foreach ( $upfront_images as $image ) {
										//If any of the element image is not smushed, add the id to resmush list
										//and skip to next image
										if ( empty( $image['is_smushed'] ) || 1 != $image['is_smushed'] ) {
											$resmush_list[] = $u_attachment_id;
											break;
										}
									}
								}
							}
						}
					}
				}//End Of Upfront loop
				$key = 'nextgen' == $type ? 'wp-smush-nextgen-resmush-list' : 'wp-smush-resmush-list';

				//Store the list in Options table
				update_option( $key, $resmush_list );

				if ( 'nextgen' != $type ) {
					//Set the variables
					$this->resmush_ids = $resmush_list;
				} else {
					//To avoid the php warning
					$wpsmushnextgenadmin->resmush_ids = $resmush_list;
				}

				if ( ( $count = count( $resmush_list ) ) > 0 ) {
					$ajax_response = 'nextgen' == $type ? $wpsmushnextgenadmin->resmush_bulk_ui( true ) : $this->bulk_ui->resmush_bulk_ui( true );
				}else{
					//Other wise don't show the scan option
					if( 'nextgen' == $type ) {
						delete_option('wp_smush_show_resmush_nextgen');
					}else{
						delete_option('wp_smush_show_resmush');
					}
				}
			}
			wp_send_json_success( array( "resmush_ids" => $resmush_list, "content" => $ajax_response ) );
		}

		/**
		 * Remove the given attachment id from resmush list and updates it to db
		 *
		 * @param $attachment_id
		 * @param string $mkey
		 *
		 */
		function update_resmush_list( $attachment_id, $mkey = 'wp-smush-resmush-list' ) {
			$resmush_list = get_option( $mkey );

			//If there are any items in the resmush list, Unset the Key
			if( !empty( $resmush_list ) && count( $resmush_list ) > 0 ) {
				$key = array_search( $attachment_id, $resmush_list );
				if ( $resmush_list ) {
					unset( $resmush_list[ $key ] );
				}
				$resmush_list = array_values( $resmush_list );
			}

			//If Resmush List is empty
			if ( empty( $resmush_list ) || 0 == count( $resmush_list ) ) {
				if( 'wp-smush-nextgen-resmush-list' == $mkey ) {
					//Remove the two options
					delete_option( 'wp_smush_show_resmush_nextgen' );
				}else {
					//Remove the two options
					delete_option( 'wp_smush_show_resmush' );
				}
				//Delete resmush list
				delete_option( $mkey );
			}else {
				update_option( $mkey, $resmush_list );
			}
		}

		/**
		 * Get the attachment ids with Upfront images
		 *
		 * @param array $skip_ids
		 *
		 * @return array|bool
		 */
		function get_upfront_images( $skip_ids = array() ) {

			$query = array(
				'fields'         => 'ids',
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'post_mime_type' => array( 'image/jpeg', 'image/gif', 'image/png' ),
				'order'          => 'ASC',
				'posts_per_page' => - 1,
				'meta_key'       => 'upfront_used_image_sizes',
				'no_found_rows'  => true
			);

			//Skip all the ids which are already in resmush list
			if ( ! empty( $skip_ids ) && is_array( $skip_ids ) ) {
				$query['post__not_in'] = $skip_ids;
			}

			$results = new WP_Query( $query );

			if ( ! is_wp_error( $results ) && $results->post_count > 0 ) {
				return $results->posts;
			} else {
				return false;
			}
		}

		/**
		 * Returns current user name to be displayed
		 * @return string
		 */
		function get_user_name() {
			//Get username
			$current_user = wp_get_current_user();
			$name = !empty( $current_user->first_name ) ? $current_user->first_name : $current_user->display_name;
			return $name;
		}

		/**
		 * Format Numbers to short form 1000 -> 1k
		 *
		 * @param $number
		 *
		 * @return string
		 */
		function format_number($number) {
			if($number >= 1000) {
				return $number/1000 . "k";   // NB: you will want to round this
			}
			else {
				return $number;
			}
		}

		/**
		 * Returns/Updates the number of images Super Smushed
		 * @return array|mixed|void
		 */
		function super_smushed_count( $return = false ) {

			//Flag to check if we need to re-evaluate the count
			$revaluate = false;

			$super_smushed = get_option( 'wp_smush_super_smushed_count', false );

			//Check if need to revalidate
			if ( ! $super_smushed || empty( $super_smushed ) || empty( $super_smushed['count'] ) ) {
				$super_smushed = array();
				$revaluate     = true;
			} else {
				$last_checked = $super_smushed['timestamp'];

				$diff = $last_checked - current_time( 'timestamp' );

				//Difference in minutes
				$diff_m = $diff / 60;

				//if last checked was more than 5 mins.
				if ( $diff_m > 5 ) {
					$revaluate = true;
				}
			}
			//Need to scan all the image
			if ( $revaluate ) {
				//Get all the Smushed attachments
				$super_smushed_images = $this->get_lossy_attachments();
				$count                = ! empty( $super_smushed_images ) ? intval( $super_smushed_images ) : 0;

				$super_smushed['count']     = $count;
				$super_smushed['timestamp'] = current_time( 'timestamp' );

				update_option( 'wp_smush_super_smushed_count', $super_smushed );
			}

			if ( ! $return ) {
				wp_send_json_success( array( 'count' => $super_smushed['count'] ) );
			}
			return $super_smushed['count'];
		}

		/**
		 * Add/Subtract from Smushed images count
		 *
		 * @param string $op_type
		 */
		function update_super_smush_count( $op_type = 'add' ) {

			//Get the existing count
			$super_smushed = get_option( 'wp_smush_super_smushed_count', false );

			//Initialize if it doesn't exists
			if ( ! $super_smushed || empty( $super_smushed['count'] ) ) {
				$super_smushed = array(
					'count' => 0
				);
			}
			//Increase if need to add
			if ( 'add' == $op_type ) {
				$super_smushed['count'] += 1;
			} elseif ( 'sub' == $op_type && $super_smushed['count'] > 0 ) {
				//Else if greater than 0, subtract 1
				$super_smushed['count'] -= 1;
			}
			//Add the timestamp
			$super_smushed['timestamp'] = current_time( 'timestamp' );

			//Update to database
			update_option( 'wp_smush_super_smushed_count', $super_smushed );
		}

	}

	global $wpsmushit_admin;
	$wpsmushit_admin = new WpSmushitAdmin();
}
