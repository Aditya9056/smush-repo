<?php
/**
 * Smush integration with Gutenberg editor: WP_Smush_Gutenberg class
 *
 * @package WP_Smush
 * @subpackage Admin/Integrations
 * @since 2.8.1
 *
 * @author Anton Vanyukov <anton@incsub.com>
 *
 * @copyright (c) 2018, Incsub (http://incsub.com)
 */

/**
 * Class WP_Smush_Gutenberg for Gutenberg integration.
 *
 * @since 2.8.1
 */
class WP_Smush_Gutenberg extends WP_Smush_Integration {

	/**
	 * WP_Smush_Gutenberg constructor.
	 *
	 * @since 2.8.1
	 */
	public function __construct() {
		$this->module   = 'gutenberg';
		$this->class    = 'free';
		$this->priority = 3;
		$this->enabled  = is_plugin_active( 'gutenberg/gutenberg.php' );

		parent::__construct();

		// Add beta tag.
		add_action( 'smush_setting_column_tag', array( $this, 'add_beta_tag' ) );

		if ( ! $this->enabled ) {
			// Disable setting if Gutenberg is not active.
			add_filter( 'wp_smush_integration_status_' . $this->module, '__return_true' );

			// Hook at the end of setting row to output an error div.
			add_action( 'smush_setting_column_right_inside', array( $this, 'integration_error' ) );

			return;
		}

		// Show submit button when Gutenberg is active.
		add_filter( 'wp_smush_integration_show_submit', '__return_true' );

		// Register gutenberg block assets.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_gb' ) );
	}

	/**************************************
	 *
	 * OVERWRITE PARENT CLASS FUNCTIONALITY
	 */

	/**
	 * Filters the setting variable to add Gutenberg setting title and description.
	 *
	 * @since 2.8.1
	 *
	 * @param array $settings  Settings array.
	 *
	 * @return mixed
	 */
	public function register( $settings ) {
		$settings[ $this->module ] = array(
			'label'       => esc_html__( 'Show Smush stats in Gutenberg blocks', 'wp-smushit' ),
			'short_label' => esc_html__( 'Gutenberg Support', 'wp-smushit' ),
			'desc'        => esc_html__(
				'Add statistics and the manual smush button to Gutenberg blocks that
							display images.', 'wp-smushit'
			),
		);

		return $settings;
	}

	/**************************************
	 *
	 * PUBLIC CLASSES
	 */

	/**
	 * Add a beta tag next to the setting title.
	 *
	 * @param string $setting_key  Setting key name.
	 *
	 * @since 2.9.0
	 */
	public function add_beta_tag( $setting_key ) {
		// Return if not Gutenberg integration.
		if ( $this->module !== $setting_key ) {
			return;
		}

		$tooltip_text = __( 'This feature is likely to work without issue, however Gutenberg is in beta stage and some issues are still present.', 'wp-smushit' );
		?>
		<span class="sui-tag sui-tag-beta sui-tooltip sui-tooltip-constrained" data-tooltip="<?php echo esc_attr( $tooltip_text ); ?>">
			<?php esc_html_e( 'Beta', 'wp-smushit' ); ?>
		</span>
		<?php
	}

	/**
	 * Prints the message for Gutenberg setup.
	 *
	 * @since 2.8.1
	 *
	 * @param string $setting_key  Settings key.
	 */
	public function integration_error( $setting_key ) {
		// Return if not Gutenberg integration.
		if ( $this->module !== $setting_key ) {
			return;
		}

		?>
		<div class="sui-notice smush-notice-sm">
			<p><?php esc_html_e( 'To use this feature you need to install and activate the Gutenberg plugin.', 'wp-smushit' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Enqueue Gutenberg block assets for backend editor.
	 *
	 * `wp-blocks`: includes block type registration and related functions.
	 * `wp-element`: includes the WordPress Element abstraction for describing the structure of your blocks.
	 * `wp-i18n`: To internationalize the block's text.
	 *
	 * @since 2.8.1
	 */
	public function enqueue_gb() {
		$enabled = WP_Smush_Settings::$settings[ $this->module ];

		if ( ! $enabled ) {
			return;
		}

		// Gutenberg block scripts.
		wp_enqueue_script(
			'smush-gutenberg',
			WP_SMUSH_URL . 'app/assets/js/blocks.min.js',
			array( 'wp-blocks', 'wp-i18n', 'wp-element' ),
			WP_SMUSH_VERSION,
			true
		);
	}

}
