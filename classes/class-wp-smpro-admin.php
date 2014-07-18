<?php
/**
 * Admin options
 * 
 * @package SmushItPro/Admin
 * 
 * @version 1.0
 * 
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 * 
 * @copyright (c) 2014, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmProAdmin' ) ) {

	class WpSmProAdmin {

		public $settings;

		public function __construct() {

			// add extra columns for smushing to media lists
			add_filter( 'manage_media_columns', array( &$this, 'columns' ) );
			add_action( 'manage_media_custom_column', array( &$this, 'custom_column' ), 10, 2 );

			// add the admin option screens
			add_action( 'admin_init', array( &$this, 'admin_init' ) );

			add_action( 'admin_head-upload.php', array( &$this, 'add_bulk_actions_via_javascript' ) );
			add_action( 'admin_action_bulk_smushit', array( &$this, 'bulk_action_handler' ) );

			add_action( 'admin_init', array( &$this, 'register_settings' ) );

			$this->init_settings();
			// instantiate bulk ui
			$bulk = new WpSmProBulk();
		}

		function init_settings() {
			$this->settings = array(
				'auto'        => __( 'Smush images on upload?', WP_SMPRO_DOMAIN ),
				'remove_meta' => __( 'Remove Exif data', WP_SMPRO_DOMAIN ),
				'progressive' => __( 'Allow progressive JPEGs', WP_SMPRO_DOMAIN ),
				'gif_to_png'  => __( 'Allow Gif to Png conversion', WP_SMPRO_DOMAIN ),
			);
		}

		/**
		 * Print column header for Smush.it results in the media library using
		 * the `manage_media_columns` hook.
		 */
		function columns( $defaults ) {
			$defaults['smushit'] = 'Smush.it';

			return $defaults;
		}

		/**
		 * Print column data for Smush.it results in the media library using
		 * the `manage_media_custom_column` hook.
		 */
		function custom_column( $column_name, $id ) {
			if ( 'smushit' == $column_name ) {
				$smush_meta = get_post_meta( $id, 'smush_meta', true );

				if ( ! empty( $smush_meta ) && ! empty( $smush_meta['full'] ) ) {

					echo $smush_meta['full']['status_msg'];

					printf( "<br><a href=\"admin.php?action=wp_smpro_queue&amp;attachment_id=%d\">%s</a>", $id, __( 'Re-smush', WP_SMPRO_DOMAIN ) );
				} else {
					if ( wp_attachment_is_image( $id ) ) {
						print __( 'Not processed', WP_SMPRO_DOMAIN );
						printf( "<br><a href=\"admin.php?action=wp_smpro_queue&amp;attachment_id=%d\">%s</a>", $id, __( 'Smush.it now!', WP_SMPRO_DOMAIN ) );
					}
				}
			}
		}

		// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/
		function add_bulk_actions_via_javascript() {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function ($) {
					$('select[name^="action"] option:last-child').before('<option value="bulk_smushit">Bulk Smush.it</option>');
				});
			</script>
		<?php
		}

		// Handles the bulk actions POST
		// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/
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
		 * Plugin setting functions
		 */
		function register_settings() {

			add_settings_section( 'wp_smpro_settings', 'WP Smush.it Pro', array(
				&$this,
				'settings_cb'
			), 'media' );

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

		function settings_cb() {
			return;
		}

		function render_checked( $key ) {
			$opt_name   = 'wp_smpro_' . $key;
			$const_name = strtoupper( $opt_name );
			$opt_val    = intval( get_option( $opt_name, constant( $const_name ) ) );

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
		 * Adds a setting field, Keep exif data or not
		 */
		function render_remove_meta_opts() {
			echo $this->render_checked( 'remove_meta' );
		}

		/**
		 * Adds a setting field, Keep exif data or not
		 */
		function render_progressive_opts() {
			echo $this->render_checked( 'progressive' );
		}

		/**
		 * Adds a setting field, Allow GIF to PNG conversion for single frame images
		 */
		function render_gif_to_png_opts() {
			echo $this->render_checked( 'gif_to_png' );
		}

		function admin_init() {
			wp_enqueue_script( 'common' );
		}

	}

}
