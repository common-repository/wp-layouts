<?php
AGSLayouts::VERSION; // Access control

$step = ( empty($_GET['step']) || $_GET['step'] == 1 ) ? 1 : (int) $_GET['step'];
?>
<div id="ags-layouts-settings">
	<div id="ags-layouts-settings-header">
		<?php if ( AGSLayouts::IS_PACKAGED_LAYOUT ) { ?>
		<h1><?php echo esc_html(AGSLayouts::getPackagedLayoutConfig('pageTitle')); ?></h1>
		<?php } else if ( AGSLayouts::isDemoImportPage() ) { ?>
            <h1><?php esc_html_e('Import Demo Data', 'wp-layouts-td');?></h1>
		<?php } else { ?>
            <h1><?php esc_html_e('WP Layouts Site Importer', 'wp-layouts-td');?></h1>
		<div id="ags-layouts-settings-header-links">
            <a id="ags-layouts-settings-header-link-settings" href="admin.php?page=ags-layouts-settings"><?php esc_html_e('Settings', 'wp-layouts-td');?></a>
            <a id="ags-layouts-settings-header-link-support" href="https://support.wpzone.co/" target="_blank"><?php esc_html_e('Support', 'wp-layouts-td');?></a>
        </div>
		<?php } ?>
	</div>
	<div id="ags-layouts-site-import">
        <?php
        if ( $step != 1 ) {
            if ( isset($_GET['ags-layouts-site-import-nonce']) && wp_verify_nonce( sanitize_key( wp_unslash( $_GET['ags-layouts-site-import-nonce'] ) ), 'ags-layouts-site-import') ) {
                include( __DIR__.'/site-import/step'.( (int) $step ).'.php' );
            } else {
                // Nonce is not valid; assume that it has expired
                ?>
                <p class="ags-layouts-notification ags-layouts-notification-info">
                    <?php esc_html_e('The import process has timed out. Please try again.', 'wp-layouts-td');?>
                </p>
            </div>
            <?php
    }
        } else if ( AGSLayouts::IS_PACKAGED_LAYOUT || AGSLayouts::isDemoImportPage() ) {
        ?>
            <form id="ags-layouts-site-import-packaged">
				<?php
				if ( AGSLayouts::isDemoImportPage() ) {
					$wplVersion = AGSLayouts::getPackagedLayoutConfig('wplVersion');
					if ( version_compare(AGSLayouts::VERSION, $wplVersion) < 0 ) {
				?>
				<p class="ags-layouts-notification ags-layouts-notification-info">
                    <?php esc_html_e('This content was created with a newer version of WP Layouts than the version installed on your site. We recommend that you update to the latest version of WP Layouts before importing this content.', 'wp-layouts-td');?>
				</p>
				<?php
					}
				}
				?>
			
                <div id="ags-layouts-site-import-intro">
                    <?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- layout config HTML strings were escaped previously
					echo AGSLayouts::getPackagedLayoutConfig('step1Html');
					?>
                </div>
				
                <div id="ags-layouts-site-import-select-container">
					<?php if ( count( AGSLayouts::getPackagedLayoutConfig('layouts') ) > 1 ) { ?>
					
					<select id="ags-layouts-site-import-select">
						<?php
						foreach (AGSLayouts::getPackagedLayoutConfig('layouts') as $layoutId => $layoutDetails) {
							echo '<option value="'.esc_attr($layoutId).'">'.esc_html($layoutDetails['name']).'</option>';
						}
						?>
					</select>
					
					<?php } else { ?>
					
					<input id="ags-layouts-site-import-select" type="hidden" value="<?php echo esc_attr( key( AGSLayouts::getPackagedLayoutConfig('layouts') ) ); ?>">
                    <div id="ags-layouts-site-import-item">
					    <h2 id="ags-layouts-site-import-item-name"><?php $layoutDetails = current( AGSLayouts::getPackagedLayoutConfig('layouts') ); echo esc_html($layoutDetails['name']); ?></h2>
                    </div>
					
					<?php } ?>
				
                </div>
                <div id="ags-layouts-site-import-continue-container">
                    <button id="ags-layouts-site-import-continue" class="aspengrove-btn-primary"><?php esc_html_e('Continue', 'wp-layouts-td');?></button>
                </div>
            </form>
        <?php } ?>
		
        </div>
		
	
</div>
<?php if ( AGSLayouts::IS_PACKAGED_LAYOUT || AGSLayouts::isDemoImportPage() ) { ?>
<p style="margin-top: -20px; margin-left: 10px; color: #999; font-style: italic;">
	<?php
	printf(
		// translators: %s is a link tag
		esc_html__('Powered by %sWP Layouts%s - easily import, export, and store page builder content and entire sites, and package your own standalone demo data / site content importers!', 'wp-layouts-td'),
		'<a href="https://wplayouts.space/" target="_blank" style="color: #317873;">',
		'</a>'
	);
	?>
</p>
<?php } ?>

<?php if ( $step == 1 ) { ?>
<script>
jQuery(document).ready(function($) {
	window.ags_layouts_admin_config.editingPostId = <?php echo( (int) AGSLayoutsSiteImporter::getImportPostId() );?>;
	window.ags_layouts_admin_config.siteImportStep2Url = <?php echo(
json_encode(
	add_query_arg(
		array(
			'page' => AGSLayouts::IS_PACKAGED_LAYOUT ? 'ags-layouts-package-import' : ( AGSLayouts::isDemoImportPage() ? 'ags-layouts-demo-import' : 'ags-layouts-site-import' ),
			'step' => 2,
			'ags-layouts-site-import-nonce' => rawurlencode( wp_create_nonce('ags-layouts-site-import') )
		),
		admin_url( 'admin.php' )
	)
)
); ?>;
	<?php if (AGSLayouts::IS_PACKAGED_LAYOUT || AGSLayouts::isDemoImportPage()) { ?>
	
	function loadLayoutImage() {
	
		var $image = $('#ags-layouts-site-import-image');
		if (!$image.length) {
			$image = $('<img id="ags-layouts-site-import-image">').insertAfter('#ags-layouts-site-import-item-name');
		}
		
		$image.attr('src', ags_layouts_api_url + (ags_layouts_api_url.indexOf('?') === -1 ? '?' : '&') + 'action=ags_layouts_get_image&image=L&layoutId=' + $('#ags-layouts-site-import-select').val() );
		
	}
	
	$('#ags-layouts-site-import-select').change(loadLayoutImage);
	loadLayoutImage();
	
	$('#ags-layouts-site-import-packaged').submit(function() {
		var dialogOptions = {
			title: '',
			autoLoader: true,
			reverseButtonOrder: true,
			firstButtonClass: 'aspengrove-btn-primary',
			buttonClass: 'aspengrove-btn-secondary',
			pageName: 'import-site',
			container: $('#ags-layouts-site-import'),
			buttons: {}
		};
		
		var dialog = agsLayoutsDialog.create(dialogOptions);
		
		ags_layout_import(
			{
				layoutId: $('#ags-layouts-site-import-select').val(),
				layoutName: $('#ags-layouts-site-import-select :selected').text()
			},
			function() {
				location.href = window.ags_layouts_admin_config.siteImportStep2Url;
			},
			'site-importer',
			dialog,
			dialog.element.find('.ags-layouts-dialog-content'),
			$('#ags-layouts-site-import form:first'),
			'replace',
			true
		);
		
		return false;
	});
	
	<?php } else { ?>
	
	ags_layout_import_ui(
		'site-importer',
		$('#ags-layouts-site-import'),
		'layouts_my',
		function() {
			location.href = window.ags_layouts_admin_config.siteImportStep2Url;
		},
		'replace',
		true
	);
	
	
	<?php } ?>
});
</script>
<?php } ?>