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
    <p>Default Taxonomy (they are found in "Posts" &#187; "Categories", accessing a category link reveals all the posts from that category) &#10230; <a target="_blank" href="https://wordpress.org/support/article/posts-categories-screen/"><?php _e('read more', 'wp-asset-clean-up'); ?></a></p>
    <?php
    $data['archive_taxonomy'] = 'category';
    $data['archive_data']     = AssetsManagerAdmin::getArchivePageDataFromRequest($data['for']);

    require_once __DIR__ . '/_archive-term-search-form.php';

    if (! empty($data['archive_data']['is_valid'])) {
        do_action('wpacu_admin_notices');
        require_once __DIR__ . '/_archive-page.php';
    }
    ?>
</div>
