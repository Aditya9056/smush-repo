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

if ( ! class_exists( 'WpSmushDir' ) ) {

	class WpSmushDir {

		/**
		 * @var Contains a list of optimised images
		 */
		public $optimised_images;

		/**
		 * @var Total Stats for the image optimisation
		 *
		 */
		public $stats;

		function __construct() {

			if ( ! $this->should_continue() ) {
				return;
			}

			//Hook UI at the end of Settings UI
            add_action( 'smush_settings_ui_bottom', array($this, 'ui') );

			//Output Stats after Resize savings
			add_action('stats_ui_after_resize_savings', array( $this, 'stats_ui') );

			//Handle Ajax request 'smush_get_directory_list'
			add_action( 'wp_ajax_smush_get_directory_list', array( $this, 'directory_list' ) );

			//Scan the given directory path for the list of images
			add_action( 'wp_ajax_image_list', array( $this, 'image_list' ) );

			//Handle Ajax Request to optimise images
			add_action( 'wp_ajax_optimise', array( $this, 'optimise' ) );

			//Handle Exclude path request
			add_action( 'wp_ajax_smush_exclude_path', array( $this, 'smush_exclude_path' ) );

			//Handle Ajax request: resume scan
			add_action( 'wp_ajax_resume_scan', array( $this, 'resume_scan' ) );

			//Handle Ajax request for directory smush stats
			add_action('wp_ajax_get_dir_smush_stats', array( $this, 'get_dir_smush_stats' ) );

		}

		function should_continue() {
			global $WpSmush;

			//Check If pro User
			$api_auth = get_site_option( 'wp_smush_api_auth' );

			//no api key set, always false
			$api_key = $WpSmush->_get_api_key();

			//Check if need to revalidate
			if ( empty( $api_key ) || ! $api_auth || empty( $api_auth ) || empty( $api_auth[ $api_key ] ) ) {
				return false;
			}

			return true;
		}

		function stats_ui() { ?>
			<hr /><?php
			$dir_smush_stats = get_option( 'dir_smush_stats' );
			$human           = $percent = 0;
			if ( ! empty( $dir_smush_stats ) && ! empty( $dir_smush_stats['dir_smush'] ) ) {
				$human   = ! empty( $dir_smush_stats['dir_smush']['percent'] ) && ! $dir_smush_stats['dir_smush']['percent'] > 0 ? $dir_smush_stats['dir_smush']['bytes'] : 0;
				$percent = ! empty( $dir_smush_stats['dir_smush']['percent'] ) && $dir_smush_stats['dir_smush']['percent'] > 0 ? number_format_i18n( $dir_smush_stats['dir_smush']['percent'], 1, '.', '' ) : 0;
			} ?>
            <!-- Savings from Directory Smush -->
            <div class="row smush-dir-savings">
                <span class="float-l wp-smush-stats-label"><strong><?php esc_html_e( "DIRECTORY SMUSH SAVINGS", "wp-smushit" ); ?></strong></span>
                <span class="float-r wp-smush-stats"><?php
	                if ( ! empty( $dir_smush_stats ) && $human == 0 && $percent < 1 ) {
	                    //If smush percentage is lower, Show stats as < 1Kb
		                $human = "< 1KB";
		                $percent = "< 1";
	                }?>
                    <span class="spinner" style="visibility: visible"
                          title="<?php esc_html_e( "Updating Stats", "wp-smushit" ); ?>"></span>
                    <span class="wp-smush-stats-human"><?php echo !empty( $human ) ? $human : ''; ?></span><?php
                    if( $percent > 1 ) { ?>
                        <span class="wp-smush-stats-sep">/</span>
                        <span class="wp-smush-stats-percent"><?php echo ! empty( $percent ) ? $percent : ''; ?>%</span><?php
                    } ?>
				</span>
            </div><?php
        }

		/**
		 *  Create the Smush image table to store the paths of scanned images, and stats
		 */
		function create_table() {
			global $wpdb;

			$charset_collate = $wpdb->get_charset_collate();

			//If path index is not specified WPDb gives an error, Lower Index size for utf8mb4
			if ( empty( $path_index_size ) && strpos( $charset_collate, 'utf8mb4' ) ) {
				$path_index_size = 191;
			} else {
				$path_index_size = 255;
			}

			/**
			 * Table: wp_smush_dir_images
			 * Columns:
			 * id -> Auto Increment ID
			 * path -> Absolute path to the image file
			 * resize -> Whether the image was resized or not
			 * image_size -> Current image size post optimisation
			 * orig_size -> Original image size before optimisation
			 * file_time -> Unix time for the file creation, to match it against the current creation time,
			 *                  in order to confirm if it is optimised or not
			 * last_scanned -> Timestamp, Get images form last scan by latest timestamp
			 *                  are from latest scan only and not the whole list from db
			 * meta -> For any future use
			 *
			 */
			$sql = "CREATE TABLE {$wpdb->prefix}smush_dir_images (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				path text NOT NULL,
				resize varchar(55),
				error varchar(55) DEFAULT NULL,
				image_size int(10) unsigned,
				orig_size int(10) unsigned,
				file_time int(10) unsigned,
				last_scanned timestamp DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
				meta text,
				UNIQUE KEY id (id),
				UNIQUE KEY path (path($path_index_size)),
				KEY image_size (image_size)
			) $charset_collate;";

			// include the upgrade library to initialize a table
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}

		/**
		 * Query db for a unsmushed latest scanned image
		 *
		 * @param int $limit Number of rows to fetch
         *
		 * @return bool True/False
		 */
		function get_scanned_images( $limit = 1 ) {
			global $wpdb;

			//Get the latest scanned unsmushed row, if any
			$query   = $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}smush_dir_images t1 WHERE image_size IS NULL && error IS NULL && last_scanned = (SELECT MAX(last_scanned) FROM {$wpdb->prefix}smush_dir_images t2 WHERE t1.id = t2.id ORDER BY t2.id )  GROUP BY id LIMIT %d", $limit );
			$results = $wpdb->get_col( $query );

			if ( empty( $results ) ) {
				return false;
			} elseif ( is_wp_error( $results ) ) {
				error_log( sprintf( "WP Smush Query Error in %s at %s: %s", __FILE__, __LINE__, $results->get_error_message() ) );
				return false;
			}

			return true;
		}

		/**
		 * Prints a resume button if required
		 */
		function show_resume_button() {
			if ( ! $this->get_scanned_images() ) {
				return null;
			}
			//Print the button ?>
            <button class="wp-smush-resume wp-smush-button button"><?php esc_html_e( "RESUME LAST SCAN", "wp-smushit" ); ?></button><?php
		}

		/**
		 * Output the required UI for WP Smush All page
		 */
		function ui() {
			//Print Directory Smush UI, if not a network site
			if( is_network_admin() ) {
				return;
			}

			global $wpsmushit_admin;
			wp_nonce_field( 'smush_get_dir_list', 'list_nonce' );
			wp_nonce_field( 'smush_get_image_list', 'image_list_nonce' );

			/** Directory Browser and Image List **/
			$wpsmushit_admin->bulk_ui->container_header( 'wp-smush-dir-browser', 'wp-smush-dir-browser', esc_html__( "DIRECTORY SMUSH", "wp-smushit" ) ); ?>
            <div class="box-content">
            <div class="row">
                <div class="wp-smush-dir-desc roboto-regular">
                    <!-- Description -->
                    <?php esc_html_e("In addition to smushing your media uploads, you may want to also smush images living outside your uploads directory. Simply add any directories you wish to smush and bulk smush away!", "wp-smushit"); ?>
                </div>
                <!-- Directory Path -->
                <input type="hidden" class="wp-smush-dir-path" value="" />
                <div class="wp-smush-scan-result hidden">
                    <hr class="primary-separator"/>
                    <div class="wp-smush-all-button-wrap top">
                        <!-- @todo: Check status of the images in last scan and do not show smush now button, if already finished -->
                        <button class="wp-smush-start"><?php esc_html_e( "BULK SMUSH", "wp-smushit" ); ?></button>
                        <button type="button" title="<?php esc_html_e( "Click to stop the directory smushing process.", "wp-smushit" ); ?>" class="button button-grey wp-smush-pause disabled"><?php esc_html_e( "CANCEL", "wp-smushit" ); ?></button>
                        <span class="spinner"></span>
                    </div>
                    <div class="content">
                        <!-- Show a list of images, inside a fixed height div, with a scroll. As soon as the image is
						optimised show a tick mark, with savings below the image. Scroll the li each time for the
						current optimised image -->
                    </div>
                    <!-- Notices -->
                    <div class="wp-smush-notice wp-smush-dir-all-done hidden" tabindex="0">
                        <i class="dev-icon dev-icon-tick"></i><?php esc_html_e( "All images are smushed and up to date. Awesome!", "wp-smushit" ); ?>
                    </div>
                    <div class="wp-smush-notice wp-smush-dir-remaining hidden" tabindex="0">
                        <i class="dev-icon wdv-icon wdv-icon-fw wdv-icon-exclamation-sign"></i><?php printf( esc_html__( "%s/%s image(s) were successfully smushed, however %s image(s) could not be smushed due to an error.", "wp-smushit" ), '<span class="wp-smush-dir-smushed"></span>', '<span class="wp-smush-dir-total"></span>', '<span class="wp-smush-dir-remaining"></span>'); ?>
                    </div>
                    <div class="wp-smush-all-button-wrap bottom">
                        <!-- @todo: Check status of the images in last scan and do not show smush now button, if already finished -->
                        <button class="wp-smush-start"><?php esc_html_e( "BULK SMUSH", "wp-smushit" ); ?></button>
                        <button type="button" title="<?php esc_html_e( "Click to stop the directory smushing process.", "wp-smushit" ); ?>" class="button button-grey wp-smush-pause disabled"><?php esc_html_e( "CANCEL", "wp-smushit" ); ?></button>
                        <span class="spinner"></span>
                    </div><?php
	                //Nonce Field
	                wp_nonce_field( 'wp_smush_all', 'wp-smush-all' ); ?>
                    <input type="hidden" name="wp-smush-continue-ajax" value=1>
                </div>
                <div class="dir-smush-button-wrap">
                    <button class="wp-smush-browse wp-smush-button button"><?php esc_html_e( "CHOOSE DIRECTORY", "wp-smushit" ); ?></button><?php
		            //Optionally show a resume button, if there were images left from last scan
		            $this->show_resume_button(); ?>
                    <span class="spinner"></span>
                </div>
                <div class="dev-overlay wp-smush-list-dialog roboto-regular">
                    <div class="back"></div>
                    <div class="box-scroll">
                        <div class="box">
                            <div class="title"><h3><?php esc_html_e( "Directory list", "wp-smushit" ); ?></h3>
                                <div class="close" aria-label="Close">×</div>
                            </div>
                            <div class="wp-smush-instruct"><?php esc_html_e( "Choose the folder you wish to smush.", "wp-smushit" ); ?></div>
                            <div class="content">
                            </div>
                            <div class="wp-smush-select-button-wrap">
                                <div class="wp-smush-section-desc"><?php esc_html_e("Smush will also include any images in sub folders of your selected folder.", "wp-smushit"); ?></div>
                                <div class="wp-smush-select-button-wrap-child">
                                    <span class="spinner"></span>
                                    <button class="wp-smush-select-dir"><?php esc_html_e( "ADD DIRECTORY", "wp-smushit" ); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="wp-smush-base-path" value="<?php echo $this->get_root_path(); ?>">
            </div>
            </div><?php
			echo "</section>";

		}

		/**
		 * Check if the image file is media library file
		 *
		 * @param $file_path
		 *
		 * @return bool
		 *
		 */
		function is_media_library_file( $file_path ) {
			$upload_dir  = wp_upload_dir();
			$upload_path = $upload_dir["path"];

			//Get the base path of file
			$base_dir = dirname( $file_path );
			if ( $base_dir == $upload_path ) {
				return true;
			}

			return false;
		}

		/**
		 * Return a directory/File list
		 */
		function directory_list() {
			//Check For Permission
			if ( ! current_user_can( 'manage_options' ) || ! is_user_logged_in() ) {
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
							if ( is_dir( $postDir . $file ) && ( ! $onlyFiles || $onlyFolders ) && ! $this->skip_dir( $postDir . $file ) ) {
								//Skip Uploads folder - Media Files
								$list .= "<li class='directory collapsed'><a rel='" . $htmlRel . "/'>" . $htmlName . "</a></li><br />";
							} else if ( ( ! $onlyFolders || $onlyFiles ) && in_array( $ext, $supported_image ) && ! $this->is_media_library_file( $postDir . $file ) ) {
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
			if ( $this->skip_dir( $file->getPath() ) ) {
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
         * Display a progress bar for the images in particular directory
		 *
		 * @param $count
		 * @param $optimised
		 * @param $dir_path
		 *
		 * @return bool|string
		 */
         function progress_ui( $count, $optimised, $dir_path ) {

			if ( ! $count ) {
				return false;
			}

			$width = ( $optimised > 0 ) ? ( $optimised / $count ) * 100 : 0;

			$class   = 0 < $width && 100 == $width ? 'complete' : 'partial';
			$o_class = 0 == $width ? 'hidden' : '';

			$content = "<div class='wp-smush-dir-progress-wrap {$o_class}'>";
			$content .= '<span class="smush-percent">' . number_format_i18n( $width, 0 ) . '% </span>';
			$content .= "<div class='wp-smush-dir-progress-wrap-inner'>
		        <span class='wp-smush-dir-progress {$class}' style='width: {$width}px'></span>
		        </div>
		    </div>";

			$content .= 0 == $width ? "<a href='#' class='wp-smush-exclude-dir' data-path='" . $dir_path . "' title='" . esc_html__( "Exclude directory from Smush List", "wp-smushit" ) . "'>&times;</a>" : '';

			return $content;
		}

		/**
         * Get the image list in a specified directory path
         *
		 * @param string $path
		 *
		 * @return string
		 */
		function get_image_list( $path = '' ) {
			global $wpdb;

			$base_dir = empty( $path ) ? $_GET['path'] : $path;
			//Directory Path
			$base_dir = realpath( $base_dir );

			//Store the path in option
			update_option( 'wp_smush_dir_path', $base_dir );

			//Directory Iterator, Exclude . and ..
			$dirIterator = new RecursiveDirectoryIterator(
				$base_dir,
				RecursiveDirectoryIterator::SKIP_DOTS
			);

			$filtered_dir = new WPSmushRecursiveFilterIterator( $dirIterator );

			//File Iterator
			$iterator = new RecursiveIteratorIterator( $filtered_dir,
				RecursiveIteratorIterator::CHILD_FIRST
			);

			//Iterate over the file List
			$files_arr = array();
			$images    = array();
			$count     = 0;
			$timestamp = current_time('mysql');
			foreach ( $iterator as $path ) {

				if ( $path->isFile() ) {
					$file_path = $path->getPathname();
					$file_name = $path->getFilename();

					if ( $this->is_image( $file_path ) && ! $this->is_media_library_file( $file_path ) ) {

						/**  To generate Markup **/
						$dir_name = dirname( $file_path );

						//Initialize if dirname doesn't exists in array already
						if ( ! isset( $files_arr[ $dir_name ] ) ) {
							$files_arr[ $dir_name ] = array();
						}
						$files_arr[ $dir_name ][ $file_name ] = $file_path;
						/** End */

						//Get the file modification time
						$file_time  = @filectime( $file_path );

						//If the image is not in db already, insert
//						if ( ! array_key_exists( $file_path, $image_list ) ) {
							/** To be stored in DB, Part of code borrowed from Ewwww Optimiser  */
							$image_size = $path->getSize();
							$images[]   = "('" . utf8_encode( $file_path ) . "',$image_size, $file_time, '$timestamp' )";
							$count ++;
//						}
					}
				}
				//Store the Images in db at an interval of 5k
				if ( $count >= 5000 ) {
					$count = 0;
					$query = "INSERT INTO {$wpdb->prefix}smush_dir_images (path,orig_size,file_time,last_scanned) VALUES %s ON DUPLICATE KEY UPDATE file_time = VALUES(file_time)";
					$sql = sprintf( $query, implode( ',', $images ) );
					$wpdb->query( $sql );
					$images = array();
				}
			}

			//Update rest of the images
			if ( ! empty( $images ) && $count > 0 ) {
				$query = "INSERT INTO {$wpdb->prefix}smush_dir_images (path,orig_size,file_time,last_scanned) VALUES %s ON DUPLICATE KEY UPDATE file_time = VALUES(file_time)";
				$sql = sprintf( $query, implode( ',', $images ) );
				$wpdb->query( $sql );
			}

			//If files array is not empty
			if( !empty( $files_arr ) ) {
				update_option('wp_smush_scan', array('path' => $base_dir ) );
			}

			return array('files_arr' => $files_arr, 'base_dir' => $base_dir );
        }

		/**
		 * Sends a Ajax response if no images are found in selected directory
		 */
        function send_error() {
	        $message = sprintf("<div class='wp-smush-info notice notice-info roboto-regular'>%s</div>", esc_html__("We could not find any images in the selected directory.", "wp-smushit") );
	        wp_send_json_error( array( 'message' => $message ) );
        }

		/**
		 * Handles Ajax request to obtain the Image list within a selected directory path
		 *
		 */
		function image_list() {

			//Check For Permission
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( "Unauthorized" );
			}

			//Verify nonce
			check_ajax_referer( 'smush_get_image_list', 'image_list_nonce' );

			//Check if directory path is set or not
			if ( empty( $_GET['smush_path'] ) ) {
				wp_send_json_error( "Empth Directory Path" );
			}

			//Get the File list
			$files = $this->get_image_list( $_GET['smush_path'] );

			//If files array is empty, send a message
			if ( empty( $files['files_arr'] ) ) {
			    $this->send_error();
			}

			//Get the markup from the list
			$markup = $this->generate_markup( $files['files_arr'], $files['base_dir'] );

			//Send response
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

			$a = @getimagesize( $path );

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
		 * Excludes the Media Upload Directory ( Checks for Year and Month )
		 *
		 * @param $path
		 *
		 * @return bool
		 *
		 * Borrowed from Shortpixel - (y)
		 *
		 * @todo: Add a option to filter images if User have turned off the Year and Month Organize option
		 *
		 */
		public function skip_dir( $path ) {
			$upload_dir  = wp_upload_dir();
			$base_dir    = $upload_dir["basedir"];
			$upload_path = $upload_dir["path"];

			$skip = false;

			//If matches the current upload path
			if ( strpos( $path, $upload_path ) == 0 ) {
				//contains one of the year subfolders of the media library
				$pathArr = explode( '/', str_replace( $base_dir . '/', "", $path ) );
				if ( count( $pathArr ) >= 1
				     && is_numeric( $pathArr[0] ) && $pathArr[0] > 1900 && $pathArr[0] < 2100 //contains the year subfolder
				     && ( count( $pathArr ) == 1 //if there is another subfolder then it's the month subfolder
				          || ( is_numeric( $pathArr[1] ) && $pathArr[1] > 0 && $pathArr[1] < 13 ) )
				) {
					$skip = true;
				}
			}
			/**
			 * Can be used to skip/include folders matching a specific directory path
             *
			 */
			apply_filters( 'wp_smush_skip_folder', $skip, $path );

			return $skip;
		}

		/**
		 * Creates a tree out of Given path array
		 *
		 * @param $path_list Array of path and images
		 * @param $base_dir Selected Base Path for the image search
         *
		 * @return array Array of images, Child Directories and images inside
         *
		 */
		function build_tree( $path_list, $base_dir ) {
			$path_tree = array();
			foreach ( $path_list as $path => $images ) {
				$path     = str_replace( $base_dir, '', $path );
				$list     = explode( '/', trim( $path, '/' ), 3 );
				$last_dir = &$path_tree;
				$length   = sizeof( $list );
				foreach ( $list as $dir ) {
					$length --;
					$last_dir =& $last_dir[ $dir ];
				}
			}

			return $path_tree;
		}

		/**
         * Returns the count of optimised image
		 *
		 * @param array $images
		 *
		 * @return int
		 */
		function optimised_count( $images = array() ) {
			//If we have optimised images
			if ( ! empty( $images ) && is_array( $images ) ) {
				$optimised = 0;
				if ( ! is_array( $this->optimised_images ) ) {
					return 0;
				}
				foreach ( $images as $item ) {
					//Check if the image is already in optimised list
					if ( array_key_exists( $item, $this->optimised_images ) ) {
						$optimised ++;
					}
				}
			}

			return $optimised;
		}

		/*
		 * Generate the markup for all the images
		 */
		function generate_markup( $images ) {

			$this->total_stats();

			$div = wp_nonce_field('wp-smush-exclude-path', 'exclude-path-nonce', '', false );
			$div .= '<ul class="wp-smush-image-list roboto-regular">';
			//Flag - Whether to print top hr tag or not
			$hr = true;
			//Flag - Not to print hr tag, if first element is ul in the scanned image list
			$index = 1;
			foreach ( $images as $image_path => $image ) {
				$count = sizeof( $image );
				$wrapper_class = '';
				if ( is_array( $image ) && $count > 1 ) {

					//Get the number of optimised images for the given image array
					$optimised_count = $this->optimised_count( $image );

					if( $optimised_count > 0 ) {
					    $wrapper_class = $count == $optimised_count ? 'complete' : 'partial';
                    }

					$div .= "<li class='wp-smush-image-ul {$wrapper_class}'>";
					if( $hr && $index > 1 ) {
                        $div .= "<hr/>";
					}

					$div .= "<span class='wp-smush-li-path'>{$image_path} <span class='wp-smush-image-count'>" . sprintf( esc_html__( "%d images", "wp-smushit" ), $count ) . "</span></span>";
					$div .= $this->progress_ui( $count, $optimised_count, $image_path );
					$div .= "<ul class='wp-smush-image-list-inner'>";
					foreach ( $image as $item ) {
						//Check if the image is already in optimised list
						$class = is_array( $this->optimised_images ) && array_key_exists( $item, $this->optimised_images ) ? ' optimised' : '';

						$div .= "<li class='wp-smush-image-ele{$class}' id='{$item}'><span class='wp-smush-image-ele-status'></span><span class='wp-smush-image-path'>{$item}</span>";
						//Close LI
						$div .= "</li>";
					}
					$div .= "</ul>
					<hr />
					</li>";
					$hr = false;
				} else {
				    $hr = true;
					$image_p = array_pop( $image );
					//Check if the image is already in optimised list
					$class = is_array( $this->optimised_images ) && array_key_exists( $image_p, $this->optimised_images ) ? ' optimised' : '';
					$div .= "<li class='wp-smush-image-ele{$class}' id='{$image_p}'><span class='wp-smush-image-ele-status'></span><span class='wp-smush-image-path'>{$image_p}</span>";
					//Close LI
					$div .= "</li>";
				}
				$index++;
			}
			$div .= '</ul>';
			$div .= "<span class='waiting-message hidden' title='" . esc_html__( "Waiting..", "wp-smushit" ) . "'></span>";

			return $div;

		}

		/**
		 * Fetch all the optimised image, Calculate stats
		 *
		 * @return array Total Stats
		 *
		 */
		function total_stats() {
			global $wpdb;

			$offset    = 0;
			$optimised = 0;
			$limit     = 1000;
			$images    = array();

			$total = $wpdb->get_col( "SELECT count(id) FROM {$wpdb->prefix}smush_dir_images" );

			$total = ! empty( $total ) && is_array( $total ) ? $total[0] : 0;

			while ( $results = $wpdb->get_results( "SELECT path, image_size, orig_size FROM {$wpdb->prefix}smush_dir_images WHERE image_size IS NOT NULL LIMIT $offset, $limit ", ARRAY_A ) ) {
				if ( ! empty( $results ) ) {
					$images = array_merge( $images, $results );
				}
				$offset += $limit;
			}

			//Iterate over stats, Return Count and savings
			if ( ! empty( $images ) ) {
				$this->stats                     = array_shift( $images );
				$path                            = $this->stats['path'];
				$this->optimised_images[ $path ] = $this->stats;

				foreach ( $images as $im ) {
					foreach ( $im as $key => $val ) {
						if ( 'path' == $key ) {
							$this->optimised_images[ $val ] = $im;
							continue;
						}
						$this->stats[ $key ] += $val;
					}
					$optimised ++;
				}
			}

			//Get the savings in bytes and percent
			if ( ! empty( $this->stats ) ) {
				$this->stats['bytes']   = $this->stats['orig_size'] - $this->stats['image_size'];
				$this->stats['percent'] = number_format_i18n( ( ( $this->stats['bytes'] / $this->stats['orig_size'] ) * 100 ), 1 );
				//Convert to human readable form
				$this->stats['bytes'] = size_format( $this->stats['bytes'], 1 );
			}

			$this->stats['total']     = $total;
			$this->stats['optimised'] = $optimised;

			return $this->stats;

		}

		/**
		 * Returns the number of images scanned and optimised
		 *
		 * @return array
		 *
		 */
		function last_scan_stats() {
			global $wpdb;
			$query   = "SELECT id, image_size, orig_size FROM {$wpdb->prefix}smush_dir_images t1 WHERE last_scanned = (SELECT MAX(last_scanned) FROM {$wpdb->prefix}smush_dir_images t2 WHERE t1.id = t2.id ORDER BY t2.id ) GROUP BY id";
			$results = $wpdb->get_results( $query, ARRAY_A );
			$total   = count( $results );
			$smushed = 0;
			$stats   = array(
				'image_size' => 0,
				'orig_size'  => 0
			);

			//Get the Smushed count, and stats sum
			foreach ( $results as $image ) {
				if ( ! is_null( $image['image_size'] ) ) {
					$smushed ++;
				}
				//Summation of stats
				foreach ( $image as $k => $v ) {
					if ( 'id' == $k ) {
						continue;
					}
					$stats[ $k ] += $v;
				}
			}

			//Stats
			$stats['total']   = $total;
			$stats['smushed'] = $smushed;

			return $stats;
		}

		/**
		 * Handles the ajax request  for image optimisation in a folder
		 */
		function optimise() {
			global $wpdb, $WpSmush;

			//Verify the ajax nonce
			check_ajax_referer( 'wp_smush_all', 'nonce' );

			$error_msg = '';

			//Get the image from db, //@todo: Make function get unsmushed images
			$query   = "SELECT id, path, orig_size FROM {$wpdb->prefix}smush_dir_images t1 WHERE image_size IS NULL && error IS NULL && last_scanned = (SELECT MAX(last_scanned) FROM {$wpdb->prefix}smush_dir_images t2 WHERE t1.id = t2.id ORDER BY t2.id ) GROUP BY id LIMIT 2";
			$results = $wpdb->get_results( $query, ARRAY_A );

			$next = ! empty( $results[1] ) ? $results[1]['path'] : '';

			//If there is no result from the query
			if ( is_wp_error( $results ) || empty( $results ) ) {
				wp_send_json_error( array(
					'next' => $next
				) );
			}

			$curr_img = $results[0];

			//We have the image path, optimise
			$smush_results = $WpSmush->do_smushit( $curr_img['path'] );

			if ( is_wp_error( $smush_results ) ) {
				$error_msg = $smush_results->get_error_message();
			} else if ( empty( $smush_results['data'] ) ) {
				//If there are no stats
				$error_msg = esc_html__( "Image couldn't be optimised", "wp-smushit" );
			}

			if ( ! empty( $error_msg ) ) {

				//Store the error in DB
				//All good, Update the stats
				$query = "UPDATE {$wpdb->prefix}smush_dir_images SET error=%s WHERE id=%d LIMIT 1";
				$query = $wpdb->prepare( $query, $error_msg, $curr_img['id'] );
				$wpdb->query( $query );

				$error_msg = "<div class='wp-smush-error'>" . $error_msg . "</div>";

				wp_send_json_error(
					array(
						'error' => $error_msg,
						'image' => array( 'path' => $curr_img['path'] ),
						'next'  => $next
					)
				);
			}

			//All good, Update the stats
			$query = "UPDATE {$wpdb->prefix}smush_dir_images SET image_size=%d WHERE id=%d LIMIT 1";
			$query = $wpdb->prepare( $query, $smush_results['data']->after_size, $curr_img['id'] );
			$wpdb->query( $query );

			//Get Total stats
			$this->total_stats();

			//Get the total stats
			$total     = $this->stats;
			$last_scan = $this->last_scan_stats();

			//Show the image wise stats
			$image = array(
				'path'      => $curr_img['path'],
				'orig_size' => $curr_img['orig_size'],
				'img_size'  => $smush_results['data']->after_size
			);

			$bytes            = $image['orig_size'] - $image['img_size'];
			$image['savings'] = size_format( $bytes, 1 );
			$image['percent'] = number_format_i18n( ( ( $bytes / $image['orig_size'] ) * 100 ), 1 ) . '%';

			$data             = array(
				'image'       => $image,
				'next'        => $next,
				'total'       => $total,
				'latest_scan' => $last_scan
			);
			wp_send_json_success( $data );
		}

		/**
		 * Remove image/image from db based on path details
		 */
		function smush_exclude_path() {

			//Validate Ajax nonce
			check_ajax_referer( 'wp-smush-exclude-path', 'nonce' );

			//If we don't have path, send json error
			if ( empty( $_POST['path'] ) ) {
				wp_send_json_error( 'missing_path' );
			}

			global $wpdb;

			$path = realpath( $_POST['path'] );
			$table = "{$wpdb->prefix}smush_dir_images";
			if( is_file( $path ) ) {
			    $sql = sprintf( "DELETE FROM $table WHERE path='%s'", $path );
            }else{
			    $sql = sprintf( "DELETE FROM $table WHERE path LIKE '%s'", '%' . $path . '%' );
            }

            //Execute the query
			$result = $wpdb->query( $sql );

			if( $result ) {
			    wp_send_json_success();
            }else{
			    wp_send_json_error();
            }
		}

		/**
		 * Send the markup for image list scanned from a directory path
         *
		 */
		function resume_scan() {
			$dir_path = get_option( 'wp_smush_dir_path' );

			//If we don't get an error path, return an error message
			if ( empty( $dir_path ) ) {
				$message = "<div class='error'>" . esc_html__( "We were unable to retrieve the image list from last scan, please continue with a latest scan", "wp-smushit" ) . "</div>";
				wp_send_json_error( array( 'message' => $message ) );
			}

			//Else, Get the image list and then markup
			$file_list = $this->get_image_list( $dir_path );

			//If there are no image files in selected directory
			if( empty( $file_list['files_arr'] ) ) {
			    $this->send_error();
            }

			$markup    = $this->generate_markup( $file_list['files_arr'], $file_list['base_dir'] );
			wp_send_json_success( $markup );
		}

		/**
         * Combine the stats from Directory Smush and Media Library Smush
         *
		 * @param $stats Directory Smush stats
		 *
		 * @return array Combined array of Stats
		 */
		function combined_stats( $stats ) {

			if ( empty( $stats ) || empty( $stats['percent'] ) || empty( $stats['bytes'] ) ) {
				return array();
			}

			global $wpsmushit_admin;

			$result    = array();
			$dasharray = 125.663706144;

			//Initialize Global Stats
			$wpsmushit_admin->setup_global_stats();

			//@todo: Redundant code, Move this to a single function
			$smushed_count = ( $resmush_count = count( $wpsmushit_admin->resmush_ids ) ) > 0 ? $wpsmushit_admin->total_count - ( $resmush_count + $wpsmushit_admin->remaining_count ) : $wpsmushit_admin->smushed_count;
			$smushed_count = $smushed_count > 0 ? $smushed_count : 0;

			//Get the total/Smushed attachment count
			$total_attachments = $wpsmushit_admin->total_count + $stats['total'];
			$total_images      = $wpsmushit_admin->stats['total_images'] + $stats['total'];

			$smushed     = $smushed_count + $stats['optimised'];
			$savings     = ! empty( $wpsmushit_admin->stats ) ? $wpsmushit_admin->stats['bytes'] + $stats['bytes'] : $stats['bytes'];
			$size_before = ! empty( $wpsmushit_admin->stats ) ? $wpsmushit_admin->stats['size_before'] + $stats['orig_size'] : $stats['orig_size'];
			$percent     = $size_before > 0 ? ( $savings / $size_before ) * 100 : 0;

			//Store the stats in array
			$result = array(
				'total_count'   => $total_attachments,
				'smushed_count' => $smushed,
				'savings'       => size_format( $savings ),
				'percent'       => round( $percent, 1 ),
				'image_count'   => $total_images,
				'dash_offset'   => $total_attachments > 0 ? $dasharray - ( $dasharray * ( $smushed / $total_attachments ) ) : $dasharray,
				'tooltip_text'  => ! empty( $total_images ) ? sprintf( __( "You've smushed %d images in total.", "wp-smushit" ), $total_images ) : ''
			);

			return $result;
		}

		/**
		 * Returns Directory Smush stats and Cumulative stats
         *
		 */
		function get_dir_smush_stats() {

			$result = array();

			//Store the Total/Smushed count
			$stats = $this->total_stats();

			$result['dir_smush'] = $stats;

			//Cumulative Stats
			$result['combined_stats'] = $this->combined_stats( $stats );

			//Store the stats in options table
			update_option('dir_smush_stats', $result, false );

			//Send ajax response
			wp_send_json_success( $result );
		}

	}

	//Class Object
	global $wpsmush_dir;
	$wpsmush_dir = new WpSmushDir();
}

/**
 * Filters the list of directories, Exclude the Media Subfolders
 *
 */
if ( class_exists( 'RecursiveFilterIterator' ) && ! class_exists( 'WPSmushRecursiveFilterIterator' ) ) {
	class WPSmushRecursiveFilterIterator extends \RecursiveFilterIterator {

		public function accept() {
			global $wpsmush_dir;
			$path = $this->current()->getPathname();
			if ( $this->isDir() ) {
				if ( ! $wpsmush_dir->skip_dir( $path ) ) {
					return true;
				}
			} else {
				return true;
			}

			return false;
		}

	}
}