<?php
/**
 * This file is based on the WordPress Importer plugin and contains other third-party code.
 * See the license.txt file in the WP Layouts plugin root directory for more information and licenses.
 */

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( ! class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require $class_wp_importer;
}

// include WXR file parser
require dirname( __FILE__ ) . '/parsers/class-wxr-parser-regex.php';

/**
 * WordPress Importer class for managing the import process of a WXR file
 *
 * @package WordPress
 * @subpackage Importer
 */
if ( class_exists( 'WP_Importer' ) ) {
class AGSLayouts_WP_Import extends WP_Importer {
	var $max_wxr_version = 1.2; // max. supported WXR version
	var $fetch_attachments = false;
	var $id; // WXR attachment ID

	// information to import from WXR file
	var $version;
	var $authors = array();
	var $posts = array();
	var $terms = array();
	var $categories = array();
	var $tags = array();
	var $base_url = '';
	var $postsAttachment = array();
	var $postsThemeBuilder = array();
	var $postsNonAttachment = array();

	// mappings from old information to new
	var $processed_authors = array();
	var $processed_terms = array();
	var $processed_posts = array();
	var $processed_posts_by_type = array();
	var $post_orphans = array();
	var $processed_menu_items = array();
	var $menu_item_orphans = array();
	var $missing_menu_items = array();
	var $url_remap = array();
	var $featured_images = array();
	
	private $lastImportPostId;
	private static $state_fields = array('processed_authors','processed_terms','processed_posts','processed_posts_by_type','post_orphans','processed_menu_items',
											'menu_item_orphans','missing_menu_items','url_remap','featured_images');

	/**
	 * The main controller for the actual import stage.
	 *
	 * @param string $file Path to the WXR file for importing
	 */
	function import( $file, $pid, $task, $taskState ) {
		$aspenImporter = AGS_Theme_Importer::$instance;
	
		add_filter( 'import_post_meta_key', array( $this, 'is_valid_meta_key' ) );
		
		$this->load_state( $pid );

		$this->import_start( $file );
		
		unlink($file);

		wp_suspend_cache_invalidation( true );
		
		switch ($task) {
			case 'content_categories':
				$this->process_categories();
				$this->save_state( $pid );
				$aspenImporter->progress('content_categories', 'done');
				break;
				
			case 'content_tags':
				$this->process_tags();
				$this->save_state( $pid );
				$aspenImporter->progress('content_tags', 'done');
				break;
				
			case 'content_terms':
				$this->process_terms();
				$this->save_state( $pid );
				$aspenImporter->progress('content_terms', 'done');
				break;
				
			case 'content_posts':
				$progress = $this->process_posts($pid, $taskState, $this->postsNonAttachment, 15);
				$this->save_state( $pid );
				$aspenImporter->progress('content_posts', $progress[0], $progress[1]);
				break;
		
			case 'content_attachments':
				$progress = $this->process_posts($pid, $taskState, $this->postsAttachment, 1);
				$this->save_state( $pid );
				$aspenImporter->progress('content_attachments', $progress[0], $progress[1]);
				break;
				
			case 'content_themebuilder':
				$progress = $this->process_posts($pid, $taskState, $this->postsThemeBuilder, 15);
				$this->save_state( $pid );
				$aspenImporter->progress('content_themebuilder', $progress[0], $progress[1]);
				break;
				
			case 'content_end':
				// wp_suspend_cache_invalidation( false );

				// update incorrect/missing information in the DB
				$this->backfill_parents();
				$this->backfill_attachment_urls();
				$this->remap_featured_images();
				
				$this->fixThemeBuilderContent();

				$this->import_end();
				$this->clear_state();
				$aspenImporter->progress('content_end', 'done');
				break;
		}
		
	}

	function load_state( $pid )
	{
		
		$state = get_user_meta(get_current_user_id(), 'ags-theme-demo-import-state', true);
		if (empty($state)) {
			return;
		}
		
		$state = json_decode( base64_decode($state), true);
		if (empty($state)) {
			throw new Exception( __('Error loading state: '.json_last_error_msg(), 'wp-layouts-td') );
		}
		
		if (empty($state['pid'])) {
			throw new Exception( __('Missing state PID', 'wp-layouts-td') );
		}
		
		if ($state['pid'] != $pid) {
			throw new Exception( __('State PID mismatch', 'wp-layouts-td') );
		}
		
		if (empty($state['WP_Import'])) {
			throw new Exception( __('Missing state information', 'wp-layouts-td') );
		}
		
		foreach (self::$state_fields as $field) {
			
			if (!isset($state['WP_Import'][$field])) {
				throw new Exception( __('Missing state field', 'wp-layouts-td') ); // JMH
			}
			
			$this->$field = $state['WP_Import'][$field];
		}
		
		
	}
	
	function save_state( $pid )
	{
		$state = array(
			'pid' => $pid,
			'WP_Import' => array()
		);
		
		foreach (self::$state_fields as $field) {
			$state['WP_Import'][$field] = $this->$field;
		}
		
		update_user_meta(get_current_user_id(), 'ags-theme-demo-import-state', base64_encode(wp_json_encode($state)) );
	}
	
	function clear_state()
	{
		delete_user_meta(get_current_user_id(), 'ags-theme-demo-import-state');
	}
	
	/**
	 * Parses the WXR file and prepares us for the task of processing parsed data
	 *
	 * @param string $file Path to the WXR file for importing
	 */
	function import_start( $file ) {
		if ( ! is_file($file) ) {
			throw new Exception( __('Content import error: The file does not exist', 'wp-layouts-td') );
		}

		$import_data = $this->parse( $file );

		$import_data = apply_filters( 'ags_layouts_wp_import_data', $import_data ); // custom filter for WP Layouts

		if ( is_wp_error( $import_data ) ) {
			throw new Exception( sprintf( __('Content import error: %s', 'wp-layouts-td'), $import_data->get_error_message() ) );
		}

		$this->version = $import_data['version'];
		$this->get_authors_from_import( $import_data );
		$this->posts = $import_data['posts'];
		$this->terms = $import_data['terms'];
		$this->categories = $import_data['categories'];
		$this->tags = $import_data['tags'];
		$this->base_url = esc_url( $import_data['base_url'] );
		
		// Divi/includes/builder/frontend-builder/theme-builder/theme-builder.php
		$themeBuilderPostTypes = array(
			defined( 'ET_THEME_BUILDER_THEME_BUILDER_POST_TYPE' ) ? ET_THEME_BUILDER_THEME_BUILDER_POST_TYPE : 'et_theme_builder',
			defined( 'ET_THEME_BUILDER_TEMPLATE_POST_TYPE' ) ? ET_THEME_BUILDER_TEMPLATE_POST_TYPE : 'et_template',
			defined( 'ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE' ) ? ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE : 'et_header_layout',
			defined( 'ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE' ) ? ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE : 'et_body_layout',
			defined( 'ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE' ) ? ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE : 'et_footer_layout'
		);
		
		
		foreach ( $this->posts as $post ) {
			if ( 'attachment' == $post['post_type'] ) {
				$this->postsAttachment[] = $post;
			} else if ( in_array($post['post_type'], $themeBuilderPostTypes) ) {
				$this->postsThemeBuilder[] = $post;
			} else {
				$this->postsNonAttachment[] = $post;
			}
		}
		
		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );

		do_action( 'import_start' );
	}

	/**
	 * Performs post-import cleanup of files and the cache
	 */
	function import_end() {
		wp_import_cleanup( $this->id );

		wp_cache_flush();
		foreach ( get_taxonomies() as $tax ) {
			delete_option( "{$tax}_children" );
			_get_term_hierarchy( $tax );
		}

		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );

		do_action( 'import_end' );
	}

	/**
	 * Retrieve authors from parsed WXR data
	 *
	 * Uses the provided author information from WXR 1.1 files
	 * or extracts info from each post for WXR 1.0 files
	 *
	 * @param array $import_data Data returned by a WXR parser
	 */
	function get_authors_from_import( $import_data ) {
		if ( ! empty( $import_data['authors'] ) ) {
			$this->authors = $import_data['authors'];
		// no author information, grab it from the posts
		} else {
			foreach ( $import_data['posts'] as $post ) {
				$login = sanitize_user( $post['post_author'], true );
				if ( empty( $login ) ) {
					AGS_Theme_Importer::$instance->message( sprintf( __( 'Failed to import author %s. Their posts will be attributed to the current user.', 'wp-layouts-td' ), esc_html($post['post_author']) ) );
					continue;
				}

				if ( ! isset($this->authors[$login]) )
					$this->authors[$login] = array(
						'author_login' => $login,
						'author_display_name' => $post['post_author']
					);
			}
		}
	}

	/**
	 * Create new categories based on import information
	 *
	 * Doesn't create a new category if its slug already exists
	 */
	function process_categories() {
		$this->categories = apply_filters( 'wp_import_categories', $this->categories );

		if ( empty( $this->categories ) )
			return;
	
		$aspenImporter = AGS_Theme_Importer::$instance;
		$i = -1;
		$total = count($this->categories);
		foreach ( $this->categories as $cat ) {
			// $aspenImporter->progress('content_categories', 'done', ++$i / $total);
			
			// if the category already exists leave it alone
			$term_id = term_exists( $cat['category_nicename'], 'category' );
			if ( $term_id ) {
				if ( is_array($term_id) ) $term_id = $term_id['term_id'];
				if ( isset($cat['term_id']) )
					$this->processed_terms[intval($cat['term_id'])] = (int) $term_id;
				continue;
			}

			$category_parent = empty( $cat['category_parent'] ) ? 0 : category_exists( $cat['category_parent'] );
			$category_description = isset( $cat['category_description'] ) ? $cat['category_description'] : '';
			$catarr = array(
				'category_nicename' => $cat['category_nicename'],
				'category_parent' => $category_parent,
				'cat_name' => $cat['cat_name'],
				'category_description' => $category_description
			);
			$catarr = wp_slash( $catarr );

			$id = wp_insert_category( $catarr );
			if ( ! is_wp_error( $id ) ) {
				if ( isset($cat['term_id']) )
					$this->processed_terms[intval($cat['term_id'])] = $id;
			} else {
				AGS_Theme_Importer::$instance->message( sprintf( __( 'Failed to import category %s', 'wp-layouts-td' ), esc_html($cat['category_nicename']) ) );
				if ( defined('IMPORT_DEBUG') && IMPORT_DEBUG )
					AGS_Theme_Importer::$instance->message( sprintf( __( 'Category import error: %s', 'wp-layouts-td' ), $id->get_error_message() ) );
				continue;
			}

			$this->process_termmeta( $cat, $id );
		}

		unset( $this->categories );
	}

	/**
	 * Create new post tags based on import information
	 *
	 * Doesn't create a tag if its slug already exists
	 */
	function process_tags() {
		$this->tags = apply_filters( 'wp_import_tags', $this->tags );

		if ( empty( $this->tags ) )
			return;
		
		$aspenImporter = AGS_Theme_Importer::$instance;
		$i = -1;
		$total = count($this->tags);
		foreach ( $this->tags as $tag ) {
			// $aspenImporter->progress('content_tags', 'done', ++$i / $total);
			
			// if the tag already exists leave it alone
			$term_id = term_exists( $tag['tag_slug'], 'post_tag' );
			if ( $term_id ) {
				if ( is_array($term_id) ) $term_id = $term_id['term_id'];
				if ( isset($tag['term_id']) )
					$this->processed_terms[intval($tag['term_id'])] = (int) $term_id;
				continue;
			}

			$tag = wp_slash( $tag );
			$tag_desc = isset( $tag['tag_description'] ) ? $tag['tag_description'] : '';
			$tagarr = array( 'slug' => $tag['tag_slug'], 'description' => $tag_desc );

			$id = wp_insert_term( $tag['tag_name'], 'post_tag', $tagarr );
			if ( ! is_wp_error( $id ) ) {
				if ( isset($tag['term_id']) )
					$this->processed_terms[intval($tag['term_id'])] = $id['term_id'];
			} else {
				AGS_Theme_Importer::$instance->message( sprintf( __( 'Failed to import post tag %s', 'wp-layouts-td' ), esc_html($tag['tag_name']) ) );
				if ( defined('IMPORT_DEBUG') && IMPORT_DEBUG )
					AGS_Theme_Importer::$instance->message( sprintf( __( 'Post tag error: %s', 'wp-layouts-td' ), $id->get_error_message() ) );
				echo '<br />';
				continue;
			}

			$this->process_termmeta( $tag, $id['term_id'] );
		}

		unset( $this->tags );
	}

	/**
	 * Create new terms based on import information
	 *
	 * Doesn't create a term its slug already exists
	 */
	function process_terms() {
		$this->terms = apply_filters( 'wp_import_terms', $this->terms );

		if ( empty( $this->terms ) )
			return;
			
		$aspenImporter = AGS_Theme_Importer::$instance;
		$i = -1;
		$total = count($this->terms);
		
		foreach ( $this->terms as $term ) {
			// $aspenImporter->progress('content_terms', 'done', ++$i / $total);
			
			// if the term already exists in the correct taxonomy leave it alone
			$term_id = term_exists( $term['slug'], $term['term_taxonomy'] );
			if ( $term_id ) {
				if ( is_array($term_id) ) $term_id = $term_id['term_id'];
				if ( isset($term['term_id']) )
					$this->processed_terms[intval($term['term_id'])] = (int) $term_id;
				continue;
			}

			if ( empty( $term['term_parent'] ) ) {
				$parent = 0;
			} else {
				$parent = term_exists( $term['term_parent'], $term['term_taxonomy'] );
				if ( is_array( $parent ) ) $parent = $parent['term_id'];
			}
			$term = wp_slash( $term );
			$description = isset( $term['term_description'] ) ? $term['term_description'] : '';
			$termarr = array( 'slug' => $term['slug'], 'description' => $description, 'parent' => intval($parent) );

			$id = wp_insert_term( $term['term_name'], $term['term_taxonomy'], $termarr );
			if ( ! is_wp_error( $id ) ) {
				if ( isset($term['term_id']) )
					$this->processed_terms[intval($term['term_id'])] = $id['term_id'];
			} else {
				AGS_Theme_Importer::$instance->message( sprintf( __( 'Failed to import %s %s', 'wp-layouts-td' ), esc_html($term['term_taxonomy']), esc_html($term['term_name']) ) );
				if ( defined('IMPORT_DEBUG') && IMPORT_DEBUG )
					AGS_Theme_Importer::$instance->message( sprintf( __( 'Term import error: %s', 'wp-layouts-td' ), $id->get_error_message() ) );
				continue;
			}

			$this->process_termmeta( $term, $id['term_id'] );
		}

		unset( $this->terms );
	}

	/**
	 * Add metadata to imported term.
	 *
	 * @since 0.6.2
	 *
	 * @param array $term    Term data from WXR import.
	 * @param int   $term_id ID of the newly created term.
	 */
	protected function process_termmeta( $term, $term_id ) {
		if ( ! isset( $term['termmeta'] ) ) {
			$term['termmeta'] = array();
		}

		/**
		 * Filters the metadata attached to an imported term.
		 *
		 * @since 0.6.2
		 *
		 * @param array $termmeta Array of term meta.
		 * @param int   $term_id  ID of the newly created term.
		 * @param array $term     Term data from the WXR import.
		 */
		$term['termmeta'] = apply_filters( 'wp_import_term_meta', $term['termmeta'], $term_id, $term );

		if ( empty( $term['termmeta'] ) ) {
			return;
		}

		foreach ( $term['termmeta'] as $meta ) {
			/**
			 * Filters the meta key for an imported piece of term meta.
			 *
			 * @since 0.6.2
			 *
			 * @param string $meta_key Meta key.
			 * @param int    $term_id  ID of the newly created term.
			 * @param array  $term     Term data from the WXR import.
			 */
			$key = apply_filters( 'import_term_meta_key', $meta['key'], $term_id, $term );
			if ( ! $key ) {
				continue;
			}

			// Export gets meta straight from the DB so could have a serialized string
			$value = maybe_unserialize( $meta['value'] );

			add_term_meta( $term_id, $key, $value );

			/**
			 * Fires after term meta is imported.
			 *
			 * @since 0.6.2
			 *
			 * @param int    $term_id ID of the newly created term.
			 * @param string $key     Meta key.
			 * @param mixed  $value   Meta value.
			 */
			do_action( 'import_term_meta', $term_id, $key, $value );
		}
	}

	/**
	 * Create new posts based on import information
	 *
	 * Posts marked as having a parent which doesn't exist will become top level items.
	 * Doesn't create a new post if: the post type doesn't exist, the given post ID
	 * is already noted as imported or a post with the same title and date already exists.
	 * Note that new/updated terms, comments and meta are imported for the last of the above.
	 */
	function process_posts($pid, $state, $posts, $postsLimit) {
		
		$aspenImporter = AGS_Theme_Importer::$instance;
		$total = count($posts);
		$postsDone = 0;
		$state = (int)$state;
		
		foreach ( array_slice($posts, $state, $postsLimit) as $post ) {
			
			$post = apply_filters( 'wp_import_post_data_raw', $post );


			if ( ! post_type_exists( $post['post_type'] ) ) {
				AGS_Theme_Importer::$instance->message( sprintf( __( 'Failed to import &quot;%s&quot; (%d): Invalid post type %s', 'wp-layouts-td' ), esc_html($post['post_title']), $post['post_id'], esc_html($post['post_type']) ) );
				do_action( 'wp_import_post_exists', $post );
				continue;
			}

			if ( isset( $this->processed_posts[$post['post_id']] ) && ! empty( $post['post_id'] ) )
				continue;

			if ( $post['status'] == 'auto-draft' )
				continue;

			if ( 'nav_menu_item' == $post['post_type'] ) {
				$this->process_menu_item( $post );
				continue;
			}

			$post_type_object = get_post_type_object( $post['post_type'] );

			$post_exists = $this->allowDuplicateThemeBuilderPost($post['post_type']) ? 0 : post_exists( $post['post_title'], '', $post['post_date'] );
			
			// Additional exists check for attachments: check if attached file name matches
			
			if ($post_exists) {
				if ( $post['post_type'] == 'attachment' && isset($post['postmeta']) ) {
					$importAttachedFile = null;
					foreach( $post['postmeta'] as $meta ) {
						if ( $meta['key'] == '_wp_attached_file' ) {
							$importAttachedFile = $meta['value'];
							break;
						}
					}
					
					$existsAttachedFile = get_post_meta($post_exists, '_wp_attached_file', true);
					
					if (!$importAttachedFile || !$existsAttachedFile || basename($importAttachedFile) != basename($existsAttachedFile)) {
						$post_exists = false;
					}
					
					
				// wp-includes/feed.php
				} else if ( $post['link'] != esc_url( apply_filters( 'the_permalink_rss', get_permalink($post_exists) ) ) ) {
					$post_exists = false;
				}
			}

			/**
			* Filter ID of the existing post corresponding to post currently importing.
			*
			* Return 0 to force the post to be imported. Filter the ID to be something else
			* to override which existing post is mapped to the imported post.
			*
			* @see post_exists()
			* @since 0.6.2
			*
			* @param int   $post_exists  Post ID, or 0 if post did not exist.
			* @param array $post         The post array to be inserted.
			*/
			$post_exists = apply_filters( 'wp_import_existing_post', $post_exists, $post );

			if ( $post_exists && get_post_type( $post_exists ) == $post['post_type'] ) {
				AGS_Theme_Importer::$instance->message( sprintf( __('%s %s already exists.', 'wp-layouts-td'), $post_type_object->labels->singular_name, $post['post_title'] ? '&quot;'.esc_html($post['post_title']).'&quot;' : '#'.((int) $post['post_id']) ) );
				$comment_post_ID = $post_id = $post_exists;
				$this->processed_posts[ intval( $post['post_id'] ) ] = intval( $post_exists );
				
				if ( 'attachment' == $post['post_type'] ) {
					// We still need to process attachment posts to ensure that the URLs get updated in imported post content
					$remote_url = ! empty($post['attachment_url']) ? $post['attachment_url'] : $post['guid'];
					$existing_url = wp_get_attachment_url($post_exists);
					
					if ( $remote_url && $existing_url ) {
						$this->url_remap[ $remote_url ] = $existing_url;
					}
					
				}
				
			} else {
				$post_parent = (int) $post['post_parent'];
				if ( $post_parent ) {
					// if we already know the parent, map it to the new local ID
					if ( isset( $this->processed_posts[$post_parent] ) ) {
						$post_parent = $this->processed_posts[$post_parent];
					// otherwise record the parent for later
					} else {
						$this->post_orphans[intval($post['post_id'])] = $post_parent;
						$post_parent = 0;
					}
				}

				$author = (int) get_current_user_id();

				$postdata = array(
					'import_id' => $post['post_id'], 'post_author' => $author, 'post_date' => $post['post_date'],
					'post_date_gmt' => $post['post_date_gmt'], 'post_content' => $post['post_content'],
					'post_excerpt' => $post['post_excerpt'], 'post_title' => $post['post_title'],
					'post_status' => $post['status'], 'post_name' => $post['post_name'],
					'comment_status' => $post['comment_status'], 'ping_status' => $post['ping_status'],
					'guid' => $post['guid'], 'post_parent' => $post_parent, 'menu_order' => $post['menu_order'],
					'post_type' => $post['post_type'], 'post_password' => $post['post_password']
				);

				$original_post_ID = $post['post_id'];
				$postdata = apply_filters( 'wp_import_post_data_processed', $postdata, $post );

				$postdata = wp_slash( $postdata );

				$this->lastImportPostId = null;
				
				if ( 'attachment' == $postdata['post_type'] ) {
					$remote_url = ! empty($post['attachment_url']) ? $post['attachment_url'] : $post['guid'];

					// try to use _wp_attached file for upload folder placement to ensure the same location as the export site
					// e.g. location is 2003/05/image.jpg but the attachment post_date is 2010/09, see media_handle_upload()
					$postdata['upload_date'] = $post['post_date'];
					if ( isset( $post['postmeta'] ) ) {
						foreach( $post['postmeta'] as $meta ) {
							if ( $meta['key'] == '_wp_attached_file' ) {
								if ( preg_match( '%^[0-9]{4}/[0-9]{2}%', $meta['value'], $matches ) )
									$postdata['upload_date'] = $matches[0];
								break;
							}
						}
					}

					$comment_post_ID = $post_id = $this->process_attachment( $postdata, $remote_url );
					
				} else {
					// This captures the post ID in case the post was inserted but an error occurred during hooks processing
					add_action('save_post_'.$postdata['post_type'], [$this, 'captureImportPostId'], 1);
					
					try {
					$comment_post_ID = $post_id = wp_insert_post( $postdata, true );
					} catch (Error $err) {
						$post_id = new WP_Error('php_error', $err->getMessage());
					}
					
					remove_action('save_post_'.$postdata['post_type'], [$this, 'captureImportPostId'], 1);
					do_action( 'wp_import_insert_post', $post_id, $original_post_ID, $postdata, $post );
				}
				
				++$postsDone;

				if ( is_wp_error( $post_id ) ) {
					AGS_Theme_Importer::$instance->message( sprintf( $this->lastImportPostId ? __( '%s &quot;%s&quot; was imported, but error(s) occurred', 'wp-layouts-td' ) : __( 'Failed to import %s &quot;%s&quot;', 'wp-layouts-td' ), $post_type_object->labels->singular_name, esc_html($post['post_title']) ) );
					if ( defined('IMPORT_DEBUG') && IMPORT_DEBUG )
						AGS_Theme_Importer::$instance->message( sprintf( __( 'Import error: %s', 'wp-layouts-td' ), esc_html($post_id->get_error_message()) ) );
					
					if ($this->lastImportPostId) {
						$post_id = $this->lastImportPostId;
					} else {
						continue;
					}
				}

				if ( $post['is_sticky'] == 1 )
					stick_post( $post_id );
			}

			// map pre-import ID to local ID
			$this->processed_posts[intval($post['post_id'])] = (int) $post_id;
			
			if ( empty($this->processed_posts_by_type[ $post['post_type'] ] ) ) {
				$this->processed_posts_by_type[ $post['post_type'] ] = array( (int) $post_id );
			} else {
				$this->processed_posts_by_type[ $post['post_type'] ][] = (int) $post_id;
			}

			if ( ! isset( $post['terms'] ) )
				$post['terms'] = array();

			$post['terms'] = apply_filters( 'wp_import_post_terms', $post['terms'], $post_id, $post );

			// add categories, tags and other terms
			if ( ! empty( $post['terms'] ) ) {
				$terms_to_set = array();
				foreach ( $post['terms'] as $term ) {
					// back compat with WXR 1.0 map 'tag' to 'post_tag'
					$taxonomy = ( 'tag' == $term['domain'] ) ? 'post_tag' : $term['domain'];
					$term_exists = term_exists( $term['slug'], $taxonomy );
					$term_id = is_array( $term_exists ) ? $term_exists['term_id'] : $term_exists;
					if ( ! $term_id ) {
						$t = wp_insert_term( $term['name'], $taxonomy, array( 'slug' => $term['slug'] ) );
						if ( ! is_wp_error( $t ) ) {
							$term_id = $t['term_id'];
							do_action( 'wp_import_insert_term', $t, $term, $post_id, $post );
						} else {
							AGS_Theme_Importer::$instance->message( sprintf( __( 'Failed to import %s %s', 'wp-layouts-td' ), esc_html($taxonomy), esc_html($term['name']) ) );
							if ( defined('IMPORT_DEBUG') && IMPORT_DEBUG )
								AGS_Theme_Importer::$instance->message( sprintf( __( 'Post term import error: %s %s', 'wp-layouts-td' ), $t->get_error_message() ) );
							do_action( 'wp_import_insert_term_failed', $t, $term, $post_id, $post );
							continue;
						}
					}
					$terms_to_set[$taxonomy][] = intval( $term_id );
				}

				foreach ( $terms_to_set as $tax => $ids ) {
					$tt_ids = wp_set_post_terms( $post_id, $ids, $tax );
					do_action( 'wp_import_set_post_terms', $tt_ids, $ids, $tax, $post_id, $post );
				}
				unset( $post['terms'], $terms_to_set );
			}

			if ( ! isset( $post['comments'] ) )
				$post['comments'] = array();

			$post['comments'] = apply_filters( 'wp_import_post_comments', $post['comments'], $post_id, $post );

			// add/update comments
			if ( ! empty( $post['comments'] ) ) {
				$num_comments = 0;
				$inserted_comments = array();
				foreach ( $post['comments'] as $comment ) {
					$comment_id	= $comment['comment_id'];
					$newcomments[$comment_id]['comment_post_ID']      = $comment_post_ID;
					$newcomments[$comment_id]['comment_author']       = $comment['comment_author'];
					$newcomments[$comment_id]['comment_author_email'] = $comment['comment_author_email'];
					$newcomments[$comment_id]['comment_author_IP']    = $comment['comment_author_IP'];
					$newcomments[$comment_id]['comment_author_url']   = $comment['comment_author_url'];
					$newcomments[$comment_id]['comment_date']         = $comment['comment_date'];
					$newcomments[$comment_id]['comment_date_gmt']     = $comment['comment_date_gmt'];
					$newcomments[$comment_id]['comment_content']      = $comment['comment_content'];
					$newcomments[$comment_id]['comment_approved']     = $comment['comment_approved'];
					$newcomments[$comment_id]['comment_type']         = $comment['comment_type'];
					$newcomments[$comment_id]['comment_parent'] 	  = $comment['comment_parent'];
					$newcomments[$comment_id]['commentmeta']          = isset( $comment['commentmeta'] ) ? $comment['commentmeta'] : array();
					if ( isset( $this->processed_authors[$comment['comment_user_id']] ) )
						$newcomments[$comment_id]['user_id'] = $this->processed_authors[$comment['comment_user_id']];
				}
				ksort( $newcomments );

				foreach ( $newcomments as $key => $comment ) {
					// if this is a new post we can skip the comment_exists() check
					if ( ! $post_exists || ! comment_exists( $comment['comment_author'], $comment['comment_date'] ) ) {
						if ( isset( $inserted_comments[$comment['comment_parent']] ) )
							$comment['comment_parent'] = $inserted_comments[$comment['comment_parent']];
						$comment = wp_filter_comment( $comment );
						$inserted_comments[$key] = wp_insert_comment( $comment );
						do_action( 'wp_import_insert_comment', $inserted_comments[$key], $comment, $comment_post_ID, $post );

						foreach( $comment['commentmeta'] as $meta ) {
							$value = maybe_unserialize( $meta['value'] );
							add_comment_meta( $inserted_comments[$key], $meta['key'], $value );
						}

						$num_comments++;
					}
				}
				unset( $newcomments, $inserted_comments, $post['comments'] );
			}

			if ( ! isset( $post['postmeta'] ) )
				$post['postmeta'] = array();

			$post['postmeta'] = apply_filters( 'wp_import_post_meta', $post['postmeta'], $post_id, $post );

			// add/update post meta
			if ( ! empty( $post['postmeta'] ) ) {
				foreach ( $post['postmeta'] as $meta ) {
					$key = apply_filters( 'import_post_meta_key', $meta['key'], $post_id, $post );
					$value = false;

					if ( '_edit_last' == $key ) {
						if ( isset( $this->processed_authors[intval($meta['value'])] ) )
							$value = $this->processed_authors[intval($meta['value'])];
						else
							$key = false;
					}

					if ( $key ) {
						// export gets meta straight from the DB so could have a serialized string
						if ( ! $value )
							$value = maybe_unserialize( $meta['value'] );

						add_post_meta( $post_id, $key, $value );
						do_action( 'import_post_meta', $post_id, $key, $value );

						// if the post has a featured image, take note of this in case of remap
						if ( '_thumbnail_id' == $key )
							$this->featured_images[$post_id] = (int) $value;
					}
				}
			}
		}
		
		if ( ($postsLimit + $state) < $total ) {
			$this->save_state( $pid );
			return array($postsLimit + $state, ($postsLimit + $state) / $total);
		}

		return array('done', 1);
	}
	
	function captureImportPostId($postId) {
		$this->lastImportPostId = $postId;
	}
	
	/*
	Divi/includes/builder/frontend-builder/theme-builder/api.php
	Divi/includes/builder/frontend-builder/theme-builder/theme-builder.php
	*/
	
	function fixThemeBuilderContent() {
		
		if (!empty($this->processed_posts_by_type[ 'et_template' ] ) ) {
			$theme_builder_id = et_theme_builder_get_theme_builder_post_id( true, true );
			
			foreach ($this->processed_posts_by_type[ 'et_template' ] as $post_id) {
			
				$templates = get_post_meta( $theme_builder_id, '_et_template', false );
				if ( ! in_array( $post_id, $templates, true ) ) {
					add_post_meta( $theme_builder_id, '_et_template', $post_id );
				}
				
				$header_id           = (int) get_post_meta( $post_id, '_et_header_layout_id', true );
				$body_id             = (int) get_post_meta( $post_id, '_et_body_layout_id', true );
				$footer_id           = (int) get_post_meta( $post_id, '_et_footer_layout_id', true );
				
				if ( $header_id && isset($this->processed_posts[ $header_id ]) ) {
					update_post_meta($post_id, '_et_header_layout_id', $this->processed_posts[ $header_id ]);
				}
				if ( $body_id && isset($this->processed_posts[ $body_id ]) ) {
					update_post_meta($post_id, '_et_body_layout_id', $this->processed_posts[ $body_id ]);
				}
				if ( $footer_id && isset($this->processed_posts[ $footer_id ]) ) {
					update_post_meta($post_id, '_et_footer_layout_id', $this->processed_posts[ $footer_id ]);
				}
				
			}
		}
	}
	
	function allowDuplicateThemeBuilderPost($postType) {
		$allowedTypes = array();
	
		if ( defined( 'ET_THEME_BUILDER_TEMPLATE_POST_TYPE' ) ) {
			$allowedTypes[] = ET_THEME_BUILDER_TEMPLATE_POST_TYPE;
		}
		if ( defined( 'ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE' ) ) {
			$allowedTypes[] = ET_THEME_BUILDER_HEADER_LAYOUT_POST_TYPE;
		}
		if ( defined( 'ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE' ) ) {
			$allowedTypes[] = ET_THEME_BUILDER_BODY_LAYOUT_POST_TYPE;
		}
		if ( defined( 'ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE' ) ) {
			$allowedTypes[] = ET_THEME_BUILDER_FOOTER_LAYOUT_POST_TYPE;
		}
	
		return in_array( $postType, $allowedTypes );
	}


	/**
	 * Attempt to create a new menu item from import data
	 *
	 * Fails for draft, orphaned menu items and those without an associated nav_menu
	 * or an invalid nav_menu term. If the post type or term object which the menu item
	 * represents doesn't exist then the menu item will not be imported (waits until the
	 * end of the import to retry again before discarding).
	 *
	 * @param array $item Menu item details from WXR file
	 */
	function process_menu_item( $item ) {
		// skip draft, orphaned menu items
		if ( 'draft' == $item['status'] )
			return;

		$menu_slug = false;
		if ( isset($item['terms']) ) {
			// loop through terms, assume first nav_menu term is correct menu
			foreach ( $item['terms'] as $term ) {
				if ( 'nav_menu' == $term['domain'] ) {
					$menu_slug = $term['slug'];
					break;
				}
			}
		}

		// no nav_menu term associated with this menu item
		if ( ! $menu_slug ) {
			AGS_Theme_Importer::$instance->message( __('Menu item skipped due to missing menu slug', 'wp-layouts-td') );
			return;
		}

		$menu_id = term_exists( $menu_slug, 'nav_menu' );
		if ( ! $menu_id ) {
			AGS_Theme_Importer::$instance->message( sprintf( __( 'Menu item skipped due to invalid menu slug: %s', 'wp-layouts-td' ), esc_html($menu_slug) ) );
			return;
		} else {
			$menu_id = is_array( $menu_id ) ? $menu_id['term_id'] : $menu_id;
		}
		
		foreach ( $item['postmeta'] as $meta )
			${$meta['key']} = $meta['value'];

		if ( 'taxonomy' == $_menu_item_type && isset( $this->processed_terms[intval($_menu_item_object_id)] ) ) {
			$_menu_item_object_id = $this->processed_terms[intval($_menu_item_object_id)];
		} else if ( 'post_type' == $_menu_item_type && isset( $this->processed_posts[intval($_menu_item_object_id)] ) ) {
			$_menu_item_object_id = $this->processed_posts[intval($_menu_item_object_id)];
		} else if ( 'custom' != $_menu_item_type ) {
			// associated object is missing or not imported yet, we'll retry later
			$this->missing_menu_items[] = $item;
			return;
		}

		if ( isset( $this->processed_menu_items[intval($_menu_item_menu_item_parent)] ) ) {
			$_menu_item_menu_item_parent = $this->processed_menu_items[intval($_menu_item_menu_item_parent)];
		} else if ( $_menu_item_menu_item_parent ) {
			$this->menu_item_orphans[intval($item['post_id'])] = (int) $_menu_item_menu_item_parent;
			$_menu_item_menu_item_parent = 0;
		}

		// wp_update_nav_menu_item expects CSS classes as a space separated string
		$_menu_item_classes = maybe_unserialize( $_menu_item_classes );
		if ( is_array( $_menu_item_classes ) )
			$_menu_item_classes = implode( ' ', $_menu_item_classes );

		$args = array(
			'menu-item-object-id' => $_menu_item_object_id,
			'menu-item-object' => $_menu_item_object,
			'menu-item-parent-id' => $_menu_item_menu_item_parent,
			'menu-item-position' => intval( $item['menu_order'] ),
			'menu-item-type' => $_menu_item_type,
			'menu-item-title' => $item['post_title'],
			'menu-item-url' => $_menu_item_url,
			'menu-item-description' => $item['post_content'],
			'menu-item-attr-title' => $item['post_excerpt'],
			'menu-item-target' => $_menu_item_target,
			'menu-item-classes' => $_menu_item_classes,
			'menu-item-xfn' => $_menu_item_xfn,
			'menu-item-status' => $item['status']
		);

		$id = wp_update_nav_menu_item( $menu_id, 0, $args );
		if ( $id && ! is_wp_error( $id ) )
			$this->processed_menu_items[intval($item['post_id'])] = (int) $id;
	}

	/**
	 * If fetching attachments is enabled then attempt to create a new attachment
	 *
	 * @param array $post Attachment post details from WXR
	 * @param string $url URL to fetch attachment from
	 * @return int|WP_Error Post ID on success, WP_Error otherwise
	 */
	function process_attachment( $post, $url ) {
		if ( ! $this->fetch_attachments )
			return new WP_Error( 'attachment_processing_error',
				__( 'Fetching attachments is not enabled', 'wp-layouts-td' ) );

		// if the URL is absolute, but does not contain address, then upload it assuming base_site_url
		if ( preg_match( '|^/[\w\W]+$|', $url ) )
			$url = rtrim( $this->base_url, '/' ) . $url;

		$upload = $this->fetch_remote_file( $url, $post );
		if ( is_wp_error( $upload ) )
			return $upload;

		if ( $info = wp_check_filetype( $upload['file'] ) )
			$post['post_mime_type'] = $info['type'];
		else
			return new WP_Error( 'attachment_processing_error', __('Invalid file type', 'wp-layouts-td') );

		$post['guid'] = $upload['url'];

		// as per wp-admin/includes/upload.php
		$post_id = wp_insert_attachment( $post, $upload['file'] );
		wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );

		// remap resized image URLs, works by stripping the extension and remapping the URL stub.
		if ( preg_match( '!^image/!', $info['type'] ) ) {
			/*
			$parts = pathinfo( $url );
			$name = basename( $parts['basename'], ".{$parts['extension']}" ); // PATHINFO_FILENAME in PHP 5.2

			$parts_new = pathinfo( $upload['url'] );
			$name_new = basename( $parts_new['basename'], ".{$parts_new['extension']}" );
			*/
			$this->url_remap[ $url ] = $upload['url'];
			
		}

		return $post_id;
	}

	/**
	 * Attempt to download a remote file attachment
	 *
	 * @param string $url URL of item to fetch
	 * @param array $post Attachment details
	 * @return array|WP_Error Local file location details on success, WP_Error otherwise
	 */
	function fetch_remote_file( $url, $post ) {
		// extract the file name and extension from the url
		$hashPos = strpos($url, '#');
		if ($hashPos !== false) {
			$file_name = base64_decode( substr($url, $hashPos + 1) );
			$url = substr($url, 0, $hashPos);
		}
		if ( empty($file_name) ) {
			$file_name = basename( $url );
		}
		
		if (strpos($url, 'ags_layouts_get_image') !== false) {
			parse_str(substr($url, strpos($url, 'ags_layouts_get_image') + 1), $query);
			
			
			if ( isset($query['layoutId']) && isset($query['image']) ) {
				include_once(__DIR__.'/../account.php');
				
				// The action parameter must be broken up in the following URL to avoid overwriting by the site export packager,
				// since the action name matches an action name within the plugin.
				$url = AGSLayouts::API_URL
						.'?action=ags_layouts_'.'get_layout_image&_ags_layouts_token='.urlencode(AGSLayoutsAccount::getToken($query['layoutId']))
						.'&_ags_layouts_site='.urlencode(get_option('siteurl'))
						.'&layoutId='.$query['layoutId']
						.'&imageFile='.urlencode($query['image']);
				
			}
			
		}

		// get placeholder file in the upload dir with a unique, sanitized filename
		$upload = wp_upload_bits( $file_name, null, '', $post['upload_date'] );
		if ( $upload['error'] )
			return new WP_Error( 'upload_dir_error', $upload['error'] );

		// fetch the remote url and write it to the placeholder file
		global $ags_wp_importer_http;
		if (!isset($ags_wp_importer_http)) {
			$ags_wp_importer_http = new WP_Http();
		}
		$result = $ags_wp_importer_http->request( $url, array(
			'stream' => true,
			'filename' => $upload['file']
		));
		
		// request failed
		if ( is_wp_error($result) || empty($result['response']['code']) ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', __('Remote server did not respond', 'wp-layouts-td') );
		}

		// make sure the fetch was successful
		if ( $result['response']['code'] != '200' ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', sprintf( __('Remote server returned error response %1$d %2$s', 'wp-layouts-td'), esc_html($result['response']['code']), get_status_header_desc($result['response']['code']) ) );
		}

		$filesize = filesize( $upload['file'] );

		if ( isset( $result['headers']['content-length'] ) && $filesize != $result['headers']['content-length'] ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', __('Remote file is incorrect size', 'wp-layouts-td') );
		}

		if ( 0 == $filesize ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', __('Zero size file downloaded', 'wp-layouts-td') );
		}

		$max_size = 0;
		if ( ! empty( $max_size ) && $filesize > $max_size ) {
			@unlink( $upload['file'] );
			return new WP_Error( 'import_file_error', sprintf(__('Remote file is too large, limit is %s', 'wp-layouts-td'), size_format($max_size) ) );
		}

		// keep track of the old and new urls so we can substitute them later
		$this->url_remap[$url] = $upload['url'];
		$this->url_remap[$post['guid']] = $upload['url']; // r13735, really needed?
		// keep track of the destination if the remote url is redirected somewhere else
		if ( isset($result['headers']['x-final-location']) && $result['headers']['x-final-location'] != $url )
			$this->url_remap[$result['headers']['x-final-location']] = $upload['url'];

		return $upload;
	}

	/**
	 * Attempt to associate posts and menu items with previously missing parents
	 *
	 * An imported post's parent may not have been imported when it was first created
	 * so try again. Similarly for child menu items and menu items which were missing
	 * the object (e.g. post) they represent in the menu
	 */
	function backfill_parents() {
		global $wpdb;

		// find parents for post orphans
		foreach ( $this->post_orphans as $child_id => $parent_id ) {
			$local_child_id = $local_parent_id = false;
			if ( isset( $this->processed_posts[$child_id] ) )
				$local_child_id = $this->processed_posts[$child_id];
			if ( isset( $this->processed_posts[$parent_id] ) )
				$local_parent_id = $this->processed_posts[$parent_id];

			if ( $local_child_id && $local_parent_id )
				$wpdb->update( $wpdb->posts, array( 'post_parent' => $local_parent_id ), array( 'ID' => $local_child_id ), '%d', '%d' );
		}

		// all other posts/terms are imported, retry menu items with missing associated object
		$missing_menu_items = $this->missing_menu_items;
		foreach ( $missing_menu_items as $item )
			$this->process_menu_item( $item );

		// find parents for menu item orphans
		foreach ( $this->menu_item_orphans as $child_id => $parent_id ) {
			$local_child_id = $local_parent_id = 0;
			if ( isset( $this->processed_menu_items[$child_id] ) )
				$local_child_id = $this->processed_menu_items[$child_id];
			if ( isset( $this->processed_menu_items[$parent_id] ) )
				$local_parent_id = $this->processed_menu_items[$parent_id];

			if ( $local_child_id && $local_parent_id )
				update_post_meta( $local_child_id, '_menu_item_menu_item_parent', (int) $local_parent_id );
		}
	}

	/**
	 * Use stored mapping information to update old attachment URLs
	 */
	function backfill_attachment_urls() {
		global $wpdb;
		// make sure we do the longest urls first, in case one is a substring of another
		uksort( $this->url_remap, array(&$this, 'cmpr_strlen') );

		foreach ( $this->url_remap as $from_url => $to_url ) {
			// remap urls in post_content
			$wpdb->query( $wpdb->prepare("UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)", $from_url, $to_url) );
			// remap enclosure urls
			$result = $wpdb->query( $wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_key='enclosure'", $from_url, $to_url) );
		}
	}

	/**
	 * Update _thumbnail_id meta to new, imported attachment IDs
	 */
	function remap_featured_images() {
		// cycle through posts that have a featured image
		foreach ( $this->featured_images as $post_id => $value ) {
			if ( isset( $this->processed_posts[$value] ) ) {
				$new_id = $this->processed_posts[$value];
				// only update if there's a difference
				if ( $new_id != $value )
					update_post_meta( $post_id, '_thumbnail_id', $new_id );
			}
		}
	}

	/**
	 * Parse a WXR file
	 *
	 * @param string $file Path to WXR file for parsing
	 * @return array Information gathered from the WXR file
	 */
	function parse( $file ) {
		$parser = new AGS_Layouts_WXR_Parser_Regex();
		return $parser->parse( $file );
	}

	/**
	 * Decide if the given meta key maps to information we will want to import
	 *
	 * @param string $key The meta key to check
	 * @return string|bool The key if we do want to import, false if not
	 */
	function is_valid_meta_key( $key ) {
		// skip attachment metadata since we'll regenerate it from scratch
		// skip _edit_lock as not relevant for import
		if ( in_array( $key, array( '_wp_attached_file', '_wp_attachment_metadata', '_edit_lock' ) ) )
			return false;
		return $key;
	}

	// return the difference in length between two strings
	function cmpr_strlen( $a, $b ) {
		return strlen($b) - strlen($a);
	}
}

} // class_exists( 'WP_Importer' )