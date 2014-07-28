/**
 * Processes bulk smushing
 * 
 * @author Saurabh Shukla <saurabh@incsub.com>
 * 
 */
jQuery('document').ready(function() {

	// url for smushing
	$send_url = ajaxurl + '?action=wp_smpro_queue';

	// url for receipt checking
	$check_url = ajaxurl + '?action=wp_smpro_check';

	// intialise queue for checking receipt status
	$check_queue = [];
        
        // initialise queue for failed/timed out requests
        $resmush_queue = [];
        
        /**
        * Change the button display on sending
        * 
        * @param {type} $button
        * @returns {undefined}
        */
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
               // done
               return;
        }
        
        /**
         * Resmush failed/timed out attachments
         * 
         * @returns {undefined}
         */
        function wp_smpro_resmush() {
                
                
                // if we have a definite number of ids
                if ($resmush_queue.length > 0) {
                        // instantiate our deferred object for piping
                        var resmushstartpoint = jQuery.Deferred();
                        resmushstartpoint.resolve();

                        // loop and pipe into deferred object
                        jQuery.each($resmush_queue, function(ix, $id) {
                                resmushstartpoint = resmushstartpoint.then(function() {
                                        // change the image status
                                        wp_smpro_change_img_status($id, 'in-progress', 'Sent for Smushing');
                                        // call the ajax requestor
                                        return smproRequest($id, 0);
                                });
                        });
                }
                
                //otherwise, do nothing
                return;

        }
        
        // if we are on bulk smushing page
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
					// start polling for receipt status
					smpro_poll_check = setInterval(function() {
						qHandler();
					}, 3000);
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
			if ($progress === 100) {
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
                 * @param {type} $id
                 * @param {type} $getnxt
                 * @returns {unresolved}
                 */
		function smproRequest($id, $getnxt) {
			// make request
			return jQuery.ajax({
				type: "GET",
				data: {attachment_id: $id, get_next: $getnxt},
				url: $send_url,
                                timeout: 60000,
				dataType: 'json'
			}).done(function(response) {
                                
                                $check_queue.push($id);
                                // increase progressbar
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
                        
                        // we don't need any parameters
			}).fail(function() {
                                // just send it to re-smush queue
                                $resmush_queue.push($id);
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
                                timeout: 60000,
				url: $check_url,
				dataType: 'json'
			}).done(function(response) {
			
                                // don't remove from queue yet
				$rem = false;
				$status = parseInt(response.status);
				
                                // if smush succeeded or failed, remove from queue
				if ($status === -1
					|| parseInt($status) === 2) {
					$rem = true;
				}
                                
                                // change display
				if ($status === -1) {
					wp_smpro_change_img_status($id, 'smush-fail', response.msg);
				}

				if ($status === 2) {
					wp_smpro_change_img_status($id, 'smush-done', response.msg);
				}
                                
                                // if done, remove from queue and show progress
				if ($rem === true) {
                                        // remove the id from queue
                                        $check_queue = jQuery.grep($check_queue, function(value) {
						return value !== $id;
					});
					smpro_check_progress();
				}else{
                                        // push back into queue
                                        $check_queue.push($id);
                                }
                                
			}).fail(function() {
				// push back into queue
                                $check_queue.push($id);
			});
		}

		/**
		 * Process the smush receipt checking queue
		 * @returns
		 */
		function qHandler() {
                        
                        // get the div to display status
			$check_status_div = jQuery('#progress-ui p#check-status');
                        
                        // if it's presnt in the UI (this isn't the image list one) 
			if ($check_status_div.length>0 && !$check_status_div.is(':visible')) {
				// display it
                                $check_status_div.slideDown('fast');
			}
                        // if there are ids in the queue
			if ($check_queue.length > 0) {
                                // remove from queue and send for checking
				$current_check = $check_queue.splice(0, 1);
				smproCheck($current_check[0]);
			}
                        
                        // done
			return;
		}
                
                /**
                 * Change the button status on bulk smush completion
                 * 
                 * @returns {undefined}
                 */
		function wp_smpro_all_done() {
			$button = jQuery('.wp-smpro-bulk-wrap #wp-smpro-begin');
			
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
                
                /**
                 * Change the image status
                 * 
                 * @param {type} $id
                 * @param {type} $status
                 * @param {type} $status_msg
                 * @returns {undefined}
                 */
		function wp_smpro_change_img_status($id, $status, $status_msg) {
			
                        // get the element
                        $attachment_element = jQuery('ul#wp-smpro-selected-images').find('li#wp-smpro-img-' + $id).first();
			if ($attachment_element.length < 1) {
				return;
			}
			
                        // get the div for status msgs
                        $status_div = $attachment_element.find('.img-smush-status').first();
			
                        // change some classes
                        $attachment_element.removeClass();
			$attachment_element.addClass($status);
                        
                        // add the message
			$status_div.html($status_msg);
		}
                
                /**
                 * Change progress bar and status
                 * 
                 * @param {type} $count
                 * @param {type} $width
                 * @returns {undefined}
                 */
		function wp_smpro_change_progress_status($count, $width) {
			// get the progress bar
                        $progress_bar = jQuery('#wp-smpro-progress-wrap #wp-smpro-smush-progress div');
			if ($progress_bar.length < 1) {
				return;
			}
                        
                        // increase progress
			$progress_bar.css('width', $width + '%');
                        
                        // change the counts
			jQuery('#smush-status #smush-sent-count').html($count);

		}
                
                /**
                 * Check status
                 * 
                 * @param {type} $count
                 * @param {type} $width
                 * @returns {undefined}
                 */
		function wp_smpro_change_check_status($count, $width) {
			// get the progress bar
                        $progress_bar = jQuery('#wp-smpro-progress-wrap #wp-smpro-check-progress div');
			if ($progress_bar.length < 1) {
				return;
			}
			
                        // increase progress
			$progress_bar.css('width', $width + '%');
                        
                        // change the counts
			jQuery('#check-status #smush-received-count').html($count);

		}
                
                /**
                 * Send for bulk smushing
                 * 
                 * @returns {undefined}
                 */
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
                // this is the media library screen

                /**
                 * Handle the smush checking queue
                 * 
                 * @returns null
                 */
		function qSingleHandler() {
                        
                        // if there's something in the queue
			if ($check_queue.length > 0) {
                                // remove from and get the first id in queue
				$current_check = $check_queue.splice(0, 1);
				// send it for checking
                                singleCheck($current_check[0]);
			}
                        
                        // there's nothing in the queue
			return;
		}

                /**
                 * Display the status changes in the media library UI
                 * 
                 * @param {type} $id
                 * @param {type} $resmush
                 * @param {type} $msg
                 * @returns {undefined}
                 */
		function wp_smpro_change_media_status($id, $resmush, $msg) {
			// get the media library row
                        $attachment_element = jQuery('.wp-list-table.media').find('tr#post-' + $id).first();
			
                        // find the div that displays status message
                        $status_div = $attachment_element.find('.smush-status').first();
                        
                        // replace the older message
			$status_div.html($msg);
                        
                        // find the smush button
			$button = $attachment_element.find('button.wp-smpro-smush');
                        
                        // find the loader ui
			$loader = $button.find('.floatingCirclesG');

			// remove the loader
			$loader.remove();

			// empty the current text
			$button.find('span').html('');

			// add new class for css adjustment
			$button.removeClass('wp-smpro-started');

			// add the button text
			if ($resmush === true) {
				$html = 'Re-smush';
			} else {
				$html = 'Smush.it now!';
			}
			$button.find('span').html($html);
			
                        // re-enable the button
			$button.prop('disabled', false);
                        
                        // done!
			return;

		}
                
                /**
                 * Checks the smush status in media library
                 * 
                 * @param {type} $id
                 * @returns {undefined}
                 */
		function singleCheck($id) {
			jQuery.ajax({
				type: "GET",
				data: {attachment_id: $id},
				url: $check_url,
                                timeout: 60000,
				dataType: 'json'
			}).done(function(response) {
				$status = parseInt(response.status);
                                
                                // if smush succeeded or failed, remove from queue
				if ($status === -1
					|| $status === 2) {
                                
                                        // remove the id from queue
                                        $check_queue = jQuery.grep($check_queue, function(value) {
						return value !== $id;
					});
                                        
                                        // change the display
					wp_smpro_change_media_status($id, true, response.msg);
				
                                }else{
                                
                                        //push the id back into queue
                                        $check_queue.push($id);
                                }
                                
                                
			}).fail(function() {
                                
                                // it failed, push back into the queue
                                $check_queue.push($id);
			});
		}
                
                /**
                 * Sends the attachment for smushing in media libarry ui
                 * 
                 * @param {type} $button
                 * @returns {undefined}
                 */
		function wp_smpro_single_smush($button) {
                        
                        // get the row
                        var $nearest_tr = $button.closest('tr').first();
			
                        // get the row's DOM id
                        var $elem_id = $nearest_tr.attr('id');
			
                        // get the attachment id from DOM id
			var $id = $elem_id.replace(/[^0-9\.]+/g, '');
			
                        // send the ajax request
			jQuery.ajax({
				type: "GET",
				data: {attachment_id: $id},
				url: $send_url,
                                timeout: 60000,
				dataType: 'json'
			}).done(function(response) {
                                
                                // push the id into the queue for checking
                                $check_queue.push($id);
			}).fail(function() {
                                $resmush_queue.push($id);
			});
		}
       


		/**
		 * Handle the media library button click
		 */
		jQuery('.wp-list-table.media').on('click', '.wp-smpro-smush', function(e) {
			
                        // prevent the default action
			e.preventDefault();

			// change the button disply
                        wp_smpro_button_progress_state(jQuery(this));
                        
                        // start smushing
			wp_smpro_single_smush(jQuery(this));
                        
                        // done
			return;

		});
                
                // set up polling for checking the queue
		smpro_poll_check = setInterval(function() {
                        // handles the checking queue
			qSingleHandler();
		}, 1000);

	}
        // poll every 10 seconds for resmushing
        smpro_poll_resmush = setInterval(function(){
                        wp_smpro_resmush();
        }, 10000);
});