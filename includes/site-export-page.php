<?php
AGSLayouts::VERSION; // Access control

include_once('site-export/caldera.php');

?>
<div id="ags-layouts-settings">
	<div id="ags-layouts-settings-header">
		<h1><?php esc_html_e('WP Layouts Site Exporter', 'wp-layouts-td');?></h1>
		<div id="ags-layouts-settings-header-links">
			<a id="ags-layouts-settings-header-link-settings" href="admin.php?page=ags-layouts-settings"><?php esc_html_e('Settings', 'wp-layouts-td');?></a>
			<a id="ags-layouts-settings-header-link-support" href="https://support.wpzone.co/" target="_blank"><?php esc_html_e('Support', 'wp-layouts-td');?></a>
		</div>
	</div>
	<div id="ags-layouts-site-export">
		<form method="post">
			<label>
				<?php esc_html_e('Export Name', 'wp-layouts-td');?>
				<input id="ags-layouts-site-export-name" type="text" value="<?php echo esc_attr( get_bloginfo() );  ?>" required>
			</label>
			
			<label>
				<?php esc_html_e('Export Image (optional; PNG format, recommended size 900x750)', 'wp-layouts-td');?>
				<input id="ags-layouts-site-export-image" type="file">
			</label>
			
			<ul id="ags-layouts-site-export-items">
				<li>
					<label>
						<input type="checkbox" name="exportItems[]" value="content" checked disabled>
						<?php esc_html_e('Site content (includes pages, posts, categories, menus, etc.)', 'wp-layouts-td');?>
					</label>
					
					
				<ul id="ags-layouts-site-export-content-exclude">
					<li>
						<label class="full-size">
							<?php esc_html_e('Exclude post types (comma separated):', 'wp-layouts-td');?>
							<input type="text" name="excludePostTypes" value="">
						</label>
					</li>
					<li>
						<label class="full-size">
							<?php esc_html_e('Exclude taxonomies (comma separated):', 'wp-layouts-td');?>
							<input type="text" name="excludeTaxonomies" value="module_width,scope,layout_type,action-group">
						</label>
						
					</li>
					<li>
						<label class="full-size">
							<?php esc_html_e('Exclude post meta entries by meta key (comma separated):', 'wp-layouts-td');?>
							<input type="text" name="excludeMeta" value="">
						</label>
						
					</li>
					<li>
						<label class="full-size">
							<?php esc_html_e('Exclude post meta entries by meta key prefix (comma separated):', 'wp-layouts-td');?>
							<input type="text" name="excludeMetaPrefix" value="">
						</label><br>
						<span class="ags-layouts-site-export-instructions"><?php esc_html_e('This setting will exclude all post meta entries starting with the specified prefix(es).', 'wp-layouts-td');?></span>
					</li>
				</ul>
				</li>
				<li>
					<label>
						<input type="checkbox" name="exportItems[]" value="themeOptions" <?php echo( in_array( get_option('template'), array('Divi', 'Extra') ) ? 'checked' : 'disabled' ); ?>>
						<?php esc_html_e('Theme options', 'wp-layouts-td');?>
					</label>
					
					<p class="ags-layouts-site-export-instructions"><?php esc_html_e('Only Divi and Extra are supported for theme options export in this beta version of the site exporter. If you would like us to support a different theme, please let us know!', 'wp-layouts-td');?></p>
				</li>
                <li>
                    <label>
                        <input type="checkbox" name="exportItems[]" value="widgets" checked>
                        <?php esc_html_e('Widgets', 'wp-layouts-td');?>
                    </label>
                </li>
				<?php if ( function_exists('et_get_option') ) { ?>
                <li>
                    <label>
                        <input type="checkbox" name="exportItems[]" value="diviModulePresets" checked>
                        <?php esc_html_e('Divi Builder module presets', 'wp-layouts-td');?>
                    </label>
                </li>
				<?php } ?>
				<li>
					<label>
						<?php esc_html_e('Plugin options', 'wp-layouts-td');?>
					</label>
					
					<ul>
						<li>
							<label>
								<input type="checkbox" name="exportItems[]" value="pluginOptions.TheEventsCalendar" <?php echo( get_option('tribe_events_calendar_options') ? 'checked' : 'disabled' ); ?>>
								<?php esc_html_e('The Events Calendar', 'wp-layouts-td');?>
							</label>
						</li>
					</ul>
					
					<p class="ags-layouts-site-export-instructions"><?php esc_html_e('We are planning to add support for more plugins as we improve this beta version of the site exporter. Please let us know if you need support for a particular plugin!', 'wp-layouts-td');?></p>
				</li>
				<?php if ( AGSLayoutsSiteExportCalderaForms::isSupported() ) { ?>
				<li>
					<label>
						<?php esc_html_e('Caldera Forms', 'wp-layouts-td');?>
					</label>
					
					<ul>
						<?php foreach ( AGSLayoutsSiteExportCalderaForms::getForms() as $formId => $formName) { ?>
						<li>
							<label>
								<input type="checkbox" name="exportItems[]" value="calderaForms.<?php echo esc_attr($formId); ?>" checked>
								<?php echo esc_html($formName); ?>
							</label>
						</li>
						<?php } ?>
					</ul>
					
				</li>
				
				<?php } ?>
			</ul>
			
			<div id="ags-layouts-site-export-status"></div>
			
			<?php wp_nonce_field('ags-layouts-site-export-ajax', 'ags_layouts_nonce'); ?>
		</form>
	</div>
</div>

<script>
jQuery(document).ready(page_ags_layouts_site_export);
</script>