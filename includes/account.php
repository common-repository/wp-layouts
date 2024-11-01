<?php
AGSLayouts::VERSION; // Access control

class AGSLayoutsAccount {
	private static $lastLoginError;
	
	public static function login($email, $password) {
		include_once(__DIR__.'/api.php');
		$loginResult = AGSLayoutsApi::get_auth_token(
			array(
				'authTokenEmail' => $email,
				'authTokenPassword' => $password,
				'authTokenSite' => get_option('siteurl')
			)
		);
		
		if (!empty($loginResult['success']) && !empty($loginResult['data']['token'])) {
			$tokenUserId = get_current_user_id();
			return update_user_meta(
				$tokenUserId,
				'_ags_layouts_auth',
				array(
					'email' => $email,
					'token' => $loginResult['data']['token']
				)
			);
		}
		
		self::$lastLoginError = empty($loginResult['success']) && !empty($loginResult['data']) ? $loginResult['data'] : '';
		
		return false;
	}
	
	static function getLastLoginError() {
		return self::$lastLoginError;
	}
	
	public static function getToken($forLayoutId=null) {
		if ( $forLayoutId && ( AGSLayouts::IS_PACKAGED_LAYOUT || AGSLayouts::getThemeDemoData() ) ) {
			$configLayouts = AGSLayouts::getPackagedLayoutConfig('layouts');
			if ( isset($configLayouts[$forLayoutId]['key']) ) {
				return $configLayouts[$forLayoutId]['key'];
			}
		}
		
		$tokenUserId = get_current_user_id();
		$auth = get_user_meta(
			$tokenUserId,
			'_ags_layouts_auth',
			true
		);
		
		if (empty($auth)) {
			$auth = get_option('ags_layouts_auth');
		}
		
		if (!empty($auth)) {
			return $auth['token'];
		}
		
	}
	public static function getAccountEmail() {
		$tokenUserId = get_current_user_id();
		$auth = get_user_meta(
			$tokenUserId,
			'_ags_layouts_auth',
			true
		);
		
		if (empty($auth)) {
			$auth = get_option('ags_layouts_auth');
		}
		
		if (!empty($auth)) {
			return $auth['email'];
		}
	}
	
	public static function isLoggedIn() {
		$token = self::getToken();
		return !empty($token);
	}
	
	public static function logout() {
		include_once(__DIR__.'/api.php');
		$logoutResult = AGSLayoutsApi::cancel_auth_token();
		
		$tokenUserId = get_current_user_id();
	
		return
			( delete_user_meta($tokenUserId, '_ags_layouts_auth') || true )
			&& ( delete_option('ags_layouts_auth') || true )
			&& !empty($logoutResult['success']);
	}
}