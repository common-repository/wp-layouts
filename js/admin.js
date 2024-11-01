/**
 * Contains code copied from and/or based on Divi Theme/ Divi Plugin by Elegant Themes
 * See the license.txt file in the WP Layouts plugin root directory for more information and licenses
 *
 */

var ags_layouts_api_url, ags_layouts_take_screenshot, ags_layouts_extra_tasks;

jQuery(document).ready(function($) {
	ags_layouts_api_url = ags_layouts_admin_config.wpAjaxUrl;
}); // end document ready


/** includes/admin-page.php **/
function page_ags_layouts($) {
	var $listContainer = $('#ags-layouts-list-container');
	var $layoutDetails = $('#ags-layouts-details');
	var $layoutDetailsImage = $('#ags-layouts-details-image');
	// var $layoutDetailsId = $('#ags-layouts-details-id');
	var $layoutDetailsName = $('#ags-layouts-details-name');
	var $layoutDetailsReadKey = $('#ags-layouts-details-read-key');
	var $loaderOverlay = $('#ags-layouts-loader-overlay');
	
	var layoutSelectHandler = function(layout, $tabContent, $tableContainer) {
		if (layout) {
			$('#ags-layouts-details-buttons > button, #ags-layouts-details-read-key-buttons > button').attr('disabled', false);
			$('#ags-layouts-details-edit')
				.toggle(layout.isEditable)
				.attr('href', '?page=ags-layouts&edit=' + layout.layoutId);
			$layoutDetails.data('ags-layout', layout)
				.removeClass('ags-layouts-details-none');
			if (layout.hasLayoutImage) {
				$layoutDetailsImage
					.removeClass('ags-layouts-no-image')
					.css('background-image', 'url(\'' + ags_layouts_api_url + '&action=ags_layouts_get_image&image=L&layoutId=' + layout.layoutId + '\')');
			} else {
				$layoutDetailsImage.addClass('ags-layouts-no-image');
			}
			// $layoutDetailsId.val(layout.layoutId);
			$layoutDetailsName.val(layout.layoutName);
			$layoutDetailsReadKey.val('');
		} else {
			$('#ags-layouts-details-buttons > button, #ags-layouts-details-read-key-buttons > button').attr('disabled', true);
			$layoutDetails
				.data('ags-layout', null)
				.addClass('ags-layouts-details-none');
			$layoutDetailsImage.css('background-image', '').removeClass('ags-layouts-no-image');
			// $layoutDetailsId.val('');
			$layoutDetailsName.val('');
			$layoutDetailsReadKey.val('');
		}
	};
	
	var listUi = ags_layouts_list_ui(-1, null, null, layoutSelectHandler, function() {
		$loaderOverlay.show();
	}, function() {
		$loaderOverlay.hide();
	});
	$listContainer.append(listUi[0]);
	listUi[1]($listContainer);
	var dataTable = listUi[2]();
	
	$layoutDetails.children('form:first').submit(function() {
		var $form = $(this),
			layout = $form.parent().data('ags-layout'),
			newLayoutName = $layoutDetailsName.val(),
			errorHandler = function() {
				ags_layouts_message_dialog(
					wp.i18n.__('Error', 'wp-layouts-td'),
					wp.i18n.__('Something went wrong while updating the layout. Please try again if your changes were not saved.', 'wp-layouts-td'),
					'O'
				);
			};
		
		if (layout && newLayoutName) {
			$loaderOverlay.show();
			$.post(ags_layouts_api_url, {
				action: 'ags_layouts_update',
				ags_layouts_data: {
					layoutId: layout.layoutId,
					layoutName: newLayoutName
				}
			}, function(response) {
				if (!response.success) {
					errorHandler();
				}
			}, 'json')
			.fail(errorHandler)
			.always(function(response) {
				layoutSelectHandler();
				dataTable.ajax.reload(null, false);
			});
		
		}
		
		return false;
	});
	
	$('#ags-layouts-details-delete').click(function() {
		var layout = $('#ags-layouts-details').data('ags-layout'),
			errorHandler = function() {
				ags_layouts_message_dialog(
					wp.i18n.__('Error', 'wp-layouts-td'),
					wp.i18n.__('Something went wrong while deleting the layout.', 'wp-layouts-td'),
					'O'
				);
			};
		
		if (layout) {
			ags_layouts_message_dialog(
				wp.i18n.__('Are you sure?', 'wp-layouts-td'),
				wp.i18n.__('Are you sure that you want to delete this layout?', 'wp-layouts-td'),
				'YN',
				function() {
					$loaderOverlay.show();
					$.post(ags_layouts_api_url, {
						action: 'ags_layouts_delete',
						layoutId: layout.layoutId
					}, function(response) {
						if (!response.success) {
							errorHandler();
						}
					}, 'json')
					.fail(errorHandler)
					.always(function(response) {
						layoutSelectHandler();
						dataTable.ajax.reload(null, false);
					});
				}
			);
		}
	});
	
	$('#ags-layouts-details-read-key-show, #ags-layouts-details-read-key-reset').click(function() {
		var layout = $('#ags-layouts-details').data('ags-layout'),
			isReset = (this.id === 'ags-layouts-details-read-key-reset'),
			errorHandler = function() {
				ags_layouts_message_dialog(
					wp.i18n.__('Error', 'wp-layouts-td'),
					wp.i18n.__('Something went wrong while retrieving or resetting the layout\'s read key.', 'wp-layouts-td'),
					'O'
				);
			},
			doAction = function() {
				$loaderOverlay.show();
				var request = {
					action: 'ags_layouts_get_read_key',
					layoutId: layout.layoutId
				};
				if (isReset) {
					request.reset = true;
				}
				$.post(ags_layouts_api_url, request, function(response) {
					if (response.success && response.data && response.data.key) {
						$layoutDetailsReadKey.val(response.data.key);
					} else {
						errorHandler();
					}
				}, 'json')
				.fail(errorHandler)
				.always(function() {
					$loaderOverlay.hide();
				});
			};
		
		if (layout) {
			if (isReset) {
				ags_layouts_message_dialog(
					wp.i18n.__('Are you sure?', 'wp-layouts-td'),
					wp.i18n.__('Are you sure that you want to reset this layout\'s read key? Any packages depending on this read key will no longer work.', 'wp-layouts-td'),
					'YN',
					doAction
				);
			} else {
				doAction();
			}
		}
	});
		
}

/** includes/site-export-packager-page.php **/
function page_ags_layouts_site_export_packager($) {
	var $form = $('#ags-layouts-site-export-packager form');
	var $list = $('#ags-layouts-site-export-packager-export-list');
	
	$('#ags-layouts-site-export-packager-export-add').click(function() {
		ags_layout_import_ui('site-importer', null, 'layouts_my', function(layout) {
			$('<li>')
				.append($('<span>').text(layout.layoutName))
				.append(
					$('<a>')
						//.text('x')
						.attr('class', 'remove-item')
						.attr('aria-label', wp.i18n.__('Remove ${layoutName}', 'wp-layouts-td').replace('${layoutName}', layout.layoutName) )
						.attr('href', '#')
						.click(function() {
							$(this).parent().remove();
							return false;
						})
				)
				.append($('<input name="layoutIds[]" type="hidden">').val(layout.layoutId))
				.appendTo($list);
		}, 'layout-callback');
	});
	
	$('#ags-layouts-site-export-packager [name="mode"]:first').change(function() {
		var mode = $(this).val();
		var enabledSelector = '.ags-layouts-' + mode + '-only';
		var disabledSelector = '.ags-layouts-' + ( mode === 'standalone' ? 'hook' : 'standalone' ) + '-only';
		
		$(enabledSelector).show();
		$(enabledSelector + '.ags-layouts-required :input').attr('required', true);
		
		$(disabledSelector).hide();
		$(disabledSelector + '.ags-layouts-required :input').attr('required', false);
	}).change();
	
	$form.submit(function() {
		$('#ags-layouts-site-export-packager-button').attr('disabled', true);
		
		$('#ags-layouts-site-export-packager-instructions-standalone, #ags-layouts-site-export-packager-instructions-hook').addClass('hidden');
		
		
		var layouts = {};
		var layoutIds = [];
		
		$('#ags-layouts-site-export-packager [name="layoutIds[]"]').each(function() {
			layoutIds.push( $(this).val() );
		});
		if (layoutIds.length) {
			
			var errorHandler = function() {
				ags_layouts_message_dialog(
					wp.i18n.__('Error', 'wp-layouts-td'),
					wp.i18n.__('Something went wrong while packaging the site export.', 'wp-layouts-td'),
					'O'
				);
				
				$('#ags-layouts-site-export-packager-button').attr('disabled', false);
			};
			
			function getLayoutData() {
				var layoutId = parseInt( layoutIds.shift().trim() );
				$.post(ags_layouts_api_url, {
					action: 'ags_layouts_get_read_key',
					layoutId: layoutId
				}, function(response) {
					if (
						response.success && response.data
						&& response.data.layoutId && parseInt( response.data.layoutId ) === layoutId
						&& response.data.layoutName && response.data.key 
					) {
						layouts[layoutId] = {
							name: response.data.layoutName,
							key: response.data.key
						};
						if (layoutIds.length) {
							getLayoutData();
						} else {
							submitPackage();
							$('#ags-layouts-site-export-packager-button').attr('disabled', false);
						}
						
					} else {
						errorHandler();
					}
				}, 'json')
				.fail(errorHandler);
			}
			getLayoutData();
			
			function submitPackage() {
				var mode = $('#ags-layouts-site-export-packager [name="mode"]:first').val();
			
				var packageData = {
					'layouts': layouts,
					'mode': mode,
					'editor': 'SiteImporter',
					'menuName': ( mode === 'standalone' ? $('#ags-layouts-site-export-packager [name="menuName"]:first').val() : '_na_' ),
					'menuParent': ( mode === 'standalone' ? $('#ags-layouts-site-export-packager [name="menuParent"]:first').val() : '_na_' ),
					'pageTitle': ( mode === 'standalone' ? $('#ags-layouts-site-export-packager [name="pageTitle"]:first').val() : '_na_' ),
					'layoutDownloadError': ( mode === 'standalone' ? $('#ags-layouts-site-export-packager [name="layoutDownloadError"]:first').val() : '_na_' ),
					'classPrefix': ( mode === 'standalone' ? $('#ags-layouts-site-export-packager [name="classPrefix"]:first').val() : '_na_' ),
					'textDomain': ( mode === 'standalone' ? $('#ags-layouts-site-export-packager [name="textDomain"]:first').val() : '_na_' ),
					'step1Html': ( mode === 'standalone' ? wp.editor.getContent('ags-layouts-site-export-packager-step1Html') : '_na_' ),
					'step2Html': ( mode === 'standalone' ? wp.editor.getContent('ags-layouts-site-export-packager-step2Html') : '_na_' ),
					'buttonText': ( mode === 'standalone' ? $('#ags-layouts-site-export-packager [name="buttonText"]:first').val() : '_na_' ),
					'progressHeading': ( mode === 'standalone' ? $('#ags-layouts-site-export-packager [name="progressHeading"]:first').val() : '_na_' ),
					'completeHeading': ( mode === 'standalone' ? $('#ags-layouts-site-export-packager [name="completeHeading"]:first').val() : '_na_' ),
					'successHtml': ( mode === 'standalone' ? wp.editor.getContent('ags-layouts-site-export-packager-successHtml') : '_na_' ),
					'errorHtml': ( mode === 'standalone' ? wp.editor.getContent('ags-layouts-site-export-packager-errorHtml') : '_na_' ),
				};
				
				switch (mode) {
					case 'standalone':
						$('#ags-layouts-site-export-package')
							.val( JSON.stringify(packageData) )
							.parent()
							.attr('action', ajaxurl)
							.submit();
						$('#ags-layouts-site-export-packager-instructions-standalone').removeClass('hidden');
						break;
					case 'hook':
						$.post(ajaxurl, {
							action: 'ags_layouts_package',
							ags_layouts_nonce: $('#ags_layouts_nonce').val(),
							'package': JSON.stringify(packageData)
						}, function(response) {
							if ( response.success && response.data ) {
								$('#ags-layouts-site-export-packager-instructions-hook code:first').text(response.data);
								$('#ags-layouts-site-export-packager-instructions-hook').removeClass('hidden');
							} else {
								errorHandler();
							}
						}, 'json')
						.fail(errorHandler);
						
						break;
				}
				
				
			}
			
			
		} else {
			ags_layouts_message_dialog(
				wp.i18n.__('No site exports selected', 'wp-layouts-td'),
				wp.i18n.__('Please select at least one site export to include in the package.', 'wp-layouts-td'),
				'O'
			);
			$('#ags-layouts-site-export-packager-button').attr('disabled', false);
		}
		
		return false;
	});
}

/** includes/site-export-page.php **/
function page_ags_layouts_site_export($) {
	var $form = $('#ags-layouts-site-export form');
	var $status = $('#ags-layouts-site-export-status');
	
	// wp-layouts\includes\site-export-packager-page.php
	var errorHandler = function() {
		console.log( 'Site export error' + (arguments.length > 1 ? ': ' + arguments[1] : '') );
		if (arguments.length > 2) {
			console.log(arguments[2]);
		}

		ags_layouts_message_dialog(
			wp.i18n.__('Error', 'wp-layouts-td'),
			wp.i18n.__('Something went wrong during the export.', 'wp-layouts-td'),
			'O'
		);
	};

	var dialogOptions = {
		title: 'WP Layouts Site Exporter',
		container: $form.parent(),
		content: $form,
		pageName: 'site-export',
		firstButtonClass: 'aspengrove-btn-secondary',
		buttons: {
			'Export Site': function() {
		
				var layoutName = $('#ags-layouts-site-export-name').val();
				var layoutData = {
					contents: '',
					extraData: {}
				};
				var tasks = [];
				var selectedTasks = {};
				var screenshotData = -1;
				
				$('#ags-layouts-site-export-items input:checked').each(function() {
					var checkedItem = $(this).val().split('.');
					if (checkedItem.length === 1) {
						selectedTasks[ checkedItem[0] ] = true;
					} else if (selectedTasks[ checkedItem[0] ] && selectedTasks[ checkedItem[0] ].length) {
						selectedTasks[ checkedItem[0] ].push(checkedItem[1]);
					} else {
						selectedTasks[ checkedItem[0] ] = [ checkedItem[1] ];
					}
				});
				
				tasks.push([
					'Exporting content...',
					function(cb) {
						/*
								This function contains code adapted from fast-xml-parser-3.16.0\README.md
								
								MIT License

								Copyright (c) 2017 Amit Kumar Gupta

								Permission is hereby granted, free of charge, to any person obtaining a copy
								of this software and associated documentation files (the "Software"), to deal
								in the Software without restriction, including without limitation the rights
								to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
								copies of the Software, and to permit persons to whom the Software is
								furnished to do so, subject to the following conditions:

								If you use this library in a public repository then you give us the right to mention your company name and logo in user's list without further permission required, but you can request them to be taken down within 30 days. 

								The above copyright notice and this permission notice shall be included in all
								copies or substantial portions of the Software.

								THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
								IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
								FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
								AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
								LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
								OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
								SOFTWARE.
						*/
					
						$.get(
							'export.php?agslayouts=1',
							{
								download: true,
							},
							function(responseString) {
								
								var result = agslayouts_xml_parser.validate(responseString, {
								
								});
								if (result !== true) console.log(result.err);
								var response = agslayouts_xml_parser.parse(responseString, {
									ignoreAttributes: false,
									cdataTagName: "__cdata",
								});
							
								var excludePostTypes = $('#ags-layouts-site-export [name="excludePostTypes"]:first').val();
								if (excludePostTypes) {
									excludePostTypes = excludePostTypes.split(',');
									for (var i = 0; i < excludePostTypes.length; ++i) {
										excludePostTypes[i] = excludePostTypes[i].trim();
									}
								} else {
									excludePostTypes = [];
								}
								excludePostTypes.push('custom_css');
								
								// copied during merge from: wp-layouts/includes/site-export-page.php
								var excludeMeta = $('#ags-layouts-site-export [name="excludeMeta"]:first').val(),
									excludeMetaPrefix = $('#ags-layouts-site-export [name="excludeMetaPrefix"]:first').val();
								if (excludeMeta) {
									excludeMeta = excludeMeta.split(',');
									for (var i = 0; i < excludeMeta.length; ++i) {
										excludeMeta[i] = excludeMeta[i].trim();
									}
								} else {
									excludeMeta = [];
								}
								if (excludeMetaPrefix) {
									excludeMetaPrefix = excludeMetaPrefix.split(',');
									for (var i = 0; i < excludeMetaPrefix.length; ++i) {
										excludeMetaPrefix[i] = excludeMetaPrefix[i].trim();
									}
								} else {
									excludeMetaPrefix = [];
								}
								
								if (response && response.rss && response.rss.channel) {
									
									if (response.rss.channel.item) {
										
										var validItems = [];
										for (var i = 0; i < response.rss.channel.item.length; ++i) {
											if ( excludePostTypes.indexOf( response.rss.channel.item[i]['wp:post_type'].__cdata ? response.rss.channel.item[i]['wp:post_type'].__cdata : response.rss.channel.item[i]['wp:post_type'] ) === -1) {
												var item = response.rss.channel.item[i];
												if ( item['wp:postmeta'] ) {
													for (var j = 0; j < item['wp:postmeta'].length; ++j) {
														if ( item['wp:postmeta'][j]['wp:meta_key'] && item['wp:postmeta'][j]['wp:meta_key'].__cdata ) {
															var metaKey = item['wp:postmeta'][j]['wp:meta_key'].__cdata;
															
															if ( excludeMeta.indexOf(metaKey) !== -1 ) {console.log('excluding meta key: '+metaKey);
																item['wp:postmeta'].splice(j, 1);
																--j;
															} else {
																var metaKeyExcluded = false;
																for (var k = 0; k < excludeMetaPrefix.length; ++k) {
																	if ( metaKey.indexOf(excludeMetaPrefix[k]) === 0 ) {console.log('excluding meta key: '+metaKey);
																		item['wp:postmeta'].splice(j, 1);
																		--j;
																		break;
																	}
																}
															}
														}
													}
												}
												validItems.push(item);
											}
										}
										response.rss.channel.item = validItems;
									
									}
									
									if ( response.rss.channel['wp:term'] ) {
								
										var excludeTaxonomies = $('#ags-layouts-site-export [name="excludeTaxonomies"]:first').val();
										if (excludeTaxonomies) {
											excludeTaxonomies = excludeTaxonomies.split(',');
											for (var i = 0; i < excludeTaxonomies.length; ++i) {
												excludeTaxonomies[i] = excludeTaxonomies[i].trim();
											}
											
											var validTerms = [];
											for (var i = 0; i < response.rss.channel['wp:term'].length; ++i) {
												if ( excludeTaxonomies.indexOf( response.rss.channel['wp:term'][i]['wp:term_taxonomy'].__cdata ? response.rss.channel['wp:term'][i]['wp:term_taxonomy'].__cdata : response.rss.channel['wp:term'][i]['wp:term_taxonomy'] ) === -1) {
													validTerms.push(response.rss.channel['wp:term'][i]);
												}
											}
											response.rss.channel['wp:term'] = validTerms;
											
										}
									
									}
								
								}
								
								var j2xParser = new agslayouts_xml_parser.j2xParser({
									ignoreAttributes: false,
									cdataTagName: "__cdata",
									format: true,
									indentBy: '',
								});
								layoutData.contents = j2xParser.parse(response);
								layoutData.contents = layoutData.contents
														.replace(/\n\<!\[CDATA\[/g, '<![CDATA[')
														.replace(/\]\]\>\n/g, ']]>');
								
								cb();
							}, 'text'
						)
						.fail(errorHandler);
					}
				]);
				
				if ( selectedTasks.widgets ) {
					tasks.push([
						'Exporting widgets...',
						function(cb) {
							$.get(
								ajaxurl,
								{
									action: 'ags_layouts_get_widgets_export',
									ags_layouts_nonce: $('#ags-layouts-site-export #ags_layouts_nonce').val(),
								},
								function(response) {
									layoutData.extraData.widgets = response;
									cb();
								},
								'text'
							)
							.fail(errorHandler);
						}
					]);
				}
				
				if ( selectedTasks.themeOptions || selectedTasks.pluginOptions ) {
					tasks.push([
						'Exporting theme and/or plugin options...',
						function(cb) {
							$.post(
								ajaxurl,
								{
									action: 'ags_layouts_get_theme_plugin_options_export',
									ags_layouts_nonce: $('#ags-layouts-site-export #ags_layouts_nonce').val(),
									themeOptions: selectedTasks.themeOptions ? true : false,
									pluginOptions: selectedTasks.pluginOptions ? selectedTasks.pluginOptions : null
								},
								function(response) {
									layoutData.extraData.agsxto = response;
									cb();
								},
								'text'
							)
							.fail(errorHandler);
						}
					]);
				}
				
				if ( selectedTasks.diviModulePresets ) {
					tasks.push([
						'Exporting Divi Builder module presets...',
						function(cb) {
							$.post(
								ajaxurl,
								{
									action: 'ags_layouts_get_divi_module_presets_export',
									ags_layouts_nonce: $('#ags-layouts-site-export #ags_layouts_nonce').val()
								},
								function(response) {
									if ( response && response.length > 2 ) {
										layoutData.extraData.diviModulePresets = response;
									}
									cb();
								},
								'text'
							)
							.fail(errorHandler);
						}
					]);
				}
				
				if ( selectedTasks.calderaForms ) {
					tasks.push([
						'Exporting Caldera Forms...',
						function(cb) {
							$.post(
								ajaxurl,
								{
									action: 'ags_layouts_get_caldera_forms_export',
									ags_layouts_nonce: $('#ags-layouts-site-export #ags_layouts_nonce').val(),
									formIds: selectedTasks.calderaForms
								},
								function(response) {
									layoutData.extraData.caldera_forms = response;
									cb();
								},
								'text'
							)
							.fail(errorHandler);
						}
					]);
				}
				
				tasks.push([
						'Exporting menu assignments...',
						function(cb) {
							$.post(
								ajaxurl,
								{
									action: 'ags_layouts_get_menu_assignments_export',
									ags_layouts_nonce: $('#ags-layouts-site-export #ags_layouts_nonce').val(),
								},
								function(response) {
									layoutData.extraData.config = {};
									
									if (response) {
										layoutData.extraData.config.menus = response;
									}
									
									// below string from my WordPress export XML file, modified the type
									if (layoutData.contents.indexOf('<wp:post_type><![CDATA[et_template]]></wp:post_type>') !== -1) {
										layoutData.extraData.config.hasThemeBuilderTemplates = true;
									}
									
									layoutData.extraData.config = JSON.stringify(layoutData.extraData.config);
									
									cb();
								},
								'json'
							)
							.fail(errorHandler);
						}
					]);
				
				tasks.push([
					'Processing image...',
					function(cb) {
						
						// The following code is copied from https://developer.mozilla.org/en-US/docs/Web/API/FileReader/readAsDataURL and modified. Original code is in the public domain.
						  const file = $('#ags-layouts-site-export-image')[0].files[0];
						  const reader = new FileReader();

						  reader.addEventListener("load", function () {
							screenshotData = reader.result;
							cb();
						  }, false);

						  if (file) {
							reader.readAsDataURL(file);
						  } else {
							cb();
						  }
						// End code is copied from https://developer.mozilla.org/en-US/docs/Web/API/FileReader/readAsDataURL and modified.
						
						
					}
				]);
				
				tasks.push([
					'Preparing to upload...',
					function() {
						
						ags_layouts_export_job_ui(
							layoutData.contents,
							screenshotData,
							'site-importer',
							layoutName,
							dialog,
							$form,
							dialog.setButtons,
							dialog.close,
							layoutData.extraData
						);
					}
				]);
				
				function runTask() {
					if (tasks.length) {
						var task = tasks.shift();
						$status.text(task[0]);
						task[1](runTask);
					}
				}
				runTask();
				
				return false;
			}
			
		}
	};
	
	dialog = agsLayoutsDialog.create(dialogOptions);
	
	
}


/** integrations/SiteImporter/edit-page.php **/
function page_ags_layouts_site_importer_edit($) {
	var $form = $('#ags-layouts-edit-site-importer-page form');
	
	$('#ags-layouts-edit-site-importer-replacement-export-add').click(function() {
		ags_layout_import_ui('site-importer', null, 'layouts_my', function(layout) {
			$('#ags-layouts-edit-site-importer-replacement-export')
				.empty()
				.append($('<span>').text(layout.layoutName))
				.append(
					$('<a>')
                        .attr('class', 'remove-item')
						.attr('aria-label', wp.i18n.__('Remove ${layoutName}', 'wp-layouts-td').replace('${layoutName}', layout.layoutName) )
						.attr('href', '#')
						.click(function() {
							$(this).parent().empty();
							return false;
						})
				)
				.append($('<input name="newLayoutId" type="hidden">').val(layout.layoutId));
		}, 'layout-callback');
	});
	
	
	$form.submit(function() {
		if ( !$('#ags-layouts-edit-site-importer-button').attr('disabled') ) {
			var newLayoutId = parseInt( $('#ags-layouts-edit-site-importer-replacement-export input').val() );
			if ( !newLayoutId ) {
				ags_layouts_message_dialog(
					wp.i18n.__('Error', 'wp-layouts-td'),
					wp.i18n.__('Please select your new site export before continuing.', 'wp-layouts-td'),
					'O'
				);
			} else if ( newLayoutId === agsLayoutsSiteImporterEditId ) {
				ags_layouts_message_dialog(
					wp.i18n.__('Error', 'wp-layouts-td'),
					wp.i18n.__('Your replacement export cannot be the same as the export you are editing.', 'wp-layouts-td'),
					'O'
				);
			} else {
				ags_layouts_message_dialog(
					wp.i18n.__('Warning', 'wp-layouts-td'),
					wp.i18n.__('This will permanently delete the data in your old site export, and may overwrite the API authentication key for your new export. Are you sure that you would like to proceed?', 'wp-layouts-td'),
					'YN',
					function() {
						$('#ags-layouts-edit-site-importer-button').attr('disabled', true);
						$form.submit();
					}
				);
			}
			
			return false;
		}
		
	});
}




function ags_layouts_message_dialog(title, message, buttons, okCb) {
	var $dialogContents = jQuery('<div>');
	
	( message.appendTo ? message : jQuery('<p>').text(message) ).appendTo($dialogContents);
	
	var dialog;
	var closeFunction = function() {
		dialog.close();
	};
	if (okCb) {
		var okFunction = function() {
			okCb();
			dialog.close();
		};
	} else {
		var okFunction = closeFunction;
	}
	
	var dialogButtons = {};
	switch (buttons) {
		case 'OC':
			dialogButtons[wp.i18n.__('OK', 'wp-layouts-td')] = okFunction;
			dialogButtons[wp.i18n.__('Cancel', 'wp-layouts-td')] = closeFunction;
			break;
		case 'YN':
			dialogButtons[wp.i18n.__('Yes', 'wp-layouts-td')] = okFunction;
			dialogButtons[wp.i18n.__('No', 'wp-layouts-td')] = closeFunction;
			break;
		default:
			dialogButtons[wp.i18n.__('OK', 'wp-layouts-td')] = okFunction;
	}
	
	dialog = agsLayoutsDialog.create({
		title: title,
		content: $dialogContents,
		reverseButtonOrder: true,
		dialogClass: 'ags-layouts-message-dialog',
		firstButtonClass: 'aspengrove-btn-primary',
		buttonClass: 'aspengrove-btn-secondary',
		buttons: dialogButtons
	});
	
	return dialog;
}

function ags_layout_export_ui(editorName, postContent, screenshotContent, dialogContainer, dialogCloseCallback, layoutName, extraData) {
	var $dialogContents = jQuery('<div>');
	var dialog;
	
	function doExport(layoutName) {
		ags_layouts_export_job_ui(
			postContent,
			screenshotContent,
			editorName,
			layoutName,
			dialog,
			$dialogContents,
			dialog.setButtons,
			dialogCloseCallback ? dialogCloseCallback : dialog.close,
			extraData
		);
	}
	
	var dialogOptions = {
		title: wp.i18n.__('Export Layout', 'wp-layouts-td'),
		content: $dialogContents,
		pageName: 'export-options',
		reverseButtonOrder: true,
		firstButtonClass: 'aspengrove-btn-primary',
		buttonClass: 'aspengrove-btn-secondary',
	};
	
	if (dialogContainer) {
		dialogOptions.container = dialogContainer;
	}
	
	if (layoutName) {
		dialog = agsLayoutsDialog.create(dialogOptions);
		doExport(layoutName);
	} else {
		jQuery('<h4>').text(wp.i18n.__('To save this content in your online layout storage, enter a name for the layout below.', 'wp-layouts-td')).appendTo($dialogContents);
		var $layoutNameField = jQuery('<input>').attr({
			type: 'text',
			placeholder: wp.i18n.__('Layout Name', 'wp-layouts-td')
		}).appendTo($dialogContents);
		
		dialogOptions.buttons = {};
		
		dialogOptions.buttons[ wp.i18n.__('Export', 'wp-layouts-td') ] = function() {
			var layoutName = $layoutNameField.val();
			if (layoutName) {
				doExport(layoutName);
			}
		};
		
		dialogOptions.buttons[ wp.i18n.__('Cancel', 'wp-layouts-td') ] = function() {
			dialog.close();
		};
		
		
		dialog = agsLayoutsDialog.create(dialogOptions);
	}
	
}

function ags_layouts_export_job_ui(postContent, screenshotContent, editor, layoutName, dialog, $dialogBody, dialogButtonsCallback, dialogCloseCallback, extraData) {
	dialogButtonsCallback({});
	
	if (dialog) {
		dialog.setPageName('export-progress');
	}

	var $statusText = jQuery('<p>').attr('id', 'ags_layout_export_status').text(wp.i18n.__('Generating layout image...', 'wp-layouts-td')).appendTo($dialogBody.empty());
	jQuery('<progress>').val(0).appendTo($dialogBody);
	
	
	var screenshotFailTimeout;
	
	var ags_layouts_after_screenshot = function(screenshotCanvas, previewHtml) {
		ags_layouts_after_screenshot = function() {
			console.log('Skipping duplicate after screenshot event');
		};
		clearTimeout(screenshotFailTimeout);
		
		if (dialog && dialog.isClosed()) {
			return;
		}
		$statusText.text(wp.i18n.__('Uploading layout contents and image...', 'wp-layouts-td'));
		var exportParams = {
			postContent: postContent,
			layoutEditor: editor,
			layoutName: layoutName
		};
		
		if (extraData) {
			exportParams.extraData = extraData;
		}
		
		if (screenshotCanvas) {
		
			if ( screenshotCanvas.startsWith && screenshotCanvas.startsWith('data:') ) {
				exportParams.screenshotData = screenshotCanvas;
			} else if (screenshotCanvas.toDataURL) {
				exportParams.screenshotData = screenshotCanvas.toDataURL();
			}
		
		}
		
		if (previewHtml && editor !== 'Divi') {
			exportParams.previewHtml = previewHtml;
		}
		
		ags_layout_export(
			exportParams,
			dialog,
			$dialogBody,
			dialogButtonsCallback,
			dialogCloseCallback
		);
	
	};
	
	if ( window.Promise && screenshotContent && screenshotContent !== -1 && ( !screenshotContent.startsWith || !screenshotContent.startsWith('data:') ) ) {
		ags_layouts_take_screenshot = function($container) {
			var targetWidth = 750, targetHeight = 900, frameWidth = $container.width();
			ags_layouts_html2canvas_callback(
				$container[0],
				{
					width: frameWidth,
					height: targetHeight / targetWidth * frameWidth,
					scale: targetWidth / frameWidth,
					onclone: function(clone) {
						var inlineSvgs = ags_layouts_HTMLCollection_to_array( clone.getElementsByTagName('svg') );
						var inlineSvgsSource = ags_layouts_HTMLCollection_to_array( document.getElementsByTagName('svg') );
						if (inlineSvgs.length === inlineSvgsSource.length) {
							for (var i = 0; i < inlineSvgs.length; ++i) {
								var $svg = jQuery(inlineSvgs[i]), $svgTempContainer = jQuery('<div>'), svgWidth = $svg.width(), svgHeight = $svg.height();
								$svgTempContainer.insertAfter($svg).append($svg);
								var svgClasses = $svg.attr('class'), svgId = $svg.attr('id');
								$svg.attr({
									id: null,
									'class': null,
									xmlns: 'http://www.w3.org/2000/svg',
									'xmlns:xlink': 'http://www.w3.org/1999/xlink',
									width: svgWidth,
									height: svgHeight,
								});
								var svgChildren = $svg[0].getElementsByTagName('*');
								var svgChildrenSource = inlineSvgsSource[i].getElementsByTagName('*');
								if (svgChildren.length === svgChildrenSource.length) {
									for (var j = 0; j < svgChildren.length; ++j) {
										var svgChildStyle = getComputedStyle(svgChildrenSource[j]);
										var $svgChildClone = jQuery(svgChildren[j]);
										for (var k = 0; k < svgChildStyle.length; ++k) {
											$svgChildClone.css(svgChildStyle[k], svgChildStyle[svgChildStyle[k]]);
										}
									}
								}
								
								try {
									jQuery('<img>')
										.attr({
											id: svgId,
											'class': svgClasses,
									'data-ags-layouts-screenshot-width': svgWidth,
									'data-ags-layouts-screenshot-height': svgHeight,
											src: 'data:image/svg+xml;base64,' + btoa(  ags_layouts_utf16to8($svgTempContainer.html())  ) // Based on https://css-tricks.com/lodge/svg/09-svg-data-uris/
										})
										.replaceAll($svgTempContainer);
								} catch (ex) {
								
								}
								
							}
						}

					}
				},
				function(screenshotCanvas) {
					ags_layouts_after_screenshot(screenshotCanvas, $container.html());
				}
			);
		};
		
		screenshotFailTimeout = setTimeout(ags_layouts_after_screenshot, 10000);
		
		
		if (screenshotContent && screenshotContent.html) {// If screenshotContent is a jQuery element, no need to use the iframe method
			ags_layouts_take_screenshot(screenshotContent);
		} else {
			jQuery('<iframe>').attr({
				id: 'ags-layouts-screenshot-frame',
				name: 'ags-layouts-screenshot-frame'
			}).appendTo($dialogBody);
			jQuery('<form>')
			.attr({
				target: 'ags-layouts-screenshot-frame',
				method: 'post',
				action: ags_layouts_admin_config.editingPostUrl
			})
			.append(jQuery('<input>')
				.attr({
					type: 'hidden',
					name: 'ags_layouts_ss',
					value: 1
				})
			)
			.append(jQuery('<input>')
				.attr({
					type: 'hidden',
					name: 'ags_layouts_ss_content',
					value: screenshotContent
				})
			)
			.append(jQuery('<input>')
				.attr({
					type: 'hidden',
					name: 'ags_layouts_ss_editor',
					value: editor
				})
			)
			.appendTo($dialogBody)
			.submit();
		}

	} else {
		ags_layouts_after_screenshot( ( screenshotContent && screenshotContent !== -1 && screenshotContent.startsWith('data:') ) ? screenshotContent : null);
	}
}

function ags_layouts_HTMLCollection_to_array(collection) {
	var cArray = [];
	for (var i = 0; i < collection.length; ++i) {
		cArray.push(collection.item(i));
	}
	return cArray;
}

function ags_layout_export(params, dialog, $dialogBody, dialogButtonsCallback, dialogCloseCallback) {
	if (dialog && dialog.isClosed()) {
		return;
	}
	params.action = 'ags_layouts_export';
	jQuery.post(ags_layouts_api_url, params, function(response) {
		if (response && response.success && response.data) {
			
			if (response.data.error) {
				var $warnings = $dialogBody.find('.ags-layouts-export-warnings');
				if (!$warnings.length) {
					$warnings = jQuery('<div class="ags-layouts-export-warnings">').insertAfter( $dialogBody.find('progress:first') );
				}
				
				switch (response.data.error) {
					case 'ImageFetch':
					case 'ImageSave':
						jQuery('<p>')
							.text( 'Unable to save file' + (response.data.errorParams && response.data.errorParams.imageUrl ? ': ' + response.data.errorParams.imageUrl : '') + '. ' )
							.append( jQuery('<a href="https://wplayouts.space/documentation/#docs-08" target="_blank">').text('(see this FAQ)') )
							.appendTo($warnings);
						break;
					default:
						jQuery('<p>').text(wp.i18n.__('An unknown error occurred: ', 'wp-layouts-td') + response.data.error).appendTo($warnings);
				}
			}
		
			if (response.data.done) {
				jQuery('#ags_layout_export_status').text(wp.i18n.__('Done!', 'wp-layouts-td'));
				$dialogBody.find('progress').val(1);
				var newButtons = {};
				newButtons[ wp.i18n.__('Close', 'wp-layouts-td') ] = function() {
					dialogCloseCallback();
				};
				
				
				dialogButtonsCallback(newButtons);
			} else {
				if (response.data.status) {
					jQuery('#ags_layout_export_status').text(response.data.status);
				}
				if (response.data.progress) {
					$dialogBody.find('progress').val(response.data.progress);
				}
				params.jobState = response.data.jobState;
				delete params.screenshotData;
				ags_layout_export(params, dialog, $dialogBody, dialogButtonsCallback, dialogCloseCallback);
			}
		} else {
			dialogCloseCallback();
			var errorMessage = wp.i18n.__('Something went wrong while exporting your layout. Please ensure that you are logged in to the WP Layouts plugin and that your site URL has not changed since last logging in, then refresh the page and try again (your security token may have expired) or contact support via https://wplayouts.space/ if the problem persists.', 'wp-layouts-td');
			if (response && response.data && response.data.error) {
				switch (response.data.error) {
					case 'quota':
						errorMessage = wp.i18n.__('The layout could not be uploaded. Your layout storage quota may be exhausted, or your plan may be expired. Please upgrade or renew your plan, or contact us if you think this message is in error.', 'wp-layouts-td');
						break;
				}
			}
			ags_layouts_message_dialog(wp.i18n.__('Error', 'wp-layouts-td'), errorMessage);
		}
	}, 'json');
}

function ags_layouts_table_grid_toggle_ui($table, dataTable, gridDefault, sortHandler) {
	var $ = jQuery;
	var $toggle = $('<div>')
					.addClass('ags-layouts-table-grid-toggle')
					.append(
						$('<span>').text(wp.i18n.__('Display:', 'wp-layouts-td'))
					);
	var $gridButton = $('<button>')
							.attr('type', 'button')
							.text(wp.i18n.__('Grid', 'wp-layouts-td'))
							.append(
								$('<span>')
									.text(wp.i18n.__('Grid', 'wp-layouts-td'))
							)
							.attr('data-ags-layouts-display-grid', 1)
							.appendTo($toggle);
	var $tableButton = $('<button>')
							.attr('type', 'button')
							.text(wp.i18n.__('Table', 'wp-layouts-td'))
							.append(
								$('<span>')
									.text(wp.i18n.__('Table', 'wp-layouts-td'))
							)
							.appendTo($toggle);
	
	var sortOptions = {
		'layoutDate-desc': wp.i18n.__('Newest first', 'wp-layouts-td'),
		'layoutDate-asc': wp.i18n.__('Oldest first', 'wp-layouts-td'),
		'layoutName-asc': wp.i18n.__('A to Z', 'wp-layouts-td'),
		'layoutName-desc': wp.i18n.__('Z to A', 'wp-layouts-td')
	};
	
	var $sortSelect = $('<select>');
	for (var sortOption in sortOptions) {
		$('<option>')
			.attr('value', sortOption)
			.text(sortOptions[sortOption])
			.appendTo($sortSelect);
	}
	$sortSelect
		.addClass('ags-layouts-sort-select')
		.change(function() {
			var sortSetting = $sortSelect.val().split('-');
			sortHandler(sortSetting[0], sortSetting[1]);
		})
		.appendTo($toggle);
	
	
	$toggle.children('button').click(function() {
		var $button = $(this);
		$button.addClass('ags-layouts-active').siblings().removeClass('ags-layouts-active');

		
		var $tableContainer = $table.closest('.ags-layouts-table');

		var currentValue = !$tableContainer.hasClass('ags-layouts-display-grid');
		var isTable = !$button.data('ags-layouts-display-grid');
		$tableContainer
			.toggleClass('ags-layouts-display-grid', !isTable)
			.toggleClass('ags-layouts-display-table', isTable);
		if (isTable !== currentValue) {
			dataTable.draw('page');
		}
	});
	
	if (gridDefault) {
		$gridButton.click();
	} else {
		$tableButton.click();
	}
	
	return $toggle;
				
}

function ags_layout_import_ui(editorName, container, tab, contentsCb, forceImportLocation, noImageDownload) {
	var dialog;
	var $ = jQuery;
	
	var dialogOptions = {
		title: wp.i18n.__('Import Layout', 'wp-layouts-td'),
		autoLoader: true,
		reverseButtonOrder: true,
		firstButtonClass: 'aspengrove-btn-primary',
		buttonClass: 'aspengrove-btn-secondary',
		pageName: 'import-list',
		buttons: {}
	};
	
	var dialogShowLoader = function() {
		if (dialog) {
			dialog.showLoader();
		}
	};
	
	var dialogHideLoader = function() {
		if (dialog) {
			dialog.hideLoader();
		}
	};
	
	var onLayoutCollectionSelect = function(layoutCollection, $tabContent, $tableContainer) {
		if (layoutCollection) {
			dialog.showLoader();
			
			$.get(ags_layouts_api_url, {
				action: 'ags_layouts_list',
				ags_layouts_collection: layoutCollection.layoutId,
				ags_layouts_editor: editorName
			}, function(response) {
				if (response.collection) {
					var $layoutCollectionView = $('<div>').addClass('ags-layouts-collection-view');
					var $layoutCollectionLayouts = $('<ol>').addClass('ags-layouts-collection-layouts');
					
					if (response.layouts.length) {
						var layouts = response.layouts;
						for (var i = 0; i < layouts.length; ++i) {
							var $item = $('<li>')
											.addClass('ags-layout-collection-link')
											.data('ags-layout-id', layouts[i].layoutId)
											.click((function(layout) {
												return function(layoutClick) {
													if (!layoutClick.target.href) {
														ags_layout_import(layout, contentsCb, editorName, dialog, $tabContent, $layoutCollectionView, forceImportLocation);
													}
												};
											})(layouts[i]))
											.appendTo($layoutCollectionLayouts),
								$itemImage = $('<span>').addClass('ags-layouts-item-image').appendTo($item),
								$itemDetails = $('<span>').addClass('ags-layouts-item-details').appendTo($item);
							
							if (layouts[i].hasLayoutImage) {
								$itemImage.css('background-image', 'url(\'' + ags_layouts_api_url + (ags_layouts_api_url.indexOf('?') === -1 ? '?' : '&') + 'action=ags_layouts_get_image&image=L&layoutId=' + layouts[i].layoutId + '\')');
							} else {
								$item.addClass('ags-layouts-no-image');
							}
							$('<span>').text(layouts[i].layoutName).appendTo($itemDetails);
							layouts[i].preview = '<a href="' + ags_layouts_admin_config.wpFrontendUrl + '?ags_layouts_preview=' + (layouts[i].layoutId * 1) + '" target="_blank" class="ags-layouts-preview-link">Live Preview</a>';
							$itemDetails.append(layouts[i].preview);
						}
					}
					
					$layoutCollectionView
						.append(
							$('<div>')
								.addClass('ags-layouts-button-back-container')
								.append(
									$('<button>')
										.addClass('ags-layouts-button-back aspengrove-btn-secondary')
										.text('Back')
										.click(function() {
											$layoutCollectionView.remove();
											dialog.setPageName('import-list');
											$tableContainer.show();
										})
								)
							
						);
					
					var $layoutCollectionInfo = $('<div>').addClass('ags-layouts-collection-info clearfix');
					
					if (response.collection.image) {
						$layoutCollectionView.addClass('ags-layouts-collection-has-image');
						$layoutCollectionInfo.append($('<img>').attr('src', response.collection.image));
					}
					
					$layoutCollectionInfo.append($('<h3>').text(response.collection.name)).append(
						$('<div>').addClass('ags-layouts-collection-description').html(response.collection.description)
					);
					
					$layoutCollectionView
						.append($layoutCollectionInfo)
						.append($layoutCollectionLayouts);
					
					$tableContainer.hide();
					dialog.setPageName('import-collection');
					$tabContent.append($layoutCollectionView);
					
				}
			}, 'json').always(function() {
				dialog.hideLoader();
			});
		}
		return 1; // Indicates selection has been handled, no further processing by DT
	};
	
	var onLayoutSelect = function(layout, $tabContent, $tableContainer) {
		if (layout) {
			ags_layout_import(layout, contentsCb, editorName, dialog, $tabContent, $tableContainer, forceImportLocation, noImageDownload);
		}
		return 1; // Indicates selection has been handled, no further processing by DT
	};
	
	if (tab) {
		var listUi = ags_layouts_list_ui(
			tab === 'layouts_my' ? -1 : 0,
			editorName,
			'50vh',
			tab === 'layouts_my' ? onLayoutSelect : onLayoutCollectionSelect,
			dialogShowLoader,
			dialogHideLoader
		);
		dialogOptions.content = listUi[0];
		dialogOptions.onLoad = listUi[1];
	} else {
		var listUiAgs = ags_layouts_list_ui(0, editorName, '50vh', onLayoutCollectionSelect, dialogShowLoader, dialogHideLoader);
		var listUiMy = ags_layouts_list_ui(-1, editorName, '50vh', onLayoutSelect, dialogShowLoader, dialogHideLoader);
		dialogOptions.tabs = {
			layouts_my: {
				name: wp.i18n.__('My Layouts', 'wp-layouts-td'),
				content: listUiMy[0],
				onLoad: listUiMy[1]
			},
			layouts_ags: {
				name: wp.i18n.__('WP Layouts', 'wp-layouts-td'),
				content: listUiAgs[0],
				onLoad: listUiAgs[1]
			}
		}
	}
	
	if (container) {
		dialogOptions.container = container;
	}
	
	dialog = agsLayoutsDialog.create(dialogOptions);
	
}

function ags_layouts_list_ui(layoutCollectionId, editorName, maxBodyHeight, itemSelectCb, loaderShowCb, loaderHideCb, allowMultiSelection, longPages) {
	var $ = jQuery,
		isLayoutCollections = !layoutCollectionId,
		tableDom = '<"ags-layouts-list-header"fl<"ags-layouts-table-grid-toggle">>t<"ags-layouts-list-footer"ip>',
		tableLanguage = {
			search: '',
			lengthMenu: '_MENU_ per page',
		},
		$content = $('<div>')
					.addClass('ags-layouts-table')
					.append($('<table>'))
					.append('<p class="wpz-aiil-message">Generate <strong>free images for your site</strong> with <a href="' + ags_layouts_admin_config.aiilUrl + '" target="_blank">the AI Image Lab plugin</a>!'),
		dataTable,
		getDataTableFunction = function() {
			return dataTable;
		};
	
	var onContentLoad = function($tabContent) {
		var $tableContainer = $tabContent.find('.ags-layouts-table:first');
		var $table = $tableContainer.find('table:first');
		
		if (loaderShowCb) {
			$table.on('preXhr.dt', loaderShowCb);
		}
		
		if (loaderHideCb) {
			$table.on('xhr.dt', loaderHideCb);
		}
		
		if (isLayoutCollections) {
			var tableColumns = [
				{
					name: 'layoutName',
					data: 'layoutName',
					title: wp.i18n.__('Layout Collection', 'wp-layouts-td'),
				},
				{
					name: 'collectionCount',
					data: 'collectionCount',
					title: wp.i18n.__('Number of Layouts', 'wp-layouts-td'),
					sortable: false
				}
			];
		} else {
			var tableColumns = [
				{
					name: 'layoutName',
					data: 'layoutName',
					title: wp.i18n.__('Layout Name', 'wp-layouts-td'),
				},
			];
			
			if (!editorName) {
				tableColumns.push({
					name: 'layoutEditor',
					data: 'layoutEditorDisplay',
					title: wp.i18n.__('Editor', 'wp-layouts-td'),
					sortable: false
				});
			}
			
			
			tableColumns.push({
				name: 'layoutDate',
				data: 'layoutDate',
				title: wp.i18n.__('Upload Date', 'wp-layouts-td'),
			});
			
			if (editorName !== 'site-importer') {
				tableColumns.push({
					name: 'preview',
					data: 'preview',
					title: wp.i18n.__('Preview', 'wp-layouts-td'),
					sortable:false,
					className: 'ags-layouts-table-preview-cell'
				});
			}
			
		}
		
		var tableErrorDialog, hasNoCollectionsAccess;
		
		var tableParams = {
			columns: tableColumns,
			serverSide: true,
			dom: tableDom,
			language: tableLanguage,
			lengthMenu: [8, 16, 48, 96],
			ajax: {
				url: ags_layouts_api_url + (ags_layouts_api_url.indexOf('?') === -1 ? '?' : '&') + 'action=ags_layouts_list' + (editorName ? '&ags_layouts_editor=' + editorName : '') + (isLayoutCollections ? '' : '&ags_layouts_collection=' + layoutCollectionId),
				dataSrc: function(response) {
					if (response.data) {
						for (var i = 0; i < response.data.length; ++i) {
							response.data[i].preview =
								( isLayoutCollections || response.data[i].layoutEditor === 'site-importer' || editorName === 'site-importer' )
								? ''
								: '<a href="' + ags_layouts_admin_config.wpFrontendUrl + '?ags_layouts_preview=' + (response.data[i].layoutId * 1) + '&layoutEditor=' + (response.data[i].layoutEditor ? response.data[i].layoutEditor : editorName) + '" target="_blank" class="ags-layouts-preview-link">Live Preview</a>';
							response.data[i].DT_RowClass = isLayoutCollections ? 'ags-layout-collection-link' : 'ags-layout-link';
							response.data[i].DT_RowData = {'ags-layout': response.data[i]};
							response.data[i].layoutEditorDisplay = ags_layouts_admin_config.editorNames[response.data[i].layoutEditor]
																	? ags_layouts_admin_config.editorNames[response.data[i].layoutEditor]
																	: ags_layouts_admin_config.editorNames['?'];
						}
						return response.data;
					}
					
					// If we reach here, something went wrong
					
					switch (response.message) {
						case 'NoCollectionsAccess':
							
							if (!hasNoCollectionsAccess) {
								hasNoCollectionsAccess = true;
								$tableContainer.hide().after(
								
									$('<div class="ags-layouts-no-collections-access">')
										.append(
											$('<h4>').text( wp.i18n.__('Sorry, this feature is only available in WP Layouts Pro.', 'wp-layouts-td') )
										)
										.append(
											$('<p>')
												.text( wp.i18n.__('WP Layouts Pro includes unlimited access to our pre-designed layouts for use in your sites! ', 'wp-layouts-td') )
												.append(
													$('<a href="https://wplayouts.space/" target="_blank">')
														.text( wp.i18n.__('Please upgrade to a Pro pricing plan to enable this feature in your WP Layouts account.', 'wp-layouts-td') )
														
												)
										)
										
								);
							}
								
							break;
						default:
							if (tableErrorDialog) {
								tableErrorDialog.close();
							}
							tableErrorDialog = ags_layouts_message_dialog(
								wp.i18n.__('Error', 'wp-layouts-td'),
								response.message ? response.message : wp.i18n.__('Something went wrong while retrieving the list of layouts. Please try again or contact support.', 'wp-layouts-td'),
								response.redirect ? 'YN' : 'O',
								response.redirect ? (function(url) { return function() { window.open(url); }; })(response.redirect) : null
							);
					}
					
					return [];
				}
			},
			select: {
				style: allowMultiSelection ? 'os' : 'single',
				info: false,
			},
			drawCallback: function(tableSettings) {
				var isGridDisplay = $tableContainer.hasClass('ags-layouts-display-grid');
				$table.find('thead:first').toggle(!isGridDisplay);
				if (tableSettings.json && tableSettings.json.data && isGridDisplay) {
					
					if (tableSettings.json.data.length) {
						var $grid = $('<ol>').addClass('ags-layouts-list-grid');
						var gridItemFactory = function(layoutId) {
							return $('<li>')
										.addClass(isLayoutCollections ? 'ags-layout-collection-link' : 'ags-layout-link')
										.data('ags-layout', layoutId)
										.click(function(layoutClick) {
											if (!layoutClick.target.href) {
												itemSelectCb(layoutId, $tabContent, $tableContainer)
												|| $(this)
													.addClass('ags-layouts-grid-item-selected')
													.siblings('.ags-layouts-grid-item-selected')
													.removeClass('ags-layouts-grid-item-selected');
											}
										})
										.appendTo($grid);
						};
					
						var layouts = tableSettings.json.data;
						for (var i = 0; i < layouts.length; ++i) {
							var $item = gridItemFactory(layouts[i]),
										$itemImage = $('<span>').addClass('ags-layouts-item-image').appendTo($item),
										$itemDetails = $('<span>').addClass('ags-layouts-item-details').appendTo($item);
							if (layouts[i].hasLayoutImage) {
								$itemImage.css('background-image', 'url(\'' + ags_layouts_api_url + (ags_layouts_api_url.indexOf('?') === -1 ? '?' : '&') + 'action=ags_layouts_get_image&image=L&layoutId=' + layouts[i].layoutId + '\')');
							} else {
								$item.addClass('ags-layouts-no-image');
							}
							$('<span>').text(layouts[i].layoutName).appendTo($itemDetails);
							if (layouts[i].preview) {
								$itemDetails.append(layouts[i].preview);
							}
							
						}
					} else {
						var $grid = $('<p>')
										.addClass('ags-layouts-list-grid-empty')
										.text(isLayoutCollections ? wp.i18n.__('Sorry, we don\'t have any layout collections for this page builder yet. Please check back later!', 'wp-layouts-td') : wp.i18n.__('There are no layouts to display.', 'wp-layouts-td'));
					}
					
					$(tableSettings.nTBody).empty().append(
						$('<tr>').append(
							$('<td>').attr('colspan', 2).append(
								$grid
							)
						)
					);
					
					return false;
				}
			},
			
		};
		
		function getColumnIndexByName(columnName) {
			for (var i = 0; i < tableParams.columns.length; ++i) {
				if (tableParams.columns[i].name === columnName) {
					return i;
				}
			}
		}
		var defaultOrdering = [getColumnIndexByName(isLayoutCollections ? 'layoutName' : 'layoutDate'), 'desc'];
		tableParams.order = [defaultOrdering];
		
		if (maxBodyHeight) {
			tableParams.scrollY = maxBodyHeight;
		}
		
		dataTable = $table.ags_layouts_DataTable(tableParams);
		
		$tableContainer
			.find('.ags-layouts-table-grid-toggle')
			.replaceWith(ags_layouts_table_grid_toggle_ui($table, dataTable, true, function(sortColumn, sortDirection) {
				var newSortSetting = [
					getColumnIndexByName(sortColumn),
					sortDirection
				];
				dataTable.order(newSortSetting);
				dataTable.ajax.reload();
			}));
		
		var isUserSelecting = false;
		
		$table.on(
			'user-select.dt',
			function(a, b, c, d, clickEvent) {
				if (clickEvent.target && itemSelectCb) {
					var $clickTarget = $(clickEvent.target);
					var $link = $clickTarget.closest('.ags-layout-link, .ags-layout-collection-link');
					
					if ($clickTarget.hasClass('ags-layouts-preview-link')) {
						var shouldSelect = false;
					} else if ($link.hasClass('selected')) {
						var shouldSelect = true;
					} else {
						// Process multi selected layouts here
						var shouldSelect = !itemSelectCb($link.data('ags-layout'), $tabContent, $tableContainer);
						if (shouldSelect) {
							isUserSelecting = true;
						}
					}
					return shouldSelect;
				}
			}
		);
		
		$table.on(
			'select.dt',
			function() {
				isUserSelecting = false;
			}
		);
		
		$table.on(
			'deselect.dt',
			function() {
				if (!isUserSelecting && itemSelectCb) {
					itemSelectCb(null, $tabContent, $tableContainer);
				}
			}
		);
		
	};

	return [$content,onContentLoad,getDataTableFunction];
	
}

function ags_layout_import(layout, contentsCb, editorName, dialog, $tabContent, $currentView, forceImportLocation, noImageDownload) {
	var $ = jQuery;
	
	$currentView.hide();	
	var $importer = $('<div>').appendTo($tabContent);
	
	var onSelectImportLocation = function(importLocation) {
		if (importLocation === 'layout-callback') {
			dialog.close();
			contentsCb(layout);
			return;
		}
		
		var importParams = {
			layoutId: layout.layoutId,
			layoutEditor: editorName,
			importLocation: importLocation
		};
		
		if (noImageDownload) {
			importParams.noImageDownload = true;
		}
		
		if (editorName === 'divi') {
			var dialogOptions = {
				title: wp.i18n.__('Import Layout', 'wp-layouts-td'),
				firstButtonClass: 'aspengrove-btn-primary',
				buttonClass: 'aspengrove-btn-secondary',
				pageName: 'import-progress',
				buttons: {},
				content: $importer
			};
			dialog = agsLayoutsDialog.create(dialogOptions);
		} else {
			dialog.setPageName('import-progress');
		}
		
		var $statusText = $('<div>').attr('id', 'ags-layouts-import-status').appendTo($importer);
		var $progress = $('<progress>').attr('id', 'ags-layouts-import-progress').val(0).appendTo($importer);
		dialog.setButtons({
			/*'Cancel': function() {
				
			},*/
		});
		
		var progressCallback = function (progress, statusText, warnings) {
			$progress.val(progress);
			$statusText.text(statusText);
			if (progress === 1) {
				dialog.close();
				if (warnings) {
					ags_layouts_message_dialog(wp.i18n.__('Import Warning(s)', 'wp-layouts-td'), warnings.join(' '));
				}
			}
		};
		
		ags_layouts_run_extra_tasks(editorName, 'import', 'before', progressCallback, function() {
		
			ags_layout_get(importParams, false, dialog, progressCallback, contentsCb);
			
		}, importParams);
		
	};
	
	if (forceImportLocation) {
		onSelectImportLocation(forceImportLocation);
	} else {
		$importer.append($('<h4>').text(wp.i18n.__('You have selected the layout "${layoutName}" for import.', 'wp-layouts-td').replace('${layoutName}', layout.layoutName)));
		
		if (editorName === 'divi') {
			$('<p>')
				.addClass('ags-layouts-notification ags-layouts-notification-info')
				.text(wp.i18n.__('Any unsaved content in the Divi builder will be saved prior to import (unless you choose to replace existing content). Any unsaved changes you have made outside of the Divi builder will be lost.', 'wp-layouts-td'))
				.appendTo($importer);
		}
		
		var importLocations = {
			'above': wp.i18n.__('Insert the layout above any existing content', 'wp-layouts-td'),
			'below': wp.i18n.__('Insert the layout below any existing content', 'wp-layouts-td'),
			'replace': wp.i18n.__('Replace any existing content with the layout', 'wp-layouts-td')
		};
		
		var $importLocationOptions = $('<div>').addClass('ags-layout-import-location-options');
		var radioAttrs = {
			type: 'radio',
			name: 'ags_layout_import_location',
			checked: true
		};
		for (var importLocation in importLocations) {
			radioAttrs.value = importLocation;
			$('<label>')
				.text(importLocations[importLocation])
				.prepend(
					$('<input>')
						.attr(radioAttrs)
				)
				.appendTo($importLocationOptions);
			delete radioAttrs.checked;
		}
		
		$importLocationOptions.appendTo($importer);
		
		
		dialog.setPageName('import-options');
		dialog.setButtons({
			'Import': function() {
				var selectedImportLocation = $importLocationOptions.find('input:checked').val();
				$importLocationOptions.siblings('p').remove();
				$importLocationOptions.remove();
				onSelectImportLocation(selectedImportLocation);
			},
			'Cancel': function() {
				dialog.setButtons({});
				$importer.remove();
				dialog.setPageName('import-list');
				$currentView.show();
				//dialog.showTabs();
			},
		});
	}
	
}

function ags_layout_get(params, existingJob, dialog, progressCb, contentsCb) {
	if (dialog && dialog.isClosed()) {
		return;
	}
	params.action = 'ags_layouts_get';
	params.postId = window.ags_layouts_admin_config.editingPostId;
	if (existingJob) {
		delete params.newJob;
	} else {
		params.newJob = 1;
		if (progressCb) {
			progressCb(0, wp.i18n.__('Starting import...', 'wp-layouts-td'));
		}
	}
	
	jQuery.get(
		ags_layouts_api_url,
		params,
		function(response) {
			
			if (response && response.success && response.data && response.data.status) {
				if (progressCb) {
					progressCb(response.data.progress, response.data.status, response.data.warnings);
				}
				if (response.data.done) {
				
					ags_layouts_run_extra_tasks(params.layoutEditor, 'import', 'after', progressCb, function() {
						if (contentsCb) {
							contentsCb(response.data.layoutContents, params);
						} else {
							location.reload();
						}
					});
					
				} else {
					ags_layout_get(params, true, dialog, progressCb, contentsCb);
				}
			} else {
				// This is temporary until we can implement a better error message
				alert(
					ags_layouts_admin_config.layoutDownloadError
						? ags_layouts_admin_config.layoutDownloadError
						: wp.i18n.__('Something went wrong while retrieving the layout. Your security token may have expired; please try refreshing the page and try again. If the problem persists, please contact WP Layouts support via', 'wp-layouts-td') + ' https://wplayouts.space/'
				);
			}
		},
		'json'
	)
	.fail(function() {
		
		// This is temporary until we can implement a better error message
		alert(
			ags_layouts_admin_config.layoutDownloadError
				? ags_layouts_admin_config.layoutDownloadError
				: wp.i18n.__('Something went wrong while retrieving the layout. Your security token may have expired; please try refreshing the page and try again. If the problem persists, please contact WP Layouts support via', 'wp-layouts-td') + ' https://wplayouts.space/'
		);
		
	});
}

function ags_layouts_register_extra_task(taskName, callback, editorName, operation, order) {
	/*
		Valid argument values:
			- operation: import, export*
			- order: before, after*
		(* not yet implemented)
	*/

	if (!ags_layouts_extra_tasks) {
		ags_layouts_extra_tasks = {};
	}
	if (!ags_layouts_extra_tasks[editorName]) {
		ags_layouts_extra_tasks[editorName] = {};
	}
	if (!ags_layouts_extra_tasks[editorName][operation]) {
		ags_layouts_extra_tasks[editorName][operation] = {};
	}
	if (!ags_layouts_extra_tasks[editorName][operation][order]) {
		ags_layouts_extra_tasks[editorName][operation][order] = [];
	}
	
	ags_layouts_extra_tasks[editorName][operation][order].push({
		name: taskName,
		callback: callback
	});
}

function ags_layouts_run_extra_tasks(editorName, operation, order, progressCallback, doneCallback, taskData, _index) {
	_index = _index ? _index : 0;
	if (ags_layouts_extra_tasks && ags_layouts_extra_tasks[editorName]
			&& ags_layouts_extra_tasks[editorName][operation] && ags_layouts_extra_tasks[editorName][operation][order]
			&& ags_layouts_extra_tasks[editorName][operation][order].length > _index) {
		var currentTask = ags_layouts_extra_tasks[editorName][operation][order][_index];
		progressCallback(order === 'after' ? 1 : 0, currentTask.name + '...');
		currentTask.callback(function() {
			ags_layouts_run_extra_tasks(editorName, operation, order, progressCallback, doneCallback, taskData, ++_index);
		}, taskData);
	} else {
		doneCallback();
	}
}

/* The following functions contain code from WP and Divi Icons Pro (fb.js) by Aspen Grove Studios */
function ags_layouts_onCreateElementWithId(id, callback) {
	var MO = window.MutationObserver ? window.MutationObserver : window.WebkitMutationObserver;
	if (MO) {
		(new MO(function(events) {
			jQuery.each(events, function(i, event) {
				if (event.addedNodes && event.addedNodes.length) {
					jQuery.each(event.addedNodes, function(i, node) {
						if (node.id === id) {
							callback(node);
						}
					});
				}
			});
		})).observe(document.body, {childList: true, subtree: true});
	}
}
function ags_layouts_onCreateElementWithClass(className, callback) {
	var MO = window.MutationObserver ? window.MutationObserver : window.WebkitMutationObserver;
	if (MO) {
		(new MO(function(events) {
			jQuery.each(events, function(i, event) {
				if (event.addedNodes && event.addedNodes.length) {
					jQuery.each(event.addedNodes, function(i, node) {
						if (typeof node.className === 'string'
								&& ( node.className === className
									|| node.className.indexOf( ' ' + className + ' ') !== -1
									|| node.className.substring(0, className.length + 1) === className + ' '
									|| node.className.substring( node.className.length - ( className.length + 1 ) ) === ' ' + className
								)
						) {
							callback(node);
						}
					});
				}
			});
		})).observe(document.body, {childList: true, subtree: true});
	}
}
function ags_layouts_onElementClassAdded(element, className, callback) {
	var MO = window.MutationObserver ? window.MutationObserver : window.WebkitMutationObserver;
	if (MO) {
		var thisMO = new MO(function() {
			if (
				element.className.indexOf(' ' + className + ' ') !== -1
				|| element.className.startsWith(className + ' ')
				|| element.className.endsWith(' ' + className)
				|| element.className === className
			) {
				thisMO.disconnect();
				callback(element);
			}
			
		});
		thisMO.observe(element, {attributes: true, attributeFilter: ['class']});
	}
}
/* End functions with WP and Divi Icons Pro code */

/* utf.js - UTF-8 <=> UTF-16 convertion
 *
 * Copyright (C) 1999 Masanao Izumo <iz@onicos.co.jp>
 * Version: 1.0
 * LastModified: Dec 25 1999
 * This library is free.  You can redistribute it and/or modify it.
 */

/*
 * Interfaces:
 * utf8 = utf16to8(utf16);
 * utf16 = utf16to8(utf8);
 */

function ags_layouts_utf16to8(str) {
    var out, i, len, c;

    out = "";
    len = str.length;
    for(i = 0; i < len; i++) {
	c = str.charCodeAt(i);
	if ((c >= 0x0001) && (c <= 0x007F)) {
	    out += str.charAt(i);
	} else if (c > 0x07FF) {
	    out += String.fromCharCode(0xE0 | ((c >> 12) & 0x0F));
	    out += String.fromCharCode(0x80 | ((c >>  6) & 0x3F));
	    out += String.fromCharCode(0x80 | ((c >>  0) & 0x3F));
	} else {
	    out += String.fromCharCode(0xC0 | ((c >>  6) & 0x1F));
	    out += String.fromCharCode(0x80 | ((c >>  0) & 0x3F));
	}
    }
    return out;
}

function ags_layouts_utf8to16(str) {
    var out, i, len, c;
    var char2, char3;

    out = "";
    len = str.length;
    i = 0;
    while(i < len) {
	c = str.charCodeAt(i++);
	switch(c >> 4)
	{ 
	  case 0: case 1: case 2: case 3: case 4: case 5: case 6: case 7:
	    // 0xxxxxxx
	    out += str.charAt(i-1);
	    break;
	  case 12: case 13:
	    // 110x xxxx   10xx xxxx
	    char2 = str.charCodeAt(i++);
	    out += String.fromCharCode(((c & 0x1F) << 6) | (char2 & 0x3F));
	    break;
	  case 14:
	    // 1110 xxxx  10xx xxxx  10xx xxxx
	    char2 = str.charCodeAt(i++);
	    char3 = str.charCodeAt(i++);
	    out += String.fromCharCode(((c & 0x0F) << 12) |
					   ((char2 & 0x3F) << 6) |
					   ((char3 & 0x3F) << 0));
	    break;
	}
    }

    return out;
}

/* End utf.js */
