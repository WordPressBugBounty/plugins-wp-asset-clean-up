<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div id="wpacu-preloaded-assets-info" class="wpacu-modal wpacu-bulk-info-modal">
    <div class="wpacu-modal-content wpacu-bulk-info-modal__content">
        <button type="button" class="wpacu-close wpacu-bulk-info-modal__close" aria-label="<?php esc_attr_e('Close', 'wp-asset-clean-up'); ?>">&times;</button>
        <header class="wpacu-bulk-info-modal__header">
            <span class="wpacu-bulk-info-modal__eyebrow"><?php esc_html_e('Bulk Changes reference', 'wp-asset-clean-up'); ?></span>
            <h2><?php esc_html_e('Preloading CSS/JS site-wide', 'wp-asset-clean-up'); ?></h2>
            <p><?php esc_html_e('This page lists stylesheets and scripts whose preload setting applies across the entire website.', 'wp-asset-clean-up'); ?></p>
        </header>
        <div class="wpacu-bulk-info-modal__body">
            <section class="wpacu-bulk-info-modal__summary"><span class="wpacu-bulk-info-modal__summary-icon"><span class="dashicons dashicons-performance" aria-hidden="true"></span></span><div><strong><?php esc_html_e('A preload selected on one page becomes a site-wide rule', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('It is not limited to the page where the setting was originally enabled.', 'wp-asset-clean-up'); ?></span></div></section>
            <div class="wpacu-bulk-info-modal__grid">
                <section class="wpacu-bulk-info-card"><span class="wpacu-bulk-info-card__label"><?php esc_html_e('Included here', 'wp-asset-clean-up'); ?></span><strong><?php esc_html_e('Stylesheets and scripts', 'wp-asset-clean-up'); ?></strong><p><?php esc_html_e('Every listed asset currently has a site-wide preload choice saved through Asset CleanUp.', 'wp-asset-clean-up'); ?></p></section>
                <section class="wpacu-bulk-info-card"><span class="wpacu-bulk-info-card__label"><?php esc_html_e('Important', 'wp-asset-clean-up'); ?></span><strong><?php esc_html_e('Preload only critical resources', 'wp-asset-clean-up'); ?></strong><p><?php esc_html_e('Too many preloads can compete with more important downloads and reduce the intended performance benefit.', 'wp-asset-clean-up'); ?></p></section>
            </div>
            <section class="wpacu-bulk-info-modal__steps">
                <h3><?php esc_html_e('How the list is updated', 'wp-asset-clean-up'); ?></h3>
                <div class="wpacu-bulk-info-step"><span class="wpacu-bulk-info-step__number">1</span><div><strong><?php esc_html_e('Open a representative page in CSS/JS Load Manager', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Use a page where the stylesheet or script is loaded.', 'wp-asset-clean-up'); ?></small></div></div>
                <div class="wpacu-bulk-info-step"><span class="wpacu-bulk-info-step__number">2</span><div><strong><?php esc_html_e('Choose a Preload option for the asset', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Saving the page adds or updates the site-wide preload rule.', 'wp-asset-clean-up'); ?></small></div></div>
                <div class="wpacu-bulk-info-step"><span class="wpacu-bulk-info-step__number">3</span><div><strong><?php esc_html_e('Choose “No (default)” to remove the preload', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Save again and the asset will disappear from this overview.', 'wp-asset-clean-up'); ?></small></div></div>
            </section>
        </div>
    </div>
</div>
