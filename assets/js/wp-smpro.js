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

		                // find the smush button
		                $button = elem.find(config.sendButton);

		                // find the spinner ui
		                $spinner = $button.find('.floatingCirclesG');

		                // remove the spinner
		                $spinner.remove();

		                // add the progress text
		                $button.find('span').html(config.msgs.sent);


                };
                
                var singleMsg = function(msgvar){
                        
                        // find the div that displays status message
                        $status_div = elem.find('.smush-status');
                        
                        if(!msgvar.str){
                                msgvar.str = config.msgs[msgvar.msg];
                        }
                        
                        // replace the older message
                        $status_div.html(msgvar.str);
		                // find the smush button
		                $button = elem.find(config.sendButton);
                        
                        if(msgvar.err){
                               
                               $status_div.addClass('fail');

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
                               sendProgress($response.sent_count);
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
                
                var sendProgress = function( sentCount ){
                
                        $percent = (sentCount/parseInt(config.counts.total))*100;
                
                        elem.find(config.sendProgressBar+' div').css('width',$percent+'%');
                        elem.find(config.statusWrap+' p#sent-status .done-count').html(sentCount);
                
//                        if(config.counts.sent === fetchCount){
//                                msg(config.msgs.sent_done, false, false);
//                                //wp_smpro_all_done();
//                        }
                };
                
                var compression = function($stats){
                        config.counts.size_before +=$stats.size_before;
                        config.counts.size_after +=$stats.size_after;
                        $bytes = config.counts.size_before - config.counts.size_after;
                        config.counts.human = formatBytes($bytes);
                        config.counts.percent = ($bytes/config.counts.size_before)*100;
                        return config.counts;
                };
                
                var fetchProgress = function($stats){
                        fetchCount++;
                
                        $percent = (fetchCount/parseInt(config.counts.total))*100;
                
                        elem.find(config.fetchProgressBar +' div').css('width',$percent+'%');
                        elem.find(config.statusWrap+' p#fetched-status .done-count').html(fetchCount);
                        
                        compression(); 
                        
                        config.statsWrap.find('#percent').html(config.counts.percent);
                        config.statsWrap.find('#human').html(config.counts.human);
//                        if(config.counts.total === fetchCount){
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
                                if($is_fetched>0 && response.bytes!==null){
                                        fetchProgress(response.bytes);
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
	                    console.log(config);
                        $.each(config.ids, function(i, $id){
	                            console.log("Inside");
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
                
                var numberFormat = function (number, decimals, dec_point, thousands_sep) {
                        number = (number + '')
                          .replace(/[^0-9+\-Ee.]/g, '');
                        var n = !isFinite(+number) ? 0 : +number,
                          prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
                          sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
                          dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
                          s = '',
                          toFixedFix = function (n, prec) {
                            var k = Math.pow(10, prec);
                            return '' + (Math.round(n * k) / k)
                              .toFixed(prec);
                          };
                        // Fix for IE parseFloat(0.55).toFixed(0) = 0;
                        s = (prec ? toFixedFix(n, prec) : '' + Math.round(n))
                          .split('.');
                        if (s[0].length > 3) {
                          s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
                        }
                        if ((s[1] || '')
                          .length < prec) {
                          s[1] = s[1] || '';
                          s[1] += new Array(prec - s[1].length + 1)
                            .join('0');
                        }
                        return s.join(dec);
                };
                
                var numberFormatI18n = function($number, $decimals){
                        $formatted = numberFormat( 
                                $number,
                                Math.abs( parseInt( $decimals ) ),
                                wp_smpro_locale.decimal,
                                wp_smpro_locale.thousands_sep
                                );
                        return $formatted;

                };
                
                var formatBytes = function( $bytes, $precision ) {
                        if(!$precision){
                                $precision = 2;
                        }
			$units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
			$bytes = Math.max( $bytes, 0 );
			$pow   = Math.floor( ( $bytes ? Math.log( $bytes ) : 0 ) / Math.log( 1024 ) );
			$pow   = Math.min( $pow, $units.length - 1 );
			$bytes /= Math.pow( 1024, $pow );

			$size = numberFormatI18n( Math.round( $bytes, $precision ), $precision );
			$unit = $units[ $pow ];
                        
                        return $size + ' ' + $unit;
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
	                            $this = $(this);
                                if($('#fetch-notice').length>0){
                                        $('#fetch-notice').slideToggle();
	                                    jQuery('.accept-slow-notice').on('click', function(){
		                                    bulkStart($this);
	                                    });
                                }else{
                                        bulkStart($this);
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