<?php
/**
 * Smush UI: WpSmushBulkUi class.
 *
 * @package WP_Smush
 * @subpackage Admin/UI
 * @version 1.0
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2016, Incsub (http://incsub.com)
 */

/**
 * Class WpSmushBulkUi
 *
 * Handle the UI part for the plugin.
 */
class WpSmushBulkUi {

	/**
	 * Settings group for resize options.
	 *
	 * @var array
	 */
	public $resize_group = array(
		'detection',
	);

	/**
	 * Settings group for full size image options.
	 *
	 * @var array
	 */
	public $full_size_group = array(
		'backup',
	);

	/**
	 * WpSmushBulkUi constructor.
	 */
	public function __construct() {
		add_action( 'smush_setting_column_right_inside', array( $this, 'settings_desc' ), 10, 2 );
		add_action( 'smush_setting_column_right_inside', array( $this, 'image_sizes' ), 15, 2 );
		add_action( 'smush_setting_column_right_inside', array( $this, 'resize_settings' ), 20, 2 );
		add_action( 'smush_setting_column_right_outside', array( $this, 'full_size_options' ), 20, 2 );
		add_action( 'smush_setting_column_right_outside', array( $this, 'detect_size_options' ), 25, 2 );
		add_action( 'smush_settings_ui_bottom', array( $this, 'pro_features_container' ) );
	}

	/**
	 * Prints the header section for a container as per the Shared UI
	 *
	 * @param string $heading      Box Heading.
	 * @param string $sub_heading  Any additional text to be shown by the side of Heading.
	 *
	 * @return string
	 */
	public function container_header( $heading = '', $sub_heading = '' ) {
		if ( empty( $heading ) ) {
			return '';
		} ?>

		<div class="sui-box-header">
			<h3 class="sui-box-title"><?php echo esc_html( $heading ); ?></h3>
			<?php if ( ! empty( $sub_heading ) ) : ?>
				<div class="sui-actions-right">
					<?php echo $sub_heading; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Prints the footer section for a container as per the Shared UI
	 *
	 * @param string $content      Footer content.
	 * @param string $sub_content  Any additional text to be shown by the side of footer.
	 *
	 * @return void
	 */
	public function container_footer( $content = '', $sub_content = '' ) {
		?>
		<div class="sui-box-footer">
			<?php echo $content; ?>
			<?php if ( ! empty( $sub_content ) ) : ?>
				<div class="sui-actions-right">
					<?php echo $sub_content; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * All the settings for basic and advanced users.
	 *
	 * @return void
	 */
	public function settings_container() {
		// Class for box.
		$class = WP_Smush::is_pro() ? 'smush-settings-wrapper wp-smush-pro' : 'smush-settings-wrapper';

		echo '<div class="sui-box ' . $class . '" id="wp-smush-settings-box">';

		// Container header.
		$this->container_header( esc_html__( 'Settings', 'wp-smushit' ) );

		echo '<div class="sui-box-body">';

		// Load settings content.
		$this->options_ui();

		// Close box body.
		echo '</div>';

		// Footer content including buttons.
		$div_end = '<span class="wp-smush-submit-wrap">
			<input type="submit" id="wp-smush-save-settings" class="sui-button sui-button-primary" aria-describedby="smush-submit-description" value="' . esc_html__( 'UPDATE SETTINGS', 'wp-smushit' ) . '">
			<span class="sui-icon-loader sui-loading sui-hidden"></span>
			<span class="smush-submit-note" id="smush-submit-description">' . esc_html__( 'Smush will automatically check for any images that need re-smushing.', 'wp-smushit' ) . '</span>
			</span>';

		// Container footer.
		$this->container_footer( '', $div_end );

		// Close settings container.
		echo '</div>';

		/**
		 * Action hook to add extra containers after settings.
		 */
		do_action( 'smush_settings_ui_bottom' );
	}

	/**
	 * Integration settings for Smush.
	 *
	 * All integrations suhc as S3, NextGen can be added to this container.
	 *
	 * @return void
	 */
	public function integrations_ui() {
		// If no integration settings found, bail.
		if ( empty( $this->intgration_group ) ) {
			return;
		}

		$is_pro = WP_Smush::is_pro();

		// Container box class.
		$class = $is_pro ? 'smush-integrations-wrapper wp-smush-pro' : 'smush-integrations-wrapper';

		echo '<div class="sui-box ' . esc_attr( $class ) . '" id="wp-smush-integrations-box">';

		// Container header.
		$this->container_header( esc_html__( 'Integrations', 'wp-smushit' ) );

		// Box body class.
		$box_body_class = $is_pro ? 'sui-box-body' : 'sui-box-body sui-upsell-items';
		echo '<div class="' . esc_attr( $box_body_class ) . '">';

		// Integration settings content.
		$this->integrations_settings();

		if ( ! $is_pro ) {
			$this->integrations_upsell();
		}

		echo '</div>';

		/**
		 * Filter to enable/disable submit button in integration settings.
		 *
		 * @param bool $show_submit Should show submit?
		 */
		$show_submit = apply_filters( 'wp_smush_integration_show_submit', false );

		// Box footer content including buttons.
		$div_end = '<span class="wp-smush-submit-wrap">
			<input type="submit" id="wp-smush-save-settings" class="sui-button sui-button-primary" aria-describedby="smush-submit-description" value="' . esc_html__( 'UPDATE SETTINGS', 'wp-smushit' ) . '" ' . disabled( ! $show_submit, true, false ) . '>
			<span class="sui-icon-loader sui-loading sui-hidden"></span>
			<span class="smush-submit-note" id="smush-submit-description">' . esc_html__( 'Smush will automatically check for any images that need re-smushing.', 'wp-smushit' ) . '</span>
			</span>';

		// Container footer if pro.
		if ( $show_submit ) {
			$this->container_footer( '', $div_end );
		}
		echo '</div>';

		/**
		 * Action hook to add extra containers after integration settings.
		 */
		do_action( 'smush_integrations_ui_bottom' );
	}

	/**
	 * Process and display the settings.
	 *
	 * Free and pro version settings are shown in same section. For free users, pro settings won't be shown.
	 * To print full size smush, resize and backup in group, we hook at `smush_setting_column_right_end`.
	 *
	 * @return void
	 */
	public function options_ui() {
		// Get all grouped settings that can be skipped.
		$grouped_settings = array_merge( $this->resize_group, $this->full_size_group, $this->intgration_group );

		// Get settings values.
		$settings = empty( WP_Smush_Settings::$settings ) ? WP_Smush_Settings::init_settings() : WP_Smush_Settings::$settings;
		?>

		<!-- Start settings form -->
		<form id="wp-smush-settings-form" method="post">

		<input type="hidden" name="setting_form" id="setting_form" value="bulk">

		<?php $opt_networkwide = WP_SMUSH_PREFIX . 'networkwide'; ?>
		<?php $opt_networkwide_val = WP_Smush_Settings::$settings['networkwide']; ?>

		<?php if ( is_multisite() && is_network_admin() ) : ?>

			<?php $class = WP_Smush_Settings::$settings['networkwide'] ? '' : ' sui-hidden'; ?>

		<div class="sui-box-settings-row wp-smush-basic">
			<div class="sui-box-settings-col-1">
				<label for="<?php echo $opt_networkwide; ?>" aria-hidden="true">
					<span class="sui-settings-label"><?php echo $wpsmushit_admin->settings['networkwide']['short_label']; ?></span>
					<span class="sui-description"><?php echo $wpsmushit_admin->settings['networkwide']['desc']; ?></span>
				</label>
			</div>
			<div class="sui-box-settings-col-2">
				<label class="sui-toggle">
					<input type="checkbox" id="<?php echo $opt_networkwide; ?>" name="<?php echo $opt_networkwide; ?>" <?php checked( $opt_networkwide_val, 1, true ); ?> value="1">
					<span class="sui-toggle-slider"></span>
					<label class="toggle-label" for="<?php echo $opt_networkwide; ?>" aria-hidden="true"></label>
				</label>
				<label for="<?php echo $opt_networkwide; ?>">
					<?php echo $wpsmushit_admin->settings['networkwide']['label']; ?>
				</label>
			</div>
		</div>
		<input type="hidden" name="setting-type" value="network">
		<div class="network-settings-wrapper<?php echo $class; ?>">
			<?php
			endif;
			if ( ! is_multisite() || ( ! WP_Smush_Settings::$settings['networkwide'] && ! is_network_admin() ) || is_network_admin() ) {
				foreach ( $wpsmushit_admin->settings as $name => $values ) {
					// Skip networkwide settings, we already printed it.
					if ( 'networkwide' == $name ) {
						continue;
					}

					// Skip premium features if not a member.
					if ( ! in_array( $name, $wpsmushit_admin->basic_features ) && ! WP_Smush::is_pro() ) {
						continue;
					}

					$setting_m_key = WP_SMUSH_PREFIX . $name;
					$setting_val   = empty( $settings[ $name ] ) ? 0 : $settings[ $name ];

					// Set the default value 1 for auto smush.
					if ( 'auto' == $name && ( false === $setting_val || ! isset( $setting_val ) ) ) {
						$setting_val = 1;
					}

					// Group Original, Resize and Backup for pro users
					if ( in_array( $name, $grouped_settings ) ) {
						continue;
					}

					$label = ! empty( $wpsmushit_admin->settings[ $name ]['short_label'] ) ? $wpsmushit_admin->settings[ $name ]['short_label'] : $wpsmushit_admin->settings[ $name ]['label'];

					// Show settings option.
					$this->settings_row( $setting_m_key, $label, $name, $setting_val );
				}
				// Hook after general settings.
				do_action( 'wp_smush_after_basic_settings' );
			}
			if ( is_multisite() && is_network_admin() ) {
				echo '</div>';
			}
			?>
		</form>
		<?php
	}

	/**
	 * Process and display the integration settings.
	 *
	 * Free and pro version settings are shown in same section. For free users, pro settings won't be shown.
	 * To print full size smush, resize and backup in group, we hook at `smush_setting_column_right_end`.
	 *
	 * @return void
	 */
	public function integrations_settings() {
		// Get settings values.
		$settings = empty( WP_Smush_Settings::$settings ) ? WP_Smush_Settings::init_settings() : WP_Smush_Settings::$settings;
		?>

		<!-- Start integration form -->
		<form id="wp-smush-settings-form" method="post">

			<input type="hidden" name="setting_form" id="setting_form" value="integration">
			<?php if ( is_multisite() && is_network_admin() ) : ?>
				<input type="hidden" name="wp-smush-networkwide" id="wp-smush-networkwide" value="1">
				<input type="hidden" name="setting-type" value="network">
				<?php
			endif;

			wp_nonce_field( 'save_wp_smush_options', 'wp_smush_options_nonce', '', true );

			// For subsite admins show only if networkwide options is not enabled.
			if ( ! is_multisite() || ( ! WP_Smush_Settings::$settings['networkwide'] && ! is_network_admin() ) || is_network_admin() ) {
				foreach ( $this->intgration_group as $name ) {
					// Settings key.
					$setting_m_key = WP_SMUSH_PREFIX . $name;
					// Disable setting.
					$disable = apply_filters( 'wp_smush_integration_status_' . $name, false );
					// Gray out row, disable setting.
					$upsell = ( ! in_array( $name, $wpsmushit_admin->basic_features ) && ! WP_Smush::is_pro() );
					// Current setting value.
					$setting_val = ( $upsell || empty( $settings[ $name ] ) || $disable ) ? 0 : $settings[ $name ];
					// Current setting label.
					$label = ! empty( $wpsmushit_admin->settings[ $name ]['short_label'] ) ? $wpsmushit_admin->settings[ $name ]['short_label'] : $wpsmushit_admin->settings[ $name ]['label'];

					// Show settings option.
					$this->settings_row( $setting_m_key, $label, $name, $setting_val, true, $disable, $upsell );

				}
				// Hook after showing integration settings.
				do_action( 'wp_smush_after_integration_settings' );
			}
			?>
		</form>
		<?php
	}

	/**
	 * Show upsell notice.
	 *
	 * @since 2.8.0
	 */
	public function integrations_upsell() {
		// Upgrade url for upsell.
		$upsell_url = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'smush-nextgen-settings-upsell',
			), $wpsmushit_admin->upgrade_url
		);
		?>
		<div class="sui-box-settings-row sui-upsell-row">
			<img class="sui-image sui-upsell-image sui-upsell-image-smush integrations-upsell-image" src="<?php echo esc_url( WP_SMUSH_URL . 'app/assets/images/smush-promo.png' ); ?>">
			<div class="sui-upsell-notice">
				<p>
					<?php
					printf(
						/* translators: %1$s - a href tag, %2$s - a href closing tag */
						esc_html__( 'Smush Pro supports hosting images on Amazon S3 and optimizing NextGen Gallery images directly through NextGen Gallery settings. %1$sTry it free%2$s with a WPMU DEV membership today!', 'wp-smushit' ),
						'<a href="' . esc_url( $upsell_url ) . '" target="_blank" title="' . esc_html__( 'Try Smush Pro for FREE', 'wp-smushit' ) . '">',
						'</a>'
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Single settings row html content.
	 *
	 * @param string $setting_m_key  Setting key.
	 * @param string $label          Setting label.
	 * @param string $name           Setting name.
	 * @param mixed  $setting_val    Setting value.
	 * @param bool   $skip_group     Skip group settings.
	 * @param bool   $disable        Disable the setting.
	 * @param bool   $upsell         Gray out row to show upsell.
	 *
	 * @return void
	 */
	public function settings_row( $setting_m_key, $label, $name, $setting_val, $skip_group = false, $disable = false, $upsell = false ) {
		// Get all grouped settings that can be skipped.
		$grouped_settings = array_merge( $this->resize_group, $this->full_size_group, $this->intgration_group );

		?>
		<div class="sui-box-settings-row wp-smush-basic <?php echo $upsell ? 'sui-disabled' : ''; ?>">
			<div class="sui-box-settings-col-1">
				<span class="sui-settings-label">
					<?php echo $label; ?>
					<?php if ( 'gutenberg' === $name ) : ?>
						<span class="sui-tag sui-tag-beta sui-tooltip sui-tooltip-constrained"
							  data-tooltip="<?php esc_attr_e( 'This feature is likely to work without issue, however Gutenberg is in beta stage and some issues are still present.', 'wp-smushit' ); ?>"
						>
							<?php esc_html_e( 'Beta', 'wp-smushit' ); ?>
						</span>
					<?php endif; ?>
				</span>

				<span class="sui-description">
					<?php echo $wpsmushit_admin->settings[ $name ]['desc']; ?>
				</span>
			</div>
			<div class="sui-box-settings-col-2" id="column-<?php echo $setting_m_key; ?>">
				<?php if ( ! in_array( $name, $grouped_settings ) || $skip_group ) : ?>
					<div class="sui-form-field">
						<label class="sui-toggle">
							<input type="checkbox" aria-describedby="<?php echo $setting_m_key . '-desc'; ?>" id="<?php echo $setting_m_key; ?>" name="<?php echo $setting_m_key; ?>" <?php checked( $setting_val, 1, true ); ?> value="1" <?php disabled( $disable ); ?>>
							<span class="sui-toggle-slider"></span>
						</label>
						<label for="<?php echo $setting_m_key; ?>">
							<?php echo $wpsmushit_admin->settings[ $name ]['label']; ?>
						</label>
						<!-- Print/Perform action in right setting column -->
						<?php do_action( 'smush_setting_column_right_inside', $name ); ?>
					</div>
				<?php endif; ?>
				<!-- Print/Perform action in right setting column -->
				<?php do_action( 'smush_setting_column_right_outside', $name ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Pro features list box to show after settings.
	 *
	 * @return void
	 */
	public function pro_features_container() {
		// Do not show if pro user.
		if ( WP_Smush::is_pro() || ( is_network_admin() && ! WP_Smush_Settings::$settings['networkwide'] ) ) {
			return;
		}

		// Upgrade url with analytics keys.
		$upgrade_url = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'smush_advancedsettings_profeature_tag',
			),
			$wpsmushit_admin->upgrade_url
		);

		// Upgrade url for upsell.
		$upsell_url = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'smush-advanced-settings-upsell',
			),
			$wpsmushit_admin->upgrade_url
		);

		?>

		<div class="sui-box">
			<div class="sui-box-header">
				<h3 class="sui-box-title"><?php esc_html_e( 'Pro Features', 'wp-smushit' ); ?></h3>
				<div class="sui-actions-right">
					<a class="sui-button sui-button-green sui-tooltip" target="_blank" href="<?php echo esc_url( $upgrade_url ); ?>" data-tooltip="<?php _e( 'Join WPMU DEV to try Smush Pro for free.', 'wp-smushit' ); ?>"><?php _e( 'UPGRADE TO PRO', 'wp-smushit' ); ?></a>
				</div>
			</div>
			<div class="sui-box-body">
				<ul class="smush-pro-features">
					<li class="smush-pro-feature-row">
						<div class="smush-pro-feature-title">
							<?php esc_html_e( 'Super-smush lossy compression', 'wp-smushit' ); ?></div>
						<div class="smush-pro-feature-desc"><?php esc_html_e( 'Optimize images 2x more than regular smushing and with no visible loss in quality using Smush’s intelligent multi-pass lossy compression.', 'wp-smushit' ); ?></div>
					</li>
					<li class="smush-pro-feature-row">
						<div class="smush-pro-feature-title">
							<?php esc_html_e( 'Smush my original full size images', 'wp-smushit' ); ?></div>
						<div class="smush-pro-feature-desc"><?php esc_html_e( 'By default, Smush only compresses thumbnails and image sizes generated by WordPress. With Smush Pro you can also smush your original images.', 'wp-smushit' ); ?></div>
					</li>
					<li class="smush-pro-feature-row">
						<div class="smush-pro-feature-title">
							<?php esc_html_e( 'Make a copy of my full size images', 'wp-smushit' ); ?></div>
						<div class="smush-pro-feature-desc"><?php esc_html_e( 'Save copies the original full-size images you upload to your site so you can restore them at any point. Note: Activating this setting will double the size of the uploads folder where your site’s images are stored.', 'wp-smushit' ); ?></div>
					</li>
					<li class="smush-pro-feature-row">
						<div class="smush-pro-feature-title">
							<?php esc_html_e( 'Auto-convert PNGs to JPEGs (lossy)', 'wp-smushit' ); ?></div>
						<div class="smush-pro-feature-desc"><?php esc_html_e( 'When you compress a PNG, Smush will check if converting it to JPEG could further reduce its size, and do so if necessary,', 'wp-smushit' ); ?></div>
					</li>
					<li class="smush-pro-feature-row">
						<div class="smush-pro-feature-title">
							<?php esc_html_e( 'NextGen Gallery Integration', 'wp-smushit' ); ?></div>
						<div class="smush-pro-feature-desc"><?php esc_html_e( 'Allow smushing images directly through NextGen Gallery settings.', 'wp-smushit' ); ?></div>
					</li>
				</ul>
				<div class="sui-upsell-row">
					<img class="sui-image sui-upsell-image sui-upsell-image-smush" src="<?php echo WP_SMUSH_URL . 'app/assets/images/smush-promo.png'; ?>">
					<div class="sui-upsell-notice">
						<p><?php printf( esc_html__( 'Smush Pro gives you all these extra settings and absolutely not limits on smushing your images? Did we mention Smush Pro also gives you up to 2x better compression too? %1$sTry it all free%2$s with a WPMU DEV membership today!', 'wp-smushit' ), '<a href="' . esc_url( $upsell_url ) . '" target="_blank" title="' . esc_html__( 'Try Smush Pro for FREE', 'wp-smushit' ) . '">', '</a>' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<?php

	}

	/**
	 * Prints Resize, Smush Original, and Backup settings.
	 *
	 * @param string $name Name of the current setting being processed
	 * @param string $section Section name.
	 *
	 * @return void
	 */
	public function full_size_options( $name = '' ) {
		// Continue only if orginal image option.
		if ( 'original' !== $name || ! WP_Smush::is_pro() ) {
			return;
		}

		foreach ( $this->full_size_group as $name ) {
			$setting_val = WP_Smush_Settings::$settings[ $name ];
			$setting_key = WP_SMUSH_PREFIX . $name;
			?>
			<div class="sui-form-field">
				<label class="sui-toggle">
					<input type="checkbox" aria-describedby="<?php echo $setting_key; ?>-desc" id="<?php echo $setting_key; ?>" name="<?php echo $setting_key; ?>" <?php checked( $setting_val, 1 ); ?> value="1">
					<span class="sui-toggle-slider"></span>
					<label class="toggle-label <?php echo $setting_key . '-label'; ?>" for="<?php echo $setting_key; ?>" aria-hidden="true"></label>
				</label>
				<label for="<?php echo $setting_key; ?>">
					<?php echo $wpsmushit_admin->settings[ $name ]['label']; ?>
				</label>
				<span class="sui-description sui-toggle-description"><?php echo $wpsmushit_admin->settings[ $name ]['desc']; ?></span>

			</div>
			<?php
		}
	}

	/**
	 * Prints front end image size detection option.
	 *
	 * @param string $name Name of the current setting being processed
	 * @param string $section Section name.
	 *
	 * @return void
	 */
	public function detect_size_options( $name ) {
		// Only add to resize setting.
		if ( 'resize' !== $name ) {
			return;
		}

		foreach ( $this->resize_group as $name ) {
			// Do not continue if setting is not found.
			if ( ! isset( WP_Smush_Settings::$settings[ $name ] ) ) {
				continue;
			}

			$setting_val = WP_Smush_Settings::$settings[ $name ];
			$setting_key = WP_SMUSH_PREFIX . $name;
			?>
			<div class="sui-form-field">
				<label class="sui-toggle">
					<input type="checkbox" aria-describedby="<?php echo $setting_key; ?>-desc" id="<?php echo $setting_key; ?>" name="<?php echo $setting_key; ?>" <?php checked( $setting_val, 1, true ); ?> value="1">
					<span class="sui-toggle-slider"></span>
					<label class="toggle-label <?php echo $setting_key . '-label'; ?>" for="<?php echo $setting_key; ?>" aria-hidden="true"></label>
				</label>
				<label for="<?php echo $setting_key; ?>">
					<?php echo $wpsmushit_admin->settings[ $name ]['label']; ?>
				</label>
				<span class="sui-description sui-toggle-description">
					<?php echo $wpsmushit_admin->settings[ $name ]['desc']; ?>
					<?php if ( 'detection' === $name ) : ?>
						<div class="sui-notice sui-notice-info smush-notice-sm smush-highlighting-notice <?php echo $setting_val === 1 ? '' : 'sui-hidden'; ?>">
							<p>
								<?php printf( esc_html__( 'Highlighting is active. %1$sView homepage%2$s.', 'wp-smushit' ), '<a href="' . home_url() . '" target="_blank">', '</a>' ); ?>
							</p>
						</div>
					<?php endif; ?>
				</span>
			</div>
			<?php
		}
	}



	/**
	 * Show additional descriptions for settings.
	 *
	 * @param string $setting_key Setting key.
	 *
	 * @return void
	 */
	public function settings_desc( $setting_key = '' ) {

		if ( empty( $setting_key ) || ! in_array(
			$setting_key, array(
				'resize',
				'original',
				'strip_exif',
				'png_to_jpg',
				's3',
			)
		)
		) {
			return;
		}
		?>
		<span class="sui-description sui-toggle-description" id="<?php echo WP_SMUSH_PREFIX . $setting_key . '-desc'; ?>">
			<?php
			switch ( $setting_key ) {

				case 'resize':
					esc_html_e( 'Save a ton of space by not storing over-sized images on your server. Set a maximum height and width for all images uploaded to your site so that any unnecessarily large images are automatically scaled down to a reasonable size. Note: Image resizing happens automatically when you upload attachments. This setting does not apply to images smushed using Directory Smush feature. To support retina devices, we recommend using 2x the dimensions of your image size.', 'wp-smushit' );
					break;
				case 'original':
					esc_html_e( 'Every time you upload an image to your site, WordPress generates a resized version of that image for every image size that your theme has registered. This means there are multiple versions of your images in your media library. By default, Smush only compresses these generated image. Activate this setting to also smush your original images. Note: Activating this setting doesn’t usually improve page speed, unless your website uses the original images in full size.', 'wp-smushit' );
					break;
				case 'strip_exif':
					esc_html_e( 'Note: This data adds to the size of the image. While this information might be important to photographers, it’s unnecessary for most users and safe to remove.', 'wp-smushit' );
					break;
				case 'png_to_jpg':
					esc_html_e( 'Note: Any PNGs with transparency will be ignored. Smush will only convert PNGs if it results in a smaller file size. The resulting file will have a new filename and extension (JPEG), and any hard-coded URLs on your site that contain the original PNG filename will need to be updated.', 'wp-smushit' );
					break;
				case 's3':
					esc_html_e( 'Note: For this process to happen automatically you need automatic smushing enabled.', 'wp-smushit' );
					break;
				case 'default':
					break;
			}
			?>
		</span>
		<?php
	}



	/**
	 * Prints all the registererd image sizes, to be selected/unselected for smushing.
	 *
	 * @param string $name Setting key.
	 *
	 * @return void
	 */
	public function image_sizes( $name = '' ) {
		// Add only to auto smush settings.
		if ( 'auto' !== $name ) {
			return;
		}

		// Additional Image sizes.
		$image_sizes = WP_Smush_Settings::get_setting( WP_SMUSH_PREFIX . 'image_sizes', false );
		$sizes       = $wpsmushit_admin->image_dimensions();

		/**
		 * Add an additional item for full size.
		 * Do not use intermediate_image_sizes filter.
		 */
		$sizes['full'] = array();

		$is_pro   = WP_Smush::is_pro();
		$disabled = '';

		$setting_status = empty( WP_Smush_Settings::$settings['auto'] ) ? 0 : WP_Smush_Settings::$settings['auto'];

		if ( ! empty( $sizes ) ) {
			?>
			<!-- List of image sizes recognised by WP Smush -->
			<div class="wp-smush-image-size-list <?php echo $setting_status ? '' : ' sui-hidden'; ?>">
			<span class="sui-description"><?php esc_html_e( 'Every time you upload an image to your site, WordPress generates a resized version of that image for every default and/or custom image size that your theme has registered. This means there are multiple versions of your images in your media library. Choose the images size/s below that you would like optimized:', 'wp-smushit' ); ?></span>
				<?php
				foreach ( $sizes as $size_k => $size ) {
					// If image sizes array isn't set, mark all checked ( Default Values ).
					if ( false === $image_sizes ) {
						$checked = true;
					} else {
						$checked = is_array( $image_sizes ) ? in_array( $size_k, $image_sizes ) : false;
					}
					// For free users, disable full size option.
					if ( $size_k === 'full' ) {
						$disabled = $is_pro ? '' : 'disabled';
						$checked  = $is_pro ? $checked : false;
					}
					?>
				<label class="sui-checkbox sui-description">
				<input type="checkbox" id="wp-smush-size-<?php echo $size_k; ?>" <?php checked( $checked, true ); ?> name="wp-smush-image_sizes[]" value="<?php echo $size_k; ?>" <?php echo $disabled; ?>>
				<span aria-hidden="true"></span>
																<?php if ( isset( $size['width'], $size['height'] ) ) { ?>
					<span class="sui-description"><?php echo $size_k . ' (' . $size['width'] . 'x' . $size['height'] . ') '; ?></span>
				<?php } else { ?>
					<span class="sui-description"><?php echo $size_k; ?>
						<?php if ( ! $is_pro ) { ?>
							<span class="sui-tag sui-tag-pro sui-tooltip sui-tooltip-constrained" data-tooltip="<?php esc_html_e( 'Join WPMU DEV to unlock multi-pass lossy compression', 'wp-smushit' ); ?>"><?php esc_html_e( 'PRO', 'wp-smushit' ); ?></span>
						<?php } ?>
					</span>
				<?php } ?>
				</label>
					<?php
				}
				?>
			</div>
			<?php
		}
	}

	/**
	 * Content of the install/upgrade notice based on free or pro version.
	 *
	 * @return void
	 */
	public function installation_notice() {
		// Whether new/existing installation.
		$install_type = get_site_option( 'wp-smush-install-type', false );

		if ( ! $install_type ) {
			$install_type = $wpsmushit_admin->smushed_count > 0 ? 'existing' : 'new';
			update_site_option( 'wp-smush-install-type', $install_type );
		}

		// Prepare notice.
		if ( 'new' === $install_type ) {
			$notice_heading = esc_html__( 'Thanks for installing Smush. We hope you like it!', 'wp-smushit' );
			$notice_content = esc_html__( 'And hey, if you do, you can join WPMU DEV for a free 30 day trial and get access to even more features!', 'wp-smushit' );
			$button_content = esc_html__( 'Try Smush Pro Free', 'wp-smushit' );
		} else {
			$notice_heading = esc_html__( 'Thanks for upgrading Smush!', 'wp-smushit' );
			$notice_content = esc_html__( 'Did you know she has secret super powers? Yes, she can super-smush images for double the savings, store original images, and bulk smush thousands of images in one go. Get started with a free WPMU DEV trial to access these advanced features.', 'wp-smushit' );
			$button_content = esc_html__( 'Try Smush Pro Free', 'wp-smushit' );
		}

		$upgrade_url = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'smush_dashboard_upgrade_notice',
			),
			$wpsmushit_admin->upgrade_url
		);
		?>
		<div class="notice smush-notice" style="display: none;">
			<div class="smush-notice-logo"><span></span></div>
			<div class="smush-notice-message<?php echo 'new' === $install_type ? ' wp-smush-fresh' : ' wp-smush-existing'; ?>">
				<strong><?php echo $notice_heading; ?></strong>
				<?php echo $notice_content; ?>
			</div>
			<div class="smush-notice-cta">
				<a href="<?php echo esc_url( $upgrade_url ); ?>" class="smush-notice-act button-primary" target="_blank">
					<?php echo $button_content; ?>
				</a>
				<button class="smush-notice-dismiss smush-dismiss-welcome" data-msg="<?php esc_html_e( 'Saving', 'wp-smushit' ); ?>"><?php esc_html_e( 'Dismiss', 'wp-smushit' ); ?></button>
			</div>
		</div>
		<?php
	}

}