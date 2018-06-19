/**
 * jQueryFileTree plugin
 */
import 'jqueryfiletree/src/jQueryFileTree.js';

/**
 * Admin modules
 */
//require('./modules/admin');
require('./modules/bulk-smush');

/**
 * Notice scripts.
 *
 * Notices are used in the following functions:
 *
 * @used-by WpSmushitAdmin::smush_updated()
 * @used-by WpSmushS3::3_support_required_notice()
 * @used-by WpSmushBulkUi::installation_notice()
 */
require('./modules/notice');