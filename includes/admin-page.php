<?php AGSLayouts::VERSION; // Access control ?>

<?php
if (!get_option('ags_layouts_hide_aiil_notice')) { $aiilUrl = admin_url('plugin-install.php?s=%22AI%20Image%20Lab%22&tab=search&type=term'); ?>
    <div class="ags-layouts-aiil-notice">
        <div class="ags-layouts-aiil-notice-title">
            <img class="ags-layouts-aiil-notice-image" src="<?php echo AGSLayouts::$pluginBaseUrl. '/images/ai-image-lab-logo.svg' ?>">
            <h3><?php esc_html_e('Free AI image generation solution from WP Zone!', 'wp-layouts-td'); ?></h3>
        </div>
        <div class="ags-layouts-aiil-notice-content">
            <p><?php /* translators: %s are link tags */ printf(esc_html('Check out %sAI Image Lab by WP Zone%s, a free solution for generating custom images for your site and editing your existing images using AI-based technology!', 'wp-layouts-td'), '<a href="'.esc_url($aiilUrl).'">', '</a>'); ?>
            </p>
            <span class="ags-layouts-aiil-notice-buttons">
            <button class="ags-layouts-aiil-notice-link ags-layouts-aiil-notice-link-primary" href="<?php echo(esc_url($aiilUrl)); ?>"><?php esc_html_e('Go to Plugins Page', 'wp-layouts-td'); ?></button>
            <button class="ags-layouts-aiil-notice-link ags-layouts-aiil-notice-link-secondary" type="button" onclick="jQuery(this).parent().remove();jQuery.post(ajaxurl, {action: 'ags-layouts-aiil-notice-dismiss', _wpnonce: '<?php echo(esc_js(wp_create_nonce('ags-layouts-aiil-notice-dismiss'))); ?>'});return false;"><?php esc_html_e('Close', 'wp-layouts-td'); ?></button>
        </span>
        </div>
    </div>
<?php } ?>

<br><p class="ags-layouts-notification ags-layouts-notification-info">
    <?php echo sprintf(
        esc_html__('%sThank you for being part of the WP Layouts Beta!%s We appreciate your patience if you encounter any problems with this product. Please visit our %ssupport site%s for tutorials, FAQs, and to contact us.', 'wp-layouts-td'),
        '<strong>',
        '</strong>',
        '<a href="https://wplayouts.space/documentation/" target="_blank">',
        '</a>'
    ); ?>
</p>

<div id="ags-layouts-settings">
    <div id="ags-layouts-settings-header">
        <?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce will be checked before saving any changes
        if ( isset( $_GET['edit'] ) ) {
            include_once(__DIR__.'/api.php');
			
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce will be checked before saving any changes
            $response = AGSLayoutsApi::get_layout( array('layoutId' => (int) $_GET['edit']) );
            if (!empty($response)) {
                if (!empty($response['success']) && !empty($response['data']['contents'])) {
                    $layout = $response['data'];
                }
            }
            $pageTitle = ( empty($layout['name']) ? __('Edit Layout', 'wp-layouts-td') : sprintf( __('Edit Layout: %s', 'wp-layouts-td'), $layout['name'] ) );

        } else {
            $pageTitle = __('My Layouts', 'wp-layouts-td');
        }
        ?>

        <h1><?php echo( esc_html($pageTitle) ); ?></h1>

        <div id="ags-layouts-settings-header-links">
            <a id="ags-layouts-settings-header-link-settings" href="admin.php?page=ags-layouts-settings">Settings</a>
            <a id="ags-layouts-settings-header-link-support" href="https://wplayouts.space/documentation/" target="_blank">Support</a>
        </div>
    </div>
<?php
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce will be checked before saving any changes
if ( isset( $_GET['edit'] ) ) {

	if ( empty($layout['editor']) ) {
?>
<p class="ags-layouts-notification ags-layouts-notification-error">
    <?php esc_html_e('The requested layout could not be loaded for editing. The layout ID may be invalid, or the layout may have been deleted.', 'wp-layouts-td'); ?>
</p>
<?php
	} else {
		switch ($layout['editor']) {
			case 'site-importer':
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce will be checked before saving any changes
				AGSLayoutsSiteImporter::layoutEditPage( (int) $_GET['edit'] );
				break;
			default:
?>
<p class="ags-layouts-notification ags-layouts-notification-error">
    <?php esc_html_e('Editing is not currently supported for this layout.', 'wp-layouts-td'); ?>
</p>
<?php
		}
	}
	
	return;
}
?>


<div id="ags-layouts-container">
	<div id="ags-layouts-list-container"></div>
	<div id="ags-layouts-details" class="ags-layouts-details-none">
        <form>
            <div id="ags-layouts-details-image"></div>

            <!--
			<label>
				<?php //esc_html_e('Layout ID:', 'wp-layouts-td');?>
				<input id="ags-layouts-details-id" readonly />
			</label>
			-->

            <label>
                <?php esc_html_e('Layout Name:', 'wp-layouts-td'); ?>
                <input id="ags-layouts-details-name" required/>
            </label>

            <div id="ags-layouts-details-buttons">
                <button id="ags-layouts-details-save" class="aspengrove-btn-primary" disabled><?php esc_html_e('Save', 'wp-layouts-td'); ?></button>
                <button type="button" id="ags-layouts-details-delete" class="aspengrove-btn-secondary" disabled><?php esc_html_e('Delete', 'wp-layouts-td'); ?></button>
				<a id="ags-layouts-details-edit" class="aspengrove-btn-third" href="#"><?php esc_html_e('Edit Layout', 'wp-layouts-td'); ?></a>
            </div>
			
            <label>
                <?php esc_html_e('Read Key:', 'wp-layouts-td'); ?>
                <input id="ags-layouts-details-read-key" readonly/>
            </label>

            <div id="ags-layouts-details-read-key-buttons">
                <button type="button" id="ags-layouts-details-read-key-show" class="aspengrove-btn-primary" disabled><?php esc_html_e(' Show', 'wp-layouts-td'); ?></button>
                <button type="button" id="ags-layouts-details-read-key-reset" class="aspengrove-btn-secondary" disabled><?php esc_html_e('Reset', 'wp-layouts-td'); ?></button>
            </div>
        </form>
    </div>
	<div id="ags-layouts-loader-overlay">
		<div id="ags-layouts-loader"></div>
	</div>
</div>

<script>
jQuery(document).ready(page_ags_layouts);
</script>