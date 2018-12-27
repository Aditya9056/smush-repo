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
                threshold: 0.1
            });

            observer.observe();
        }
    };

    WP_Smush_LazyLoad.init();
}());
