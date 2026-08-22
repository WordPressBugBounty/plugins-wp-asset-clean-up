<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div id="wpacu-move-inline-jquery" class="wpacu-modal wpacu-script-move-modal">
    <div class="wpacu-modal-content wpacu-script-move-modal__content">
        <button type="button" class="wpacu-close wpacu-script-move-modal__close" aria-label="<?php esc_attr_e('Close', 'wp-asset-clean-up'); ?>">&times;</button>

        <header class="wpacu-script-move-modal__header">
            <span class="wpacu-script-move-modal__eyebrow"><?php esc_html_e('Execution order repair', 'wp-asset-clean-up'); ?></span>
            <h2><?php esc_html_e('Move inline jQuery after the library', 'wp-asset-clean-up'); ?></h2>
            <p><?php esc_html_e('Inline code that depends on jQuery is moved below the required library tags while preserving the order of the inline snippets.', 'wp-asset-clean-up'); ?></p>
        </header>

        <div class="wpacu-script-move-modal__comparison">
            <section class="wpacu-script-move-scenario">
                <header class="wpacu-script-move-scenario__header">
                    <span class="wpacu-script-move-scenario__number">1</span>
                    <div>
                        <strong><?php esc_html_e('Without jQuery Migrate', 'wp-asset-clean-up'); ?></strong>
                        <small><?php esc_html_e('The inline snippet moves below jquery.js', 'wp-asset-clean-up'); ?></small>
                    </div>
                </header>

                <section class="wpacu-script-move-example is-before">
                    <header class="wpacu-script-move-example__header">
                        <span class="wpacu-script-move-example__badge"><?php esc_html_e('Before', 'wp-asset-clean-up'); ?></span>
                        <div><strong><?php esc_html_e('Inline code runs too early', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('jQuery is not available yet', 'wp-asset-clean-up'); ?></small></div>
                    </header>
                    <pre><code><span class="wpacu-script-move-example__moved">&lt;script&gt;jQuery(document).ready(function($) { /* code here */ });&lt;/script&gt;</span>
<span class="wpacu-script-move-example__library">&lt;script src="/wp-includes/js/jquery.js"&gt;&lt;/script&gt;</span></code></pre>
                </section>

                <div class="wpacu-script-move-modal__transition" aria-hidden="true"><span class="dashicons dashicons-arrow-down-alt"></span><span><?php esc_html_e('Dependency order corrected', 'wp-asset-clean-up'); ?></span></div>

                <section class="wpacu-script-move-example is-after">
                    <header class="wpacu-script-move-example__header">
                        <span class="wpacu-script-move-example__badge"><?php esc_html_e('After', 'wp-asset-clean-up'); ?></span>
                        <div><strong><?php esc_html_e('Library loads before inline code', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('The dependency is available when the snippet runs', 'wp-asset-clean-up'); ?></small></div>
                    </header>
                    <pre><code><span class="wpacu-script-move-example__library">&lt;script src="/wp-includes/js/jquery.js"&gt;&lt;/script&gt;</span>
<span class="wpacu-script-move-example__moved">&lt;script&gt;jQuery(document).ready(function($) { /* code here */ });&lt;/script&gt;</span></code></pre>
                </section>
            </section>

            <section class="wpacu-script-move-scenario">
                <header class="wpacu-script-move-scenario__header">
                    <span class="wpacu-script-move-scenario__number">2</span>
                    <div>
                        <strong><?php esc_html_e('With jQuery Migrate', 'wp-asset-clean-up'); ?></strong>
                        <small><?php esc_html_e('Both inline snippets move below the two library tags', 'wp-asset-clean-up'); ?></small>
                    </div>
                </header>

                <section class="wpacu-script-move-example is-before">
                    <header class="wpacu-script-move-example__header">
                        <span class="wpacu-script-move-example__badge"><?php esc_html_e('Before', 'wp-asset-clean-up'); ?></span>
                        <div><strong><?php esc_html_e('Inline code precedes its dependencies', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Both snippets can fail before the libraries load', 'wp-asset-clean-up'); ?></small></div>
                    </header>
                    <pre><code><span class="wpacu-script-move-example__moved">&lt;script&gt;jQuery(document).ready(function($) { /* code here */ });&lt;/script&gt;
&lt;script&gt;$(document).ready(function() { /* another code here */ });&lt;/script&gt;</span>
<span class="wpacu-script-move-example__library">&lt;script src="/wp-includes/js/jquery.js"&gt;&lt;/script&gt;
&lt;script src="/wp-includes/js/jquery-migrate.min.js"&gt;&lt;/script&gt;</span></code></pre>
                </section>

                <div class="wpacu-script-move-modal__transition" aria-hidden="true"><span class="dashicons dashicons-arrow-down-alt"></span><span><?php esc_html_e('Dependency order corrected', 'wp-asset-clean-up'); ?></span></div>

                <section class="wpacu-script-move-example is-after">
                    <header class="wpacu-script-move-example__header">
                        <span class="wpacu-script-move-example__badge"><?php esc_html_e('After', 'wp-asset-clean-up'); ?></span>
                        <div><strong><?php esc_html_e('Libraries load before inline code', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('The relative order inside each group is preserved', 'wp-asset-clean-up'); ?></small></div>
                    </header>
                    <pre><code><span class="wpacu-script-move-example__library">&lt;script src="/wp-includes/js/jquery.js"&gt;&lt;/script&gt;
&lt;script src="/wp-includes/js/jquery-migrate.min.js"&gt;&lt;/script&gt;</span>
<span class="wpacu-script-move-example__moved">&lt;script&gt;jQuery(document).ready(function($) { /* code here */ });&lt;/script&gt;
&lt;script&gt;$(document).ready(function() { /* another code here */ });&lt;/script&gt;</span></code></pre>
                </section>
            </section>
        </div>
    </div>
</div>
