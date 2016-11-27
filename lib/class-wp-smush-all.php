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
				   class="wp-smush-browse wp-smush-button button"><?php esc_html_e( "Select", "wp-smushit" ); ?></a>
			</div>
			<div class="wp-smush-scan-result">
				<p class="wp-smush-div-heading"><b><?php esc_html_e( "IMAGES TO BE OPTIMISED", "wp-smushit" ); ?></b></p>
				<button class="wp-smush-start"><?php esc_html_e("SMUSH NOW", "wp-smushit"); ?></button>
				<div class="wp-smush-folder-stats">
					<!-- Show the pretty Graph with a circle, total image count, optimised images, savings-->
				</div>
				<div class="content">
					<!-- Show a list of images, inside a fixed height div, with a scroll. As soon as the image is
					optimised show a tick mark, with savings below the image. Scroll the li each time for the
					current optimised image -->

				</div>
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
						<div class="wp-smush-select-button-wrap">
							<span class="spinner"></span>
							<button class="wp-smush-select-dir"><?php esc_html_e( "Scan", "wp-smushit" ); ?></button>
						</div>
					</div>
				</div>
			</div>
			<input type="hidden" name="wp-smush-base-path" value="<?php echo $this->get_root_path(); ?>"><?php
		}

		/**
		 * Return a directory/File list
		 */
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
								//Skip Uploads folder - Media Files

								$list .= "<li class='directory collapsed'><a rel='" . $htmlRel . "/'>" . $htmlName . "</a></li><br />";
							} else if ( ( ! $onlyFolders || $onlyFiles ) && in_array( $ext, $supported_image ) ) {
								$list .= "<li class='file ext_{$ext}'><a rel='" . $htmlRel . "'>" . $htmlName . "</a></li><br />";
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
		function exclude( $file, $key, $iterator ) {
			// Will exclude everything under these directories
			$exclude_dir = array( '.git', 'test' );

			//Exclude from the list, if one of the media upload folders
			if ( $this->is_subfolder( $file->getPath() ) ) {
				return true;
			}

			//Exclude Directories like git, and test
			if ( $iterator->hasChildren() && ! in_array( $file->getFilename(), $exclude_dir ) ) {
				return true;
			}

			//Do not exclude, if image
			if ( $file->isFile() && $this->is_image_from_extension( $file->getPath() ) ) {
				return true;
			}

			return $file->isFile();
		}

		/**
		 * Handles Ajax request to obtain the Image list within a selected directory path
		 *
		 */
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
			$base_dir = realpath( $_GET['path'] );

			//Directory Iterator, Exclude . and ..
			$innerIterator = new RecursiveDirectoryIterator(
				$base_dir,
				RecursiveDirectoryIterator::SKIP_DOTS
			);

			//File Iterator
			//@todo: Manual Filtering since Recursive callback iterator is 5.4.0+
			$iterator = new RecursiveIteratorIterator( $innerIterator,
				RecursiveIteratorIterator::CHILD_FIRST
			);

			$files = array();
			foreach ( $iterator as $path ) {

				if ( $path->isFile() ) {
					$file_path = $path->getPathname();
					$file_name = $path->getFilename();
					if ( ! file_exists( $file_path ) ) {
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

			$markup = $this->generate_image_list( $files );

			wp_send_json_success( $markup );

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

		/**
		 *
		 * @param $path
		 *
		 * @return bool
		 *
		 * Borrowed from Shortpixel - (y)
		 *
		 */
		public function is_subfolder( $path ) {
			$upload_dir  = wp_upload_dir();
			$base_dir    = $upload_dir["basedir"];
			$upload_path = $upload_dir["path"];

			//If matches the current upload path
			if ( $upload_path == $path ) {
				return true;
			}

			//contains one of the year subfolders of the media library
			if ( strpos( $path, $upload_path ) == 0 ) {
				$pathArr = explode( '/', str_replace( $base_dir . '/', "", $path ) );
				if ( count( $pathArr ) >= 1
				     && is_numeric( $pathArr[0] ) && $pathArr[0] > 1900 && $pathArr[0] < 2100 //contains the year subfolder
				     && ( count( $pathArr ) == 1 //if there is another subfolder then it's the month subfolder
				          || ( is_numeric( $pathArr[1] ) && $pathArr[1] > 0 && $pathArr[1] < 13 ) )
				) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Creates a tree out of Given path array
		 *
		 * @param $path_list Array of path and images
		 *
		 * @param $base_dir Selected Base Path for the image search
		 *
		 */
		function build_tree( $path_list, $base_dir ) {
			$path_tree = array();
			foreach ( $path_list as $path => $images ) {
				$path     = str_replace( $base_dir, '', $path );
				$list     = explode( '/', trim( $path, '/' ), 3 );
				$last_dir = &$path_tree;
				$length = sizeof( $list );
				foreach ( $list as $dir ) {
					$length--;
					$last_dir =& $last_dir[ $dir ];
				}
				echo "<pre>";
				print_r( $path_tree );
				print_r( $images );
				print_r( $dir );
				echo "</pre>";
				exit;
			}
			return $path_tree;
		}

		/*
		 * Generate the markup for all the images
		 */
		function generate_markup( $images ) {
			$div = '<ul class="wp-smush-image-list">';
			foreach( $images as $image_path => $image ) {
				$count = sizeof( $image );
				if( is_array( $image ) && $count > 1 ) {
					$div .= "<li class='wp-smush-image-ul'><span class='wp-smush-li-path'>{$image_path} - " . sprintf( esc_html__( "%d image(s)", "wp-smushit" ), $count ) . "</span>
					<ul class='wp-smush-image-list-inner'>";
					foreach ($image as $item ) {
						$div .= "<li class='wp-smush-image-ele' id='{$item}'>{$item}";
						//Optimisation Status
						$div .= "<span class='wp-smush-image-ele-optimised'></span>";
						//Div to show stats after
						$div .= "<div class='wp-smush-image-ele-stats'></div>";
						//Close LI
						$div .= "</li>";
					}
					$div .= "</ul>
					</li>";
				}else{
					$image_p = array_pop( $image );
					$div .= "<li class='wp-smush-image-ele' id='{$image_p}'>{$image_p}";
					//Optimisation Status
					$div .= "<span class='wp-smush-image-ele-optimised' title='". esc_html_e("", "wp-smushit") ."'></span>";
					//Div to show stats after
					$div .= "<div class='wp-smush-image-ele-stats'></div>";
					//Close LI
					$div .= "</li>";
				}
			}
			$div .= '</ul>';
			return $div;

		}

		/**
		 *
		 * Create a tree and markup for the same from given file list
		 *
		 * @param $files List of files in their respective directories
		 *
		 * @param $base_dir Base Path to search for images
		 *
		 */
		function generate_image_list( $files ) {
			return $this->generate_markup( $files );
		}
	}

	//Class Object
	global $wpsmush_all;
	$wpsmush_all = new WpSmushAll();
}