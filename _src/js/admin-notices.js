jQuery( function ( $ ) {

	// Remove dismissable notices
	$( '.sui-wrap' ).on( 'click', '.sui-notice-dismiss', function ( e ) {
		e.preventDefault();

		$( this ).parent().stop().slideUp( 'slow' );
	} );

} );