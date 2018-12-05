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
        membership: 'free', // Assume free by default.
        modal: document.getElementById('smush-onboarding-dialog'),
        settings: {
            first: true,
            last: false,
            slide: 'start',
            value: false
        },
        selection: {
            'auto': true,
            'lossy': true,
            'strip_exif': true,
            'original': false,
            'usage': true
        },
        contentContainer: document.getElementById('smush-onboarding-content'),
        onboardingSlides: [ 'start', 'auto', 'lossy', 'strip_exif', 'original', 'usage' ],
        touchX: null,
        touchY: null,

        /**
         * Init module.
         */
        init: function() {
            if ( ! this.modal ) {
                return;
            }

            this.membership = document.getElementById('smush-onboarding').dataset.type;

            if ( 'pro' !== this.membership ) {
                this.onboardingSlides = [ 'start', 'auto', 'strip_exif', 'usage' ];
                this.selection.lossy = false;
            }

            this.renderTemplate();

            // Skip setup.
            const skipButton = this.modal.querySelector('.smush-onboarding-skip-link');
            if ( skipButton ) {
                skipButton.addEventListener('click', this.skipSetup);
            }

            // Show the modal.
            SUI.dialogs['smush-onboarding-dialog'].show();
        },

        /**
         * Get swipe coordinates.
         *
         * @param e
         */
        handleTouchStart: function(e) {
            const firstTouch = e.touches[0];
            this.touchX = firstTouch.clientX;
            this.touchY = firstTouch.clientY;
        },

        /**
         * Process swipe left/right.
         *
         * @param e
         */
        handleTouchMove: function(e) {
            if ( ! this.touchX || ! this.touchY ) {
                return;
            }

            const xUp = e.touches[0].clientX,
                  yUp = e.touches[0].clientY,
                  xDiff = this.touchX - xUp,
                  yDiff = this.touchY - yUp;

            if ( Math.abs(xDiff) > Math.abs(yDiff) ) {
                if ( xDiff > 0 ) {
                    if ( false === WP_Smush.onboarding.settings.last ) {
                        WP_Smush.onboarding.next(null, 'next');
                    }
                } else {
                    if ( false === WP_Smush.onboarding.settings.first ) {
                        WP_Smush.onboarding.next(null, 'prev');
                    }
                }
            }

            this.touchX = null;
            this.touchY = null;
        },

        /**
         * Update the template, register new listeners.
         */
        renderTemplate: function() {
            // Grab the selected value.
            const input = this.modal.querySelector('input[type="checkbox"]');
            if ( input ) {
                this.selection[input.id] = input.checked;
            }

            const template = WP_Smush.onboarding.template('smush-onboarding');
            const content = template(this.settings);

            if ( content ) {
                // Hide to apply animation later on.
                this.contentContainer.style.display = 'none';
                this.contentContainer.innerHTML = content;
                // Apply animation
                jQuery('#smush-onboarding-content').fadeIn();
            }

            this.modal.addEventListener('touchstart', this.handleTouchStart, false);
            this.modal.addEventListener('touchmove', this.handleTouchMove, false);

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
         * @param whereTo
         */
        next: function(e, whereTo = null) {
            const index = this.onboardingSlides.indexOf(this.settings.slide);
            let newIndex = 0;

            if ( ! whereTo ) {
                newIndex = e.classList.contains('next') ? index + 1 : index - 1;
            } else {
                newIndex = 'next' === whereTo ? index + 1 : index - 1;
            }

            this.settings = {
                first: 0 === newIndex,
                last: newIndex + 1 === this.onboardingSlides.length, // length !== index
                slide: this.onboardingSlides[newIndex],
                value: this.selection[this.onboardingSlides[newIndex]]
            };

            this.renderTemplate();
        },

        /**
         * Handle circle navigation.
         *
         * @param target
         */
        goTo: function(target) {
            const newIndex = this.onboardingSlides.indexOf(target);

            this.settings = {
                first: 0 === newIndex,
                last: newIndex + 1 === this.onboardingSlides.length, // length !== index
                slide: target,
                value: this.selection[target]
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
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: 'action=skip_smush_setup&_ajax_nonce='+_nonce.value
            })
                .then(SUI.dialogs['smush-onboarding-dialog'].hide())
                .catch(error => console.error(error));
        }
    };

    /**
     * Template function (underscores based).
     *
     * @type {Function}
     */
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
            return compiled(data);
        };
    });

    window.onload = () => WP_Smush.onboarding.init();

}());