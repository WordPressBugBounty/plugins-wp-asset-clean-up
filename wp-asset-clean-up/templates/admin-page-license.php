<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
    exit;
}

include_once WPACU_PLUGIN_DIR . '/templates/_top-area.php';
?>
<!-- [wpacu_lite] -->
<div class="wpacu-wrap">
    <p><?php echo sprintf(
            __('You\'re using the lite version of %s (v%s), so no license key is needed. You\'ll receive automatic notifications whenever a new version is available for download.', 'wp-asset-clean-up'),
            WPACU_PLUGIN_TITLE,
            WPACU_PLUGIN_VERSION
        );
    ?></p>
    <p><em><?php echo sprintf(
            __('To unlock all features and get premium support, you can %supgrade to the Pro version%s.', 'wp-asset-clean-up'),
            '<a href="'.apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL.'?utm_source=plugin_license').'">', '</a>'
        );
    ?></em></p>

    <div class="wrap-upgrade-info">
        <p><span class="dashicons dashicons-info"></span> <?php echo sprintf(
                __('If you already purchased the Pro version and you don\'t know how to activate it, %sfollow the steps from the "Help" section%s.', 'wp-asset-clean-up'),
                '<a href="'.esc_url(admin_url('admin.php?page=wpassetcleanup_get_help')).'">', '</a>'
            );
        ?></p>
        <div class="wpacu_clearfix"></div>
    </div>
</div>
<!-- [/wpacu_lite] -->
