<?php
if (! defined('ABSPATH')) {
    exit;
}

if (defined('WPACU_PRO_PLUGIN_VERSION') && ! empty($assetLocationChanged)) {
    $data['row']['at_least_one_rule_set'] = true;
    ?>
    <div style="display: inline-block; color: #004567; font-style: italic; font-size: 90%; font-weight: 600; margin-left: 15px;">
        <span class="dashicons dashicons-info" style="font-size: 19px; line-height: normal;"></span> <?php esc_html_e('This file has its initial location changed.', 'wp-asset-clean-up'); ?>
    </div>
    <?php
}
