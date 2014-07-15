<?php

/**
 * Description of wp-smushit-pro-admin
 *
 * @author Saurabh Shukla <contact.saurabhshukla@gmail.com>
 */
class WpSmushItPro_Admin {

	public $settings = array(
		'smushit_auto'     => 'Smush images on upload?',
		'smushit_timeout'  => 'Timeout (in seconds)',
		'remove_exif'      => 'Remove Exif data',
		'progressive_jpeg' => 'Allow progressive JPEGs',
		'gif_to_png'       => 'Allow Gif to Png conversion',
	);

	public function __construct() {
		// add extra columns for smushing to media lists
		add_filter( 'manage_media_columns', array( &$this, 'columns' ) );
		add_action( 'manage_media_custom_column', array( &$this, 'custom_column' ), 10, 2 );

		// add the admin option screens
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );

		add_action( 'admin_head-upload.php', array( &$this, 'add_bulk_actions_via_javascript' ) );
		add_action( 'admin_action_bulk_smushit', array( &$this, 'bulk_action_handler' ) );

		add_action( 'admin_init', array( &$this, 'register_settings' ) );
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
			$data = wp_get_attachment_metadata( $id );
			if ( isset( $data['smush_meta'] ) && ! empty( $data['smush_meta']['full'] ) ) {
				print $data['smush_meta']['full']['status_msg'];
				printf( "<br><a href=\"admin.php?action=wp_smushit_manual&amp;attachment_ID=%d\">%s</a>", $id, __( 'Re-smush', WP_SMUSHIT_PRO_DOMAIN ) );
			} else {
				if ( wp_attachment_is_image( $id ) ) {
					print __( 'Not processed', WP_SMUSHIT_PRO_DOMAIN );
					printf( "<br><a href=\"admin.php?action=wp_smushit_manual&amp;attachment_ID=%d\">%s</a>", $id, __( 'Smush.it now!', WP_SMUSHIT_PRO_DOMAIN ) );
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
		wp_redirect( add_query_arg( '_wpnonce', wp_create_nonce( 'wp-smushit-bulk' ), admin_url( 'upload.php?page=wp-smushit-bulk&goback=1&ids=' . $ids ) ) );
		exit();
	}

	/**
	 * Plugin setting functions
	 */
	function register_settings() {

		add_settings_section( 'wp_smushit_pro_settings', 'WP Smush.it Pro', array(
			&$this,
			'settings_cb'
		), 'media' );

		foreach ( $this->settings as $name => $text ) {
			add_settings_field(
				'wp_smushit_pro_' . $name,
				__( $text, WP_SMUSHIT_PRO_DOMAIN ),
				array( $this, 'render_' . $name . '_opts' ),
				'media',
				'wp_smushit_pro_settings'
			);

			register_setting( 'media', 'wp_smushit_pro_' . $name );
		}
	}

	function settings_cb() {

	}

	/**
	 * Allows user to choose whether to automatically smush images or not
	 */
	function render_auto_opts() {
		$key = 'wp_smushit_pro_smushit_auto';
		$val = intval( get_option( $key, WP_SMUSHIT_PRO_AUTO_OK ) );
		printf( "<select name='%1\$s' id='%1\$s'>", esc_attr( $key ) );
		echo '<option value=' . WP_SMUSHIT_PRO_AUTO_OK . ' ' . selected( WP_SMUSHIT_PRO_AUTO_OK, $val ) . '>' . __( 'Automatically process on upload', WP_SMUSHIT_PRO_DOMAIN ) . '</option>';
		echo '<option value=' . WP_SMUSHIT_PRO_AUTO_NEVER . ' ' . selected( WP_SMUSHIT_PRO_AUTO_NEVER, $val ) . '>' . __( 'Do not process on upload', WP_SMUSHIT_PRO_DOMAIN ) . '</option>';

		if ( $val > 0 ) {
			printf( '<option value="%d" selected="selected">', $val ) .
			printf( __( 'Temporarily disabled until %s', WP_SMUSHIT_PRO_DOMAIN ), date( 'M j, Y \a\t H:i', $val ) ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Maximum Time out for Smush it
	 *
	 * @param $key
	 */
	function render_timeout_opts( $key ) {
		$key = 'wp_smushit_smushit_timeout';
		$val = intval( get_option( $key, WP_SMUSHIT_PRO_AUTO_OK ) );
		printf( "<input type='text' name='%1\$s' id='%1\$s' value='%2\%d'>", esc_attr( $key ), intval( get_option( $key, 60 ) ) );
	}

	/**
	 * Display an option to allow Smushit debugging
	 */
	function render_debug_opts() {
		$key = 'wp_smushit_smushit_debug';
		$val = get_option( $key, WP_SMUSHIT_PRO_DEBUG );
		?><input type="checkbox" name="<?php echo $key ?>" <?php
		if ( $val ) {
			echo ' checked="checked" ';
		}
		?>/> <?php
		_e( 'If you are having trouble with the plugin enable this option can reveal some information about your system needed for support.', WP_SMUSHIT_PRO_DOMAIN );
	}

	/**
	 * Adds a setting field, Keep exif data or not
	 */
	function render_exif_opts() {
		$key = 'wp_smushit_pro_remove_exif';
		$val = get_option( $key, WP_SMUSH_PRO_REMOVE_EXIF );
		?>
		<input type="checkbox" name="wp_smushit_pro_remove_exif" <?php checked( $val, 'on', true ); ?> /><?php
	}

	/**
	 * Adds a setting field, Keep exif data or not
	 */
	function render_progressive_jpeg_opts() {
		$key = 'wp_smushit_pro_progressive_jpeg';
		$val = get_option( $key, true );
		?>
		<input type="checkbox" name="wp_smushit_pro_progressive_jpeg" <?php checked( $val, 'on', true ); ?> /><?php
	}

	/**
	 * Adds a setting field, Allow GIF to PNG conversion for single frame images
	 */
	function render_gif_to_png() {
		$key = 'wp_smushit_pro_gif_to_png';
		$val = get_option( $key, true );
		?>
		<input type="checkbox" name="wp_smushit_pro_gif_to_png" <?php checked( $val, 'on', true ); ?> /><?php
	}

	// default is 6hrs
	function temporarily_disable( $seconds = 21600 ) {
		update_option( 'wp_smushit_pro_smushit_auto', time() + $seconds );
	}

	function admin_init() {
		load_plugin_textdomain( WP_SMUSHIT_PRO_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		wp_enqueue_script( 'common' );
	}

	/**
	 * Add Bulk option settings page
	 */
	function admin_menu() {
		add_media_page( 'Bulk Smush.it', 'Bulk Smush.it', 'edit_others_posts', 'wp-smushit-bulk', array(
			&$this,
			'bulk_preview'
		) );
	}

	/**
	 * Allows user to Bulk Smush the images
	 */
	function bulk_preview() {
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', 1 );
		}
		@ini_set( 'output_buffering', 'on' );
		@ini_set( 'zlib.output_compression', 0 );
		@ini_set( 'implicit_flush', 1 );

		$attachments = null;
		$auto_start  = false;

		if ( isset( $_REQUEST['ids'] ) ) {
			$attachments = get_posts( array(
				'numberposts'    => - 1,
				'include'        => explode( ',', $_REQUEST['ids'] ),
				'post_type'      => 'attachment',
				'post_mime_type' => 'image'
			) );
			$auto_start  = true;
		} else {
			$attachments = get_posts( array(
				'numberposts'    => - 1,
				'post_type'      => 'attachment',
				'post_mime_type' => 'image'
			) );
		}
		?>
		<div class="wrap">
			<div id="icon-upload" class="icon32"><br/></div>
			<h2><?php _e( 'Bulk WP Smush.it Pro', WP_SMUSHIT_PRO_DOMAIN ) ?></h2>
			<?php
			if ( sizeof( $attachments ) < 1 ) {
				_e( "<p>You don't appear to have uploaded any images yet.</p>", WP_SMUSHIT_PRO_DOMAIN );
			} else {
				if ( empty( $_POST ) && ! $auto_start ) { // instructions page
					_e( "<p>This tool will run all of the images in your media library through the WP Smush.it web service. Any image already processed will not be reprocessed. Any new images or unsuccessful attempts will be processed.</p>", WP_SMUSHIT_PRO_DOMAIN );
					_e( "<p>As part of the Yahoo! Smush.it API this plugin wil provide a URL to each of your images to be processed. The Yahoo! service will download the image via the URL. The Yahoo Smush.it service will then return a URL to this plugin of the new version of the image. This image will be downloaded and replace the original image on your server.</p>", WP_SMUSHIT_PRO_DOMAIN );
					?>
					<hr/>
					<?php
					$attachment_count = sizeof( $attachments );
					$time             = $attachment_count * 3 / 60;
					printf( __( "<p>We found %d images in your media library. Be forewarned, <strong>it will take <em>at least</em> %f minutes</strong> to process all these images if they have never been smushed before.</p>", WP_SMUSHIT_PRO_DOMAIN ), $attachment_count, round( $time, 2 ) );
					?>
					<form method="post" action="">
						<?php wp_nonce_field( 'wp-smushit-bulk', '_wpnonce' ); ?>
						<button type="submit" class="button-secondary action"><?php _e( 'Run all my images through WP Smush.it Pro right now', WP_SMUSHIT_PRO_DOMAIN ) ?></button>
						<?php _e( "<p><em>N.B. If your server <tt>gzip</tt>s content you may not see the progress updates as your files are processed.</em></p>", WP_SMUSHIT_PRO_DOMAIN ); ?>
						<?php
						if ( WP_SMUSHIT_PRO_DEBUG ) {
							_e( "<p>DEBUG mode is currently enabled. To disable see the Settings > Media page.</p>", WP_SMUSHIT_PRO_DOMAIN );
						}
						?>
					</form>
				<?php
				} else { // run the script
					if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'wp-smushit-bulk' ) || ! current_user_can( 'edit_others_posts' ) ) {
						wp_die( __( 'Cheatin&#8217; uh?' ) );
					}


					@ob_implicit_flush( true );
					@ob_end_flush();
					foreach ( $attachments as $attachment ) {
						printf( __( "<p>Processing <strong>%s</strong>&hellip;<br />", WP_SMUSHIT_PRO_DOMAIN ), esc_html( $attachment->post_name ) );
						$original_meta = wp_get_attachment_metadata( $attachment->ID, true );

						$meta = $this->resize_from_meta_data( $original_meta, $attachment->ID, false );
						printf( "&mdash; [original] %d x %d: ", intval( $meta['width'] ), intval( $meta['height'] ) );

						if ( ( isset( $original_meta['wp_smushit'] ) ) && ( $original_meta['wp_smushit'] == $meta['wp_smushit'] ) && ( stripos( $meta['wp_smushit'], 'Smush.it error' ) === false )
						) {
							if ( ( stripos( $meta['wp_smushit'], '<a' ) === false ) && ( stripos( $meta['wp_smushit'], __( 'No savings', WP_SMUSHIT_PRO_DOMAIN ) ) === false )
							) {
								echo $meta['wp_smushit'] . ' ' . __( '<strong>already smushed</strong>', WP_SMUSHIT_PRO_DOMAIN );
							} else {
								echo $meta['wp_smushit'];
							}
						} else {
							echo $meta['wp_smushit'];
						}
						echo '<br />';

						if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
							foreach ( $meta['sizes'] as $size_name => $size ) {
								printf( "&mdash; [%s] %d x %d: ", $size_name, intval( $size['width'] ), intval( $size['height'] ) );
								if ( $original_meta['sizes'][ $size_name ]['wp_smushit'] == $size['wp_smushit'] && stripos( $meta['sizes'][ $size_name ]['wp_smushit'], 'Smush.it error' ) === false ) {
									echo $size['wp_smushit'] . ' ' . __( '<strong>already smushed</strong>', WP_SMUSHIT_PRO_DOMAIN );
								} else {
									echo $size['wp_smushit'];
								}
								echo '<br />';
							}
						}
						echo "</p>";

						wp_update_attachment_metadata( $attachment->ID, $meta );

						// rate limiting is good manners, let's be nice to Yahoo!
						sleep( 0.5 );
						@ob_flush();
						flush();
					}
					_e( '<hr /></p>Smush.it finished processing.</p>', WP_SMUSHIT_PRO_DOMAIN );
				}
			}
			?>
		</div>
	<?php
	}

}
    