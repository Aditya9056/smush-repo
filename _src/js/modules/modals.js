/**
 * Modals JavaScript code.
 */

( function () {
    'use strict';

    /**
     * Onboarding modal.
     *
     * @since 3.1
     */
    WP_Smush.onboarding = {
        modal: document.getElementById('smush-onboarding-dialog'),
        settings: {
            first: true,
            last: false,
            slide: 'start',
            value: false
        },
        selection: {
            'auto': true,
            'lossy': false,
            'strip_exif': true,
            'original': false,
            'usage': true
        },
        contentContainer: document.getElementById('smush-onboarding-content'),
        onboardingSlides: [ 'start', 'auto', 'lossy', 'strip_exif', 'original', 'usage' ],

        init: function() {
            if ( ! this.modal ) {
                return;
            }

            this.renderTemplate();

            // Show the modal.
            SUI.dialogs['smush-onboarding-dialog'].show();

            // Skip setup.
            const skipButton = this.modal.querySelector('.smush-onboarding-skip-link');
            if ( skipButton ) {
                skipButton.addEventListener('click', this.skipSetup);
            }
        },

        renderTemplate: function() {
            // Grab the selected value.
            const input = this.modal.querySelector('input[type="checkbox"]');
            if ( input ) {
                this.selection[input.id] = input.checked;
            }

            const template = WP_Smush.onboarding.template('smush-onboarding');
            const content = template(this.settings);

            if ( content ) {
                this.contentContainer.innerHTML = content;
            }

            this.bindSubmit();
        },

        /**
         * Catch "Finish setup wizard" button click.
         */
        bindSubmit: function() {
            const submitButton = this.modal.querySelector('button[type="submit"]');
            const self = this;

            if ( submitButton ) {
                submitButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    const _nonce = document.getElementById('_wpnonce');

                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8'
                        },
                        body: 'action=smush_setup&smush_settings='+JSON.stringify(self.selection)+'&_ajax_nonce='+_nonce.value
                    })
                    .then(() => {
                        SUI.dialogs['smush-onboarding-dialog'].hide();
                        location.reload();
                    })
                    .catch(error => console.error(error));
                });
            }
        },

        /**
         * Handle navigation.
         *
         * @param e
         */
        next: function(e) {
            const index = this.onboardingSlides.indexOf(this.settings.slide),
                  newIndex = e.classList.contains('next') ? index + 1 : index - 1;

            this.settings = {
                first: 0 === newIndex,
                last: newIndex + 1 === this.onboardingSlides.length, // length !== index
                slide: this.onboardingSlides[newIndex],
                value: this.selection[this.onboardingSlides[newIndex]]
            };

            this.renderTemplate();
        },

        /**
         * Skip onboarding experience.
         */
        skipSetup: () => {
            const _nonce = document.getElementById('_wpnonce');

            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8'
                },
                body: 'action=skip_smush_setup&_ajax_nonce='+_nonce.value
            })
            .then(SUI.dialogs['smush-onboarding-dialog'].hide())
            .catch(error => console.error(error));
        }
    };

    WP_Smush.onboarding.template = _.memoize(id => {
        let compiled,
            options = {
                evaluate:    /<#([\s\S]+?)#>/g,
                interpolate: /\{\{\{([\s\S]+?)\}\}\}/g,
                escape:      /\{\{([^\}]+?)\}\}(?!\})/g,
                variable:    'data'
            };

        return data => {
            _.templateSettings = options;
            compiled = compiled || _.template(document.getElementById(id).innerHTML);
            //compiled = _.template(document.getElementById(id).innerHTML);
            return compiled(data);
        };
    });

    window.onload = () => WP_Smush.onboarding.init();

}());