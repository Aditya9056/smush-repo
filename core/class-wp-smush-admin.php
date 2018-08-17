<?php

/**
 * Class WP_Smush_Admin
 */
class WP_Smush_Admin {

	/**
	 * Plugin pages.
	 *
	 * @var array
	 */
	public $pages = array();

	/**
	 * AJAX module.
	 *
	 * @var WP_Smush_Ajax
	 */
	public $ajax;

	/**
	 * WP_Smush_Admin constructor.
	 */
	public function __construct() {
		$this->includes();

		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'network_admin_menu', array( $this, 'add_menu_pages' ) );

		add_action( 'admin_init', array( $this, 'smush_i18n' ) );
		// Add information to privacy policy page (only during creation).
		add_action( 'admin_init', array( $this, 'add_policy' ) );

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$this->ajax = new WP_Smush_Ajax();
		}

		add_filter( 'plugin_action_links_' . WP_SMUSH_BASENAME, array( $this, 'settings_link' ) );
		add_filter( 'network_admin_plugin_action_links_' . WP_SMUSH_BASENAME, array( $this, 'settings_link' ) );

		// Admin pointer for new Smush installation.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_pointer' ) );

		/**
		 * Prints a membership validation issue notice in Media Library
		 */
		add_action( 'admin_notices', array( $this, 'media_library_membership_notice' ) );

		// Add Smush Columns.
		add_filter( 'manage_media_columns', array( $this, 'columns' ) );
		add_action( 'manage_media_custom_column', array( $this, 'custom_column' ), 10, 2 );
		add_filter( 'manage_upload_sortable_columns', array( $this, 'sortable_column' ) );

		// Manage column sorting.
		add_action( 'pre_get_posts', array( $this, 'smushit_orderby' ) );

		// Smush image filter from Media Library.
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_media_query' ) );

		// Load js and css on pages with Media Uploader - WP Enqueue Media.
		//add_action( 'wp_enqueue_media', array( 'WP_Smush_View', 'enqueue_scripts' ) );
	}

	/**
	 * Load translation files.
	 */
	public function smush_i18n() {
		load_plugin_textdomain(
			'wp-smushit',
			false,
			dirname( WP_SMUSH_BASENAME ) . '/languages'
		);
	}

	/**
	 * Adds a Smush pro settings link on plugin page.
	 *
	 * @param array $links        Current links.
	 * @param bool  $url_only     Get only URL.
	 * @param bool  $networkwide  Do we need the network wide setting url.
	 *
	 * @return array|string
	 */
	public function settings_link( $links, $url_only = false, $networkwide = false ) {
		$settings_page = is_multisite() && is_network_admin() ? network_admin_url( 'admin.php?page=smush' ) : menu_page_url( 'smush', false );
		// If networkwide setting url is needed.
		$settings_page = $url_only && $networkwide && is_multisite() ? network_admin_url( 'admin.php?page=smush' ) : $settings_page;
		$settings      = '<a href="' . $settings_page . '">' . __( 'Settings', 'wp-smushit' ) . '</a>';

		// Return only settings page link.
		if ( $url_only ) {
			return $settings_page;
		}

		// Added a fix for weird warning in multisite, "array_unshift() expects parameter 1 to be array, null given".
		if ( ! empty( $links ) ) {
			array_unshift( $links, $settings );
		} else {
			$links = array( $settings );
		}

		return $links;
	}

	/**
	 * Includes for plugin pages.
	 */
	private function includes() {
		/* @noinspection PhpIncludeInspection */
		include_once WP_SMUSH_DIR . 'app/abstract-wp-smush-view.php';
		/* @noinspection PhpIncludeInspection */
		include_once WP_SMUSH_DIR . 'app/class-wp-smush-dashboard.php';
	}

	/**
	 * Add menu pages.
	 */
	public function add_menu_pages() {
		$title = WP_Smush::is_pro() ? esc_html__( 'Smush Pro', 'wp-smushit' ) : esc_html__( 'Smush', 'wp-smushit' );

		$this->pages['smush'] = new WP_Smush_Dashboard( $title );
	}

	/**
	 * Add Smush Policy to "Privace Policy" page during creation.
	 *
	 * @since 2.3.0
	 */
	public function add_policy() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content  = '<h3>' . __( 'Plugin: Smush', 'wp-smushit' ) . '</h3>';
		$content .=
			'<p>' . __( 'Note: Smush does not interact with end users on your website. The only input option Smush has is to a newsletter subscription for site admins only. If you would like to notify your users of this in your privacy policy, you can use the information below.', 'wp-smushit' ) . '</p>';
		$content .=
			'<p>' . __( 'Smush sends images to the WPMU DEV servers to optimize them for web use. This includes the transfer of EXIF data. The EXIF data will either be stripped or returned as it is. It is not stored on the WPMU DEV servers.', 'wp-smushit' ) . '</p>';
		$content .=
			'<p>' . sprintf(
				__( "Smush uses the Stackpath Content Delivery Network (CDN). Stackpath may store web log information of site visitors, including IPs, UA, referrer, Location and ISP info of site visitors for 7 days. Files and images served by the CDN may be stored and served from countries other than your own. Stackpath's privacy policy can be found %1\$shere%2\$s.", 'wp-smushit' ),
				'<a href="https://www.stackpath.com/legal/privacy-statement/" target="_blank">',
				'</a>'
			) . '</p>';

		if ( strpos( WP_SMUSH_DIR, 'wp-smushit' ) !== false ) {
			// Only for wordpress.org members.
			$content .=
				'<p>' . __( 'Smush uses a third-party email service (Drip) to send informational emails to the site administrator. The administrator\'s email address is sent to Drip and a cookie is set by the service. Only administrator information is collected by Drip.', 'wp-smushit' ) . '</p>';
		}

		wp_add_privacy_policy_content(
			__( 'WP Smush', 'wp-smushit' ),
			wp_kses_post( wpautop( $content, false ) )
		);
	}

	/**
	 * Prints the Membership Validation issue notice
	 */
	public function media_library_membership_notice() {
		// No need to print it for free version.
		if ( ! WP_Smush::is_pro() ) {
			return;
		}

		// Show it on Media Library page only.
		$screen    = get_current_screen();
		$screen_id = ! empty( $screen ) ? $screen->id : '';
		// Do not show notice anywhere else.
		if ( empty( $screen ) || 'upload' !== $screen_id ) {
			return;
		}

		$this->get_user_validation_message( false );
	}

	/**
	 * Get membership validation message.
	 *
	 * @param bool $notice Is a notice.
	 */
	public function get_user_validation_message( $notice = true ) {
		$notice_class = $notice ? ' sui-notice sui-notice-warning' : ' notice notice-warning is-dismissible';
		$wpmu_contact = sprintf( '<a href="%s" target="_blank">', esc_url( 'https://premium.wpmudev.org/contact' ) );
		$recheck_link = '<a href="#" id="wp-smush-revalidate-member" data-message="%s">';
		?>

		<div id="wp-smush-invalid-member" data-message="<?php esc_attr_e( 'Validating..', 'wp-smushit' ); ?>" class="sui-hidden hidden <?php echo esc_attr( $notice_class ); ?>">
			<p>
				<?php
				printf(
					/* translators: $1$s: recheck link, $2$s: closing a tag, %3$s; contact link, %4$s: closing a tag */
					esc_html__( 'It looks like Smush couldn’t verify your WPMU DEV membership so Pro features
					have been disabled for now. If you think this is an error, run a %1$sre-check%2$s or get in touch
					with our %3$ssupport team%4$s.', 'wp-smushit' ),
					$recheck_link, '</a>', $wpmu_contact, '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Register smush custom pointer to wp-pointer.
	 *
	 * Use WordPress dismiss-wp-pointer action on pointer dismissal to store dismissal flag in meta via ajax.
	 *
	 * @since 2.9
	 */
	public function register_admin_pointer() {
		// Pointer content.
		$content  = '<h3>' . __( 'Get Optimized', 'wp-smushit' ) . '</h3>';
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

		// Explode them by comma.
		$dismissed_pointers = explode( ',', (string) $dismissed_pointers );

		// If smush pointer is not found in dismissed pointers, show.
		if ( in_array( 'smush_pointer', $dismissed_pointers, true ) ) {
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
	 * Print column header for Smush results in the media library using the `manage_media_columns` hook.
	 *
	 * @param array $defaults  Defaults array.
	 *
	 * @return mixed
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
	 * @return mixed
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
		if ( 'smushit' == $column_name ) {
			WP_Smush::get_instance()->core()->mod->smush->set_status( $id );
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
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( isset( $orderby ) && 'smushit' === $orderby ) {
			$query->set(
				'meta_query', array(
					'relation' => 'OR',
					array(
						'key'     => $this->smushed_meta_key,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => $this->smushed_meta_key,
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
					'key'     => 'wp-smush-ignore-bulk',
					'value'   => 'true',
					'compare' => 'EXISTS',
				),
			);
		}

		return $query;
	}

}
