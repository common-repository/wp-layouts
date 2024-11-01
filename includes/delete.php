<?php
AGSLayouts::VERSION; // Access control

class AGSLayoutsDeleter {
	
	public static function run() {
		if ( empty($_POST['layoutId']) || !is_numeric($_POST['layoutId'])
				// phpcs:ignore ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- not sanitizing or unslashing nonce value
				|| empty($_GET['ags-layouts-nonce']) || !wp_verify_nonce($_GET['ags-layouts-nonce'], 'ags-layouts-ajax') ) {
			return;
		}
		
		$request = array(
			'layoutId' => (int) $_POST['layoutId'],
		);
		
		include_once(__DIR__.'/api.php');
		$response = AGSLayoutsApi::delete_layout($request);
		
		if (empty($response['success'])) {
			wp_send_json_error();
		} else {
			wp_send_json_success();
		}
		
	}
	
}
AGSLayoutsDeleter::run();