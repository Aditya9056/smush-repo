/**
 * Bulk Smush functionality.
 *
 * @since 2.9.0  Moved from admin.js
 */

import Smush from '../smush/smush';

( function( $ ) {
	'use strict';

	WP_Smush.bulk = {

		init: () => {

			/**
			 * Handle the Bulk Smush/Bulk re-Smush button click.
			 */
			$( 'body' ).on( 'click', 'button.wp-smush-all', function( e ) {
				e.preventDefault();

				$( '.sui-notice-top.sui-notice-success' ).remove();

				// Remove limit exceeded styles.
				const progress = $( '.wp-smush-bulk-progress-bar-wrapper' );
				/** @var {string} wp_smush_msgs.bulk_stop */
				progress.removeClass( 'wp-smush-exceed-limit' )
					.find( '.sui-progress-close' ).attr( 'data-tooltip', wp_smush_msgs.bulk_stop );
				// Hide Resume button.
				progress.find( '.sui-box-body' ).addClass( 'sui-hidden' );

				// Disable re-Smush and scan button.
				// TODO: refine what is disabled.
				$( '.wp-resmush.wp-smush-action, .wp-smush-scan, .wp-smush-all:not(.sui-progress-close), a.wp-smush-lossy-enable, button.wp-smush-resize-enable, input#wp-smush-save-settings' ).attr( 'disabled', 'disabled' );

				// Check for IDs, if there is none (unsmushed or lossless), don't call Smush function.
				/** @var {array} wp_smushit_data.unsmushed */
				if ( 'undefined' === typeof wp_smushit_data ||
					( 0 === wp_smushit_data.unsmushed.length && 0 === wp_smushit_data.resmush.length )
				) {
					return false;
				}

				$( '.wp-smush-remaining' ).hide();

				// Show loader.
				$( '.sui-summary-smush .smush-stats-icon' )
					.removeClass( 'sui-icon-info sui-warning' )
					.addClass( 'sui-icon-loader sui-loading' );

				new Smush( $( this ), true, 'media' );
			} );

		}

	};

	WP_Smush.bulk.init();

}( jQuery ));
