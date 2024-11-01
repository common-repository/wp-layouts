<?php
AGSLayouts::VERSION; // Access control

class AGSLayoutsDownloader {
	
	const MAX_DB_CHUNK_LENGTH = 127000;
	
	public static function run() {
		global $wpdb;
		
		//header('Content-Type: text/javascript');
		$importLocations = array('return', 'replace', 'above', 'below');
		if (
			empty($_GET['layoutId']) || !is_numeric($_GET['layoutId'])
				|| empty($_GET['postId']) || !is_numeric($_GET['postId'])
				|| empty($_GET['importLocation'])
				// phpcs:ignore ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized, ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- not sanitizing or unslashing nonce value
				|| empty($_GET['ags-layouts-nonce']) || !wp_verify_nonce($_GET['ags-layouts-nonce'], 'ags-layouts-ajax')
		) {
			return;
		}
		
		// phpcs:ignore ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized, ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- checking GET field against fixed values
		$importLocation = $_GET['importLocation'];
		if ( !in_array($importLocation, $importLocations) ) {
			return;
		}
		
		$layoutId = (int) $_GET['layoutId'];
		$postId = (int) $_GET['postId'];
		
		AGSLayouts::requireEditPermission($postId);
		
		if (empty($_GET['newJob'])) {
			$job = get_post_meta($postId, '_ags_layouts_job');
			if ($job) {
				$jobChunks = array();
				foreach ( $job as $chunk ) {
					$colonPos = strpos($chunk, ':');
					$jobChunks[ (int) substr($chunk, 0, $colonPos) ] = substr($chunk, $colonPos + 1);
				}
				
				$job = @unserialize(@base64_decode( implode('', $jobChunks) ));
			}
			
			
		}
        
		if (
			empty($job)
			|| time() - $job['lastUpdateTime'] > 300
			|| $job['layoutId'] != $layoutId
		) {
			$job = array(
				'layoutId' => $layoutId,
				'postId' => $postId
			);

            self::nextTask($job, 'getLayout', null, esc_html__('Downloading layout information...', 'wp-layouts-td'), 0);
		} else {
			$job['postId'] = $postId;
		}
        
		switch($job['nextTask']['task']) {
			case 'getLayout':
				$layout = self::getLayout($layoutId);
				if (empty($layout)) {
					return;
				}
				
				$job['layout'] = $layout;
				if ( empty($layout['images']) || !empty($_GET['noImageDownload']) ) {
                    self::nextTask($job, 'insertLayout', null, esc_html__('Processing layout...', 'wp-layouts-td'), 1 / 2);
				} else {
					$imageCount = count($layout['images']);
                    self::nextTask($job, 'getLayoutImage', array('imageIndex' => 0), sprintf(esc_html__('Downloading image 1 of %s ...','wp-layouts-td'), $imageCount), (1 / ($imageCount + 2)));
				}
				break;
			case 'getLayoutImage':
				$imageCount = count($job['layout']['images']);
				if (!isset($job['nextTask']['params']['imageIndex'])) {
					return;
				}
				
				$imageSuccess = false;
				$imageIndex = $job['nextTask']['params']['imageIndex'];
				if (isset($job['layout']['images'][$imageIndex])) {
					
					$imageSizes = array();
					if (isset($job['layout']['images'][$imageIndex]['indexes'])) {
						foreach ( $job['layout']['images'][$imageIndex]['indexes'] as $index => $params) {
							if ( is_array($params) && isset($params[1]) && isset($params[2]) ) {
								$imageSizes[] = array($params[1], $params[2]);
							}
						}
					}
					
					$imageData = self::getLayoutImage(
						$layoutId,
						$postId,
						$job['layout']['images'][$imageIndex]['file'],
						$job['layout']['images'][$imageIndex]['name'],
						$imageSizes
					);
					if (!empty($imageData)) {
						$job['layout']['images'][$imageIndex] = array_merge($job['layout']['images'][$imageIndex], $imageData);
						$imageSuccess = true;
					}
				}
				
				if (!$imageSuccess) {
                    self::logWarning($job, esc_html__('One or more image(s) may not have been imported correctly. Please check the import to confirm.', 'wp-layouts-td'));
				}
				
				++$imageIndex;
				if ($imageIndex == $imageCount) {
                    self::nextTask($job, 'insertLayout', null, esc_html__('Processing layout...', 'wp-layouts-td'), (($imageCount + 1) / ($imageCount + 2)));
				} else {
                    self::nextTask($job, 'getLayoutImage', array('imageIndex' => $imageIndex), sprintf(esc_html__('Downloading image %s of %s...','wp-layouts-td'), ($imageIndex + 1), $imageCount), (($imageIndex + 2) / ($imageCount + 2)));
				}
				
				break;
			case 'insertLayout':
				if (empty($job['layout']['images'])) {
					$layoutContents = $job['layout']['contents'];
				} else {
					$insertions = array();
					foreach ($job['layout']['images'] as $i => $image) {
						foreach ($image['indexes'] as $index => $field) {
							if ( empty($_GET['noImageDownload']) ) {
								$fullField = 'local_'.(is_array($field)
												? (isset($field[0]) ? $field[0] : 'unknown').'-'.(isset($field[1]) ? $field[1] : '').'x'.(isset($field[2]) ? $field[2] : '')
												: $field);
								if ( empty($image[$fullField]) ) {
									self::logWarning($job, esc_html__('One or more image(s) may not have been inserted into your content correctly. Please check the import to confirm.', 'wp-layouts-td'));
								} else {
									$insertions[$index] = $image[$fullField];
								}
							} else if ( $field == 'url' ) {
								$insertions[$index] = admin_url( 'admin-ajax.php?action=ags_layouts_get_image&layoutId='.( (int) $layoutId ).'&image='.$image['file'].'#'.str_replace( '=', '', base64_encode($image['name']) ) );
							}
						}
					}
					
					$origLayoutContents = $job['layout']['contents'];
				
					ksort($insertions);
					$lastIndex = 0;
					$layoutContents = '';
					foreach ($insertions as $index => $insertion) {
						if ($index != $lastIndex) {
							$layoutContents .= substr($origLayoutContents, $lastIndex, $index - $lastIndex);
						}
						$layoutContents .= $insertion;
						$lastIndex = $index;
					}
					$layoutContents .= substr($origLayoutContents, $lastIndex);
				}
				
				if ($importLocation != 'return') {
				
					$extraData = array();
					
					switch( isset($_GET['layoutEditor']) ? $_GET['layoutEditor'] : '' ) {
						case 'beaverbuilder':
							AGSLayoutsBB::insertLayout($layoutContents, $postId, $importLocation);
							break;
						case 'elementor':
							break;
						case 'gutenberg':
							$extraData['layoutContents'] = $layoutContents;
							break;
						case 'site-importer':
							$layoutContents = AGSLayoutsSiteImporter::preInsertProcess($layoutContents);
							AGSLayoutsSiteImporter::importExtraData($postId, isset($job['layout']['extraData']) ? $job['layout']['extraData'] : array() );
							AGSLayoutsSiteImporter::import($postId, $layoutContents );
							break;
						default:
							
							switch($_GET['layoutEditor']) {
								case 'divi':
									$layoutContents = AGSLayoutsDivi::preInsertProcess($layoutContents);
									if (isset($job['layout']['extraData'])) {
										AGSLayoutsDivi::importExtraData($postId, $importLocation, $job['layout']['extraData']);
									}
									break;
							}
							
						
							$post = get_post($postId);
							if (empty($post)) {
								return;
							}
							
							$layoutContents = sanitize_post_field('post_content', $layoutContents, $postId, 'db');
							$chunks = str_split($layoutContents, self::MAX_DB_CHUNK_LENGTH);
							$prepend = false;
							$preserveExisting = true;
							
							switch($importLocation) {
								case 'replace':
									$preserveExisting = false;
									break;
								case 'above':
									$prepend = true;
									// Need to reverse array
									break;
								case 'below':
									break;
							}
							
							foreach ($chunks as $chunkNum => $chunk) {
								$vars = ($preserveExisting || $chunkNum) ? array($chunk, $postId) : array('', $chunk, $postId);
								$wpdb->query(
									$wpdb->prepare(
										'UPDATE '.$wpdb->posts
										.' SET post_content = CONCAT('
											.(!$prepend && ($preserveExisting || $chunkNum) ? 'post_content' : '%s') // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
											.', '
											.($prepend && ($preserveExisting || $chunkNum) ? 'post_content' : '%s') // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
										.') WHERE ID=%d',
										$vars
									)
								);
							}
							
					}
				
					
				} else {
					$extraData = array('layoutContents' => $layoutContents);
				}
				
				self::nextTask($job, 'done', null, 'Done!', 1, $extraData);
				break;
			case 'done':
				delete_post_meta($postId, '_ags_layouts_job');
		}
		
		
	}
	
	public static function getLayout($layoutId) {
		include_once(__DIR__.'/api.php');
		$response = AGSLayoutsApi::get_layout( array('layoutId' => $layoutId) );
		if (!empty($response)) {
			if (!empty($response['success']) && !empty($response['data']['contents'])) {
				return $response['data'];
			}
		}
		return false;
	}
	
	public static function getLayoutImage($layoutId, $postId, $imageFile, $imageName, $imageSizes) {
		include_once(__DIR__.'/api.php');
		$response = AGSLayoutsApi::get_layout_image( array(
			'layoutId' => $layoutId,
			'imageFile' => $imageFile
		) );
		if (!empty($response)) {
			$downloadedFile = array(
				'tmp_name' => wp_tempnam(),
				'name' => $imageName
			);
			file_put_contents($downloadedFile['tmp_name'], $response);
			
			foreach ($imageSizes as $imageSize) {
				add_image_size(
					'ags-layouts-temp-'.$imageSize[0].'x'.$imageSize[1],
					$imageSize[0],
					$imageSize[1],
					true
				);
			}
			
			$imageId = media_handle_sideload($downloadedFile, $postId);
			
			foreach ($imageSizes as $imageSize) {
				remove_image_size('ags-layouts-temp-'.$imageSize[0].'x'.$imageSize[1]);
			}
			
			@unlink($downloadedFile['tmp_name']);
			if (!empty($imageId)) {
				$result = array(
					'local_id' => $imageId,
					'local_url' => wp_get_attachment_url($imageId)
				);
				foreach ($imageSizes as $imageSize) {
					$result[ 'local_url-'.$imageSize[0].'x'.$imageSize[1] ] = wp_get_attachment_image_url($imageId, $imageSize );
				}
				return $result;
			}
		}
		return false;
	}
	
	public static function nextTask($job, $nextTask, $taskParams, $statusText, $progress, $extraData=array()) {
		$job['nextTask'] = array('task' => $nextTask);
		if (!empty($taskParams)) {
			$job['nextTask']['params'] = $taskParams;
		}
		$job['lastUpdateTime'] = time();
		
		delete_post_meta($job['postId'], '_ags_layouts_job');
		
		$jobStr = base64_encode(serialize($job));
		
		foreach ( str_split($jobStr, self::MAX_DB_CHUNK_LENGTH) as $chunkNo => $chunk ) {
			add_post_meta($job['postId'], '_ags_layouts_job', $chunkNo.':'.$chunk);
		}
		
		$output = array(
			'status' => $statusText,
			'progress' => $progress
		);
		if ($nextTask == 'done') {
			$output['done'] = true;
			if (!empty($job['warnings'])) {
				$output['warnings'] = array_unique($job['warnings']);
			}
		}
		
		$output = array_merge($extraData, $output);
		
		wp_send_json_success($output);
	}
	
	static function logWarning(&$job, $warningMessage) {
		if (empty($job['warnings'])) {
			$job['warnings'] = array();
		}
		$job['warnings'][] = $warningMessage;
	}
}
AGSLayoutsDownloader::run();