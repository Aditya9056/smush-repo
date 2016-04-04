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
    };
    // url for smushing
    WP_Smush.errors = [];
    WP_Smush.timeout = wp_smushit_data.timeout;
    /**
     * Checks for the specified param in URL
     * @param arg
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

    WP_Smush.Smush = function ($button, bulk, smush_type) {
        var self = this;
        var skip_resmush = $button.data('smush');
        //If smush attribute is not defined, Need not skip resmush ids
        skip_resmush = ( ( typeof skip_resmush == typeof undefined ) || skip_resmush == false ) ? false : true;

        this.init = function () {
            this.$button = $($button[0]);
            this.is_bulk = typeof bulk ? bulk : false;
            this.url = ajaxurl;
            this.$log = $(".smush-final-log");
            this.deferred = jQuery.Deferred();
            this.deferred.errors = [];

            //If button has resmush class, and we do have ids that needs to resmushed, put them in the list
            this.ids = wp_smushit_data.resmush.length > 0 && !skip_resmush ? wp_smushit_data.resmush.concat( wp_smushit_data.unsmushed ) : wp_smushit_data.unsmushed ;

            this.is_bulk_resmush = wp_smushit_data.resmush.length > 0 && !skip_resmush ? true : false;

            this.$status = this.$button.parent().find('.smush-status');

            //Added for NextGen support
            this.smush_type = typeof smush_type ? smush_type : false;
            this.single_ajax_suffix = this.smush_type ? 'smush_manual_nextgen' : 'wp_smushit_manual';
            this.bulk_ajax_suffix = this.smush_type ? 'wp_smushit_nextgen_bulk' : 'wp_smushit_bulk';
            this.url = this.is_bulk ? smushAddParams(this.url, {action: this.bulk_ajax_suffix}) : smushAddParams(this.url, {action: this.single_ajax_suffix});
        };

        /** Send Ajax request for smushing the image **/
        WP_Smush.ajax = function (is_bulk_resmush, $id, $send_url, $getnxt, nonce) {
            "use strict";
            var param = {
                is_bulk_resmush: is_bulk_resmush,
                attachment_id: $id,
                get_next: $getnxt,
                _nonce: nonce
            };
            param = jQuery.param(param);
            return $.ajax({
                type: "GET",
                data: param,
                url: $send_url,
                timeout: WP_Smush.timeout,
                dataType: 'json'
            });
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

            //Hide Notice
            $('.bulk-smush-wrapper .wp-smush-notice').hide();

            //Hide the Bulk Div
            $('.wp-smush-bulk-wrapper').hide();

            //Show the Progress Bar
            $('.bulk-smush-wrapper .wp-smush-bulk-progress-bar-wrapper').show();

        };

        this.single_start = function () {
            if (this.is_bulk) return;
            this.show_loader();
            this.$status.removeClass("error");
        };

        this.enable_button = function () {
            this.$button.prop("disabled", false);
            //For Bulk process, Enable other buttons
            $('button.wp-smush-all').removeAttr('disabled');
            $('button.wp-smush-scan').removeAttr('disabled');
        };

        this.show_loader = function () {
            progress_bar(this.$button, wp_smush_msgs.smushing, 'show');
        };

        this.hide_loader = function () {
            progress_bar(this.$button, wp_smush_msgs.smushing, 'hide');
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
                    self.$status.html( response.data.status );
                    //Check if stats div exists
                    var parent = self.$status.parent();
                    var stats_div = parent.find('.smush-stats-wrapper');
                    if( 'undefined' != stats_div && stats_div.length ) {
                        stats_div.replaceWith( response.data.stats );
                    }else{
                        parent.append( response.data.stats );
                    }
                }
                self.enable_button();
            }).error(function (response) {
                self.$status.html(response.data);
                self.$status.addClass("error");
                self.enable_button();
            });

        };

        /** After the Bulk Smushing has been Finished **/
        this.bulk_done = function () {
            if (!this.is_bulk) return;

            //Enable the button
            this.enable_button();

            //Show
            $('.bulk-smush-wrapper .wp-smush-all-done').show();

            //Hide the Progress Bar
            $('.wp-smush-bulk-progress-bar-wrapper').hide();

            //Enable Resmush and scan button
            jQuery('.wp-resmush.wp-smush-action, .wp-smush-scan').removeAttr('disabled');
        };

        this.is_resolved = function () {
            "use strict";
            return this.deferred.state() === "resolved";
        };

        this.free_exceeded = function () {

            // Add new class for css adjustment
            this.$button.removeClass('wp-smush-started');

            //Enable button
            this.$button.prop("disabled", false);
        };

        this.update_progress = function (_res) {
            //If not bulk
            if (!this.is_bulk_resmush && !this.is_bulk) {
                return;
            }

            if (!this.is_bulk_resmush) {
                //handle progress for normal bulk smush
                var progress = ( _res.data.stats.smushed / _res.data.stats.total) * 100;
            } else {
                //If the Request was successful, Update the progress bar
                if (_res.success) {
                    //Handle progress for Super smush progress bar
                    if (wp_smushit_data.resmush.length > 0) {
                        //Update the Count
                        $('.wp-smush-images-remaining').html(wp_smushit_data.resmush.length);
                    } else if (wp_smushit_data.resmush.length == 0) {
                        //If all images are resmushed, show the All Smushed message

                        //Show All Smushed
                        $('.bulk-resmush-wrapper .wp-smush-all-done').removeClass('hidden');

                        //Hide Everything else
                        $('.wp-smush-resmush-wrap, .wp-smush-bulk-progress-bar-wrapper').hide();
                    }

                }
            }

            //if we have received the progress data, update the stats else skip
            if ('undefined' != typeof _res.data.stats) {
                var smushed_count = self.is_bulk_resmush ? ( _res.data.stats.total - _res.data.stats.resmush_count ) : _res.data.stats.smushed;
                //Update stats
                $('.smush-total-reduction-percent .wp-smush-stats').html(_res.data.stats.percent);
                $('.smush-total-reduction-bytes .wp-smush-stats').html(_res.data.stats.human);

                $('.smush-attachments .wp-smush-stats .smushed-count, .wp-smush-images-smushed').html(smushed_count);
                if( $('.super-smush-attachments .smushed-count').length && 'undefined' != typeof _res.data.stats.super_smushed ) {
                    $('.super-smush-attachments .smushed-count').html( _res.data.stats.super_smushed );
                }

                if( $('.smush-attachments .wp-smush-remaining-count').length && 'undefined' != typeof self.ids ) {
                    $('.smush-attachments .wp-smush-remaining-count').html( self.ids.length );
                }

                // increase the progress bar
                this._update_progress(_res.data.stats.smushed, progress);
            }
        };

        this._update_progress = function (count, width) {
            "use strict";
            if (!this.is_bulk) {
                return;
            }
            //Update the Progress Bar Width
            // get the progress bar
            var $progress_bar = jQuery('.bulk-smush-wrapper .wp-smush-progress-inner');
            if ($progress_bar.length < 1) {
                return;
            }
            // increase progress
            $progress_bar.css('width', width + '%');

        };

        this.continue = function () {
            return this.ids.length > 0 && this.is_bulk;
        };

        this.increment_errors = function ( id ) {
            WP_Smush.errors.push(id);
        };

        //Send ajax request for smushing single and bulk, call update_progress on ajax response
        this.call_ajax = function () {
            var nonce_value = '';
            this.current_id = this.is_bulk ? this.ids.shift() : this.$button.data("id"); //remove from array while processing so we can continue where left off
            var nonce_field = this.$button.parent().find('#_wp_smush_nonce');
            if (nonce_field) {
                nonce_value = nonce_field.val();
            }

            this.request = WP_Smush.ajax(this.is_bulk_resmush, this.current_id, this.url, 0, nonce_value)
                .error(function () {
                    self.increment_errors( self.current_id );
                }).done(function (res ) {
                    //Increase the error count if any
                    if (typeof res.success === "undefined" || ( typeof res.success !== "undefined" && res.success === false && res.data.error !== 'bulk_request_image_limit_exceeded' )) {
                        self.increment_errors( self.current_id );
                    }
                    //If no response or success is false, do not process further
                    if (typeof res == 'undefined' || !res || !res.success) {
                        if ('undefined' !== typeof res.data || typeof res.data.error_msg !== 'undefined') {
                            //Print the error on screen
                            self.$log.append(res.data.error_msg);
                            self.$log.removeClass('hidden');
                        }
                    }

                    if (typeof res.data !== "undefined" && res.data.error == 'bulk_request_image_limit_exceeded' && !self.is_resolved()) {
                        self.free_exceeded();
                    } else {

                        if (self.is_bulk) {
                            self.update_progress(res);
                        }
                    }
                    self.single_done();
                }).complete(function () {
                    if (!self.continue() || !self.is_bulk) {
                        //Calls deferred.done()
                        self.deferred.resolve();
                    }else{
                        self.call_ajax();
                    }
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
                    var error_message = '<div class="wp-smush-ajax-error">' + wp_smush_msgs.error_in_bulk.replace("{{errors}}", WP_Smush.errors.length) + '</div>';
                    self.$log.prepend(error_message);
                }

                self.bulk_done();

                //Re enable the buttons
                jQuery('.wp-smush-button:not(.wp-smush-finished), .wp-smush-scan').removeAttr('disabled');
            });

        };
        /** Handles the Cancel button Click
         *
         * Update the UI, and enables the bulk smush button
         *
         **/
        this.cancel_ajax = function () {
            jQuery('.wp-smush-cancel-bulk').on('click', function () {
                self.request.abort();
                self.enable_button();
                self.$button.removeClass('wp-smush-started');
                $('.wp-smush-bulk-wrapper').show();

                //Show the Progress Bar
                $('.wp-smush-bulk-progress-bar-wrapper').hide();
            });
        };

        this.start();
        this.run();
        this.bind_deferred_events();

        //Handle Cancel Ajax
        this.cancel_ajax();

        return this.deferred;
    };

    /**
     * Handle the Bulk Smush/ Bulk Resmush button click
     */
    $('body').on('click', 'button.wp-smush-all', function (e) {

        // prevent the default action
        e.preventDefault();

        jQuery('.wp-smush-notice.wp-smush-settings-updated').remove();

        //Disable Resmush and scan button
        jQuery('.wp-resmush.wp-smush-action, .wp-smush-scan, .wp-smush-button').attr('disabled', 'disabled');

        //Check for ids, if there is none (Unsmushed or lossless), don't call smush function
        if (typeof wp_smushit_data == 'undefined' ||
            ( wp_smushit_data.unsmushed.length == 0 && wp_smushit_data.resmush.length == 0 )
        ) {

            return false;

        }

        $(".wp-resmush-remaining").hide();
        $(".smush-remaining-images-notice").remove();

        new WP_Smush.Smush($(this), true);

        
    });

    /** Disable the action links **/
    var disable_links = function (c_element) {

        var parent = c_element.parent();
        //reduce parent opacity
        parent.css({'opacity': '0.5'});
        //Disable Links
        parent.find('a').attr('disabled', 'disabled');
    };

    /** Enable the Action Links **/
    var enable_links = function (c_element) {

        var parent = c_element.parent();

        //reduce parent opacity
        parent.css({'opacity': '1'});
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

        //Hide stats
        jQuery('.smush-stats-wrapper').hide();

        //Get the image ID and nonce
        var params = {
            action: smush_action,
            attachment_id: current_button.data('id'),
            _nonce: current_button.data('nonce')
        };

        //Reduce the opacity of stats and disable the click
        disable_links( current_button );

        progress_bar( current_button, wp_smush_msgs.smushing, 'show' );

        //Restore the image
        $.post(ajaxurl, params, function (r) {

            progress_bar( current_button, wp_smush_msgs.smushing, 'hide' );

            //reset all functionality
            enable_links( current_button );

            if (r.success && 'undefined' != typeof( r.data.button ) ) {
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

        
    });

    /** Handle smush button click **/
    $('body').on('click', '.wp-smush-send:not(.wp-smush-resmush)', function (e) {

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
            ( wp_smushit_data.unsmushed.length == 0 && wp_smushit_data.resmush.length == 0 )
        ) {

            return false;

        }

        jQuery('.wp-smush-button, .wp-smush-scan').attr('disabled', 'disabled');
        $(".smush-remaining-images-notice").remove();
        new WP_Smush.Smush($(this), true, 'nextgen');
        
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
        var spinner = button.parent().find('.spinner');

        //Check if type is set in data attributes
        var scan_type = button.data('type');
        scan_type = 'undefined' == typeof scan_type ? 'media' : scan_type;

        //Show spinner
        spinner.addClass('is-active');

        //Disable Bulk smush button and itself
        button.attr('disabled', 'disabled');
        jQuery('.wp-smush-button' ).attr('disabled', 'disabled');

        //Hide Settings changed Notice
        jQuery('.wp-smush-settings-changed').hide();

        //Show Loading Animation
        jQuery('.bulk-resmush-wrapper .wp-smush-progress-bar-wrap').removeClass('hidden');

        //Ajax Params
        var params = {
            action: 'scan_for_resmush',
            type: scan_type,
            get_ui: true,
            wp_smush_options_nonce: jQuery('#wp_smush_options_nonce').val()
        };

        //Send ajax request and get ids if any
        $.get(ajaxurl, params, function (r) {
            //Check if we have the ids,  initialize the local variable
            if( 'undefined' != r.data.resmush_ids ) {
                wp_smushit_data.resmush = r.data.resmush_ids;
                var smushed_count = wp_smushit_data.count_smushed - r.data.resmush_ids.length;
                jQuery('.smush-attachments .smushed-count').html(smushed_count)

            }
            //Hide the Existing wrapper
            var notices = $('.bulk-smush-wrapper .wp-smush-notice');
            if (notices.length > 0) {
                notices.hide();
            }
            //If content is recieved, Prepend it
            if( 'undefined' != typeof r.data.content ) {
                $('.bulk-smush-wrapper .box-container').prepend( r.data.content );
            }

            //Show Bulk wrapper
            $('.wp-smush-bulk-wrapper').show();

        }).always( function() {

            //Hide the progress bar
            jQuery('.bulk-smush-wrapper .wp-smush-bulk-progress-bar-wrapper').hide();

            //Enable the Bulk Smush Button and itself
            button.removeAttr('disabled');

            //Hide Spinner
            spinner.removeClass('is-active');
            jQuery('.wp-smush-button').removeAttr('disabled');
        });

    });

    /** Modify Title style using jQuery tooltip, Show help text on help image hover **/
    //If tooltip function exists
    if( jQuery.isFunction(jQuery.fn.tooltip) ) {
        $('.wp-smush-title').tooltip();
    }

    //Dismiss Welcome notice
    jQuery('.smush-dismiss-welcome').on('click', function(e) {
        e.preventDefault();
        var $el = $(this).parents().eq(1);
        $el.fadeTo( 100, 0, function() {
            $el.slideUp( 100, function() {
                $el.remove();
            });
        });
        //Send a ajax request to save the dismissed notice option
        var param = {
            action: 'dismiss_smush_notice'
        };
        $.post(ajaxurl, param );
    });

    //Remove Notice
    $('.wp-smush-settings-updated .dev-icon-cross').on('click', function(e) {
        e.preventDefault();
        var $el = $(this).parent();
        $el.fadeTo( 100, 0, function() {
            $el.slideUp( 100, function() {
                $el.remove();
            });
        });
    });

    //On Click Update Settings. Check for change in settings
    $('#wp-smush-save-settings').on('click', function(e) {
        e.preventDefault();

        var self = $(this);
        //Update text
        self.attr('disabled', 'disabled').addClass('button-grey').val( wp_smush_msgs.checking );
        self.parent().find('.spinner').addClass('is-active');

        //Check if type is set in data attributes
        var scan_type = self.data('type');
        scan_type = 'undefined' == typeof scan_type ? 'media' : scan_type;

        //Ajax param
        var param = {
            action: 'scan_for_resmush',
            scan_type: scan_type
        };

        param = jQuery.param( param ) + '&' + jQuery('.smush-settings-wrapper form').serialize();

        //Send ajax, Update Settings, And Check For resmush
        jQuery.post(ajaxurl, param).done(function () {
            jQuery('.smush-settings-wrapper form').submit();
            return true;
        });
    });

    //On Resmush click
    jQuery('.wp-smush-skip-resmush').on('click', function (e) {
        e.preventDefault();
        var self = jQuery(this);
        var container = self.parents().eq(1);

        //Remove Parent div
        var $el = self.parent();
        $el.fadeTo( 100, 0, function() {
            $el.slideUp( 100, function() {
                $el.remove();
            });
        });
        //Remove Settings Notice
        jQuery('.wp-smush-notice.wp-smush-settings-updated').remove();

        //Set button attribute to skip re-smush ids
        container.find('.wp-smush-all').attr('data-smush', 'skip_resmush');

        //Update Stats
        if( wp_smushit_data.count_smushed == wp_smushit_data.count_total ) {

            //Show all done notice
            jQuery('.wp-smush-notice.wp-smush-all-done').show();

            //Hide Smush button
            jQuery('.wp-smush-bulk-wrapper ').hide()

        }

        var smushed_count = 'undefined' != typeof wp_smushit_data.count_smushed ? wp_smushit_data.count_smushed : 0
        jQuery('.smush-attachments .wp-smush-stats .smushed-count, .wp-smush-images-smushed').html(smushed_count);

        //Show the default bulk smush notice
        jQuery('.wp-smush-bulk-wrapper .wp-smush-notice').removeClass('hidden');

        var params = {
            action: 'delete_resmush_list'
        }
        //Delete resmush list
        jQuery.post(ajaxurl, params);

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

})(jQuery);
