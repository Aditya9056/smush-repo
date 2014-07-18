jQuery('document').ready(function(){
    
    if( wp_smpro_start_id != null && typeof wp_smpro_start_id != 'undefined') {
        $start_id = wp_smpro_start_id;
    }
    // form the url
    $send_url = ajaxurl + '?action=wp_smpro_queue';
    
    $check_url = ajaxurl+ '?action=wp_smpro_check';
        
    $remaining = wp_smpro_total - wp_smpro_progress;
        
    single_check_width = (1/$remaining)*100;
    
    $check_queue = [];
    
    $queue_done = 0;
    var smpro_poll_check;
    
    function smpro_progress(){
        wp_smpro_progress++;
        $progress = (wp_smpro_progress/wp_smpro_total)*100;
        if($progress===100){
            jQuery('input#wp-sm-pro-begin').prop('disabled',true);
            smpro_poll_check = setInterval(function() {
                qHandler();
            }, 1000);
        }
        jQuery('#wp-smpro-sent div').css('width',$progress+'%');
        
    }
    
    function smpro_check_progress(){
        $queue_done++;
        $new_width = ($queue_done/$remaining)*100;
        if($new_width===100){
            smpro_show_status('Bulk smushing completed, back to media library!');
            clearInterval(smpro_poll_check);
        }
        jQuery('#wp-smpro-received div').css('width',$progress+'%');
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
            data: {attachment_id: $id},
            url: $send_url
        }).done(function(response){
                smpro_show_status("Sent for smushing [" + $id + "]");
                smpro_progress();
                if(response!==''){
                    $start_id = parseInt(response);
                    $check_queue.push($start_id);
                    return $start_id;
                }
            }).fail(function(response){
                smpro_show_status("Smush request failed [" + $id+"]");
                smpro_progress();
            });
    }
    
    function smproCheck($id) {
        return jQuery.ajax({
            type: "GET",
            data: {attachment_id: $id},
            url: $check_url
        }).done(function(response) {
            $rem = false;
            switch(parseInt(response)){
                case 2:
                    $status_msg = "Received ["  + $id + "]";
                    $rem = true;
                    break;
                case 1:
                    $status_msg = "Still awaiting ["  + $id + "]";
                    break;
                case -1:
                    $status_msg = "Smush failed ["  + $id + "]";
                    $rem = true;
                    break;
                default:
                    $status_msg = "Unknown error ["  + $id + "]";
                    break;
            }
            smpro_show_status($status_msg);
            if($rem === true){
                smpro_check_progress();
            }
        }).fail(function(response) {
            smpro_show_status("Checking failed [" + $id + "]");
            smpro_check_progress();
        });
    }
    
    function qHandler(){
        if($check_queue.length>0){
            $current_check = $check_queue.splice(0,1);
            smproCheck($current_check[0]);
        }
        return;
    }
    
    jQuery('.bulk_queue_wrap'). on('click', 'input#wp-sm-pro-begin', function(e){
        
        e.preventDefault();
    
        if(wp_smpro_total<1){
            smpro_show_status('Nothing to send');
            return;
        }
        
        
        smpro_show_status('Sending ' + $remaining + ' of total '+wp_smpro_total+' attachments');
        
        var startingpoint=jQuery.Deferred();
        startingpoint.resolve();
        if( wp_smpro_ids.length>0) {
            $check_queue = wp_smpro_ids;
            jQuery.each(wp_smpro_ids,function(ix,$id) {
                startingpoint=startingpoint.then( function() {
                    smpro_show_status("Making request for [" + $id + "]");
                    return smproRequest($id);
                });
            });
        }else{
            for (var i = 0; i < $remaining; i++){
                startingpoint=startingpoint.then( function() {
                    smpro_show_status("Making request for [" + $start_id + "]");
                    return smproRequest($start_id);
                });
            }
        }
        
        
        
    });    

});
