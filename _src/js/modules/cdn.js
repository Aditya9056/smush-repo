/**
 * CDN functionality.
 *
 * @since 3.0
 */

( function( $ ) {
	'use strict';

	$('#smush-enable-cdn').on('click', (e) => {
		e.preventDefault();

		$.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				action: 'smush_enable_cdn',
				_ajax_nonce: $('input[name="smush-enable-cdn-nonce"]').val()
			}
		}).success(() => {
			console.log( 'success' );
		}).error(() => {
			console.log( 'error' );
		});

		//location.reload();
	})

}( jQuery ));