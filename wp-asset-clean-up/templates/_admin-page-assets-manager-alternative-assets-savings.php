<?php
if ( ! defined('ABSPATH') || ! isset($comparison)) {
    exit;
}
$savingsIcon = $comparison['state'] === 'smaller' ? 'arrow-down-alt'
    : ($comparison['state'] === 'larger' ? 'arrow-up-alt' : '');
?>
<details class="wpacu-alt-assets__savings is-<?php echo esc_attr($comparison['state']); ?>">
    <summary aria-label="<?php echo esc_attr(sprintf(__('%s. Show file size comparison.', 'wp-asset-clean-up'), $comparison['label'])); ?>">
        <?php if ($savingsIcon !== '') { ?><span class="dashicons dashicons-<?php echo esc_attr($savingsIcon); ?>" aria-hidden="true"></span><?php } ?>
        <?php echo esc_html($comparison['label']); ?>
        <span class="dashicons dashicons-info-outline wpacu-alt-assets__savings-info" aria-hidden="true"></span>
    </summary>
    <div class="wpacu-alt-assets__savings-explanation">
        <strong><?php esc_html_e('File size comparison', 'wp-asset-clean-up'); ?></strong>
        <dl>
            <dt><?php esc_html_e('Detected original', 'wp-asset-clean-up'); ?></dt>
            <dd><?php echo $comparison['original_bytes'] > 0 ? esc_html(size_format($comparison['original_bytes'], 1)) : esc_html__('Unknown', 'wp-asset-clean-up'); ?></dd>
            <dt><?php esc_html_e('Generated alternative', 'wp-asset-clean-up'); ?></dt>
            <dd><?php echo $comparison['alternative_bytes'] > 0 ? esc_html(size_format($comparison['alternative_bytes'], 1)) : esc_html__('Unknown', 'wp-asset-clean-up'); ?></dd>
        </dl>
        <p><?php esc_html_e('Compares the detected original file size with the generated alternative, using its Output choice. Follow global settings applies the current minification settings and exclusions.', 'wp-asset-clean-up'); ?></p>
        <?php if ($comparison['state'] === 'unknown') { ?>
            <p><?php esc_html_e('A percentage is shown only when both file sizes are known.', 'wp-asset-clean-up'); ?></p>
        <?php } elseif ($comparison['state'] !== 'smaller') { ?>
            <p><?php esc_html_e('The generated alternative is not smaller than the detected original. Review the code if your goal is to reduce its size.', 'wp-asset-clean-up'); ?></p>
        <?php } ?>
        <p><?php esc_html_e('Sizes are before gzip/Brotli compression. Page-specific settings and Test Mode can change what visitors receive. An OFF alternative is not served.', 'wp-asset-clean-up'); ?></p>
    </div>
</details>
