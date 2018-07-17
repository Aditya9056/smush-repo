<?php
/**
 * Directory Smush UI: WP_Smush_Dir_UI class
 *
 * @package WP_Smush
 * @subpackage Admin/UI
 * @since 2.8.1
 */

if ( ! class_exists( 'WP_Smush_Dir_UI' ) ) {
	/**
	 * Class WP_Smush_Dir_UI
	 */
	class WP_Smush_Dir_UI {

		/**
		 * WP_Smush_Dir_UI constructor.
		 */
		public function __construct() {
			// Hook UI at the end of Settings UI.
			add_action( 'smush_directory_settings_ui', array( $this, 'ui' ), 11 );
			// Output Stats after Resize savings.
			add_action( 'stats_ui_after_resize_savings', array( $this, 'stats_ui' ), 10 );
		}

		/**
		 * Check if there is any unsmushed image from last scan
		 *
		 * TODO: don't think we need this according to new design
		 *
		 * @return bool True/False
		 */
		private function get_unsmushed_image() {
			global $wpdb, $wp_smush;

			// If super-smush enabled, add lossy check.
			$lossy_condition = $wp_smush->lossy_enabled ? '(image_size IS NULL OR lossy <> 1)' : 'image_size IS NULL';

			$results = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}smush_dir_images WHERE {$lossy_condition} && last_scan = (SELECT MAX(last_scan) FROM {$wpdb->prefix}smush_dir_images t2 )  GROUP BY id ORDER BY id LIMIT 1" );

			// If the query went through.
			if ( empty( $results ) ) {
				return false;
			}

			if ( is_wp_error( $results ) ) {
				error_log( sprintf( "WP Smush Query Error in %s at %s: %s", __FILE__, __LINE__, $results->get_error_message() ) );
				return false;
			}

			return true;
		}

		/**
		 * Bulk Smush UI and progress bar.
		 */
		public function ui() {
			global $wp_smush, $wpsmushit_admin, $wpsmush_bulkui, $wpsmush_dir;

			// Print Directory Smush UI, if not a network site.
			if ( is_network_admin() ) {
				return;
			}

			// Reset the bulk limit.
			if ( ! $wp_smush->validate_install() ) {
				/**
				 * Reset transient.
				 *
				 * @var WpSmushitAdmin $wpsmushit_admin
				 */
				$wpsmushit_admin->check_bulk_limit( true, 'dir_sent_count' );
			}

			wp_nonce_field( 'smush_get_dir_list', 'list_nonce' );
			wp_nonce_field( 'smush_get_image_list', 'image_list_nonce' );

			$upgrade_url = add_query_arg(
				array(
					'utm_source'   => 'smush',
					'utm_medium'   => 'plugin',
					'utm_campaign' => 'smush_directorysmush_limit_notice',
				),
				$wpsmushit_admin->upgrade_url
			);

			$dir_button = '<button class="sui-button wp-smush-browse sui-hidden" data-a11y-dialog-show="wp-smush-list-dialog">' . esc_html__( 'ADD FOLDER', 'wp-smushit' ) . '</button>';

			echo '<div class="sui-box" id="wp-smush-dir-wrap-box">';

			/**
			 * Container header.
			 *
			 * @var WpSmushBulkUi $wpsmush_bulkui
			 */
			$wpsmush_bulkui->container_header( esc_html__( 'Directory Smush', 'wp-smushit' ), $dir_button ); ?>

			<div class="sui-box-body">
				<!-- Directory Path -->
				<input type="hidden" class="wp-smush-dir-path" value=""/>
				<div class="wp-smush-scan-result">
					<div class="content">
						<!-- Show a list of images, inside a fixed height div, with a scroll. As soon as the image is
						optimised show a tick mark, with savings below the image. Scroll the li each time for the
						current optimised image -->
						<span class="wp-smush-no-image tc">
							<img src="<?php echo esc_url( WP_SMUSH_URL . 'assets/images/smush-no-media.png' ); ?>" alt="<?php esc_html_e( 'Directory Smush - Choose Folder', 'wp-smushit' ); ?>">
						</span>
						<p class="wp-smush-no-images-content tc roboto-regular">
							<?php esc_html_e( 'In addition to smushing your media uploads, you may want to also smush images living outside your uploads directory.', 'wp-smushit' ); ?><br>
							<?php esc_html_e( 'Get started by adding files and folders you wish to optimize.', 'wp-smushit' ); ?>
						</p>
						<span class="wp-smush-upload-images sui-no-padding-bottom tc">
							<button type="button" class="sui-button sui-button-primary wp-smush-browse tc" data-a11y-dialog-show="wp-smush-list-dialog"><?php esc_html_e( 'CHOOSE FOLDER', 'wp-smushit' ); ?></button>
							<?php $this->show_resume_button(); ?>
						</span>
					</div>
					<table class="smush-dir-smush-done sui-table sui-hidden">
						<thead>
						<tr>
							<th><?php esc_html_e( 'Folder', 'wp-smushit' ); ?></th>
						</tr>
						</thead>
						<tbody>
						<tr>
							<td class="smush-notice-content">
								<div class="sui-notice sui-notice-info smush-no-images sui-hidden">
									<p><?php esc_html_e( 'You haven’t added any folders to smush.', 'wp-smushit' ); ?></p>
								</div>
							</td>
						</tr>
						<tr>
							<td>
								<button type="button" class="sui-button wp-smush-browse wp-smush-browse-top" data-a11y-dialog-show="wp-smush-list-dialog">
									<?php esc_html_e( 'ADD FOLDER', 'wp-smushit' ); ?>
								</button>
							</td>
						</tr>
						</tbody>
					</table>
					<!-- Notices -->
					<div class="sui-notice sui-notice-success wp-smush-dir-all-done sui-hidden">
						<p><?php esc_html_e( 'All images for the selected directory are smushed and up to date. Awesome!', 'wp-smushit' ); ?></p>
					</div>
					<div class="sui-notice sui-notice-warning wp-smush-dir-remaining sui-hidden">
						<p>
						<?php printf(
							/* translators: %1$s: smushed span, %2$s: dir total span, %3$s: dir remaining span */
							esc_html__( '%1$s/%2$s image(s) were successfully smushed, however %3$s image(s) could not be smushed due to an error.', 'wp-smushit' ),
							'<span class="wp-smush-dir-smushed"></span>',
							'<span class="wp-smush-dir-total"></span>',
							'<span class="wp-smush-dir-remaining"></span>'
						); ?>
						</p>
					</div>
					<div class="sui-notice sui-notice-info wp-smush-dir-limit sui-hidden">
						<p>
							<?php printf(
								/* translators: %1$s: a tag start, %2$s: closing a tag */
								esc_html__( '%1$sUpgrade to pro%2$s to bulk smush all your directory images with one click. Free users can smush 50 images with each click.', 'wp-smushit' ),
								'<a href="' . esc_url( $upgrade_url ) . '" target="_blank" title="' . esc_html__( 'Smush Pro', 'wp-smushit' ) . '">',
								'</a>'
							); ?>
						</p>
					</div>
					<?php wp_nonce_field( 'wp_smush_all', 'wp-smush-all' ); ?>
					<input type="hidden" name="wp-smush-continue-ajax" value=1>
				</div>
				<input type="hidden" name="wp-smush-base-path" value="<?php echo $wpsmush_dir->get_root_path(); ?>">
			</div>
			<?php $this->directory_list_dialog(); ?>
			<?php $this->progress_dialog(); ?>
			</div>
			<?php
		}

		/**
		 * Prints a resume button if required.
		 */
		public function show_resume_button() {
			if ( ! $this->get_unsmushed_image() ) {
				return null;
			}
			// Print the button. ?>
			<button type="button" class="sui-button wp-smush-resume tc">
				<?php esc_html_e( 'RESUME LAST SCAN', 'wp-smushit' ); ?>
			</button>
			<span class="wp-smush-resume-loder sui-icon-loader sui-loading sui-hidden" aria-hidden="true"></span>
			<?php
		}

		/**
		 * Output the content for Directory smush list dialog content
		 */
		public function directory_list_dialog() {
			$current_screen = get_current_screen();
			if ( empty( $current_screen ) || empty( $current_screen->base ) || ( 'toplevel_page_smush' != $current_screen->base && 'toplevel_page_smush-network' != $current_screen->base ) ) {
				return;
			}
			?>

			<div class="sui-dialog wp-smush-list-dialog" aria-hidden="true" id="wp-smush-list-dialog">
				<div class="sui-dialog-overlay sui-fade-in" tabindex="0"></div>
				<div class="sui-dialog-content sui-bounce-in" role="dialog">
					<div class="sui-box" role="document">
						<div class="sui-box-header">
							<h3 class="sui-box-title"><?php esc_html_e( 'Choose Directory', 'wp-smushit' ); ?></h3>
							<div class="sui-actions-right">
								<button class="sui-dialog-close" aria-label="<?php esc_html_e( 'Close', 'wp-smushit' ); ?>"></button>
							</div>
						</div>

						<div class="sui-box-body">
							<p><?php esc_html_e( 'Choose which folder you wish to smush. Smush will automatically include any images in subfolders of your selected folder.', 'wp-smushit' ); ?></p>
							<div class="content"></div>
						</div>

						<div class="sui-box-footer">
							<div class="sui-actions-right">
								<span class="add-dir-loader"></span>
								<button class="sui-modal-close sui-button wp-smush-select-dir">
									<?php esc_html_e( 'SMUSH', 'wp-smushit' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Output the progress dialog for the Directory smush list dialog
		 */
		public function progress_dialog() {
			$current_screen = get_current_screen();
			if ( empty( $current_screen ) || empty( $current_screen->base ) || ( 'toplevel_page_smush' != $current_screen->base && 'toplevel_page_smush-network' != $current_screen->base ) ) {
				return;
			}
			?>

			<div class="sui-dialog wp-smush-progress-dialog" aria-hidden="true" id="wp-smush-progress-dialog">
				<div class="sui-dialog-overlay sui-fade-in" tabindex="0"></div>
				<div class="sui-dialog-content sui-bounce-in" role="dialog">
					<div class="sui-box" role="document">

						<div class="sui-box-header">
							<h3 class="sui-box-title"><?php esc_html_e( 'Choose Directory', 'wp-smushit' ); ?></h3>
							<div class="sui-actions-right">
								<button class="sui-dialog-close" aria-label="<?php esc_html_e( 'Close', 'wp-smushit' ); ?>"></button>
							</div>
						</div>

						<div class="sui-box-body">
							<p><?php esc_html_e( 'Bulk smushing is in progress, you need to leave this tab open until the process completes.', 'wp-smushit' ); ?></p>

							<div class="sui-progress-block sui-progress-can-close">
								<div class="sui-progress">
									<div class="sui-progress-text sui-icon-loader sui-loading">
										<span>0%</span>
									</div>
									<div class="sui-progress-bar">
										<span style="width: 0"></span>
									</div>
								</div>
								<button class="sui-progress-close sui-tooltip" id="cancel-directory-smush" type="button" data-a11y-dialog-hide data-tooltip="<?php esc_attr_e( 'Cancel', 'wp-smushit' ); ?>">
									<i class="sui-icon-close"></i>
								</button>
							</div>

							<div class="sui-progress-state">
								<span class="sui-progress-state-text">
									<?php esc_html_e( '200/400 images optimized', 'wphb' ); ?>
								</span>
							</div>
						</div>

						<div class="sui-box-footer">
							<div class="sui-actions-right">
								<span class="add-dir-loader"></span>
								<button class="sui-modal-close sui-button wp-smush-cancel-dir"><?php esc_html_e( 'CANCEL', 'wp-smushit' ); ?></button>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Set directory smush stats to stats box.
		 *
		 * @return void
		 */
		public function stats_ui() {
			$dir_smush_stats = get_option( 'dir_smush_stats' );
			$human = 0;
			if ( ! empty( $dir_smush_stats ) && ! empty( $dir_smush_stats['dir_smush'] ) ) {
				$human = ! empty( $dir_smush_stats['dir_smush']['bytes'] ) && $dir_smush_stats['dir_smush']['bytes'] > 0 ? $dir_smush_stats['dir_smush']['bytes'] : 0;
			}
			?>
			<!-- Savings from Directory Smush -->
			<li class="smush-dir-savings">
				<span class="sui-list-label"><?php esc_html_e( 'Directory Smush Savings', 'wp-smushit' ); ?>
					<?php if ( $human <= 0 ) { ?>
						<p class="wp-smush-stats-label-message">
							<?php esc_html_e( "Smush images that aren't located in your uploads folder.", 'wp-smushit' ); ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=smush&tab=directory' ) ); ?>" class="wp-smush-dir-link"
							   title="<?php esc_attr_e( "Select a directory you'd like to Smush.", 'wp-smushit' ); ?>">
								<?php esc_html_e( 'Choose directory', 'wp-smushit' ); ?>
							</a>
						</p>
					<?php } ?>
				</span>
				<span class="wp-smush-stats sui-list-detail">
					<i class="sui-icon-loader sui-loading" aria-hidden="true" title="<?php esc_html_e( 'Updating Stats', 'wp-smushit' ); ?>"></i>
					<span class="wp-smush-stats-human"></span>
					<span class="wp-smush-stats-sep sui-hidden">/</span>
					<span class="wp-smush-stats-percent"></span>
				</span>
			</li>
			<?php
		}

	}
} // End if().
