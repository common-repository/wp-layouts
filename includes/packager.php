<?php
AGSLayouts::VERSION; // Access control

class AGSLayoutsPackager {
	const USE_DIR = true;
	
	public static function run() {
		check_admin_referer('ags-layouts-site-export-package', 'ags_layouts_nonce');
		
		/** Validate package data **/
	
		if ( empty($_POST['package']) ) {
			wp_die('Missing package data');
		}
		
		$keys = array('layouts', 'mode', 'editor', 'menuName', 'menuParent', 'pageTitle', 'layoutDownloadError', 'classPrefix', 'textDomain', 'step1Html', 'step2Html',
						'buttonText', 'progressHeading', 'completeHeading', 'successHtml', 'errorHtml');
		$keys_i18n_text = array('menuName', 'pageTitle', 'layoutDownloadError', 'buttonText', 'progressHeading', 'completeHeading');
		$keys_i18n_html = array('step1Html', 'step2Html', 'successHtml', 'errorHtml');
		
		// phpcs:ignore ET.Sniffs.ValidatedSanitizedInput.InputNotSanitized -- input is validated as JSON and array keys are checked below
		$package = json_decode( wp_unslash( $_POST['package'] ), true );
		
		if ( empty($package) || array_diff( array_keys($package), $keys) || count($package) != count($keys) ) {
			wp_die('Invalid package data');
		}
		
		$mode = $package['mode'];
		if ( $mode != 'standalone' && $mode != 'hook' ) {
			wp_die('Invalid mode');
		}
		unset($package['mode']);
		
		
		foreach ($package['layouts'] as $layoutId => $layoutData) {
			if ( !is_numeric($layoutId) || !$layoutId || $layoutId != absint($layoutId) ) {
				wp_die('Invalid layout ID');
			}
			if ( empty($layoutData['name']) || empty($layoutData['key']) || count($layoutData) != 2 ) {
				wp_die('Invalid layout data');
			}
		}
		
		if ( $package['editor'] != 'SiteImporter' ) {
			wp_die('Unsupported editor');
		}
		
		
		if ( $mode == 'standalone' ) {
			
			$package['menuName'] = trim($package['menuName']);
			if ( empty($package['menuName']) ) {
				wp_die('Missing menu item name');
			}
			
			$package['menuParent'] = trim($package['menuParent']);
			if ( empty($package['menuParent']) ) {
				wp_die('Missing menu item parent');
			}
			
			$package['pageTitle'] = trim($package['pageTitle']);
			if ( empty($package['pageTitle']) ) {
				wp_die('Missing page title');
			}
			
			$package['layoutDownloadError'] = trim($package['layoutDownloadError']);
			if ( empty($package['layoutDownloadError']) ) {
				wp_die('Missing site export retrieval error message');
			}
			
			$package['classPrefix'] = trim($package['classPrefix']);
			if ( empty($package['classPrefix']) ) {
				wp_die('Missing class prefix');
			} else if ( !ctype_alnum( str_replace( '_', '', $package['classPrefix'] ) ) ) {
				wp_die('Invalid class prefix');
			}
			
			$package['textDomain'] = trim($package['textDomain']);
			if ( empty($package['textDomain']) ) {
				wp_die('Missing text domain');
			}
			
			$package['buttonText'] = trim($package['buttonText']);
			if ( empty($package['buttonText']) ) {
				wp_die('Missing button text');
			}
			
			$package['progressHeading'] = trim($package['progressHeading']);
			if ( empty($package['progressHeading']) ) {
				wp_die('Missing progress heading');
			}
			
			$package['completeHeading'] = trim($package['completeHeading']);
			if ( empty($package['completeHeading']) ) {
				wp_die('Missing complete heading');
			}
			
			$package['step1Html'] = trim( str_replace( array("\n", "\r"), '', wpautop($package['step1Html']) ) );
			$package['step2Html'] = trim( str_replace( array("\n", "\r"), '', wpautop($package['step2Html']) ) );
			$package['successHtml'] = trim( str_replace( array("\n", "\r"), '', wpautop($package['successHtml']) ) );
			$package['errorHtml'] = trim( str_replace( array("\n", "\r"), '', wpautop($package['errorHtml']) ) );
			
		} else {
			
			unset($package['menuName']);
			unset($package['menuParent']);
			unset($package['pageTitle']);
			unset($package['classPrefix']);
			unset($package['layoutDownloadError']);
			unset($package['textDomain']);
			unset($package['step1Html']);
			unset($package['step2Html']);
			unset($package['buttonText']);
			unset($package['progressHeading']);
			unset($package['completeHeading']);
			unset($package['successHtml']);
			unset($package['errorHtml']);
			
			$package['wplVersion'] = AGSLayouts::VERSION;
		
			$code = 'add_filter(\'ags_layouts_theme_demo_data\', function() {'."\n"
					.'  return '.str_replace( "\n", "\n  ", var_export($package, true) ).';'."\n"
					.'});';
			wp_send_json_success($code);
			exit;
		}
		
		
		
		
		/** Assemble package files **/
		
		if (self::USE_DIR) {
			$dir = wp_tempnam();
			if ( empty($dir) ) {
				wp_die( 'Unable to create temporary directory' );
			}
			unlink($dir);
			if ( !mkdir($dir, 0755) ) {
				wp_die( 'Unable to create temporary directory' );
			}
			$dir .= '/';
		}
		
		$zipFile = wp_tempnam();
		if ( empty($zipFile) ) {
			wp_die( 'Unable to create temporary file' );
		}
		
		$zip = new ZipArchive();
		if ( !$zip->open($zipFile, ZipArchive::OVERWRITE) ) {
			wp_die( 'Unable to create zip file' );
		}
		
		$files = file_get_contents(AGSLayouts::$pluginDirectory.'packager-files.txt');
		if ( empty($files) ) {
			wp_die('Unable to read files list');
		}
		
		$files = explode("\n", $files); 
		$extraProcessingFileExtensions = array('php');
		$classNamePattern = '(AGSLayouts[a-zA-Z0-9_]*)';
		$phpReplace = array(
			array(
				'/class '.$classNamePattern.'/',
				'/new '.$classNamePattern.'/',
				'/'.$classNamePattern.'\\:\\:/',
				'/\''.$classNamePattern.'\'/',
				'/"'.$classNamePattern.'"/',
				'/(ags_layouts_[a-zA-Z0-9_]*\\s*\\()/', // function definitions and calls
			),
			array(
				'class '.$package['classPrefix'].'$1',
				'new '.$package['classPrefix'].'$1',
				$package['classPrefix'].'$1::',
				'\''.$package['classPrefix'].'$1\'',
				'"'.$package['classPrefix'].'$1"',
				$package['classPrefix'].'$1',
			)
		);
		
		foreach ($files as $file) {
			$file = trim($file);
			if ( empty($file) ) {
				continue;
			}
			
			$origFile = AGSLayouts::$pluginDirectory.$file;
			$newFile = $dir.$file;
			if ( !is_file($origFile) ) {
				wp_die( 'Missing file: '.esc_html($origFile) );
			}
			
			$pathinfo = pathinfo($newFile);
			
			if (self::USE_DIR) {
				if ( !is_dir( $pathinfo['dirname'] ) && !mkdir($pathinfo['dirname'], 0755, true) ) {
					wp_die( 'Unable to create directory: '.esc_html($pathinfo['dirname']) );
				}
			}
			
			if ( empty($pathinfo['extension']) || !in_array($pathinfo['extension'], $extraProcessingFileExtensions) ) {
				if ( self::USE_DIR && !copy($origFile, $newFile) ) {
					wp_die( 'Unable to copy file: '.esc_html($origFile) );
				}
				if ( !$zip->addFile($origFile, $file) ) {
					wp_die( 'Unable to add file to zip: '.esc_html($origFile) );
				}
			} else {
				switch ($pathinfo['extension']) {
					case 'php':
						$fileContents = file_get_contents( $origFile );
						$fileContents = preg_replace($phpReplace[0], $phpReplace[1], $fileContents);
						
						if ( $pathinfo['basename'] == 'ags-layouts.php' ) {
							
							$isPackagedConst = 'const IS_PACKAGED_LAYOUT = false;';
							$isPackagedConstStart = strpos($fileContents, $isPackagedConst);
							$isPackagedIf = 'if (self::IS_PACKAGED_LAYOUT) {';
							$isPackagedIfStart = strpos($fileContents, $isPackagedIf);
							
							if ( !$isPackagedConstStart || !$isPackagedIfStart) {
								wp_die('Error while adding configuration data');
							}
							
							$i18nStart = '#WPL_I18N_START#';
							$i18nStartEsc = '#WPL_I18N_START_ESC#';
							$i18nStartEscConcat = '#WPL_I18N_START_ESC_CONCAT#';
							$i18nEnd = '#WPL_I18N_END#';
							$i18nEndConcat = '#WPL_I18N_END_CONCAT#';
							
							foreach ( $package['layouts'] as &$layout ) {
								$layout['name'] = $i18nStart.trim($layout['name']).$i18nEnd;
							}
							
							foreach ( $keys_i18n_text as $key ) {
								$package[$key] = $i18nStart.trim($package[$key]).$i18nEnd;
							}
							
							foreach ( $keys_i18n_html as $key ) {
								$package[$key] = html_entity_decode( trim($package[$key]) );
								$fieldSub = substr($package[$key], 1, -1);
								$fieldLastIndex = strlen($package[$key]) - 1;
								$fieldLastChar = $package[$key][$fieldLastIndex];
								$package[$key] = 
									$package[$key][0]
									.str_replace(
										array('<', '>'),
										array($i18nEndConcat.'<', '>'.$i18nStartEscConcat),
										$fieldSub
									)
									.$package[$key][$fieldLastIndex];
								
								if ( $package[$key][0] != '<' ) {
									$package[$key] = $i18nStartEsc.$package[$key];
								}
								
								if ( $fieldLastChar != '>' ) {
									$package[$key] = $package[$key].$i18nEnd;
								}
							}
							
							$textDomain = var_export($package['textDomain'], true);
							unset($package['textDomain']);
							$config = var_export($package, true);
							
							$config = str_replace(
								array('\''.$i18nStart, '\''.$i18nStartEsc, $i18nStartEscConcat, $i18nEnd.'\'', $i18nEndConcat),
								array( '__(\'', 'esc_html__(\'', '\'.esc_html__(\'', '\','.$textDomain.')', '\','.$textDomain.').\'' ),
								$config
							);
							
							$isPackagedConstEnd = $isPackagedConstStart + strlen($isPackagedConst);
							$isPackagedIfEnd = $isPackagedIfStart + strlen($isPackagedIf);
							
							$fileContents =
								substr($fileContents, 0, $isPackagedConstStart)
								.'const IS_PACKAGED_LAYOUT = true;'."\n"
								.'private static $PACKAGED_LAYOUT_CONFIG;'
								.substr($fileContents, $isPackagedConstEnd, $isPackagedIfEnd - $isPackagedConstEnd )
								."\n".'self::$PACKAGED_LAYOUT_CONFIG = '.$config.';'
								.substr($fileContents, $isPackagedIfEnd );
							$file = substr($file, 0, -15).'importer.php';
							if ( self::USE_DIR ) {
								$newFile = substr($newFile, 0, -15).'importer.php';
							}
						}
						
						
						if ( self::USE_DIR && !file_put_contents($newFile, $fileContents) ) {
							wp_die( 'Unable to copy file: '.esc_html($origFile) );
						}
						
						if ( !$zip->addFromString($file, $fileContents) ) {
							wp_die( 'Unable to add file to zip: '.esc_html($origFile) );
						}
						break;
				}
			}
			
		}
		
		$notice = 'The files in this directory and subdirectories were generated by WP Layouts (https://wplayouts.space/). WP Layouts is licensed under GPLv3 or later and includes code from third-party sources. See comments in importer.php, other code files, the ./license directory, and the ./includes/site-import/license directory for more details.

These files were generated on '.current_time('Y-m-d').'. This notice is provided in addition to any modification dates and names in individual files, which reflect work done on WP Layouts itself but not the importer generation process.
';
		
		if ( !$zip->addFromString('notice.txt', $notice) ) {
			wp_die( 'Unable to add file to zip: '.esc_html($origFile) );
		}
		
		/** Create and output the zip file **/
		
		
		if ( !$zip->close() ) {
			wp_die( 'Unable to close zip file: '.esc_html( $zip->getStatusString() ) );
		}
		
		// Following code copied from WordPress and modified - wp-admin\includes\export.php. See license.txt in the WP Layouts plugin root directory for copyright and license.
		header( 'Content-Description: File Transfer' );
		header( 'Content-Disposition: attachment; filename=wpl-export.zip' );
		header( 'Content-Type: application/zip', true );
		// End code copied from WordPress
		
		readfile($zipFile);
		
		unlink($zipFile);
		
		exit;
		
	}
	
}
AGSLayoutsPackager::run();