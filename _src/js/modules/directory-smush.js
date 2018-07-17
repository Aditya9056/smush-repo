import { createTree } from 'jquery.fancytree';
import Scanner from './directory-scanner';
import {getLink} from "../../../../wp-hummingbird/_src/js/utils/helpers";

( function( $ ) {
	'use strict';

	WP_Smush.directory = {
		selected: [],
		tree: [],
		wp_smush_msgs: [],

		init: function () {
			const self = this;

			// Init image scanner.
			//console.log( 'Total steps/current step: ' + wp_smushit_data.dir_smush.totalSteps + ' ' + wp_smushit_data.dir_smush.currentScanStep );
			//this.scanner = new Scanner( wp_smushit_data.dir_smush.totalSteps, wp_smushit_data.dir_smush.currentScanStep );

			/**
			 * Smush translation strings.
			 *
			 * @var {array} wp_smush_msgs
			 */
			if ( wp_smush_msgs ) {
				this.wp_smush_msgs = wp_smush_msgs;
			}

			/**
			 * Folder select: Choose Folder in Directory Smush tab clicked.
			 */
			$( 'div.sui-wrap' ).on( 'click', 'button.wp-smush-browse', function ( e ) {
				e.preventDefault();

				// Hide all the notices.
				$( 'div.wp-smush-scan-result div.wp-smush-notice' ).hide();

				// If disabled, do not process.
				if ( $( this ).attr( 'disabled' ) ) {
					return;
				}

				// Disable buttons.
				$( this ).attr( 'disabled', 'disabled' );

				// Remove notice.
				$( 'div.wp-smush-info' ).remove();

				self.showSmushDialog();

				// Display file tree for directory Smush.
				self.initFileTree();
			} );

			/**
			 * Stats section: Directory Link
			 */
			$( 'body' ).on( 'click', 'a.wp-smush-dir-link', function ( e ) {
				if ( $( 'div.sui-wrap button.wp-smush-browse' ).length > 0 ) {
					e.preventDefault();
					SUI.dialogs["wp-smush-list-dialog"].show();
					//Display File tree for Directory Smush
					self.initFileTree();
				}
			} );

			/**
			 * Smush images: Smush in Choose Directory modal clicked
			 */
			$( '.wp-smush-select-dir' ).on( 'click', function ( e ) {
				e.preventDefault();

				// If disabled, do not process
				if ( $( this ).attr( 'disabled' ) ) {
					return;
				}

				const button = $( this );

				$( 'div.wp-smush-list-dialog div.sui-box-body' ).css( { 'opacity': '0.8' } );
				$( 'div.wp-smush-list-dialog div.sui-box-body a' ).unbind( 'click' );

				// Disable button
				button.attr( 'disabled', 'disabled' );

				let spinner = button.parent().find( '.add-dir-loader' );
				// Display the spinner
				spinner.addClass( 'sui-icon-loader sui-loading' );

				const selectedFolders = self.tree.getSelectedNodes(),
				      abs_path        = $( 'input[name="wp-smush-base-path"]' ).val(); // Absolute path.

				let paths = [];
				selectedFolders.forEach( function ( folder ) {
					paths.push( abs_path + '/' + folder.key );
				});

				// Send a ajax request to get a list of all the image files
				const param = {
					action: 'image_list',
					smush_path: paths,
					image_list_nonce: $( 'input[name="image_list_nonce"]' ).val()
				};

				$.get( ajaxurl, param, function ( response ) {
					// Close the dialog.
					SUI.dialogs['wp-smush-list-dialog'].hide();

					// TODO: check for errors.
					self.scanner = new Scanner( response.data, 0 );
					self.showProgressDialog();
					self.scanner.scan();
				} );
			} );
		},

		/**
		 * Get directory list using Ajax.
		 *
		 * @param {string} node  Node for which to get the directory list.
		 *
		 * @returns {string}
		 */
		getDirectoryList: function ( node = '' ) {
			let res = '';

			$.ajax( {
				type: "GET",
				url: ajaxurl,
				data: {
					action: 'smush_get_directory_list',
					list_nonce: jQuery( 'input[name="list_nonce"]' ).val(),
					dir: node
				},
				success: function ( response ) {
					res = response.data;
				},
				async: false
			} );

			// Update the button text.
			$( 'button.wp-smush-select-dir' ).html( self.wp_smush_msgs.add_dir );

			return res;
		},

		/**
		 * Init fileTree.
		 */
		initFileTree: function () {
			const self = this;

			self.tree = createTree('.wp-smush-list-dialog .content', {
				autoCollapse: true, // Automatically collapse all siblings, when a node is expanded
				clickFolderMode: 3, // 1:activate, 2:expand, 3:activate and expand, 4:activate (dblclick expands)
				checkbox: true,     // Show checkboxes
				debugLevel: 0,      // 0:quiet, 1:errors, 2:warnings, 3:infos, 4:debug
				selectMode: 3,      // 1:single, 2:multi, 3:multi-hier
				tabindex: '0',      // Whole tree behaves as one single control
				source: self.getDirectoryList,
				lazyLoad: function( event, data ) {
					const node = data.node;
					data.result = self.getDirectoryList( node.key );
				},
				loadChildren: function( event, data ) {
					// Apply parent's state to new child nodes:
					data.node.fixSelection3AfterClick();
				}
			});
		},

		/**
		 * Show directory list popup and focus on close button.
		 */
		showSmushDialog: function () {
			// Shows the available directories.
			SUI.dialogs['wp-smush-list-dialog'].show();
			$( '.wp-smush-list-dialog div.close' ).focus();
		},

		/**
		 * Show progress dialog.
		 */
		showProgressDialog: function () {
			SUI.dialogs['wp-smush-progress-dialog'].show();
			$( '.wp-smush-progress-dialog div.close' ).focus();
		},

		/**
		 * Update progress bar during directory smush.
		 *
		 * @param {int}     progress  Current progress in percent.
		 * @param {boolean} cancel    Cancel status.
		 */
		updateProgressBar: function ( progress, cancel = false ) {
			if ( progress > 100 ) {
				progress = 100;
			}

			// Update progress bar
			$( '.sui-progress-block .sui-progress-text span' ).text( progress + '%' );
			$( '.sui-progress-block .sui-progress-bar span' ).width( progress + '%' );

			if ( progress >= 90 ) {
				$( '.sui-progress-state .sui-progress-state-text' ).text( 'Finalizing...' );
			}

			if ( cancel ) {
				$( '.sui-progress-state .sui-progress-state-text' ).text( 'Cancelling...' );
			}
		},

	};

	WP_Smush.directory.init();

}( jQuery ));