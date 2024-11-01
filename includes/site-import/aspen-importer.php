<?php
/**
 * This file contains third-party code;
 * See the license.txt file in the WP Layouts plugin root directory for more information and licenses
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/** Display verbose errors */
if (!defined('IMPORT_DEBUG')) {
	// phpcs:ignore WordPress.Security.NonceVerification
	define( 'IMPORT_DEBUG', !empty($_GET['ags-demo-debug']) );
}

// Don't duplicate me!
if (!class_exists('AGS_Theme_Importer')) {

    class AGS_Theme_Importer
    {

        private $progress, $taskProgressRatios;

        public  $importPost, $importPostContent, $theme_options, $divi_module_presets, $widgets, $content_demo,
				$flag_as_imported = array('content' => false, 'menus' => false, 'options' => false, 'widgets' => false),
				$imported_demos = array(), $add_admin_menu = true, $messages = array(), $config,
				$tasks = array('init', 'content_categories', 'content_tags', 'content_terms', 'content_posts', 'content_attachments', 'content_themebuilder', 'theme_options', 'divi_module_presets', 'set_menus', 'widgets', 'caldera_forms', 'content_end');
       
	   public static	$instance,
						$compatiblity = array(
							'content_terms' => array(
								'woocommerce' => array(
									'enableOnClass' => 'WooCommerce',
									'loadOnce' => true
								)
							)
						);

        /**
         * Constructor. Hooks all interactions to initialize the class.
         *
         * @since 0.0.2
         */
        public function __construct() {
			$importPostId = AGSLayoutsSiteImporter::getImportPostId();
			if ($importPostId) {
				$this->importPost = get_post($importPostId);
			}
			$this->importPostContent = AGSLayoutsSiteImporter::getImportPostContent($importPostId);
			
			$this->content_demo = !empty($this->importPostContent);
			$this->widgets = get_post_meta($this->importPost->ID, '_ags_layouts_widgets', true);
			$this->caldera_forms = get_post_meta($this->importPost->ID, '_ags_layouts_caldera_forms', true);
			$this->theme_options = get_post_meta($this->importPost->ID, '_ags_layouts_agsxto', true);
			$this->divi_module_presets = get_post_meta($this->importPost->ID, '_ags_layouts_divi_module_presets', true);
			$this->config = get_post_meta($this->importPost->ID, '_ags_layouts_config', true);
			if ($this->config) {
				$this->config = @json_decode($this->config, true);
				if ($this->config === false) {
					$this->message( __('Error reading config.', 'wp-layouts-td') );
					$this->progress('error', 'done', 1);
				}
			}
			
            self::$instance = $this;
        }

        /**
         * Avoids adding duplicate meta causing arrays in arrays from WP_importer
         *
         * @param null $continue
         * @param unknown $post_id
         * @param unknown $meta_key
         * @param unknown $meta_value
         * @param unknown $unique
         *
         * @return
         * @since 0.0.2
         *
         */
        public function check_previous_meta($continue, $post_id, $meta_key, $meta_value, $unique)
        {

            if ($meta_key == '_et_use_on' || $meta_key == '_et_template') {
                return $continue;
            }

            $old_value = get_metadata('post', $post_id, $meta_key);

            if ( is_array($old_value) && count($old_value) == 1) {

                if ($old_value[0] === $meta_value) {
                    return false;
                } elseif ($old_value[0] !== $meta_value) {
                    update_post_meta($post_id, $meta_key, $meta_value);
                    return false;
                }
            }
        }

        /**
         * Process all imports
         *
         * @params $content
         * @params $options
         * @params $options
         * @params $widgets
         *
         * @return null
         * @since 0.0.3
         *
         */
        public function process_imports($enabledTasks, $pid, $lastTask, $lastTaskState)
        {
			if ( array_diff($enabledTasks, $this->tasks) ) {
				$this->message( __('Invalid selected task name(s).', 'wp-layouts-td') );
				$this->progress('error', 'done', 1);
			}
			
			$lastTaskNum = array_search($lastTask, $this->tasks);
			$hasContentTasks = array_intersect(array('content_categories', 'content_tags', 'content_terms', 'content_posts', 'content_attachments', 'content_themebuilder'), $enabledTasks);
			
			if ( $lastTaskNum === false ) {
				$this->message( __('Invalid last task name.', 'wp-layouts-td') );
				$this->progress('error', 'done', 1);
			}
			
			if ( !$lastTaskNum ) {
				// This is the first task after init so let's clear the state
				delete_user_meta(get_current_user_id(), 'ags-theme-demo-import-state');
			}
			
			if ($lastTaskState == 'done') {
				do {
					++$lastTaskNum;
				} while(
					isset($this->tasks[$lastTaskNum])
					&& !(
						in_array($this->tasks[$lastTaskNum], $enabledTasks)
						|| ( $this->tasks[$lastTaskNum] == 'content_end' && $hasContentTasks )
					)
				);
				if ( isset($this->tasks[$lastTaskNum]) ) {
					$nextTask = $this->tasks[$lastTaskNum];
					$nextTaskState = null;
				}
			} else {
				$nextTask = $lastTask;
				$nextTaskState = $lastTaskState;
			}
			
			if ( isset($nextTask) ) {
			
				// Run compatibility
				if ( isset( self::$compatiblity[$nextTask] ) ) {
					foreach (self::$compatiblity[$nextTask] as $compatName => $compatParams) {
						if ( AGSLayouts::isEnabled($compatParams) && ( empty($compatParams['loadOnce']) || $nextTaskState === null ) ) {
							include_once(__DIR__.'/compatibility/'.$compatName.'/'.$nextTask.'.php');
						}
					}
				}
			
				// Set up progress reporting
				$this->taskProgressRatios = array();
				if (!empty($this->content_demo) && $hasContentTasks) {
					
					if ( in_array('content_categories', $enabledTasks) ) {
						$this->taskProgressRatios['content_categories'] = 2;
					}
					
					if ( in_array('content_tags', $enabledTasks) ) {
						$this->taskProgressRatios['content_tags'] = 2;
					}
					
					if ( in_array('content_terms', $enabledTasks) ) {
						$this->taskProgressRatios['content_terms'] = 2;
					}
					
					if ( in_array('content_posts', $enabledTasks) ) {
						$this->taskProgressRatios['content_posts'] = 20;
					}
					
					if ( in_array('content_attachments', $enabledTasks) ) {
						$this->taskProgressRatios['content_attachments'] = 170;
					}
					
					if ( in_array('content_themebuilder', $enabledTasks) ) {
						$this->taskProgressRatios['content_themebuilder'] = 20;
					}
				}
				if ( in_array('theme_options', $enabledTasks) && !empty($this->theme_options) ) {
					$this->taskProgressRatios['theme_options'] = 5;
				}
				if ( in_array('divi_module_presets', $enabledTasks) && !empty($this->divi_module_presets) ) {
					$this->taskProgressRatios['divi_module_presets'] = 5;
				}
				if ( in_array('set_menus', $enabledTasks) ) {
					$this->taskProgressRatios['set_menus'] = 1;
				}
				
				if ( in_array('widgets', $enabledTasks) && !empty($this->widgets) ) {
					$this->taskProgressRatios['widgets'] = 5;
				}
				if (  in_array('caldera_forms', $enabledTasks) && !empty($this->caldera_forms) ) {
					$this->taskProgressRatios['caldera_forms'] = 5;
				}
				
				if (!empty($this->content_demo) && $hasContentTasks) {
					$this->taskProgressRatios['content_end'] = 2;
				}
				
				
				$total = array_sum($this->taskProgressRatios);
				foreach ($this->taskProgressRatios as $i => $value) {
					$this->taskProgressRatios[$i] = $value / $total;
				}
				
				switch ($nextTask) {
					case 'content_categories':
					case 'content_tags':
					case 'content_terms':
					case 'content_posts':
					case 'content_attachments':
					case 'content_themebuilder':
					case 'content_end':
						if (!empty($this->content_demo)) {
							if (defined('IMPORT_DEBUG') && IMPORT_DEBUG) {
								$this->message( current_time('r').' '.__('Importing demo content...', 'wp-layouts-td') );
							}
							
							if ( empty($this->importPostContent) ) {
								$this->message( __('Could not load demo content.', 'wp-layouts-td') );
								$this->progress('error', 'done', 1);
							}
							
							$this->set_demo_data( $this->importPostContent, $pid, $nextTask, $nextTaskState );
							break;
						}
						
					case 'theme_options':
						if (!empty($this->theme_options)) {
							if (defined('IMPORT_DEBUG') && IMPORT_DEBUG) {
								$this->message( current_time('r').' '.__('Importing theme options...', 'wp-layouts-td') );
							}
							$this->set_demo_theme_options($this->theme_options);
							$this->progress('theme_options', 'done');
							break;
						}
					case 'divi_module_presets':
						if (!empty($this->divi_module_presets)) {
							if (defined('IMPORT_DEBUG') && IMPORT_DEBUG) {
								$this->message( current_time('r').' '.__('Importing Divi Builder module presets...', 'wp-layouts-td') );
							}
							$this->import_divi_module_presets($this->divi_module_presets);
							$this->progress('divi_module_presets', 'done');
							break;
						}
					case 'set_menus':
						$this->set_demo_menus();
						$this->progress('set_menus', 'done');
						break;
						
					case 'widgets':
						if ( !empty($this->widgets) ) {
							if (defined('IMPORT_DEBUG') && IMPORT_DEBUG) {
								$this->message( current_time('r').' '.__('Importing widgets...', 'wp-layouts-td') );
							}
							$this->import_widgets( @json_decode($this->widgets), $pid );
							$this->progress('widgets', 'done');
							break;
						}
						
					case 'caldera_forms':
						if ( !empty($this->caldera_forms) ) {
							if (class_exists('Caldera_Forms_Forms')) {
								if (defined('IMPORT_DEBUG') && IMPORT_DEBUG) {
									$this->message( current_time('r').' '.__('Importing Caldera Forms data...', 'wp-layouts-td') );
								}

								if ( !$this->import_caldera_forms($this->caldera_forms) ) {
									$this->message( __('An error occurred while importing Caldera Forms data.', 'wp-layouts-td') );
								}
							} else {
								$this->message( __('The Caldera Forms plugin could not be found. Caldera Forms data will not be imported.', 'wp-layouts-td') );
							}

							$this->progress('caldera_forms', 'done');
							break;
						}
				}
			
			}

            // From wordpress-importer.php
            // echo '<p>' . esc_html__('All done, enjoy your new child theme!', 'wp-layouts-td') . '</p>';
            // echo '<p><em>' . esc_html__('Please check whether the import was successful.', 'wp-layouts-td') . '</em></p>';
            $this->progress(null, 'done', 1);
            do_action('radium_import_end');
        }

        /**
         * add_widget_to_sidebar Import sidebars
         * @param string $sidebar_slug Sidebar slug to add widget
         * @param string $widget_slug Widget slug
         * @param string $count_mod position in sidebar
         * @param array $widget_settings widget settings
         *
         * @return null
         * @since 0.0.2
         *
         */
        public function add_widget_to_sidebar($sidebar_slug, $widget_slug, $count_mod, $widget_settings = array())
        {
            $sidebars_widgets = get_option('sidebars_widgets');

            if (!isset($sidebars_widgets[$sidebar_slug]))
                $sidebars_widgets[$sidebar_slug] = array('_multiwidget' => 1);

            $newWidget = get_option('widget_' . $widget_slug);

            if (!is_array($newWidget))
                $newWidget = array();

            $count = count($newWidget) + 1 + $count_mod;
            $sidebars_widgets[$sidebar_slug][] = $widget_slug . '-' . $count;

            $newWidget[$count] = $widget_settings;

            update_option('sidebars_widgets', $sidebars_widgets);
            update_option('widget_' . $widget_slug, $newWidget);
        }

		
        public function set_demo_data($fileContents, $pid, $task, $taskState)
        {
            if (!defined('WP_LOAD_IMPORTERS')) define('WP_LOAD_IMPORTERS', true);

            require_once ABSPATH . 'wp-admin/includes/import.php';

            $importer_error = false;

            if (!class_exists('AGSLayouts_WP_Import')) {
                $class_wp_import = dirname(__FILE__) . '/wordpress-importer.php';

                if (file_exists($class_wp_import))
                    require_once($class_wp_import);
					
				if (!class_exists('AGSLayouts_WP_Import')) {
                    $importer_error = true;
				}
            }

            if ($importer_error) {
                die("Error on import");
            } else {
                // Register Divi Builder layouts first, if applicable
                if (function_exists('et_builder_register_layouts') && !post_type_exists('et_pb_layout')) {
                    et_builder_register_layouts();
                }
				
				
				$uploadDir = wp_upload_dir();
				if ( empty($uploadDir['basedir']) ) {
					echo esc_html__('Unable to get the uploads directory location', 'wp-layouts-td');
				} else {
					$file = $uploadDir['basedir'].'/wpl-site-import-'.wp_generate_password(30, false);
					
					if ( !empty($fileContents) && file_put_contents($file, $fileContents) ) {
						unset($fileContents);

						$wp_import = new AGSLayouts_WP_Import();
						$wp_import->fetch_attachments = true;

						add_filter('add_post_metadata', array($this, 'check_previous_meta'), 10, 5);
						$wp_import->import($file, $pid, $task, $taskState);
						remove_filter('add_post_metadata', array($this, 'check_previous_meta'), 10, 5);

						$this->flag_as_imported['content'] = true;
					} else {
						echo esc_html__('The XML file containing the dummy content is not available or could not be read or written. Please ensure that the file permissions are set to chmod 755.', 'wp-layouts-td') . '<br/>';
						echo esc_html__('If this doesn\'t work please use the WordPress importer and manually import the XML file (located in your theme .zip file in the admin/demo-files directory).', 'wp-layouts-td');
					}
				}
				
			}

            do_action('radium_importer_after_theme_content_import');
        }

		/**
		 * Add menus - the menus listed here largely depend on the ones registered in the theme
		 *
		 * @since 0.0.1
		 */
		public function set_demo_menus()
		{
			$menuLocations = array();
			
			if ( !empty($this->config['menus']) ) {
				foreach ($this->config['menus'] as $location => $name) {
					$menu = get_term_by('name', $name, 'nav_menu');
					if ( !empty($menu) ) {
						$menuLocations[$location] = $menu->term_id;
					}
				}
				
				set_theme_mod( 'nav_menu_locations', $menuLocations);
			}
			
			$this->flag_as_imported['menus'] = true;

		}

        public function set_demo_theme_options($data)
        {
            // Does the data exist?
            if ($data) {

                // Get file contents and decode
                $options = @unserialize(@base64_decode($data));

                // Only if there is data
                if (!empty($options) && is_array($options)) {
                    $variableValues = array(
                        'siteurl' => get_option('siteurl') // Trailing slash is automatically removed by WP in get_option()
                    );

                    foreach ($options as $optionName => $option) {
                        /*$optionValue = array_merge(
                            get_option($optionName, array()),
                            $this->set_theme_options_variables($option['value'], $variableValues)
                        );*/
                        $optionValue = $this->set_theme_options_variables($option['value'], $variableValues);
                        if (isset($option['delete'])) {
                            foreach ($option['delete'] as $deleteField) {
                                unset($optionValue[$deleteField]);
                            }
                        }
                        update_option($optionName, $optionValue);
                    }

                    $this->flag_as_imported['options'] = true;
                } else {
					$this->message( __('An error occurred while importing theme and/or plugin options.', 'wp-layouts-td') );
                }

                if (method_exists('ET_Core_PageResource', 'remove_static_resources')) {
                    ET_Core_PageResource::remove_static_resources('all', 'all');
                }

                //do_action( 'radium_importer_after_theme_options_import', $this->active_import, $this->demo_files_path );

            } else {
				$this->message( __('Theme/plugin options import data could not be found', 'wp-layouts-td') );
            }
        }

        /* Helper function: replace theme option variable placeholders with values */
        private function set_theme_options_variables($options, $variableValues)
        {
            foreach ($options as $optionKey => $optionValue) {
                if (is_array($optionValue)) {
                    $options[$optionKey] = $this->set_theme_options_variables($optionValue, $variableValues);
                } else if (is_string($optionValue)) {
                    foreach ($variableValues as $variableName => $variableValue) {
                        $options[$optionKey] = str_replace('{{ags.' . $variableName . '}}', $variableValue, $optionValue);
                    }
                }
            }
            return $options;
        }

        /**
         * Available widgets
         *
         * Gather site's widgets into array with ID base, name, etc.
         * Used by export and import functions.
         *
         * @return array Widget information
         * @global array $wp_registered_widget_updates
         * @since 0.0.2
         *
         */
        function available_widgets()
        {
            global $wp_registered_widget_controls;
            $widget_controls = $wp_registered_widget_controls;
            $available_widgets = array();

            foreach ($widget_controls as $widget) {
                if (!empty($widget['id_base']) && !isset($available_widgets[$widget['id_base']])) { // no dupes
                    $available_widgets[$widget['id_base']]['id_base'] = $widget['id_base'];
                    $available_widgets[$widget['id_base']]['name'] = $widget['name'];
                }
            }

            return apply_filters('radium_theme_import_widget_available_widgets', $available_widgets);
        }

        /**
         * Import widget JSON data
         *
         * @param object $data JSON widget data from .json file
         * @return array Results array
         * @since 0.0.2
         * @global array $wp_registered_sidebars
         */
        public function import_widgets($data, $pid)
        {

            global $wp_registered_sidebars;

            // Have valid data?
            // If no data or could not decode
            if (empty($data) || !is_object($data)) {
				$this->message( __('Skipping widgets import due to invalid widgets data', 'wp-layouts-td') );
                return;
            }

            // Get all available widgets site supports
            $available_widgets = $this->available_widgets();

            // Get all existing widget instances
            $widget_instances = array();
            foreach ($available_widgets as $widget_data) {
                $widget_instances[$widget_data['id_base']] = get_option('widget_' . $widget_data['id_base']);
            }

            // Begin results
            $results = array();

            // Loop import data's sidebars
            foreach ($data as $sidebar_id => $widgets) {

                // Skip inactive widgets
                // (should not be in export file)
                if ('wp_inactive_widgets' == $sidebar_id) {
                    continue;
                }

                // Check if sidebar is available on this site
                // Otherwise add widgets to inactive, and say so
                if (isset($wp_registered_sidebars[$sidebar_id])) {
                    $sidebar_available = true;
                    $use_sidebar_id = $sidebar_id;
                    $sidebar_message_type = 'success';
                    $sidebar_message = '';
                } else {
                    $sidebar_available = false;
                    $use_sidebar_id = 'wp_inactive_widgets'; // add to inactive if sidebar does not exist in theme
                    $sidebar_message_type = 'error';
                    $sidebar_message = __('Sidebar does not exist in theme (using Inactive)', 'wp-layouts-td');
                }

                // Result for sidebar
                $results[$sidebar_id]['name'] = !empty($wp_registered_sidebars[$sidebar_id]['name']) ? $wp_registered_sidebars[$sidebar_id]['name'] : $sidebar_id; // sidebar name if theme supports it; otherwise ID
                $results[$sidebar_id]['message_type'] = $sidebar_message_type;
                $results[$sidebar_id]['message'] = $sidebar_message;
                $results[$sidebar_id]['widgets'] = array();

                // Loop widgets
                foreach ($widgets as $widget_instance_id => $widget) {

                    $fail = false;

                    // Get id_base (remove -# from end) and instance ID number
                    $id_base = preg_replace('/-[0-9]+$/', '', $widget_instance_id);
                    $instance_id_number = str_replace($id_base . '-', '', $widget_instance_id);

                    // Does site support this widget?
                    if (!$fail && !isset($available_widgets[$id_base])) {
                        $fail = true;
                        $widget_message_type = 'error';
                        $widget_message = __('Site does not support widget', 'wp-layouts-td'); // explain why widget not imported
                    }

                    // Filter to modify settings before import
                    // Do before identical check because changes may make it identical to end result (such as URL replacements)
                    $widget = apply_filters('radium_theme_import_widget_settings', $widget);

                    // Does widget with identical settings already exist in same sidebar?
                    if (!$fail && isset($widget_instances[$id_base])) {

                        // Get existing widgets in this sidebar
                        $sidebars_widgets = get_option('sidebars_widgets');
                        $sidebar_widgets = isset($sidebars_widgets[$use_sidebar_id]) ? $sidebars_widgets[$use_sidebar_id] : array(); // check Inactive if that's where will go

                        // Loop widgets with ID base
                        $single_widget_instances = !empty($widget_instances[$id_base]) ? $widget_instances[$id_base] : array();
                        foreach ($single_widget_instances as $check_id => $check_widget) {

                            // Is widget in same sidebar and has identical settings?
                            if (in_array("$id_base-$check_id", $sidebar_widgets) && (array)$widget == $check_widget) {

                                $fail = true;
                                $widget_message_type = 'warning';
                                $widget_message = __('Widget already exists', 'wp-layouts-td'); // explain why widget not imported
                                break;
                            }
                        }
                    }

                    // No failure
                    if (!$fail) {
						switch ($id_base) {
						
						case 'et_ads':
						
						if (!empty($widget->ads)) {
                            foreach ($widget->ads as &$ad) {
                                $ad = get_object_vars($ad);
                            }
                        }
						break;
						
						case 'nav_menu':
						
						if (isset($widget->nav_menu)) {
							 if (!class_exists('AGSLayouts_WP_Import')) {
								$class_wp_import = dirname(__FILE__) . '/wordpress-importer.php';
								if (file_exists($class_wp_import)) {
									require_once($class_wp_import);
								}
							}
					
							if (class_exists('AGSLayouts_WP_Import')) {
								$wp_import = new AGSLayouts_WP_Import();
								$wp_import->load_state($pid);
								
								if ( isset($wp_import->processed_terms[$widget->nav_menu]) ) {
									$widget->nav_menu = (int) $wp_import->processed_terms[$widget->nav_menu];
								}
							}
							
						}
						break;
						
							
						}
					
                        

                        // Add widget instance
                        $single_widget_instances = get_option('widget_' . $id_base); // all instances for that widget ID base, get fresh every time
                        $single_widget_instances = !empty($single_widget_instances) ? $single_widget_instances : array('_multiwidget' => 1); // start fresh if have to
                        $single_widget_instances[] = (array)$widget; // add it

                        // Get the key it was given
                        end($single_widget_instances);
                        $new_instance_id_number = key($single_widget_instances);

                        // If key is 0, make it 1
                        // When 0, an issue can occur where adding a widget causes data from other widget to load, and the widget doesn't stick (reload wipes it)
                        if ('0' === strval($new_instance_id_number)) {
                            $new_instance_id_number = 1;
                            $single_widget_instances[$new_instance_id_number] = $single_widget_instances[0];
                            unset($single_widget_instances[0]);
                        }

                        // Move _multiwidget to end of array for uniformity
                        if (isset($single_widget_instances['_multiwidget'])) {
                            $multiwidget = $single_widget_instances['_multiwidget'];
                            unset($single_widget_instances['_multiwidget']);
                            $single_widget_instances['_multiwidget'] = $multiwidget;
                        }

                        // Update option with new widget
                        update_option('widget_' . $id_base, $single_widget_instances);

                        // Assign widget instance to sidebar
                        $sidebars_widgets = get_option('sidebars_widgets'); // which sidebars have which widgets, get fresh every time
                        $new_instance_id = $id_base . '-' . $new_instance_id_number; // use ID number from new widget instance
                        $sidebars_widgets[$use_sidebar_id][] = $new_instance_id; // add new instance to sidebar
                        update_option('sidebars_widgets', $sidebars_widgets); // save the amended data

                        // Success message
                        if ($sidebar_available) {
                            $widget_message_type = 'success';
                            $widget_message = __('Imported', 'wp-layouts-td');
                        } else {
                            $widget_message_type = 'warning';
                            $widget_message = __('Imported to Inactive', 'wp-layouts-td');
                        }
                    }

                    // Result for widget instance
                    $results[$sidebar_id]['widgets'][$widget_instance_id]['name'] = isset($available_widgets[$id_base]['name']) ? $available_widgets[$id_base]['name'] : $id_base; // widget name or ID if name not available (not supported by site)
                    $results[$sidebar_id]['widgets'][$widget_instance_id]['title'] = empty($widget->title) ? __('No Title', 'wp-layouts-td') : $widget->title; // show "No Title" if widget instance is untitled
                    $results[$sidebar_id]['widgets'][$widget_instance_id]['message_type'] = $widget_message_type;
                    $results[$sidebar_id]['widgets'][$widget_instance_id]['message'] = $widget_message;
                }
            }

            $this->flag_as_imported['widgets'] = true;

            // Hook after import
            do_action('radium_theme_import_widget_after_import');

            // Return results
            return apply_filters('radium_theme_import_widget_results', $results);
        }

        public function import_caldera_forms($form_configs)
        {
            if (!class_exists('Caldera_Forms_Forms')) {
                return false;
            }
			
			$form_configs = json_decode($form_configs, true);
			if (empty($form_configs)) {
				return false;
			}

			$forms = Caldera_Forms_Forms::get_forms();
			
			foreach ( $form_configs as $form_config ) {
				// Check for existing form
				if (isset($forms[$form_config['ID']])) {
					$this->message(  sprintf( __('A Caldera Form with ID "%s" already exists on the site. This form will not be imported.', 'wp-layouts-td'), $form_config['ID'] ) );
				} else {
					//import_form() returns form ID, or false on fail
					$form_id = Caldera_Forms_Forms::import_form($form_config);
				}
			}
			
            return true;
        }
		
		public function import_divi_module_presets($json)
		{
			$presets = @json_decode($json, true);
			
			if ( function_exists('et_core_portability_load') && !empty($presets['presets']) ) {
				
				$presetsForImport = [];
				foreach ($presets['presets'] as $module => $modulePresets) {
					$presetsForImport[$module] = [
						'presets' => array_map(
							function($preset) {
								$preset['created'] = time();
								$preset['modified'] = $preset['created'];
								return $preset;
							},
							$modulePresets
						)
					];
				}
				
				$portability = et_core_portability_load('epanel');
				
				if ( !$portability->import_global_presets($presetsForImport) ) {
					return false;
				}
				
				if (!empty($presets['colors'])) {
					
					$colorsForImport = [];
					foreach ($presets['colors'] as $colorId => $color) {
						$colorsForImport[] = [ $colorId, $color ];
					}
					
					if ( !$portability->import_global_colors($colorsForImport) ) {
						return false;
					}
					
				}
				
				return true;
				
			}
			
			return false;
		}

        public function progress($task, $taskState, $val=1)
        {
            if (empty($task)) {
                $progress = $val;
            } else {
                $progress = 0;
                foreach ($this->tasks as $pastTask) {
					if ($pastTask == $task) {
						$progress += (isset($this->taskProgressRatios[$task]) ? $val * $this->taskProgressRatios[$task] : 0);
						break;
					} else {
						$progress += (isset($this->taskProgressRatios[$pastTask]) ? $this->taskProgressRatios[$pastTask] : 0);
					}
                }
            }
			
			$output = array(
				'progress' => $progress
			);
			
			
			if ($task) {
				$output['state'] = array(
					'task' => $task,
					'taskState' => $taskState,
				);
			}
			
			
			if ($this->messages) {
				$output['messages'] = $this->messages;
			}
			
			echo( json_encode($output) );
			
			exit;
        }
		
        public function message($message)
        {
           $this->messages[] = $message;
        }
		
		
    }//class
}//function_exists
