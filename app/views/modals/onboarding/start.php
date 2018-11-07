<?php
/**
 * Onboarding modal.
 *
 * @since 3.1
 * @package WP_Smush
 */

$this->view( 'modals/onboarding/auto' );
$this->view( 'modals/onboarding/lossy' );
?>

<div class="sui-dialog sui-dialog-sm smush-onboarding-dialog" tabindex="-1" id="smush-onboarding-dialog">
	<div class="sui-dialog-overlay sui-fade-in"></div>
	<div class="sui-dialog-content sui-bounce-in" aria-labelledby="dialogTitle" aria-describedby="dialogDescription" role="dialog">
		<div class="sui-box" role="document">
			<div class="sui-box-header sui-dialog-with-image">
				<div class="sui-dialog-image" aria-hidden="true">
                    <img src="<?php echo esc_url( WP_SMUSH_URL . 'app/assets/images/onboarding/start.png' ); ?>" alt="<?php esc_attr_e( 'Smush On-Boarding Modal', 'wp-smushit' ); ?>" class="sui-image sui-image-center">
				</div>

				<h3 class="sui-box-title" id="dialogTitle">
                    <?php
                    $current_user = wp_get_current_user();
                    printf(
                        /* translators: %s: current user name */
                        esc_html__( 'Hey, %s!', 'wp-smushit' ),
	                    $current_user->display_name
                    );
                    ?>
                </h3>
			</div>

			<div class="sui-box-body">
				<p><?php esc_html_e( 'Nice work installing Smush! Let’s get started by choosing how you want this plugin to work, and then let Smush do all the heavy lifting for you.', 'wp-smushit' ); ?></p>

				<button type="button" class="sui-button sui-button-primary sui-button-icon-right" data-a11y-dialog-hide="smush-onboarding-dialog">
					<?php esc_html_e( 'Begin setup', 'wp-smushit' ); ?>
					<i class="sui-icon-chevron-right" aria-hidden="true"></i>
				</button>

                <div class="smush-onboarding-arrows">
                    <a href="#" class="previous sui-hidden">
                        <i class="sui-icon-chevron-left" aria-hidden="true"></i>
                    </a>
                    <a href="#" class="next" data-slide="smush-onboarding-dialog-auto" onclick="WP_Smush.onboarding.nav(this)">
                        <i class="sui-icon-chevron-right" aria-hidden="true"></i>
                    </a>
                </div>
			</div>

			<div class="sui-box-footer">
				<div class="smush-onboarding-dots">
                    <span class="active"></span>
                    <span></span>
                    <span></span>
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
