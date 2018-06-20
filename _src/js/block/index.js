/**
 * BLOCK: extend image block
 */

//  Import CSS.
import './style.scss';

const { __ } = wp.i18n; 				 // Import __() from wp.i18n
const { registerBlockType } = wp.blocks; // Import registerBlockType() from wp.blocks
const { getControlSettings } = wp.blocks;




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

console.log( getControlSettings( 'core/image' ) );

/*
function addBackgroundColorStyle( props ) {
	return Object.assign( props, { style: { backgroundColor: 'red' } } );
}

wp.hooks.addFilter(
	'blocks.getSaveContent.extraProps',
	'my-plugin/add-background-color-style',
	addBackgroundColorStyle
);

*/

/*
function setBlockEdit() {

}

wp.hooks.addFilter(
	'blocks.BlockEdit',
	'wp-smushit/set-block-edit',
	setBlockEdit
)
*/