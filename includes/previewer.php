<?php
AGSLayouts::VERSION; // Access control

class AGSLayoutsPreviewer {
	private static $previewPostId, $allPreviewPostIds, $disablePostsFilter;
	
	// phpcs:disable WordPress.Security.NonceVerification -- previewing layouts is not a CSRF risk
	public static function handlePreviewRequest() {
	
		self::$disablePostsFilter = true;
		
		// Check for elementor post GET variable, if it is set then call onInit directly with a post override
		if (empty($_GET['post'])) {
			add_action( 'wp', array('AGSLayoutsPreviewer', 'onInit') );
		} else {
			self::onInit( (int) $_GET['post']);
		}
		
	}
	// phpcs:enable WordPress.Security.NonceVerification
	
	// phpcs:disable WordPress.Security.NonceVerification -- previewing layouts is not a CSRF risk
	static function onInit($currentPostId=null) {
		self::$previewPostId = self::getPreviewPageId();
		if (!self::$previewPostId) {
			self::$previewPostId = self::createPreviewPage();
		}
		
		if (!is_numeric( $currentPostId )) {
			global $post;
			if (isset($post) && isset($post->ID)) {
				$currentPostId = $post->ID;
			}
		}
		
		if ($currentPostId && $currentPostId == self::$previewPostId) {
			if (
				empty($_GET['previewKey'])
				|| $_GET['previewKey'] != get_post_meta(self::$previewPostId, '_ags_layouts_preview_key', true)
			) {
                wp_die(esc_html__('This layout preview is no longer available.', 'wp-layouts-td'));
			}
			
			add_filter('the_title', array('AGSLayoutsPreviewer', 'filterPostTitle'), 1, 2);
			
			if (!isset($_GET['elementor-preview'])) {
				add_action('wp_footer', array('AGSLayoutsPreviewer', 'outputPreviewNotice'));
			}
			
			// Required for Elementor:
			add_action('admin_print_footer_scripts', array('AGSLayoutsPreviewer', 'outputPreviewNotice'));
			
			add_filter('body_class', array('AGSLayoutsPreviewer', 'addPreviewBodyClasses'));
			add_filter('admin_body_class', array('AGSLayoutsPreviewer', 'addPreviewBodyClasses'));
			add_action('admin_print_footer_scripts', array('AGSLayoutsPreviewer', 'addPreviewBodyClassesScript'));
		} else {
		
			// phpcs:ignore ET.Sniffs.ValidatedSanitizedInput.InputNotValidated -- the $_GET['ags_layouts_preview'] is checked before this file is included
			$ags_layouts_preview = (int) $_GET['ags_layouts_preview'];
			
			$layout = self::getLayout( $ags_layouts_preview );
			if (empty($layout)) {
                wp_die(esc_html__('The layout could not be retrieved.', 'wp-layouts-td'));
			}
			
			if (empty($layout['images'])) {
				$layoutContents = $layout['contents'];
			} else {
				$insertions = array();
				foreach ($layout['images'] as $i => $image) {
					foreach ($image['indexes'] as $index => $field) {
						$insertions[$index] = admin_url( 'admin-ajax.php?action=ags_layouts_get_image&layoutId='.$ags_layouts_preview.'&image='.$image['file'] );
					}
				}
				
				$origLayoutContents = $layout['contents'];
			
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
			
			$previewUrl = get_permalink(self::$previewPostId);
			
			// phpcs:ignore ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized -- checked against a set of fixed values in the following switch
			$layoutEditor = isset($_GET['layoutEditor']) ? $_GET['layoutEditor'] : '';
			
			switch( $layoutEditor ) {
				case 'beaverbuilder':
					if (!class_exists('AGSLayoutsBB')) {
                        wp_die(sprintf( esc_html__('The layout %s was created with Beaver Builder. Please enable Beaver Builder to view this preview.', 'wp-layouts-td' ), esc_html( $layout['name'] )));
					}
					$previewUrl = AGSLayoutsBB::setupPreviewPost(self::$previewPostId, $layoutContents);
					$skipUpdatePost = true;
					break;
				case 'elementor':
					if (!class_exists('AGSLayoutsElementor')) {
                        wp_die(sprintf( esc_html__('The layout %s was created with Elementor. Please enable Elementor to view this preview.', 'wp-layouts-td' ), esc_html( $layout['name'] )));
					}
					$previewUrl = AGSLayoutsElementor::setupPreviewPost(self::$previewPostId, $layoutContents);
					$skipUpdatePost = true;
					break;
				case 'divi':
					if (!class_exists('AGSLayoutsDivi')) {
                        wp_die(sprintf( esc_html__('The layout %s was created with Divi. Please enable Divi to view this preview.', 'wp-layouts-td' ), esc_html( $layout['name'] )));
					}
					$previewUrl = AGSLayoutsDivi::setupPreviewPost(self::$previewPostId, $layoutContents);
					break;
				case 'gutenberg':
					if (!class_exists('AGSLayoutsGutenberg')) {
                        wp_die(sprintf( esc_html__('The layout %s was created with the WordPress block editor (Gutenberg). Please enable the WordPress block editor to view this preview.', 'wp-layouts-td' ), esc_html( $layout['name'] )));
					}
					$previewUrl = AGSLayoutsGutenberg::setupPreviewPost(self::$previewPostId, $layoutContents);
					break;
				default:
                    wp_die(esc_html__('Preview is not supported for this layout.','wp-layouts-td'));
			}
			
			update_post_meta(self::$previewPostId, '_ags_layouts_preview_name', $layout['name']);
			update_post_meta(self::$previewPostId, '_ags_layouts_preview_editor', $layoutEditor);
			$previewKey = wp_generate_password(20, false);
			update_post_meta(self::$previewPostId, '_ags_layouts_preview_key', $previewKey);
			
			if (empty($skipUpdatePost)) {
				$previewPost = get_post(self::$previewPostId);
				if (empty($previewPost)) {
					return;
				}
				$previewPost->post_content = $layoutContents;
				
				add_filter('wp_revisions_to_keep', array('AGSLayoutsPreviewer', 'disableRevisions'));
				wp_update_post($previewPost);
				remove_filter('wp_revisions_to_keep', array('AGSLayoutsPreviewer', 'disableRevisions'));
			}
			
			$previewUrl .= (strpos($previewUrl, '?') === false ? '?' : '&').'ags_layouts_preview='.$ags_layouts_preview.'&previewKey='.$previewKey;
			wp_redirect($previewUrl);
		}
		
	}
	// phpcs:enable WordPress.Security.NonceVerification
	
	static function getPreviewPageId($previewPageUserId=null, $multiple = false) {
		self::$disablePostsFilter = true;
		if (!$previewPageUserId) {
			$previewPageUserId = get_current_user_id();
		}
		$previewPageIds = get_posts(array(
			'author' => $previewPageUserId,
			'post_type' => 'page',
			'post_status' => 'private',
			'meta_key' => '_ags_layouts_preview_page',
			'posts_per_page' => 1,
			'fields' => 'ids'
		));
		self::$disablePostsFilter = false;
		return $multiple ? $previewPageIds : ( empty($previewPageIds) ? null : $previewPageIds[0] );
	}
	
	static function getAllPreviewPageIds() {
		self::$disablePostsFilter = true;
		$previewPageIds = get_posts(array(
			'post_type' => 'page',
			'post_status' => 'private',
			'meta_key' => '_ags_layouts_preview_page',
			'nopaging' => true,
			'fields' => 'ids'
		));
		self::$disablePostsFilter = false;
		return $previewPageIds;
	}
	
	static function createPreviewPage() {
		$previewPostId = wp_insert_post(array(
			'post_title' => esc_html__('WP Layouts Preview', 'wp-layouts-td'),
			'post_type' => 'page',
			'post_status' => 'private'
		));
		
		if ($previewPostId && is_numeric($previewPostId)) {
			update_post_meta($previewPostId, '_ags_layouts_preview_page', 1);
			return $previewPostId;
		}
	}
	
	static function outputPreviewNotice() {
		$layoutName = get_post_meta(self::$previewPostId, '_ags_layouts_preview_name', true);
        ?>
        <div class="ags_layouts_preview_notice">
            <?php echo sprintf( esc_html__('This is a preview of the layout %s. Changes made here will not be saved in the layout.', 'wp-layouts-td' ), '<strong>'.esc_html($layoutName).'</strong>' );?>
        </div>
        <?php

    }
	
	static function filterPostsQuery($query) {
		if (empty(self::$disablePostsFilter)) {
			if (!isset(self::$allPreviewPostIds)) {
				self::$allPreviewPostIds = self::getAllPreviewPageIds();
			}
		
			if (isset($query->query_vars['post__not_in']) && is_array($query->query_vars['post__not_in'])) {
				$query->query_vars['post__not_in'] = array_merge($query->query_vars['post__not_in'], self::$allPreviewPostIds);
			} else {
				$query->query_vars['post__not_in'] = self::$allPreviewPostIds;
			}
		}
	}
	
	static function onUserDeleted($deletedUserId) {
		foreach (self::getPreviewPageId($deletedUserId, true) as $previewPostId) {
			wp_delete_post($previewPostId, true);
		}
	}
	
	// Hooked in main plugin file
	static function onPluginDeactivate() {
		foreach (self::getAllPreviewPageIds() as $previewPostId) {
			wp_delete_post($previewPostId, true);
		}
	}
	
	static function disableRevisions() {
		return 0;
	}
	
	static function isLayoutPreview() {
		self::$previewPostId = self::getPreviewPageId();
		if (self::$previewPostId) {
			global $post;
			if (isset($post) && isset($post->ID)) {
				$currentPostId = $post->ID;
			}
			
			if ($currentPostId && $currentPostId == self::$previewPostId) {
				return true;
			}
		}
		return false;
	}
	
	static function addPreviewBodyClasses($currentClasses) {
		$editor = get_post_meta(self::$previewPostId, '_ags_layouts_preview_editor', true);
		$editorClass = 'ags-layouts-preview-'.($editor ? esc_attr($editor) : 'unknown-editor');
		return
			is_array($currentClasses)
				? array_merge($currentClasses, array('ags-layouts-preview', $editorClass))
				: $currentClasses.' ags-layouts-preview '.$editorClass;
	}
	
	static function addPreviewBodyClassesScript() {
		echo('<script>document.body.className += \' '.esc_js( implode(' ', self::addPreviewBodyClasses(array())) ).'\';</script>');
	}
	
	public static function filterPostTitle($currentTitle, $postId) {
        return $postId == self::$previewPostId ? esc_html__('Layout Preview', 'wp-layouts-td') : $currentTitle;
	}
	
	public static function getLayout($layoutId) {
		include_once(__DIR__.'/api.php');
		try {
			$response = AGSLayoutsApi::get_layout( array('layoutId' => $layoutId) );
			if (!empty($response)) {
				if (!empty($response['success']) && !empty($response['data']['contents'])) {
					return $response['data'];
				}
			}
		} catch (Exception $ex) {
			
		}
		return false;
	}
	
}
		
add_action('pre_get_posts', array('AGSLayoutsPreviewer', 'filterPostsQuery'));
add_action('deleted_user', array('AGSLayoutsPreviewer', 'onUserDeleted'));
add_action('remove_user_from_blog', array('AGSLayoutsPreviewer', 'onUserDeleted'));