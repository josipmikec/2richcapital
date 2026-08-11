jQuery(document).ready(function ($) {

	if ( typeof mocaw_grace_notice_data === 'undefined' ) {
		return;
	}

	function normalizeAdminPageUrlForRedirectCheck(url) {
		return String(url).split('#')[0].split('?')[0].replace(/\/$/, '');
	}

	const renewalFAQUrl = mocaw_grace_notice_data.renewal_faq_url;
	const renewalFAQBtn = $('#mocaw_grace_notice_faq_btn');
	const cancelBtn = $('#mocaw_grace_notice_cancel_btn');

	renewalFAQBtn.on('click', function () {
		window.open(renewalFAQUrl, '_blank', 'noopener,noreferrer');
	});
	cancelBtn.on('click', function () {
		$.ajax({
			url: mocaw_grace_notice_data.ajax_url,
			type: 'POST',
			data: {
				action: 'mocaw_dismiss_grace_notice',
				nonce: mocaw_grace_notice_data.nonce
			},
			success: function () {
				window.location.reload();
			},
			error: function () {
				alert('Something went wrong.');
			}
		});
	});

	function fadeOutGraceNotice() {
		$('.mocaw-grace-notice-overlay').fadeOut(400);
	}

	const graceNoticeSyncLicenseBtn = $('#mocaw_grace_notice_sync_license_btn');
	const syncBtnDefaultLabel = graceNoticeSyncLicenseBtn.text();

	function restoreSyncButton(btn) {
		btn.prop('disabled', false)
			.removeClass('mocaw-grace-sync-message')
			.addClass('mocaw-plain-notice-btn')
			.text(syncBtnDefaultLabel);
	}

	graceNoticeSyncLicenseBtn.on('click', function (e) {
		e.preventDefault();
		const btn = $(this);

		btn.prop('disabled', true)
			.removeClass('mocaw-plain-notice-btn')
			.addClass('mocaw-grace-sync-message')
			.text('Syncing your license...');

		$.ajax({
			url: mocaw_grace_notice_data.ajax_url,
			type: 'POST',
			data: {
				action: 'mocaw_expiry_page_license_sync',
				nonce: mocaw_grace_notice_data.nonce
			},
			success: function (response) {
				if (response.success) {
					btn.text(response.data.message);
					setTimeout(function () {
						fadeOutGraceNotice();
					}, 1000);
				} else {
					restoreSyncButton(btn);
					alert((response && response.data && response.data.message) ? response.data.message : 'You have not renewed your license yet.');
				}
			},
			error: function () {
				restoreSyncButton(btn);
				alert('Something went wrong.');
			}
		});
	});

	const graceDeactivatePluginBtn = $('#mocaw_grace_notice_deactivate_btn');
	graceDeactivatePluginBtn.on('click', function (e) {
		e.preventDefault();
		const deactivateButton = $(this);
		const isGraceExpiredDeactivate = !!mocaw_grace_notice_data.redirect_after_grace_expired_notice;
		const pluginsPageUrl = mocaw_grace_notice_data.plugins_page_url || '';
		const shouldRedirectToPluginsPage =
			isGraceExpiredDeactivate &&
			pluginsPageUrl &&
			normalizeAdminPageUrlForRedirectCheck(window.location.href) !==
				normalizeAdminPageUrlForRedirectCheck(pluginsPageUrl);

		if ( isGraceExpiredDeactivate ) {
			const confirmMessage = shouldRedirectToPluginsPage
				? ( mocaw_grace_notice_data.confirm_redirect_message || 'Your license grace period has ended. The plugin will be deactivated and you will be taken to the Plugins page. Click OK to continue.' )
				: ( mocaw_grace_notice_data.confirm_deactivate_message || 'Your license grace period has ended. The plugin will be deactivated. Click OK to continue.' );

			if ( ! window.confirm( confirmMessage ) ) {
				return;
			}
		}

		deactivateButton.prop('disabled', true).text('Deactivating plugin...');
		$.ajax({
			url: mocaw_grace_notice_data.ajax_url,
			type: 'POST',
			data: {
				action: 'mocaw_deactivate_plugin',
				nonce: mocaw_grace_notice_data.nonce
			},
			success: function (response) {
				if (response.success) {
					if (shouldRedirectToPluginsPage) {
						window.location.assign(pluginsPageUrl);
					} else {
						alert('Plugin deactivated successfully');
						window.location.reload();
					}
				} else {
					deactivateButton.prop('disabled', false).text('Deactivate Plugin');
					const errorMessage = (response && response.data && response.data.message) ? response.data.message : 'Something went wrong.';
					alert('Error: ' + errorMessage);
				}
			},
			error: function (xhr, status, error) {
				deactivateButton.prop('disabled', false).text('Deactivate Plugin');
				alert('Error: ' + error);
			}
		});
	});
});
