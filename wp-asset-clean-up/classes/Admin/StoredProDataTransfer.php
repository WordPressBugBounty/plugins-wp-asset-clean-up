<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\Misc;
use WpAssetCleanUp\OptimiseAssets\CriticalCss;

/**
 * Transfer stored Pro configuration in either edition without enabling Pro features.
 */
class StoredProDataTransfer
{
    private static function optionMap()
    {
        return array(
            'front_page_data' => 'front_page_data',
            'post_type_via_tax_exceptions' => 'post_type_via_tax_load_exceptions',
            'tax_type_exceptions' => 'tax_type_load_exceptions',
            'author_type_exceptions' => 'author_type_load_exceptions',
            'extras_exceptions' => 'extras_load_exceptions'
        );
    }

    /** Validate recognized sections before any part of the import is written. */
    public static function validateImport($valuesArray)
    {
        $error = __('The stored Pro configuration in the import file is invalid. No data was imported.', 'wp-asset-clean-up');
        foreach (self::optionMap() as $key => $suffix) {
            if (array_key_exists($key, $valuesArray) && ! is_array($valuesArray[$key])
                && ! in_array($valuesArray[$key], array(null, false, ''), true)) {
                return $error;
            }
        }
        foreach (array('terms_metas' => 'term', 'users_metas' => 'user') as $key => $type) {
            if ( ! array_key_exists($key, $valuesArray) || in_array($valuesArray[$key], array(null, false, ''), true)) {
                continue;
            }
            if ( ! is_array($valuesArray[$key])) {
                return $error;
            }
            $idKey = $type . '_id';
            foreach ($valuesArray[$key] as $row) {
                if ( ! is_array($row) || ! isset($row[$idKey], $row['meta_key']) || ! array_key_exists('meta_value', $row)
                    || ! (is_int($row[$idKey]) || (is_string($row[$idKey]) && ctype_digit($row[$idKey])))
                    || (int)$row[$idKey] < 1 || ! is_string($row['meta_key'])
                    || strpos($row['meta_key'], '_' . WPACU_PLUGIN_ID . '_') !== 0
                    || strpos($row['meta_key'], "\0") !== false
                    || ! (is_scalar($row['meta_value']) || $row['meta_value'] === null)) {
                    return $error;
                }
            }
        }
        return '';
    }

    /** Labels shared by Lite and Pro confirmations. */
    public static function getImportLabels()
    {
        return array(
            'front_page_data' => __('Homepage-specific async/defer rules and their exceptions', 'wp-asset-clean-up'),
            'post_type_via_tax_exceptions' => __('Load exceptions for pages assigned to taxonomy terms', 'wp-asset-clean-up'),
            'tax_type_exceptions' => __('Load exceptions for taxonomy archives', 'wp-asset-clean-up'),
            'author_type_exceptions' => __('Load exceptions for author archives', 'wp-asset-clean-up'),
            'extras_exceptions' => __('Load exceptions for archive, search and other page types', 'wp-asset-clean-up'),
            'terms_metas' => __('Taxonomy term rules and options', 'wp-asset-clean-up'),
            'users_metas' => __('Author archive rules and options', 'wp-asset-clean-up'),
            'pro_configuration_stored' => __('Pro configuration was imported and saved for use when Asset CleanUp Pro is active.', 'wp-asset-clean-up')
        );
    }


    /**
     * @param array $tableList
     * @param object $wpdb
     *
     * @return array
     */
    public static function filterExportEverythingTableList($tableList, $wpdb)
    {
        $tableList[] = $wpdb->termmeta;
        $tableList[] = $wpdb->usermeta;

        return array_values(array_unique($tableList));
    }

    /**
     * @param array $allMetaResults
     * @param array $tableList
     * @param string $metaKeyLike
     *
     * @return array
     */
    public static function filterExportEverythingMetaResults($allMetaResults, $tableList, $metaKeyLike)
    {
        global $wpdb;

        $criticalCssMetaKey = CriticalCss::getMetaKey();

        foreach ($tableList as $tableName) {
            if ( $tableName === $wpdb->termmeta ) {
                $allMetaResults['termmeta'] = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT term_id, meta_key, meta_value
                         FROM `{$wpdb->termmeta}`
                         WHERE meta_key LIKE %s
                           AND meta_key <> %s",
                        $metaKeyLike,
                        $criticalCssMetaKey
                    ),
                    ARRAY_A
                );
            }

            if ( $tableName === $wpdb->usermeta ) {
                $allMetaResults['usermeta'] = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT user_id, meta_key, meta_value
                         FROM `{$wpdb->usermeta}`
                         WHERE meta_key LIKE %s
                           AND meta_key <> %s",
                        $metaKeyLike,
                        $criticalCssMetaKey
                    ),
                    ARRAY_A
                );
            }
        }

        return $allMetaResults;
    }

    /**
     * @param array $valuesArray
     * @param array $allMetaResults
     *
     * @return array
     */
    public static function filterExportEverythingValues($valuesArray, $allMetaResults)
    {
        // Homepage-specific script attributes and their load exceptions
        $frontPageDataJson  = get_option(WPACU_PLUGIN_ID . '_front_page_data');
        $frontPageDataArray = wpacuJsonDecodeToArray($frontPageDataJson);

        // Load exceptions for extras: is_archive(), author, search, 404 pages
        $extrasLoadExceptionsListJson = get_option(WPACU_PLUGIN_ID . '_extras_load_exceptions');
        $extrasLoadExceptionsArray    = wpacuJsonDecodeToArray($extrasLoadExceptionsListJson);

        // Load exceptions for pages that have certain taxonomies set
        $postTypeViaTaxLoadExceptionsJson  = get_option(WPACU_PLUGIN_ID . '_post_type_via_tax_load_exceptions');
        $postTypeViaTaxLoadExceptionsArray = wpacuJsonDecodeToArray($postTypeViaTaxLoadExceptionsJson);

        $taxTypeLoadExceptionsJson  = get_option(WPACU_PLUGIN_ID . '_tax_type_load_exceptions');
        $taxTypeLoadExceptionsArray = wpacuJsonDecodeToArray($taxTypeLoadExceptionsJson);

        // Load exceptions for all author archive pages
        $authorTypeLoadExceptionsJson  = get_option(WPACU_PLUGIN_ID . '_author_type_load_exceptions');
        $authorTypeLoadExceptionsArray = wpacuJsonDecodeToArray($authorTypeLoadExceptionsJson);

        $valuesArray['front_page_data']              = $frontPageDataArray;
        $valuesArray['post_type_via_tax_exceptions'] = $postTypeViaTaxLoadExceptionsArray;
        $valuesArray['tax_type_exceptions']          = $taxTypeLoadExceptionsArray;
        $valuesArray['author_type_exceptions']       = $authorTypeLoadExceptionsArray;
        $valuesArray['extras_exceptions']            = $extrasLoadExceptionsArray;
        $valuesArray['terms_metas']                  = isset($allMetaResults['termmeta']) ? $allMetaResults['termmeta'] : array();
        $valuesArray['users_metas']                  = isset($allMetaResults['usermeta']) ? $allMetaResults['usermeta'] : array();

        return $valuesArray;
    }

    /** Compatibility entry point; validation belongs to the shared importer. */
    public static function validateAlternativeAssetsImport($error, $valuesArray)
    {
        return \WpAssetCleanUp\Admin\AlternativeAssetsTransfer::validateImport($error, $valuesArray);
    }

    /**
     * @param array $importedList
     * @param array $valuesArray
     *
     * @return array
     */
    public static function filterImportAfterPostTypeExceptions($importedList, $valuesArray)
    {
        if (self::validateImport($valuesArray) !== '') { return $importedList; }
        $stored = false;
        foreach (self::optionMap() as $key => $suffix) {
            if (isset($valuesArray[$key]) && is_array($valuesArray[$key])) {
                Misc::addUpdateOption(WPACU_PLUGIN_ID . '_' . $suffix, wp_json_encode($valuesArray[$key]));
                $importedList[] = $key;
                $stored = $stored || ! empty($valuesArray[$key]);
            }
        }
        if ($stored && ! defined('WPACU_PRO_PLUGIN_VERSION')) {
            $importedList[] = 'pro_configuration_stored';
        }
        return $importedList;
    }

    /**
     * The request-context map is derived from Plugins Manager rules and the
     * current site's URLs. Do not move it between installations; the writer
     * rebuilds it from the imported rules for the destination environment.
     *
     * @param mixed $globalData
     *
     * @return mixed
     */
    public static function filterGlobalDataForTransfer($globalData)
    {
        if ( ! is_array($globalData) ) {
            return $globalData;
        }

        unset($globalData['plugins_request_context_map']);

        return $globalData;
    }

    /**
     * @param array $importedList
     * @param array $valuesArray
     *
     * @return array
     */
    public static function filterImportGlobalDataImportedList($importedList, $valuesArray)
    {
        if ( ! empty($valuesArray['global_data']) ) {
            $importedList['global_data_values'] = $valuesArray['global_data']; // this is used to print what was imported as it's a complex option value
        }

        return $importedList;
    }

    /**
     * @param array $importedList
     * @param array $valuesArray
     *
     * @return array
     */
    public static function filterImportAfterCommonMetas($importedList, $valuesArray)
    {
        if (self::validateImport($valuesArray) !== '') { return $importedList; }
        $criticalCssMetaKey = CriticalCss::getMetaKey();
        $hasDedicatedCriticalCssMetas = ! empty($valuesArray['critical_css_metas']) && is_array($valuesArray['critical_css_metas']);
        $stored = false;
        foreach (array('terms_metas' => 'term', 'users_metas' => 'user') as $key => $type) {
            if (empty($valuesArray[$key])) { continue; }
            foreach ($valuesArray[$key] as $row) {
                if ($hasDedicatedCriticalCssMetas && $row['meta_key'] === $criticalCssMetaKey) { continue; }
                // WordPress removes one slash layer when saving metadata.
                update_metadata($type, (int)$row[$type . '_id'], $row['meta_key'], wp_slash((string)$row['meta_value']));
            }
            $importedList[] = $key;
            $stored = true;
        }
        if ($stored && ! defined('WPACU_PRO_PLUGIN_VERSION') && ! in_array('pro_configuration_stored', $importedList, true)) {
            $importedList[] = 'pro_configuration_stored';
        }
        return $importedList;
    }

}
