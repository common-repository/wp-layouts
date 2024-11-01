<?php
class AGSLayoutsUploader {
	
	public static function run() {
		if (
				// phpcs:ignore ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- not sanitizing or unslashing nonce value
				empty($_GET['ags-layouts-nonce']) || !wp_verify_nonce($_GET['ags-layouts-nonce'], 'ags-layouts-ajax')
		) {
			wp_send_json_error( array('error' => 'invalid_nonce') );
			return;
		}
		
		include_once(__DIR__.'/api.php');
		$apiData = array();
		
		if (empty($_POST['jobState'])) {
			if ( empty($_POST['postContent']) || empty($_POST['layoutEditor']) || empty($_POST['layoutName']) ) {
				wp_send_json_error( array('error' => 'missing_params') );
				return;
			}
			
			$apiData['layoutEditor'] = sanitize_text_field( wp_unslash( $_POST['layoutEditor'] ) );
			if (!empty($_POST['screenshotData'])) {
				$apiData['screenshotData'] = sanitize_text_field( wp_unslash( $_POST['screenshotData'] ) ); // data will be checked further on the WP Layouts server
			}
			
			// phpcs:ignore ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized -- data is for storage on the layouts server and is saved "as is" (with some processing)
			$apiData['layoutContents'] = wp_unslash($_POST['postContent']);
			
			switch ($_POST['layoutEditor']) {
				case 'divi':
					// This is very similar to how Divi does it but developed independently :)
					$contents = json_decode($apiData['layoutContents'], true);
					if (empty($contents)) {
						wp_send_json_error( array('error' => 'layout_json_error') );
						return;
					}
					unset($contents[0]['attrs']['template_type']);
					$apiData['layoutContents'] = et_fb_process_to_shortcode($contents);
					
					if (!empty($_POST['extraData'])) {
						// phpcs:ignore ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized -- sanitization occurs in AGSLayoutsDivi::processExtraData()
						foreach (AGSLayoutsDivi::processExtraData( wp_unslash( $_POST['extraData'] ) ) as $extraDataField => $extraDataContents) {
							$apiData['extraData['.$extraDataField.']'] = $extraDataContents;
						}
					}
					break;
				case 'beaverbuilder':
					$apiData['layoutContents'] = AGSLayoutsBB::preUploadProcess($apiData['layoutContents']);
					break;
				case 'elementor':
					$apiData['layoutContents'] = AGSLayoutsElementor::preUploadProcess($apiData['layoutContents']);
					break;
				case 'site-importer':
					if (!empty($_POST['extraData'])) {
						// phpcs:ignore ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized -- extraData keys are checked by the AGSLayoutsApi class, values are stored "as is"
						foreach (wp_unslash($_POST['extraData']) as $extraDataField => $extraDataContents) {
							$apiData['extraData['.$extraDataField.']'] = $extraDataContents;
						}
					}
					break;
			}
			
			$apiData['layoutName'] = sanitize_text_field( wp_unslash( $_POST['layoutName'] ) );
			
			$uploadsDirectoryInfo = wp_upload_dir(null, false);
			if (empty($uploadsDirectoryInfo['baseurl'])) {
				wp_send_json_error( array('error' => 'no_uploads_url') );
				return;
			}
			$apiData['imagesUrl'] = $uploadsDirectoryInfo['baseurl'];
		} else if (!isset($_POST['jobState']['layoutId'])) {
			return;
		} else {
			$apiData['jobState[layoutId]'] = (int) $_POST['jobState']['layoutId'];
			if ( !empty($_POST['jobState']['request']) ) {
				
				$uploadsDirectoryInfo = wp_upload_dir();
				if (!empty($uploadsDirectoryInfo['baseurl'])) {
					
					// C:\agswp\htdocs\dev\wp-content\plugins\wp-layouts-server\ags-layouts-server.php
					$imagesBaseUrl = $uploadsDirectoryInfo['baseurl'].'/';
					
					$imageUrlSearchStrings = array(
						$imagesBaseUrl
					);
					
					if (!strcasecmp(substr($imagesBaseUrl, 0, 6), 'https:')) {
						$imageUrlSearchStrings[] = 'http:'.substr($imagesBaseUrl, 6);
						$domainStart = 8;
					} else if (!strcasecmp(substr( $imagesBaseUrl, 0, 5), 'http:')) {
						$imageUrlSearchStrings[] = 'https:'.substr($imagesBaseUrl, 5);
						$domainStart = 7;
					}
					
					if (isset($domainStart)) {
						$pathStart = strpos($imagesBaseUrl, '/', $domainStart);
						$imageUrlSearchStrings[] = substr($imagesBaseUrl, $pathStart);
					}
					
					$jobStateRequest = esc_url_raw( wp_unslash( $_POST['jobState']['request'] ) );
					
					foreach ($imageUrlSearchStrings as $searchString) {
						$searchStringLength = strlen($searchString);
						if ( substr($jobStateRequest, 0, $searchStringLength) == $searchString ) {
							$requestRelativeUrl = substr($jobStateRequest, $searchStringLength);
							break;
						}
					}
					
					if ( isset($requestRelativeUrl) ) {
						$requestPath = $uploadsDirectoryInfo['basedir'].'/'.$requestRelativeUrl;
						$requestContents = @file_get_contents($requestPath);
						if ($requestContents) {
							$apiData['response[url]'] = $jobStateRequest;
							$apiData['response[data]'] = base64_encode($requestContents);
						}
					}
					
				}
				
			}
		}
		
		try {
			$response = AGSLayoutsApi::store($apiData);
		} catch (AGSLayoutsApiTokenException $ex) {
			wp_send_json_error( array('error' => 'auth') );
			return;
		} catch (Exception $ex) {
			wp_send_json_error( array('error' => 'store_layout_error') );
			return;
		}
		
		$nonFatalErrorData = array();
		
		if (empty($response['success']) || empty($response['data'])) {
			if (isset($response['data']['error'])) {
				if (empty($response['data']['nonFatal'])) {
					
					$errorResponse = array('error' => $response['data']['error']);
					if (isset($response['data']['errorParams'])) {
						$errorResponse['errorParams'] = $response['data']['errorParams'];
					}
					wp_send_json_error($errorResponse);
				
				} else {
					$nonFatalErrorData = array('error' => $response['data']['error']);
					if (isset($response['data']['errorParams'])) {
						$nonFatalErrorData['errorParams'] = $response['data']['errorParams'];
					}
				}
			} else {
				wp_send_json_error();
			}
		}
		
		if (!empty($response['data']['done'])) {
			wp_send_json_success( array_merge( array('done' => true), $nonFatalErrorData ) );
		}
		
		$output = array(
			'jobState' => empty($response['data']['jobState']) ? array() : $response['data']['jobState']
		);
		if (!empty($response['data']['status'])) {
			$output['status'] = $response['data']['status'];
		}
		if (!empty($response['data']['progress'])) {
			$output['progress'] = $response['data']['progress'];
		}
		
		wp_send_json_success( array_merge( $output, $nonFatalErrorData ) );
	}
	
}
AGSLayoutsUploader::run();