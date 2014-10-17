/**
 * Processes bulk smushing
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 *
 */
jQuery('document').ready(function () {

	// if we are on bulk smushing page
	if (pagenow === 'media_page_wp-smpro-admin') {

		jQuery('.wp-smpro-bulk-wrap').smushitpro({
			'msgs': wp_smpro_msgs,
			'counts': wp_smpro_counts,
			'ids': wp_smpro_sent_ids,
			'ajaxurl': ajaxurl,
			'wp_smpro_request_sent': wp_smpro_request_sent,
			'wp_smpro_poll_interval': wp_smpro_poll_interval,
			'smush_status': ajaxurl + '?action=wp_smpro_smush_status',
			'isSingle': false

		});
	} else {
		/**
		 * Handle the media library button click
		 */
		jQuery('.wp-list-table.media tr').smushitpro({
			'msgs': wp_smpro_msgs,
			'counts': wp_smpro_counts,
			'ajaxurl': ajaxurl,
			'isSingle': true
		});
	}

});

