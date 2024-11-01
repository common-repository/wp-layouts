<?php
AGSLayouts::VERSION; // Access control

class AGSLayoutsSiteImporter {
	private static $curl, $importPostId;
	
	static function onInit() {
		// wp-layouts-server/ags-layouts-server.php
		register_post_type('wplayouts-siteimport', array(
			'labels' => array(
                'name' => esc_html__('WP Layouts Site Imports', 'wp-layouts-td'),
                'singular_name' => esc_html__('WP Layouts Site Import', 'wp-layouts-td')
			),
			'public' => false,
			'can_export' => false,
			'supports' => array(
				
			),
		));

	}
	
	
	static function preInsertProcess($layoutContents) {
		// The WordPress Importer Regex Parser requires certain tags to be on their own line,
		// so we need to ensure there is a line break after each tag (but without affecting any
		// HTML tags that may be in CDATA fields)
		
		$eolLength = strlen(PHP_EOL);
		
		$matchOffset = 0;
		
		while (true) {
		
			preg_match('#\</.+\>[^\n\r]#U', $layoutContents, $tag, PREG_OFFSET_CAPTURE, $matchOffset);
			if ( !isset($tag[0][1]) ) {
				break;
			}
			
			$tagStart = $tag[0][1];
			$tagEnd = $tag[0][1] + strlen($tag[0][0]); // $tagEnd is the index immediately after the tag
			$layoutContentsLength = strlen($layoutContents);
			$closestCDataOpen = strrpos($layoutContents, '<![CDATA[', $tagStart - $layoutContentsLength);
			$closestCDataClose = strrpos($layoutContents, ']]>', $tagStart - $layoutContentsLength);
			
			if ( $closestCDataClose < $closestCDataOpen ) {
				// We are in a CDATA field
				$matchOffset = $tagEnd;
				continue;
			}
			
			$layoutContents = substr($layoutContents, 0, $tagEnd).PHP_EOL.substr($layoutContents, $tagEnd);
			
			$matchOffset = $tagEnd + $eolLength;
		}
		
		return $layoutContents;
		
	}
	
	static function import($postId, $layoutContents) {
		delete_post_meta($postId, '_ags_layouts_content');
		foreach ( str_split($layoutContents, AGSLayoutsDownloader::MAX_DB_CHUNK_LENGTH) as $chunkNo => $chunk ) {
			add_post_meta($postId, '_ags_layouts_content', $chunkNo.':'.$chunk);
		}
	}
	
	static function importExtraData($postId, $extraData) {
		if ( isset($extraData['widgets']) ) {
			update_post_meta($postId, '_ags_layouts_widgets', wp_slash($extraData['widgets']) );
		} else {
			delete_post_meta($postId, '_ags_layouts_widgets');
		}
		
		if ( isset($extraData['caldera_forms']) ) {
			update_post_meta($postId, '_ags_layouts_caldera_forms', wp_slash($extraData['caldera_forms']) );
		} else {
			delete_post_meta($postId, '_ags_layouts_caldera_forms');
		}
		
		if ( isset($extraData['agsxto']) ) {
			update_post_meta($postId, '_ags_layouts_agsxto', wp_slash($extraData['agsxto']) );
		} else {
			delete_post_meta($postId, '_ags_layouts_agsxto');
		}
		
		if ( isset($extraData['diviModulePresets']) ) {
			update_post_meta($postId, '_ags_layouts_divi_module_presets', wp_slash($extraData['diviModulePresets']) );
		} else {
			delete_post_meta($postId, '_ags_layouts_divi_module_presets');
		}
		
		if ( isset($extraData['config']) ) {
			update_post_meta($postId, '_ags_layouts_config', wp_slash($extraData['config']) );
		} else {
			delete_post_meta($postId, '_ags_layouts_config');
		}
		
	}
	
	
	static function getImportPostContent($postId) {
		$content = get_post_meta($postId, '_ags_layouts_content');
		if ($content) {
			$contentChunks = array();
			foreach ( $content as $chunk ) {
				$colonPos = strpos($chunk, ':');
				$contentChunks[ (int) substr($chunk, 0, $colonPos) ] = substr($chunk, $colonPos + 1);
			}
			
			return implode('', $contentChunks);
		}
	}
	
	static function getImportPostId($userId=null, $multiple = false) {
		if (empty(self::$importPostId)) {
			if (!$userId) {
				$userId = get_current_user_id();
			}
			$ids = get_posts(array(
				'author' => $userId,
				'post_type' => 'wplayouts-siteimport',
				'post_status' => 'private',
				'posts_per_page' => 1,
				'fields' => 'ids'
			));
			self::$importPostId = empty($ids) ? self::createImportPost() : $ids[0];
		}
		return self::$importPostId;
	}
	
	static function getAllImportPostIds() {
		$previewPageIds = get_posts(array(
			'post_type' => 'wplayouts-siteimport',
			'post_status' => 'private',
			'nopaging' => true,
			'fields' => 'ids'
		));
		return $previewPageIds;
	}
	
	static function createImportPost() {
		$importPostId = wp_insert_post(array(
			'post_type' => 'wplayouts-siteimport',
			'post_status' => 'private'
		));
		
		if ($importPostId && is_numeric($importPostId)) {
			return $importPostId;
		}
	}
	
	static function onUserDeleted($deletedUserId) {
		wp_delete_post(self::getImportPostId($deletedUserId), true);
	}
	
	// Hooked in main plugin file
	static function onPluginDeactivate() {
		foreach (self::getAllImportPostIds() as $postId) {
			wp_delete_post($postId, true);
		}
	}
	
	static function layoutEditPage($layoutId) {
		include(__DIR__.'/edit-page.php');
	}
}

add_action('init', array('AGSLayoutsSiteImporter', 'onInit'), 99);
add_action('deleted_user', array('AGSLayoutsSiteImporter', 'onUserDeleted'));
add_action('remove_user_from_blog', array('AGSLayoutsSiteImporter', 'onUserDeleted'));

include( __DIR__.'/../../includes/site-import/functions.php' );