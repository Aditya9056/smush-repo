/**
 * Directory scanner module that will Smush images in the Directory Smush modal.
 *
 * @since 2.8.1
 *
 * @param totalSteps
 * @param currentStep
 * @returns {{scan: scan, cancel: (function(): (*|$.promise|{})), getProgress: getProgress, onFinishStep: onFinishStep, onFinish: onFinish}}
 * @constructor
 */

const DirectoryScanner = ( totalSteps, currentStep ) => {
	totalSteps = parseInt( totalSteps );
	currentStep = parseInt( currentStep );
	let cancelling = false;

	let obj = {
		scan: function() {
			let remainingSteps = totalSteps - currentStep;
			if ( currentStep !== 0 ) {
				// Scan started on a previous page load.
				step( remainingSteps );
			} else {
				$.post( ajaxurl, { action: 'directory_smush_start' },
					() => step( remainingSteps ) );
			}
		},

		cancel: function() {
			cancelling = true;
			return $.post( ajaxurl, { action: 'directory_smush_cancel' } );
		},

		getProgress: function() {
			if ( cancelling ) {
				return 0;
			}
			const remainingSteps = totalSteps - currentStep;
			return Math.min( Math.round( ( parseInt( ( totalSteps - remainingSteps ) ) * 100 ) / totalSteps ), 99 );
		},

		onFinishStep: function( progress ) {
			$( '.wp-smush-progress-dialog .sui-progress-state-text' ).html( currentStep + '/' + totalSteps + ' ' + wp_smush_msgs.progress_smushed );
			WP_Smush.directory.updateProgressBar( progress );
		},

		onFinish: function() {
			WP_Smush.directory.updateProgressBar( 100 );
			window.location.href='admin.php?page=smush&tab=directory';
		},
	};

	/**
	 * Execute a scan step recursively
	 *
	 * Private to avoid overriding
	 *
	 * @param remainingSteps
	 */
	const step = function( remainingSteps ) {
		if ( remainingSteps >= 0 ) {
			currentStep = totalSteps - remainingSteps;
			$.post( ajaxurl, {
				action: 'directory_smush_check_step',
				step: currentStep
			}, () => {
				currentStep++;
				remainingSteps = remainingSteps - 1;
				obj.onFinishStep( obj.getProgress() );
				step( remainingSteps );
			} );
		} else {
			$.post( ajaxurl, { action: 'directory_smush_finish' },
				( response ) => obj.onFinish( response ) );
		}
	};

	return obj;
};

export default DirectoryScanner;