/* global jQuery, wpeg */
(function ($) {
	'use strict';

	// --- Settings Page ---

	$('#wpeg-settings-form').on('submit', function (e) {
		e.preventDefault();

		var data = {
			action: 'wpeg_save_settings',
			nonce: wpeg.nonce,
			default_rule: $('input[name="default_rule"]:checked').val(),
			jwt_expiry: $('#wpeg-jwt-expiry').val()
		};

		if ($('input[name="lockdown"]').is(':checked')) {
			data.lockdown = '1';
		}
		if ($('input[name="hide_index"]').is(':checked')) {
			data.hide_index = '1';
		}
		if ($('input[name="basic_auth"]').is(':checked')) {
			data.basic_auth = '1';
		}

		$.post(wpeg.ajax_url, data, function (response) {
			var $status = $('#wpeg-settings-status');
			if (response.success) {
				$status.text('Saved!').removeClass('error').addClass('success');
			} else {
				$status.text('Error saving.').removeClass('success').addClass('error');
			}
			setTimeout(function () { $status.text(''); }, 3000);
		});
	});

	// Regenerate JWT Secret
	$('#wpeg-regenerate-secret').on('click', function () {
		if (!confirm(wpeg.i18n.confirm_regenerate)) {
			return;
		}

		$.post(wpeg.ajax_url, {
			action: 'wpeg_regenerate_jwt_secret',
			nonce: wpeg.nonce
		}, function (response) {
			if (response.success) {
				$('#wpeg-jwt-secret').val(response.data.secret);
			}
		});
	});

	// --- API Keys Page ---

	$('#wpeg-generate-key').on('click', function () {
		var name = $('#wpeg-new-key-name').val().trim();
		if (!name) {
			$('#wpeg-new-key-name').focus();
			return;
		}

		var $btn = $(this).prop('disabled', true);

		$.post(wpeg.ajax_url, {
			action: 'wpeg_generate_key',
			nonce: wpeg.nonce,
			name: name
		}, function (response) {
			$btn.prop('disabled', false);

			if (response.success) {
				$('#wpeg-raw-key').text(response.data.raw_key);
				$('#wpeg-key-modal').show();
				$('#wpeg-new-key-name').val('');
			} else {
				alert(response.data);
			}
		});
	});

	// Copy key to clipboard
	$('#wpeg-copy-key').on('click', function () {
		var key = $('#wpeg-raw-key').text();
		navigator.clipboard.writeText(key).then(function () {
			alert(wpeg.i18n.key_copied);
		}).catch(function () {
			alert(wpeg.i18n.copy_failed);
		});
	});

	// Close modal and reload to show the new key in the table
	$('#wpeg-close-modal').on('click', function () {
		$('#wpeg-key-modal').hide();
		window.location.reload();
	});

	// Revoke key
	$(document).on('click', '.wpeg-revoke-key', function () {
		if (!confirm(wpeg.i18n.confirm_revoke)) {
			return;
		}

		var $btn = $(this);
		var keyId = $btn.data('key-id');

		$.post(wpeg.ajax_url, {
			action: 'wpeg_revoke_key',
			nonce: wpeg.nonce,
			key_id: keyId
		}, function (response) {
			if (response.success) {
				window.location.reload();
			} else {
				alert(response.data);
			}
		});
	});

	// --- Endpoints Page ---

	// Accordion toggle
	$(document).on('click', '.wpeg-accordion-toggle', function () {
		var $accordion = $(this).closest('.wpeg-accordion');
		var $body = $accordion.find('.wpeg-accordion-body');

		$accordion.toggleClass('is-open');
		$body.slideToggle(200);
	});

	// Namespace nav pill click — scroll and open
	$('.wpeg-nav-pill').on('click', function (e) {
		e.preventDefault();
		var targetId = $(this).attr('href');
		var $target = $(targetId);

		if (!$target.hasClass('is-open')) {
			$target.addClass('is-open');
			$target.find('.wpeg-accordion-body').slideDown(200);
		}

		$('html, body').animate({ scrollTop: $target.offset().top - 40 }, 300);
	});

	// Expand / Collapse all
	$('#wpeg-expand-all').on('click', function () {
		$('.wpeg-accordion:not(.wpeg-hidden)').addClass('is-open').find('.wpeg-accordion-body').slideDown(200);
	});

	$('#wpeg-collapse-all').on('click', function () {
		$('.wpeg-accordion').removeClass('is-open').find('.wpeg-accordion-body').slideUp(200);
	});

	// Rule dropdown change — AJAX save
	$(document).on('change', '.wpeg-rule-select', function () {
		var $select = $(this);
		var ruleId = $select.data('rule-id');
		var rule = $select.val();

		$.post(wpeg.ajax_url, {
			action: 'wpeg_update_rule',
			nonce: wpeg.nonce,
			rule_id: ruleId,
			rule: rule
		}, function (response) {
			if (response.success) {
				$select.closest('tr').addClass('wpeg-saved');
				$select.closest('tr').attr('data-rule', rule);
				setTimeout(function () {
					$select.closest('tr').removeClass('wpeg-saved');
				}, 600);
			}
		});
	});

	// Refresh routes
	$('#wpeg-refresh-routes').on('click', function () {
		var $btn = $(this).prop('disabled', true).text('Refreshing...');

		$.post(wpeg.ajax_url, {
			action: 'wpeg_refresh_routes',
			nonce: wpeg.nonce
		}, function (response) {
			if (response.success) {
				window.location.reload();
			} else {
				$btn.prop('disabled', false).text('Refresh Routes');
			}
		});
	});

	// Filters — search and rule filter
	$('#wpeg-filter-search').on('input', applyFilters);
	$('#wpeg-filter-rule').on('change', applyFilters);

	function applyFilters() {
		var search = $('#wpeg-filter-search').val().toLowerCase();
		var rule = $('#wpeg-filter-rule').val();

		$('.wpeg-accordion').each(function () {
			var $accordion = $(this);
			var visibleRows = 0;

			$accordion.find('tbody tr').each(function () {
				var $row = $(this);
				var route = ($row.attr('data-route') || '').toLowerCase();
				var rowRule = $row.attr('data-rule') || '';

				var show = true;
				if (search && route.indexOf(search) === -1) show = false;
				if (rule && rowRule !== rule) show = false;

				$row.toggle(show);
				if (show) visibleRows++;
			});

			// Hide entire accordion section if no rows match
			if (visibleRows === 0 && (search || rule)) {
				$accordion.addClass('wpeg-hidden');
			} else {
				$accordion.removeClass('wpeg-hidden');
			}

			// Auto-open sections with matching results when searching
			if (search && visibleRows > 0 && !$accordion.hasClass('is-open')) {
				$accordion.addClass('is-open');
				$accordion.find('.wpeg-accordion-body').show();
			}
		});

		// Also hide/show nav pills
		$('.wpeg-nav-pill').each(function () {
			var href = $(this).attr('href');
			var $target = $(href);
			$(this).toggle(!$target.hasClass('wpeg-hidden'));
		});
	}

	// Select all visible checkboxes
	$('#wpeg-select-all').on('change', function () {
		var checked = $(this).is(':checked');
		$('.wpeg-accordion:not(.wpeg-hidden) tbody tr:visible .wpeg-endpoint-check').prop('checked', checked);
	});

	// Per-namespace select all
	$(document).on('change', '.wpeg-select-all-ns', function () {
		var checked = $(this).is(':checked');
		$(this).closest('.wpeg-accordion').find('tbody tr:visible .wpeg-endpoint-check').prop('checked', checked);
	});

	// Bulk apply
	$('#wpeg-apply-bulk').on('click', function () {
		var rule = $('#wpeg-bulk-rule').val();
		if (!rule) return;

		var ids = [];
		$('.wpeg-endpoint-check:checked').each(function () {
			ids.push($(this).val());
		});

		if (!ids.length) return;

		$.post(wpeg.ajax_url, {
			action: 'wpeg_bulk_update_rules',
			nonce: wpeg.nonce,
			ids: ids,
			rule: rule
		}, function (response) {
			if (response.success) {
				window.location.reload();
			}
		});
	});

})(jQuery);
