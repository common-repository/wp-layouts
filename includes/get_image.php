<?php
/**
 * Contains code copied from and/or based on WordPress by Automattic, released under GPLv2+
 * See the license.txt file in the WP Layouts plugin root directory for more information and licenses
 *
 */


AGSLayouts::VERSION; // Access control

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- this file defines a read-only operation not vulnerable to CSRF attacks

class AGSLayoutsGetImage {
	
	public static function run() {
		if (empty($_GET['layoutId']) || !is_numeric($_GET['layoutId']) || empty($_GET['image'])) {
			return;
		}
		
		$isLayoutImage = $_GET['image'] == 'L';
		
		// The following code is copied from WordPress - see information near the top of this file - modified
		// Source: https://github.com/WordPress/WordPress/blob/master/wp-admin/load-scripts.php
		
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized, ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized -- $_SERVER['SERVER_PROTOCOL'] is checked against a set of fixed values
		$protocol = ( isset($_SERVER['SERVER_PROTOCOL']) && in_array( $_SERVER['SERVER_PROTOCOL'], array( 'HTTP/1.1', 'HTTP/2', 'HTTP/2.0' ) ) ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
		$expires_offset = 31536000; // 1 year
		if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) {
			header( "$protocol 304 Not Modified" );
			exit();
		}
		header( 'Etag: 1' );
		if ($isLayoutImage) {
			header( 'Content-Type: image/jpeg' );
		}
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $expires_offset ) . ' GMT' );
		header( "Cache-Control: public, max-age=$expires_offset" );
		
		// End code copied from WordPress
		
		include_once(__DIR__.'/api.php');
		$image = AGSLayoutsApi::get_layout_image( array(
			'layoutId' => (int) $_GET['layoutId'],
			'imageFile' => sanitize_text_field( wp_unslash($_GET['image']) )
		) );
		
		if ($image) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- this is image data being output
			echo $image;
		} else if ($isLayoutImage) {
			wp_safe_redirect(AGSLayouts::$pluginBaseUrl.'images/no-thumb.svg');
		}
		
	}
}
AGSLayoutsGetImage::run();