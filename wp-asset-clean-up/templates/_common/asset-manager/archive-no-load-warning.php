<?php
if (! defined('ABSPATH')) {
    exit;
}

$wpacuArchiveNoLoadMessage = defined('WPACU_PRO_PLUGIN_VERSION')
    ? __('This page\'s URI is matched by one of the rules that prevents %s from loading, thus no CSS/JS are available to manage. Remove the matching rule and reload this page to open the CSS/JS manager.', 'wp-asset-clean-up')
    : __('This page\'s URI is matched by one of the rules that prevents %s from loading, thus no CSS/JS are available to preview. Remove the matching rule and reload this page to inspect its assets.', 'wp-asset-clean-up');
?>
<p class="wpacu-warning" style="margin: 15px 0 0; padding: 10px; font-size: inherit; width: 99%;">
    <span style="color: red;" class="dashicons dashicons-info"></span>
    <?php echo sprintf(esc_html($wpacuArchiveNoLoadMessage), esc_html(WPACU_PLUGIN_TITLE)); ?>
</p>
<?php unset($wpacuArchiveNoLoadMessage); ?>
