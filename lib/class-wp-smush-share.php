<?php
/**
 * @package WP Smush
 *
 * @version 2.4
 *
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2016, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmushShare' ) ) {

	class WpSmushShare {
		function __construct() {
			add_filter( 'language_attributes', array( $this, 'add_opengraph_doctype' ) );
			add_action( 'admin_head', array( $this, 'insert_fb_in_head' ), 5 );
//			add_action( 'admin_enqueue_scripts', array( $this, 'insert_fb_js' ), 5 );
		}

		function share_widget() {
			global $wpsmushit_admin, $wpsmush_stats;
			$savings     = $wpsmushit_admin->global_stats_from_ids();
			$image_count = $wpsmush_stats->smushed_count();

			//If there is any saving, greater than 1Mb, show stats
			if ( empty( $savings ) || empty( $savings['bytes'] ) || $savings['bytes'] <= 1048576 || $image_count <= 1 || ! is_super_admin() ) {
				return false;
			}
			$message   = sprintf( esc_html__( "%s, you've smushed %d images and saved %s in total. Help your friends save bandwidth easily, and help me in my quest to Smush the internet!", "wp-smushit" ), $wpsmushit_admin->get_user_name(), $image_count, $savings['human'] );
			$share_msg = "I saved 6.5MB on my site with WP Smush ( " . urlencode( "https://wordpress.org/plugins/wp-smushit/" ) . ") - wanna make your website smaller and faster?";
			$url       = urlencode( "http://wordpress.org/plugins/wp-smushit/" ); ?>
			<section class="dev-box" id="wp-smush-share-widget">
			<div class="box-content roboto-medium">
				<p class="wp-smush-share-message"><?php echo $message; ?></p>
				<div class="wp-smush-share-buttons-wrapper">
					<!-- Twitter Button -->
					<a href="https://twitter.com/intent/tweet?text=<?php echo $share_msg; ?>"
					   class="button wp-smush-share-button" id="wp-smush-twitter-share">
						<i class="dev-icon dev-icon-twitter"></i><?php esc_html_e( "SHARE", "wp-smushit" ); ?></a>
					<!-- Facebook Button -->
					<a href="http://www.facebook.com/sharer.php?s=100&p[title]=WP Smush&p[summary]=Custom Content&p[url]=http://wordpress.org/plugins/wp-smushit/"
					   class="button wp-smush-share-button" id="wp-smush-facebook-share">
						<i class="dev-icon dev-icon-facebook"></i><?php esc_html_e( "Facebook", "wp-smushit" ); ?></a>
					<a href="whatsapp://send?text='<?php echo $share_msg; ?>'" class="button wp-smush-share-button"
					   id="wp-smush-whatsapp-share">
						<?php esc_html_e( "WhatsApp", "wp-smushit" ); ?></a>
				</div>
			</div>
			</section><?php
		}

		//Lets add Open Graph Meta Info

		function insert_fb_in_head() {
			if ( ! function_exists( 'get_current_screen' ) ) {
				return;
			}
			$current_screen = get_current_screen();
			$current_page   = $current_screen->base;
			if ( 'media_page_wp-smush-bulk' != $current_page ) {
				return;
			}
			echo '<meta property="og:url" content="https://wordpress.org/plugins/wp-smushit/"/>';
			echo '<meta property="og:type" content="article"/>';
			echo '<meta property="og:title" content="I saved 6.5MB on my site with WP Smush - wanna make your website smaller and faster?"/>';
			echo '<meta property="og:description" content="Resize and optimize all of your images with the incredibly powerful and 100% free image smusher, brought to you by the superteam at WPMU DEV!"/>';
		}

		//Adding the Open Graph in the Language Attributes
		function add_opengraph_doctype( $output ) {
			if ( ! function_exists( 'get_current_screen' ) ) {
				return;
			}

			$current_screen = get_current_screen();
			if ( empty( $current_screen ) ) {
				return;
			}

			$current_page = $current_screen->base;
			if ( 'media_page_wp-smush-bulk' != $current_page ) {
				return;
			}

			return $output . ' xmlns:og="http://opengraphprotocol.org/schema/" xmlns:fb="http://www.facebook.com/2008/fbml"';
		}

		function insert_fb_js() { ?>
			<!-- Comment #2: SDK -->
			<div id="fb-root"></div>
			<script>(function (d, s, id) {
					var js, fjs = d.getElementsByTagName(s)[0];
					if (d.getElementById(id)) return;
					js = d.createElement(s);
					js.id = id;
					js.src = "//connect.facebook.net/en_US/sdk.js#xfbml=1&version=v2.6";
					fjs.parentNode.insertBefore(js, fjs);
				}(document, 'script', 'facebook-jssdk'));</script><?php

		}

	}

	global $wpsmush_share;
	$wpsmush_share = new WpSmushShare();
}