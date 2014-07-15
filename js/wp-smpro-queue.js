jQuery('document').ready(function(){
    
    jQuery('.bulk_queue_wrap'). on('click', 'input#smush', function(e){
        e.preventDefault();
        ids = jQuery('ul.bulk_queue li input.id-input');
    
        if(count(img_list)<1){
            return;
        }
        
        ids.each(function(i,e){
            // get attachment id
            $id = jQuery(this).val();
            
            // create request url
            $url = wp_ajax_url + '?action=wp_smpro_queue&attachment_id='+$id;
            
            var request = jQuery.post(url);
            
            request.done(function(response) {
                if(is_num(response)){   
                    move_queue(response);
                }else{
                    show_status(response);
                }
            });
            
            request.fail(function() {
               shows_status( "Request Failed for id "+$id );
            });
        });
    });
    
    function show_status($msg){
        if($msg=''){
            return;
        }
        $status_div = jQuery('.bulk_queue_wrap').find('.status-div').first();
        $single_status = jQuery('<span/>');
        $single_status.addClass('single-status');
        $single_status.html($msg);
        $status_div.append($single_status);
    }
    
    function move_queue($id){
        show_status('Sent for smushing, id '+ $id);
        $completed = jQuery('ul.bulk_queue li input#id-'+ $id);
        $completed.remove();
        
    }
});