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

			//Scan the given directory path for the list of images
			add_action( 'wp_ajax_get_image_list', array( $this, 'get_image_list' ) );

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
			wp_nonce_field( 'smush_get_dir_list', 'list_nonce' );
			wp_nonce_field( 'smush_get_image_list', 'image_list_nonce' ); ?>
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
						<button class="wp-smush-select-dir"><?php esc_html_e( "Select", "wp-smushit" ); ?></button>
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

		/**
		 * @param SplFileInfo $file
		 * @param mixed $key
		 * @param RecursiveCallbackFilterIterator $iterator
		 *
		 * @return bool True if you need to recurse or if the item is acceptable
		 */
		function exclude_files( $file, $key, $iterator ) {
			// Will exclude everything under these directories
			$exclude = array( '.php', '.svg' );
			if ( $iterator->hasChildren() && ! in_array( $file->getFilename(), $exclude ) ) {
				return true;
			}

			return $file->isFile();
		}

		function get_image_list() {

			//Check For Permission
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( "Unauthorized" );
			}

			//Verify nonce
			check_ajax_referer( 'smush_get_image_list', 'image_list_nonce' );

			//Check if directory path is set or not
			if ( empty( $_GET['path'] ) ) {
				wp_send_json_error( "Empth Directory Path" );
			}

			//Directory Path
			$path = realpath( $_GET['path'] );

			//Directory Iterator, Exclude . and ..
			$innerIterator = new RecursiveDirectoryIterator(
				$path,
				RecursiveDirectoryIterator::SKIP_DOTS
			);

			//File Iterator
			$iterator = new RecursiveIteratorIterator(
				new RecursiveCallbackFilterIterator( $innerIterator, array( $this, 'exclude_files' ) ),
				RecursiveIteratorIterator::CHILD_FIRST
			);

			$files = array();
			foreach ( $iterator as $path ) {

				if ( $path->isFile() ) {
					$file_path = $path->getPathname();
					$file_name = $path->getFilename();
					if( !file_exists( $file_path ) ) {
						echo $file_path;
					}

					if ( $this->is_image( $file_path ) ) {
						$dir_name = dirname( $file_path );

						//Initialize if dirname doesn't exists in array already
						if ( ! isset( $files[ $dir_name ] ) ) {
							$files[ $dir_name ] = array();
						}
						$files[ $dir_name ][ $file_name ] = $file_path;
					}
				}
			}
			wp_send_json_success( $files );

		}

		/**
		 * Check whether the given path is a image or not
		 *
		 * @param $path
		 *
		 * @return bool
		 *
		 */
		function is_image( $path ) {

			//Check if the path is valid
			if ( ! file_exists( $path ) || ! $this->is_image_from_extension( $path ) ) {
				return false;
			}

			$a = getimagesize( $path );

			//If a is not set
			if ( ! $a || empty( $a ) ) {
				return false;
			}

			$image_type = $a[2];

			if ( in_array( $image_type, array( IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG ) ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Check if the given file path is a supported image format
		 *
		 * @param $path File Path
		 *
		 * @return bool Whether a image or not
		 */
		function is_image_from_extension( $path ) {
			$supported_image = array(
				'gif',
				'jpg',
				'jpeg',
				'png'
			);
			$ext             = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ); // Using strtolower to overcome case sensitive
			if ( in_array( $ext, $supported_image ) ) {
				return true;
			}

			return false;
		}
	}

	//Class Object
	global $wpsmush_all;
	$wpsmush_all = new WpSmushAll();
}