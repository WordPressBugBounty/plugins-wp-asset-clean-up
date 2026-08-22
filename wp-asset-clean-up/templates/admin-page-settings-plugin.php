<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\MiscAdmin;
use WpAssetCleanUp\Settings;

if (! isset($data)) {
    exit;
}

include_once __DIR__ . '/_top-area.php';

do_action('wpacu_admin_notices');

$wikiStatus = ($data['wiki_read'] == 1)
    ? '<span class="wpacu-status-badge wpacu-is-reviewed" id="wpacuSidebarReviewBadge">Reviewed</span>'
	: '<span class="wpacu-status-badge" id="wpacuSidebarReviewBadge">Review</span>';

$selectedTabArea = $selectedSubTabArea = '';
$allSettingsSubTabs = array();

$settingsTabs = array(
    'wpacu-setting-before-you-start'      => esc_html__( 'Before you start', 'wp-asset-clean-up' ) . '&nbsp;&nbsp;' . $wikiStatus,
    'wpacu-setting-plugin-usage-settings' => esc_html__( 'Plugin Usage Preferences', 'wp-asset-clean-up' ),
    'wpacu-setting-test-mode'             => esc_html__( 'Test Mode', 'wp-asset-clean-up' ),
    'wpacu-setting-optimize-css'          => esc_html__( 'Optimize CSS', 'wp-asset-clean-up' ),
    'wpacu-setting-optimize-js'           => esc_html__( 'Optimize JavaScript', 'wp-asset-clean-up' ),
    'wpacu-setting-resource-loading'      => esc_html__( 'Resource Loading', 'wp-asset-clean-up' ),
    'wpacu-setting-cdn-rewrite-urls'      => esc_html__( 'CDN: Rewrite Cache File URLs', 'wp-asset-clean-up' ),
    'wpacu-setting-common-files-unload'   => esc_html__( 'Common Site-Wide Unloads', 'wp-asset-clean-up' ),
    'wpacu-setting-html-source-cleanup'   => esc_html__( 'HTML Source Cleanup', 'wp-asset-clean-up' ),
    'wpacu-setting-local-fonts'           => esc_html__( 'Local Fonts', 'wp-asset-clean-up' ),
    'wpacu-setting-google-fonts'          => esc_html__( 'Google Fonts', 'wp-asset-clean-up' ),
    'wpacu-setting-disable-rss-feed'      => esc_html__( 'Disable RSS Feed', 'wp-asset-clean-up' ),
    'wpacu-setting-disable-xml-rpc'       => esc_html__( 'Disable XML-RPC', 'wp-asset-clean-up' )
);

$settingsSubTabsConfig     = \WpAssetCleanUp\Admin\SettingsAdmin::getSubTabsConfig('all');
$settingsSubTabsConfigList = $settingsSubTabsConfig['sub_tabs'];

$settingsSubTabs = array();

foreach ($settingsSubTabsConfigList as $settingTabKey => $settingsTabValue) {
    foreach (array_keys($settingsTabValue) as $settingSubTabKey) {
        $settingsSubTabs[$settingTabKey][] = $settingSubTabKey;
    }
}

$settingsTabActive = 'wpacu-setting-plugin-usage-settings';

// Is 'Stripping the "fat"' marked as read? Mark the "General & Files Management" as the default tab
$defaultTabArea = ($data['wiki_read'] == 1) ? 'wpacu-setting-plugin-usage-settings' : 'wpacu-setting-before-you-start';
$defaultSubTabArea = 'wpacu-plugin-usage-settings-assets-management';

$selectedTabArea = isset($_REQUEST['wpacu_selected_tab_area'])
                   && array_key_exists($_REQUEST['wpacu_selected_tab_area'], $settingsTabs)
        ? $_REQUEST['wpacu_selected_tab_area']
        : $defaultTabArea;

if ($selectedTabArea === 'wpacu-setting-strip-the-fat') { // fallback
    $selectedTabArea = 'wpacu-setting-before-you-start';
}

$settingsTabActive = $selectedTabArea;

$defaultSubTabArea = isset($settingsSubTabs[$selectedTabArea])
        ? current($settingsSubTabs[$selectedTabArea])
        : '';

$selectedSubTabArea = isset($settingsSubTabsConfig['sub_tab_selected'])
        ? $settingsSubTabsConfig['sub_tab_selected']
        : $defaultSubTabArea;

$inputStyle = Settings::getInputStyle($data);

?>
<div class="wpacu-wrap wpacu-settings-area <?php echo esc_attr(Settings::getInputStyleCssClasses($inputStyle)); ?>"
     data-wpacu-input-style="<?php echo esc_attr($inputStyle); ?>">
    <form method="post" action="" id="wpacu-settings-form">
        <input type="hidden" name="wpacu_settings_page" value="1" />

        <div id="wpacu-settings-vertical-tab-wrap">
            <div class="wpacu-settings-tab">
                <?php
                $wpacuOptionOn  = '<span class="wpacu-circle-status wpacu-on"></span>';
                $wpacuOptionOff = '<span class="wpacu-circle-status wpacu-off"></span>';

                foreach ($settingsTabs as $settingsTabKey => $settingsTabText) {
                    $wpacuActiveTab  = ($settingsTabActive === $settingsTabKey) ? 'active' : '';
                    $wpacuNavTextSub = '';

                    if ($settingsTabKey === 'wpacu-setting-test-mode') {
                        $testModeStatus = ($data['test_mode'] == 1) ? $wpacuOptionOn : $wpacuOptionOff;
                        $wpacuNavTextSub = '<div class="wpacu-tab-extra-text" style="display: inline-block; margin-left: 8px;"><small><span class="wpacu-status-wrap" data-linked-to="wpacu_test_mode_enable">'.$testModeStatus.'</span></small></div>';
                    }

                    if ($settingsTabKey === 'wpacu-setting-disable-rss-feed') {
                        $rssSettingsChanged = ! empty($data['disable_rss_feed'])
                            || ! empty($data['remove_main_feed_link'])
                            || ! empty($data['remove_comment_feed_link']);
                        $rssFeedIndicatorVisibleClass = $rssSettingsChanged ? ' is-visible' : '';
                        $rssFeedIndicatorPartialClass = empty($data['disable_rss_feed']) && $rssSettingsChanged ? ' wpacu-partial' : '';
                        $wpacuNavTextSub = '<div id="wpacu-disable-rss-feed-menu-indicator" class="wpacu-tab-extra-text wpacu-settings-changed-indicator' . esc_attr($rssFeedIndicatorVisibleClass) . '"><small><span class="wpacu-status-wrap"><span class="wpacu-circle-status wpacu-attention' . esc_attr($rssFeedIndicatorPartialClass) . '"></span></span></small></div>';
                    }

                    if ($settingsTabKey === 'wpacu-setting-disable-xml-rpc') {
                        $disableXmlRpcValue = isset($data['disable_xmlrpc']) ? (string) $data['disable_xmlrpc'] : 'keep_it_on';
                        $disableXmlRpcChanged = in_array($disableXmlRpcValue, array('disable_pingback', 'disable_all'), true);
                        $xmlRpcIndicatorVisibleClass = $disableXmlRpcChanged ? ' is-visible' : '';
                        $xmlRpcIndicatorPartialClass = ($disableXmlRpcValue === 'disable_pingback') ? ' wpacu-partial' : '';
                        $wpacuNavTextSub = '<div id="wpacu-disable-xml-rpc-menu-indicator" class="wpacu-tab-extra-text wpacu-settings-changed-indicator' . esc_attr($xmlRpcIndicatorVisibleClass) . '"><small><span class="wpacu-status-wrap"><span class="wpacu-circle-status wpacu-attention' . esc_attr($xmlRpcIndicatorPartialClass) . '"></span></span></small></div>';
                    }

                    if ($settingsTabKey === 'wpacu-setting-optimize-css') {
                        $cssMinifyStatus = ( ! empty($data['minify_loaded_css']) && empty($data['is_optimize_css_enabled_by_other_party']))
                            ? $wpacuOptionOn
                            : $wpacuOptionOff;

                        $cssCombineEnabled = isset($data['combine_loaded_css'])
                            && in_array($data['combine_loaded_css'], array('for_all', 1, '1'), true)
                            && empty($data['is_optimize_css_enabled_by_other_party']);

                        $cssAdvancedSettingsActive = ! empty($data['inline_css_files'])
                            || $cssCombineEnabled
                            || ! empty($data['cache_dynamic_loaded_css']);

                        $cssAdvancedStatus = $cssAdvancedSettingsActive
                            ? '<span class="wpacu-circle-status wpacu-advanced"></span>'
                            : $wpacuOptionOff;

                        $cssAdvancedActiveTitle   = esc_attr__('One or more advanced CSS settings are enabled', 'wp-asset-clean-up');
                        $cssAdvancedInactiveTitle = esc_attr__('No advanced CSS settings are enabled', 'wp-asset-clean-up');
                        $cssAdvancedCurrentTitle  = $cssAdvancedSettingsActive ? $cssAdvancedActiveTitle : $cssAdvancedInactiveTitle;

                        $wpacuNavTextSub = '<div class="wpacu-tab-extra-text"><small>'
                            . '<span class="wpacu-status-wrap" data-linked-to="wpacu_minify_css_enable">' . $cssMinifyStatus . ' ' . __('Minify', 'wp-asset-clean-up') . '</span>'
                            . ' &nbsp;&nbsp; '
                            . '<span id="wpacuOptimizeCssAdvancedMenuStatus" class="wpacu-status-wrap" data-active-title="' . $cssAdvancedActiveTitle . '" data-inactive-title="' . $cssAdvancedInactiveTitle . '" title="' . $cssAdvancedCurrentTitle . '">' . $cssAdvancedStatus . ' ' . __('Advanced', 'wp-asset-clean-up') . '</span>'
                            . '</small></div>';
                    }

                    if ($settingsTabKey === 'wpacu-setting-optimize-js') {
                        $jsMinifyStatus = ( ! empty($data['minify_loaded_js']) && empty($data['is_optimize_js_enabled_by_other_party']))
                            ? $wpacuOptionOn
                            : $wpacuOptionOff;

                        $jsCombineEnabled = isset($data['combine_loaded_js'])
                            && in_array($data['combine_loaded_js'], array('for_admin', 'for_all', 1, '1'), true)
                            && empty($data['is_optimize_js_enabled_by_other_party'])
                            && ! wpacuIsDefinedConstant('WPACU_WP_ROCKET_DELAY_JS_ENABLED');

                        $jsInlineFilesEnabled = ! empty($data['inline_js_files'])
                            && empty($data['is_optimize_js_enabled_by_other_party']);

                        $jsAdvancedSettingsActive = $jsCombineEnabled
                            || $jsInlineFilesEnabled
                            || ! empty($data['move_inline_jquery_after_src_tag'])
                            || ! empty($data['move_scripts_to_body'])
                            || ! empty($data['cache_dynamic_loaded_js']);

                        $jsAdvancedStatus = $jsAdvancedSettingsActive
                            ? '<span class="wpacu-circle-status wpacu-advanced"></span>'
                            : $wpacuOptionOff;

                        $jsAdvancedActiveTitle   = esc_attr__('One or more advanced JavaScript settings are enabled', 'wp-asset-clean-up');
                        $jsAdvancedInactiveTitle = esc_attr__('No advanced JavaScript settings are enabled', 'wp-asset-clean-up');
                        $jsAdvancedCurrentTitle  = $jsAdvancedSettingsActive ? $jsAdvancedActiveTitle : $jsAdvancedInactiveTitle;

                        $wpacuNavTextSub = '<div class="wpacu-tab-extra-text"><small>'
                            . '<span class="wpacu-status-wrap" data-linked-to="wpacu_minify_js_enable">' . $jsMinifyStatus . ' ' . __('Minify', 'wp-asset-clean-up') . '</span>'
                            . ' &nbsp;&nbsp; '
                            . '<span id="wpacuOptimizeJsAdvancedMenuStatus" class="wpacu-status-wrap" data-active-title="' . $jsAdvancedActiveTitle . '" data-inactive-title="' . $jsAdvancedInactiveTitle . '" title="' . $jsAdvancedCurrentTitle . '">' . $jsAdvancedStatus . ' ' . __('Advanced', 'wp-asset-clean-up') . '</span>'
                            . '</small></div>';

                        if ( ! empty($data['is_optimize_js_enabled_by_other_party']) || wpacuIsDefinedConstant('WPACU_WP_ROCKET_DELAY_JS_ENABLED') ) {
                            $wpacuNavTextSub .= '<div style="margin-top: 3px;"><small style="font-weight: lighter; color: grey;"><strong>Status:</strong> Partially locked, already enabled in other plugin(s)</small></div>';
                        }
                    }

                    if ($settingsTabKey === 'wpacu-setting-resource-loading') {
                        $resourceLoadingEnabled = ! empty($data['resource_loading']['_enabled']);
                        $resourceLoadingImageAttrEnabled = ! empty($data['resource_loading']['images']['attr']['_enabled']);
                        $resourceLoadingImageLazyLoadEnabled = ! empty($data['resource_loading']['images']['lazy_load']['_enabled']);
                        $resourceLoadingHasEnabledFeatures = $resourceLoadingImageAttrEnabled || $resourceLoadingImageLazyLoadEnabled;

                        if ($resourceLoadingEnabled && $resourceLoadingHasEnabledFeatures) {
                            $resourceLoadingState = 'active';
                            $resourceLoadingStateLabel = esc_html__('Active', 'wp-asset-clean-up');
                            $resourceLoadingStateDescription = esc_html__('At least one option is enabled', 'wp-asset-clean-up');
                        } elseif ($resourceLoadingEnabled) {
                            $resourceLoadingState = 'enabled-empty';
                            $resourceLoadingStateLabel = esc_html__('Enabled', 'wp-asset-clean-up');
                            $resourceLoadingStateDescription = esc_html__('No options active', 'wp-asset-clean-up');
                        } elseif ($resourceLoadingHasEnabledFeatures) {
                            $resourceLoadingState = 'paused';
                            $resourceLoadingStateLabel = esc_html__('Paused', 'wp-asset-clean-up');
                            $resourceLoadingStateDescription = esc_html__('Configured options are currently inactive', 'wp-asset-clean-up');
                        } else {
                            $resourceLoadingState = 'off';
                            $resourceLoadingStateLabel = '';
                            $resourceLoadingStateDescription = '';
                        }

                        $resourceLoadingImageAttrStatusClass = $resourceLoadingImageAttrEnabled
                            ? ($resourceLoadingEnabled ? 'wpacu-on' : 'wpacu-paused')
                            : 'wpacu-off';

                        $resourceLoadingImageLazyLoadStatusClass = $resourceLoadingImageLazyLoadEnabled
                            ? ($resourceLoadingEnabled ? 'wpacu-on' : 'wpacu-paused')
                            : 'wpacu-off';

                        $resourceLoadingBadgeHiddenStyle = ($resourceLoadingState === 'off') ? ' style="display: none;"' : '';
                        $resourceLoadingDescriptionHiddenStyle = ($resourceLoadingState === 'off') ? ' style="display: none;"' : '';

                        $wpacuNavTextSub = '&nbsp; &nbsp;<span id="wpacu-resource-loading-state-badge" class="wpacu-resource-loading-state-badge is-' . esc_attr($resourceLoadingState) . '" data-state="' . esc_attr($resourceLoadingState) . '"' . $resourceLoadingBadgeHiddenStyle . '>' . $resourceLoadingStateLabel . '</span>';

                        $wpacuNavTextSub .= '<div class="wpacu-tab-extra-text wpacu-resource-loading-state-row"' . $resourceLoadingDescriptionHiddenStyle . '><small>'
                            . '<span id="wpacu-resource-loading-state-description" class="wpacu-resource-loading-state-description">' . $resourceLoadingStateDescription . '</span>'
                            . '</small></div>';

                        $wpacuNavTextSub .= '<div class="wpacu-tab-extra-text" id="wpacu-resource-loading-vertical-tab-area"><small>'
                            . '<span class="wpacu-status-wrap wpacu-resource-loading-feature-status" data-linked-to="wpacu_resource_loading_images_attr_enabled" data-resource-loading-feature="image-attributes"><span class="wpacu-circle-status ' . esc_attr($resourceLoadingImageAttrStatusClass) . '"></span> ' . esc_html__('Image Attributes', 'wp-asset-clean-up') . '</span>'
                            . ' &nbsp;&nbsp; '
                            . '<span class="wpacu-status-wrap wpacu-resource-loading-feature-status" data-linked-to="wpacu_resource_loading_images_lazy_load_enabled" data-resource-loading-feature="lazy-load"><span class="wpacu-circle-status ' . esc_attr($resourceLoadingImageLazyLoadStatusClass) . '"></span> ' . esc_html__('Lazy Load', 'wp-asset-clean-up') . '</span>'
                            . '</small></div>';
                    }

                    if ($settingsTabKey === 'wpacu-setting-cdn-rewrite-urls') {
                        $cdnRewriteEnabled = ! empty($data['cdn_rewrite_enable']);
                        $cdnRewriteCssHostname = isset($data['cdn_rewrite_url_css']) ? trim((string) $data['cdn_rewrite_url_css']) : '';
                        $cdnRewriteJsHostname = isset($data['cdn_rewrite_url_js']) ? trim((string) $data['cdn_rewrite_url_js']) : '';
                        $cdnRewriteHostnameCount = ($cdnRewriteCssHostname !== '' ? 1 : 0)
                            + ($cdnRewriteJsHostname !== '' ? 1 : 0);
                        $cdnRewriteStatusClass = $cdnRewriteEnabled && $cdnRewriteHostnameCount === 2
                            ? 'wpacu-on'
                            : ($cdnRewriteEnabled && $cdnRewriteHostnameCount === 1 ? 'wpacu-on wpacu-partial' : 'wpacu-off');
                        $cdnRewriteStatus = '<span class="wpacu-circle-status ' . esc_attr($cdnRewriteStatusClass) . '"></span>';
                        $wpacuNavTextSub = '<div class="wpacu-tab-extra-text" style="display: inline-block; margin-left: 8px;"><small><span id="wpacu-cdn-rewrite-menu-status" class="wpacu-status-wrap" data-linked-to="wpacu_cdn_rewrite_enable">'.$cdnRewriteStatus.'</span></small></div>';
                        $wpacuNavTextSub .= '<div class="wpacu-tab-extra-text" style="display: inline-block;"><small style="color: gray;"><span>Generated CSS/JS only</span></small></div>';
                    }

                    if ($settingsTabKey === 'wpacu-setting-common-files-unload') {
                        $commonFilesUnloadChanged = ! empty($data['disable_emojis'])
                            || ! empty($data['disable_oembed'])
                            || ! empty($data['disable_comment_reply'])
                            || ! empty($data['disable_dashicons_for_guests'])
                            || ! empty($data['disable_wp_block_library'])
                            || ! empty($data['disable_jquery_migrate']);
                        $commonFilesUnloadVisibleClass = $commonFilesUnloadChanged ? ' is-visible' : '';
                        $wpacuNavTextSub .= '<div id="wpacu-common-files-unload-menu-indicator" class="wpacu-tab-extra-text wpacu-settings-changed-indicator' . esc_attr($commonFilesUnloadVisibleClass) . '"><small><span class="wpacu-status-wrap"><span class="wpacu-circle-status wpacu-attention"></span></span></small></div>';
                        $wpacuNavTextSub .= '<div style="margin-top: 3px;"><small style="font-weight: lighter;">' . esc_html__('WordPress core assets', 'wp-asset-clean-up') . '</small></div>';
                    }

                    if ($settingsTabKey === 'wpacu-setting-html-source-cleanup') {
                        $htmlSourceCleanupChanged = ! empty($data['remove_rsd_link'])
                            || ! empty($data['remove_rest_api_link'])
                            || ! empty($data['remove_shortlink'])
                            || ! empty($data['remove_wp_version'])
                            || ! empty($data['remove_generator_tag'])
                            || ! empty($data['remove_posts_rel_links'])
                            || ! empty($data['remove_wlw_link'])
                            || ! empty($data['remove_html_comments']);
                        $htmlSourceCleanupVisibleClass = $htmlSourceCleanupChanged ? ' is-visible' : '';
                        $wpacuNavTextSub .= '<div id="wpacu-html-source-cleanup-menu-indicator" class="wpacu-tab-extra-text wpacu-settings-changed-indicator' . esc_attr($htmlSourceCleanupVisibleClass) . '"><small><span class="wpacu-status-wrap"><span class="wpacu-circle-status wpacu-attention"></span></span></small></div>';
                        $wpacuNavTextSub .= '<div style="margin-top: 3px;"><small style="font-weight: lighter;">' . esc_html__('Metadata, discovery links, comments', 'wp-asset-clean-up') . '</small></div>';
                    }

                    if ($settingsTabKey === 'wpacu-setting-local-fonts') {
                        $wpacuNavTextSub .= '<div style="margin-top: 3px;"><small style="font-weight: lighter;">Font-Display, Preload</small></div>';
                    }

                    if ($settingsTabKey === 'wpacu-setting-google-fonts') {
                        $googleFontsRemovalIndicatorVisibleClass = ! empty($data['google_fonts_remove']) ? ' is-visible' : '';
                        $wpacuNavTextSub .= '<div id="wpacu-google-fonts-removal-menu-indicator" class="wpacu-tab-extra-text wpacu-settings-changed-indicator' . esc_attr($googleFontsRemovalIndicatorVisibleClass) . '"><small><span class="wpacu-status-wrap"><span class="wpacu-circle-status wpacu-attention"></span></span></small></div>';
                        $wpacuNavTextSub .= '<div style="margin-top: 3px;"><small style="font-weight: lighter;">Combine, Async Load, Font-Display, Preconnect, Preload, <span>Removal</span></small></div>';
                    }
                ?>
                    <a href="#<?php echo esc_attr($settingsTabKey); ?>"
                       class="wpacu-settings-tab-link <?php echo esc_attr($wpacuActiveTab); ?>"
                       data-wpacu-settings-tab-key="<?php echo esc_attr($settingsTabKey); ?>"><?php
                            echo MiscAdmin::stripIrrelevantHtmlTags($settingsTabText . $wpacuNavTextSub);
                        ?></a>
                <?php
                }
                ?>
            </div>

            <?php
            include_once __DIR__ . '/_admin-page-settings-plugin-areas/_before-you-start.php';
            include_once __DIR__ . '/_admin-page-settings-plugin-areas/_plugin-usage-settings.php';
            include_once __DIR__ . '/_admin-page-settings-plugin-areas/_test-mode.php';
            include_once __DIR__ . '/_admin-page-settings-plugin-areas/_optimize-css.php';
            include_once __DIR__ . '/_admin-page-settings-plugin-areas/_optimize-js.php';
            include_once __DIR__ . '/_admin-page-settings-plugin-areas/_resource-loading.php';
            include_once __DIR__ . '/_admin-page-settings-plugin-areas/_cdn-rewrite-urls.php';
            include_once __DIR__ . '/_admin-page-settings-plugin-areas/_common-files-unload.php';
            include_once __DIR__ . '/_admin-page-settings-plugin-areas/_html-source-cleanup.php';
            include_once __DIR__ . '/_admin-page-settings-plugin-areas/_fonts-local.php';
            include_once __DIR__ . '/_admin-page-settings-plugin-areas/_fonts-google.php';
            include_once __DIR__ . '/_admin-page-settings-plugin-areas/_disable-rss-feed.php';
            include_once __DIR__ . '/_admin-page-settings-plugin-areas/_disable-xml-rpc-protocol.php';
            ?>

            <div class="clearfix"></div>
        </div>

        <div id="wpacu-update-button-area" class="wpacu-settings-save-area">
			<?php
			wp_nonce_field('wpacu_settings_update', 'wpacu_settings_nonce');
			?>
            <div class="wpacu-settings-save-action">
			<?php
			submit_button(__('Save Changes', 'wp-asset-clean-up'));
			?>
                <div id="wpacu-updating-settings">
                    <img src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" align="top" width="20" height="20" alt="" />
                </div>
            </div>
            <div class="wpacu-settings-save-status" aria-live="polite">
                <span class="dashicons dashicons-update" aria-hidden="true"></span>
                <span><strong data-wpacu-settings-change-count>0</strong><small data-wpacu-settings-change-label><?php esc_html_e('No unsaved changes', 'wp-asset-clean-up'); ?></small></span>
            </div>
            <div class="wpacu-settings-save-reminder">
                <span class="dashicons dashicons-saved" aria-hidden="true"></span>
                <span><strong><?php esc_html_e("Don't forget to save changes", 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Your preferences are applied only after saving.', 'wp-asset-clean-up'); ?></small></span>
            </div>
        </div>
        <input type="hidden"
               name="wpacu_selected_tab_area"
               id="wpacu-selected-tab-area"
               value="<?php echo esc_attr($selectedTabArea); ?>" />
        <input type="hidden"
               name="wpacu_selected_sub_tab_area"
               id="wpacu-selected-sub-tab-area"
               value="<?php echo esc_attr($selectedSubTabArea); ?>" />
    </form>
</div>

<script type="text/javascript">
    <?php
    if ( ! empty($_POST) ) {
    ?>
        // Situations: After settings update (post mode), do not jump to URL's anchor
        if (location.hash) {
            setTimeout(function() {
                window.scrollTo(0, 0);
            }, 1);
        }
    <?php
    }
    ?>

    var wpacuStopSpinner = wpacuShowAreaSpinner(
        '#wpacu-settings-admin-sub-tabs-wrap',
        {
            position: 'center',
            edgePadding: 8
        }
    );

    document.addEventListener(
        'DOMContentLoaded',
        wpacuStopSpinner,
        {
            once: true
        }
    );

    document.addEventListener('DOMContentLoaded', function () {
        var rssFeedInput = document.getElementById('wpacu_disable_rss_feed');
        var mainFeedLinkInput = document.getElementById('wpacu_remove_main_feed_link');
        var commentFeedLinkInput = document.getElementById('wpacu_remove_comment_feed_link');
        var rssFeedIndicator = document.getElementById('wpacu-disable-rss-feed-menu-indicator');
        var rssFeedIndicatorCircle = rssFeedIndicator ? rssFeedIndicator.querySelector('.wpacu-circle-status') : null;
        var xmlRpcInputs = document.querySelectorAll('input[name$="[disable_xmlrpc]"]');
        var xmlRpcIndicator = document.getElementById('wpacu-disable-xml-rpc-menu-indicator');
        var xmlRpcIndicatorCircle = xmlRpcIndicator ? xmlRpcIndicator.querySelector('.wpacu-circle-status') : null;
        var googleFontsRemovalInput = document.getElementById('wpacu_google_fonts_remove');
        var googleFontsRemovalIndicator = document.getElementById('wpacu-google-fonts-removal-menu-indicator');
        var cdnRewriteInput = document.getElementById('wpacu_cdn_rewrite_enable');
        var cdnRewriteCssInput = document.getElementById('wpacu_cdn_rewrite_url_css');
        var cdnRewriteJsInput = document.getElementById('wpacu_cdn_rewrite_url_js');
        var cdnRewriteMenuStatus = document.getElementById('wpacu-cdn-rewrite-menu-status');
        var cdnRewriteMenuCircle = cdnRewriteMenuStatus ? cdnRewriteMenuStatus.querySelector('.wpacu-circle-status') : null;
        var commonFilesUnloadInputs = document.querySelectorAll('#wpacu-common-unloads-settings input[type="checkbox"]');
        var commonFilesUnloadIndicator = document.getElementById('wpacu-common-files-unload-menu-indicator');
        var htmlSourceCleanupInputs = document.querySelectorAll('#wpacu-html-source-cleanup-settings input[type="checkbox"], #wpacu-html-source-cleanup-settings input[type="hidden"][name*="[remove_"]');
        var htmlSourceCleanupIndicator = document.getElementById('wpacu-html-source-cleanup-menu-indicator');
        var menuVisibilityInputs = document.querySelectorAll('#wpacu-menu-visibility-settings input[type="checkbox"]');
        var menuVisibilityIndicator = document.getElementById('wpacu-menu-visibility-sub-tab-indicator');
        var accessControlArea = document.getElementById('wpacu-access-control-settings');
        var accessControlIndicator = document.getElementById('wpacu-access-control-sub-tab-indicator');

        function selectHasValue(select) {
            return Array.prototype.some.call(select.options, function (option) {
                return option.selected && option.value !== '';
            });
        }

        function accessControlHasAdditionalAccess() {
            if (! accessControlArea) {
                return false;
            }

            var roleSelect = accessControlArea.querySelector('[data-wpacu-access-role-select="1"]');
            var usersSelect = accessControlArea.querySelector('[data-wpacu-access-users-select="1"]');
            var enabledUserInputs = accessControlArea.querySelectorAll('[data-wpacu-non-admin-chosen-user-id] input[type="hidden"]:not(:disabled)');

            return (roleSelect && selectHasValue(roleSelect))
                || (usersSelect && selectHasValue(usersSelect))
                || enabledUserInputs.length > 0;
        }

        function updateChangedSettingIndicators() {
            if (rssFeedInput && rssFeedIndicator) {
                var rssSettingsChanged = rssFeedInput.checked
                    || (mainFeedLinkInput && mainFeedLinkInput.checked)
                    || (commentFeedLinkInput && commentFeedLinkInput.checked);

                rssFeedIndicator.classList.toggle('is-visible', Boolean(rssSettingsChanged));

                if (rssFeedIndicatorCircle) {
                    rssFeedIndicatorCircle.classList.toggle(
                        'wpacu-partial',
                        Boolean(! rssFeedInput.checked && rssSettingsChanged)
                    );
                }
            }

            if (xmlRpcInputs.length && xmlRpcIndicator) {
                var selectedXmlRpcInput = document.querySelector('input[name$="[disable_xmlrpc]"]:checked');
                var xmlRpcChanged = selectedXmlRpcInput && selectedXmlRpcInput.value !== 'keep_it_on';

                xmlRpcIndicator.classList.toggle('is-visible', Boolean(xmlRpcChanged));

                if (xmlRpcIndicatorCircle) {
                    xmlRpcIndicatorCircle.classList.toggle(
                        'wpacu-partial',
                        Boolean(selectedXmlRpcInput && selectedXmlRpcInput.value === 'disable_pingback')
                    );
                }
            }

            if (googleFontsRemovalInput && googleFontsRemovalIndicator) {
                googleFontsRemovalIndicator.classList.toggle('is-visible', googleFontsRemovalInput.checked);
            }

            if (cdnRewriteInput && cdnRewriteMenuCircle) {
                var cdnHostnameCount = (cdnRewriteCssInput && cdnRewriteCssInput.value.trim() !== '' ? 1 : 0)
                    + (cdnRewriteJsInput && cdnRewriteJsInput.value.trim() !== '' ? 1 : 0);
                var cdnFullyConfigured = cdnRewriteInput.checked && cdnHostnameCount === 2;
                var cdnPartiallyConfigured = cdnRewriteInput.checked && cdnHostnameCount === 1;

                cdnRewriteMenuCircle.classList.toggle('wpacu-on', cdnFullyConfigured || cdnPartiallyConfigured);
                cdnRewriteMenuCircle.classList.toggle('wpacu-partial', cdnPartiallyConfigured);
                cdnRewriteMenuCircle.classList.toggle('wpacu-off', ! cdnFullyConfigured && ! cdnPartiallyConfigured);
            }

            if (commonFilesUnloadInputs.length && commonFilesUnloadIndicator) {
                var commonFilesUnloadChanged = Array.prototype.some.call(commonFilesUnloadInputs, function (input) {
                    return input.checked;
                });

                commonFilesUnloadIndicator.classList.toggle('is-visible', commonFilesUnloadChanged);
            }

            if (htmlSourceCleanupInputs.length && htmlSourceCleanupIndicator) {
                var htmlSourceCleanupChanged = Array.prototype.some.call(htmlSourceCleanupInputs, function (input) {
                    return input.type === 'checkbox' ? input.checked : input.value === '1';
                });

                htmlSourceCleanupIndicator.classList.toggle('is-visible', htmlSourceCleanupChanged);
            }

            if (menuVisibilityInputs.length && menuVisibilityIndicator) {
                var menuVisibilityChanged = Array.prototype.some.call(menuVisibilityInputs, function (input) {
                    return input.checked;
                });

                menuVisibilityIndicator.classList.toggle('is-visible', menuVisibilityChanged);
            }

            if (accessControlArea && accessControlIndicator) {
                accessControlIndicator.classList.toggle('is-visible', accessControlHasAdditionalAccess());
            }
        }

        if (rssFeedInput) {
            rssFeedInput.addEventListener('change', updateChangedSettingIndicators);
        }

        if (mainFeedLinkInput) {
            mainFeedLinkInput.addEventListener('change', updateChangedSettingIndicators);
        }

        if (commentFeedLinkInput) {
            commentFeedLinkInput.addEventListener('change', updateChangedSettingIndicators);
        }

        Array.prototype.forEach.call(xmlRpcInputs, function (xmlRpcInput) {
            xmlRpcInput.addEventListener('change', updateChangedSettingIndicators);
        });

        if (googleFontsRemovalInput) {
            googleFontsRemovalInput.addEventListener('change', updateChangedSettingIndicators);
        }

        [cdnRewriteInput, cdnRewriteCssInput, cdnRewriteJsInput].forEach(function (cdnInput) {
            if (cdnInput) {
                cdnInput.addEventListener('input', updateChangedSettingIndicators);
                cdnInput.addEventListener('change', updateChangedSettingIndicators);
            }
        });

        Array.prototype.forEach.call(commonFilesUnloadInputs, function (commonFilesUnloadInput) {
            commonFilesUnloadInput.addEventListener('change', updateChangedSettingIndicators);
        });

        Array.prototype.forEach.call(htmlSourceCleanupInputs, function (htmlSourceCleanupInput) {
            if (htmlSourceCleanupInput.type === 'checkbox') {
                htmlSourceCleanupInput.addEventListener('change', updateChangedSettingIndicators);
            }
        });

        Array.prototype.forEach.call(menuVisibilityInputs, function (menuVisibilityInput) {
            menuVisibilityInput.addEventListener('change', updateChangedSettingIndicators);
        });

        if (accessControlArea) {
            accessControlArea.addEventListener('change', updateChangedSettingIndicators);

            if (typeof window.MutationObserver !== 'undefined') {
                var accessControlObserver = new window.MutationObserver(updateChangedSettingIndicators);

                accessControlObserver.observe(accessControlArea, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['disabled', 'selected']
                });
            }
        }

        updateChangedSettingIndicators();

        var beforeYouStartTab = document.querySelector(
            '.wpacu-settings-tab-link[data-wpacu-settings-tab-key="wpacu-setting-before-you-start"]'
        );

        var beforeYouStartArea = document.getElementById('wpacu-setting-before-you-start');

        if (! beforeYouStartTab || ! beforeYouStartArea) {
            return;
        }

        function updateAreaClass() {
            beforeYouStartArea.classList.toggle(
                'wpacu-area-shown',
                beforeYouStartTab.classList.contains('active')
            );
        }

        updateAreaClass();

        document.querySelectorAll('.wpacu-settings-tab-link').forEach(function (tab) {
            tab.addEventListener('click', function () {
                setTimeout(updateAreaClass, 0);
            });
        });
    });
</script>
<script>
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('wpacu-settings-form');
        var saveArea = document.getElementById('wpacu-update-button-area');

        if (! form || ! saveArea) {
            return;
        }

        var ignoredNames = [
            'wpacu_settings_page',
            'wpacu_selected_tab_area',
            'wpacu_selected_sub_tab_area',
            'wpacu_settings_nonce',
            '_wp_http_referer',
            'submit'
        ];

        function getControls() {
            return Array.prototype.slice.call(form.querySelectorAll('input[name], select[name], textarea[name]'))
                .filter(function (control) {
                    return ignoredNames.indexOf(control.name) === -1
                        && control.type !== 'submit'
                        && control.type !== 'button';
                });
        }

        function getNames(controls) {
            return controls.reduce(function (result, control) {
                if (result.indexOf(control.name) === -1) {
                    result.push(control.name);
                }
                return result;
            }, []);
        }

        function getValue(name, controls) {
            var values = controls.filter(function (control) {
                return control.name === name;
            }).reduce(function (result, control) {
                if ((control.type === 'checkbox' || control.type === 'radio') && ! control.checked) {
                    return result;
                }

                if (control.tagName === 'SELECT' && control.multiple) {
                    Array.prototype.forEach.call(control.options, function (option) {
                        if (option.selected) {
                            result.push(option.value);
                        }
                    });
                    return result;
                }

                result.push(control.value);
                return result;
            }, []);

            return JSON.stringify(values.sort());
        }

        var initialControls = getControls();
        var initialNames = getNames(initialControls);
        var initialState = initialNames.reduce(function (result, name) {
            result[name] = getValue(name, initialControls);
            return result;
        }, {});
        var updateTimer;

        function updateCount() {
            var controls = getControls();
            var names = getNames(controls);

            initialNames.forEach(function (name) {
                if (names.indexOf(name) === -1) {
                    names.push(name);
                }
            });

            var count = names.reduce(function (total, name) {
                var initialValue = Object.prototype.hasOwnProperty.call(initialState, name)
                    ? initialState[name]
                    : '[]';

                return total + (initialValue === getValue(name, controls) ? 0 : 1);
            }, 0);
            var countOutput = saveArea.querySelector('[data-wpacu-settings-change-count]');
            var labelOutput = saveArea.querySelector('[data-wpacu-settings-change-label]');

            if (countOutput) {
                countOutput.textContent = String(count);
            }

            if (labelOutput) {
                labelOutput.textContent = count === 0
                    ? <?php echo wp_json_encode(__('No unsaved changes', 'wp-asset-clean-up')); ?>
                    : (count === 1
                        ? <?php echo wp_json_encode(__('Unsaved change', 'wp-asset-clean-up')); ?>
                        : <?php echo wp_json_encode(__('Unsaved changes', 'wp-asset-clean-up')); ?>);
            }

            saveArea.classList.toggle('wpacu-settings-has-changes', count > 0);
        }

        function scheduleUpdate() {
            window.clearTimeout(updateTimer);
            updateTimer = window.setTimeout(updateCount, 20);
        }

        form.addEventListener('input', scheduleUpdate);
        form.addEventListener('change', scheduleUpdate);

        if (typeof window.MutationObserver !== 'undefined') {
            new window.MutationObserver(scheduleUpdate).observe(
                document.getElementById('wpacu-settings-vertical-tab-wrap'),
                { childList: true, subtree: true }
            );
        }

        updateCount();
    }, { once: true });
}());
</script>
