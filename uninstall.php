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
	'auto',

);
foreach ( $smushit_keys as $key ) {
	$key = 'wp-smush-' . $key;
	if ( is_multisite() ) {
		$blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs} LIMIT 100", ARRAY_A );
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
//Delete Post meta
$meta_type  = 'post';
$user_id    = 0;
$meta_key   = 'wp-smpro-smush-data';
$meta_value = '';
$delete_all = true;

if ( is_multisite() ) {
	$blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs} LIMIT 100", ARRAY_A );
	if ( $blogs ) {
		foreach ( $blogs as $blog ) {
			switch_to_blog( $blog['blog_id'] );
			delete_metadata( $meta_type, $user_id, $meta_key, $meta_value, $delete_all );
		}
		restore_current_blog();
	}
} else {
	delete_metadata( $meta_type, $user_id, $meta_key, $meta_value, $delete_all );
}
?>