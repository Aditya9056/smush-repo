<?php
/**
 * Tools meta box.
 *
 * @since 3.2.1
 * @package WP_Smush
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

?>

<form id="wp-smush-settings-form" method="post">
	<input type="hidden" name="setting_form" id="setting_form" value="tools">

	<?php
	if ( ! is_multisite() || ( ! $settings['networkwide'] && ! is_network_admin() ) || is_network_admin() ) {
		foreach ( $settings_data as $name => $values ) {
			// If not CDN setting - skip.
			if ( ! in_array( $name, $grouped_settings, true ) ) {
				continue;
			}

			$label = ! empty( $settings_data[ $name ]['short_label'] ) ? $settings_data[ $name ]['short_label'] : $settings_data[ $name ]['label'];

			// Show settings option.
			$this->settings_row( WP_SMUSH_PREFIX . $name, $label, $name, $settings[ $name ] );
		}
	}
	?>
</form>
