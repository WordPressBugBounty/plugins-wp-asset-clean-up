<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\AssetsManagerAdmin;

if (! isset($data)) {
    exit;
}
?>
<div style="margin: 25px 0 0;">
    <p><?php esc_html_e('Custom Taxonomies are added via the theme or plugins such as WooCommerce product categories/tags.', 'wp-asset-clean-up'); ?> &#10230; <a target="_blank" href="https://wordpress.org/support/article/taxonomies/"><?php _e('read more', 'wp-asset-clean-up'); ?></a></p>
    <?php
    $data['archive_taxonomy'] = isset($_GET['wpacu_taxonomy']) ? sanitize_text_field($_GET['wpacu_taxonomy']) : '';
    $data['archive_data']     = AssetsManagerAdmin::getArchivePageDataFromRequest($data['for']);

    require_once __DIR__ . '/_archive-term-search-form.php';

    if (! empty($data['archive_data']['is_valid'])) {
        do_action('wpacu_admin_notices');
        require_once __DIR__ . '/_archive-page.php';
    }
    ?>
</div>
