<?php

class WP_Smush_Admin {

	/**
	 * Plugin pages.
	 *
	 * @var array
	 */
	public $pages = array();

	/**
	 * WP_Smush_Admin constructor.
	 */
	public function __construct() {
		$this->includes();

		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'network_admin_menu', array( $this, 'add_menu_pages' ) );

		add_action( 'admin_init', array( $this, 'smush_i18n' ) );

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			new WP_Smush_Ajax();
		}

		add_filter( 'plugin_action_links_' . WP_SMUSH_BASENAME, array( $this, 'settings_link' ) );
		add_filter( 'network_admin_plugin_action_links_' . WP_SMUSH_BASENAME, array( $this, 'settings_link' ) );
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

}
