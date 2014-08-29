/**
 * Processes bulk smushing
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 *
 */
jQuery('document').ready(function() {
        
        // url for sending
        $send_url = ajaxurl + '?action=wp_smpro_send';
        
        // url for fetching
        $fetch_url = ajaxurl + '?action=wp_smpro_fetch';
        
        $smushedcount = 0;
        
        function wp_smpro_send_request(){
                return jQuery.ajax({
                                type: "GET",
                                url: $send_url,
                                timeout: 60000,
                                dataType: 'json'
                        }).done(function(response) {
                                if(parseInt(response.status_code)>0){
                                        wp_smpro_show_msg('fail', response.status_message, false);
                                }else{
                                        wp_smpro_show_msg('update', response.status_message, true);
                                }
                                return;
                        }).fail(function() {
                                return;
                        });
        }
        
        
        function wp_smpro_show_msg(msg, str, err){
                if (jQuery('.wp-smpro-msg.' + msg).length > 0) {
                        return;
                }
                var $msg = jQuery('<div id="message" class="wp-smpro-msg"></div>');
                if(!err){
                        $msg.addClass('updated');
                }else{
                        $msg.addClass('smush-notices');
                }
                $msg.addClass(msg);
                $msg.append(jQuery('<p></p>'));
                if(!str){
                        str = wp_smpro_msgs[msg];
                }
                $msg.find('p').append(str);
                $msg.css('display', 'none');
                jQuery('#progress-ui').after($msg);
                $msg.slideToggle();
                
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

               // done
               return;
       }
        
        function smproFetch($id){
                return jQuery.ajax({
                        type: "GET",
                        data: {attachment_id: $id, get_next: $getnxt},
                        url: $fetch_url,
                        timeout: 60000
                }).done(function(response){
                        $is_fetched = parseInt(response);
                        // file was successfully fetched
                        if($is_fetched>0){
                                smproProgress();
                        }else{
                                // do nothing
                        }
                }).fail(function(){
                        
                });
        }
        
        function smproProgress(){
                $smushed_count++;
                $percent = ($smushed_count/parseInt(wp_smpro_counts.total))*100;
                
                jQuery('#wp-smpro-smushed-progress div').css('width',$percent+'%');
                
                if(wp_smpro_counts.sent === $smushed_count){
                        wp_smpro_show_msg(wp_smpro_msgs.sent_done, false, false);
                        wp_smpro_all_done();
                }
                
        }
        
        function wp_smpro_bulk_fetch(){
                
                $smushed_count = wp_smpro_counts.smushed;
                
                var startingpoint = jQuery.Deferred();
                startingpoint.resolve();

                        
                jQuery.each(wp_smpro_ids, function(i, $id){
                        startingpoint = startingpoint.then(function() {
                                return smproRequest($id);
                        });
                        
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
        
        // if we are on bulk smushing page
        if (pagenow === 'media_page_wp-smpro-admin') {
                
                jQuery('.wp-smpro-bulk-wrap').on('click', '#wp-smpro-send', function(e) {
                                // prevent the default action
                                e.preventDefault();
                                
                                wp_smpro_send_request();
                                
                                return;

                        }).on('click', '#wp-smpro-fetch', function(e) {
                                // prevent the default action
                                e.preventDefault();
                                
                                wp_smpro_button_progress_state(jQuery(this));
                                
                                wp_smpro_bulk_fetch();
                                
                                return;
                                
                        });
        }else{
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
        }

});

