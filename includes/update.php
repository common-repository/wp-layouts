<?php
AGSLayouts::VERSION; // Access control

class AGSLayoutsLayoutUpdater {
	
	public static function run() {
		if ( empty($_POST['ags_layouts_data']['layoutId'])
				|| !is_numeric($_POST['ags_layouts_data']['layoutId'])
				|| empty($_POST['ags_layouts_data']['layoutName'])
				// phpcs:ignore ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- not sanitizing or unslashing nonce value
				|| empty($_GET['ags-layouts-nonce']) || !wp_verify_nonce($_GET['ags-layouts-nonce'], 'ags-layouts-ajax') ) {
			return;
		}
		
		include_once(__DIR__.'/api.php');
		
		$response = AGSLayoutsApi::update_layout( array(
			'layoutId' => (int) $_POST['ags_layouts_data']['layoutId'],
			'layoutName' => sanitize_text_field( wp_unslash( $_POST['ags_layouts_data']['layoutName'] ) )
		) );
		
		if (empty($response['success'])) {
			wp_send_json_error();
		} else {
			wp_send_json_success();
		}
		
	}
	
}
AGSLayoutsLayoutUpdater::run();