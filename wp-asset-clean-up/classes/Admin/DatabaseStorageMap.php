<?php
namespace WpAssetCleanUp\Admin;

/**
 * Builds the read-only database/data architecture map used in Tools -> Storage.
 *
 * The registry documents storage owned by the plugin. The runtime snapshot reads
 * aggregate metadata (key names, record counts, byte lengths and transient timeout
 * timestamps); rule and configuration payloads are never selected or rendered.
 *
 * @package WpAssetCleanUp\Admin
 */
class DatabaseStorageMap
{
    /**
     * Return all data required by the Database Map template.
     *
     * @return array
     */
    public static function getPageData()
    {
        $registry = self::getRegistry();
        $snapshot = self::getCurrentInstallation($registry);
        $registry = self::appendCurrentUsageToRegistry($registry, $snapshot['rows']);

        return array(
            'registry'       => $registry,
            'current_rows'   => $snapshot['rows'],
            'summary'        => self::getSummary($registry, $snapshot['rows']),
            'query_errors'   => $snapshot['query_errors'],
            'generated_at'   => current_time('mysql'),
            'storage_types'  => self::getStorageTypes(),
        );
    }

    /**
     * Declared storage architecture for the common Lite/Pro codebase.
     * Pro-only records are appended through an internal filter.
     *
     * @return array
     */
    public static function getRegistry()
    {
        $pluginId = defined('WPACU_PLUGIN_ID') ? WPACU_PLUGIN_ID : 'wpassetcleanup';
        $metaPrefix = '_' . $pluginId . '_';

        $registry = array(
            array(
                'id'        => 'global_settings',
                'component' => __('Global settings', 'wp-asset-clean-up'),
                'storage'   => 'option',
                'keys'      => array($pluginId . '_settings'),
                'purpose'   => __('Configuration saved from the Settings area, including optimization preferences and feature-specific settings.', 'wp-asset-clean-up'),
                'scope'     => __('Site-wide', 'wp-asset-clean-up'),
                'format'    => __('JSON object', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent. Updated when plugin settings are saved; optionally removed during a full reset or uninstall cleanup.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Included', 'wp-asset-clean-up'),
                'source'    => array('classes/Settings.php', 'classes/Admin/SettingsAdmin.php', 'classes/Admin/ImportExport.php'),
            ),
            array(
                'id'        => 'homepage_unloads',
                'component' => __('CSS/JS Manager: homepage unloads', 'wp-asset-clean-up'),
                'storage'   => 'option',
                'keys'      => array($pluginId . '_front_page_no_load'),
                'purpose'   => __('Stylesheets and scripts unloaded specifically on the website homepage.', 'wp-asset-clean-up'),
                'scope'     => __('Homepage', 'wp-asset-clean-up'),
                'format'    => __('JSON object', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent rule data. Created or updated when homepage unload rules are saved.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Included', 'wp-asset-clean-up'),
                'source'    => array('classes/Main.php', 'classes/Update.php', 'classes/Admin/ImportExport.php'),
            ),
            array(
                'id'        => 'homepage_load_exceptions',
                'component' => __('CSS/JS Manager: homepage exceptions', 'wp-asset-clean-up'),
                'storage'   => 'option',
                'keys'      => array($pluginId . '_front_page_load_exceptions'),
                'purpose'   => __('Homepage load exceptions that override broader unload rules.', 'wp-asset-clean-up'),
                'scope'     => __('Homepage', 'wp-asset-clean-up'),
                'format'    => __('JSON object', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent rule data. Removed automatically when the exception list becomes empty.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Included', 'wp-asset-clean-up'),
                'source'    => array('classes/Main.php', 'classes/Update.php', 'classes/Admin/ImportExport.php'),
            ),
            array(
                'id'        => 'global_unload_rules',
                'component' => __('CSS/JS Manager: site-wide unloads', 'wp-asset-clean-up'),
                'storage'   => 'option',
                'keys'      => array($pluginId . '_global_unload'),
                'purpose'   => __('Stylesheets and scripts unloaded everywhere unless a more specific load exception applies.', 'wp-asset-clean-up'),
                'scope'     => __('Site-wide', 'wp-asset-clean-up'),
                'format'    => __('JSON object', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent rule data. Updated by site-wide unload controls and maintenance routines.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Included', 'wp-asset-clean-up'),
                'source'    => array('classes/Main.php', 'classes/Update.php', 'classes/Maintenance.php'),
            ),
            array(
                'id'        => 'bulk_unload_rules',
                'component' => __('CSS/JS Manager: bulk unloads', 'wp-asset-clean-up'),
                'storage'   => 'option',
                'keys'      => array($pluginId . '_bulk_unload'),
                'purpose'   => __('Bulk unload rules applied by post type and other supported page groups.', 'wp-asset-clean-up'),
                'scope'     => __('Grouped page rules', 'wp-asset-clean-up'),
                'format'    => __('JSON object', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent rule data. Updated from Bulk Changes and rule editors.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Included', 'wp-asset-clean-up'),
                'source'    => array('classes/Main.php', 'classes/Update.php', 'classes/Maintenance.php'),
            ),
            array(
                'id'        => 'post_type_load_exceptions',
                'component' => __('CSS/JS Manager: post type exceptions', 'wp-asset-clean-up'),
                'storage'   => 'option',
                'keys'      => array($pluginId . '_post_type_load_exceptions'),
                'purpose'   => __('Load exceptions applied to all singular entries belonging to a selected post type.', 'wp-asset-clean-up'),
                'scope'     => __('Post type', 'wp-asset-clean-up'),
                'format'    => __('JSON object', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent rule data. Removed when no post type exceptions remain.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Included', 'wp-asset-clean-up'),
                'source'    => array('classes/LoadExceptions.php', 'classes/Update.php', 'classes/Admin/ImportExport.php'),
            ),
            array(
                'id'        => 'global_structured_data',
                'component' => __('Shared rules and interface state', 'wp-asset-clean-up'),
                'storage'   => 'option',
                'keys'      => array($pluginId . '_global_data'),
                'purpose'   => __('Shared structured data used by rule editors, asset notes, preloads, row state and other cross-page features. Pro extends this record with Plugins Manager and advanced asset rules.', 'wp-asset-clean-up'),
                'scope'     => __('Site-wide', 'wp-asset-clean-up'),
                'format'    => __('JSON object with feature namespaces', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent. Individual namespaces are added, updated or removed by the feature that owns them.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Partial', 'wp-asset-clean-up'),
                'source'    => array('classes/Update.php', 'classes/Admin/OverviewEditUpdate.php', 'classes/Admin/ImportExport.php'),
            ),
            array(
                'id'        => 'critical_css_configuration',
                'component' => __('Critical CSS: rule configuration', 'wp-asset-clean-up'),
                'storage'   => 'option',
                'keys'      => array($pluginId . '_critical_css_config'),
                'purpose'   => __('Global status and location-rule configuration used to resolve the appropriate Critical CSS for a request.', 'wp-asset-clean-up'),
                'scope'     => __('Site-wide rule index', 'wp-asset-clean-up'),
                'format'    => __('JSON object', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent. Updated from Manage Critical CSS and removed by Critical CSS reset.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Included', 'wp-asset-clean-up'),
                'source'    => array('classes/OptimiseAssets/CriticalCss.php', 'classes/Admin/CriticalCssAdmin.php', 'classes/Admin/ImportExport.php'),
            ),
            array(
                'id'        => 'critical_css_content',
                'component' => __('Critical CSS: stored content', 'wp-asset-clean-up'),
                'storage'   => 'option',
                'patterns'  => array($pluginId . '_critical_css_location_key_*'),
                'purpose'   => __('Critical CSS content and related settings stored per generated location key.', 'wp-asset-clean-up'),
                'scope'     => __('Location rule', 'wp-asset-clean-up'),
                'format'    => __('JSON object containing CSS and settings', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent while the matching Critical CSS location exists; deleted when that location is removed.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Included', 'wp-asset-clean-up'),
                'source'    => array('classes/OptimiseAssets/CriticalCss.php', 'classes/Admin/CriticalCssAdmin.php', 'classes/Admin/ImportExport.php'),
            ),
            array(
                'id'        => 'page_unload_rules',
                'component' => __('CSS/JS Manager: page unloads', 'wp-asset-clean-up'),
                'storage'   => 'postmeta',
                'keys'      => array($metaPrefix . 'no_load'),
                'purpose'   => __('Stylesheets and scripts unloaded on an individual post, page or other singular object.', 'wp-asset-clean-up'),
                'scope'     => __('Individual post object', 'wp-asset-clean-up'),
                'format'    => __('JSON object', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent object metadata. Removed with the post or when the rule list becomes empty.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Included', 'wp-asset-clean-up'),
                'source'    => array('classes/Main.php', 'classes/Update.php', 'classes/Admin/ImportExport.php'),
            ),
            array(
                'id'        => 'page_load_exceptions',
                'component' => __('CSS/JS Manager: page exceptions', 'wp-asset-clean-up'),
                'storage'   => 'postmeta',
                'keys'      => array($metaPrefix . 'load_exceptions'),
                'purpose'   => __('Per-page load exceptions that override broader unload rules.', 'wp-asset-clean-up'),
                'scope'     => __('Individual post object', 'wp-asset-clean-up'),
                'format'    => __('JSON object', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent object metadata. Removed with the post or when no exceptions remain.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Included', 'wp-asset-clean-up'),
                'source'    => array('classes/Main.php', 'classes/Update.php', 'classes/Admin/ImportExport.php'),
            ),
            array(
                'id'        => 'page_options',
                'component' => __('Page-level optimization options', 'wp-asset-clean-up'),
                'storage'   => 'postmeta',
                'keys'      => array($metaPrefix . 'page_options'),
                'purpose'   => __('Optimization preferences that apply only to an individual post or page.', 'wp-asset-clean-up'),
                'scope'     => __('Individual post object', 'wp-asset-clean-up'),
                'format'    => __('JSON object', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent object metadata. Removed with the post or when all page options return to defaults.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Included', 'wp-asset-clean-up'),
                'source'    => array('classes/Misc.php', 'classes/Update.php', 'classes/Admin/ImportExport.php'),
            ),
            array(
                'id'        => 'object_asset_data_post_meta',
                'component' => __('Advanced asset data: singular object', 'wp-asset-clean-up'),
                'storage'   => 'postmeta',
                'keys'      => array($metaPrefix . 'data'),
                'purpose'   => __('Structured per-object asset configuration, including advanced script attributes, positions and related rules.', 'wp-asset-clean-up'),
                'scope'     => __('Individual post, page or custom post type object', 'wp-asset-clean-up'),
                'format'    => __('JSON object', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent object metadata. Removed with the object or by the complete plugin-data cleanup.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Included', 'wp-asset-clean-up'),
                'source'    => array('classes/Admin/OverviewEditUpdate.php', 'pro/classes/MainPro.php', 'pro/classes/UpdatePro.php', 'classes/Admin/ImportExport.php'),
            ),
            array(
                'id'        => 'object_asset_data_term_meta',
                'component' => __('Advanced asset data: taxonomy term', 'wp-asset-clean-up'),
                'storage'   => 'termmeta',
                'keys'      => array($metaPrefix . 'data'),
                'purpose'   => __('Structured asset configuration stored for an individual taxonomy term archive.', 'wp-asset-clean-up'),
                'scope'     => __('Individual taxonomy term', 'wp-asset-clean-up'),
                'format'    => __('JSON object', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent object metadata. Removed with the term or by the complete plugin-data cleanup.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Included in Pro exports', 'wp-asset-clean-up'),
                'source'    => array('classes/Admin/OverviewEditUpdate.php', 'pro/classes/MainPro.php', 'pro/classes/UpdatePro.php'),
            ),
            array(
                'id'        => 'object_asset_data_user_meta',
                'component' => __('Advanced asset data: author archive', 'wp-asset-clean-up'),
                'storage'   => 'usermeta',
                'keys'      => array($metaPrefix . 'data'),
                'purpose'   => __('Structured asset configuration stored for an individual author archive.', 'wp-asset-clean-up'),
                'scope'     => __('Individual user/author', 'wp-asset-clean-up'),
                'format'    => __('JSON object', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent object metadata. Removed with the user when safe, or by complete plugin-data cleanup outside shared multisite use.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Included in Pro exports', 'wp-asset-clean-up'),
                'source'    => array('classes/Admin/OverviewEditUpdate.php', 'pro/classes/MainPro.php', 'pro/classes/UpdatePro.php'),
            ),
            array(
                'id'        => 'critical_css_post_meta',
                'component' => __('Critical CSS: singular object', 'wp-asset-clean-up'),
                'storage'   => 'postmeta',
                'keys'      => array($metaPrefix . 'critical_css'),
                'purpose'   => __('Critical CSS configuration stored directly on a post or page. It has priority over broader matching rules.', 'wp-asset-clean-up'),
                'scope'     => __('Individual post object', 'wp-asset-clean-up'),
                'format'    => __('JSON object', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent object metadata. Removed with the post or when the granular Critical CSS rule is deleted.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Included', 'wp-asset-clean-up'),
                'source'    => array('classes/OptimiseAssets/CriticalCss.php', 'classes/Admin/CriticalCssAdmin.php', 'classes/Admin/ImportExport.php'),
            ),
            array(
                'id'        => 'critical_css_term_meta',
                'component' => __('Critical CSS: taxonomy term', 'wp-asset-clean-up'),
                'storage'   => 'termmeta',
                'keys'      => array($metaPrefix . 'critical_css'),
                'purpose'   => __('Critical CSS configuration stored directly on a taxonomy term.', 'wp-asset-clean-up'),
                'scope'     => __('Individual taxonomy term', 'wp-asset-clean-up'),
                'format'    => __('JSON object', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent object metadata. Removed with the term or when the granular rule is deleted.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Included in Pro exports', 'wp-asset-clean-up'),
                'source'    => array('classes/OptimiseAssets/CriticalCss.php', 'classes/Admin/CriticalCssAdmin.php'),
            ),
            array(
                'id'        => 'critical_css_user_meta',
                'component' => __('Critical CSS: author archive', 'wp-asset-clean-up'),
                'storage'   => 'usermeta',
                'keys'      => array($metaPrefix . 'critical_css'),
                'purpose'   => __('Critical CSS configuration stored for an individual author archive.', 'wp-asset-clean-up'),
                'scope'     => __('Individual user/author', 'wp-asset-clean-up'),
                'format'    => __('JSON object', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent object metadata. Removed with the user or when the granular rule is deleted.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Included in Pro exports', 'wp-asset-clean-up'),
                'source'    => array('classes/OptimiseAssets/CriticalCss.php', 'classes/Admin/CriticalCssAdmin.php'),
            ),
            array(
                'id'        => 'delegated_admin_access',
                'component' => __('Delegated plugin access', 'wp-asset-clean-up'),
                'storage'   => 'usermeta',
                'keys'      => array($pluginId . '_user_chosen_for_access_to_assets_manager'),
                'purpose'   => __('Marker for non-administrator users explicitly allowed to access Asset CleanUp management areas.', 'wp-asset-clean-up'),
                'scope'     => __('Individual user', 'wp-asset-clean-up'),
                'format'    => __('Integer flag', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent while delegated access remains enabled for the user.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Not exported', 'wp-asset-clean-up'),
                'source'    => array('classes/Admin/SettingsAdminOnlyForAdmin.php'),
            ),
            array(
                'id'        => 'administration_state',
                'component' => __('Administration and consent state', 'wp-asset-clean-up'),
                'storage'   => 'option',
                'keys'      => array(
                    $pluginId . '_first_usage',
                    $pluginId . '_review_notice_status',
                    $pluginId . '_tracking_last_send',
                    $pluginId . '_hide_tracking_notice',
                ),
                'purpose'   => __('Timestamps and preference markers used for review prompts, usage consent and administrative notices.', 'wp-asset-clean-up'),
                'scope'     => __('Site-wide administration', 'wp-asset-clean-up'),
                'format'    => __('Timestamp, flag or small array', 'wp-asset-clean-up'),
                'lifecycle' => __('Persistent operational state. Recreated when the related workflow runs again.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Not exported', 'wp-asset-clean-up'),
                'source'    => array('classes/Admin/PluginReview.php', 'classes/PluginTracking.php'),
            ),
            array(
                'id'        => 'generated_file_runtime_transients',
                'component' => __('Generated-file cache state', 'wp-asset-clean-up'),
                'storage'   => 'transient',
                'keys'      => array(
                    $pluginId . '_assets_info',
                    $pluginId . '_clear_assets_cache',
                    $pluginId . '_clear_assets_cache_via_link',
                    $pluginId . '_cache_just_cleared_via_link_dash_area',
                    $pluginId . '_last_clear_cache',
                    $pluginId . '_clean_unused_assets_info_lock',
                ),
                'patterns'  => array(
                    $pluginId . '_external_srcs_ref_*',
                ),
                'purpose'   => __('Temporary asset indexes, external-source collections, cache-clear markers and maintenance locks used by the generated CSS/JS file workflow.', 'wp-asset-clean-up'),
                'scope'     => __('Generated-file and asset operations', 'wp-asset-clean-up'),
                'format'    => __('Mixed temporary data', 'wp-asset-clean-up'),
                'lifecycle' => __('Temporary. Expired or deleted after cache maintenance, asset discovery or the related administration notice completes.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Not exported', 'wp-asset-clean-up'),
                'source'    => array('classes/Main.php', 'classes/AssetsManager.php', 'classes/HardcodedAssets.php', 'classes/Maintenance.php', 'classes/OptimiseAssets/OptimizeCommon.php'),
            ),
            array(
                'id'        => 'administration_workflow_transients',
                'component' => __('Administration workflow messages', 'wp-asset-clean-up'),
                'storage'   => 'transient',
                'keys'      => array(
                    $pluginId . '_frontend_assets_manager_just_updated',
                    $pluginId . '_preloads_just_removed',
                    $pluginId . '_settings_updated',
                    $pluginId . '_settings_submit_errors',
                    $pluginId . '_redirect_after_activation',
                    $pluginId . '_overview_edit_updated_rules',
                    $pluginId . '_import_done',
                    $pluginId . '_import_error',
                    $pluginId . '_reset_done',
                ),
                'purpose'   => __('Short-lived success messages, validation errors and operation-result payloads passed safely across administration redirects.', 'wp-asset-clean-up'),
                'scope'     => __('WordPress administration workflows', 'wp-asset-clean-up'),
                'format'    => __('Flag, message or small result array', 'wp-asset-clean-up'),
                'lifecycle' => __('Temporary. Usually consumed and deleted on the next administration request, with a short expiration as a fallback.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Not exported', 'wp-asset-clean-up'),
                'source'    => array('classes/Update.php', 'classes/Preloads.php', 'classes/Admin/SettingsAdmin.php', 'classes/Admin/Plugin.php', 'classes/Admin/OverviewEditUpdate.php', 'classes/Admin/ImportExport.php', 'classes/Admin/Tools.php'),
            ),
            array(
                'id'        => 'calculated_runtime_transients',
                'component' => __('Calculated administration caches', 'wp-asset-clean-up'),
                'storage'   => 'transient',
                'keys'      => array(
                    $pluginId . '_active_plugins_icons',
                    $pluginId . '_site_meta_generator_tags',
                    $pluginId . '_total_non_admin_users',
                ),
                'patterns'  => array(
                    $pluginId . '_total_unloaded_assets_*',
                ),
                'purpose'   => __('Calculated counts, plugin icon metadata and compatibility data cached to avoid repeating relatively expensive administration work.', 'wp-asset-clean-up'),
                'scope'     => __('Calculated site information', 'wp-asset-clean-up'),
                'format'    => __('Count, JSON string or calculated array', 'wp-asset-clean-up'),
                'lifecycle' => __('Temporary cache. Recomputed after its expiration or after the related data changes.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Recreated automatically', 'wp-asset-clean-up'),
                'source'    => array('classes/CleanUp.php', 'classes/Admin/MiscAdmin.php', 'classes/Admin/SettingsAdminOnlyForAdmin.php'),
            ),
            array(
                'id'        => 'optimized_asset_lookup_transients',
                'component' => __('Optimized CSS/JS lookup cache', 'wp-asset-clean-up'),
                'storage'   => 'transient',
                'patterns'  => array('wpacu_css_optimize_*', 'wpacu_js_optimize_*'),
                'purpose'   => __('Database-side lookup metadata for individual optimized CSS and JavaScript files. These records are created only when cached file details are configured to use the database.', 'wp-asset-clean-up'),
                'scope'     => __('Per optimized asset', 'wp-asset-clean-up'),
                'format'    => __('String marker or newline-delimited asset metadata', 'wp-asset-clean-up'),
                'lifecycle' => __('Temporary cache. Rebuilt as assets are optimized and removed when generated-file cache data is cleared.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Not exported', 'wp-asset-clean-up'),
                'source'    => array('classes/OptimiseAssets/OptimizeCss.php', 'classes/OptimiseAssets/OptimizeJs.php', 'classes/OptimiseAssets/OptimizeCommon.php', 'classes/Admin/Tools.php'),
            ),
            array(
                'id'        => 'google_fonts_stylesheet_transients',
                'component' => __('Google Fonts stylesheet resolution cache', 'wp-asset-clean-up'),
                'storage'   => 'transient',
                'patterns'  => array('wpacu_gfcss_*'),
                'purpose'   => __('Resolved Google Fonts stylesheet responses cached by stylesheet URL and browser user agent to avoid repeated remote requests during font analysis.', 'wp-asset-clean-up'),
                'scope'     => __('Per stylesheet and user agent', 'wp-asset-clean-up'),
                'format'    => __('Array containing the stylesheet response body', 'wp-asset-clean-up'),
                'lifecycle' => __('Temporary scanner cache. Refreshed after its Transients API expiration interval.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Not exported', 'wp-asset-clean-up'),
                'source'    => array('classes/OptimiseAssets/FontsGooglePreloadScanner.php'),
            ),
            array(
                'id'        => 'wp_core_block_style_handles_transient',
                'component' => __('WordPress block stylesheet handle cache', 'wp-asset-clean-up'),
                'storage'   => 'transient',
                'keys'      => array('wpacu_wp_core_css_handles_from_wp_includes_blocks'),
                'purpose'   => __('Cached list of WordPress core block stylesheet handles discovered from block metadata in wp-includes.', 'wp-asset-clean-up'),
                'scope'     => __('Current WordPress installation', 'wp-asset-clean-up'),
                'format'    => __('Array of stylesheet handles', 'wp-asset-clean-up'),
                'lifecycle' => __('Temporary discovery cache. Rebuilt after its Transients API expiration interval.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Recreated automatically', 'wp-asset-clean-up'),
                'source'    => array('classes/Misc.php'),
            ),
            array(
                'id'        => 'font_scanner_transients',
                'component' => __('Font scanner sessions and reports', 'wp-asset-clean-up'),
                'storage'   => 'transient',
                'patterns'  => array('wpacu_gfps_*', 'wpacu_lfps_*'),
                'purpose'   => __('Temporary Google Fonts and local-font scanner session state, progress and report data.', 'wp-asset-clean-up'),
                'scope'     => __('Per scanner token', 'wp-asset-clean-up'),
                'format'    => __('JSON/array session data', 'wp-asset-clean-up'),
                'lifecycle' => __('Temporary. Expires after the scanner workflow or is removed when the report is cleared.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Not exported', 'wp-asset-clean-up'),
                'source'    => array('classes/OptimiseAssets/FontPreloadScanner.php', 'classes/OptimiseAssets/FontPreloadScannerEarly.php'),
            ),
            array(
                'id'        => 'announcement_transients',
                'component' => __('Plugin announcement feed cache', 'wp-asset-clean-up'),
                'storage'   => 'transient',
                'keys'      => array('wpacu_lite_announcements', 'wpacu_pro_announcements'),
                'purpose'   => __('Sanitized announcement feed cached locally to avoid a remote request on each administration page load.', 'wp-asset-clean-up'),
                'scope'     => __('Plugin edition', 'wp-asset-clean-up'),
                'format'    => __('Array', 'wp-asset-clean-up'),
                'lifecycle' => __('Temporary. Refreshed after its Transients API expiration interval.', 'wp-asset-clean-up'),
                'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
                'transfer'  => __('Not exported', 'wp-asset-clean-up'),
                'source'    => array('classes/Admin/PluginAnnouncements.php'),
            ),
        );

        /**
         * Filter the declared storage registry.
         *
         * Pro uses this hook to append edition-specific database records while
         * keeping the common Database Map reusable by Lite.
         *
         * @param array $registry
         */
        $registry = apply_filters('wpacu_internal_database_storage_map_registry', $registry);

        return self::normalizeRegistry($registry);
    }

    /**
     * @return array
     */
    private static function getStorageTypes()
    {
        return array(
            'option'    => __('WordPress options', 'wp-asset-clean-up'),
            'postmeta'  => __('Post meta', 'wp-asset-clean-up'),
            'termmeta'  => __('Term meta', 'wp-asset-clean-up'),
            'usermeta'  => __('User meta', 'wp-asset-clean-up'),
            'transient' => __('Transients / cache', 'wp-asset-clean-up'),
        );
    }

    /**
     * @param array $registry
     *
     * @return array
     */
    private static function normalizeRegistry($registry)
    {
        if (! is_array($registry)) {
            return array();
        }

        $defaults = array(
            'id'        => '',
            'component' => '',
            'storage'   => 'option',
            'keys'      => array(),
            'patterns'  => array(),
            'purpose'   => '',
            'scope'     => '',
            'format'    => '',
            'lifecycle' => '',
            'edition'   => __('Lite & Pro', 'wp-asset-clean-up'),
            'transfer'  => __('Not exported', 'wp-asset-clean-up'),
            'source'    => array(),
            'sensitive' => false,
        );

        $normalized = array();
        $seenIds = array();
        $allowedStorage = array_keys(self::getStorageTypes());

        foreach ($registry as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $entry = array_merge($defaults, $entry);
            $entry['id'] = sanitize_key($entry['id'] !== '' ? $entry['id'] : 'storage_' . $index);

            if (isset($seenIds[$entry['id']])) {
                $entry['id'] .= '_' . $index;
            }
            $seenIds[$entry['id']] = true;

            $entry['storage'] = in_array($entry['storage'], $allowedStorage, true) ? $entry['storage'] : 'option';
            $entry['keys'] = self::normalizeStringList($entry['keys']);
            $entry['patterns'] = self::normalizeStringList($entry['patterns']);
            $entry['source'] = self::normalizeStringList($entry['source']);
            $entry['sensitive'] = (bool)$entry['sensitive'];

            if (empty($entry['keys']) && empty($entry['patterns'])) {
                continue;
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @param mixed $values
     *
     * @return array
     */
    private static function normalizeStringList($values)
    {
        if (! is_array($values)) {
            $values = array($values);
        }

        $normalized = array();
        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $value = trim((string)$value);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Read only aggregate information for records owned by the plugin.
     *
     * @param array $registry
     *
     * @return array
     */
    private static function getCurrentInstallation($registry)
    {
        global $wpdb;

        $rows = array();
        $queryErrors = array();

        $optionRows = self::queryOptionRows();
        if ($optionRows === false) {
            $queryErrors[] = __('WordPress options could not be inspected.', 'wp-asset-clean-up');
        } else {
            foreach ($optionRows as $row) {
                $rows[] = self::prepareCurrentRow(
                    'option',
                    isset($row['storage_key']) ? $row['storage_key'] : '',
                    isset($row['records']) ? (int)$row['records'] : 1,
                    isset($row['bytes']) ? (int)$row['bytes'] : 0,
                    isset($row['autoload']) ? (string)$row['autoload'] : '',
                    0,
                    $registry,
                    $wpdb->options
                );
            }
        }

        $metaQueries = array(
            'postmeta' => array($wpdb->postmeta, false),
            'termmeta' => array($wpdb->termmeta, false),
            'usermeta' => array($wpdb->usermeta, true),
        );

        foreach ($metaQueries as $storage => $metaQuery) {
            $metaRows = self::queryMetaRows($metaQuery[0], $metaQuery[1]);
            if ($metaRows === false) {
                $queryErrors[] = sprintf(__('%s could not be inspected.', 'wp-asset-clean-up'), self::getStorageLabel($storage));
                continue;
            }

            foreach ($metaRows as $row) {
                $rows[] = self::prepareCurrentRow(
                    $storage,
                    isset($row['storage_key']) ? $row['storage_key'] : '',
                    isset($row['records']) ? (int)$row['records'] : 0,
                    isset($row['bytes']) ? (int)$row['bytes'] : 0,
                    '',
                    0,
                    $registry,
                    $metaQuery[0]
                );
            }
        }

        $transientRows = self::queryTransientRows($registry);
        if ($transientRows === false) {
            $queryErrors[] = __('WordPress transients could not be inspected.', 'wp-asset-clean-up');
        } else {
            foreach ($transientRows as $row) {
                $rows[] = self::prepareCurrentRow(
                    'transient',
                    isset($row['storage_key']) ? $row['storage_key'] : '',
                    1,
                    isset($row['bytes']) ? (int)$row['bytes'] : 0,
                    isset($row['autoload']) ? (string)$row['autoload'] : '',
                    isset($row['expires']) ? (int)$row['expires'] : 0,
                    $registry,
                    $wpdb->options
                );
            }
        }

        usort($rows, array(__CLASS__, 'sortCurrentRows'));

        return array(
            'rows'         => $rows,
            'query_errors' => $queryErrors,
        );
    }

    /**
     * @return array|false
     */
    private static function queryOptionRows()
    {
        global $wpdb;

        $pluginPrefix = $wpdb->esc_like((defined('WPACU_PLUGIN_ID') ? WPACU_PLUGIN_ID : 'wpassetcleanup') . '_') . '%';
        $legacyPrefix = $wpdb->esc_like('wpacu_') . '%';
        $sql = $wpdb->prepare(
            "SELECT option_name AS storage_key, 1 AS records, LENGTH(option_value) AS bytes, autoload
             FROM {$wpdb->options}
             WHERE option_name LIKE %s OR option_name LIKE %s
             ORDER BY option_name ASC",
            $pluginPrefix,
            $legacyPrefix
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : false;
    }

    /**
     * @param string $table
     * @param bool   $includeDelegatedAccessKey
     *
     * @return array|false
     */
    private static function queryMetaRows($table, $includeDelegatedAccessKey)
    {
        global $wpdb;

        $metaLike = $wpdb->esc_like('_' . (defined('WPACU_PLUGIN_ID') ? WPACU_PLUGIN_ID : 'wpassetcleanup') . '_') . '%';
        $legacyMetaLike = $wpdb->esc_like('_wpacu_') . '%';
        $where = '(meta_key LIKE %s OR meta_key LIKE %s)';
        $args = array($metaLike, $legacyMetaLike);

        if ($includeDelegatedAccessKey) {
            $where .= ' OR meta_key = %s';
            $args[] = (defined('WPACU_PLUGIN_ID') ? WPACU_PLUGIN_ID : 'wpassetcleanup') . '_user_chosen_for_access_to_assets_manager';
        }

        $query = "SELECT meta_key AS storage_key, COUNT(*) AS records, COALESCE(SUM(LENGTH(meta_value)), 0) AS bytes
                  FROM {$table}
                  WHERE {$where}
                  GROUP BY meta_key
                  ORDER BY meta_key ASC";

        $prepared = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($query), $args));
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        return is_array($rows) ? $rows : false;
    }

    /**
     * @param array $registry
     *
     * @return array|false
     */
    private static function queryTransientRows($registry)
    {
        global $wpdb;

        $prefixes = self::getTransientQueryPrefixes($registry);
        if (empty($prefixes)) {
            return array();
        }

        $valueWhere = array();
        $valueArgs = array();
        $timeoutWhere = array();
        $timeoutArgs = array();

        foreach ($prefixes as $prefix) {
            $valueWhere[] = 'option_name LIKE %s';
            $valueArgs[] = $wpdb->esc_like('_transient_' . $prefix) . '%';
            $timeoutWhere[] = 'option_name LIKE %s';
            $timeoutArgs[] = $wpdb->esc_like('_transient_timeout_' . $prefix) . '%';
        }

        $valueSql = "SELECT option_name, LENGTH(option_value) AS bytes, autoload
                     FROM {$wpdb->options}
                     WHERE " . implode(' OR ', $valueWhere) . '
                     ORDER BY option_name ASC';
        $valueSql = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($valueSql), $valueArgs));
        $valueRows = $wpdb->get_results($valueSql, ARRAY_A);

        if (! is_array($valueRows)) {
            return false;
        }

        $timeoutSql = "SELECT option_name, option_value AS timeout_value
                       FROM {$wpdb->options}
                       WHERE " . implode(' OR ', $timeoutWhere);
        $timeoutSql = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($timeoutSql), $timeoutArgs));
        $timeoutRows = $wpdb->get_results($timeoutSql, ARRAY_A);

        if (! is_array($timeoutRows)) {
            return false;
        }

        $timeouts = array();
        foreach ($timeoutRows as $timeoutRow) {
            $optionName = isset($timeoutRow['option_name']) ? (string)$timeoutRow['option_name'] : '';
            $logicalKey = strpos($optionName, '_transient_timeout_') === 0 ? substr($optionName, strlen('_transient_timeout_')) : '';
            if ($logicalKey !== '') {
                $timeouts[$logicalKey] = isset($timeoutRow['timeout_value']) ? (int)$timeoutRow['timeout_value'] : 0;
            }
        }

        $rows = array();
        foreach ($valueRows as $valueRow) {
            $optionName = isset($valueRow['option_name']) ? (string)$valueRow['option_name'] : '';
            $logicalKey = strpos($optionName, '_transient_') === 0 ? substr($optionName, strlen('_transient_')) : '';
            if ($logicalKey === '') {
                continue;
            }

            $rows[] = array(
                'storage_key' => $logicalKey,
                'bytes'       => isset($valueRow['bytes']) ? (int)$valueRow['bytes'] : 0,
                'autoload'    => isset($valueRow['autoload']) ? (string)$valueRow['autoload'] : '',
                'expires'     => isset($timeouts[$logicalKey]) ? (int)$timeouts[$logicalKey] : 0,
            );
        }

        return $rows;
    }

    /**
     * @param array $registry
     *
     * @return array
     */
    private static function getTransientQueryPrefixes($registry)
    {
        // Inspect both storage namespaces owned by Asset CleanUp so unknown/legacy
        // transient keys can be surfaced as "Needs review", not silently ignored.
        $prefixes = array(
            (defined('WPACU_PLUGIN_ID') ? WPACU_PLUGIN_ID : 'wpassetcleanup') . '_',
            'wpacu_',
        );

        foreach ($registry as $entry) {
            if (! isset($entry['storage']) || $entry['storage'] !== 'transient') {
                continue;
            }

            $candidates = array_merge(
                isset($entry['keys']) ? $entry['keys'] : array(),
                isset($entry['patterns']) ? $entry['patterns'] : array()
            );

            foreach ($candidates as $candidate) {
                $wildcardPos = strpos($candidate, '*');
                $prefix = $wildcardPos === false ? $candidate : substr($candidate, 0, $wildcardPos);
                $prefix = trim($prefix);
                if ($prefix !== '') {
                    $prefixes[] = $prefix;
                }
            }
        }

        $prefixes = array_values(array_unique($prefixes));
        usort($prefixes, function ($first, $second) {
            return strlen($first) - strlen($second);
        });

        // Remove prefixes already covered by a shorter prefix to keep the SQL compact.
        $compact = array();
        foreach ($prefixes as $prefix) {
            $covered = false;
            foreach ($compact as $existingPrefix) {
                if (strpos($prefix, $existingPrefix) === 0) {
                    $covered = true;
                    break;
                }
            }

            if (! $covered) {
                $compact[] = $prefix;
            }
        }

        return $compact;
    }

    /**
     * @param string $storage
     * @param string $storageKey
     * @param int    $records
     * @param int    $bytes
     * @param string $autoload
     * @param int    $expires
     * @param array  $registry
     * @param string $tableName
     *
     * @return array
     */
    private static function prepareCurrentRow($storage, $storageKey, $records, $bytes, $autoload, $expires, $registry, $tableName)
    {
        $matchIndex = self::findRegistryMatchIndex($storage, $storageKey, $registry);
        $isRegistered = $matchIndex !== false;
        $entry = $isRegistered ? $registry[$matchIndex] : array();
        $isExpired = $storage === 'transient' && $expires > 0 && $expires <= time();

        $isStoredInOptionsTable = in_array($storage, array('option', 'transient'), true);

        return array(
            'storage'          => $storage,
            'storage_label'    => self::getStorageLabel($storage),
            'storage_key'      => $storageKey,
            'location'         => $tableName,
            'records'          => max(0, (int)$records),
            'bytes'            => max(0, (int)$bytes),
            'bytes_formatted'  => self::formatBytes($bytes),
            'autoload'         => $autoload,
            'autoloaded'       => $isStoredInOptionsTable && self::isAutoloaded($autoload),
            'autoload_label'   => $isStoredInOptionsTable ? self::getAutoloadLabel($autoload) : __('Not applicable', 'wp-asset-clean-up'),
            'expires'          => (int)$expires,
            'expiration_label' => $storage === 'transient' ? self::getExpirationLabel($expires) : __('Not applicable', 'wp-asset-clean-up'),
            'status'           => $isExpired ? 'expired' : ($isRegistered ? 'registered' : 'unregistered'),
            'status_label'     => $isExpired ? __('Expired', 'wp-asset-clean-up') : ($isRegistered ? __('Registered', 'wp-asset-clean-up') : __('Review', 'wp-asset-clean-up')),
            'registry_id'      => $isRegistered && isset($entry['id']) ? $entry['id'] : '',
            'component'        => $isRegistered && isset($entry['component']) ? $entry['component'] : __('Unregistered WPACU data', 'wp-asset-clean-up'),
            'purpose'          => $isRegistered && isset($entry['purpose']) ? $entry['purpose'] : __('The key matches an Asset CleanUp storage prefix but is not declared in the current storage registry. It may be legacy data or a storage record that still needs documentation.', 'wp-asset-clean-up'),
            'edition'          => $isRegistered && isset($entry['edition']) ? $entry['edition'] : __('Needs review', 'wp-asset-clean-up'),
            'transfer'         => $isRegistered && isset($entry['transfer']) ? $entry['transfer'] : __('Unknown', 'wp-asset-clean-up'),
            'source'           => $isRegistered && isset($entry['source']) ? $entry['source'] : array(),
            'sensitive'        => $isRegistered && ! empty($entry['sensitive']),
        );
    }

    /**
     * @param string $storage
     * @param string $storageKey
     * @param array  $registry
     *
     * @return int|false
     */
    private static function findRegistryMatchIndex($storage, $storageKey, $registry)
    {
        // Exact matches take priority over wildcard groups.
        foreach ($registry as $index => $entry) {
            if ($entry['storage'] !== $storage) {
                continue;
            }

            if (in_array($storageKey, $entry['keys'], true)) {
                return $index;
            }
        }

        foreach ($registry as $index => $entry) {
            if ($entry['storage'] !== $storage) {
                continue;
            }

            foreach ($entry['patterns'] as $pattern) {
                if (self::wildcardMatch($pattern, $storageKey)) {
                    return $index;
                }
            }
        }

        return false;
    }

    /**
     * @param string $pattern
     * @param string $value
     *
     * @return bool
     */
    private static function wildcardMatch($pattern, $value)
    {
        $quoted = preg_quote($pattern, '/');
        $regex = '/^' . str_replace('\\*', '.*', $quoted) . '$/';
        return (bool)preg_match($regex, $value);
    }

    /**
     * Add current installation counters to each declared architecture record.
     *
     * @param array $registry
     * @param array $rows
     *
     * @return array
     */
    private static function appendCurrentUsageToRegistry($registry, $rows)
    {
        foreach ($registry as $index => $entry) {
            $registry[$index]['current_keys'] = 0;
            $registry[$index]['current_records'] = 0;
            $registry[$index]['current_bytes'] = 0;
            $registry[$index]['current_bytes_formatted'] = self::formatBytes(0);
        }

        foreach ($rows as $row) {
            if (empty($row['registry_id'])) {
                continue;
            }

            foreach ($registry as $index => $entry) {
                if ($entry['id'] !== $row['registry_id']) {
                    continue;
                }

                $registry[$index]['current_keys']++;
                $registry[$index]['current_records'] += (int)$row['records'];
                $registry[$index]['current_bytes'] += (int)$row['bytes'];
                $registry[$index]['current_bytes_formatted'] = self::formatBytes($registry[$index]['current_bytes']);
                break;
            }
        }

        return $registry;
    }

    /**
     * @param array $registry
     * @param array $rows
     *
     * @return array
     */
    private static function getSummary($registry, $rows)
    {
        $records = 0;
        $bytes = 0;
        $autoloadedRows = 0;
        $autoloadedBytes = 0;
        $unregistered = 0;
        $activeStorageTypes = array();

        foreach ($rows as $row) {
            $records += (int)$row['records'];
            $bytes += (int)$row['bytes'];
            $activeStorageTypes[$row['storage']] = true;

            if (! empty($row['autoloaded'])) {
                $autoloadedRows++;
                $autoloadedBytes += (int)$row['bytes'];
            }

            if ($row['status'] === 'unregistered') {
                $unregistered++;
            }
        }

        return array(
            'registered_groups'          => count($registry),
            'current_keys'               => count($rows),
            'current_records'            => $records,
            'database_bytes'             => $bytes,
            'database_bytes_formatted'   => self::formatBytes($bytes),
            'autoloaded_rows'            => $autoloadedRows,
            'autoloaded_bytes'           => $autoloadedBytes,
            'autoloaded_bytes_formatted' => self::formatBytes($autoloadedBytes),
            'unregistered_keys'          => $unregistered,
            'active_storage_types'       => count($activeStorageTypes),
        );
    }

    /**
     * @param array $first
     * @param array $second
     *
     * @return int
     */
    private static function sortCurrentRows($first, $second)
    {
        $order = array('option' => 1, 'postmeta' => 2, 'termmeta' => 3, 'usermeta' => 4, 'transient' => 5);
        $firstOrder = isset($order[$first['storage']]) ? $order[$first['storage']] : 99;
        $secondOrder = isset($order[$second['storage']]) ? $order[$second['storage']] : 99;

        if ($firstOrder === $secondOrder) {
            return strcmp($first['storage_key'], $second['storage_key']);
        }

        return $firstOrder < $secondOrder ? -1 : 1;
    }

    /**
     * @param string $storage
     *
     * @return string
     */
    private static function getStorageLabel($storage)
    {
        $types = self::getStorageTypes();
        return isset($types[$storage]) ? $types[$storage] : $storage;
    }

    /**
     * @param string $autoload
     *
     * @return bool
     */
    private static function isAutoloaded($autoload)
    {
        $autoload = strtolower(trim((string)$autoload));
        if ($autoload === '') {
            return false;
        }

        return ! in_array($autoload, array('no', 'off', 'auto-off'), true);
    }

    /**
     * @param string $autoload
     *
     * @return string
     */
    private static function getAutoloadLabel($autoload)
    {
        if ($autoload === '') {
            return __('Unknown', 'wp-asset-clean-up');
        }

        return self::isAutoloaded($autoload)
            ? sprintf(__('Yes (%s)', 'wp-asset-clean-up'), $autoload)
            : sprintf(__('No (%s)', 'wp-asset-clean-up'), $autoload);
    }

    /**
     * @param int $expires
     *
     * @return string
     */
    private static function getExpirationLabel($expires)
    {
        $expires = (int)$expires;
        if ($expires <= 0) {
            return __('No expiration recorded', 'wp-asset-clean-up');
        }

        if ($expires <= time()) {
            return sprintf(__('Expired %s ago', 'wp-asset-clean-up'), human_time_diff($expires, time()));
        }

        return sprintf(__('Expires in %s', 'wp-asset-clean-up'), human_time_diff(time(), $expires));
    }

    /**
     * @param int $bytes
     *
     * @return string
     */
    private static function formatBytes($bytes)
    {
        $bytes = max(0, (int)$bytes);

        if (function_exists('size_format')) {
            return size_format($bytes, 2);
        }

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = array('KB', 'MB', 'GB', 'TB');
        $value = $bytes / 1024;
        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'TB') {
                return round($value, 2) . ' ' . $unit;
            }
            $value /= 1024;
        }

        return $bytes . ' B';
    }
}
