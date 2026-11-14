<?php
if ( ! defined('ABSPATH') ) {
    exit;
}
?>
<div id="wpacu-alt-assets-guide" class="wpacu-singular-posts-modal wpacu-alt-assets-guide" hidden>
    <div class="wpacu-singular-posts-modal-backdrop" data-wpacu-alt-guide-close aria-hidden="true"></div>
    <div class="wpacu-singular-posts-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="wpacu-alt-guide-title" aria-describedby="wpacu-alt-guide-description" tabindex="-1">
        <header class="wpacu-singular-posts-modal-header">
            <span class="wpacu-alt-assets-guide__eyebrow"><?php esc_html_e('Alternative Assets', 'wp-asset-clean-up'); ?></span>
            <h2 id="wpacu-alt-guide-title"><?php esc_html_e('Keep what your page needs.', 'wp-asset-clean-up'); ?></h2>
            <p id="wpacu-alt-guide-description"><?php esc_html_e('Start with an existing CSS or JavaScript asset. Edit a copy, then choose where to load it.', 'wp-asset-clean-up'); ?></p>
            <button type="button" class="button-link wpacu-singular-posts-modal-close" data-wpacu-alt-guide-close aria-label="<?php esc_attr_e('Close', 'wp-asset-clean-up'); ?>">&times;</button>
        </header>
        <div class="wpacu-singular-posts-modal-body">
            <ol class="wpacu-alt-assets-guide__steps">
                <li class="wpacu-alt-assets-guide__step">
                    <div class="wpacu-alt-assets-guide__step-heading"><span aria-hidden="true">1</span><h3><?php esc_html_e('Start with the original', 'wp-asset-clean-up'); ?></h3></div>
                    <div class="wpacu-alt-assets-guide__visual" aria-hidden="true">
                        <div class="wpacu-alt-assets-guide__file-heading"><span class="dashicons dashicons-media-code"></span><code>styles.css</code><b>120 KB</b></div>
                        <div class="wpacu-alt-assets-guide__code-block is-kept"><code>.navigation { … }</code><code>.button { … }</code></div>
                        <div class="wpacu-alt-assets-guide__code-block is-extra"><code>.gallery { … }</code><code>.shop-grid { … }</code><code>.slider { … }</code></div>
                    </div>
                    <p><?php esc_html_e('A theme or plugin asset may contain code for many page types. Your selected pages may need only part of it.', 'wp-asset-clean-up'); ?></p>
                </li>
                <li class="wpacu-alt-assets-guide__step">
                    <div class="wpacu-alt-assets-guide__step-heading"><span aria-hidden="true">2</span><h3><?php esc_html_e('Edit a copy', 'wp-asset-clean-up'); ?></h3></div>
                    <div class="wpacu-alt-assets-guide__visual is-editor" aria-hidden="true">
                        <div class="wpacu-alt-assets-guide__file-heading"><span class="dashicons dashicons-edit"></span><span><?php esc_html_e('Your alternative', 'wp-asset-clean-up'); ?></span></div>
                        <div class="wpacu-alt-assets-guide__code-block is-kept"><code>.navigation { … }</code><code>.button { … }</code></div>
                        <div class="wpacu-alt-assets-guide__code-block is-removed"><code>.gallery { … }</code><code>.shop-grid { … }</code><code>.slider { … }</code></div>
                    </div>
                    <p><?php esc_html_e('Copy the original code into the editor. Keep the parts your pages need and remove the rest yourself.', 'wp-asset-clean-up'); ?></p>
                </li>
                <li class="wpacu-alt-assets-guide__step">
                    <div class="wpacu-alt-assets-guide__step-heading"><span aria-hidden="true">3</span><h3><?php esc_html_e('Load a lighter alternative', 'wp-asset-clean-up'); ?></h3></div>
                    <div class="wpacu-alt-assets-guide__visual is-result" aria-hidden="true">
                        <div class="wpacu-alt-assets-guide__file-heading"><span class="dashicons dashicons-media-code"></span><span><?php esc_html_e('Alternative CSS', 'wp-asset-clean-up'); ?></span><b>42 KB</b></div>
                        <div class="wpacu-alt-assets-guide__code-block is-kept"><code>.navigation { … }</code><code>.button { … }</code></div>
                        <div class="wpacu-alt-assets-guide__result-note"><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e('Only where your conditions match', 'wp-asset-clean-up'); ?></div>
                    </div>
                    <p><?php esc_html_e('Save your copy and choose its conditions. Matching pages load your alternative instead of the original asset.', 'wp-asset-clean-up'); ?></p>
                </li>
            </ol>

            <section class="wpacu-alt-assets-guide__comparison" aria-labelledby="wpacu-alt-guide-example">
                <div class="wpacu-alt-assets-guide__saving">
                    <span id="wpacu-alt-guide-example" class="wpacu-alt-assets-guide__eyebrow"><?php esc_html_e('Illustrative CSS example', 'wp-asset-clean-up'); ?></span>
                    <strong>65% <span><?php esc_html_e('smaller', 'wp-asset-clean-up'); ?></span></strong>
                    <span><?php esc_html_e('78 KB less in this asset', 'wp-asset-clean-up'); ?></span>
                </div>
                <div class="wpacu-alt-assets-guide__bars">
                    <div class="wpacu-alt-assets-guide__bar-label"><span><?php esc_html_e('Original CSS', 'wp-asset-clean-up'); ?></span><strong>120 KB</strong></div>
                    <div class="wpacu-alt-assets-guide__bar-track" aria-hidden="true"><span></span></div>
                    <div class="wpacu-alt-assets-guide__bar-label"><span><?php esc_html_e('Alternative CSS', 'wp-asset-clean-up'); ?></span><strong>42 KB</strong></div>
                    <div class="wpacu-alt-assets-guide__bar-track is-alternative" aria-hidden="true"><span></span></div>
                </div>
                <p class="wpacu-alt-assets-guide__comparison-note"><?php esc_html_e('Example sizes, not a measurement of your site. A smaller asset does not mean the page loads faster by the same percentage.', 'wp-asset-clean-up'); ?></p>
            </section>

            <div class="wpacu-alt-assets-guide__guidance">
                <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                <p><strong><?php esc_html_e('You decide what to remove.', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('This requires CSS or JavaScript knowledge. Asset CleanUp does not automatically identify unused code. Test the affected pages before enabling your alternative.', 'wp-asset-clean-up'); ?></p>
            </div>
            <details class="wpacu-alt-assets-guide__details">
                <summary><?php esc_html_e('For developers: what stays intact', 'wp-asset-clean-up'); ?></summary>
                <p><?php esc_html_e('The original source code is not modified. Currently, alternatives replace CSS or JavaScript assets loaded from a URL. Their WordPress handle, dependencies and associated inline code are preserved. Assets containing only inline code are not supported yet. Turn the alternative off to restore the original asset.', 'wp-asset-clean-up'); ?></p>
            </details>
        </div>
        <footer class="wpacu-singular-posts-modal-footer">
            <span><?php esc_html_e('The same process applies to CSS and JavaScript.', 'wp-asset-clean-up'); ?></span>
            <button type="button" class="button button-primary" data-wpacu-alt-guide-close><?php esc_html_e('Got it', 'wp-asset-clean-up'); ?></button>
        </footer>
    </div>
</div>
