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
		}).success(() => location.reload()
		).error((resp) => {
			const response = JSON.parse( resp.responseText );
			if ( 'undefined' !== typeof response.data.msg ) {
				// TODO: show a notice
				console.log( response.data.msg );
			}
		});
	})

}( jQuery ));