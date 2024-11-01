/*
AGS-Dialog by Aspen Grove Studios 
100% original, no based on or copied code
*/

var agsLayoutsDialog = {
	prefix: 'ags-layouts-',
	currentLayer: 0,
	create: function(params) {
		var $ = jQuery;
		var content = params.content ? params.content : '';
		var $dialog = $('<div>')
			.addClass(this.prefix + 'dialog');
		var dialogPageName = params.pageName;
		if (dialogPageName) {
			$dialog.addClass(this.prefix + 'dialog-page-' + dialogPageName);
		}
		if (params.dialogClass) {
			$dialog.addClass(params.dialogClass);
		}
		var $dialogHeader = $('<div>')
			.addClass(this.prefix + 'dialog-header')
			.appendTo($dialog);
		var $dialogBody = $('<div>')
			.addClass(this.prefix + 'dialog-body')
			.appendTo($dialog);
		var dialog = this, dialogPrefix = this.prefix;
		if (!params.container) {
			var $overlay = $('<div>')
				.addClass(dialogPrefix + 'dialog-overlay');
			++this.currentLayer;
			$dialog.attr('id', dialogPrefix + 'dialog-' + this.currentLayer);
			var closeFunction = function() {
				params.onClose && params.onClose();
				--dialog.currentLayer;
				$overlay.remove();
			};
			$('<button>')
				.attr('type', 'button')
				.addClass(this.prefix + 'dialog-close')
				.text('x')
				.click(closeFunction)
				.appendTo($dialogHeader);

			if (params.title) {
				$('<h2>').html(params.title).appendTo($dialogHeader);
			}
		}

		var showLoaderFunction = function() {
			if (!$dialog.find('.' + dialogPrefix + 'dialog-loader-overlay').length) {
				$('<div>')
					.addClass(dialogPrefix + 'dialog-loader-overlay')
					.append(
						$('<div>')
							.addClass(dialogPrefix + 'dialog-loader')
					)
					.appendTo(params.tabs ? $tabContentContainer.children('.' + activeTabContentClass) : $content);
			}
		};

		if (params.tabs) {
			var $tabsContainer = $('<div>').addClass(this.prefix + 'dialog-tabs').appendTo($dialogBody);
			var $tabContentContainer = $('<div>').addClass(this.prefix + 'dialog-tab-content').appendTo($dialogBody);

			var activeTabClass = dialogPrefix + 'dialog-tab-active';
			var activeTabContentClass = dialogPrefix + 'dialog-tab-content-active';
			for (var tabId in params.tabs) {
				var tab = params.tabs[tabId];
				$('<button>')
					.attr('type', 'button')
					.text(tab.name)
					.click(
						(function(tabId, tab) {
							return function() {
								$(this).addClass(activeTabClass).siblings().removeClass(activeTabClass);
								var $tabContent = $tabContentContainer.find('.' + dialogPrefix + 'tab-content-' + tabId);
								/*if (typeof tab.content == 'function') {
									tab.content(function(contentElement) {
										$tabContent.empty().append(contentElement);
									});
								} else if ($tabContent.is(':empty')) {
									$tabContent.append(tab.content);
									if (tab.onLoad) {
										tab.onLoad($tabContent);
									}
								}*/

								$tabContent
									.addClass(activeTabContentClass)
									.siblings()
									.hide()
									.removeClass(activeTabContentClass);

								if ($tabContent.is(':empty')) {
									$tabContent.append(tab.content);
									if (params.autoLoader) {
										showLoaderFunction();
									}
									if (tab.onLoad) {
										tab.onLoad($tabContent);
									}
								}

								$tabContent.show();
							};
						})(tabId, tab)
					)
					.appendTo($tabsContainer);

				$('<div>')
					.addClass(dialogPrefix + 'tab-content-' + tabId)
					.appendTo($tabContentContainer)
					.hide();
			}
		} else {
			var $content = $('<div>')
				.addClass(this.prefix + 'dialog-content')
				.append(content)
				.appendTo($dialogBody);
		}

		var $buttonsContainer = $('<div>').addClass(this.prefix + 'dialog-buttons').appendTo($dialogBody);
		var $firstButton;
		var setButtonsFunction = function(buttons) {
			$buttonsContainer.empty();
			if (buttons) {
				var isFirstButton = true;
				for (var buttonLabel in buttons) {
					var $button = $('<button>')
						.attr('type', 'button')
						.text(buttonLabel)
						.click(buttons[buttonLabel]);
					if (isFirstButton) {
						$firstButton = $button;
					}
					if (isFirstButton && params.firstButtonClass) {
						$button.addClass(params.firstButtonClass);
					} else if (params.buttonClass) {
						$button.addClass(params.buttonClass);
					}
					if (params.reverseButtonOrder) {
						$button.prependTo($buttonsContainer);
					} else {
						$button.appendTo($buttonsContainer);
					}
					isFirstButton = false;
				}
			}
		};
		setButtonsFunction(params.buttons);

		if (params.container) {
			$dialog.appendTo(params.container);
		} else {
			$dialog.appendTo($overlay);
			$overlay.appendTo('body:first');
		}

		if ($firstButton) {
			$firstButton.focus();
		}

		if (params.tabs) {
			$tabsContainer.children(':first').click();
		} else if (params.onLoad) {
			if (params.autoLoader) {
				showLoaderFunction();
			}
			params.onLoad($content);
		}

		return {
			element: $dialog,
			close: closeFunction ? closeFunction : function() {},
			setButtons: setButtonsFunction,
			showLoader: showLoaderFunction,
			hideLoader: function() {
				$dialog.find('.' + dialogPrefix + 'dialog-loader-overlay').remove();
			},
			isClosed: function() {
				return !$(document.body).has($dialog[0]);
			},
			showTabs: function() {
				if ($tabsContainer) {
					$tabsContainer.show();
				}
			},
			hideTabs: function() {
				if ($tabsContainer) {
					$tabsContainer.hide();
				}
			},
			setPageName: function(pageName) {
				if (dialogPageName) {
					$dialog.removeClass(dialogPrefix + 'dialog-page-' + dialogPageName);
				}
				if (pageName) {
					$dialog.addClass(dialogPrefix + 'dialog-page-' + pageName);
					dialogPageName = pageName;
				}
			},
		};
	}
};
