<?php
/**
 * WP Layouts Site Importer - WooCommerce Compatibility
 * This file contains code copied from WooCommerce.
 * See the license.txt file in the WP Layouts plugin root directory for more information and licenses
 *
*/

// woocommerce/includes/admin/class-wc-admin-importers.php
add_filter('ags_layouts_wp_import_data', function($import_data) {

	if ( isset( $import_data['posts'] ) && ! empty( $import_data['posts'] ) ) {
		foreach ( $import_data['posts'] as $post ) {
			if ( 'product' === $post['post_type'] && ! empty( $post['terms'] ) ) {
				foreach ( $post['terms'] as $term ) {
					if ( strstr( $term['domain'], 'pa_' ) ) {
						if ( ! taxonomy_exists( $term['domain'] ) ) {
							$attribute_name = wc_attribute_taxonomy_slug( $term['domain'] );

							// Create the taxonomy.
							if ( ! in_array( $attribute_name, wc_get_attribute_taxonomies(), true ) ) {
								wc_create_attribute(
									array(
										'name'         => $attribute_name,
										'slug'         => $attribute_name,
										'type'         => 'select',
										'order_by'     => 'menu_order',
										'has_archives' => false,
									)
								);
							}

							// Register the taxonomy now so that the import works!
							register_taxonomy(
								$term['domain'],
								apply_filters( 'woocommerce_taxonomy_objects_' . $term['domain'], array( 'product' ) ),
								apply_filters(
									'woocommerce_taxonomy_args_' . $term['domain'],
									array(
										'hierarchical' => true,
										'show_ui'      => false,
										'query_var'    => true,
										'rewrite'      => false,
									)
								)
							);
						}
					}
				}
			}
		}
	}
	
	return $import_data;
});