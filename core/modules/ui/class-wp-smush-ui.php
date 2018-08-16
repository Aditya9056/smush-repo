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

}