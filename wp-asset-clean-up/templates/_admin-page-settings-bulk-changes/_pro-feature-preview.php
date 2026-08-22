<?php
if (! defined('ABSPATH')) {
    exit;
}

$wpacuBulkPreviewTitle = isset($wpacuBulkPreviewTitle) ? $wpacuBulkPreviewTitle : __('Bulk Changes', 'wp-asset-clean-up');
$wpacuBulkPreviewDescription = isset($wpacuBulkPreviewDescription) ? $wpacuBulkPreviewDescription : '';
$wpacuBulkPreviewHelp = isset($wpacuBulkPreviewHelp) ? $wpacuBulkPreviewHelp : '';
$wpacuBulkPreviewMedium = isset($wpacuBulkPreviewMedium) ? $wpacuBulkPreviewMedium : 'bulk_changes';
?>
<section class="wpacu-bulk-pro-preview" style="max-width: 980px; margin: 20px 0; padding: 22px; border: 1px solid #ccd9de; border-radius: 8px; background: #fff;">
    <div style="display: flex; align-items: flex-start; gap: 14px;">
        <img style="margin-top: 2px; opacity: .65;" width="22" height="22" src="<?php echo esc_url(WPACU_PLUGIN_URL . '/assets/icons/icon-lock.svg'); ?>" alt="" />
        <div>
            <div style="color: #13738a; font-size: 11px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase;"><?php esc_html_e('Asset CleanUp Pro', 'wp-asset-clean-up'); ?></div>
            <h2 style="margin: 5px 0 8px;"><?php echo esc_html($wpacuBulkPreviewTitle); ?></h2>
            <?php if ($wpacuBulkPreviewDescription !== '') { ?><p style="margin: 0; line-height: 1.6;"><?php echo wp_kses($wpacuBulkPreviewDescription, array('code' => array(), 'em' => array(), 'strong' => array())); ?></p><?php } ?>
            <?php if ($wpacuBulkPreviewHelp !== '') { ?><p style="margin: 12px 0 0; color: #52636b; line-height: 1.6;"><?php echo wp_kses($wpacuBulkPreviewHelp, array('em' => array(), 'strong' => array())); ?></p><?php } ?>
            <p style="margin: 16px 0 0;"><a class="button button-secondary" href="<?php echo esc_url(apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL . '?utm_source=plugin_bulk_changes&utm_medium=' . rawurlencode($wpacuBulkPreviewMedium))); ?>"><?php esc_html_e('View Asset CleanUp Pro', 'wp-asset-clean-up'); ?></a></p>
        </div>
    </div>
</section>

