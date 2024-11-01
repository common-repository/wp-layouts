<?php
AGSLayouts::VERSION; // Access control

class AGSLayoutsList {
	
	// phpcs:disable WordPress.Security.NonceVerification -- listing layouts is not a CSRF risk
	public static function run() {
		$output = array();
		
		try {
			$request = array();
		
			
			if (!empty($_GET['ags_layouts_editor'])) {
				$request['ags_layouts_editor'] = sanitize_text_field( wp_unslash( $_GET['ags_layouts_editor'] ) );
			}
			
			if (isset($_GET['ags_layouts_collection'])) {
				$request['ags_layouts_collection'] = (int) $_GET['ags_layouts_collection'];
			}
			
			if (!empty($_GET['search']['value'])) {
				$request['query'] = sanitize_text_field( wp_unslash( $_GET['search']['value'] ) );
			}
			
			if (isset($_GET['order'][0]['column']) && !empty($_GET['columns'][ (int) $_GET['order'][0]['column'] ]['data'])) {
				$request['sortBy'] = sanitize_text_field( wp_unslash( $_GET['columns'][ (int) $_GET['order'][0]['column'] ]['data'] ) );
				$request['sortOrder'] = ( isset($_GET['order'][0]['dir']) && $_GET['order'][0]['dir'] == 'desc' ) ? 'D' : 'A';
			}
			
			if (isset($_GET['start'])) {
				$request['offset'] = (int) $_GET['start'];
			}
			
			if (isset($_GET['length'])) {
				$request['limit'] = (int) $_GET['length'];
			}
			
			include_once(__DIR__.'/api.php');
			$response = AGSLayoutsApi::list_layouts($request);
			
			if (isset($_GET['draw'])) {
				$output['draw'] = (int) $_GET['draw'];
			}
			
			if (empty($response['success']) || empty($response['data'])) {
				$errorCode = isset($response['data']['error']) ? $response['data']['error'] : '';
				switch ($errorCode) {
					case 'auth':
                        $output['message'] = esc_html__('Your request could not be authenticated. This can happen if your site URL has changed since you last logged in to the WP Layouts plugin, or for other reasons. Please try logging out and back in under WP Layouts - Settings, and contact support if this problem persists.', 'wp-layouts-td');
                        break;
					case 'noCollectionsAccess':
						$output['message'] = 'NoCollectionsAccess';
						break;
					default:
						$output['message'] = '';
				}
				$output['recordsTotal'] = 0;
				$output['recordsFiltered'] = 0;
			} else if (!empty($_GET['ags_layouts_collection']) && $_GET['ags_layouts_collection'] != -1) {
				$output['collection'] = $response['data']['collection'];
				if ( isset( $output['collection']['description'] ) ) {
					$output['collection']['description'] = wp_kses_post( $output['collection']['description'] );
				}
				$output['layouts'] = $response['data']['layouts'];
			} else {
				$output['recordsTotal'] = $response['data']['stats']['all'];
				$output['recordsFiltered'] = isset($response['data']['stats']['query']) ? $response['data']['stats']['query'] : $output['recordsTotal'];
				$output['data'] = $response['data']['layouts'];
				
				foreach ($output['data'] as &$layout) {
					switch ( isset($request['ags_layouts_editor']) ? $request['ags_layouts_editor'] : $layout['layoutEditor'] ) {
						case 'site-importer':
							$layout['isEditable'] = true;
							break;
						default:
							$layout['isEditable'] = false;
					}
				}
			
			}
		} catch (AGSLayoutsApiTokenException $ex) {
            $output['message'] = __('You are currently not logged in. Please log in under WP Layouts > Settings and try again. Would you like to go there now (opens in a new window)?', 'wp-layouts-td');
            $output['redirect'] = admin_url('admin.php?page=ags-layouts-settings');
			$output['recordsTotal'] = 0;
			$output['recordsFiltered'] = 0;
		}
		
		echo(json_encode($output));
		
	}
	// phpcs:enable WordPress.Security.NonceVerification
	
}
AGSLayoutsList::run();