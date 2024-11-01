/**
 * See the license.txt file in the WP Layouts plugin root directory for more information and licenses
 */

jQuery(document).ready(function($) {
	// Start code copied from Forty Two Blocks StyleControls render function
	var blockEditor = document.getElementById('editor');
	if (!blockEditor) {
		return;
	}
	var layoutBlockTypeIds = [];

	var E = wp.element.createElement;

	function getLayoutsList(isMyLayouts) {

		jQuery.get(
			ags_layouts_api_url,
			{
				action: 'ags_layouts_list',
				ags_layouts_collection: isMyLayouts ? -1 : 0,
				ags_layouts_editor: 'gutenberg'
			},
			function(layoutsList) {

				if (layoutsList && layoutsList.data) {
					var layouts = layoutsList.data;
					for (var i = 0; i < layouts.length; ++i) {
						var blockTypeId = 'ags-layouts/l' + layouts[i].layoutId;
						if (layoutBlockTypeIds.indexOf(blockTypeId) === -1) {
							layoutBlockTypeIds.push(blockTypeId);
							wp.blocks.registerBlockType(blockTypeId,
								(function(layoutId, layoutName) {
									var layoutImportStatus = {

									};
									return {
										title: layoutName,
										category: isMyLayouts ? 'ags-layouts-my' : 'ags-layouts-ags',
										icon: E('img',
											{
												key: 'icon',
												src: ags_layouts_admin_config.pluginBaseUrl + 'images/icon-layout.svg',
												//className: 'dashicon ags-layouts-block-settings-menu-icon',
											}
										),
										edit: function(editParams) {
											if (editParams.isSelected && !layoutImportStatus[editParams.clientId]) {
												layoutImportStatus[editParams.clientId] = {
													statusUpdate: 1,
													statusText: wp.i18n.__('Downloading layout...', 'wp-layouts-td'),
													progress: 0
												};
												var myLayoutImportStatus = layoutImportStatus[editParams.clientId];
												replaceSelectionWithLayout(
													layoutId,
													function(progress, statusText) {
														myLayoutImportStatus.progress = progress;
														myLayoutImportStatus.statusText = statusText;
														editParams.setAttributes({
															statusUpdate: ++myLayoutImportStatus.statusUpdate
														});
													},
													function() {
														delete layoutImportStatus[editParams.clientId];
													}
												);
											}

											return E('div',
												{
													key: 'import-progress',
													className: 'ags-layouts-gutenberg-import-block'
												},
												[
													E('p',
														{
															key: 'status-text',
														},
														layoutImportStatus[editParams.clientId].statusText
													),
													E('progress',
														{
															key: 'progress-bar',
															value: layoutImportStatus[editParams.clientId].progress,
														}
													)
												]
											);
										},
										save: function() {

										},
									};
								})(layouts[i].layoutId, layouts[i].layoutName)
							);
						}
					}
				}
			},
			'json'
		);
	}
	$(blockEditor).on('click', '.editor-inserter__toggle, .edit-post-header-toolbar__inserter-toggle', function() {
		if ($(this).attr('aria-expanded') !== 'true') {
			getLayoutsList();
			getLayoutsList(true);
		}
	});

	function gutenbergExport() {
		var	editorSelect = wp.data.select( 'core/editor' ),
			editorDispatch = wp.data.dispatch( 'core/editor' ),
			selectedBlocks =
				editorSelect.hasMultiSelection()
					? editorSelect.getMultiSelectedBlocks()
					: [editorSelect.getSelectedBlock()];

		var allBlocks = editorSelect.getBlocks();
		editorDispatch.resetBlocks(selectedBlocks);
		var selectionContent = editorSelect.getEditedPostContent();
		editorDispatch.resetBlocks(allBlocks);

		ags_layout_export_ui('gutenberg', JSON.stringify(selectedBlocks), selectionContent);
	}

	window.ags_layouts_gutenbergExportAll = function() {
		var	editorSelect = wp.data.select( 'core/editor' ),
			editorDispatch = wp.data.dispatch( 'core/editor' );

		var allBlocks = editorSelect.getBlocks();
		//editorDispatch.resetBlocks(selectedBlocks);
		var selectionContent = editorSelect.getEditedPostContent();
		//editorDispatch.resetBlocks(allBlocks);

		ags_layout_export_ui('gutenberg', JSON.stringify(allBlocks), selectionContent);
	};

	function replaceSelectionWithLayout(layoutId, progressCb, doneCb) {
		var	editorSelect = wp.data.select( 'core/editor' ),
			editorDispatch = wp.data.dispatch( 'core/editor' ),
			selectedBlock = editorSelect.getSelectedBlock(),
			cloneBlock = wp.blocks.cloneBlock;

		var importParams = {
			layoutId: layoutId,
			importLocation: 'return'
		};
		ags_layout_get(
			importParams,
			false,
			false,
			progressCb,
			function (layoutContents) {
				layoutContents = JSON.parse(layoutContents);
				if (layoutContents && layoutContents.length) {
					// Make a copy of each block so that it gets its own clientId, etc.
					for (var i = 0; i < layoutContents.length; ++i) {
						layoutContents[i] = cloneBlock(layoutContents[i]);
					}
					editorDispatch.replaceBlock(selectedBlock.clientId, layoutContents);
					doneCb();
				}
			}
		);
	}

	var registerPlugin = wp.plugins.registerPlugin;
	var PluginBlockSettingsMenuItem = wp.editPost.PluginBlockSettingsMenuItem;
	registerPlugin( 'ags-layouts-block-settings-menu-item', {
		render: function() {
			return E( PluginBlockSettingsMenuItem,
				{
					label: wp.i18n.__('Add to WP Layouts', 'wp-layouts-td'),
					icon: E('img',
						{
							key: 'icon',
							src: ags_layouts_admin_config.pluginBaseUrl + 'images/icon-layout-add-22x24.svg',
						}
					),
					onClick: gutenbergExport,
				}
			);
		}
	} );

	if (window.ags_layouts_gutenberg_preview) {
		var	editorSelect = wp.data.select( 'core/editor' ),
			editorDispatch = wp.data.dispatch( 'core/editor' ),
			cloneBlock = wp.blocks.cloneBlock,
			checkEditorInterval = setInterval(function() {
				var blockIdsToReplace = editorSelect.getBlockOrder();
				if (blockIdsToReplace) {
					clearInterval(checkEditorInterval);
					for (var i = 0; i < window.ags_layouts_gutenberg_preview.length; ++i) {
						window.ags_layouts_gutenberg_preview[i] = cloneBlock(window.ags_layouts_gutenberg_preview[i]);
					}
					editorDispatch.replaceBlocks(blockIdsToReplace, window.ags_layouts_gutenberg_preview);
				}
			}, 500);
	}
});

function ags_layouts_gutenbergImportLayout() {
	var	editorSelect = wp.data.select( 'core/editor' ),
		editorDispatch = wp.data.dispatch( 'core/editor' ),
		cloneBlock = wp.blocks.cloneBlock;

	ags_layout_import_ui(
		'gutenberg',
		null,
		null,
		function (layoutContents, importParams) {
			console.log('got gutenberg layout contents');
			console.log(layoutContents);
			layoutContents = JSON.parse(layoutContents);
			if (layoutContents && layoutContents.length) {
				// Make a copy of each block so that it gets its own clientId, etc.
				for (var i = 0; i < layoutContents.length; ++i) {
					layoutContents[i] = cloneBlock(layoutContents[i]);
				}

				switch (importParams.importLocation) {
					case 'above':
						editorDispatch.insertBlocks(layoutContents, 0);
						break;
					case 'below':
						editorDispatch.insertBlocks(layoutContents);
						break;
					case 'replace':
						var blockIdsToReplace = editorSelect.getBlockOrder();
						editorDispatch.replaceBlocks(blockIdsToReplace, layoutContents);
						break;
				}

			}
		}
	);
}