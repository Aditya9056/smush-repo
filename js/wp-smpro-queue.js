jQuery('document').ready(function(){
    if( wp_smpro_start_id != null && typeof wp_smpro_start_id != 'undefined') {
        $start_id = wp_smpro_start_id;
    }
    // form the url
    $url = ajaxurl + '?action=wp_smpro_queue';
        
    $init_left = wp_smpro_total - wp_smpro_progress;
    
    function smpro_progress(){
        wp_smpro_progress++;
        $progress = (wp_smpro_progress/wp_smpro_total)*100;
        jQuery('#wp-smpro-progressbar div').css('width',$progress+'%');
        
    }
    
    function smpro_show_status($msg){
        if($msg===''){
            return;
        }
        $status_div = jQuery('.bulk_queue_wrap').find('.status-div').first();
        $single_status = jQuery('<span/>');
        $single_status.addClass('single-status');
        $single_status.html($msg);
        $status_div.append($single_status);
    }
    
    function smproRequest($id) {
        return jQuery.ajax({
            type: "GET",
            data: {attachment_ID:$id},
            url: $url
        }).done(function(response){
                smpro_show_status("Sent for smushing [" + $id + "]");
                smpro_progress();
                return $start_id = parseInt(response);
            }).fail(function(response){
                smpro_show_status("Smush request failed [" + $id+"]");
                smpro_progress();
            });
    }

    

    

    
    jQuery('.bulk_queue_wrap'). on('click', 'input#wp-sm-pro-begin', function(e){
        
        e.preventDefault();
    
        if(wp_smpro_total<1){
            smpro_show_status('Nothing to send');
            return;
        }
        
        $left = (wp_smpro_total-wp_smpro_progress);
        
        smpro_show_status('Sending ' + $left + ' of total '+wp_smpro_total+' attachments');
        
        var startingpoint=jQuery.Deferred();
        startingpoint.resolve();
        
        if( wp_smpro_ids.length>0) {
            jQuery.each(wp_smpro_ids,function(ix,$id) {
                startingpoint=startingpoint.then( function() {
                    smpro_show_status("Making request for [" + $id + "]");
                    return smproRequest($id);
                });
            });
        }else{
            for (var i = 0; i < $init_left; i++){
                startingpoint=startingpoint.then( function() {
                    smpro_show_status("Making request for [" + $start_id + "]");
                    return smproRequest($start_id);
                });
            }
        }
        
    });    
    
});
