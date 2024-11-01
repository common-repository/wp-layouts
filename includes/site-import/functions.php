<?php
/**
 * Contains code copied from and/or based on third-party code
 * See the license.txt file in the WP Layouts plugin root directory for more information and licenses
 *
 */

AGSLayouts::VERSION; // Access control


// aspen-demo-content/admin-menu.php
add_action('wp_ajax_ags_layouts_site_import', function () {
	check_ajax_referer('ags-layouts-site-import-ajax', 'ags-layouts-site-import-ajax-none');
	if (!empty($_POST['demo-tasks']) && isset($_POST['pid']) && isset($_POST['task']) && isset($_POST['taskState'])) {
		include __DIR__.'/aspen-importer.php';
		$importer = new AGS_Theme_Importer();
		$importer->process_imports(
		
			// phpcs:ignore ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized, ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- $_POST['demo-tasks'] is checked against a set of fixed values in the process_imports function
			$_POST['demo-tasks'],
			
			(int) $_POST['pid'],
			
			// phpcs:ignore ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized, ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- $_POST['task'] is checked against a set of fixed values in the process_imports function
			$_POST['task'],
			
			sanitize_text_field( wp_unslash( $_POST['taskState'] ) )
			
		);
		exit;
	}
}); // wp-layouts/ags-layouts.php
