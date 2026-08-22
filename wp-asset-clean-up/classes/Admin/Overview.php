<?php
namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\Admin;
use WpAssetCleanUp\AssetsManager;
use WpAssetCleanUp\Main;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\MiscArray;
use WpAssetCleanUp\Settings;

/**
 *
 * Class Overview
 * @package WpAssetCleanUp
 */
class Overview
{
    /**
     * @var array
     */
    public $data = array(
        'page_options_to_text' => array()
    );

    /**
     * Overview constructor.
     */
    public function __construct()
    {
        $this->data['page_options_to_text'] = self::getPageOptionsToText();

        // The code initiated in this function is relevant only in the "Overview" page
        if (Misc::getVar('request', 'page') !== WPACU_PLUGIN_ID . '_overview') {
            return;
        }
    }


    /**
     * Returns taxonomy term details for term_taxonomy_id values used by Plugins Manager rules.
     *
     * @param array $termTaxonomyIds
     *
     * @return array
     */
    public static function getTaxonomyTermDetailsByTermTaxonomyIds($termTaxonomyIds)
    {
        static $cachedTermDetails = array();

        $termTaxonomyIds = array_values(array_unique(array_filter(array_map('absint', (array)$termTaxonomyIds))));

        if (empty($termTaxonomyIds)) {
            return array();
        }

        $missingIds = array_values(array_diff($termTaxonomyIds, array_keys($cachedTermDetails)));

        if ( ! empty($missingIds)) {
            global $wpdb;

            $placeholders = implode(', ', array_fill(0, count($missingIds), '%d'));

            $sql = "
                SELECT tt.term_taxonomy_id, tt.taxonomy, tt.parent, tt.count, t.term_id, t.name, t.slug
                FROM {$wpdb->term_taxonomy} AS tt
                INNER JOIN {$wpdb->terms} AS t ON t.term_id = tt.term_id
                WHERE tt.term_taxonomy_id IN ({$placeholders})
            ";

            $rows = $wpdb->get_results($wpdb->prepare($sql, $missingIds), ARRAY_A);

            foreach ($rows as $row) {
                $taxonomy      = (string)$row['taxonomy'];
                $taxonomyObj   = get_taxonomy($taxonomy);
                $taxonomyLabel = ($taxonomyObj && ! empty($taxonomyObj->labels->name))
                    ? $taxonomyObj->labels->name
                    : $taxonomy;

                $termPath = array();

                if ((int)$row['parent'] > 0 && taxonomy_exists($taxonomy)) {
                    $ancestorIds = array_reverse(get_ancestors((int)$row['term_id'], $taxonomy, 'taxonomy'));

                    foreach ($ancestorIds as $ancestorId) {
                        $ancestor = get_term($ancestorId, $taxonomy);

                        if ($ancestor && ! is_wp_error($ancestor)) {
                            $termPath[] = $ancestor->name;
                        }
                    }
                }

                $termPath[] = (string)$row['name'];

                $cachedTermDetails[(int)$row['term_taxonomy_id']] = array(
                    'term_taxonomy_id' => (int)$row['term_taxonomy_id'],
                    'term_id'          => (int)$row['term_id'],
                    'term_name'        => (string)$row['name'],
                    'term_slug'        => (string)$row['slug'],
                    'term_path'        => implode(' › ', $termPath),
                    'taxonomy'         => $taxonomy,
                    'taxonomy_label'   => $taxonomyLabel,
                    'posts_count'      => (int)$row['count']
                );
            }

            foreach ($missingIds as $missingId) {
                if ( ! array_key_exists($missingId, $cachedTermDetails)) {
                    $cachedTermDetails[$missingId] = false;
                }
            }
        }

        $termDetails = array();

        foreach ($termTaxonomyIds as $termTaxonomyId) {
            $termDetails[$termTaxonomyId] = $cachedTermDetails[$termTaxonomyId];
        }

        return $termDetails;
    }

    /**
     * Returns readable labels for term_taxonomy_id values used by Plugins Manager rules.
     *
     * @param array $termTaxonomyIds
     *
     * @return array
     */
    public static function getTaxonomyTermLabelsByTermTaxonomyIds($termTaxonomyIds)
    {
        $termTaxonomyIds = array_values(array_unique(array_filter(array_map('absint', (array)$termTaxonomyIds))));

        if (empty($termTaxonomyIds)) {
            return array();
        }

        $termDetails = self::getTaxonomyTermDetailsByTermTaxonomyIds($termTaxonomyIds);
        $termLabels  = array();

        foreach ($termTaxonomyIds as $termTaxonomyId) {
            if (empty($termDetails[$termTaxonomyId])) {
                $termLabels[$termTaxonomyId] = sprintf(
                    __('Unknown taxonomy term — term_taxonomy_id: %d', 'wp-asset-clean-up'),
                    $termTaxonomyId
                );

                continue;
            }

            $termData      = $termDetails[$termTaxonomyId];
            $postsCount    = (int)$termData['posts_count'];
            $postsCountText = sprintf(
                _n('%s post', '%s posts', $postsCount, 'wp-asset-clean-up'),
                number_format_i18n($postsCount)
            );

            $termLabels[$termTaxonomyId] = sprintf(
                '%s: %s (%s) — ID: %d',
                $termData['taxonomy_label'],
                $termData['term_path'],
                $postsCountText,
                (int)$termData['term_id']
            );
        }

        return $termLabels;
    }

    /**
     * Returns readable labels for post IDs used by Plugins Manager singular-page rules.
     *
     * @param array $postIds
     *
     * @return array
     */
    public static function getPostLabelsByIds($postIds)
    {
        static $cachedLabels = array();

        $postIds = array_values(array_unique(array_filter(array_map('absint', (array)$postIds))));

        if (empty($postIds)) {
            return array();
        }

        $missingIds = array_values(array_diff($postIds, array_keys($cachedLabels)));

        if ( ! empty($missingIds)) {
            foreach ($missingIds as $missingId) {
                $post = get_post($missingId);

                if ( ! isset($post->ID, $post->post_type)) {
                    $cachedLabels[$missingId] = sprintf(
                        __('Unknown or deleted singular page — ID: %d', 'wp-asset-clean-up'),
                        $missingId
                    );

                    continue;
                }

                $postTypeObject = get_post_type_object($post->post_type);
                $postTypeLabel  = ($postTypeObject && ! empty($postTypeObject->labels->singular_name))
                    ? $postTypeObject->labels->singular_name
                    : $post->post_type;

                $postTitle = trim((string)$post->post_title);

                if ($postTitle === '') {
                    $postTitle = __('(no title)', 'wp-asset-clean-up');
                }

                $cachedLabels[(int)$post->ID] = sprintf(
                    '%s — %s — ID: %d',
                    $postTitle,
                    $postTypeLabel,
                    (int)$post->ID
                );
            }
        }

        return array_intersect_key($cachedLabels, array_flip($postIds));
    }

    /**
     * Returns the tooltip text used for a singular post, page or custom post type entry.
     *
     * @param \WP_Post $postData
     * @param bool     $includeTrashNotice
     *
     * @return string
     */
    public static function getPostTooltipText($postData, $includeTrashNotice = false)
    {
        if ( ! isset($postData->post_title, $postData->post_type)) {
            return '';
        }

        $postTitle = trim((string)$postData->post_title);
        $postSlug = isset($postData->post_name) ? trim(rawurldecode((string)$postData->post_name)) : '';

        if ($postTitle === '') {
            $postTitle = __('(no title)', 'wp-asset-clean-up');
        }

        if ($postSlug === '') {
            $postSlug = __('(not set)', 'wp-asset-clean-up');
        }

        $tooltipText = sprintf(
            __('Post Title: %1$s, Post Type: %2$s, Slug: %3$s', 'wp-asset-clean-up'),
            $postTitle,
            (string)$postData->post_type,
            $postSlug
        );

        if ($includeTrashNotice) {
            $tooltipText .= ' ' . __('(The post is in the Trash and restorable)', 'wp-asset-clean-up');
        }

        return $tooltipText;
    }

    /**
     * @return array
     */
    public static function getPageOptionsToText()
    {
        return array(
            'no_css_minify'      => __('Do not minify CSS', 'wp-asset-clean-up'),
            'no_css_optimize'    => __('Do not combine CSS', 'wp-asset-clean-up'),
            'no_js_minify'       => __('Do not minify JS', 'wp-asset-clean-up'),
            'no_js_optimize'     => __('Do not combine JS', 'wp-asset-clean-up'),
            'no_assets_settings' => __('Do not apply any CSS &amp; JavaScript settings', 'wp-asset-clean-up'),
            'no_wpacu_load'      => sprintf(__('Do not load %s on this page', 'wp-asset-clean-up'), WPACU_PLUGIN_TITLE)
        );
    }

    /**
     * @param string $viewOrChangeOutput
     * @param array $ruleKey
     *
     * @return string
     */
    public static function wrapRuleViewChangeOutput($viewOrChangeOutput, $ruleKey)
    {
        $mainClass = '';

        if (strpos($ruleKey, 'unload') === 0) {
            $mainClass = 'wpacu_unload_rule';
        } elseif (strpos($ruleKey, 'load') === 0) {
            $mainClass = 'wpacu_load_rule';
        }

        $content = '<ul class="' . $mainClass . '">' . "\n";
            $content .= '<li>' . $viewOrChangeOutput . '</li>' . "\n";
        $content .= '</ul>';

        return $content;

    }

    /**
     * @param $handleData
     *
     * @return array
     */
    public static function renderHandleChangesOutput($handleData)
    {
        $handleChangesOutputs  = array();

        // It could turn to "true" IF the site-wide rule is turned ON and there are other unload rules on top of it (useless ones in this case)
        $hasRedundantUnloadRules = false;

        // Site-wide
        $handleDataKey = $outputGroupKey = $ruleKey = 'unload_site_wide';

        if (isset($handleData[$handleDataKey])) {
            $handleChangeOutput = self::renderNoWrapRuleOutput(
                 '<span style="color: #cc0000;">Unloaded site-wide (everywhere)</span>',
                 $handleData,
                 $ruleKey
            );

            $handleChangesOutputs[$outputGroupKey] = self::wrapRuleViewChangeOutput($handleChangeOutput, $ruleKey);
        }

        // Bulk unload (on all posts, categories, etc.)
        $handleDataKey = 'unload_bulk';

        if (isset($handleData[$handleDataKey])) {
            if ( isset($handleData[$handleDataKey]['post_type']) && ! empty($handleData[$handleDataKey]['post_type']) ) {
                $outputGroupKey = 'unload_bulk_post_type';
                $ruleKey = 'unload_bulk_post_type';

                $handleChangesOutputs[$outputGroupKey]  = '';

                $handleChangesOutputs[$outputGroupKey] .= '<span style="color: #cc0000;">Unloaded on all pages belonging to the following post types:</span>';

                $postTypesOutputs = array();

                foreach ($handleData[$handleDataKey]['post_type'] as $postType) {
                    $output = '<span style="color: #cc0000;">' . $postType . Overview::anyNoPostTypeEntriesMsg($postType) . '</span>';

                    $postTypesOutputs[] = self::renderNoWrapRuleOutput(
                        $output,
                        $handleData,
                        $ruleKey,
                        $postType
                    );
                }

                if (self::isViewMode()) {
                    $postTypesOutputsFormatted = array_map(static function ($value) {
                        return '<strong>' . $value . '</strong>';
                    }, $postTypesOutputs);

                    $handleChangesOutputs[$outputGroupKey] .= ' ' . implode(', ', $postTypesOutputsFormatted);
                } else {
                    foreach ($postTypesOutputs as $postTypeOutput) {
                        $handleChangesOutputs[$outputGroupKey] .= $postTypeOutput;
                    }
                }

                if (isset($handleChangesOutputs['unload_site_wide'])) {
                    $handleChangesOutputs[$outputGroupKey] .= '&nbsp;<em><small>* redundant unload rule</small></em>';
                    $hasRedundantUnloadRules = true;
                }

                $handleChangesOutputs[$outputGroupKey] = self::wrapRuleViewChangeOutput($handleChangesOutputs[$outputGroupKey], $ruleKey);
            }

            // [Advanced/Pro ones]
            $filterRenderHandleChangesOutput = OverviewAdvanced::filterRenderHandleChangesOutput(
                $handleDataKey,
                $handleData,
                $handleChangesOutputs
            );
            $handleChangesOutputs = $filterRenderHandleChangesOutput['handle_changes_outputs'];
            // [/Advanced/Pro ones]
        }

        $handleDataKey = $outputGroupKey = $ruleKey = 'unload_on_home_page';

        if (isset($handleData[$handleDataKey]) && $handleData[$handleDataKey]) {
            $handleChangesOutputs[$outputGroupKey] = '';

                $output = '<span style="color: #cc0000;">Unloaded</span> on the <a target="_blank" href="' . Misc::getPageUrl(0) . '">homepage</a>';

                $handleChangesOutputs[$outputGroupKey] = self::renderNoWrapRuleOutput(
                     $output,
                     $handleData,
                     $ruleKey
                );

                if (isset($handleChangesOutputs['unload_site_wide'])) {
                    $handleChangesOutputs[$outputGroupKey] .= '&nbsp;<em><small>* redundant unload rule</small></em>';
                    $hasRedundantUnloadRules = true;
                }

                $handleChangesOutputs[$outputGroupKey] = self::wrapRuleViewChangeOutput($handleChangesOutputs[$outputGroupKey], $ruleKey);
        }

        $handleDataKey = 'unload_on_this_page';
        $outputGroupKey = 'unload_on_these_posts';

        // On this page: post, page, attachment, custom post type
        if ( ! empty($handleData[$handleDataKey]['post']) ) {
            sort($handleData[$handleDataKey]['post']);

            $ruleKey = 'unload_on_this_post';

            $wrapOutput = function ($output, $postId) use ($handleData, $ruleKey) {
                return self::renderNoWrapRuleOutput(
                     $output,
                     $handleData,
                     $ruleKey,
                     $postId
                );
            };

            $postsListArray = array();

            foreach ($handleData[$handleDataKey]['post'] as $postId) {
                $postData = get_post($postId);

                if ( ! isset($postData->post_title, $postData->post_type) ) {
                    $output = '<s class="wpacu-tooltip" title="N/A (post deleted)" style="color: #cc0000;">' . esc_html($postId) . '</s>';

                    $postsListArray[] = $wrapOutput($output, $postId);

                    continue;
                }

                $postStatus = $postData->post_status;

                $output = '<a title="' . esc_attr(self::getPostTooltipText($postData)) . '"'
                        . ' class="wpacu-tooltip"'
                        . ' target="_blank"'
                        . ' href="' . esc_url(get_edit_post_link($postId, '')) . '">'
                            . esc_html($postId)
                        . '</a>';

                $wrappedOutput = $wrapOutput($output, $postId);

                if ($postStatus === 'trash') {
                    $wrappedOutput .= '&nbsp;<span style="color: #cc0000;" title="The post is in the \'Trash\'. This rule is not relevant if the post URL is not accessible." class="wpacu-tooltip dashicons dashicons-warning"></span>';
                }

                $postsListArray[] = $wrappedOutput;
            }

            $handleChangesOutputs[$outputGroupKey] = '<span style="color: #cc0000;">Unloaded on these specific posts, pages, products, or other entries:</span> ';

            if (self::isViewMode()) {
                $postsListArrayFormatted = array_map(static function ($value) {
                    return $value;
                }, $postsListArray);

                $handleChangesOutputs[$outputGroupKey] .= implode(', ', $postsListArrayFormatted);
            } else {
                $handleChangesOutputs[$outputGroupKey] .= implode('', $postsListArray);
            }

            if (isset($handleChangesOutputs['unload_site_wide'])) {
                $handleChangesOutputs[$outputGroupKey] .= '&nbsp;<em><small>* redundant unload rule</small></em>';
                $hasRedundantUnloadRules = true;
            }

            $handleChangesOutputs[$outputGroupKey] = Overview::wrapRuleViewChangeOutput($handleChangesOutputs[$outputGroupKey], $ruleKey);
        }

        // [Advanced/Pro ones]
        $filterRenderHandleChangesOutput = OverviewAdvanced::filterRenderHandleChangesOutput(
            'unload_on_this_page',
            $handleData,
            $handleChangesOutputs,
            $hasRedundantUnloadRules
        );

        $handleChangesOutputs    = $filterRenderHandleChangesOutput['handle_changes_outputs'];
        $hasRedundantUnloadRules = $filterRenderHandleChangesOutput['has_redundant_rules'];

        $filterRenderHandleChangesOutput = OverviewAdvanced::filterRenderHandleChangesOutput(
            'unload_regex',
            $handleData,
            $handleChangesOutputs,
            $hasRedundantUnloadRules
        );

        $handleChangesOutputs    = $filterRenderHandleChangesOutput['handle_changes_outputs'];
        $hasRedundantUnloadRules = $filterRenderHandleChangesOutput['has_redundant_rules'];
        // [/Advanced/Pro ones]

        // Maybe it has other unload rules on top of the site-wide one (which covers everything)
        if ($hasRedundantUnloadRules) {
            $clearRedundantUnloadRulesArea = '';

            $ruleKey = 'unload_redundant';

            if (self::isEditMode()) {
                $textClear = __('Clear all redundant unload rules for this handle', 'wp-asset-clean-up');

                $clearRedundantUnloadRulesArea = self::renderNoWrapRuleOutput(
                     $textClear,
                     $handleData,
                     $ruleKey,
                     1
                );
            }

            $hasRedundantUnloadRulesNotice = '<em><small><strong>Note:</strong> The handle has already a bulk rule (e.g. site-wide) applied, and it overwrites any existing per page rules, thus, the redundant rules could be removed.</small></em>&nbsp;';
            $handleChangesOutputs['has_redundant_unload_rules'] = self::wrapRuleViewChangeOutput(
                $hasRedundantUnloadRulesNotice . $clearRedundantUnloadRulesArea,
                $ruleKey
            );
        }

        if ( isset($handleData['ignore_child']) && $handleData['ignore_child'] ) {
            $handleChangesOutputs['ignore_child'] = self::renderRuleOutput(
                 'If unloaded by any rule, ignore dependencies and keep its "children" loaded',
                 $handleData,
                 'ignore_child',
                 1 // presence-based rule, default value
            );
        }

        // Load exceptions? Per page, via RegEx, if user is logged-in
        $handleDataKey = $ruleKey =  'load_exception_on_home_page';
        $outputGroupKey = $handleDataKey;

        if (isset($handleData[$handleDataKey]) && $handleData[$handleDataKey]) {
            $homepageUrl = Misc::getPageUrl(0);
            $label       = 'Loaded (as an exception) on the <a target="_blank" href="' . esc_url($homepageUrl) . '">homepage</a>';

            $handleChangesOutputs[$outputGroupKey] = self::renderNoWrapRuleOutput(
                 '<span style="color: green;">' . $label . '</span>',
                 $handleData,
                 $ruleKey,
                 1 // boolean true as value for presence-based rule
            );

            $handleChangesOutputs[$outputGroupKey] = self::wrapRuleViewChangeOutput($handleChangesOutputs[$outputGroupKey], $ruleKey);
        }

        if ( ! empty($handleData['load_exception_on_this_page']['post']) ) {
            $outputGroupKey = 'load_exception_on_this_post';
            $ruleKey = 'load_exception_on_this_post';

            $handleChangesOutputs[$outputGroupKey] = '<span style="color: green;">Loaded (as an exception) on these specific posts, pages, products, or other entries: ';

            $postsListArray = array();

            sort($handleData['load_exception_on_this_page']['post']);

            foreach ($handleData['load_exception_on_this_page']['post'] as $postId) {
                $postData = get_post($postId);

                if (isset($postData->post_title, $postData->post_type)) {
                    $output = '<a title="' . esc_attr(self::getPostTooltipText($postData)) . '" class="wpacu-tooltip" target="_blank" href="' . esc_url(admin_url('post.php?post=' . $postId . '&action=edit')) . '">' . $postId . '</a>';
                } else {
                    $output = '<s class="wpacu-tooltip" title="N/A (post deleted)" style="color: #cc0000;">' . $postId . '</s>';
                }

                $postsListArray[] = self::renderNoWrapRuleOutput(
                     $output,
                     $handleData,
                     $ruleKey,
                     $postId
                );
            }

            if (Overview::isViewMode()) {
                $postsListArrayFormatted = array_map(static function ($value) {
                    return $value;
                }, $postsListArray);

                $handleChangesOutputs[$outputGroupKey] .= implode(', ', $postsListArrayFormatted);
            } else {
                $handleChangesOutputs[$outputGroupKey] .= implode(' ', $postsListArray);
            }

            $handleChangesOutputs[$outputGroupKey] .= '</span>';

            $handleChangesOutputs[$outputGroupKey] = self::wrapRuleViewChangeOutput($handleChangesOutputs[$outputGroupKey], 'load_exception_on_this_post');
        }

        // Note: If "self::wrapRuleViewChangeOutput" is used at the end (whole content)
        // then "self::renderNoWrapRuleOutput" has to be used for the rules!
        // This is applied to any rule (unload or load exception)

        // e.g. Unloaded site-wide, but loaded on all 'product' (WooCommerce) pages
        if (isset($handleData['load_exception_post_type'])) {
            $outputGroupKey = 'load_exception_post_type';
            $ruleKey = 'load_exception_post_type';

            $handleChangesOutputs[$outputGroupKey] = '<span style="color: green;">Loaded (as an exception)</span> in all pages of the following post types: ';

            $postTypesListArray = array();

            sort($handleData['load_exception_post_type']);

            foreach ($handleData['load_exception_post_type'] as $postType) {
                $output = self::renderNoWrapRuleOutput(
                    '<strong>' . $postType . '</strong>' . Overview::anyNoPostTypeEntriesMsg($postType),
                    $handleData,
                    $ruleKey,
                    $postType
                );

                $postTypesListArray[] = $output;
            }

            if (Overview::isViewMode()) {
                $postTypesListArrayFormatted = array_map(static function ($value) {
                    return '<strong>' . $value . '</strong>';
                }, $postTypesListArray);

                $handleChangesOutputs[$outputGroupKey] .= implode(', ', $postTypesListArrayFormatted);
            } else {
                $handleChangesOutputs[$outputGroupKey] .= implode(' ', $postTypesListArray);
            }

            $handleChangesOutputs[$outputGroupKey] = self::wrapRuleViewChangeOutput($handleChangesOutputs[$outputGroupKey], $ruleKey);
        }

        // [Advanced/Pro ones]
        $filterRenderHandleChangesOutput = OverviewAdvanced::filterRenderHandleChangesOutput(
            'load_exceptions',
            $handleData,
            $handleChangesOutputs
        );

        $handleChangesOutputs  = $filterRenderHandleChangesOutput['handle_changes_outputs'];
        // [/Advanced/Pro ones]

        if (isset($handleData['load_it_logged_in']) && $handleData['load_it_logged_in']) {
            if (MiscArray::hasKeyStartingWith($handleChangesOutputs, 'load')) { // any load exception already set?
                $textToShow = ' <strong>or</strong> <span style="color: green;">if the user is logged-in</span>';
            } else {
                $textToShow = '<span style="color: green;">Loaded (as an exception)</span> if the user is logged-in';
            }

            $handleChangesOutputs['load_it_logged_in'] = self::renderRuleOutput(
                 $textToShow,
                 $handleData,
                 'load_it_logged_in',
                 1 // presence-based rule, default value
            );
        }

        // Since more than one load exception rule is set, merge them on the same row to save space and avoid duplicated words
        if (isset($handleChangesOutputs['load_exception_on_this_post'], $handleChangesOutputs['load_exception_regex'])) {
            $handleChangesOutputs['load_exception_all'] = $handleChangesOutputs['load_exception_on_this_post'] . $handleChangesOutputs['load_exception_regex'];
            unset($handleChangesOutputs['load_exception_on_this_post'], $handleChangesOutputs['load_exception_regex']);
        }

        $anyUnloadRule        = MiscArray::hasKeyStartingWith($handleChangesOutputs, 'unload');
        $anyLoadExceptionRule = MiscArray::hasKeyStartingWith($handleChangesOutputs, 'load');

        if ( ! $anyUnloadRule && $anyLoadExceptionRule ) {
            $clearLoadExceptionsArea = '';

            if (self::isEditMode()) {
                $labelText = __('Clear all load exceptions for this handle', 'wp-asset-clean-up');

                $clearLoadExceptionsArea = self::renderRuleOutput(
                     $labelText,
                     $handleData,
                     'load_exceptions_clear_all',
                     1
                );
            }

            $handleChangesOutputs['load_exception_notice'] = '<div><em><small><strong>Note:</strong> Although a load exception rule is added, it is not relevant as there are no rules that would work together with it (e.g. unloaded site-wide, on all posts). This exception can be removed as the file is loaded anyway in all pages.</small></em>&nbsp;' .
                                                            ' ' . $clearLoadExceptionsArea . '</div><div style="clear:both;"></div>';
        }

        return $handleChangesOutputs;
    }

    /**
     * @param string $output
     * @param array  $handleData
     * @param string $ruleKey
     * @param mixed  $ruleValue
     * @param mixed  $ruleParentValue
     *
     * @return mixed|string
     */
    public static function renderRuleOutput($output, $handleData, $ruleKey, $ruleValue = null, $ruleParentValue = '')
    {
        if ($ruleValue === null) {
            return OverviewEdit::renderMaybeEditSettingChangesWrapOutputRule(
                $output,
                $handleData,
                $ruleKey
            );
        }

        return OverviewEdit::renderMaybeEditSettingChangesWrapOutputRule(
            $output,
            $handleData,
            $ruleKey,
            $ruleValue,
            $ruleParentValue
        );
    }

    /**
     * @param string $output
     * @param array  $handleData
     * @param string $ruleKey
     * @param mixed  $ruleValue
     * @param mixed  $ruleParentValue
     *
     * @return mixed|string
     */
    public static function renderNoWrapRuleOutput($output, $handleData, $ruleKey, $ruleValue = null, $ruleParentValue = '')
    {
        $handleData['no_wrap'] = true;

        return self::renderRuleOutput(
            $output,
            $handleData,
            $ruleKey,
            $ruleValue,
            $ruleParentValue
        );
    }

    /**
     * @return bool
     */
    public static function isViewMode()
    {
        return ! Overview::isEditMode();
    }

    /**
     * @return bool
     */
    public static function isEditMode()
    {
        return isset($_GET['wpacu_edit_mode']) && $_GET['wpacu_edit_mode'];
    }

    /**
     * @return array
     */
    public static function handlesWithAtLeastOneRule()
    {
        global $wpdb;

        $wpacuPluginId = WPACU_PLUGIN_ID;

        $allHandles = array();

        /*
         * Per page rules (unload, load exceptions if a bulk rule is enabled, async & defer for SCRIPT tags)
         */
        // Homepage (Unloads)
        $wpacuFrontPageUnloads = get_option(WPACU_PLUGIN_ID . '_front_page_no_load');

        if ($wpacuFrontPageUnloads) {
            $wpacuFrontPageUnloadsArray = wpacuJsonDecodeToArray($wpacuFrontPageUnloads);

            foreach (array('styles', 'scripts') as $assetType) {
                if ( ! empty($wpacuFrontPageUnloadsArray[$assetType])) {
                    foreach ($wpacuFrontPageUnloadsArray[$assetType] as $assetHandle) {
                        $allHandles[$assetType][$assetHandle]['unload_on_home_page'] = 1;
                        }
                }
            }
        }

        // Homepage (Load Exceptions)
        $wpacuFrontPageLoadExceptions = get_option(WPACU_PLUGIN_ID . '_front_page_load_exceptions');

        if ($wpacuFrontPageLoadExceptions) {
            $wpacuFrontPageLoadExceptionsArray = wpacuJsonDecodeToArray($wpacuFrontPageLoadExceptions);

            foreach (array('styles', 'scripts') as $assetType) {
                if ( ! empty($wpacuFrontPageLoadExceptionsArray[$assetType])) {
                    foreach ($wpacuFrontPageLoadExceptionsArray[$assetType] as $assetHandle) {
                        $allHandles[$assetType][$assetHandle]['load_exception_on_home_page'] = 1;
                        }
                }
            }
        }

        // Homepage (async, defer)
        $wpacuFrontPageData = get_option(WPACU_PLUGIN_ID . '_front_page_data');

        if ($wpacuFrontPageData) {
            $wpacuFrontPageDataArray = wpacuJsonDecodeToArray($wpacuFrontPageData);
            if ( ! empty($wpacuFrontPageDataArray['scripts'])) {
                foreach ($wpacuFrontPageDataArray['scripts'] as $assetHandle => $assetData) {
                    if ( ! empty($assetData['attributes'])) {
                        // async, defer attributes
                        $allHandles['scripts'][$assetHandle]['script_attrs']['home_page'] = $assetData['attributes'];
                        }
                }
            }

            // Do not apply "async", "defer" exceptions (e.g. "defer" is applied site-wide, except the home page)
            if ( ! empty($wpacuFrontPageDataArray['scripts_attributes_no_load'])) {
                foreach ($wpacuFrontPageDataArray['scripts_attributes_no_load'] as $assetHandle => $assetAttrsNoLoad) {
                    $allHandles['scripts'][$assetHandle]['attrs_no_load']['home_page'] = $assetAttrsNoLoad;
                    }
            }
        }

        // Custom Post Type Load Exceptions
        // e.g. the asset could be unloaded site-wide and loaded on all pages belonging to a post type such as WooCommerce single 'product' page
        $wpacuPostTypeLoadExceptions = get_option(WPACU_PLUGIN_ID . '_post_type_load_exceptions');

        if ($wpacuPostTypeLoadExceptions) {
            $wpacuPostTypeLoadExceptionsArray = wpacuJsonDecodeToArray($wpacuPostTypeLoadExceptions);

            foreach ($wpacuPostTypeLoadExceptionsArray as $wpacuPostType => $dbAssetHandles) {
                foreach (array('styles', 'scripts') as $assetType) {
                    if (isset($dbAssetHandles[$assetType]) && $dbAssetHandles[$assetType]) {
                        foreach ($dbAssetHandles[$assetType] as $assetHandle => $assetValue) {
                            if ($assetValue !== '') {
                                $allHandles[$assetType][$assetHandle]['load_exception_post_type'][] = $wpacuPostType;
                                }
                        }
                    }
                }
            }
        }

        $allHandles = OverviewAdvanced::filterHandlesWithAtLeastOneRule('load_exceptions', $allHandles);

        // Get all Asset CleanUp (Pro) meta keys from all WordPress meta tables where it can be possibly used
        foreach (array($wpdb->postmeta, $wpdb->termmeta, $wpdb->usermeta) as $tableName) {
            $wpacuGetValuesQuery = <<<SQL
SELECT * FROM `{$tableName}`
WHERE meta_key IN('_{$wpacuPluginId}_no_load', '_{$wpacuPluginId}_data', '_{$wpacuPluginId}_load_exceptions')
SQL;
            $wpacuMetaData       = $wpdb->get_results($wpacuGetValuesQuery, ARRAY_A);

            foreach ($wpacuMetaData as $wpacuValues) {
                $decodedValues = @json_decode($wpacuValues['meta_value'], ARRAY_A);

                if (empty($decodedValues)) {
                    continue;
                }

                // $refId is the ID for the targeted element from the meta table which could be: post, taxonomy ID or user ID
                if ($tableName === $wpdb->postmeta) {
                    $refId  = $wpacuValues['post_id'];
                    $refKey = 'post';
                } elseif ($tableName === $wpdb->termmeta) {
                    $refId  = $wpacuValues['term_id'];
                    $refKey = 'term';
                } else {
                    $refId  = $wpacuValues['user_id'];
                    $refKey = 'user';
                }

                if ($wpacuValues['meta_key'] === '_' . $wpacuPluginId . '_no_load') {
                    foreach ($decodedValues as $assetType => $assetHandles) {
                        foreach ($assetHandles as $assetHandle) {
                            // Unload it on this page
                            $allHandles[$assetType][$assetHandle]['unload_on_this_page'][$refKey][] = $refId;
                            }
                    }
                } elseif ($wpacuValues['meta_key'] === '_' . $wpacuPluginId . '_load_exceptions') {
                    foreach ($decodedValues as $assetType => $assetHandles) {
                        foreach ($assetHandles as $assetHandle) {
                            // If bulk unloaded, 'Load it on this page'
                            $allHandles[$assetType][$assetHandle]['load_exception_on_this_page'][$refKey][] = $refId;
                            }
                    }
                } elseif ($wpacuValues['meta_key'] === '_' . $wpacuPluginId . '_data') {
                    if ( ! empty($decodedValues['scripts'])) {
                        foreach ($decodedValues['scripts'] as $assetHandle => $scriptData) {
                            if ( ! empty($scriptData['attributes'])) {
                                // async, defer attributes
                                $allHandles['scripts'][$assetHandle]['script_attrs'][$refKey][$refId] = $scriptData['attributes'];
                                }
                        }
                    }

                    if ( ! empty($decodedValues['scripts_attributes_no_load'])) {
                        foreach ($decodedValues['scripts_attributes_no_load'] as $assetHandle => $scriptNoLoadAttrs) {
                            $allHandles['scripts'][$assetHandle]['attrs_no_load'][$refKey][$refId] = $scriptNoLoadAttrs;
                            }
                    }
                }
            }
        }

        /*
         * Global (Site-wide) Rules: Preloading, Position changing, Unload via RegEx, etc.
         */
        $wpacuGlobalDataArray = wpacuGetGlobalData();

        $allPossibleDataTypesCommon = array(
            'load_it_logged_in',
            'preloads',
            'notes',
            'ignore_child',
            'everywhere'
        );

        $allPossibleDataTypesProOnes = array(
            'positions',
            'media_queries_load',
            'date',
            '404',
            'search'
        );

        $allPossibleDataTypes = array_merge($allPossibleDataTypesCommon, $allPossibleDataTypesProOnes);

        foreach (array('styles', 'scripts') as $assetType) {
            if ($assetType === 'scripts' && isset($wpacuGlobalDataArray[$assetType])) {
                foreach (array_keys($wpacuGlobalDataArray[$assetType]) as $dataType) {
                    if (strpos($dataType, 'custom_post_type_archive_') !== false) {
                        $allPossibleDataTypes[] = $dataType;
                    }
                }

                }

            foreach ($allPossibleDataTypes as $dataType) {
                if ( ! empty($wpacuGlobalDataArray[$assetType][$dataType])) {
                    foreach ($wpacuGlobalDataArray[$assetType][$dataType] as $assetHandle => $dataValue) {
                        if ($dataType === 'everywhere' && $assetType === 'scripts' && isset($dataValue['attributes'])) {
                            if (count($dataValue['attributes']) === 0) {
                                continue;
                            }
                            // async/defer applied site-wide
                            $allHandles[$assetType][$assetHandle]['script_site_wide_attrs'] = $dataValue['attributes'];
                            } elseif ($dataType !== 'everywhere' && $assetType === 'scripts' && isset($dataValue['attributes'])) {
                            // For date, 404, search pages
                            $allHandles[$assetType][$assetHandle]['script_attrs'][$dataType] = $dataValue['attributes'];
                            } else {
                            $allHandles[$assetType][$assetHandle][$dataType] = $dataValue;
                            }
                    }
                }
            }

            // [Advanced/Pro ones]
            foreach (array('unload_regex', 'load_regex') as $unloadType) {
                if ( ! empty($wpacuGlobalDataArray[$assetType][$unloadType])) {
                    foreach ($wpacuGlobalDataArray[$assetType][$unloadType] as $assetHandle => $unloadData) {
                        if (isset($unloadData['enable'], $unloadData['value']) && $unloadData['enable'] && $unloadData['value']) {
                            $allHandles[$assetType][$assetHandle][$unloadType] = $unloadData['value'];
                            }
                    }
                }
            }
            // [Advanced/Pro ones]
        }

        // [Advanced/Pro ones]
        // Do not apply "async", "defer" exceptions (e.g. "defer" is applied site-wide, except the 404, search, date)
        if ( ! empty($wpacuGlobalDataArray['scripts_attributes_no_load'])) {
            foreach ($wpacuGlobalDataArray['scripts_attributes_no_load'] as $unloadedIn => $unloadedInValues) {
                foreach ($unloadedInValues as $assetHandle => $assetAttrsNoLoad) {
                    $allHandles['scripts'][$assetHandle]['attrs_no_load'][$unloadedIn] = $assetAttrsNoLoad;
                    }
            }
        }
        // [Advanced/Pro ones]

        /*
         * Unload Site-Wide (Everywhere) Rules: Preloading, Position changing, Unload via RegEx, etc.
         */
        $wpacuGlobalUnloadData      = get_option(WPACU_PLUGIN_ID . '_global_unload');
        $wpacuGlobalUnloadDataArray = wpacuJsonDecodeToArray($wpacuGlobalUnloadData);

        foreach (array('styles', 'scripts') as $assetType) {
            if ( ! empty($wpacuGlobalUnloadDataArray[$assetType])) {
                foreach ($wpacuGlobalUnloadDataArray[$assetType] as $assetHandle) {
                    $allHandles[$assetType][$assetHandle]['unload_site_wide'] = 1;
                    }
            }
        }

        /*
        * Bulk Unload Rules - post, page, custom post type (e.g. product, download), taxonomy (e.g. category), 404, date, archive (for custom post type) with pagination etc.
        */
        $wpacuBulkUnloadData      = get_option(WPACU_PLUGIN_ID . '_bulk_unload');
        $wpacuBulkUnloadDataArray = wpacuJsonDecodeToArray($wpacuBulkUnloadData);

        foreach (array('styles', 'scripts') as $assetType) {
            if ( ! empty($wpacuBulkUnloadDataArray[$assetType])) {
                foreach ($wpacuBulkUnloadDataArray[$assetType] as $unloadBulkType => $unloadBulkValues) {
                    if (empty($unloadBulkValues)) {
                        continue;
                    }

                    // $unloadBulkType could be 'post_type', 'post_type_via_tax', 'date', '404', 'taxonomy', 'search', 'custom_post_type_archive_[post_type_name_here]', etc.
                    if ($unloadBulkType === 'post_type') {
                        foreach ($unloadBulkValues as $postType => $assetHandles) {
                            foreach ($assetHandles as $assetHandle) {
                                $allHandles[$assetType][$assetHandle]['unload_bulk'][$unloadBulkType][] = $postType;
                                }
                        }
                    }

                    // [Advanced/Pro ones]
                    $allHandles = OverviewAdvanced::filterHandlesWithAtLeastOneRule(
                        'unload_bulk',
                        $allHandles,
                        array(
                            'unload_bulk_type'   => $unloadBulkType,
                            'unload_bulk_values' => $unloadBulkValues,
                            'asset_type'         => $assetType
                        )
                    );
                    // [/Advanced/Pro ones]
                }
            }
        }

        if (isset($allHandles['styles'])) {
            ksort($allHandles['styles']);
        }

        if (isset($allHandles['scripts'])) {
            ksort($allHandles['scripts']);
        }

        return $allHandles;
    }

    /**
     *
     */
    public function pageOverview()
    {
        $allHandles = self::handlesWithAtLeastOneRule();
        $this->data['handles'] = $allHandles;

        if (isset($this->data['handles']['styles']) || isset($this->data['handles']['scripts'])) {
            // Only fetch the assets' information if there is something to be shown
            // to avoid useless queries to the database
            $this->data['assets_info'] = Main::getHandlesInfo();
            $this->data['external_srcs_ref'] = AssetsManager::setExternalSrcsRef($this->data['assets_info'], 'overview');
        }

        // [PAGE OPTIONS]
        // 1) For posts, pages and custom post types
        global $wpdb;

        $this->data['page_options_results'] = array();

        $pageOptionsResults = $wpdb->get_results('SELECT post_id, meta_value FROM `' . $wpdb->postmeta . "` WHERE meta_key='_" . WPACU_PLUGIN_ID . "_page_options' && meta_value !=''",
                ARRAY_A);

        foreach ($pageOptionsResults as $pageOptionResult) {
            $postId         = $pageOptionResult['post_id'];
            $optionsDecoded = @json_decode($pageOptionResult['meta_value'], ARRAY_A);

            if (is_array($optionsDecoded) && ! empty($optionsDecoded)) {
                $this->data['page_options_results']['posts'][] = array(
                    'post_id' => $postId,
                    'options' => $optionsDecoded
                );
            }
        }

        // 2) For the homepage set as latest posts (e.g. not a single page set as the front page, this is included in the previous check)
        $globalPageOptionsList = wpacuGetGlobalData();

        if ( ! empty($globalPageOptionsList['page_options']['homepage'])) {
            $this->data['page_options_results']['homepage'] = array('options' => $globalPageOptionsList['page_options']['homepage']);
        }
        // [/PAGE OPTIONS]

        // [CRITICAL CSS]
        $this->data['critical_css_disabled'] = false;
        $this->data['critical_css_config'] = array();
        $this->data['critical_css_overview'] = array(
            'locations'      => array(),
            'rules_count'    => 0,
            'general_count'  => 0,
            'specific_count' => 0
        );

        if (Main::instance()->settings['critical_css_status'] === 'off') {
            $this->data['critical_css_disabled'] = true;
        }

        $criticalCssConfigOption = get_option(WPACU_PLUGIN_ID . '_critical_css_config');

        if ($criticalCssConfigOption) {
            $criticalCssConfig = wpacuJsonDecodeToArray($criticalCssConfigOption);

            if (wpacuJsonLastError() === JSON_ERROR_NONE && is_array($criticalCssConfig)) {
                $this->data['critical_css_config'] = $criticalCssConfig;
            }
        }

        $this->data['critical_css_overview'] = CriticalCssAdmin::getEnabledCriticalCssOverviewData(
            $this->data['critical_css_config']
        );
        // [/CRITICAL CSS]

        $this->data['input_style'] = Settings::getInputStyle(Main::instance()->settings);

        // [Advanced/Pro ones]
        $this->data = OverviewAdvanced::getPageOverviewData($this->data);
        // [/Advanced/Pro ones]

        MainAdmin::instance()->parseTemplate('admin-page-overview', $this->data, true);
    }

    /**
     * @param array  $handleData
     * @param string $attr
     *
     * @return string
     */
    private static function getScriptAttrNoLoadExceptionsOutput($handleData, $attr)
    {
        if (empty($handleData['attrs_no_load']) || ! is_array($handleData['attrs_no_load'])) {
            return '';
        }

        $itemsLimit = self::isEditMode() ? 0 : 2;

        $handle    = isset($handleData['handle']) ? $handleData['handle'] : '';
        $assetType = isset($handleData['asset_type']) ? $handleData['asset_type'] : '';

        $uniqueBase = $assetType . '|' . $handle . '|' . $attr;

        $exceptions = array();

        // Per post/page/custom post type
        if ( ! empty($handleData['attrs_no_load']['post']) ) {
            $postItems = array();

            ksort($handleData['attrs_no_load']['post']);

            foreach ($handleData['attrs_no_load']['post'] as $postId => $attrList) {
                if ( ! in_array($attr, $attrList, true)) {
                    continue;
                }

                $postItems[] = self::renderScriptAttrRuleOutput(
                    self::getScriptAttrPostLabel($postId),
                    $handleData,
                    'post_script_attr_no_load',
                    $attr,
                    $postId
                );
            }

            if ( ! empty($postItems)) {
                $exceptions[] = '<strong>Posts:</strong> ' . self::maybeCollapseScriptAttrItemsOutput(
                    $postItems,
                    $itemsLimit,
                    $uniqueBase . '|posts'
                );
            }
        }

        // Per taxonomy term page
        if ( ! empty($handleData['attrs_no_load']['term']) ) {
            $termItems = array();

            ksort($handleData['attrs_no_load']['term']);

            foreach ($handleData['attrs_no_load']['term'] as $termId => $attrList) {
                if ( ! in_array($attr, $attrList, true)) {
                    continue;
                }

                $termItems[] = self::renderScriptAttrRuleOutput(
                    self::getScriptAttrTermLabel($termId),
                    $handleData,
                    'taxonomy_term_script_attr_no_load',
                    $attr,
                    $termId
                );
            }

            if ( ! empty($termItems) ) {
                $exceptions[] = '<strong>Taxonomies:</strong> ' . self::maybeCollapseScriptAttrItemsOutput(
                    $termItems,
                    $itemsLimit,
                    $uniqueBase . '|taxonomies'
                );
            }
        }

        // Per author archive
        if ( ! empty($handleData['attrs_no_load']['user']) ) {
            $userItems = array();

            ksort($handleData['attrs_no_load']['user']);

            foreach ($handleData['attrs_no_load']['user'] as $userId => $attrList) {
                if ( ! in_array($attr, $attrList, true)) {
                    continue;
                }

                $userItems[] = self::renderScriptAttrRuleOutput(
                    self::getScriptAttrUserLabel($userId),
                    $handleData,
                    'author_archive_script_attr_no_load',
                    $attr,
                    $userId
                );
            }

            if ( ! empty($userItems)) {
                $exceptions[] = '<strong>Authors:</strong> ' . self::maybeCollapseScriptAttrItemsOutput(
                    $userItems,
                    $itemsLimit,
                    $uniqueBase . '|authors'
                );
            }
        }

        // Bulk pages: search/date/404/custom post type archive
        // Other pages: homepage/search/date/404/custom post type archive
        $otherPageItems = array();

        foreach ($handleData['attrs_no_load'] as $scopeKey => $attrList) {
            if (in_array($scopeKey, array('post', 'term', 'user'), true) || empty($attrList) || ! is_array($attrList)) {
                continue;
            }

            if ( ! in_array($attr, $attrList, true)) {
                continue;
            }

            $scopeLabel = $scopeKey;

            if ($scopeKey === 'home_page') {
                $scopeLabel = 'Homepage';
            } elseif ($scopeKey === 'date') {
                $scopeLabel = 'Date archive';
            } elseif ((string)$scopeKey === '404') {
                $scopeLabel = '404 page';
            } elseif ($scopeKey === 'search') {
                $scopeLabel = 'Search results';
            } elseif (strpos($scopeKey, 'custom_post_type_archive_') === 0) {
                $scopeLabel = 'CPT archive: ' . str_replace('custom_post_type_archive_', '', $scopeKey);
            }

            $otherPageItems[] = self::renderScriptAttrRuleOutput(
                esc_html($scopeLabel),
                $handleData,
                'bulk_script_attr_no_load',
                $attr,
                $scopeKey
            );
        }

        if ( ! empty($otherPageItems)) {
            $exceptions[] = '<strong>Other pages:</strong> ' . self::maybeCollapseScriptAttrItemsOutput(
                $otherPageItems,
                $itemsLimit,
                $uniqueBase . '|other_pages'
            );
        }

        return implode(' | ', $exceptions);
    }

    /**
     * @param array $handleData
     *
     * @return array
     */
    private static function getScriptAttrNoLoadAttrs($handleData)
    {
        if (empty($handleData['attrs_no_load']) || ! is_array($handleData['attrs_no_load'])) {
            return array();
        }

        $attrs = array();

        foreach ($handleData['attrs_no_load'] as $scopeKey => $attrList) {
            if (in_array($scopeKey, array('post', 'term', 'user'), true)) {
                foreach ($attrList as $objectAttrList) {
                    if (is_array($objectAttrList)) {
                        $attrs = array_merge($attrs, $objectAttrList);
                    }
                }

                continue;
            }

            if (is_array($attrList)) {
                $attrs = array_merge($attrs, $attrList);
            }
        }

        return array_values(array_unique($attrs));
    }

    /**
     * @param $items
     * @param $limit
     * @param $uniqueContext
     *
     * @return string
     */
    private static function maybeCollapseScriptAttrItemsOutput($items, $limit = 4, $uniqueContext = '')
    {
        if ($limit < 1 || count($items) <= $limit) {
            return implode(', ', $items);
        }

        $visibleItems = array_slice($items, 0, $limit);
        $hiddenItems  = array_slice($items, $limit);
        $moreText     = sprintf(__('View %d more', 'wp-asset-clean-up'), count($hiddenItems));
        $lessText     = __('Show less', 'wp-asset-clean-up');
        $uniqueId     = 'wpacu_more_' . md5($uniqueContext . '|' . implode('|', array_map('wp_strip_all_tags', $items)));

        return implode(', ', $visibleItems)
            . ' <span id="' . esc_attr($uniqueId) . '" class="wpacu-script-attr-more-items" style="display:none;">, '
                  . implode(', ', $hiddenItems)
            . '</span>'
            . ' <span class="wpacu-script-attr-toggle-wrap">('
              . '<a href="#"'
                . ' class="wpacu-script-attr-toggle-more"'
                . ' data-wpacu-target="' . esc_attr($uniqueId) . '"'
                . ' data-wpacu-more-text="' . esc_attr($moreText) . '"'
                . ' data-wpacu-less-text="' . esc_attr($lessText) . '">'
                    . esc_html($moreText)
              . '</a>'
            . ')</span>';
    }

    /**
     * @param array $handleData
     *
     * @return string
     */
    private static function renderScriptAttrsOverviewOutput($handleData)
    {
        $scriptAttrs = isset($handleData['script_attrs']) && is_array($handleData['script_attrs'])
            ? $handleData['script_attrs']
            : array();

        $scriptSiteWideAttrs = isset($handleData['script_site_wide_attrs']) && is_array($handleData['script_site_wide_attrs'])
            ? $handleData['script_site_wide_attrs']
            : array();

        $scriptAttrsNoLoad = self::getScriptAttrNoLoadAttrs($handleData);

        if (empty($scriptAttrs) && empty($scriptSiteWideAttrs) && empty($scriptAttrsNoLoad)) {
            return '';
        }

        $groupedAttrs = array();

        // Site-wide attributes, with optional exceptions from applying
        if ( ! empty($scriptSiteWideAttrs) ) {
            foreach ($scriptSiteWideAttrs as $attr) {
                $siteWideOutput = self::renderScriptAttrRuleOutput(
                    'All pages',
                    $handleData,
                    'site_wide_script_attr',
                    $attr
                );

                $exceptionsOutput = self::getScriptAttrNoLoadExceptionsOutput($handleData, $attr);

                if ($exceptionsOutput) {
                    $siteWideOutput .= '<span class="wpacu-script-attr-exceptions">
                        <span class="wpacu-script-attr-except-badge">except</span>
                        '.$exceptionsOutput.'
                    </span>';
                }

                self::addScriptAttrOverviewItem(
                    $groupedAttrs,
                    $attr,
                    'Site-wide',
                    $siteWideOutput
                );
            }
        }

        /*
         * Orphan "do not apply this site-wide attribute here" rules.
         * These can remain after the main site-wide async/defer rule is cleared.
         */
        $orphanAttrsNoLoad = array_diff($scriptAttrsNoLoad, $scriptSiteWideAttrs);

        foreach ($orphanAttrsNoLoad as $attr) {
            $exceptionsOutput = self::getScriptAttrNoLoadExceptionsOutput($handleData, $attr);

            if ( ! $exceptionsOutput) {
                continue;
            }

            self::addScriptAttrOverviewItem(
                $groupedAttrs,
                $attr,
                '<span style="font-weight: 200;">No-load leftovers</span>',
                $exceptionsOutput
            );
        }

        // Homepage
        if ( ! empty($scriptAttrs['home_page']) ) {
            foreach ($scriptAttrs['home_page'] as $attr) {
                self::addScriptAttrOverviewItem(
                    $groupedAttrs,
                    $attr,
                    'Homepage',
                    self::renderScriptAttrRuleOutput('Homepage', $handleData, 'home_page_script_attr', $attr)
                );
            }
        }

        // Date / 404 / Search
        $simpleArchiveTypes = array(
            'date'   => array('Date archives', 'bulk_script_attr'),
            '404'    => array('404 page', 'bulk_script_attr'),
            'search' => array('Search results', 'bulk_script_attr')
        );

        foreach ($simpleArchiveTypes as $scriptAttrKey => $scriptAttrData) {
            if (empty($scriptAttrs[$scriptAttrKey])) {
                continue;
            }

            foreach ($scriptAttrs[$scriptAttrKey] as $attr) {
                self::addScriptAttrOverviewItem(
                    $groupedAttrs,
                    $attr,
                    $scriptAttrData[0],
                    self::renderScriptAttrRuleOutput($scriptAttrData[0], $handleData, $scriptAttrData[1], $attr, $scriptAttrKey)
                );
            }
        }

        // Archive pages for Custom Post Types
        foreach ($scriptAttrs as $scriptAttrsKey => $attrList) {
            if (strpos($scriptAttrsKey, 'custom_post_type_archive_') !== 0 || empty($attrList)) {
                continue;
            }

            $customPostTypeName = str_replace('custom_post_type_archive_', '', $scriptAttrsKey);

            foreach ($attrList as $attr) {
                self::addScriptAttrOverviewItem(
                    $groupedAttrs,
                    $attr,
                    'CPT archives',
                    self::renderScriptAttrRuleOutput(
                        '<strong>' . esc_html($customPostTypeName) . '</strong>',
                        $handleData,
                        'custom_post_type_archive_script_attr',
                        $attr,
                        $customPostTypeName
                    )
                );
            }
        }

        // Posts / Pages / Custom Post Types
        if ( ! empty($scriptAttrs['post']) ) {
            ksort($scriptAttrs['post']);

            foreach ($scriptAttrs['post'] as $postId => $attrList) {
                $postLabel = self::getScriptAttrPostLabel($postId);

                foreach ($attrList as $attr) {
                    self::addScriptAttrOverviewItem(
                        $groupedAttrs,
                        $attr,
                        'Posts',
                        self::renderScriptAttrRuleOutput($postLabel, $handleData, 'post_script_attr', $attr, $postId)
                    );
                }
            }
        }

        // Author archives
        if ( ! empty($scriptAttrs['user']) ) {
            ksort($scriptAttrs['user']);

            foreach ($scriptAttrs['user'] as $userId => $attrList) {
                $userLabel = self::getScriptAttrUserLabel($userId);

                foreach ($attrList as $attr) {
                    self::addScriptAttrOverviewItem(
                        $groupedAttrs,
                        $attr,
                        'Author archives',
                        self::renderScriptAttrRuleOutput($userLabel, $handleData, 'author_archive_script_attr', $attr, $userId)
                    );
                }
            }
        }

        // Taxonomy term pages
        if ( ! empty($scriptAttrs['term']) ) {
            ksort($scriptAttrs['term']);

            foreach ($scriptAttrs['term'] as $termId => $attrList) {
                $termLabel = self::getScriptAttrTermLabel($termId);

                foreach ($attrList as $attr) {
                    self::addScriptAttrOverviewItem(
                        $groupedAttrs,
                        $attr,
                        'Taxonomy pages',
                        self::renderScriptAttrRuleOutput($termLabel, $handleData, 'taxonomy_term_script_attr', $attr, $termId)
                    );
                }
            }
        }

        if (empty($groupedAttrs)) {
            return '';
        }

        $output = '<div class="wpacu-script-attrs-overview">';
        $output .= '<span class="wpacu-script-attrs-title">Script attributes:</span>';

        $attrOrder = array_unique(array_merge(array('defer', 'async'), array_keys($groupedAttrs)));

        foreach ($attrOrder as $attr) {
            if (empty($groupedAttrs[$attr])) {
                continue;
            }

            $output .= '<div class="wpacu-script-attr-row">';
            $output .= '<span class="wpacu-script-attr-badge wpacu-script-attr-' . esc_attr($attr) . '">' . esc_html($attr) . '</span> ';

            $scopeOutput = array();

            foreach ($groupedAttrs[$attr] as $scopeLabel => $items) {
                $scopeOutput[] = '<span class="wpacu-script-attr-scope">' . wp_kses($scopeLabel, array('span' => array('style' => array()))) . ':</span> ' . implode(', ', $items);
            }

            $output .= implode(' <span class="wpacu-script-attr-separator">|</span> ', $scopeOutput);
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * @param array  $groupedAttrs
     * @param string $attr
     * @param string $scopeLabel
     * @param string $output
     *
     * @return void
     */
    private static function addScriptAttrOverviewItem(&$groupedAttrs, $attr, $scopeLabel, $output)
    {
        if ( ! isset($groupedAttrs[$attr]) ) {
            $groupedAttrs[$attr] = array();
        }

        if ( ! isset($groupedAttrs[$attr][$scopeLabel]) ) {
            $groupedAttrs[$attr][$scopeLabel] = array();
        }

        $groupedAttrs[$attr][$scopeLabel][] = $output;
    }

    /**
     * @param string $output
     * @param array  $handleData
     * @param string $ruleKey
     * @param string $attr
     * @param string $parentValue
     *
     * @return string
     */
    private static function renderScriptAttrRuleOutput($output, $handleData, $ruleKey, $attr, $parentValue = '')
    {
        return self::renderNoWrapRuleOutput(
            $output,
            $handleData,
            $ruleKey,
            $attr,
            $parentValue
        );
    }

    /**
     * @param int $postId
     *
     * @return string
     */
    private static function getScriptAttrPostLabel($postId)
    {
        $postData = get_post($postId);

        if ( ! isset($postData->post_title, $postData->post_type) ) {
            return '<s class="wpacu-tooltip" title="N/A (post deleted)" style="color: #cc0000;">' . esc_html($postId) . '</s>';
        }

        $label = '<a title="' . esc_attr(self::getPostTooltipText($postData)) . '"'
               . ' class="wpacu-tooltip"'
               . ' target="_blank"'
               . ' href="' . esc_url(admin_url('post.php?post=' . $postId . '&action=edit')) . '">'
               . esc_html($postId)
               . '</a>';

        if ($postData->post_status === 'trash') {
            $label = '<s title="' . esc_attr(self::getPostTooltipText($postData, true)) . '" class="wpacu-tooltip">' . esc_html($postId) . '</s>';
        }

        return $label;
    }

    /**
     * @param int $userId
     *
     * @return string
     */
    private static function getScriptAttrUserLabel($userId)
    {
        $userData = get_userdata($userId);

        if ( ! $userData ) {
            return '<s class="wpacu-tooltip" title="N/A (user deleted)" style="color: #cc0000;">User ID: ' . esc_html($userId) . '</s>';
        }

        $authorLink    = get_author_posts_url($userId);
        $authorRelLink = str_replace(site_url(), '', $authorLink);

        return '<a target="_blank" href="' . esc_url($authorLink) . '">' . esc_html($authorRelLink) . '</a>';
    }

    /**
     * @param int $termId
     *
     * @return string
     */
    private static function getScriptAttrTermLabel($termId)
    {
        $termData = term_exists((int)$termId) ? get_term($termId) : false;

        if ( ! $termData || (isset($termData->errors['invalid_taxonomy']) && ! empty($termData->errors['invalid_taxonomy'])) ) {
            return '<span style="color: darkred; font-style: italic;">Taxonomy ID ' . esc_html($termId) . ' does not exist anymore</span>';
        }

        $taxonomy    = $termData->taxonomy;
        $termLink    = taxonomy_exists($taxonomy) ? get_term_link($termData, $taxonomy) : false;

		if ( ! is_string($termLink)) {
			return '<span style="color: darkred; font-style: italic;">Taxonomy for term ID ' . esc_html($termId) . ' is not registered anymore</span>';
		}

        $termRelLink = str_replace(site_url(), '', $termLink);

        return '<a target="_blank" href="' . esc_url($termLink) . '">' . esc_html($termRelLink) . '</a> <small>(' . esc_html($taxonomy) . ')</small>';
    }

    /**
     * @param $handle
     * @param $assetType
     * @param $data
     * @param string $for ('default': bulk unloads, regex unloads)
     */
    public static function renderHandleTd($handle, $assetType, $data, $for = 'default')
    {
        global $wp_version;

        $handleData = '';
        $isCoreFile = false; // default

        $assetTypeS = substr($assetType, 0, -1); // "styles" to "style" & "scripts" to "script"

        if (isset($data['handles'][$assetType][$handle]) && $data['handles'][$assetType][$handle]) {
            $handleData = $data['handles'][$assetType][$handle];
        }

        // For edit mode
        $handleData['handle']     = $handle;
        $handleData['asset_type'] = $assetType;

        if ($for === 'default') {
            // [Advanced/Pro ones]
            $isHardcoded        = (strncmp($handle, 'wpacu_hardcoded_', 16) === 0);
            $hardcodedTagOutput = false;

            $attrToGet = ($assetType === 'styles') ? 'href' : 'src';

            if ($isHardcoded
                && isset($data['assets_info'][$assetType][$handle]['output'])
                && ($hardcodedTagOutput = $data['assets_info'][$assetType][$handle]['output'])
                && stripos($hardcodedTagOutput, ' ' . $attrToGet) !== false) {
                $sourceValue = Misc::getValueFromTag($hardcodedTagOutput);

                if ($sourceValue) {
                    $data['assets_info'][$assetType][$handle]['src'] = $sourceValue;
                }
            }

            // [/Advanced/Pro ones]

            // Show the original "src" and "ver, not the altered one
            // (if filters such as "wpacu_{$handle}_(css|js)_handle_obj" were used to load alternative versions of the file, depending on the situation)
            $srcKey = isset($data['assets_info'][$assetType][$handle]['src_origin']) ? 'src_origin' : 'src';
            $verKey = isset($data['assets_info'][$assetType][$handle]['ver_origin']) ? 'ver_origin' : 'ver';

            $src = (isset($data['assets_info'][$assetType][$handle][$srcKey]) && $data['assets_info'][$assetType][$handle][$srcKey]) ? $data['assets_info'][$assetType][$handle][$srcKey] : false;

             $conditionalCommentOutput = '';

            if (isset($data['assets_info'][$assetType][$handle]['extra']['conditional']) && $data['assets_info'][$assetType][$handle]['extra']['conditional']) {
                // Enqueued asset
                $conditionalComment = $data['assets_info'][$assetType][$handle]['extra']['conditional'];
            } // [Advanced/Pro ones]
            else {
                // Perhaps it's a hardcoded asset
                $conditionalComment = isset($data['assets_info'][$assetType][$handle]['cond_comm']) ? $data['assets_info'][$assetType][$handle]['cond_comm'] : '';
            }
            // [/Advanced/Pro ones]

            if ($conditionalComment) {
                $conditionalCommentOutput = '<small>&nbsp;&nbsp;<span><img style="vertical-align: middle;" width="20" height="20" src="' . WPACU_PLUGIN_URL . '/assets/icons/icon-ie.svg" alt="" title="Microsoft / Public domain" />&nbsp;<span style="font-weight: 400; color: #1C87CF;">Loads only in Internet Explorer based on the following condition:</span> <em> if ' . $conditionalComment . '</em></span></small>&nbsp;';
            }

            $isExternalSrc = true;

            if ($assetType === 'styles') {
                $isBase64EncodedSrc = stripos($src, 'data:text/css;base64,') !== false;
            } else {
                $isBase64EncodedSrc = stripos($src, 'data:text/javascript;base64,') !== false;
            }

            if ($isBase64EncodedSrc
                || Misc::getLocalSrcIfExist($src)
                || strpos($src, '/?') !== false // Dynamic Local URL
                || strncmp(str_replace(site_url(), '', $src), '?',
                            1) === 0 // Starts with ? right after the site url (it's a local URL)
            ) {
                $isExternalSrc = false;
                $isCoreFile    = MiscAdmin::isCoreFile($data['assets_info'][$assetType][$handle]);
            }

            if ($isBase64EncodedSrc) {
                $src = Misc::getHrefFromSource($src);
            }

            $ver = $wp_version; // default
            if (isset($data['assets_info'][$assetType][$handle][$verKey]) && $data['assets_info'][$assetType][$handle][$verKey]) {
                $ver = is_array($data['assets_info'][$assetType][$handle][$verKey])
                        ? implode(',', $data['assets_info'][$assetType][$handle][$verKey])
                        : $data['assets_info'][$assetType][$handle][$verKey];
            }

            // [Advanced/Pro ones]
            if ( ! $isHardcoded) {
                // [/Advanced/Pro ones]
                ?>
                <strong><span style="color: green;"><?php echo esc_html($handle); ?></span></strong>
                <small><em>v<?php echo esc_html($ver); ?></em></small>
                <?php
                echo $conditionalCommentOutput; // if any
                // [Advanced/Pro ones]
            } else {
                // Hardcoded Link/Style/Script
                $hardcodedTitle = '';

                if (strpos($handle, '_link_') !== false) {
                    $hardcodedTitle = 'Hardcoded LINK (rel="stylesheet")';
                } elseif (strpos($handle, '_style_') !== false) {
                    $hardcodedTitle = 'Hardcoded inline STYLE';
                } elseif (strpos($handle, '_script_inline_') !== false) {
                    $hardcodedTitle = 'Hardcoded inline SCRIPT';
                } elseif (strpos($handle, '_noscript_inline_') !== false) {
                    $hardcodedTitle = 'Hardcoded inline NOSCRIPT';
                } elseif (strpos($handle, '_script_') !== false) {
                    $hardcodedTitle = 'Hardcoded SCRIPT (with "src")';
                }
                ?>
                <strong><?php echo esc_html($hardcodedTitle); ?></strong>
                <?php
                if ($hardcodedTagOutput) {
                    echo $conditionalCommentOutput; // if any

                    $maxCharsToShow = 400;

                    if (strlen($hardcodedTagOutput) > $maxCharsToShow) {
                        echo '<code><small>' . htmlentities2(substr($hardcodedTagOutput, 0,
                                        $maxCharsToShow)) . '</small></code>... &nbsp;<a data-wpacu-modal-target="wpacu-' . esc_attr($handle) . '-modal-target" href="#wpacu-' . esc_attr($handle) . '-modal" class="button button-secondary">View All</a>';
                        ?>
                        <div id="<?php echo 'wpacu-' . esc_attr($handle) . '-modal'; ?>" class="wpacu-modal wpacu-source-viewer-modal">
                            <div class="wpacu-modal-content wpacu-source-viewer-modal__content">
                                <button type="button" class="wpacu-close wpacu-source-viewer-modal__close" aria-label="<?php esc_attr_e('Close', 'wp-asset-clean-up'); ?>">&times;</button>
                                <header class="wpacu-source-viewer-modal__header">
                                    <span class="wpacu-source-viewer-modal__eyebrow"><?php esc_html_e('Source viewer', 'wp-asset-clean-up'); ?></span>
                                    <div class="wpacu-source-viewer-modal__title-row">
                                        <h2><?php echo esc_html($hardcodedTitle); ?></h2>
                                        <span class="wpacu-source-viewer-modal__badge"><?php esc_html_e('HTML tag', 'wp-asset-clean-up'); ?></span>
                                    </div>
                                    <p class="wpacu-source-viewer-modal__description"><?php esc_html_e('Complete hardcoded markup detected for this asset.', 'wp-asset-clean-up'); ?></p>
                                </header>
                                <div class="wpacu-source-viewer-modal__body"><pre><code><?php echo htmlentities2($hardcodedTagOutput); ?></code></pre></div>
                            </div>
                        </div>
                        <?php
                    } else {
                        // Under the limit? Show everything
                        echo '<code><small>' . htmlentities2($hardcodedTagOutput) . '</small></code>';
                    }
                }
            }
            // [/Advanced/Pro ones]

            if ($isCoreFile) {
                ?>
                <span title="WordPress Core File" style="font-size: 15px; vertical-align: middle;"
                      class="dashicons dashicons-wordpress-alt wpacu-tooltip"></span>
                <?php
            }

            // If called from "Bulk Changes" -> "Preloads"
            $preloadedStatus = isset($data['assets_info'][$assetType][$handle]['preloaded_status']) ? $data['assets_info'][$assetType][$handle]['preloaded_status'] : false;
            if ($preloadedStatus === 'async') {
                echo '&nbsp;(<strong><em>' . $preloadedStatus . '</em></strong>)';
            }

            $handleExtras = array();

            // If called from "Overview"
            if (isset($handleData['preloads']) && $handleData['preloads']) {
                $textToShow = 'Preloaded';

                if ($handleData['preloads'] === 'async') {
                    $textToShow .= ' (async)';
                }

                $output = '<span style="color: #004567; font-weight: 600;">' . $textToShow . '</span>';

                $handleChangesOutputPreloaded = self::renderNoWrapRuleOutput(
                     $output,
                     $handleData,
                     'preloads',
                     $handleData['preloads']
                );

                $handleExtras[0] = $handleChangesOutputPreloaded;
            }

            if (isset($handleData['positions']) && $handleData['positions']) {
                if (self::isEditMode()) {
                    $textToShow = 'Moved to <code>&lt;' . esc_html($handleData['positions']) . '&gt;</code>';

                    $output = '<span style="color: #004567; font-weight: 600;">' . $textToShow . '</span>';

                    $handleChangesOutputPositions = self::renderNoWrapRuleOutput(
                         $output,
                         $handleData,
                         'positions'
                    );

                    $handleExtras[1] = $handleChangesOutputPositions;
                } else {
                    $handleExtras[1] = '<span style="color: #004567; font-weight: 600;">Moved to <code>&lt;' . esc_html($handleData['positions']) . '&gt;</code></span>';
                }
            }

            if (isset($handleExtras[0])) {
                $handleExtras[0] = ' <span style="font-weight: 300; color: grey;">/</span> '.$handleExtras[0];
            } elseif (isset($handleExtras[1])) {
                $handleExtras[1] = ' <span style="font-weight: 300; color: grey;">/</span> '.$handleExtras[1];
            }

            /*
             * 1) Per page (homepage, a post, a category, etc.)
             * Async, Defer attributes
             */
            $scriptAttrsOutput = self::renderScriptAttrsOverviewOutput($handleData);

            if ($scriptAttrsOutput) {
                $handleExtras[30] = $scriptAttrsOutput;
            }

            $oldCodeTrigger = false;

            if ($oldCodeTrigger) {

                /*
                 * 1) Per page (homepage, a post, a category, etc.)
                 * Async, Defer attributes
                 */
                // Per home page
                if ( ! empty($handleData['script_attrs']['home_page'])) {
                    ksort($handleData['script_attrs']['home_page']);
                    $handleExtras[2] = 'Homepage attributes: ';

                    if (self::isEditMode()) {
                        $formattedValuesArray = array_map(static function ($value) use ($handleData) {
                            return self::renderNoWrapRuleOutput(
                                '<strong>' . esc_html($value) . '</strong>',
                                $handleData,
                                'home_page_script_attr',
                                $value
                            );
                        }, $handleData['script_attrs']['home_page']);

                        $handleExtras[2] .= implode(', ', $formattedValuesArray);
                    } else {
                        $handleExtras[2] .= '<strong>' . esc_html(implode(', ', $handleData['script_attrs']['home_page'])) . '</strong>';
                    }
                }

                // Date archive pages
                if ( ! empty($handleData['script_attrs']['date'])) {
                    ksort($handleData['script_attrs']['date']);
                    $handleExtras[22] = 'Date archive attributes: ';

                    if (self::isEditMode()) {
                        $formattedValuesArray = array_map(static function ($value) use ($handleData) {
                            return self::renderRuleOutput(
                                '<strong>' . esc_html($value) . '</strong>',
                                $handleData,
                                'date',
                                $value
                            );
                        }, $handleData['script_attrs']['date']);

                        $handleExtras[22] .= implode(', ', $formattedValuesArray);
                    } else {
                        $handleExtras[22] .= '<strong>' . esc_html(implode(', ', $handleData['script_attrs']['date'])) . '</strong>';
                    }
                }

                // 404 page
                if ( ! empty($handleData['script_attrs']['404'])) {
                    ksort($handleData['script_attrs']['404']);
                    $handleExtras[23] = '404 Not Found attributes: ';

                    if (self::isEditMode()) {
                        $formattedValuesArray = array_map(static function ($value) use ($handleData) {
                            return self::renderRuleOutput(
                                '<strong>' . esc_html($value) . '</strong>',
                                $handleData,
                                '404',
                                $value
                            );
                        }, $handleData['script_attrs']['404']);

                        $handleExtras[23] .= implode(', ', $formattedValuesArray);
                    } else {
                        $handleExtras[23] .= '<strong>' . esc_html(implode(', ', $handleData['script_attrs']['404'])) . '</strong>';
                    }
                }

                // Search results page
                if ( ! empty($handleData['script_attrs']['search'])) {
                    ksort($handleData['script_attrs']['search']);
                    $handleExtras[24] = 'Search results attributes: ';

                    if (self::isEditMode()) {
                        $formattedValuesArray = array_map(static function ($value) use ($handleData) {
                            return self::renderRuleOutput(
                                '<strong>' . esc_html($value) . '</strong>',
                                $handleData,
                                'search_script_attr',
                                $value
                            );
                        }, $handleData['script_attrs']['search']);

                        $handleExtras[24] .= implode(', ', $formattedValuesArray);
                    } else {
                        $handleExtras[24] .= '<strong>' . esc_html(implode(', ', $handleData['script_attrs']['search'])) . '</strong>';
                    }
                }

                // Archive page for Custom Post Type (those created via theme editing or via plugins such as "Custom Post Type UI")
                $scriptAttrsStr = (isset($handleData['script_attrs']) && is_array($handleData['script_attrs']))
                        ? implode('', array_keys($handleData['script_attrs']))
                        : '';

                if (strpos($scriptAttrsStr, 'custom_post_type_archive_') !== false) {
                    $keyNo = 225;

                    $handleExtras[$keyNo] = 'Archive custom post type page: ';

                    foreach ($handleData['script_attrs'] as $scriptAttrsKey => $scriptAttrsValue) {
                        $customPostTypeName = str_replace('custom_post_type_archive_', '', $scriptAttrsKey);

                        $handleExtras[$keyNo] .= '"' . $customPostTypeName . '" post type attributes: ';

                        $currentAttrText = '';

                        if (self::isEditMode()) {
                            $formattedValuesArray = array_map(static function ($value) use (
                                    $handleData,
                                    $customPostTypeName
                            ) {
                                return self::renderRuleOutput(
                                        '<strong>' . esc_html($value) . '</strong>',
                                        $handleData,
                                        'custom_post_type_archive_script_attr',
                                        $value,
                                        $customPostTypeName
                                );
                            }, $scriptAttrsValue);

                            $currentAttrText .= implode(', ', $formattedValuesArray) . ' / &nbsp;&nbsp;&nbsp;';
                        } else {
                            $currentAttrText .= '<strong>' . esc_html(implode(', ',
                                            $handleData['script_attrs'][$scriptAttrsKey])) . '</strong> / ';
                        }

                        $handleExtras[$keyNo] .= $currentAttrText;
                    }

                    $handleExtras[$keyNo] = rtrim($handleExtras[$keyNo], ' / &nbsp;&nbsp;&nbsp;');
                }

                // Per post page
                if ( ! empty($handleData['script_attrs']['post'])) {
                    $handleExtras[3] = 'Per post attributes: ';

                    $ruleKey = 'post_script_attr';

                    $postsList = '';

                    ksort($handleData['script_attrs']['post']);

                    foreach ($handleData['script_attrs']['post'] as $postId => $attrList) {
                        $postData = get_post($postId);

                        if (isset($postData->post_title, $postData->post_type)) {
                            $inTrash = $postData->post_status === 'trash';

                            if (self::isEditMode()) {
                                $formattedValuesArray = array_map(static function ($value) use (
                                        $handleData,
                                        $postId,
                                        $ruleKey
                                ) {
                                    return self::renderNoWrapRuleOutput(
                                            '<strong>' . esc_html($value) . '</strong>',
                                            $handleData,
                                            $ruleKey,
                                            $value,
                                            $postId
                                    );
                                }, $attrList);

                                if ($inTrash) {
                                    $postsList .= '<s title="' . esc_attr(self::getPostTooltipText($postData, true)) . '" class="wpacu-tooltip">' . $postId . '</s> &rarr; ' . implode(', ',
                                                    $formattedValuesArray) . ' / &nbsp;&nbsp;&nbsp; ';

                                } else {
                                    $postsList .= '<a title="' . esc_attr(self::getPostTooltipText($postData)) . '" class="wpacu-tooltip" target="_blank" href="' . esc_url(admin_url('post.php?post=' . $postId . '&action=edit')) . '">' . $postId . '</a> &rarr; ' . implode(', ',
                                                    $formattedValuesArray) . ' / &nbsp;&nbsp;&nbsp; ';
                                }
                            } else {
                                $postsList .= '<a title="' . esc_attr(self::getPostTooltipText($postData)) . '" class="wpacu-tooltip" target="_blank" href="' . esc_url(admin_url('post.php?post=' . $postId . '&action=edit')) . '">' . $postId . '</a> &rarr; <strong>' . esc_html(implode(', ',
                                                $attrList)) . '</strong> / &nbsp;&nbsp;&nbsp; ';
                            }
                        } else {
                            if (self::isEditMode()) {
                                $formattedValuesArray = array_map(static function ($value) use (
                                        $handleData,
                                        $postId,
                                        $ruleKey
                                ) {
                                    return self::renderRuleOutput(
                                            '<strong>' . esc_html($value) . '</strong>',
                                            $handleData,
                                            $ruleKey,
                                            $value,
                                            $postId
                                    );
                                }, $attrList);

                                $postsList .= '<s class="wpacu-tooltip" title="N/A (post deleted)" style="color: #cc0000;">' . $postId . '</s> &rarr; ' . implode(', ',
                                                $formattedValuesArray) . ' / &nbsp;&nbsp;&nbsp; ';
                            } else {
                                $postsList .= '<s class="wpacu-tooltip" title="N/A (post deleted)" style="color: #cc0000;">' . $postId . '</s> / &nbsp;&nbsp;&nbsp; ';
                            }
                        }
                    }

                    $handleExtras[3] .= rtrim($postsList, ' / &nbsp;&nbsp;&nbsp; ');
                }

                // User archive page (specific author)
                if ( ! empty($handleData['script_attrs']['user'])) {
                    $ruleKey = 'author_archive_script_attr';

                    $handleExtras[31] = 'Per author page attributes: ';

                    $authorPagesList = '';

                    ksort($handleData['script_attrs']['user']);

                    foreach ($handleData['script_attrs']['user'] as $userId => $attrList) {
                        $userData = get_userdata($userId);

                        if ($userData) {
                            $authorLink    = get_author_posts_url($userId);
                            $authorRelLink = str_replace(site_url(), '', $authorLink);

                            if (self::isEditMode()) {
                                $formattedValuesArray = array_map(static function ($value) use (
                                        $handleData,
                                        $userId,
                                        $ruleKey
                                ) {
                                    return self::renderNoWrapRuleOutput(
                                            '<strong>' . esc_html($value) . '</strong>',
                                            $handleData,
                                            $ruleKey,
                                            $value,
                                            $userId
                                    );
                                }, $attrList);

                                $authorPagesList .= '<a target="_blank" href="' . esc_url($authorLink) . '">' . esc_html($authorRelLink) . '</a> &rarr; ' . implode(', ',
                                                $formattedValuesArray) . ' | &nbsp;&nbsp;&nbsp; ';
                            } else {
                                $authorPagesList .= '<a target="_blank" href="' . esc_url($authorLink) . '">' . esc_html($authorRelLink) . '</a> &rarr; <strong>' . esc_html(implode(', ',
                                                $attrList)) . '</strong> | &nbsp;&nbsp;&nbsp; ';
                            }
                        } else {
                            if (self::isEditMode()) {
                                $formattedValuesArray = array_map(static function ($value) use (
                                    $handleData,
                                    $userId,
                                    $ruleKey
                                ) {
                                    return self::renderNoWrapRuleOutput(
                                        '<strong>' . esc_html($value) . '</strong>',
                                        $handleData,
                                        $ruleKey,
                                        $value,
                                        $userId
                                    );
                                }, $attrList);

                                $authorPagesList .= '<s class="wpacu-tooltip" title="N/A (user deleted)" style="color: #cc0000;">User ID: ' . esc_html($userId) . '</s> &rarr; ' . implode(', ',
                                                $formattedValuesArray) . ' | &nbsp;&nbsp;&nbsp; ';
                            } else {
                                $authorPagesList .= '<s class="wpacu-tooltip" title="N/A (user deleted)" style="color: #cc0000;">User ID: ' . esc_html($userId) . '</s> &rarr; <strong>' . esc_html(implode(', ',
                                                $attrList)) . '</strong> | &nbsp;&nbsp;&nbsp; ';
                            }
                        }
                    }

                    $authorPagesList = rtrim($authorPagesList, ' | &nbsp;&nbsp;&nbsp; ');

                    $handleExtras[31] .= $authorPagesList;
                }

                // Per category page
                if ( ! empty($handleData['script_attrs']['term'])) {
                    $handleExtras[33] = 'Per taxonomy attributes: ';

                    $taxPagesList = '';

                    foreach ($handleData['script_attrs']['term'] as $termId => $attrList) {
                        $taxData = term_exists((int)$termId) ? get_term($termId) : false;

                        if ( ! $taxData || (isset($taxData->errors['invalid_taxonomy']) && ! empty($taxData->errors['invalid_taxonomy']))) {
                            if (self::isEditMode()) {
                                $formattedValuesArray = array_map(static function ($value) use ($handleData, $termId) {
                                    return self::renderNoWrapRuleOutput(
                                        '<strong>' . esc_html($value) . '</strong>',
                                        $handleData,
                                        'taxonomy_term_script_attr',
                                        $value,
                                        $termId
                                    );
                                }, $attrList);

                                $taxPagesList .= '<span style="color: darkred; font-style: italic;">Error: Taxonomy with ID ' . esc_html($termId) . ' does not exist anymore (rule does not apply)</span> &rarr; ' . implode(', ',
                                                $formattedValuesArray) . ' | &nbsp;&nbsp;&nbsp; ';
                            } else {
                                $taxPagesList .= '<span style="color: darkred; font-style: italic;">Error: Taxonomy with ID ' . esc_html($termId) . ' does not exist anymore (rule does not apply)</span> | &nbsp;&nbsp;&nbsp; ';
                            }
                        } else {
                            $taxonomy    = $taxData->taxonomy;
                            $termLink    = taxonomy_exists($taxonomy) ? get_term_link($taxData, $taxonomy) : false;
                            $termRelLink = is_string($termLink) ? str_replace(site_url(), '', $termLink) : '';

							if ( ! is_string($termLink)) {
								$taxPagesList .= '<span style="color: darkred; font-style: italic;">Error: Taxonomy for term ID ' . esc_html($termId) . ' is not registered anymore (rule does not apply)</span> | &nbsp;&nbsp;&nbsp; ';
								continue;
							}

                            if (self::isEditMode()) {
                                $formattedValuesArray = array_map(static function ($value) use ($handleData, $termId) {
                                    return self::renderNoWrapRuleOutput(
                                        '<strong>' . esc_html($value) . '</strong>',
                                        $handleData,
                                        'taxonomy_term_script_attr',
                                        $value,
                                        $termId
                                    );
                                }, $attrList);

                                $taxPagesList .= '<a href="' . esc_url($termLink) . '">' . esc_html($termRelLink) . '</a> &rarr; '
                                                 . implode(', ',
                                                $formattedValuesArray) . ' (' . $taxonomy . ') | &nbsp;&nbsp;&nbsp; ';
                            } else {
                                $taxPagesList .= '<a href="' . esc_url($termLink) . '">' . esc_html($termRelLink) . '</a> &rarr; <strong>'
                                                 . esc_html(implode(', ',
                                                $attrList)) . '</strong> | &nbsp;&nbsp;&nbsp; ';
                            }
                        }
                    }

                    $taxPagesList = rtrim($taxPagesList, ' | &nbsp;&nbsp;&nbsp; ');

                    $handleExtras[33] .= $taxPagesList;
                }


                /*
                 * 2) Site-wide type
                 * Any async, defer site-wide attributes? Exceptions will be also shown
                 */
                if (isset($handleData['script_site_wide_attrs'])) {
                    $handleExtras[4] = 'Site-wide attributes: ';

                    foreach ($handleData['script_site_wide_attrs'] as $attrValue) {
                        if (self::isEditMode()) {
                            $handleExtras[4] .= self::renderRuleOutput(
                                    '<strong>' . esc_html($attrValue) . '</strong>',
                                    $handleData,
                                    'site_wide_script_attr',
                                    $attrValue
                            );
                        } else {
                            $handleExtras[4] .= '<strong>' . esc_html($attrValue) . '</strong>';
                        }

                        // Are there any exceptions? e.g. async, defer unloaded site-wide, but loaded on the homepage
                        if ( ! empty($handleData['attrs_no_load'])) {
                            // $attrSetIn could be 'home_page', 'term', 'user', 'date', '404', 'search'
                            $handleExtras[4] .= ' <em>(with exceptions from applying added for these pages: ';

                            $handleAttrsExceptionsList = '';

                            foreach ($handleData['attrs_no_load'] as $attrSetIn => $attrSetValues) {
                                if ($attrSetIn === 'home_page' && in_array($attrValue, $attrSetValues)) {
                                    $output = 'Homepage';

                                    $handleAttrsExceptionsList .= self::isEditMode()
                                            ? self::renderNoWrapRuleOutput(
                                                $output,
                                                $handleData,
                                                'attrs_no_load_' . $attrSetIn . '_' . $attrValue,
                                                $attrValue
                                            ) . ', '
                                            : $output . ', ';
                                }

                                if ($attrSetIn === 'date' && in_array($attrValue, $attrSetValues)) {
                                    $output = 'Date Archive';

                                    $handleAttrsExceptionsList .= self::isEditMode()
                                            ? self::renderNoWrapRuleOutput(
                                                $output,
                                                $handleData,
                                                'attrs_no_load_' . $attrSetIn . '_' . $attrValue,
                                                $attrValue
                                            ) . ', '
                                            : $output . ', ';
                                }

                                if ((int)$attrSetIn === 404 && in_array($attrValue, $attrSetValues)) {
                                    $output = '404 Not Found';

                                    $handleAttrsExceptionsList .= self::isEditMode()
                                            ? self::renderNoWrapRuleOutput(
                                                $output,
                                                $handleData,
                                                'attrs_no_load_' . $attrSetIn . '_' . $attrValue,
                                                $attrValue
                                            ) . ', '
                                            : $output . ', ';
                                }

                                if ($attrSetIn === 'search' && in_array($attrValue, $attrSetValues)) {
                                    $output = 'Search Results';

                                    $handleAttrsExceptionsList .= self::isEditMode()
                                            ? self::renderNoWrapRuleOutput(
                                                $output,
                                                $handleData,
                                                'attrs_no_load_' . $attrSetIn . '_' . $attrValue,
                                                $attrValue
                                            ) . ', '
                                            : $output . ', ';
                                }

                                if (strpos($attrSetIn, 'custom_post_type_archive_') !== false && in_array($attrValue, $attrSetValues)) {
                                    $customPostTypeName = str_replace('custom_post_type_archive_', '', $attrSetIn);

                                    $output = 'Archive "' . esc_html($customPostTypeName) . '" custom post type';

                                    $handleAttrsExceptionsList .= self::isEditMode()
                                            ? self::renderNoWrapRuleOutput(
                                                    $output,
                                                    $handleData,
                                                    'attrs_no_load_' . $attrSetIn . '_' . $attrValue,
                                                    $attrValue
                                            ) . ', '
                                            : $output . ', ';
                                }

                                // Post pages such as posts, pages, product (WooCommerce), download (Easy Digital Downloads), etc.
                                if ($attrSetIn === 'post') {
                                    $postPagesList = '';

                                    foreach ($attrSetValues as $postId => $attrSetValuesTwo) {
                                        if ( ! in_array($attrValue, $attrSetValuesTwo)) {
                                            continue;
                                        }

                                        $postData = get_post($postId);

                                        if (isset($postData->post_title, $postData->post_type)) {
                                            $output = '<a title="' . esc_attr(self::getPostTooltipText($postData)) . '" class="wpacu-tooltip" target="_blank" href="' . esc_url(admin_url('post.php?post=' . $postId . '&action=edit')) . '">' . $postId . '</a>';
                                        } else {
                                            $output = '<s style="color: #cc0000;">' . (int)$postId . '</s> <em>N/A (post deleted)</em>';
                                        }

                                        $postPagesList .= self::isEditMode()
                                                ? self::renderNoWrapRuleOutput(
                                                        $output,
                                                        $handleData,
                                                        'post_no_load_script_attr',
                                                        $attrValue,
                                                        $postId
                                                ) . ' | ' : $output . ' | ';
                                    }

                                    if ($postPagesList) {
                                        $postPagesList             = trim($postPagesList, ' | ') . ', ';
                                        $handleAttrsExceptionsList .= $postPagesList;
                                    }
                                }

                                // Taxonomy pages such as category archive, product category in WooCommerce
                                if ($attrSetIn === 'term') {
                                    $taxPagesList = '';

                                    foreach ($attrSetValues as $termId => $attrSetValuesTwo) {
                                        if ( ! in_array($attrValue, $attrSetValuesTwo)) {
                                            continue;
                                        }

                                        $taxData = term_exists((int)$termId) ? get_term($termId) : false;

                                        if ( ! $taxData || (isset($taxData->errors['invalid_taxonomy']) && ! empty($taxData->errors['invalid_taxonomy']))) {
                                            $output = '<span style="color: darkred; font-style: italic;">Error: Taxonomy with ID ' . (int)$termId . ' does not exist anymore (rule does not apply)</span>';
                                        } else {
                                            $taxonomy    = $taxData->taxonomy;
                                            $termLink    = taxonomy_exists($taxonomy) ? get_term_link($taxData, $taxonomy) : false;
                                            $termRelLink = is_string($termLink) ? str_replace(site_url(), '', $termLink) : '';

											$output = ! is_string($termLink)
												? '<span style="color: darkred; font-style: italic;">Error: Taxonomy for term ID ' . (int)$termId . ' is not registered anymore (rule does not apply)</span>'
												: '<a href="' . esc_url($termLink) . '">' . esc_html($termRelLink) . '</a>';
                                        }

                                        $taxPagesList .= self::isEditMode()
                                                ? self::renderRuleOutput(
                                                        $output,
                                                        $handleData,
                                                        'attrs_no_load_' . $attrSetIn . '_' . $attrValue,
                                                        $termId . '::' . $attrValue
                                                ) . ' | '
                                                : $output . ' | ';
                                    }

                                    if ($taxPagesList) {
                                        $taxPagesList              = trim($taxPagesList, ' | ') . ', ';
                                        $handleAttrsExceptionsList .= $taxPagesList;
                                    }
                                }

                                // Author archive pages (e.g. /author/john/page/2/)
                                if ($attrSetIn === 'user') {
                                    $authorPagesList = '';

                                    foreach ($attrSetValues as $userId => $attrSetValuesTwo) {
                                        if ( ! in_array($attrValue, $attrSetValuesTwo)) {
                                            continue;
                                        }

                                        $authorLink    = get_author_posts_url(get_the_author_meta('ID', $userId));
                                        $authorRelLink = str_replace(site_url(), '', $authorLink);

                                        $output = '<a target="_blank" href="' . esc_url($authorLink) . '">' . esc_html($authorRelLink) . '</a>';

                                        $authorPagesList .= self::isEditMode()
                                                ? self::renderRuleOutput(
                                                        $output,
                                                        $handleData,
                                                        'attrs_no_load_' . $attrSetIn . '_' . $attrValue,
                                                        $userId . '::' . $attrValue
                                                ) . ' | '
                                                : $output . ' | ';
                                    }

                                    if ($authorPagesList) {
                                        $authorPagesList           = trim($authorPagesList, ' | ') . ', ';
                                        $handleAttrsExceptionsList .= $authorPagesList;
                                    }
                                }
                            }

                            $handleAttrsExceptionsList = trim($handleAttrsExceptionsList, ', ');

                            $handleExtras[4] .= $handleAttrsExceptionsList;
                            $handleExtras[4] .= '</em>), ';
                        }

                        $handleExtras[4] .= ', ';
                    }

                    $handleExtras[4] = trim($handleExtras[4], ', ');
                }
            }

            if ( ! empty($handleExtras)) {
                echo '<small>' . implode(' <span style="font-weight: 300; color: grey;">/</span> ', $handleExtras) . '</small>';
            }

            if ($src) {
                if ( ! $isBase64EncodedSrc) {
                    $verDb          = (isset($data['assets_info'][$assetType][$handle][$verKey]) && $data['assets_info'][$assetType][$handle][$verKey]) ? $data['assets_info'][$assetType][$handle][$verKey] : false;
                    $appendAfterSrc = (strpos($src, '?') === false) ? '?' : '&';

                    if ($verDb) {
                        if (is_array($verDb)) {
                            $appendAfterSrc .= http_build_query(array('ver' => $data['assets_info'][$assetType][$handle][$verKey]));
                        } else {
                            $appendAfterSrc .= 'ver=' . $ver;
                        }
                    } else {
                        $appendAfterSrc .= 'ver=' . $wp_version; // default
                    }
                    ?>
                    <div>
                        <a <?php if ($isExternalSrc) { ?> data-wpacu-external-source="<?php echo esc_attr($src . $appendAfterSrc); ?>" <?php } ?>
                                href="<?php echo esc_attr(Misc::getHrefFromSource($src) . $appendAfterSrc); ?>"
                                target="_blank">
                            <small><?php echo esc_html(str_replace(site_url(), '', $src)); ?></small>
                        </a> <?php if ($isExternalSrc) { ?><span data-wpacu-external-source-status></span><?php } ?>
                    </div>
                    <?php
                    $maybeInactiveAsset = Admin\MiscAdmin::maybeIsInactiveAsset($src);

                    if (is_array($maybeInactiveAsset) && ! empty($maybeInactiveAsset)) {
                        ?>
                        <div>
                            <?php if ($maybeInactiveAsset['from'] === 'plugin') { ?>
                                <small><strong>Note:</strong> <span
                                            style="color: darkred;">The plugin `<strong><?php echo esc_html($maybeInactiveAsset['name']); ?></strong>` seems to be inactive, thus any rules set are also inactive &amp; irrelevant, unless you re-activate the plugin.</span></small>
                            <?php } elseif ($maybeInactiveAsset['from'] === 'theme') { ?>
                                <small><strong>Note:</strong> <span
                                            style="color: darkred;">The theme `<strong><?php echo esc_html($maybeInactiveAsset['name']); ?></strong>` seems to be inactive, thus any rules set are also inactive &amp; irrelevant, unless you re-activate the theme.</span></small>
                            <?php } ?>

                            <?php
                            if (self::isEditMode()) {
                                echo self::renderRuleOutput(
                                     esc_html__('Clear all rules for this inactive handle', 'wp-asset-clean-up') .
                                                                                                              '</span>',
                                     array(
                                                                                                                  'handle'     => $handle,
                                                                                                                  'asset_type' => $assetType
                                                                                                              ),
                                     'clear_all_rules',
                                     1
                                );
                            } else {
                                $clearForFullTextViewMode = sprintf(
                                        __('You can switch to %s"Edit Mode"%s to clear all the rules for this inactive handle',
                                                'wp-asset-clean-up'),
                                        '<a href="' . admin_url('admin.php?page=wpassetcleanup_overview&wpacu_edit_mode=1') . '">',
                                        '</a>'
                                );

                                echo '<div style="margin: 5px 0;"><em><small>' . $clearForFullTextViewMode . '</small></em></div>';
                            }
                            ?>
                        </div>
                        <?php
                    }
                } else {
                    // Extract base64 encoded data and decode it
                    if ($assetTypeS === 'style') {
                        $dataToCheck     = 'data:text/css;base64,';
                        $viewDecodedText = __('View Decoded CSS', 'wp-asset-clean-up');
                    } else {
                        $dataToCheck     = 'data:text/javascript;base64,';
                        $viewDecodedText = __('View Decoded JS', 'wp-asset-clean-up');
                    }

                    $base64Encoded = str_replace($dataToCheck, '', $src);
                    $maxDecodedSourceBytes = 2097152;
                    $maxEncodedSourceBytes = (int) ceil(($maxDecodedSourceBytes * 4) / 3) + 4;
                    $decodedSource = false;

                    if (strlen($base64Encoded) <= $maxEncodedSourceBytes) {
                        $decodedSource = base64_decode($base64Encoded, true);
                    }

                    if ($decodedSource === false || strlen($decodedSource) > $maxDecodedSourceBytes) {
                        $decodedSource = __('The embedded source could not be decoded safely or is too large to preview.', 'wp-asset-clean-up');
                    }

                    $viewDecodedBase64Unique = 'wpacu-view-decoded-base64-format-' . $assetTypeS . '-' . sha1($src) . '-' . Misc::uniqueId();
                    ?>
                    <div>
                        <small>
                            <?php if ($assetTypeS === 'style') { ?>
                                * The "href" attribute is not pointing to an actual file and contains CSS code in Base64 format (it starts with "
                                <em><?php echo $dataToCheck; ?></em>").
                            <?php } else { ?>
                                * The "src" attribute is not pointing to an actual file and contains JavaScript code in Base64 format (it starts with "
                                <em><?php echo $dataToCheck; ?></em>").
                            <?php } ?>
                            <a data-wpacu-modal-target="<?php echo $viewDecodedBase64Unique; ?>-target"
                               href="#<?php echo $viewDecodedBase64Unique; ?>"><?php echo $viewDecodedText; ?></a>
                        </small>
                    </div>
                    <div id="<?php echo $viewDecodedBase64Unique; ?>" class="wpacu-modal wpacu-source-viewer-modal">
                        <div class="wpacu-modal-content wpacu-source-viewer-modal__content">
                            <button type="button" class="wpacu-close wpacu-source-viewer-modal__close" aria-label="<?php esc_attr_e('Close', 'wp-asset-clean-up'); ?>">&times;</button>
                            <header class="wpacu-source-viewer-modal__header">
                                <span class="wpacu-source-viewer-modal__eyebrow"><?php esc_html_e('Decoded data URL', 'wp-asset-clean-up'); ?></span>
                                <div class="wpacu-source-viewer-modal__title-row">
                                    <h2><?php echo $assetTypeS === 'style' ? esc_html__('Embedded CSS source', 'wp-asset-clean-up') : esc_html__('Embedded JavaScript source', 'wp-asset-clean-up'); ?></h2>
                                    <span class="wpacu-source-viewer-modal__badge"><?php echo esc_html(strtoupper($assetTypeS === 'style' ? 'CSS' : 'JavaScript')); ?></span>
                                </div>
                                <p class="wpacu-source-viewer-modal__description"><?php esc_html_e('Readable source decoded from the Base64 data URL used by this asset.', 'wp-asset-clean-up'); ?></p>
                            </header>
                            <div class="wpacu-source-viewer-modal__body"><pre><code><?php echo esc_html($decodedSource); ?></code></pre></div>
                        </div>
                    </div>
                <?php }
            }

            // [Advanced/Pro ones]
            // Any media query load?
            if (isset($handleData['media_queries_load']['enable']) && $handleData['media_queries_load']['enable']) {
                $enableStatus    = (int)$handleData['media_queries_load']['enable'];
                $mediaQueryValue = '';

                // Case 1: Make the browser download the file only if this media query is matched: $mediaQueryCustomValue
                if ($enableStatus === 1 && $handleData['media_queries_load']['value']) {
                    $mediaQueryValue = $handleData['media_queries_load']['value'];
                }

                // Case 2: Make the browser download the file only if its current media query is matched
                // The LINK tag already has a "media" attribute different from "all"
                if ($enableStatus === 2) {
                    $mediaQueryValue = isset($data['assets_info'][$assetType][$handle]['args']) ? $data['assets_info'][$assetType][$handle]['args'] : 'Its own one already set';
                }

                if ($mediaQueryValue) {
                    $handleData['html_output_value'] = '<code style="color: #004567;">'.htmlspecialchars($mediaQueryValue).'</code>';

                    if (self::isEditMode()) {
                        $output = '<small style="color: #004567;"><span class="dashicons dashicons-desktop" style="vertical-align: middle;"></span> Downloads if this media query matches:&nbsp;</small>';

                        $handleChangesOutputPreloaded = self::renderRuleOutput(
                             $output,
                             $handleData,
                             'media_query',
                             esc_attr($mediaQueryValue)
                        );

                        echo $handleChangesOutputPreloaded;
                    } else {
                        ?>
                        <div>
                            <small style="color: #004567;">
                                <span class="dashicons dashicons-desktop" style="color: #004567; vertical-align: middle;"></span>
                                Downloads if this media query matches:
                                <?php echo $handleData['html_output_value']; ?>
                            </small>
                        </div>
                        <?php
                    }
                }
            }
            // [/Advanced/Pro ones]

            // Any note?
            if (isset($handleData['notes']) && $handleData['notes']) {
                if (self::isEditMode()) {
                    $output = '<small><span class="dashicons dashicons-welcome-write-blog" style="vertical-align: middle;"></span> Note: </small>';

                    $handleChangesOutputPreloaded = self::renderRuleOutput(
                         $output,
                         $handleData,
                         'note',
                         esc_attr($handleData['notes'])
                    );

                    echo $handleChangesOutputPreloaded;
                } else {
                    ?>
                    <div><small><span class="dashicons dashicons-welcome-write-blog" style="vertical-align: middle;"></span>
                            Note: <em><?php echo ucfirst(htmlspecialchars($handleData['notes'])); ?></em></small></div>
                    <?php
                }
            }
            ?>
            <?php
        }
    }

    /**
     * @return void
     */
    public static function renderViewEditModeAreaToggleButton()
    {
        $currentUrl = $_SERVER['REQUEST_URI'];

        if (self::isEditMode()) {
            $newUrl          = remove_query_arg('wpacu_edit_mode', $currentUrl);
            $iconClass       = 'dashicons-visibility';
            $textNoticeLabel = sprintf(
                    esc_html__('You are in %s', 'wp-asset-clean-up'),
                    '<strong>' . esc_html__('Edit Mode', 'wp-asset-clean-up') . '</strong>'
            );
            $buttonLabel     = __('Switch to View Mode', 'wp-asset-clean-up');
        } else {
            $newUrl          = add_query_arg('wpacu_edit_mode', '1', $currentUrl);
            $iconClass       = 'dashicons-edit';
            $textNoticeLabel = sprintf(
                    esc_html__('You are in %s', 'wp-asset-clean-up'),
                    '<strong>' . esc_html__('View Mode', 'wp-asset-clean-up') . '</strong>'
            );
            $buttonLabel     = __('Switch to Edit Mode', 'wp-asset-clean-up');
        }
        ?>
        <div <?php if (self::isEditMode()) { ?>data-wpacu-overview-edit-mode<?php } ?>
             id="wpacu-overview-render-edit-mode-toggle-area">
            <div data-wpacu-text-notice-area="1">
                <span style="color: #0073aa; font-weight: 500;"><?php echo $textNoticeLabel; ?></span>
            </div>&nbsp;
            <div data-wpacu-button-area="1">
                <a href="<?php echo esc_url($newUrl); ?>" class="button button-secondary">
                    <span class="dashicons <?php echo esc_attr($iconClass); ?>"></span>
                    <?php echo esc_html($buttonLabel); ?>
                </a>
            </div>
        </div>
        <div style="clear: both;"></div>
        <?php
    }

    /**
     * @param $postType
     *
     * @return string
     */
    public static function anyNoPostTypeEntriesMsg($postType)
    {
        $appendAfter    = '';
        $postTypeStatus = Misc::isValidPostType($postType);

        if ( ! $postTypeStatus['has_records']) {
            $appendAfter = ' <span style="color: #cc0000;" title="There are no posts in the database having the following post type: ' . $postType . '" class="wpacu-tooltip dashicons dashicons-warning"></span>';
        }

        return $appendAfter;
    }
}
