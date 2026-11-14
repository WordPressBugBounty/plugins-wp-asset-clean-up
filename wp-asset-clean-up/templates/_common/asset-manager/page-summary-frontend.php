<?php
// The same responsive context row is used by both front-end manager workspaces.
if (!isset($wpacuPageSummary)) { exit; }
$wpacuSummaryType = !empty($wpacuPageSummary['post_type']) ? $wpacuPageSummary['post_type'] : '';
$wpacuSummaryIcon = $wpacuSummaryType === 'page' ? 'page' : ($wpacuSummaryType === 'attachment' ? 'media' : 'post');
$wpacuSummaryWoo  = !empty($wpacuPageSummary['woocommerce']) || $wpacuSummaryType === 'product'
    || (function_exists('is_woocommerce') && is_woocommerce())
    || (function_exists('is_cart') && is_cart())
    || (function_exists('is_checkout') && is_checkout());
?>
<div class="wpacu-page-summary wpacu-page-summary--frontend">
    <div class="wpacu-page-summary-items">
        <?php if (!empty($wpacuPageSummary['is_homepage']) || !empty($wpacuPageSummary['is_blog_page'])) { ?>
            <div class="wpacu-page-summary-item wpacu-page-summary-context">
                <span class="dashicons dashicons-admin-<?php echo !empty($wpacuPageSummary['is_homepage']) ? 'home' : 'post'; ?>" aria-hidden="true"></span>
                <span><?php echo !empty($wpacuPageSummary['is_homepage'])
                    ? esc_html__('You are currently viewing the home page.', 'wp-asset-clean-up')
                    : esc_html__('You are currently viewing the page that shows your latest posts.', 'wp-asset-clean-up'); ?></span>
            </div>
        <?php } ?>
        <?php if (!empty($wpacuPageSummary['url'])) { ?>
            <div class="wpacu-page-summary-item wpacu-page-summary-url">
                <strong><?php esc_html_e('Page URL:', 'wp-asset-clean-up'); ?></strong>
                <a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url($wpacuPageSummary['url']); ?>"
                   title="<?php echo esc_attr(isset($wpacuPageSummary['title']) ? $wpacuPageSummary['title'] : ''); ?>"
                   aria-label="<?php echo esc_attr(sprintf(__('%s (opens in a new tab)', 'wp-asset-clean-up'), $wpacuPageSummary['url'])); ?>"><?php echo esc_html($wpacuPageSummary['url']); ?></a>
            </div>
        <?php } ?>
        <?php if (!empty($wpacuPageSummary['post_id'])) { ?>
            <div class="wpacu-page-summary-item wpacu-page-summary-id"><strong><?php esc_html_e('Post ID:', 'wp-asset-clean-up'); ?></strong> <?php echo (int)$wpacuPageSummary['post_id']; ?></div>
        <?php } ?>
        <?php if ($wpacuSummaryType) { ?>
            <div class="wpacu-page-summary-item wpacu-page-summary-type">
                <?php if ($wpacuSummaryWoo) { ?>
                    <img class="wpacu-page-summary-woo" src="<?php echo esc_url(WPACU_PLUGIN_URL . '/assets/icons/woocommerce-icon-logo.svg'); ?>" alt="WooCommerce" width="28" height="28">
                <?php } else { ?>
                    <span class="dashicons dashicons-admin-<?php echo esc_attr($wpacuSummaryIcon); ?>" aria-hidden="true"></span>
                <?php } ?>
                <span><u><?php echo esc_html($wpacuSummaryType); ?></u> <?php echo $wpacuSummaryType === 'post' ? esc_html__('type.', 'wp-asset-clean-up') : esc_html__('post type.', 'wp-asset-clean-up'); ?></span>
            </div>
        <?php } ?>
    </div>
    <?php if (!empty($wpacuPageSummary['redirected_url'])) { ?>
        <div class="wpacu-redirected-fetch-url-reminder">
            <strong><span class="dashicons dashicons-migrate" aria-hidden="true"></span> <?php esc_html_e('Redirected to:', 'wp-asset-clean-up'); ?></strong>
            <a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url($wpacuPageSummary['redirected_url']); ?>"><?php echo esc_html($wpacuPageSummary['redirected_url']); ?></a>
        </div>
    <?php } ?>
</div>
