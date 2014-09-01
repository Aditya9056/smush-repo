(function($){
        
        var SmushitPro = function(element, options){
                var elem = $(element);
                
                var defaults = {
                        isSingle     : false,
                        ajaxurl      : '',
                        msgs          : {},
                        counts        : {},
                        spinner       : $('#wp-smpro-spinner-wrap .floatingCirclesG'),
                        msgClass     : 'wp-smpro-msg',
                        ids           : [],
                        sendButton      : '#wp-smpro-send',
                        fetchButton       : '#wp-smpro-fetch',
                        cancelButton      : '#wp-smpro-cancel',
                        sendProgressBar : '#wp-smpro-sent-progress',
                        fetchProgressBar        : '#wp-smpro-fetched-progress',
                        statusWrap              : '#wp-smpro-progress-status',
                        statsWrap               : '#wp-smpro-compression',
                };
                
                var fetchCount = 0;
        
                var sentCount = 0;

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
                        if(config.isSingle){
                                singleMsg(msgvar);
                                return;
                        }
                        if (elem.find(config.msgClass+'.' + msgvar.msg).length > 0) {
                                return;
                        }
                        var $msg = $('<div id="message" class="'+config.msgClass+'"></div>');
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
                        elem.find(config.statusWrap).after($msg);
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
                                $button = elem.find(config.sendButton);

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
                        if(!config.isSingle){
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
                        sentCount++;
                
                        $percent = (sentCount/parseInt(config.counts.total))*100;
                
                        elem.find(config.sendProgressBar+' div').css('width',$percent+'%');
                        elem.find(config.statusWrap+' p#sent-status .done-count').html(sentCount);
                
//                        if(config.counts.sent === fetchCount){
//                                msg(config.msgs.sent_done, false, false);
//                                //wp_smpro_all_done();
//                        }
                };
                
                var fetchProgress = function($stats){
                        fetchCount++;
                
                        $percent = (fetchCount/parseInt(config.counts.total))*100;
                
                        elem.find(config.fetchProgressBar +' div').css('width',$percent+'%');
                        elem.find(config.statusWrap+' p#fetched-status .done-count').html(fetchCount);
                        
                        config.statsWrap.find('#percent').html($stats['compression_percent']);
                        config.statsWrap.find('#human').html($stats['compression_human']);
//                        if(config.counts.sent === fetchCount){
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
                                var $is_fetched = parseInt(response.success);
                                // file was successfully fetched
                                if($is_fetched>0 && response.stats!==null){
                                        fetchProgress(response.stats);
                                }else{
                                        msg({
                                                'msg':'fail',
                                                'str':response.msg,
                                                'err':true
                                        });
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
                
                        fetchCount = config.counts.smushed;

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

                        $button = elem.find(config.fetchButton);

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
                
                elem.on('click', config.sendButton, function(e) {
                                // prevent the default action
                                e.preventDefault();
                                buttonProgress($(this), config.msgs.sending);
                                if(!config.isSingle){
                                        sentCount = config.counts.sent;
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

                        }).on('click', config.fetchButton, function(e) {
                                // prevent the default action
                                e.preventDefault();
                                if($('#fetch-notice').length>0){
                                        $('#fetch-notice').slideToggle();
                                }else{
                                        bulkStart($(this));
                                }
                                
                                return;
                                
                        }).on('click', '#fetch-notice button.button', function(e) {
                        
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
                        
                        }).on('click', config.cancelButton, function(e) {
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