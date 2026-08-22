<?php
namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\AssetsManager;
use WpAssetCleanUp\Main;
use WpAssetCleanUp\Menu;
use WpAssetCleanUp\MetaBoxes;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\MiscArray;
use WpAssetCleanUp\OptimiseAssets\CriticalCss;
use WpAssetCleanUp\OptimiseAssets\MinifyCss;

/**
 * Class CriticalCssAdmin
 */
class CriticalCssAdmin
{
    /**
     * @var string
     */
    public static $defaultLayout = 'classic';

    /**
     * These are stored as keys with values in the database.
     * This will be later filled with custom post types and custom taxonomies, if any.
     *
     * @var string[]
     */
    public static $allDbLocationKeyPages = array(
        'homepage', 'posts', 'pages', 'media', 'category', 'tag', 'search', 'author', 'date', '404_not_found'
    );

    /**
     * CriticalCssAdmin constructor.
     */
    public function __construct()
    {
        // Dashboard management: "CSS & JS Manager" -> "Manage Critical CSS"
        $wpacuSubPage = isset($_GET['wpacu_sub_page']) ? $_GET['wpacu_sub_page'] : '';

        if ($wpacuSubPage === 'manage_critical_css') {
            add_action('admin_init', function() {
                self::$allDbLocationKeyPages = CriticalCssAdmin::fillAllDbLocationKeyPages(self::$allDbLocationKeyPages);
            }, 1);

            add_action('admin_enqueue_scripts', function() {
                $styleAssetKey = self::getManagerLayout() === 'classic'
                    ? 'critical_css_admin_classic'
                    : 'critical_css_admin';

                if ( ! isset(\WpAssetCleanUp\OwnAssets::$ownAssets['styles'][$styleAssetKey]) ) {
                    \WpAssetCleanUp\OwnAssets::prepareVars();
                }

                $styleAsset = \WpAssetCleanUp\OwnAssets::$ownAssets['styles'][$styleAssetKey];
                $coreStyle  = \WpAssetCleanUp\OwnAssets::$ownAssets['styles']['style_core'];

                wp_enqueue_style(
                    $styleAsset['handle'],
                    plugins_url($styleAsset['rel_path'], WPACU_PLUGIN_FILE),
                    array($coreStyle['handle']),
                    \WpAssetCleanUp\OwnAssets::assetVer($styleAsset['rel_path'])
                );
            }, 30);
        }

        add_action('admin_init', array($this, 'updateCriticalCss'));

        // Global Critical CSS master switch shown directly in the manager.
        add_action(
            'wp_ajax_' . WPACU_PLUGIN_ID . '_update_critical_css_global_status',
            array($this, 'ajaxUpdateCriticalCssGlobalStatus')
        );
    }

    /**
     * Return the Critical CSS manager layout without storing a UI preference in the database.
     * Define WPACU_CRITICAL_CSS_MANAGER_LAYOUT as either "classic" or "compact" while testing,
     * or use the wpacu_critical_css_manager_layout filter.
     *
     * @return string
     */
    public static function getManagerLayout()
    {
        $layout = defined('WPACU_CRITICAL_CSS_MANAGER_LAYOUT')
            ? constant('WPACU_CRITICAL_CSS_MANAGER_LAYOUT')
            : self::$defaultLayout;

        $layout = sanitize_key((string)apply_filters('wpacu_critical_css_manager_layout', $layout));

        return in_array($layout, array('classic', 'compact'), true) ? $layout : self::$defaultLayout;
    }

    /**
     * Return the selected Custom Post Types view. The older
     * wpacu_for=custom_post_type_archives URL remains supported.
     *
     * @param string $wpacuFor
     *
     * @return string Empty when this is not a Custom Post Types context.
     */
    public static function getCustomPostTypeView($wpacuFor)
    {
        if ($wpacuFor === 'custom_post_type_archives') {
            return 'archives';
        }

        if ($wpacuFor !== 'custom_post_types') {
            return '';
        }

        $postTypeView = sanitize_key(Misc::getVar('get', 'wpacu_post_type_view', 'singular'));

        return $postTypeView === 'archives' ? 'archives' : 'singular';
    }

    /**
     * Build the parent Custom Post Types URL used by the Singular / Archives navigation.
     *
     * @param string $postTypeView
     * @param string $postType
     *
     * @return string
     */
    public static function getCustomPostTypesManagementUrl($postTypeView = 'singular', $postType = '')
    {
        $queryArgs = array(
            'page'                 => WPACU_PLUGIN_ID . '_assets_manager',
            'wpacu_sub_page'       => 'manage_critical_css',
            'wpacu_for'            => 'custom_post_types',
            'wpacu_post_type_view' => $postTypeView === 'archives' ? 'archives' : 'singular'
        );

        if ($postType !== '') {
            $queryArgs['wpacu_current_post_type'] = sanitize_key($postType);
        }

        return add_query_arg($queryArgs, admin_url('admin.php'));
    }

    /**
     * Prepare the data shared by the classic and compact Critical CSS manager layouts.
     * This method only resolves context, storage and saved rule data; all HTML stays in templates.
     *
     * @param array $data
     *
     * @return array
     */
    public static function prepareManagerViewData($data)
    {
        if ( ! is_array($data) ) {
            $data = array();
        }

        $criticalCssConfigJson = get_option(WPACU_PLUGIN_ID . '_critical_css_config');
        $criticalCssConfig     = json_decode($criticalCssConfigJson, true);

        if (wpacuJsonLastError() !== JSON_ERROR_NONE || ! is_array($criticalCssConfig)) {
            $criticalCssConfig = array();
        }

        $data['critical_css_layout']                     = self::getManagerLayout();
        $data['critical_css_config']                     = $criticalCssConfig;
        $data['critical_css_tabs_all_enabled_locations'] = ! empty($criticalCssConfig)
            ? self::getAllEnabledLocations($criticalCssConfig)
            : array();
        $data['critical_css_is_global_disabled']         = isset($data['wpacu_settings']['critical_css_status'])
            && $data['wpacu_settings']['critical_css_status'] === 'off';
        $data['critical_css_rule_stats']                  = self::getCriticalCssRuleStats();
        $data['show_critical_css_options']                = true;
        $data['show_no_records_notice']                   = false;
        $data['critical_css_location_key']                = false;
        $data['critical_css_scope']                       = 'general';
        $data['critical_css_storage']                     = array();
        $data['critical_css_specific_rules']              = array();
        $data['critical_css_granular_view']               = array();
        $data['critical_css_show_editor']                 = false;

        $requestedWpacuFor = isset($data['for']) ? sanitize_key($data['for']) : '';
        $postTypeView      = self::getCustomPostTypeView($requestedWpacuFor);
        $wpacuFor          = $requestedWpacuFor;

        // Keep the existing archive context internally. Only the navigation is grouped
        // under the Custom Post Types parent tab.
        if ($requestedWpacuFor === 'custom_post_types' && $postTypeView === 'archives') {
            $wpacuFor = 'custom_post_type_archives';
        }

        $data['for']                         = $wpacuFor;
        $data['critical_css_post_type_view'] = $postTypeView;

        $locationKey = false;

        if ($wpacuFor === '') {
            return $data;
        }

        if ($wpacuFor === 'homepage') {
            $locationKey = 'homepage';
        } elseif (in_array($wpacuFor, array('posts', 'pages', 'media_attachment'), true)) {
            if ($wpacuFor === 'posts') {
                $postTypeToCheck   = 'post';
                $postStatusToCheck = array('publish', 'private');
                $locationKey       = 'posts';
            } elseif ($wpacuFor === 'pages') {
                $postTypeToCheck   = 'page';
                $postStatusToCheck = array('publish', 'private');
                $locationKey       = 'pages';
            } else {
                $postTypeToCheck   = 'attachment';
                $postStatusToCheck = array('inherit');
                $locationKey       = 'media';
            }

            $postCounts = wp_count_posts($postTypeToCheck);
            $recordsCount = 0;

            foreach ($postStatusToCheck as $postStatus) {
                if (isset($postCounts->{$postStatus})) {
                    $recordsCount += (int)$postCounts->{$postStatus};
                }
            }

            if ($recordsCount < 1) {
                $data['show_no_records_notice'] = true;
            }
        } elseif ($wpacuFor === 'custom_post_types') {
            $data['custom_post_types_list'] = MiscAdmin::getCustomPostTypesList();

            if ( ! empty($data['custom_post_types_list']) ) {
                $chosenPostType = sanitize_key(Misc::getVar('get', 'wpacu_current_post_type', ''));

                if ($chosenPostType === '' || ! isset($data['custom_post_types_list'][$chosenPostType])) {
                    $chosenPostType = Misc::arrayKeyFirst($data['custom_post_types_list']);
                }

                $data['chosen_post_type'] = $chosenPostType;
                $locationKey              = 'custom_post_type_' . $chosenPostType;
            } else {
                $data['show_critical_css_options'] = false;
            }
        } elseif ($wpacuFor === 'custom_post_type_archives') {
            $data['custom_post_type_archives_list'] = AssetsManagerAdmin::getCustomPostTypeArchives();

            if ( ! empty($data['custom_post_type_archives_list']) ) {
                $chosenPostType = sanitize_key(Misc::getVar('get', 'wpacu_current_post_type', ''));

                if ($chosenPostType === '' || ! isset($data['custom_post_type_archives_list'][$chosenPostType])) {
                    $chosenPostType = Misc::arrayKeyFirst($data['custom_post_type_archives_list']);
                }

                $data['chosen_post_type'] = $chosenPostType;
                $locationKey              = 'custom_post_type_archive_' . $chosenPostType;
            } else {
                $data['show_critical_css_options'] = false;
            }
        } elseif ($wpacuFor === 'category') {
            $locationKey = 'category';
        } elseif ($wpacuFor === 'tag') {
            $locationKey = 'tag';
        } elseif ($wpacuFor === 'custom_taxonomies') {
            $data['custom_taxonomies_list'] = MiscAdmin::getCustomTaxonomyList();

            if ( ! empty($data['custom_taxonomies_list']) ) {
                $chosenTaxonomy = sanitize_key(Misc::getVar('get', 'wpacu_current_taxonomy', ''));

                if ($chosenTaxonomy === '' || ! isset($data['custom_taxonomies_list'][$chosenTaxonomy])) {
                    $chosenTaxonomy = Misc::arrayKeyFirst($data['custom_taxonomies_list']);
                }

                $data['chosen_taxonomy'] = $chosenTaxonomy;
                $locationKey             = 'custom_taxonomy_' . $chosenTaxonomy;
            } else {
                $data['show_critical_css_options'] = false;
            }
        } elseif ($wpacuFor === 'search') {
            $locationKey = 'search';
        } elseif ($wpacuFor === 'author') {
            $locationKey = 'author';
        } elseif ($wpacuFor === 'date') {
            $locationKey = 'date';
        } elseif ($wpacuFor === '404_not_found') {
            $locationKey = '404_not_found';
        } elseif ($wpacuFor === 'via_code') {
            $locationKey = 'via_code';
            $data['show_critical_css_options'] = false;
        }

        $data['critical_css_location_key'] = $locationKey;

        if ( ! $locationKey || $locationKey === 'via_code') {
            return $data;
        }

        $scope         = self::getManagementScope($wpacuFor);
        $storage       = self::getStorageContextFromRequest($wpacuFor, $locationKey, $scope);
        $specificRules = self::supportsGranularManagement($wpacuFor)
            ? self::getGranularCriticalCssRules($wpacuFor, $locationKey)
            : array();
        $showEditor    = ! self::supportsGranularManagement($wpacuFor)
            || $scope === 'general'
            || ( ! empty($storage['is_valid']) && ! empty($storage['is_granular']) );

        if (empty($storage['is_valid']) || ! $showEditor) {
            $data['show_critical_css_options'] = false;
        }

        $data['critical_css_scope']          = $scope;
        $data['critical_css_storage']        = $storage;
        $data['critical_css_specific_rules'] = $specificRules;
        $data['critical_css_show_editor']    = $showEditor;

        if (self::supportsGranularManagement($wpacuFor)) {
            $data['critical_css_granular_view'] = self::getGranularManagementViewData(
                $wpacuFor,
                $locationKey,
                $scope,
                $storage,
                $specificRules,
                $data
            );
        }

        return $data;
    }

    /**
     * Prepare labels, URLs and object counts used by both granular layout templates.
     *
     * @param string $wpacuFor
     * @param string $locationKey
     * @param string $scope
     * @param array  $storageContext
     * @param array  $specificRules
     * @param array  $data
     *
     * @return array
     */
    public static function getGranularManagementViewData($wpacuFor, $locationKey, $scope, $storageContext, $specificRules, $data)
    {
        $viewData = array(
            'plural_label'             => '',
            'singular_label'           => '',
            'general_label'            => '',
            'manage_objects_url'       => '',
            'context_type'             => '',
            'context_value'            => '',
            'show_all_limit_key'       => '',
            'placeholder'              => '',
            'general_url'              => self::getGeneralManagementUrl($wpacuFor, $locationKey),
            'specific_url'             => self::getSpecificManagementUrl($wpacuFor, $locationKey),
            'rules_count'              => count($specificRules),
            'enabled_count'            => 0,
            'is_selected_object'       => ! empty($storageContext['is_valid']) && ! empty($storageContext['is_granular']),
            'selected_object_id'       => ! empty($storageContext['is_granular']) ? (int)$storageContext['object_id'] : 0,
            'selected_rule_exists'     => false,
            'load_search_form'         => false,
            'total_objects'            => 0,
            'show_all_limit'           => 0,
            'show_all_on_focus'        => false,
            'show_search_initially'    => false,
            'media_permalink_disabled' => false
        );

        if ($wpacuFor === 'posts') {
            $viewData['plural_label']       = __('Posts', 'wp-asset-clean-up');
            $viewData['singular_label']     = __('Post', 'wp-asset-clean-up');
            $viewData['general_label']      = __('All Posts', 'wp-asset-clean-up');
            $viewData['manage_objects_url'] = admin_url('edit.php');
            $viewData['context_type']       = 'post_type';
            $viewData['context_value']      = 'post';
            $viewData['show_all_limit_key'] = 'posts';
            $viewData['placeholder']        = __('Search Posts by title or ID', 'wp-asset-clean-up');
        } elseif ($wpacuFor === 'pages') {
            $viewData['plural_label']       = __('Pages', 'wp-asset-clean-up');
            $viewData['singular_label']     = __('Page', 'wp-asset-clean-up');
            $viewData['general_label']      = __('All Pages', 'wp-asset-clean-up');
            $viewData['manage_objects_url'] = admin_url('edit.php?post_type=page');
            $viewData['context_type']       = 'post_type';
            $viewData['context_value']      = 'page';
            $viewData['show_all_limit_key'] = 'pages';
            $viewData['placeholder']        = __('Search Pages by title or ID', 'wp-asset-clean-up');
        } elseif ($wpacuFor === 'media_attachment') {
            $viewData['plural_label']       = __('Media attachment pages', 'wp-asset-clean-up');
            $viewData['singular_label']     = __('Media attachment', 'wp-asset-clean-up');
            $viewData['general_label']      = __('All Attachment Pages', 'wp-asset-clean-up');
            $viewData['manage_objects_url'] = admin_url('upload.php');
            $viewData['context_type']       = 'post_type';
            $viewData['context_value']      = 'attachment';
            $viewData['show_all_limit_key'] = 'media';
            $viewData['placeholder']        = __('Search Media by title or ID', 'wp-asset-clean-up');
        } elseif ($wpacuFor === 'custom_post_types') {
            $postType = isset($data['chosen_post_type']) ? $data['chosen_post_type'] : '';
            $postTypeObject = $postType !== '' ? get_post_type_object($postType) : false;

            $viewData['plural_label'] = ($postTypeObject && isset($postTypeObject->labels->name))
                ? $postTypeObject->labels->name
                : $postType;
            $viewData['singular_label'] = ($postTypeObject && isset($postTypeObject->labels->singular_name))
                ? $postTypeObject->labels->singular_name
                : $postType;
            $viewData['general_label']      = sprintf(__('All %s', 'wp-asset-clean-up'), $viewData['plural_label']);
            $viewData['manage_objects_url'] = admin_url('edit.php?post_type=' . rawurlencode($postType));
            $viewData['context_type']       = 'post_type';
            $viewData['context_value']      = $postType;
            $viewData['show_all_limit_key'] = 'custom_post_types';
            $viewData['placeholder']        = sprintf(__('Search %s by title or ID', 'wp-asset-clean-up'), $viewData['plural_label']);
        } elseif ($wpacuFor === 'category') {
            $viewData['plural_label']       = __('Categories', 'wp-asset-clean-up');
            $viewData['singular_label']     = __('Category', 'wp-asset-clean-up');
            $viewData['general_label']      = __('All Categories', 'wp-asset-clean-up');
            $viewData['manage_objects_url'] = admin_url('edit-tags.php?taxonomy=category');
            $viewData['context_type']       = 'taxonomy';
            $viewData['context_value']      = 'category';
            $viewData['show_all_limit_key'] = 'taxonomies';
            $viewData['placeholder']        = __('Search Categories by name or ID', 'wp-asset-clean-up');
        } elseif ($wpacuFor === 'tag') {
            $viewData['plural_label']       = __('Tags', 'wp-asset-clean-up');
            $viewData['singular_label']     = __('Tag', 'wp-asset-clean-up');
            $viewData['general_label']      = __('All Tags', 'wp-asset-clean-up');
            $viewData['manage_objects_url'] = admin_url('edit-tags.php?taxonomy=post_tag');
            $viewData['context_type']       = 'taxonomy';
            $viewData['context_value']      = 'post_tag';
            $viewData['show_all_limit_key'] = 'taxonomies';
            $viewData['placeholder']        = __('Search Tags by name or ID', 'wp-asset-clean-up');
        } elseif ($wpacuFor === 'custom_taxonomies') {
            $taxonomy = isset($data['chosen_taxonomy']) ? $data['chosen_taxonomy'] : '';
            $taxonomyObject = $taxonomy !== '' ? get_taxonomy($taxonomy) : false;

            $viewData['plural_label'] = ($taxonomyObject && isset($taxonomyObject->labels->name))
                ? $taxonomyObject->labels->name
                : $taxonomy;
            $viewData['singular_label'] = ($taxonomyObject && isset($taxonomyObject->labels->singular_name))
                ? $taxonomyObject->labels->singular_name
                : $taxonomy;

            $taxonomyQueryArgs = array('taxonomy' => $taxonomy);

            if ($taxonomyObject && ! empty($taxonomyObject->object_type[0]) && $taxonomyObject->object_type[0] !== 'post') {
                $taxonomyQueryArgs['post_type'] = $taxonomyObject->object_type[0];
            }

            $viewData['general_label']      = sprintf(__('All %s', 'wp-asset-clean-up'), $viewData['plural_label']);
            $viewData['manage_objects_url'] = add_query_arg($taxonomyQueryArgs, admin_url('edit-tags.php'));
            $viewData['context_type']       = 'taxonomy';
            $viewData['context_value']      = $taxonomy;
            $viewData['show_all_limit_key'] = 'taxonomies';
            $viewData['placeholder']        = sprintf(__('Search %s by name or ID', 'wp-asset-clean-up'), $viewData['plural_label']);
        } elseif ($wpacuFor === 'author') {
            $viewData['plural_label']       = __('Authors', 'wp-asset-clean-up');
            $viewData['singular_label']     = __('Author', 'wp-asset-clean-up');
            $viewData['general_label']      = __('All Authors', 'wp-asset-clean-up');
            $viewData['manage_objects_url'] = admin_url('users.php');
            $viewData['context_type']       = 'user';
            $viewData['context_value']      = 'author';
            $viewData['show_all_limit_key'] = 'users';
            $viewData['placeholder']        = __('Search Authors by name or ID', 'wp-asset-clean-up');
        }

        foreach ($specificRules as $specificRule) {
            if ( ! empty($specificRule['enable']) ) {
                $viewData['enabled_count']++;
            }

            if ((int)$specificRule['object_id'] === $viewData['selected_object_id']) {
                $viewData['selected_rule_exists'] = true;
            }
        }

        $viewData['load_search_form'] = $viewData['context_type'] !== '' && $viewData['context_value'] !== '';

        if ($viewData['load_search_form'] && $viewData['context_type'] === 'post_type') {
            if ($viewData['context_value'] === 'attachment' && MetaBoxes::isMediaWithPermalinkDeactivated()) {
                $viewData['load_search_form']         = false;
                $viewData['media_permalink_disabled'] = true;
            } else {
                $postCounts   = wp_count_posts($viewData['context_value']);
                $postStatuses = $viewData['context_value'] === 'attachment' ? array('inherit') : array('publish', 'private');

                foreach ($postStatuses as $postStatus) {
                    if (isset($postCounts->{$postStatus})) {
                        $viewData['total_objects'] += (int)$postCounts->{$postStatus};
                    }
                }
            }
        } elseif ($viewData['load_search_form'] && $viewData['context_type'] === 'taxonomy') {
            $termsCount = wp_count_terms($viewData['context_value'], array('hide_empty' => false));
            $viewData['total_objects'] = is_wp_error($termsCount) ? 0 : (int)$termsCount;
        } elseif ($viewData['load_search_form'] && $viewData['context_type'] === 'user') {
            $usersCountData = count_users();
            $viewData['total_objects'] = isset($usersCountData['total_users']) ? (int)$usersCountData['total_users'] : 0;
        }

        if ($viewData['load_search_form'] && $viewData['total_objects'] < 1) {
            $viewData['load_search_form'] = false;
        }

        $viewData['show_all_limit'] = isset(AjaxSearchPagesAutocomplete::$showAllResultsIfCountIsUpToArray[$viewData['show_all_limit_key']])
            ? (int)AjaxSearchPagesAutocomplete::$showAllResultsIfCountIsUpToArray[$viewData['show_all_limit_key']]
            : 0;
        $viewData['show_all_on_focus'] = $viewData['show_all_limit'] > 0
            && $viewData['total_objects'] <= $viewData['show_all_limit'];
        $viewData['show_search_initially'] = $scope === 'specific'
            && $viewData['rules_count'] === 0
            && ! $viewData['is_selected_object'];

        return $viewData;
    }

    /**
     * @param array $criticalCssConfig
     *
     * @return array
     */
    public static function getAllEnabledLocations($criticalCssConfig)
    {
        $allEnabledLocations = array();

        foreach (self::$allDbLocationKeyPages as $locationKey) {
            if (is_string($locationKey) && isset($criticalCssConfig[$locationKey]['enable']) && $criticalCssConfig[$locationKey]['enable']) {
                $allEnabledLocations[] = $locationKey;
            }
        }

        return $allEnabledLocations;
    }

    /**
     * @param array $allPossibleKeys
     *
     * @return array
     */
    public static function fillAllDbLocationKeyPages($allPossibleKeys)
    {
        if ( ! empty(MiscAdmin::getCustomPostTypesList()) ) {
            $allPossibleKeys[] = 'custom_post_types';
        }

        if ( ! empty(AssetsManagerAdmin::getCustomPostTypeArchives()) ) {
            $allPossibleKeys[] = 'custom_post_type_archives';
        }

        if ( ! empty(MiscAdmin::getCustomTaxonomyList()) ) {
            $allPossibleKeys[] = 'custom_taxonomies';
        }

        return $allPossibleKeys;
    }

    /**
     * @param array  $postTypesList
     * @param string $chosenPostType
     * @param array  $criticalCssConfig
     */
    public static function buildCustomPostTypesListLinks($postTypesList, $chosenPostType, $criticalCssConfig)
    {
        ?>
        <ul id="wpacu_custom_pages_nav_links">
            <?php
            foreach ($postTypesList as $postTypeKey => $postTypeValue) {
                $liClass    = ($chosenPostType === $postTypeKey) ? 'wpacu-current' : '';
                $locationKey = 'custom_post_type_' . $postTypeKey;
                $navLink    = self::getCustomPostTypesManagementUrl('singular', $postTypeKey);
                $isEnabled  = (isset($criticalCssConfig[$locationKey]['enable']) && $criticalCssConfig[$locationKey]['enable'])
                    || self::hasEnabledGranularCriticalCssForLocation($locationKey);
                $wpacuStatus = $isEnabled ? 'wpacu-on' : 'wpacu-off';
                ?>
                <li class="<?php echo esc_attr($liClass); ?>">
                    <a href="<?php echo esc_url($navLink); ?>"><?php echo wp_kses_post($postTypeValue); ?><span data-wpacu-custom-page-type="<?php echo esc_attr($postTypeKey); ?>_post_type" class="wpacu-circle-status <?php echo esc_attr($wpacuStatus); ?>"></span></a>
                </li>
                <?php
            }
            ?>
        </ul>
        <?php
    }

    /**
     * @param array  $taxonomyList
     * @param string $chosenTaxonomy
     * @param array  $criticalCssConfig
     */
    public static function buildTaxonomyListLinks($taxonomyList, $chosenTaxonomy, $criticalCssConfig)
    {
        ?>
        <ul id="wpacu_custom_pages_nav_links">
            <?php
            foreach ($taxonomyList as $taxonomyKey => $taxonomyValue) {
                $liClass     = ($chosenTaxonomy === $taxonomyKey) ? 'wpacu-current' : '';
                $locationKey = 'custom_taxonomy_' . $taxonomyKey;
                $navLink     = admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=custom_taxonomies&wpacu_current_taxonomy=' . rawurlencode($taxonomyKey));
                $isEnabled   = (isset($criticalCssConfig[$locationKey]['enable']) && $criticalCssConfig[$locationKey]['enable'])
                    || self::hasEnabledGranularCriticalCssForLocation($locationKey);
                $wpacuStatus = $isEnabled ? 'wpacu-on' : 'wpacu-off';
                ?>
                <li class="<?php echo esc_attr($liClass); ?>">
                    <a href="<?php echo esc_url($navLink); ?>"><?php echo wp_kses_post($taxonomyValue); ?><span data-wpacu-custom-page-type="<?php echo esc_attr($taxonomyKey); ?>_taxonomy" class="wpacu-circle-status <?php echo esc_attr($wpacuStatus); ?>"></span></a>
                </li>
                <?php
            }
            ?>
        </ul>
        <?php
    }

    /**
     * @param array  $criticalCssConfig
     * @param string $dbKeyPrefix
     *
     * @return bool
     */
    public static function isEnabledForAtLeastOnePageType($criticalCssConfig, $dbKeyPrefix)
    {
        if ($dbKeyPrefix === 'custom_taxonomy') {
            $availableObjects = MiscAdmin::getCustomTaxonomyList();
        } elseif ($dbKeyPrefix === 'custom_post_type') {
            $availableObjects = MiscAdmin::getCustomPostTypesList();
        } elseif ($dbKeyPrefix === 'custom_post_type_archive') {
            $availableObjects = AssetsManagerAdmin::getCustomPostTypeArchives();
        } else {
            return false;
        }

        foreach ($availableObjects as $objectKey => $objectLabel) {
            $locationKey = $dbKeyPrefix . '_' . $objectKey;

            if ((isset($criticalCssConfig[$locationKey]['enable']) && $criticalCssConfig[$locationKey]['enable'])
                || self::hasEnabledGranularCriticalCssForLocation($locationKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the storage context for the current Critical CSS management request.
     * The default context is the existing page-group option. A valid object ID switches
     * the storage to postmeta, termmeta or usermeta.
     *
     * @param string $wpacuFor
     * @param string $locationKey
     *
     * @return array
     */
    public static function getStorageContextFromRequest($wpacuFor, $locationKey, $scope = 'general')
    {
        $context = array(
            'storage_type' => 'option',
            'object_type'  => '',
            'object_id'    => 0,
            'object'       => false,
            'requested'    => false,
            'is_granular'  => false,
            'is_valid'     => true,
            'label'        => '',
            'type_label'   => '',
            'url'          => '',
            'general_url'  => self::getGeneralManagementUrl($wpacuFor, $locationKey),
            'error'        => ''
        );

        // The General sub-tab must always use the existing option-based rule, even if an object ID is present in the URL.
        if ($scope !== 'specific' || ! self::supportsGranularManagement($wpacuFor)) {
            return $context;
        }

        if (in_array($wpacuFor, array('posts', 'pages', 'media_attachment', 'custom_post_types'), true)) {
            $postId = (int)Misc::getVar('get', 'wpacu_post_id', 0);

            if ($postId < 1) {
                return $context;
            }

            $context['requested'] = true;
            $post = get_post($postId);

            if ( ! ($post instanceof \WP_Post) || self::getGeneralLocationKeyForPostType($post->post_type) !== $locationKey) {
                $context['is_valid'] = false;
                $context['error']    = __('The requested singular page is not valid for this Critical CSS section.', 'wp-asset-clean-up');
                return $context;
            }

            $postTypeObject = get_post_type_object($post->post_type);
            $postTitle      = get_the_title($postId);

            $context['storage_type'] = 'post_meta';
            $context['object_type']  = 'post';
            $context['object_id']    = $postId;
            $context['object']       = $post;
            $context['is_granular']  = true;
            $context['label']        = $postTitle !== '' ? $postTitle : __('(no title)', 'wp-asset-clean-up');
            $context['type_label']   = ($postTypeObject && isset($postTypeObject->labels->singular_name)) ? $postTypeObject->labels->singular_name : $post->post_type;
            $context['url']          = (string)get_permalink($postId);

            return $context;
        }

        if (in_array($wpacuFor, array('category', 'tag', 'custom_taxonomies'), true)) {
            $termId = (int)Misc::getVar('get', 'wpacu_term_id', 0);

            if ($termId < 1) {
                return $context;
            }

            $context['requested'] = true;
            $expectedTaxonomy = self::getTaxonomyFromLocationKey($locationKey);
            $term = $expectedTaxonomy ? get_term($termId, $expectedTaxonomy) : false;

            if ( ! ($term instanceof \WP_Term) || $term->taxonomy !== $expectedTaxonomy) {
                $context['is_valid'] = false;
                $context['error']    = __('The requested taxonomy term is not valid for this Critical CSS section.', 'wp-asset-clean-up');
                return $context;
            }

            $taxonomyObject = get_taxonomy($term->taxonomy);
            $termUrl        = get_term_link($term, $term->taxonomy);

            $context['storage_type'] = 'term_meta';
            $context['object_type']  = 'term';
            $context['object_id']    = (int)$term->term_id;
            $context['object']       = $term;
            $context['is_granular']  = true;
            $context['label']        = $term->name;
            $context['type_label']   = ($taxonomyObject && isset($taxonomyObject->labels->singular_name)) ? $taxonomyObject->labels->singular_name : $term->taxonomy;
            $context['url']          = is_wp_error($termUrl) ? '' : (string)$termUrl;

            return $context;
        }

        if ($wpacuFor === 'author') {
            $authorId = (int)Misc::getVar('get', 'wpacu_author_id', 0);

            if ($authorId < 1) {
                return $context;
            }

            $context['requested'] = true;
            $user = get_userdata($authorId);

            if ( ! ($user instanceof \WP_User) ) {
                $context['is_valid'] = false;
                $context['error']    = __('The requested author is not valid.', 'wp-asset-clean-up');
                return $context;
            }

            $context['storage_type'] = 'user_meta';
            $context['object_type']  = 'user';
            $context['object_id']    = $authorId;
            $context['object']       = $user;
            $context['is_granular']  = true;
            $context['label']        = $user->display_name;
            $context['type_label']   = __('Author', 'wp-asset-clean-up');
            $context['url']          = get_author_posts_url($authorId);

            return $context;
        }

        return $context;
    }

    /**
     * @param string $wpacuFor
     *
     * @return bool
     */
    public static function supportsGranularManagement($wpacuFor)
    {
        return in_array($wpacuFor, array(
            'posts', 'pages', 'media_attachment', 'custom_post_types',
            'category', 'tag', 'custom_taxonomies', 'author'
        ), true);
    }

    /**
     * Return the real page-reload sub-tab. Old URLs that already contain an object ID
     * are treated as Specific for backward compatibility.
     *
     * @param string $wpacuFor
     *
     * @return string
     */
    public static function getManagementScope($wpacuFor)
    {
        if ( ! self::supportsGranularManagement($wpacuFor) ) {
            return 'general';
        }

        $scope = sanitize_key(Misc::getVar('get', 'wpacu_critical_css_scope', ''));

        if ($scope === 'specific') {
            return 'specific';
        }

        if ($scope === 'general') {
            return 'general';
        }

        if ((int)Misc::getVar('get', 'wpacu_post_id', 0) > 0
            || (int)Misc::getVar('get', 'wpacu_term_id', 0) > 0
            || (int)Misc::getVar('get', 'wpacu_author_id', 0) > 0) {
            return 'specific';
        }

        return 'general';
    }

    /**
     * @param string $wpacuFor
     * @param string $locationKey
     *
     * @return string
     */
    public static function getGeneralManagementUrl($wpacuFor, $locationKey)
    {
        $queryArgs = array(
            'page'                     => WPACU_PLUGIN_ID . '_assets_manager',
            'wpacu_sub_page'           => 'manage_critical_css',
            'wpacu_for'                => $wpacuFor,
            'wpacu_critical_css_scope' => 'general'
        );

        // Check the archive prefix first because it also starts with "custom_post_type_".
        if (is_string($locationKey) && strpos($locationKey, 'custom_post_type_archive_') === 0) {
            $queryArgs['wpacu_for']            = 'custom_post_types';
            $queryArgs['wpacu_post_type_view'] = 'archives';
            $queryArgs['wpacu_current_post_type'] = substr($locationKey, strlen('custom_post_type_archive_'));
        } elseif (is_string($locationKey) && strpos($locationKey, 'custom_post_type_') === 0) {
            $queryArgs['wpacu_for']            = 'custom_post_types';
            $queryArgs['wpacu_post_type_view'] = 'singular';
            $queryArgs['wpacu_current_post_type'] = substr($locationKey, strlen('custom_post_type_'));
        } elseif (is_string($locationKey) && strpos($locationKey, 'custom_taxonomy_') === 0) {
            $queryArgs['wpacu_current_taxonomy'] = substr($locationKey, strlen('custom_taxonomy_'));
        }

        return add_query_arg($queryArgs, admin_url('admin.php'));
    }

    /**
     * @param string $wpacuFor
     * @param string $locationKey
     * @param int    $objectId
     *
     * @return string
     */
    public static function getSpecificManagementUrl($wpacuFor, $locationKey, $objectId = 0)
    {
        $queryArgs = array(
            'page'                     => WPACU_PLUGIN_ID . '_assets_manager',
            'wpacu_sub_page'           => 'manage_critical_css',
            'wpacu_for'                => $wpacuFor,
            'wpacu_critical_css_scope' => 'specific'
        );

        if (is_string($locationKey) && strpos($locationKey, 'custom_post_type_') === 0
            && strpos($locationKey, 'custom_post_type_archive_') !== 0) {
            $queryArgs['wpacu_for']            = 'custom_post_types';
            $queryArgs['wpacu_post_type_view'] = 'singular';
            $queryArgs['wpacu_current_post_type'] = substr($locationKey, strlen('custom_post_type_'));
        } elseif (is_string($locationKey) && strpos($locationKey, 'custom_taxonomy_') === 0) {
            $queryArgs['wpacu_current_taxonomy'] = substr($locationKey, strlen('custom_taxonomy_'));
        }

        if ($objectId > 0) {
            if (in_array($wpacuFor, array('posts', 'pages', 'media_attachment', 'custom_post_types'), true)) {
                $queryArgs['wpacu_post_id'] = $objectId;
            } elseif (in_array($wpacuFor, array('category', 'tag', 'custom_taxonomies'), true)) {
                $queryArgs['wpacu_term_id'] = $objectId;
            } elseif ($wpacuFor === 'author') {
                $queryArgs['wpacu_author_id'] = $objectId;
            }
        }

        return add_query_arg($queryArgs, admin_url('admin.php'));
    }

    /**
     * Return every object in the selected context that has a stored granular Critical CSS rule.
     * Disabled rules are intentionally included so the user can find and manage them.
     *
     * @param string $wpacuFor
     * @param string $locationKey
     *
     * @return array
     */
    public static function getGranularCriticalCssRules($wpacuFor, $locationKey)
    {
        if ( ! self::supportsGranularManagement($wpacuFor) ) {
            return array();
        }

        static $rulesCache = array();
        $cacheKey = $wpacuFor . '|' . $locationKey;

        if (isset($rulesCache[$cacheKey])) {
            return $rulesCache[$cacheKey];
        }

        global $wpdb;

        $rules   = array();
        $metaKey = CriticalCss::getMetaKey();

        if (in_array($wpacuFor, array('posts', 'pages', 'media_attachment', 'custom_post_types'), true)) {
            if ($locationKey === 'posts') {
                $postType = 'post';
            } elseif ($locationKey === 'pages') {
                $postType = 'page';
            } elseif ($locationKey === 'media') {
                $postType = 'attachment';
            } elseif (strpos($locationKey, 'custom_post_type_') === 0) {
                $postType = substr($locationKey, strlen('custom_post_type_'));
            } else {
                $postType = '';
            }

            if ($postType !== '' && post_type_exists($postType)) {
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT p.ID, p.post_title, p.post_status, pm.meta_value
                     FROM `{$wpdb->postmeta}` pm
                     INNER JOIN `{$wpdb->posts}` p ON p.ID=pm.post_id
                     WHERE pm.meta_key=%s AND p.post_type=%s AND p.post_status NOT IN ('trash','auto-draft')
                     ORDER BY p.post_title ASC, p.ID ASC",
                    $metaKey,
                    $postType
                ), ARRAY_A);

                $postTypeObject = get_post_type_object($postType);
                $typeLabel = ($postTypeObject && isset($postTypeObject->labels->singular_name))
                    ? $postTypeObject->labels->singular_name
                    : $postType;

                foreach ((array)$rows as $row) {
                    $storedData = CriticalCss::decodeStoredCriticalCssData(maybe_unserialize($row['meta_value']));

                    if (empty($storedData['content_original']) && empty($storedData['content_minified'])) {
                        continue;
                    }

                    $objectId = (int)$row['ID'];
                    $title    = trim((string)$row['post_title']);

                    $rules[] = array(
                        'object_id'   => $objectId,
                        'label'       => $title !== '' ? $title : __('(no title)', 'wp-asset-clean-up'),
                        'type_label'  => $typeLabel,
                        'url'         => (string)get_permalink($objectId),
                        'edit_url'    => self::getSpecificManagementUrl($wpacuFor, $locationKey, $objectId),
                        'enable'      => ! empty($storedData['enable']),
                        'show_method' => isset($storedData['show_method']) ? $storedData['show_method'] : 'original'
                    );
                }
            }
        } elseif (in_array($wpacuFor, array('category', 'tag', 'custom_taxonomies'), true)) {
            $taxonomy = self::getTaxonomyFromLocationKey($locationKey);

            if ($taxonomy !== '' && taxonomy_exists($taxonomy)) {
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT t.term_id, t.name, tm.meta_value
                     FROM `{$wpdb->termmeta}` tm
                     INNER JOIN `{$wpdb->terms}` t ON t.term_id=tm.term_id
                     INNER JOIN `{$wpdb->term_taxonomy}` tt ON tt.term_id=t.term_id
                     WHERE tm.meta_key=%s AND tt.taxonomy=%s
                     ORDER BY t.name ASC, t.term_id ASC",
                    $metaKey,
                    $taxonomy
                ), ARRAY_A);

                $taxonomyObject = get_taxonomy($taxonomy);
                $typeLabel = ($taxonomyObject && isset($taxonomyObject->labels->singular_name))
                    ? $taxonomyObject->labels->singular_name
                    : $taxonomy;

                foreach ((array)$rows as $row) {
                    $storedData = CriticalCss::decodeStoredCriticalCssData(maybe_unserialize($row['meta_value']));

                    if (empty($storedData['content_original']) && empty($storedData['content_minified'])) {
                        continue;
                    }

                    $objectId = (int)$row['term_id'];
                    $term     = get_term($objectId, $taxonomy);
                    $termUrl  = $term instanceof \WP_Term ? get_term_link($term, $taxonomy) : '';

                    $rules[] = array(
                        'object_id'   => $objectId,
                        'label'       => (string)$row['name'],
                        'type_label'  => $typeLabel,
                        'url'         => is_wp_error($termUrl) ? '' : (string)$termUrl,
                        'edit_url'    => self::getSpecificManagementUrl($wpacuFor, $locationKey, $objectId),
                        'enable'      => ! empty($storedData['enable']),
                        'show_method' => isset($storedData['show_method']) ? $storedData['show_method'] : 'original'
                    );
                }
            }
        } elseif ($wpacuFor === 'author' && $locationKey === 'author') {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT u.ID, u.display_name, um.meta_value
                 FROM `{$wpdb->usermeta}` um
                 INNER JOIN `{$wpdb->users}` u ON u.ID=um.user_id
                 WHERE um.meta_key=%s
                 ORDER BY u.display_name ASC, u.ID ASC",
                $metaKey
            ), ARRAY_A);

            foreach ((array)$rows as $row) {
                $storedData = CriticalCss::decodeStoredCriticalCssData(maybe_unserialize($row['meta_value']));

                if (empty($storedData['content_original']) && empty($storedData['content_minified'])) {
                    continue;
                }

                $objectId = (int)$row['ID'];

                $rules[] = array(
                    'object_id'   => $objectId,
                    'label'       => (string)$row['display_name'],
                    'type_label'  => __('Author', 'wp-asset-clean-up'),
                    'url'         => get_author_posts_url($objectId),
                    'edit_url'    => self::getSpecificManagementUrl($wpacuFor, $locationKey, $objectId),
                    'enable'      => ! empty($storedData['enable']),
                    'show_method' => isset($storedData['show_method']) ? $storedData['show_method'] : 'original'
                );
            }
        }

        $rulesCache[$cacheKey] = $rules;

        return $rules;
    }

    /**
     * Count saved Critical CSS rules without building the heavier Overview data.
     * General rules are stored in options, while page/term/author-specific rules
     * are stored in the corresponding meta tables. Disabled rules are included
     * because the count is used to show what remains saved while output is paused.
     *
     * @return int[]
     */
    public static function getCriticalCssRuleStats()
    {
        static $stats = null;

        if (is_array($stats)) {
            return $stats;
        }

        $stats = array(
            'total_count'    => 0,
            'enabled_count'  => 0,
            'disabled_count' => 0,
            'general_count'  => 0,
            'specific_count' => 0
        );

        global $wpdb;

        $criticalCssConfig = CriticalCss::decodeStoredCriticalCssData(
            get_option(WPACU_PLUGIN_ID . '_critical_css_config')
        );
        $contentOptionPrefix = WPACU_PLUGIN_ID . '_critical_css_location_key_';
        $contentOptionLike   = $wpdb->esc_like($contentOptionPrefix) . '%';

        $generalRows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM `{$wpdb->options}` WHERE option_name LIKE %s",
                $contentOptionLike
            ),
            ARRAY_A
        );

        foreach ((array)$generalRows as $generalRow) {
            $storedData = CriticalCss::decodeStoredCriticalCssData($generalRow['option_value']);

            if ( ! self::hasStoredCriticalCssContent($storedData) ) {
                continue;
            }

            $locationKey = substr((string)$generalRow['option_name'], strlen($contentOptionPrefix));
            $isEnabled   = $locationKey !== ''
                && ! empty($criticalCssConfig[$locationKey]['enable']);

            $stats['general_count']++;
            $stats['total_count']++;
            $stats[$isEnabled ? 'enabled_count' : 'disabled_count']++;
        }

        $metaKey = CriticalCss::getMetaKey();
        $metaSources = array(
            array(
                'table'         => $wpdb->postmeta,
                'id_column'     => 'meta_id',
                'object_column' => 'post_id'
            ),
            array(
                'table'         => $wpdb->termmeta,
                'id_column'     => 'meta_id',
                'object_column' => 'term_id'
            ),
            array(
                'table'         => $wpdb->usermeta,
                'id_column'     => 'umeta_id',
                'object_column' => 'user_id'
            )
        );

        foreach ($metaSources as $metaSource) {
            $tableName    = $metaSource['table'];
            $idColumn     = $metaSource['id_column'];
            $objectColumn = $metaSource['object_column'];
            $seenObjects  = array();

            $specificRows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT `{$idColumn}` AS row_id, `{$objectColumn}` AS object_id, meta_value
                     FROM `{$tableName}`
                     WHERE meta_key=%s
                     ORDER BY `{$idColumn}` DESC",
                    $metaKey
                ),
                ARRAY_A
            );

            foreach ((array)$specificRows as $specificRow) {
                $objectId = isset($specificRow['object_id']) ? (int)$specificRow['object_id'] : 0;

                if ($objectId < 1 || isset($seenObjects[$objectId])) {
                    continue;
                }

                $storedData = CriticalCss::decodeStoredCriticalCssData(
                    maybe_unserialize($specificRow['meta_value'])
                );

                if ( ! self::hasStoredCriticalCssContent($storedData) ) {
                    continue;
                }

                $seenObjects[$objectId] = true;
                $isEnabled = ! empty($storedData['enable']);

                $stats['specific_count']++;
                $stats['total_count']++;
                $stats[$isEnabled ? 'enabled_count' : 'disabled_count']++;
            }
        }

        return $stats;
    }

    /**
     * Save the global Critical CSS state from the manager without modifying any rule.
     *
     * @return void
     */
    public function ajaxUpdateCriticalCssGlobalStatus()
    {
        $action = isset($_POST['action']) && is_string($_POST['action'])
            ? sanitize_key(wp_unslash($_POST['action']))
            : '';
        $status = isset($_POST['wpacu_status']) && is_string($_POST['wpacu_status'])
            ? sanitize_key(wp_unslash($_POST['wpacu_status']))
            : '';
        $nonce = isset($_POST['wpacu_nonce']) && is_string($_POST['wpacu_nonce'])
            ? wp_unslash($_POST['wpacu_nonce'])
            : '';

        $isValidRequest = $action === WPACU_PLUGIN_ID . '_update_critical_css_global_status'
            && in_array($status, array('on', 'off'), true)
            && Menu::userCanAccessPlugin()
            && AssetsManager::instance()->currentUserCanViewAssetsList()
            && wp_verify_nonce($nonce, 'wpacu_update_critical_css_global_status');

        if ( ! $isValidRequest ) {
            wp_send_json_error(array(
                'message' => __('The Critical CSS status could not be updated. Refresh the page and try again.', 'wp-asset-clean-up')
            ));
        }

        $settingsAdmin = new SettingsAdmin();
        $settingsAdmin->updateOption('critical_css_status', $status);

        if ($settingsAdmin->getOption('critical_css_status') !== $status) {
            wp_send_json_error(array(
                'message' => __('The Critical CSS status was not saved. Refresh the page and try again.', 'wp-asset-clean-up')
            ));
        }

        wp_send_json_success(array(
            'status'  => $status,
            'label'   => $status === 'off'
                ? __('Paused', 'wp-asset-clean-up')
                : __('Active', 'wp-asset-clean-up'),
            'message' => $status === 'off'
                ? __('Critical CSS output is paused. All saved rules remain available.', 'wp-asset-clean-up')
                : __('Critical CSS output is active for enabled matching rules.', 'wp-asset-clean-up')
        ));
    }

    /**
     * Return all enabled Critical CSS rules prepared for the Overview page.
     * The actual CSS content is intentionally omitted; only the location,
     * object details, output method and direct management links are returned.
     *
     * @param array $criticalCssConfig
     *
     * @return array
     */
    public static function getEnabledCriticalCssOverviewData($criticalCssConfig)
    {
        if ( ! is_array($criticalCssConfig) ) {
            $criticalCssConfig = array();
        }

        $locations     = array();
        $generalCount  = 0;
        $specificCount = 0;

        foreach ($criticalCssConfig as $locationKey => $locationOptions) {
            if ( ! is_string($locationKey)
                || ! is_array($locationOptions)
                || empty($locationOptions['enable'])) {
                continue;
            }

            $locationData = self::getCriticalCssOverviewLocationData($locationKey);

            if (empty($locationData)) {
                continue;
            }

            $storedContent = CriticalCss::decodeStoredCriticalCssData(
                get_option(WPACU_PLUGIN_ID . '_critical_css_location_key_' . $locationKey)
            );

            if ( ! self::hasStoredCriticalCssContent($storedContent) ) {
                continue;
            }

            self::addCriticalCssOverviewRule(
                $locations,
                $locationData,
                array(
                    'location_key' => $locationKey,
                    'scope'        => 'general',
                    'scope_label'  => __('General', 'wp-asset-clean-up'),
                    'label'        => $locationData['general_rule_label'],
                    'type_label'   => '',
                    'object_id'    => 0,
                    'url'          => isset($locationData['url']) ? $locationData['url'] : '',
                    'edit_url'     => self::getGeneralManagementUrl($locationData['wpacu_for'], $locationKey),
                    'show_method'  => isset($locationOptions['show_method']) && $locationOptions['show_method'] === 'minified'
                        ? 'minified'
                        : 'original',
                    'storage_type' => 'option'
                )
            );

            $generalCount++;
        }

        foreach (self::getEnabledGranularCriticalCssRulesForOverview() as $specificRule) {
            $locationKey  = $specificRule['location_key'];
            $locationData = self::getCriticalCssOverviewLocationData($locationKey);

            if (empty($locationData)) {
                continue;
            }

            self::addCriticalCssOverviewRule($locations, $locationData, $specificRule);
            $specificCount++;
        }

        uasort($locations, static function($locationA, $locationB) {
            $sortOrderA = isset($locationA['sort_order']) ? (int)$locationA['sort_order'] : PHP_INT_MAX;
            $sortOrderB = isset($locationB['sort_order']) ? (int)$locationB['sort_order'] : PHP_INT_MAX;

            if ($sortOrderA !== $sortOrderB) {
                return $sortOrderA < $sortOrderB ? -1 : 1;
            }

            return strnatcasecmp($locationA['label'], $locationB['label']);
        });

        foreach ($locations as &$locationData) {
            usort($locationData['rules'], static function($ruleA, $ruleB) {
                if ($ruleA['scope'] !== $ruleB['scope']) {
                    return $ruleA['scope'] === 'general' ? -1 : 1;
                }

                $labelCompare = strnatcasecmp($ruleA['label'], $ruleB['label']);

                if ($labelCompare !== 0) {
                    return $labelCompare;
                }

                $objectIdA = (int)$ruleA['object_id'];
                $objectIdB = (int)$ruleB['object_id'];

                if ($objectIdA === $objectIdB) {
                    return 0;
                }

                return $objectIdA < $objectIdB ? -1 : 1;
            });

            $locationData['rules_count'] = count($locationData['rules']);
        }
        unset($locationData);

        return array(
            'locations'      => array_values($locations),
            'rules_count'    => $generalCount + $specificCount,
            'general_count'  => $generalCount,
            'specific_count' => $specificCount
        );
    }

    /**
     * Clear the enabled Critical CSS rules selected in the Overview edit form.
     *
     * The submitted identifiers are matched against the current Overview data
     * before anything is removed. This prevents stale or altered form values
     * from clearing a different Critical CSS entry.
     *
     * @param array $criticalCssDataToClear
     *
     * @return array Cleared rule details used by the shared Overview notice.
     */
    public static function clearSelectedCriticalCssOverviewRules($criticalCssDataToClear)
    {
        if (empty($criticalCssDataToClear) || ! is_array($criticalCssDataToClear)) {
            return array();
        }

        $selectedRuleKeys = self::getSelectedCriticalCssOverviewRuleKeys($criticalCssDataToClear);

        if (empty($selectedRuleKeys)) {
            return array();
        }

        $criticalCssConfigOption = WPACU_PLUGIN_ID . '_critical_css_config';
        $criticalCssConfig = CriticalCss::decodeStoredCriticalCssData(
            get_option($criticalCssConfigOption)
        );

        $overviewData = self::getEnabledCriticalCssOverviewData($criticalCssConfig);
        $locations    = isset($overviewData['locations']) && is_array($overviewData['locations'])
            ? $overviewData['locations']
            : array();

        if (empty($locations)) {
            return array();
        }

        $clearedRules         = array();
        $criticalCssMetaKey   = CriticalCss::getMetaKey();
        $criticalCssConfigWasChanged = false;

        foreach ($locations as $locationData) {
            if (empty($locationData['location_key']) || empty($locationData['rules']) || ! is_array($locationData['rules'])) {
                continue;
            }

            $locationKey   = sanitize_key($locationData['location_key']);
            $locationLabel = isset($locationData['label']) ? (string)$locationData['label'] : $locationKey;

            foreach ($locationData['rules'] as $ruleData) {
                if ( ! is_array($ruleData)) {
                    continue;
                }

                $scope       = isset($ruleData['scope']) ? sanitize_key($ruleData['scope']) : '';
                $storageType = isset($ruleData['storage_type']) ? sanitize_key($ruleData['storage_type']) : '';
                $objectId    = isset($ruleData['object_id']) ? (int)$ruleData['object_id'] : 0;

                $ruleSelectionKey = self::getCriticalCssOverviewRuleSelectionKey(
                    $scope,
                    $locationKey,
                    $storageType,
                    $objectId
                );

                if ($ruleSelectionKey === '' || ! isset($selectedRuleKeys[$ruleSelectionKey])) {
                    continue;
                }

                $wasCleared = false;

                if ($scope === 'general' && $storageType === 'option') {
                    $contentOption = WPACU_PLUGIN_ID . '_critical_css_location_key_' . $locationKey;

                    if (delete_option($contentOption)) {
                        $wasCleared = true;
                    }

                    if (isset($criticalCssConfig[$locationKey])) {
                        unset($criticalCssConfig[$locationKey]);
                        $criticalCssConfigWasChanged = true;
                        $wasCleared = true;
                    }
                } elseif ($scope === 'specific'
                    && in_array($storageType, array('post_meta', 'term_meta', 'user_meta'), true)
                    && $objectId > 0) {
                    $wasCleared = self::deleteCriticalCssMeta($storageType, $objectId, $criticalCssMetaKey);
                }

                if ( ! $wasCleared) {
                    continue;
                }

                $clearedRules[] = array(
                    'scope'          => $scope,
                    'scope_label'    => isset($ruleData['scope_label']) ? (string)$ruleData['scope_label'] : $scope,
                    'location_key'   => $locationKey,
                    'location_label' => $locationLabel,
                    'label'          => isset($ruleData['label']) ? (string)$ruleData['label'] : '',
                    'type_label'     => isset($ruleData['type_label']) ? (string)$ruleData['type_label'] : '',
                    'object_id'      => $objectId,
                    'storage_type'   => $storageType
                );
            }
        }

        if ($criticalCssConfigWasChanged) {
            $criticalCssConfig = MiscArray::filterList($criticalCssConfig);

            if (empty($criticalCssConfig)) {
                delete_option($criticalCssConfigOption);
            } else {
                Misc::addUpdateOption($criticalCssConfigOption, wp_json_encode($criticalCssConfig));
            }
        }

        return $clearedRules;
    }

    /**
     * Convert the Critical CSS portion of the Overview POST data to a flat,
     * validated lookup map.
     *
     * @param array $criticalCssDataToClear
     *
     * @return array
     */
    private static function getSelectedCriticalCssOverviewRuleKeys($criticalCssDataToClear)
    {
        $selectedRuleKeys = array();

        if ( ! empty($criticalCssDataToClear['general']) && is_array($criticalCssDataToClear['general'])) {
            foreach ($criticalCssDataToClear['general'] as $locationKey => $selected) {
                if (empty($selected) || is_array($selected)) {
                    continue;
                }

                $locationKey = sanitize_key(wp_unslash($locationKey));
                $selectionKey = self::getCriticalCssOverviewRuleSelectionKey(
                    'general',
                    $locationKey,
                    'option',
                    0
                );

                if ($selectionKey !== '') {
                    $selectedRuleKeys[$selectionKey] = true;
                }
            }
        }

        if ( ! empty($criticalCssDataToClear['specific']) && is_array($criticalCssDataToClear['specific'])) {
            foreach ($criticalCssDataToClear['specific'] as $storageType => $locationsData) {
                $storageType = sanitize_key(wp_unslash($storageType));

                if ( ! in_array($storageType, array('post_meta', 'term_meta', 'user_meta'), true)
                    || empty($locationsData)
                    || ! is_array($locationsData)) {
                    continue;
                }

                foreach ($locationsData as $locationKey => $objectIdsData) {
                    $locationKey = sanitize_key(wp_unslash($locationKey));

                    if ($locationKey === '' || empty($objectIdsData) || ! is_array($objectIdsData)) {
                        continue;
                    }

                    foreach ($objectIdsData as $objectId => $selected) {
                        if (empty($selected) || is_array($selected)) {
                            continue;
                        }

                        $selectionKey = self::getCriticalCssOverviewRuleSelectionKey(
                            'specific',
                            $locationKey,
                            $storageType,
                            absint($objectId)
                        );

                        if ($selectionKey !== '') {
                            $selectedRuleKeys[$selectionKey] = true;
                        }
                    }
                }
            }
        }

        return $selectedRuleKeys;
    }

    /**
     * @param string $scope
     * @param string $locationKey
     * @param string $storageType
     * @param int    $objectId
     *
     * @return string
     */
    private static function getCriticalCssOverviewRuleSelectionKey($scope, $locationKey, $storageType, $objectId)
    {
        if ($scope === 'general' && $locationKey !== '' && $storageType === 'option') {
            return 'general|' . $locationKey;
        }

        if ($scope === 'specific'
            && $locationKey !== ''
            && in_array($storageType, array('post_meta', 'term_meta', 'user_meta'), true)
            && (int)$objectId > 0) {
            return 'specific|' . $storageType . '|' . $locationKey . '|' . (int)$objectId;
        }

        return '';
    }

    /**
     * @param array $locations
     * @param array $locationData
     * @param array $ruleData
     */
    private static function addCriticalCssOverviewRule(&$locations, $locationData, $ruleData)
    {
        $locationKey = $locationData['location_key'];

        if ( ! isset($locations[$locationKey]) ) {
            $locations[$locationKey]          = $locationData;
            $locations[$locationKey]['rules'] = array();
        }

        $locations[$locationKey]['rules'][] = $ruleData;
    }

    /**
     * Resolve the labels, management context and display order for a Critical CSS location key.
     *
     * @param string $locationKey
     *
     * @return array
     */
    private static function getCriticalCssOverviewLocationData($locationKey)
    {
        static $standardLocations = null;

        if ($standardLocations === null) {
            $standardLocations = array(
                'homepage' => array(
                    'wpacu_for'          => 'homepage',
                    'label'              => __('Homepage', 'wp-asset-clean-up'),
                    'general_rule_label' => __('Homepage', 'wp-asset-clean-up'),
                    'sort_order'         => 10,
                    'url'                => home_url('/')
                ),
                'posts' => array(
                    'wpacu_for'          => 'posts',
                    'label'              => __('Posts', 'wp-asset-clean-up'),
                    'general_rule_label' => __('All Posts', 'wp-asset-clean-up'),
                    'sort_order'         => 20
                ),
                'pages' => array(
                    'wpacu_for'          => 'pages',
                    'label'              => __('Pages', 'wp-asset-clean-up'),
                    'general_rule_label' => __('All Pages', 'wp-asset-clean-up'),
                    'sort_order'         => 30
                ),
                'media' => array(
                    'wpacu_for'          => 'media_attachment',
                    'label'              => __('Media attachment pages', 'wp-asset-clean-up'),
                    'general_rule_label' => __('All Media attachment pages', 'wp-asset-clean-up'),
                    'sort_order'         => 50
                ),
                'category' => array(
                    'wpacu_for'          => 'category',
                    'label'              => __('Categories', 'wp-asset-clean-up'),
                    'general_rule_label' => __('All category archive pages', 'wp-asset-clean-up'),
                    'sort_order'         => 60
                ),
                'tag' => array(
                    'wpacu_for'          => 'tag',
                    'label'              => __('Tags', 'wp-asset-clean-up'),
                    'general_rule_label' => __('All tag archive pages', 'wp-asset-clean-up'),
                    'sort_order'         => 70
                ),
                'search' => array(
                    'wpacu_for'          => 'search',
                    'label'              => __('Search results', 'wp-asset-clean-up'),
                    'general_rule_label' => __('All search result pages', 'wp-asset-clean-up'),
                    'sort_order'         => 90
                ),
                'author' => array(
                    'wpacu_for'          => 'author',
                    'label'              => __('Author archives', 'wp-asset-clean-up'),
                    'general_rule_label' => __('All author archive pages', 'wp-asset-clean-up'),
                    'sort_order'         => 100
                ),
                'date' => array(
                    'wpacu_for'          => 'date',
                    'label'              => __('Date archives', 'wp-asset-clean-up'),
                    'general_rule_label' => __('All date archive pages', 'wp-asset-clean-up'),
                    'sort_order'         => 110
                ),
                '404_not_found' => array(
                    'wpacu_for'          => '404_not_found',
                    'label'              => __('404 Not Found', 'wp-asset-clean-up'),
                    'general_rule_label' => __('All 404 Not Found pages', 'wp-asset-clean-up'),
                    'sort_order'         => 120
                )
            );
        }

        if (isset($standardLocations[$locationKey])) {
            return array_merge(
                array(
                    'location_key'       => $locationKey,
                    'location_type'      => 'standard',
                    'location_type_label' => '',
                    'object_key'         => ''
                ),
                $standardLocations[$locationKey]
            );
        }

        if (strpos($locationKey, 'custom_post_type_archive_') === 0) {
            $postType = substr($locationKey, strlen('custom_post_type_archive_'));

            if ($postType === '') {
                return array();
            }

            static $archives = null;

            if ($archives === null) {
                $archives = AssetsManagerAdmin::getCustomPostTypeArchives();
            }

            $postTypeObject = get_post_type_object($postType);
            $archiveLabel   = isset($archives[$postType]['label']) && $archives[$postType]['label'] !== ''
                ? $archives[$postType]['label']
                : (($postTypeObject && ! empty($postTypeObject->labels->name)) ? $postTypeObject->labels->name : $postType);
            $archiveUrl = isset($archives[$postType]['url']) && $archives[$postType]['url'] !== ''
                ? $archives[$postType]['url']
                : ($postTypeObject ? (string)get_post_type_archive_link($postType) : '');

            return array(
                'location_key'        => $locationKey,
                'wpacu_for'           => 'custom_post_type_archives',
                'label'               => sprintf(__('%s Archive', 'wp-asset-clean-up'), $archiveLabel),
                'general_rule_label'  => sprintf(__('%s Archive', 'wp-asset-clean-up'), $archiveLabel),
                'location_type'       => 'custom_post_type_archive',
                'location_type_label' => __('Custom Post Type Archive', 'wp-asset-clean-up'),
                'object_key'          => $postType,
                'sort_order'          => 45,
                'url'                 => $archiveUrl
            );
        }

        if (strpos($locationKey, 'custom_post_type_') === 0) {
            $postType = substr($locationKey, strlen('custom_post_type_'));
            $postTypeObject = $postType !== '' ? get_post_type_object($postType) : false;

            if ($postType === '') {
                return array();
            }

            $pluralLabel = ($postTypeObject && ! empty($postTypeObject->labels->name))
                ? $postTypeObject->labels->name
                : $postType;

            return array(
                'location_key'        => $locationKey,
                'wpacu_for'           => 'custom_post_types',
                'label'               => $pluralLabel,
                'general_rule_label'  => sprintf(__('All %s', 'wp-asset-clean-up'), $pluralLabel),
                'location_type'       => 'custom_post_type',
                'location_type_label' => __('Custom Post Type', 'wp-asset-clean-up'),
                'object_key'          => $postType,
                'sort_order'          => 40
            );
        }

        if (strpos($locationKey, 'custom_taxonomy_') === 0) {
            $taxonomy = substr($locationKey, strlen('custom_taxonomy_'));
            $taxonomyObject = $taxonomy !== '' ? get_taxonomy($taxonomy) : false;

            if ($taxonomy === '') {
                return array();
            }

            $pluralLabel = ($taxonomyObject && ! empty($taxonomyObject->labels->name))
                ? $taxonomyObject->labels->name
                : $taxonomy;

            return array(
                'location_key'        => $locationKey,
                'wpacu_for'           => 'custom_taxonomies',
                'label'               => $pluralLabel,
                'general_rule_label'  => sprintf(__('All %s archive pages', 'wp-asset-clean-up'), $pluralLabel),
                'location_type'       => 'custom_taxonomy',
                'location_type_label' => __('Custom Taxonomy', 'wp-asset-clean-up'),
                'object_key'          => $taxonomy,
                'sort_order'          => 80
            );
        }

        return array();
    }

    /**
     * Fetch every enabled object-level Critical CSS rule in three indexed metadata queries.
     *
     * @return array
     */
    private static function getEnabledGranularCriticalCssRulesForOverview()
    {
        global $wpdb;

        $rules   = array();
        $metaKey = CriticalCss::getMetaKey();

        $postRows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_name, p.post_type, pm.meta_value
             FROM `{$wpdb->postmeta}` pm
             INNER JOIN `{$wpdb->posts}` p ON p.ID=pm.post_id
             WHERE pm.meta_key=%s AND p.post_status NOT IN ('trash','auto-draft')
             ORDER BY p.post_type ASC, p.post_title ASC, p.ID ASC",
            $metaKey
        ), ARRAY_A);

        $seenPosts = array();

        foreach ((array)$postRows as $postRow) {
            $objectId = (int)$postRow['ID'];

            if ($objectId < 1 || isset($seenPosts[$objectId])) {
                continue;
            }

            $storedData = CriticalCss::decodeStoredCriticalCssData(maybe_unserialize($postRow['meta_value']));

            if (empty($storedData['enable']) || ! self::hasStoredCriticalCssContent($storedData)) {
                continue;
            }

            $locationKey  = self::getGeneralLocationKeyForPostType($postRow['post_type']);
            $locationData = self::getCriticalCssOverviewLocationData($locationKey);

            if (empty($locationData)) {
                continue;
            }

            $postTypeObject = get_post_type_object($postRow['post_type']);
            $typeLabel      = ($postTypeObject && ! empty($postTypeObject->labels->singular_name))
                ? $postTypeObject->labels->singular_name
                : $postRow['post_type'];
            $title = trim((string)$postRow['post_title']);

            $rules[] = array(
                'location_key' => $locationKey,
                'scope'        => 'specific',
                'scope_label'  => __('Specific', 'wp-asset-clean-up'),
                'label'        => $title !== '' ? $title : __('(no title)', 'wp-asset-clean-up'),
                'type_label'   => $typeLabel,
                'object_id'    => $objectId,
                'object_slug'  => isset($postRow['post_name']) ? (string)$postRow['post_name'] : '',
                'url'          => (string)get_permalink($objectId),
                'edit_url'     => self::getSpecificManagementUrl($locationData['wpacu_for'], $locationKey, $objectId),
                'show_method'  => isset($storedData['show_method']) && $storedData['show_method'] === 'minified'
                    ? 'minified'
                    : 'original',
                'storage_type' => 'post_meta'
            );

            $seenPosts[$objectId] = true;
        }

        $termRows = $wpdb->get_results($wpdb->prepare(
            "SELECT t.term_id, t.name, t.slug, tt.taxonomy, tm.meta_value
             FROM `{$wpdb->termmeta}` tm
             INNER JOIN `{$wpdb->terms}` t ON t.term_id=tm.term_id
             INNER JOIN `{$wpdb->term_taxonomy}` tt ON tt.term_id=t.term_id
             WHERE tm.meta_key=%s
             ORDER BY tt.taxonomy ASC, t.name ASC, t.term_id ASC",
            $metaKey
        ), ARRAY_A);

        $seenTerms = array();

        foreach ((array)$termRows as $termRow) {
            $objectId = (int)$termRow['term_id'];
            $taxonomy = (string)$termRow['taxonomy'];
            $seenKey  = $taxonomy . '|' . $objectId;

            if ($objectId < 1 || $taxonomy === '' || isset($seenTerms[$seenKey])) {
                continue;
            }

            $storedData = CriticalCss::decodeStoredCriticalCssData(maybe_unserialize($termRow['meta_value']));

            if (empty($storedData['enable']) || ! self::hasStoredCriticalCssContent($storedData)) {
                continue;
            }

            if ($taxonomy === 'category') {
                $locationKey = 'category';
            } elseif ($taxonomy === 'post_tag') {
                $locationKey = 'tag';
            } else {
                $locationKey = 'custom_taxonomy_' . $taxonomy;
            }

            $locationData = self::getCriticalCssOverviewLocationData($locationKey);

            if (empty($locationData)) {
                continue;
            }

            $taxonomyObject = get_taxonomy($taxonomy);
            $typeLabel      = ($taxonomyObject && ! empty($taxonomyObject->labels->singular_name))
                ? $taxonomyObject->labels->singular_name
                : $taxonomy;
            $term           = get_term($objectId, $taxonomy);
            $termUrl        = $term instanceof \WP_Term ? get_term_link($term, $taxonomy) : '';

            $rules[] = array(
                'location_key' => $locationKey,
                'scope'        => 'specific',
                'scope_label'  => __('Specific', 'wp-asset-clean-up'),
                'label'        => (string)$termRow['name'],
                'type_label'   => $typeLabel,
                'object_id'    => $objectId,
                'object_slug'  => isset($termRow['slug']) ? (string)$termRow['slug'] : '',
                'url'          => is_wp_error($termUrl) ? '' : (string)$termUrl,
                'edit_url'     => self::getSpecificManagementUrl($locationData['wpacu_for'], $locationKey, $objectId),
                'show_method'  => isset($storedData['show_method']) && $storedData['show_method'] === 'minified'
                    ? 'minified'
                    : 'original',
                'storage_type' => 'term_meta'
            );

            $seenTerms[$seenKey] = true;
        }

        $userRows = $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.display_name, u.user_nicename, um.meta_value
             FROM `{$wpdb->usermeta}` um
             INNER JOIN `{$wpdb->users}` u ON u.ID=um.user_id
             WHERE um.meta_key=%s
             ORDER BY u.display_name ASC, u.ID ASC",
            $metaKey
        ), ARRAY_A);

        $seenUsers = array();

        foreach ((array)$userRows as $userRow) {
            $objectId = (int)$userRow['ID'];

            if ($objectId < 1 || isset($seenUsers[$objectId])) {
                continue;
            }

            $storedData = CriticalCss::decodeStoredCriticalCssData(maybe_unserialize($userRow['meta_value']));

            if (empty($storedData['enable']) || ! self::hasStoredCriticalCssContent($storedData)) {
                continue;
            }

            $rules[] = array(
                'location_key' => 'author',
                'scope'        => 'specific',
                'scope_label'  => __('Specific', 'wp-asset-clean-up'),
                'label'        => (string)$userRow['display_name'],
                'type_label'   => __('Author', 'wp-asset-clean-up'),
                'object_id'    => $objectId,
                'object_slug'  => isset($userRow['user_nicename']) ? (string)$userRow['user_nicename'] : '',
                'url'          => get_author_posts_url($objectId),
                'edit_url'     => self::getSpecificManagementUrl('author', 'author', $objectId),
                'show_method'  => isset($storedData['show_method']) && $storedData['show_method'] === 'minified'
                    ? 'minified'
                    : 'original',
                'storage_type' => 'user_meta'
            );

            $seenUsers[$objectId] = true;
        }

        return $rules;
    }

    /**
     * @param array $storedData
     *
     * @return bool
     */
    private static function hasStoredCriticalCssContent($storedData)
    {
        return is_array($storedData)
            && ( ! empty($storedData['content_original']) || ! empty($storedData['content_minified']) );
    }

    /**
     * @param string $postType
     *
     * @return string
     */
    public static function getGeneralLocationKeyForPostType($postType)
    {
        if ($postType === 'post') {
            return 'posts';
        }

        if ($postType === 'page') {
            return 'pages';
        }

        if ($postType === 'attachment') {
            return 'media';
        }

        return $postType ? 'custom_post_type_' . $postType : '';
    }

    /**
     * @param string $locationKey
     *
     * @return string
     */
    public static function getTaxonomyFromLocationKey($locationKey)
    {
        if ($locationKey === 'category') {
            return 'category';
        }

        if ($locationKey === 'tag') {
            return 'post_tag';
        }

        if (strpos($locationKey, 'custom_taxonomy_') === 0) {
            return substr($locationKey, strlen('custom_taxonomy_'));
        }

        return '';
    }

    /**
     * @param array  $storageContext
     * @param string $locationKey
     * @param array  $criticalCssConfig
     *
     * @return array
     */
    public static function getStoredCriticalCssData($storageContext, $locationKey, $criticalCssConfig)
    {
        $storedData = array(
            'enable'                        => false,
            'show_method'                   => 'original',
            'content_original'              => '',
            'content_minified'              => '',
            'content_was_stored_in_options' => false
        );

        if (isset($storageContext['storage_type']) && $storageContext['storage_type'] !== 'option') {
            $rawMetaValue = '';
            $objectId     = isset($storageContext['object_id']) ? (int)$storageContext['object_id'] : 0;
            $metaKey      = CriticalCss::getMetaKey();

            if ($storageContext['storage_type'] === 'post_meta') {
                $rawMetaValue = get_post_meta($objectId, $metaKey, true);
            } elseif ($storageContext['storage_type'] === 'term_meta') {
                $rawMetaValue = get_term_meta($objectId, $metaKey, true);
            } elseif ($storageContext['storage_type'] === 'user_meta') {
                $rawMetaValue = get_user_meta($objectId, $metaKey, true);
            }

            $metaData = CriticalCss::decodeStoredCriticalCssData($rawMetaValue);

            if ( ! empty($metaData) ) {
                $storedData = array_merge($storedData, $metaData);
            }

            return $storedData;
        }

        $storedData['content_was_stored_in_options'] = true;
        $storedData['enable'] = isset($criticalCssConfig[$locationKey]['enable']) && $criticalCssConfig[$locationKey]['enable'];
        $storedData['show_method'] = isset($criticalCssConfig[$locationKey]['show_method']) && $criticalCssConfig[$locationKey]['show_method']
            ? $criticalCssConfig[$locationKey]['show_method']
            : 'original';

        $contentDataJson = get_option(WPACU_PLUGIN_ID . '_critical_css_location_key_' . $locationKey);
        $contentData     = CriticalCss::decodeStoredCriticalCssData($contentDataJson);

        if ( ! empty($contentData) ) {
            $storedData = array_merge($storedData, $contentData);
        }

        return $storedData;
    }

    /**
     * @param string $locationKey
     *
     * @return bool
     */
    public static function isValidGeneralLocationKey($locationKey)
    {
        if (in_array($locationKey, array('homepage', 'posts', 'pages', 'media', 'category', 'tag', 'search', 'author', 'date', '404_not_found'), true)) {
            return true;
        }

        // Check the archive prefix first because it also starts with "custom_post_type_".
        if (strpos($locationKey, 'custom_post_type_archive_') === 0) {
            $postType = substr($locationKey, strlen('custom_post_type_archive_'));
            $archives = AssetsManagerAdmin::getCustomPostTypeArchives();

            return $postType !== '' && isset($archives[$postType]);
        }

        if (strpos($locationKey, 'custom_post_type_') === 0) {
            $postType = substr($locationKey, strlen('custom_post_type_'));
            return $postType !== '' && post_type_exists($postType);
        }

        if (strpos($locationKey, 'custom_taxonomy_') === 0) {
            $taxonomy = substr($locationKey, strlen('custom_taxonomy_'));
            return $taxonomy !== '' && taxonomy_exists($taxonomy);
        }

        return false;
    }

    /**
     * Save either the legacy general option or the new object-level metadata.
     */
    public function updateCriticalCss()
    {
        if ( ! Misc::getVar('post', 'wpacu_critical_css_submit') ) {
            return;
        }

        if ( ! Menu::userCanAccessPlugin()
            || ! AssetsManager::instance()->currentUserCanViewAssetsList() ) {
            return;
        }

        $mainKeyForm = WPACU_PLUGIN_ID . '_critical_css';

        check_admin_referer('wpacu_critical_css_update', 'wpacu_critical_css_nonce');

        if ( ! (isset($_POST[$mainKeyForm]) && is_array($_POST[$mainKeyForm])) ) {
            return;
        }

        $formData    = $_POST[$mainKeyForm];
        $locationKey = isset($formData['location_key']) ? sanitize_key($formData['location_key']) : '';

        if ( ! self::isValidGeneralLocationKey($locationKey) ) {
            return;
        }

        $storageType = isset($formData['storage_type']) ? sanitize_key($formData['storage_type']) : 'option';
        $objectId    = isset($formData['object_id']) ? (int)$formData['object_id'] : 0;
        $enable      = isset($formData['enable']) && (int)$formData['enable'] === 1;
        $showMethod  = isset($formData['show_method']) ? sanitize_key($formData['show_method']) : 'original';

        if ( ! in_array($showMethod, array('original', 'minified'), true) ) {
            $showMethod = 'original';
        }

        $contentFromRequest = isset($formData['content']) && is_string($formData['content']) ? $formData['content'] : '';

        if ($storageType === 'option') {
            $this->updateGeneralCriticalCss($locationKey, $enable, $contentFromRequest, $showMethod);
            return;
        }

        if ( ! in_array($storageType, array('post_meta', 'term_meta', 'user_meta'), true) ) {
            return;
        }

        if ( ! self::isValidMetaStorageTarget($storageType, $objectId, $locationKey) ) {
            return;
        }

        // Metadata APIs unslash values internally. Normalize the request first, then wp_slash() the JSON before saving.
        $content = wp_unslash($contentFromRequest);
        $this->updateGranularCriticalCss($storageType, $objectId, $enable, $content, $showMethod);
    }

    /**
     * @param string $locationKey
     * @param bool   $enable
     * @param string $content
     * @param string $showMethod
     */
    private function updateGeneralCriticalCss($locationKey, $enable, $content, $showMethod)
    {
        $optionToUpdate = WPACU_PLUGIN_ID . '_critical_css_config';

        $existingListJson  = get_option($optionToUpdate);
        $existingListData  = Main::instance()->existingList($existingListJson, array());
        $existingList      = $existingListData['list'];

        $hasContent = trim($content) !== '';

        // Recalculate the state on every save. A checked toggle without CSS
        // must not leave a previously enabled rule active after its content
        // has been removed.
        $existingList[$locationKey]['enable'] = ($enable && $hasContent);

        $existingList[$locationKey]['show_method'] = $showMethod;

        Misc::addUpdateOption($optionToUpdate, wp_json_encode(MiscArray::filterList($existingList)));

        $optionToUpdateForCssContent = WPACU_PLUGIN_ID . '_critical_css_location_key_' . $locationKey;

        if ($hasContent) {
            $contentToSaveArray = self::prepareContentToSave($content, $showMethod);
            Misc::addUpdateOption($optionToUpdateForCssContent, wp_json_encode($contentToSaveArray));
        } else {
            delete_option($optionToUpdateForCssContent);
        }
    }

    /**
     * @param string $storageType
     * @param int    $objectId
     * @param bool   $enable
     * @param string $content
     * @param string $showMethod
     */
    private function updateGranularCriticalCss($storageType, $objectId, $enable, $content, $showMethod)
    {
        $metaKey = CriticalCss::getMetaKey();

        if (trim($content) === '') {
            self::deleteCriticalCssMeta($storageType, $objectId, $metaKey);
            return;
        }

        $contentToSaveArray = self::prepareContentToSave($content, $showMethod);
        $contentToSaveArray['enable']      = (bool)$enable;
        $contentToSaveArray['show_method'] = $showMethod;

        $metaValue = wp_slash(wp_json_encode($contentToSaveArray));

        if ($storageType === 'post_meta') {
            update_post_meta($objectId, $metaKey, $metaValue);
        } elseif ($storageType === 'term_meta') {
            update_term_meta($objectId, $metaKey, $metaValue);
        } elseif ($storageType === 'user_meta') {
            update_user_meta($objectId, $metaKey, $metaValue);
        }
    }

    /**
     * @param string $content
     * @param string $showMethod
     *
     * @return array
     */
    private static function prepareContentToSave($content, $showMethod)
    {
        $contentToSaveArray = array(
            'content_original' => $content
        );

        if ($showMethod === 'minified') {
            $contentToSaveArray['content_minified'] = MinifyCss::applyMinification($content, true);

            if ($contentToSaveArray['content_minified'] === $contentToSaveArray['content_original']) {
                unset($contentToSaveArray['content_minified']);
            }
        }

        return $contentToSaveArray;
    }

    /**
     * @param string $storageType
     * @param int    $objectId
     * @param string $metaKey
     *
     * @return bool
     */
    private static function deleteCriticalCssMeta($storageType, $objectId, $metaKey)
    {
        if ($storageType === 'post_meta') {
            return (bool)delete_post_meta($objectId, $metaKey);
        }

        if ($storageType === 'term_meta') {
            return (bool)delete_term_meta($objectId, $metaKey);
        }

        if ($storageType === 'user_meta') {
            return (bool)delete_user_meta($objectId, $metaKey);
        }

        return false;
    }

    /**
     * @param string $storageType
     * @param int    $objectId
     * @param string $locationKey
     *
     * @return bool
     */
    private static function isValidMetaStorageTarget($storageType, $objectId, $locationKey)
    {
        if ($objectId < 1) {
            return false;
        }

        if ($storageType === 'post_meta') {
            $post = get_post($objectId);
            return $post instanceof \WP_Post && self::getGeneralLocationKeyForPostType($post->post_type) === $locationKey;
        }

        if ($storageType === 'term_meta') {
            $taxonomy = self::getTaxonomyFromLocationKey($locationKey);
            $term     = $taxonomy ? get_term($objectId, $taxonomy) : false;
            return $term instanceof \WP_Term && $term->taxonomy === $taxonomy;
        }

        if ($storageType === 'user_meta') {
            return $locationKey === 'author' && get_userdata($objectId) instanceof \WP_User;
        }

        return false;
    }

    /**
     * @param string $locationKey
     *
     * @return bool
     */
    public static function hasEnabledGranularCriticalCssForLocation($locationKey)
    {
        $enabledLocations = self::getEnabledGranularCriticalCssLocations();

        return isset($enabledLocations[$locationKey]);
    }

    /**
     * Fetch all granular locations in at most three indexed queries, rather than
     * running a separate query for every main tab, custom post type and taxonomy.
     *
     * @return array
     */
    private static function getEnabledGranularCriticalCssLocations()
    {
        static $enabledLocations = null;

        if (is_array($enabledLocations)) {
            return $enabledLocations;
        }

        global $wpdb;

        $enabledLocations = array();
        $metaKey          = CriticalCss::getMetaKey();

        $postMetaRows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.post_type, pm.meta_value FROM `{$wpdb->postmeta}` pm INNER JOIN `{$wpdb->posts}` p ON p.ID=pm.post_id WHERE pm.meta_key=%s AND p.post_status NOT IN ('trash','auto-draft')",
            $metaKey
        ), ARRAY_A);

        foreach ((array)$postMetaRows as $postMetaRow) {
            $storedData = CriticalCss::decodeStoredCriticalCssData(maybe_unserialize($postMetaRow['meta_value']));

            if (empty($storedData['enable'])) {
                continue;
            }

            $locationKey = self::getGeneralLocationKeyForPostType($postMetaRow['post_type']);

            if ($locationKey !== '') {
                $enabledLocations[$locationKey] = true;
            }
        }

        $termMetaRows = $wpdb->get_results($wpdb->prepare(
            "SELECT tt.taxonomy, tm.meta_value FROM `{$wpdb->termmeta}` tm INNER JOIN `{$wpdb->term_taxonomy}` tt ON tt.term_id=tm.term_id WHERE tm.meta_key=%s",
            $metaKey
        ), ARRAY_A);

        foreach ((array)$termMetaRows as $termMetaRow) {
            $storedData = CriticalCss::decodeStoredCriticalCssData(maybe_unserialize($termMetaRow['meta_value']));

            if (empty($storedData['enable'])) {
                continue;
            }

            $taxonomy = $termMetaRow['taxonomy'];

            if ($taxonomy === 'category') {
                $locationKey = 'category';
            } elseif ($taxonomy === 'post_tag') {
                $locationKey = 'tag';
            } else {
                $locationKey = $taxonomy !== '' ? 'custom_taxonomy_' . $taxonomy : '';
            }

            if ($locationKey !== '') {
                $enabledLocations[$locationKey] = true;
            }
        }

        $userMetaValues = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_value FROM `{$wpdb->usermeta}` WHERE meta_key=%s",
            $metaKey
        ));

        foreach ((array)$userMetaValues as $userMetaValue) {
            $storedData = CriticalCss::decodeStoredCriticalCssData(maybe_unserialize($userMetaValue));

            if ( ! empty($storedData['enable']) ) {
                $enabledLocations['author'] = true;
                break;
            }
        }

        return $enabledLocations;
    }

    /**
     * @param array  $data
     * @param string $wpacuFor
     *
     * @return string
     */
    public static function classToAppendToCriticalCssNavTab($data, $wpacuFor)
    {
        $classToAppend = '';

        $isCustomPostTypesMainTab = $wpacuFor === 'custom_post_types';
        $isCustomPostTypesContext = isset($data['for'])
            && in_array($data['for'], array('custom_post_types', 'custom_post_type_archives'), true);

        if ($data['for'] === $wpacuFor || ($isCustomPostTypesMainTab && $isCustomPostTypesContext)) {
            $classToAppend .= ' wpacu-nav-tab-active ';
        }

        if ($wpacuFor === 'media_attachment') {
            $dbKeyPrefix = 'media';
        } elseif ($wpacuFor === 'custom_post_types') {
            $dbKeyPrefix = 'custom_post_type';
        } elseif ($wpacuFor === 'custom_post_type_archives') {
            $dbKeyPrefix = 'custom_post_type_archive';
        } elseif ($wpacuFor === 'custom_taxonomies') {
            $dbKeyPrefix = 'custom_taxonomy';
        } else {
            $dbKeyPrefix = $wpacuFor;
        }

        if ($isCustomPostTypesMainTab) {
            // The parent status is active when either Singular or Archives has
            // at least one enabled rule. Singular also includes granular metadata.
            $condition = self::isEnabledForAtLeastOnePageType($data['critical_css_config'], 'custom_post_type')
                || self::isEnabledForAtLeastOnePageType($data['critical_css_config'], 'custom_post_type_archive');
        } elseif (in_array($wpacuFor, array('custom_post_type_archives', 'custom_taxonomies'), true)) {
            $condition = self::isEnabledForAtLeastOnePageType($data['critical_css_config'], $dbKeyPrefix);
        } elseif ($wpacuFor === 'media_attachment') {
            $condition = in_array('media', $data['critical_css_tabs_all_enabled_locations'], true)
                || self::hasEnabledGranularCriticalCssForLocation('media');
        } else {
            $condition = in_array($wpacuFor, $data['critical_css_tabs_all_enabled_locations'], true)
                || self::hasEnabledGranularCriticalCssForLocation($wpacuFor);
        }

        $classToAppend .= $condition ? ' wpacu-on ' : ' wpacu-off ';

        return $classToAppend;
    }
}
