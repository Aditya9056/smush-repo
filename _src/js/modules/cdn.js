/**
 * CDN functionality.
 *
 * @since 3.0
 */

( function( $ ) {
	'use strict';

	WP_Smush.CDN = {
		init: function () {
			const self = this;

			/**
			 * Handle "Get Started" button click on disabled CDN page.
			 */
			$('#smush-enable-cdn').on('click', () => {
				$('#smush-enable-cdn').addClass('sui-button-onload').hide().show(0); // Force repaint

				self.toggle_cdn( true );
			});

			/**
			 * Handle "Cancel Activation' button click on CDN page.
			 */
			$('#smush-cancel-cdn').on('click', (e) => {
				e.preventDefault();
				self.toggle_cdn( false );
			});
		},

		/**
		 * Toggle CDN.
		 *
		 * @since 3.0
		 *
		 * @param $enable
		 */
		toggle_cdn: function ( $enable ) {
			$.ajax({
				method: 'POST',
				url: ajaxurl,
				data: {
					action: 'smush_toggle_cdn',
					param: $enable,
					_ajax_nonce: $('input[name="wp_smush_options_nonce"]').val()
				}
			})
			.success((resp) => {
				// Success.
				if ( 'undefined' !== typeof resp.success && resp.success ) {
					location.reload();
				} else {
					this.showNotice( resp.data.message );
				}
			})
			.error((resp) => {
				const response = JSON.parse( resp.responseText );
				this.showNotice( response.data.message );
			});
		},

		/**
		 * Show message (notice).
		 *
		 * @since 3.0
		 *
		 * @param {string} message
		 */
		showNotice: function ( message ) {
			if ( 'undefined' === typeof message ) {
				return;
			}

			const notice = $('#wp-smush-ajax-notice');

			notice.addClass('sui-notice-error').html('<p>' + message + '</p>');

			$('#smush-enable-cdn').removeClass('sui-button-onload');

			notice.slideDown();
			setTimeout( function() {
				notice.slideUp();
			}, 5000 );
		}
	};

	WP_Smush.CDN.init();

}( jQuery ));