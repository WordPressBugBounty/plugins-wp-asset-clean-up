<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div id="wpacu-combine-js-method-info" class="wpacu-modal wpacu-js-grouping-modal">
    <div class="wpacu-modal-content wpacu-js-grouping-modal__content">
        <button type="button" class="wpacu-close wpacu-js-grouping-modal__close" aria-label="<?php esc_attr_e('Close', 'wp-asset-clean-up'); ?>">&times;</button>
        <header class="wpacu-js-grouping-modal__header">
            <span class="wpacu-js-grouping-modal__eyebrow"><?php esc_html_e('Combination strategy', 'wp-asset-clean-up'); ?></span>
            <h2><?php esc_html_e('How JavaScript files are grouped', 'wp-asset-clean-up'); ?></h2>
            <p><?php esc_html_e('Asset CleanUp combines only compatible scripts and preserves the boundaries that control where and how they execute.', 'wp-asset-clean-up'); ?></p>
        </header>
        <div class="wpacu-js-grouping-modal__body">
            <section class="wpacu-js-grouping-stage is-source">
                <span class="wpacu-js-grouping-stage__icon"><span class="dashicons dashicons-media-code" aria-hidden="true"></span></span>
                <div><span class="wpacu-js-grouping-stage__label"><?php esc_html_e('Input', 'wp-asset-clean-up'); ?></span><strong><?php esc_html_e('Eligible JavaScript remaining after unloading', 'wp-asset-clean-up'); ?></strong><p><?php esc_html_e('Excluded, external or otherwise ineligible scripts are left outside the combination process.', 'wp-asset-clean-up'); ?></p></div>
            </section>
            <div class="wpacu-js-grouping-modal__arrow" aria-hidden="true"><span class="dashicons dashicons-arrow-down-alt"></span></div>
            <section class="wpacu-js-grouping-boundaries">
                <header><strong><?php esc_html_e('Compatibility boundaries are preserved', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Scripts cross a boundary only when doing so is known to be safe.', 'wp-asset-clean-up'); ?></small></header>
                <div class="wpacu-js-grouping-boundaries__grid">
                    <div><code>&lt;head&gt;</code><span><?php esc_html_e('Head scripts remain separate from body scripts', 'wp-asset-clean-up'); ?></span></div>
                    <div><code>&lt;body&gt;</code><span><?php esc_html_e('Body placement and execution context are retained', 'wp-asset-clean-up'); ?></span></div>
                    <div><code>async / defer</code><span><?php esc_html_e('Loading strategies are not mixed indiscriminately', 'wp-asset-clean-up'); ?></span></div>
                    <div><code>inline</code><span><?php esc_html_e('Associated inline code stays with its handle where supported', 'wp-asset-clean-up'); ?></span></div>
                </div>
            </section>
            <div class="wpacu-js-grouping-modal__arrow" aria-hidden="true"><span class="dashicons dashicons-arrow-down-alt"></span></div>
            <section class="wpacu-js-grouping-stage is-output">
                <span class="wpacu-js-grouping-stage__icon"><span class="dashicons dashicons-archive" aria-hidden="true"></span></span>
                <div><span class="wpacu-js-grouping-stage__label"><?php esc_html_e('Output', 'wp-asset-clean-up'); ?></span><strong><?php esc_html_e('One or more compatible cached groups', 'wp-asset-clean-up'); ?></strong><p><?php esc_html_e('The final number depends on the scripts, locations and loading attributes detected on the current page.', 'wp-asset-clean-up'); ?></p></div>
            </section>
            <section class="wpacu-js-grouping-exceptions">
                <strong><?php esc_html_e('These can remain separate', 'wp-asset-clean-up'); ?></strong>
                <div><span>jQuery</span><span>jQuery Migrate</span><span><?php esc_html_e('Excluded files', 'wp-asset-clean-up'); ?></span><span><?php esc_html_e('Incompatible attributes', 'wp-asset-clean-up'); ?></span></div>
            </section>
        </div>
    </div>
</div>
