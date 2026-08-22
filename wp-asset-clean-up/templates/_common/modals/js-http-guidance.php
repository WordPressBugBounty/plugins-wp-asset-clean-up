<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div id="wpacu-http2-info-js" class="wpacu-modal wpacu-protocol-guidance-modal">
    <div class="wpacu-modal-content wpacu-protocol-guidance-modal__content">
        <button type="button" class="wpacu-close wpacu-protocol-guidance-modal__close" aria-label="<?php esc_attr_e('Close', 'wp-asset-clean-up'); ?>">&times;</button>

        <header class="wpacu-protocol-guidance-modal__header">
            <span class="wpacu-protocol-guidance-modal__eyebrow"><?php esc_html_e('JavaScript delivery guidance', 'wp-asset-clean-up'); ?></span>
            <h2><?php esc_html_e('Combining JavaScript on modern HTTP connections', 'wp-asset-clean-up'); ?></h2>
            <p><?php esc_html_e('HTTP/2 and HTTP/3 can transfer multiple scripts efficiently over one connection, so reducing request count alone is no longer an automatic performance win.', 'wp-asset-clean-up'); ?></p>
        </header>

        <div class="wpacu-protocol-guidance-modal__body">
            <section class="wpacu-protocol-guidance-verdict">
                <span class="wpacu-protocol-guidance-verdict__icon"><span class="dashicons dashicons-performance" aria-hidden="true"></span></span>
                <div>
                    <span class="wpacu-protocol-guidance-verdict__badge"><?php esc_html_e('Modern protocol', 'wp-asset-clean-up'); ?></span>
                    <h3><?php esc_html_e('Leave JavaScript combination disabled by default', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('First unload unnecessary scripts and minify what remains. Combine only when repeatable tests demonstrate a consistent benefit.', 'wp-asset-clean-up'); ?></p>
                </div>
            </section>

            <div class="wpacu-protocol-guidance-grid">
                <section class="wpacu-protocol-guidance-card is-benefit">
                    <header>
                        <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                        <div><strong><?php esc_html_e('When combining can help', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Specific legacy or fragmented setups', 'wp-asset-clean-up'); ?></small></div>
                    </header>
                    <ul>
                        <li><?php esc_html_e('The request path is limited to HTTP/1.1 or has unusually high latency.', 'wp-asset-clean-up'); ?></li>
                        <li><?php esc_html_e('A page loads many tiny compatible scripts from the same slow origin.', 'wp-asset-clean-up'); ?></li>
                    </ul>
                </section>

                <section class="wpacu-protocol-guidance-card is-cost">
                    <header>
                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                        <div><strong><?php esc_html_e('What combining can cost', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Common JavaScript trade-offs', 'wp-asset-clean-up'); ?></small></div>
                    </header>
                    <ul>
                        <li><?php esc_html_e('Visitors may download a larger bundle containing code the current page does not use.', 'wp-asset-clean-up'); ?></li>
                        <li><?php esc_html_e('A small source change can invalidate the entire combined browser cache.', 'wp-asset-clean-up'); ?></li>
                        <li><?php esc_html_e('Execution-order or compatibility failures can become harder to isolate.', 'wp-asset-clean-up'); ?></li>
                    </ul>
                </section>
            </div>

            <section class="wpacu-protocol-guidance-test">
                <div class="wpacu-protocol-guidance-test__heading">
                    <span class="dashicons dashicons-chart-line" aria-hidden="true"></span>
                    <div><strong><?php esc_html_e('Test performance and functionality together', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Check the browser console and complete important user flows before publishing the change.', 'wp-asset-clean-up'); ?></small></div>
                </div>
                <div class="wpacu-protocol-guidance-test__checks">
                    <span><?php esc_html_e('Menus and forms', 'wp-asset-clean-up'); ?></span>
                    <span><?php esc_html_e('Checkout flows', 'wp-asset-clean-up'); ?></span>
                    <span><?php esc_html_e('Sliders and popups', 'wp-asset-clean-up'); ?></span>
                    <span><?php esc_html_e('Consent tools', 'wp-asset-clean-up'); ?></span>
                    <span><?php esc_html_e('Browser console', 'wp-asset-clean-up'); ?></span>
                </div>
                <a target="_blank" rel="noopener noreferrer" href="https://www.assetcleanup.com/docs/?p=2004"><span class="dashicons dashicons-external" aria-hidden="true"></span><?php esc_html_e('Read the JavaScript combination guide', 'wp-asset-clean-up'); ?></a>
            </section>
        </div>
    </div>
</div>
