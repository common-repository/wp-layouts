<?php
/**
Plugin Name:       WP Layouts
Plugin URI:        https://wplayouts.space/
Description:       Save, store and import layouts instantly, all in ONE place with the click of a button!
Version:          0.6.22
Author:            WP Layouts
License:           GPLv3
License URI:       http://www.gnu.org/licenses/gpl.html
GitLab Plugin URI: https://gitlab.com/aspengrovestudios/wp-layouts/
Text Domain:       wp-layouts-td
 */

/*
Despite the following, this project is licensed exclusively
under GPLv3 (no future versions).
This statement modifies the following text.

WP Layouts plugin
Copyright (C) 2024 WP Zone

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.

========

Credits:

This plugin includes code based on parts of WordPress and the Gutenberg plugin
by Automattic, released under GPLv2+, licensed under GPLv3+ (see wp-license.txt
in the license directory for the copyright, license, and additional credits applicable
to WordPress and Gutenberg, and the license.txt file in the license directory
for GPLv3 text).

This plugin includes code based on parts of the Divi theme and/or the
Divi Builder, copyright Elegant Themes, released under GPLv2, licensed under
GPLv3 for this project by special permission (see divi-CREDITS.md in the license
directory for credits applicable to Divi, and license.txt file in the license
directory for GPLv3 text).

This plugin includes code based on parts of Beaver Builder Plugin (Standard Version)
and/or Beaver Builder Plugin (Lite Version), copyright 2014 Beaver Builder, released
under GPLv2+ (according to the plugin's readme.txt file; fl-builder.php specifies
different licensing but we are using the licensing specified in readme.txt),
licensed under GPLv3+ (see license.txt file in the license directory for GPLv3 text).

This plugin contains code based on and/or copied from the Elementor plugin (free version),
copyright Elementor, licensed under GNU General Public License version 3 (GPLv3) or later.
See license.txt in the WP Layouts plugin root directory for the text of GPLv3.

Contains code from https://developer.mozilla.org/en-US/docs/Web/API/FileReader/readAsDataURL. Public domain.

*/

// The following line must remain before all other code in this file so that no other code
// is executed if WordPress is not loaded
register_deactivation_hook(__FILE__, array('AGSLayoutsPreviewer', 'onPluginDeactivate'));
register_deactivation_hook(__FILE__, array('AGSLayoutsSiteImporter', 'onPluginDeactivate'));

class AGSLayouts {

	const VERSION = '0.6.22';
	const API_URL = 'https://ls.wplayouts.space/wp-admin/admin-ajax.php';
	//const API_URL = 'http://localhost/dev/layoutsserver/wp-admin/admin-ajax.php';
	const SITE_EXPORT_IMPORT_CAP = 'manage_options';
	const IS_PACKAGED_LAYOUT = false;
	
	public static 	$pluginBaseUrl,
					$pluginDirectory,
					$pluginFile,
					$supportedEditors = array(
						'Gutenberg' => array(
							'displayName' => 'Gutenberg'
						),
						'SiteImporter' => array(
							'displayName' => 'WP Layouts Site Importer',
							'enableAlways' => true,
						),
						'Divi' => array(
							'displayName' => 'Divi Builder',
							'enableOnFunction' => 'ET_BUILDER_SHOULD_LOAD_FRAMEWORK',
						),
						'BeaverBuilder' => array(
							'displayName' => 'Beaver Builder',
							'enableOnClass' => 'FLBuilderLoader',
						),
						'Elementor' => array(
							'displayName' => 'Elementor',
							'enableOnClass' => 'Elementor\Plugin',
						),
					);
	public static $isDoingLayoutImage;
	private static $themeDemoData;
	
	public static function setup() {
		if (self::IS_PACKAGED_LAYOUT) {
			$contentDir = str_replace('\\', '/', trailingslashit(WP_CONTENT_DIR) );
			$currentDir = str_replace('\\', '/', __DIR__);
			
			if ( substr( $currentDir, 0, strlen($contentDir) ) != $contentDir ) {
				return;
			}
			self::$pluginBaseUrl = content_url().substr( $currentDir, strlen($contentDir) - 1 ).'/';
		} else {
			self::$pluginBaseUrl = plugin_dir_url(__FILE__);
		}
		self::$pluginDirectory = __DIR__.'/';
		self::$pluginFile = __FILE__;
		
		
		/* Hooks */
		add_action('admin_menu', array('AGSLayouts', 'registerAdminPage'), 11);
		add_action('admin_enqueue_scripts', array('AGSLayouts', 'adminScripts'), self::IS_PACKAGED_LAYOUT ? 99 : 10);
		add_action('wp_ajax_ags_layouts_get', array('AGSLayouts', 'getLayout'));
		add_action('wp_ajax_ags_layouts_get_read_key', array('AGSLayouts', 'getLayoutReadKey'));
		add_action('wp_ajax_ags_layouts_get_image', array('AGSLayouts', 'getImage'));
		
		add_action('init', array('AGSLayouts', 'loadIntegrations'),11);
		
		add_filter('et_builder_load_requests', function($requests) {
			if (isset($requests['action'])) {
				$requests['action'][] = 'ags_layouts_export';
			} else {
				$requests['action'] = [ 'ags_layouts_export' ];
			}
			
			return $requests;
		}, 99);
		
		if (!self::IS_PACKAGED_LAYOUT) {
			add_action('wp_enqueue_scripts', array('AGSLayouts', 'frontendScripts'));
		
			include_once(self::$pluginDirectory.'includes/previewer.php');
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- previewing is not an operation that needs protection from CSRF attacks
			if (!empty($_GET['ags_layouts_preview'])) {
				AGSLayoutsPreviewer::handlePreviewRequest();
			}
			
			include(self::$pluginDirectory.'includes/export-ajax.php');
		}
		
	}
	
	static function loadIntegrations() {
		if (self::IS_PACKAGED_LAYOUT) {
			@include(AGSLayouts::$pluginDirectory.'integrations/'.self::$PACKAGED_LAYOUT_CONFIG['editor'].'/'.self::$PACKAGED_LAYOUT_CONFIG['editor'].'.php');
		} else {
			foreach (self::$supportedEditors as $editorName => $editorDetails) {
				if (self::isIntegrationEnabled($editorName)) {
					@include(AGSLayouts::$pluginDirectory.'integrations/'.$editorName.'/'.$editorName.'.php');
				}
			}
		}
	}
	
	static function isIntegrationEnabled($integrationName) {
		if (self::IS_PACKAGED_LAYOUT) {
			return $integrationName == self::$PACKAGED_LAYOUT_CONFIG['editor'];
		}
	
		switch ($integrationName) {
			case 'Gutenberg':
				//return apply_filters('use_block_editor_for_post_type', true, 'page') || apply_filters('use_block_editor_for_post_type', true, 'post');
				return true;
			default:
				return self::isEnabled(self::$supportedEditors[$integrationName]);
		}
	}
	
	static function isEnabled($conditions) {
		return !empty($conditions['enableAlways'])
			|| (isset($conditions['enableOnClass']) && class_exists($conditions['enableOnClass']))
			|| (isset($conditions['enableOnFunction']) && function_exists($conditions['enableOnFunction']));
	}
	
	public static function registerAdminPage() {
		/* Admin Pages */
		
		if (self::IS_PACKAGED_LAYOUT) {
			add_submenu_page(
				self::$PACKAGED_LAYOUT_CONFIG['menuParent'],
				self::$PACKAGED_LAYOUT_CONFIG['pageTitle'],
				self::$PACKAGED_LAYOUT_CONFIG['menuName'],
				self::SITE_EXPORT_IMPORT_CAP,
				'ags-layouts-package-import',
				array('AGSLayouts', 'siteImportPage')
			);
		} else {

            include(self::$pluginDirectory.'includes/account.php');
            $isLoggedIn = AGSLayoutsAccount::isLoggedIn();

            $mainPage = add_menu_page('WP Layouts', 'WP Layouts', 'edit_posts', 'ags-layouts', array('AGSLayouts', 'adminPage'), '', 650);
            if ( self::getThemeDemoData() ) {
                add_submenu_page('ags-layouts', esc_html__( 'Import Demo Data', 'wp-layouts-td'), esc_html__( 'Import Demo Data', 'wp-layouts-td'), self::SITE_EXPORT_IMPORT_CAP, 'ags-layouts-demo-import', array('AGSLayouts', 'siteImportPage'));
            }
			if ( $isLoggedIn ) {
				add_submenu_page('ags-layouts', esc_html__( 'Site Import', 'wp-layouts-td'), esc_html__( 'Site Import', 'wp-layouts-td'), self::SITE_EXPORT_IMPORT_CAP, 'ags-layouts-site-import', array('AGSLayouts', 'siteImportPage'));
				add_submenu_page('ags-layouts', esc_html__( 'Site Export', 'wp-layouts-td'), esc_html__( 'Site Export', 'wp-layouts-td'), self::SITE_EXPORT_IMPORT_CAP, 'ags-layouts-site-export', array('AGSLayouts', 'siteExportPage'));
				add_submenu_page('ags-layouts', esc_html__( 'Site Export Packager', 'wp-layouts-td'), esc_html__( 'Site Export Packager', 'wp-layouts-td'), self::SITE_EXPORT_IMPORT_CAP, 'ags-layouts-site-export-packager', array('AGSLayouts', 'siteExportPackagerPage'));
			}
            add_submenu_page('ags-layouts', esc_html__( 'Settings', 'wp-layouts-td'), esc_html__( 'Settings', 'wp-layouts-td'), 'edit_posts', 'ags-layouts-settings', array('AGSLayouts', 'settingsPage'));
        }
		
	}
	
	public static function frontendScripts($forceIncludeAdminScripts=false) {
		/* Hooks */
		if ($forceIncludeAdminScripts
				|| (class_exists('AGSLayoutsDivi') && AGSLayoutsDivi::shouldLoadAdminScriptsOnFrontend())
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- we are just checking if the builder is in use
				|| isset($_GET['fl_builder'])
		) {
			self::adminScripts(true);
		} else {
			if (!empty(self::$isDoingLayoutImage)) {
				wp_enqueue_script('jquery');
			}
			self::registerScriptDependencies();
		}
	}
	
	public static function adminPage() {
		include(self::$pluginDirectory.'includes/admin-page.php');
	}
	
	public static function settingsPage() {
		include(self::$pluginDirectory.'includes/settings-page.php');
	}
	
	public static function siteImportPage() {
		include(self::$pluginDirectory.'includes/site-import-page.php');
	}
	
	public static function siteExportPage() {
		include(self::$pluginDirectory.'includes/site-export-page.php');
	}
	
	public static function siteExportPackagerPage() {
		include(self::$pluginDirectory.'includes/site-export-packager-page.php');
	}
	
	public static function adminScripts($forFrontend=false) {
		if (self::IS_PACKAGED_LAYOUT) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- just checking which page we are on, not a CSRF risk
			if ( !( isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] == 'admin.php' && isset($_GET['page']) && $_GET['page'] == 'ags-layouts-package-import' ) ) {
				return;
			}
			
			wp_deregister_style('ags-layouts-admin');
			wp_deregister_style('ags-layouts-dialog');
			wp_deregister_script('ags-layouts-admin');
			wp_deregister_script('ags-layouts-dialog');
			wp_deregister_script('ags-layouts-circle-progress');
		}
		
		self::registerScriptDependencies();
		wp_enqueue_style('ags-layouts-admin', self::$pluginBaseUrl.'css/admin.css', array(), self::VERSION);
		wp_enqueue_script('ags-layouts-admin', self::$pluginBaseUrl.'js/admin.js', array('jquery', 'wp-i18n'), self::VERSION);
        wp_set_script_translations('ags-layouts-admin', 'wp-layouts-td', self::$pluginBaseUrl.'languages');
		
		$adminConfig = array(
			'wpFrontendUrl' => get_home_url(),
			'wpAjaxUrl' => admin_url( 'admin-ajax.php?ags-layouts-nonce='.wp_create_nonce('ags-layouts-ajax') ),
			'pluginBaseUrl' => self::$pluginBaseUrl,
			'aiilUrl' => admin_url('plugin-install.php?s=AI%20Image%20Lab%20by%20WP%20Zone&tab=search&type=term'),
			'editorNames' => array(
                '?' => esc_html__( 'Unknown', 'wp-layouts-td'),
                'gutenberg' => 'WordPress',
                'divi' => 'Divi',
                'elementor' => 'Elementor',
                'beaverbuilder' => 'Beaver Builder',
                'site-importer' => esc_html__('Site Export', 'wp-layouts-td')
			)
		);
		$isEditingPost = ($forFrontend || (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] == 'post.php')) && isset($GLOBALS['post']);
		if ($isEditingPost) {
			$adminConfig['editingPostId'] = $GLOBALS['post']->ID;
			$adminConfig['editingPostUrl'] = get_permalink($GLOBALS['post']);
		}
		
		if (self::IS_PACKAGED_LAYOUT) {
			$adminConfig['layoutDownloadError'] = self::$PACKAGED_LAYOUT_CONFIG['layoutDownloadError'];
		}
		wp_localize_script('ags-layouts-admin', 'ags_layouts_admin_config', $adminConfig);
		
		wp_enqueue_style('ags-layouts-dialog', self::$pluginBaseUrl.'vendor/ags-dialog/ags-dialog.css', array(), self::VERSION);
		wp_enqueue_script('ags-layouts-dialog', self::$pluginBaseUrl.'vendor/ags-dialog/ags-dialog.js', array('jquery'), self::VERSION);
		wp_enqueue_script('ags-layouts-circle-progress', self::$pluginBaseUrl.'js/circle-progress.js', array('jquery'), self::VERSION);
		wp_enqueue_script('ags-layouts-xml-parser', self::$pluginBaseUrl.'js/parser.min.js', array('jquery'), self::VERSION);
		
		if (!self::IS_PACKAGED_LAYOUT) {
			wp_enqueue_script('ags-layouts-html2canvas', self::$pluginBaseUrl.'js/html2canvas.min.js', array(), self::VERSION);
			wp_enqueue_style('ags-layouts-datatables', self::$pluginBaseUrl.'vendor/datatables/datatables.min.css', array(), self::VERSION);
			wp_enqueue_script('ags-layouts-datatables', self::$pluginBaseUrl.'vendor/datatables/datatables.min.js', array('jquery'), self::VERSION);
		}
		
	}
	
	static function registerScriptDependencies() {
		wp_register_script('ags-layouts-util', self::$pluginBaseUrl.'js/util.js', array('jquery'), self::VERSION);
	}
	
	public static function getLayout() {
		include(self::$pluginDirectory.'includes/downloader.php');
		exit;
	}
	
	public static function getLayoutReadKey() {
		include(self::$pluginDirectory.'includes/get-read-key.php');
		exit;
	}
	
	public static function getImage() {
		include(self::$pluginDirectory.'includes/get_image.php');
		exit;
	}
	
	static function requireEditPermission($forPostId=null) {
		if ($forPostId === null ? !current_user_can('edit_posts') : !current_user_can('edit_post', $forPostId)) {
			exit;
		}
	}
	
	static function getThemeDemoData() {
		if (!self::$themeDemoData && self::$themeDemoData !== false) {
			$themeDemoData = apply_filters('ags_layouts_theme_demo_data', false);
			
			if ($themeDemoData !== false) {
			
				$keys = array('layouts', 'editor', 'step1Html', 'step2Html', 'buttonText', 'progressHeading', 'completeHeading', 'successHtml', 'errorHtml', 'wplVersion');
				
				/*
				As of WPL 0.6.7, the following keys are no longer generated in exported hook code.
				They are still supported for any existing implementations, but they are now prefilled with default data if they are not supplied.
				*/
				
				if (empty($themeDemoData['step1Html'])) {
					$themeDemoData['step1Html'] = '<p>'
						.esc_html__('Your theme uses WP Layouts to install demo data.', 'wp-layouts-td')
						.' '
						.(
							isset($themeDemoData['layouts']) && count($themeDemoData['layouts']) == 1
								? esc_html__('Click Continue to install the demo data shown below.')
								: esc_html__('Select which version of the demo data you would like to import, then click Continue.')
						)
						.'</p>';
				}
				
				if (empty($themeDemoData['step2Html'])) {
					$themeDemoData['step2Html'] = '<p>'.esc_html__('Choose which items you would like to import.', 'wp-layouts-td').'</p>';
				}
				
				if (empty($themeDemoData['buttonText'])) {
					$themeDemoData['buttonText'] = __('Import Demo Data', 'wp-layouts-td');
				}
				
				if (empty($themeDemoData['progressHeading'])) {
					$themeDemoData['progressHeading'] = __('Importing Demo Data...', 'wp-layouts-td');
				}
				
				if (empty($themeDemoData['completeHeading'])) {
					$themeDemoData['completeHeading'] = __('Import Complete!', 'wp-layouts-td');
				}
				
				if (empty($themeDemoData['successHtml'])) {
					$themeDemoData['successHtml'] = '<p>'
						.esc_html__('The demo data import is complete!', 'wp-layouts-td')
						.'<br>'
						.esc_html__('Please check to make sure that the import was successful.', 'wp-layouts-td')
						.'</p>';
				}
				
				if (empty($themeDemoData['errorHtml'])) {
					$themeDemoData['errorHtml'] = '<p>'
						.esc_html__('Something went wrong.', 'wp-layouts-td')
						.'<br>'
						.esc_html__('Unfortunately, the demo data import could not be completed due to an error. Please try again.', 'wp-layouts-td')
						.'</p>';
				}
				
				
				
				if ( !is_array($themeDemoData) || array_diff( $keys, array_keys($themeDemoData) ) ) {
                    trigger_error(esc_html__('Unable to load theme demo data: Invalid data', 'wp-layouts-td'));
					self::$themeDemoData = false;
					return;
				}
				
				$themeDemoData = array_intersect_key( $themeDemoData, array_combine($keys, $keys) );
				
				foreach ($themeDemoData['layouts'] as $layoutId => $layoutData) {
					if ( !is_numeric($layoutId) || !$layoutId || $layoutId != absint($layoutId) ) {
                        trigger_error(esc_html__('Unable to load theme demo data: Invalid layout ID', 'wp-layouts-td'));
						self::$themeDemoData = false;
						return;
					}
					if ( empty($layoutData['name']) || empty($layoutData['key']) || count($layoutData) != 2 ) {
                        trigger_error(esc_html__('Unable to load theme demo data: Invalid layout data', 'wp-layouts-td'));
						self::$themeDemoData = false;
						return;
					}
				}
				
				if ( $themeDemoData['editor'] != 'SiteImporter' ) {
                    trigger_error(esc_html__('Unable to load theme demo data: Unsupported editor', 'wp-layouts-td'));;
					self::$themeDemoData = false;
					return;
				}
				
			}
			
			
			self::$themeDemoData = $themeDemoData;
		}
		
		return self::$themeDemoData;
	}
	
	
	static function isDemoImportPage() {
		$screen = get_current_screen();
		return isset($screen->id) && $screen->id == 'wp-layouts_page_ags-layouts-demo-import';
	}
	
	static function getPackagedLayoutConfig($key) {
		$config = AGSLayouts::IS_PACKAGED_LAYOUT ? AGSLayouts::$PACKAGED_LAYOUT_CONFIG : self::getThemeDemoData();
		return isset($config[$key]) ? $config[$key] : null;
	}
	
}

AGSLayouts::setup();

add_action('wp_ajax_ags-layouts-aiil-notice-dismiss', function() {
	check_ajax_referer('ags-layouts-aiil-notice-dismiss');
	update_option('ags_layouts_hide_aiil_notice', 1, false);
});