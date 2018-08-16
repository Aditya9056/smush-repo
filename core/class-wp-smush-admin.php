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

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$this->ajax = new WP_Smush_Ajax();
		}

		add_filter( 'plugin_action_links_' . WP_SMUSH_BASENAME, array( $this, 'settings_link' ) );
		add_filter( 'network_admin_plugin_action_links_' . WP_SMUSH_BASENAME, array( $this, 'settings_link' ) );

		// Admin pointer for new Smush installation.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_pointer' ) );

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

}
