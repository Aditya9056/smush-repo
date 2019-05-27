<?php
/**
 * Restore images modal.
 *
 * @since 3.2.2
 * @package WP_Smush
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

?>

<script type="text/template" id="smush-bulk-restore">
	<# console.log( data ); #>
	<div class="sui-box-header">
		<h3 class="sui-box-title" id="dialogTitle">
			<# if ( 'start' === data.slide ) { #>
			<?php esc_html_e( 'Restore Thumbnails', 'wp-smushit' ); ?>
			<# } else if ( 'progress' === data.slide ) { #>
			<i class="sui-icon-loader sui-loading" aria-hidden="true"></i>
			<?php esc_html_e( 'Restoring Images', 'wp-smushit' ); ?>
			<# } else if ( 'finish' === data.slide ) { #>
			<i class="sui-icon-check" aria-hidden="true"></i>
			<?php esc_html_e( 'Restore Complete', 'wp-smushit' ); ?>
			<# } #>
		</h3>
		<div class="sui-actions-right">
			<button onclick="WP_Smush.restore.cancel()" data-a11y-dialog-hide class="sui-dialog-close" aria-label="Close this dialog window"></button>
		</div>
	</div>

	<div class="sui-box-body">
		<p>
			<# if ( 'start' === data.slide ) { #>
			<?php esc_html_e( 'Are you sure you want to restore all image thumbnails to their original, non-optimized states?', 'wp-smushit' ); ?>
			<# } else if ( 'progress' === data.slide ) { #>
			<?php esc_html_e( 'Your bulk restore is still in progress, please leave this tab open while the process runs.', 'wp-smushit' ); ?>
			<# } else if ( 'finish' === data.slide ) { #>
			<?php esc_html_e( 'Your bulk restore has finished running.', 'wp-smushit' ); ?>
			<# } #>
		</p>

		<div class="sui-block-content-center">
			<# if ( 'start' === data.slide ) { #>
			<button class="sui-button sui-button-ghost" onclick="WP_Smush.restore.cancel()" data-a11y-dialog-hide>
				<?php esc_html_e( 'Cancel', 'wp-smushit' ); ?>
			</button>
			<button class="sui-button" id="smush-bulk-restore-button">
				<?php esc_html_e( 'Confirm', 'wp-smushit' ); ?>
			</button>
			<# } else if ( 'progress' === data.slide ) { #>
			<div class="sui-progress-block sui-progress-can-close">
				<div class="sui-progress">
					<span class="sui-progress-icon" aria-hidden="true">
						<i class="sui-icon-loader sui-loading"></i>
					</span>
					<div class="sui-progress-text">
						<span>0%</span>
					</div>
					<div class="sui-progress-bar" aria-hidden="true">
						<span style="width: 0"></span>
					</div>
				</div>
				<button class="sui-button-icon sui-tooltip" onclick="WP_Smush.restore.cancel()" type="button" data-tooltip="<?php esc_attr_e( 'Cancel', 'wp-smushit' ); ?>">
					<i class="sui-icon-close"></i>
				</button>
			</div>

			<div class="sui-progress-state">
				<span class="sui-progress-state-text">
					<?php esc_html_e( 'Initializing restore...', 'wp-smushit' ); ?>
				</span>
			</div>
			<# } else if ( 'finish' === data.slide ) { #>
			<div class="sui-notice sui-notice-success">
				<p>{{{ data.success }}}
					<?php esc_html_e( 'images were successfully restored.', 'wp-smushit' ); ?>
				</p>
			</div>
			<button class="sui-button" onclick="window.location.reload()" data-a11y-dialog-hide type="button">
				<?php esc_html_e( 'Finish', 'wp-smushit' ); ?>
			</button>
			<# } #>
		</div>
	</div>

	<?php if ( ! $this->hide_wpmudev_branding() ) : ?>
		<img class="sui-image sui-image-center"
			src="<?php echo esc_url( WP_SMUSH_URL . 'app/assets/images/onboarding/graphic-onboarding.png' ); ?>"
			srcset="<?php echo esc_url( WP_SMUSH_URL . 'app/assets/images/onboarding/graphic-onboarding@2x.png' ); ?> 2x"
			alt="<?php esc_attr_e( 'WP Smush', 'wp-smushit' ); ?>">
	<?php endif; ?>
</script>

<div class="sui-dialog sui-dialog-sm smush-restore-images-dialog" aria-hidden="true" tabindex="-1" id="smush-restore-images-dialog">
	<div class="sui-dialog-overlay sui-fade-in"></div>
	<div class="sui-dialog-content sui-bounce-in" aria-labelledby="dialogTitle" aria-describedby="dialogDescription" role="dialog">
		<div class="sui-box" role="document">
			<div id="smush-bulk-restore-content"></div>
			<?php wp_nonce_field( 'smush_bulk_restore' ); ?>
		</div>
	</div>
</div>
