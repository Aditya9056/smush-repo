<?php
/**
 * Settings meta box.
 *
 * @package WP_Smush
 *
 * @var array $basic_features       Basic features list.
 * @var bool  $cdn_enabled          CDN status.
 * @var array $grouped_settings     Grouped settings that can be skipeed.
 * @var bool  $opt_networkwide_val  Networkwide or not?
 * @var array $settings             Settings values.
 * @var array $settings_data        Settings labels and descriptions.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

?>

<?php if ( $cdn_enabled && ( ( ! is_network_admin() && ! $opt_networkwide_val ) || ( is_network_admin() && $opt_networkwide_val ) ) ) : ?>
	<div class="sui-notice sui-notice-info">
		<p><?php esc_html_e( 'Your images are currently being served via the WPMU DEV CDN. Bulk smush will continue to operate as per your settings below and is treated completely separately in case you ever want to disable the CDN.', 'wp-smushit' ); ?></p>
	</div>
<?php endif; ?>

<form id="wp-smush-settings-form" method="post">
	<input type="hidden" name="setting_form" id="setting_form" value="bulk">
	<?php if ( is_multisite() && is_network_admin() ) : ?>
		<input type="hidden" name="setting-type" value="network">
		<div class="sui-box-settings-row wp-smush-basic">
			<div class="sui-box-settings-col-1">
				<label for="<?php echo esc_attr( WP_SMUSH_PREFIX . 'networkwide' ); ?>" aria-hidden="true">
					<span class="sui-settings-label">
						<?php echo esc_html( $settings_data['networkwide']['short_label'] ); ?>
					</span>
					<span class="sui-description">
						<?php echo esc_html( $settings_data['networkwide']['desc'] ); ?>
					</span>
				</label>
			</div>
			<div class="sui-box-settings-col-2">
				<label class="sui-toggle">
					<input type="checkbox" id="<?php echo esc_attr( WP_SMUSH_PREFIX . 'networkwide' ); ?>" name="<?php echo esc_attr( WP_SMUSH_PREFIX . 'networkwide' ); ?>" <?php checked( $opt_networkwide_val, 1, true ); ?> value="1">
					<span class="sui-toggle-slider"></span>
					<label class="toggle-label" for="<?php echo esc_attr( WP_SMUSH_PREFIX . 'networkwide' ); ?>" aria-hidden="true"></label>
				</label>
				<label for="<?php echo esc_attr( WP_SMUSH_PREFIX . 'networkwide' ); ?>">
					<?php echo esc_html( $settings_data['networkwide']['label'] ); ?>
				</label>
			</div>
		</div>
		<div class="network-settings-wrapper<?php echo $opt_networkwide_val ? '' : ' sui-hidden'; ?>">
	<?php endif; ?>

	<?php if ( ! is_multisite() || ( ! $opt_networkwide_val && ! is_network_admin() ) || is_network_admin() ) : ?>
	<?php if ( isset( $a ) && 11 === $a ) : ?>
		<div class="sui-box-settings-row">
			<div class="sui-box-settings-col-1">
				<span class="sui-settings-label"><?php esc_html_e( 'Image Sizes', 'wp-smushit' ); ?></span>
				<span class="sui-description">
					<?php esc_html_e( 'WordPress generates multiple image thumbnails for each image you upload. Choose which of those thumbnail sizes you want to include when bulk smushing.', 'wp-smushit' ); ?>
				</span>
			</div>
			<div class="sui-box-settings-col-2">
				<div class="sui-side-tabs sui-tabs">
					<div data-tabs="">
						<label for="animation-fadein" class="sui-tab-item active">
							<input type="radio" name="animation[value]" value="fadein" id="animation-fadein" checked="checked">
							<?php esc_html_e( 'All Image Sizes', 'wp-smushit' ); ?>
						</label>
						<label for="animation-spinner" class="sui-tab-item">
							<input type="radio" name="animation[value]" value="spinner" id="animation-spinner">
							<?php esc_html_e( 'Custom', 'wp-smushit' ); ?>
						</label>
					</div><!-- end data-tabs -->
					<div data-panes="">
						<div class="sui-tab-boxed active" style="display:none"></div>
						<div class="sui-tab-boxed">
							<?php
							// Additional Image sizes.
							$image_sizes = $this->settings->get_setting( WP_SMUSH_PREFIX . 'image_sizes', false );
							$sizes       = WP_Smush::get_instance()->core()->image_dimensions();

							/**
							 * Add an additional item for full size.
							 * Do not use intermediate_image_sizes filter.
							 */
							$sizes['full'] = array();
							?>

							<?php if ( ! empty( $sizes ) ) : ?>
								<!-- List of image sizes recognised by WP Smush -->
								<span class="sui-label">
									<?php esc_html_e( 'Included image sizes', 'wp-smushit' ); ?>
								</span>
								<?php
								foreach ( $sizes as $size_k => $size ) {
									// If image sizes array isn't set, mark all checked ( Default Values ).
									if ( false === $image_sizes ) {
										$checked = true;
									} else {
										// WPMDUDEV hosting support: cast $size_k to string to properly work with object cache.
										$checked = is_array( $image_sizes ) ? in_array( (string) $size_k, $image_sizes, true ) : false;
									}
									// For free users, remove full size option.
									if ( 'full' === $size_k ) {
										continue;
									}
									?>
									<label class="sui-checkbox sui-checkbox-stacked sui-checkbox-sm">
										<input type="checkbox" id="wp-bulk-size-<?php echo esc_attr( $size_k ); ?>" <?php checked( $checked, true ); ?> name="wp-bulk-image_sizes[]" value="<?php echo esc_attr( $size_k ); ?>">
										<span aria-hidden="true"></span>
										<?php if ( isset( $size['width'], $size['height'] ) ) : ?>
											<span class="sui-description">
												<?php echo esc_html( $size_k . ' (' . $size['width'] . 'x' . $size['height'] . ') ' ); ?>
											</span>
										<?php else : ?>
											<span><?php echo esc_attr( $size_k ); ?></span>
										<?php endif; ?>
									</label>
									<?php
								}
								?>
							<?php endif; ?>
						</div>
					</div><!-- end data-panes -->
				</div><!-- end .sui-tabs -->
			</div><!-- end .sui-box-settings-col-2 -->
		</div>
		<?php endif; ?>

		<?php
		foreach ( $settings_data as $name => $value ) {
			// Skip networkwide settings, we already printed it.
			if ( 'networkwide' === $name ) {
				continue;
			}

			// Skip premium features if not a member.
			if ( ! in_array( $name, $basic_features, true ) && ! WP_Smush::is_pro() ) {
				continue;
			}

			$setting_m_key = WP_SMUSH_PREFIX . $name;
			$setting_val   = empty( $settings[ $name ] ) ? false : $settings[ $name ];

			// Group original, resize and backup for PRO users.
			if ( in_array( $name, $grouped_settings, true ) ) {
				continue;
			}

			$label = ! empty( $value['short_label'] ) ? $value['short_label'] : $value['label'];

			// Show settings option.
			$this->settings_row( $setting_m_key, $label, $name, $setting_val );
		}

		// Hook after general settings.
		do_action( 'wp_smush_after_basic_settings' );
	endif;

	if ( is_multisite() && is_network_admin() ) {
		echo '</div>';
	}
	?>
</form>
