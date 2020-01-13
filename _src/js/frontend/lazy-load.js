window.lazySizesConfig = window.lazySizesConfig || {};

window.lazySizesConfig.lazyClass = 'lazyload';
window.lazySizesConfig.loadingClass = 'lazyloading';
window.lazySizesConfig.loadedClass = 'lazyloaded';

window.lazySizesConfig.loadMode = 1;

import lazySizes from 'lazysizes';
import 'lazysizes/plugins/native-loading/ls.native-loading';

lazySizes.cfg.nativeLoading = {
	setLoadingAttribute: true,
	disableListeners: {
		scroll: true,
	},
};

lazySizes.init();
