/**
 * Processes bulk smushing
 * 
 * @author Saurabh Shukla <saurabh@incsub.com>
 * 
 */
jQuery('document').ready(function() {

	// if these are set by php
	if (wp_smpro_start_id !== null && typeof wp_smpro_start_id !== 'undefined') {
		$start_id = wp_smpro_start_id;
	}

	// form the urls
	// for smushing
	$send_url = ajaxurl + '?action=wp_smpro_queue';
	
	// for receipt checking
	$check_url = ajaxurl + '?action=wp_smpro_check';
	
	// remaining smushes
	$remaining = wp_smpro_total - wp_smpro_progress;
	
	// the width for a single receipt check
	single_check_width = (1 / $remaining) * 100;
	
	// intialise queue for checking receipt status
	$check_queue = [];
	
	// count of receipt checks
	$queue_done = 0;
	
	// a var to run the polling
	var smpro_poll_check;
	
	/**
	 * Show progress of smushing
	 */
	function smpro_progress() {
		
		// increase progress count
		wp_smpro_progress++;
		
		// calculate %
		$progress = (wp_smpro_progress / wp_smpro_total) * 100;
		
		// all done
		if ($progress === 100) {
			// disable start button
			jQuery('input#wp-sm-pro-begin').prop('disabled', true);
			// start polling for receipt status
			smpro_poll_check = setInterval(function() {
				qHandler();
			}, 1000);
		}
		
		// increase the progress bar
		jQuery('#wp-smpro-sent div').css('width', $progress + '%');

	}
	
	/**
	 * Show progress of receipt checking
	 */
	function smpro_check_progress() {
		// increment the done counter
		$queue_done++;
		
		// calculate %
		$new_width = ($queue_done / $remaining) * 100;
		
		// all done
		if ($new_width === 100) {
			smpro_show_status('Bulk smushing completed, back to media library!');
			// stop polling
			clearInterval(smpro_poll_check);
		}
		
		// increase progress bar
		jQuery('#wp-smpro-received div').css('width', $progress + '%');
	}
	
	/**
	 * Display status messages
	 * 
	 * @param {string} $msg Status message string
	 */
	function smpro_show_status($msg) {
		if ($msg === '') {
			return;
		}
		$status_div = jQuery('.bulk_queue_wrap').find('.status-div').first();
		$single_status = jQuery('<span/>');
		$single_status.addClass('single-status');
		$single_status.html($msg);
		$status_div.append($single_status);
	}
	
	/**
	 * Send ajax request for smushing
	 * 
	 * @param {integer} $id attachment id
	 * @returns {object} The whole ajax request to reolve this promise and start next
	 */
	function smproRequest($id) {
		// make request
		return jQuery.ajax({
			type: "GET",
			data: {attachment_id: $id},
			url: $send_url
		}).done(function(response) {
			// update status
			smpro_show_status("Sent for smushing [" + $id + "]");
			smpro_progress();
			
			// push into the receipt check queue, for polling
			if (response !== '') {
				$start_id = parseInt(response);
				$check_queue.push($start_id);
				return $start_id;
			}
			
		}).fail(function(response) {
			// update status and still progress
			smpro_show_status("Smush request failed [" + $id + "]");
			smpro_progress();
		});
	}
	
	/**
	 * 
	 * @param {type} $id
	 * @returns {object} the ajax request
	 */
	function smproCheck($id) {
		// send request
		return jQuery.ajax({
			type: "GET",
			data: {attachment_id: $id},
			url: $check_url
		}).done(function(response) {
			// don't remove from queue yet
			$rem = false;
			
			// handle different responses
			// if smush succeeded or failed, remove from queue
			switch (parseInt(response)) {
				case 2:
					$status_msg = "Received [" + $id + "]";
					$rem = true;
					break;
				case 1:
					$status_msg = "Still awaiting [" + $id + "]";
					break;
				case -1:
					$status_msg = "Smush failed [" + $id + "]";
					$rem = true;
					break;
				default:
					$status_msg = "Unknown error [" + $id + "]";
					break;
			}
			// show status
			smpro_show_status($status_msg);
			// if done, remove from queue and show progress
			if ($rem === true) {
				smpro_check_progress();
			}
		}).fail(function(response) {
			smpro_show_status("Checking failed [" + $id + "]");
			smpro_check_progress();
		});
	}
	
	/**
	 * Process the smush receipt checking queue
	 * @returns
	 */
	function qHandler() {
		if ($check_queue.length > 0) {
			$current_check = $check_queue.splice(0, 1);
			smproCheck($current_check[0]);
		}
		return;
	}
	
	/**
	 * Handle the start button click
	 */
	jQuery('.bulk_queue_wrap').on('click', 'input#wp-sm-pro-begin', function(e) {

		e.preventDefault();
		
		// nothing to smush, get out
		if (wp_smpro_total < 1) {
			smpro_show_status('Nothing to send');
			return;
		}

		// show stats
		smpro_show_status('Sending ' + $remaining + ' of total ' + wp_smpro_total + ' attachments');
		
		// instantiate our deferred object for piping
		var startingpoint = jQuery.Deferred();
		startingpoint.resolve();
		
		// if we have a definite number of ids
		if (wp_smpro_ids.length > 0) {
			$check_queue = wp_smpro_ids;
			jQuery.each(wp_smpro_ids, function(ix, $id) {
				startingpoint = startingpoint.then(function() {
					smpro_show_status("Making request for [" + $id + "]");
					return smproRequest($id);
				});
			});
		} else {
			// we smush everything that needs smushing
			for (var i = 0; i < $remaining; i++) {
				startingpoint = startingpoint.then(function() {
					smpro_show_status("Making request for [" + $start_id + "]");
					return smproRequest($start_id);
				});
			}
		}
	});
});