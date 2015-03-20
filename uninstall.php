<?php
/**
 * Remove plugin settings data
 *
 * @since 1.7
 *
 */

//if uninstall not called from WordPress exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}
global $wpdb;

$smushit_keys = array(
	'smushit_auto',
	'smushit_timeout',
	'smushit_enforce_same_url',
	'smushit_debug',
	'error_log',
	'notice_log'
);
foreach ( $smushit_keys as $key ) {
	$key = 'wp_smushit_' . $key;
	if ( is_multisite() ) {
		$blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs}", ARRAY_A );
		if ( $blogs ) {
			foreach ( $blogs as $blog ) {
				switch_to_blog( $blog['blog_id'] );
				delete_option( $key );
				delete_site_option( $key );
			}
			restore_current_blog();
		}
	} else {
		delete_option( $key );
	}
}
?>