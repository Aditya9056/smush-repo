/**
 * Modals JavaScript code.
 */

( function ( $ ) {
    'use strict';

    /**
     * Onboarding modal.
     *
     * @since 3.1
     */
    WP_Smush.onboarding = {
        onboardingModals: [ 'smush-onboarding-dialog', 'smush-onboarding-dialog-auto', 'smush-onboarding-dialog-lossy' ],

        init: function() {
            const modal = document.getElementById('smush-onboarding-dialog');

            // If quick setup box is not found, return.
            if ( ! modal ) {
                return;
            }

            // Show the modal.
            SUI.dialogs['smush-onboarding-dialog'].show();

            // Skip setup.
            const skipButton = modal.querySelector('.smush-onboarding-skip-link');
            if ( skipButton ) {
                skipButton.addEventListener('click', this.skipSetup);
            }
        },

        skipSetup: () => {
            const nonceField = document.getElementById('_wpnonce');

            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8'
                },
                body: 'action=skip_smush_setup&_ajax_nonce='+nonceField.value
            }).catch(error => console.error(error));
        },

        nav: function(e) {
            const slideName = e.dataset.slide;

            if ( 'undefined' === typeof slideName ) {
                return;
            }

            const index = this.onboardingModals.indexOf(slideName);
            const oldIndex = 'next' === e.className ? index - 1 : index + 1;

            if ( index < this.onboardingModals.length ) {
                SUI.dialogs[this.onboardingModals[oldIndex]].hide();
                SUI.dialogs[this.onboardingModals[index]].show();
            }
        }
    };

    window.onload = () => WP_Smush.onboarding.init();

    /**
     * Remove dismissable notices.
     */
    $( '.sui-wrap' ).on( 'click', '.sui-notice-dismiss', function ( e ) {
        e.preventDefault();
        $( this ).parent().stop().slideUp( 'slow' );
    } );

    /**
     * Quick Setup - Form Submit
     */
    $( '#smush-quick-setup-submit' ).on( 'click', function () {
        const self = $( this );

        $.ajax( {
            type: 'POST',
            url: ajaxurl,
            data: $( '#smush-quick-setup-form' ).serialize(),
            beforeSend: function () {
                // Disable the button.
                self.attr( 'disabled', 'disabled' );

                // Show loader.
                $( '<span class="sui-icon-loader sui-loading"></span>' ).insertAfter( self );
            },
            success: function ( data ) {
                // Enable the button.
                self.removeAttr( 'disabled' );
                // Remove the loader.
                self.parent().find( 'span.spinner' ).remove();

                // Reload the Page.
                location.reload();
            }
        } );
    } );

}( jQuery ));