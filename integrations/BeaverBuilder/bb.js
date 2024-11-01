/**
 * This file includes code based on and/or copied from parts of Beaver Builder Plugin (Standard Version) and/or Beaver
 * Builder Plugin (Lite Version), copyright 2014 Beaver Builder, released under GPLv2+
 * (according to the plugin's readme.txt file; fl-builder.php specifies different licensing
 * but we are using the licensing specified in readme.txt), licensed under GPLv3+
 * See the license.txt file in the WP Layouts plugin root directory for more information and licenses
 */

jQuery(document).ready(function($) {

	// Add hooks for the save and import menu items

	if (window.FLBuilder) {
		FLBuilder.addHook('AGSLayoutsSavePage', function() {
			FLBuilder.showAjaxLoader();
			FLBuilder.MainMenu.hide();
			var $content = $('.fl-builder-content:first');
			$.post(ajaxurl, {
				action: 'ags_layouts_bb_get_nodes',
				postId: $content.data('post-id')
			}, function(response) {
				FLBuilder.hideAjaxLoader();
				if (response.success && response.data) {
					ags_layout_export_ui('beaverbuilder', response.data, $content);
				}
			}, 'json');
		});

		FLBuilder.addHook('AGSLayoutsImport', function() {
			FLBuilder.MainMenu.hide();
			ags_layout_import_ui('beaverbuilder', null, null, FLBuilder._updateLayout);
		});
	}

	// Add the save button to the UI

	var toolbarTemplates = [
		$('#tmpl-fl-row-overlay'),
		$('#tmpl-fl-col-overlay'),
		$('#tmpl-fl-module-overlay'),
	];
	for (var i = 0; i < toolbarTemplates.length; ++i) {
		var templateContent = toolbarTemplates[i].html();
		var $removeButton = $(templateContent).find('i.fl-block-remove');
		if ($removeButton.length) {
			$removeButton
				.attr('class', 'ags-layouts-bb-module-save fl-tip')
				.attr('title', wp.i18n.__('Save to WP Layouts', 'wp-layouts-td'));
			var insertionIndex = templateContent.lastIndexOf('<i ', templateContent.indexOf('fl-block-remove'));
			toolbarTemplates[i].html(
				templateContent.substr(0, insertionIndex) + $('<div>').append($removeButton).html() + templateContent.substr(insertionIndex)
			);
		}
	}

	// Handle save button clicks

	$('.fl-builder-content').on('click', '.ags-layouts-bb-module-save', function() {
		FLBuilder.showAjaxLoader();
		var $saveButton = $(this), $node = $saveButton.closest('[data-node]');
		$.post(ajaxurl, {
			action: 'ags_layouts_bb_get_nodes',
			postId: $saveButton.closest('.fl-builder-content').data('post-id'),
			nodeId: $node.data('node')
		}, function(response) {
			FLBuilder.hideAjaxLoader();
			if (response.success && response.data) {
				ags_layout_export_ui('beaverbuilder', response.data, $node);
			}
		}, 'json');
		return false;
	});

});
// end of ready function
