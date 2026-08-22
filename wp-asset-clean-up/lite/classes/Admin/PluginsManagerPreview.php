<?php
namespace WpAssetCleanUpLite\Admin;

use WpAssetCleanUp\Admin\PluginsManagerAdmin;
use WpAssetCleanUp\Main;

/**
 * Read-only data adapter for the Lite Plugins Manager preview.
 *
 * It mirrors the presentation state used by the production Pro Plugins
 * Manager layouts. No Pro rule execution or persistence logic lives here.
 */
class PluginsManagerPreview
{
    /**
     * @param array $pageData
     * @param array $pluginData
     * @param int   $pluginIndex
     *
     * @return array
     */
    public static function preparePluginViewData($pageData, $pluginData, $pluginIndex = 0)
    {
        $pluginPath = isset($pluginData['path']) ? (string)$pluginData['path'] : '';
        $pluginPathParts = explode('/', $pluginPath);
        $pluginDir = reset($pluginPathParts);
        $rules = isset($pageData['rules']) && is_array($pageData['rules'])
            ? $pageData['rules']
            : array();
        $pluginRules = isset($rules[$pluginPath]) && is_array($rules[$pluginPath])
            ? $rules[$pluginPath]
            : array();
        $pluginStatus = isset($pluginRules['status']) ? $pluginRules['status'] : array();

        if ( ! is_array($pluginStatus)) {
            $pluginStatus = array($pluginStatus);
        }

        $pluginStatus = array_values(array_unique(array_filter(array_map('sanitize_key', $pluginStatus))));

        $data = array(
            'plugin_path' => $pluginPath,
            'plugin_dir'  => $pluginDir,
            'rules'       => $pluginRules,
            'status'      => $pluginStatus
        );

        $data['is_unload_site_wide'] = in_array('unload_site_wide', $pluginStatus, true);
        $data['is_unload_homepage'] = in_array('unload_home_page', $pluginStatus, true);
        $data['is_unload_via_post'] = in_array('unload_via_post', $pluginStatus, true);
        $data['is_unload_via_post_type'] = in_array('unload_via_post_type', $pluginStatus, true);
        $data['is_unload_via_post_tax_term'] = in_array('unload_via_post_tax_term', $pluginStatus, true);
        $data['is_unload_via_tax_term'] = in_array('unload_via_tax_term', $pluginStatus, true);
        $data['is_unload_via_tax'] = in_array('unload_via_tax', $pluginStatus, true);
        $data['is_unload_via_archive'] = in_array('unload_via_archive', $pluginStatus, true);
        $data['is_unload_via_author_archive'] = in_array('unload_via_author_archive', $pluginStatus, true);
        $data['is_unload_via_regex'] = in_array('unload_via_regex', $pluginStatus, true);
        $data['is_unload_if_logged_in'] = in_array('unload_logged_in', $pluginStatus, true);
        $data['is_unload_logged_in_via_role'] = in_array('unload_logged_in_via_role', $pluginStatus, true);

        $data['unload_via_post_chosen'] = self::getRuleValues($pluginRules, 'unload_via_post');
        $data['unload_via_post_type_chosen'] = self::getRuleValues($pluginRules, 'unload_via_post_type');
        $data['unload_via_post_tax_term_chosen'] = self::getRuleValues($pluginRules, 'unload_via_post_tax_term');
        $data['unload_via_tax_term_chosen'] = self::getRuleValues($pluginRules, 'unload_via_tax_term');
        $data['unload_via_tax_chosen'] = self::getRuleValues($pluginRules, 'unload_via_tax');
        $data['unload_via_archive_chosen'] = self::getRuleValues($pluginRules, 'unload_via_archive');
        $data['unload_via_author_archive_chosen'] = self::getRuleValues($pluginRules, 'unload_via_author_archive');
        $data['unload_logged_in_via_role_chosen'] = self::getRuleValues($pluginRules, 'unload_logged_in_via_role');
        $data['unload_via_regex_value'] = self::getRuleTextValue($pluginRules, 'unload_via_regex');

        if (empty($data['unload_logged_in_via_role_chosen'])) {
            $data['is_unload_logged_in_via_role'] = false;
        }

        $data['is_load_homepage'] = in_array('load_home_page', $pluginStatus, true);
        $data['is_load_via_post'] = in_array('load_via_post', $pluginStatus, true);
        $data['is_load_via_post_type'] = in_array('load_via_post_type', $pluginStatus, true);
        $data['is_load_via_post_tax_term'] = in_array('load_via_post_tax_term', $pluginStatus, true);
        $data['is_load_via_tax_term'] = in_array('load_via_tax_term', $pluginStatus, true);
        $data['is_load_via_tax'] = in_array('load_via_tax', $pluginStatus, true);
        $data['is_load_via_archive'] = in_array('load_via_archive', $pluginStatus, true);
        $data['is_load_via_author_archive'] = in_array('load_via_author_archive', $pluginStatus, true);
        $data['is_load_via_regex'] = in_array('load_via_regex', $pluginStatus, true);
        $data['is_load_if_logged_in'] = in_array('load_logged_in', $pluginStatus, true);
        $data['is_load_logged_in_via_role'] = in_array('load_logged_in_via_role', $pluginStatus, true);

        // Preserve support for older Pro data that stored these two flags outside status[].
        if (isset($pluginRules['load_via_regex']['enable']) && $pluginRules['load_via_regex']['enable']) {
            $data['is_load_via_regex'] = true;
        }

        if (isset($pluginRules['load_logged_in']['enable']) && $pluginRules['load_logged_in']['enable']) {
            $data['is_load_if_logged_in'] = true;
        }

        $data['load_via_post_chosen'] = self::getRuleValues($pluginRules, 'load_via_post');
        $data['load_via_post_type_chosen'] = self::getRuleValues($pluginRules, 'load_via_post_type');
        $data['load_via_post_tax_term_chosen'] = self::getRuleValues($pluginRules, 'load_via_post_tax_term');
        $data['load_via_tax_term_chosen'] = self::getRuleValues($pluginRules, 'load_via_tax_term');
        $data['load_via_tax_chosen'] = self::getRuleValues($pluginRules, 'load_via_tax');
        $data['load_via_archive_chosen'] = self::getRuleValues($pluginRules, 'load_via_archive');
        $data['load_via_author_archive_chosen'] = self::getRuleValues($pluginRules, 'load_via_author_archive');
        $data['load_logged_in_via_role_chosen'] = self::getRuleValues($pluginRules, 'load_logged_in_via_role');
        $data['load_via_regex_value'] = self::getRuleTextValue($pluginRules, 'load_via_regex');

        $unloadGroups = array(
            'site' => array(
                $data['is_unload_site_wide'],
                $data['is_unload_homepage']
            ),
            'singular' => array(
                $data['is_unload_via_post'],
                $data['is_unload_via_post_type'],
                $data['is_unload_via_post_tax_term']
            ),
            'archives' => array(
                $data['is_unload_via_tax_term'],
                $data['is_unload_via_tax'],
                $data['is_unload_via_archive'],
                $data['is_unload_via_author_archive']
            ),
            'conditions' => array(
                $data['is_unload_via_regex'],
                $data['is_unload_if_logged_in'],
                $data['is_unload_logged_in_via_role']
            )
        );

        $loadGroups = array(
            'site' => array(
                $data['is_load_homepage']
            ),
            'singular' => array(
                $data['is_load_via_post'],
                $data['is_load_via_post_type'],
                $data['is_load_via_post_tax_term']
            ),
            'archives' => array(
                $data['is_load_via_tax_term'],
                $data['is_load_via_tax'],
                $data['is_load_via_archive'],
                $data['is_load_via_author_archive']
            ),
            'conditions' => array(
                $data['is_load_via_regex'],
                $data['is_load_if_logged_in'],
                $data['is_load_logged_in_via_role']
            )
        );

        $unloadCounts = self::countRuleGroups($unloadGroups);
        $loadCounts = self::countRuleGroups($loadGroups);
        $unloadRulesCount = array_sum($unloadCounts);
        $loadExceptionsCount = array_sum($loadCounts);
        $contractedList = isset($pageData['plugins_contracted_list']) && is_array($pageData['plugins_contracted_list'])
            ? $pageData['plugins_contracted_list']
            : array();

        if (array_key_exists($pluginPath, $contractedList)) {
            $pluginAreaState = 'contracted';
        } elseif ((int)$pluginIndex === 0 || $unloadRulesCount > 0 || $loadExceptionsCount > 0) {
            $pluginAreaState = 'expanded';
        } else {
            $pluginAreaState = 'contracted';
        }

        return array(
            'data'                  => $data,
            'plugin_data'           => $pluginData,
            'plugin_path'           => $pluginPath,
            'plugin_dir'            => $pluginDir,
            'plugin_status'         => $pluginStatus,
            'plugin_area_state'     => $pluginAreaState,
            'unload_counts'         => $unloadCounts,
            'load_counts'           => $loadCounts,
            'unload_rules_count'    => $unloadRulesCount,
            'load_exceptions_count' => $loadExceptionsCount,
            'no_unload_rule_set'    => $unloadRulesCount < 1
        );
    }

    /**
     * Prepare the read-only state used by the production Pro Dashboard layout.
     * Dashboard rules deliberately remain a smaller set than front-end rules:
     * site-wide, request URI and logged-in user role conditions, plus matching
     * load exceptions.
     *
     * @param array $pageData
     * @param array $pluginData
     *
     * @return array
     */
    public static function prepareDashboardPluginViewData($pageData, $pluginData)
    {
        $pluginPath = isset($pluginData['path']) ? (string)$pluginData['path'] : '';
        $pluginPathParts = explode('/', $pluginPath);
        $pluginDir = reset($pluginPathParts);
        $rules = isset($pageData['rules']) && is_array($pageData['rules'])
            ? $pageData['rules']
            : array();
        $pluginRules = isset($rules[$pluginPath]) && is_array($rules[$pluginPath])
            ? $rules[$pluginPath]
            : array();
        $pluginStatus = isset($pluginRules['status']) ? $pluginRules['status'] : array();

        if ( ! is_array($pluginStatus)) {
            $pluginStatus = array($pluginStatus);
        }

        $pluginStatus = array_values(array_unique(array_filter(array_map('sanitize_key', $pluginStatus))));

        $viewData = array(
            'plugin_path' => $pluginPath,
            'rules'       => $pluginRules
        );

        $viewData['is_unload_site_wide'] = in_array('unload_site_wide', $pluginStatus, true);
        $viewData['is_unload_via_regex'] = in_array('unload_via_regex', $pluginStatus, true);
        $viewData['is_unload_logged_in_via_role'] = in_array('unload_logged_in_via_role', $pluginStatus, true);
        $viewData['unload_logged_in_via_role_chosen'] = self::getRuleValues($pluginRules, 'unload_logged_in_via_role');
        $viewData['unload_via_regex_value'] = self::getRuleTextValue($pluginRules, 'unload_via_regex');

        if (empty($viewData['unload_logged_in_via_role_chosen'])) {
            $viewData['is_unload_logged_in_via_role'] = false;
        }

        $viewData['is_load_via_regex'] = in_array('load_via_regex', $pluginStatus, true);

        // Older Pro versions stored the RegEx exception enable flag outside status[].
        if (isset($pluginRules['load_via_regex']['enable']) && $pluginRules['load_via_regex']['enable']) {
            $viewData['is_load_via_regex'] = true;
        }

        $viewData['is_load_logged_in_via_role'] = in_array('load_logged_in_via_role', $pluginStatus, true);
        $viewData['load_logged_in_via_role_chosen'] = self::getRuleValues($pluginRules, 'load_logged_in_via_role');
        $viewData['load_via_regex_value'] = self::getRuleTextValue($pluginRules, 'load_via_regex');

        $unloadRulesCount = (int)$viewData['is_unload_site_wide']
            + (int)$viewData['is_unload_via_regex']
            + (int)$viewData['is_unload_logged_in_via_role'];
        $loadExceptionsCount = (int)$viewData['is_load_via_regex']
            + (int)$viewData['is_load_logged_in_via_role'];

        $contractedList = isset($pageData['plugins_contracted_list']) && is_array($pageData['plugins_contracted_list'])
            ? $pageData['plugins_contracted_list']
            : array();

        return array(
            'data'              => $viewData,
            'plugin_data'       => $pluginData,
            'plugin_path'       => $pluginPath,
            'plugin_dir'        => $pluginDir,
            'plugin_status'         => $pluginStatus,
            'plugin_area_state'     => array_key_exists($pluginPath, $contractedList) ? 'contracted' : 'expanded',
            'unload_rules_count'    => $unloadRulesCount,
            'load_exceptions_count' => $loadExceptionsCount
        );
    }

    /**
     * Read the row expansion preference left by Pro without loading any Pro
     * execution or persistence code.
     *
     * @param string $location
     *
     * @return array
     */
    public static function getPluginsContractedList($location = 'front')
    {
        $location = $location === 'dash' ? 'dash' : 'front';
        $globalKey = 'plugin_row_contracted';
        $emptyData = array($globalKey => array('front' => array(), 'dash' => array()));
        $existingData = Main::instance()->existingList(
            get_option(WPACU_PLUGIN_ID . '_global_data'),
            $emptyData
        );
        $decodedData = isset($existingData['list']) && is_array($existingData['list'])
            ? $existingData['list']
            : $emptyData;

        if (
            ! isset($decodedData[$globalKey][$location])
            || ! is_array($decodedData[$globalKey][$location])
        ) {
            return array();
        }

        return $decodedData[$globalKey][$location];
    }

    /**
     * @return array
     */
    public static function getPublicPostTypes()
    {
        static $postTypes = null;

        if ($postTypes !== null) {
            return $postTypes;
        }

        $postTypes = array();
        $objects = get_post_types(array('public' => true), 'objects');

        foreach ((array)$objects as $postTypeKey => $postTypeObject) {
            if ($postTypeKey === 'attachment') {
                continue;
            }

            $postTypes[$postTypeKey] = isset($postTypeObject->labels->name)
                ? $postTypeObject->labels->name
                : $postTypeKey;
        }

        return $postTypes;
    }

    /**
     * @return array
     */
    public static function getPublicTaxonomies()
    {
        static $taxonomies = null;

        if ($taxonomies !== null) {
            return $taxonomies;
        }

        $taxonomies = array();
        $objects = get_taxonomies(array('public' => true), 'objects');

        foreach ((array)$objects as $taxonomyKey => $taxonomyObject) {
            $taxonomies[$taxonomyKey] = isset($taxonomyObject->labels->name)
                ? $taxonomyObject->labels->name
                : $taxonomyKey;
        }

        return $taxonomies;
    }

    /**
     * @return array
     */
    public static function getArchiveTypes()
    {
        $archiveTypes = PluginsManagerAdmin::generateArchivePageTypesList(false);

        return is_array($archiveTypes) ? $archiveTypes : array();
    }

    /**
     * @return array
     */
    public static function getUserRoles()
    {
        static $roles = null;

        if ($roles !== null) {
            return $roles;
        }

        $roles = array();
        $rolesObject = wp_roles();

        if (isset($rolesObject->roles) && is_array($rolesObject->roles)) {
            foreach ($rolesObject->roles as $roleKey => $roleData) {
                $roleName = isset($roleData['name']) ? $roleData['name'] : $roleKey;
                $roles[$roleKey] = function_exists('translate_user_role')
                    ? translate_user_role($roleName)
                    : $roleName;
            }
        }

        return $roles;
    }

    /**
     * @return string
     */
    public static function getAlwaysLoadedStatus()
    {
        $label = __('Always loaded', 'wp-asset-clean-up');

        return sprintf(
            '<span class="wpacu-pm-always-loaded-status wpacu-pm-always-loaded-status--minimal-dot" data-wpacu-always-loaded-status-type="minimal-dot" title="%1$s"><span class="wpacu-pm-always-loaded-status-dot" aria-hidden="true"></span><span class="wpacu-pm-always-loaded-status-text">%2$s</span></span>',
            esc_attr($label),
            esc_html($label)
        );
    }

    /**
     * @param array  $values
     * @param array  $options
     * @param string $fallbackPrefix
     *
     * @return array
     */
    public static function getSelectedLabels($values, $options, $fallbackPrefix = '#')
    {
        $labels = array();

        foreach ((array)$values as $value) {
            $valueKey = (string)$value;

            if (isset($options[$valueKey])) {
                $labels[$valueKey] = $options[$valueKey];
            } else {
                $labels[$valueKey] = $fallbackPrefix . $valueKey;
            }
        }

        return $labels;
    }

    /**
     * Resolve entity IDs used by preserved Pro rules in three bulk queries.
     *
     * @param array $rules
     *
     * @return array
     */
    public static function prepareRuleEntityLabelMaps($rules)
    {
        $ids = array(
            'post'   => array(),
            'term'   => array(),
            'author' => array()
        );
        $ruleTypes = self::getEntityRuleTypes();

        foreach ((array)$rules as $pluginRules) {
            if ( ! is_array($pluginRules)) {
                continue;
            }

            foreach ($ruleTypes as $ruleKey => $entityType) {
                foreach (self::getRuleValues($pluginRules, $ruleKey) as $value) {
                    $entityId = is_scalar($value) ? absint($value) : 0;

                    if ($entityId > 0) {
                        $ids[$entityType][$entityId] = $entityId;
                    }
                }
            }
        }

        $labels = array(
            'post'   => array(),
            'term'   => array(),
            'author' => array()
        );

        if ( ! empty($ids['post'])) {
            $posts = get_posts(array(
                'post_type'        => array_values(get_post_types()),
                'post_status'      => array_values(get_post_stati()),
                'posts_per_page'   => -1,
                'post__in'         => array_values($ids['post']),
                'orderby'          => 'post__in',
                'suppress_filters' => false
            ));

            foreach ((array)$posts as $post) {
                $title = trim((string)get_the_title($post));
                $labels['post'][(int)$post->ID] = $title !== ''
                    ? $title
                    : __('Untitled content', 'wp-asset-clean-up');
            }
        }

        if ( ! empty($ids['term'])) {
            $terms = get_terms(array(
                'taxonomy'   => get_taxonomies(),
                'include'    => array_values($ids['term']),
                'hide_empty' => false
            ));

            if ( ! is_wp_error($terms)) {
                foreach ((array)$terms as $term) {
                    $labels['term'][(int)$term->term_id] = (string)$term->name;
                }
            }
        }

        if ( ! empty($ids['author'])) {
            $users = get_users(array(
                'include' => array_values($ids['author']),
                'fields'  => array('ID', 'display_name')
            ));

            foreach ((array)$users as $user) {
                $displayName = trim((string)$user->display_name);
                $labels['author'][(int)$user->ID] = $displayName !== ''
                    ? $displayName
                    : __('Unnamed user', 'wp-asset-clean-up');
            }
        }

        foreach ($labels as $entityType => $entityLabels) {
            $labelCounts = array_count_values(array_map('strval', $entityLabels));

            foreach ($entityLabels as $entityId => $entityLabel) {
                if (isset($labelCounts[(string)$entityLabel]) && $labelCounts[(string)$entityLabel] > 1) {
                    $labels[$entityType][$entityId] = sprintf(
                        __('%1$s (ID: %2$d)', 'wp-asset-clean-up'),
                        $entityLabel,
                        $entityId
                    );
                }
            }
        }

        return $labels;
    }

    /**
     * @param string $ruleKey
     * @param array  $values
     * @param array  $labelMaps
     *
     * @return array
     */
    public static function getRuleEntityLabels($ruleKey, $values, $labelMaps)
    {
        $ruleTypes = self::getEntityRuleTypes();

        if ( ! isset($ruleTypes[$ruleKey])) {
            return array_map('strval', (array)$values);
        }

        $entityType = $ruleTypes[$ruleKey];
        $entityLabels = isset($labelMaps[$entityType]) && is_array($labelMaps[$entityType])
            ? $labelMaps[$entityType]
            : array();
        $fallbackLabels = array(
            'post'   => __('Deleted content (ID: %d)', 'wp-asset-clean-up'),
            'term'   => __('Deleted term (ID: %d)', 'wp-asset-clean-up'),
            'author' => __('Deleted user (ID: %d)', 'wp-asset-clean-up')
        );
        $labels = array();

        foreach ((array)$values as $value) {
            $entityId = is_scalar($value) ? absint($value) : 0;

            if ($entityId > 0 && isset($entityLabels[$entityId])) {
                $labels[] = $entityLabels[$entityId];
            } elseif ($entityId > 0) {
                $labels[] = sprintf($fallbackLabels[$entityType], $entityId);
            } else {
                $savedValue = is_scalar($value) ? (string)$value : wp_json_encode($value);
                $labels[] = sprintf(__('Unknown saved value: %s', 'wp-asset-clean-up'), $savedValue);
            }
        }

        return $labels;
    }

    /**
     * @return array
     */
    private static function getEntityRuleTypes()
    {
        return array(
            'unload_via_post'           => 'post',
            'load_via_post'             => 'post',
            'unload_via_post_tax_term'  => 'term',
            'load_via_post_tax_term'    => 'term',
            'unload_via_tax_term'       => 'term',
            'load_via_tax_term'         => 'term',
            'unload_via_author_archive' => 'author',
            'load_via_author_archive'   => 'author'
        );
    }

    /**
     * @param array  $pluginRules
     * @param string $ruleKey
     *
     * @return array
     */
    private static function getRuleValues($pluginRules, $ruleKey)
    {
        if (
            isset($pluginRules[$ruleKey]['values'])
            && is_array($pluginRules[$ruleKey]['values'])
        ) {
            return array_values($pluginRules[$ruleKey]['values']);
        }

        return array();
    }

    /**
     * @param array  $pluginRules
     * @param string $ruleKey
     *
     * @return string
     */
    private static function getRuleTextValue($pluginRules, $ruleKey)
    {
        if (isset($pluginRules[$ruleKey]['value']) && ! is_array($pluginRules[$ruleKey]['value'])) {
            return (string)$pluginRules[$ruleKey]['value'];
        }

        return '';
    }

    /**
     * @param array $groups
     *
     * @return array
     */
    private static function countRuleGroups($groups)
    {
        $counts = array();

        foreach ($groups as $groupKey => $groupValues) {
            $counts[$groupKey] = count(array_filter($groupValues));
        }

        return $counts;
    }
}
