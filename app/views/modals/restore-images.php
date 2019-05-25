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

<div class="dialog sui-dialog sui-dialog-sm wp-smush-restore-images-dialog" aria-hidden="true" id="wp-smush-restore-images-dialog">
	<div class="sui-dialog-overlay" tabindex="-1" data-a11y-dialog-hide></div>
	<div class="sui-dialog-content" aria-labelledby="restoreImages" aria-describedby="dialogDescription" role="dialog">
		<div class="sui-box" role="document">
			<div class="sui-box-header">
				<h3 class="sui-box-title" id="restoreImages">
					<?php esc_html_e( 'Restore Thumbnails', 'wp-smushit' ); ?>
				</h3>
			</div>

			<div class="sui-box-body">
				<p><?php esc_html_e( 'Are you sure you want to restore all image thumbnails to their original, non-optimized states?', 'wp-smushit' ); ?></p>

				<div class="sui-block-content-center">
					<a class="sui-button sui-button-ghost" data-a11y-dialog-hide>
						<?php esc_html_e( 'Cancel', 'wp-smushit' ); ?>
					</a>
					<a class="sui-button" id="smush-bulk-restore-button">
						<?php esc_html_e( 'Confirm', 'wp-smushit' ); ?>
					</a>
				</div>
			</div>

			<?php if ( ! $this->hide_wpmudev_branding() ) : ?>
				<img class="sui-image sui-image-center"
					src="<?php echo esc_url( WP_SMUSH_URL . 'app/assets/images/onboarding/graphic-onboarding.png' ); ?>"
					srcset="<?php echo esc_url( WP_SMUSH_URL . 'app/assets/images/onboarding/graphic-onboarding@2x.png' ); ?> 2x"
					alt="<?php esc_attr_e( 'WP Smush', 'wp-smushit' ); ?>">
			<?php endif; ?>
		</div>
	</div>
</div>
