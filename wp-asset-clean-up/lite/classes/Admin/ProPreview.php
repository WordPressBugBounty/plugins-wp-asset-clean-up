<?php
namespace WpAssetCleanUpLite\Admin;

use WpAssetCleanUp\Menu;

/**
 * Shared presentation and hard safety boundaries for Pro previews shown in Lite.
 *
 * The preview markup deliberately lives in Lite templates. This class makes sure
 * the matching Pro-only write actions stay unavailable even if a request is
 * crafted manually instead of being submitted through the disabled interface.
 */
class ProPreview
{
    /**
     * @param string $context
     *
     * @return string
     */
    public static function getUpgradeUrl($context = 'feature_preview')
    {
        $context = sanitize_key($context);

        if ($context === '') {
            $context = 'feature_preview';
        }

        $url = WPACU_PLUGIN_GO_PRO_URL
             . '?utm_source=wpacu_lite_preview&utm_medium=' . rawurlencode($context);

        return apply_filters('wpacu_go_pro_affiliate_link', $url);
    }

    /**
     * @param string $title
     * @param string $message
     * @param string $context
     * @param bool   $compact
     *
     * @return void
     */
    public static function renderNotice($title, $message, $context = 'feature_preview', $compact = false)
    {
        $classes = 'wpacu-lite-pro-preview-notice';

        if ($compact) {
            $classes .= ' wpacu-lite-pro-preview-notice-compact';
        }

        $buttonTitle = __('Get Asset CleanUp Pro', 'wp-asset-clean-up');
        $buttonHint  = __('View pricing and choose the right plan', 'wp-asset-clean-up');
        if (strpos($context, 'css_js_manager_') === 0) {
            $discount = UpgradeNotices::getUpgradeDiscount();
            if ($discount > 0) {
                /* translators: %d is the current discount percentage. */
                $buttonHint = sprintf(__('Save %d%% during Black Friday', 'wp-asset-clean-up'), $discount);
            }
        } else {
            $buttonTitle = apply_filters('wpacu_internal_upgrade_label', $buttonTitle);
        }
        ?>
        <div class="<?php echo esc_attr($classes); ?>" data-wpacu-lite-pro-preview-notice="1">
            <div class="wpacu-lite-pro-preview-notice-icon" aria-hidden="true">
                <img src="<?php echo esc_url(WPACU_PLUGIN_URL . '/assets/icons/icon-lock.svg'); ?>"
                     width="22" height="22" alt="" />
            </div>
            <div class="wpacu-lite-pro-preview-notice-copy">
                <strong><?php echo esc_html($title); ?></strong>
                <span><?php echo esc_html($message); ?></span>
            </div>
            <a class="wpacu-lite-pm-upgrade-button wpacu-lite-pro-preview-cta"
               target="_blank"
               rel="noopener noreferrer"
               href="<?php echo esc_url(self::getUpgradeUrl($context)); ?>">
                <span class="dashicons dashicons-unlock" aria-hidden="true"></span>
                <span class="wpacu-lite-pm-upgrade-button-copy">
                    <strong><?php echo esc_html($buttonTitle); ?></strong>
                    <small><?php echo esc_html($buttonHint); ?></small>
                </span>
            </a>
        </div>
        <?php
    }

    /**
     * @param string $label
     *
     * @return void
     */
    public static function renderBadge($label = 'PRO')
    {
        ?><span class="wpacu-lite-pro-badge"><?php echo esc_html($label); ?></span><?php
    }

    /**
     * @param string $context
     * @return array
     */
    public static function getAlertSettings($context = 'dashboard_preview')
    {
        return array(
            'title'   => __('Available in Asset CleanUp Pro', 'wp-asset-clean-up'),
            'message' => __('This control is part of the Pro preview. Asset CleanUp Lite does not save or apply these rules. Upgrade to Pro to use this feature.', 'wp-asset-clean-up'),
            'confirm' => __('Back to Preview', 'wp-asset-clean-up'),
            'link'    => apply_filters('wpacu_internal_upgrade_label', __('Explore Pro Features', 'wp-asset-clean-up')),
            'url'     => esc_url_raw(add_query_arg(array(
                'utm_campaign' => 'asset_cleanup_pro',
                'utm_content'  => sanitize_key($context)
            ), self::getUpgradeUrl('locked_control')))
        );
    }

    /**
     * Load the preview layer on Asset CleanUp admin pages, with alerts only
     * where locked controls and their SweetAlert dependency are available.
     *
     * @return void
     */
    public static function enqueueAssets()
    {
        $pluginPage = Menu::isPluginPage();

        if ( ! $pluginPage ) {
            return;
        }

        $styleRelPath = '/lite/assets/pro-preview.css';
        $scriptRelPath = '/lite/assets/pro-preview.js';

        $stylePath = WPACU_PLUGIN_DIR . $styleRelPath;
        $scriptPath = WPACU_PLUGIN_DIR . $scriptRelPath;

        wp_enqueue_style(
            WPACU_PLUGIN_ID . '-lite-pro-preview',
            plugins_url($styleRelPath, WPACU_PLUGIN_FILE),
            array(WPACU_PLUGIN_ID . '-style'),
            is_file($stylePath) ? (string)filemtime($stylePath) : WPACU_PLUGIN_VERSION
        );

        wp_enqueue_script(
            WPACU_PLUGIN_ID . '-lite-pro-preview',
            plugins_url($scriptRelPath, WPACU_PLUGIN_FILE),
            array('jquery'),
            is_file($scriptPath) ? (string)filemtime($scriptPath) : WPACU_PLUGIN_VERSION,
            true
        );

        if (in_array($pluginPage, array('settings', 'assets_manager', 'plugins_manager'), true)) {
            $alertRelPath = '/lite/assets/pro-preview-alert.js';
            $alertHandle  = WPACU_PLUGIN_ID . '-lite-pro-preview-alert';
            wp_enqueue_script(
                $alertHandle,
                plugins_url($alertRelPath, WPACU_PLUGIN_FILE),
                array(WPACU_PLUGIN_ID . '-sweetalert2-js', WPACU_PLUGIN_ID . '-lite-pro-preview'),
                (string)filemtime(WPACU_PLUGIN_DIR . $alertRelPath),
                true
            );
            wp_localize_script($alertHandle, 'wpacuLiteProPreviewAlertSettings', self::getAlertSettings());
        }

        self::enqueuePluginsManagerPreviewStyles();
    }


    /**
     * Reuse the production Pro Plugins Manager style layer in both Lite
     * previews. Dashboard uses the shared Pro layer; front-end additionally
     * loads the Compact Grid layout layer.
     *
     * @return void
     */
    private static function enqueuePluginsManagerPreviewStyles()
    {
        $page = isset($_GET['page']) && ! is_array($_GET['page'])
            ? sanitize_key(wp_unslash($_GET['page']))
            : '';
        $subPage = isset($_GET['wpacu_sub_page']) && ! is_array($_GET['wpacu_sub_page'])
            ? sanitize_key(wp_unslash($_GET['wpacu_sub_page']))
            : 'manage_plugins_front';

        if (
            $page !== WPACU_PLUGIN_ID . '_plugins_manager'
            || ! in_array($subPage, array('manage_plugins_front', 'manage_plugins_dash'), true)
        ) {
            return;
        }

        $debug = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) || isset($_GET['wpacu_debug']);
        $extension = $debug ? '.css' : '.min.css';
        $assets = array(
            'common' => '/lite/assets/plugins-manager-preview/common' . $extension
        );

        if ($subPage === 'manage_plugins_front') {
            $assets['compact-grid'] = '/lite/assets/plugins-manager-preview/compact-grid' . $extension;
        }

        $assets['overrides'] = '/lite/assets/plugins-manager-preview/overrides' . $extension;
        $previousHandle = WPACU_PLUGIN_ID . '-lite-pro-preview';

        foreach ($assets as $assetKey => $assetRelPath) {
            $assetPath = WPACU_PLUGIN_DIR . $assetRelPath;
            $handle = WPACU_PLUGIN_ID . '-lite-plugins-manager-preview-' . $assetKey;

            wp_enqueue_style(
                $handle,
                plugins_url($assetRelPath, WPACU_PLUGIN_FILE),
                array($previousHandle),
                is_file($assetPath) ? (string)filemtime($assetPath) : WPACU_PLUGIN_VERSION
            );

            $previousHandle = $handle;
        }
    }

    /**
     * Never allow Pro-only writes from Lite, including handcrafted POST requests.
     *
     * @return void
     */
    public static function blockProOnlyWrites()
    {
        // Only validated built-in category/tag archives can be edited in Lite.
        if (isset($_POST['wpacu_manage_archive_page_assets']) && ! \WpAssetCleanUpLite\ArchiveRules::dashboardTerm()) {
            unset($_POST['wpacu_manage_archive_page_assets'], $_REQUEST['wpacu_manage_archive_page_assets']);
        }

        // Plugins Manager is a read-only product preview in Lite. Strip both
        // the normal submit marker and a handcrafted payload that omits it.
        if (isset($_POST['wpacu_plugins_manager_submit']) || isset($_POST['wpacu_plugins'])) {
            unset(
                $_POST['wpacu_plugins_manager_submit'],
                $_POST['wpacu_plugins'],
                $_REQUEST['wpacu_plugins_manager_submit'],
                $_REQUEST['wpacu_plugins']
            );
        }
    }
}
