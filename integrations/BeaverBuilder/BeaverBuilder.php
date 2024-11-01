<?php
/**
 * This file includes code based on and/or copied from parts of Beaver Builder Plugin
 * (Standard Version) and/or Beaver Builder Plugin (Lite Version), copyright 2014 Beaver Builder,
 * released under GPLv2+ (according to the plugin's readme.txt file; fl-builder.php
 * specifies different licensing but we are using the licensing specified in readme.txt),
 * licensed under GPLv3+ (see license.txt file in this plugin's "license" directory for GPLv3 text).
 *
 * See the license.txt file in the WP Layouts plugin root directory for more information and licenses
 */


AGSLayouts::VERSION; // Access control

class AGSLayoutsBB {
	
	public static function setup() {
		/* Hooks */
		add_action('wp_enqueue_scripts', array('AGSLayoutsBB', 'frontendScripts'));
		add_action('wp_ajax_ags_layouts_bb_get_nodes', array('AGSLayoutsBB', 'getPostNodesAjax'));
		add_filter('fl_builder_main_menu', array('AGSLayoutsBB', 'filterBuilderMenu'));
		add_filter('fl_builder_ui_bar_buttons', array('AGSLayoutsBB', 'filterBuilderTopBarButtons'));
	}
	
	public static function frontendScripts($forFrontend=false) {
        wp_enqueue_script('ags-layouts-bb', AGSLayouts::$pluginBaseUrl.'integrations/BeaverBuilder/bb.js', array('jquery', 'wp-i18n'), AGSLayouts::VERSION);
        wp_set_script_translations('ags-layouts-bb', 'wp-layouts-td', AGSLayouts::$pluginBaseUrl.'languages');
	}
	
	// phpcs:disable WordPress.Security.NonceVerification -- read only operation
	public static function getPostNodesAjax() {
		if (!empty($_POST['postId'])) {
			$postId = (int) $_POST['postId'];
			AGSLayouts::requireEditPermission( $postId );
			$builderData = get_post_meta($postId, '_fl_builder_draft', true);
			if (!empty($_POST['nodeId'])) {
				$nodeId = sanitize_text_field( wp_unslash( $_POST['nodeId'] ) );
				if (!isset($builderData[$nodeId])) {
					wp_send_json_error();
				}
				$nodes = self::getNodeWithDescendants($nodeId, $builderData);
			} else {
				$nodes = array_values($builderData);
			}
			wp_send_json_success(self::nodesToJson($nodes));
		}
		wp_send_json_error();
	}
	// phpcs:enable WordPress.Security.NonceVerification
	
	private static function getNodeWithDescendants($nodeId, $builderData, &$nodesArray=array()) {
		$nodesArray[] = $builderData[$nodeId];
		foreach ($builderData as $node) {
			if (isset($node->parent) && $node->parent == $nodeId) {
				self::getNodeWithDescendants($node->node, $builderData, $nodesArray);
			}
		}
		return $nodesArray;
	}
	
	public static function nodesToJson($data, $_recur=false) {
		switch (gettype($data)) {
			case 'array':
				$children = array();
				foreach ($data as $childKey => $childValue) {
					$childResult = self::nodesToJson($childValue, true);
					$children[$_recur ? $childResult[0].$childKey : $childKey] = $childResult[1];
				}
				return $_recur ? array('@', $children) : json_encode($children);
			case 'object':
				$children = array();
				foreach (get_object_vars($data) as $childKey => $childValue) {
					$childResult = self::nodesToJson($childValue, true);
					$children[$_recur ? $childResult[0].$childKey : $childKey] = $childResult[1];
				}
				return $_recur ? array('_', $children) : json_encode($children);
		}
		return $_recur ? array('_', $data) : json_encode($data);
	}
	
	public static function nodesFromJson($data, $_transformToArray=false, $_recur=false) {
		if (!$_recur) {
			$data = json_decode($data);
		}
		
		switch (gettype($data)) {
			case 'array':
				foreach ($data as $childKey => $childValue) {
					unset($data[$childKey]);
					$data[$_recur ? substr($childKey, 1) : $childKey] = self::nodesFromJson($childValue, $childKey[0] == '@', true);
				}
				return $data;
			case 'object':
				$objectVars = get_object_vars($data);
				if ($_transformToArray) {
					$data = array();
				}
				foreach ($objectVars as $childKey => $childValue) {
					$childResult = self::nodesFromJson($childValue, $childKey[0] == '@', true);
					$trueKey = substr($childKey, 1);
					if ($_transformToArray) {
						$data[$trueKey] = $childResult;
					} else {
						unset($data->$childKey);
						$data->$trueKey = $childResult;
					}
				}
				return $data;
		}
		return $data;
	}
	
	public static function insertLayout($layoutContents, $postId, $importLocation) {
		$layoutContents = self::nodesFromJson($layoutContents);
		
		if (!empty($postId) && is_array($layoutContents)) {
			AGSLayouts::requireEditPermission($postId);
			if ($importLocation == 'replace') {
				update_post_meta($postId, '_fl_builder_draft', array());
			}
			
			add_filter('fl_builder_node_status', array('AGSLayoutsBB', 'forceEditStatusDraft'));
			FLBuilderModel::set_post_id($postId);
			
			$builderData = get_post_meta($postId, '_fl_builder_draft', true);
			
			$allNodeIds = array();
			foreach ($layoutContents as &$node) {
				
				$originalNodeId = $node->node;
				do {
					$hasNodeConflict = false;
					foreach ($builderData as $nodeId => $existingNode) {
						if ($nodeId == $node->node) {
							$hasNodeConflict = true;
							break;
						}
					}
					if ($hasNodeConflict) {
						$node->node = FLBuilderModel::generate_node_id();
					}
				} while ($hasNodeConflict);
				
				if ($node->node != $originalNodeId) {
					foreach ($layoutContents as &$node2) {
						if ($node2->parent == $originalNodeId) {
							$node2->parent = $node->node;
						}
					}
				}
				
			}
			
			$newBuilderData = array();
			$rootNodes = array();
			
			foreach ($layoutContents as &$node) {
				if (empty($node->parent)) {
					$rootNodes[] = $node;
					$node->parent = null;
				}
				$newBuilderData[$node->node] = $node;
			}
			
			// Not sure if this is needed, but previously $rootNode was always set:
			if ( empty($rootNodes) ) {
				$rootNodes[] = $layoutContents[0];
			}
			
			$currentPosition = -1;
			$nodesToRemove = array();
			
			foreach ($rootNodes as $rootNode) {
				$rowNode = $importLocation == 'above' ? FLBuilderModel::add_row('1-col', ++$currentPosition) : FLBuilderModel::add_row('1-col');
				$columnGroupNode = current(FLBuilderModel::get_child_nodes($rowNode));
				$columnNodeId = current(FLBuilderModel::get_child_nodes($columnGroupNode))->node;
					
				// Get the builder data again after adding nodes above
				$builderData = get_post_meta($postId, '_fl_builder_draft', true);
				
				if ($rootNode->type == 'row') {
					$rootNode->position = $rowNode->position;
					$nodesToRemove[$rowNode->node] = true;
					$nodesToRemove[$columnGroupNode->node] = true;
					$nodesToRemove[$columnNodeId] = true;
				} else {
					$rootNode->position = 0;
					if ($rootNode->type == 'column') {
						$rootNode->parent = $columnGroupNode->node;
						$nodesToRemove[$columnNodeId] = true;
					} else {
						$rootNode->parent = $columnNodeId;
					}
				}
				
			}
			
			$builderData = array_diff_key($builderData, $nodesToRemove);
			
			$builderData = array_merge($builderData, $newBuilderData);
			remove_filter('fl_builder_node_status', array('AGSLayoutsBB', 'forceEditStatusDraft'));
			
			update_post_meta($postId, '_fl_builder_draft', $builderData);
			return true;
		}
		
		return false;
	}
	
	public static function forceEditStatusDraft() {
		return 'draft';
	}
	
	public static function preUploadProcess($layout) {
		$layout = self::nodesFromJson($layout);
		
		$imageFields = array('photo', 'photos', 'bg_image');
		
		if (is_array($layout)) {
		
			$nodeIds = array();
			foreach ($layout as $node) {
				$nodeIds[$node->node] = true;
			}
		
			foreach ($layout as &$node) {
				if (empty($nodeIds[$node->parent])) {
					unset($node->parent);
				}
				if (empty($node->parent)) {
					unset($node->position);
				}
				
				if (isset($node->settings)) {
				
					if (isset($node->settings->type) && $node->settings->type == 'photo') {
						unset($node->settings->data);
					} else {
						unset($node->settings->photo_data);
					}
					
					foreach ($imageFields as $imageField) {
						
						if ( !empty($node->settings->$imageField) ) {
						
							if ( is_numeric($node->settings->$imageField) ) {
								$imageUrl = wp_get_attachment_url($node->settings->$imageField);
								if (!empty($imageUrl)) {
									$node->settings->$imageField = 'agslayouts.id:'.$imageUrl;
								}
							} else if ( is_array($node->settings->$imageField) ) {
								foreach ($node->settings->$imageField as &$photo) {
									if (is_numeric($photo)) {
										$imageUrl = wp_get_attachment_url($photo);
										if (!empty($imageUrl)) {
											$photo = 'agslayouts.id:'.$imageUrl;
										}
									}
								}
							}
							
						}
						
					}
				
					
				}
				
			}
		}
		
		return self::nodesToJson($layout);
	}
	
	public static function filterBuilderMenu($menu) {
		if (isset($menu['main']['items'])) {
			$menuIndex = 11;
			while (isset($menu['main']['items'][$menuIndex]) || isset($menu['main']['items'][$menuIndex + 1])) {
				++$menuIndex;
			}
            $menu['main']['items'][$menuIndex] = array(
                'label' => esc_html__('Import from WP Layouts', 'wp-layouts-td'),
                'type' => 'event',
                'eventName' => 'AGSLayoutsImport'
            );
            $menu['main']['items'][$menuIndex + 1] = array(
                'label' => esc_html__('Save to WP Layouts', 'wp-layouts-td'),
                'type' => 'event',
                'eventName' => 'AGSLayoutsSavePage'
            );
		}
		return $menu;
	}
	
	public static function filterBuilderTopBarButtons($buttons) {
		$newButtons = array();
		foreach ($buttons as $buttonId => $button) {
			if ($buttonId == 'content-panel') {
				$newButtons['ags-layouts-import'] = array(
                    'label' => esc_html__('Add from WP Layouts', 'wp-layouts-td'),
					'onclick' => 'FLBuilder.triggerHook(\'AGSLayoutsImport\');'
				);
			}
			$newButtons[$buttonId] = $button;
		}
		return $newButtons;
	}
	
	static function setupPreviewPost($previewPostId, $layoutContents) {
		self::insertLayout($layoutContents, $previewPostId, 'replace');
		$previewUrl = get_permalink($previewPostId);
		$previewUrl .= (strpos($previewUrl, '?') === false ? '?' : '&').'fl_builder';
		return $previewUrl;
	}
	
}

AGSLayoutsBB::setup();