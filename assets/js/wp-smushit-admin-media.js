var WP_Smush = WP_Smush || {};
jQuery(function ($) {
    "use strict";
    if (!wp.media) return;

    var manualUrl = ajaxurl + '?action=wp_smushit_manual';

    var SmushButton = Backbone.View.extend({
        className: "media-lib-wp-smush-el",
        tagName: "div",
        events: {
            "click .media-lib-wp-smush-button": "click"
        },
        template : _.template('<button class="button button-primary media-lib-wp-smush-button"><%= label %></button>'),
        initialize: function () {
            this.render();
        },
        render: function () {
            var data = this.model.toJSON();
            this.$el.html( this.template( { label: wp_smushit_msgs.smush_it} ) );

            this.$button = this.$(".button");

            this.$button.data("id", data.id);
        },
        click: function (e) {
            var ajax = WP_Smush.sendRequest(this.$button),
                self = this;

            e.preventDefault();
            e.stopPropagation();

            this.$el.css({display: "block"});
            this.$button.prop("disabled", true);
            this.$button.text( wp_smushit_msgs.sending );
            ajax.complete(function (res) {
                self.$button.prop("disabled", false);
                self.$button.text( wp_smushit_msgs.smush_it );
            });
        }
    });


    /**
     * Add smush it button to the image thumb
     */
    WP_Smush.Attachments = wp.media.view.Attachments.extend({
        createAttachmentView: function (attachment) {

            var view = wp.media.view.Attachments.__super__.createAttachmentView.apply(this, arguments);

            _.defer(function () {
                var smush_button = new SmushButton({model: view.model});
                view.$el.append(smush_button.el);
                view.$el.addClass("has-smush-button");
            });

            return view;
        }
    });
    wp.media.view.Attachments = WP_Smush.Attachments;
});
