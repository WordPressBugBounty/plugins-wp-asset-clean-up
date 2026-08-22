<?php
namespace WpAssetCleanUpLite;

use WpAssetCleanUpLite\Admin\MainAdminLite;
use WpAssetCleanUpLite\Admin\PluginLite;
use WpAssetCleanUpLite\Admin\ProPreview;
use WpAssetCleanUpLite\Admin\UpgradeNotices;

/**
 *
 */
class _BootstrapLite
{
    /**
     * @return void
     */
    public static function registerEarlyHooks()
    {
        add_action('wpacu_internal_register_edition_hooks', array(__CLASS__, 'registerInternalHooks'));
        add_filter('wpacu_internal_autoload_namespaces',    array(__CLASS__, 'autoloadNamespaces'));
    }

    /**
     * @return void
     */
    public static function registerInternalHooks()
    {
        // /classes/
        self::registerInternalLiteHooksFor('MenuLite');
        self::registerInternalLiteHooksFor('AdminBarLite');
        self::registerInternalLiteHooksFor('MainLite');
        self::registerInternalLiteHooksFor('MainFrontLite');
        self::registerInternalLiteHooksFor('OwnAssetsLite');

        // /classes/Admin
        self::registerInternalLiteHooksFor('PluginLite');
        self::registerInternalLiteHooksFor('UpgradeNotices');
        self::registerInternalLiteHooksFor('MainAdminLite');
        self::registerInternalLiteHooksFor('ProPreview');
    }

    /**
     * @param $namespaces
     *
     * @return array
     */
    public static function autoloadNamespaces($namespaces)
    {
        if (defined('WPACU_LITE_CLASSES_PATH')) {
            $namespaces['WpAssetCleanUpLite'] = WPACU_LITE_CLASSES_PATH;
        }

        return $namespaces;
    }

    /**
     * Keep one lazily-created object per Lite class so state can be shared between callbacks.
     * Merely using ClassName::class does not trigger the autoloader.
     *
     * @param string $className
     *
     * @return object
     */
    private static function getLazyInternalLiteInstance($className)
    {
        static $instances = array();

        if ( ! isset($instances[$className]) ) {
            $instances[$className] = new $className();
        }

        return $instances[$className];
    }

    /**
     * @param string $class
     *
     * @return void
     */
    public static function registerInternalLiteHooksFor($class)
    {
        if ($class === 'MenuLite') {
            add_action('admin_init',                               array(MenuLite::class, 'maybeRedirectLicensePage'));
            add_action('admin_init',                               array(MenuLite::class, 'maybeRedirectGoProPage'));
            add_filter('wpacu_internal_menu_all_pages',            array(MenuLite::class, 'filterAllMenuPages'));
            add_filter('wpacu_internal_top_area_links',            array(MenuLite::class, 'filterTopAreaLinks'));
            add_action('wpacu_internal_admin_menu_after_get_help', array(MenuLite::class, 'removeLicenseSubmenuPage'), 5);
            add_action('wpacu_internal_admin_menu_after_get_help', array(MenuLite::class, 'addGoProSubmenuPage'));
        }

        if ($class === 'AdminBarLite') {
            add_filter('wpacu_internal_admin_bar_unloaded_assets_lists', array(AdminBarLite::class, 'filterUnloadedAssetsLists'));
            add_action('wpacu_internal_admin_bar_after_overview',        array(AdminBarLite::class, 'addSupportForumLink'));
        }

        if ($class === 'MainLite') {
            add_action('wpacu_internal_current_post_id_set', static function ($currentPostId, $main) {
                /** @var MainLite $instance */
                $instance = self::getLazyInternalLiteInstance(MainLite::class);

                return $instance->setUpdateableStatus($currentPostId, $main);
            }, 10, 2);
        }

        if ($class === 'MainFrontLite') {
            add_filter('wpacu_internal_main_front_should_stop_set_vars_after_update', static function ($shouldStop) {
                /** @var MainFrontLite $instance */
                $instance = self::getLazyInternalLiteInstance(MainFrontLite::class);

                return $instance->shouldStopSetVarsAfterUpdate($shouldStop);
            });

            add_filter('wpacu_internal_main_front_use_global_unload_only', static function ($useGlobalUnloadOnly, $assetType, $globalUnload) {
                /** @var MainFrontLite $instance */
                $instance = self::getLazyInternalLiteInstance(MainFrontLite::class);

                return $instance->useGlobalUnloadOnly($useGlobalUnloadOnly, $assetType, $globalUnload);
            }, 10, 3);
        }

        if ($class === 'PluginLite') {
            add_filter('admin_footer_text',                  array(PluginLite::class, 'adminFooterText'), 1, 1);
            add_filter('wpacu_internal_plugin_action_links', static function ($links) {
                /** @var PluginLite $instance */
                $instance = self::getLazyInternalLiteInstance(PluginLite::class);

                return $instance->addGoProActionLink($links);
            });
        }

        if ($class === 'UpgradeNotices') {
            add_action('current_screen', array(UpgradeNotices::class, 'currentScreen'));
        }

        if ($class === 'MainAdminLite') {
            add_action('wp', array(MainAdminLite::class, 'maybeForce404ForDashboardAssetsFetch'), 0);

            add_filter('wpacu_internal_should_stop_frontend_edit_view_output',          array(MainAdminLite::class, 'filterShouldStopFrontendEditViewOutput'), 10, 4);
            add_filter('wpacu_internal_misc_get_page_url_admin_area',                 array(MainAdminLite::class, 'filterAdminAreaPageUrl'), 10, 2);
            add_filter('wpacu_internal_object_data',                                  array(MainAdminLite::class, 'filterObjectDataForArchivePreview'), 20, 1);
            add_filter('wpacu_internal_template_file_path',                             array(MainAdminLite::class, 'filterTemplateFilePath'), 10, 3);
            add_filter('wpacu_internal_is_dashboard_ajax_call_for_specific_page_type', array(MainAdminLite::class, 'filterIsDashboardAjaxCallForSpecificPageType'));
            add_filter('wpacu_internal_get_assets_type',                                array(MainAdminLite::class, 'filterGetAssetsType'), 10, 2);
            add_filter('wpacu_internal_data_var_template',                              array(MainAdminLite::class, 'filterDataVarTemplate'));
            add_filter('wpacu_internal_data_for_non_singular_asset_management',         array(MainAdminLite::class, 'filterDataForNonSingularAssetManagement'));
        }

        if ($class === 'ProPreview') {
            add_action('admin_init',            array(ProPreview::class, 'blockProOnlyWrites'), 0);
            add_action('admin_enqueue_scripts', array(ProPreview::class, 'enqueueAssets'), 40);
        }

        if ($class === 'OwnAssetsLite') {
            add_filter('wpacu_internal_own_assets_sweetalert_inline_style',  array(OwnAssetsLite::class, 'sweetAlertInlineStyle'));
            add_action('wpacu_internal_own_assets_after_sweetalert_enqueue', array(OwnAssetsLite::class, 'sweetAlertUpgradeToProPopups'));
        }
    }

}
