<?php
/**
 * @package SmushItPro/Admin
 * @subpackage Admin
 * @version 1.0
 * 
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 * 
 * @copyright (c) 2014, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmProAdmin' ) ) {
	/**
	 * Show settings in Media settings and add column to media library
	 * 
	 */
	class WpSmProAdmin {
		
		/**
		 *
		 * @var array Settings
		 */
		public $settings;
		
		/**
		 * Constructor
		 */
		public function __construct() {

			// add extra columns for smushing to media lists
			add_filter( 'manage_media_columns', array( &$this, 'columns' ) );
			add_action( 'manage_media_custom_column', array( &$this, 'custom_column' ), 10, 2 );

			// add the admin option screens
			add_action( 'admin_init', array( &$this, 'admin_init' ) );
			
			// hook js for bulk actions
			add_action( 'admin_head-upload.php', array( &$this, 'add_bulk_actions_js' ) );
			
			// hook handler for bulk smushing action
			add_action( 'admin_action_bulk_smushit', array( &$this, 'bulk_action_handler' ) );
			
			// hook settings
			add_action( 'admin_init', array( &$this, 'register_settings' ) );
			
			// initialise translation ready settings and titles
			$this->init_settings();
			
			// instantiate bulk ui
			$bulk = new WpSmProBulk();
		}
		
		/**
		 * Translation ready settings
		 */
		function init_settings() {
			$this->settings = array(
				'auto'        => __( 'Smush images on upload?', WP_SMPRO_DOMAIN ),
				'remove_meta' => __( 'Remove Exif data', WP_SMPRO_DOMAIN ),
				'progressive' => __( 'Allow progressive JPEGs', WP_SMPRO_DOMAIN ),
				'gif_to_png'  => __( 'Allow Gif to Png conversion', WP_SMPRO_DOMAIN ),
			);
		}

		
		/**
		 * Print column header for Smush.it results in the media library
		 * 
		 * @param array $defaults The default columns
		 * @return array columns with our header added
		 */
		function columns( $defaults ) {
			$defaults['smushit'] = 'Smush.it';

			return $defaults;
		}

		/**
		 * Show our custom smush data for each attachment
		 * 
		 * @param string $column_name The name of the column
		 * @param int $id The attachment id
		 */
		function custom_column( $column_name, $id ) {
			
			// if it isn't our column, get out
			if ( 'smushit' == $column_name ) {
				return;
			}
			
			
			$attachment_file_path = get_attached_file($id);

			global $wp_sm_pro;
			
			// check if this is a gif and it should be smushed
			if(!$wp_sm_pro->sender->send_if_gif($id, $attachment_file_path)){
				return;
			}

			// otherwise, get the smush meta
			$smush_meta = get_post_meta( $id, 'smush_meta', true );
			
			// if there's smush details, show it
			if ( ! empty( $smush_meta ) && ! empty( $smush_meta['full'] ) ) {

				echo $smush_meta['full']['status_msg'];

				printf( "<br><a href=\"admin.php?action=wp_smpro_queue&amp;attachment_id=%d\">%s</a>", $id, __( 'Re-smush', WP_SMPRO_DOMAIN ) );
			} else {
				// not smushed yet, check if attachment is image
				if ( wp_attachment_is_image( $id ) ) {
					print __( 'Not processed', WP_SMPRO_DOMAIN );
					printf( "<br><a href=\"admin.php?action=wp_smpro_queue&amp;attachment_id=%d\">%s</a>", $id, __( 'Smush.it now!', WP_SMPRO_DOMAIN ) );
				}
			}
			
		}

		/**
		 * Add js for bulk actions.
		 * 
		 * Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/
		 */
		function add_bulk_actions_js() {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function ($) {
					$('select[name^="action"] option:last-child').before('<option value="bulk_smushit">Bulk Smush.it</option>');
				});
			</script>
		<?php
		}

		/**
		 * Handles the bulk actions POST, and redirects to bulk ui.
		 * 
		 * Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/
		 * 
		 * @return null 
		 */
		function bulk_action_handler() {
			check_admin_referer( 'bulk-media' );

			if ( empty( $_REQUEST['media'] ) || ! is_array( $_REQUEST['media'] ) ) {
				return;
			}

			$ids = implode( ',', array_map( 'intval', $_REQUEST['media'] ) );

			// Can't use wp_nonce_url() as it escapes HTML entities
			wp_redirect( add_query_arg( '_wpnonce', wp_create_nonce( 'wp-smpro-bulk' ), admin_url( 'upload.php?page=wp-smpro-bulk&goback=1&ids=' . $ids ) ) );
			exit();
		}

		/**
		 * Add settings
		 */
		function register_settings() {
			
			// add the section to media settings
			add_settings_section( 'wp_smpro_settings', 'WP Smush.it Pro', array(
				&$this,
				'settings_cb'
			), 'media' );
			
			// add each setting
			foreach ( $this->settings as $name => $text ) {
				add_settings_field(
					'wp_smpro_' . $name, __( $text, WP_SMPRO_DOMAIN ), array(
						$this,
						'render_' . $name . '_opts'
					), 'media', 'wp_smpro_settings'
				);

				register_setting( 'media', 'wp_smpro_' . $name );
			}
		}
		
		/**
		 * Empty callback
		 * 
		 * @return null
		 */
		function settings_cb() {
			return;
		}
		
		/**
		 * Render a checkbox
		 * 
		 * @param string $key The setting's name
		 * @return string checkbox html
		 */
		function render_checked( $key ) {
			// the key for options table
			$opt_name   = 'wp_smpro_' . $key;
			
			// the defined constant
			$const_name = strtoupper( $opt_name );
			
			// default value
			$opt_val    = intval( get_option( $opt_name, constant( $const_name ) ) );
			
			// return html
			return sprintf(
				"<input type='checkbox' name='%1\$s' id='%1\$s' value='1' %2\$s>", esc_attr( $opt_name ), checked( $opt_val, true, false )
			);
		}

		/**
		 * Allows user to choose whether to automatically smush images or not
		 */
		function render_auto_opts() {
			echo $this->render_checked( 'auto' );
		}

		/**
		 * Keep exif data or not
		 */
		function render_remove_meta_opts() {
			echo $this->render_checked( 'remove_meta' );
		}

		/**
		 * Progressive optimisation
		 */
		function render_progressive_opts() {
			echo $this->render_checked( 'progressive' );
		}

		/**
		 * Allow GIF to PNG conversion for single frame images
		 */
		function render_gif_to_png_opts() {
			echo $this->render_checked( 'gif_to_png' );
		}
		
		/**
		 * enqueue common script
		 */
		function admin_init() {
			wp_enqueue_script( 'common' );
		}

	}

}