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
            <a class="button button-primary wpacu-lite-pro-preview-cta"
               target="_blank"
               rel="noopener noreferrer"
               href="<?php echo esc_url(self::getUpgradeUrl($context)); ?>">
                <?php esc_html_e('Get Asset CleanUp Pro', 'wp-asset-clean-up'); ?>
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
     * Load the lightweight preview layer only on Asset CleanUp admin pages.
     *
     * @return void
     */
    public static function enqueueAssets()
    {
        if ( ! Menu::isPluginPage() ) {
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
        // CSS/JS Manager archive contexts are previews in Lite.
        if (isset($_POST['wpacu_manage_archive_page_assets'])) {
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
