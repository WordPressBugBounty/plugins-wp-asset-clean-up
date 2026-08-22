<?php
namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\LoadExceptions;
use WpAssetCleanUp\MiscArray;

/**
 * This contain references to Pro rules
 *
 * e.g. they could be leftovers if the Lite version is used
 */
class OverviewAdvanced
{
    /**
     * @param $data
     *
     * @return mixed
     */
    public static function getPageOverviewData($data)
    {
        $data['plugins_with_rules'] = array();

        // Any plugins unloaded site-wide (with exceptions) or based on other conditions?
        // Get all the saved rules for both active, inactive and deleted plugins
        $getAllPluginsRules = PluginsManagerAdmin::getPluginRulesFiltered(false, true);

        if ( ! empty($getAllPluginsRules) ) {
            // Are there plugins with rules?
            // Fetch all installed plugins (active and inactive) so their proper
            // names can be used. Rules belonging to a plugin that is no longer
            // installed must still be listed: imports can legitimately bring
            // those rules over before the matching plugin is installed.
            $currentPluginsWithRules = array();
            $allCurrentPlugins       = get_plugins();

            foreach ($getAllPluginsRules as $locationKey => $pluginsRules) {
                foreach ($pluginsRules as $pluginPath => $pluginRules) {
                    // Skip Asset CleanUp as it's obviously needed for the functionality
                    if (strpos($pluginPath, 'wp-asset-clean-up') !== false) {
                        continue;
                    }

                    $isPluginInstalled = isset($allCurrentPlugins[$pluginPath]);
                    $pluginTitle       = $isPluginInstalled && ! empty($allCurrentPlugins[$pluginPath]['Name'])
                        ? $allCurrentPlugins[$pluginPath]['Name']
                        : self::getPluginFallbackTitle($pluginPath);

                    $currentPluginsWithRules[$locationKey][] = array(
                        'title'        => $pluginTitle,
                        'path'         => $pluginPath,
                        'rules'        => $pluginRules,
                        'is_installed' => $isPluginInstalled
                    );
                }
            }

            if ( ! empty($currentPluginsWithRules) ) {
                foreach ( array_keys( $currentPluginsWithRules ) as $locationKey ) {
                    usort( $currentPluginsWithRules[ $locationKey ], static function( $a, $b ) {
                        $titleComparison = strcasecmp($a['title'], $b['title']);

                        if ($titleComparison !== 0) {
                            return $titleComparison;
                        }

                        return strcmp($a['path'], $b['path']);
                    } );
                }
            }

            $pluginsDir = dirname( WPACU_PLUGIN_DIR ) . '/';

            // Get active plugins and their basic information
            $activePlugins = wp_get_active_and_valid_plugins();

            foreach ($activePlugins as $activePluginKey => $activePluginValue) {
                $activePlugins[$activePluginKey] = str_replace($pluginsDir, '', $activePluginValue);
            }

            // Multisite?
            $data['plugins_active_network'] = array();

            if (is_multisite()) {
                $networkActivePlugins = wp_get_active_network_plugins();

                if ( ! empty( $networkActivePlugins ) ) {
                    foreach ( $networkActivePlugins as $networkActivePlugin ) {
                        $networkActivePluginSanitized     = str_replace( $pluginsDir, '', $networkActivePlugin );
                        $activePlugins[]                  = $networkActivePluginSanitized;
                        $data['plugins_active_network'][] = $networkActivePluginSanitized;
                    }
                }
            }

            $activePlugins = array_unique($activePlugins);

            $data['plugins_active']     = $activePlugins;
            $data['plugins_with_rules'] = $currentPluginsWithRules; // all rules for all plugins
            $data['plugins_icons']      = MiscAdmin::getAllActivePluginsIcons();
        }

        return $data;
    }

    /**
     * Build a readable title when a saved Plugins Manager rule targets a
     * plugin that is not installed on the current website.
     *
     * @param string $pluginPath
     *
     * @return string
     */
    private static function getPluginFallbackTitle($pluginPath)
    {
        $pluginPath = trim(str_replace('\\', '/', (string)$pluginPath));
        $pluginDir  = dirname($pluginPath);

        if ($pluginDir === '.' || $pluginDir === '/') {
            $pluginSlug = basename($pluginPath, '.php');
        } else {
            $pluginDirParts = explode('/', trim($pluginDir, '/'));
            $pluginSlug     = end($pluginDirParts);
        }

        $pluginTitle = trim(ucwords(str_replace(array('-', '_'), ' ', $pluginSlug)));

        return $pluginTitle !== '' ? $pluginTitle : $pluginPath;
    }

    /**
     * @param $allHandles
     * @param $filterFor
     * @param $extraValues
     *
     * @return array
     */
    public static function filterHandlesWithAtLeastOneRule($filterFor, $allHandles, $extraValues = array())
    {
        if ($filterFor === 'load_exceptions') {
            // Load exception for all pages of [post] type having specific taxonomies set
            $wpacuPostTypeLoadExceptionsViaTax = LoadExceptions::getTaxonomyValuesAssocToPostTypeLoadExceptions();

            if ( ! empty($wpacuPostTypeLoadExceptionsViaTax)) {
                foreach ($wpacuPostTypeLoadExceptionsViaTax as $postType => $assetsData) {
                    if ( ! (isset($assetsData['styles']) || isset($assetsData['scripts']))) {
                        continue;
                    }

                    foreach ($assetsData as $assetType => $assetsValues) {
                        foreach ($assetsValues as $assetHandle => $assetData) {
                            if (isset($assetData['enable']) && $assetData['enable'] && ! empty($assetData['values'])) {
                                $allHandles[ $assetType ][ $assetHandle ]['load_exception_post_type_via_tax'][ $postType ] = $assetData['values'];
                                }
                        }
                    }
                }
            }

            // Load exception for all pages belonging to a specific taxonomy (e.g. /category/[any_value_here])
            $wpacuLoadExceptionsViaTaxType = LoadExceptions::getLoadExceptionsViaTaxType();

            if ( ! empty($wpacuLoadExceptionsViaTaxType) ) {
                foreach ( $wpacuLoadExceptionsViaTaxType as $taxonomyName => $assetsData ) {
                    if ( ! empty($assetsData) ) {
                        foreach ( $assetsData as $assetType => $assetHandles ) {
                            $assetHandles = array_unique($assetHandles);

                            foreach ( $assetHandles as $assetHandle ) {
                                $allHandles[$assetType][$assetHandle]['load_exception_via_tax_type'][] = $taxonomyName;
                            }
                        }
                    }
                }
            }

            // Load exception for all archive pages belonging to any author (e.g. /author/[any_value_here])
            $wpacuLoadExceptionsViaAuthorType = LoadExceptions::getLoadExceptionsViaAuthorType();

            if ( ! empty($wpacuLoadExceptionsViaAuthorType) ) {
                foreach ( $wpacuLoadExceptionsViaAuthorType as $assetType => $assetHandles ) {
                    foreach ( $assetHandles as $assetHandle ) {
                        $allHandles[$assetType][$assetHandle]['load_exception_via_author_type'] = 1;
                    }
                }
            }

            /*
             * Load exceptions for 404, Search, Date
             */
            $extrasLoadExceptionsJson = get_option( WPACU_PLUGIN_ID . '_extras_load_exceptions', '');

            if ($extrasLoadExceptionsJson === '') {
                $loadExceptionsExtras = array(); // no exceptions stored in the `options` table
            } else {
                $loadExceptionsExtras = json_decode( $extrasLoadExceptionsJson, true );

                if (wpacuJsonLastError() !== JSON_ERROR_NONE ) {
                    $loadExceptionsExtras = array();
                }
            }

            if ( ! empty($loadExceptionsExtras) ) {
                foreach ($loadExceptionsExtras as $refKeyExtra => $values) {
                    foreach ($values as $assetType => $assetHandles) {
                        foreach ($assetHandles as $assetHandle) {
                            $allHandles[ $assetType ][ $assetHandle ]['load_exception_on_this_page'][ $refKeyExtra ] = 1;
                            }
                    }
                }
            }
        }

        if ($filterFor === 'unload_bulk') {
            $unloadBulkType   = $extraValues['unload_bulk_type'];
            $unloadBulkValues = $extraValues['unload_bulk_values'];
            $assetType        = $extraValues['asset_type'];

            if ($unloadBulkType === 'post_type_via_tax') {
                foreach ($unloadBulkValues as $postType => $assetHandles) {
                    foreach ($assetHandles as $assetHandle => $assetData) {
                        if (isset($assetData['enable']) && $assetData['enable'] && ! empty($assetData['values'])) {
                            $allHandles[ $assetType ][ $assetHandle ]['unload_bulk'][$unloadBulkType][$postType] = $assetData['values'];
                            }
                    }
                }

            }

            if (in_array($unloadBulkType, array('date', '404', 'search')) || (strpos($unloadBulkType, 'custom_post_type_archive_') !== false)) {
                foreach ($unloadBulkValues as $assetHandle) {
                    $allHandles[ $assetType ][ $assetHandle ]['unload_bulk'][$unloadBulkType] = 1;
                    }
            }

            if ($unloadBulkType === 'taxonomy') {
                foreach ($unloadBulkValues as $taxonomyType => $assetHandles) {
                    foreach ($assetHandles as $assetHandle) {
                        $allHandles[ $assetType ][ $assetHandle ]['unload_bulk'][$unloadBulkType][] = $taxonomyType;
                        }
                }
            }

            if ($unloadBulkType === 'author' && ! empty($unloadBulkValues['all'])) {
                foreach ($unloadBulkValues['all'] as $assetHandle) {
                    $allHandles[ $assetType ][ $assetHandle ]['unload_bulk'][$unloadBulkType] = 1;
                    }
            }
        }

        return $allHandles;
    }

    /**
     * @param $handleDataKey
     * @param $handleData
     * @param $handleChangesOutputs
     * @param $hasRedundantRules
     *
     * @return array
     */
    public static function filterRenderHandleChangesOutput($handleDataKey, $handleData, $handleChangesOutputs, $hasRedundantRules = false)
    {
        /*
         * Unload on multiple pages of a specific type
         * e.g. posts linked to a category, any date archive page, any 404 page, any search results page
         */
        if ($handleDataKey === 'unload_bulk') {
            $outputGroupKey = 'unload_post_type_via_tax';

            if (isset($handleData[$handleDataKey]['post_type_via_tax'])) {
                $ruleKey = 'unload_on_all_post_types_via_tax_term';

                foreach ($handleData[$handleDataKey]['post_type_via_tax'] as $postType => $termIds) {
                    if (empty($termIds)) {
                        continue;
                    }

                    $taxTermsToList = $taxLabelsToNames = array();
                    $anyDelTaxList  = array();
                    $outputGroup    = '<span style="color: #cc0000;">Unloaded on all pages of <strong>' . esc_html($postType) . '</strong> (post type) ' . Overview::anyNoPostTypeEntriesMsg($postType) . ' associated with these taxonomies:</span><br />';

                    foreach ($termIds as $termId) {
                        if (! term_exists((int)$termId)) {
                            $output = '<strong><s>' . esc_html($termId) . '</s></strong>' .
                                      ' <span style="color: #cc0000;" title="The taxonomy might have been deleted: ID ' . esc_attr($termId) . '" class="wpacu-tooltip dashicons dashicons-warning"></span>';

                            $taxTermsToList['Deleted or Missing'][] = Overview::renderRuleOutput(
                                 $output,
                                 $handleData,
                                 'unload_on_all_post_types_with_tax_term',
                                 $postType,
                                 $termId
                            );

                            $anyDelTaxList[] = $termId;
                            continue;
                        }

						$term     = get_term($termId);
						$taxonomy = ($term && ! is_wp_error($term)) ? get_taxonomy($term->taxonomy) : false;
						$taxLabel = ($taxonomy && ! empty($taxonomy->label))
							? $taxonomy->label
							: (($term && ! is_wp_error($term)) ? $term->taxonomy : 'Deleted or Missing');

						$output = ($term && ! is_wp_error($term))
							? esc_html($term->name) . ' (' . esc_html($term->count) . ')'
							: '<strong><s>' . esc_html($termId) . '</s></strong>';

						if ( ! $taxonomy) {
							$output .= ' <span style="color: #cc0000;" title="The taxonomy is not registered anymore and this rule does not currently apply." class="wpacu-tooltip dashicons dashicons-warning"></span>';
						}

                        $wrapped = Overview::renderNoWrapRuleOutput(
                             $output,
                             $handleData,
                             $ruleKey,
                             $termId,
                             $postType
                        );

                        $taxLabelsToNames[$taxLabel] = $term->taxonomy;
                        $taxTermsToList[$taxLabel][] = $wrapped;
                    }

                    if ( ! empty($taxTermsToList) ) {
                        foreach (array_keys($taxTermsToList) as $taxonomyLabel) {
                            usort($taxTermsToList[$taxonomyLabel], static function($a, $b) {
                                return strcasecmp(strip_tags($a), strip_tags($b));
                            });
                        }

                        $handleChangesOutputs[$outputGroupKey] = $outputGroup;

                        $handleChangesOutputs[$outputGroupKey]     .= '<ul>';

                        foreach ($taxTermsToList as $categoryTitle => $termsAssoc) {
                            $taxonomyName = isset($taxLabelsToNames[$categoryTitle]) ? $taxLabelsToNames[$categoryTitle] : 'unknown';
                            $handleChangesOutputs[$outputGroupKey] .=
                                '<li>'.
                                    '<strong>' . esc_html($categoryTitle) . '</strong> (' . esc_html($taxonomyName) . '): ' . implode(' | ', $termsAssoc) .
                                '</li>';
                        }

                        $handleChangesOutputs[$outputGroupKey] .= '</ul>';

                        if ( ! empty($anyDelTaxList) ) {
                            $handleChangesOutputs[$outputGroupKey] .= '<ul>';
                                $delTaxIds = implode(', ', array_map('esc_html', $anyDelTaxList));
                                $handleChangesOutputs[$outputGroupKey] .=
                                    '<li>'.
                                        '<span style="color: #cc0000;" title="The following taxonomy IDs were also found (the taxonomies might have been deleted from the database): ' . esc_attr($delTaxIds) . '" class="wpacu-tooltip dashicons dashicons-warning"></span>'.
                                    '</li>';
                            $handleChangesOutputs[$outputGroupKey] .= '</ul>';
                        }
                    }

                    if (isset($handleChangesOutputs['unload_site_wide'])) {
                        $handleChangesOutputs[$outputGroupKey] .= '&nbsp;<em><small>* redundant unload rule</small></em>';
                        $hasRedundantRules = true;
                    }

                    $handleChangesOutputs[$outputGroupKey] = Overview::wrapRuleViewChangeOutput($handleChangesOutputs[$outputGroupKey], $ruleKey);
                }
            }

            if ( ! empty($handleData[$handleDataKey]['taxonomy'])) {
                $outputGroupKey = 'unload_on_all_tax_archive_pages';

                $handleChangesOutputs[$outputGroupKey] = '<span style="color: #cc0000;">Unloaded on all archive pages of these taxonomies: ';

                $taxonomyListArray = array();

                $ruleKey = 'unload_on_all_tax_archive_pages';

                foreach ($handleData[$handleDataKey]['taxonomy'] as $taxonomy) {
                    $appendAfter = '';

                    if ( ! taxonomy_exists($taxonomy)) {
                        $appendAfter = ' <span style="color: #cc0000;" title="The following taxonomy might not exist anymore: ' . $taxonomy . '" class="wpacu-tooltip dashicons dashicons-warning"></span> ';
                        $output = $taxonomy . ' '. $appendAfter;
                    } else {
                        $taxonomyObj = get_taxonomy($taxonomy);

                        $taxonomyLabel = ($taxonomyObj && ! empty($taxonomyObj->label)) ? $taxonomyObj->label : $taxonomy;

                        $output = '<a title="" target="_blank" href="' . esc_url(admin_url('edit-tags.php?taxonomy=' . $taxonomy)) . '">' . esc_html($taxonomyLabel) . '</a> (' . $taxonomy . ') ' . $appendAfter;
                    }

                    $taxonomyListArray[] = Overview::renderNoWrapRuleOutput(
                         $output,
                         $handleData,
                         $ruleKey,
                         $taxonomy
                    );
                }

                if (Overview::isViewMode()) {
                    $taxonomyListArrayFormatted = array_map(static function ($value) {
                        return $value;
                    }, $taxonomyListArray);

                    $handleChangesOutputs[$outputGroupKey] .= implode(', ', $taxonomyListArrayFormatted);
                } else {
                    $handleChangesOutputs[$outputGroupKey] .= implode(' ', $taxonomyListArray);
                }

                $handleChangesOutputs[$outputGroupKey] .= '</span>';

                if (isset($handleChangesOutputs['unload_site_wide'])) {
                    $handleChangesOutputs[$outputGroupKey] .= '&nbsp;<em><small>* redundant unload rule</small></em>';
                    $hasRedundantRules = true;
                }

                $handleChangesOutputs[$outputGroupKey] = Overview::wrapRuleViewChangeOutput($handleChangesOutputs[$outputGroupKey], $ruleKey);
            }

            $unloadBulkKeys    = array_keys($handleData[$handleDataKey]);
            $unloadBulkKeysStr = implode('', $unloadBulkKeys);

            if (isset($handleData[$handleDataKey]['date'])
                || isset($handleData[$handleDataKey]['404'])
                || isset($handleData[$handleDataKey]['search'])
                || (strpos($unloadBulkKeysStr, 'custom_post_type_archive_') !== false)
            ) {
                foreach ($handleData[$handleDataKey] as $bulkType => $bulkValue) {
                    $ruleKey = '';

                    if ($bulkType === 'date' && $bulkValue === 1) {
                        $outputGroupKey = 'unload_on_date_archive_pages';
                        $ruleKey = 'unload_on_archive_pages';

                        $output = '<span style="color: #cc0000;">Unloaded on all archive `Date` pages (any date)</span>';

                        $handleChangesOutputs[$outputGroupKey] = Overview::renderNoWrapRuleOutput(
                             $output,
                             $handleData,
                             $ruleKey,
                             'date'
                        );
                    } elseif ($bulkType === 'search' && $bulkValue === 1) {
                        $outputGroupKey = 'unload_on_archive_pages_search';
                        $ruleKey = 'unload_on_search_page';

                        $output = '<span style="color: #cc0000;">Unloaded on `Search` page (any keyword)</span>';

                        $handleChangesOutputs[$outputGroupKey] = Overview::renderNoWrapRuleOutput(
                            $output,
                            $handleData,
                            $ruleKey
                        );
                    } elseif ($bulkType === 404 && $bulkValue === 1) {
                        $outputGroupKey = 'unload_on_archive_pages_404';
                        $ruleKey = 'unload_on_404_page';

                        $output = '<span style="color: #cc0000;">Unloaded on `404 Not Found` page (any URL)</span>';

                        $handleChangesOutputs['unload_on_archive_pages_404'] = Overview::renderNoWrapRuleOutput(
                            $output,
                            $handleData,
                            $ruleKey
                        );
                    } elseif (strpos($bulkType, 'custom_post_type_archive_') !== false) {
                        $outputGroupKey = 'unload_on_' . $bulkType;
                        $ruleKey = 'unload_on_archive_pages';

                        $customPostType = str_replace('custom_post_type_archive_', '', $bulkType);
                        $output         = '<span style="color: #cc0000;">Unloaded on the archive (list of posts) page of <strong>' . $customPostType . '</strong> custom post type' . Overview::anyNoPostTypeEntriesMsg($customPostType) . '</span>';

                        $handleChangesOutputs[$outputGroupKey] = Overview::renderNoWrapRuleOutput(
                             $output,
                             $handleData,
                             $ruleKey,
                             $customPostType,
                             'custom_post_type'
                        );
                    }

                    if ($ruleKey) {
                        if (isset($handleChangesOutputs['unload_site_wide'])) {
                            $handleChangesOutputs[$outputGroupKey] .= '&nbsp;<em><small>* redundant unload rule</small></em>';
                            $hasRedundantRules = true;
                        }

                        $handleChangesOutputs[$outputGroupKey] = Overview::wrapRuleViewChangeOutput($handleChangesOutputs[$outputGroupKey], $ruleKey);
                    }
                }
            }

            if (isset($handleData[$handleDataKey]['author']) && $handleData[$handleDataKey]['author']) {
                $outputGroupKey = 'unload_all_author_pages';
                $ruleKey = 'unload_all_author_pages';

                $output = '<span style="color: #cc0000;">Unloaded on all <strong>author</strong> pages</span>';

                $handleChangesOutputs[$outputGroupKey] = Overview::renderNoWrapRuleOutput(
                    $output,
                    $handleData,
                    $ruleKey
                );

                if (isset($handleChangesOutputs['unload_site_wide'])) {
                    $handleChangesOutputs[$outputGroupKey] .= '&nbsp;<em><small>* redundant unload rule</small></em>';
                    $hasRedundantRules = true;
                }

                $handleChangesOutputs[$outputGroupKey] = Overview::wrapRuleViewChangeOutput($handleChangesOutputs[$outputGroupKey], $ruleKey);
            }

            return array('handle_changes_outputs' => $handleChangesOutputs, 'has_redundant_rules' => $hasRedundantRules);
        }

        /*
         * Unload on this page (e.g. post, category page, 404 page, etc.)
         */
        if ($handleDataKey === 'unload_on_this_page') {
            // Unload on this page: specific taxonomy term (e.g., 'category', 'product_cat')
            if (isset($handleData[$handleDataKey]['term'])) {
                $outputGroupKey = 'unload_on_these_taxonomy_pages';

                $ruleKey = 'unload_on_taxonomy_page';

                $handleChangesOutputs[$outputGroupKey] = '<span style="color: #cc0000;">Unloaded in the following taxonomy pages:</span> ';

                $taxListArray = array();

                sort($handleData[$handleDataKey]['term']);

                foreach ($handleData[$handleDataKey]['term'] as $termId) {
                    $taxData = term_exists((int)$termId) ? get_term($termId) : false;

                    if (! $taxData || (isset($taxData->errors['invalid_taxonomy']) && ! empty($taxData->errors['invalid_taxonomy']))) {
                        $label = '<span style="color: darkred; font-style: italic;">Error: Taxonomy with ID ' . esc_html($termId) . ' does not exist anymore (rule does not apply)</span>';

                        $taxListArray[] = Overview::renderNoWrapRuleOutput(
                             $label,
                             $handleData,
                             'unload_on_taxonomy_page',
                             $termId
                        );
                    } else {
                        $taxonomy = $taxData->taxonomy;

                        global $wp_rewrite;
                        $termPermalink = $wp_rewrite->get_extra_permastruct($taxonomy);

						$termLink    = taxonomy_exists($taxonomy) ? get_term_link($taxData, $taxonomy) : false;
						$termRelLink = is_string($termLink) ? str_replace(site_url(), '', $termLink) : '';

						$label = ! is_string($termLink)
							? '<span style="color: darkred; font-style: italic;">Error: The taxonomy for term ID ' . esc_html($termId) . ' is not registered anymore (rule does not apply)</span>'
							: '<a target="_blank" href="' . esc_url($termLink) . '">' . esc_html($termRelLink) . '</a>';

                        // Adaugă iconiță dacă nu există permalink
                        if (! $termPermalink) {
                            $label .= ' <span style="color: #cc0000;" title="The taxonomy might not exist anymore as its permalink could not be retrieved" class="wpacu-tooltip dashicons dashicons-warning"></span>';
                        }

                        $taxListArray[] = Overview::renderNoWrapRuleOutput(
                             $label,
                             $handleData,
                             'unload_on_taxonomy_page',
                             $termId
                        );
                    }
                }

                if (Overview::isViewMode()) {
                    $taxListArrayFormatted = array_map(static function ($value) {
                        return $value;
                    }, $taxListArray);

                    $handleChangesOutputs[$outputGroupKey] .= implode(', ', $taxListArrayFormatted);
                } else {
                    $handleChangesOutputs[$outputGroupKey] .= implode(' ', $taxListArray);
                }

                if (isset($handleChangesOutputs['unload_site_wide'])) {
                    $handleChangesOutputs[$outputGroupKey] .= '&nbsp;<em><small>* redundant unload rule</small></em>';
                    $hasRedundantRules = true;
                }

                $handleChangesOutputs[$outputGroupKey] = Overview::wrapRuleViewChangeOutput($handleChangesOutputs[$outputGroupKey], $ruleKey);
            }

            if ( ! empty($handleData[$handleDataKey]['user']) ) {
                $outputGroupKey = 'unload_on_these_author_pages';
                $ruleKey = 'unload_on_these_author_pages';

                $handleChangesOutputs[$outputGroupKey] = '<span style="color: #cc0000;">Unloaded in the following author pages: ';

                $authorListArray = array();

                sort($handleData[$handleDataKey]['user']);

                foreach ($handleData[$handleDataKey]['user'] as $userId) {
                    $user = get_user_by('id', $userId);

                    if (isset($user->ID)) {
                        $authorLink    = get_author_posts_url($userId);
                        $authorRelLink = str_replace(site_url(), '', $authorLink);

                        $label = '<a target="_blank" href="' . esc_url($authorLink) . '">' . esc_html($authorRelLink) . '</a>';
                    } else {
                        $label = '<s style="color: #cc0000;">N/A (The user with the following was deleted: <strong>' . esc_html($userId) . '</strong>)</s>';
                    }

                    $authorListArray[] = Overview::renderNoWrapRuleOutput(
                        $label,
                        $handleData,
                        $ruleKey,
                        'author',
                        $userId
                    );
                }

                if (Overview::isViewMode()) {
                    $authorListArrayFormatted = array_map(static function ($value) {
                        return $value;
                    }, $authorListArray);

                    $handleChangesOutputs[$outputGroupKey] .= implode(', ', $authorListArrayFormatted);
                } else {
                    $handleChangesOutputs[$outputGroupKey] .= implode(' ', $authorListArray);
                }

                $handleChangesOutputs[$outputGroupKey] .= '</span>';

                if (isset($handleChangesOutputs['unload_site_wide'])) {
                    $handleChangesOutputs[$outputGroupKey] .= '&nbsp;<em><small>* redundant unload rule</small></em>';
                    $hasRedundantRules = true;
                }

                $handleChangesOutputs[$outputGroupKey] = Overview::wrapRuleViewChangeOutput($handleChangesOutputs[$outputGroupKey], $ruleKey);

            }

            return array(
                'handle_changes_outputs' => $handleChangesOutputs,
                'has_redundant_rules'    => $hasRedundantRules
            );
        }

        /*
         * Unload whenever the request URI matches the RegEx(es)
         */
        if ($handleDataKey === 'unload_regex') {
            $outputGroupKey = 'unload_uri_matches_regexes';
            $ruleKey = 'unload_regex';

            // Unload via RegEx
            if (isset($handleData[$handleDataKey]) && $handleData[$handleDataKey]) {
                $handleChangesOutputs[$outputGroupKey] = '';

                $regexOutput = '<span style="color: #cc0000;">Unloaded if the request URI (from the URL) matches this RegEx(es):';

                $handleData['html_output_value'] = '<code>' . nl2br($handleData[$handleDataKey]) . '</code>';

                if (Overview::isViewMode()) {
                    $regexOutput .= ' '.$handleData['html_output_value'];
                }

                $regexOutput .= '</span>';

                // Aplicăm metoda care adaugă checkbox dacă e în edit mode
                $handleChangesOutputs[$outputGroupKey] .= Overview::renderNoWrapRuleOutput(
                     $regexOutput,
                     $handleData,
                     $ruleKey,
                     $handleData[$handleDataKey]
                );

                if (isset($handleChangesOutputs['unload_site_wide'])) {
                    $handleChangesOutputs[$outputGroupKey] .= '&nbsp;<em><small>* redundant unload rule</small></em>';
                    $hasRedundantRules = true;
                }

                $handleChangesOutputs[$outputGroupKey] = Overview::wrapRuleViewChangeOutput($handleChangesOutputs[$outputGroupKey], $ruleKey);
            }

            return array(
                'handle_changes_outputs' => $handleChangesOutputs,
                'has_redundant_rules'    => $hasRedundantRules
            );
        }

        /*
         * Load Exceptions
         */
        if ($handleDataKey === 'load_exceptions') {
            // Load exception on all pages of [post] type when specific taxonomies are set
            $outputGroupKey = $ruleKey = 'load_exception_post_type_via_tax';

            if (isset($handleData[$outputGroupKey])) {
                foreach ($handleData[$outputGroupKey] as $postType => $termIds) {
                    if (empty($termIds)) {
                        continue;
                    }

                    $outputGroupKey = $outputGroupKey . '_' . $postType;

                    $handleChangesOutputs[$outputGroupKey] = '';

                    $taxTerms = array();

                    $handleChangesOutputs[$outputGroupKey] .= '<span style="color: green;">Loaded (as an exception)</span> in all pages of <strong>'
                                        . esc_html($postType) .
                                   '</strong> post type' . Overview::anyNoPostTypeEntriesMsg($postType) . ' that have these taxonomies set:<br />';

                    foreach ($termIds as $termId) {
                        if ( ! term_exists((int)$termId) ) {
                            $taxLabel = 'N/A: '.(int)$termId;
                            $output   = '<strong><s>' . (int)$termId . '</s></strong>' .
                                        '<span style="color: #cc0000;" title="The taxonomy might not be available anymore as it was not detected from the specified ID: ' . esc_attr($termId) . '" class="wpacu-tooltip dashicons dashicons-warning"></span>';
                        } else {
							$term     = get_term($termId);
							$taxonomy = ($term && ! is_wp_error($term)) ? get_taxonomy($term->taxonomy) : false;
							$taxLabel = ($taxonomy && ! empty($taxonomy->label))
								? $taxonomy->label
								: (($term && ! is_wp_error($term)) ? $term->taxonomy : 'N/A: '.(int)$termId);

							$output = ($term && ! is_wp_error($term))
								? esc_html($term->name) . ' (' . esc_html($term->count) . ')'
								: '<strong><s>' . (int)$termId . '</s></strong>';

							if ( ! $taxonomy) {
								$output .= ' <span style="color: #cc0000;" title="The taxonomy is not registered anymore and this rule does not currently apply." class="wpacu-tooltip dashicons dashicons-warning"></span>';
							}
                        }

                        $wrapped = Overview::renderNoWrapRuleOutput(
                             $output,
                             $handleData,
                             $ruleKey,
                             $postType,
                             $termId
                        );

                        $taxTerms[$taxLabel][] = $wrapped;
                    }

                    foreach ($taxTerms as $taxonomyLabel => $termsOutputs) {
                        // Optional: sort terms alphabetically
                        usort($termsOutputs, static function ($a, $b) {
                            return strcasecmp(strip_tags($a), strip_tags($b));
                        });

                        $handleChangesOutputs[$outputGroupKey] .=
                            '<div style="margin: 5px 0;">'.
                                '<strong>' . esc_html($taxonomyLabel) . '</strong>: ' .
                                implode(' | ', $termsOutputs) .
                            '</div>';
                    }

                    $handleChangesOutputs[$outputGroupKey] = Overview::wrapRuleViewChangeOutput($handleChangesOutputs[$outputGroupKey], $ruleKey);
                }
            }

            // Load exceptions? Per taxonomy page (e.g. /category/clothes/)
            $outputGroupKey = 'load_exception_on_these_taxonomy_pages';
            $ruleKey = 'load_exception_on_this_page_tax_id';

            if ( ! empty($handleData['load_exception_on_this_page']['term']) ) {
                $handleChangesOutputs[$outputGroupKey] = '<span style="color: green;">Loaded (as an exception) in the following <strong>taxonomy</strong> pages: ';

                $taxTermListArray = array();

                sort($handleData['load_exception_on_this_page']['term']);

                foreach ($handleData['load_exception_on_this_page']['term'] as $termId) {
                    $termData = get_term_by('term_taxonomy_id', $termId);

                    if ($termData) {
                        $output = '<a title="" target="_blank" href="' . esc_url( admin_url( 'term.php?taxonomy=' . $termData->taxonomy . '&tag_ID=' . $termId ) ) . '">' . $termId . '</a> (' . $termData->name . ' / taxonomy: ' . $termData->taxonomy . ')';
                    } else {
                        $output = '<span style="color: darkred; font-style: italic;">Error: Taxonomy with ID '.$termId.' does not exist anymore (rule does not apply)</span>';
                    }

                    $taxTermListArray[] = Overview::renderNoWrapRuleOutput(
                         $output,
                         $handleData,
                         $ruleKey,
                         $termId
                    );
                }

                if (Overview::isViewMode()) {
                    $taxTermListArrayFormatted = array_map(static function ($value) {
                        return $value;
                    }, $taxTermListArray);

                    $handleChangesOutputs[$outputGroupKey] .= implode(', ', $taxTermListArrayFormatted);
                } else {
                    $handleChangesOutputs[$outputGroupKey] .= implode(' ', $taxTermListArray);
                }

                $handleChangesOutputs[$outputGroupKey] .= '</span>';

                $handleChangesOutputs[$outputGroupKey] = Overview::wrapRuleViewChangeOutput($handleChangesOutputs[$outputGroupKey], $ruleKey);
            }

            $outputGroupKey = 'load_exception_via_tax_type';
            $handleDataKey  = $outputGroupKey;

            $ruleKey = 'load_exception_via_taxonomy_type';

            if ( ! empty($handleData[$handleDataKey]) ) {
                $handleChangesOutputs[$outputGroupKey] = '<span style="color: green;">Loaded (as an exception) on all pages belonging to the following <strong>taxonomy</strong> pages: ';

                $pagesWithTaxArray = array();

                sort($handleData[$handleDataKey]);

                foreach ($handleData[$handleDataKey] as $taxName) {
                    $taxData = get_taxonomy($taxName);

                    if ($taxData) {
                        $output = '<a title="" target="_blank" href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=' . $taxName ) ) . '">' . esc_html($taxData->label) . '</a> (' . esc_html($taxName) . ')';
                    } else {
                        $output  = '<span style="color: darkred; font-style: italic;">Error: Taxonomy ' . esc_html($taxName) . ' does not seem to exist anymore (rule does not apply)</span>';
                    }

                    $pagesWithTaxArray[] = Overview::renderNoWrapRuleOutput(
                         $output,
                         $handleData,
                         $ruleKey,
                         $taxName
                    );
                }

                if (Overview::isViewMode()) {
                    $pagesWithTaxArrayFormatted = array_map(static function ($value) {
                        return $value;
                    }, $pagesWithTaxArray);

                    $handleChangesOutputs[$outputGroupKey] .= implode(', ', $pagesWithTaxArrayFormatted);
                } else {
                    $handleChangesOutputs[$outputGroupKey] .= implode(' ', $pagesWithTaxArray);
                }

                $handleChangesOutputs[$outputGroupKey] .= '</span>';
                $handleChangesOutputs[$outputGroupKey] = Overview::wrapRuleViewChangeOutput($handleChangesOutputs[$outputGroupKey], $ruleKey);
            }

            $outputGroupKey = 'load_exception_via_author_type';
            $handleDataKey = $outputGroupKey;

            $ruleKey = 'load_exception_via_author_type';

            if (isset($handleData[$handleDataKey])) {
                $output = '<span style="color: green;">Loaded (as an exception) on all author archive pages</span>';

                $handleChangesOutputs[$outputGroupKey] = Overview::renderNoWrapRuleOutput(
                     $output,
                     $handleData,
                     $ruleKey,
                     1
                );

                $handleChangesOutputs[$outputGroupKey] = Overview::wrapRuleViewChangeOutput($handleChangesOutputs[$outputGroupKey], $ruleKey);
            }

            // Load exceptions? Per user archive page (e.g. /author/john/)
            $outputGroupKey = 'load_exception_on_these_author_pages';
            $handleDataKey = 'load_exception_on_this_page';

            if (isset($handleData[$handleDataKey]['user'])) {
                $handleChangesOutputs[$outputGroupKey] = '<span style="color: green;">Loaded (as an exception) in the following user archive pages: ';

                $usersListArray = array();

                sort($handleData[$handleDataKey]['user']);

                $ruleKey = 'load_exception_on_this_user';

                foreach ($handleData[$handleDataKey]['user'] as $userId) {
                    $userData = get_user_by('id', $userId);

                    if ($userData) {
                        $output = '<a title="" target="_blank" href="' . esc_url(admin_url('user-edit.php?user_id=' . $userData->ID)) . '"><strong>' . esc_html($userData->data->user_nicename) . '</strong>' . ' (User ID: ' . $userData->ID . ')</a>';
                    } else {
                        $output = '<span style="color: darkred; font-style: italic;">Error: User with ID ' . esc_html($userId) . ' does not exist anymore (rule does not apply)</span>';
                    }

                    $usersListArray[] = Overview::renderRuleOutput(
                         $output,
                         $handleData,
                         $ruleKey,
                         $userId
                    );
                }

                if (Overview::isViewMode()) {
                    $usersListArrayFormatted = array_map(static function ($value) {
                        return '<strong>' . $value . '</strong>';
                    }, $usersListArray);

                    $handleChangesOutputs[$outputGroupKey] .= implode(', ', $usersListArrayFormatted);
                } else {
                    $handleChangesOutputs[$outputGroupKey] .= implode(' ', $usersListArray);
                }

                $handleChangesOutputs[$outputGroupKey] .= '</span>';

                $handleChangesOutputs[$outputGroupKey] = Overview::wrapRuleViewChangeOutput($handleChangesOutputs[$outputGroupKey], $ruleKey);
            }

            // Load exceptions? Search page
            if (isset($handleData[$handleDataKey]['search'])) {
                $output = '<span style="color: green;">Loaded (as an exception) in a `Search` page (any term)</span>';

                $handleChangesOutputs['load_exception_on_search_any_term'] = Overview::renderRuleOutput(
                     $output,
                     $handleData,
                     'load_exception_on_search_page',
                     1
                );
            }

            // Load exceptions? 404 page
            if (isset($handleData[$handleDataKey]['404'])) {
                $output = '<span style="color: green;">Loaded (as an exception) in a `404 (Not Found)` page</span>';

                $handleChangesOutputs['load_exception_on_404_page'] = Overview::renderRuleOutput(
                     $output,
                     $handleData,
                     'load_exception_on_404_page',
                     1
                );
            }

            // Load exceptions? Date archive page
            if (isset($handleData[$handleDataKey]['date'])) {
                $output = '<span style="color: green;">Loaded (as an exception) in a `Date` archive page</span>';

                $handleChangesOutputs['load_exception_on_date_archive_page'] = Overview::renderRuleOutput(
                     $output,
                     $handleData,
                     'load_exception_on_archive_page',
                     'date'
                );
            }

            // Load exceptions? Custom post type archive page
            $loadExceptionsPageStr = isset($handleData[$handleDataKey]) && is_array($handleData[$handleDataKey]) ? implode('', array_keys($handleData[$handleDataKey])) : '';

            if (strpos($loadExceptionsPageStr, 'custom_post_type_archive_') !== false) {
                foreach (array_keys($handleData[$handleDataKey]) as $loadExceptionForDataType) {
                    if (strpos($loadExceptionForDataType, 'custom_post_type_archive_') !== false) {
                        $customPostType = str_replace('custom_post_type_archive_', '', $loadExceptionForDataType);

                        $output = '<span style="color: green;">Loaded (as an exception) in an archive page (custom post type: <em>' . esc_html($customPostType) . '</em>)' . Overview::anyNoPostTypeEntriesMsg($customPostType) . '</span>';

                        $handleChangesOutputs['load_exception_on_' . $loadExceptionForDataType] = Overview::renderRuleOutput(
                             $output,
                             $handleData,
                             'load_exception_on_custom_post_type_archive',
                             $loadExceptionForDataType
                        );
                    }
                }
            }

            $handleDataKey = 'load_regex';

            if (isset($handleData[$handleDataKey]) && $handleData[$handleDataKey]) {
                $outputGroupKey = 'load_exception_regex';

                $ruleKey = 'load_regex';

                if (MiscArray::hasKeyStartingWith($handleChangesOutputs, 'load')) {
                    $textToShow = ' <strong>or</strong> <span style="color: green;">if the request URI (from the URL) matches this RegEx:</span>';
                } else {
                    $textToShow = '<span style="color: green;">Loaded (as an exception) if the request URI (from the URL) matches this RegEx(es):</span>';
                }

                $output = $textToShow;

                $handleData['html_output_value'] = '<code style="color: green;">' . nl2br($handleData['load_regex']) . '</code>';

                if (Overview::isViewMode()) {
                    // e.g. in edit mode, it will show the textarea with the value below the $output
                    $output .= ' '.$handleData['html_output_value'];
                }

                $handleChangesOutputs[$outputGroupKey] = Overview::renderRuleOutput(
                     $output,
                     $handleData,
                     $ruleKey,
                     $handleData[$handleDataKey]
                );
            }

            return array(
                'handle_changes_outputs' => $handleChangesOutputs,
                'has_redundant_rules'    => $hasRedundantRules
            );
        }

        return array();
    }
}
