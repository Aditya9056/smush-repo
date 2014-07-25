/**
 * Processes bulk smushing
 * 
 * @author Saurabh Shukla <saurabh@incsub.com>
 * 
 */
jQuery('document').ready(function() {
	// form the urls
	// for smushing
	$send_url = ajaxurl + '?action=wp_smpro_queue';

	// for receipt checking
	$check_url = ajaxurl + '?action=wp_smpro_check';

	// intialise queue for checking receipt status
	$check_queue = [];

	if (pagenow === 'media_page_wp-smpro-admin') {

		// if these are set by php
		if (wp_smpro_start_id !== null && typeof wp_smpro_start_id !== 'undefined') {
			$start_id = wp_smpro_start_id;
		}

		// remaining smushes
		$remaining = wp_smpro_remaining;

		// count of receipt checks
		$queue_done = 0;

		$smush_done = 0;

		// a var to run the polling
		var smpro_poll_check;

		/**
		 * Show progress of smushing
		 */
		function smpro_progress() {

			// increase progress count
			$smush_done++;

			// calculate %
			$progress = ($smush_done / $remaining) * 100;

			// all sent
			if ($progress === 100) {

				// if there's nothing in queue, we are done
				if ($check_queue.length < 1) {
					wp_smpro_all_done();
				} else {
					console.log('set up poll');
					// start polling for receipt status
					smpro_poll_check = setInterval(function() {
						qHandler();
					}, 1000);
				}
			}

			// increase the progress bar
			wp_smpro_change_progress_status($smush_done, $progress);

		}

		/**
		 * Show progress of receipt checking
		 */
		function smpro_check_progress() {
			// increment the done counter
			$queue_done++;

			// calculate %
			$progress = ($queue_done / $remaining) * 100;

			// all done
			if ($check_queue.length < 1) {
				wp_smpro_all_done();
				// stop polling
				clearInterval(smpro_poll_check);
			}

			// increase progress bar
			wp_smpro_change_check_status($queue_done, $progress);
		}

		/**
		 * Send ajax request for smushing
		 * 
		 * @param {integer} $id attachment id
		 * @returns {object} The whole ajax request to resolve this promise and start next
		 */
		function smproRequest($id, $getnxt) {
			// make request
			return jQuery.ajax({
				type: "GET",
				data: {attachment_id: $id, get_next: $getnxt},
				url: $send_url,
				dataType: 'json'
			}).done(function(response) {
				if (parseInt(response.status_code) === 0) {
					wp_smpro_change_img_status($id, 'smush-fail', response.status_msg);
					$check_queue = jQuery.grep($check_queue, function(value) {
						return value !== $id;
					});
				}
				smpro_progress();
				if ($getnxt !== 0) {
					// push into the receipt check queue, for polling
					if (response.next !== null) {
						$start_id = response.next;
						$check_queue.push($start_id);
						return $start_id;
					}

				}
				// otherwise, our queue is already formed


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
			console.log('sent');
			return jQuery.ajax({
				type: "GET",
				data: {attachment_id: $id},
				url: $check_url,
				dataType: 'json'
			}).done(function(response) {
				console.log('success');
				// don't remove from queue yet
				$rem = false;
				$status = parseInt(response.status);
				// handle different responses
				// if smush succeeded or failed, remove from queue
				console.log(response);
				if ($status === -1
					|| parseInt($status) === 2) {
					$rem = true;
				}

				if ($status === -1) {
					wp_smpro_change_img_status($id, 'smush-fail', response.msg);
				}

				if ($status === 2) {
					wp_smpro_change_img_status($id, 'smush-done', response.msg);
				}
				// if done, remove from queue and show progress
				if ($rem === true) {
					smpro_check_progress();
				}
			}).fail(function(response) {
				console.log('failed');
				smpro_show_status("Checking failed [" + $id + "]");
				smpro_check_progress();
			});
		}

		/**
		 * Process the smush receipt checking queue
		 * @returns
		 */
		function qHandler() {
			console.log('called bulk');
			$check_status_div = jQuery('#progress-ui p#check-status');

			if ($check_status_div.length>0 && !$check_status_div.is(':visible')) {
				$check_status_div.slideDown('fast');
			}
			if ($check_queue.length > 0) {
				$current_check = $check_queue.splice(0, 1);
				smproCheck($current_check[0]);
			}
			return;
		}

		function wp_smpro_all_done() {
			$button = jQuery('.wp-smpro-bulk-wrap #wp-smpro-begin');
			finish_button_state($button);
		}

		function finish_button_state($button) {
			// copy the loader into an object
			$loader = $button.find('.floatingCirclesG');

			// remove the loader
			$loader.remove();

			// empty the current text
			$button.find('span').html('');

			// add new class for css adjustment
			$button.removeClass('wp-smpro-started');
			$button.addClass('wp-smpro-finished');

			// add the progress text
			$button.find('span').html('All done!');

			return;
		}

		function wp_smpro_change_img_status($id, $status, $status_msg) {
			console.log('Ireached here');
			$attachment_element = jQuery('ul#wp-smpro-selected-images').find('li#wp-smpro-img-' + $id).first();
			if ($attachment_element.length < 1) {
				return;
			}
			console.log('and here');
			$status_div = $attachment_element.find('.img-smush-status').first();
			$attachment_element.removeClass();
			$attachment_element.addClass($status);
			$status_div.html($status_msg);
		}

		function wp_smpro_change_progress_status($count, $width) {
			$progress_bar = jQuery('#wp-smpro-progress-wrap #wp-smpro-smush-progress div');
			if ($progress_bar.length < 1) {
				return;
			}
			$progress_bar.css('width', $width + '%');
			jQuery('#smush-status #smush-sent-count').html($count);

		}

		function wp_smpro_change_check_status($count, $width) {
			$progress_bar = jQuery('#wp-smpro-progress-wrap #wp-smpro-check-progress div');
			if ($progress_bar.length < 1) {
				return;
			}
			$progress_bar.css('width', $width + '%');
			jQuery('#check-status #smush-received-count').html($count);

		}

		function wp_smpro_bulk_smush() {

			// instantiate our deferred object for piping
			var startingpoint = jQuery.Deferred();
			startingpoint.resolve();

			// if we have a definite number of ids
			if (wp_smpro_ids.length > 0) {
				// set up the queue to check receiving
				$check_queue = wp_smpro_ids;
				// loop and pipe into deferred object
				jQuery.each(wp_smpro_ids, function(ix, $id) {
					startingpoint = startingpoint.then(function() {
						// change the image status
						wp_smpro_change_img_status($id, 'in-progress', 'Sent for Smushing');
						// call the ajax requestor
						return smproRequest($id, 0);
					});
				});
			} else {
				// we smush everything that needs smushing
				for (var i = 0; i < $remaining; i++) {
					startingpoint = startingpoint.then(function() {
						return smproRequest($start_id, 1);
					});
				}
			}

		}
		/**
		 * Handle the start button click
		 */
		jQuery('.wp-smpro-bulk-wrap').on('click', '#wp-smpro-begin', function(e) {
			// prevent the default action
			e.preventDefault();

			wp_smpro_button_progress_state(jQuery(this));

			wp_smpro_bulk_smush();

			return;

		});
		
	} else {


		function qSingleHandler() {
			if ($check_queue.length > 0) {
				$current_check = $check_queue.splice(0, 1);
				singleCheck($current_check[0]);
			}

			return;
		}


		function wp_smpro_change_media_status($id, $resmush, $msg) {
			$attachment_element = jQuery('.wp-list-table.media').find('tr#post-' + $id).first();
			$status_div = $attachment_element.find('.smush-status').first();
			$status_div.html($msg);
			$button = $attachment_element.find('button.wp-smpro-smush');
			$loader = $button.find('.floatingCirclesG');

			// remove the loader
			$loader.remove();

			// empty the current text
			$button.find('span').html('');

			// add new class for css adjustment
			$button.removeClass('wp-smpro-started');

			// add the progress text
			if ($resmush === true) {
				$html = 'Re-smush';
			} else {
				$html = 'Smush.it now!'
			}
			$button.find('span').html($html);
			// disable the button
			$button.prop('disabled', false);

			return;

		}

		function singleCheck($id) {
			jQuery.ajax({
				type: "GET",
				data: {attachment_id: $id},
				url: $check_url,
				dataType: 'json'
			}).done(function(response) {
				$status = parseInt(response.status);
				// handle different responses
				// if smush succeeded or failed, remove from queue
				if ($status === -1
					|| $status === 2) {
					$check_queue = jQuery.grep($check_queue, function(value) {
						return value !== $id;
					});
					wp_smpro_change_media_status($id, true, response.msg);
				}

			}).fail(function(response) {

			});
		}

		function wp_smpro_single_smush($button) {
			console.log($button);
			$nearest_tr = $button.closest('tr').first();
			console.log($nearest_tr);
			$elem_id = $nearest_tr.attr('id');
			console.log($elem_id);
			$id = $elem_id.replace(/[^0-9\.]+/g, '');
			console.log($id);
			jQuery.ajax({
				type: "GET",
				data: {attachment_id: $id},
				url: $send_url,
				dataType: 'json'
			}).done(function(response) {
				console.log($id);
				console.log(response);
				$current_id = parseInt(response.current_id);
				if (parseInt(response.status_code) === 0) {
					wp_smpro_change_media_status($current_id, false, response.status_msg);
				} else {
					console.log('we got to: '+$current_id);
					console.log($check_queue);
					$check_queue.push($current_id);
					console.log($check_queue);
				}


			}).fail(function(response) {

			});
		}

		function wp_smpro_button_progress_state($button) {
			// copy the loader into an object
			$loader = jQuery('#wp-smpro-loader-wrap .floatingCirclesG').clone();

			// empty the current text
			$button.find('span').html('');

			// add new class for css adjustment
			$button.addClass('wp-smpro-started');

			// prepend the loader html
			$button.prepend($loader);

			// add the progress text
			$button.find('span').html('Smushing in Progress');

			// disable the button
			$button.prop('disabled', true);

			if (typeof (wp_smpro_ids) !== 'undefined') {
				if (wp_smpro_ids.length < 1) {
					jQuery('#progress-ui').slideDown('fast');
				}
			}
			return;
		}


		/**
		 * Handle the media library button click
		 */
		jQuery('.wp-list-table.media').on('click', '.wp-smpro-smush', function(e) {
			// prevent the default action
			e.preventDefault();

			wp_smpro_button_progress_state(jQuery(this));

			wp_smpro_single_smush(jQuery(this));

			return;

		});

		smpro_poll_check = setInterval(function() {
			qSingleHandler();
		}, 1000);

	}
});