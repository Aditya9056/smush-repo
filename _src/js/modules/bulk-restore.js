/**
 * Bulk restore JavaScript code.
 * @since 3.2.2
 */

( function () {
    'use strict';

    /**
     * Bulk restore modal.
     *
     * @since 3.2.2
     */
    WP_Smush.restore = {
        modal: document.getElementById('smush-restore-images-dialog'),
        contentContainer: document.getElementById('smush-bulk-restore-content'),
        settings: {
            slide: 'start' // start, progress or finish.
        },
        items: [],   // total items, 1 item = 1 step.
        success: [], // successful items restored.
        errors: [],  // failed items.
        currentStep: 0,
        totalSteps: 0,

        /**
         * Init module.
         */
        init: function() {
            if ( ! this.modal ) {
                return;
            }

            this.renderTemplate();

            // Show the modal.
            SUI.dialogs['smush-restore-images-dialog'].show();
        },

        /**
         * Update the template, register new listeners.
         */
        renderTemplate: function() {
            const template = WP_Smush.onboarding.template('smush-bulk-restore');
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
            const confirmButton = this.modal.querySelector('button[id="smush-bulk-restore-button"]');
            const self = this;

            if ( confirmButton ) {
                confirmButton.addEventListener('click', function(e) {
                    e.preventDefault();

                    self.settings = { slide: 'progress' };

                    self.renderTemplate();
                    self.initScan();
                    self.updateProgressBar();
                });
            }
        },

        /**
         * Cancel the bulk restore.
         */
        cancel: function() {
            if ( 'start' === this.settings.slide ) {
                // Hide the modal.
                SUI.dialogs['smush-restore-images-dialog'].hide();
            } else {
                // TODO: Cancel bulk restore.
                this.updateProgressBar( true );
                window.location.reload();
            }
        },

        /**
         * Update progress bar during directory smush.
         *
         * @param {boolean} cancel    Cancel status.
         */
        updateProgressBar: function ( cancel = false ) {
            let progress = 0;
            if ( 0 < this.currentStep ) {
                progress = Math.min( Math.round( this.currentStep * 100 / this.totalSteps ), 99 );
            }

            if ( progress > 100 ) {
                progress = 100;
            }

            // Update progress bar
            this.modal.querySelector('.sui-progress-text span').innerHTML = progress + '%';
            this.modal.querySelector('.sui-progress-bar span').style.width = progress + '%';

            const statusDiv = this.modal.querySelector('.sui-progress-state-text');
            if ( progress >= 90 ) {
                statusDiv.innerHTML = 'Finalizing...'
            } else if ( cancel ) {
                statusDiv.innerHTML = 'Cancelling...'
            } else {
                statusDiv.innerHTML = this.currentStep + '/' + this.totalSteps + ' ' + 'images restored';
            }
        },

        /**
         * First step in bulk restore - get the bulk attachment count.
         */
        initScan: function() {
            const self = this;
            const _nonce = document.getElementById('_wpnonce');

            const xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl+'?action=get_image_count', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.onload = () => {
                if (200 === xhr.status) {
                    const res = JSON.parse(xhr.response);
                    if ( 'undefined' !== typeof res.data.items ) {
                        self.items = res.data.items;
                        self.totalSteps = res.data.items.length;
                        self.step();
                    }
                } else {
                    console.log('Request failed.  Returned status of ' + xhr.status);
                }
            };
            xhr.send('_ajax_nonce='+_nonce.value);
        },

        /**
         * Execute a scan step recursively
         */
        step: function() {
            console.log(this.items);
            const self = this;
            const _nonce = document.getElementById('_wpnonce');

            if ( 0 < this.items.length ) {
                const item = this.items.pop();
                const xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxurl+'?action=restore_step', true);
                xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                xhr.onload = () => {
                    this.currentStep++;

                    if (200 === xhr.status) {
                        self.success.push(item);
                    } else {
                        self.errors.push(item);
                    }

                    self.updateProgressBar();
                    self.step();
                };
                xhr.send('item='+item+'&_ajax_nonce='+_nonce.value);
            } else {
                // Finish.
                console.log(this.success);
                console.log(this.errors);
            }
        }
    };

    /**
     * Template function (underscores based).
     *
     * @type {Function}
     */
    WP_Smush.restore.template = _.memoize(id => {
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

}());
