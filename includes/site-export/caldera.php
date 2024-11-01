<?php
/*
Caldera Forms integration for WP Layouts site export

This file includes code from the Caldera Forms plugin. Licensed in this project under the GNU General Public License version 3
or later. Copyright Caldera Forms. Original licensing information from the project's composer.json file is shown below:

(plugins/caldera-forms/composer.json)

"name": "desertsnowman/caldera-forms",
"description": "Create complex grid based, responsive forms easily with an easy to use drag and drop layout builder",
"type": "wordpress-plugin",
"keywords": [
	"wordpress",
	"forms",
	"caldera"
],
"license": "GPL-2.0+",
"authors": [
	{
		"name": "Josh Pollock",
		"homepage": "https://JoshPress.net",
		"role": "Lead Developer"
	},
	{
		"name": "David Cramer",
		"homepage": "http://cramer.co.za",
		"role": "Founding Developer"
	},
	{
		"name": "Nicolas Figueira",
		"homepage": "https://newo.me/",
		"role": "Contributing Developer"
	}
]

See the license.txt file in the WP Layouts plugin root directory for more information and licenses

*/

class AGSLayoutsSiteExportCalderaForms {
	
	static function isSupported() {
		return class_exists('Caldera_Forms') && Caldera_Forms::get_manage_cap() && Caldera_Forms::get_manage_cap( 'export' );
	}
	
	static function getForms() {
	
		// caldera-forms/classes/admin.php
		if( current_user_can( Caldera_Forms::get_manage_cap() ) ){
			$forms = Caldera_Forms_Forms::get_forms( true );
			foreach ($forms as &$form) {
				$form = $form['name'];
			}
			return $forms;
		}
	
	}
	
	static function exportForms($formIds) {
		$forms = array();
	
		foreach ($formIds as $formId) {
			// caldera-forms/classes/admin.php
			if ( current_user_can( Caldera_Forms::get_manage_cap( 'export', $formId ) ) ){
				// caldera-forms/classes/admin.php
				
				$form = Caldera_Forms_Forms::get_form( $formId );

				if(empty($form)){
					wp_die( esc_html__('Form does not exist.', 'caldera-forms' ) );
				}
				
				$forms[] = $form;
			}
		}
	
		
		return json_encode($forms);
	}
	
	
	
	
}