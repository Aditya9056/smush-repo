(function ($) {

	var SmushitPro = function (element, options) {
		var elem = $(element);

		var defaults = {
			isSingle: false,
			ajaxurl: '',
			msgs: {},
			counts: {},
			spinner: $('.checking-status #wp-smpro-spinner-wrap .floatingCirclesG'),
			msgClass: 'wp-smpro-msg',
			ids: [],
			sendButton: '#wp-smpro-send',
			fetchButton: '#wp-smpro-fetch',
			cancelButton: '#wp-smpro-cancel',
			resetButton: '#wp-smpro-reset-bulk',
			sendProgressBar: '#wp-smpro-sent-progress',
			fetchProgressBar: '#wp-smpro-fetched-progress',
			statusWrap: '#wp-smpro-progress-status',
			statsWrap: '#wp-smpro-compression'
		};

		var fetchCount = 0;

		var sentCount = 0;

		var process_next = true;

		// merge the passed options object with defaults
		var config = $.extend(defaults, options || {});

		var init = function () {

			// url for sending
			config.send_url = config.ajaxurl + '?action=wp_smpro_send';

			// url for fetching
			config.fetch_url = config.ajaxurl + '?action=wp_smpro_fetch';

			config.hide_notice_url = config.ajaxurl + '?action=wp_smpro_hide';

			config.resetUrl = config.ajaxurl + '?action=wp_smpro_reset_bulk_request';

		};

		var msg = function (msgvar) {
			if (config.isSingle) {
				singleMsg(msgvar);
				return;
			}

			if (elem.find(config.msgClass + '.' + msgvar.msg).length > 0) {
				return;
			}
			var $msg = $('<div id="message" class="' + config.msgClass + '"></div>');
			if (!msgvar.err) {
				$msg.addClass('updated');
			} else {
				$msg.addClass('smush-notices');
			}
			$msg.addClass(msgvar.msg);
			$msg.append(jQuery('<p></p>'));
			if (!msgvar.str) {
				msgvar.str = config.msgs[msgvar.msg];
			}
			$msg.find('p').append(msgvar.str);
			$msg.css('display', 'none');
			elem.find(config.statusWrap).after($msg);
			$msg.slideToggle();

			// find the smush button
			$button = elem.find(config.sendButton);

			// find the spinner ui
			$spinner = $button.find('.floatingCirclesG');

			// remove the spinner
			$spinner.remove();

			// add the progress text
			$button.find('span').html(config.msgs.sent);

		};
		var checkSmushStatus = function () {
			jQuery('.smush-api-status').remove();
			jQuery('.smush-notices.checking-status').show();
			jQuery.ajax({
				type: "GET",
				url: config.smush_status
			}).done(function (response) {
				jQuery('.smush-notices.checking-status').hide();
				if (!response.success) {
					var callback = true;
					if (typeof response.data.check_status != 'undefined') {
						if (!response.data.check_status) {
							callback = false;
						}
					}
					//Call itself after every fix interval
					if (callback) {
						setTimeout(function () {
							checkSmushStatus();
						}, config.wp_smpro_poll_interval.interval);
					}
					if (typeof response.data !== 'undefined') {
						var div = '<div class="smush-notices update smush-api-status"><p>' + response.data.message + '</p></div>';
						jQuery('#progress-ui').after(div);
					}
					delete callback;
					//}
				} else {
					wp_smpro_sent_ids = response.data;

					config.ids = wp_smpro_sent_ids;

					//Enable the fetch button

					// find the div that displays status message
					$status_div = elem.find('.smush-status');

					// find the smush button
					$button = elem.find(config.sendButton);

					if ($button.length === 0) {
						$button = elem.find(jQuery('#wp-smpro-waiting'));
					}

					// empty the current text
					$button.find('span').html(config.msgs.fetch);

					// add new class for css adjustment
					$button.removeClass('wp-smpro-started');

					// add new class for css adjustment
					$button.attr('id', 'wp-smpro-fetch');

					// re-enable all the buttons
					$button.prop('disabled', false);

					//Sweet Alert
					swal({
						title: '',
						text: config.msgs.smush_completed,
						type: "success",
						confirmButtonColor: "#2ea2cc"
					});

				}
				return;
			}).fail(function () {
				jQuery('.smush-notices.checking-status').hide();
			});
		}

		var singleMsg = function (msgvar) {

			// find the div that displays status message
			$status_div = elem.find('.smush-status');

			if (!msgvar.str) {
				msgvar.str = config.msgs[msgvar.msg];
			}

			// replace the older message
			$status_div.html(msgvar.str);
			// find the smush button
			$button = elem.find(config.sendButton);

			if (msgvar.err) {

				$status_div.addClass('fail');

				// find the spinner ui
				$spinner = $button.find('.floatingCirclesG');

				// remove the spinner
				$spinner.remove();

				// empty the current text
				$button.find('span').html(config.msgs.smush_now);

				// add new class for css adjustment
				$button.removeClass('wp-smpro-started');

				// re-enable all the buttons
				$button.prop('disabled', false);
				//For grid view
				jQuery('.smush-wrap').removeClass('unsmushed');

			} else {
				$status_div.addClass('success');
				$button.remove();
				//For grid view
				jQuery('.smush-wrap').removeClass('unsmushed');
			}
			// done!
			return;
		};

		var send = function ($id) {
			jQuery('.wp-smpro-msg.updated.update').remove();
			var $data = {};
			var is_bulk = true;
			if ($id !== false) {
				is_bulk = false;
				$data = {'attachment_id': $id};
				jQuery('.column-smushit #wp-smpro-send').attr('disabled', 'disabled');
			}

			return $.ajax({
				type: "GET",
				url: config.send_url,
				data: $data,
				timeout: 90000,
				dataType: 'json'
			}).done(function (response) {
				jQuery('.column-smushit #wp-smpro-send').removeAttr('disabled');
				if (typeof response.error !== 'undefined') {
					sendFailure(response, is_bulk);
				} else {
					sendSuccess(response);
					if (!config.isSingle) {
						//sweet alert
						swal({
							title: '',
							text: config.msgs.smush_email,
							type: "success",
							confirmButtonColor: "#2ea2cc"
						});
					}
					if (!$id) {
						setTimeout(function () {
							checkSmushStatus();
						}, config.wp_smpro_poll_interval.interval);
					}
				}
				return;
			}).fail(function (jqXHR, textStatus, errorThrown) {
				jQuery('.column-smushit #wp-smpro-send').removeAttr('disabled');
				response = {};
				response.error = config.msgs.timeout;
				sendFailure(response, is_bulk);
				return;
			});

		};

		if (typeof config.wp_smpro_request_sent !== 'undefined' && config.wp_smpro_request_sent.sent) {
			//Only for bulk
			if (elem.attr('class') === 'wp-smpro-bulk-wrap' && elem.attr('id') === 'all-bulk') {
				checkSmushStatus();
			}
		}

		var sendSuccess = function ($response) {
			if (!config.isSingle) {
				sendProgress($response.success.sent_count);
			}
			$msg = $response.debug ? $response.success.status_message + '<br />' + $response.debug : $response.success.status_message;
			var msgvar = {
				'msg': 'update',
				'str': $msg,
				'err': false
			};
			msg(msgvar);
			return;

		};

		var sendFailure = function ($response, is_bulk) {

			if ($.isEmptyObject($response)) {
				$response = {'status_message': config.msgs.send_fail};
			}

			$msg = $response.debug ? $response.error + '<br />' + $response.debug : $response.error;

			var msgvar = {
				'msg': 'fail',
				'str': $msg,
				'err': true
			};

			msg(msgvar);
			var button = jQuery(config.sendButton);
			if (is_bulk) {
				button.find('span').html(config.msgs.bulk_smush_now);
			} else {
				button.find('span').html(config.msgs.smush_now);
			}
			jQuery(config.sendButton).removeAttr('disabled');
			return;
		};

		var sendProgress = function (sentCount) {

			$percent = (sentCount / parseInt(config.counts.total)) * 100;

			elem.find(config.sendProgressBar + ' div').css('width', $percent + '%');
			elem.find(config.statusWrap + ' p#sent-status .done-count').html(sentCount);
			elem.find(config.statusWrap + ' p#fetched-status .sent-count').html(sentCount);
			config.counts.sent = sentCount;

//                        if(config.counts.sent === fetchCount){
//                                msg(config.msgs.sent_done, false, false);
//                                //wp_smpro_all_done();
//                        }
		};

		var compression = function ($stats) {
			config.counts.size_before = parseInt(config.counts.size_before);
			config.counts.size_after = parseInt(config.counts.size_after);
			config.counts.percent = parseInt(config.counts.percent);

			config.counts.size_before += parseInt($stats.size_before);
			config.counts.size_after += parseInt($stats.size_after);

			$bytes = config.counts.size_before - config.counts.size_after;
			config.counts.human = formatBytes($bytes);
			config.counts.percent = ( ($bytes / config.counts.size_before) * 100 ).toFixed(2);
			return config.counts;
		};

		var fetchProgress = function ($stats) {
			fetchCount++;

			$percent = (fetchCount / parseInt(config.counts.total)) * 100;

			elem.find(config.fetchProgressBar + ' div').css('width', $percent + '%');
			elem.find(config.statusWrap + ' p#fetched-status .done-count').html(fetchCount);
			config.counts.smushed = fetchCount;

			$count_percent = parseFloat(config.counts.percent).toFixed(2);

			jQuery(config.statsWrap).find('#percent').html($count_percent);
			jQuery(config.statsWrap).find('#human').html(config.counts.human);

			if (config.counts.total == fetchCount) {
				jQuery(window).off('beforeunload');
				$button = jQuery(config.fetchButton);

				// find the spinner ui
				$spinner = $button.find('.floatingCirclesG');
				$spinner.remove();

				$button.find('span').html(config.msgs.done);
				$button.attr('id', 'wp-smpro-finished');
				$button.removeClass('wp-smpro-started');
				jQuery(config.cancelButton).remove();
			} else if (config.counts.sent == fetchCount) {
				jQuery(window).off('beforeunload');
				$button = jQuery(config.fetchButton);

				// find the spinner ui
				$spinner = $button.find('.floatingCirclesG');
				$spinner.remove();
				$button.find('span').html(config.msgs.bulk_smush_now);
				$button.attr('id', 'wp-smpro-send');
				$button.removeClass('wp-smpro-started');
				$button.removeAttr('disabled');
				jQuery(config.cancelButton).remove();
			}
		};

		var fetch = function ($id) {
			var is_bulk = false;
			if (!$id) {
				is_bulk = true;
			}

			jQuery('.wp-smpro-msg.smush-notices.fail').remove();
			return jQuery.ajax({
				type: "GET",
				data: {attachment_id: $id},
				url: config.fetch_url,
				timeout: 90000
			}).done(function (response) {
				response = jQuery.parseJSON(response);
				var $is_fetched = response.success;
				// file was successfully fetched
				if ($is_fetched && response.stats.bytes !== null) {
					fetchProgress(response.stats);

					//If element exists
					if (jQuery('#wp-smpro-selected-images').length) {
						$selected_element = jQuery('#wp-smpro-selected-images #wp-smpro-img-' + $id);
						$selected_element.addClass('smush-done');
						$selected_element.find('.img-smush-status').html(response.smush_text);
					}
				} else {
					msg({
						'msg': 'fail',
						'str': response.msg,
						'err': true
					});
					return;
				}
			}).fail(function (jqXHR, textStatus, errorThrown) {
				response = {};
				console.log(errorThrown);
				response.error = config.msgs.timeout;
				sendFailure(response, is_bulk);
				return;
			});
		};

		var buttonProgress = function ($button, $text) {

			// copy the spinner into an object
			$spinner = config.spinner.clone();

			// empty the current text
			$button.find('span').html('');

			// add new class for css adjustment
			$button.addClass('wp-smpro-started');

			// prepend the spinner html
			$button.prepend($spinner);

			// add the progress text
			$button.find('span').html($text);

			// disable the button
			$button.prop('disabled', true);

			// done
			return;
		};

		var bulkFetch = function () {

			fetchCount = config.counts.smushed;

			var startingpoint = jQuery.Deferred();
			startingpoint.resolve();
			if (config.ids.length === 0 || config.ids === '') {
				return false;
			}
			//Fetch ids which have been smushed and recieved
			$.each(config.ids, function ($request_id, $ids) {
				$.each($ids.sent_ids, function (i, $id) {
					startingpoint = startingpoint.then(function () {
						return fetch($id);
					});
				});
				//Remove notice after fetching is done
				jQuery('.updated.bulk-smush-notice').remove();
			});
			jQuery.when(startingpoint).done(function () {
				bulkReset();
			});
		};

		var bulkStart = function ($button) {
			buttonProgress($button, config.msgs.fetching);

			//Enable the Cancel button
			jQuery(config.cancelButton).removeClass('disabled');

			bulkFetch();

			//Before leave screen, show alert
			jQuery(window).on('beforeunload', function (e) {
				var message = wp_smpro_msgs.no_leave.replace(/(<([^>]+)>)/ig, "");
				(e || window.event).returnValue = message;
				return message;
			});
		};

		var bulkCancel = function () {
			$(window).off('beforeunload');

			$button = elem.find(config.fetchButton);

			// copy the spinner into an object
			$spinner = $button.find('.floatingCirclesG');

			// remove the spinner
			$spinner.remove();

			// empty the current text
			$button.find('span').html('');

			$button.removeClass('wp-smpro-started');

			$button.prop('disabled', false);

			$button.find('span').html(wp_smpro_msgs.fetch);

		};

		var bulkReset = function () {
			$(window).off('beforeunload');

			$button = elem.find(config.fetchButton);

			// copy the spinner into an object
			$spinner = $button.find('.floatingCirclesG');

			// remove the spinner
			$spinner.remove();

			// empty the current text
			$button.find('span').html('');

			$button.removeClass('wp-smpro-started');

			$button.prop('disabled', false);

			$button.find('span').html(wp_smpro_msgs.bulk_smush_now);

		};

		var numberFormat = function (number, decimals, dec_point, thousands_sep) {
			number = (number + '')
				.replace(/[^0-9+\-Ee.]/g, '');
			var n = !isFinite(+number) ? 0 : +number,
				prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
				sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
				dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
				s = '',
				toFixedFix = function (n, prec) {
					var k = Math.pow(10, prec);
					return '' + (Math.round(n * k) / k)
							.toFixed(prec);
				};
			// Fix for IE parseFloat(0.55).toFixed(0) = 0;
			s = (prec ? toFixedFix(n, prec) : '' + Math.round(n))
				.split('.');
			if (s[0].length > 3) {
				s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
			}
			if ((s[1] || '')
					.length < prec) {
				s[1] = s[1] || '';
				s[1] += new Array(prec - s[1].length + 1)
					.join('0');
			}
			return s.join(dec);
		};

		var numberFormatI18n = function ($number, $decimals) {
			$formatted = numberFormat(
				$number,
				Math.abs(parseInt($decimals)),
				wp_smpro_locale.decimal,
				wp_smpro_locale.thousands_sep
			);
			return $formatted;

		};

		var formatBytes = function ($bytes, $precision) {
			if (!$precision) {
				$precision = 2;
			}
			$units = ['B', 'KB', 'MB', 'GB', 'TB'];
			$bytes = Math.max($bytes, 0);
			$pow = Math.floor(( $bytes ? Math.log($bytes) : 0 ) / Math.log(1024));
			$pow = Math.min($pow, $units.length - 1);
			$bytes /= Math.pow(1024, $pow);

			$size = numberFormatI18n(Math.round($bytes, $precision), $precision);
			$unit = $units[$pow];

			return $size + ' ' + $unit;
		};

		var resetBulkRequest = function () {

			jQuery('.smush-notices').remove();
			return jQuery.ajax({
				type: "POST",
				url: config.resetUrl,
				data: {'nonce': jQuery('#wp-smpro-reset-nonce').val()},
				timeout: 90000
			}).done(function (response) {
				var button = jQuery('#wp-smpro-waiting');
				if (typeof response !== 'undefined' && response.success) {

					//Remove the reset button
					jQuery(config.resetButton).remove();

					//Add a message
					var div = '<div class="smush-notices update bulk-reset"><p>' + response.data + '</p></div>';
					swal({
						title: wp_smpro_msgs.done,
						text: wp_smpro_msgs.reset_success,
						type: "success",
						confirmButtonColor: "#2ea2cc"
					});
					jQuery('#progress-ui').after(div);

					//disable the check status
					wp_smpro_request_sent = {"sent": ""};

					//Enable the send bulk request button
					button.removeClass('wp-smpro-started');

					button.prop('disabled', false);

					button.find('span').html(wp_smpro_msgs.bulk_smush_now);
					button.attr('id', 'wp-smpro-send');
					//reload page, after 5s
					function countdown() {
						var i = jQuery('body span#counter');
						if (parseInt(i.html()) < 1) {
							location.reload();
						} else {
							i.html(parseInt(i.html()) - 1);
						}
					};
					setInterval(function () {
						countdown();
					}, 1000);

				} else {
					//there was an error resetting, just show a error message and ask for page reload
					//Add a message
					var div = '<div class="smush-notices update bulk-reset"><p>' + response.data + '</p></div>';
					jQuery('#progress-ui').after(div);
				}
			});
		};
		var sendRequest = function (thisObject) {
			buttonProgress( thisObject, config.msgs.sending);

			//Remove Selected image div if there
			jQuery('#select-bulk').remove();

			if (!config.isSingle) {
				sentCount = config.counts.sent;
				send(false);
			} else {
				// get the row
				var $nearest_tr = thisObject.closest('tr').first();

				if ($nearest_tr.length !== 0) {
					// get the row's DOM id
					var $elem_id = $nearest_tr.attr('id');
					// get the attachment id from DOM id
					var $id = $elem_id.replace(/[^0-9\.]+/g, '');
				} else {
					var $id = thisObject.parents().eq(5).data('id');
				}

				send($id);
			}
		};

		init();

		//Handle button clicks and call respective functions
		elem.on('click', config.sendButton, function (e) {
			// prevent the default action
			e.preventDefault();
			var thisObj = jQuery(this);
			//remove all smush notices
			jQuery('.smush-notices').remove();
			//Check if unsmushed image count is lesser than 20, suggest to send manual smush requests
			if (( wp_smpro_counts.total - wp_smpro_counts.smushed ) <= 30) {
				swal({
						title: wp_smpro_msgs.prompt_manual,
						type: "warning",
						showCancelButton: true,
						confirmButtonColor: "#DD6B55",
						confirmButtonText: wp_smpro_msgs.continue,
						closeOnConfirm: true

					}, function () {
						sendRequest(thisObj);
					}
				);
			} else {
				sendRequest(thisObj);
			}


			return;

		}).on('click', config.fetchButton, function (e) {
			// prevent the default action
			e.preventDefault();
			$this = $(this);
			var count = 0;
			if ($('#fetch-notice').length > 0) {
				$('#fetch-notice').slideToggle();
			} else {
				bulkStart($this);
			}
			return;

		}).on('click', '.accept-slow-notice', function (e) {

			bulkStart($this);

		}).on('click', '#fetch-notice button.button', function (e) {

			e.preventDefault();

			if ($(this).hasClass('button-secondary')) {
				jQuery.ajax({
					type: "GET",
					data: {hide: true},
					url: config.hide_notice_url,
					timeout: 60000
				});
			}

			$('#fetch-notice').slideToggle(function () {
				$('#fetch-notice').remove();
			});

		}).on('click', config.cancelButton, function (e) {
			// prevent the default action
			e.preventDefault();

			config.process_next = false;

			bulkCancel();

			return;

		}).on('click', config.resetButton, function (e) {
			e.preventDefault();
			//Confirm for reset request
			swal({
				title: wp_smpro_msgs.confirm,
				type: "warning",
				showCancelButton: true,
				confirmButtonColor: "#DD6B55",
				confirmButtonText: wp_smpro_msgs.confirm_button,
				closeOnConfirm: true

			}, function () {
				resetBulkRequest();
			});
			return;
		});
	};

	$.fn.smushitpro = function (options) {
		return this.each(function () {
			var element = $(this);

			// Return early if this element already has a plugin instance
			if (element.data('smushitpro'))
				return;

			// pass options to plugin constructor and create a new instance
			var smushitpro = new SmushitPro(this, options);

			// Store plugin object in this element's data
			element.data('smushitpro', smushitpro);
		});
	};
	if (typeof wp !== 'undefined') {
		_.extend(wp.media.view.AttachmentCompat.prototype, {
			render: function () {
				$view = this;
				//Dirty hack, as there is no way around to get updated status of attachment
				$html = jQuery.get(ajaxurl + '?action=attachment_status', {'id': this.model.get('id')}, function (res) {
					$view.$el.html(res.data);
					$view.views.render();
					return $view;
				});
			}
		});
	}

})(jQuery, _);
jQuery(document).ready(function () {
	jQuery('body').on('click', '.attachment-info #wp-smpro-send', function () {
		/**
		 * Handle the media library button click
		 */
		jQuery('.attachment-info .attachment-compat').smushitpro({
			'msgs': wp_smpro_msgs,
			'counts': wp_smpro_counts,
			'ajaxurl': ajaxurl,
			'isSingle': true
		});
		jQuery('.attachment-info .attachment-compat #wp-smpro-send').click();
	});
});