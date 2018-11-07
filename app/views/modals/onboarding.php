<div class="sui-dialog sui-dialog-sm" tabindex="-1" id="smush-onboarding-dialog">
	<div class="sui-dialog-overlay sui-fade-in"></div>
	<div class="sui-dialog-content sui-bounce-in" aria-labelledby="dialogTitle" aria-describedby="dialogDescription" role="dialog">
		<div class="sui-box" role="document">
			<div class="sui-box-header sui-dialog-with-image">
				<div class="sui-dialog-image" aria-hidden="true">
					<!--<img src="dist/images/activecampaign.png" srcset="dist/images/activecampaign.png 1x, dist/images/activecampaign@2x.png 2x" alt="Active Campaign" class="sui-image sui-image-center">-->
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

                <a href="#" class="smush-onboarding-skip-link">
                    <?php esc_html_e( 'Skip this, I’ll set it up later', 'wp-smushit' ); ?>
                </a>
			</div>
		</div>
	</div>
</div>

<style>
    .sui-dialog.sui-dialog-sm .sui-dialog-content {
        max-width: 560px !important;
    }

    .sui-box-header.sui-dialog-with-image {
        padding-top: 160px !important;
    }

	.sui-box-header .sui-dialog-image {
		width: 500px !important;
		height: 160px !important;
		margin-left: -250px !important;
		background-color: #ccc !important;
	}

	.sui-box .sui-box-header .sui-box-title {
		font: bold 22px/30px "Roboto", Arial, sans-serif !important;
	}

	.sui-box-body p {
		color: #888 !important;
        max-width: 340px;
        margin: 0 auto;
	}

    .smush-onboarding-dots {
        display: flex;
        margin-top: 20px;
    }

    .smush-onboarding-dots span {
        height: 7px;
        width: 7px;
        border-radius: 50%;
        background-color: #E6E6E6;
        margin: 0 5px;
    }

    .smush-onboarding-dots span.active {
        background-color: #666666;
    }

    .smush-onboarding-skip-link {
        position: absolute;
        bottom: -50px;
        opacity: 0.7;
        font-size: 13px;
        color: #fff !important;
        letter-spacing: -0.25px;
        line-height: 22px;
    }
</style>

<script type="text/javascript">
    $(window).on('load', function () {
        jQuery(document).ready(function () {
            SUI.dialogs['smush-onboarding-dialog'].show();
        });
    });
</script>