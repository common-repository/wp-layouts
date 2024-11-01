<?php
/**
 * Contains code copied from and/or based on Elementor Plugin
 * copyright Elementor, licensed under GNU General Public License version 3 (GPLv3) or later.
 * See the license.txt file in the WP Layouts plugin root directory for more information and licenses
 *
 */


AGSLayouts::VERSION; // Access control

class AGSLayoutsElementor {
	
	private static $screenshotContents;
	
	public static function setup() {
		/* Hooks */
		add_action('elementor/editor/before_enqueue_scripts', array('AGSLayoutsElementor', 'editorScripts'));
		
		add_filter('ags_layouts_screenshot_content_unfiltered', array('AGSLayoutsElementor', 'addScreenshotBuilderDataOverride'));
		add_filter('ags_layouts_screenshot_content_filtered', array('AGSLayoutsElementor', 'removeScreenshotBuilderDataOverride'));
		
		add_filter('elementor/document/urls/preview', array('AGSLayoutsElementor', 'filterPreviewFrameUrl'));
	}
	
	public static function editorScripts() {
		AGSLayouts::frontendScripts(true);
		self::scripts();
	}
	
	public static function scripts() {
        wp_enqueue_script('ags-layouts-elementor', AGSLayouts::$pluginBaseUrl.'integrations/Elementor/elem.js', array('jquery', 'ags-layouts-util', 'wp-i18n'), AGSLayouts::VERSION);//redid
        wp_set_script_translations('ags-layouts-elementor', 'wp-layouts-td', AGSLayouts::$pluginBaseUrl.'languages');
	}
	
	static function addScreenshotBuilderDataOverride($screenshotContents) {
		self::$screenshotContents = json_decode($screenshotContents, true);
		add_filter('elementor/frontend/builder_content_data', array('AGSLayoutsElementor', 'overrideBuilderDataForScreenshot'));
	}
	
	static function removeScreenshotBuilderDataOverride() {
		remove_filter('elementor/frontend/builder_content_data', array('AGSLayoutsElementor', 'overrideBuilderDataForScreenshot'));
	}
	
	static function overrideBuilderDataForScreenshot() {
		return self::$screenshotContents;
	}
	
	public static function preUploadProcess($contents) {
		$contentsArray = json_decode($contents, true);
		if ($contentsArray) {
			self::fixImageArrays($contentsArray);
			$newContents = json_encode($contentsArray);
			return $newContents ? $newContents : $contents;
		}
		return $contents;
	}
	
	private static function fixImageArrays(&$contents) {
		foreach ($contents as &$field) {
			if (is_array($field)) {
				$isArrayOfImages = array_reduce($field, array('AGSLayoutsElementor', 'isImageSpecification'), true);
				if ($isArrayOfImages) {
					$field = array_map(array('AGSLayoutsElementor', 'fixImageSpecification'), $field);
				} else {
					self::fixImageArrays($field);
				}
			}
		}
	}
	
	static function isImageSpecification($siblingsResult, $testValue) {
		return $siblingsResult && !empty($testValue['id']) && !empty($testValue['url']);
	}
	
	static function fixImageSpecification($imageSpec) {
		$imageSpec['id'] = 'agslayouts.id:'.$imageSpec['url'];
		return $imageSpec;
	}
	
	static function setupPreviewPost($previewPostId, $layoutContents) {
		update_post_meta($previewPostId, '_elementor_data', $layoutContents);
		update_post_meta($previewPostId, '_elementor_edit_mode', 'builder');
		update_post_meta($previewPostId, '_elementor_template_type', 'post');
		
		return admin_url('post.php?post='.$previewPostId.'&action=elementor');
	}
	
	// phpcs:disable WordPress.Security.NonceVerification -- just filtering a URL, not a CSRF risk
	static function filterPreviewFrameUrl($previewUrl) {
		if (AGSLayoutsPreviewer::isLayoutPreview() && isset($_GET['ags_layouts_preview']) ) {
			$previewUrl .= (strpos($previewUrl, '?') === false ? '?' : '&')
							.'ags_layouts_preview='.( (int) $_GET['ags_layouts_preview'] )
							.( empty($_GET['previewKey']) ? '' : '&previewKey='.urlencode( sanitize_text_field( wp_unslash( $_GET['previewKey'] ) ) ) );
		}
		return $previewUrl;
	}
	// phpcs:enable WordPress.Security.NonceVerification
}

AGSLayoutsElementor::setup();