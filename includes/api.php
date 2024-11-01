<?php
AGSLayouts::VERSION; // Access control

class AGSLayoutsApi {
	
	const METHOD_GET = 0;
	const METHOD_POST = 1;
	static $ENDPOINTS;
	
	public static function __callStatic($endpoint, $args) {
		
		self::$ENDPOINTS = array(
			'list_layouts' => array( self::METHOD_POST, array('ags_layouts_editor', 'ags_layouts_collection', 'query', 'sortBy', 'sortOrder', 'offset', 'limit') ),
			'delete_layout' => array( self::METHOD_POST, array('layoutId') ),
			'get_layout' => array( self::METHOD_GET, array('layoutId') ),
			'get_layout_meta' => array( self::METHOD_GET, array('layoutId') ),
			'get_layout_image' => array( self::METHOD_GET, array('layoutId', 'imageFile') ),
			'update_layout' => array( self::METHOD_POST, array('layoutId', 'layoutName') ),
			'replace_layout' => array( self::METHOD_POST, array('oldLayoutId', 'newLayoutId') ),
			'store' => array( self::METHOD_POST, array('layoutName', 'layoutEditor', 'layoutContents', 'imagesUrl', 'screenshotData',
														'extraData[customCSS]', 'extraData[widgets]', 'extraData[agsxto]', 'extraData[diviModulePresets]',
														'extraData[caldera_forms]', 'extraData[config]', 'jobState[layoutId]', 'response[url]', 'response[data]') ),
			'get_auth_token' => array( self::METHOD_POST, array('authTokenEmail', 'authTokenPassword', 'authTokenSite') ),
			'cancel_auth_token' => array( self::METHOD_POST, array() ),
		);
		
		if ( !isset( self::$ENDPOINTS[$endpoint] ) ) {
            throw new Exception(esc_html__('Invalid endpoint name:' , 'wp-layouts-td') . $endpoint);
		}
		
		if (
			( !isset( $args[0] ) || !is_array($args[0]) || array_diff( array_keys($args[0]), self::$ENDPOINTS[$endpoint][1] ) )
			&& ( $args || self::$ENDPOINTS[$endpoint][1]  )
		) {
            throw new Exception(esc_html__('Missing or invalid arguments', 'wp-layouts-td'));
		}
		
		$args = $args ? $args[0] : array();
		$args['action'] = 'ags_layouts_'.$endpoint;
		$args['_ags_layouts_ver'] = AGSLayouts::VERSION;
		
		if ( $endpoint != 'get_auth_token' ) {
			include_once(__DIR__.'/account.php');
			$token = AGSLayoutsAccount::getToken( empty($args['layoutId']) ? null : $args['layoutId'] );
			
			if (!$token) {
				include_once(__DIR__.'/exceptions/ApiTokenException.php');
				throw new AGSLayoutsApiTokenException();
			}
			
			$args['_ags_layouts_token'] = $token;
			$args['_ags_layouts_site'] = get_option('siteurl');
		}
		
		switch ( self::$ENDPOINTS[$endpoint][0] ) {
			case self::METHOD_POST:
				$result = wp_remote_post(AGSLayouts::API_URL, array(
					'body' => $args,
					'timeout' => 300
				));
				break;
			case self::METHOD_GET:
				$result = wp_remote_get(AGSLayouts::API_URL.'?'.build_query($args), array(
					'timeout' => 300
				));
				break;
		}
		
		$resultBody = wp_remote_retrieve_body($result);
		
		if ( $endpoint != 'get_layout_image' ) {
			$resultBody = @json_decode($resultBody, true);
		}
		
		return $resultBody;
		
	}
	
}