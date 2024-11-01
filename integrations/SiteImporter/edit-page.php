<?php AGSLayouts::VERSION; // Access control ?>

<div id="ags-layouts-edit-site-importer-page">
	
	<?php
	if ( isset($_POST['newLayoutId']) && isset($_GET['edit']) ) {
		try {
			if ( empty( $_POST['ags_layouts_nonce'] ) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['ags_layouts_nonce'])), 'ags-layouts-site-export-edit') ) {
				throw new Exception();
			}
			$result = AGSLayoutsApi::replace_layout( array( 'oldLayoutId' => (int) $_GET['edit'], 'newLayoutId' => (int) $_POST['newLayoutId'] ) );
			if ( empty( $result['success'] ) ) {
				throw new Exception();
			} else {
?>
<p class="ags-layouts-notification ags-layouts-notification-success">
    <?php esc_html_e('The site export has been updated.', 'wp-layouts-td'); ?>
</p>
<?php
			}
		} catch (Exception $ex) {
?>
<p class="ags-layouts-notification ags-layouts-notification-error">
    <?php esc_html_e('Something went wrong while updating the site export. Your security token may have expired; please try again.', 'wp-layouts-td'); ?>
</p>
<?php
		}
	}
	?>
	
	<p>
		<?php esc_html_e('To modify this site export, please create a new site export to replace it with.
							Once you have confirmed that your new site export works as expected, you can replace the previous export with the new data.
							When the old export is replaced, it will no longer appear in your WP Layouts plugin, but any references to the old export ID will continue to work using the data from the new export.
							The read key (used for authentication purposes by packaged exports) will be copied from the old export to the new export, and any existing read key on the new export will be overwritten.',
							'wp-layouts-td'
			); ?>
	</p>
	
	<form method="post">
		<div id="ags-layouts-edit-site-importer-replacement">
			<label><?php esc_html_e('New export:', 'wp-layouts-td');?></label>
			<div id="ags-layouts-edit-site-importer-replacement-export"></div>
			<button id="ags-layouts-edit-site-importer-replacement-export-add" class="aspengrove-btn-add" type="button"><?php esc_html_e('Select Export', 'wp-layouts-td');?></button>
		</div>
		
		<?php wp_nonce_field('ags-layouts-site-export-edit', 'ags_layouts_nonce'); ?>
		<button id="ags-layouts-edit-site-importer-button" class="aspengrove-btn-secondary"><?php esc_html_e('Update Site Export', 'wp-layouts-td');?></button>
	</form>
</div>

<script>
var agsLayoutsSiteImporterEditId = <?php echo( (int) $_GET['edit'] ); ?>;
jQuery(document).ready(page_ags_layouts_site_importer_edit);
</script>