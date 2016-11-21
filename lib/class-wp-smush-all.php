<?php
/**
 * @package WP Smush
 * @subpackage Admin
 * @since 2.6
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2016, Incsub (http://incsub.com)
 */

if ( ! class_exists( 'WpSmushAll' ) ) {

	class WpSmushAll {

		function __construct() {

			//Handle Ajax request 'smush_get_directory_list'
			add_action( 'wp_ajax_smush_get_directory_list', array( $this, 'directory_list' ) );

		}

		function admin_menu() {
			global $wpsmushit_admin;
			$wpsmushit_admin->bulk_ui->smush_page_header();
			//Page Content
			$this->ui();
			$wpsmushit_admin->bulk_ui->smush_page_footer();
		}

		/**
		 * Output the required UI for WP Smush All page
		 */
		function ui() {
			wp_nonce_field( 'smush_get_dir_list', 'list_nonce' );?>
			<div class="wp-smush-dir-browser">
				<label for="wp-smush-dir"><?php esc_attr_e( "Image Directories", "wp-smushit" ); ?>
					<input type="text" value="" class="wp-smush-dir-path" name="smush_dir_path" id="wp-smush-dir">
				</label>
				<a href="#"
				   class="wp-smush-browse wp-smush-button button"><?php esc_html_e( "Browse", "wp-smushit" ); ?></a>
			</div>
			<div class="dev-overlay wp-smush-list-dialog">
				<div class="back"></div>
				<div class="box-scroll">
					<div class="box">
						<div class="title"><h3><?php esc_html_e( "Directory list", "wp-smushit" ); ?></h3>
							<div class="close" aria-label="Close">×</div>
						</div>
						<div class="content">
							<div class="wp-smush-loading-wrap">
								<span class="spinner"></span>
								<span class="wp-smush-loading-text">Loading..</span>
							</div>
						</div>
						<button class="wp-smush-select-dir"><?php esc_html_e("Select", "wp-smushit"); ?></button>
					</div>
				</div>
			</div>
			<input type="hidden" name="wp-smush-base-path" value="<?php echo $this->get_root_path(); ?>">
			<div class="wp-smush-scan-wrap">
				<button type="button" class="wp-smush-scan wp-smush-button">
					<?php esc_html_e( "Scan", "wp-smushit" ); ?>
				</button>
				<span class="spinner"></span>
			</div><?php
		}

		function directory_list() {
			//Check For Permission
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( "Unauthorized" );
			}
			//Verify nonce
			check_ajax_referer( 'smush_get_dir_list', 'list_nonce' );

			//Get the Root path for a main site or subsite
			$root = $this->get_root_path();

			$postDir = rawurldecode( $root . ( isset( $_GET['dir'] ) ? $_GET['dir'] : null ) );

			$supported_image = array(
				'gif',
				'jpg',
				'jpeg',
				'png'
			);

			// set checkbox if multiSelect set to true
			$checkbox    = ( isset( $_GET['multiSelect'] ) && $_GET['multiSelect'] == 'true' ) ? "<input type='checkbox' />" : null;
			$onlyFolders = ( '/' == $_GET['dir'] || isset( $_GET['onlyFolders'] ) && $_GET['onlyFolders'] == 'true' ) ? true : false;
			$onlyFiles   = ( isset( $_GET['onlyFiles'] ) && $_GET['onlyFiles'] == 'true' ) ? true : false;

			if ( file_exists( $postDir ) ) {

				$files     = scandir( $postDir );
				$returnDir = substr( $postDir, strlen( $root ) );

				natcasesort( $files );

				if ( count( $files ) > 2 ) {
					$list = "<ul class='jqueryFileTree'>";
					foreach ( $files as $file ) {

						$htmlRel  = htmlentities( $returnDir . $file );
						$htmlName = htmlentities( $file );
						$ext      = preg_replace( '/^.*\./', '', $file );

						if ( file_exists( $postDir . $file ) && $file != '.' && $file != '..' ) {
							if ( is_dir( $postDir . $file ) && ( ! $onlyFiles || $onlyFolders ) ) {
								$list .= "<li class='directory collapsed'>{$checkbox}<a rel='" . $htmlRel . "/'>" . $htmlName . "</a></li>";
							} else if ( ( ! $onlyFolders || $onlyFiles ) && in_array( $ext, $supported_image ) ) {
								$list .= "<li class='file ext_{$ext}'>{$checkbox}<a rel='" . $htmlRel . "'>" . $htmlName . "</a></li>";
							}
						}
					}

					$list .= "</ul>";
				}
			}
			echo $list;
			die();

		}

		public function get_root_path() {
			if ( is_main_site() ) {

				return rtrim( get_home_path(), '/' );
			} else {
				$up = wp_upload_dir();

				return $up['basedir'];
			}
		}

	}

	//Class Object
	global $wpsmush_all;
	$wpsmush_all = new WpSmushAll();
}