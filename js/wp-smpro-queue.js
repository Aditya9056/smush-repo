jQuery('document').ready(function(){
    
    function DeferredAjax(opts) {
        this.options=opts;
        this.deferred=jQuery.Deferred();
        this.attachment_id=opts.attachment_id;
    }
    
    DeferredAjax.prototype.invoke=function() {
        var self=this, data={attachment_id:self.attachment_id};
        console.log("Making request for [" + self.attachment_id + "]");
        
        $url = wp_ajax_url + '?action=wp_smpro_queue';
        
        return jQuery.ajax({
            type: "GET",
            url: $url,
            data: data,
            success: function(){
                console.log("Successful request for [" + self.attachment_id + "]");
                self.deferred.resolve();
            }
        });
    };
    
    DeferredAjax.prototype.promise=function() {
        return this.deferred.promise();
    };

    
    jQuery('.bulk_queue_wrap'). on('click', 'input#smush', function(e){
        e.preventDefault();
        ids = jQuery('ul.bulk_queue li input.id-input');
    
        if(count(img_list)<1){
            return;
        }
        
        var startingpoint = jQuery.Deferred();
    
        startingpoint.resolve();

        jQuery.each(ids, function(ix, id) {
            var da = new DeferredAjax({
                attachment_id: id
            });
            jQuery.when(startingpoint ).then(function() {
                da.invoke();
            });
            startingpoint= da;
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
});