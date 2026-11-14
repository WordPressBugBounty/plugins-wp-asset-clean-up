<?php
/*
 * No direct access to this file
 */
if ( ! isset($data) ) {
	exit;
}

$wpacuSubPage = isset($_GET['wpacu_sub_page']) && is_string($_GET['wpacu_sub_page'])
    ? sanitize_key(wp_unslash($_GET['wpacu_sub_page']))
    : 'manage_css_js';

if ( ! in_array($wpacuSubPage, array('manage_css_js', 'manage_alternative_assets', 'manage_critical_css'), true) ) {
    $wpacuSubPage = 'manage_css_js';
}

$wpacuCanManageAlternatives = \WpAssetCleanUp\Menu::userCanManageAlternativeAssets();
if ($wpacuSubPage === 'manage_alternative_assets' && ! \WpAssetCleanUp\Menu::userCanAccessPlugin()) {
    wp_die(esc_html__('You are not allowed to manage Alternative Assets.', 'wp-asset-clean-up'), '', array('response' => 403));
}

include_once __DIR__ . '/_top-area.php';
?>
<div class="wpacu-wrap" style="margin: -12px 0 0;">
    <div class="wpacu-sub-page-tabs-wrap wpacu-css-js-tabs-toolbar" data-wpacu-master-toolbar> <!-- Sub-tabs wrap -->
        <!-- Sub-nav menu -->
        <label class="wpacu-sub-page-nav-label <?php if ($wpacuSubPage === 'manage_css_js') { ?>wpacu-selected<?php } ?>">
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_css_js')); ?>">
                <?php echo esc_attr__('MANAGE CSS/JS', 'wp-asset-clean-up'); ?>
            </a>
        </label>
        <label class="wpacu-sub-page-nav-label <?php if ($wpacuSubPage === 'manage_critical_css') { ?>wpacu-selected<?php } ?>">
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_critical_css')); ?>">
                <?php echo esc_attr__('MANAGE CRITICAL CSS', 'wp-asset-clean-up'); ?>
            </a>
        </label>
        <label class="wpacu-sub-page-nav-label <?php if ($wpacuSubPage === 'manage_alternative_assets') { ?>wpacu-selected<?php } ?>">
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_alternative_assets')); ?>">
                <?php echo esc_attr__('ALTERNATIVE ASSETS', 'wp-asset-clean-up'); ?>
                <?php $wpacuActiveAlternatives = $wpacuCanManageAlternatives ? \WpAssetCleanUp\OptimiseAssets\AlternativeAssets::getActiveCount() : 0; ?>
                <span id="wpacu-alternative-assets-active-count" data-active-count="<?php echo (int)$wpacuActiveAlternatives; ?>" data-thousands-separator="<?php echo esc_attr($GLOBALS['wp_locale']->number_format['thousands_sep']); ?>" aria-live="polite"<?php if ($wpacuActiveAlternatives === 0) { ?> hidden<?php } ?>><?php
                    if ($wpacuActiveAlternatives > 0) {
                        echo '(' . esc_html(number_format_i18n($wpacuActiveAlternatives)) . ')';
                    }
                ?></span>
            </a>
        </label>
        <!-- /Sub-nav menu -->
        <?php if ($wpacuSubPage === 'manage_css_js') {
            require __DIR__ . '/_common/asset-manager/global-status.php';
        } ?>
    </div> <!-- /Sub-tabs wrap -->

    <?php
    if ($wpacuSubPage === 'manage_css_js') {
       include_once __DIR__ . '/admin-page-assets-manager-manage-css-js.php';
    }

    if ($wpacuSubPage === 'manage_alternative_assets') {
        include_once __DIR__ . '/admin-page-assets-manager-alternative-assets.php';
    }

    if ($wpacuSubPage === 'manage_critical_css') {
        include_once __DIR__ . '/admin-page-assets-manager-critical-css.php';
    }
    ?>
</div>
