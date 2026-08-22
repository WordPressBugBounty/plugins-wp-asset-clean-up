<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\AssetsManagerAdmin;

if (! isset($data)) {
    exit;
}

$data['archive_data'] = AssetsManagerAdmin::getArchivePageDataFromRequest('search');
?>
<div style="margin: 25px 0 0;">
    <?php
    require_once __DIR__ . '/_archive-simple-form.php';

    if (! empty($data['archive_data']['error'])) {
        ?>
        <div style="padding: 10px; background: white; border-radius: 10px; border: 1px solid #c3c4c7;">
            <span class="dashicons dashicons-warning" style="color: #cc0000;"></span>
            <?php echo esc_html($data['archive_data']['error']); ?>
        </div>
        <?php
        return;
    }

    if (! empty($data['archive_data']['is_valid'])) {
        do_action('wpacu_admin_notices');
        require_once __DIR__ . '/_archive-page.php';
    }
    ?>
</div>
