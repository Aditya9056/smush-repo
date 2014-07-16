jQuery('document').ready(function(){
    
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
    
    // custom deferred object
    function DeferredAjax(opts) {
        this.options=opts;
        this.deferred=jQuery.Deferred();
        this.attachment_id=opts.attachment_id;
    }
    
    // invokation
    DeferredAjax.prototype.invoke=function() {
        // assign data
        var self=this, data={attachment_id:self.attachment_id};
        
        // visible output
        smpro_show_status("Making request for [" + self.attachment_id + "]");
        
        // form the url
        $url = wp_ajax_url + '?action=wp_smpro_queue';
        
        // ajax call
        return jQuery.ajax({
            type: "GET",
            url: $url,
            data: data
        }).done(function(response){
                smpro_show_status("Sent for smushing [" + self.attachment_id + "]");
                smpro_progress();
                self.deferred.resolve();
            }).fail(function(response){
                smpro_show_status("Smush request failed [" + self.attachment_id+"]");
                smpro_progress();
                self.deferred.resolve();
            });
    };
    
    DeferredAjax.prototype.promise=function() {
        return this.deferred.promise();
    };

    
    jQuery('.bulk_queue_wrap'). on('click', 'input#wp-sm-pro-begin', function(e){
        
        e.preventDefault();
    
        if(wp_smpro_total<1){
            smpro_show_status('Nothing to send');
            return;
        }
        $left = (wp_smpro_total-wp_smpro_progress);
        smpro_show_status('Sending ' + left + ' of total '+wp_smpro_total+' attachments');
        var startingpoint = jQuery.Deferred();
    
        startingpoint.resolve();
        if(!empty(wp_smpro_ids) ){
            jQuery.each(wp_smpro_ids, function(ix, id) {
                var da = new DeferredAjax({
                    attachment_id: id
                });
                jQuery.when(startingpoint ).then(function() {
                    da.invoke();
                });
                startingpoint= da;
            });
        } else {
            for (var i = 0; i < $init_left; i++){
                
                var da = new DeferredAjax({
                    attachment_id: id
                });
                jQuery.when(startingpoint ).then(function() {
                    da.invoke();
                });
                startingpoint= da;
            
            }
        }
        
    });
    //code for removing elems from array
    
//    var removeItem = 2;   // item do array que deverÃ¡ ser removido
// 
//    arr = jQuery.grep(arr, function(value) {
//        return value !== removeItem;
//    });
    
    
});