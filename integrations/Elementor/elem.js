/*
 * This file contains code based on and/or copied from the Elementor plugin (free version),
 * copyright Elementor, licensed under GNU General Public License version 3 (GPLv3) or later.
 * See the license.txt file in the WP Layouts plugin root directory for more information and licenses
 */

jQuery(document).ready(function($) {

	// Inject into the save dialog
	var $saveDialogTemplate = $('#tmpl-elementor-template-library-save-template');
	if ($saveDialogTemplate.length) {
		var $saveOptionWPL = $('<input>').attr({
			type: 'radio',
			name: 'ags_layouts_elem_save',
			value: 1
		});
		var $saveOptionElem = $saveOptionWPL.clone().attr({
			value: 0,
			checked: true
		});
		var $saveOptions = $('<ul>')
			.append($('<li>').append($('<label>').html(wp.i18n.__('Save to Elementor Library', 'wp-layouts-td')).prepend($saveOptionElem)))
			.append($('<li>').append($('<label>').html(wp.i18n.__('Save to WP Layouts', 'wp-layouts-td')).prepend($saveOptionWPL)));
		var saveOptionsHtml = '<ul class="ags-layouts-elem-save-options">' + $saveOptions.html() + '</ul>';

		var saveDialogHtml = $saveDialogTemplate.html();
		var formClosePos = saveDialogHtml.indexOf('</form>');

		if (formClosePos !== -1) {
			saveDialogHtml = saveDialogHtml.substr(0, formClosePos) + saveOptionsHtml + saveDialogHtml.substr(formClosePos);
		}
		$saveDialogTemplate.html(saveDialogHtml);
	}

	AGSLayoutsUtil.replaceFunctionWithFunction(elementor.templates, 'saveTemplate', function(saveType, formData) {
		var $dialogContent = $('#elementor-template-library-save-template').closest('.dialog-content').empty();

		if ($dialogContent && formData.ags_layouts_elem_save && parseInt(formData.ags_layouts_elem_save) && formData.content) {
			var content = JSON.stringify(formData.content);
			ags_layout_export_ui(
				'elementor',
				content,
				content,
				$dialogContent,
				function() {
					elementor.templates.component.close();
				},
				formData.title
			);
		} else {
			elementor.templates.ags_layouts_orig__saveTemplate(saveType, formData);
		}
	});

	// Add tabs to Elementor library

	elementor.templates.component.addTab('templates/ags-layouts-ags', {
		title: wp.i18n.__('WP Layouts', 'wp-layouts-td'),
		filter: {
			source: 'ags-layouts-ags'
		}
	});
	elementor.templates.component.addTab('templates/ags-layouts-my', {
		title: wp.i18n.__('My WP Layouts', 'wp-layouts-td'),
		filter: {
			source: 'ags-layouts-my'
		}
	});

	AGSLayoutsUtil.replaceFunctionWithFunction(elementor.templates, 'setScreen', function(args) {
		if ( args && args.source
			&& (args.source === 'ags-layouts-my' || args.source === 'ags-layouts-ags') ) {
			ags_layout_import_ui(
				'elementor',
				$(elementor.templates.layout.modalContent.el).empty(),
				args.source === 'ags-layouts-my' ? 'layouts_my' : 'layouts_ags',
				function (layoutContents) {
					layoutContents = JSON.parse(layoutContents);

					if (layoutContents) {

						// Make sure each top-level element is a section
						for (var i = 0; i < layoutContents.length; ++i) {
							if (layoutContents[i].elType !== 'section') {
								// Section structure based on Elementor output; ID will be assigned later
								layoutContents[i] = {
									elType: 'section',
									isInner: false,
									settings: [],
									defaultEditSettings: [],
									editSettings: [],
									elements: [
										{
											elType: 'column',
											isInner: false,
											settings: {
												'_column_size': 100
											},
											defaultEditSettings: [],
											editSettings: [],
											elements: [
												layoutContents[i]
											]
										}
									]
								};
							}
						}

						// Assign new IDs
						function setElementIds(elements) {
							for (var i = 0; i < elements.length; ++i) {
								elements[i].id = elementor.helpers.getUniqueID();
								if (elements[i].elements) {
									elements[i].elements = setElementIds(elements[i].elements);
								}
							}
							return elements;
						}
						layoutContents = setElementIds(layoutContents);

						// Insert the layout contents - based on importTemplate() function
						// assets/dev/js/editor/components/template-library/manager.js (line 170)
						elementor.getPreviewView().addChildModel(layoutContents, {});
					}

					elementor.templates.setScreen(args);

					elementor.templates.component.close();
				},
				'return'
			);
		} else {
			elementor.templates.ags_layouts_orig__setScreen(args);
		}
	});


}); // end of document ready handler