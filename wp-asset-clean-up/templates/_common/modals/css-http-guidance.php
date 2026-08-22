<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div id="wpacu-http2-info-css" class="wpacu-modal wpacu-protocol-guidance-modal">
    <div class="wpacu-modal-content wpacu-protocol-guidance-modal__content">
        <button type="button" class="wpacu-close wpacu-protocol-guidance-modal__close" aria-label="<?php esc_attr_e('Close', 'wp-asset-clean-up'); ?>">&times;</button>

        <header class="wpacu-protocol-guidance-modal__header">
            <span class="wpacu-protocol-guidance-modal__eyebrow"><?php esc_html_e('CSS delivery guidance', 'wp-asset-clean-up'); ?></span>
            <h2><?php esc_html_e('Combining CSS on modern HTTP connections', 'wp-asset-clean-up'); ?></h2>
            <p><?php esc_html_e('HTTP/2 and HTTP/3 can transfer multiple stylesheets efficiently over one connection, so fewer requests do not automatically mean a faster page.', 'wp-asset-clean-up'); ?></p>
        </header>

        <div class="wpacu-protocol-guidance-modal__body">
            <section class="wpacu-protocol-guidance-verdict">
                <span class="wpacu-protocol-guidance-verdict__icon"><span class="dashicons dashicons-performance" aria-hidden="true"></span></span>
                <div>
                    <span class="wpacu-protocol-guidance-verdict__badge"><?php esc_html_e('Modern protocol', 'wp-asset-clean-up'); ?></span>
                    <h3><?php esc_html_e('Leave CSS combination disabled by default', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('Enable it only when repeatable measurements on representative pages show a meaningful improvement.', 'wp-asset-clean-up'); ?></p>
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
                        <li><?php esc_html_e('A page loads a very large number of tiny stylesheets from the same slow origin.', 'wp-asset-clean-up'); ?></li>
                    </ul>
                </section>

                <section class="wpacu-protocol-guidance-card is-cost">
                    <header>
                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                        <div><strong><?php esc_html_e('What combining can cost', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Common trade-offs on modern sites', 'wp-asset-clean-up'); ?></small></div>
                    </header>
                    <ul>
                        <li><?php esc_html_e('One small source change invalidates a much larger combined cache file.', 'wp-asset-clean-up'); ?></li>
                        <li><?php esc_html_e('Visitors may download CSS that the current page does not use.', 'wp-asset-clean-up'); ?></li>
                        <li><?php esc_html_e('Ordering, media attributes and third-party files can require exclusions.', 'wp-asset-clean-up'); ?></li>
                    </ul>
                </section>
            </div>

            <section class="wpacu-protocol-guidance-test">
                <div class="wpacu-protocol-guidance-test__heading">
                    <span class="dashicons dashicons-chart-line" aria-hidden="true"></span>
                    <div><strong><?php esc_html_e('Measure the result, not only the request count', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Compare the same pages before and after enabling combination.', 'wp-asset-clean-up'); ?></small></div>
                </div>
                <div class="wpacu-protocol-guidance-test__checks">
                    <span><?php esc_html_e('Cold cache', 'wp-asset-clean-up'); ?></span>
                    <span><?php esc_html_e('Repeat views', 'wp-asset-clean-up'); ?></span>
                    <span><?php esc_html_e('Mobile and desktop', 'wp-asset-clean-up'); ?></span>
                    <span><?php esc_html_e('Guest and logged-in', 'wp-asset-clean-up'); ?></span>
                </div>
                <a target="_blank" rel="noopener" href="https://www.assetcleanup.com/docs/?p=2004"><span class="dashicons dashicons-external" aria-hidden="true"></span><?php esc_html_e('Read the CSS combination guide', 'wp-asset-clean-up'); ?></a>
            </section>
        </div>
    </div>
</div>
