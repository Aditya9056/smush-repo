<?php
/**
 * CDN meta box.
 *
 * @since 3.0
 * @package WP_Smush
 *
 * @var string $status      CDN status: warning (inactive), success (active) or error (expired).
 * @var array  $status_msg  Array of CDN status messages.
 */

?>

<p>
	<?php
	esc_html_e( 'Take load off your server by serving your images from our blazing-fast CDN.', 'wp-smushit' );
	?>
</p>

<div class="sui-notice sui-notice-<?php echo esc_attr( $status ); ?> smush-notice-sm">
	<p><?php echo esc_html( $status_msg[ $status ] ); ?></p>

	<?php if ( 'error' === $status ) : ?>
		<div class="sui-notice-buttons">
			<a href="#" class="sui-button">
				<?php esc_html_e( 'Upgrade Plan', 'wp-smushit' ); ?>
			</a>
		</div>
	<?php endif; ?>
</div>

<div class="sui-box-settings-row">
	<div class="sui-box-settings-col-1">
		<span class="sui-settings-label">
			<?php esc_html_e( 'Quota & Bandwidth', 'wp-smushit' ); ?>
		</span>
		<span class="sui-description">
			<?php
			esc_html_e( 'Monitor how much your websites are using the CDN. If you reach a cap it’s easy to
			upgrade to grab more bandwidth.', 'wp-smushit' );
			?>
		</span>
	</div>
	<div class="sui-box-settings-col-2">
		<div class="smush-cdn-quota-boxes">
			<div class="sui-border-frame">
				<span>0KB</span>
				<span class="sui-description">
					<?php esc_html_e( 'Bandwidth', 'wp-smushit' ); ?>
				</span>
			</div>

			<div class="sui-border-frame sui-left">
				<span>0</span>
				<span class="sui-description">
					<?php esc_html_e( 'Requests', 'wp-smushit' ); ?>
				</span>
			</div>
		</div>

		<span class="sui-description sui-toggle-description sui-no-margin-left">
			<?php
			printf(
				/* translators: %1$s: opening A (href) tag, %2$s; closing A (href) tag. */
				esc_html__( 'Note: Your current plan included 10GB bandwidth to use over 30 days.
				%1$sUpgrade Plan%2$s for more bandwidth.', 'wp-smushit' ),
				'<a href="#" target="_blank">',
				'</a>'
			);
			?>
		</span>
	</div>
</div>

<div class="sui-box-settings-row">
	<div class="sui-box-settings-col-1">
		<span class="sui-settings-label">
			<?php esc_html_e( 'Supported Media Types', 'wp-smushit' ); ?>
		</span>
		<span class="sui-description">
			<?php
			esc_html_e( 'Here’s a list of the media types we will serve from the CDN.', 'wp-smushit' );
			?>
		</span>
	</div>
	<div class="sui-box-settings-col-2">
		<span class="smush-filename-extension smush-extension-jpg">
			<?php esc_html_e( 'jpg', 'wp-smushit' ); ?>
		</span>
		<span class="smush-filename-extension smush-extension-png">
			<?php esc_html_e( 'png', 'wp-smushit' ); ?>
		</span>
		<span class="smush-filename-extension smush-extension-gif">
			<?php esc_html_e( 'gif', 'wp-smushit' ); ?>
		</span>
		<span class="smush-filename-extension smush-extension-webp">
			<?php esc_html_e( 'webp', 'wp-smushit' ); ?>
		</span>

		<span class="sui-description sui-toggle-description sui-no-margin-left">
			<?php
			esc_html_e( 'Note: At this time we don’t support video media types. We recommend uploading media to a
			third-party provider and embedding videos into your posts/pages.', 'wp-smushit' );
			?>
		</span>
	</div>
</div>

<div class="sui-box-settings-row">
	<div class="sui-box-settings-col-1">
		<span class="sui-settings-label">
			<?php esc_html_e( 'Automatic Resizing', 'wp-smushit' ); ?>
		</span>
		<span class="sui-description">
			<?php
			esc_html_e( 'If your images don’t match their containers, we’ll automatically serve a correctly
			sized image.', 'wp-smushit' );
			?>
		</span>
	</div>
	<div class="sui-box-settings-col-2">
		<label class="sui-toggle">
			<input type="checkbox" id="auto-resize">
			<span class="sui-toggle-slider"></span>
		</label>
		<label for="auto-resize">
			<?php esc_html_e( 'Enable automatic resizing of my images', 'wp-smushit' ); ?>
		</label>

		<span class="sui-description sui-toggle-description">
			<?php
			esc_html_e( 'Having trouble with Google PageSpeeds ‘compress and resize’ suggestion? This feature will
			fix this without any coding needed! Note: No resizing is done on your actual images, only what is served
			from the CDN - so your original images will remain untouched.', 'wp-smushit' );
			?>
		</span>
	</div>
</div>

<div class="sui-box-settings-row">
	<div class="sui-box-settings-col-1">
		<span class="sui-settings-label">
			<?php esc_html_e( 'Super-smush', 'wp-smushit' ); ?>
		</span>
		<span class="sui-description">
			<?php
			esc_html_e( 'Optimize images up to 2x more than regular smush with our multi-pass lossy
			compression.', 'wp-smushit' );
			?>
		</span>
	</div>
	<div class="sui-box-settings-col-2">
		<label class="sui-toggle">
			<input type="checkbox" id="super-smush">
			<span class="sui-toggle-slider"></span>
		</label>
		<label for="super-smush">
			<?php esc_html_e( 'Super-smush my images', 'wp-smushit' ); ?>
		</label>
	</div>
</div>

<div class="sui-box-settings-row">
	<div class="sui-box-settings-col-1">
		<span class="sui-settings-label">
			<?php esc_html_e( 'WebP conversion', 'wp-smushit' ); ?>
		</span>
		<span class="sui-description">
			<?php
			esc_html_e( 'Smush can automatically convert and serve your images as WebP to compatible
			browsers.', 'wp-smushit' );
			?>
		</span>
	</div>
	<div class="sui-box-settings-col-2">
		<label class="sui-toggle">
			<input type="checkbox" id="webp">
			<span class="sui-toggle-slider"></span>
		</label>
		<label for="webp">
			<?php esc_html_e( 'Enable WebP conversion', 'wp-smushit' ); ?>
		</label>

		<span class="sui-description sui-toggle-description">
			<?php
			esc_html_e( 'Note: We’ll detect and serve WebP images to browsers that will accept them by checking
			Accept Headers, and gracefully fall back to normal PNGs or JPEGs for non-compatible
			browsers.', 'wp-smushit' );
			?>
		</span>
	</div>
</div>

<div class="sui-box-settings-row">
	<div class="sui-box-settings-col-1">
		<span class="sui-settings-label">
			<?php esc_html_e( 'Meta Data', 'wp-smushit' ); ?>
		</span>
		<span class="sui-description">
			<?php
			esc_html_e( 'Whenever you take a photo, your camera stores metadata, such as focal length, date,
			time and location, within the image.', 'wp-smushit' );
			?>
		</span>
	</div>
	<div class="sui-box-settings-col-2">
		<label class="sui-toggle">
			<input type="checkbox" id="strip-metadata">
			<span class="sui-toggle-slider"></span>
		</label>
		<label for="strip-metadata">
			<?php esc_html_e( 'Strip my image meta data', 'wp-smushit' ); ?>
		</label>

		<span class="sui-description sui-toggle-description">
			<?php
			esc_html_e( 'Note: This data adds to the size of the image. While this information might be important
			to photographers, it’s unnecessary for most users and safe to remove.', 'wp-smushit' );
			?>
		</span>
	</div>
</div>

<div class="sui-box-settings-row">
	<div class="sui-box-settings-col-1">
		<span class="sui-settings-label">
			<?php esc_html_e( 'PNG to JPEG conversion', 'wp-smushit' ); ?>
		</span>
		<span class="sui-description">
			<?php
			esc_html_e( 'When you compress a PNG, Smush will check if converting it to JPEG could further
			reduce its size.', 'wp-smushit' );
			?>
		</span>
	</div>
	<div class="sui-box-settings-col-2">
		<label class="sui-toggle">
			<input type="checkbox" id="png-to-jpg">
			<span class="sui-toggle-slider"></span>
		</label>
		<label for="png-to-jpg">
			<?php esc_html_e( 'Auto-convert PNGs to JPEGs (lossy)', 'wp-smushit' ); ?>
		</label>

		<span class="sui-description sui-toggle-description">
			<?php
			esc_html_e( 'Note: Any PNGs with transparency will be ignored. Smush will only convert PNGs if it
			results in a smaller file size. The resulting file will have a new filename and extension (JPEG), and any
			hard-coded URLs on your site that contain the original PNG filename will need to be
			updated.', 'wp-smushit' );
			?>
		</span>
	</div>
</div>

<div class="sui-box-settings-row">
	<div class="sui-box-settings-col-1">
		<span class="sui-settings-label">
			<?php esc_html_e( 'Deactivate', 'wp-smushit' ); ?>
		</span>
		<span class="sui-description">
			<?php
			esc_html_e( 'If you no longer require your images hosted from our CDN you can disable
			this feature.', 'wp-smushit' );
			?>
		</span>
	</div>
	<div class="sui-box-settings-col-2">
		<button class="sui-button sui-button-ghost">
			<?php esc_html_e( 'Cancel Activation', 'wp-smushit' ); ?>
		</button>
		<span class="sui-description sui-toggle-description sui-no-margin-left">
			<?php
			esc_html_e( 'Note: You won’t lose any imagery by deactivating, all of your attachments are still
			stored locally on your own server.', 'wp-smushit' );
			?>
		</span>
	</div>
</div>
