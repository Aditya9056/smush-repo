// Polyfill for intersection observer.
require('intersection-observer');

import lozad from 'lozad';

/**
 * Lazy loading functionality.
 *
 * @since 3.2
 */
( function() {
    'use strict';

    const WP_Smush_LazyLoad = {
        init: () => {
            const observer = lozad('.lazy-load', {
                threshold: 0.1,
                load: function(el) {
                    console.log('loading element');
                },
                loaded: function(el) {
                    el.classList.add('loaded');
                    console.log('loaded');
                }
            });

            observer.observe();
        }
    };

    WP_Smush_LazyLoad.init();
}());
