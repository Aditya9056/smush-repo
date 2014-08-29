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

                                return;
                                
                        });
        }

});

