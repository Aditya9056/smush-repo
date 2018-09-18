<?php
/**
 * Settings meta box.
 *
 * @package WP_Smush
 *
 * @var array $basic_features       Basic features list.
 * @var array $grouped_settings     Grouped settings that can be skipeed.
 * @var bool  $opt_networkwide_val  Networkwide or not?
 * @var array $settings             Settings values.
 * @var array $settings_data        Settings labels and descriptions.
 */

?>

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

	<?php
	if ( ! is_multisite() || ( ! $opt_networkwide_val && ! is_network_admin() ) || is_network_admin() ) {
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

			// Disable only auto Smush if CDN is enabled.
			$disable = WP_Smush::get_instance()->core()->mod->cdn->get_status() && 'auto' === $name;

			// Show settings option.
			$this->settings_row( $setting_m_key, $label, $name, $setting_val, $disable, $disable );
		}

		// Hook after general settings.
		do_action( 'wp_smush_after_basic_settings' );
	}

	if ( is_multisite() && is_network_admin() ) {
		echo '</div>';
	}
	?>
</form>
