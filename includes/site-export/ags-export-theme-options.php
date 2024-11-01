<?php
/**
 * Contains code based on AGS Export Theme Options plugin by Aspen Grove Studios
 * See the license.txt file in the WP Layouts plugin root directory for more information and licenses
 *
 */

define('AGSXTO_DEBUG', false);

function ags_layouts_xto_export( $themeOptions=true, $pluginOptions=array() ) {
	global $pagenow;

	// Options to export
	$options = array();
	
	if ($themeOptions) {
		// Theme
		$agsxto_theme = get_option('template');
		$agsxto_theme_child = get_option('stylesheet');
	
		if (!in_array($agsxto_theme, array('Divi', 'Extra'))) {
			wp_die('The current theme is not supported for options export.');
		}

		// Exclude option keys copied from dev site database
		
		$options['theme_mods_'.$agsxto_theme_child] = array(
			'exclude' => array(0, 'sidebars_widgets', 'et_pb_css_synced', 'custom_css_post_id', 'et_updated_layouts_built_for_post_types', 'nav_menu_locations'), 
			//'missing_delete' => array('divi_main_accent_color','divi_second_accent_color','divi_child_header_font_color','divi_child_body_font_color',)
		);
		$options['et_'.strtolower($agsxto_theme)] = array(
			'exclude' => array(
				'static_css_custom_css_safety_check_done', '2_5_flush_rewrite_rules', 'et_flush_rewrite_rules_library', 
				'divi_previous_installed_version', 'divi_latest_installed_version', 'divi_email_provider_credentials_migrated',
				'divi_1_3_images', 'et_pb_layouts_updated', 'library_removed_legacy_layouts', 'divi_2_4_documentation_message',
				'product_tour_status', 'builder_global_presets', 'builder_global_presets_history'
			), 
			//'missing_delete' => array('primary_nav_dropdown_line_color','primary_nav_dropdown_link_color',)
		);
	}
	
	
	if ($pluginOptions) {
		
		foreach ( (array) $pluginOptions as $plugin ) {
			switch ($plugin) {
				case 'TheEventsCalendar':
					$options['tribe_events_calendar_options'] = array('google_maps_js_api_key','last-update-message-the-events-calendar');
					break;
			}
		}
		
	}
	
	// Variables to substitute in option values: string => variable name
	$variables = array(
		get_option('siteurl') => 'siteurl' // Trailing slash is automatically removed by WP in get_option()
	);

	$export = array();
	foreach ($options as $option => $optionParams) {
		$exportOption = get_option($option);
		
		if (isset($optionParams['exclude'])) {
			foreach ($optionParams['exclude'] as $subOption) {
				unset($exportOption[$subOption]);
			}
		}
		
		if ($option == 'theme_mods_'.$agsxto_theme_child && isset($exportOption['nav_menu_locations'])) {
			foreach ($exportOption['nav_menu_locations'] as $locationName => $locationMenu) {
				if (!empty($locationMenu)) {
					$locationMenu = wp_get_nav_menu_object($locationMenu);
					$exportOption['nav_menu_locations'][$locationName] = (empty($locationMenu->slug) ? '' : $locationMenu->slug);
				}
			}
		}
		
		// Substitute variables
		$export[$option] = array('value' => ags_layouts_xto_substitute_variables($exportOption, $variables));
		
		if (!empty($optionParams['missing_delete'])) {
			$delete = array();
			foreach ($optionParams['missing_delete'] as $optionField) {
				if (!isset($export[$option]['value'][$optionField])) {
					$delete[] = $optionField;
				}
			}
			if (!empty($delete)) {
				$export[$option]['delete'] = $delete;
			}
		}
		
	}
	
	header('Content-Type: text/plain');
	if (AGSXTO_DEBUG) {
		print_r($export);
	} else {
		header('Content-Disposition: attachment; filename="theme_options.dat"');
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- base 64 encoded string
		echo( base64_encode( serialize($export) ) );
	}
	exit;
}

function ags_layouts_xto_substitute_variables($options, $variables) {
	foreach ($options as $key => $value) {
		if (is_array($value)) {
			$options[$key] = ags_layouts_xto_substitute_variables($value, $variables);
		} else if (is_string($value)) {
			foreach ($variables as $string => $variableName) {
				$options[$key] = str_replace($string, '{{ags.'.$variableName.'}}', $value);
			}
		}
	}
	return $options;
}
