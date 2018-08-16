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
		// Update the Super Smush count, after the smushing.
		add_action( 'wp_smush_image_optimised', array( $this, 'update_lists' ), '', 2 );


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

}
