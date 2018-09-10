jQuery( function ( $ ) {
	'use strict';

	/**
	 * After page load, initialize toggle event.
	 *
	 * On detection link click, show all wrongly scaled images with
	 * a highlighted border and resize box.
	 * Upon clicking again, remove highlights.
	 */
	$( window ).load( () => detect_wrong_imgs() );

	/**
	 * Function to highlight all scaled images.
	 *
	 * Add yellow border and then show one small box to
	 * resize the images as per the required size, on fly.
	 */
	let detect_wrong_imgs = () => {
		$( 'body img:not(.avatar)' ).each( function() {
		//$( 'body img[data-smush-image]' ).each( function () {
			const ele = $( this );

			// If width attribute is not set, do not continue.
			// @todo We need to check if we can detect images in other way.
			if ( ele.css( 'width' ) === null || ele.css( 'height' ) === null ) {
				return true;
			}

			// Get defined width and height.
			const css_width      = ele.css( 'width' ).replace( 'px', '' ),
				  css_height     = ele.css( 'height' ).replace( 'px', '' ),
				  img_width      = ele.prop( 'naturalWidth' ),
				  img_height     = ele.prop( 'naturalHeight' ),
				  higher_width   = ( css_width * 1.5 ) < img_width,
				  higher_height  = ( css_height * 1.5 ) < img_height,
				  smaller_width  = css_width > img_width,
				  smaller_height = css_height > img_height;

			let	tooltip_text = '';

			// In case image is in correct size, do not continue.
			if ( ! higher_width && ! higher_height && ! smaller_width && ! smaller_height ) {
				return true;
			}

			if ( higher_width || higher_height ) {
				tooltip_text = wp_smush_resize_vars.large_image;
			} else if ( smaller_width || smaller_height ) {
				tooltip_text = wp_smush_resize_vars.small_image;
			}

			tooltip_text = tooltip_text.replace( 'width', css_width );
			tooltip_text = tooltip_text.replace( 'height', css_height );

			// Create HTML content to append.
			let content = '<div class="smush-resize-box smush-tooltip smush-tooltip-constrained" data-tooltip="' + tooltip_text + '">' +
				'<span class="smush-tag">' + img_width + ' × ' + img_height + ' px</span>' +
				'<i class="smush-front-icons smush-front-icon-arrows-in" aria-hidden="true"></i>' +
				'<span class="smush-tag smush-tag-success">' + css_width + ' × ' + css_height + ' px</span>' +
				'</div>';

			// Append resize box to image.
			ele.before( content );

			// Add a class to image.
			ele.addClass( 'smush-detected-img' );
		} );
	};

} );
