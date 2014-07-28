<?php

/**
 * @package SmushItPro
 * @subpackage Admin
 * @version 1.0
 * 
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 * 
 * @copyright (c) 2014, Incsub (http://incsub.com)
 */
if (!class_exists('WpSmProMediaLibrary')) {

	/**
	 * Show settings in Media settings and add column to media library
	 * 
	 */
	class WpSmProMediaLibrary {

		public function __construct() {

			$wpmudev_apikey = get_site_option( 'wpmudev_apikey' );

			// add the admin option screens
			add_action( 'admin_init', array( $this, 'admin_init' ) );

			if ( ! empty( $wpmudev_apikey ) ) {
				// add extra columns for smushing to media lists
				add_filter( 'manage_media_columns', array( $this, 'columns' ) );
				add_action( 'manage_media_custom_column', array( $this, 'custom_column' ), 10, 2 );
			}
			
			// hook js for bulk actions
			add_action( 'admin_head-upload.php', array( $this, 'add_bulk_actions_js' ) );
			
			// hook handler for bulk smushing action
			add_action( 'admin_action_bulk_smushit', array( $this, 'bulk_action_handler' ) );

			
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
			if ( 'smushit' != $column_name ) {
				return;
			}

			$attachment_file_path = get_attached_file($id);

			// otherwise, get the smush meta
			$smush_meta_full = get_post_meta( $id, 'smush_meta_full', true );
			
			// if there's smush details, show it
			if ( ! empty( $smush_meta_full ) ) {
				?>
				<p class="smush-status">
					<?php echo $smush_meta_full['status_msg']; ?>
				</p>
				<button class="wp-smpro-smush button">
					<span>
						<?php _e('Re-smush', WP_SMPRO_DOMAIN); ?>
					</span>
				</button>
				<?php
			} else {
				// not smushed yet, check if attachment is image
				if ( wp_attachment_is_image( $id ) ) {
					?>
					<p class="smush-status">
						<?php _e( 'Not processed', WP_SMPRO_DOMAIN ); ?>
					</p>
					<button class="wp-smpro-smush button">
						<span>
							<?php _e('Smush.it now!', WP_SMPRO_DOMAIN); ?>
						</span>
					</button>
				<?php
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
			wp_redirect( add_query_arg( '_wpnonce', wp_create_nonce( 'wp-smpro-admin' ), admin_url( 'upload.php?page=wp-smpro-admin&goback=1&ids=' . $ids ) ) );
			exit();
		}
		
		/**
		 * enqueue common script
		 */
		function admin_init() {
			wp_enqueue_script( 'common' );
		}
		
		


	}

}