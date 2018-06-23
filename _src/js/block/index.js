/**
 * BLOCK: extend image block
 */

import React from 'react';

import './style.scss';

const { __ } = wp.i18n,
	  el     = wp.element.createElement;

export function smushStats( stats ) {

	console.log( stats );

	return (
		<h1>asdasds</h1>
	);
}

let addInspectorControl = wp.element.createHigherOrderComponent( function( BlockEdit ) {
	return function( props ) {
		// If not image block, return unmodified block.
		if ( 'core/image' !== props.name ) {
			return el(
				wp.element.Fragment,
				{},
				el(
					BlockEdit,
					props
				)
			);
		}

		jQuery.post( ajaxurl, { action: 'get_gb_stats', id: props.attributes.id } )
			.done( function ( response ) {
				if ( 'undefined' !== typeof response.data ) {

					/*
					props.setAttributes( {
						smush: response.data.stats,
					} );
					*/
				}
		} );

		//console.log( props.attributes.smush );

		return el(
			wp.element.Fragment,
			{},
			el(
				BlockEdit,
				props
			),
			el(
				wp.editor.InspectorControls,
				{},
				el(
					wp.components.PanelBody,
					{
						title: __( 'Smush Stats' )
					},
					'a'
				),
			)
		);

	};
}, 'withInspectorControls' );

wp.hooks.addFilter( 'editor.BlockEdit', 'wp-smushit/smush-data-control', addInspectorControl );


