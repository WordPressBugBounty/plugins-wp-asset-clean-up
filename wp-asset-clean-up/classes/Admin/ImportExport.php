<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\FileSystem;
use WpAssetCleanUp\Main;
use WpAssetCleanUp\Menu;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\OptimiseAssets\CriticalCss;
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;
use WpAssetCleanUp\Settings;

/**
 * Class ImportExport
 * @package WpAssetCleanUp
 */
class ImportExport
{
	const MAX_IMPORT_FILE_BYTES = 20971520; // 20 MiB

	/**
	 * Keep imports within both Asset CleanUp's own safety limit and the
	 * upload limit configured for the current WordPress installation.
	 *
	 * @return int
	 */
	public static function getMaxImportFileBytes()
	{
		$maxImportFileBytes = (int) self::MAX_IMPORT_FILE_BYTES;

		if (function_exists('wp_max_upload_size')) {
			$wpMaxUploadSize = (int) wp_max_upload_size();

			if ($wpMaxUploadSize > 0) {
				$maxImportFileBytes = min($maxImportFileBytes, $wpMaxUploadSize);
			}
		}

		return max(1, $maxImportFileBytes);
	}

    /**
     * Validate structures that are consumed as arrays at runtime. Imports are
     * validated before any option is changed so a malformed file is atomic.
     *
     * @param array $valuesArray
     * @return string Empty when valid; otherwise the translated error message.
     */
    private static function validateRuntimeArrayStructures($valuesArray)
    {
        if (array_key_exists('settings', $valuesArray)) {
            if ( ! is_array($valuesArray['settings'])) {
                return __('The Settings section in the import file is not valid.', 'wp-asset-clean-up');
            }

            foreach (array('resource_loading', 'announcements', 'do_not_load_plugin_features') as $settingsArrayKey) {
                if (array_key_exists($settingsArrayKey, $valuesArray['settings'])
                    && ! is_array($valuesArray['settings'][$settingsArrayKey])) {
                    return __('The Settings section in the import file contains an invalid nested value.', 'wp-asset-clean-up');
                }
            }
        }

        if (array_key_exists('global_data', $valuesArray)) {
            if ( ! is_array($valuesArray['global_data'])) {
                return __('The global rules section in the import file is not valid.', 'wp-asset-clean-up');
            }

            foreach ($valuesArray['global_data'] as $globalDataKey => $globalDataValue) {
                if ( ! is_string($globalDataKey) || ! is_array($globalDataValue)) {
                    return __('The global rules section in the import file contains an invalid value.', 'wp-asset-clean-up');
                }
            }

            foreach (array('styles', 'scripts') as $assetType) {
                if (empty($valuesArray['global_data'][$assetType])) {
                    continue;
                }

                foreach ($valuesArray['global_data'][$assetType] as $ruleType => $rules) {
                    if ( ! is_string($ruleType) || ! is_array($rules)) {
                        return __('The global asset rules in the import file are not valid.', 'wp-asset-clean-up');
                    }
                }
            }
        }

        if (array_key_exists('critical_css_options', $valuesArray)) {
            if ( ! is_array($valuesArray['critical_css_options'])) {
                return __('The Critical CSS options section in the import file is not valid.', 'wp-asset-clean-up');
            }

            foreach ($valuesArray['critical_css_options'] as $optionName => $optionValue) {
                if ( ! is_string($optionName)
                    || strpos($optionName, WPACU_PLUGIN_ID . '_critical_css_') !== 0
                    || ! (is_scalar($optionValue) || $optionValue === null)) {
                    return __('The Critical CSS options section in the import file contains an invalid value.', 'wp-asset-clean-up');
                }
            }
        }

        return '';
    }

	/***** BEGIN EXPORT ******/
    /**
     * Get the saved Plugins Manager rules for one location.
     *
     * @param string $locationKey Either "plugins" or "plugins_dash".
     * @return array
     */
    public static function getPluginsManagerRulesArray($locationKey)
    {
        if ( ! in_array($locationKey, array('plugins', 'plugins_dash'), true)) {
            return array();
        }

        $globalData = wpacuGetGlobalData();

        return ! empty($globalData[$locationKey]) && is_array($globalData[$locationKey])
            ? $globalData[$locationKey]
            : array();
    }

    /**
     * @return array
     */
    public static function getCriticalCssOptionsArray()
    {
        global $wpdb;

        $criticalCssOptionsArray = array();

        $criticalCssOptionNameLike = $wpdb->esc_like(WPACU_PLUGIN_ID . '_critical_css_') . '%';
        $allCriticalCssOptionNames = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM `{$wpdb->options}` WHERE option_name LIKE %s",
                $criticalCssOptionNameLike
            )
        );

        if ( ! empty($allCriticalCssOptionNames)) {
            foreach ($allCriticalCssOptionNames as $criticalCssOptionName) {
                $criticalCssOptionsArray[$criticalCssOptionName] = get_option($criticalCssOptionName);
            }
        }

        return $criticalCssOptionsArray;
    }

    /**
     * Fetch only the object metadata used by granular Critical CSS.
     *
     * @return array
     */
    public static function getCriticalCssMetasArray()
    {
        global $wpdb;

        $metaKey = CriticalCss::getMetaKey();

        return array(
            'postmeta' => $wpdb->get_results(
                $wpdb->prepare("SELECT post_id, meta_key, meta_value FROM `{$wpdb->postmeta}` WHERE meta_key=%s", $metaKey),
                ARRAY_A
            ),
            'termmeta' => $wpdb->get_results(
                $wpdb->prepare("SELECT term_id, meta_key, meta_value FROM `{$wpdb->termmeta}` WHERE meta_key=%s", $metaKey),
                ARRAY_A
            ),
            'usermeta' => $wpdb->get_results(
                $wpdb->prepare("SELECT user_id, meta_key, meta_value FROM `{$wpdb->usermeta}` WHERE meta_key=%s", $metaKey),
                ARRAY_A
            )
        );
    }

    /**
     * Export the Critical CSS setting that lives in the main settings option.
     * The standalone Critical CSS export should restore the feature status as
     * well as its general and granular rules.
     *
     * @return array
     */
    public static function getCriticalCssSettingsArray()
    {
        $settingsOption = get_option(WPACU_PLUGIN_ID . '_settings');
        $settings = array();

        if (is_string($settingsOption) && $settingsOption !== '') {
            $decodedSettings = json_decode($settingsOption, ARRAY_A);

            if (is_array($decodedSettings) && wpacuJsonLastError() === JSON_ERROR_NONE) {
                $settings = $decodedSettings;
            }
        }

        if (empty($settings)) {
            $settings = (new Settings())->getAll();
        }

        return array(
            'critical_css_status' => isset($settings['critical_css_status'])
                ? $settings['critical_css_status']
                : 'off'
        );
    }

    /**
	 * @return string
	 */
	public function jsonSettings()
	{
		$wpacuSettings = new Settings();
		$settingsArray = $wpacuSettings->getAll();

		// Older installations can store this empty list as a scalar value. Keep new
		// exports self-compatible and use the runtime type expected by the importer.
		if ( ! isset($settingsArray['do_not_load_plugin_features'])
			|| ! is_array($settingsArray['do_not_load_plugin_features'])) {
			$settingsArray['do_not_load_plugin_features'] = array();
		}

		// Some "Site-wide Common Unloads" values are fetched outside the "Settings" option values
		// e.g., jQuery Migrate, Comment Reply
		$globalUnloadList = Main::instance()->getGlobalUnload();

		// CSS
		$settingsArray['disable_dashicons_for_guests'] = in_array( 'dashicons',        $globalUnloadList['styles'] );
		$settingsArray['disable_wp_block_library']     = in_array( 'wp-block-library', $globalUnloadList['styles'] );

		// JS
		$settingsArray['disable_jquery_migrate'] = in_array( 'jquery-migrate',   $globalUnloadList['scripts'] );
		$settingsArray['disable_comment_reply']  = in_array( 'comment-reply',    $globalUnloadList['scripts'] );

		return wp_json_encode($settingsArray);
	}

	/**
	 * Was the "Export" button clicked? Do verifications and send the right headers
	 */
	public function doExport()
	{
		if (! Menu::userCanAccessPlugin()) {
			return;
		}

		if (! Misc::getVar('post', 'wpacu_do_export_nonce')) {
			return;
		}

		$wpacuExportForRaw = Misc::getVar('post', 'wpacu_export_for');
		$wpacuExportFor = is_string($wpacuExportForRaw)
			? sanitize_key(wp_unslash($wpacuExportForRaw))
			: '';
		$allowedExportTypes = array(
			'settings',
			'critical_css',
			'plugins_manager_frontend',
			'plugins_manager_dashboard',
			'everything'
		);

		if (! in_array($wpacuExportFor, $allowedExportTypes, true)) {
			return;
		}

		// Last important check
		\check_admin_referer('wpacu_do_export', 'wpacu_do_export_nonce');

		$valuesArray = array();
		$exportComment = 'Exported [exported_text] via '.WPACU_PLUGIN_TITLE.' (v'.WPACU_PLUGIN_VERSION.') - Timestamp: '.time();

		// "Settings" values (could be just default ones if none are found in the database)
		if ($wpacuExportFor === 'settings') {
			$exportComment = str_replace('[exported_text]', 'Settings', $exportComment);

			$settingsJson = $this->jsonSettings();

			$valuesArray = array(
				'__comment' => $exportComment,
				'settings'  => json_decode($settingsJson, ARRAY_A)
			);
		}

		if ($wpacuExportFor === 'critical_css') {
			$exportComment = str_replace('[exported_text]', 'Critical CSS', $exportComment);

			$criticalCssOptionsArray = self::getCriticalCssOptionsArray();

			$valuesArray = array(
				'__comment'             => $exportComment,
				'critical_css_settings' => self::getCriticalCssSettingsArray(),
				'critical_css_options'  => $criticalCssOptionsArray,
				'critical_css_metas'    => self::getCriticalCssMetasArray()
			);
		}

        if (in_array($wpacuExportFor, array('plugins_manager_frontend', 'plugins_manager_dashboard'), true)) {
            $isDashboardExport = $wpacuExportFor === 'plugins_manager_dashboard';
            $locationKey       = $isDashboardExport ? 'plugins_dash' : 'plugins';
            $locationLabel     = $isDashboardExport ? 'Dashboard rules' : 'Front-end rules';
            $rules             = self::getPluginsManagerRulesArray($locationKey);

            if ( ! empty($rules)) {
                $exportComment = str_replace('[exported_text]', 'Plugins Manager ' . $locationLabel, $exportComment);
                $valuesArray = array(
                    '__comment'       => $exportComment,
                    'plugins_manager' => array(
                        'location' => $isDashboardExport ? 'dashboard' : 'frontend',
                        'rules'    => $rules
                    )
                );
            }
        }

		if ($wpacuExportFor === 'everything') {
			$exportComment = str_replace('[exported_text]', 'Everything', $exportComment);

			// "Settings"
			$settingsJson = $this->jsonSettings();

			// "Homepage"
			$frontPageNoLoad      = get_option(WPACU_PLUGIN_ID . '_front_page_no_load');
			$frontPageNoLoadArray = wpacuJsonDecodeToArray($frontPageNoLoad);

			$frontPageExceptionsListJson  = get_option(WPACU_PLUGIN_ID . '_front_page_load_exceptions');
			$frontPageExceptionsListArray = wpacuJsonDecodeToArray($frontPageExceptionsListJson);

			// "Site-wide" Unloads
			$globalUnloadListJson = get_option(WPACU_PLUGIN_ID . '_global_unload');
			$globalUnloadArray    = wpacuJsonDecodeToArray($globalUnloadListJson);

			// "Bulk" unloads (for all pages, posts, custom post type)
			$bulkUnloadListJson = get_option(WPACU_PLUGIN_ID . '_bulk_unload');
			$bulkUnloadArray    = wpacuJsonDecodeToArray($bulkUnloadListJson);

			// Post type: load exceptions
			$postTypeLoadExceptionsJson  = get_option(WPACU_PLUGIN_ID . '_post_type_load_exceptions');
			$postTypeLoadExceptionsArray = wpacuJsonDecodeToArray($postTypeLoadExceptionsJson);

			$globalDataListArray = apply_filters(
				'wpacu_internal_import_export_global_data_for_export',
				wpacuGetGlobalData()
			);

			global $wpdb;

			$allMetaResults = array('postmeta' => array());

			$metaKeyLike       = $wpdb->esc_like('_' . WPACU_PLUGIN_ID . '_') . '%';
			$criticalCssMetaKey = CriticalCss::getMetaKey();

			$tableList = apply_filters('wpacu_internal_import_export_export_table_list', array($wpdb->postmeta), $wpdb);

			foreach ($tableList as $tableName) {
				if ( $tableName === $wpdb->postmeta ) {
					$allMetaResults['postmeta'] = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT post_id, meta_key, meta_value
							 FROM `{$wpdb->postmeta}`
							 WHERE meta_key LIKE %s
							   AND meta_key <> %s",
							$metaKeyLike,
							$criticalCssMetaKey
						),
						ARRAY_A
					);
				}
			}

			$allMetaResults = apply_filters('wpacu_internal_import_export_export_meta_results', $allMetaResults, $tableList, $metaKeyLike);

			// Export Field Names should be kept as they are and in case
			// they are changed later on, a fallback should be in place
            $valuesArray = array(
                '__comment' => $exportComment,
                'settings'  => json_decode($settingsJson, ARRAY_A),

                'homepage' => array(
                    'unloads'         => $frontPageNoLoadArray,
                    'load_exceptions' => $frontPageExceptionsListArray
                ),

                'global_unload' => $globalUnloadArray,
                'bulk_unload'   => $bulkUnloadArray,

                'post_type_exceptions' => $postTypeLoadExceptionsArray,

                'global_data' => $globalDataListArray,

                'posts_metas' => $allMetaResults['postmeta']
            );

            $valuesArray['critical_css_options'] = self::getCriticalCssOptionsArray();
            $valuesArray['critical_css_metas']   = self::getCriticalCssMetasArray();

            $valuesArray = apply_filters('wpacu_internal_import_export_export_values', $valuesArray, $allMetaResults);
		}

        if (empty($valuesArray)) {
            // It has to be filled, otherwise the wrong parameters might have been set
            exit();
        }

		// Was the right selection made? Continue
		$date = gmdate('j-M-Y-H.i');
		$host = sanitize_file_name((string) wp_parse_url(site_url(), PHP_URL_HOST));

		if ($host === '') {
			$host = 'site';
		}

		$wpacuExportForPartOfFileName = str_replace('_', '-', $wpacuExportFor);

        $wpacuPluginSlugForFileName = self::getPluginSlugForExportFileName();

		$exportFileName = sanitize_file_name($wpacuPluginSlugForFileName .
            '-exported-' . $wpacuExportForPartOfFileName . '-from-' . $host . '-' . $date .
			'.json');

		$encodedValues = wp_json_encode($valuesArray);

		if (! is_string($encodedValues) || $encodedValues === '') {
			wp_die(
				esc_html__('The configuration could not be encoded as JSON. No export file was generated.', 'wp-asset-clean-up'),
				'',
				array('response' => 500)
			);
		}

		nocache_headers();
		header('Content-Type: application/json; charset=UTF-8');
		header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="'.$exportFileName.'"');

		echo $encodedValues;
		exit();
	}
	/***** END EXPORT ******/

	/***** BEGIN IMPORT ******/
	/**
	 *
	 */
	public function doImport()
	{
		if (! Menu::userCanAccessPlugin()) {
			return;
		}

		if (! Misc::getVar('post', 'wpacu_do_import_nonce')) {
			return;
		}

		// Verify the request before inspecting or reading the uploaded file.
		\check_admin_referer('wpacu_do_import', 'wpacu_do_import_nonce');
		$maxImportFileBytes = self::getMaxImportFileBytes();

		$uploadedFile = isset($_FILES['wpacu_import_file']) && is_array($_FILES['wpacu_import_file'])
			? $_FILES['wpacu_import_file']
			: array();
		$uploadError = isset($uploadedFile['error']) ? (int) $uploadedFile['error'] : UPLOAD_ERR_NO_FILE;
		$jsonTmpName = isset($uploadedFile['tmp_name']) && is_string($uploadedFile['tmp_name'])
			? $uploadedFile['tmp_name']
			: '';
		$jsonFileSize = isset($uploadedFile['size']) ? (int) $uploadedFile['size'] : 0;

		if ($uploadError === UPLOAD_ERR_NO_FILE) {
			$this->redirectAfterImportError(__('No import file was selected.', 'wp-asset-clean-up'));
		}

		if (in_array($uploadError, array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)) {
			$this->redirectAfterImportError(sprintf(
				__('The import file is larger than the allowed limit of %s.', 'wp-asset-clean-up'),
				size_format($maxImportFileBytes)
			));
		}

		if ($uploadError !== UPLOAD_ERR_OK) {
			$this->redirectAfterImportError(__('The import file could not be uploaded. Please try again.', 'wp-asset-clean-up'));
		}

		if ($jsonTmpName === '' || ! is_file($jsonTmpName) || ! is_readable($jsonTmpName)) {
			$this->redirectAfterImportError(__('The uploaded import file could not be read.', 'wp-asset-clean-up'));
		}

		$actualJsonFileSize = filesize($jsonTmpName);

		if ($actualJsonFileSize === false) {
			$this->redirectAfterImportError(__('The uploaded import file size could not be determined.', 'wp-asset-clean-up'));
		}

		// Do not trust the multipart metadata alone. Check the actual temporary file
		// before reading it into memory and use the larger reported value.
		$jsonFileSize = max($jsonFileSize, (int) $actualJsonFileSize);

		if ($jsonFileSize < 1) {
			$this->redirectAfterImportError(__('The selected import file is empty.', 'wp-asset-clean-up'));
		}

		if ($jsonFileSize > $maxImportFileBytes) {
			$this->redirectAfterImportError(sprintf(
				__('The import file is larger than the allowed limit of %s.', 'wp-asset-clean-up'),
				size_format($maxImportFileBytes)
			));
		}

		$valuesJson = FileSystem::fileGetContents($jsonTmpName);

		if (! is_string($valuesJson) || $valuesJson === '') {
			$this->redirectAfterImportError(__('The uploaded import file could not be read.', 'wp-asset-clean-up'));
		}

		if (strlen($valuesJson) > $maxImportFileBytes) {
			$this->redirectAfterImportError(sprintf(
				__('The import file is larger than the allowed limit of %s.', 'wp-asset-clean-up'),
				size_format($maxImportFileBytes)
			));
		}

		$valuesArray = json_decode($valuesJson, ARRAY_A);

		if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
			$this->redirectAfterImportError(__('The selected file does not contain valid JSON.', 'wp-asset-clean-up'));
		}

		if (! is_array($valuesArray)) {
			$this->redirectAfterImportError(__('The import file format is not supported.', 'wp-asset-clean-up'));
		}

		$pluginsManagerImport = null;

		// Backward compatibility: older exports can contain an empty scalar for a
		// setting that is consumed as an array. Normalise only known empty legacy
		// representations; non-empty malformed values must still fail validation.
		if (isset($valuesArray['settings']) && is_array($valuesArray['settings'])
			&& array_key_exists('do_not_load_plugin_features', $valuesArray['settings'])
			&& in_array($valuesArray['settings']['do_not_load_plugin_features'], array('', null, false), true)) {
			$valuesArray['settings']['do_not_load_plugin_features'] = array();
		}

		if (array_key_exists('plugins_manager', $valuesArray)) {
			$pluginsManagerSection = $valuesArray['plugins_manager'];
			$locationMap = array(
				'frontend'  => 'plugins',
				'dashboard' => 'plugins_dash'
			);

			if (! is_array($pluginsManagerSection)
				|| ! array_key_exists('rules', $pluginsManagerSection)
				|| ! is_array($pluginsManagerSection['rules'])
				|| ! isset($pluginsManagerSection['location'])
				|| ! is_scalar($pluginsManagerSection['location'])) {
				$this->redirectAfterImportError(__('The Plugins Manager section in the import file is not valid.', 'wp-asset-clean-up'));
			}

			$importLocation = sanitize_key((string) $pluginsManagerSection['location']);

			if (! isset($locationMap[$importLocation])) {
				$this->redirectAfterImportError(__('The Plugins Manager import location is not valid.', 'wp-asset-clean-up'));
			}

			foreach ($pluginsManagerSection['rules'] as $pluginFile => $pluginRules) {
				if (! is_string($pluginFile) || trim($pluginFile) === '' || strpos($pluginFile, "\0") !== false || ! is_array($pluginRules)) {
					$this->redirectAfterImportError(__('The Plugins Manager rules in the import file are not valid.', 'wp-asset-clean-up'));
				}
			}

			$pluginsManagerImport = array(
				'location'     => $importLocation,
				'location_key' => $locationMap[$importLocation],
				'rules'        => $pluginsManagerSection['rules']
			);
		}

        $structureValidationError = self::validateRuntimeArrayStructures($valuesArray);

        if ($structureValidationError !== '') {
            $this->redirectAfterImportError($structureValidationError);
        }

		$importedList = array();

		// Option groups replace matching stored values; object metadata is upserted by object ID and meta key.

		// "Settings" (Replace)
		if (isset($valuesArray['settings']) && is_array($valuesArray['settings']) && ! empty($valuesArray['settings'])) {
			// "Site-wide Common Unloads" - apply settings

			// JS
			$disableJQueryMigrate            = isset( $valuesArray['settings']['disable_jquery_migrate'] ) ? $valuesArray['settings']['disable_jquery_migrate'] : false;
			$disableCommentReply             = isset( $valuesArray['settings']['disable_comment_reply'] ) ? $valuesArray['settings']['disable_comment_reply'] : false;

			// CSS
			$disableGutenbergCssBlockLibrary = isset( $valuesArray['settings']['disable_wp_block_library'] ) ? $valuesArray['settings']['disable_wp_block_library'] : false;
			$disableDashiconsForGuests       = isset( $valuesArray['settings']['disable_dashicons_for_guests'] ) ? $valuesArray['settings']['disable_dashicons_for_guests'] : false;

            $wpacuSettingsAdmin = new SettingsAdmin();
            $wpacuSettingsAdmin->updateSiteWideRuleForCommonAssets(
				array(
					// JS
					'jquery_migrate'   => $disableJQueryMigrate,
					'comment_reply'    => $disableCommentReply,

					// CSS
					'wp_block_library' => $disableGutenbergCssBlockLibrary,
					'dashicons'        => $disableDashiconsForGuests,
				)
			);

			Misc::addUpdateOption(WPACU_PLUGIN_ID . '_settings', wp_json_encode($valuesArray['settings']));
			$importedList[] = 'settings';
		}

		// "Homepage" Unloads
		if (isset($valuesArray['homepage']) && is_array($valuesArray['homepage'])
			&& isset($valuesArray['homepage']['unloads']) && is_array($valuesArray['homepage']['unloads'])
			&& (isset($valuesArray['homepage']['unloads']['scripts'])
		    || isset($valuesArray['homepage']['unloads']['styles']))) {
			Misc::addUpdateOption(WPACU_PLUGIN_ID . '_front_page_no_load', wp_json_encode($valuesArray['homepage']['unloads']));
			$importedList[] = 'homepage_unloads';
		}

		// "Homepage" Load Exceptions
		if (isset($valuesArray['homepage']) && is_array($valuesArray['homepage'])
			&& isset($valuesArray['homepage']['load_exceptions']) && is_array($valuesArray['homepage']['load_exceptions'])
			&& (isset($valuesArray['homepage']['load_exceptions']['scripts'])
		    || isset($valuesArray['homepage']['load_exceptions']['styles']))) {
			Misc::addUpdateOption(WPACU_PLUGIN_ID . '_front_page_load_exceptions', wp_json_encode($valuesArray['homepage']['load_exceptions']));
			$importedList[] = 'homepage_exceptions';
		}

		// "Site-Wide" (Everywhere) Unloads
		if (isset($valuesArray['global_unload']) && is_array($valuesArray['global_unload'])
			&& (isset($valuesArray['global_unload']['scripts'])
		    || isset($valuesArray['global_unload']['styles']))) {
			Misc::addUpdateOption(WPACU_PLUGIN_ID . '_global_unload', wp_json_encode($valuesArray['global_unload']));
			$importedList[] = 'sitewide_unloads';
		}

		// Bulk Unloads (e.g. Unload on all pages of product post type)
		if (isset($valuesArray['bulk_unload']) && is_array($valuesArray['bulk_unload'])
			&& (isset($valuesArray['bulk_unload']['scripts'])
		    || isset($valuesArray['bulk_unload']['styles']))) {
			Misc::addUpdateOption(WPACU_PLUGIN_ID . '_bulk_unload', wp_json_encode($valuesArray['bulk_unload']));
			$importedList[] = 'bulk_unload';
		}

		// Post type: load exception
		if ( ! empty($valuesArray['post_type_exceptions']) && is_array($valuesArray['post_type_exceptions']) ) {
			Misc::addUpdateOption(WPACU_PLUGIN_ID . '_post_type_load_exceptions', wp_json_encode($valuesArray['post_type_exceptions']));
			$importedList[] = 'post_type_load_exceptions';
		}

        $importedList = apply_filters('wpacu_internal_import_export_after_post_type_exceptions', $importedList, $valuesArray);

		// Dedicated Plugins Manager export: replace only the selected location.
		// An explicitly empty rules array is meaningful: it clears that location.
		if (is_array($pluginsManagerImport)) {
			$globalDataToImport = wpacuGetGlobalData();
			$globalDataToImport = is_array($globalDataToImport) ? $globalDataToImport : array();

			if (empty($pluginsManagerImport['rules'])) {
				unset($globalDataToImport[$pluginsManagerImport['location_key']]);
			} else {
				$globalDataToImport[$pluginsManagerImport['location_key']] = $pluginsManagerImport['rules'];
			}

			Misc::addUpdateOption(WPACU_PLUGIN_ID . '_global_data', wp_json_encode($globalDataToImport));
			$importedList[] = $pluginsManagerImport['location'] === 'dashboard'
				? 'plugins_manager_dashboard'
				: 'plugins_manager_frontend';
		}

		// Global Data (CSS/JS rules, Plugins Manager rules, preloads, attributes, etc.)
		if ( ! empty($valuesArray['global_data']) && is_array($valuesArray['global_data']) ) {
			$globalDataToImport = apply_filters(
				'wpacu_internal_import_export_global_data_for_import',
				$valuesArray['global_data'],
				$valuesArray
			);

			if ( ! is_array($globalDataToImport) ) {
				$globalDataToImport = array();
			}

			if ( ! empty($globalDataToImport) ) {
				Misc::addUpdateOption(WPACU_PLUGIN_ID . '_global_data', wp_json_encode($globalDataToImport));
				$importedList[] = 'global_data';

				$valuesArray['global_data'] = $globalDataToImport;
				$importedList = apply_filters('wpacu_internal_import_export_global_data_list', $importedList, $valuesArray);
			}
		}

		// [START] All Posts Metas (per page unloads, load exceptions, page options from side meta box)
		$targetKey = 'posts_metas';

		if ( ! empty($valuesArray[$targetKey]) && is_array($valuesArray[$targetKey]) ) {
			$criticalCssMetaKey = CriticalCss::getMetaKey();
			$hasDedicatedCriticalCssMetas = ! empty($valuesArray['critical_css_metas'])
				&& is_array($valuesArray['critical_css_metas']);

			$postsMetaWasImported = false;

			foreach ($valuesArray[$targetKey] as $metaValues) {
				// It needs to have a post ID and meta key starting with _' . WPACU_PLUGIN_ID . '
				if ( ! (is_array($metaValues)
					&& isset($metaValues['post_id'], $metaValues['meta_key'], $metaValues['meta_value'])
					&& (int) $metaValues['post_id'] > 0
					&& is_string($metaValues['meta_key'])
					&& strpos($metaValues['meta_key'], '_' . WPACU_PLUGIN_ID) === 0
					&& (is_scalar($metaValues['meta_value']) || $metaValues['meta_value'] === null)) ) {
					continue;
				}

				$postId    = (int) $metaValues['post_id'];
				$metaKey   = $metaValues['meta_key'];
				$metaValue = (string) $metaValues['meta_value']; // already JSON encoded

				// New exports keep Critical CSS metadata in its dedicated section so
				// it can be imported with the correct slashing. Older exports can have
				// it in both places; skip the generic duplicate in that situation.
				if ($hasDedicatedCriticalCssMetas && $metaKey === $criticalCssMetaKey) {
					continue;
				}

				if (! add_post_meta($postId, $metaKey, $metaValue, true)) {
					update_post_meta($postId, $metaKey, $metaValue);
				}

				$postsMetaWasImported = true;
			}

			if ($postsMetaWasImported) {
				$importedList[] = 'posts_metas';
			}
		}
		// [END] All Posts Metas (per page unloads, load exceptions, page options from side meta box)

        $criticalCssWasImported = false;

		if ( ! empty($valuesArray['critical_css_settings'])
			&& is_array($valuesArray['critical_css_settings'])
			&& isset($valuesArray['critical_css_settings']['critical_css_status']) ) {
			$criticalCssStatus = $valuesArray['critical_css_settings']['critical_css_status'] === 'on'
				? 'on'
				: 'off';
			$currentSettingsJson = get_option(WPACU_PLUGIN_ID . '_settings');
			$currentSettings = array();

			if (is_string($currentSettingsJson) && $currentSettingsJson !== '') {
				$decodedSettings = json_decode($currentSettingsJson, ARRAY_A);

				if (is_array($decodedSettings) && wpacuJsonLastError() === JSON_ERROR_NONE) {
					$currentSettings = $decodedSettings;
				}
			}

			if (empty($currentSettings)) {
				$currentSettings = (new Settings())->getAll();
			}

			$currentSettings['critical_css_status'] = $criticalCssStatus;
			Misc::addUpdateOption(WPACU_PLUGIN_ID . '_settings', wp_json_encode($currentSettings));
			$criticalCssWasImported = true;
		}

		if ( ! empty($valuesArray['critical_css_options']) && is_array($valuesArray['critical_css_options']) ) {
            foreach ($valuesArray['critical_css_options'] as $optionName => $optionValue) {
				if (is_string($optionName) && strpos($optionName, WPACU_PLUGIN_ID . '_critical_css_') === 0) {
                    Misc::addUpdateOption($optionName, $optionValue);
                    $criticalCssWasImported = true;
                }
            }
        }

        /*
         * Pro imports its general term/user metadata through this filter. Run it before
         * the dedicated Critical CSS metadata import so values containing backslashes
         * are finally saved through the correctly slashed metadata path below.
         */
        $importedList = apply_filters('wpacu_internal_import_export_after_common_metas', $importedList, $valuesArray);

        if ( ! empty($valuesArray['critical_css_metas']) && is_array($valuesArray['critical_css_metas']) ) {
            $criticalCssMetaKey = CriticalCss::getMetaKey();
            $metaGroups = array(
                'postmeta' => array('id_key' => 'post_id', 'update_callback' => 'update_post_meta'),
                'termmeta' => array('id_key' => 'term_id', 'update_callback' => 'update_term_meta'),
                'usermeta' => array('id_key' => 'user_id', 'update_callback' => 'update_user_meta')
            );

            foreach ($metaGroups as $metaGroupKey => $metaGroupConfig) {
                if (empty($valuesArray['critical_css_metas'][$metaGroupKey]) || ! is_array($valuesArray['critical_css_metas'][$metaGroupKey])) {
                    continue;
                }

				foreach ($valuesArray['critical_css_metas'][$metaGroupKey] as $metaValues) {
                    $idKey = $metaGroupConfig['id_key'];

					if ( ! (is_array($metaValues)
							&& isset($metaValues[$idKey], $metaValues['meta_key'], $metaValues['meta_value'])
                            && (int)$metaValues[$idKey] > 0
                            && $metaValues['meta_key'] === $criticalCssMetaKey
                            && is_string($metaValues['meta_value'])) ) {
                        continue;
                    }

                    call_user_func(
                        $metaGroupConfig['update_callback'],
                        (int)$metaValues[$idKey],
                        $criticalCssMetaKey,
                        wp_slash($metaValues['meta_value'])
                    );
                    $criticalCssWasImported = true;
                }
            }
        }

        if ($criticalCssWasImported) {
            $importedList[] = 'critical_css_options';
        }


		if (! empty($importedList)) {
			// After import was completed, clear all CSS/JS cache
			OptimizeCommon::clearCache();

			set_transient(WPACU_PLUGIN_ID . '_import_done', $importedList, 30);

			wp_safe_redirect(admin_url('admin.php?page=wpassetcleanup_tools&wpacu_for=import_export&wpacu_import_done=1&wpacu_time=' . time()));
			exit();
		}

		$this->redirectAfterImportError(__('No supported Asset CleanUp configuration data was found in the selected file.', 'wp-asset-clean-up'));
	}
	/***** END IMPORT ******/

	/**
	 * Store an import error briefly and return the user to the Import/Export tab.
	 *
	 * @param string $message
	 * @return void
	 */
	private function redirectAfterImportError($message)
	{
		set_transient(WPACU_PLUGIN_ID . '_import_error', (string) $message, 30);

		wp_safe_redirect(admin_url('admin.php?page=wpassetcleanup_tools&wpacu_for=import_export&wpacu_import_error=1&wpacu_time=' . time()));
		exit();
	}

    /**
     * @return string
     */
    private static function getPluginSlugForExportFileName()
    {
        $pluginExportStr = str_replace('wp-', '', WPACU_PLUGIN_SLUG);

        if (substr($pluginExportStr, -4) !== '-pro') {
            $pluginExportStr .= '-lite';
        }

        return $pluginExportStr;
    }
}
