<?php
/*
 * This file includes code based on parts of WordPress and the Gutenberg plugin
 * by Automattic, released under GPLv2+, licensed under GPLv3+ (see wp-license.txt
 * in the plugin root -> license directory for the license and additional credits
 * applicable to WordPress and Gutenberg, and the license.txt file in the plugin
 * root
 */

AGSLayouts::VERSION; // Access control

class AGSLayoutsGutenberg {
	
	public static function setup() {
		
		/* Hooks */
		add_action('admin_enqueue_scripts', array('AGSLayoutsGutenberg', 'scripts'));
		add_action('add_meta_boxes', array('AGSLayoutsGutenberg', 'gutenbergMetaBox'));
		add_filter('block_categories', array('AGSLayoutsGutenberg', 'registerBlockCategories'));
		
	}
	
	public static function scripts() {
		//wp_enqueue_style('ags-layouts-boilerplate', AGSLayouts::$pluginBaseUrl.'integrations/Boilerplate/boilerplate.css', array(), AGSLayouts::VERSION);
        wp_enqueue_script('ags-layouts-gutenberg', AGSLayouts::$pluginBaseUrl.'integrations/Gutenberg/gutenberg.js', array('jquery', 'wp-i18n'), AGSLayouts::VERSION);
        wp_set_script_translations('ags-layouts-gutenberg', 'wp-layouts-td', AGSLayouts::$pluginBaseUrl.'languages');
		
		// phpcs:ignore WordPress.Security.NonceVerification -- previewing layouts is not a CSRF risk
		if (!empty($_GET['ags_layouts_preview'])) {
			$layoutContents = @json_decode( get_the_content() );
			if ($layoutContents) {
				wp_localize_script('ags-layouts-gutenberg', 'ags_layouts_gutenberg_preview', $layoutContents);
			}
		}
	}
	
	public static function registerBlockCategories($blockCategories) {
		// Maybe add icon?
        $blockCategories[] = array(
            'slug' => 'ags-layouts-ags',
            'title' => esc_html__('WP Layouts', 'wp-layouts-td')
        );
        $blockCategories[] = array(
            'slug' => 'ags-layouts-my',
            'title' =>  esc_html__('My WP Layouts', 'wp-layouts-td')
        );
		return $blockCategories;
	}
	
	public static function gutenbergMetaBox() {
		if (isset($GLOBALS['post']) && function_exists('use_block_editor_for_post') && use_block_editor_for_post($GLOBALS['post'])) {
            add_meta_box('ags-layouts-gutenberg', esc_html__('Import/Export Layout', 'wp-layouts-td'), function() {
				$postContent = trim($GLOBALS['post']->post_content);
				$isNonGutenbergContent = !empty($postContent) && substr($postContent, 0, 4) != '<!--';
				if ($isNonGutenbergContent) {
?>
<p class="ags-layouts-notification ags-layouts-notification-info">
    <?php echo sprintf(esc_html__('This content doesn\'t look like it was created by the block editor (when the editor loaded). The block editor integration in WP Layouts is only designed to work with block editor content. %sClick here%s to access the integration anyway.', 'wp-layouts-td' ),
    '<a href="#ags-layouts-gutenberg" onclick="jQuery(this).parent().next().removeClass(\'hidden\').prev().remove();">',
    '</a>'
    );?>
</p>
<?php
				}
?>
<div<?php if ($isNonGutenbergContent) echo(' class="hidden"'); ?>>
    <button type="button" onclick="ags_layouts_gutenbergImportLayout();" class="aspengrove-btn-secondary"><?php esc_html_e('Import from WP Layouts', 'wp-layouts-td');?></button>
    <button type="button" onclick="ags_layouts_gutenbergExportAll();" class="aspengrove-btn-secondary"><?php esc_html_e('Export to WP Layouts', 'wp-layouts-td');?></button>
</div>
<?php
			}, get_current_screen(), 'side');
		}
	}
	
	static function setupPreviewPost($previewPostId, $layoutContents) {
		return admin_url('post.php?post='.$previewPostId.'&action=edit');
	}
}

AGSLayoutsGutenberg::setup();