<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div id="wpacu-add-bulk-rules-info" class="wpacu-modal wpacu-bulk-info-modal">
    <div class="wpacu-modal-content wpacu-bulk-info-modal__content">
        <button type="button" class="wpacu-close wpacu-bulk-info-modal__close" aria-label="<?php esc_attr_e('Close', 'wp-asset-clean-up'); ?>">&times;</button>
        <header class="wpacu-bulk-info-modal__header">
            <span class="wpacu-bulk-info-modal__eyebrow"><?php esc_html_e('Bulk Changes reference', 'wp-asset-clean-up'); ?></span>
            <h2><?php esc_html_e('Unloading CSS/JS across groups of pages', 'wp-asset-clean-up'); ?></h2>
            <p><?php esc_html_e('This overview collects unloading rules that affect multiple URLs instead of only one individually managed page.', 'wp-asset-clean-up'); ?></p>
        </header>
        <div class="wpacu-bulk-info-modal__body">
            <section class="wpacu-bulk-info-modal__summary"><span class="wpacu-bulk-info-modal__summary-icon"><span class="dashicons dashicons-filter" aria-hidden="true"></span></span><div><strong><?php esc_html_e('One rule can affect an entire page group', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('Bulk rules are created in CSS/JS Load Manager and are summarized here for easier review and removal.', 'wp-asset-clean-up'); ?></span></div></section>
            <div class="wpacu-bulk-info-modal__grid">
                <section class="wpacu-bulk-info-card"><span class="wpacu-bulk-info-card__label"><?php esc_html_e('Everywhere', 'wp-asset-clean-up'); ?></span><strong><?php esc_html_e('Site-wide unloading', 'wp-asset-clean-up'); ?></strong><p><?php esc_html_e('Unload an asset across the whole website, including the homepage and supported archive views.', 'wp-asset-clean-up'); ?></p></section>
                <section class="wpacu-bulk-info-card"><span class="wpacu-bulk-info-card__label"><?php esc_html_e('Content type', 'wp-asset-clean-up'); ?></span><strong><?php esc_html_e('Post type rules', 'wp-asset-clean-up'); ?></strong><p><?php echo wp_kses_post(__('Apply unloading to every page of a post type, such as <code>product</code>.', 'wp-asset-clean-up')); ?></p></section>
                <section class="wpacu-bulk-info-card"><span class="wpacu-bulk-info-card__label"><?php esc_html_e('Classification', 'wp-asset-clean-up'); ?></span><strong><?php esc_html_e('Taxonomy rules', 'wp-asset-clean-up'); ?></strong><p><?php echo wp_kses_post(__('Target taxonomy pages such as categories or a custom taxonomy like <code>product_cat</code>.', 'wp-asset-clean-up')); ?></p></section>
                <section class="wpacu-bulk-info-card"><span class="wpacu-bulk-info-card__label"><?php esc_html_e('Template', 'wp-asset-clean-up'); ?></span><strong><?php esc_html_e('Archive and special-page rules', 'wp-asset-clean-up'); ?></strong><p><?php esc_html_e('Target post type archives or special views such as all 404 Not Found URLs.', 'wp-asset-clean-up'); ?></p></section>
            </div>
            <section class="wpacu-bulk-info-modal__steps">
                <h3><?php esc_html_e('How a bulk rule reaches this list', 'wp-asset-clean-up'); ?></h3>
                <div class="wpacu-bulk-info-step"><span class="wpacu-bulk-info-step__number">1</span><div><strong><?php esc_html_e('Open CSS/JS Load Manager', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Manage a representative page where the target asset is loaded.', 'wp-asset-clean-up'); ?></small></div></div>
                <div class="wpacu-bulk-info-step"><span class="wpacu-bulk-info-step__number">2</span><div><strong><?php esc_html_e('Choose a site-wide or group unloading option', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('The available groups depend on the current page and WordPress content structure.', 'wp-asset-clean-up'); ?></small></div></div>
                <div class="wpacu-bulk-info-step"><span class="wpacu-bulk-info-step__number">3</span><div><strong><?php esc_html_e('Save and review the resulting rule here', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Removing the bulk rule allows the asset to load again unless another matching rule still applies.', 'wp-asset-clean-up'); ?></small></div></div>
            </section>
        </div>
    </div>
</div>
