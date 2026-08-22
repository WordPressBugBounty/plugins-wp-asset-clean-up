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
    <p><?php esc_html_e('This page is reached when a request is not valid. Asset CleanUp will use a stable non-existing test URL to load the active theme\'s 404 template.', 'wp-asset-clean-up'); ?> &#10230; <a target="_blank" href="https://codex.wordpress.org/Creating_an_Error_404_Page"><?php _e('read more', 'wp-asset-clean-up'); ?></a></p>
    <?php
    $data['archive_data'] = AssetsManagerAdmin::getArchivePageDataFromRequest($data['for']);

    if (! empty($data['archive_data']['is_valid'])) {
        do_action('wpacu_admin_notices');
        require_once __DIR__ . '/_archive-page.php';
    }
    ?>
</div>
