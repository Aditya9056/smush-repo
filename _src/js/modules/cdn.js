/**
 * CDN functionality.
 *
 * @since 3.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Toggle CDN.
	 *
	 * @since 3.0
	 *
	 * @param $enable
	 */
	function toggle_cdn( $enable ) {
		$.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				action: 'smush_toggle_cdn',
				param: $enable,
				_ajax_nonce: $('input[name="wp_smush_options_nonce"]').val()
			}
		}).success(() => location.reload()
		).error((resp) => {
			const response = JSON.parse( resp.responseText );
			if ( 'undefined' !== typeof response.data.msg ) {
				// TODO: show a notice
				console.log( response.data.msg );
			}
		});
	}

	/**
	 * Handle "Get Started" button click on disabled CDN page.
	 */
	$('#smush-enable-cdn').on('click', (e) => {
		e.preventDefault();
		toggle_cdn( true );
	});

	/**
	 * Handle "Cancel Activation' button click on CDN page.
	 */
	$('#smush-cancel-cdn').on('click', (e) => {
		e.preventDefault();
		toggle_cdn( false );
	});

}( jQuery ));