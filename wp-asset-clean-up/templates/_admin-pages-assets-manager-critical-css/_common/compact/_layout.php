<?php

use WpAssetCleanUp\Admin\CriticalCssAdmin;
use WpAssetCleanUp\AssetsManager;

/*
 * No direct access to this file
 */
if ( ! isset($data, $criticalCssConfig, $locationKey) ) {
    exit;
}

$canManageCriticalCss = AssetsManager::instance()->currentUserCanViewAssetsList();

if (in_array($data['for'], array('posts', 'pages', 'custom_post_types'), true)) {
    ?>
    <div style="background: white; border: 1px solid #cdcdcd; padding: 10px; margin: 0 0 10px;"><p style="margin: 0;"><strong>Note:</strong> The changes in the "General" sub-tab apply to page groups such as posts, pages or a custom post type. If a landing page or another individual entry needs different Critical CSS, use the "Specific" sub-tab.</p></div>
    <?php
}

if ($canManageCriticalCss) {
    require WPACU_PLUGIN_DIR . '/templates/_common/critical-css/global-status.php';
}
?>

<div class="wpacu_clearfix"></div>

<?php
if ( ! $canManageCriticalCss ) {
    ?>
    <div class="wpacu-error" style="padding: 10px;">
        <?php echo sprintf(__('Only the administrators listed here can manage the critical CSS: %s"Settings" &#10141; "Plugin Usage Preferences" &#10141; "Allow managing assets to:"%s. If you believe you should have access to this page, you can add yourself to that list.', 'wp-asset-clean-up'), '<a target="_blank" href="'.esc_url(admin_url('admin.php?page=wpassetcleanup_settings&wpacu_selected_tab_area=wpacu-setting-plugin-usage-settings')).'">', '</a>'); ?>
    </div>
    <?php
}

if ($canManageCriticalCss && isset($data['for']) && $data['for']) {
    ?>
    <div id="wpacu-critical-css-main-tab-area">
    <?php
    include __DIR__ . '/_context-selector.php';

    if ($locationKey === 'via_code') {
        include dirname(dirname(__DIR__)) . '/_via-code.php';
    } else {
        if (in_array($data['for'], array('custom_post_types', 'custom_post_type_archives'), true)) {
            include __DIR__ . '/_post-type-view-tabs.php';
            include __DIR__ . '/_subtype-selector.php';
        }

        $supportsGranular = $locationKey && CriticalCssAdmin::supportsGranularManagement($data['for']);
        $isSpecificScope  = $supportsGranular
            && isset($data['critical_css_scope'])
            && $data['critical_css_scope'] === 'specific';

        include __DIR__ . '/_granular-selector.php';

        // Show notices when Critical CSS is updated, including on the Specific rule-list screen.
        do_action('wpacu_admin_notices');

        if ( ! $locationKey ) {
            include __DIR__ . '/_applies-to.php';
        } elseif ( ! empty($data['critical_css_show_editor']) ) {
            if ($isSpecificScope) {
                ?>
                <div class="wpacu-critical-css-specific-editor">
                <?php
            } else {
                ?>
                <div style="margin: 10px 0 0;" class="wpacu_clearfix"></div>
                <?php
                include __DIR__ . '/_applies-to.php';
            }
            ?>

            <form id="wpacu-critical-css-form" method="post" action="">
                <?php include __DIR__ . '/_settings.php'; ?>

                <?php if ($data['show_critical_css_options']) { ?>
                    <div id="wpacu-update-critical-css-button-area" class="wpacu-critical-css-editor-actions">
                        <?php
                        $storedRuleExists = isset($storedData['content_original'])
                                            && is_string($storedData['content_original'])
                                            && trim($storedData['content_original']) !== '';

                        $isAddMode = ! $storedRuleExists;

                        $submitButtonLabel = $isAddMode
                            ? __('ADD', 'wp-asset-clean-up')
                            : __('UPDATE', 'wp-asset-clean-up');
                        ?>
                        <button type="submit"
                                name="submit"
                                class="button button-primary"><?php
                            if ($isAddMode) {
                                echo '<span style="line-height: 1.9;" class="dashicons dashicons-insert"></span>&nbsp;';
                            } else {
                                echo '<span style="line-height: 1.9;" class="dashicons dashicons-update"></span>&nbsp;';
                            }

                            echo esc_html($submitButtonLabel);
                            ?></button>

                        <div id="wpacu-updating-critical-css" class="wpacu-hide">
                            <img src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" align="top" width="20" height="20" alt="" />
                        </div>
                        <?php wp_nonce_field('wpacu_critical_css_update', 'wpacu_critical_css_nonce'); ?>
                        <input type="hidden" name="wpacu_critical_css_submit" value="1" />
                    </div>
                <?php } ?>
            </form>
            <?php

            if ($isSpecificScope) {
                ?>
                </div>
                </div>
                <?php
            }
        }

        if ($isSpecificScope) {
            include __DIR__ . '/_specific-rules-list.php';
        }
    }
    ?>
    </div>
    <script>
    var wpacuStopSpinner = wpacuShowAreaSpinner(
        '#wpacu-critical-css-main-tab-area',
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
    </script>
    <?php
}
