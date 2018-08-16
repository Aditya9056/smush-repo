<?php
/**
 * Smush admin functionality: WpSmushitAdmin class
 *
 * @package WP_Smush
 * @subpackage Admin
 * @version 1.0
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2016, Incsub (http://incsub.com)
 */

/**
 * Class WpSmushitAdmin
 *
 * @property int $remaining_count
 * @property int $total_count
 * @property int $smushed_count
 */
class WpSmushitAdmin extends WP_Smush_Main {
	public $bulk;

	/**
	 * WpSmushitAdmin constructor.
	 */
	public function __construct() {
		// Hook custom screen.
		add_action( 'admin_menu', array( $this, 'screen' ) );

		// Network Settings Page.
		add_action( 'network_admin_menu', array( $this, 'screen' ) );

		// Update the Super Smush count, after the smushing.
		add_action( 'wp_smush_image_optimised', array( $this, 'update_lists' ), '', 2 );

		/**
		 * Prints a membership validation issue notice in Media Library
		 */
		add_action( 'admin_notices', array( $this, 'media_library_membership_notice' ) );

		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'smush_send_status' ), 99, 3 );

		// Load js and css on pages with Media Uploader - WP Enqueue Media.
		add_action( 'wp_enqueue_media', array( $this, 'enqueue' ) );

		// Admin pointer for new Smush installation.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_pointer' ) );

		// Smush image filter from Media Library.
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_media_query' ) );
	}

	/**
	 * Add Bulk option settings page.
	 */
	function screen() {
		global $wpsmush_bulkui;

		$cap   = is_multisite() ? 'manage_network_options' : 'manage_options';
		$title = $this->validate_install() ? esc_html__( 'Smush Pro', 'wp-smushit' ) : esc_html__( 'Smush', 'wp-smushit' );
		add_menu_page(
			$title, $title, $cap, 'smush', array(
				$wpsmush_bulkui,
				'ui',
			), $this->get_menu_icon()
		);

		// For Nextgen gallery Pages, check later in enqueue function.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Display Thumbnails, if bulk action is choosen
	 *
	 * @Note: Not in use right now, Will use it in future for Media Bulk action
	 */
	function selected_ui( $send_ids, $received_ids ) {
		if ( empty( $received_ids ) ) {
			return;
		}

		?>
		<div id="select-bulk" class="wp-smush-bulk-wrap">
			<p>
				<?php
				printf(
					__(
						'<strong>%1$d of %2$d images</strong> were sent for smushing:',
						'wp-smushit'
					),
					count( $send_ids ), count( $received_ids )
				);
				?>
			</p>
			<ul id="wp-smush-selected-images">
				<?php
				foreach ( $received_ids as $attachment_id ) {
					$this->attachment_ui( $attachment_id );
				}
				?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Get the smush button text for attachment
	 *
	 * @param $id Attachment ID for which the Status has to be set
	 *
	 * @return string
	 */
	function smush_status( $id ) {
		global $wp_smush;

		// Show Temporary Status, For Async Optimisation, No Good workaround
		if ( ! get_option( "wp-smush-restore-$id", false ) && ! empty( $_POST['action'] ) && 'upload-attachment' == $_POST['action'] && $wp_smush->is_auto_smush_enabled() ) {
			// the status
			$status_txt = '<p class="smush-status">' . __( 'Smushing in progress..', 'wp-smushit' ) . '</p>';

			// we need to show the smush button
			$show_button = false;

			// the button text
			$button_txt = __( 'Smush Now!', 'wp-smushit' );

			return $this->column_html( $id, $status_txt, $button_txt, $show_button, true, false, true );
		}
		// Else Return the normal status
		$response = trim( $this->set_status( $id, false ) );

		return $response;
	}

	/**
	 * Get the smushed attachments from the database, except gif
	 *
	 * @global object $wpdb
	 *
	 * @return object query results
	 */
	function get_smushed_attachments() {

		global $wpdb;

		$allowed_images = "( 'image/jpeg', 'image/jpg', 'image/x-citrix-jpeg', 'image/png', 'image/x-png' )";

		$limit      = $this->query_limit();
		$offset     = 0;
		$query_next = true;

		while ( $query_next ) {
			// get the attachment id, smush data
			$sql = 'SELECT p.ID as attachment_id, p.post_mime_type as type, ms.meta_value as smush_data'
					   . " FROM $wpdb->posts as p"
					   . " LEFT JOIN $wpdb->postmeta as ms"
					   . " ON (p.ID= ms.post_id AND ms.meta_key='wp-smpro-smush-data')"
					   . ' WHERE'
					   . " p.post_type='attachment'"
					   . ' AND p.post_mime_type IN ' . $allowed_images
					   . ' ORDER BY p . ID DESC'
					   // add a limit
					   . ' LIMIT ' . $limit;
			$results = $wpdb->get_results( $sql );

			// Update the offset
			$offset += $limit;
			if ( ! empty( $this->total_count ) && $this->total_count <= $offset ) {
				$query_next = false;
			} elseif ( ! $results || empty( $results ) ) {
				$query_next = false;
			}
		}

		return $results;
	}

	/**
	 * Format Numbers to short form 1000 -> 1k
	 *
	 * @param $number
	 *
	 * @return string
	 */
	function format_number( $number ) {
		if ( $number >= 1000 ) {
			return $number / 1000 . 'k';   // NB: you will want to round this
		} else {
			return $number;
		}
	}

	/**
	 * Add/Remove image id from Super Smushed images count
	 *
	 * @param int    $id Image id
	 *
	 * @param string $op_type Add/remove, whether to add the image id or remove it from the list
	 *
	 * @return bool Whether the Super Smushed option was update or not
	 */
	function update_super_smush_count( $id, $op_type = 'add', $key = 'wp-smush-super_smushed' ) {

		// Get the existing count
		$super_smushed = get_option( $key, false );

		// Initialize if it doesn't exists
		if ( ! $super_smushed || empty( $super_smushed['ids'] ) ) {
			$super_smushed = array(
				'ids' => array(),
			);
		}

		// Insert the id, if not in there already
		if ( 'add' == $op_type && ! in_array( $id, $super_smushed['ids'] ) ) {

			$super_smushed['ids'][] = $id;

		} elseif ( 'remove' == $op_type && false !== ( $k = array_search( $id, $super_smushed['ids'] ) ) ) {

			// Else remove the id from the list
			unset( $super_smushed['ids'][ $k ] );

			// Reset all the indexes
			$super_smushed['ids'] = array_values( $super_smushed['ids'] );

		}

		// Add the timestamp
		$super_smushed['timestamp'] = current_time( 'timestamp' );

		update_option( $key, $super_smushed, false );

		// Update to database
		return true;
	}

	/**
	 * Checks if the image compression is lossy, stores the image id in options table
	 *
	 * @param int    $id Image Id
	 *
	 * @param array  $stats Compression Stats
	 *
	 * @param string $key Meta Key for storing the Super Smushed ids (Optional for Media Library)
	 *                    Need To be specified for NextGen
	 *
	 * @return bool
	 */
	function update_lists( $id, $stats, $key = '' ) {
		// If Stats are empty or the image id is not provided, return
		if ( empty( $stats ) || empty( $id ) || empty( $stats['stats'] ) ) {
			return false;
		}

		// Update Super Smush count
		if ( isset( $stats['stats']['lossy'] ) && 1 == $stats['stats']['lossy'] ) {
			if ( empty( $key ) ) {
				update_post_meta( $id, 'wp-smush-lossy', 1 );
			} else {
				$this->update_super_smush_count( $id, 'add', $key );
			}
		}

		// Check and update re-smush list for media gallery
		if ( ! empty( $this->resmush_ids ) && in_array( $id, $this->resmush_ids ) ) {
			$this->update_resmush_list( $id );
		}

	}

	/**
	 * Allows to bulk restore the images, if there is any backup for them
	 */
	function bulk_restore() {
		$smushed_attachments = ! empty( $this->smushed_attachments ) ? $this->smushed_attachments : $wpsmush_db->smushed_count( true );
		foreach ( $smushed_attachments as $attachment ) {
			WP_Smush::get_instance()->core()->backup->restore_image( $attachment->attachment_id, false );
		}
	}

	/**
	 * Show Update info in admin Notice
	 */
	function smush_updated() {
		// @todo: Update Smush Update Notice for next release
		// Make sure to not display this message for next release
		$plugin_data = get_plugin_data( WP_SMUSH_DIR . 'wp-smush.php', false, false );
		$version     = ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : '';

		// If Versions Do not match
		if ( empty( $version ) || $version != WP_SMUSH_VERSION ) {
			return true;
		}

		// Do not display it for other users
		if ( ! is_super_admin() || ! current_user_can( 'manage_options' ) ) {
			return true;
		}

		// If dismissed, Delete the option on Plugin Activation, For alter releases
		if ( 1 == get_site_option( 'wp-smush-hide_update_info' ) ) {
			return true;
		}

		// Get Plugin dir, Return if it's WP Smush Pro installation
		if ( ! defined( 'WP_SMUSH_DIR' ) && strpos( WP_SMUSH_DIR, 'wp-smush-pro' ) !== false ) {
			return true;
		}

		// Do not display the notice on Bulk Smush Screen
		global $current_screen;
		if ( ! empty( $current_screen->base ) && ( 'toplevel_page_smush' == $current_screen->base || 'toplevel_page_smush-network' == $current_screen->base || 'gallery_page_wp-smush-nextgen-bulk' == $current_screen->base || 'toplevel_page_smush-network' == $current_screen->base ) ) {
			return true;
		}

		$upgrade_url   = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'smush_async_upgrade_notice',
			),
			$this->upgrade_url
		);
		$settings_link = is_multisite() && is_network_admin() ? network_admin_url( 'admin.php?page=smush' ) : menu_page_url( 'smush', false );

		$settings_link = '<a href="' . $settings_link . '" title="' . esc_html__( 'Review your setting now.', 'wp-smushit' ) . '">';
		$upgrade_link  = '<a href="' . esc_url( $upgrade_url ) . '" title="' . esc_html__( 'Smush Pro', 'wp-smushit' ) . '">';
		$message_s     = sprintf( esc_html__( "Welcome to the newest version of Smush! In this update we've added the ability to bulk smush images in directories outside your uploads folder.", 'wp-smushit' ), WP_SMUSH_VERSION, '<strong>', '</strong>' );

		// Message for network admin
		$message_s .= is_multisite() ? sprintf( esc_html__( ' And as a multisite user, you can manage %1$sSmush settings%2$s globally across all sites!', 'wp-smushit' ), $settings_link, '</a>' ) : '';

		// Upgrade link for free users
		$message_s .= ! $this->validate_install() ? sprintf( esc_html__( ' %1$sFind out more here >>%2$s', 'wp-smushit' ), $upgrade_link, '</a>' ) : '';
		?>
		<div class="notice notice-info is-dismissible wp-smush-update-info">
		<p><?php echo $message_s; ?></p>
		</div>
		<?php
	}

	/**
	 * Check whether to skip a specific image size or not
	 *
	 * @param string $size Registered image size
	 *
	 * @return bool true/false Whether to skip the image size or not
	 */
	function skip_image_size( $size = '' ) {
		global $wpsmush_settings;

		// No image size specified, Don't skip
		if ( empty( $size ) ) {
			return false;
		}

		$image_sizes = $wpsmush_settings->get_setting( WP_SMUSH_PREFIX . 'image_sizes' );

		// If Images sizes aren't set, don't skip any of the image size
		if ( false === $image_sizes ) {
			return false;
		}

		// Check if the size is in the smush list
		if ( is_array( $image_sizes ) && ! in_array( $size, $image_sizes ) ) {
			return true;
		}

	}

	/**
	 * Prints the Membership Validation issue notice
	 */
	function media_library_membership_notice() {
		global $wpsmush_bulkui;

		// No need to print it for free version
		if ( ! $this->validate_install() ) {
			return;
		}
		// Show it on Media Library page only
		$screen    = get_current_screen();
		$screen_id = ! empty( $screen ) ? $screen->id : '';
		// Do not show notice anywhere else
		if ( empty( $screen ) || 'upload' != $screen_id ) {
			return;
		}

		echo $wpsmush_bulkui->get_user_validation_message( false );
	}



	/**
	 * Load media assets.
	 */
	public function extend_media_modal() {
		if ( wp_script_is( 'smush-backbone-extension', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_script(
			'smush-backbone-extension', WP_SMUSH_URL . 'app/assets/js/media.min.js', array(
				'jquery',
				'media-editor', // Used in image filters
				'media-views',
				'media-grid',
				'wp-util',
				'wp-api',
			), WP_SMUSH_VERSION, true
		);

		wp_localize_script(
			'smush-backbone-extension', 'smush_vars', array(
				'strings' => array(
					'stats_label'  => esc_html__( 'Smush', 'wp-smushit' ),
					'filter_all'   => esc_html__( 'Smush: All images', 'wp-smushit' ),
					'filter_excl'  => esc_html__( 'Smush: Bulk ignored', 'wp-smushit' ),
				),
				'nonce'   => array(
					'get_smush_status' => wp_create_nonce( 'get-smush-status' ),
				)
			)
		);
	}

	/**
	 * Send smush status for attachment
	 *
	 * @param $response
	 * @param $attachment
	 *
	 * @return mixed
	 */
	function smush_send_status( $response, $attachment ) {
		if ( ! isset( $attachment->ID ) ) {
			return $response;
		}

		// Validate nonce
		$status            = $this->smush_status( $attachment->ID );
		$response['smush'] = $status;

		return $response;
	}

	/**
	 * Smush icon svg image
	 *
	 * @return string
	 */
	private function get_menu_icon() {
		ob_start();
		?>
		<svg width="16px" height="16px" viewBox="0 0 16 16" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
			<g id="Symbols" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
				<g id="WP-/-Menu---Free" transform="translate(-12.000000, -428.000000)" fill="#FFFFFF;">
					<path d="M26.9310561,432.026782 C27.2629305,432.598346 27.5228884,433.217017 27.7109375,433.882812 C27.9036468,434.565108 28,435.27083 28,436 C28,437.104172 27.7916687,438.14062 27.375,439.109375 C26.9479145,440.07813 26.3750036,440.924476 25.65625,441.648438 C24.9374964,442.372399 24.0937548,442.942706 23.125,443.359375 C22.1562452,443.78646 21.1197972,444 20.015625,444 L26.9310562,432.026782 L26.9310561,432.026782 Z M26.9310561,432.026782 C26.9228316,432.012617 26.9145629,431.998482 26.90625,431.984375 L26.9375,432.015625 L26.9310562,432.026782 L26.9310561,432.026782 Z M16.625,433.171875 L23.375,433.171875 L20,439.03125 L16.625,433.171875 Z M14.046875,430.671875 L14.046875,430.65625 C14.4114602,430.249998 14.8177061,429.88021 15.265625,429.546875 C15.7031272,429.223957 16.1744766,428.945314 16.6796875,428.710938 C17.1848984,428.476561 17.7187472,428.296876 18.28125,428.171875 C18.8333361,428.046874 19.406247,427.984375 20,427.984375 C20.593753,427.984375 21.1666639,428.046874 21.71875,428.171875 C22.2812528,428.296876 22.8151016,428.476561 23.3203125,428.710938 C23.8255234,428.945314 24.3020811,429.223957 24.75,429.546875 C25.1875022,429.88021 25.5937481,430.255206 25.96875,430.671875 L14.046875,430.671875 Z M13.0625,432.03125 L19.984375,444 C18.8802028,444 17.8437548,443.78646 16.875,443.359375 C15.9062452,442.942706 15.0625036,442.372399 14.34375,441.648438 C13.6249964,440.924476 13.0572937,440.07813 12.640625,439.109375 C12.2239563,438.14062 12.015625,437.104172 12.015625,436 C12.015625,435.27083 12.1067699,434.567712 12.2890625,433.890625 C12.4713551,433.213538 12.729165,432.593753 13.0625,432.03125 Z" id="icon-smush"></path>
				</g>
			</g>
		</svg>
		<?php
		$svg = ob_get_clean();

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Add custom admin pointer using wp-pointer.
	 *
	 * We have removed activation redirect to Smush settings
	 * in new version to avoid interrupting bulk activations.
	 * Show a pointer notice to Smush settings menu on new
	 * activations.
	 *
	 * @since 2.9
	 */
	public function admin_pointer() {

		// Get dismissed pointers meta.
		$dismissed_pointers = get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true );

		// Explod them by comma.
		$dismissed_pointers = explode( ',', (string) $dismissed_pointers );

		// If smush pointer is not found in dismissed pointers, show.
		if ( in_array( 'smush_pointer', $dismissed_pointers ) ) {
			return;
		}

		// We had a flag in old versions for activation redirect. Check that also.
		if ( get_site_option( 'wp-smush-skip-redirect' ) ) {
			return;
		}

		// Enqueue wp-pointer styles and scripts.
		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );

		// Register our custom pointer.
		add_action( 'admin_print_footer_scripts', array( $this, 'register_admin_pointer' ) );
	}

	/**
	 * Register smush custom pointer to wp-pointer.
	 *
	 * Use wordpress dismiss-wp-pointer action on pointer
	 * dismissal to store dismissal flag in meta via ajax.
	 *
	 * @since 2.9
	 */
	public function register_admin_pointer() {
		// Pointer content.
		$content = '<h3>' . __( 'Get Optimized', 'wp-smushit' ) . '</h3>';
		$content .= '<p>' . __( 'Resize, compress and optimize your images here.', 'wp-smushit' ) . '</p>';
		?>

		<script type="text/javascript">
			//<![CDATA[
			jQuery( document ).ready( function( $ ) {
				// jQuery selector to point the message to.
				$( '#toplevel_page_smush' ).pointer({
					content: '<?php echo $content; ?>',
					position: {
						edge: 'left',
						align: 'center'
					},
					close: function() {
						$.post( ajaxurl, {
							pointer: 'smush_pointer',
							action: 'dismiss-wp-pointer'
						});
					}
				}).pointer( 'open' );
			});
			//]]>
		</script>
		<?php
	}



	/**
	 * Add our filter to the media query filter in Media Library.
	 *
	 * @since 2.9.0
	 *
	 * @see wp_ajax_query_attachments()
	 *
	 * @param array $query
	 *
	 * @return mixed
	 */
	public function filter_media_query( $query ) {
		if ( isset( $_POST['query']['stats'] ) && 'null' === $_POST['query']['stats'] ) {
			$query['meta_query'] = array(
				array(
					'key'     => 'wp-smush-ignore-bulk',
					'value'   => 'true',
					'compare' => 'EXISTS',
				),
			);
		}

		return $query;
	}

}

global $wpsmushit_admin;
$wpsmushit_admin = new WpSmushitAdmin();

