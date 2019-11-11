<?php
/**
 * Media library class.
 *
 * Responsible for displaying a UI (stats + action links) in the media library and the editor.
 *
 * @since 3.4.0
 * @package Smush\App
 */

namespace Smush\App;

use Smush\Core\Core;
use Smush\Core\Helper;
use Smush\Core\Integrations\S3\Compat;
use Smush\Core\Modules\Abstract_Module;
use Smush\Core\Modules\Smush;
use Smush\WP_Smush;
use WP_Post;
use WP_Query;

/**
 * Class Media_Library
 */
class Media_Library extends Abstract_Module {

	/**
	 * Core instance.
	 *
	 * @var Core $core
	 */
	private $core;

	/**
	 * Media_Library constructor.
	 *
	 * @param Core $core  Core instance.
	 */
	public function __construct( Core $core ) {
		parent::__construct();
		$this->core = $core;
	}

	/**
	 * Init functionality that is related to the UI.
	 */
	public function init_ui() {
		// Media library columns.
		add_filter( 'manage_media_columns', array( $this, 'columns' ) );
		add_filter( 'manage_upload_sortable_columns', array( $this, 'sortable_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'custom_column' ), 10, 2 );

		// Manage column sorting.
		add_action( 'pre_get_posts', array( $this, 'smushit_orderby' ) );

		// Smush image filter from Media Library.
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_media_query' ) );
		// Smush image filter from Media Library (list view).
		add_action( 'restrict_manage_posts', array( $this, 'add_filter_dropdown' ) );

		// Add pre WordPress 5.0 compatibility.
		add_filter( 'wp_kses_allowed_html', array( $this, 'filter_html_attributes' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'extend_media_modal' ), 15 );

		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'smush_send_status' ), 99, 3 );
	}

	/**
	 * Print column header for Smush results in the media library using the `manage_media_columns` hook.
	 *
	 * @param array $defaults  Defaults array.
	 *
	 * @return array
	 */
	public function columns( $defaults ) {
		$defaults['smushit'] = 'Smush';

		return $defaults;
	}

	/**
	 * Add the Smushit Column to sortable list
	 *
	 * @param array $columns  Columns array.
	 *
	 * @return array
	 */
	public function sortable_column( $columns ) {
		$columns['smushit'] = 'smushit';

		return $columns;
	}

	/**
	 * Print column data for Smush results in the media library using
	 * the `manage_media_custom_column` hook.
	 *
	 * @param string $column_name  Column name.
	 * @param int    $id           Attachment ID.
	 */
	public function custom_column( $column_name, $id ) {
		if ( 'smushit' === $column_name ) {
			echo wp_kses_post( $this->set_status( $id ) );
		}
	}

	/**
	 * Order by query for smush columns.
	 *
	 * @param WP_Query $query  Query.
	 *
	 * @return WP_Query
	 */
	public function smushit_orderby( $query ) {
		global $current_screen;

		// Filter only media screen.
		if ( ! is_admin() || ( ! empty( $current_screen ) && 'upload' !== $current_screen->base ) ) {
			return $query;
		}

		if ( isset( $_REQUEST['smush-filter'] ) && 'ignored' === $_REQUEST['smush-filter'] ) {
			$query->set(
				'meta_query',
				array(
					array(
						'key'     => WP_SMUSH_PREFIX . 'ignore-bulk',
						'value'   => 'true',
						'compare' => 'EXISTS',
					),
				)
			);

			return $query;
		}

		$orderby = $query->get( 'orderby' );

		if ( isset( $orderby ) && 'smushit' === $orderby ) {
			$query->set(
				'meta_query',
				array(
					'relation' => 'OR',
					array(
						'key'     => Smush::$smushed_meta_key,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => Smush::$smushed_meta_key,
						'compare' => 'NOT EXISTS',
					),
				)
			);
			$query->set( 'orderby', 'meta_value_num' );
		}

		return $query;
	}

	/**
	 * Add our filter to the media query filter in Media Library.
	 *
	 * @since 2.9.0
	 *
	 * @see wp_ajax_query_attachments()
	 *
	 * @param array $query  Query.
	 *
	 * @return mixed
	 */
	public function filter_media_query( $query ) {
		if ( isset( $_POST['query']['stats'] ) && 'null' === $_POST['query']['stats'] ) {
			$query['meta_query'] = array(
				array(
					'key'     => WP_SMUSH_PREFIX . 'ignore-bulk',
					'value'   => 'true',
					'compare' => 'EXISTS',
				),
			);
		}

		return $query;
	}

	/**
	 * Adds a search dropdown in Media Library list view to filter out images that have been
	 * ignored with bulk Smush.
	 *
	 * @since 3.2.0
	 */
	public function add_filter_dropdown() {
		$scr = get_current_screen();

		if ( 'upload' !== $scr->base ) {
			return;
		}

		$ignored = filter_input( INPUT_GET, 'smush-filter', FILTER_SANITIZE_STRING );

		?>
		<label for="smush_filter" class="screen-reader-text">
			<?php esc_html_e( 'Filter by Smush status', 'wp-smushit' ); ?>
		</label>
		<select class="smush-filters" name="smush-filter" id="smush_filter">
			<option value="" <?php selected( $ignored, '' ); ?>><?php esc_html_e( 'Smush: All images', 'wp-smushit' ); ?></option>
			<option value="ignored" <?php selected( $ignored, 'ignored' ); ?>><?php esc_html_e( 'Smush: Bulk ignored', 'wp-smushit' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Data attributes are not allowed on <a> elements on WordPress before 5.0.0.
	 * Add backward compatibility.
	 *
	 * @since 3.5.0
	 * @see https://github.com/WordPress/WordPress/commit/a0309e80b6a4d805e4f230649be07b4bfb1a56a5#diff-a0e0d196dd71dde453474b0f791828fe
	 * @param array $context  Context.
	 *
	 * @return mixed
	 */
	public function filter_html_attributes( $context ) {
		global $wp_version;

		if ( version_compare( '5.0.0', $wp_version, '<' ) ) {
			return $context;
		}

		$context['a']['data-tooltip'] = true;
		$context['a']['data-id']      = true;
		$context['a']['data-nonce']   = true;

		return $context;
	}

	/**
	 * Load media assets.
	 *
	 * Localization also used in Gutenberg integration.
	 */
	public function extend_media_modal() {
		if ( wp_script_is( 'smush-backbone-extension', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_script(
			'smush-backbone-extension',
			WP_SMUSH_URL . 'app/assets/js/smush-media.min.js',
			array(
				'jquery',
				'media-editor', // Used in image filters.
				'media-views',
				'media-grid',
				'wp-util',
				'wp-api',
			),
			WP_SMUSH_VERSION,
			true
		);

		wp_localize_script(
			'smush-backbone-extension',
			'smush_vars',
			array(
				'strings' => array(
					'stats_label' => esc_html__( 'Smush', 'wp-smushit' ),
					'filter_all'  => esc_html__( 'Smush: All images', 'wp-smushit' ),
					'filter_excl' => esc_html__( 'Smush: Bulk ignored', 'wp-smushit' ),
					'gb'          => array(
						'stats'        => esc_html__( 'Smush Stats', 'wp-smushit' ),
						'select_image' => esc_html__( 'Select an image to view Smush stats.', 'wp-smushit' ),
						'size'         => esc_html__( 'Image size', 'wp-smushit' ),
						'savings'      => esc_html__( 'Savings', 'wp-smushit' ),
					),
				),
				'nonce'   => array(
					'get_smush_status' => wp_create_nonce( 'get-smush-status' ),
				),
			)
		);
	}

	/**
	 * Send smush status for attachment.
	 *
	 * @param array   $response    Response array.
	 * @param WP_Post $attachment  Attachment object.
	 *
	 * @return mixed
	 */
	public function smush_send_status( $response, $attachment ) {
		if ( ! isset( $attachment->ID ) ) {
			return $response;
		}

		// Validate nonce.
		$status            = $this->smush_status( $attachment->ID );
		$response['smush'] = $status;

		return $response;
	}

	/**
	 * Get the smush button text for attachment.
	 *
	 * @param int $id  Attachment ID for which the Status has to be set.
	 *
	 * @return string
	 */
	private function smush_status( $id ) {
		// Show Temporary Status, For Async Optimisation, No Good workaround.
		if ( ! get_option( "wp-smush-restore-{$id}", false ) && ! empty( $_POST['action'] ) && 'upload-attachment' === $_POST['action'] && $this->settings->get( 'auto' ) ) {
			$status_txt = '<p class="smush-status">' . __( 'Smushing in progress..', 'wp-smushit' ) . '</p>';

			// We need to show the smush button.
			$show_button = false;
			$button_txt  = __( 'Smush Now!', 'wp-smushit' );

			return $this->column_html( $id, $status_txt, $button_txt, $show_button );
		}
		// Else Return the normal status.
		return trim( $this->set_status( $id ) );
	}

	/**
	 * Set send button status
	 *
	 * @param int  $id        Attachment ID.
	 * @param bool $text_only Returns the stats text instead of button.
	 *
	 * @return string|array
	 */
	public function set_status( $id, $text_only = false ) {
		$status_txt = '';
		$button_txt = '';
		$stats      = '';
		$links      = '';

		$show_button = false;

		$wp_smush_data      = get_post_meta( $id, Smush::$smushed_meta_key, true );
		$wp_resize_savings  = get_post_meta( $id, WP_SMUSH_PREFIX . 'resize_savings', true );
		$conversion_savings = get_post_meta( $id, WP_SMUSH_PREFIX . 'pngjpg_savings', true );

		$combined_stats = $this->core->combined_stats( $wp_smush_data, $wp_resize_savings );
		$combined_stats = $this->core->combine_conversion_stats( $combined_stats, $conversion_savings );

		// Remove Smush s3 hook, as it downloads the file again.
		if ( class_exists( '\Compat' ) && class_exists( '\AS3CF_Plugin_Compatibility' ) ) {
			$s3_compat = new Compat();
			remove_filter( 'as3cf_get_attached_file', array( $s3_compat, 'smush_download_file' ), 11, 4 );
		}
		$attachment_data = wp_get_attachment_metadata( $id );

		// If the image is smushed.
		if ( ! empty( $wp_smush_data ) ) {
			$image_count    = count( $wp_smush_data['sizes'] );
			$bytes          = isset( $combined_stats['stats']['bytes'] ) ? $combined_stats['stats']['bytes'] : 0;
			$bytes_readable = ! empty( $bytes ) ? size_format( $bytes, 1 ) : '';
			$percent        = isset( $combined_stats['stats']['percent'] ) ? $combined_stats['stats']['percent'] : 0;
			$percent        = $percent < 0 ? 0 : $percent;

			// Show resmush link, if the settings were changed.
			$show_resmush = $this->show_resmush( $id, $wp_smush_data, $attachment_data );

			if ( empty( $wp_resize_savings['bytes'] ) && isset( $wp_smush_data['stats']['size_before'] ) && 0 === $wp_smush_data['stats']['size_before'] && ! empty( $wp_smush_data['sizes'] ) ) {
				$status_txt = __( 'Already Optimized', 'wp-smushit' );
				if ( $show_resmush ) {
					$links .= self::get_resmsuh_link( $id );
				}
				$show_button = false;
			} else {
				if ( 0 === $bytes || 0 === $percent ) {
					$status_txt = __( 'Already Optimized', 'wp-smushit' );

					if ( $show_resmush ) {
						$links .= self::get_resmsuh_link( $id );
					}
				} elseif ( ! empty( $percent ) && ! empty( $bytes_readable ) ) {
					/* translators: %d: number of images reduced */
					$status_txt = $image_count > 1 ? sprintf( __( '%d images reduced ', 'wp-smushit' ), $image_count ) : __( 'Reduced ', 'wp-smushit' );

					$stats_percent = number_format_i18n( $percent, 2 );
					$stats_percent = $stats_percent > 0 ? sprintf( '(%01.1f%%)', $stats_percent ) : '';
					/* translators: %1$s: bytes in readable format, %2$s: percent */
					$status_txt .= sprintf( __( 'by %1$s %2$s', 'wp-smushit' ), $bytes_readable, $stats_percent );

					$file_path = get_attached_file( $id );
					$size      = file_exists( $file_path ) ? filesize( $file_path ) : 0;
					if ( $size > 0 ) {
						$update_size = size_format( $size, 0 ); // Used in js to update image stat.
						$size        = size_format( $size, 1 );
						/* translators: %s: image size */
						$image_size  = sprintf( __( '<br /> Image Size: %s', 'wp-smushit' ), $size );
						$status_txt .= $image_size;
					}

					$show_resmush = $this->show_resmush( $id, $wp_smush_data, $attachment_data );

					if ( $show_resmush ) {
						$links .= self::get_resmsuh_link( $id );
					}

					// Restore Image: Check if we need to show the restore image option.
					$show_restore = $this->show_restore_option( $id, $attachment_data );

					if ( $show_restore ) {
						if ( ! empty( $links ) ) {
							$links .= '| ';
						}
						$links .= self::get_restore_link( $id );
					}

					// Detailed Stats: Show detailed stats if available.
					if ( ! empty( $wp_smush_data['sizes'] ) ) {
						if ( ! empty( $links ) ) {
							$links .= '| ';
						}

						// Detailed Stats Link.
						$links .= sprintf(
							'<a href="#" class="wp-smush-action smush-stats-details wp-smush-title sui-tooltip sui-tooltip-mobile sui-tooltip-top-left-mobile" data-tooltip="%s">%s</a>',
							esc_html__( 'Detailed stats for all the image sizes', 'wp-smushit' ),
							esc_html__( 'View Stats', 'wp-smushit' )
						);

						// Stats.
						$stats = $this->get_detailed_stats( $id, $wp_smush_data, $attachment_data );

						$links .= $stats;
					}
				}
			}
			// Wrap links if not empty.
			$links = ! empty( $links ) ? "<div class='sui-smush-media smush-status-links'>" . $links . '</div>' : '';

			/** Super Smush Button  */
			// IF current compression is lossy.
			$is_lossy = false;
			if ( ! empty( $wp_smush_data ) && ! empty( $wp_smush_data['stats'] ) ) {
				$is_lossy = ! empty( $wp_smush_data['stats']['lossy'] ) ? $wp_smush_data['stats']['lossy'] : false;
			}

			// Check image type.
			$image_type = get_post_mime_type( $id );

			// Check if premium user, compression was lossless, and lossy compression is enabled.
			// If we are displaying the resmush option already, no need to show the Super Smush button.
			if ( ! $show_resmush && ! $is_lossy && WP_Smush::is_pro() && $this->settings->get( 'lossy' ) && 'image/gif' !== $image_type ) {
				$button_txt  = __( 'Super-Smush', 'wp-smushit' );
				$show_button = true;
			}
		} elseif ( get_option( 'smush-in-progress-' . $id, false ) ) {
			// The status.
			$status_txt = __( 'Smushing in progress..', 'wp-smushit' );

			// Set WP Smush data to true in order to show the text.
			$wp_smush_data = true;

			// We need to show the smush button.
			$show_button = false;

			// The button text.
			$button_txt = '';
		} else {

			// Show status text.
			$wp_smush_data = true;

			// The status.
			$ignored    = get_post_meta( $id, WP_SMUSH_PREFIX . 'ignore-bulk', true );
			$status_txt = 'true' === $ignored ? __( 'Ignored in Bulk Smush', 'wp-smushit' ) : __( 'Not processed', 'wp-smushit' );

			// We need to show the smush button.
			$show_button = true;

			// The button text.
			$button_txt = __( 'Smush', 'wp-smushit' );
		}

		$class      = $wp_smush_data ? '' : ' hidden';
		$status_txt = '<p class="smush-status' . $class . '">' . $status_txt . '</p>';

		$status_txt .= $links;

		if ( $text_only ) {
			// For ajax response.
			return array(
				'status'       => $status_txt,
				'stats'        => $stats,
				'show_warning' => intval( $this->core->mod->smush->show_warning() ),
				'new_size'     => isset( $update_size ) ? $update_size : 0,
			);
		}

		// If we are not showing smush button, append progress bar, else it is already there.
		if ( ! $show_button ) {
			$status_txt .= self::progress_bar();
		}

		return $this->column_html( $id, $status_txt, $button_txt, $show_button );
	}

	/**
	 * Skip messages respective to their IDs.
	 *
	 * @param string $msg_id  Message ID.
	 *
	 * @return bool
	 */
	public function skip_reason( $msg_id ) {
		$count           = count( get_intermediate_image_sizes() );
		$smush_orgnl_txt = sprintf(
			/* translators: %s: number of thumbnails */
			esc_html__(
				'When you upload an image to WordPress it automatically creates %s thumbnail sizes
			that are commonly used in your pages. WordPress also stores the original full-size image, but because
			these are not usually embedded on your site we don’t Smush them. Pro users can
			override this.',
				'wp-smushit'
			),
			$count
		);

		$skip_msg = array(
			'large_size' => $smush_orgnl_txt,
			'size_limit' => esc_html__(
				"Image couldn't be smushed as it exceeded the 5Mb size limit,
			Pro users can smush images with size up to 32Mb.",
				'wp-smushit'
			),
		);

		$skip_rsn = ! empty( $skip_msg[ $msg_id ] ) ? esc_html__( ' Skipped', 'wp-smushit' ) : '';
		$skip_rsn = ! empty( $skip_rsn ) ? $skip_rsn . '<span class="sui-tooltip sui-tooltip-left sui-tooltip-constrained sui-tooltip-top-right-mobile" data-tooltip="' . $skip_msg[ $msg_id ] . '"><i class="dashicons dashicons-editor-help"></i></span>' : '';

		return $skip_rsn;
	}

	/**
	 * Checks the current settings and returns the value whether to enable or not the resmush option.
	 *
	 * @param string $id               Attachment ID.
	 * @param array  $wp_smush_data    Smush data.
	 * @param array  $attachment_data  Attachment data.
	 *
	 * @return bool
	 */
	private function show_resmush( $id = '', $wp_smush_data = array(), $attachment_data = array() ) {
		// Resmush: Show resmush link, Check if user have enabled smushing the original and full image was skipped
		// Or: If keep exif is unchecked and the smushed image have exif
		// PNG To JPEG.
		if ( $this->settings->get( 'original' ) && WP_Smush::is_pro() ) {
			// IF full image was not smushed.
			if ( ! empty( $wp_smush_data ) && empty( $wp_smush_data['sizes']['full'] ) ) {
				return true;
			}
		}

		// If image needs to be resized.
		if ( $this->core->mod->resize->should_resize( $id ) ) {
			return true;
		}

		// EXIF Check.
		if ( $this->settings->get( 'strip_exif' ) ) {
			// If Keep Exif was set to true initially, and since it is set to false now.
			if ( isset( $wp_smush_data['stats']['keep_exif'] ) && true === $wp_smush_data['stats']['keep_exif'] ) {
				return true;
			}
		}

		// PNG to JPEG.
		if ( $this->core->mod->png2jpg->can_be_converted( $id ) ) {
			return true;
		}

		// This is duplicating a part of scan_images() in class-ajax.php. See detailed description there.
		$image_sizes = $this->settings->get_setting( WP_SMUSH_PREFIX . 'image_sizes' );

		// Empty means we need to smush all images. So get all sizes of current site.
		if ( empty( $image_sizes ) ) {
			$image_sizes = array_keys( $this->core->image_dimensions() );
		}

		$smushed_image_sizes = isset( $wp_smush_data['sizes'] ) && is_array( $wp_smush_data['sizes'] ) ? count( $wp_smush_data['sizes'] ) : 0;
		if ( is_array( $image_sizes ) && count( $image_sizes ) > $smushed_image_sizes && isset( $attachment_data['sizes'] ) && count( $attachment_data['sizes'] ) !== $smushed_image_sizes ) {
			foreach ( $image_sizes as $image_size ) {
				// Already compressed.
				if ( isset( $wp_smush_data['sizes'][ $image_size ] ) ) {
					continue;
				}

				// If image has the size that can be compressed.
				if ( isset( $attachment_data['sizes'][ $image_size ] ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * If any of the image size have a backup file, show the restore option
	 *
	 * @param int          $image_id         Attachment ID.
	 * @param string|array $attachment_data  Attachment data.
	 *
	 * @return bool
	 */
	private function show_restore_option( $image_id, $attachment_data ) {
		// No Attachment data, don't go ahead.
		if ( empty( $attachment_data ) ) {
			return false;
		}

		// Get the image path for all sizes.
		$file = get_attached_file( $image_id );

		// Get stored backup path, if any.
		$backup_sizes = get_post_meta( $image_id, '_wp_attachment_backup_sizes', true );

		// Check if we've a backup path.
		if ( ! empty( $backup_sizes ) && ( ! empty( $backup_sizes['smush-full'] ) || ! empty( $backup_sizes['smush_png_path'] ) ) ) {
			// Check for PNG backup.
			$backup = ! empty( $backup_sizes['smush_png_path'] ) ? $backup_sizes['smush_png_path'] : '';

			// Check for original full size image backup.
			$backup = empty( $backup ) && ! empty( $backup_sizes['smush-full'] ) ? $backup_sizes['smush-full'] : $backup;
			$backup = ! empty( $backup['file'] ) ? $backup['file'] : '';
		}

		// If we still don't have a backup path, use traditional method to get it.
		if ( empty( $backup ) ) {
			// Check backup for Full size.
			$backup = $this->core->mod->backup->get_image_backup_path( $file );
		} else {
			// Get the full path for file backup.
			$backup = str_replace( wp_basename( $file ), wp_basename( $backup ), $file );
		}

		$file_exists = apply_filters( 'smush_backup_exists', file_exists( $backup ), $image_id, $backup );

		if ( $file_exists ) {
			return true;
		}

		// Additional Backup Check for JPEGs converted from PNG.
		$pngjpg_savings = get_post_meta( $image_id, WP_SMUSH_PREFIX . 'pngjpg_savings', true );
		if ( ! empty( $pngjpg_savings ) ) {

			// Get the original File path and check if it exists.
			$backup = get_post_meta( $image_id, WP_SMUSH_PREFIX . 'original_file', true );
			$backup = Helper::original_file( $backup );

			if ( ! empty( $backup ) && is_file( $backup ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Print the column html.
	 *
	 * @param string  $id           Media id.
	 * @param string  $html         Status text.
	 * @param string  $button_txt   Button label.
	 * @param boolean $show_button  Whether to shoe the button.
	 *
	 * @return string
	 */
	private function column_html( $id, $html = '', $button_txt = '', $show_button = true ) {
		// Don't proceed if attachment is not image, or if image is not a jpg, png or gif.
		if ( ! wp_attachment_is_image( $id ) || ! in_array( get_post_mime_type( $id ), Core::$mime_types, true ) ) {
			$status_txt = __( 'Not processed', 'wp-smushit' );
			return $status_txt;
		}

		// If we aren't showing the button.
		if ( ! $show_button ) {
			return $html;
		}

		if ( 'Super-Smush' === $button_txt ) {
			$html .= ' | ';
		}

		$html .= "<a href='#' class='wp-smush-send' data-id='{$id}'>{$button_txt}</a>";

		$skipped = get_post_meta( $id, WP_SMUSH_PREFIX . 'ignore-bulk', true );
		if ( 'true' === $skipped ) {
			$nonce = wp_create_nonce( 'wp-smush-remove-skipped' );
			$html .= " | <a href='#' class='wp-smush-remove-skipped' data-id={$id} data-nonce={$nonce}>" . __( 'Show in bulk Smush', 'wp-smushit' ) . '</a>';
		} else {
			$html .= " | <a href='#' class='smush-ignore-image' data-id='{$id}'>" . esc_html__( 'Ignore', 'wp-smushit' ) . '</a>';
		}

		$html .= self::progress_bar();

		return $html;
	}

	/**
	 * Shows the image size and the compression for each of them
	 *
	 * @param int   $image_id             Attachment ID.
	 * @param array $wp_smush_data        Smush data array.
	 * @param array $attachment_metadata  Attachment metadata.
	 *
	 * @return string
	 */
	private function get_detailed_stats( $image_id, $wp_smush_data, $attachment_metadata ) {
		$stats      = '<div id="smush-stats-' . $image_id . '" class="sui-smush-media smush-stats-wrapper hidden">
			<table class="wp-smush-stats-holder">
				<thead>
					<tr>
						<th class="smush-stats-header">' . esc_html__( 'Image size', 'wp-smushit' ) . '</th>
						<th class="smush-stats-header">' . esc_html__( 'Savings', 'wp-smushit' ) . '</th>
					</tr>
				</thead>
				<tbody>';
		$size_stats = $wp_smush_data['sizes'];

		// Reorder Sizes as per the maximum savings.
		uasort(
			$size_stats,
			function( $a, $b ) {
				return $a->bytes < $b->bytes;
			}
		);

		if ( ! empty( $attachment_metadata['sizes'] ) ) {
			// Get skipped images.
			$skipped = $this->get_skipped_images( $image_id, $size_stats, $attachment_metadata );

			if ( ! empty( $skipped ) ) {
				foreach ( $skipped as $img_data ) {
					$skip_class = 'size_limit' === $img_data['reason'] ? ' error' : '';
					$stats     .= '<tr>
				<td>' . strtoupper( $img_data['size'] ) . '</td>
				<td class="smush-skipped' . $skip_class . '">' . $this->skip_reason( $img_data['reason'] ) . '</td>
			</tr>';
				}
			}
		}
		// Show Sizes and their compression.
		foreach ( $size_stats as $size_key => $size_value ) {
			$dimensions = '';
			// Get the dimensions for the image size if available.
			if ( ! empty( $this->image_sizes ) && ! empty( $this->image_sizes[ $size_key ] ) ) {
				$dimensions = $this->image_sizes[ $size_key ]['width'] . 'x' . $this->image_sizes[ $size_key ]['height'];
			}
			$dimensions = ! empty( $dimensions ) ? sprintf( ' <br /> (%s)', $dimensions ) : '';
			if ( $size_value->bytes > 0 ) {
				$percent = round( $size_value->percent, 1 );
				$percent = $percent > 0 ? ' ( ' . $percent . '% )' : '';
				$stats  .= '<tr>
				<td>' . strtoupper( $size_key ) . $dimensions . '</td>
				<td>' . size_format( $size_value->bytes, 1 ) . $percent . '</td>
			</tr>';
			}
		}
		$stats .= '</tbody>
			</table>
		</div>';

		return $stats;
	}

	/**
	 * Return a list of images not smushed and reason
	 *
	 * @param int   $image_id             Attachment ID.
	 * @param array $size_stats           Stats array.
	 * @param array $attachment_metadata  Attachment metadata.
	 *
	 * @return array
	 */
	private function get_skipped_images( $image_id, $size_stats, $attachment_metadata ) {
		$skipped = array();

		// Get a list of all the sizes, Show skipped images.
		$media_size = get_intermediate_image_sizes();

		// Full size.
		$full_image = get_attached_file( $image_id );

		// If full image was not smushed, reason 1. Large Size logic, 2. Free and greater than 5Mb.
		if ( ! array_key_exists( 'full', $size_stats ) && ! WP_Smush::is_pro() ) {
			// For free version, Check the image size.
			$skipped[] = array(
				'size'   => 'full',
				'reason' => 'large_size',
			);

			// For free version, check if full size is greater than 5 Mb, show the skipped status.
			$file_size = file_exists( $full_image ) ? filesize( $full_image ) : '';
			if ( empty( $skipped ) && ! empty( $file_size ) && ( $file_size / WP_SMUSH_MAX_BYTES ) > 1 ) {
				$skipped[] = array(
					'size'   => 'full',
					'reason' => 'size_limit',
				);
			}
		}
		// For other sizes, check if the image was generated and not available in stats.
		if ( is_array( $media_size ) ) {
			foreach ( $media_size as $size ) {
				if ( array_key_exists( $size, $attachment_metadata['sizes'] ) && ! array_key_exists( $size, $size_stats ) && ! empty( $size['file'] ) ) {
					// Image Path.
					$img_path   = path_join( dirname( $full_image ), $size['file'] );
					$image_size = file_exists( $img_path ) ? filesize( $img_path ) : '';
					if ( ! empty( $image_size ) && ( $image_size / WP_SMUSH_MAX_BYTES ) > 1 ) {
						$skipped[] = array(
							'size'   => 'full',
							'reason' => 'size_limit',
						);
					}
				}
			}
		}

		return $skipped;
	}

	/**
	 * Returns the HTML for progress bar
	 *
	 * @return string
	 */
	public static function progress_bar() {
		return '<span class="spinner wp-smush-progress"></span>';
	}

	/**
	 * Generates a Resmush link for a image.
	 *
	 * @param int    $image_id  Attachment ID.
	 * @param string $type      Type of attachment.
	 *
	 * @return bool|string
	 */
	public static function get_resmsuh_link( $image_id, $type = 'wp' ) {
		if ( empty( $image_id ) ) {
			return false;
		}

		$class  = 'wp-smush-action wp-smush-title sui-tooltip sui-tooltip-constrained';
		$class .= 'wp' === $type ? ' wp-smush-resmush' : ' wp-smush-nextgen-resmush';

		return sprintf(
			'<a href="#" data-tooltip="%s" data-id="%d" data-nonce="%s" class="%s">%s</a>',
			esc_html__( 'Smush image including original file.', 'wp-smushit' ),
			$image_id,
			wp_create_nonce( 'wp-smush-resmush-' . $image_id ),
			$class,
			esc_html__( 'Resmush', 'wp-smushit' )
		);
	}

	/**
	 * Returns a restore link for given image id
	 *
	 * @param int    $image_id  Attachment ID.
	 * @param string $type      Attachment type.
	 *
	 * @return bool|string
	 */
	public static function get_restore_link( $image_id, $type = 'wp' ) {
		if ( empty( $image_id ) ) {
			return false;
		}

		$class  = 'wp-smush-action wp-smush-title sui-tooltip';
		$class .= 'wp' === $type ? ' wp-smush-restore' : ' wp-smush-nextgen-restore';

		return sprintf(
			'<a href="#" data-tooltip="%s" data-id="%d" data-nonce="%s" class="%s">%s</a>',
			esc_html__( 'Restore original image.', 'wp-smushit' ),
			$image_id,
			wp_create_nonce( 'wp-smush-restore-' . $image_id ),
			$class,
			esc_html__( 'Restore', 'wp-smushit' )
		);
	}

}
