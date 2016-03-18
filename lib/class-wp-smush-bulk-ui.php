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
	 *
	 * @property int $remaining_count
	 * @property int $total_count
	 * @property int $smushed_count
	 * @property int $exceeding_items_count
	 */
	class WpSmushBulkUi {

		function __construct() {
			add_action('wp_smush_after_stats_box', array($this, 'wp_smush_promo') );
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
			global $WpSmush;

			//Header Of the Box
			$this->container_header( 'wp-smush-welcome', esc_html__( "WELCOME", "wp-smushit" ), '', true );

			//Get username
			$current_user = wp_get_current_user();
			$name = !empty( $current_user->first_name ) ? $current_user->first_name : $current_user->display_name;

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
					<p class="wp-smush-welcome-message"><?php printf( esc_html__(' %1$s Nice one, %3$s%2$s! You\'ve just installed %4$s, the hottest image compression plugin for WordPress that will reduce your image sizes significantly! We\'ve already applied recommended settings, which you can change anytime. %1$sGet started by running your first Smush!%2$s', "wp-smushit"), '<strong>','</strong>', $name, $plugin_name ); ?></p>
				</div>
			</div><?php
			echo "</section>";
		}

		/**
		* Bulk Smush UI and Progress bar
		*/
		function bulk_smush_container() {
			$smush_individual_msg = sprintf( esc_html__("Smush individual images via your %sMedia Library%s", "wp-smushit"), '<a href="' . esc_url( admin_url('upload.php') ) . '" title="' . esc_html__( 'Media Library', 'wp-smushit') .'">', '</a>' );
			$this->container_header('bulk-smush-wrapper', esc_html__("BULK SMUSH", "wp-smushit"), $smush_individual_msg ); ?>
			<div class="box-container"></div><?php
			echo "</section>";
		}
		/**
		* All the settings for Basic and Advanced Users
		*/
		function settings_ui() {

			$this->container_header('smush-settings-wrapper', esc_html__("SETTINGS", "wp-smushit"), '' );
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
					<span class="float-r wp-smush-stats"><strong><?php echo intval( $wpsmushit_admin->smushed_count ) . '/' . $wpsmushit_admin->total_count; ?></strong></span>
				</div><?php
				if( $WpSmush->is_pro() ) {?>
					<hr>
					<div class="row smush-attachments">
						<span class="float-l wp-smush-stats-label"><strong><?php esc_html_e( "ATTACHMENTS SUPER-SMUSHED", "wp-smushit" ); ?></strong></span>
						<span class="float-r wp-smush-stats"><strong><?php echo intval( $wpsmushit_admin->super_smushed ) . '/' . $wpsmushit_admin->total_count; ?></strong></span>
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

			//Content for the End of boc container
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
			echo "</div><!-- Box Content -->
			</section><!-- Main Section -->";
		}

		/**
		 * Adds progress bar for ReSmush bulk, if there are any images, that needs to be resmushed
		 */
		function resmush_bulk_ui( $return = false ) {

			global $WpSmush, $wpsmushit_admin;
			//Check if we need to show it as per the curent settings
			if( !$WpSmush->smush_original && $WpSmush->keep_exif && !$WpSmush->lossy_enabled ) {
				return;
			}
			$count = count( $wpsmushit_admin->resmush_ids );

			$ss_progress_ui = '<div class="wp-resmush-wrapper"><h4>' . esc_html__( 'Re-Smush Images', 'wp-smushit' ) . '</h4>';
			$ss_progress_ui .= '<p>' . sprintf( esc_html__( 'We found %d attachments that were previously optimised. With the current settings they can be further smushed for more savings.', 'wp-smushit' ), $count ) . '</p>';
			$ss_progress_ui .= '<div id="progress-ui" class="super-smush">';

			// display the progress bars
			$ss_progress_ui .= '<div id="wp-smush-ss-progress-wrap">
			<div id="wp-smush-ss-progress" class="wp-smush-progressbar"><div style="width:0%"></div></div>
			<p id="wp-smush-compression">'
			                   . sprintf(
				                   _n( '<span class="remaining-count">%d</span> attachment left to Re-Smush',
					                   '<span class="remaining-count">%d</span> attachments left to Re-Smush',
					                   $count,
					                   'wp-smushit' ), $count, $count )
			                   . '</p>
                </div>
                </div><!-- End of progress ui -->';

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
					if( 1 != get_option('hide_smush_welcome') && 1 != get_option('hide_smush_features') && 0 >= $wpsmushit_admin->smushed_count && is_super_admin() ) {?>
						<div class="block float-l smush-welcome-wrapper">
							<?php $this->welcome_screen(); ?>
						</div><?php
					} ?>

					<!-- Bulk Smush Progress Bar -->
					<div class="wp-smushit-container-left col-two-third float-l"><?php
						//Bulk Smush Container
						$this->bulk_smush_container();
						//Settings
						$this->settings_ui();
						?>
					</div>

					<!-- Stats -->
					<div class="wp-smushit-container-right col-third float-l"><?php
						//Stats
						$this->smush_stats_container();
						if( !$WpSmush->is_pro() ) {
							/**
                            * Allows to Hook in Additional Containers after Stats Box for free version
                            * Pro Version has a full width settings box, so we don't want to do it there
							*/
							do_action('wp_smush_after_stats_box');
						} ?>
					</div>
				</div>
			</div>
			<?php
			$wpsmushit_admin->print_loader();
		}
		/**
		* Pro Version and HummingBird
		*/
		function wp_smush_promo() {
			$this->container_header( 'wp-smush-pro-adv', "TRY WP SMUSH PRO - FREE!" ); ?>
			<div class="box-content">
				<p class="wp-smush-promo-content">Get access to not only WP Smush, but 100+ premium plugins, Upfront themes, security & performance solutions and 24/7 expert support to make you fly – best of all, it’s <strong>absolutely FREE to try!</strong></p>
				<p class="wp-smush-promo-content-smaller">Join 389,434 happy members today with no lock in and 100% GPL, cancel any time and use forever on unlimited sites for only $49 p/m</p>
				<span class="wp-smush-pro-cta"><a href="#" class="button button-cta button-green">START 14 DAY FREE TRIAL</a></span>
			</div>
			<img src="<?php echo WP_SMUSH_URL . 'assets/images/smush-pro.png'; ?>"
					     alt="<?php esc_html_e( "TRY WP SMUSH PRO - DEV TEAM", "wp-smushit" ); ?>"><?php
			echo "</section>";
		}
	}
}