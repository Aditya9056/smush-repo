jQuery( function ( $ ) {

	/**
	 * After page load, initialize toggle event.
	 *
	 * On detection link click, show all wrongly scaled images with
	 * a highlighted border and resize box.
	 * Upon clicking again, remove highlights.
	 */
	$( window ).load( function () {

		// Handle detect link click.
		$( '#wp-admin-bar-smush-resize-detection' ).toggle( function () {
			detect_wrong_imgs();
		}, function () {
			revert_detection();
		} );

		$( 'body' ).on( 'click', '.smush-resize-submit', resize_image );

	} );

	/**
	 * Function to highlight all scaled images.
	 *
	 * Add yellow border and then show one small box to
	 * resize the images as per the required size, on fly.
	 */
	var detect_wrong_imgs = function () {

		$( 'body img[data-attachment-id]' ).each( function () {

			var ele = $( this );

			// If width attribute is not set, do not continue.
			if ( ele.css( 'width' ) === null || ele.css( 'height' ) === null ) {
				return true;
			}

			// Get defined width and height.
			var css_width = ele.css( 'width' ).replace( 'px', '' ),
				css_height = ele.css( 'height' ).replace( 'px', '' ),
				img_width = ele.prop( 'naturalWidth' ),
				img_height = ele.prop( 'naturalHeight' ),
				higher_width = css_width < img_width,
				higher_height = css_height < img_height,
				smaller_width = css_width > img_width,
				smaller_height = css_height > img_height,
				attachment_id = ele.data( 'attachment-id' );

			// Incase image is in correct size, do not continue.
			if ( !higher_width && !higher_height && !smaller_width && !smaller_height ) {
				return true;
			}

			// Continue only if image has a valid attachment id.
			if ( attachment_id === '' || attachment_id <= 0 ) {
				return true;
			}

			// Create HTML content to append.
			var before_content = '<div class="smush-resize-box">' +
				'<span class="smush-tag">' + img_width + 'px × ' + img_height + 'px</span>' +
				'<i class="smush-front-icons smush-front-icon-arrows-in" aria-hidden="true"></i>' +
				'<span class="smush-tag smush-tag-success">' + css_width + 'px × ' + css_height + 'px</span>' +
				'<i class="smush-front-icons smush-front-icon-arrow-right smush-resize-submit" data-attachment-id="' + attachment_id + '" aria-hidden="true"></i>' +
				'</div>';

			var after_content = '';

			// Append resize box to image.
			ele.before( before_content );
			ele.after( after_content );

			// Add a class to image.
			ele.addClass( 'smush-detected-img' );
		} );
	};

	/**
	 * Function to remove highlights from images.
	 *
	 * Remove already added borders and highlights from
	 * images. Also remove the resize box.
	 */
	var revert_detection = function () {

		// Remove all detection boxes.
		$( '.smush-resize-box' ).remove();

		// Remove custom class from images.
		$( '.smush-detected-img' ).removeClass( 'smush-detected-img' );
	};

	/**
	 * Function to resize image via CDN.
	 *
	 * Send ajax request and set new resized version
	 * based on the required container size.
	 */
	var resize_image = function () {

		var ele = $( this );

		ele.addClass( 'smush-front-icon-loader' );

		// Send a ajax request to set new resized version.
		var params = {
			action: 'smush_auto_resize',
			attachment_id: ele.data( 'attachment-id' ),
			resize_nonce: wp_smush_resize_vars.ajax_nonce,
		};

		// Set the new resized version of image.
		$.get( wp_smush_resize_vars.ajaxurl, params, function ( res ) {

			ele.removeClass( 'smush-front-icon-loader' );

		} ).done( function ( res ) {

			ele.removeClass( 'smush-front-icon-loader' );
		} );
	};
} );
