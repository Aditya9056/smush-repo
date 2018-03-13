<?php
/**
 * Displays the UI for .org plugin recommendations
 *
 * @package WP Smush
 * @subpackage Admin
 * @since 2.7.9
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2018, Incsub (http://incsub.com)
 */

if ( ! class_exists( 'WpSmushRecommender' ) ) {

	class WpSmushRecommender {

		function __construct() {

			if ( ! $this->should_continue() ) {
				return;
			}

			//Hook UI at the end of Settings UI
			add_action( 'smush_settings_ui_bottom', array( $this, 'ui' ), 12 );

		}

		/**
		 * Do not display Directory smush for Subsites
		 *
		 * @return bool True/False, whether to display the Directory smush or not
		 *
		 */
		function should_continue() {
			global $WpSmush;

			//Do not show directory smush, if not main site in a network
			if ( $WpSmush->validate_install() ) {
				return false;
			}

			return true;
		}

		/**
		 * Output the required UI for Plugin recommendations
		 */
		function ui() {
			global $wpsmushit_admin; ?>

            <section class="smush-recommendation-section">
            <div class="smush-recommender-separator">
                <span class="outer-line"></span>
                <i class="icon-fi-plugin-2" aria-hidden="true" style="margin:10px 0"></i>
                <span class="outer-line"></span>
            </div>
            <h4 class="smush-recommender-title"><?php esc_html_e( "Check out our other free wordpress.org plugins!", "wp-smushit" ); ?></h4>
            <div class="smush-recommendations-wrapper row"><?php
				$upgrade_url = add_query_arg(
					array(
						'utm_source'   => 'Smush-Free',
						'utm_medium'   => 'Banner',
						'utm_campaign' => 'settings-sidebar'
					),
					$wpsmushit_admin->upgrade_url
				);
				//Hummingbird
				$hb_title   = esc_html__( "Hummingbird Page Speed Optimization", "wp-smushit" );
				$hb_content = esc_html__( "Performance Tests, File Optimization & Compression, Page, Browser & Gravatar Caching, GZIP Compression, CloudFlare Integration & more.", "wp-smushit" );
				$hb_class   = "hummingbird";
				$hb_url     = esc_url( "https://wordpress.org/plugins/hummingbird-performance/" );
				echo $this->recommendation_box( $hb_title, $hb_content, $hb_url, $hb_class );
				//Defender
				$df_title   = esc_html__( "Defender Security, Monitoring, and Hack Protection", "wp-smushit" );
				$df_content = esc_html__( "Security Tweaks & Recommendations, File & Malware Scanning, Login & 404 Lockout Protection, Two-Factor Authentication & more.", "wp-smushit" );
				$df_class   = "defender";
				$df_url     = esc_url( "https://wordpress.org/plugins/defender-security/" );
				echo $this->recommendation_box( $df_title, $df_content, $df_url, $df_class );
				//SmartCrawl
				$sc_title   = esc_html__( "SmartCrawl Search Engine Optimization", "wp-smushit" );
				$sc_content = esc_html__( "Customize Titles & Meta Data, OpenGraph, Twitter & Pinterest Support, Auto-Keyword Linking, SEO & Readability Analysis, Sitemaps, URL Crawler & more.", "wp-smushit" );
				$sc_class   = "smartcrawl";
				$sc_url     = esc_url( "https://wordpress.org/plugins/smartcrawl" );
				echo $this->recommendation_box( $sc_title, $sc_content, $sc_url, $sc_class );
				?>
            </div>
            </section><?php

		}

		/**
		 * Prints the UI for the given recommended plugin
		 *
		 * @param $title
		 * @param $content
		 * @param $link
		 * @param $plugin_class
		 */
		function recommendation_box( $title, $content, $link, $plugin_class ) {
			//Put bg to box parent div ?>
            <div class="smush-recommendation-box col-third smush-plugin-<?php echo $plugin_class; ?>">
                <div class="smush-plugin-inner-wrap">
                    <div class="smush-plugin-banner">
                        <div class="smush-plugin-icon"></div>
                    </div>
                    <h5 class="smush-plugin-title"><?php echo $title; ?></h5>
                    <div class="smush-plugin-description"><?php echo $content; ?></div>
                    <a href="<?php echo esc_url( $link ); ?>" class="smush-plugin-link" target="_blank"><span
                                class="smush-plugin-button-inner"><?php esc_html_e( "VIEW FEATURES", "wp-smushit" ) ?> <i
                                    class="icon-fi-arrow-right"></i></span></a>
                </div>
            </div><?php
		}

	}

	//Class Object
	global $wpsmush_recommender;
	$wpsmush_promo = new WpSmushRecommender();
}