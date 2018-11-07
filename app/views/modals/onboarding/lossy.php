<div class="sui-dialog sui-dialog-sm smush-onboarding-dialog" tabindex="-1" id="smush-onboarding-dialog-lossy">
	<div class="sui-dialog-overlay sui-fade-in"></div>
	<div class="sui-dialog-content sui-bounce-in" aria-labelledby="dialogTitle" aria-describedby="dialogDescription" role="dialog">
		<div class="sui-box" role="document">
			<div class="sui-box-header sui-dialog-with-image">
				<div class="sui-dialog-image" aria-hidden="true">
					<img src="<?php echo esc_url( WP_SMUSH_URL . 'app/assets/images/onboarding/lossy.png' ); ?>" alt="<?php esc_attr_e( 'Advanced Compression', 'wp-smushit' ); ?>" class="sui-image sui-image-center">
				</div>

				<h3 class="sui-box-title" id="dialogTitle">
					<?php esc_html_e( 'Advanced Compression', 'wp-smushit' ); ?>
				</h3>
			</div>

			<div class="sui-box-body">
				<p><?php esc_html_e( 'Optimize images up to 2x more than regular smush with our multi-pass lossy compression.', 'wp-smushit' ); ?></p>

				<div class="smush-onboarding-toggle">
					<label class="sui-toggle">
						<input type="checkbox" id="toggle-with-label">
						<span class="sui-toggle-slider"></span>
					</label>
					<label for="toggle-with-label" class="sui-toggle-label">
						<?php esc_html_e( 'Enable enhanced multi-pass lossy compression', 'wp-smushit' ); ?>
					</label>
				</div>

				<div class="smush-onboarding-arrows">
					<a href="#" class="previous" data-slide="smush-onboarding-dialog-auto" onclick="WP_Smush.onboarding.nav(this)">
						<i class="sui-icon-chevron-left" aria-hidden="true"></i>
					</a>
					<a href="#" class="next" data-slide="smush-onboarding-dialog-" onclick="WP_Smush.onboarding.nav(this)">
						<i class="sui-icon-chevron-right" aria-hidden="true"></i>
					</a>
				</div>
			</div>

			<div class="sui-box-footer">
				<div class="smush-onboarding-dots">
					<span></span>
					<span></span>
					<span class="active"></span>
					<span></span>
					<span></span>
					<span></span>
				</div>

				<?php wp_nonce_field( 'smush_quick_setup' ); ?>
				<a href="#" class="smush-onboarding-skip-link" data-a11y-dialog-hide>
					<?php esc_html_e( 'Skip this, I’ll set it up later', 'wp-smushit' ); ?>
				</a>
			</div>
		</div>
	</div>
</div>
