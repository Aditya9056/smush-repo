/**
 * Processes bulk smushing
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 *
 */
var WP_Smush = WP_Smush || {};
jQuery('document').ready(function ($) {
    // url for smushing
    $bulk_send_url = ajaxurl + '?action=wp_smushit_bulk';
    $manual_smush_url = ajaxurl + '?action=wp_smushit_manual';
    $remaining = '';
    $smush_done = false;
    timeout = 60000;
    /**
     * Checks for the specified param in URL
     * @param sParam
     * @returns {*}
     */
    function geturlparam(arg) {
        $sPageURL = window.location.search.substring(1);
        $sURLVariables = $sPageURL.split('&');

        for (var i = 0; i < $sURLVariables.length; i++) {
            $sParameterName = $sURLVariables[i].split('=');
            if ($sParameterName[0] == arg) {
                return $sParameterName[1];
            }
        }
    }

    /**
     * Show progress of smushing
     */
    function smushit_progress(stats) {

        $progress = ( stats.data.smushed / stats.data.total) * 100;

        // calculate %
        if ($remaining != 0) {
            $remaining--;
        }

        //Update stats
        jQuery('#wp-smush-compression #human').html(stats.data.human);
        jQuery('#wp-smush-compression #percent').html(stats.data.percent);

        // increase the progress bar
        wp_smushit_change_progress_status(stats.data.smushed, $progress);
        if ($remaining == 0) {
            wp_smushit_all_done();
        }

    }


    WP_Smush.ajax = function ($id, $send_url, $getnxt) {
        "use strict";
        return $.ajax({
            type: "GET",
            data: {attachment_id: $id, get_next: $getnxt},
            url: $send_url,
            timeout: WP_Smush.timeout,
            dataType: 'json'
        });
    };

    /**
     * Send ajax request for smushing
     *
     * @param {type} $id
     * @param {type} $getnxt
     * @returns {unresolved}
     */
    WP_Smush.smushitRequest = function ($id, $getnxt, $is_single, current_elem) {

        //Specify the smush URL, for single or bulk smush
        var $send_url = $is_single ? $manual_smush_url : $bulk_send_url;
        if (typeof current_elem != 'undefined') {
            var $status = current_elem.parent().find('.smush-status');

            $status.removeClass("error");
        }
        // make request
        return WP_Smush.ajax($id, $send_url, $getnxt).done(function (response) {

            //Handle bulk smush progress
            if (!$is_single) {
                if (response.success) {
                    // increase progressbar
                    smushit_progress(response);
                }
                return;
            } else {
                //Check for response message
                if (typeof response.data != 'undefined') {
                    //Append the smush stats or error

                    $status.html(response.data);

                    if (response.success && response.data !== "Not processed") {
                        //For grid View
                        if (jQuery('.smush-wrap.unsmushed').length > 0) {
                            current_elem.parent().removeClass('unsmushed').addClass('smushed');
                        }
                        current_elem.remove();
                    } else {
                        $status.addClass("error");
                    }
                    $status.html(response.data);
                }
                //For grid View
                if (jQuery('.smush-wrap.unsmushed').length > 0) {
                    jQuery('.smush-wrap.unsmushed').removeClass('unsmushed').addClass('smushed');
                }
            }
        }).error(function (response) {
            $status.html(response.data);
            $status.addClass("error");
        });
    };

    /**
     * Change the button status on bulk smush completion
     *
     * @returns {undefined}
     */
    function wp_smushit_all_done() {
        $button = jQuery('.wp-smpushit-container #wp-smush-send');

        // copy the loader into an object
        $loader = $button.find('.floatingCirclesG');

        // remove the loader
        $loader.hide();

        // empty the current text
        $button.find('span').html('');

        // add new class for css adjustment
        $button.removeClass('wp-smush-started');
        $button.addClass('wp-smush-finished');

        // add the progress text
        $button.find('span').html(wp_smush_msgs.done);

        return;
    }

    /**
     * Change the button status on bulk smush free pause
     *
     * @returns {undefined}
     */
    function wp_smushit_free_done() {
        $button = jQuery('.wp-smpushit-container #wp-smush-send');

        // copy the loader into an object
        $loader = $button.find('.floatingCirclesG');

        // remove the loader
        $loader.hide();

        // empty the current text
        $button.find('span').html('');

        // add new class for css adjustment
        $button.removeClass('wp-smush-started');
        //$button.addClass('wp-smush-finished');
        $button.prop("disabled", false);

        // add the progress text
        $button.find('span').html(wp_smush_msgs.bulk_now);

        return;
    }

    /**
     * Free limite exceeded
     *
     * @returns {undefined}
     */
    function wp_smushit_free_exceeded() {
        "use strict";
        return wp_smushit_free_done();
    }

    /**
     * Change progress bar and status
     *
     * @param {type} $count
     * @param {type} $width
     * @returns {undefined}
     */
    function wp_smushit_change_progress_status($count, $width) {
        // get the progress bar
        var $progress_bar = jQuery('#wp-smush-progress-wrap #wp-smush-fetched-progress div');
        if ($progress_bar.length < 1) {
            return;
        }
        $('.done-count').html($count);
        // increase progress
        $progress_bar.css('width', $width + '%');

    }

    /**
     * Send for bulk smushing
     *
     * @returns {undefined}
     */
    //function wp_smushit_bulk_smush() {
    //	// instantiate our deferred object for piping
    //	var errors = [],
    //		$log = $(".smush-final-log");
    //
    //	//Show progress bar
    //	$('#progress-ui').show();
    //
    //	// if we have a definite number of ids
    //	if (wp_smushit_data.unsmushed.length > 0) {
    //
    //		var id = wp_smushit_data.unsmushed.shift(); //remove from array while processing so we can continue where left off
    //		$remaining = wp_smushit_data.unsmushed.length;
    //
    //		ajax = WP_Smush.smushitRequest(id, 0, false)
    //			.error(function () {
    //				errors.push(id);
    //
    //			}).done(function (res) {
    //				if (typeof res.success === "undefined" || ( typeof res.success !== "undefined" && res.success === false && res.data.error !== 'bulk_request_image_limit_exceeded' )) {
    //					errors.push(id);
    //				}
    //				if (typeof res.data !== "undefined" && res.data.error == 'bulk_request_image_limit_exceeded') {
    //					wp_smushit_free_exceeded();
    //					return;
    //				}
    //				//Call it back
    //				wp_smushit_bulk_smush();
    //			});
    //
    //		if (errors.length) {
    //			var error_message = wp_smush_msgs.error_in_bulk.replace("{{errors}}", errors.length);
    //			$log.append(error_message);
    //		}
    //
    //	}
    //
    //}




    /**
     * Send a ajax request for smushing and show waiting
     */
    WP_Smush.sendRequest = function (current_elem) {

        //Get media id
        var $id = current_elem.data('id');

        if (!$id) {
            return false;
        }

        //Send the ajax request
        return WP_Smush.smushitRequest($id, 0, true, current_elem).complete(function () {
            "use strict";
            current_elem.prop("disabled", false);
        });
    };

    WP_Smush.Smush = function( $button, bulk ){
        var self = this;

        this.init = function( arguments ){
            this.$button = $($button[0]);
            this.is_bulk = typeof bulk ? bulk : false;
            this.url = this.is_bulk ? $bulk_send_url : $manual_smush_url;
            this.button_text = this.is_bulk ? wp_smush_msgs.bulk_now : wp_smush_msgs.smush_now;
            this.$log = $(".smush-final-log");
            this.$button_span = this.$button.find("span");
            this.$loader = $(".wp-smush-loader-wrap").clone();
            this.deferred = jQuery.Deferred();
            this.deferred.errors = [];
            this.ids = wp_smushit_data.unsmushed;
            this.$status = this.$button.parent().find('.smush-status');
        };

        this.start = function(){

            this.$button.attr('disabled', 'disabled');
            this.$button.addClass('wp-smush-started');
            if( !this.$button.find(".wp-smush-loader-wrap").length )
                this.$button.prepend(this.$loader);

            this.show_loader();
            this.bulk_start();
            this.single_start();
        };

        this.bulk_start = function(){
            if( !this.is_bulk ) return;
            $('#progress-ui').show();
            this.$button_span.text(wp_smush_msgs.progress);

        };

        this.single_start = function(){
            if( this.is_bulk ) return;
            this.$button_span.text(wp_smush_msgs.sending);
            this.$status.removeClass("error");
        };

        this.enable_button = function(){
            this.$button.prop("disabled", false);
        };


        this.disable_button = function(){
            this.$button.prop("disabled", true);
        };

        this.show_loader = function(){
            this.$loader.removeClass("hidden");
            this.$loader.show();
        };

        this.hide_loader = function(){
            this.$loader.hide();
        };

        this.single_done = function(){
            if( this.is_bulk ) return;

            this.hide_loader();
            this.request.done(function(response){
                if (typeof response.data != 'undefined') {
                    //Append the smush stats or error
                    self.$status.html(response.data);
                    if (response.success && response.data !== "Not processed") {
                        self.$button.remove();
                    } else {
                        self.$status.addClass("error");
                    }
                    self.$status.html(response.data);
                }
                self.$button_span.text( self.button_text );
                self.enable_button();
            }).error(function (response) {
                self.$status.html(response.data);
                self.$status.addClass("error");
                self.enable_button();
                self.$button_span.text( self.button_text );
            });

        };

        this.bulk_done = function(){
           if( !this.is_bulk ) return;

           this.hide_loader();

            // Add finished class
            this.$button.addClass('wp-smush-finished');

            // Remove started class
            this.$button.removeClass('wp-smush-started');

            //Enable the button
            this.disable_button();

            // Update button text
            self.$button_span.text( wp_smush_msgs.done );
        };

        this.free_exceeded = function(){
            this.hide_loader();

            // Add new class for css adjustment
            this.$button.removeClass('wp-smush-started');

            //Enable button
            this.$button.prop("disabled", false);

            // Update text
            this.$button.find('span').html(wp_smush_msgs.bulk_now);
        };

        this.update_progress = function(stats){
            var progress = ( stats.data.smushed / stats.data.total) * 100;

            //Update stats
            $('#wp-smush-compression #human').html(stats.data.human);
            $('#wp-smush-compression #percent').html(stats.data.percent);

            // increase the progress bar
            wp_smushit_change_progress_status(stats.data.smushed, progress);
        };

        this.continue = function(){
            return  this.ids.length > 0 && this.is_bulk;
        };

        this.increment_errors = function(){
            this.deferred.errors.push(this.current_id);
        };

        this.call_ajax = function(){

            this.current_id = this.ids.shift(); //remove from array while processing so we can continue where left off

            this.request = WP_Smush.ajax(this.current_id, this.url , 0)
                .complete(function(){
                    if( !self.continue() || !self.is_bulk ){
                        self.deferred.resolve();
                    }
                })
                .error(function () {
                    self.increment_errors();
                }).done(function (res) {
                    self.update_progress(res);

                    if (typeof res.success === "undefined" || ( typeof res.success !== "undefined" && res.success === false && res.data.error !== 'bulk_request_image_limit_exceeded' )) {
                       self.increment_errors();
                    }

                    if (typeof res.data !== "undefined" && res.data.error == 'bulk_request_image_limit_exceeded') {
                        self.free_exceeded();
                    }else{
                        if( self.continue() ){
                            self.call_ajax();
                        }else{
                            self.bulk_done();
                        }
                    }
                    self.single_done();
                });

        };

        this.init( arguments );
        this.run = function(){

            // if we have a definite number of ids
            if (this.ids.length > 0) {
                this.call_ajax();
            }

        };

        this.bind_deferred_events = function(){

            this.deferred.done(function(){
                if (self.deferred.errors.length) {
                    var error_message = wp_smush_msgs.error_in_bulk.replace("{{errors}}", self.deferred.errors.length);
                    self.$log.append(error_message);
                }
                self.bulk_done();
            });

        };

        this.start();
        this.run();
        this.bind_deferred_events();
        return this.deferred;
    };
    /**
     * Handle the start button click
     */
    $('button[name="smush-all"]').on('click', function (e) {
        // prevent the default action
        e.preventDefault();

        $(".smush-remaining-images-notice").remove();
        //Enable Cancel button
        $('#wp-smush-cancel').prop('disabled', false);

        new WP_Smush.Smush( $(this), true );

        //buttonProgress(jQuery(this), wp_smush_msgs.progress, wp_smushit_bulk_smush());
    });

    //Handle smush button click
    $('body').on('click', '.wp-smush-send', function (e) {

        // prevent the default action
        e.preventDefault();
        new WP_Smush.Smush( $(this), false );
        return;
        var $this = $(this);
        //if item view
        if (jQuery('.attachment-info .attachment-compat').length > 0) {
            /**
             * Handle the media library button click
             */
            jQuery('.attachment-info .attachment-compat').wpsmush({
                'msgs': wp_smush_msgs,
                'ajaxurl': ajaxurl,
                'isSingle': true
            });
        }

        var deffered = WP_Smush.sendRequest($this);
        //Add loader
        buttonProgress(jQuery(this), wp_smush_msgs.progress, deffered);


        //remove all smush notices
        $('.smush-notices').remove();
        $this.text(wp_smush_msgs.sending);
        //Send Smush request

        deffered.complete(function () {
            $this.text(wp_smush_msgs.smush_now);
        });
        $this.prop("disabled", true);

        return;
    });

    var buttonProgress = function ($button, $text, deferred) {

        // copy the spinner into an object
        $spinner = jQuery('#wp-smush-loader-wrap').clone();

        // empty the current text
        $button.find('span').html('').addClass('wp-smushing');

        // add new class for css adjustment
        $button.addClass('wp-smpro-started');

        // prepend the spinner html
        $button.prepend($spinner);

        //Show spinner
        $button.find('.wp-smush-loader-wrap').removeClass('hidden');

        // add the progress text
        $button.find('span').html($text);

        // disable the button
        $button.prop('disabled', true);

        // done
        deferred.done(function () {
            $spinner.remove();
            $button.removeClass("wp-smushing");
            if (deferred.errors && deferred.errors.length) {
                $button.text(wp_smush_msgs.bulk_now);
            } else {
                $button.text(wp_smush_msgs.done);
            }


        });

        return [$spinner, $button];
    };
});
(function ($) {
    var Smush = function (element, options) {
        var elem = $(element);

        var defaults = {
            isSingle: false,
            ajaxurl: '',
            msgs: {},
            msgClass: 'wp-smush-msg',
            ids: []
        };
    };
    $.fn.wpsmush = function (options) {
        return this.each(function () {
            var element = $(this);

            // Return early if this element already has a plugin instance
            if (element.data('wpsmush'))
                return;

            // pass options to plugin constructor and create a new instance
            var wpsmush = new Smush(this, options);

            // Store plugin object in this element's data
            element.data('wpsmush', wpsmush);
        });
    };
    if (typeof wp !== 'undefined') {
        _.extend(wp.media.view.AttachmentCompat.prototype, {
            render: function () {
                $view = this;
                //Dirty hack, as there is no way around to get updated status of attachment
                $html = jQuery.get(ajaxurl + '?action=attachment_status', {'id': this.model.get('id')}, function (res) {
                    $view.$el.html(res.data);
                    $view.views.render();
                    return $view;
                });
            }
        });
    }
})(jQuery);
