/**
 * This file includes code based on parts of the Divi theme and/or the Divi Builder by Elegant Themes.
 * See the license.txt file in the WP Layouts plugin root directory for more information and licenses
 *
 */

jQuery(document).ready(function($) {

	var ags_layouts_api_url = ags_layouts_admin_config.wpAjaxUrl,
		isThemeBuilder = document.getElementById('et-theme-builder'),
		currentLayoutSlotTemplate, currentLayoutSlotType, lockSaveToLibraryCheckboxState = false,
		originalAjax;

	// Check for old backend builder
	var oldBackendBuilderMessage = wp.i18n.__('It looks like you are using the old version of the Divi backend builder, which is not compatible with WP Layouts. If you would like to use WP Layouts with Divi, please ensure you are using an up-to-date version of Divi, then enable the new Divi builder via the link below the editor or in Divi settings.', 'wp-layouts-td');
	/* This line is based on the Divi theme (see copyright and licensing details at the top of this file) - modified */ var oldBackendBuilderTabsSelector = '[data-open_tab="et-pb-ags-layouts-tab"],[data-open_tab="et-pb-ags-layouts-my-tab"]';
	$(document.body).on('click', oldBackendBuilderTabsSelector, function() {
		ags_layouts_message_dialog('', oldBackendBuilderMessage);
	});

	$(document.body).on('click', '.et-tb-layout-slot', function() {

		currentLayoutSlotTemplate = $(this).closest('.et-tb-template-list>*').index();

		switch ( $(this).index() ) {
			case 0:
				currentLayoutSlotType = 'header';
				break;
			case 1:
				currentLayoutSlotType = 'body';
				break;
			case 2:
				currentLayoutSlotType = 'footer';
				break;
		}

	});
	
	ags_layouts_onCreateElementWithClass('et-fb-right-click-menu', function(menu) {
		$(menu).children('.et-fb-right-click-menu__item--save:first').each(function() {
			var $saveMenuItem = $(this),
			$saveWplMenuItem = $saveMenuItem.clone().removeClass('et-fb-right-click-menu__item--save').addClass('et-fb-right-click-menu__item--save-wpl');
			$saveWplMenuItem
				.find('.et-fb-right-click-menu__item-title:first')
				.text( wp.i18n.__('Save to WP Layouts', 'wp-layouts-td') )
				.click(function() {
					lockSaveToLibraryCheckboxState = true;
					$(this).closest('.et-fb-right-click-menu__item--save-wpl')
							.siblings('.et-fb-right-click-menu__item--save:first')
							.find('.et-fb-right-click-menu__item-title:first')
							.click();
				});
			$saveWplMenuItem.insertAfter($saveMenuItem);
		});
	});

	ags_layouts_onCreateElementWithId('et_pb_layout_controls', function(layoutControls) {
		$('<p>')
			.addClass('ags-layouts-divi-not-supported ags-layouts-notification ags-layouts-notification-error')
			.text(oldBackendBuilderMessage)
			.insertBefore('#et_pb_layout');
	});

	ags_layouts_register_extra_task(wp.i18n.__('Saving existing content (if applicable)', 'wp-layouts-td'), function(doneCallback, importParams) {

		if ( importParams && importParams.importLocation === 'replace') {
			console.log('Skipping saving of existing content since we are replacing it.');
			doneCallback();
			return;
		}
	
		var fbApp = document.getElementById('et-fb-app');

		if ( isThemeBuilder && !fbApp ) {

			var $saveButton = $('.et-tb-admin-save-button');
			if ($saveButton.length && currentLayoutSlotType) {
				ags_layouts_onElementClassAdded($saveButton[0], 'et-tb-admin-save-button--success', function() {
					$.get(ags_layouts_api_url, {
						action: 'ags_layouts_get_tb_id',
						template: currentLayoutSlotTemplate,
						type: currentLayoutSlotType,
						nonce: ags_layouts_divi.ajaxNonce
					}, function(tbId) {

						if (tbId && tbId.success && tbId.data) {
							window.ags_layouts_admin_config.editingPostId = tbId.data;
							doneCallback();
						}

					}, 'json');
				});
				$saveButton.click();
			}

		} else if ( document.getElementById('wpbody') && !isThemeBuilder ) {

			$('#et-fb-settings-column').remove();
			var $postForm = $('#post');
			var postFormSubmitHandler = function() {
				$postForm.off('submit', postFormSubmitHandler);
				doneCallback();
				return false;
			};
			$postForm.on('submit', postFormSubmitHandler);

			$('#save-post:visible').click().length || $('#publish').click();

			setTimeout(function() {
				$('.et-fb-preloader').remove();
			}, 500);

		} else {

			var $saveButton = $('.et-fb-button--save-draft:first');

			if (!$saveButton.length) {
				$saveButton = $('.et-fb-button--publish:first');
			}

			var $saveButtonIcon = $saveButton.find('.et-fb-icon:first');


			if ($saveButtonIcon.length) {
				ags_layouts_onElementClassAdded($saveButtonIcon[0], 'et-fb-icon--check', function() {
					if (isThemeBuilder) {
						var frame = $(fbApp).find('iframe:first');
						if ( frame.length && frame[0].contentWindow && frame[0].contentWindow.ETBuilderBackendDynamic && frame[0].contentWindow.ETBuilderBackendDynamic.postId) {// all good
							window.ags_layouts_admin_config.editingPostId = frame[0].contentWindow.ETBuilderBackendDynamic.postId; // all good

							var frameSrc = frame.attr('src');
							frame
								.attr('src', 'about:blank')
								.siblings().remove();
							ags_layouts_register_extra_task(wp.i18n.__('Reloading...', 'wp-layouts-td'), function(doneCallback) {
								frame.attr('src', frameSrc);
							}, 'divi', 'import', 'after');
						}
					}

					doneCallback();
				});
				$saveButton.click();
			}
		}

	}, 'divi', 'import', 'before');



	// Visual builder
	$('body:first').on('click', '#et-fb-app .et-fb-modal-settings--library .et-fb-settings-tabs-nav-item', function() {
		var $tab = $(this);
		var $modal = $tab.closest('.et-fb-modal-settings--library');
		var $modalTabContents = $modal.find('.et-fb-library-container:first');


		var contentsCb = isThemeBuilder ? function(){} : null;

		if ($tab.hasClass('et-fb-settings-options_tab_ags-layouts')) {
			$modalTabContents.children().hide();
			var $container = $('<div>').addClass('ags-layouts-dialog-container').appendTo($modalTabContents);
			ags_layout_import_ui('divi', $container, 'layouts_ags', contentsCb);
		} else if ($tab.hasClass('et-fb-settings-options_tab_ags-layouts-my')) {
			$modalTabContents.children().hide();
			var $container = $('<div>').addClass('ags-layouts-dialog-container').appendTo($modalTabContents);
			ags_layout_import_ui('divi', $container, 'layouts_my', contentsCb);
		} else {
			$modalTabContents.children('.ags-layouts-dialog-container').remove();
			$modalTabContents.children().show();
		}
	});

	// Theme builder
	$('body:first').on('click', '.et-tb-admin-modals-portal .et-tb-divi-library-modal .et-common-tabs-navigation__button', function() {
		var $tab = $(this);
		var $modal = $tab.closest('.et-tb-divi-library-modal');
		var $modalTabContents = $modal.find('.et-common-divi-library__container:first');

		var $dataKey = $tab.attr('data-key');
		if (typeof $dataKey !== typeof undefined && $dataKey !== false) {

			if ($dataKey === 'ags-layouts') {
				$modalTabContents.children().hide();
				var $container = $('<div>').addClass('ags-layouts-dialog-container').appendTo($modalTabContents);
				ags_layout_import_ui('divi', $container, 'layouts_ags', null, 'replace');
			} else if ($dataKey === 'ags-layouts-my') {
				$modalTabContents.children().hide();
				var $container = $('<div>').addClass('ags-layouts-dialog-container').appendTo($modalTabContents);
				ags_layout_import_ui('divi', $container, 'layouts_my', null, 'replace');
			} else {
				$modalTabContents.children('.ags-layouts-dialog-container').remove();
				$modalTabContents.children().show();
			}
		}
	});

	ags_layouts_onCreateElementWithId('et-fb-settings-column', function(element) {
		var $element = $(element);
		if ($element.hasClass('et-fb-tooltip-modal--save_to_library')) {
			
			if (originalAjax) {
				$('#et-fb-app-frame, #et-bfb-app-frame')[0].contentWindow.jQuery.ajax = originalAjax;
				originalAjax = null;
			}
			
			var $nameOption = $element.find('input[type=\'text\']:first').closest('.et-fb-settings-option');
			var $saveButton = $element.find('.et-fb-save-library-button:not(.ags-layouts-export-button):first').click(function() {
				if ($saveButton.hasClass('ags-layouts-export-button')) {
					document.cookie = 'ags_layouts_divi_ready=0;path=/';
					var cookieCheckCount = 0;
					var cookieCheckInterval = setInterval(function() {
						if (document.cookie.indexOf('ags_layouts_divi_ready=1') !== -1) {
							clearInterval(cookieCheckInterval);
							document.cookie = 'ags_layouts_divi_ready=0;path=/';

							$.post(ags_layouts_api_url, {action: 'ags_layouts_get_temp_layout_contents'}, function(layoutContents) {
								if (layoutContents && layoutContents.success && layoutContents.data && layoutContents.data.name && layoutContents.data.contents) {

									if (isThemeBuilder) {
										var fbApp = document.getElementById('et-fb-app'); // latest
										var frame = $(fbApp).find('iframe:first');

										// This still needs to be fleshed out
										if ( frame.length && frame[0].contentWindow && frame[0].contentWindow.ETBuilderBackendDynamic) {// all good
											window.ags_layouts_admin_config.editingPostUrl = null; // all good 2
										}

									}

									ags_layout_export_ui('divi', layoutContents.data.contents, layoutContents.data.contents, null, null, layoutContents.data.name,
										layoutContents.data.isFullPage
											? {
												fullPageId: window.ags_layouts_admin_config.editingPostId
											}
											: null
									);
								}
							}, 'json');
						}

						++cookieCheckCount;
						if (cookieCheckCount === 40) {
							clearInterval(cookieCheckInterval);
						}
					}, 250);
				}
			});
			var $agsLayoutsOption = $nameOption.clone();
			$agsLayoutsOption.find('label:first').text('WP Layouts:');
			$('<div>')
				.addClass('et-fb-multiple-checkboxes-wrap')
				.append(
					$('<p>')
						.append(
							$('<label>')
								.text(wp.i18n.__('Save to My WP Layouts for use on other sites', 'wp-layouts-td'))
								.prepend(
									$('<input>')
										.attr({
											type: 'checkbox',
											name: 'destination_ags_layouts',
										})
										.change(function() {
											var isChecked = $(this).is(':checked');
											$element.find('[name^=\'et_fb_multiple_checkboxes\']').closest('.et-fb-settings-option').toggle(!isChecked);
											var $newCategoryNameField = $element.find('[name=\'new_category_name\']').val(isChecked ? '__AGS_LAYOUTS__' : '').focus().blur();

											if (isChecked) {
												$saveButton
													.addClass('ags-layouts-export-button')
													.data('ags-layouts-original-content', $saveButton.html())
													.text(wp.i18n.__('Upload to My WP Layouts', 'wp-layouts-td'));
													
													var $appFrame = $('#et-fb-app-frame, #et-bfb-app-frame');
													originalAjax = $appFrame[0].contentWindow.jQuery.ajax;
													$appFrame[0].contentWindow.jQuery.ajax = function() {
														
														if (arguments.length && arguments[0].data && arguments[0].data.action === 'et_fb_save_layout') {
															arguments[0].data.et_layout_new_cat = '__AGS_LAYOUTS__';
														}
														
														$appFrame[0].contentWindow.jQuery.ajax = originalAjax;
														
														return originalAjax.apply(null, arguments);
														
													};
													
											} else {
												$saveButton
													.removeClass('ags-layouts-export-button')
													.html($saveButton.data('ags-layouts-original-content'));
												
												if (originalAjax) {
													$('#et-fb-app-frame, #et-bfb-app-frame')[0].contentWindow.jQuery.ajax = originalAjax;
													originalAjax = null;
												}
											}

											$newCategoryNameField.closest('.et-fb-settings-option').toggle(!isChecked);
													
											if (!$newCategoryNameField.length) {
												$element.find('.et-fb-settings-option:gt(1)').toggle(!isChecked);
											}

										})
								)
						)

				)
				.replaceAll($agsLayoutsOption.find('input:first'));

			$agsLayoutsOption.insertAfter($nameOption);
			
			if (lockSaveToLibraryCheckboxState) {
				lockSaveToLibraryCheckboxState = false;
				$agsLayoutsOption.find('input:first').attr('checked', true).change();
				$agsLayoutsOption.remove();
				
				var $dialogTitle = $element.find('.et-fb-settings-heading:first');
				$dialogTitleChildren = $dialogTitle.children();
				$dialogTitle.text(wp.i18n.__('Add to WP Layouts', 'wp-layouts-td')).append($dialogTitleChildren);
				
				$element.find('.et-fb-settings-options > .et-fb-description-text').remove();
			}
		}
	});


}); // end document ready