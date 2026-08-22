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
    <p><?php esc_html_e('Shows all posts belonging to a specific author.', 'wp-asset-clean-up'); ?></p>
    <?php
    $data['archive_data'] = AssetsManagerAdmin::getArchivePageDataFromRequest($data['for']);

    require_once __DIR__ . '/_archive-user-search-form.php';

    if (! empty($data['archive_data']['is_valid'])) {
        do_action('wpacu_admin_notices');
        require_once __DIR__ . '/_archive-page.php';
    }
    ?>
</div>
