<?php
AGSLayouts::VERSION; // Access control

class AGSLayoutsExportAjax {
	
	static function setup() {
		
		add_action('wp_ajax_ags_layouts_export', array('AGSLayoutsExportAjax', 'exportLayout'));
		add_action('wp_ajax_ags_layouts_update', array('AGSLayoutsExportAjax', 'updateLayout'));
		add_action('wp_ajax_ags_layouts_delete', array('AGSLayoutsExportAjax', 'deleteLayout'));
		add_action('wp_ajax_ags_layouts_list', array('AGSLayoutsExportAjax', 'listLayouts'));
		add_action('wp_ajax_ags_layouts_package', array('AGSLayoutsExportAjax', 'package'));
		add_action('wp_ajax_ags_layouts_get_widgets_export', array('AGSLayoutsExportAjax', 'getWidgetsExport'));
		add_action('wp_ajax_ags_layouts_get_caldera_forms_export', array('AGSLayoutsExportAjax', 'getCalderaFormsExport'));
		add_action('wp_ajax_ags_layouts_get_theme_plugin_options_export', array('AGSLayoutsExportAjax', 'getThemePluginOptionsExport'));
		add_action('wp_ajax_ags_layouts_get_menu_assignments_export', array('AGSLayoutsExportAjax', 'getMenuAssignmentsExport'));
		add_action('wp_ajax_ags_layouts_get_divi_module_presets_export', array('AGSLayoutsExportAjax', 'getDiviModulePresetsExport'));
		
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- not making any database changes (etc.) that need CSRF protection
		if (!empty($_POST['ags_layouts_ss']) && !empty($_POST['ags_layouts_ss_content']) && !empty($_POST['ags_layouts_ss_editor'])) {
			AGSLayouts::$isDoingLayoutImage=true;
			add_filter('the_content', array('AGSLayoutsExportAjax', 'filterContentForScreenshot'));
		}

		// Following filter is required here by the Divi integration
		add_filter('et_builder_load_requests', function($loadParameters) {
			if (isset($loadParameters['action'])) {
				$loadParameters['action'][] = 'ags_layouts_export';
			} else {
				$loadParameters['action'] = array('ags_layouts_export');
			}
			return $loadParameters;
		});
		
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- not making any database changes (etc.) that need CSRF protection
		if ( !empty($_GET['agslayouts']) ) {
			add_action('export_wp', array('AGSLayoutsExportAjax', 'onWpExport'));
		}
	}
	
	public static function exportLayout() {
		include(AGSLayouts::$pluginDirectory.'includes/uploader.php');
		exit;
	}
	
	public static function updateLayout() {
		include(AGSLayouts::$pluginDirectory.'includes/update.php');
		exit;
	}
	
	public static function deleteLayout() {
		include(AGSLayouts::$pluginDirectory.'includes/delete.php');
		exit;
	}
	
	public static function listLayouts() {
		include(AGSLayouts::$pluginDirectory.'includes/list.php');
		exit;
	}
	
	public static function package() {
		include(AGSLayouts::$pluginDirectory.'includes/packager.php');
		exit;
	}
	
	public static function getWidgetsExport() {
		if ( current_user_can(AGSLayouts::SITE_EXPORT_IMPORT_CAP) ) {
			check_ajax_referer('ags-layouts-site-export-ajax', 'ags_layouts_nonce');
			include(AGSLayouts::$pluginDirectory.'includes/site-export/widgets-export.php');
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded output
			echo ags_layouts_wie_generate_export_data();
		}
		
		exit;
	}
	
	public static function getCalderaFormsExport() {
		if ( current_user_can(AGSLayouts::SITE_EXPORT_IMPORT_CAP) && isset($_POST['formIds']) && is_array($_POST['formIds']) ) {
			check_ajax_referer('ags-layouts-site-export-ajax', 'ags_layouts_nonce');
			include(AGSLayouts::$pluginDirectory.'includes/site-export/caldera.php');
			
			$allFormIds = array_keys( AGSLayoutsSiteExportCalderaForms::getForms() );
			
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized -- POST variable is checked against a set of known values
			echo AGSLayoutsSiteExportCalderaForms::exportForms( array_intersect($_POST['formIds'], $allFormIds) );
		}
		
		exit;
	}
	
	public static function getThemePluginOptionsExport() {
		if ( current_user_can(AGSLayouts::SITE_EXPORT_IMPORT_CAP) ) {
			check_ajax_referer('ags-layouts-site-export-ajax', 'ags_layouts_nonce');
			include(AGSLayouts::$pluginDirectory.'includes/site-export/ags-export-theme-options.php');
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized -- base 64 encoded string output; pluginOptions variable is checked against fixed value(s)
			echo ags_layouts_xto_export( !empty($_POST['themeOptions']), empty($_POST['pluginOptions']) ? array() : $_POST['pluginOptions'] );
		}
		
		exit;
	}
	
	public static function getMenuAssignmentsExport() {
		if ( current_user_can(AGSLayouts::SITE_EXPORT_IMPORT_CAP) ) {
			check_ajax_referer('ags-layouts-site-export-ajax', 'ags_layouts_nonce');
			
			$menuAssignments = get_theme_mod('nav_menu_locations');
			foreach ($menuAssignments as $location => &$id) {
				if (!$id) {
					unset($menuAssignments[$location]);
				}
				
				$menu = get_term($id);
				
				if ( empty($menu->name) ) {
					unset($menuAssignments[$location]);
					continue;
				}
				$id = $menu->name;
				
			}
			
			echo json_encode($menuAssignments);
		}
		
		exit;
	}
	
	public static function getDiviModulePresetsExport() {
		if ( current_user_can(AGSLayouts::SITE_EXPORT_IMPORT_CAP) && function_exists('et_get_option') ) {
			check_ajax_referer('ags-layouts-site-export-ajax', 'ags_layouts_nonce');
			
			$presetData = et_get_option( 'builder_global_presets' );
			$presets = [];
			if ($presetData) {
				foreach ($presetData as $module => $modulePresetData) {
					if (!empty($modulePresetData->presets)) {
						$presets[$module] = (array) $modulePresetData->presets;
						
						unset($presets[$module]['_initial']);
						
						if ($presets[$module]) {
							foreach ($presets[$module] as &$preset) {
								unset($preset->created, $preset->updated);
							}
						} else {
							unset($presets[$module]);
						}
					}
				}
			}
			
			$response = [];
			
			if ($presets) {
				$response['presets'] = $presets;
				
				$colors = et_get_option( 'et_global_colors' );
				
				if ($colors) {
					$response['colors'] = $colors;
				}
			}
			
			echo( json_encode( $response ) );
		}
		
		exit;
	}
	
	public static function filterContentForScreenshot() {
		// phpcs:ignore ET.Sniffs.ValidatedSanitizedInput.InputNotValidated, ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- ags_layouts_ss_content is checked to be defined before this function is hooked; input run through wp_kses_post() later; no CSRF risk due to no persistent changes made etc.
		$layoutContents = wp_unslash($_POST['ags_layouts_ss_content']);
		
		// Switch statement copied from includes/uploader.php (modified)
		
		// phpcs:ignore ET.Sniffs.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.NonceVerification.Missing -- ags_layouts_ss_editor is checked to be defined before this function is hooked; no CSRF risk due to no persistent changes made etc.
		switch ($_POST['ags_layouts_ss_editor']) {
			case 'divi':
				// This is very similar to how Divi does it but developed independently :)
				$layoutContents = json_decode($layoutContents, true);
				if (empty($layoutContents)) {
					return;
				}
				$layoutContents = et_fb_process_to_shortcode($layoutContents);
				break;
			case 'gutenberg':
				break;
		}
		
		apply_filters('ags_layouts_screenshot_content_unfiltered', $layoutContents);
		
		remove_filter('the_content', array('AGSLayoutsExportAjax', 'filterContentForScreenshot'));
		$layoutContents = apply_filters('the_content', $layoutContents);
		add_filter('the_content', array('AGSLayoutsExportAjax', 'filterContentForScreenshot'));
		
		apply_filters('ags_layouts_screenshot_content_filtered', $layoutContents);
		
		
		return '
			<div class="ags_layouts_screenshot_container"></div>
			<script>
			var agsLayoutsScreenshotHasRun = false;
			jQuery(document).ready(function($) {
				if (!agsLayoutsScreenshotHasRun) {
					agsLayoutsScreenshotHasRun = true;
					var widthMax = 0;
					var $widestContainer;
					var $containers = $(\'.ags_layouts_screenshot_container\');
					for (var i = 0; i < $containers.length; ++i) {
						var $container = $($containers[i]);
						if ($container.width() > widthMax) {
							widthMax = $container.width();
							$widestContainer = $container;
						}
					}
					$widestContainer.html('.json_encode(wp_kses_post($layoutContents), 1, JSON_HEX_TAG & JSON_HEX_AMP & JSON_HEX_APOS & JSON_HEX_QUOT).');
					window.parent.ags_layouts_take_screenshot($widestContainer);
				}
			});
			</script>
		';
	}
	
	
	public static function onWpExport() {
		add_filter('query', [__CLASS__, 'filterExportQuery']);
		ob_start( array('AGSLayoutsExportAjax', 'processWpExportOutput') );
	}
	
	public static function processWpExportOutput($str) {
		return html_entity_decode( htmlentities($str, ENT_XML1 | ENT_IGNORE, 'UTF-8'), ENT_XML1, 'UTF-8' );
	}
	
	public static function filterExportQuery($query) {
		global $wpdb;
		$prefix = 'SELECT ID FROM '.$wpdb->posts.' ';
		if (substr($query, 0, strlen($prefix)) != $prefix) {
			return $query;
		}
		remove_filter('query', [__CLASS__, 'filterExportQuery']);
		
		// Don't export trashed posts
		$query .= ' AND '.$wpdb->posts.'.post_status != "trash"';
		
		// Only export theme builder layouts that are linked to a non-trashed template
		$query .= ' AND ('.$wpdb->posts.'.post_type != "et_template" OR '.$wpdb->posts.'.ID NOT IN (
						SELECT post_id FROM '.$wpdb->postmeta.' tbpm3
						WHERE tbpm3.meta_key="_et_theme_builder_marked_as_unused"
					) )
					AND ('.$wpdb->posts.'.post_type NOT IN ("et_header_layout", "et_body_layout", "et_footer_layout") OR '.$wpdb->posts.'.ID IN (
						SELECT tbpm.meta_value FROM '.$wpdb->postmeta.' tbpm
						JOIN '.$wpdb->posts.' tbp ON (tbp.ID=tbpm.post_id)
						WHERE tbpm.meta_key IN("_et_header_layout_id", "_et_body_layout_id", "_et_footer_layout_id")
							AND tbp.post_type = "et_template" AND tbp.post_status != "trash" AND tbpm.meta_value != "0"
							AND NOT EXISTS(
								SELECT 1 FROM '.$wpdb->postmeta.' tbpm2
								WHERE tbpm2.post_id=tbp.ID AND tbpm2.meta_key="_et_theme_builder_marked_as_unused"
							)
					) )';
		
		return $query;
	}
	
}

AGSLayoutsExportAjax::setup();