/**
 * BLOCK: extend image block
 */

import React from 'react';

import './style.scss';

const { __ } = wp.i18n,
	  el     = wp.element.createElement;

/**
 * Transform bytes to human readable format.
 *
 * @param {int} bytes
 * @returns {string}
 */
function humanFileSize( bytes ) {
	const thresh = 1000,
		  units  = ['kB','MB','GB','TB','PB','EB','ZB','YB'];

	if ( Math.abs( bytes ) < thresh ) {
		return bytes + ' B';
	}

	let u = -1;
	do {
		bytes /= thresh;
		++u;
	} while ( Math.abs( bytes ) >= thresh && u < units.length - 1 );

	return bytes.toFixed( 1) + ' ' + units[u];
}

/**
 * Generate Smush stats table.
 *
 * @param {object} stats
 * @returns {*}
 */
export function smushStats( stats ) {
	if ( 'undefined' === typeof stats ) {
		return (
			<div>
				Select an image to view Smush stats.
			</div>
		);
	}

	return (
		<div id="smush-stats-135" className="sui-smush-media smush-stats-wrapper hidden" style={ { display: 'block' } }>
			<table className="wp-smush-stats-holder">
				<thead>
				<tr>
					<th className="smush-stats-header">Image size</th>
					<th className="smush-stats-header">Savings</th>
				</tr>
				</thead>
				<tbody>
				{ Object.keys( stats.sizes ).map( ( item, i ) => (
					<tr key={ i }>
						<td>{ item.toUpperCase() }</td>
						<td>{ humanFileSize( stats.sizes[item].bytes ) } ( { stats.sizes[item].percent }% )</td>
					</tr>)
				) }
				</tbody>
			</table>
		</div>
	);
}

/**
 * Modify the block’s edit component.
 * Receives the original block BlockEdit component and returns a new wrapped component.
 */
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

		let smushData = props.attributes.smush;

		if ( 'undefined' === typeof smushData ) {
			let image = new wp.api.models.Media( { id: props.attributes.id } );
			image.fetch( { attribute: 'smush' } ).done( function ( img ) {
				if ( typeof img.smush === 'object' ) {
					props.setAttributes( { smush: img.smush } );
				}
			});
		}

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
					smushStats( smushData )
				),
			)
		);

	};
}, 'withInspectorControls' );

wp.hooks.addFilter( 'editor.BlockEdit', 'wp-smushit/smush-data-control', addInspectorControl );
