<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\FileSystem;
use WpAssetCleanUp\Main;
use WpAssetCleanUp\Menu;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;
use WpAssetCleanUp\Settings;

/**
 * Class ImportExport
 * @package WpAssetCleanUp
 */
class ImportExport
{
	/***** BEGIN EXPORT ******/
    /**
     * @return array
     */
    public static function getCriticalCssOptionsArray()
    {
        global $wpdb;

        $criticalCssOptionsArray = array();

        $likeCssQuery                      = WPACU_PLUGIN_ID . '_critical_css_%';
        $sqlFetchAnyCriticalCssOptionNames = <<<SQL
SELECT option_name FROM `{$wpdb->prefix}options` WHERE option_name LIKE '{$likeCssQuery}'
SQL;
        $allCriticalCssOptionNames         = $wpdb->get_col($sqlFetchAnyCriticalCssOptionNames);

        if ( ! empty($allCriticalCssOptionNames)) {
            foreach ($allCriticalCssOptionNames as $criticalCssOptionName) {
                $criticalCssOptionsArray[$criticalCssOptionName] = get_option($criticalCssOptionName);
            }
        }

        return $criticalCssOptionsArray;
    }

    /**
	 * @return string
	 */
	public function jsonSettings()
	{
		$wpacuSettings = new Settings();
		$settingsArray = $wpacuSettings->getAll();

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
		if (! Menu::userCanAccessAssetCleanUp()) {
			return;
		}

		if (! Misc::getVar('post', 'wpacu_do_export_nonce')) {
			return;
		}

		$wpacuExportFor = Misc::getVar('post', 'wpacu_export_for');

		if (! $wpacuExportFor) {
			return;
		}

		// Last important check
		\check_admin_referer('wpacu_do_export', 'wpacu_do_export_nonce');

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
				'__comment' => $exportComment,
				'critical_css_options' => $criticalCssOptionsArray
			);
		}

		if ($wpacuExportFor === 'everything') {
			$exportComment = str_replace('[exported_text]', 'Everything', $exportComment);

			// "Settings"
			$settingsJson = $this->jsonSettings();

			// "Homepage"
			$frontPageNoLoad      = get_option(WPACU_PLUGIN_ID . '_front_page_no_load');
			$frontPageNoLoadArray = json_decode($frontPageNoLoad, ARRAY_A);

			$frontPageExceptionsListJson  = get_option(WPACU_PLUGIN_ID . '_front_page_load_exceptions');
			$frontPageExceptionsListArray = json_decode($frontPageExceptionsListJson, ARRAY_A);

			// "Site-wide" Unloads
			$globalUnloadListJson = get_option(WPACU_PLUGIN_ID . '_global_unload');
			$globalUnloadArray    = json_decode($globalUnloadListJson, ARRAY_A);

			// "Bulk" unloads (for all pages, posts, custom post type)
			$bulkUnloadListJson = get_option(WPACU_PLUGIN_ID . '_bulk_unload');
			$bulkUnloadArray    = json_decode($bulkUnloadListJson, ARRAY_A);

			// Post type: load exceptions
			$postTypeLoadExceptionsJson  = get_option(WPACU_PLUGIN_ID . '_post_type_load_exceptions');
			$postTypeLoadExceptionsArray = json_decode($postTypeLoadExceptionsJson, ARRAY_A);

			$globalDataListArray = wpacuGetGlobalData();
			global $wpdb;

			$allMetaResults = array();

			$metaKeyLike = '_' . WPACU_PLUGIN_ID . '_%';

			$tableList = array($wpdb->postmeta);

			foreach ($tableList as $tableName) {
				if ( $tableName === $wpdb->postmeta ) {
					$sqlFetchPostsMetas         = <<<SQL
SELECT post_id, meta_key, meta_value FROM `{$wpdb->postmeta}` WHERE meta_key LIKE '{$metaKeyLike}'
SQL;
					$allMetaResults['postmeta'] = $wpdb->get_results( $sqlFetchPostsMetas, ARRAY_A );
				}
			}

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

            $valuesArray['critical_css_options']         = self::getCriticalCssOptionsArray();
		}

        if (empty($valuesArray)) {
            // It has to be filled, otherwise the wrong parameters might have been set
            exit();
        }

		// Was the right selection made? Continue
		$date = date('j-M-Y-H.i');
		$host = parse_url(site_url(), PHP_URL_HOST);

		$wpacuExportForPartOfFileName = str_replace('_', '-', $wpacuExportFor);

		header('Content-Type: application/json');
		header('Content-Disposition: attachment; filename="asset-cleanup-lite-exported-'.$wpacuExportForPartOfFileName.'-from-'.$host.'-'.$date.'.json"');

		echo wp_json_encode($valuesArray);
		exit();
	}
	/***** END EXPORT ******/

	/***** BEGIN IMPORT ******/
	/**
	 *
	 */
	public function doImport()
	{
		if (! Menu::userCanAccessAssetCleanUp()) {
			return;
		}

		if (! Misc::getVar('post', 'wpacu_do_import_nonce')) {
			return;
		}

		$jsonTmpName = isset($_FILES['wpacu_import_file']['tmp_name']) ? $_FILES['wpacu_import_file']['tmp_name'] : false;

		if (! $jsonTmpName) {
			return;
		}

		// Last important check
		\check_admin_referer('wpacu_do_import', 'wpacu_do_import_nonce');

		if (! is_file($jsonTmpName)) {
			return;
		}

		$valuesJson = FileSystem::fileGetContents($jsonTmpName);

		$valuesArray = json_decode($valuesJson, ARRAY_A);

		if ( ! (JSON_ERROR_NONE === wpacuJsonLastError())) {
			return;
		}

		$importedList = array();

		// NOTE: The values are not replaced, but added to the existing ones (if any)

		// "Settings" (Replace)
		if ( ! empty($valuesArray['settings']) ) {
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
		if (isset($valuesArray['homepage']['unloads']['scripts'])
		    || isset($valuesArray['homepage']['unloads']['styles'])) {
			Misc::addUpdateOption(WPACU_PLUGIN_ID . '_front_page_no_load', wp_json_encode($valuesArray['homepage']['unloads']));
			$importedList[] = 'homepage_unloads';
		}

		// "Homepage" Load Exceptions
		if (isset($valuesArray['homepage']['load_exceptions']['scripts'])
		    || isset($valuesArray['homepage']['load_exceptions']['styles'])) {
			Misc::addUpdateOption(WPACU_PLUGIN_ID . '_front_page_load_exceptions', wp_json_encode($valuesArray['homepage']['load_exceptions']));
			$importedList[] = 'homepage_exceptions';
		}

		// "Site-Wide" (Everywhere) Unloads
		if (isset($valuesArray['global_unload']['scripts'])
		    || isset($valuesArray['global_unload']['styles'])) {
			Misc::addUpdateOption(WPACU_PLUGIN_ID . '_global_unload', wp_json_encode($valuesArray['global_unload']));
			$importedList[] = 'sitewide_unloads';
		}

		// Bulk Unloads (e.g. Unload on all pages of product post type)
		if (isset($valuesArray['bulk_unload']['scripts'])
		    || isset($valuesArray['bulk_unload']['styles'])) {
			Misc::addUpdateOption(WPACU_PLUGIN_ID . '_bulk_unload', wp_json_encode($valuesArray['bulk_unload']));
			$importedList[] = 'bulk_unload';
		}

		// Post type: load exception
		if ( ! empty($valuesArray['post_type_exceptions']) ) {
			Misc::addUpdateOption(WPACU_PLUGIN_ID . '_post_type_load_exceptions', wp_json_encode($valuesArray['post_type_exceptions']));
			$importedList[] = 'post_type_load_exceptions';
		}

		// Global Data
		if (isset($valuesArray['global_data']['scripts'])
		    || isset($valuesArray['global_data']['styles'])) {
			Misc::addUpdateOption(WPACU_PLUGIN_ID . '_global_data', wp_json_encode($valuesArray['global_data']));
			$importedList[] = 'global_data';
		}

		// [START] All Posts Metas (per page unloads, load exceptions, page options from side meta box)
		$targetKey = 'posts_metas';

		if ( ! empty($valuesArray[$targetKey]) ) {
			foreach ($valuesArray[$targetKey] as $metaValues) {
				// It needs to have a post ID and meta key starting with _' . WPACU_PLUGIN_ID . '
				if ( ! (isset($metaValues['post_id'], $metaValues['meta_key'])
					&& strpos($metaValues['meta_key'], '_' . WPACU_PLUGIN_ID) === 0) ) {
					continue;
				}

				$postId    = $metaValues['post_id'];
				$metaKey   = $metaValues['meta_key'];
				$metaValue = $metaValues['meta_value']; // already JSON encoded

				if (! add_post_meta($postId, $metaKey, $metaValue, true)) {
					update_post_meta($postId, $metaKey, $metaValue);
				}
			}

			$importedList[] = 'posts_metas';
		}
		// [END] All Posts Metas (per page unloads, load exceptions, page options from side meta box)

        if ( ! empty( $valuesArray['critical_css_options'] ) ) {
            foreach ( $valuesArray['critical_css_options'] as $optionName => $optionValue ) {
                if ( strpos( $optionName, WPACU_PLUGIN_ID . '_critical_css_' ) === 0 ) {
                    Misc::addUpdateOption( $optionName, $optionValue );
                }
            }

            $importedList[] = 'critical_css_options';
        }

		if (! empty($importedList)) {
			// After import was completed, clear all CSS/JS cache
			OptimizeCommon::clearCache();

			set_transient(WPACU_PLUGIN_ID . '_import_done', $importedList, 30);

			wp_redirect(admin_url('admin.php?page=wpassetcleanup_tools&wpacu_for=import_export&wpacu_import_done=1&wpacu_time=' . time()));
			exit();
		}
	}
	/***** END IMPORT ******/
}
