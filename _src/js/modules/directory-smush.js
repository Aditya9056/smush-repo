import { createTree } from 'jquery.fancytree';

( function( $ ) {
	'use strict';

	WP_Smush.directory = {
		selected: [],
		tree: [],
		wp_smush_msgs: [],

		init: function () {
			let self = this;

			/**
			 * Smush translation strings.
			 *
			 * @var {array} wp_smush_msgs
			 */
			if ( wp_smush_msgs ) {
				this.wp_smush_msgs = wp_smush_msgs;
			}

			/**
			 * WP Smush all: Scan Images
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
				$( 'button.wp-smush-resume' ).attr( 'disabled', 'disabled' );

				// Remove notice.
				$( 'div.wp-smush-info' ).remove();

				self.showDialog();

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
			 * Image Directories: On select button click
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

				// Remove resume button
				$( 'button.wp-smush-resume' ).remove();

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

				//Send a ajax request to get a list of all the image files
				const param = {
					action: 'image_list',
					smush_path: paths,
					image_list_nonce: $( 'input[name="image_list_nonce"]' ).val()
				};

				//Get the List of images
				$.get( ajaxurl, param, function ( res ) {
					console.log( res );
					/*
					if ( !res.success && 'undefined' !== typeof ( res.data.message ) ) {
						$( 'div.wp-smush-scan-result div.content' ).html( res.data.message );
					} else {
						$( '.wp-smush-scan-result .smush-dir-smush-done' ).addClass( 'sui-hidden' );
						$( 'div.wp-smush-scan-result div.content' ).html( res.data );
						var wp_smush_dir_image_ids = res.data.ids;
					}
					set_accordion();
					close_dialog();

					//Remove the spinner
					spinner.removeClass( 'sui-icon-loader sui-loading' );

					//Show Scan result
					$( '.wp-smush-scan-result' ).removeClass( 'sui-hidden' ).show();
					*/
				} ).done( function ( res ) {

					/*
					// Show select directory button on top.
					$( 'div.sui-box-header button.wp-smush-browse' ).removeClass( 'sui-hidden' );

					//If there was no image list, return
					if ( !res.success ) {
						//Hide the smush button
						$( 'div.wp-smush-all-button-wrap.bottom' ).addClass( 'sui-hidden' );
						return;
					}

					//Show the smush button
					$( 'div.wp-smush-all-button-wrap.bottom' ).removeClass( 'sui-hidden' );

					//Remove disabled attribute for the button
					$( 'button.wp-smush-start' ).removeAttr( 'disabled' );

					//Append a Directory browser button at the top
					add_dir_browser_button();

					//Clone and add Smush button
					add_smush_button();

					*/
					window.location.hash = '#wp-smush-dir-browser';
				} );

				/*
				// Get the Selected directory path
				let path = $( '.jqueryFileTree .selected a' ).attr( 'rel' );
				path = ( 'undefined' === typeof path ) ? '' : path;

				// Absolute path
				let abs_path = $( 'input[name="wp-smush-base-path"]' ).val();

				//Fill in the input field
				$( '.wp-smush-dir-path' ).val( abs_path + '/' + path );

				//Send a ajax request to get a list of all the image files
				var param = {
					action: 'image_list',
					smush_path: $( '.wp-smush-dir-path' ).val(),
					image_list_nonce: $( 'input[name="image_list_nonce"]' ).val()
				};

				//Get the List of images
				$.get( ajaxurl, param, function ( res ) {
					if ( !res.success && 'undefined' !== typeof ( res.data.message ) ) {
						$( 'div.wp-smush-scan-result div.content' ).html( res.data.message );
					} else {
						$( '.wp-smush-scan-result .smush-dir-smush-done' ).addClass( 'sui-hidden' );
						$( 'div.wp-smush-scan-result div.content' ).html( res.data );
						var wp_smush_dir_image_ids = res.data.ids;
					}
					set_accordion();
					close_dialog();

					//Remove the spinner
					spinner.removeClass( 'sui-icon-loader sui-loading' );

					//Show Scan result
					$( '.wp-smush-scan-result' ).removeClass( 'sui-hidden' ).show();
				} ).done( function ( res ) {

					// Show select directory button on top.
					$( 'div.sui-box-header button.wp-smush-browse' ).removeClass( 'sui-hidden' );

					//If there was no image list, return
					if ( !res.success ) {
						//Hide the smush button
						$( 'div.wp-smush-all-button-wrap.bottom' ).addClass( 'sui-hidden' );
						return;
					}

					//Show the smush button
					$( 'div.wp-smush-all-button-wrap.bottom' ).removeClass( 'sui-hidden' );

					//Remove disabled attribute for the button
					$( 'button.wp-smush-start' ).removeAttr( 'disabled' );

					//Append a Directory browser button at the top
					add_dir_browser_button();

					//Clone and add Smush button
					add_smush_button();

					window.location.hash = '#wp-smush-dir-browser';
				} );
				*/
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
		showDialog: function () {
			// Shows the available directories.
			$( '.wp-smush-list-dialog' ).show();
			$( '.wp-smush-list-dialog div.close' ).focus();
		}

	};

	WP_Smush.directory.init();

}( jQuery ));