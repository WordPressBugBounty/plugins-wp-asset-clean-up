<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div id="wpacu-move-scripts-to-body-examples" class="wpacu-modal wpacu-script-move-modal">
    <div class="wpacu-modal-content wpacu-script-move-modal__content">
        <button type="button" class="wpacu-close wpacu-script-move-modal__close" aria-label="<?php esc_attr_e('Close', 'wp-asset-clean-up'); ?>">&times;</button>

        <header class="wpacu-script-move-modal__header">
            <span class="wpacu-script-move-modal__eyebrow"><?php esc_html_e('Before and after', 'wp-asset-clean-up'); ?></span>
            <h2><?php echo wp_kses_post(__('Move <code>&lt;SCRIPT&gt;</code> tags from HEAD to BODY', 'wp-asset-clean-up')); ?></h2>
            <p><?php esc_html_e('The highlighted script tags keep their internal order, but move from the document head into the body.', 'wp-asset-clean-up'); ?></p>
        </header>

        <div class="wpacu-script-move-modal__comparison">
            <section class="wpacu-script-move-example is-before">
                <header class="wpacu-script-move-example__header">
                    <span class="wpacu-script-move-example__badge"><?php esc_html_e('Before', 'wp-asset-clean-up'); ?></span>
                    <div>
                        <strong><?php esc_html_e('Scripts load in HEAD', 'wp-asset-clean-up'); ?></strong>
                        <small><?php esc_html_e('Original document structure', 'wp-asset-clean-up'); ?></small>
                    </div>
                </header>
                <pre><code>&lt;head&gt;
    &lt;title&gt;Your page title here&lt;/title&gt;
    ...
<span class="wpacu-script-move-example__moved">    &lt;script src="/wp-includes/js/jquery.js"&gt;&lt;/script&gt;
    &lt;script&gt;/* code here */&lt;/script&gt;</span>
&lt;/head&gt;
&lt;body&gt;
    ...
&lt;/body&gt;</code></pre>
            </section>

            <div class="wpacu-script-move-modal__transition" aria-hidden="true">
                <span class="dashicons dashicons-arrow-down-alt"></span>
                <span><?php esc_html_e('Script tags are relocated', 'wp-asset-clean-up'); ?></span>
            </div>

            <section class="wpacu-script-move-example is-after">
                <header class="wpacu-script-move-example__header">
                    <span class="wpacu-script-move-example__badge"><?php esc_html_e('After', 'wp-asset-clean-up'); ?></span>
                    <div>
                        <strong><?php esc_html_e('Scripts load in BODY', 'wp-asset-clean-up'); ?></strong>
                        <small><?php esc_html_e('Result after relocation', 'wp-asset-clean-up'); ?></small>
                    </div>
                </header>
                <pre><code>&lt;head&gt;
    &lt;title&gt;Your page title here&lt;/title&gt;
    ...
&lt;/head&gt;
&lt;body&gt;
<span class="wpacu-script-move-example__moved">    &lt;script src="/wp-includes/js/jquery.js"&gt;&lt;/script&gt;
    &lt;script&gt;/* code here */&lt;/script&gt;</span>
    ...
&lt;/body&gt;</code></pre>
            </section>
        </div>
    </div>
</div>
