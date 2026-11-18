<?php
namespace WpAssetCleanUpLite;

use WpAssetCleanUp\Admin\MiscAdmin;
use WpAssetCleanUp\Admin\PluginsManagerAdmin;
use WpAssetCleanUp\FrontendManagerFrame;
use WpAssetCleanUp\Main;
use WpAssetCleanUp\OwnAssets;
use WpAssetCleanUp\Settings;

/**
 * Presents the existing read-only Lite preview inside the front-end manager.
 */
class FrontendPluginsPreview
{
    /**
     * @param array $workspaces
     * @return array
     */
    public static function workspaces($workspaces)
    {
        if (!is_404() && !empty(Main::instance()->settings['frontend_show'])
            && Main::canShowFrontendManager() && PluginsManagerAdmin::currentUserCanViewManager()) {
            $workspaces['plugins'] = __('Plugins Manager', 'wp-asset-clean-up') . ' · ' . __('Preview', 'wp-asset-clean-up');
        }
        return $workspaces;
    }

    /**
     * Mark the Lite Plugins Manager preview without changing other workspace icons.
     *
     * @param string $icon
     * @param string $workspace
     *
     * @return string
     */
    public static function workspaceIcon($icon, $workspace)
    {
        return $workspace === 'plugins' ? 'lock' : $icon;
    }

    /**
     * @return void
     */
    public static function preventCaching()
    {
        if (FrontendManagerFrame::activeManager() === 'plugins') {
            nocache_headers();
        }
    }

    /**
     * @return void
     */
    public static function render()
    {
        if (FrontendManagerFrame::activeManager() !== 'plugins') {
            return;
        }
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;

        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $allRules   = PluginsManagerAdmin::getAllRules(true);
        $context    = FrontendPluginsPreviewData::context();
        $settings   = (new Settings())->getAll();
        $globalData = wpacuJsonDecodeToArray(get_option(WPACU_PLUGIN_ID . '_global_data', '{}'));
        $data       = array(
            'wpacu_sub_page'        => 'manage_plugins_front',
            'active_plugins'       => PluginsManagerAdmin::getActivePlugins(),
            'plugins_icons'        => MiscAdmin::getAllActivePluginsIcons(),
            'plugins_preview_rules'=> isset($allRules['plugins']) ? $allRules['plugins'] : array(),
            'plugins_list_layout'  => isset($settings['plugins_manager_page_list_layout']) && $settings['plugins_manager_page_list_layout'] === 'by-unload-rules' ? 'by-unload-rules' : 'all',
            'plugins_contracted_list' => isset($globalData['plugin_row_contracted']['front']) && is_array($globalData['plugin_row_contracted']['front']) ? $globalData['plugin_row_contracted']['front'] : array()
        );

        if (is_singular()) {
            $data['post_type'] = get_post_type();
            $data              = \WpAssetCleanUp\Admin\MainAdmin::instance()->setPageTemplate($data);
        }

        ob_start();
        echo '<div id="wpacu_wrap_assets">';
        include WPACU_PLUGIN_DIR . '/templates/_admin-page-plugins-manager/_front-context-preview.php';
        echo '</div>';
        $html = ob_get_clean();

        $debug     = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) || isset($_GET['wpacu_debug']);
        $extension = $debug ? '.css' : '.min.css';
        $styles    = array(
            self::assetUrl('/lite/assets/pro-preview.css'),
            self::assetUrl('/lite/assets/plugins-manager-preview/by-page' . $extension)
        );
        echo FrontendManagerFrame::render($html, 'plugins', array(
            'styles'  => $styles,
            'scripts' => array(
                WPACU_PLUGIN_ID . '-lite-pro-preview-alert' => self::assetUrl('/lite/assets/pro-preview-alert' . ($debug ? '.js' : '.min.js')),
                WPACU_PLUGIN_ID . '-lite-plugins-by-page-preview' => self::assetUrl('/lite/assets/plugins-manager-preview/by-page' . ($debug ? '.js' : '.min.js'))
            )
        ));
    }

    /**
     * @param string $path
     * @return string
     */
    private static function assetUrl($path)
    {
        return add_query_arg('wpacuversion', OwnAssets::assetVer($path), plugins_url($path, WPACU_PLUGIN_FILE));
    }
}
