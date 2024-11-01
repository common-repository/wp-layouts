<?php
/**
 * Contains code copied from and/or based onthe Widget Importer & Exporter plugin.
 * See the license.txt file in the WP Layouts plugin root directory for more information and licenses
 *
*/

// No direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Available widgets
 *
 * Gather site's widgets into array with ID base, name, etc.
 * Used by export and import functions.
 *
 * @since 0.4
 * @global array $wp_registered_widget_updates
 * @return array Widget information
 */
function ags_layouts_wie_available_widgets() {

	global $wp_registered_widget_controls;

	$widget_controls = $wp_registered_widget_controls;

	$available_widgets = array();

	foreach ( $widget_controls as $widget ) {

		// No duplicates.
		if ( ! empty( $widget['id_base'] ) && ! isset( $available_widgets[ $widget['id_base'] ] ) ) {
			$available_widgets[ $widget['id_base'] ]['id_base'] = $widget['id_base'];
			$available_widgets[ $widget['id_base'] ]['name']    = $widget['name'];
		}

	}

	return apply_filters( 'wie_available_widgets', $available_widgets );

}

/**
 * Export Functions
 *
 * @package    Widget_Importer_Exporter
 * @subpackage Functions
 * @copyright  Copyright (c) 2013 - 2017, ChurchThemes.com
 * @link       https://churchthemes.com/plugins/widget-importer-exporter/
 * @license    GPLv2 or later
 * @since      0.1
 */

/**
 * Generate export data
 *
 * @since 0.1
 * @return string Export file contents
 */
function ags_layouts_wie_generate_export_data() {

	// Get all available widgets site supports.
	$available_widgets = ags_layouts_wie_available_widgets();

	// Get all widget instances for each widget.
	$widget_instances = array();

	// Loop widgets.
	foreach ( $available_widgets as $widget_data ) {

		// Get all instances for this ID base.
		$instances = get_option( 'widget_' . $widget_data['id_base'] );

		// Have instances.
		if ( ! empty( $instances ) ) {

			// Loop instances.
			foreach ( $instances as $instance_id => $instance_data ) {

				// Key is ID (not _multiwidget).
				if ( is_numeric( $instance_id ) ) {
					$unique_instance_id = $widget_data['id_base'] . '-' . $instance_id;
					$widget_instances[ $unique_instance_id ] = $instance_data;
				}

			}

		}

	}

	// Gather sidebars with their widget instances.
	$sidebars_widgets = get_option( 'sidebars_widgets' );
	$sidebars_widget_instances = array();
	foreach ( $sidebars_widgets as $sidebar_id => $widget_ids ) {

		// Skip inactive widgets.
		if ( 'wp_inactive_widgets' === $sidebar_id ) {
			continue;
		}

		// Skip if no data or not an array (array_version).
		if ( ! is_array( $widget_ids ) || empty( $widget_ids ) ) {
			continue;
		}

		// Loop widget IDs for this sidebar.
		foreach ( $widget_ids as $widget_id ) {

			// Is there an instance for this widget ID?
			if ( isset( $widget_instances[ $widget_id ] ) ) {

				// Add to array.
				$sidebars_widget_instances[ $sidebar_id ][ $widget_id ] = $widget_instances[ $widget_id ];

			}

		}

	}

	// Filter pre-encoded data.
	$data = apply_filters( 'wie_unencoded_export_data', $sidebars_widget_instances );

	// Encode the data for file contents.
	$encoded_data = wp_json_encode( $data );

	// Return contents.
	return apply_filters( 'wie_generate_export_data', $encoded_data );

}
