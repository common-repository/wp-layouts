<?php
/**
 * This file contains third-party code
 * See the license.txt file in the WP Layouts plugin root directory for more information and licenses.
 */

AGSLayouts::VERSION; // Access control

include __DIR__.'/aspen-importer.php';
$importer = new AGS_Theme_Importer();

// will be escaped later before output:
$button_text = ( AGSLayouts::IS_PACKAGED_LAYOUT || AGSLayouts::isDemoImportPage() ) ? AGSLayouts::getPackagedLayoutConfig('buttonText') : __('Import Demo Data', 'wp-layouts-td');
?>

<div class="radium-importer-wrap" id="wpl-import-demo-wrapper" data-demo-id="1">

	<form id="ags-demo-importer-form" method="post">
	
		<?php if (AGSLayouts::IS_PACKAGED_LAYOUT || AGSLayouts::isDemoImportPage()) { ?>
			<div id="ags-layouts-site-import-intro">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- layout config HTML strings were escaped previously
				echo AGSLayouts::getPackagedLayoutConfig('step2Html');
				?>
			</div>
		<?php } else { ?>
		
		<p>
			1. <?php esc_html_e('Before you begin, make sure all the required', 'wp-layouts-td'); ?>
			<strong> <?php esc_html_e('plugins are activated', 'wp-layouts-td'); ?>. </strong>
			(<em> <?php esc_html_e('if applicable', 'wp-layouts-td'); ?> </em>)
		</p>
		<p>
			2. <?php esc_html_e('Please click the import button once and wait for the process to complete.', 'wp-layouts-td'); ?>
			<?php esc_html_e('Please do not navigate away from this page until the import is complete.', 'wp-layouts-td'); ?>
		</p>
		
		<?php } ?>
		
		<?php wp_nonce_field('ags-layouts-site-import-ajax', 'ags-layouts-site-import-ajax-none'); ?>

		
		
		<div id="ags-demo-importer-options">
		
			<label class="ags-import-demo-label-all">
				<input type="checkbox" id="ags-import-demo-tasks-all" checked>
				<?php esc_html_e('Check/uncheck all', 'wp-layouts-td'); ?>
			</label>

			<?php if (!empty($importer->content_demo)) { ?>
			<label>
				<input type="checkbox" name="demo-tasks[]" value="content_posts" checked>
				<?php esc_html_e('Content: pages, blog posts, menu items, etc.', 'wp-layouts-td'); ?>
			</label>
			<label>
			<input type="checkbox" name="demo-tasks[]" value="content_attachments" checked>
				<?php esc_html_e('Images', 'wp-layouts-td'); ?>
			</label>
			
			<?php if ( !empty($importer->config['hasThemeBuilderTemplates']) ) { ?>
			<label>
			<input type="checkbox" name="demo-tasks[]" value="content_themebuilder" checked>
				<?php esc_html_e('Theme builder templates', 'wp-layouts-td'); ?>
			</label>
			<?php } ?>
			
			<label>
			<input type="checkbox" name="demo-tasks[]" value="content_categories" checked>
				<?php esc_html_e('Blog post categories', 'wp-layouts-td'); ?>
			</label>
			<label>
			<input type="checkbox" name="demo-tasks[]" value="content_tags" checked>
				<?php esc_html_e('Blog post tags', 'wp-layouts-td'); ?>
			</label>
			<label>
			<input type="checkbox" name="demo-tasks[]" value="content_terms" checked>
				<?php esc_html_e('Menus & taxonomy terms', 'wp-layouts-td'); ?>
			</label>
			<?php
			}


			if (!empty($importer->theme_options)) {
			?>
			<label>
			<input type="checkbox" name="demo-tasks[]" value="theme_options" checked>
				<?php esc_html_e('Theme/plugin options', 'wp-layouts-td'); ?>
			</label>
			<?php
			}
			
			
			if (!empty($importer->divi_module_presets)) {
			?>
			<label>
			<input type="checkbox" name="demo-tasks[]" value="divi_module_presets" checked>
				<?php esc_html_e('Divi builder module presets', 'wp-layouts-td'); ?>
			</label>
			<?php
			}


			?>
			<label>
			<input type="checkbox" name="demo-tasks[]" value="set_menus" checked>
				<?php esc_html_e('Menu location assignments', 'wp-layouts-td'); ?>
			</label>
			<?php


			if (!empty($importer->widgets)) {
			?>
			<label>
			<input type="checkbox" name="demo-tasks[]" value="widgets" checked>
				<?php esc_html_e('Widgets', 'wp-layouts-td'); ?>
			</label><?php
			}

			if ( !empty($importer->caldera_forms) ) {
			?>

			<label>
			<input type="checkbox" name="demo-tasks[]" value="caldera_forms" checked>
				<?php esc_html_e('Caldera Forms', 'wp-layouts-td'); ?>
			</label>
			<?php
			}

			?>
		</div>
		
		<input id="ags-theme-demo-import-button" name="reset" class="panel-save button-primary radium-import-start" type="submit"
			   value="<?php echo esc_attr($button_text); ?>"/>

	</form>
	<div id="ags-demo-importer-status">
		<div id="ags-demo-importer-status-inprogress">
		<?php
		if ( AGSLayouts::IS_PACKAGED_LAYOUT || AGSLayouts::isDemoImportPage() ) {
			echo esc_html( AGSLayouts::getPackagedLayoutConfig('progressHeading') );
		} else {
			esc_html_e('Importing Demo Data...', 'wp-layouts-td');
		}
		?>
		</div>
		
		<div id="ags-demo-importer-status-complete">
		<?php
		if ( AGSLayouts::IS_PACKAGED_LAYOUT || AGSLayouts::isDemoImportPage() ) {
			echo esc_html( AGSLayouts::getPackagedLayoutConfig('completeHeading') );
		} else {
			esc_html_e('Import Complete!', 'wp-layouts-td');
		}
		?>
		</div>

		<div id="ags-demo-importer-progress">
			<strong></strong>
		</div>
		
		<div id="ags-demo-importer-complete-message">
		<?php
		if ( AGSLayouts::IS_PACKAGED_LAYOUT || AGSLayouts::isDemoImportPage() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- layout config HTML strings were escaped previously
			echo AGSLayouts::getPackagedLayoutConfig('successHtml');
		} else {
		?>
		
			<?php esc_html_e('Enjoy your new child theme!', 'wp-layouts-td'); ?>
			<br/><?php esc_html_e('Please check to make sure that the import was successful.', 'wp-layouts-td'); ?>
			
		<?php } ?>
		</div>
		
		<div id="ags-demo-importer-error-message">
		<?php if ( AGSLayouts::IS_PACKAGED_LAYOUT || AGSLayouts::isDemoImportPage() ) { ?>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- layout config HTML strings were escaped previously
			echo AGSLayouts::getPackagedLayoutConfig('errorHtml');
			?>
		<?php } else { ?>
		
			<?php esc_html_e('Something went wrong.', 'wp-layouts-td'); ?><br/>
			<?php esc_html_e('Unfortunately, demo data import could not be completed due to an error. Please try again.', 'wp-layouts-td'); ?>
			
		<?php } ?>
		</div>
		
		<div id="ags-demo-importer-messages"></div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	$('#ags-demo-importer-form').submit(function() {
		$('#ags-demo-importer-form, #ags-demo-importer-status-complete, #ags-demo-importer-complete-message, #ags-demo-importer-error-message').hide();
		$('#ags-demo-importer-status-inprogress').show();
		$('#ags-demo-importer-messages').empty().show();
		
		
		if ($('#ags-demo-importer-progress').data('circle-progress')) {
			$('#ags-demo-importer-progress').replaceWith('<div id="ags-demo-importer-progress"><strong></strong></div>');
		}
		$('#ags-demo-importer-progress')
			.circleProgress()
			.on('circle-animation-progress', function(ev, animationProgress, value) {
				$(this).children('strong').text(Math.round(value * 100) + '%');
			});
		$('#ags-demo-importer-status').show();
		
		var formData = $('#ags-demo-importer-form').serializeArray();
		$('#ags-demo-importer-form :checkbox, #ags-theme-demo-import-button').attr('disabled', true);
		importStep(formData, Math.floor( Math.random() * 1000000) + 1, 'init', 'done');
		
		return false;
	});
	
	$('#ags-demo-importer-form :checkbox:not(#ags-import-demo-tasks-all)').change(function() {
		$('#ags-theme-demo-import-button').attr('disabled', !$('#ags-demo-importer-form :checkbox:not(#ags-import-demo-tasks-all):checked:first').length);
		$('#ags-import-demo-tasks-all').prop('checked', !$('#ags-demo-importer-form :checkbox:not(#ags-import-demo-tasks-all):not(:checked):first').length);
	});
	
	
	$('#ags-import-demo-tasks-all').change(function() {
		var isChecked = $('#ags-import-demo-tasks-all').prop('checked');
		$('#ags-theme-demo-import-button').attr('disabled', !isChecked);
		$('#ags-demo-importer-form :checkbox:not(#ags-import-demo-tasks-all)').prop('checked', isChecked);
	});
	
	function importStep(formData, pid, task, taskState, retry) {
		
		$.post(
			ajaxurl,
			[
				{name: 'action', value: 'ags_layouts_site_import'},
				{name: 'pid', value: pid},
				{name: 'task', value: task},
				{name: 'taskState', value: taskState}
			].concat(formData),
			function(response) {
		
				if (response && response.progress) {
					
					$('#ags-demo-importer-progress').circleProgress('value', response.progress);
					
					if (retry) {
						
						var retryMessage = <?php echo( wp_json_encode( __('An item or step of the import was attempted multiple times due to an error. This may result in duplicate content in the import.', 'wp-layouts-td') ) ); ?>;
						
						if (response.messages && response.messages.length) {
							response.messages.push(retryMessage);
						} else {
							response.messages = [ retryMessage ];
						}
						
					}
					
					if (response.messages && response.messages.length) {
						var $messages = $('#ags-demo-importer-messages');
						$.each(response.messages, function(i, message) {
							$messages
								.append(
									$('<span>').text(message)
								)
								.append(
									'<br>'
								);
						});
					}
					
					if (response.progress === 1) {
						if (response.state && response.state.task && response.state.task === 'error') {
							importError();
						}
					
						$('#ags-demo-importer-status-complete').show();
						$('#ags-demo-importer-status-inprogress').hide();
						$('#ags-demo-importer-complete-message').slideDown();
						$('#ags-demo-importer-form :checkbox, #ags-theme-demo-import-button').attr('disabled', false);
						
						return;
					}
					
					if (response.state && response.state.task && response.state.taskState) {
						importStep(formData, pid, response.state.task, response.state.taskState);
					} else {
						importError();
					}
					
				} else {
					importError();
				}
				
				
			},
			'json'
		).fail(function() {
			
			if ( !retry || retry < 3 ) {
				setTimeout(function() {
					importStep(formData, pid, task, taskState, retry ? ++retry : 1);
				}, 1000);
			} else {
				importError();
			}
			
		});
	}
	
	function importError() {
		$('#ags-demo-importer-error-message').slideDown();
		$('#ags-demo-importer-form :checkbox, #ags-theme-demo-import-button').attr('disabled', false);
	}
	
});
</script>