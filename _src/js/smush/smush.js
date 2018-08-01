/**
 * Smush class.
 *
 * @since 2.9.0  Moved from admin.js into a dedicated ES6 class.
 */

class Smush {

	/**
	 * Class constructor.
	 *
	 * @param {object}  button  Button object that made the call.
	 * @param {boolean} bulk    Bulk smush or not.
	 * @param {string}  type    Accepts: 'nextgen', 'media'.
	 */
	constructor( button, bulk, type = 'media' ) {
		this.errors = [];
		//If smush attribute is not defined, Need not skip re-Smush IDs.
		this.skip_resmush = ! ( 'undefined' === typeof button.data( 'smush' ) || ! button.data( 'smush' ) );

		this.button          = jQuery( button[0] );
		this.is_bulk         = typeof bulk ? bulk : false;
		this.url             = ajaxurl;
		this.log             = jQuery( '.smush-final-log' );
		this.deferred        = jQuery.Deferred();
		this.deferred.errors = [];

		const ids = 0 < wp_smushit_data.resmush.length && ! this.skip_resmush ? ( wp_smushit_data.unsmushed.length > 0 ? wp_smushit_data.resmush.concat( wp_smushit_data.unsmushed ) : wp_smushit_data.resmush ) : wp_smushit_data.unsmushed;
		if ( 'object' === typeof ids ) {
			// If button has re-Smush class, and we do have ids that needs to re-Smushed, put them in the list.
			this.ids = ids.filter( function ( itm, i, a ) {
				return i === a.indexOf( itm );
			} );
		} else {
			this.ids = ids;
		}

		this.is_bulk_resmush = 0 < wp_smushit_data.resmush.length && ! this.skip_resmush;

		this.status = this.button.parent().find( '.smush-status' );

		// Added for NextGen support.
		this.smush_type         = type;
		this.single_ajax_suffix = 'nextgen' === this.smush_type ? 'smush_manual_nextgen' : 'wp_smushit_manual';
		this.bulk_ajax_suffix   = 'nextgen' === this.smush_type ? 'wp_smushit_nextgen_bulk' : 'wp_smushit_bulk';
		this.url = this.is_bulk ? Smush.smushAddParams( this.url, { action: this.bulk_ajax_suffix } ) : Smush.smushAddParams( this.url, { action: this.single_ajax_suffix } );

		this.start();
		this.run();
		this.bind_deferred_events();

		// Handle cancel ajax.
		this.cancel_ajax();

		return this.deferred;
	}

	/**
	 * Add params to the URL.
	 *
	 * @param {string} url   URL to add the params to.
	 * @param {object} data  Object with params.
	 * @returns {*}
	 */
	static smushAddParams( url, data ) {
		if ( ! jQuery.isEmptyObject( data ) ) {
			url += ( url.indexOf( '?' ) >= 0 ? '&' : '?' ) + jQuery.param( data );
		}

		return url;
	}

	/**
	 * Check membership validity.
	 *
	 * @param data
	 * @param {int} data.show_warning
	 */
	static membership_validity( data ) {
		const member_validity_notice = jQuery( '#wp-smush-invalid-member' );

		// Check for membership warning.
		if ( 'undefined' !== typeof ( data ) && 'undefined' !== typeof ( data.show_warning ) && member_validity_notice.length > 0 ) {
			if ( data.show_warning ) {
				member_validity_notice.show();
			} else {
				member_validity_notice.hide();
			}
		}
	};

	/**
	 * Send Ajax request for Smushing the image.
	 *
	 * @param {boolean} is_bulk_resmush
	 * @param {int}     id
	 * @param {string}  send_url
	 * @param {int}     getnxt
	 * @param {string}  nonce
	 * @returns {*|jQuery.promise|void}
	 */
	static ajax( is_bulk_resmush, id, send_url, getnxt, nonce ) {
		const param = jQuery.param({
			is_bulk_resmush: is_bulk_resmush,
			attachment_id: id,
			get_next: getnxt,
			_nonce: nonce
		});

		return jQuery.ajax( {
			type: 'GET',
			data: param,
			url: send_url,
			/** @var {array} wp_smushit_data */
			timeout: wp_smushit_data.timeout,
			dataType: 'json'
		} );
	};

	/**
	 * Show loader in button for single and bulk Smush.
	 */
	start() {
		this.button.attr( 'disabled', 'disabled' );
		this.button.addClass( 'wp-smush-started' );

		this.bulk_start();
		this.single_start();
	};

	/**
	 * Start bulk Smush.
	 */
	bulk_start() {
		if ( ! this.is_bulk ) return;

		// Hide the bulk div.
		jQuery( '.wp-smush-bulk-wrapper' ).hide();

		// Show the progress bar.
		jQuery( '.bulk-smush-wrapper .wp-smush-bulk-progress-bar-wrapper' ).show();

		// Remove any global notices if there.
		jQuery( '.sui-notice-top' ).remove();

		// Hide the bulk limit message.
		jQuery( 'p.smush-error-message.limit_exceeded' ).remove();

		// Hide parent wrapper, if there are no other messages.
		if ( 0 >= jQuery( 'div.smush-final-log p' ).length ) {
			jQuery( 'div.smush-final-log' ).hide();
		}
	};

	/**
	 * Start single image Smush.
	 */
	single_start() {
		if ( this.is_bulk ) return;
		this.show_loader();
		this.status.removeClass( 'error' );
	};

	/**
	 * Enable button.
	 */
	enable_button() {
		this.button.prop( 'disabled', false );
		// For bulk process, enable other buttons.
		jQuery( 'button.wp-smush-all' ).removeAttr( 'disabled' );
		jQuery( 'button.wp-smush-scan, a.wp-smush-lossy-enable, button.wp-smush-resize-enable, input#wp-smush-save-settings' ).removeAttr( 'disabled' );
	};

	/**
	 * Show loader.
	 *
	 * @var {string} wp_smush_msgs.smushing
	 */
	show_loader() {
		Smush.progress_bar( this.button, wp_smush_msgs.smushing, 'show' );
	};

	/**
	 * Hide loader.
	 *
	 * @var {string} wp_smush_msgs.smushing
	 */
	hide_loader() {
		Smush.progress_bar( this.button, wp_smush_msgs.smushing, 'hide' );
	};

	/**
	 * Show/hide the progress bar for Smushing/Restore/SuperSmush.
	 *
	 * @param cur_ele
	 * @param txt Message to be displayed
	 * @param {string} state show/hide
	 */
	static progress_bar( cur_ele, txt, state ) {
		// Update progress bar text and show it.
		const progress_button = cur_ele.parents().eq( 1 ).find( '.wp-smush-progress' );

		if ( 'show' === state ) {
			progress_button.html( txt );
		} else {
			/** @var {string} wp_smush_msgs.all_done */
			progress_button.html( wp_smush_msgs.all_done );
		}

		progress_button.toggleClass( 'visible' );
	};

	/**
	 * Finish single image Smush.
	 */
	single_done() {
		if ( this.is_bulk ) return;

		this.hide_loader();

		const self = this;

		this.request.done( function ( response ) {
			if ( 'undefined' !== typeof response.data ) {

				// Check if stats div exists.
				const parent    = self.status.parent(),
					stats_div = parent.find( '.smush-stats-wrapper' );

				// If we've updated status, replace the content.
				if ( response.data.status ) {
					//remove Links
					parent.find( '.smush-status-links' ).remove();
					self.status.replaceWith( response.data.status );
				}

				// Check whether to show membership validity notice or not.
				Smush.membership_validity( response.data );

				if ( response.success && 'Not processed' !== response.data ) {
					self.status.removeClass( 'sui-hidden' );
					self.button.parent().removeClass( 'unsmushed' ).addClass( 'smushed' );
					self.button.remove();
				} else {
					self.status.addClass( 'error' );
					/** @var {string} response.data.error_msg */
					self.status.html( response.data.error_msg );
					self.status.show();
				}
				if ( 'undefined' !== stats_div && stats_div.length ) {
					stats_div.replaceWith( response.data.stats );
				} else {
					parent.append( response.data.stats );
				}

				/**
				 * Update image size in attachment info panel.
				 * @var {string|int} response.data.new_size
				 */
				Smush.update_image_stats( response.data.new_size );
			}
			self.enable_button();
		} ).error( function ( response ) {
			self.status.html( response.data );
			self.status.addClass( 'error' );
			self.enable_button();
		} );
	};

	/**
	 * Set pro savings stats if not premium user.
	 *
	 * For non-premium users, show expected avarage savings based
	 * on the free version savings.
	 */
	static set_pro_savings() {
		// Default values.
		let savings       = wp_smushit_data.savings_percent > 0 ? wp_smushit_data.savings_percent : 0,
			savings_bytes = wp_smushit_data.savings_bytes > 0 ? wp_smushit_data.savings_bytes : 0,
			orig_diff     = 2.22058824;

		if ( savings > 49 ) {
			orig_diff = 1.22054412;
		}

		// Calculate Pro savings.
		if ( savings > 0 ) {
			savings       = orig_diff * savings;
			savings_bytes = orig_diff * savings_bytes;
		}

		wp_smushit_data.pro_savings = {
			'percent': WP_Smush.helpers.precise_round( savings, 1 ),
			'savings_bytes': WP_Smush.helpers.formatBytes( savings_bytes, 1 )
		}
	};

	/**
	 * Update all stats sections based on the response.
	 *
	 * @param scan_type Current scan type.
	 */
	static update_stats( scan_type ) {
		const is_nextgen = 'undefined' !== typeof scan_type && 'nextgen' === scan_type;
		let super_savings = 0;

		// Calculate updated savings in bytes.
		wp_smushit_data.savings_bytes = parseInt( wp_smushit_data.size_before ) - parseInt( wp_smushit_data.size_after );

		const formatted_size = WP_Smush.helpers.formatBytes( wp_smushit_data.savings_bytes, 1 );
		const statsHuman     = jQuery( '.wp-smush-savings .wp-smush-stats-human' );

		if ( is_nextgen ) {
			statsHuman.html( formatted_size );
		} else {
			statsHuman.html( WP_Smush.helpers.getFormatFromString( formatted_size ) );
			jQuery( '.sui-summary-large.wp-smush-stats-human' ).html( WP_Smush.helpers.getSizeFromString( formatted_size ) );
		}

		// Update the savings percent.
		wp_smushit_data.savings_percent = WP_Smush.helpers.precise_round( ( parseInt( wp_smushit_data.savings_bytes ) / parseInt( wp_smushit_data.size_before ) ) * 100, 1 );
		if ( ! isNaN( wp_smushit_data.savings_percent ) ) {
			jQuery( '.wp-smush-savings .wp-smush-stats-percent' ).html( wp_smushit_data.savings_percent );
		}

		// Update Smush percent.
		wp_smushit_data.smush_percent = WP_Smush.helpers.precise_round( ( parseInt( wp_smushit_data.count_smushed ) / parseInt( wp_smushit_data.count_total ) ) * 100, 1 );
		jQuery( 'span.wp-smush-images-percent' ).html( wp_smushit_data.smush_percent );

		// Super-Smush savings.
		if ( 'undefined' !== typeof wp_smushit_data.savings_bytes && 'undefined' !== typeof wp_smushit_data.savings_resize ) {
			super_savings = parseInt( wp_smushit_data.savings_bytes ) - parseInt( wp_smushit_data.savings_resize );
			if ( super_savings > 0 ) {
				jQuery( 'li.super-smush-attachments span.smushed-savings' ).html( WP_Smush.helpers.formatBytes( super_savings, 1 ) );
			}
		}

		// Update image count.
		if ( is_nextgen ) {
			jQuery( '.sui-summary-details span.wp-smush-total-optimised' ).html( wp_smushit_data.count_images );
		} else {
			jQuery( 'span.smushed-items-count span.wp-smush-count-total span.wp-smush-total-optimised' ).html( wp_smushit_data.count_images );
		}

		// Update resize image count.
		jQuery( 'span.smushed-items-count span.wp-smush-count-resize-total span.wp-smush-total-optimised' ).html( wp_smushit_data.count_resize );

		// Update super-Smushed image count.
		const smushedCountDiv = jQuery( 'li.super-smush-attachments .smushed-count' );
		if ( smushedCountDiv.length && 'undefined' !== typeof wp_smushit_data.count_supersmushed ) {
			smushedCountDiv.html( wp_smushit_data.count_supersmushed );
		}

		// Update conversion savings.
		const smush_conversion_savings = jQuery( '.smush-conversion-savings' );
		if ( smush_conversion_savings.length > 0 && 'undefined' !== typeof ( wp_smushit_data.savings_conversion ) && wp_smushit_data.savings_conversion != '' ) {
			const conversion_savings = smush_conversion_savings.find( '.wp-smush-stats' );
			if ( conversion_savings.length > 0 ) {
				conversion_savings.html( WP_Smush.helpers.formatBytes( wp_smushit_data.savings_conversion, 1 ) );
			}
		}

		// Update resize savings.
		const smush_resize_savings = jQuery( '.smush-resize-savings' );
		if ( smush_resize_savings.length > 0 && 'undefined' !== typeof ( wp_smushit_data.savings_resize ) && wp_smushit_data.savings_resize != '' ) {
			// Get the resize savings in number.
			const savings_value = parseInt( wp_smushit_data.savings_resize );
			const resize_savings = smush_resize_savings.find( '.wp-smush-stats' );
			const resize_message = smush_resize_savings.find( '.wp-smush-stats-label-message' );
			// Replace only if value is grater than 0.
			if ( savings_value > 0 && resize_savings.length > 0 ) {
				// Hide message.
				if ( resize_message.length > 0 ) {
					resize_message.hide();
				}
				resize_savings.html( WP_Smush.helpers.formatBytes( wp_smushit_data.savings_resize, 1 ) );
			}
		}

		//Update pro Savings
		Smush.set_pro_savings();

		// Updating pro savings stats.
		if ( 'undefined' !== typeof wp_smushit_data.pro_savings ) {
			// Pro stats section.
			const smush_pro_savings = jQuery( '.smush-avg-pro-savings' );
			if ( smush_pro_savings.length > 0 ) {
				const pro_savings_percent = smush_pro_savings.find( '.wp-smush-stats-percent' );
				const pro_savings_bytes = smush_pro_savings.find( '.wp-smush-stats-human' );
				if ( pro_savings_percent.length > 0 && 'undefined' !== typeof wp_smushit_data.pro_savings.percent && wp_smushit_data.pro_savings.percent != '' ) {
					pro_savings_percent.html( wp_smushit_data.pro_savings.percent );
				}
				if ( pro_savings_bytes.length > 0 && 'undefined' !== typeof wp_smushit_data.pro_savings.savings_bytes && wp_smushit_data.pro_savings.savings_bytes != '' ) {
					pro_savings_bytes.html( wp_smushit_data.pro_savings.savings_bytes );
				}
			}
		}
	}

	/**
	 * Update image size in attachment info panel.
	 *
	 * @since 2.8
	 *
	 * @param {int} new_size
	 */
	static update_image_stats( new_size ) {
		if ( 0 === new_size ) {
			return;
		}

		const attachmentSize = jQuery( '.attachment-info .file-size' );
		const currentSize = attachmentSize.contents().filter( function () {
			return this.nodeType === 3;
		} ).text();

		// There is a space before the size.
		if ( currentSize !== ( ' ' + new_size ) ) {
			const sizeStrongEl = attachmentSize.contents().filter( function () {
				return this.nodeType === 1;
			} ).text();
			attachmentSize.html( '<strong>' + sizeStrongEl + '</strong> ' + new_size );
		}
	}

	/**
	 * Sync stats.
	 */
	sync_stats() {
		const message_holder = jQuery( 'div.wp-smush-bulk-progress-bar-wrapper div.wp-smush-count.tc' );
		// Store the existing content in a variable.
		const progress_message = message_holder.html();
		/** @var {string} wp_smush_msgs.sync_stats */
		message_holder.html( wp_smush_msgs.sync_stats );

		// Send ajax.
		jQuery.ajax( {
			type: 'GET',
			url: this.url,
			data: {
				'action': 'get_stats'
			},
			success: function ( response ) {
				if ( response && 'undefined' !== typeof response ) {
					response = response.data;
					jQuery.extend( wp_smushit_data, {
						count_images: response.count_images,
						count_smushed: response.count_smushed,
						count_total: response.count_total,
						count_resize: response.count_resize,
						count_supersmushed: response.count_supersmushed,
						savings_bytes: response.savings_bytes,
						savings_conversion: response.savings_conversion,
						savings_resize: response.savings_resize,
						size_before: response.size_before,
						size_after: response.size_after
					} );
					// Got the stats, update it.
					Smush.update_stats( this.smush_type );
				}
			}
		} ).always( () => message_holder.html( progress_message ) );
	};

	/**
	 * After the bulk Smushing has been finished.
	 */
	bulk_done() {
		if ( ! this.is_bulk ) return;

		// Enable the button.
		this.enable_button();

		const statusIcon = jQuery( '.sui-summary-smush .smush-stats-icon' );

		// Show notice.
		if ( 0 === this.ids.length ) {
			statusIcon.addClass( 'sui-hidden' );
			jQuery( '.bulk-smush-wrapper .wp-smush-all-done, .wp-smush-pagespeed-recommendation' ).show();
			jQuery( '.wp-smush-bulk-wrapper' ).hide();
			// Hide the progress bar if scan is finished.
			jQuery( '.wp-smush-bulk-progress-bar-wrapper' ).hide();
		} else {
			// Show loader.
			statusIcon.removeClass( 'sui-icon-loader sui-loading sui-hidden' ).addClass( 'sui-icon-info sui-warning' );

			const notice = jQuery( '.bulk-smush-wrapper .wp-smush-resmush-notice' );

			if ( notice.length > 0 ) {
				notice.show();
			} else {
				jQuery( '.bulk-smush-wrapper .wp-smush-remaining' ).show();
			}
		}

		// Enable re-Smush and scan button.
		jQuery( '.wp-resmush.wp-smush-action, .wp-smush-scan' ).removeAttr( 'disabled' );
	};

	is_resolved() {
		return 'resolved' === this.deferred.state();
	};

	/**
	 * Free Smush limit exceeded.
	 */
	free_exceeded() {
		if ( this.ids.length > 0 ) {
			const progress = jQuery( '.wp-smush-bulk-progress-bar-wrapper' );
			progress.addClass( 'wp-smush-exceed-limit' )
				.find( '.sui-progress-close' )
				/** @var {string} wp_smush_msgs.bulk_resume */
				.attr( 'data-tooltip', wp_smush_msgs.bulk_resume )
				.removeClass( 'wp-smush-cancel-bulk' )
				.addClass( 'wp-smush-all' ); // TODO: can we not add this class and instead add another listener?

			progress.find( '.sui-box-body.sui-hidden' ).removeClass( 'sui-hidden' );
		} else {
			jQuery( '.wp-smush-notice.wp-smush-all-done, .wp-smush-pagespeed-recommendation' ).show();
		}
	};

	/**
	 * Update remaining count.
	 */
	update_remaining_count() {
		if ( this.is_bulk_resmush ) {
			// Re-Smush notice.
			const resumeCountDiv = jQuery( '.wp-smush-resmush-notice .wp-smush-remaining-count' );
			if ( resumeCountDiv.length && 'undefined' !== typeof this.ids ) {
				resumeCountDiv.html( this.ids.length );
			}
		} else {
			// Smush notice.
			const wrapperCountDiv = jQuery( '.bulk-smush-wrapper .wp-smush-remaining-count' );
			if ( wrapperCountDiv.length && 'undefined' !== typeof this.ids ) {
				wrapperCountDiv.html( this.ids.length );
			}
		}

		// Update sidebar count.
		const sidenavCountDiv = jQuery( '.smush-sidenav .wp-smush-remaining-count' );
		if ( sidenavCountDiv.length && 'undefined' !== typeof this.ids ) {
			if ( this.ids.length > 0 ) {
				sidenavCountDiv.html( this.ids.length );
			} else {
				jQuery( '.sui-summary-smush .smush-stats-icon' ).addClass( 'sui-hidden' );
				sidenavCountDiv.removeClass( 'sui-tag sui-tag-warning' ).html( '' );
			}
		}
	};

	/**
	 * Adds the stats for the current image to existing stats.
	 * @param {array}   image_stats
	 * @param {string}  image_stats.count
	 * @param {boolean} image_stats.is_lossy
	 * @param {array}   image_stats.savings_resize
	 * @param {array}   image_stats.savings_conversion
	 * @param {string}  image_stats.size_before
	 * @param {string}  image_stats.size_after
	 * @param {string}  type
	 */
	static update_localized_stats( image_stats, type ) {
		// Increase the Smush count.
		if ( 'undefined' === typeof wp_smushit_data ) return;

		// No need to increase attachment count, resize, conversion savings for directory Smush.
		if ( 'media' === type ) {
			wp_smushit_data.count_smushed = parseInt( wp_smushit_data.count_smushed ) + 1;

			// Increase Smushed image count.
			wp_smushit_data.count_images = parseInt( wp_smushit_data.count_images ) + parseInt( image_stats.count );

			// Increase super Smush count, if applicable.
			if ( image_stats.is_lossy ) {
				wp_smushit_data.count_supersmushed = parseInt( wp_smushit_data.count_supersmushed ) + 1;
			}

			// Add to resize savings.
			wp_smushit_data.savings_resize = 'undefined' !== typeof image_stats.savings_resize.bytes ? parseInt( wp_smushit_data.savings_resize ) + parseInt( image_stats.savings_resize.bytes ) : parseInt( wp_smushit_data.savings_resize );

			// Update resize count.
			wp_smushit_data.count_resize = 'undefined' !== typeof image_stats.savings_resize.bytes ? parseInt( wp_smushit_data.count_resize ) + 1 : wp_smushit_data.count_resize;

			// Add to conversion savings.
			wp_smushit_data.savings_conversion = 'undefined' !== typeof image_stats.savings_conversion && 'undefined' !== typeof image_stats.savings_conversion.bytes ? parseInt( wp_smushit_data.savings_conversion ) + parseInt( image_stats.savings_conversion.bytes ) : parseInt( wp_smushit_data.savings_conversion );
		} else if ( 'directory_smush' === type ) {
			//Increase smushed image count
			wp_smushit_data.count_images = parseInt( wp_smushit_data.count_images ) + 1;
		} else if ( 'nextgen' === type ) {
			wp_smushit_data.count_smushed = parseInt( wp_smushit_data.count_smushed ) + 1;
			wp_smushit_data.count_supersmushed = parseInt( wp_smushit_data.count_supersmushed ) + 1;

			// Increase Smushed image count.
			wp_smushit_data.count_images = parseInt( wp_smushit_data.count_images ) + parseInt( image_stats.count );
		}

		// If we have savings. Update savings.
		if ( image_stats.size_before > image_stats.size_after ) {
			wp_smushit_data.size_before = 'undefined' !== typeof image_stats.size_before ? parseInt( wp_smushit_data.size_before ) + parseInt( image_stats.size_before ) : parseInt( wp_smushit_data.size_before );
			wp_smushit_data.size_after = 'undefined' !== typeof image_stats.size_after ? parseInt( wp_smushit_data.size_after ) + parseInt( image_stats.size_after ) : parseInt( wp_smushit_data.size_after );
		}

		// Add stats for resizing. Update savings.
		if ( 'undefined' !== typeof image_stats.savings_resize ) {
			wp_smushit_data.size_before = 'undefined' !== typeof image_stats.savings_resize.size_before ? parseInt( wp_smushit_data.size_before ) + parseInt( image_stats.savings_resize.size_before ) : parseInt( wp_smushit_data.size_before );
			wp_smushit_data.size_after = 'undefined' !== typeof image_stats.savings_resize.size_after ? parseInt( wp_smushit_data.size_after ) + parseInt( image_stats.savings_resize.size_after ) : parseInt( wp_smushit_data.size_after );
		}

		// Add stats for conversion. Update savings.
		if ( 'undefined' !== typeof image_stats.savings_conversion ) {
			wp_smushit_data.size_before = 'undefined' !== typeof image_stats.savings_conversion.size_before ? parseInt( wp_smushit_data.size_before ) + parseInt( image_stats.savings_conversion.size_before ) : parseInt( wp_smushit_data.size_before );
			wp_smushit_data.size_after = 'undefined' !== typeof image_stats.savings_conversion.size_after ? parseInt( wp_smushit_data.size_after ) + parseInt( image_stats.savings_conversion.size_after ) : parseInt( wp_smushit_data.size_after );
		}
	};

	/**
	 * Update progress.
	 *
	 * @param _res
	 */
	update_progress( _res ) {
		if ( ! this.is_bulk_resmush && ! this.is_bulk ) return;

		let progress = '';

		// Update localized stats.
		if ( _res && ( 'undefined' !== typeof _res.data || 'undefined' !== typeof _res.data.stats ) ) {
			Smush.update_localized_stats( _res.data.stats, this.smush_type );
		}

		if ( ! this.is_bulk_resmush ) {
			// Handle progress for normal bulk smush.
			progress = ( wp_smushit_data.count_smushed / wp_smushit_data.count_total ) * 100;
		} else {
			// If the request was successful, update the progress bar.
			if ( _res.success ) {
				// Handle progress for super Smush progress bar.
				if ( wp_smushit_data.resmush.length > 0 ) {
					// Update the count.
					jQuery( '.wp-smush-images-remaining' ).html( wp_smushit_data.resmush.length );
				} else if ( 0 === wp_smushit_data.resmush.length && 0 === this.ids.length ) {
					// If all images are re-Smushed, show the All Smushed message.
					jQuery( '.bulk-resmush-wrapper .wp-smush-all-done, .wp-smush-pagespeed-recommendation' ).removeClass( 'sui-hidden' );

					// Hide everything else.
					jQuery( '.wp-smush-resmush-wrap, .wp-smush-bulk-progress-bar-wrapper' ).hide();
				}
			}

			// Handle progress for normal bulk Smush. Set progress bar width.
			if ( 'undefined' !== typeof this.ids && 'undefined' !== typeof wp_smushit_data.count_total && wp_smushit_data.count_total > 0 ) {
				progress = ( wp_smushit_data.count_smushed / wp_smushit_data.count_total ) * 100;
			}
		}

		// No more images left. Show bulk wrapper and Smush notice.
		if ( 0 === this.ids.length ) {
			// Sync stats for bulk Smush media library ( skip for Nextgen ).
			if ( 'nextgen' !== this.smush_type ) {
				this.sync_stats();
			}
			jQuery( '.bulk-smush-wrapper .wp-smush-all-done, .wp-smush-pagespeed-recommendation' ).show();
			jQuery( '.wp-smush-bulk-wrapper' ).hide();
		}

		// Update remaining count.
		this.update_remaining_count();

		// If we have received the progress data, update the stats else skip.
		if ( 'undefined' !== typeof _res.data.stats ) {
			// Increase the progress bar.
			this._update_progress( wp_smushit_data.count_smushed, progress );
			// Update the counter.
			Smush._update_progress_status( wp_smushit_data.count_smushed, wp_smushit_data.count_total);
		}
		// Update stats and counts.
		Smush.update_stats( this.smush_type );
	};

	/**
	 * Update progress.
	 *
	 * @param {int}    count  Number of images Smushed.
	 * @param {string} width  Percentage complete.
	 * @private
	 */
	_update_progress( count, width ) {
		if ( ! this.is_bulk && ! this.is_bulk_resmush ) return;

		// Update the progress bar width. Get the progress bar.
		const progress_bar = jQuery( '.bulk-smush-wrapper .wp-smush-progress-inner' );
		if ( progress_bar.length < 1 ) {
			return;
		}

		// Increase progress.
		progress_bar.css( 'width', width + '%' );
	};

	/**
	 * Update progress bar status.
	 *
	 * @param {int} smushed  Number of Smushed images.
	 * @param {int} total    Total number of imags.
	 * @private
	 */
	static _update_progress_status( smushed, total ) {
		const progress_status = jQuery( '.bulk-smush-wrapper .sui-progress-state-text' );

		if ( 1 > progress_status.length ) {
			return;
		}

		progress_status.find( 'span' ).html( smushed + '/' + total );
	};

	/**
	 * Whether to send the ajax requests further or not.
	 *
	 * @returns {*|boolean}
	 */
	continue() {
		let continue_smush = this.button.attr( 'continue_smush' );

		if ( 'undefined' === typeof continue_smush ) {
			continue_smush = true;
		}

		if ( 'false' === continue_smush || ! continue_smush ) {
			continue_smush = false;
		}

		return continue_smush && this.ids.length > 0 && this.is_bulk;
	};

	/**
	 * Add image ID to the errors array.
	 *
	 * @param id
	 */
	increment_errors( id ) {
		this.errors.push( id );
	};

	/**
	 * Send ajax request for Smushing single and bulk, call update_progress on ajax response.
	 *
	 * @returns {*|{}}
	 */
	call_ajax() {
		let nonce_value = '';
		// Remove from array while processing so we can continue where left off.
		this.current_id = this.is_bulk ? this.ids.shift() : this.button.data( 'id' );

		// Remove the ID from respective variable as well.
		Smush.update_smush_ids( this.current_id );

		const nonce_field = this.button.parent().find( '#_wp_smush_nonce' );
		if ( nonce_field ) {
			nonce_value = nonce_field.val();
		}

		const self = this;

		this.request = Smush.ajax( this.is_bulk_resmush, this.current_id, this.url, 0, nonce_value )
			.error( function () {
				// TODO: check that this method ever fires.
				self.increment_errors( self.current_id );
			} )
			.done( function ( res ) {
				// Increase the error count except if bulk request limit exceeded.
				if ( 'undefined' === typeof res.success || ( 'undefined' !== typeof res.success && false === res.success && 'undefined' !== typeof res.data && 'bulk_request_image_limit_exceeded' !== res.data.error ) ) {
					self.increment_errors( self.current_id );
				}

				// If no response or success is false, do not process further.
				if ( ( ! res || ! res.success ) && ( 'undefined' !== typeof res && 'undefined' !== typeof res.data && 'undefined' !== typeof res.data.error ) ) {
					// TODO: Handle Bulk Smush limit error message

					/** @var {string} res.data.error_class */
					const error_class = 'undefined' !== typeof res.data.error_class ? 'smush-error-message ' + res.data.error_class : 'smush-error-message';
					/** @var {string} res.data.error_message */
					const error_msg = '<p class="' + error_class + '">' + res.data.error_message + '</p>';

					if ( 'undefined' !== typeof res.data.error && 'bulk_request_image_limit_exceeded' === res.data.error ) {
						const ajax_error_message = jQuery( '.wp-smush-ajax-error' );
						// If we have ajax error message div, append after it.
						if ( ajax_error_message.length > 0 ) {
							ajax_error_message.after( error_msg );
						} else {
							// Otherwise prepend.
							self.log.prepend( error_msg );
						}
					} else if ( 'undefined' !== typeof res.data.error_class && '' !== res.data.error_class && jQuery( 'div.smush-final-log .' + res.data.error_class ).length > 0 ) {
						const error_count = jQuery( 'p.smush-error-message.' + res.data.error_class + ' .image-error-count' );
						// Get the error count, increase and append.
						let image_count = error_count.html();
						image_count = parseInt( image_count ) + 1;
						// Append the updated image count.
						error_count.html( image_count );
					} else {
						// Print the error on screen.
						self.log.append( error_msg );
					}

					self.log.show();
				}

				// Check whether to show the warning notice or not.
				Smush.membership_validity( res.data );

				/**
				 * Bulk Smush limit exceeded: Stop ajax requests, remove progress bar, append the last image ID
				 * back to Smush variable, and reset variables to allow the user to continue bulk Smush.
				 */
				if ( 'undefined' !== typeof res.data && 'bulk_request_image_limit_exceeded' === res.data.error && ! self.is_resolved() ) {
					// Add a data attribute to the Smush button, to stop sending ajax.
					self.button.attr( 'continue_smush', false );

					self.free_exceeded();

					// Reinsert the current ID.
					wp_smushit_data.unsmushed.unshift( self.current_id );

					// Update the remaining count to length of remaining IDs + 1 (current ID).
					self.update_remaining_count();
				} else if ( self.is_bulk && res.success ) {
					self.update_progress( res );
				} else if ( 0 === self.ids.length ) {
					// Sync stats anyway.
					self.sync_stats();
				}

				self.single_done();
			} )
			.complete( function () {
				if ( ! self.continue() || ! self.is_bulk ) {
					// Calls deferred.done()
					self.deferred.resolve();
				} else {
					self.call_ajax();
				}
			} );

		this.deferred.errors = this.errors;
		return this.deferred;
	};

	/**
	 * Send ajax request for single and bulk Smushing.
	 */
	run() {
		// If bulk and we have a definite number of IDs.
		if ( this.is_bulk && this.ids.length > 0 )
			this.call_ajax();

		if ( ! this.is_bulk )
			this.call_ajax();
	};

	/**
	 * Show bulk Smush errors, and disable bulk Smush button on completion.
	 */
	bind_deferred_events() {
		const self = this;

		this.deferred.done( function () {
			self.button.removeAttr( 'continue_smush' );

			if ( self.errors.length ) {
				/** @var {string} wp_smush_msgs.error_in_bulk */
				const error_message = '<div class="wp-smush-ajax-error">' + wp_smush_msgs.error_in_bulk.replace( "{{errors}}", self.errors.length ) + '</div>';
				// Remove any existing notice.
				jQuery( '.wp-smush-ajax-error' ).remove();
				self.log.prepend( error_message );
			}

			self.bulk_done();

			// Re-enable the buttons.
			jQuery( '.wp-smush-all:not(.wp-smush-finished), .wp-smush-scan' ).removeAttr( 'disabled' );
		} );
	};

	/**
	 * Handles the cancel button click.
	 * Update the UI, and enable the bulk Smush button.
	 */
	cancel_ajax() {
		const self = this;

		jQuery( '.wp-smush-cancel-bulk' ).on( 'click', function () {
			// Add a data attribute to the Smush button, to stop sending ajax.
			self.button.attr( 'continue_smush', false );
			// Sync and update stats.
			self.sync_stats();
			Smush.update_stats( this.smush_type );

			self.request.abort();
			self.enable_button();
			self.button.removeClass( 'wp-smush-started' );
			wp_smushit_data.unsmushed.unshift( self.current_id );
			jQuery( '.wp-smush-bulk-wrapper' ).show();

			// Hide the progress bar.
			jQuery( '.wp-smush-bulk-progress-bar-wrapper' ).hide();
		} );
	};

	/**
	 * Remove the current ID from the unSmushed/re-Smush variable.
	 *
	 * @param current_id
	 */
	static update_smush_ids( current_id ) {
		if ( 'undefined' !== typeof wp_smushit_data.unsmushed && wp_smushit_data.unsmushed.length > 0 ) {
			const u_index = wp_smushit_data.unsmushed.indexOf( current_id );
			if ( u_index > -1 ) {
				wp_smushit_data.unsmushed.splice( u_index, 1 );
			}
		}

		// Remove from the re-Smush list.
		if ( 'undefined' !== typeof wp_smushit_data.resmush && wp_smushit_data.resmush.length > 0 ) {
			const index = wp_smushit_data.resmush.indexOf( current_id );
			if ( index > -1 ) {
				wp_smushit_data.resmush.splice( index, 1 );
			}
		}
	};

}

export default Smush;