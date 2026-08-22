<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\AssetsManagerAdmin;
use WpAssetCleanUp\Misc;

if (! isset($data)) {
    exit;
}

$postTypeView = sanitize_key(Misc::getVar('get', 'wpacu_post_type_view', 'singular'));

if (! in_array($postTypeView, array('singular', 'archives'), true)) {
    $postTypeView = 'singular';
}

$baseUrl = admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_for=custom_post_types');
?>
<div style="margin: 25px 0 0;">
    <p>Popular examples: 'product' created by WooCommerce, 'download' created by Easy Digital Downloads etc. &#10230; <a target="_blank" href="https://wordpress.org/support/article/post-types/#custom-post-types"><?php _e('read more', 'wp-asset-clean-up'); ?></a></p>

    <nav class="wpacu-nav-tab-wrapper" style="margin: 15px 0 20px;">
        <a href="<?php echo esc_url($baseUrl . '&wpacu_post_type_view=singular'); ?>"
           class="wpacu-nav-tab <?php if ($postTypeView === 'singular') { ?>wpacu-nav-tab-active<?php } ?>">
            <?php esc_html_e('Individual Pages', 'wp-asset-clean-up'); ?>
        </a>
        <a href="<?php echo esc_url($baseUrl . '&wpacu_post_type_view=archives'); ?>"
           class="wpacu-nav-tab <?php if ($postTypeView === 'archives') { ?>wpacu-nav-tab-active<?php } ?>">
            <?php esc_html_e('Archive Pages', 'wp-asset-clean-up'); ?>
        </a>
    </nav>

    <?php
    $data['dashboard_edit_not_allowed'] = false;

    require_once __DIR__ . '/_common/_is-dashboard-edit-allowed.php';

    if ($data['dashboard_edit_not_allowed']) {
        return;
    }

    if ($postTypeView === 'archives') {
        $archives = AssetsManagerAdmin::getCustomPostTypeArchives();

        if (empty($archives)) {
            ?>
            <div style="padding: 10px; background: white; border-radius: 10px; border: 1px solid #c3c4c7;">
                <span class="dashicons dashicons-warning" style="color: #cc0000;"></span>
                <?php esc_html_e('No public custom post type archive is available on this website.', 'wp-asset-clean-up'); ?>
            </div>
            <?php
            return;
        }

        $selectedPostType = sanitize_key(Misc::getVar('get', 'wpacu_post_type', ''));

        if ($selectedPostType === '' || ! isset($archives[$selectedPostType])) {
            $selectedPostType = AssetsManagerAdmin::getDefaultCustomPostTypeArchive();
        }
        ?>
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin: 0 0 20px;">
            <input type="hidden" name="page" value="<?php echo esc_attr(WPACU_PLUGIN_ID . '_assets_manager'); ?>" />
            <input type="hidden" name="wpacu_for" value="custom_post_types" />
            <input type="hidden" name="wpacu_post_type_view" value="archives" />

            <label for="wpacu-custom-post-type-archive-choice"><strong><?php esc_html_e('Custom Post Type Archive', 'wp-asset-clean-up'); ?>:</strong></label>
            <select id="wpacu-custom-post-type-archive-choice" name="wpacu_post_type" onchange="this.form.submit();">
                <?php foreach ($archives as $postTypeKey => $archive) { ?>
                    <option value="<?php echo esc_attr($postTypeKey); ?>" <?php selected($selectedPostType, $postTypeKey); ?>>
                        <?php echo esc_html($archive['label'] . ' (' . $postTypeKey . ')'); ?>
                    </option>
                <?php } ?>
            </select>
            <noscript><button type="submit" class="button button-secondary"><?php esc_html_e('Load CSS/JS Manager', 'wp-asset-clean-up'); ?></button></noscript>
        </form>
        <?php
        $data['archive_data'] = AssetsManagerAdmin::getArchivePageDataFromRequest('custom_post_types');

        if (! empty($data['archive_data']['is_valid'])) {
            do_action('wpacu_admin_notices');
            require_once __DIR__ . '/_archive-page.php';
        }

        return;
    }

    $data['post_id'] = (isset($_GET['wpacu_post_id']) && $_GET['wpacu_post_id']) ? (int)$_GET['wpacu_post_id'] : false;
    $data['post_type'] = sanitize_key(Misc::getVar('get', 'wpacu_post_type', ''));

    if ($data['post_id']) {
        // There's a POST ID requested in the URL / Show the assets
        $postTypeFromPostId = get_post_type($data['post_id']);

        if ($postTypeFromPostId) {
            $data['post_type'] = $postTypeFromPostId;
        }

        do_action('wpacu_admin_notices');
        require_once __DIR__ . '/_singular-page.php';
    } else {
        // There's no POST ID requested; select a valid context for the search form.
        $postTypesList = AssetsManagerAdmin::getCustomPostTypesWithPosts();

        if ($data['post_type'] === '' || ! isset($postTypesList[$data['post_type']])) {
            $data['post_type'] = AssetsManagerAdmin::getDefaultCustomPostTypeWithPosts();
        }

        require_once __DIR__ . '/_singular-page-search-form.php';
    }
    ?>
</div>
