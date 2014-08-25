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
        original_count = 0;
        wp_smpro_is_throttled = 0;
        

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
                                        // call the ajax requestor
                                        return smproRequest($id, 0);
                                });
                        });
                }

                //otherwise, do nothing
                return;

        }

        /**
         * Send ajax request for smushing
         *
         * @param {type} $id
         * @param {type} $getnxt
         * @returns {unresolved}
         */
        function smproRequest($id, $getnxt) {
                if (!$process_next) {
                        return false;
                }
                if (pagenow === 'media_page_wp-smpro-admin') {
                        // increase progressbar
                        smpro_progress();
                }
                // make request
                return jQuery.ajax({
                        type: "GET",
                        data: {attachment_id: $id, get_next: $getnxt},
                        url: $send_url,
                        timeout: 60000,
                        dataType: 'json'
                }).done(function(response) {
                        $status = parseInt(response.status_code);
                        if ($status === 404 || $status === 503) {
                                //Exit deferred
                                $process_next = false;

                                //Update the button Status
                                $button = jQuery('.wp-smpro-bulk-wrap #wp-smpro-begin');

                                // copy the spinner into an object
                                $spinner = $button.find('.floatingCirclesG');

                                // remove the spinner
                                $spinner.remove();

                                // empty the current text
                                $button.find('span').html(wp_smpro_msgs.smush_all);
                                $button.css('padding-left', '10px');

                                return;
                        }
                        

                        if ($getnxt !== 0) {
                                // push into the receipt check queue, for polling
                                if (response.next !== null) {
                                        $start_id = response.next;
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



        // if we are on bulk smushing page
        if (pagenow === 'media_page_wp-smpro-admin') {

                original_count = wp_smpro_counts.sent.total;

                function wp_smpro_show_msg(msg) {
                        console.log(msg);
                        if (jQuery('.wp-smpro-msg.' + msg).length > 0) {
                                return;
                        }
                        var $msg = jQuery('<div id="message" class="wp-smpro-msg updated"></div>');
                        $msg.addClass(msg);
                        $msg.append(jQuery('<p></p>'));
                        $msg.find('p').append(wp_smpro_msgs[msg]);
                        $msg.css('display', 'none');
                        jQuery('#wp-smpro-begin').before($msg);
                        $msg.slideToggle();
                }

                function wp_smpro_check_done() {


                        $button = jQuery('.wp-smpro-bulk-wrap #wp-smpro-begin');

                        // copy the spinner into an object
                        $spinner = $button.find('.floatingCirclesG');

                        // remove the spinner
                        $spinner.remove();

                        // empty the current text
                        $button.find('span').html('');

                        // add new class for css adjustment
                        $button.removeClass('wp-smpro-started');
                        $button.removeClass('wp-smpro-unstarted');
                        $button.addClass('wp-smpro-resmush');

                        original_count = wp_smpro_counts.sent.total;

                        // reenable the button
                        $button.prop('disabled', false);

                        // add the progress text
                        $button.find('span').html(wp_smpro_msgs.resmush_all);


                        return;
                }

                function wp_smpro_refresh_progress() {
                        if(wp_smpro_is_throttled>0){
                                wp_smpro_show_msg('throttled');
                                $process_next = false;
                        
                                setTimeout(function(){
                                        wp_smpro_cancelled();
                                }, 2000);
                        }
                        var $progress = 0;
                        jQuery.each(wp_smpro_counts, function(i, e) {
                                $progress = (e.done / original_count) * 100;
                                
                                

                                if ($progress === 100 && i === 'sent') {
                                        if (wp_smpro_counts.received.total !== e.total) {
                                                jQuery('.wp-smpro-msg.no_leave').slideToggle(function() {
                                                        jQuery('.wp-smpro-msg.no_leave').remove();
                                                });
                                                wp_smpro_show_msg('leave_screen');
                                        }
                                }

                                if ($progress === 100 && i === 'received') {
                                        wp_smpro_check_done();
                                }
                                if ($progress === 100 && i === 'smushed') {
                                        wp_smpro_all_done();
                                }
                                wp_smpro_change_progress_status(i, e.done, original_count, $progress);
                                $progress = 0;
                        });
                }

                function wp_smpro_reset_smush() {
                        $process_next = true;
                        $reset_url = ajaxurl + '?action=wp_smpro_reset';
                        return jQuery.ajax({
                                type: "GET",
                                url: $reset_url,
                                timeout: 60000,
                                dataType: 'json'
                        }).done(function(response) {
                                wp_smpro_counts = response;
                                wp_smpro_refresh_progress();
                                wp_smpro_bulk_smush();
                                return;
                        }).fail(function() {
                                return;
                        });
                }

                /**
                 * Show progress of smushing
                 */
                function smpro_progress() {

                        // increase progress count
                        wp_smpro_counts.sent.done++;

                        // calculate %
                        var $progress = (wp_smpro_counts.sent.done / original_count) * 100;

                        // all sent
                        if ($progress === 100 && wp_smpro_counts.smushed.done !== wp_smpro_counts.sent.done) {
                                jQuery('.wp-smpro-msg.no_leave').slideToggle(function() {
                                        jQuery('.wp-smpro-msg.no_leave').remove();
                                });
                                wp_smpro_show_msg('leave_screen');

                        }

                        if ($progress > 100) {
                                return;
                        }

                        // increase the progress bar
                        wp_smpro_change_progress_status('sent', wp_smpro_counts.sent.done, original_count, $progress);

                }

                /**
                 * Change the button status on bulk smush completion
                 *
                 * @returns {undefined}
                 */
                function wp_smpro_all_done() {
                        $button = jQuery('.wp-smpro-bulk-wrap #wp-smpro-begin');

                        // copy the spinner into an object
                        $spinner = $button.find('.floatingCirclesG');

                        // remove the spinner
                        $spinner.remove();

                        // empty the current text
                        $button.find('span').html('');

                        $button.removeClass('wp-smpro-started');

                        $msg = jQuery('#message.wp-smpro-msg');

                        $msg.slideToggle(function() {
                                $msg.remove();
                        });
                        
                        $cancel_button = jQuery('.wp-smpro-bulk-wrap #wp-smpro-cancel');


                        if (original_count < wp_smpro_counts.sent.total) {

                                original_count = wp_smpro_counts.sent.total;

                                wp_smpro_show_msg('refresh_screen');
                                // add new class for css adjustment
                                $button.addClass('wp-smpro-resmush');
                                // add the progress text
                                $button.find('span').html(wp_smpro_msgs.smush_all);

                                $button.prop('disabled', false);


                        } else {
                                // add new class for css adjustment
                                $button.addClass('wp-smpro-finished');
                                // add the progress text
                                $button.find('span').html(wp_smpro_msgs.done);

                                $button.prop('disabled', true);
                                
                                $cancel_button.prop('disabled', true);

                                // slow down the heartbeat
                                wp.heartbeat.interval(60);
                        }


                        return;
                }


                function wp_smpro_cancelled() {
                        // remove previous messages
                        $msg = jQuery('#message.wp-smpro-msg');

                        $msg.slideToggle(function() {
                                $msg.remove();
                        });
                        
                        $button = jQuery('.wp-smpro-bulk-wrap #wp-smpro-begin');

                        // copy the spinner into an object
                        $spinner = $button.find('.floatingCirclesG');

                        // remove the spinner
                        $spinner.remove();

                        // empty the current text
                        $button.find('span').html('');

                        // add new class for css adjustment
                        $button.removeClass('wp-smpro-started');
                        $button.removeClass('wp-smpro-unstarted');
                        $button.addClass('wp-smpro-resmush');

                        original_count = wp_smpro_counts.sent.total;

                        // reenable the button
                        $button.prop('disabled', false);

                        // add the progress text
                        $button.find('span').html(wp_smpro_msgs.smush_all);
                        
                }

                /**
                 * Change progress bar and status
                 *
                 * @param {type} $count
                 * @param {type} $width
                 * @returns {undefined}
                 */
                function wp_smpro_change_progress_status($identifier, $count, $totalcount, $width) {
                        if ($width > 100) {
                                return;
                        }
                        // get the progress bar
                        $progress_bar = jQuery('#wp-smpro-progress-wrap #wp-smpro-' + $identifier + '-progress div');
                        if ($progress_bar.length < 1) {
                                return;
                        }

                        // increase progress
                        $progress_bar.animate({'width': $width + '%'});


                        // change the counts
                        jQuery('#' + $identifier + '-status .done-count').html($count);
                        jQuery('#' + $identifier + '-status .total-count').html($totalcount);

                        //update stats
                        jQuery('p#wp-smpro-compression span#kb').html(wp_smpro_counts.stats['compressed_kb']);
                        jQuery('p#wp-smpro-compression span#percent').html(wp_smpro_counts.stats['compressed_percent']);


                }

                /**
                 * Send for bulk smushing
                 *
                 * @returns {undefined}
                 */
                function wp_smpro_bulk_smush() {
                        $remaining = wp_smpro_counts.sent.left;
                        $start_id = wp_smpro_counts.sent.start_id;
                        original_count = wp_smpro_counts.sent.total;
                        if ($remaining < 0) {
                                return;
                        }
                        // instantiate our deferred object for piping
                        var startingpoint = jQuery.Deferred();
                        startingpoint.resolve();

                        // we smush everything that needs smushing
                        for (var i = 0; i < $remaining; i++) {
                                startingpoint = startingpoint.then(function() {
                                        return smproRequest($start_id, 1);
                                });
                        }

                        wp.heartbeat.interval('fast');

                        //Enqueue are data
                        wp.heartbeat.enqueue('wp-smpro-refresh-progress', 'dummy', false);


                }

                /**
                 * Change the button display on sending
                 *
                 * @param {type} $button
                 * @returns {undefined}
                 */
                function wp_smpro_button_progress_state($button) {

                        // copy the spinner into an object
                        $spinner = jQuery('#wp-smpro-spinner-wrap .floatingCirclesG').clone();

                        // empty the current text
                        $button.find('span').html('');

                        // add new class for css adjustment
                        $button.addClass('wp-smpro-started');

                        // prepend the spinner html
                        $button.prepend($spinner);

                        // add the progress text
                        $button.find('span').html(wp_smpro_msgs.progress);

                        // disable the button
                        $button.prop('disabled', true);


                        jQuery('#progress-ui').slideDown('fast');

                        // done
                        return;
                }

                /**
                 * Handle the start button click
                 */

                jQuery('.wp-smpro-bulk-wrap').on('click', '#wp-smpro-begin', function(e) {
                        // prevent the default action
                        e.preventDefault();

                        wp_smpro_button_progress_state(jQuery(this));

                        wp_smpro_reset_smush();

                        // remove previous messages
                        $msg = jQuery('#message.wp-smpro-msg');

                        $msg.slideToggle(function() {
                                $msg.remove();
                        });

                        // show a notice
                        wp_smpro_show_msg('no_leave');

                        return;

                });
                
                jQuery('.wp-smpro-bulk-wrap').on('click', '#wp-smpro-cancel', function(e) {
                        // prevent the default action
                        e.preventDefault();

                        $process_next = false;
                        
                        wp_smpro_cancelled();

                        return;

                });
                
                jQuery('.wp-smpro-bulk-wrap').on('click', '.smush-notices button.button', function(e) {
                        e.preventDefault();
                        jQuery('.smush-notices').slideToggle(function() {
                                jQuery('.smush-notices').remove();
                        });
                });


                // Listen for the custom event "heartbeat-tick" on $(document).
                jQuery(document).on('heartbeat-tick.wp-smpro-refresh-progress', function(e, data) {

                        // Receive Data back from Heartbeat
                        if (data.hasOwnProperty('wp-smpro-refresh-progress')) {
                                wp_smpro_counts = data['wp-smpro-refresh-progress'];
                                wp_smpro_is_throttled = parseInt(data['wp-smpro-is-throttled']);
                                wp_smpro_refresh_progress();
                        }

                        // Pass data back into namespace
                        wp.heartbeat.enqueue('wp-smpro-refresh-progress', 'dummy', false);

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

                        // find the spinner ui
                        $spinner = $button.find('.floatingCirclesG');

                        // remove the spinner
                        $spinner.remove();

                        // empty the current text
                        $button.find('span').html('');

                        // add new class for css adjustment
                        $button.removeClass('wp-smpro-started');

                        // add the button text
                        if ($resmush === true) {
                                $button.remove();
                        } else {
                                $html = wp_smpro_msgs.resmush;
                                $button.find('span').html($html);
                        }


                        // re-enable all the buttons
                        jQuery('.wp-smpro-smush').prop('disabled', false);

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
                                        $resmush = true;
                                        if ($status === -1) {
                                                $resmush = false;
                                        }

                                        // change the display
                                        wp_smpro_change_media_status($id, $resmush, response.msg);

                                } else {

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
                                timeout: 20000,
                                dataType: 'json'
                        }).done(function(res) {

                                //If API is not accessible
                                if (res.status_code == 404) {
                                        wp_smpro_change_media_status($id, false, res.status_msg);
                                        return;
                                }
                                wp_smpro_change_media_status($id, true, wp_smpro_msgs.sent);
                                // push the id into the queue for checking
                                $check_queue.push($id);
                        }).fail(function() {
                                $resmush_queue.push($id);
                        });
                }

                /**
                 * Updates the button status and disables all the other buttons
                 *
                 * @param {type} $button
                 * @returns {undefined}
                 */
                function wp_smpro_single_button_progress_state($button) {

                        // copy the spinner into an object
                        $spinner = jQuery('#wp-smpro-spinner-wrap .floatingCirclesG').clone();

                        // empty the current text
                        $button.find('span').html('');

                        // add new class for css adjustment
                        $button.addClass('wp-smpro-started');

                        // prepend the spinner html
                        $button.prepend($spinner);

                        // add the progress text
                        $button.find('span').html(wp_smpro_msgs.progress);

                        // disable all the buttons
                        jQuery('.wp-smpro-smush').prop('disabled', true);

                        // done
                        return;
                }

                /**
                 * Handle the media library button click
                 */
                jQuery('.wp-list-table.media').on('click', '.wp-smpro-smush', function(e) {

                        // prevent the default action
                        e.preventDefault();

                        // change the button disply
                        wp_smpro_single_button_progress_state(jQuery(this));

                        // start smushing
                        wp_smpro_single_smush(jQuery(this));

                        // done
                        return;

                });

                // set up polling for checking the queue
                smpro_poll_check = setInterval(function() {
                        // handles the checking queue
                        qSingleHandler();
                }, 2000);

        }
        // poll every 10 seconds for resmushing
        smpro_poll_resmush = setInterval(function() {
                wp_smpro_resmush();
        }, 10000);
});