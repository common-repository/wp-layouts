<?php
AGSLayouts::VERSION; // Access control

?>
<div id="ags-layouts-settings">
	<div id="ags-layouts-settings-header">
		<h1><?php esc_html_e('WP Layouts Site Export Packager', 'wp-layouts-td');?></h1>
		<div id="ags-layouts-settings-header-links">
            <a id="ags-layouts-settings-header-link-settings" href="admin.php?page=ags-layouts-settings"><?php esc_html_e('Settings', 'wp-layouts-td');?></a>
            <a id="ags-layouts-settings-header-link-support" href="https://support.wpzone.co/" target="_blank"><?php esc_html_e('Support', 'wp-layouts-td');?></a>
		</div>
	</div>
	<div id="ags-layouts-site-export-packager">
		<form method="post">
			<div>
				<?php esc_html_e('Mode:', 'wp-layouts-td');?>
				<select name="mode" required>
					<option value="standalone"><?php esc_html_e('Standalone Package', 'wp-layouts-td');?></option>
					<option value="hook"<?php if (isset($_GET['mode']) && $_GET['mode'] == 'hook') echo(' selected'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>><?php esc_html_e('Theme Demo Data Hook', 'wp-layouts-td');?></option>
				</select>
			</div><br>
			
            <div id="ags-layouts-site-export-packager-export">
                <label><?php esc_html_e('Site exports:', 'wp-layouts-td');?></label>
                <ol id="ags-layouts-site-export-packager-export-list">
                </ol>
                <button id="ags-layouts-site-export-packager-export-add" class="aspengrove-btn-add" type="button"><?php esc_html_e('Add Export', 'wp-layouts-td');?></button>
            </div>
			<label class="ags-layouts-standalone-only ags-layouts-required">
				<?php esc_html_e('Menu item name:', 'wp-layouts-td');?>
				<input name="menuName" type="text" value="Import Demo Data" required>
			</label>
			<label class="ags-layouts-standalone-only ags-layouts-required">
				<?php esc_html_e('Menu item parent slug:', 'wp-layouts-td');?>
				<input name="menuParent" type="text" value="" required>
			</label>
			<label class="ags-layouts-standalone-only ags-layouts-required">
				<?php esc_html_e('Class prefix:', 'wp-layouts-td');?>
				<input name="classPrefix" type="text" value="MyTheme_" required>
			</label>
			<label class="ags-layouts-standalone-only ags-layouts-required">
				<?php esc_html_e('Text domain:', 'wp-layouts-td');?>
				<input name="textDomain" type="text" value="mytheme" required>
			</label>
			<label class="ags-layouts-standalone-only ags-layouts-required">
				<?php esc_html_e('Import page title:', 'wp-layouts-td');?>
				<input name="pageTitle" type="text" value="Import Demo Data" required>
			</label>
			<label class="ags-layouts-standalone-only">
				<?php esc_html_e('Site export retrieval error message:', 'wp-layouts-td');?>
				<input name="layoutDownloadError" type="text" value="Something went wrong while retrieving the demo data. Your security token may have expired; please try refreshing the page and try again. If the problem persists, please contact support." required>
			</label>
			<label class="ags-layouts-standalone-only">
				<?php esc_html_e('Introductory content - import preview/selection screen:', 'wp-layouts-td');?>
				<?php
				wp_editor(
					'<p>Select which version of the demo data you would like to import, then click Continue.</p>',
					'ags-layouts-site-export-packager-step1Html',
					array('media_buttons' => false,  'textarea_rows' => 2)
				);
				?>
			</label>
			<label class="ags-layouts-standalone-only">
				<?php esc_html_e('Introductory content - import options screen:', 'wp-layouts-td');?>
				<?php
				wp_editor(
					'<p>Choose which items you would like to import.</p>',
					'ags-layouts-site-export-packager-step2Html',
					array('media_buttons' => false,  'textarea_rows' => 2)
				);
				?>
			</label>
			<label class="ags-layouts-standalone-only">
				<?php esc_html_e('Import button text:', 'wp-layouts-td');?>
				<input name="buttonText" type="text" value="Import Demo Data" required>
			</label>
			<label class="ags-layouts-standalone-only">
				<?php esc_html_e('Import progress heading:', 'wp-layouts-td');?>
				<input name="progressHeading" type="text" value="Importing Demo Data..." required>
			</label>
			<label class="ags-layouts-standalone-only">
				<?php esc_html_e('Import complete heading:', 'wp-layouts-td');?>
				<input name="completeHeading" type="text" value="Import Complete!" required>
			</label>
			<label class="ags-layouts-standalone-only">
				<?php esc_html_e('Import successful message:', 'wp-layouts-td');?>
				<?php
				wp_editor(
					'<p>Enjoy your new child theme!<br>Please check to make sure that the import was successful.</p>',
					'ags-layouts-site-export-packager-successHtml',
					array('media_buttons' => false,  'textarea_rows' => 2)
				);
				?>
			</label>
			<label class="ags-layouts-standalone-only">
				<?php esc_html_e('Import error message:', 'wp-layouts-td');?>
				<?php
				wp_editor(
					'<p>Something went wrong.<br>Unfortunately, demo data import could not be completed due to an error. Please try again.</p>',
					'ags-layouts-site-export-packager-errorHtml',
					array('media_buttons' => false,  'textarea_rows' => 2)
				);
				?>
			</label>
			
			<button id="ags-layouts-site-export-packager-button" class="aspengrove-btn-secondary"><?php esc_html_e('Create Package', 'wp-layouts-td');?></button>
		</form>
		
		<div id="ags-layouts-site-export-packager-instructions-standalone" class="hidden">
			<p>
			<?php esc_html_e('After clicking Create Package, a zip file will be created containing the code and other resources for the data importer.
			To add the importer to your child theme, unzip the package zip file into a directory within the child theme (this
			directory should not contain any other files). Then add the following line of code to the end of your theme\'s functions.php
			file, replacing /path/to/importer/ with the path to the directory containing the package files, relative to the directory
			containing the functions.php file.', 'wp-layouts-td');?>
			</p>
			<code>include_once __DIR__.'/path/to/importer/importer.php';</code>
		</div>
		
		<div id="ags-layouts-site-export-packager-instructions-hook" class="hidden">
			<p>
			<?php esc_html_e('Add the following code to your theme\'s functions.php file:', 'wp-layouts-td');?>
			</p>
			<pre><code></code></pre>
		</div>
		
	</div>
</div>
	
<form method="post">
	<input name="action" value="ags_layouts_package" type="hidden">
	<input id="ags-layouts-site-export-package" name="package" type="hidden">
	<?php wp_nonce_field('ags-layouts-site-export-package', 'ags_layouts_nonce'); ?>
</form>

<script>
jQuery(document).ready(page_ags_layouts_site_export_packager);
</script>