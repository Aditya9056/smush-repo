<?php
/**
 * Dashboard page class: WP_Smush_Dashboard extends WP_Smush_View.
 *
 * @since 2.9.0
 * @package WP_Smush
 */

/**
 * Class WP_Smush_Dashboard
 */
class WP_Smush_Dashboard extends WP_Smush_View {

	/**
	 * Settings group for resize options.
	 *
	 * @var array
	 */
	private $resize_group = array(
		'detection',
	);

	/**
	 * Settings group for full size image options.
	 *
	 * @var array
	 */
	private $full_size_group = array(
		'backup',
	);

	/**
	 * Settings group for integration options.
	 *
	 * @var array
	 */
	private $integration_group = array();

	/**
	 * Register page action hooks
	 */
	public function add_action_hooks() {
		parent::add_action_hooks();

		add_action( 'smush_setting_column_right_inside', array( $this, 'settings_desc' ), 10, 2 );
		add_action( 'smush_setting_column_right_inside', array( $this, 'image_sizes' ), 15, 2 );
		add_action( 'smush_setting_column_right_inside', array( $this, 'resize_settings' ), 20, 2 );
		add_action( 'smush_setting_column_right_outside', array( $this, 'full_size_options' ), 20, 2 );
		add_action( 'smush_setting_column_right_outside', array( $this, 'detect_size_options' ), 25, 2 );

		// Add stats to stats box.
		add_action( 'stats_ui_after_resize_savings', array( $this, 'pro_savings_stats' ), 15 );
		add_action( 'stats_ui_after_resize_savings', array( $this, 'conversion_savings_stats' ), 15 );
		add_action( 'stats_ui_after_resize_savings', array( $this, 'directory_stats_ui' ), 10 );
	}

	/**
	 * Function triggered when the page is loaded before render any content.
	 */
	public function on_load() {
		// Hook into integration settings.
		$this->integration_group = apply_filters( 'wp_smush_integration_settings', array() );

		// If a free user, update the limits.
		if ( ! WP_Smush::is_pro() ) {
			// Reset transient.
			WP_Smush_Core::check_bulk_limit( true );
		}

		// Init the tabs.
		$this->tabs = apply_filters(
			'smush_setting_tabs', array(
				'bulk'         => __( 'Bulk Smush', 'wp-smushit' ),
				'directory'    => __( 'Directory Smush', 'wp-smushit' ),
				'integrations' => __( 'Integrations', 'wp-smushit' ),
				'cdn'          => __( 'CDN', 'wp-smushit' ),
			)
		);

		$networkwide = WP_Smush_Settings::$settings['networkwide'];

		// Tabs that can be shown in network admin networkwide (bulk, integrations, cdn).
		if ( is_multisite() && $networkwide && is_network_admin() ) {
			unset( $this->tabs['directory'] );
		}

		// Tabs that can be shown in subsites if networkwide (bulk and directory).
		if ( is_multisite() && $networkwide && ! is_network_admin() ) {
			unset( $this->tabs['integrations'] );
		}

		if ( empty( $this->integration_group ) ) {
			unset( $this->tabs['integrations'] );
		}

		// Icons in the submenu.
		add_filter( 'wp_smush_admin_after_tab_' . $this->get_slug(), array( $this, 'after_tab' ) );
	}

	/**
	 * Register meta boxes.
	 */
	public function register_meta_boxes() {
		$is_pro         = WP_Smush::is_pro();
		$is_network     = is_network_admin();
		$is_networkwide = WP_Smush_Settings::$settings['networkwide'];

		if ( ! $is_network ) {
			$this->add_meta_box( 'meta-boxes/summary',
				null,
				array( $this, 'dashboard_summary_metabox' ),
				null,
				null,
				'summary',
				array(
					'box_class'         => 'sui-box sui-summary sui-summary-smush',
					'box_content_class' => false,
				)
			);

			// If not a pro user.
			if ( ! $is_pro ) {
				/**
				 * Allows to hook in additional containers after stats box for free version
				 * Pro Version has a full width settings box, so we don't want to do it there.
				 */
				do_action( 'wp_smush_after_stats_box' );
			}
		}

		// Class for box.
		$settings_class = $is_pro ? 'smush-settings-wrapper wp-smush-pro' : 'smush-settings-wrapper';

		if ( $is_network && ! $is_networkwide ) {
			$this->add_meta_box( 'meta-boxes/settings',
				__( 'Settings', 'wp-smushit' ),
				array( $this, 'settings_metabox' ),
				null,
				null,
				'bulk',
				array(
					'box_class' => "sui-box {$settings_class}",
				)
			);

			return;
		}

		switch ( $this->get_current_tab() ) {
			case 'directory':
				$this->add_meta_box( 'meta-boxes/directory',
					__( 'Directory Smush', 'wp-smushit' ),
					array( $this, 'directory_smush_metabox' ),
					null,
					null,
					'directory'
				);

				break;

			case 'integrations':
				// Show integrations box.
				$class          = $is_pro ? 'smush-integrations-wrapper wp-smush-pro' : 'smush-integrations-wrapper';
				$box_body_class = $is_pro ? '' : 'sui-upsell-items';

				$this->add_meta_box( 'meta-boxes/integrations',
					__( 'Integrations', 'wp-smushit' ),
					array( $this, 'integrations_metabox' ),
					null,
					array( $this, 'integrations_metabox_footer' ),
					'integrations',
					array(
						'box_class'         => "sui-box {$class}",
						'box_content_class' => "sui-box-body {$box_body_class}",
					)
				);

				break;

			case 'bulk':
			default:
				// Show bulk smush box if a subsite admin.
				if ( ! $is_network ) {
					// Class for bulk smush box.
					$class = $is_pro ? 'bulk-smush-wrapper wp-smush-pro-install' : 'bulk-smush-wrapper';

					$this->add_meta_box( 'meta-boxes/bulk',
						__( 'Bulk Smush', 'wp-smushit' ),
						array( $this, 'bulk_smush_metabox' ),
						null,
						null,
						'bulk',
						array(
							'box_class' => "sui-box {$class}",
						)
					);
				}

				if ( $is_network || ! $is_networkwide ) {
					$this->add_meta_box( 'meta-boxes/settings',
						__( 'Settings', 'wp-smushit' ),
						array( $this, 'settings_metabox' ),
						null,
						null,
						'bulk',
						array(
							'box_class' => "sui-box {$settings_class}",
						)
					);
				}

				// Do not show if pro user.
				if ( ! $is_pro && ( ! is_network_admin() || WP_Smush_Settings::$settings['networkwide'] ) ) {
					$this->add_meta_box( 'meta-boxes/pro-features',
						__( 'Pre Features', 'wp-smushit' ),
						array( $this, 'pro_features_metabox' ),
						array( $this, 'pro_features_metabox_header' ),
						null,
						'bulk'
					);
				}

				break;

			case 'cdn':
				if ( ! $is_pro ) {
					$this->add_meta_box( 'meta-boxes/cdn-upsell',
						__( 'CDN', 'wp-smushit' ),
						array( $this, 'cdn_upsell_metabox' ),
						array( $this, 'cdn_upsell_metabox_header' ),
						null,
						'cdn'
					);
				} elseif ( 0 === WP_Smush_Settings::$settings['cdn'] ) {
					$this->add_meta_box( 'meta-boxes/cdn/disabled',
						__( 'CDN', 'wp-smushit' ),
						null,
						null,
						null,
						'cdn'
					);
				} else {
					$this->add_meta_box( 'meta-boxes/cdn',
						__( 'CDN', 'wp-smushit' ),
						array( $this, 'cdn_metabox' ),
						null,
						null,
						'cdn'
					);
				}

				break;
		}
	}

	/**
	 * Add remaining count to bulk smush tab.
	 *
	 * @param string $tab  Current tab.
	 */
	public function after_tab( $tab ) {
		if ( 'bulk' === $tab ) {
			$remaining = WP_Smush::get_instance()->core()->remaining_count;
			if ( 0 < $remaining ) {
				echo '<span class="sui-tag sui-tag-warning wp-smush-remaining-count">' . absint( $remaining ) . '</span>';
				return;
			}

			echo '<i class="sui-icon-check-tick sui-success" aria-hidden="true"></i>';
			return;
		}
	}

	/**
	 * Prints Dimensions required for Resizing
	 *
	 * @param string $name Setting name.
	 * @param string $class_prefix Custom class prefix.
	 */
	public function resize_settings( $name = '', $class_prefix = '' ) {
		// Add only to full size settings.
		if ( 'resize' !== $name ) {
			return;
		}

		// Dimensions.
		$resize_sizes = WP_Smush_Settings::get_setting(
			WP_SMUSH_PREFIX . 'resize_sizes', array(
				'width'  => '',
				'height' => '',
			)
		);

		// Set default prefix is custom prefix is empty.
		$prefix = empty( $class_prefix ) ? WP_SMUSH_PREFIX : $class_prefix;

		// Get max dimensions.
		$max_sizes = WP_Smush::get_instance()->core()->get_max_image_dimensions();

		$setting_status = empty( WP_Smush_Settings::$settings['resize'] ) ? 0 : WP_Smush_Settings::$settings['resize'];
		?>
		<div class="wp-smush-resize-settings-wrap<?php echo $setting_status ? '' : ' sui-hidden'; ?>">
			<div class="sui-row">
				<div class="sui-col">
					<label aria-labelledby="<?php echo esc_attr( $prefix ); ?>label-max-width" for="<?php echo esc_attr( $prefix ) . esc_attr( $name ) . '_width'; ?>" class="sui-label">
						<?php esc_html_e( 'Max width', 'wp-smushit' ); ?>
					</label>
					<input aria-required="true" type="number" class="sui-form-control wp-smush-resize-input"
							aria-describedby="<?php echo esc_attr( $prefix ); ?>resize-note"
							id="<?php echo esc_attr( $prefix ) . esc_attr( $name ) . '_width'; ?>"
							name="<?php echo esc_attr( WP_SMUSH_PREFIX ) . esc_attr( $name ) . '_width'; ?>"
							value="<?php echo isset( $resize_sizes['width'] ) && ! empty( $resize_sizes['width'] ) ? absint( $resize_sizes['width'] ) : 2048; ?>">
				</div>
				<div class="sui-col">
					<label aria-labelledby="<?php echo esc_attr( $prefix ); ?>label-max-height" for="<?php echo esc_attr( $prefix . $name ) . '_height'; ?>" class="sui-label">
						<?php esc_html_e( 'Max height', 'wp-smushit' ); ?>
					</label>
					<input aria-required="true" type="number" class="sui-form-control wp-smush-resize-input"
							aria-describedby="<?php echo esc_attr( $prefix ); ?>resize-note"
							id="<?php echo esc_attr( $prefix . $name ) . '_height'; ?>"
							name="<?php echo esc_attr( WP_SMUSH_PREFIX . $name ) . '_height'; ?>"
							value="<?php echo isset( $resize_sizes['height'] ) && ! empty( $resize_sizes['height'] ) ? absint( $resize_sizes['height'] ) : 2048; ?>">
				</div>
			</div>
			<div class="sui-description" id="<?php echo esc_attr( $prefix ); ?>resize-note">
				<?php
				printf(
					/* translators: %1$s: strong tag, %2$d: max width size, %3$s: tag, %4$d: max height size, %5$s: closing strong tag  */
					esc_html__( 'Currently, your largest image size is set at %1$s%2$dpx wide %3$s %4$dpx high%5$s.', 'wp-smushit' ),
					'<strong>',
					esc_html( $max_sizes['width'] ),
					'&times;',
					esc_html( $max_sizes['height'] ),
					'</strong>'
				);
				?>
			</div>
			<div class="sui-description sui-notice sui-notice-info wp-smush-update-width sui-hidden" tabindex="0">
				<?php esc_html_e( "Just to let you know, the width you've entered is less than your largest image and may result in pixelation.", 'wp-smushit' ); ?>
			</div>
			<div class="sui-description sui-notice sui-notice-info wp-smush-update-height sui-hidden" tabindex="0">
				<?php esc_html_e( 'Just to let you know, the height you’ve entered is less than your largest image and may result in pixelation.', 'wp-smushit' ); ?>
			</div>
		</div>
		<span class="sui-description sui-toggle-description">
			<?php
			printf(
				/* translators: %s: link to gifgifs.com */
				esc_html__( 'Note: Image resizing happens automatically when you upload attachments. To support
				retina devices, we recommend using 2x the dimensions of your image size. Animated GIFs will not be
				resized as they will lose their animation, please use a tool such as %s to resize
				then re-upload.', 'wp-smushit' ),
				'<a href="http://gifgifs.com/resizer/" target="_blank">http://gifgifs.com/resizer/</a>'
			);
			?>
			</span>
		<?php
	}

	/**
	 * Show super smush stats in stats section.
	 *
	 * If a pro member and super smush is enabled, show super smushed
	 * stats else show message that encourage them to enable super smush.
	 * If free user show the avg savings that can be achived using Pro.
	 *
	 * @return void
	 */
	public function pro_savings_stats() {
		$settings = WP_Smush_Settings::$settings;

		$networkwide = (bool) $settings['networkwide'];

		$core = WP_Smush::get_instance()->core();

		if ( ! WP_Smush::is_pro() ) {
			if ( empty( $core->stats ) || empty( $core->stats['pro_savings'] ) ) {
				$core->set_pro_savings();
			}
			$pro_savings      = $core->stats['pro_savings'];
			$show_pro_savings = $pro_savings['savings'] > 0 ? true : false;
			if ( $show_pro_savings ) {
				?>
				<li class="smush-avg-pro-savings" id="smush-avg-pro-savings">
					<span class="sui-list-label"><?php esc_html_e( 'Pro Savings', 'wp-smushit' ); ?>
						<span class="sui-tag sui-tag-pro sui-tooltip sui-tooltip-constrained" data-tooltip="<?php esc_html_e( 'Join WPMU DEV to unlock multi-pass lossy compression', 'wp-smushit' ); ?>">
							<?php esc_html_e( 'PRO', 'wp-smushit' ); ?>
						</span>
					</span>
					<span class="sui-list-detail wp-smush-stats">
						<span class="wp-smush-stats-human"><?php echo esc_html( $pro_savings['savings'] ); ?></span>
						<span class="wp-smush-stats-sep">/</span>
						<span class="wp-smush-stats-percent"><?php echo esc_html( $pro_savings['percent'] ); ?></span>%
					</span>
				</li>
				<?php
			}
		} else {
			$compression_savings = 0;
			if ( ! empty( $core->stats ) && ! empty( $core->stats['bytes'] ) ) {
				$compression_savings = $core->stats['bytes'] - $core->stats['resize_savings'];
			}
			?>
			<li class="super-smush-attachments">
				<span class="sui-list-label">
					<?php esc_html_e( 'Super-Smush Savings', 'wp-smushit' ); ?>
					<?php if ( ! $core->mod->smush->lossy_enabled ) { ?>
						<p class="wp-smush-stats-label-message">
							<?php
							$link_class = 'wp-smush-lossy-enable-link';
							if ( is_multisite() && $networkwide ) {
								$settings_link = WP_Smush::get_instance()->admin()->settings_link( array(), true, true ) . '#enable-lossy';
							} elseif ( 'bulk' !== $this->get_current_tab() ) {
								$settings_link = WP_Smush::get_instance()->admin()->settings_link( array(), true ) . '#enable-lossy';
							} else {
								$settings_link = '#';
								$link_class    = 'wp-smush-lossy-enable';
							}
							printf(
								/* translators: %1$s; starting a tag, %2$s: ending a tag */
								esc_html__( 'Compress images up to 2x more than regular smush with almost no visible drop in quality. %1$sEnable Super-smush%2$s', 'wp-smushit' ),
								'<a role="button" class="' . esc_attr( $link_class ) . '" href="' . esc_url( $settings_link ) . '">',
								'<span class="sui-screen-reader-text">' . esc_html__( 'Clicking this link will toggle the Super Smush checkbox.', 'wp-smushit' ) . '</span></a>'
							);
							?>
						</p>
					<?php } ?>
				</span>
				<?php if ( $core->mod->smush->lossy_enabled ) { ?>
					<span class="sui-list-detail wp-smush-stats">
						<span class="smushed-savings">
							<?php echo esc_html( size_format( $compression_savings, 1 ) ); ?>
						</span>
					</span>
				<?php } ?>
			</li>
			<?php
		}
	}

	/**
	 * Show conversion savings stats in stats section.
	 *
	 * Show Png to Jpg conversion savings in stats box if the
	 * settings enabled or savings found.
	 *
	 * @return void
	 */
	public function conversion_savings_stats() {
		$core = WP_Smush::get_instance()->core();

		if ( WP_Smush::is_pro() && ! empty( $core->stats['conversion_savings'] ) && $core->stats['conversion_savings'] > 0 ) {
			?>
			<li class="smush-conversion-savings">
				<span class="sui-list-label">
					<?php esc_html_e( 'PNG to JPEG savings', 'wp-smushit' ); ?>
				</span>
				<span class="sui-list-detail wp-smush-stats">
					<?php echo $core->stats['conversion_savings'] > 0 ? esc_html( size_format( $core->stats['conversion_savings'], 1 ) ) : '0 MB'; ?>
				</span>
			</li>
			<?php
		}
	}

	/**
	 * Set directory smush stats to stats box.
	 *
	 * @return void
	 */
	public function directory_stats_ui() {
		$dir_smush_stats = get_option( 'dir_smush_stats' );
		$human           = 0;
		if ( ! empty( $dir_smush_stats ) && ! empty( $dir_smush_stats['dir_smush'] ) ) {
			$human = ! empty( $dir_smush_stats['dir_smush']['bytes'] ) && $dir_smush_stats['dir_smush']['bytes'] > 0 ? $dir_smush_stats['dir_smush']['bytes'] : 0;
		}
		?>
		<li class="smush-dir-savings">
			<span class="sui-list-label"><?php esc_html_e( 'Directory Smush Savings', 'wp-smushit' ); ?>
				<?php if ( $human <= 0 ) { ?>
					<p class="wp-smush-stats-label-message">
						<?php esc_html_e( "Smush images that aren't located in your uploads folder.", 'wp-smushit' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=smush&tab=directory' ) ); ?>" class="wp-smush-dir-link"
							title="<?php esc_attr_e( "Select a directory you'd like to Smush.", 'wp-smushit' ); ?>">
							<?php esc_html_e( 'Choose directory', 'wp-smushit' ); ?>
						</a>
					</p>
				<?php } ?>
			</span>
			<span class="wp-smush-stats sui-list-detail">
				<i class="sui-icon-loader sui-loading" aria-hidden="true" title="<?php esc_attr_e( 'Updating Stats', 'wp-smushit' ); ?>"></i>
				<span class="wp-smush-stats-human"></span>
				<span class="wp-smush-stats-sep sui-hidden">/</span>
				<span class="wp-smush-stats-percent"></span>
			</span>
		</li>
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
		$grouped_settings = array_merge( $this->resize_group, $this->full_size_group, $this->integration_group );

		?>
		<div class="sui-box-settings-row wp-smush-basic <?php echo $upsell ? 'sui-disabled' : ''; ?>">
			<div class="sui-box-settings-col-1">
				<span class="sui-settings-label <?php echo 'gutenberg' === $name ? 'sui-settings-label-with-tag' : ''; ?>">
					<?php echo esc_html( $label ); ?>
					<?php do_action( 'smush_setting_column_tag', $name ); ?>
				</span>

				<span class="sui-description">
					<?php echo WP_Smush::get_instance()->core()->settings[ $name ]['desc']; ?>
				</span>
			</div>
			<div class="sui-box-settings-col-2" id="column-<?php echo esc_attr( $setting_m_key ); ?>">
				<?php if ( ! in_array( $name, $grouped_settings, true ) || $skip_group ) : ?>
					<div class="sui-form-field">
						<label class="sui-toggle">
							<input type="checkbox" aria-describedby="<?php echo esc_attr( $setting_m_key . '-desc' ); ?>" id="<?php echo esc_attr( $setting_m_key ); ?>" name="<?php echo esc_attr( $setting_m_key ); ?>" <?php checked( $setting_val, 1, true ); ?> value="1" <?php disabled( $disable ); ?>>
							<span class="sui-toggle-slider"></span>
						</label>
						<label for="<?php echo esc_attr( $setting_m_key ); ?>">
							<?php echo esc_html( WP_Smush::get_instance()->core()->settings[ $name ]['label'] ); ?>
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
	 * Show additional descriptions for settings.
	 *
	 * @param string $setting_key Setting key.
	 */
	public function settings_desc( $setting_key = '' ) {
		if ( empty( $setting_key ) || ! in_array(
			$setting_key, array(
				'resize',
				'original',
				'strip_exif',
				'png_to_jpg',
				's3',
			), true
		) ) {
			return;
		}
		?>
		<span class="sui-description sui-toggle-description" id="<?php echo esc_attr( WP_SMUSH_PREFIX . $setting_key . '-desc' ); ?>">
			<?php
			switch ( $setting_key ) {
				case 'resize':
					esc_html_e( 'Save a ton of space by not storing over-sized images on your server. Set a
					maximum height and width for all images uploaded to your site so that any unnecessarily large
					images are automatically scaled down to a reasonable size. Note: Image resizing happens
					automatically when you upload attachments. This setting does not apply to images smushed using
					Directory Smush feature. To support retina devices, we recommend using 2x the dimensions of
					your image size.', 'wp-smushit' );
					break;
				case 'original':
					esc_html_e( 'Every time you upload an image to your site, WordPress generates a resized
					version of that image for every image size that your theme has registered. This means there
					are multiple versions of your images in your media library. By default, Smush only compresses
					these generated images. Activate this setting to also smush your original images. Note: Activating
					this setting doesn’t usually improve page speed, unless your website uses the original images
					in full size.', 'wp-smushit' );
					break;
				case 'strip_exif':
					esc_html_e( 'Note: This data adds to the size of the image. While this information might be
					important to photographers, it’s unnecessary for most users and safe to remove.', 'wp-smushit' );
					break;
				case 'png_to_jpg':
					esc_html_e( 'Note: Any PNGs with transparency will be ignored. Smush will only convert PNGs
					if it results in a smaller file size. The resulting file will have a new filename and extension
					(JPEG), and any hard-coded URLs on your site that contain the original PNG filename will need
					to be updated.', 'wp-smushit' );
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
		$sizes       = WP_Smush::get_instance()->core()->image_dimensions();

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
				<span class="sui-description">
					<?php
					esc_html_e( 'Every time you upload an image to your site, WordPress generates a
					resized version of that image for every default and/or custom image size that your theme has
					registered. This means there are multiple versions of your images in your media library. Choose
					the images sizes below that you would like optimized:', 'wp-smushit' );
					?>
				</span>
				<?php
				foreach ( $sizes as $size_k => $size ) {
					// If image sizes array isn't set, mark all checked ( Default Values ).
					if ( false === $image_sizes ) {
						$checked = true;
					} else {
						$checked = is_array( $image_sizes ) ? in_array( $size_k, $image_sizes, true ) : false;
					}
					// For free users, disable full size option.
					if ( 'full' === $size_k ) {
						$disabled = $is_pro ? '' : 'disabled';
						$checked  = $is_pro ? $checked : false;
					}
					?>
					<label class="sui-checkbox sui-description">
						<input type="checkbox" id="wp-smush-size-<?php echo esc_attr( $size_k ); ?>" <?php checked( $checked, true ); ?> name="wp-smush-image_sizes[]" value="<?php echo esc_attr( $size_k ); ?>" <?php echo esc_attr( $disabled ); ?>>
						<span aria-hidden="true"></span>
						<?php if ( isset( $size['width'], $size['height'] ) ) : ?>
							<span class="sui-description">
								<?php echo esc_html( $size_k . ' (' . $size['width'] . 'x' . $size['height'] . ') ' ); ?>
							</span>
						<?php else : ?>
							<span class="sui-description"><?php echo esc_attr( $size_k ); ?>
								<?php if ( ! $is_pro ) : ?>
									<span class="sui-tag sui-tag-pro sui-tooltip sui-tooltip-constrained" data-tooltip="<?php esc_html_e( 'Join WPMU DEV to unlock multi-pass lossy compression', 'wp-smushit' ); ?>">
										<?php esc_html_e( 'PRO', 'wp-smushit' ); ?>
									</span>
								<?php endif; ?>
							</span>
						<?php endif; ?>
					</label>
					<?php
				}
				?>
			</div>
			<?php
		}
	}

	/**
	 * Prints Resize, Smush Original, and Backup settings.
	 *
	 * @param string $name  Name of the current setting being processed.
	 */
	public function full_size_options( $name = '' ) {
		// Continue only if original image option.
		if ( 'original' !== $name || ! WP_Smush::is_pro() ) {
			return;
		}

		foreach ( $this->full_size_group as $name ) {
			$setting_val = WP_Smush_Settings::$settings[ $name ];
			$setting_key = WP_SMUSH_PREFIX . $name;
			?>
			<div class="sui-form-field">
				<label class="sui-toggle">
					<input type="checkbox" aria-describedby="<?php echo esc_attr( $setting_key ); ?>-desc" id="<?php echo esc_attr( $setting_key ); ?>" name="<?php echo esc_attr( $setting_key ); ?>" <?php checked( $setting_val, 1 ); ?> value="1">
					<span class="sui-toggle-slider"></span>
					<label class="toggle-label <?php echo esc_attr( $setting_key . '-label' ); ?>" for="<?php echo esc_attr( $setting_key ); ?>" aria-hidden="true"></label>
				</label>
				<label for="<?php echo esc_attr( $setting_key ); ?>">
					<?php echo esc_html( WP_Smush::get_instance()->core()->settings[ $name ]['label'] ); ?>
				</label>
				<span class="sui-description sui-toggle-description">
					<?php echo esc_html( WP_Smush::get_instance()->core()->settings[ $name ]['desc'] ); ?>
				</span>
			</div>
			<?php
		}
	}

	/**
	 * Prints front end image size detection option.
	 *
	 * @param string $name  Name of the current setting being processed.
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
					<input type="checkbox" aria-describedby="<?php echo esc_attr( $setting_key ); ?>-desc" id="<?php echo esc_attr( $setting_key ); ?>" name="<?php echo esc_attr( $setting_key ); ?>" <?php checked( $setting_val, 1, true ); ?> value="1">
					<span class="sui-toggle-slider"></span>
					<label class="toggle-label <?php echo esc_attr( $setting_key . '-label' ); ?>" for="<?php echo esc_attr( $setting_key ); ?>" aria-hidden="true"></label>
				</label>
				<label for="<?php echo esc_attr( $setting_key ); ?>">
					<?php echo esc_html( WP_Smush::get_instance()->core()->settings[ $name ]['label'] ); ?>
				</label>
				<span class="sui-description sui-toggle-description">
					<?php echo esc_html( WP_Smush::get_instance()->core()->settings[ $name ]['desc'] ); ?>
					<?php if ( 'detection' === $name ) : ?>
						<?php if ( 1 === $setting_val ) : // If detection is enabled. ?>
							<div class="sui-notice sui-notice-info smush-notice-sm smush-highlighting-notice">
								<p>
									<?php
									printf(
										/* translators: %1$s: opening a tag, %2$s: closing a tag */
										esc_html__( 'Incorrect image size highlighting is active. %1$sView the
										frontend%2$s of your website to see which images aren\'t the correct size
										for their containers.', 'wp-smushit' ),
										'<a href="' . esc_url( home_url() ) . '" target="_blank">',
										'</a>'
									);
									?>
								</p>
							</div>
						<?php endif; ?>
						<div class="sui-notice sui-notice-warning smush-notice-sm smush-highlighting-warning sui-hidden">
							<p>
								<?php
								esc_html_e( 'Almost there! To finish activating this feature you must
								save your settings.', 'wp-smushit' );
								?>
							</p>
						</div>
					<?php endif; ?>
				</span>
			</div>
			<?php
		}
	}

	/**
	 * Show directory smush result notice.
	 *
	 * If we are redirected from a directory smush finish page,
	 * show the result notice if success/fail count is available.
	 *
	 * @since 2.9.0
	 */
	public function smush_result_notice() {
		// Get the counts from transient.
		$items          = get_transient( 'wp-smush-show-dir-scan-notice' );
		$failed_items   = get_transient( 'wp-smush-dir-scan-failed-items' );
		$notice_message = esc_html__( 'All images failed to optimize.', 'wp-smushit' );
		$notice_class   = 'sui-notice-error';

		// Not all images optimized.
		if ( ! empty( $failed_items ) && ! empty( $items ) ) {
			$notice_message = sprintf(
				/* translators: %1$d: number of images smushed and %1$d number of failed. */
				esc_html__( '%1$d images were successfully optimized and %2$d images failed.', 'wp-smushit' ),
				absint( $items ),
				absint( $failed_items )
			);
			$notice_class   = 'sui-notice-warning';
		} elseif ( ! empty( $items ) && empty( $failed_items ) ) {
			// Yay! All images were optimized.
			$notice_message = sprintf(
				/* translators: %d: number of images */
				esc_html__( '%d images were successfully optimized.', 'wp-smushit' ),
				absint( $items )
			);
			$notice_class   = 'sui-notice-success';
		}

		// If we have counts, show the notice.
		if ( ! empty( $items ) || ! empty( $failed_items ) ) {
			// Delete the transients.
			delete_transient( 'wp-smush-show-dir-scan-notice' );
			delete_transient( 'wp-smush-dir-scan-failed-items' );
			?>
			<div class="sui-notice-top sui-can-dismiss <?php echo esc_attr( $notice_class ); ?>">
				<p class="sui-notice-content">
					<?php echo $notice_message; ?>
				</p>
				<span class="sui-notice-dismiss">
					<a role="button" href="#" aria-label="<?php esc_attr_e( 'Dismiss', 'wp-smushit' ); ?>" class="sui-icon-check"></a>
				</span>
			</div>
			<?php
		}
	}

	/**
	 * Summary meta box.
	 */
	public function dashboard_summary_metabox() {
		$core = WP_Smush::get_instance()->core();

		$settings     = WP_Smush_Settings::$settings;
		$resize_count = $core->mod->db->resize_savings( false, false, true );

		// Split human size to get format and size.
		$human = explode( ' ', $core->stats['human'] );

		$resize_savings = 0;
		// Get current resize savings.
		if ( ! empty( $core->stats['resize_savings'] ) && $core->stats['resize_savings'] > 0 ) {
			$resize_savings = size_format( $core->stats['resize_savings'], 1 );
		}

		$this->view( 'meta-boxes/summary/meta-box', array(
			'human_format'    => empty( $human[1] ) ? 'B' : $human[1],
			'human_size'      => empty( $human[0] ) ? '0' : $human[0],
			'networkwide'     => (bool) $settings['networkwide'],
			'remaining'       => $core->remaining_count,
			'resize_count'    => ! $resize_count ? 0 : $resize_count,
			'resize_enabled'  => (bool) $settings['resize'],
			'resize_savings'  => $resize_savings,
			'stats_percent'   => $core->stats['percent'] > 0 ? number_format_i18n( $core->stats['percent'], 1 ) : 0,
			'total_optimized' => $core->stats['total_images'],
		) );
	}

	/**
	 * Bulk smush meta box.
	 *
	 * Container box to handle bulk smush actions. Show progress bars,
	 * bulk smush action buttons etc. in this box.
	 */
	public function bulk_smush_metabox() {
		$core = WP_Smush::get_instance()->core();

		$upgrade_url = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'smush_stats_enable_lossy',
			), $core->upgrade_url
		);

		$bulk_upgrade_url = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'smush_bulksmush_limit_notice',
			), $core->upgrade_url
		);

		$pro_upgrade_url = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'smush_bulksmush_upsell_notice',
			), $core->upgrade_url
		);

		$this->view( 'meta-boxes/bulk/meta-box', array(
			'all_done'         => $core->smushed_count === $core->total_count && empty( $core->resmush_ids ),
			'bulk_upgrade_url' => $bulk_upgrade_url,
			'core'             => $core,
			'hide_pagespeed'   => get_site_option( WP_SMUSH_PREFIX . 'hide_pagespeed_suggestion' ),
			'is_pro'           => WP_Smush::is_pro(),
			'lossy_enabled'    => $core->mod->smush->lossy_enabled,
			'pro_upgrade_url'  => $pro_upgrade_url,
			'upgrade_url'      => $upgrade_url,
		) );
	}

	/**
	 * Settings meta box.
	 *
	 * Free and pro version settings are shown in same section. For free users, pro settings won't be shown.
	 * To print full size smush, resize and backup in group, we hook at `smush_setting_column_right_end`.
	 */
	public function settings_metabox() {
		// Get all grouped settings that can be skipped.
		$grouped_settings = array_merge( $this->resize_group, $this->full_size_group, $this->integration_group );

		// Get settings values.
		$settings = empty( WP_Smush_Settings::$settings ) ? WP_Smush_Settings::init_settings() : WP_Smush_Settings::$settings;

		$this->view( 'meta-boxes/settings/meta-box', array(
			'basic_features'      => WP_Smush_Core::$basic_features,
			'grouped_settings'    => $grouped_settings,
			'opt_networkwide_val' => WP_Smush_Settings::$settings['networkwide'],
			'settings'            => $settings,
			'settings_data'       => WP_Smush::get_instance()->core()->settings,
		) );
	}

	/**
	 * Pro features list box to show after settings.
	 */
	public function pro_features_metabox() {
		// Upgrade url for upsell.
		$upsell_url = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'smush-advanced-settings-upsell',
			), WP_Smush::get_instance()->core()->upgrade_url
		);

		$this->view( 'meta-boxes/pro-features/meta-box', array(
			'upsell_url' => $upsell_url,
		) );
	}

	/**
	 * Pro features meta box header.
	 */
	public function pro_features_metabox_header() {
		// Upgrade url with analytics keys.
		$upgrade_url = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'smush_advancedsettings_profeature_tag',
			), WP_Smush::get_instance()->core()->upgrade_url
		);

		$this->view( 'meta-boxes/pro-features/meta-box-header', array(
			'title'       => __( 'Pre Features', 'wp-smushit' ),
			'upgrade_url' => $upgrade_url,
		) );
	}

	/**
	 * Directory Smush meta box.
	 */
	public function directory_smush_metabox() {
		// Reset the bulk limit transient.
		if ( ! WP_Smush::is_pro() ) {
			WP_Smush_Core::check_bulk_limit( true, 'dir_sent_count' );
		}

		$core = WP_Smush::get_instance()->core();

		$upgrade_url = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'smush_directorysmush_limit_notice',
			), $core->upgrade_url
		);

		$this->view( 'meta-boxes/directory/meta-box', array(
			'root_path'   => $core->mod->dir->get_root_path(),
			'upgrade_url' => $upgrade_url,
		) );
	}

	/**
	 * Integrations meta box.
	 *
	 * Free and pro version settings are shown in same section. For free users, pro settings won't be shown.
	 * To print full size smush, resize and backup in group, we hook at `smush_setting_column_right_end`.
	 */
	public function integrations_metabox() {
		$core = WP_Smush::get_instance()->core();

		$upsell_url = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'smush-nextgen-settings-upsell',
			), $core->upgrade_url
		);

		// Get settings values.
		$settings = empty( WP_Smush_Settings::$settings ) ? WP_Smush_Settings::init_settings() : WP_Smush_Settings::$settings;

		$this->view( 'meta-boxes/integrations/meta-box', array(
			'basic_features'    => WP_Smush_Core::$basic_features,
			'is_pro'            => WP_Smush::is_pro(),
			'integration_group' => $this->integration_group,
			'settings'          => $settings,
			'settings_data'     => $core->settings,
			'upsell_url'        => $upsell_url,
		) );
	}

	/**
	 * Integrations meta box footer.
	 */
	public function integrations_metabox_footer() {
		/**
		 * Filter to enable/disable submit button in integration settings.
		 *
		 * @param bool $show_submit Should show submit?
		 */
		$this->view( 'meta-boxes/integrations/meta-box-footer', array(
			'show_submit' => apply_filters( 'wp_smush_integration_show_submit', false ),
		) );
	}

	/**
	 * CDN meta box (for free users).
	 *
	 * @since 3.0
	 */
	public function cdn_upsell_metabox() {
		$upgrade_url = add_query_arg(
			array(
				'utm_source'   => 'smush',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'smush_stats_enable_lossy',
			), WP_Smush::get_instance()->core()->upgrade_url
		);

		$this->view( 'meta-boxes/cdn/upsell-meta-box', array(
			'upgrade_url' => $upgrade_url,
		) );
	}

	/**
	 * Upsell meta box header.
	 *
	 * @since 3.0
	 */
	public function cdn_upsell_metabox_header() {
		$this->view( 'meta-boxes/cdn/upsell-meta-box-header', array(
			'title' => __( 'CDN', 'wp-smushit' ),
		) );
	}

	/**
	 * CDN meta box.
	 *
	 * @since 3.0
	 */
	public function cdn_metabox() {
		$status_msg = array(
			'warning' => __( 'CDN is not yet active. Configure your settings below and click Activate.', 'wp-smushit' ),
			'success' => __( 'CDN is active. Your media is being served from the WPMU DEV CDN.', 'wp-smushit' ),
			'error'   => __( 'CDN is inactive. You have gone over your 30 day cap so we’ve stopped serving your images.
					Upgrade your plan now to reactivate this service.', 'wp-smushit' ),
		);

		$this->view( 'meta-boxes/cdn/meta-box', array(
			'status'     => 'warning', // inactive (warning), active (success) or expired (error).
			'status_msg' => $status_msg,
		) );
	}

}
