(function($){
        
        var SmushitPro = function(element, options){
                var elem = $(element);
                
                var defaults = {
                        is_single     : false,
                        ajaxurl      : '',
                        msgs          : {},
                        counts        : {},
                        spinner       : $('#wp-smpro-spinner-wrap .floatingCirclesG'),
                        msg_class     : '.wp-smpro-msg',
                        ids           : []
                };
                
                var smushed_count = 0;
        
                var sent_count = 0;

                var process_next = true;
                
                // merge the passed options object with defaults
                var config = $.extend(defaults, options || {});
                
                var init = function(){
                
                        // url for sending
                        config.send_url = config.ajaxurl + '?action=wp_smpro_send';

                        // url for fetching
                        config.fetch_url = config.ajaxurl + '?action=wp_smpro_fetch';

                        config.hide_notice_url = config.ajaxurl + '?action=wp_smpro_hide';
                
                        
                };
                
                var msg = function(msgvar){
                        if(config.is_single){
                                singleMsg(msgvar);
                                return;
                        }
                        if (elem.find(config.msg_class+'.' + msgvar.msg).length > 0) {
                                return;
                        }
                        var $msg = $('<div id="message" class="wp-smpro-msg"></div>');
                        if(!msgvar.err){
                                $msg.addClass('updated');
                        }else{
                                $msg.addClass('smush-notices');
                        }
                        $msg.addClass(msgvar.msg);
                        $msg.append(jQuery('<p></p>'));
                        if(!msgvar.str){
                                msgvar.str = config.msgs[msgvar.msg];
                        }
                        $msg.find('p').append(msgvar.str);
                        $msg.css('display', 'none');
                        elem.find('#wp-smpro-progress-status').after($msg);
                        $msg.slideToggle();

                };
                
                var singleMsg = function(msgvar){
                        
                        // find the div that displays status message
                        $status_div = elem.find('.smush-status');
                        
                        if(!msgvar.str){
                                msgvar.str = config.msgs[msgvar.msg];
                        }
                        
                        // replace the older message
                        $status_div.html(msgvar.str);
                        
                        if(msgvar.err){
                               
                               $status_div.addClass('fail');
                               // find the smush button
                                $button = elem.find('button#wp-smpro-send');

                                // find the spinner ui
                                $spinner = $button.find('.floatingCirclesG');

                                // remove the spinner
                                $spinner.remove();

                                // empty the current text
                                $button.find('span').html(config.msgs.smush_now);

                                // add new class for css adjustment
                                $button.removeClass('wp-smpro-started');

                                // re-enable all the buttons
                                $button.prop('disabled', false);

                        }else{
                               $status_div.addClass('success');
                               $button.remove();
                        }

                        
                        // done!
                        return;
                };
                
                var send = function($id){
                        
                        var $data = {};
                        if( $id !== false ){
                            $data = {'attachment_id':$id}; 
                        }
                        return $.ajax({
                                        type: "GET",
                                        url: config.send_url,
                                        data: $data,
                                        timeout: 60000,
                                        dataType: 'json'
                                }).done(function(response) {
                                        if(parseInt(response.status_code)>0){
                                                sendSuccess(response);
                                        }else{
                                                sendFailure(response);
                                        }
                                        return;
                                }).fail(function() {
                                        response = {};
                                        sendFailure(response);
                                        return;
                                });

                };
                
                
                var sendSuccess = function($response){
                        if(!config.is_single){
                               sendProgress($response.count);                         
                        }
                        var msgvar = {
                                'msg':'update',
                                'str':$response.status_message,
                                'err':false
                        };
                        msg(msgvar);
                        return;

                };
                
                var sendFailure = function($response){
                
                        if($.isEmptyObject($response)){
                                $response = {'status_message': config.msgs.send_fail};
                        }
                        
                        var msgvar = {
                                'msg':'fail',
                                'str':$response.status_message,
                                'err':true
                        };
                        
                        msg(msgvar);
                        return;
                };
                
                var sendProgress = function(){
                        sent_count++;
                
                        $percent = (sent_count/parseInt(config.counts.total))*100;
                
                        elem.find('#wp-smpro-sent-progress div').css('width',$percent+'%');
                        elem.find('#wp-smpro-progress-status p#smushed-status .done-count').html(sent_count);
                
//                        if(config.counts.sent === smushed_count){
//                                msg(config.msgs.sent_done, false, false);
//                                //wp_smpro_all_done();
//                        }
                };
                
                var fetchProgress = function(){
                        smushed_count++;
                
                        $percent = (smushed_count/parseInt(config.counts.total))*100;
                
                        elem.find('#wp-smpro-smushed-progress div').css('width',$percent+'%');
                        elem.find('#wp-smpro-progress-status p#smushed-status .done-count').html(smushed_count);
                
//                        if(config.counts.sent === smushed_count){
//                                msg(config.msgs.sent_done, false, false);
//                                //wp_smpro_all_done();
//                        }
                };
                
                var fetch = function($id){
                        if (!config.process_next) {
                                return false;
                        }
                        return jQuery.ajax({
                                type: "GET",
                                data: {attachment_id: $id, get_next: $getnxt},
                                url: config.fetch_url,
                                timeout: 60000
                        }).done(function(response){
                                var $is_fetched = parseInt(response);
                                // file was successfully fetched
                                if($is_fetched>0){
                                        fetchProgress();
                                }else{
                                        // do nothing
                                }
                        }).fail(function(){

                        });
                };
                
                var buttonProgress = function($button, $text) {

                        // copy the spinner into an object
                        $spinner = config.spinner.clone();

                        // empty the current text
                        $button.find('span').html('');

                        // add new class for css adjustment
                        $button.addClass('wp-smpro-started');

                        // prepend the spinner html
                        $button.prepend($spinner);

                        // add the progress text
                        $button.find('span').html($text);

                        // disable the button
                        $button.prop('disabled', true);

                        // done
                        return;
                };
                
                var bulkFetch = function(){
                
                        smushed_count = config.counts.smushed;

                        var startingpoint = jQuery.Deferred();
                        startingpoint.resolve();


                        $.each(config.ids, function(i, $id){
                                startingpoint = startingpoint.then(function() {
                                        return fetch($id);
                                });

                        });
                };
                
                var bulkStart = function($button){
                        buttonProgress($button, config.msgs.fetching);

                        bulkFetch();

                        //Before leave screen, show alert
                        jQuery(window).on('beforeunload', function (e) {
                            return wp_smpro_msgs.no_leave.replace(/(<([^>]+)>)/ig,"");
                        });
                };
                
                var bulkCancel = function(){
                        $(window).off('beforeunload');

                        $button = $('#wp-smpro-fetch');

                        // copy the spinner into an object
                        $spinner = $button.find('.floatingCirclesG');

                        // remove the spinner
                        $spinner.remove();

                        // empty the current text
                        $button.find('span').html('');

                        $button.removeClass('wp-smpro-started');

                        $button.prop('disabled', false);

                        $button.find('span').html(wp_smpro_msgs.fetch);

                };
                
                
                init();
                
                elem.on('click', '#wp-smpro-send', function(e) {
                                // prevent the default action
                                e.preventDefault();
                                buttonProgress($(this), config.msgs.sending);
                                if(!config.is_single){
                                        sent_count = config.counts.sent;
                                        send(false);
                                }else{
                                        // get the row
                                        var $nearest_tr = $(this).closest('tr').first();
                                        
                                        // get the row's DOM id
                                        var $elem_id = $nearest_tr.attr('id');
                                        
                                        // get the attachment id from DOM id
                                        var $id = $elem_id.replace(/[^0-9\.]+/g, '');
                                        
                                        send($id);
                                }
                                        
                                
                                return;

                        }).on('click', '#wp-smpro-fetch', function(e) {
                                // prevent the default action
                                e.preventDefault();
                                if($('#fetch-notice').length>0){
                                        $('#fetch-notice').slideToggle();
                                }else{
                                        bulkStart($(this));
                                }
                                
                                return;
                                
                        });
                        
                elem.on('click', '#fetch-notice button.button', function(e) {
                        
                        e.preventDefault();
                        
                        if($(this).hasClass('button-secondary')){
                                jQuery.ajax({
                                        type: "GET",
                                        data: {hide: true},
                                        url: config.hide_notice_url,
                                        timeout: 60000
                                }); 
                        }
                        
                        $('#fetch-notice').slideToggle(function() {
                                $('#fetch-notice').remove();
                        });
                        
                        bulkStart($(this));
                        
                });
                
                elem.on('click', '#wp-smpro-cancel', function(e) {
                        // prevent the default action
                        e.preventDefault();

                        config.process_next = false;
                        
                        bulkCancel();

                        return;

                });
        };
                
        $.fn.smushitpro = function(options){
            return this.each(function(){
                var element = $(this);
                
                // Return early if this element already has a plugin instance
                if (element.data('smushitpro'))
                    return;

                // pass options to plugin constructor and create a new instance
                var smushitpro = new SmushitPro(this, options);

                // Store plugin object in this element's data
                element.data('smushitpro', smushitpro);
            });
        };
        
        
})( jQuery );