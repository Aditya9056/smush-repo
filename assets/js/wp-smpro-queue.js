/**
 * Processes bulk smushing
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 *
 */
jQuery('document').ready(function() {
        
        
        // if we are on bulk smushing page
        if (pagenow === 'media_page_wp-smpro-admin') {
                
                jQuery('.wp-smpro-bulk-wrap').smushitpro({
                        'msgs'          : wp_smpro_msgs,
                        'counts'        : wp_smpro_counts,
                        'ids'           : wp_smpro_ids,
                        'ajax_url'      : ajax_url,
                        'is_single'     : false
                        
                });
        }else{
                /**
                 * Handle the media library button click
                 */
                jQuery('.wp-list-table.media').smushitpro({
                        'msgs'          : wp_smpro_msgs,
                        'counts'        : wp_smpro_counts,
                        'ajax_url'      : ajax_url,
                        'is_single'     : true
                        
                });
        }

});

