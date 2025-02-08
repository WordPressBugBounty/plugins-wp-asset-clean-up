<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\MiscAdmin;

if (! isset($data)) {
    exit;
}

include_once __DIR__ . '/_top-area.php';

do_action('wpacu_admin_notices');

$wikiStatus = ($data['wiki_read'] == 1) ? '<small style="font-weight: 200; color: green;">* '.esc_html__('read', 'wp-asset-clean-up').'</small>'
	: '<small style="font-weight: 200; color: #cc0000;"><span class="dashicons dashicons-warning" style="width: 15px; height: 15px; margin: 2px 0 0 0; font-size: 16px;"></span> '.esc_html__('unread', 'wp-asset-clean-up').'</small>';

$selectedTabArea = $selectedSubTabArea = '';
$allSettingsSubTabs = array();

$settingsTabs = array(
    'wpacu-setting-strip-the-fat'         => esc_html__( 'Stripping the "fat"', 'wp-asset-clean-up' ) . ' ' . $wikiStatus,
    'wpacu-setting-plugin-usage-settings' => esc_html__( 'Plugin Usage Preferences', 'wp-asset-clean-up' ),
    'wpacu-setting-test-mode'             => esc_html__( 'Test Mode', 'wp-asset-clean-up' ),
    'wpacu-setting-optimize-css'          => esc_html__( 'Optimize CSS', 'wp-asset-clean-up' ),
    'wpacu-setting-optimize-js'           => esc_html__( 'Optimize JavaScript', 'wp-asset-clean-up' ),
    'wpacu-setting-cdn-rewrite-urls'      => esc_html__( 'CDN: Rewrite assets URLs', 'wp-asset-clean-up' ),
    'wpacu-setting-common-files-unload'   => esc_html__( 'Site-Wide Common Unloads', 'wp-asset-clean-up' ),
    'wpacu-setting-html-source-cleanup'   => esc_html__( 'HTML Source CleanUp', 'wp-asset-clean-up' ),
    'wpacu-setting-local-fonts'           => esc_html__( 'Local Fonts', 'wp-asset-clean-up' ),
    'wpacu-setting-google-fonts'          => esc_html__( 'Google Fonts', 'wp-asset-clean-up' ),
    'wpacu-setting-disable-rss-feed'      => esc_html__( 'Disable RSS Feed', 'wp-asset-clean-up' ),
    'wpacu-setting-disable-xml-rpc'       => esc_html__( 'Disable XML-RPC', 'wp-asset-clean-up' )
);

$settingsSubTabs = array(
    // Tab => Sub Tab
    'wpacu-setting-plugin-usage-settings' => array(
        'wpacu-plugin-usage-settings-assets-management',
        'wpacu-plugin-usage-settings-cache',
        'wpacu-plugin-usage-settings-accessibility',
        'wpacu-plugin-usage-settings-analytics',
        'wpacu-plugin-usage-settings-announcements',
        'wpacu-plugin-usage-settings-visibility',
        'wpacu-plugin-usage-settings-no-load-on-specific-pages',
        'wpacu-plugin-usage-settings-access',
    ),

    'wpacu-setting-google-fonts' => array(
        'wpacu-google-fonts-optimize',
        'wpacu-google-fonts-remove'
    )
);

$settingsTabActive = 'wpacu-setting-plugin-usage-settings';

// Is 'Stripping the "fat"' marked as read? Mark the "General & Files Management" as the default tab
$defaultTabArea = ($data['wiki_read'] == 1) ? 'wpacu-setting-plugin-usage-settings' : 'wpacu-setting-strip-the-fat';
$defaultSubTabArea = 'wpacu-plugin-usage-settings-assets-management';

$selectedTabArea = isset($_REQUEST['wpacu_selected_tab_area']) && array_key_exists($_REQUEST['wpacu_selected_tab_area'],
    $settingsTabs) // the tab id area has to be the one within the list above
    ? $_REQUEST['wpacu_selected_tab_area'] // after update
    : $defaultTabArea; // default

foreach ($settingsSubTabs as $mainTab => $subTabs) {
    $subTabsRef = $subTabs;

    if ($mainTab === $selectedTabArea) {
        $defaultSubTabArea = reset($subTabsRef);
    }

    foreach ($subTabs as $subTab) {
        $allSettingsSubTabs[] = $subTab;
    }
}

if ($selectedTabArea && array_key_exists($selectedTabArea, $settingsTabs)) {
    $settingsTabActive = $selectedTabArea;
}

$selectedSubTabArea = isset($_REQUEST['wpacu_selected_sub_tab_area']) && in_array($_REQUEST['wpacu_selected_sub_tab_area'], $allSettingsSubTabs) // the sub tab id area has to be the one within the list above
    ? $_REQUEST['wpacu_selected_sub_tab_area']
    : $defaultSubTabArea; // default
?>
<div class="wpacu-wrap wpacu-settings-area  <?php if ($data['input_style'] !== 'standard') { ?>wpacu-switch-enhanced<?php } else { ?>wpacu-switch-standard<?php } ?>">
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
                        $wpacuNavTextSub = '<div class="wpacu-tab-extra-text" style="display: inline-block; margin-left: 8px;"><small><span class="wpacu-status-wrap" data-linked-to="wpacu_enable_test_mode">'.$testModeStatus.'</span></small></div>';
                    }

                    if ($settingsTabKey === 'wpacu-setting-optimize-css') {
                        $cssMinifyStatus  = ($data['minify_loaded_css']  == 1 && empty($data['is_optimize_css_enabled_by_other_party'])) ? $wpacuOptionOn : $wpacuOptionOff;
                        $cssCombineStatus = ($data['combine_loaded_css'] == 1 && empty($data['is_optimize_css_enabled_by_other_party'])) ? $wpacuOptionOn : $wpacuOptionOff;
                        $wpacuNavTextSub  = '<div class="wpacu-tab-extra-text"><small><span class="wpacu-status-wrap" data-linked-to="wpacu_minify_css_enable">' . $cssMinifyStatus . ' '.__('Minify', 'wp-asset-clean-up').'</span> &nbsp;&nbsp; <span class="wpacu-status-wrap" data-linked-to="wpacu_combine_loaded_css_enable">' . $cssCombineStatus . ' '.__('Combine', 'wp-asset-clean-up').'</span>&nbsp; <span style="color: grey;">+ Defer, Inline</span></small></div>';

                        if ( ! empty($data['is_optimize_css_enabled_by_other_party']) || wpacuIsDefinedConstant('WPACU_WP_ROCKET_REMOVE_UNUSED_CSS_ENABLED') ) {
                            $wpacuNavTextSub .= '<div style="margin-top: 3px;"><small style="font-weight: lighter; color: grey;"><strong>Status:</strong> Partially locked, already enabled in other plugin(s)</small></div>';
                        }
                    }

                    if ($settingsTabKey === 'wpacu-setting-optimize-js') {
                        $jsMinifyStatus  = ($data['minify_loaded_js']  == 1 && empty($data['is_optimize_js_enabled_by_other_party'])) ? $wpacuOptionOn : $wpacuOptionOff;
                        $jsCombineStatus = ($data['combine_loaded_js'] == 1 && empty($data['is_optimize_js_enabled_by_other_party'])) ? $wpacuOptionOn : $wpacuOptionOff;
                        $wpacuNavTextSub = '<div class="wpacu-tab-extra-text"><small><span class="wpacu-status-wrap" data-linked-to="wpacu_minify_js_enable">' . $jsMinifyStatus . ' '.__('Minify', 'wp-asset-clean-up').'</span> &nbsp;&nbsp; <span class="wpacu-status-wrap" data-linked-to="wpacu_combine_loaded_js_enable">' . $jsCombineStatus . ' '.__('Combine', 'wp-asset-clean-up').'</span>&nbsp; <span style="color: grey;">+ Defer, Inline</span></small></div>';

                        if ( ! empty($data['is_optimize_js_enabled_by_other_party']) || wpacuIsDefinedConstant('WPACU_WP_ROCKET_DELAY_JS_ENABLED') ) {
                            $wpacuNavTextSub .= '<div style="margin-top: 3px;"><small style="font-weight: lighter; color: grey;"><strong>Status:</strong> Partially locked, already enabled in other plugin(s)</small></div>';
                        }
                    }

                    if ($settingsTabKey === 'wpacu-setting-cdn-rewrite-urls') {
                        $cdnRewriteStatus = ($data['cdn_rewrite_enable'] == 1) ? $wpacuOptionOn : $wpacuOptionOff;
                        $wpacuNavTextSub = '<div class="wpacu-tab-extra-text" style="display: inline-block; margin-left: 8px;"><small><span class="wpacu-status-wrap" data-linked-to="wpacu_cdn_rewrite_enable">'.$cdnRewriteStatus.'</span></small></div>';
                    }

                    if ($settingsTabKey === 'wpacu-setting-local-fonts') {
                        $wpacuNavTextSub .= '<div style="margin-top: 3px;"><small style="font-weight: lighter;">Font-Display, Preload</small></div>';
                    }

                    if ($settingsTabKey === 'wpacu-setting-google-fonts') {
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
            include_once __DIR__ . '/_admin-page-settings-plugin-areas/_strip-the-fat.php';
            include_once __DIR__ . '/_admin-page-settings-plugin-areas/_plugin-usage-settings.php';
            include_once __DIR__ . '/_admin-page-settings-plugin-areas/_test-mode.php';
            include_once __DIR__ . '/_admin-page-settings-plugin-areas/_optimize-css.php';
            include_once __DIR__ . '/_admin-page-settings-plugin-areas/_optimize-js.php';
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

        <div id="wpacu-update-button-area">
			<?php
			wp_nonce_field('wpacu_settings_update', 'wpacu_settings_nonce');
			submit_button(__('Update All Settings', 'wp-asset-clean-up'));
			?>
            <div id="wpacu-updating-settings">
                <img src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" align="top" width="20" height="20" alt="" />
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
</script>