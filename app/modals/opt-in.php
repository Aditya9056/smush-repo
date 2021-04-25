<?php
/**
* Show Opt-in modal.
*
* @since 3.7.0
*/

if ( ! defined( 'WPINC' ) ) {
	die;
}

$mc_user_id = '53a1e972a043d1264ed082a5b';
$mc_list_id = '4b14b58816';
$action = 'https://edublogs.us1.list-manage.com/subscribe/post-json?u='.$mc_user_id.'&id='.$mc_list_id.'&c=?';
$admin_email = get_site_option( 'admin_email' );


?>

<div class="sui-modal sui-modal-md">
	<div
		role="dialog"
		id="smush-opt-in-dialog"
		class="sui-modal-content smush-opt-in-dialog"
		aria-modal="true"
		aria-labelledby="smush-title-opt-in-dialog"
	>
		<div class="sui-box">
			<div class="sui-box-header sui-flatten sui-content-center sui-spacing-sides--20">
				<figure class="sui-box-banner" aria-hidden="true">
					<img src="<?php echo esc_url( WP_SMUSH_URL . 'app/assets/images/opt-in/opt-in.png' ); ?>"
						srcset="<?php echo esc_url( WP_SMUSH_URL . 'app/assets/images/opt-in/opt-in.png' ); ?> 1x, <?php echo esc_url( WP_SMUSH_URL . 'app/assets/images/opt-in/opt-in' ); ?>@2x.png 2x"
						alt="<?php esc_attr_e( 'Smush Opt-in Modal', 'wp-smushit' ); ?>" class="sui-image sui-image-center">
				</figure>

				<button class="sui-button-icon sui-button-float--right" data-modal-close="" onclick="WP_Smush.onboarding.hide_opt_in_modal()">
					<i class="sui-icon-close sui-md" aria-hidden="true"></i>
				</button>
			</div>

			<div class="sui-box-body sui-content-center sui-spacing-sides--30 sui-spacing-top--0">
				<h3 class="sui-box-title sui-lg" id="smush-title-opt-in-dialog" style="white-space: normal">
					<?php esc_html_e( 'Get the most out of Smush', 'wp-smushit' ); ?>
				</h3>

				<p class="sui-description">
					<?php esc_html_e( 'Ready to get started harnessing Smush’s full image-optimizing potential?', 'wp-smushit' ); ?>
				</p>

				<p class="sui-description">
					<?php esc_html_e( 'Subscribe below to receive the latest Smush news, along with some practical tips and tricks - including how to configure Smush to', 'wp-smushit' ); ?>
					<strong> <?php esc_html_e( 'resolve all 4 of Google’s PageSpeed image recommendations.', 'wp-smushit' ); ?></strong>
				</p>

				<form class="sui-box sui-flatten sui-content-center sui-spacing-sides--20" id="opt_in_form" value="<?php echo esc_attr( $admin_email ); ?>">
					<div class="sui-form-field">
						<div class="sui-control-with-icon" style="margin-left: calc(100% - 76%);">
							<span class="sui-icon-mail" aria-hidden="true"></span>
							<label for="user-email" id="label-user-email" class="sui-screen-reader-text">User Email</label>
							<input placeholder="Your Email" name="user_email" id="user-email" class="sui-form-control sui-input-md" aria-labelledby="label-user-email" aria-describedby="error-unique-id description-unique-id" />
						</div>
					</div>

					<div class="sui-form-field">
						<label for="whip-news" class="sui-checkbox sui-checkbox-sm">
							<input type="checkbox" id="whip-news" aria-labelledby="label-whip-news" />
							<span aria-hidden="true"></span>
							<span id="label-whip-news">Also subscribe to <a href="#">The Whip Newsletter</a></span>
						</label>

						<div style="margin-left: calc(100% - 76%); max-width: 274px;">
							<p class="sui-description">
								<?php esc_html_e( 'The WhiP serves you the latest WP news and resources - with a refreshing and punny twist!', 'wp-smushit' ); ?>
							</p>
						</div>
						<!--
						NOTE:
						Notice error message element it is empty. This because content should be printed
						when error happens and not before to avoid screenreaders confusing users.
						-->
						<span id="error-unique-id" class="sui-error-message" style="display: none;" role="alert"></span>
					</div>

					<button type="submit" class="sui-button sui-button-blue"  data-modal-close="" onclick="WP_Smush.onboarding.submit_opt_in_modal()" role="button">
						<i class="sui-icon-send" aria-hidden="true"></i> GET TRICKS
					</button>
				</form>
			</div>
		</div>
	</div>
</div>
