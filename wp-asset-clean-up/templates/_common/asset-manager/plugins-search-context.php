<?php
if (!isset($context['archive_type']) || $context['archive_type'] !== 'search') { return; }
?>
<p class="wpacu-pm-search-context"><strong><span style="color: #0f6cab;" class="dashicons dashicons-search" aria-hidden="true"></span>
    <?php esc_html_e('This is a default WordPress Search page.', 'wp-asset-clean-up'); ?>
    <?php echo defined('WPACU_PRO_PLUGIN_VERSION')
        ? esc_html__('Any changes made here will be applied to any search request made on this page.', 'wp-asset-clean-up')
        : esc_html__('In Pro, rules for this search page apply to any search request made on this page.', 'wp-asset-clean-up'); ?>
</strong></p>
