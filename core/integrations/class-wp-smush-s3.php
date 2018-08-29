<?php
/**
 * S3 integration: WP_Smush_S3 class
 *
 * @package WP_Smush
 * @subpackage S3
 * @since 2.7
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2017, Incsub (http://incsub.com)
 */

/**
 * Class WP_Smush_S3
 */
class WP_Smush_S3 extends WP_Smush_Integration {

	/**
	 * WP_Smush_S3 constructor.
	 */
	public function __construct() {
		$this->module   = 's3';
		$this->class    = 'pro';
		$this->priority = 5;
		$this->enabled  = function_exists( 'as3cf_init' );

		parent::__construct();

		// Hook at the end of setting row to output a error div.
		add_action( 'smush_setting_column_right_inside', array( $this, 's3_setup_message' ), 15 );

		// Do not continue if not PRO member or S3 Offload plugin is not installed.
		if ( ! WP_Smush::is_pro() || ! $this->enabled ) {
			return;
		}

		/**
		 * FILTERS
		 */
		// Show submit button when a pro user and the S3 plugin is installed.
		add_filter( 'wp_smush_integration_show_submit', '__return_true' );
		// Check if the backup file exists.
		add_filter( 'smush_backup_exists', array( $this, 'backup_exists_on_s3' ), 10, 3 );

		/**
		 * ACTIONS
		 */
		// Check if the file exists for the given path and download.
		add_action( 'smush_file_exists', array( $this, 'maybe_download_file' ), 10, 3 );
		// Show S3 integration message, if user hasn't enabled it.
		add_action( 'wp_smush_header_notices', array( $this, 's3_support_required_notice' ) );
	}

	/**************************************
	 *
	 * OVERWRITE PARENT CLASS FUNCTIONALITY
	 */

	/**
	 * Filters the setting variable to add S3 setting title and description.
	 *
	 * @param array $settings  Settings array.
	 *
	 * @return mixed
	 */
	public function register( $settings ) {
		$plugin_url                = esc_url( 'https://wordpress.org/plugins/amazon-s3-and-cloudfront/' );
		$settings[ $this->module ] = array(
			'label'       => __( 'Enable Amazon S3 support', 'wp-smushit' ),
			'short_label' => __( 'Amazon S3', 'wp-smushit' ),
			'desc'        => sprintf(
				esc_html__( "Storing your image on S3 buckets using %1\$sWP Offload S3%2\$s? Smush can detect
				and smush those assets for you, including when you're removing files from your host server.", 'wp-smushit' ),
				"<a href='{$plugin_url}' target = '_blank'>",
				'</a>'
			),
		);

		return $settings;
	}

	/**************************************
	 *
	 * PUBLIC CLASSES
	 */

	/**
	 * Check if the file is served by S3 and download the file for given path
	 *
	 * @param string $file_path      Full file path.
	 * @param string $attachment_id  Attachment ID.
	 * @param array  $size_details   Array of width and height for the image.
	 *
	 * @return bool|string False/ File Path
	 */
	public function maybe_download_file( $file_path = '', $attachment_id = '', $size_details = array() ) {
		if ( empty( $file_path ) || empty( $attachment_id ) ) {
			return false;
		}

		// Download if file not exists and served by S3.
		if ( ! file_exists( $file_path ) && $this->is_image_on_s3( $attachment_id ) ) {
			return $this->download_file( $attachment_id, $size_details, $file_path );
		}

		return false;
	}

	/**
	 * Checks if the given attachment is on S3 or not, Returns S3 URL or WP Error
	 *
	 * @param string $attachment_id  Attachment ID.
	 *
	 * @return bool
	 */
	public function is_image_on_s3( $attachment_id = '' ) {
		/**
		 * Amazon_S3_And_CloudFront global.
		 *
		 * @var Amazon_S3_And_CloudFront $as3cf
		 */
		global $as3cf;

		if ( empty( $attachment_id ) || ! is_object( $as3cf ) ) {
			return false;
		}

		// If we only have the attachment id.
		$full_url = $as3cf->is_attachment_served_by_s3( $attachment_id, true );

		// If the file path contains S3, get the s3 URL for the file.
		return ! empty( $full_url ) ? $as3cf->get_attachment_url( $attachment_id ) : false;
	}

	/**
	 * Checks if we've backup on S3 for the given attachment id and backup path
	 *
	 * @param bool   $exists         If backup exists on S3.
	 * @param string $attachment_id  Attachment ID.
	 * @param string $backup_path    Backup path.
	 *
	 * @return bool
	 */
	public function backup_exists_on_s3( $exists, $attachment_id = '', $backup_path = '' ) {
		// If the file is on S3, Check if backup image object exists.
		if ( $this->is_image_on_s3( $attachment_id ) ) {
			return $this->does_image_exists( $attachment_id, $backup_path );
		}

		return $exists;
	}

	/**
	 * Error message to show when S3 support is required.
	 *
	 * Show a error message to admins, if they need to enable S3 support. If "remove files from
	 * server" option is enabled in WP Offload S3 plugin, we need WP Smush Pro to enable S3 support.
	 *
	 * @return bool
	 */
	public function s3_support_required_notice() {
		// Do not display it for other users. Do not display on network screens, if network-wide option is disabled.
		if ( ! current_user_can( 'manage_options' ) || ( is_network_admin() && ! WP_Smush_Settings::$settings['networkwide'] ) ) {
			return true;
		}

		// Do not display the notice on Bulk Smush Screen.
		global $current_screen;

		$allowed_pages = array(
			'toplevel_page_smush',
			'gallery_page_wp-smush-nextgen-bulk',
			'toplevel_page_smush-network',
		);

		if ( ! empty( $current_screen->base ) && ! in_array( $current_screen->base, $allowed_pages, true ) ) {
			return true;
		}

		// If already dismissed, do not show.
		if ( '1' === get_site_option( 'wp-smush-hide_s3support_alert' ) ) {
			return true;
		}

		// Return early, if support is not required.
		if ( ! $this->s3_support_required() ) {
			return true;
		}

		// Settings link.
		$settings_link = is_multisite() && is_network_admin()
			? network_admin_url( 'admin.php?page=smush' )
			: menu_page_url( 'smush', false );

		if ( WP_Smush::is_pro() ) {
			$message = sprintf(
				/**
				 * If premium user, but S3 support is not enabled.
				 *
				 * Translators: %1$s: opening strong tag, %2$s: closing strong tag, %s: settings link,
				 * %3$s: opening a and strong tags, %4$s: closing a and strong tags
				 */
				__( "We can see you have WP Offload S3 installed with the %1\$sRemove Files From Server%2\$s option
				activated. If you want to optimize your S3 images you'll need to enable the %3\$sAmazon S3 Support%4\$s
				feature in Smush's settings.", 'wp-smushit' ),
				'<strong>',
				'</strong>',
				"<a href='{$settings_link}'><strong>",
				'</strong></a>'
			);
		} else {
			$message = sprintf(
				/**
				 * If not a premium user.
				 *
				 * Translators: %1$s: opening strong tag, %2$s: closing strong tag, %s: settings link,
				 * %3$s: opening a and strong tags, %4$s: closing a and strong tags
				 */
				__( "We can see you have WP Offload S3 installed with the %1\$sRemove Files From Server%2\$s option
				activated. If you want to optimize your S3 images you'll need to %3\$supgrade to Smush Pro%4\$s", 'wp-smushit' ),
				'<strong>',
				'</strong>',
				'<a href=' . esc_url( 'https://premium.wpmudev.org/project/wp-smush-pro' ) . '><strong>',
				'</strong></a>'
			);
		}

		?>
		<div class="sui-notice sui-notice-warning wp-smush-s3support-alert">
			<p><?php echo $message; ?></p>
			<span class="sui-notice-dismiss">
				<a href="#">
					<?php esc_html_e( 'Dismiss', 'wp-smushit' ); ?>
				</a>
			</span>
		</div>
		<?php
	}

	/**
	 * Prints the message for S3 setup
	 *
	 * @param string $setting_key  Settings key.
	 */
	public function s3_setup_message( $setting_key ) {
		// Return if not S3.
		if ( $this->module !== $setting_key ) {
			return;
		}

		/**
		 * Amazon_S3_And_CloudFront global.
		 *
		 * @var Amazon_S3_And_CloudFront $as3cf
		 */
		global $as3cf;

		$is_pro = WP_Smush::is_pro();

		// If S3 integration is not enabled, return.
		$setting_val = $is_pro ? WP_Smush_Settings::$settings[ $this->module ] : 0;

		// If integration is disabled when S3 offload is active, do not continue.
		if ( ! $setting_val && is_object( $as3cf ) ) {
			return;
		}

		// If S3 offload global variable is not available, plugin is not active.
		if ( ! is_object( $as3cf ) ) {
			$class   = '';
			$message = __( 'To use this feature you need to install WP Offload S3 and have an Amazon S3 account setup.', 'wp-smushit' );
		} elseif ( ! method_exists( $as3cf, 'is_plugin_setup' ) ) {
			// Check if in case for some reason, we couldn't find the required function.
			$class       = ' sui-notice-warning';
			$support_url = esc_url( 'https://premium.wpmudev.org/contact' );
			$message     = sprintf(
				/* translators: %1$s: opening a tag, %2$s: closing a tag */
				esc_html__( 'We are having trouble interacting with WP Offload S3, make sure the plugin is
				activated. Or you can %1$sreport a bug%2$s.', 'wp-smushit' ),
				'<a href="' . $support_url . '" target="_blank">',
				'</a>'
			);
		} elseif ( ! $as3cf->is_plugin_setup() ) {
			// Plugin is not setup, or some information is missing.
			$class         = ' sui-notice-warning';
			$configure_url = $as3cf->get_plugin_page_url();
			$message       = sprintf(
				/* translators: %1$s: opening a tag, %2$s: closing a tag */
				esc_html__( 'It seems you haven’t finished setting up WP Offload S3 yet. %1$sConfigure it
				now%2$s to enable Amazon S3 support.', 'wp-smushit' ),
				'<a href="' . $configure_url . '" target="_blank">',
				'</a>'
			);
		} else {
			// S3 support is active.
			$class   = ' sui-notice-info';
			$message = __( 'Amazon S3 support is active.', 'wp-smushit' );
		}

		// Return early if we don't need to do anything.
		if ( empty( $message ) || ! $is_pro ) {
			return;
		}
		?>
		<div class="sui-notice<?php echo esc_attr( $class ); ?> smush-notice-sm">
			<p><?php echo $message; ?></p>
		</div>
		<?php
	}

	/**************************************
	 *
	 * PRIVATE CLASSES
	 */

	/**
	 * Download a specified file to local server with respect to provided attachment id
	 *  and/or Attachment path.
	 *
	 * @param int    $attachment_id  Attachment ID.
	 * @param array  $size_details   Size details array.
	 * @param string $uf_file_path   File path.
	 *
	 * @return bool|string  Returns file path or false
	 */
	private function download_file( $attachment_id, $size_details = array(), $uf_file_path = '' ) {
		if ( empty( $attachment_id ) || ! WP_Smush_Settings::$settings[ $this->module ] || ! WP_Smush::is_pro() ) {
			return false;
		}

		/**
		 * Amazon_S3_And_CloudFront global.
		 *
		 * @var Amazon_S3_And_CloudFront $as3cf
		 */
		global $as3cf;

		$file = false;

		// If file path wasn't specified in argument.
		$uf_file_path = empty( $uf_file_path ) ? get_attached_file( $attachment_id, true ) : $uf_file_path;

		// If we have plugin method available, us that otherwise check it ourselves.
		if ( method_exists( $as3cf, 'is_attachment_served_by_s3' ) ) {
			$s3_object        = $as3cf->is_attachment_served_by_s3( $attachment_id, true );
			$size_prefix      = dirname( $s3_object['key'] );
			$size_file_prefix = ( '.' === $size_prefix ) ? '' : $size_prefix . '/';
			if ( ! empty( $size_details ) && is_array( $size_details ) ) {
				$s3_object['key'] = path_join( $size_file_prefix, $size_details['file'] );
			} elseif ( ! empty( $uf_file_path ) ) {
				// Get the File path using basename for given attachment path.
				$s3_object['key'] = path_join( $size_file_prefix, wp_basename( $uf_file_path ) );
			}

			// Try to download the attachment.
			if ( $s3_object && is_object( $as3cf->plugin_compat ) && method_exists( $as3cf->plugin_compat, 'copy_s3_file_to_server' ) ) {
				// Download file.
				$file = $as3cf->plugin_compat->copy_s3_file_to_server( $s3_object, $uf_file_path );
			}

			if ( $file ) {
				return $file;
			}
		}

		// If we don't have the file, Try it the basic way.
		if ( ! $file ) {
			$s3_url = $this->is_image_on_s3( $attachment_id );

			// If we couldn't get the image URL, return false.
			if ( is_wp_error( $s3_url ) || empty( $s3_url ) || ! $s3_url ) {
				return false;
			}

			if ( ! empty( $size_details ) ) {
				// If size details are available, Update the URL to get the image for the specified size.
				$s3_url = str_replace( wp_basename( $s3_url ), $size_details['file'], $s3_url );
			} elseif ( ! empty( $uf_file_path ) ) {
				// Get the File path using basename for given attachment path.
				$s3_url = str_replace( wp_basename( $s3_url ), wp_basename( $uf_file_path ), $s3_url );
			}

			// Download the file.
			$temp_file = download_url( $s3_url );
			$renamed   = false;
			if ( ! is_wp_error( $temp_file ) ) {
				$renamed = @copy( $temp_file, $uf_file_path );
				unlink( $temp_file );
			}

			// If we were able to successfully rename the file, return file path.
			if ( $renamed ) {
				return $uf_file_path;
			}
		}

		return false;
	}

	/**
	 * Check if file exists for the given path
	 *
	 * @param string $attachment_id  Attachment ID.
	 * @param string $file_path      File path.
	 *
	 * @return bool
	 */
	private function does_image_exists( $attachment_id = '', $file_path = '' ) {
		/**
		 * Amazon_S3_And_CloudFront global.
		 *
		 * @var Amazon_S3_And_CloudFront $as3cf
		 */
		global $as3cf;

		if ( empty( $attachment_id ) || empty( $file_path ) ) {
			return false;
		}
		// Return if method doesn't exists.
		if ( ! method_exists( $as3cf, 'is_attachment_served_by_s3' ) ) {
			error_log( "Couldn't find method is_attachment_served_by_s3." );
			return false;
		}
		// Get s3 object for the file.
		$s3_object = $as3cf->is_attachment_served_by_s3( $attachment_id, true );

		$size_prefix      = dirname( $s3_object['key'] );
		$size_file_prefix = ( '.' === $size_prefix ) ? '' : $size_prefix . '/';

		// Get the File path using basename for given attachment path.
		$s3_object['key'] = path_join( $size_file_prefix, wp_basename( $file_path ) );

		// Get bucket details.
		$bucket = $as3cf->get_setting( 'bucket' );
		$region = $as3cf->get_setting( 'region' );

		if ( is_wp_error( $region ) ) {
			return false;
		}

		$s3client = $as3cf->get_s3client( $region );

		// If we still have the older version of S3 Offload, use old method.
		if ( method_exists( $s3client, 'doesObjectExist' ) ) {
			$file_exists = $s3client->doesObjectExist( $bucket, $s3_object['key'] );
		} else {
			$file_exists = $s3client->does_object_exist( $bucket, $s3_object['key'] );
		}

		return $file_exists;
	}

	/**
	 * Check if S3 support is required for Smush.
	 *
	 * @return bool
	 */
	private function s3_support_required() {
		/**
		 * Amazon_S3_And_CloudFront global.
		 *
		 * @var Amazon_S3_And_CloudFront $as3cf
		 */
		global $as3cf;

		// Check if S3 offload plugin is active and delete file from server option is enabled.
		if ( ! is_object( $as3cf ) || ! method_exists( $as3cf, 'get_setting' ) || ! $as3cf->get_setting( 'remove-local-file' ) ) {
			return false;
		}

		// If not Pro user or S3 support is disabled.
		return ( ! WP_Smush::is_pro() || ! WP_Smush_Settings::$settings[ $this->module ] );
	}

}