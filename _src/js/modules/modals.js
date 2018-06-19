( function( $ ) {
	'use strict';

	/**
	 * Quick Setup - Form Submit
	 */
	$( '#smush-quick-setup-submit' ).on( 'click', function () {
		const self          = $( this ),
			  submit_button = self.find( 'button[type="submit"]' );

		$.ajax( {
			type: 'POST',
			url: ajaxurl,
			data: $( '#smush-quick-setup-form' ).serialize(),
			beforeSend: function () {
				// Disable the button.
				submit_button.attr( 'disabled', 'disabled' );

				// Show loader.
				$( '<span class="sui-icon-loader sui-loading"></span>' ).insertAfter( submit_button );
			},
			success: function ( data ) {
				// Enable the button.
				submit_button.removeAttr( 'disabled' );
				// Remove the loader.
				submit_button.parent().find( 'span.spinner' ).remove();

				if ( data.success == 1 ) {
					// Remove skip button.
					$( '.smush-skip-setup' ).hide();
				}
				// Reload the Page.
				//location.reload();
			}
		} );

		return false;
	} );

	/**
	 * Quick Setup - Skip button
	 */
	$( 'body' ).on( 'submit', '.smush-skip-setup', function () {
		const self = $( this );

		$.ajax( {
			type: 'POST',
			url: ajaxurl,
			data: self.serialize(),
			beforeSend: function () {
				self.find( '.button' ).attr( 'disabled', 'disabled' );
			},
			success: function ( data ) {
				location.reload();
			}
		} );

		return false;
	} );

}( jQuery ));