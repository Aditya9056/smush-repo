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
if( !defined('WP_SMPRO_RESET_URL')) {
	define('WP_SMPRO_RESET_URL', 'https://smush.wpmudev.org/reset/');
}
global $wpdb;
/**
 * Sends a reset request to API
 */
function remove_bulk_request() {
//Check if there is a pending bulk request, tell api to remove it
	$bulk_request     = get_option( "wp-smpro-bulk-sent", array() );
	$current_requests = get_option( "wp-smpro-current-requests", array() );
	if ( ! empty( $bulk_request ) && ! empty( $current_requests[ $bulk_request ] ) ) {
		$request_data               = array();
		$request_data['api_key']    = get_site_option( 'wpmudev_apikey' );
		$request_data['token']      = $current_requests[ $bulk_request ]['token'];
		$request_data['request_id'] = $bulk_request;

		$request_data = json_encode( $request_data );

		$req_args = array(
			'body'       => array(
				'json' => $request_data
			),
			'user-agent' => 'WP Smush PRO(' . '+' . get_site_url() . ')',
			'timeout'    => 30,
			'sslverify'  => false
		);

		// make the post request and return the response
		wp_remote_post( WP_SMPRO_RESET_URL, $req_args );
	}
}
if ( is_multisite() ) {
	$blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs}", ARRAY_A );
	if ( $blogs ) {
		foreach ( $blogs as $blog ) {
			switch_to_blog( $blog['blog_id'] );
			remove_bulk_request();
		}
		restore_current_blog();
	}
} else {
	remove_bulk_request();
}
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