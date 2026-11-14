<?php
// Shared by the CSS/JS and Plugins managers. Values are supplied by the caller.
if (!isset($wpacuPageSummary)) { exit; }
if (!empty($wpacuPageSummary['frontend'])) {
    include __DIR__ . '/page-summary-frontend.php';
    return;
}
$wpacuSummaryType = !empty($wpacuPageSummary['post_type']) ? $wpacuPageSummary['post_type'] : '';
$wpacuSummaryIcon = $wpacuSummaryType === 'page' ? 'page' : ($wpacuSummaryType === 'attachment' ? 'media' : 'post');
?>
<div class="wpacu-page-summary" style="box-sizing: border-box; <?php if (!empty($wpacuPageSummary['inline'])) { ?>margin: 0; padding: 0;<?php } else { ?>margin: 0 0 14px; padding: 10px; border-left: 1px solid #ddd; background: #f5f5f5;<?php } ?> color: #3c434a;">
    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px 20px;">
        <?php if (!empty($wpacuPageSummary['url'])) { ?>
        <div class="wpacu-page-summary-url" style="flex: <?php echo !empty($wpacuPageSummary['compact']) ? '0 1 auto' : '1 1 300px'; ?>; min-width: 0; overflow-wrap: anywhere;">
            <strong><?php esc_html_e('Page URL:', 'wp-asset-clean-up'); ?></strong>
            <a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url($wpacuPageSummary['url']); ?>"
               title="<?php echo esc_attr($wpacuPageSummary['title']); ?>"
               aria-label="<?php echo esc_attr(sprintf(__('%s (opens in a new tab)', 'wp-asset-clean-up'), $wpacuPageSummary['url'])); ?>"
               style="color: #0073aa; font-weight: 600;"><?php echo esc_html($wpacuPageSummary['url']); ?></a>
        </div>
        <?php } elseif (!empty($wpacuPageSummary['title'])) { ?>
        <div style="flex: <?php echo !empty($wpacuPageSummary['compact']) ? '0 1 auto' : '1 1 300px'; ?>; min-width: 0;"><strong><?php echo esc_html($wpacuPageSummary['title']); ?></strong></div>
        <?php } ?>
        <?php if ($wpacuSummaryType) { ?>
        <div class="wpacu-page-summary-type" style="display: flex; flex: 0 0 auto; align-items: center; gap: 5px;<?php if (empty($wpacuPageSummary['compact']) && (!empty($wpacuPageSummary['url']) || !empty($wpacuPageSummary['title']))) { ?> margin-left: auto;<?php } ?>">
            <?php if (!empty($wpacuPageSummary['post_id'])) { ?>
                <span style="padding-right: 10px; margin-right: 5px; border-right: 1px solid #ddd;"><?php esc_html_e('Post ID:', 'wp-asset-clean-up'); ?> <?php echo (int)$wpacuPageSummary['post_id']; ?></span>
            <?php } ?>
            <?php if (!empty($wpacuPageSummary['woocommerce'])) { ?>
                <img src="<?php echo esc_url(WPACU_PLUGIN_URL.'/assets/icons/woocommerce-icon-logo.svg'); ?>" alt="WooCommerce" style="width: 20px; height: 20px;" />
            <?php } else { ?>
                <span class="dashicons dashicons-admin-<?php echo esc_attr($wpacuSummaryIcon); ?>" aria-hidden="true" style="color: #0f6cab;"></span>
            <?php } ?>
            <span><u><?php echo esc_html($wpacuSummaryType); ?></u> <?php echo $wpacuSummaryType === 'post' ? esc_html__('type.', 'wp-asset-clean-up') : esc_html__('post type.', 'wp-asset-clean-up'); ?></span>
        </div>
        <?php } ?>
    </div>
    <?php if (!empty($wpacuPageSummary['redirected_url'])) { ?>
        <div class="wpacu-redirected-fetch-url-reminder" style="margin-top: 10px; overflow-wrap: anywhere;">
            <strong><span class="dashicons dashicons-migrate" aria-hidden="true"></span> <?php esc_html_e('Redirected to:', 'wp-asset-clean-up'); ?></strong>
            <a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url($wpacuPageSummary['redirected_url']); ?>"><?php echo esc_html($wpacuPageSummary['redirected_url']); ?></a>
        </div>
    <?php } ?>
</div>
