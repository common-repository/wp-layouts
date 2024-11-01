<?php
/**
 * Contains code copied from and/or based on Divi by Elegant Themes
 * See the license.txt file in the WP Layouts plugin root directory for more information and licenses
 *
 */


AGSLayouts::VERSION; // Access control

class AGSLayoutsDivi {

	const DIVI_LIBRARY_SAVE_ACTION = 'et_fb_save_layout';
	
	public static function setup() {
		
		/* Hooks */
		add_action('admin_enqueue_scripts', array('AGSLayoutsDivi', 'scriptsAdmin'));
		add_action('wp_enqueue_scripts', array('AGSLayoutsDivi', 'scriptsFrontend'));
		add_action('wp_ajax_'.self::DIVI_LIBRARY_SAVE_ACTION, array('AGSLayoutsDivi', 'ajaxInterceptDiviLibrarySave'), 1);
		add_action('wp_ajax_ags_layouts_get_temp_layout_contents', array('AGSLayoutsDivi', 'ajaxGetTempLayoutContents'));
		add_action('wp_ajax_ags_layouts_get_tb_templates', array('AGSLayoutsDivi', 'ajaxGetTbTemplates'));
		add_action('wp_ajax_ags_layouts_get_tb_id', array('AGSLayoutsDivi', 'getTbId'), 9);
		
		add_filter('et_builder_library_modal_custom_tabs', function($libraryTabs) {
            $libraryTabs['ags-layouts'] = esc_html__('WP Layouts', 'wp-layouts-td');
            $libraryTabs['ags-layouts-my'] = esc_html__('My WP Layouts', 'wp-layouts-td');
			return $libraryTabs;
		});
	}
	
	static function getTbId() {
	
		if ( isset($_GET['template']) && !empty($_GET['type']) && is_numeric($_GET['template'])
				&& !empty($_GET['nonce'])
				&& wp_verify_nonce(sanitize_key(wp_unslash($_GET['nonce'])), 'ags-layouts-ajax') ) {
			
			// wp-layouts\includes\get_image.php
			$type = sanitize_text_field( wp_unslash( $_GET['type'] ) );
			
			// Following code copied from the Divi theme, see comment near top of this file for license and copyright, modified
			
			// Divi\includes\builder\frontend-builder\theme-builder\api.php, function et_theme_builder_api_create_layout
			et_builder_security_check( 'theme_builder', 'edit_others_posts' );
			
			// Divi\includes\builder\frontend-builder\theme-builder\admin.php
			// Divi\includes\builder\frontend-builder\theme-builder\theme-builder.php
			$templates = et_theme_builder_get_theme_builder_template_ids( true );
			$post_type = et_theme_builder_get_valid_layout_post_type( $type );
			
			if ( !empty( $templates[ $_GET['template'] ] ) && $post_type ) {
				$post_id = et_theme_builder_insert_layout( array(
					'post_type' => $post_type,
				) );
				
				if ( !is_wp_error( $post_id ) ) {
				
					// Divi\includes\builder\core.php, function et_builder_enable_for_post
					update_post_meta( $post_id, '_et_pb_show_page_creation', 'off' );
					
					// Divi\includes\builder\frontend-builder\theme-builder\theme-builder.php
					update_post_meta($templates[ (int) $_GET['template'] ] , '_et_'.$type.'_layout_id', $post_id );
					update_post_meta($templates[ (int) $_GET['template'] ] , '_et_'.$type.'_layout_enabled', '1' );
					
					wp_send_json_success( $post_id );
				}
				
			}
			
			// End code copied from the Divi theme
			
		}
	}
	
	public static function scriptsFrontend() {
		if (self::shouldLoadAdminScriptsOnFrontend()) {
			self::scriptsAdmin();
		}
	}
	
	public static function scriptsAdmin() {
        wp_enqueue_script('ags-layouts-divi', AGSLayouts::$pluginBaseUrl.'integrations/Divi/divi.js', array('jquery', 'wp-i18n'), AGSLayouts::VERSION);
        wp_localize_script('ags-layouts-divi', 'ags_layouts_divi', array(
			'ajaxNonce' => wp_create_nonce('ags-layouts-ajax')
		));
        wp_set_script_translations('ags-layouts-divi', 'wp-layouts-td', AGSLayouts::$pluginBaseUrl.'languages');
	}
	
	public static function ajaxInterceptDiviLibrarySave() {
		if (
			isset($_POST['et_layout_new_cat']) && $_POST['et_layout_new_cat'] == '__AGS_LAYOUTS__'
			&& isset($_POST['et_layout_name']) && isset($_POST['et_layout_type']) && isset($_POST['et_layout_content'])
			// The following line was copied from Divi and modified (Divi/includes/builder/functions.php)
			&& isset($_POST['et_fb_save_library_modules_nonce']) && wp_verify_nonce( sanitize_key(wp_unslash($_POST['et_fb_save_library_modules_nonce'])), 'et_fb_save_library_modules_nonce' )
		) {
			update_user_meta(
				get_current_user_id(),
				'_ags_layouts_temp_contents',
				array(
					sha1('ags_layouts_'.wp_get_session_token()),
					sanitize_text_field( wp_unslash( $_POST['et_layout_name'] ) ),
					$_POST['et_layout_type'] == 'layout',
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized -- purposefully not unslashed since update_user_meta expects slashed data and unslashing twice causes issues with JSON; the only purpose of saving this value is so it can be output to the browser for later processing, no sanitization is performed here
					$_POST['et_layout_content']
				)
			);

			setcookie('ags_layouts_divi_ready', 1, 0, "/");
			exit;
		}
	}
	
	public static function ajaxGetTempLayoutContents() {
		$contents = get_user_meta(get_current_user_id(), '_ags_layouts_temp_contents', true);
		setcookie('ags_layouts_divi_ready', 0, 0, "/");
		if (@count($contents) == 4 && $contents[0] == sha1('ags_layouts_'.wp_get_session_token()) ) {
			$response = array(
				'name' => $contents[1],
				'contents' => $contents[3]
			);
			if ($contents[2]) {
				$response['isFullPage'] = true;
			}
			wp_send_json_success($response);
		}
		wp_send_json_error();
	}
	
	public static function shouldLoadAdminScriptsOnFrontend() {
		return function_exists('et_core_is_fb_enabled') && et_core_is_fb_enabled();
	}
	
	public static function preInsertProcess($shortcode) {
		$containsFullwidthModule = ( strpos($shortcode, '[et_pb_fullwidth_') !== false );
	
		$requiredShortcodeHierarchy =
			$containsFullwidthModule
			? array(
				'et_pb_section' => 'fullwidth="on"'
			)
			: array(
				'et_pb_column' => '',
				'et_pb_row' => '',
				'et_pb_section' => ''
			);
		
		$hierarchyExists = true;
		foreach ($requiredShortcodeHierarchy as $requiredShortcodeTag => $newShortcodeTagArgs) {
			$hierarchyExists = $hierarchyExists && strpos($shortcode, '[/'.$requiredShortcodeTag.']');
			if (!$hierarchyExists) {
				$shortcode = '['.$requiredShortcodeTag.(empty($newShortcodeTagArgs) ? '' : ' '.$newShortcodeTagArgs).']'.$shortcode.'[/'.$requiredShortcodeTag.']';
			}
		}
		
		return $shortcode;
	}
	
	static function processExtraData($extraData) {
		// NOTE: $extraData has not yet been sanitized!
	
		$processedExtraData = array();
		if (isset($extraData['fullPageId'])) {
			$customCSS = get_post_meta( (int) $extraData['fullPageId'], '_et_pb_custom_css', true);
			$customCSS = @trim($customCSS);
			$processedExtraData['customCSS'] = empty($customCSS) ? '' : $customCSS;
		}
		return $processedExtraData;
	}
	
	static function importExtraData($postId, $importLocation, $extraData) {
		if (isset($extraData['customCSS'])) {
			$customCSS = wp_kses_post( $extraData['customCSS'] );
		
			if ($importLocation != 'replace') {
				$currentCSS = get_post_meta($postId, '_et_pb_custom_css', true);
			}
			
			switch($importLocation) {
				case 'replace':
					$newCSS = $extraData['customCSS'];
					break;
				case 'above':
					$newCSS = $extraData['customCSS']."\n".$currentCSS;
					break;
				case 'below':
					$newCSS = $currentCSS."\n".$extraData['customCSS'];
					break;
			}
			
			update_post_meta($postId, '_et_pb_custom_css', $newCSS);
		}

	}
	
	static function setupPreviewPost($previewPostId, $layoutContents) {
		$layoutContents = self::preInsertProcess($layoutContents);
		// Following code based on the Divi theme, see comment near top of this file for license and copyright, modified
		
		// Update builder status
		$activate_builder = update_post_meta( $previewPostId, '_et_pb_use_builder', 'on' );
		
		$previewUrl = et_fb_get_builder_url( get_permalink($previewPostId) );
		
		// End code based on the Divi theme
		return $previewUrl;
	}
}

AGSLayoutsDivi::setup();