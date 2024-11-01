<?php
AGSLayouts::VERSION; // Access control

class AGSLayoutsLayoutGetReadKey {
	private static $curl;
	
	public static function run() {
		if (empty($_POST['layoutId'])
				|| !is_numeric($_POST['layoutId'])
				|| empty($_GET['ags-layouts-nonce'])
				|| !wp_verify_nonce(sanitize_key(wp_unslash($_GET['ags-layouts-nonce'])), 'ags-layouts-ajax')) {
			return;
		}
		
		$request = array(
			'action' => 'ags_layouts_get_layout_read_key',
			'layoutId' => (int) $_POST['layoutId']
		);
		
		if (!empty($_POST['reset'])) {
			$request['reset'] = true;
		}
		
		
		include_once(__DIR__.'/account.php');
		$request['_ags_layouts_token'] = AGSLayoutsAccount::getToken();
		$request['_ags_layouts_site'] = get_option('siteurl');
		
		
		self::$curl = curl_init();
		curl_setopt_array(self::$curl, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $request,
			CURLOPT_URL => AGSLayouts::API_URL
		));
		
		$response = @curl_exec(self::$curl);
		$response = @json_decode($response, true);
		
		if (empty($response['success'])) {
			wp_send_json_error();
		} else {
			wp_send_json_success($response['data']);
		}
		
	}
	
}
AGSLayoutsLayoutGetReadKey::run();