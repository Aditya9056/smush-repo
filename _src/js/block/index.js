/**
 * BLOCK: extend image block
 */

import assign from 'lodash/assign';
import React from 'react';

//  Import CSS.
import './style.scss';

const { __ } = wp.i18n; 				 // Import __() from wp.i18n
const { InspectorControls } = wp.blocks;
//const { registerBlockType } = wp.blocks; // Import registerBlockType() from wp.blocks





export function addAttribute( settings, blockName ) {
	//console.log( settings );
	//console.log( blockName );

	if ( 'core/image' === blockName ) {
		// Use Lodash's assign to gracefully handle if attributes are undefined
		settings.attributes = assign(settings.attributes, {
			smushStats: {
				type: 'string',
			},
		});
	}

	return settings;
}

export function addInspectorControl( element ) {
	console.log( element );
	console.log( element.originalContent );

	return class extends element {

	}


	//return element;
}


wp.hooks.addFilter( 'blocks.registerBlockType', 'wp-smushit/smush-data', addAttribute );
wp.hooks.addFilter( 'blocks.BlockEdit', 'wp-smushit/smush-data-control', addInspectorControl );










export function setBlockTest( blockType, attributes ) {
	//console.log( blockType );
	//console.log( attributes );
	//registerBlockType

	/*
	const { save } = blockType;
	let saveContent;

	if ( save.prototype instanceof Component ) {
		saveContent = createElement( save, { attributes } );
	} else {
		saveContent = save( { attributes } );

		// Special-case function render implementation to allow raw HTML return
		if ( 'string' === typeof saveContent ) {
			return saveContent;
		}
	}
	*/

	/*
	const addExtraContainerProps = ( element ) => {
		if ( ! element || ! isObject( element ) ) {
			return element;
		}

		// Applying the filters adding extra props
		const props = wp.blocks.applyFilters( 'getSaveContent.extraProps', { ...element.props }, blockType, attributes );
		console.log( props );

		return cloneElement( element, props );
	};
	const contentWithExtraProps = Children.map( saveContent, addExtraContainerProps );

	// Otherwise, infer as element
	return renderToString( contentWithExtraProps );
	*/
}

wp.hooks.addFilter(
	'blocks.getSaveElement',
	'wp-smushit/set-block-save',
	setBlockTest
);




// Our filter function
function setBlockCustomClassName( className, blockName ) {
	return blockName === 'core/image' ?
		'wp-shmushit-code' :
		className;
}

// Adding the filter
wp.hooks.addFilter(
	'blocks.getBlockDefaultClassName',
	'wp-smushit/set-block-custom-class-name',
	setBlockCustomClassName
);


/*
function addBackgroundColorStyle( props ) {
	//console.log( props );
	return Object.assign( props, { style: { backgroundColor: 'red' } } );
}

wp.hooks.addFilter(
	'blocks.getSaveContent.extraProps',
	'my-plugin/add-background-color-style',
	addBackgroundColorStyle
);
*/
