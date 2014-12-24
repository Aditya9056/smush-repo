<?php
/**
 * Remove plugin settings data
 *
 * @since 2.7.9
 *
 */

//if uninstall not called from WordPress exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}
global $wpdb;
$smush_pro_keys = array(
	'auto',
	'remove_meta',
	'progressive',
	'debug_mode',
	'hide-notice',
	'sent-ids',
	'bulk-sent',
	'bulk-received',
	'current-requests',
);
foreach ( $smush_pro_keys as $key ) {
	$key = 'wp-smpro-' . $key;
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
//Delete post meta for all the images
$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key='wp-smpro-is-smushed' OR meta_key='wp-smpro-smush-data' OR meta_key LIKE '%wp-smpro-request%' " );
?>