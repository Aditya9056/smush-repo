<?php
/**
 * @package WP Smush
 * @subpackage Admin
 * @version 1.0
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2016, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmushBulkUi' ) ) {
	/**
	 * Show settings in Media settings and add column to media library
	 *
	 */

	/**
	* Class WpSmushBulkUi
    */
	class WpSmushBulkUi {

		function __construct() {
			//Add a container for Smush Pro promo
			add_action('wp_smush_after_stats_box', array($this, 'wp_smush_promo') );
			//Add a Container for Hummingbird Promo
			add_action('wp_smush_after_stats_box', array($this, 'wp_smush_hummingbird_promo') );
		}

		/**
        * Prints the Header Section for a container as per the Shared UI
		*
		* @param string $classes Any additional classes that needs to be added to section
		* @param string $heading Box Heading
		* @param string $sub_heading Any additional text to be shown by the side of Heading
		* @param bool $dismissible If the Box is dimissible
		*
		* @return string
        */
		function container_header( $classes = '', $heading = '', $sub_heading = '', $dismissible = false ) {
			if( empty( $heading ) ) {
				return '';
			}
			echo '<section class="dev-box ' . $classes . '" id="wp-smush-container">'; ?>
			<div class="wp-smush-container-header box-title">
				<h3><?php echo $heading ?></h3><?php
				//Sub Heading
				if( !empty( $sub_heading ) ) {?>
					<div class="smush-container-subheading"><?php echo $sub_heading ?></div><?php
				}
				//Dismissible
				if( $dismissible ) {?>
					<div class="float-r smush-dismiss-welcome">
						<a href="#" title="<?php esc_html_e( "Dismiss Welcome notice", "wp-smushit" ); ?>">
							<i class="wdv-icon wdv-icon-fw wdv-icon-remove"></i>
						</a>
					</div><?php
				} ?>
			</div><?php
		}

		/**
		*  Prints the content of WelCome Screen for New Installation
        *  Dismissible by default
        */
		function welcome_screen() {
			global $WpSmush, $wpsmushit_admin;

			//Header Of the Box
			$this->container_header( 'wp-smush-welcome', esc_html__( "WELCOME", "wp-smushit" ), '', true );

			$user_name = $wpsmushit_admin->get_user_name();

			//Settings Page heading
			$plugin_name = $WpSmush->is_pro() ? "WP Smush Pro" : "WP Smush";
			?>
			<!-- Content -->
			<div class="box-content">
				<div class="col-third">
					<img src="<?php echo WP_SMUSH_URL . 'assets/images/DEV-Man-Running.png'; ?>"
					     alt="<?php esc_html_e( "Welcome Screen - DEV Man Running", "wp-smushit" ); ?>">
				</div>
				<div class="col-half wp-smush-welcome-content">
					<h4><?php esc_html_e("OH YEAH, IT'S COMPRESSION TIME", "wp-smushit"); ?></h4>
					<p class="wp-smush-welcome-message"><?php printf( esc_html__(' %1$s Nice one, %3$s%2$s! You\'ve just installed %4$s, the hottest image compression plugin for WordPress that will reduce your image sizes significantly! We\'ve already applied recommended settings, which you can change anytime. %1$sGet started by running your first Smush!%2$s', "wp-smushit"), '<strong>','</strong>', $user_name, $plugin_name ); ?></p>
				</div>
			</div><?php
			echo "</section>";
		}

		/**
		* Bulk Smush UI and Progress bar
		*/
		function bulk_smush_container() {
			$smush_individual_msg = sprintf( esc_html__( "Smush individual images via your %sMedia Library%s", "wp-smushit" ), '<a href="' . esc_url( admin_url( 'upload.php' ) ) . '" title="' . esc_html__( 'Media Library', 'wp-smushit' ) . '">', '</a>' );
			$this->container_header( 'bulk-smush-wrapper', esc_html__( "BULK SMUSH", "wp-smushit" ), $smush_individual_msg ); ?>
			<div class="box-container"><?php
				$this->bulk_smush_content(); ?>
			</div><?php
			echo "</section>";
		}

		/**
		* All the settings for Basic and Advanced Users
		*/
		function settings_ui() {
			global $wpsmushit_admin;
			$class = $wpsmushit_admin->is_pro() ? 'smush-settings-wrapper wp-smush-pro' : 'smush-settings-wrapper';
			$this->container_header($class, esc_html__("SETTINGS", "wp-smushit"), '' );
			// display the options
			$this->options_ui();
		}

		/**
		 * Outputs the Smush stats for the site
		 */
		function smush_stats_container() {
			global $WpSmush, $wpsmushit_admin;
			$this->container_header('smush-stats-wrapper', esc_html__("STATS", "wp-smushit"), '' ); ?>
			<div class="box-content">
				<div class="row smush-total-reduction-percent">
					<span class="float-l wp-smush-stats-label"><strong><?php esc_html_e( "TOTAL % REDUCTIONS", "wp-smushit" ); ?></strong></span>
					<span class="float-r wp-smush-stats"><strong><?php echo $wpsmushit_admin->stats['percent'] > 0  ? number_format_i18n( $wpsmushit_admin->stats['percent'], 2, '.', '' ) : 0; ?>%</strong></span>
				</div>
				<hr>
				<div class="row smush-total-reduction-bytes">
					<span class="float-l wp-smush-stats-label"><strong><?php esc_html_e( "TOTAL SIZE REDUCTIONS", "wp-smushit" ); ?></strong></span>
					<span class="float-r wp-smush-stats"><strong><?php echo $wpsmushit_admin->stats['human'] > 0  ? $wpsmushit_admin->stats['human'] : "0MB"; ?></strong></span>
				</div>
				<hr>
				<div class="row smush-attachments">
					<span class="float-l wp-smush-stats-label"><strong><?php esc_html_e( "ATTACHMENTS SMUSHED", "wp-smushit" ); ?></strong></span>
					<span class="float-r wp-smush-stats"><strong><span class="smushed-count"><?php echo intval( $wpsmushit_admin->smushed_count ) . '</span>/' . $wpsmushit_admin->total_count; ?></strong></span>
				</div><?php
				if( $WpSmush->is_pro() ) {?>
					<hr>
					<div class="row super-smush-attachments">
						<span class="float-l wp-smush-stats-label"><strong><?php esc_html_e( "ATTACHMENTS SUPER-SMUSHED", "wp-smushit" ); ?></strong></span>
						<span class="float-r wp-smush-stats"><strong><span class="smushed-count"><?php echo intval( $wpsmushit_admin->super_smushed ) . '</span>/' . $wpsmushit_admin->total_count; ?></strong></span>
					</div><?php
				}
				/**
				 * Allows you to output any content within the stats box at the end
				 */
				do_action('wp_smush_after_stats');
				?>
			</div><?php
			echo "</section>";
		}

		/**
		* Outputs the advanced settings for Pro users, Disabled for basic users by default
		*/
		function advanced_settings() {
			global $WpSmush, $wpsmushit_admin;

			//Content for the End of box container
			$div_end =
					wp_nonce_field( 'save_wp_smush_options', 'wp_smush_options_nonce', '', false ) .
					'<input type="submit" id="wp-smush-save-settings" class="button button-primary"
					       value="' . esc_html__( 'UPDATE SETTINGS', 'wp-smushit' ) .'">
					</form>
				</div><!-- Box Content -->
			</section><!-- Main Section -->';

			//For Basic User, Show advanced settings in a separate box
			if( ! $WpSmush->is_pro() ) {
				echo $div_end;
				$this->container_header( 'wp-smush-premium', esc_html__( "ADVANCED SETTINGS", "wp-smushit" ), esc_html__( 'PRO ONLY', 'wp-smushit' ), false );?>
				<div class="box-content"><?php
			}

			//Available advanced settings
			$pro_settings = array(
				'original',
				'lossy',
				'backup',
				'nextgen'
			);

			if( $WpSmush->is_pro() ) {
				echo "<hr />";
			}

			//Used for printing separator
			$numItems = count( $pro_settings );
			$i = 0;

			//Iterate Over all the available settings, and print a row for each of them
			foreach( $pro_settings as $setting_key ) {
				$setting_m_key = WP_SMUSH_PREFIX. $setting_key;
				$setting_val = $WpSmush->is_pro() ? get_option( $setting_m_key, false ) : 0;?>
				<div class='wp-smush-setting-row wp-smush-advanced'>
					<label class="inline-label"for="<?php echo $setting_m_key; ?>">
					<span class="wp-smush-setting-label"><?php echo $wpsmushit_admin->settings[$setting_key]['label']; ?></span>
					       <br/>
					       <small class="smush-setting-description">
					        <?php echo $wpsmushit_admin->settings[$setting_key]['desc']; ?>
					       </small>
			        </label>
					<span class="toggle float-r">
						<input type="checkbox" class="toggle-checkbox"
						       id="<?php echo $setting_m_key; ?>" <?php checked( $setting_val, 1, true ); ?> value="1" name="<?php echo $setting_m_key; ?>">
						<label class="toggle-label" for="<?php echo $setting_m_key; ?>"></label>
					</span>
				</div><?php
				if(++$i != $numItems) {
					echo "<hr />";
				}
			}
			//Output Form end and Submit button for pro version
			if( $WpSmush->is_pro() ) {
				echo  $div_end;
			}else{
				echo "</div><!-- Box Content -->
				</section><!-- Main Section -->";
			}
		}

		/**
		 * Adds progress bar for ReSmush bulk, if there are any images, that needs to be resmushed
		 */
		function resmush_bulk_ui( $return = false ) {

			global $wpsmushit_admin;

			$count = count( $wpsmushit_admin->resmush_ids );

			//Notice: Number of images that can be smushed
			$ss_progress_ui = '<div class="wp-smush-resmush-wrap">
				<div class="wp-smush-notice wp-smush-remaining">
					<i class="dev-icon"><img src="'. WP_SMUSH_URL . 'assets/images/icon-gzip.svg"></i>' . sprintf( esc_html__( "%s, you have %s%d images%s that can be further optimised with current settings.", "wp-smushit" ), $wpsmushit_admin->get_user_name(), '<strong>', $count, '</strong>' )
				.'</div>
				<hr  class="wp-smush-sep" />';

			$ss_progress_ui .= $wpsmushit_admin->setup_button( true, true ) . '</div>';
			//If need to return the content
			if ( $return ) {
				return $ss_progress_ui;
			}

			echo $ss_progress_ui;
		}

		/**
		 * Process and display the options form
		 */
		function options_ui() {
			global $wpsmushit_admin;
			echo '<div class="box-container">
				<form action="" method="post">';
			//Smush auto key
			$opt_auto = WP_SMUSH_PREFIX . 'auto';
			//Auto value
			$opt_auto_val = get_option( $opt_auto, false );

			//If value is not set for auto smushing set it to 1
			if ( $opt_auto_val === false ) {
				//default to checked
				$opt_auto_val = 1;
			}

			//Keep Exif
			$opt_keep_exif = WP_SMUSH_PREFIX . 'keep_exif';
			//Keep Exif
			$opt_keep_exif_val = get_option( $opt_keep_exif, false ); ?>
			<div class='wp-smush-setting-row wp-smush-basic'>
				<label class="inline-label"
				       for="<?php echo $opt_auto; ?>"><span
						class="wp-smush-setting-label"><?php echo $wpsmushit_admin->settings['auto']['label']; ?></span><br/>
					<small
						class="smush-setting-description"><?php echo $wpsmushit_admin->settings['auto']['desc']; ?></small>
				</label>
					<span class="toggle float-r">
						<input type="checkbox" class="toggle-checkbox"
						       id="<?php echo $opt_auto; ?>"
						       name="<?php echo $opt_auto; ?>" <?php checked( $opt_auto_val, 1, true ); ?> value="1">
						<label class="toggle-label" for="<?php echo $opt_auto; ?>"></label>
					</span>
			</div>
			<hr/>
			<div class='wp-smush-setting-row wp-smush-basic'>
				<label class="inline-label" for="<?php echo $opt_keep_exif; ?>"><span
						class="wp-smush-setting-label"><?php echo $wpsmushit_admin->settings['keep_exif']['label']; ?></span>
					<br/>
					<small class="smush-setting-description">
						<?php echo $wpsmushit_admin->settings['keep_exif']['desc']; ?>
					</small>
				</label>
						<span class="toggle float-r">
							<input type="checkbox" class="toggle-checkbox"
							       id="<?php echo $opt_keep_exif; ?>" <?php checked( $opt_keep_exif_val, 1, true ); ?>
							       value="1" name="<?php echo $opt_keep_exif; ?>">
							<label class="toggle-label" for="<?php echo $opt_keep_exif; ?>"></label>
						</span>
			</div> <!-- End of Basic Settings --><?php

			do_action( 'wp_smush_after_basic_settings' );
			$this->advanced_settings();
		}

		/**
		 * Display the ui
		 */
		function ui() {
			global $WpSmush, $wpsmushit_admin;
			//Include Shared UI
			require_once WP_SMUSH_DIR . 'assets/shared-ui/plugin-ui.php';

			//Initialize global Stats
			$wpsmushit_admin->setup_global_stats();

			//Page Heading for Free and Pro Version
			$page_heading = $WpSmush->is_pro() ? esc_html__( 'WP Smush Pro', 'wp-smushit' ) : esc_html__( 'WP Smush', 'wp-smushit' );

			$auto_smush_message = $WpSmush->is_auto_smush_enabled() ? sprintf( esc_html__( "Automatic smushing is %senabled%s. Newly uploaded images will be automagically compressed." ), '<span class="wp-smush-auto-enabled">', '</span>' ) : sprintf( esc_html__( "Automatic smushing is %sdisabled%s. Newly uploaded images will need to be manually smushed." ), '<span class="wp-smush-auto-disabled">', '</span>' );
			?>
			<div class="wrap">
				<div class="wp-smush-page-header">
					<h1 class="wp-smush-page-heading"><?php echo $page_heading; ?></h1>
					<div class="wp-smush-auto-message"><?php echo $auto_smush_message; ?></div>
				</div>
				<div class="row wp-smushit-container-wrap"><?php

					//Show welcome message for only a new installation and for only network admins
					if ( 1 != get_option( 'hide_smush_welcome' ) && 1 != get_option( 'hide_smush_features' ) && 0 >= $wpsmushit_admin->smushed_count && is_super_admin() ) { ?>
						<div class="block float-l smush-welcome-wrapper">
						<?php $this->welcome_screen(); ?>
						</div><?php
					} ?>

					<!-- Bulk Smush Progress Bar -->
					<div class="wp-smushit-container-left col-two-third float-l"><?php
						//Bulk Smush Container
						$this->bulk_smush_container();
						//Bulk Re Smush Container
						$this->bulk_re_smush_container();
						//Settings
						$this->settings_ui();
						?>
					</div>

					<!-- Stats -->
					<div class="wp-smushit-container-right col-third float-l"><?php
						//Stats
						$this->smush_stats_container();
						if ( ! $WpSmush->is_pro() ) {
							/**
							 * Allows to Hook in Additional Containers after Stats Box for free version
							 * Pro Version has a full width settings box, so we don't want to do it there
							 */
							do_action( 'wp_smush_after_stats_box' );
						} ?>
					</div>
				</div>
			</div>
			<?php
			$wpsmushit_admin->print_loader();
		}

		/**
		 * Pro Version
		 */
		function wp_smush_promo() {
			global $wpsmushit_admin;
			$this->container_header( 'wp-smush-pro-adv', "TRY WP SMUSH PRO - FREE!" ); ?>
			<div class="box-content">
				<p class="wp-smush-promo-content">Get access to not only WP Smush, but 100+ premium plugins, Upfront
					themes, security & performance solutions and 24/7 expert support to make you fly – best of all, it’s
					<strong>absolutely FREE to try!</strong></p>
				<p class="wp-smush-promo-content-smaller tc">Join 389,434 happy members today with no lock in and 100%
					GPL, cancel any time and use forever on unlimited sites for only $49 p/m</p>
				<span class="wp-smush-pro-cta tc"><a href="<?php echo esc_url( $wpsmushit_admin->upgrade_url ); ?>" class="button button-cta button-green">START 14 DAY FREE
						TRIAL</a></span>
			</div>
			<img src="<?php echo WP_SMUSH_URL . 'assets/images/smush-pro.png'; ?>"
			     alt="<?php esc_html_e( "TRY WP SMUSH PRO - DEV TEAM", "wp-smushit" ); ?>"><?php
			echo "</section>";
		}

		/**
		 * HummingBird Promo
		 */
		function wp_smush_hummingbird_promo() {
			$this->container_header( 'wp-smush-hb-adv', "BOOST YOUR PERFORMANCE" ); ?>
			<div class="box-content">
			<span class="wp-smush-hummingbird-image tc">
					<img src="<?php echo WP_SMUSH_URL . 'assets/images/hummingbird.png'; ?>"
					     alt="<?php esc_html_e( "BOOST YOUR PERFORMANCE - HUMMINGBIRD", "wp-smushit" ); ?>">
		        </span>
			<p class="wp-smush-promo-content tc">Hummingbird enables file compression and browser caching, file
				minification and performance reports – because when it comes to pagespeed, every millisecond
				counts.</strong></p>
			<span class="wp-smush-hb-cta tc"><a href="#" class="button button-cta button-yellow">TRY
					HUMMINGBIRD</a></span>
			</div><?php
			echo "</section>";
		}

		/**
		* Outputs the Content for Bulk Smush Div
	    */
		function bulk_smush_content() {
			global $WpSmush, $wpsmushit_admin;
			$all_done = $wpsmushit_admin->smushed_count == $wpsmushit_admin->total_count;

			//If there are no images in Media Library
			if ( 0 >= $wpsmushit_admin->total_count ) { ?>
				<span class="wp-smush-no-image tc"><img
						src="<?php echo WP_SMUSH_URL . 'assets/images/upload-images.png'; ?>"
						alt="<?php esc_html_e( "No attachments found - Upload some images", "wp-smushit" ); ?>">
		        </span>
				<p class="wp-smush-no-images-content tc"><?php printf( esc_html__( "We haven’t found any images in your %smedia library%s yet so there’s no smushing to be done! Once you upload images, reload this page and start playing!", "wp-smushit" ), '<a href="' . esc_url( admin_url( 'upload.php' ) ) . '">', '</a>' ); ?></p>
				<span class="wp-smush-upload-images tc"><a class="button button-cta"
				                                           href="<?php echo esc_url( admin_url( 'media-new.php' ) ); ?>"><?php esc_html_e( "UPLOAD IMAGES", "wp-smushit" ); ?></a>
				</span><?php
			} else { ?>
				<!-- Hide All done div if there are images pending -->
				<div class="wp-smush-notice wp-smush-all-done<?php echo $all_done ? '' : ' hidden'?>">
					<i class="dev-icon dev-icon-tick"></i><?php esc_html_e( "You have 0 attachments that need smushing, awesome!", "wp-smushit" ); ?>
				</div>
				<div class="wp-smush-bulk-wrapper <?php echo $all_done ? ' hidden' : ''; ?>"><?php
					//If all the images in media library are smushed
					//Button Text
					if( $WpSmush->is_pro() ) {
						$button_content = esc_html__("BULK SMUSH NOW", "wp-smushit");
					}else{
						$count = $wpsmushit_admin->remaining_count < 50 ? $wpsmushit_admin->remaining_count : 50;
						$button_content = sprintf( esc_html__("BULK SMUSH %d ATTACHMENTS", "wp-smushit"), $count );
					}
					?>
					<div class="wp-smush-notice wp-smush-remaining">
						<i class="dev-icon"><img src="<?php echo WP_SMUSH_URL . 'assets/images/icon-gzip.svg'; ?>"></i><?php printf( esc_html__( "%s, you have %s%d images%s that needs smushing!", "wp-smushit" ), $wpsmushit_admin->get_user_name(), '<strong>', $wpsmushit_admin->remaining_count, '</strong>' ); ?>
					</div>
					<hr >
					<div class="smush-final-log notice notice-warning inline hidden"></div>
					<button type="button" class="wp-smush-all wp-smush-button"><?php echo $button_content; ?></button><?php

					//Smush .org Limit
					if( ! $wpsmushit_admin->is_pro() ) {?>
						<div class="wp-smush-pro-trial"><?php printf( esc_html__( "The free version of WP Smush is capped to 50 images per bulk smush, and up to 1MB images. Upgrade to WP Smush Pro to get unlimited images sizes, originals and no bulk smushing limits + more – %stry it absolutely FREE for 14 days%s", "wp-smushit"), '<a href="'. esc_url( $wpsmushit_admin->upgrade_url ) .'">', '</a>'); ?></div><?php
					}

					//Enable Super Smush
					if( $wpsmushit_admin->is_pro() && !$wpsmushit_admin->lossy_enabled ) {?>
						<p class="wp-smush-enable-lossy"><?php esc_html_e("Enable Super-smush in the Settings area to get even more savings with almost no noticeable quality loss.", "wp-smushit"); ?></p><?php
					}?>
				</div><?php
				$this->progress_bar();
			}
		}

		/**
		 * Content for showing Progress Bar
		 */
		function progress_bar() {
			global $wpsmushit_admin;
			// calculate %ages, avoid divide by zero error with no attachments
			if ( $wpsmushit_admin->total_count > 0 && $wpsmushit_admin->smushed_count > 0 ) {
				$smushed_pc = $wpsmushit_admin->smushed_count / $wpsmushit_admin->total_count * 100;
			} else {
				$smushed_pc = 0;
			} ?>
			<div class="wp-smush-bulk-progress-bar-wrapper hidden">
			<p class="wp-smush-bulk-active"><?php printf( esc_html__( "%sBulk smush is currently running.%s You don’t need to keep this page open, smush will continue to run until all images are smushed.", "wp-smushit" ), '<strong>', '</strong>' ); ?></p>
			<div class="wp-smush-progress-wrap">
				<div class="wp-smush-progress-bar-wrap">
					<div class="wp-smush-progress-bar">
						<div class="wp-smush-progress-inner" style="width: <?php echo $smushed_pc; ?>%;">
							<div class="wp-smush-progress-count"><span
									class="wp-smush-images-smushed"><?php echo $wpsmushit_admin->format_number( $wpsmushit_admin->smushed_count ); ?></span>/<span
									class="wp-smush-images-total"><?php echo $wpsmushit_admin->format_number( $wpsmushit_admin->total_count ); ?></span>
							</div>
						</div>
					</div>
				</div>
				<div
					class="wp-smush-count tc"><?php printf( esc_html__( "%s%d%s of %d attachments have been smushed." ), '<span class="wp-smush-images-smushed">', $wpsmushit_admin->smushed_count, '</span>', $wpsmushit_admin->total_count ); ?></div>
			</div>
			<div class="smush-final-log notice notice-warning inline hidden"></div>
			<hr class="wp-smush-sep">
			<button type="button"
			        class="button button-grey wp-smush-cancel-bulk"><?php esc_html_e( "CANCEL", "wp-smushit" ); ?></button>
			</div><?php
		}
		/**
		* Bulk resmush UI
		*/
		function bulk_re_smush_container() {
			//If we are not suppose to show the resmush UI
			if( 1 != get_option('wp_smush_show_resmush') ) {
				return;
			}

			$this->container_header( 'bulk-resmush-wrapper', esc_html__( "RE-SMUSH", "wp-smushit" ) ); ?>
			<div class="box-container"><?php
				//If we have the resmush ids list, Show Resmush notice and button
				if( $resmush_ids = get_option( "wp-smush-resmush-list" ) ) {
					$this->resmush_bulk_ui();
				}else{
					$this->bulk_resmush_content();
				}?>
			</div><?php
			echo "</section>";
		}

		/**
		* Bulk Resmush Content
		*/
		function bulk_resmush_content() {?>
			<div class="wp-smush-resmush-wrapper">
				<div class="wp-smush-settings-changed"><?php esc_html_e("You changed your settings recently. Let's run a quick check to see if any of your images can be further optimised to the new settings."); ?></div>
				<div class="wp-smush-progress-bar-wrap hidden">
					<div class="wp-smush-progress-bar">
						<div class="wp-smush-progress-inner" style="width: 100%;"><span class="wp-scan-progress-text"><?php esc_html_e("Scanning images..", "wp-smushit"); ?></span></div>
					</div>
				</div>
				<hr class="wp-smush-sep">
				<button class="wp-smush-scan button-grey" data-nonce="<?php echo wp_create_nonce( 'smush-scan-images' ); ?>"><?php esc_html_e("RUN IMAGE CHECK", "wp-smushit"); ?></button>
			</div><?php
		}
	}
}