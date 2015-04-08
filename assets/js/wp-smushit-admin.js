/**
 * Processes bulk smushing
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 *
 */
jQuery('document').ready(function () {

    // url for smushing
    $send_url = ajaxurl + '?action=wp_smushit_bulk';
    $remaining = '';
    $smush_done = 1;

    /**
     * Checks for the specified param in URL
     * @param sParam
     * @returns {*}
     */
    function geturlparam ( arg ) {
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
    function smushit_progress() {

        $total = $smush_done + $remaining;

        // calculate %
        if ($remaining != 0) {

            // increase progress count
            $smush_done++;

            $progress = ( $smush_done / $total) * 100;
        }else{
            jQuery('#smush-status').html(wp_smushit_msgs['done']);
        }
        jQuery('#smushing-total').html($total);

        // increase the progress bar
        wp_smushit_change_progress_status($smush_done, $progress);

    }

    /**
     * Send ajax request for smushing
     *
     * @param {type} $id
     * @param {type} $getnxt
     * @returns {unresolved}
     */
    function smushitRequest($id, $getnxt) {
        // make request
        return jQuery.ajax({
            type: "GET",
            data: {attachment_id: $id, get_next: $getnxt},
            url: $send_url,
            timeout: 60000,
            dataType: 'json'
        }).done(function (response) {

            // increase progressbar
            smushit_progress();
        });
    }

    /**
     * Change the button status on bulk smush completion
     *
     * @returns {undefined}
     */
    function wp_smushit_all_done() {
        $button = jQuery('.wp-smushit-bulk-wrap #wp-smushit-begin');

        // copy the loader into an object
        $loader = $button.find('.floatingCirclesG');

        // remove the loader
        $loader.remove();

        // empty the current text
        $button.find('span').html('');

        // add new class for css adjustment
        $button.removeClass('wp-smushit-started');
        $button.addClass('wp-smushit-finished');

        // add the progress text
        $button.find('span').html(wp_smushit_msgs.done);

        return;
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
        $progress_bar = jQuery('#wp-smushit-progress-wrap #wp-smushit-smush-progress div');
        if ($progress_bar.length < 1) {
            return;
        }
        jQuery('#smushed-count').html($count);
        // increase progress
        $progress_bar.css('width', $width + '%');

    }

    /**
     * Send for bulk smushing
     *
     * @returns {undefined}
     */
    function wp_smushit_bulk_smush() {
        // instantiate our deferred object for piping
        var startingpoint = jQuery.Deferred();
        startingpoint.resolve();

        //Show progress bar
        jQuery('#wp-smushit-progress-wrap #wp-smushit-smush-progress div').css('width', 0);
        jQuery('#progress-ui').show();

        // if we have a definite number of ids
        if (wp_smushit_ids.length > 0) {

            $remaining = wp_smushit_ids.length;

            // loop and pipe into deferred object
            jQuery.each(wp_smushit_ids, function (ix, $id) {
                startingpoint = startingpoint.then(function () {
                    $remaining = $remaining - 1;

                    // call the ajax requestor
                    return smushitRequest($id, 0);
                });
            });
        }

    }
    //If ids are set in url, click over bulk smush button
    $ids = geturlparam('ids');
    if ($ids && $ids != '') {
        wp_smushit_bulk_smush();

        return;
    }

    /**
     * Handle the start button click
     */
    jQuery('button[name="smush-all"]').on('click', function (e) {
        // prevent the default action
        e.preventDefault();

        jQuery(this).attr('disabled', 'disabled');

        wp_smushit_bulk_smush();

        return;

    });
});