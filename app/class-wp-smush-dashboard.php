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
	 * Register page action hooks
	 */
	public function add_action_hooks() {
		parent::add_action_hooks();

		// Add stats to stats box.
		add_action( 'stats_ui_after_resize_savings', array( $this, 'pro_savings_stats' ), 15 );
		add_action( 'stats_ui_after_resize_savings', array( $this, 'conversion_savings_stats' ), 15 );
	}

	/**
	 * Function triggered when the page is loaded before render any content.
	 */
	public function on_load() {
		// Hook into integration settings.
		$this->intgration_group = apply_filters( 'wp_smush_integration_settings', array() );

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
				// 'cdn'          => __( 'CDN', 'wp-smushit' ),
			)
		);

		$networkwide = WP_Smush_Settings::$settings['networkwide'];

		// TODO: verify that this works properly.

		// Tabs that can be shown in network admin networkwide (bulk, integrations, cdn).
		if ( is_multisite() && $networkwide && is_network_admin() ) {
			unset( $this->tabs['directory'] );
		}

		// Tabs that can be shown in subsites if networkwide (bulk and directory).
		if ( is_multisite() && $networkwide && ! is_network_admin() ) {
			unset( $this->tabs['integrations'] );
			//unset( $this->tabs['cdn'] );
		}

		if ( empty( $this->intgration_group ) ) {
			unset( $this->tabs['integrations'] );
		}

		// Icons in the submenu.
		add_filter( 'wp_smush_admin_after_tab_' . $this->get_slug(), array( $this, 'after_tab' ) );
	}

	/**
	 * Register meta boxes.
	 */
	public function register_meta_boxes() {
		$is_network     = is_network_admin();
		$is_networkwide = WP_Smush_Settings::$settings['networkwide'];

		if ( ! $is_network ) {
			$this->add_meta_box( 'summary',
				null,
				array( $this, 'dashboard_summary_metabox' ),
				null,
				null,
				'summary',
				array(
					'box_class'         => 'sui-box sui-summary-smush',
					'box_content_class' => false,
				)
			);

			// If not a pro user.
			if ( ! WP_Smush::is_pro() ) {
				/**
				 * Allows to hook in additional containers after stats box for free version
				 * Pro Version has a full width settings box, so we don't want to do it there.
				 */
				do_action( 'wp_smush_after_stats_box' );
			}
		}

		if ( $is_network && ! $is_networkwide ) {

			return;
		}

		switch ( $this->get_current_tab() ) {
			case 'bulk':
			default:
				// Show bulk smush box if a subsite admin.
				if ( ! $is_network ) {
					// Class for bulk smush box.
					$class = WP_Smush::is_pro() ? 'bulk-smush-wrapper wp-smush-pro-install' : 'bulk-smush-wrapper';

					$this->add_meta_box( 'bulk',
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
					// Show settings box.
					//$this->settings_container();
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
					<?php if ( ! $core->smush->lossy_enabled ) { ?>
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
				<?php if ( $core->smush->lossy_enabled ) { ?>
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
	 * Shows a option to ignore the Image ids which can be resmushed while bulk smushing.
	 *
	 * @param bool $count  Resmush + unsmushed image count.
	 * @param bool $show   Should show or not.
	 */
	public function bulk_resmush_content( $count = false, $show = false ) {
		// If we already have count, don't fetch it.
		if ( false === $count ) {
			// If we have the resmush ids list, Show Resmush notice and button.
			if ( $resmush_ids = get_option( 'wp-smush-resmush-list' ) ) {
				// Count.
				$count = count( $resmush_ids );

				// Whether to show the remaining re-smush notice.
				$show = $count > 0 ? true : false;

				// Get the actual remainaing count.
				if ( ! isset( $wpsmushit_admin->remaining_count ) ) {
					WP_Smush::get_instance()->core()->setup_global_stats();
				}

				$count = WP_Smush::get_instance()->core()->remaining_count;
			}
		}

		// Show only if we have any images to ber resmushed.
		if ( $show ) {
			?>
			<div class="sui-notice sui-notice-warning wp-smush-resmush-notice wp-smush-remaining" tabindex="0">
				<p>
					<span class="wp-smush-notice-text">
						<?php
						printf(
							/* translators: %1$s: user name, %2$s: strong tag, %3$s: span tag, %4$d: number of remaining umages, %5$s: closing span tag, %6$s: closing strong tag  */
							_n( '%1$s, you have %2$s%3$s%4$d%5$s attachment%6$s that needs re-compressing!', '%1$s, you have %2$s%3$s%4$d%5$s attachments%6$s that need re-compressing!', $count, 'wp-smushit' ),
							esc_html( WP_Smush_Helper::get_user_name() ),
							'<strong>',
							'<span class="wp-smush-remaining-count">',
							absint( $count ),
							'</span>',
							'</strong>'
						);
						?>
					</span>
				</p>
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
		$resize_count = WP_Smush::get_instance()->core()->db->resize_savings( false, false, true );

		// Split human size to get format and size.
		$human = explode( ' ', $core->stats['human'] );

		$resize_savings = 0;
		// Get current resize savings.
		if ( ! empty( $core->stats['resize_savings'] ) && $core->stats['resize_savings'] > 0 ) {
			$resize_savings = size_format( $core->stats['resize_savings'], 1 );
		}

		$this->view( 'summary/meta-box', array(
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

		$this->view( 'bulk/meta-box', array(
			'all_done'         => $core->smushed_count === $core->total_count && empty( $core->resmush_ids ),
			'bulk_upgrade_url' => $bulk_upgrade_url,
			'core'             => $core,
			'hide_pagespeed'   => get_site_option( WP_SMUSH_PREFIX . 'hide_pagespeed_suggestion' ),
			'is_pro'           => WP_Smush::is_pro(),
			'lossy_enabled'    => $core->smush->lossy_enabled,
			'pro_upgrade_url'  => $pro_upgrade_url,
			'upgrade_url'      => $upgrade_url,
		) );
	}

}
