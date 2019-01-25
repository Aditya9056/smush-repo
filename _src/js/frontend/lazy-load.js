// Polyfill for intersection observer (@see https://caniuse.com/#search=IntersectionObserver)
require('intersection-observer');

import lozad from 'lozad';

/**
 * Lazy loading functionality.
 *
 * @since 3.2.0
 */
( function() {
    'use strict';

    const WP_Smush_LazyLoad = {
        init: () => {
            const observer = lozad('.lazy-load', {
                threshold: 0.1,
                load: function(el) {
                    const img = el.getAttribute('data-src');
                    el.src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

                    el.onload = function() {
                        el.src = img;
                        console.log( 'finished' );
                        //el.classList.remove('lazy-hidden');
                    };
                }
            });

            observer.observe();
        }
    };

    WP_Smush_LazyLoad.init();
}());
