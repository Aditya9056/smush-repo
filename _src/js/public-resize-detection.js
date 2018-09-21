/**
 * Image resize detection (IRS).
 *
 * Show all wrongly scaled images with a highlighted border and resize box.
 *
 * Made in pure JS.
 * DO NOT ADD JQUERY SUPPORT!!!
 *
 * @since 2.9
 */
( function() {
	'use strict';

	const WP_Smush_IRS = {
		contentDiv: '',
		larger: 0,
		smaller: 0,

		/**
		 * Init scripts.
		 */
		init: function () {
			/** @var {array} wp_smush_resize_vars */
			if ( wp_smush_resize_vars ) {
				self.strings = wp_smush_resize_vars;
			}

			this.detectImages();
		},

		/**
		 * Various checks to see if the image should be processed.
		 *
		 * @param {object} image
		 * @returns {boolean}
		 */
		shouldSkipImage: function(image) {
			// Skip avatars.
			if ( image.classList.contains('avatar') ) {
				return true;
			}

			// If width attribute is not set, do not continue.
			return null === image.clientWidth || null === image.clientHeight;
		},

		/**
		 * Get tooltip text.
		 *
		 * @param {object} props
		 * @returns {string}
		 */
		getTooltipText: function(props) {
			let tooltip_text = '';

			if ( props.bigger_width || props.bigger_height ) {
				/** @var {string} strings.large_image */
				tooltip_text = strings.large_image;
			} else if ( props.smaller_width || props.smaller_height ) {
				/** @var {string} strings.small_image */
				tooltip_text = strings.small_image;
			}

			return tooltip_text.replace('width', props.real_width)
				.replace('height', props.real_height);
		},

		/**
		 * Create HTML content to append.
		 *
		 * @param {object} props
		 * @returns {HTMLElement}
		 */
		createItemDiv: function(props) {
			const item = document.createElement('div');
			item.setAttribute('class', 'smush-resize-box smush-tooltip smush-tooltip-constrained');
			item.setAttribute('data-tooltip', this.getTooltipText(props));

			const count = document.createElement('span');
			count.innerText = props.bigger_width || props.bigger_height ? this.larger : this.smaller;

			const tag = document.createElement('span');
			tag.setAttribute('class', 'smush-tag');
			tag.innerText = props.computed_width + ' × ' + props.computed_height + 'px';

			const icon = document.createElement('i');
			icon.setAttribute('class', 'smush-front-icons smush-front-icon-arrows-in');
			icon.setAttribute('aria-hidden', 'true');

			const tagSuccess = document.createElement('span');
			tagSuccess.setAttribute('class', 'smush-tag smush-tag-success');
			tagSuccess.innerText = props.real_width + ' × ' + props.real_height + 'px';

			item.appendChild(count);
			item.appendChild(tag);
			item.appendChild(icon);
			item.appendChild(tagSuccess);

			return item;
		},

		/**
		 * Decide where to place the image.
		 *
		 * @param {object} props
		 */
		getContentDiv: function(props) {
			if ( props.bigger_width || props.bigger_height ) {
				this.larger++;
				this.contentDiv = document.getElementById('smush-image-bar-items-bigger');
			} else if ( props.smaller_width || props.smaller_height ) {
				this.smaller++;
				this.contentDiv = document.getElementById('smush-image-bar-items-smaller');
			}
		},

		/**
		 * Remove sections that don't have images.
		 */
		removeEmptyDivs: function() {
			if ( 0 === this.larger ) {
				const div = document.getElementById('smush-image-bar-items-bigger');
				div.hidden = true;
			}

			if ( 0 === this.smaller ) {
				const div = document.getElementById('smush-image-bar-items-smaller');
				div.hidden = true;
			}
		},

		/**
		 * Function to highlight all scaled images.
		 *
		 * Add yellow border and then show one small box to
		 * resize the images as per the required size, on fly.
		 */
		detectImages: function() {
			const images = document.getElementsByTagName('img');

			for ( let image of images ) {
				if ( this.shouldSkipImage(image) ) {
					continue;
				}

				// Get defined width and height.
				const props = {
					real_width:      image.clientWidth,
					real_height:     image.clientHeight,
					computed_width:  image.naturalWidth,
					computed_height: image.naturalHeight,
					bigger_width:  ( image.clientWidth * 1.5 ) < image.naturalWidth,
					bigger_height: ( image.clientHeight * 1.5 ) < image.naturalHeight,
					smaller_width:   image.clientWidth > image.naturalWidth,
					smaller_height:  image.clientHeight > image.naturalHeight
				};

				// In case image is in correct size, do not continue.
				if ( ! props.bigger_width && ! props.bigger_height && ! props.smaller_width && ! props.smaller_height ) {
					continue;
				}

				image.classList.add('smush-detected-img');

				this.getContentDiv(props);

				this.contentDiv.appendChild( this.createItemDiv(props) );
			}

			this.removeEmptyDivs();

		} // End detectImages()

	}; // End WP_Smush_IRS

	/**
	 * After page load, initialize toggle event.
	 */
	window.onload = WP_Smush_IRS.init();

}());