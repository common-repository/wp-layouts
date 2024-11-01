<?php
AGSLayouts::VERSION; // Access control
include_once(__DIR__.'/account.php');
if (!empty($_POST['ags_layouts_action'])) {
	check_admin_referer('ags_layouts_settings_action', 'ags_layouts_nonce');

	switch ($_POST['ags_layouts_action']) {
		case 'login':
			if (!empty($_POST['ags_layouts_email']) && !empty($_POST['ags_layouts_password'])) {
			
				$loginResult = AGSLayoutsAccount::login(
								sanitize_text_field( wp_unslash( $_POST['ags_layouts_email'] ) ),
								sanitize_text_field( wp_unslash( $_POST['ags_layouts_password'] ) )
				);
				if ($loginResult) {
					$message = esc_html__('You have been logged in successfully!', 'wp-layouts-td');
				} else {
					switch ( AGSLayoutsAccount::getLastLoginError() ) {
						case 'auth':
							$message = sprintf( esc_html__('Your email and/or password is incorrect; %sclick here%s to reset your password. If you are receiving this message in error, please %scontact support%s.', 'wp-layouts-td' ),
                                '<a href="https://wplayouts.space/reset-password/" target="_blank">', '</a>', '<a href="https://support.wpzone.co/" target="_blank">', '</a>');
							break;
						case 'temp_locked':
							$message = sprintf( esc_html__('Your login has been temporarily locked due to too many recent failed login attempts. Please try again in 5 minutes or %sclick here%s to reset your password.', 'wp-layouts-td' ),
                                '<a href="https://wplayouts.space/reset-password/" target="_blank">', '</a>');
							break;
						case 'license_key_add_site':
							$message = sprintf( esc_html__('This site could not be activated in your account. You may have reached your site activation limit, in which case you would need to log out in WP Layouts on one of your other sites before logging in here. If you think this message is in error, please %scontact support%s.', 'wp-layouts-td' ),
                                '<a href="https://support.wpzone.co/" target="_blank">', '</a>');
							break;
						case 'no_license_key':
							$message = sprintf( esc_html__('We couldn\'t find an active WP Layouts plan in your account. Please ensure you are using the latest version of the WP Layouts plugin, and %scontact support%s if you are still seeing this message.', 'wp-layouts-td' ),
                                '<a href="https://support.wpzone.co/" target="_blank">', '</a>');
							break;
						default:
							$message = sprintf( esc_html__('Login failed; please try again. If you continue to receive this message, please %scontact support%s.', 'wp-layouts-td' ),
                                '<a href="https://support.wpzone.co/" target="_blank">', '</a>');
					}
				}

				
				$messageType = $loginResult ? 'success' : 'error';
			}
			break;
		case 'logout':
			$logoutResult = AGSLayoutsAccount::logout();
			$message = $logoutResult
						? esc_html__('You have been logged out.', 'wp-layouts-td')
						: esc_html__('An error occurred while logging out. Please try navigating to Layouts > Layouts to confirm whether you are still logged in on this site. You may also need to manually deactivate this site by logging in to your account on our website.', 'wp-layouts-td');
			$messageType = $logoutResult ? 'success' : 'error';
			break;
	}
}

$isLoggedIn = AGSLayoutsAccount::isLoggedIn();
if (empty($message)) {
	$message = $isLoggedIn
				? esc_html__('You are currently logged in with the email address shown below.', 'wp-layouts-td')
				: 'You are currently not logged in. <a href="https://wplayouts.space/checkout?edd_action=add_to_cart&download_id=1314" target="_blank">Click here to sign up for a free account!</a>'
					.( AGSLayouts::getThemeDemoData() ? ' You do not need an account to import your theme\'s demo data; <a href="'.esc_url( admin_url( 'admin.php?page=ags-layouts-demo-import' ) ).'">click here</a> to go to the Import Demo Data page.' : '');
	
	$messageType = 'info';
}

?>

<?php if (!get_option('ags_layouts_hide_aiil_notice')) { $aiilUrl = admin_url('plugin-install.php?s=%22AI%20Image%20Lab%22&tab=search&type=term'); ?>
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

<br><p class="ags-layouts-notification ags-layouts-notification-info"><strong>Thank you for being part of the WP Layouts Beta!</strong> We appreciate your patience if you encounter any problems with this product. Please visit our <a href="https://wplayouts.space/documentation/" target="_blank">support site</a> for tutorials, FAQs, and to contact us.</p>

<div id="ags-layouts-settings">
	<div id="ags-layouts-settings-header">
        <h1><?php esc_html_e('WP Layouts Beta', 'wp-layouts-td');?></h1>
		<div id="ags-layouts-settings-header-links">
            <a id="ags-layouts-settings-header-link-settings" href=""><?php esc_html_e('Settings', 'wp-layouts-td');?></a>
            <a id="ags-layouts-settings-header-link-support" href="https://support.wpzone.co/" target="_blank"><?php esc_html_e('Support', 'wp-layouts-td');?></a>
        </div>
	</div>
	<ul id="ags-layouts-settings-tabs">
        <li><a href="#account"><?php esc_html_e('Account', 'wp-layouts-td');?></a></li>
	</ul>
	<div id="ags-layouts-settings-account">
		<p class="ags-layouts-notification ags-layouts-notification-<?php echo($messageType); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed value (see above) ?>">
			<?php echo($message); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed value (see above) ?>
		</p>
		<form method="post">
			<label>
                <span><?php esc_html_e('Email:', 'wp-layouts-td');?></span>
				<input type="email" name="ags_layouts_email" value="<?php echo(esc_attr(AGSLayoutsAccount::getAccountEmail())); ?>">
			</label><br>
			<?php if ($isLoggedIn) { ?>
                <button name="ags_layouts_action" class="aspengrove-btn-secondary" value="logout"><?php esc_html_e('Logout', 'wp-layouts-td');?></button>
				<?php if ( get_option('ags_layouts_auth') ) { ?>
                    <div id="ags-layouts-spacer"></div>
					<p class="ags-layouts-notification ags-layouts-notification-warning">
						<?php esc_html_e('You are currently logged in to WP Layouts using a site-wide login, which is deprecated in this version of WP Layouts. WP Layouts now allows each WordPress user to log in to the WP Layouts account of their choice, rather than having all users on the site logged in to the same account. Clicking Logout above will log out of the site-wide login session, and subsequent logins will be specific to the individual WordPress user accounts on this site.', 'wp-layouts-td');?>
					</p>
				<?php } ?>
			<?php } else { ?>
			<label>
                <span><?php esc_html_e('Password:', 'wp-layouts-td');?></span>
				<input type="password" name="ags_layouts_password">
			</label><br>
			<div id="ags-layouts-login-buttons">
                <a id="ags-layouts-password-reset-link" href="https://wplayouts.space/reset-password/" target="_blank"><?php esc_html_e('Reset your password', 'wp-layouts-td');?></a>
                <button name="ags_layouts_action" class="aspengrove-btn-primary" value="login"><?php esc_html_e('Login', 'wp-layouts-td');?></button>
            </div>
			<?php } ?>
			
			<?php wp_nonce_field('ags_layouts_settings_action', 'ags_layouts_nonce'); ?>
		</form>
	</div>
</div>