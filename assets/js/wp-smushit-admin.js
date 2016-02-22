/**
 * Processes bulk smushing
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umeshsingla05@gmail.com>
 *
 */
var WP_Smush = WP_Smush || {};

/**
 * Show/hide the progress bar for Smushing/Restore/SuperSmush
 *
 * @param cur_ele
 * @param txt Message to be displayed
 * @param state show/hide
 */
var progress_bar = function( cur_ele, txt, state ) {

    //Update Progress bar text and show it
    var progress_button = cur_ele.parents().eq(1).find('.wp-smush-progress');

    if( 'show' == state ) {
        progress_button.find('span').html(txt);
        progress_button.removeClass('hidden');
    }else{
        progress_button.find('span').html( wp_smush_msgs.all_done );
        progress_button.hide();
    }
};
jQuery(function ($) {
    var smushAddParams = function (url, data) {
        if (!$.isEmptyObject(data)) {
            url += ( url.indexOf('?') >= 0 ? '&' : '?' ) + $.param(data);
        }

        return url;
    }
    // url for smushing
    WP_Smush.errors = [];
    WP_Smush.timeout = wp_smushit_data.timeout;
    /**
     * Checks for the specified param in URL
     * @param sParam
     * @returns {*}
     */
    WP_Smush.geturlparam = function (arg) {
        var $sPageURL = window.location.search.substring(1);
        var $sURLVariables = $sPageURL.split('&');

        for (var i = 0; i < $sURLVariables.length; i++) {
            var $sParameterName = $sURLVariables[i].split('=');
            if ($sParameterName[0] == arg) {
                return $sParameterName[1];
            }
        }
    };

    /** Send Ajax request for smushing the image **/
    WP_Smush.ajax = function ($id, $send_url, $getnxt, nonce) {
        "use strict";
        return $.ajax({
            type: "GET",
            data: {attachment_id: $id, get_next: $getnxt, _nonce: nonce},
            url: $send_url,
            timeout: WP_Smush.timeout,
            dataType: 'json'
        });
    };

    WP_Smush.Smush = function ($button, bulk, smush_type) {
        var self = this;

        this.init = function (arguments) {
            this.$button = $($button[0]);
            this.is_bulk = typeof bulk ? bulk : false;
            this.url = ajaxurl;
            this.button_text = this.is_bulk ? wp_smush_msgs.bulk_now : wp_smush_msgs.smush_now;
            this.$log = $(".smush-final-log");
            this.$button_span = this.$button.find("span");
            this.$loader = $(".wp-smush-loader-wrap").eq(0).clone();
            this.deferred = jQuery.Deferred();
            this.deferred.errors = [];
            this.ids = wp_smushit_data.unsmushed.length > 0 ? wp_smushit_data.unsmushed : wp_smushit_data.resmush;
            this.is_bulk_super_smush = wp_smushit_data.unsmushed.length > 0 ? false : true;
            this.resmush_count = wp_smushit_data.resmush.length;
            this.$status = this.$button.parent().find('.smush-status');
            //Added for NextGen support
            this.smush_type = typeof smush_type ? smush_type : false;
            this.single_ajax_suffix = this.smush_type ? 'smush_manual_nextgen' : 'wp_smushit_manual';
            this.bulk_ajax_suffix = this.smush_type ? 'wp_smushit_nextgen_bulk' : 'wp_smushit_bulk';
            this.url = this.is_bulk ? smushAddParams(this.url, {action: this.bulk_ajax_suffix}) : smushAddParams(this.url, {action: this.single_ajax_suffix});
        };

        //Show loader in button for single and bulk smush
        this.start = function () {

            this.$button.attr('disabled', 'disabled');
            this.$button.addClass('wp-smush-started');

            this.bulk_start();
            this.single_start();
        };

        this.bulk_start = function () {
            if (!this.is_bulk) return;
            $('#progress-ui').show();
            this.$button_span.text(wp_smush_msgs.progress);

            this.show_bulk_loader();

        };

        this.single_start = function () {
            if (this.is_bulk) return;
            this.show_loader();
            this.$status.removeClass("error");
        };

        this.enable_button = function () {
            this.$button.prop("disabled", false);
        };


        this.disable_button = function () {
            this.$button.prop("disabled", true);
        };

        this.show_loader = function () {
            progress_bar( this.$button, wp_smush_msgs.smushing, 'show');
        };

        this.show_bulk_loader = function() {
            //Loader
            if (!this.$button.find(".wp-smush-loader-wrap").length) {
                this.$button.prepend(this.$loader);
            } else {
                this.$loader = this.$button.find(".wp-smush-loader-wrap");
            }
            this.$loader.removeClass("hidden");
            this.$loader.show();
        }

        this.hide_loader = function () {
            progress_bar( this.$button, wp_smush_msgs.smushing, 'hide');
        };

        this.single_done = function () {
            if (this.is_bulk) return;

            this.hide_loader();
            this.request.done(function (response) {
                if (typeof response.data != 'undefined') {
                    //Append the smush stats or error
                    self.$status.html(response.data);
                    if (response.success && response.data !== "Not processed") {
                        self.$status.removeClass('hidden');
                        self.$button.parent().removeClass('unsmushed').addClass('smushed');
                        self.$button.remove();
                    } else {
                        self.$status.addClass("error");
                    }
                    self.$status.html(response.data);
                }
                self.$button_span.text(self.button_text);
                self.enable_button();
            }).error(function (response) {
                self.$status.html(response.data);
                self.$status.addClass("error");
                self.enable_button();
                self.$button_span.text(self.button_text);
            });

        };

        this.bulk_done = function () {
            if (!this.is_bulk) return;

            this.$loader.hide();

            // Add finished class
            this.$button.addClass('wp-smush-finished');

            // Remove started class
            this.$button.removeClass('wp-smush-started');

            //Enable the button
            this.disable_button();

            // Update button text
            self.$button_span.text(wp_smush_msgs.done);

            //Enable Resmush and scan button
            jQuery('.wp-resmush.wp-smush-action, .wp-smush-scan').removeAttr('disabled');
        };

        this.is_resolved = function () {
            "use strict";
            return this.deferred.state() === "resolved";
        };

        this.free_exceeded = function () {
            this.$loader.hide();

            // Add new class for css adjustment
            this.$button.removeClass('wp-smush-started');

            //Enable button
            this.$button.prop("disabled", false);

            // Update text
            this.$button.find('span').html(wp_smush_msgs.bulk_now);
        };

        this.update_progress = function (_res) {
            //If not bulk
            if (!this.is_bulk_super_smush && !this.is_bulk) {
                return;
            }

            if (!this.is_bulk_super_smush) {
                //handle progress for normal bulk smush
                var progress = ( _res.data.stats.smushed / _res.data.stats.total) * 100;
            } else {
                //Handle progress for Super smush progress bar
                if (wp_smushit_data.lossless.length > 0) {
                    $('#wp-smush-ss-progress-wrap .remaining-count').html(wp_smushit_data.lossless.length);
                } else if (wp_smushit_data.lossless.length == 0) {
                    $('#wp-smush-ss-progress-wrap #wp-smush-compression').html(wp_smush_msgs.all_supersmushed);
                }

            }

            //Update stats
            $('#wp-smush-compression #human').html(_res.data.stats.human);
            $('#wp-smush-compression #percent').html(_res.data.stats.percent);

            // increase the progress bar
            this._update_progress(_res.data.stats.smushed, progress);
        };

        this._update_progress = function (count, width) {
            "use strict";
            if (!this.is_bulk) {
                return;
            }

            if (!this.is_bulk_super_smush) {
                // get the progress bar
                var $progress_bar = jQuery('#wp-smush-progress-wrap #wp-smush-fetched-progress div');
                if ($progress_bar.length < 1) {
                    return;
                }
                $('.done-count').html(count);
                // increase progress
                $progress_bar.css('width', width + '%');
            } else {

                if (this.resmush_count > 0) {
                    var remaining_resmush = this.resmush_count - wp_smushit_data.lossless.length;
                    var progress_width = ( remaining_resmush / this.resmush_count * 100 );
                    var $progress_bar = jQuery('#wp-smush-ss-progress-wrap #wp-smush-ss-progress div');
                    if ($progress_bar.length < 1) {
                        return;
                    }
                    // increase progress
                    $progress_bar.css('width', progress_width + '%');
                }
            }

        };

        this.continue = function () {
            return this.ids.length > 0 && this.is_bulk;
        };

        this.increment_errors = function () {
            WP_Smush.errors.push(this.current_id);
        };

        //Send ajax request for smushing single and bulk, call update_progress on ajax response
        this.call_ajax = function () {

            var nonce_field = false;
            var nonce_value = '';
            this.current_id = this.is_bulk ? this.ids.shift() : this.$button.data("id"); //remove from array while processing so we can continue where left off
            nonce_field = this.$button.parent().find('#_wp_smush_nonce');
            if (nonce_field) {
                nonce_value = nonce_field.val();
            }

            this.request = WP_Smush.ajax(this.current_id, this.url, 0, nonce_value)
                .complete(function () {
                    if (!self.continue() || !self.is_bulk) {
                        self.deferred.resolve();
                    }
                })
                .error(function () {
                    self.increment_errors();
                }).done(function (res) {
                    //If no response or success is false, do not process further
                    if (typeof res == 'undefined' || !res || !res.success) {
                        if ('undefined' !== typeof res.data || typeof res.data.error_msg !== 'undefined') {
                            //Print the error on screen
                            self.$log.append(res.data.error_msg);
                            //We can proceed to next image
                            if (self.continue()) {
                                self.call_ajax();
                            }
                        }
                    }
                    if (typeof res.success === "undefined" || ( typeof res.success !== "undefined" && res.success === false && res.data.error !== 'bulk_request_image_limit_exceeded' )) {
                        self.increment_errors();
                    }

                    if (typeof res.data !== "undefined" && res.data.error == 'bulk_request_image_limit_exceeded' && !self.is_resolved()) {
                        self.free_exceeded();
                    } else {

                        if (self.is_bulk) {
                            self.update_progress(res);
                        }

                        if (self.continue()) {
                            self.call_ajax();
                        }
                    }
                    self.single_done();
                });

            self.deferred.errors = WP_Smush.errors;
            return self.deferred;
        };

        this.init(arguments);

        //Send ajax request for single and bulk smushing
        this.run = function () {

            // if we have a definite number of ids
            if (this.is_bulk && this.ids.length > 0) {
                this.call_ajax();
            }

            if (!this.is_bulk)
                this.call_ajax();

        };

        //Show bulk smush errors, and disable bulk smush button on completion
        this.bind_deferred_events = function () {

            this.deferred.done(function () {
                if (WP_Smush.errors.length) {
                    var error_message = wp_smush_msgs.error_in_bulk.replace("{{errors}}", WP_Smush.errors.length);
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
        //Disable Resmush and scan button
        jQuery('.wp-resmush.wp-smush-action, .wp-smush-scan').attr('disabled', 'disabled');

        //Check for ids, if there is none (Unsmushed or lossless), don't call smush function
        if (typeof wp_smushit_data == 'undefined' ||
            ( wp_smushit_data.unsmushed.length == 0 && wp_smushit_data.lossless.length == 0 )
        ) {

            return false;

        }

        $(".smush-remaining-images-notice").remove();

        new WP_Smush.Smush($(this), true);

        return;
    });

    /** Disable the action links **/
    var disable_links = function( c_element ) {

        var parent = c_element.parent();
        //reduce parent opacity
        parent.css({'opacity' : '0.5' });
        //Disable Links
        parent.find('a').attr('disabled', 'disabled');
    };

    /** Enable the Action Links **/
    var enable_links = function( c_element ) {

        var parent = c_element.parent();

        //reduce parent opacity
        parent.css({'opacity' : '1' });
        //Disable Links
        parent.find('a').removeAttr('disabled');
    };
    /**
     * Restore image request with a specified action for Media Library / NextGen Gallery
     * @param e
     * @param current_button
     * @param smush_action
     * @returns {boolean}
     */
    var process_smush_action = function( e, current_button, smush_action ) {

        //If disabled
        if( 'disabled' == current_button.attr('disabled') ) {
            return false;
        }

        e.preventDefault();

        //Remove Error
        jQuery('.wp-smush-error').remove();

        //Get the image ID and nonce
        var params = {
            action: smush_action,
            attachment_id: current_button.data('id'),
            _nonce: current_button.data('nonce')
        }

        //Reduce the opacity of stats and disable the click
        disable_links( current_button );

        progress_bar( current_button, wp_smush_msgs.smushing, 'show' );

        //Restore the image
        $.post(ajaxurl, params, function (r) {

            progress_bar( current_button, wp_smush_msgs.smushing, 'hide' );

            //reset all functionality
            enable_links( current_button );

            if (r.success) {
                //Show the smush button, and remove stats and restore option
                current_button.parents().eq(1).html(r.data.button);
            } else {
                if(r.data.message ) {
                    //show error
                    current_button.parent().append(r.data.message);
                }
            }
        })
    };
    var bulk_loader = function( $button ) {
        var loader = $(".wp-smush-loader-wrap").eq(0).clone();
        //Loader
        if (!$button.find(".wp-smush-loader-wrap").length) {
            $button.prepend(loader);
        } else {
            loader = $button.find(".wp-smush-loader-wrap");
        }
        loader.removeClass("hidden");
        loader.show();
    }

    /**
     * Handle the Smush Stats link click
     */
    $('body').on('click', 'a.smush-stats-details', function (e) {

        //If disabled
        if( 'disabled' == $(this).attr('disabled') ) {
            return false;
        }

        // prevent the default action
        e.preventDefault();
        //Replace the `+` with a `-`
        var slide_symbol = $(this).find('.stats-toggle');
        $(this).parents().eq(1).find('.smush-stats-wrapper').slideToggle();
        slide_symbol.text(slide_symbol.text() == '+' ? '-' : '+');

        return;
    });

    /** Handle smush button click **/
    $('body').on('click', '.wp-smush-send', function (e) {

        // prevent the default action
        e.preventDefault();
        new WP_Smush.Smush($(this), false);
    });

    /** Handle NextGen Gallery smush button click **/
    $('body').on('click', '.wp-smush-nextgen-send', function (e) {

        // prevent the default action
        e.preventDefault();
        new WP_Smush.Smush($(this), false, 'nextgen');
    });

    /** Handle NextGen Gallery Bulk smush button click **/
    $('body').on('click', '.wp-smush-nextgen-bulk', function (e) {

        // prevent the default action
        e.preventDefault();

        //Check for ids, if there is none (Unsmushed or lossless), don't call smush function
        if (typeof wp_smushit_data == 'undefined' ||
            ( wp_smushit_data.unsmushed.length == 0 && wp_smushit_data.lossless.length == 0 )
        ) {

            return false;

        }

        $(".smush-remaining-images-notice").remove();
        new WP_Smush.Smush($(this), true, 'nextgen');
        return;
    });

    /** Restore: Media Library **/
    jQuery('body').on('click', '.wp-smush-action.wp-smush-restore', function (e) {
        var current_button = $(this);
        var smush_action = 'smush_restore_image';
        process_smush_action( e, current_button, smush_action );
    });

    /** Resmush: Media Library **/
    jQuery('body').on('click', '.wp-smush-action.wp-smush-resmush', function (e) {
        var current_button = $(this);
        var smush_action = 'smush_resmush_image';
        process_smush_action( e, current_button, smush_action );
    });

    /** Restore: NextGen Gallery **/
    jQuery('body').on('click', '.wp-smush-action.wp-smush-nextgen-restore', function (e) {
        var current_button = $(this);
        var smush_action = 'smush_restore_nextgen_image';
        process_smush_action( e, current_button, smush_action );
    });

    /** Resmush: NextGen Gallery **/
    jQuery('body').on('click', '.wp-smush-action.wp-smush-nextgen-resmush', function (e) {
        var current_button = $(this);
        var smush_action = 'smush_resmush_nextgen_image';
        process_smush_action( e, current_button, smush_action );
    });
    //Scan For resmushing images
    jQuery('body').on('click', '.wp-smush-scan', function(e) {

        e.preventDefault();

        var button = jQuery(this);

        //Disable Bulk smush button and itself
        button.attr('disabled', 'disabled');
        jQuery('.wp-smush-button.wp-smush-send' ).attr('disabled', 'disabled');

        //Show Loader animation
        bulk_loader(button);

        //Ajax Params
        params = {
            action: 'scan_for_resmush',
            nonce: jQuery(this).data('nonce')
        };

        //Send ajax request and get ids if any
        $.get(ajaxurl, params, function (r) {
            setTimeout(
                function()
                {
                    //do something special
                }, 5000);
            //Check if we have the ids,  initialize the local variable
            if( 'undefined' != r.data.resmush_ids ) {
                wp_smushit_data.resmush = r.data.resmush_ids;
            }
            //If the div already exists, just replace the content, else append it
            if( jQuery('.wp-resmush-wrapper').length > 0 ) {
                jQuery('.wp-resmush-wrapper').replaceWith(r.data.content);
            }else{
                jQuery('#wp-smush-resmush').append(r.data.content);
            }
        });

        //Remove Loader
        button.find(".wp-smush-loader-wrap").hide();

        //Enable the Bulk Smush Button and itself
        button.removeAttr('disabled');
        jQuery('.wp-smush-button.wp-smush-send').removeAttr('disabled');


    });

    /** Modify Title style using jQuery tooltip, Show help text on help image hover **/
    $('.wp-smush-title').tooltip();

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

})(jQuery);
