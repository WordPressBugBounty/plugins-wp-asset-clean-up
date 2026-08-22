<?php
if (! defined('ABSPATH')) {
    exit;
}

$isLocalFontDisplay = isset($fontDisplayModalVariant) && $fontDisplayModalVariant === 'local';
$modalId = $isLocalFontDisplay ? 'wpacu-local-fonts-display-info' : 'wpacu-google-fonts-display-info';
?>
<div id="<?php echo esc_attr($modalId); ?>" class="wpacu-modal wpacu-font-display-modal">
    <div class="wpacu-modal-content wpacu-font-display-modal__content">
        <button type="button" class="wpacu-close wpacu-font-display-modal__close" aria-label="<?php esc_attr_e('Close', 'wp-asset-clean-up'); ?>">&times;</button>

        <header class="wpacu-font-display-modal__header">
            <span class="wpacu-font-display-modal__eyebrow"><?php echo esc_html($isLocalFontDisplay ? __('Local font rendering reference', 'wp-asset-clean-up') : __('Font rendering reference', 'wp-asset-clean-up')); ?></span>
            <h2><?php esc_html_e('Compare font-display behaviors', 'wp-asset-clean-up'); ?></h2>
            <p><?php echo esc_html($isLocalFontDisplay ? __('Choose how quickly text appears while self-hosted font files load and whether the browser must replace the fallback font.', 'wp-asset-clean-up') : __('Choose how quickly text appears and whether the browser must replace the fallback font after the web font becomes available.', 'wp-asset-clean-up')); ?></p>
        </header>

        <div class="wpacu-font-display-modal__list">
            <section class="wpacu-font-display-option is-recommended">
                <div class="wpacu-font-display-option__name"><code>swap</code><span class="wpacu-font-display-option__badge"><?php esc_html_e('Recommended', 'wp-asset-clean-up'); ?></span></div>
                <div class="wpacu-font-display-option__body"><strong><?php esc_html_e('Text remains visible immediately', 'wp-asset-clean-up'); ?></strong><p><?php echo esc_html($isLocalFontDisplay ? __('The fallback font appears first and is replaced when the local font loads. A brief visual change (FOUT) is possible.', 'wp-asset-clean-up') : __('The fallback font appears first and is replaced when the web font loads. A brief visual change (FOUT) is possible.', 'wp-asset-clean-up')); ?></p></div>
            </section>

            <section class="wpacu-font-display-option">
                <div class="wpacu-font-display-option__name"><code>block</code><span class="wpacu-font-display-option__badge is-warning"><?php esc_html_e('Invisible briefly', 'wp-asset-clean-up'); ?></span></div>
                <div class="wpacu-font-display-option__body"><strong><?php echo esc_html($isLocalFontDisplay ? __('Waits briefly for the local font', 'wp-asset-clean-up') : __('Waits briefly for the web font', 'wp-asset-clean-up')); ?></strong><p><?php echo esc_html($isLocalFontDisplay ? __('Text may be invisible during the block period, then uses a fallback until the local font loads. This can cause FOIT.', 'wp-asset-clean-up') : __('Text may be invisible during the block period, then uses a fallback until the web font loads. This can cause FOIT.', 'wp-asset-clean-up')); ?></p></div>
            </section>

            <section class="wpacu-font-display-option">
                <div class="wpacu-font-display-option__name"><code>fallback</code><span class="wpacu-font-display-option__badge"><?php esc_html_e('Short window', 'wp-asset-clean-up'); ?></span></div>
                <div class="wpacu-font-display-option__body"><strong><?php esc_html_e('Balances speed and font consistency', 'wp-asset-clean-up'); ?></strong><p><?php echo esc_html($isLocalFontDisplay ? __('After a very short block period, the fallback font appears. The local font replaces it only within a limited swap period.', 'wp-asset-clean-up') : __('After a very short block period, the fallback font appears. The web font replaces it only within a limited swap period.', 'wp-asset-clean-up')); ?></p></div>
            </section>

            <section class="wpacu-font-display-option">
                <div class="wpacu-font-display-option__name"><code>optional</code><span class="wpacu-font-display-option__badge"><?php esc_html_e('Performance first', 'wp-asset-clean-up'); ?></span></div>
                <div class="wpacu-font-display-option__body"><strong><?php echo esc_html($isLocalFontDisplay ? __('Lets the browser skip the local font', 'wp-asset-clean-up') : __('Lets the browser skip the web font', 'wp-asset-clean-up')); ?></strong><p><?php esc_html_e('Text remains usable on slow connections, but the browser may keep the fallback font for the entire page view.', 'wp-asset-clean-up'); ?></p></div>
            </section>

            <section class="wpacu-font-display-option">
                <div class="wpacu-font-display-option__name"><code>auto</code><span class="wpacu-font-display-option__badge is-neutral"><?php esc_html_e('Browser controlled', 'wp-asset-clean-up'); ?></span></div>
                <div class="wpacu-font-display-option__body"><strong><?php esc_html_e('Uses the browser default', 'wp-asset-clean-up'); ?></strong><p><?php esc_html_e('The browser decides the block and swap periods. The result can vary between browsers and versions.', 'wp-asset-clean-up'); ?></p></div>
            </section>

            <?php if ($isLocalFontDisplay) : ?>
                <section class="wpacu-font-display-modal__example">
                    <header><span class="dashicons dashicons-editor-code" aria-hidden="true"></span><div><strong><?php esc_html_e('Example @font-face output', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Asset CleanUp adds the selected declaration without modifying the original font file.', 'wp-asset-clean-up'); ?></small></div></header>
                    <pre><code>@font-face {
  font-family: 'proxima-nova-1';
  src: url('/fonts/proxima-nova-light.woff2') format('woff2');
  font-weight: 300;
  font-style: normal;
  <mark>font-display: swap;</mark>
}</code></pre>
                </section>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php unset($isLocalFontDisplay, $modalId); ?>
