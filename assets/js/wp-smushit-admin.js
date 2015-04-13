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

    WP_Smush.Smush = function( $button, bulk ){
        var self = this;

        this.init = function( arguments ){
            this.$button = $($button[0]);
            this.is_bulk = typeof bulk ? bulk : false;
            this.url = this.is_bulk ? $bulk_send_url : $manual_smush_url;
            this.button_text = this.is_bulk ? wp_smush_msgs.bulk_now : wp_smush_msgs.smush_now;
            this.$log = $(".smush-final-log");
            this.$button_span = this.$button.find("span");
            this.$loader = $(".wp-smush-loader-wrap").eq(0).clone();
            this.deferred = jQuery.Deferred();
            this.deferred.errors = [];
            this.ids = wp_smushit_data.unsmushed;
            this.$status = this.$button.parent().find('.smush-status');
        };

        this.start = function(){

            this.$button.attr('disabled', 'disabled');
            this.$button.addClass('wp-smush-started');
            if( !this.$button.find(".wp-smush-loader-wrap").length ){
                this.$button.prepend(this.$loader);
            }else{
                this.$loader = this.$button.find(".wp-smush-loader-wrap");
            }


            this.show_loader();
            this.bulk_start();
            this.single_start();
        };

        this.bulk_start = function(){
            if( !this.is_bulk ) return;
            $('#progress-ui').show();
            this.$button_span.text(wp_smush_msgs.progress);
            this.show_loader();

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
            this._update_progress(stats.data.smushed, progress);
        };

        this._update_progress = function( count, width ){
            "use strict";
            // get the progress bar
            var $progress_bar = jQuery('#wp-smush-progress-wrap #wp-smush-fetched-progress div');
            if ($progress_bar.length < 1) {
                return;
            }
            $('.done-count').html(count);
            // increase progress
            $progress_bar.css('width', width + '%');

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
    });

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
