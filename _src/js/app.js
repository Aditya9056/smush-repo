/**
 * Admin modules
 */

let WP_Smush = WP_Smush || {};
window.WP_Smush = WP_Smush;

/**
 * IE polyfill for includes.
 *
 * @since 3.1.0
 */
if (!String.prototype.includes) {
    String.prototype.includes = function(search, start) {
        if (typeof start !== 'number') {
            start = 0;
        }

        if (start + search.length > this.length) {
            return false;
        } else {
            return this.indexOf(search, start) !== -1;
        }
    };
}

require( './modules/helpers' );
require( './modules/admin' );
require( './modules/bulk-smush' );
require( './modules/onboarding' );
require( './modules/directory-smush' );
require( './smush/cdn' );
require( './smush/lazy-load' );
require( './modules/bulk-restore' );

/**
 * Notice scripts.
 *
 * Notices are used in the following functions:
 *
 * @used-by \Smush\Core\Modules\Smush::smush_updated()
 * @used-by \Smush\Core\Integrations\S3::3_support_required_notice()
 * @used-by \Smush\App\Abstract_Page::installation_notice()
 *
 * TODO: should this be moved out in a separate file like common.scss?
 */
require( './modules/notice' );
