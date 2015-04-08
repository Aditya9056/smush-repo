var WP_Smush = WP_Smush || {};
jQuery(function($){
    "use strict";
    if( !wp.media ) return;

    var manualUrl = ajaxurl + '?action=wp_smushit_manual';

    var SmushButton =  Backbone.View.extend({
        className: "media-lib-wp-smush-el",
        tagName: "div",
        events: {
            "click .media-lib-wp-smush-button" : "click"
        },
        initialize: function(options){
            this.render();
        },
        render: function(){
            var data = this.model.toJSON(),
                $button = $("<button class='button button-primary media-lib-wp-smush-button'>Smush it</button>"),
                $loader = $('<span class="wp-smush-loader"></span>').hide();

            this.$button = $button;
            this.$loader = $loader;

            this.$el.html($button);
            this.$el.append($loader);
            $button.data("id", data.id);
        },
        click: function(e){
            var ajax = WP_Smush.sendRequest( this.$button),
                self = this;

            e.preventDefault();
            e.stopPropagation();

            this.$el.css({ display: "block"});
            this.$button.prop("disabled", true);

            this.$loader.show();

            ajax.complete(function(res){
                self.$loader.hide();
                self.$button.prop("disabled", false);
            });
        }
    });


    /**
     * Add smush it button to the image thumb
     */
    WP_Smush.Attachments = wp.media.view.Attachments.extend({
        createAttachmentView: function(attachment){

            var view = wp.media.view.Attachments.__super__.createAttachmentView.apply(this, arguments);

            _.defer(function(){
                var smush_button = new SmushButton({model: view.model});
                view.$el.append(smush_button.el);
                view.$el.addClass("has-smush-button");
            });

            return view;
        }
    });
    wp.media.view.Attachments = WP_Smush.Attachments;
});
