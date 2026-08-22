<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\AssetsManagerAdmin;
use WpAssetCleanUp\Admin\AjaxSearchPagesAutocomplete;

if (! isset($data)) {
    exit;
}

$loadSearchFormForPages = true; // default
$showAllResultsOnFocus  = false;
$showAllResultsIfCountIsUpTo = 0;

if (isset($data['for']) && $data['for'] === 'custom_post_types') {
    $postTypesList = AssetsManagerAdmin::getCustomPostTypesWithPosts();

    if (empty($postTypesList)) {
        $loadSearchFormForPages = false;
        ?>
        <div style="padding: 10px; background: white; border-radius: 10px; border: 1px solid #c3c4c7;">
            <span class="dashicons dashicons-warning" style="color: #004567;"></span>
            <?php esc_html_e('There are no public custom post types with published or private entries available.', 'wp-asset-clean-up'); ?>
        </div>
        <?php
    } else {
        if (empty($data['post_type']) || ! isset($postTypesList[$data['post_type']])) {
            $data['post_type'] = AssetsManagerAdmin::getDefaultCustomPostTypeWithPosts();
        }

        $selectedPostTypeData = $postTypesList[$data['post_type']];
        $showAllResultsIfCountIsUpTo = AjaxSearchPagesAutocomplete::$showAllResultsIfCountIsUpToArray['custom_post_types'];
        $showAllResultsOnFocus = ((int)$selectedPostTypeData['count'] <= $showAllResultsIfCountIsUpTo);
        ?>
        <form id="wpacu-custom-post-type-form" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin: 0 0 15px;">
            <input type="hidden" name="page" value="<?php echo esc_attr(WPACU_PLUGIN_ID); ?>_assets_manager" />
            <input type="hidden" name="wpacu_for" value="custom_post_types" />
            <?php esc_html_e('Choose the custom post type first, then search within its entries:', 'wp-asset-clean-up'); ?>
            <select id="wpacu-custom-post-type-choice" name="wpacu_post_type" onchange="this.form.submit();">
                <?php foreach ($postTypesList as $postTypeKey => $postTypeData) { ?>
                    <?php
                    $postsCountText = sprintf(
                        _n('%s post', '%s posts', $postTypeData['count'], 'wp-asset-clean-up'),
                        number_format_i18n($postTypeData['count'])
                    );
                    ?>
                    <option <?php selected($data['post_type'], $postTypeKey); ?> value="<?php echo esc_attr($postTypeKey); ?>">
                        <?php echo esc_html($postTypeData['label']); ?> (<?php echo esc_html($postTypeKey); ?>, <?php echo esc_html($postsCountText); ?>)
                    </option>
                <?php } ?>
            </select>
        </form>
        <?php
    }
} elseif (isset($data['for']) && $data['for'] === 'posts') {
    $wpCountPosts = wp_count_posts('post');
    $totalPosts   = (int)$wpCountPosts->publish + (int)$wpCountPosts->private;

    if ($totalPosts > 0) {
        $showAllResultsIfCountIsUpTo = AjaxSearchPagesAutocomplete::$showAllResultsIfCountIsUpToArray['posts'];
        $showAllResultsOnFocus = ($totalPosts <= $showAllResultsIfCountIsUpTo);
    } else {
        $loadSearchFormForPages = false;
        ?>
        <div style="padding: 10px; background: white; border-radius: 10px; border: 1px solid #c3c4c7;">
            <span class="dashicons dashicons-warning" style="color: #004567;"></span>
            There aren't any posts added in <a style="text-decoration: none;" target="_blank" href="<?php echo esc_url(admin_url('edit.php')); ?>"><span class="dashicons dashicons-admin-post"></span> "Posts" --&gt; "All Posts"</a>.
        </div>
        <?php
    }
} elseif (isset($data['for']) && $data['for'] === 'pages') {
    $pages = get_pages(array('post_type' => 'page', 'post_status' => array('publish', 'private')));

    if (empty($pages)) {
        $loadSearchFormForPages = false;
        ?>
        <div style="padding: 10px; background: white; border-radius: 10px; border: 1px solid #c3c4c7;">
            <span class="dashicons dashicons-warning" style="color: #004567;"></span>
            There aren't any pages added in <a style="text-decoration: none;" target="_blank" href="<?php echo esc_url(admin_url('edit.php?post_type=page')); ?>"><span class="dashicons dashicons-admin-page"></span> "Pages" --&gt; "All Pages"</a>.
        </div>
        <?php
    } else {
        $showAllResultsIfCountIsUpTo = AjaxSearchPagesAutocomplete::$showAllResultsIfCountIsUpToArray['pages'];
        $showAllResultsOnFocus = (count($pages) <= $showAllResultsIfCountIsUpTo);
    }
} elseif (isset($data['for']) && $data['for'] === 'media_attachment') {
    $attachmentsCount = wp_count_posts('attachment', 'readable');

    $totalAttachments = 0;

    foreach ((array) $attachmentsCount as $status => $count) {
        if ($status === 'trash' || $status === 'auto-draft') {
            continue;
        }

        $totalAttachments += (int) $count;
    }

    if ($totalAttachments > 0) {
        $showAllResultsIfCountIsUpTo = AjaxSearchPagesAutocomplete::$showAllResultsIfCountIsUpToArray['media'];

        $showAllResultsOnFocus = ($totalAttachments <= $showAllResultsIfCountIsUpTo);
    } else {
        $loadSearchFormForPages = false;
        ?>
        <div style="padding: 10px; background: white; border-radius: 10px; border: 1px solid #c3c4c7;">
            <span class="dashicons dashicons-warning" style="color: #004567;"></span>
            <?php esc_html_e(
                'There are no media files available. The search bar will be available after at least one media file is added.',
                'wp-asset-clean-up'
            ); ?>
        </div>
        <?php
    }
}

if ($loadSearchFormForPages) {
    $postTypeObject = $data['post_type'] ? get_post_type_object($data['post_type']) : null;
    $postTypeLabel  = ($postTypeObject && isset($postTypeObject->labels->singular_name))
        ? $postTypeObject->labels->singular_name
        : $data['post_type'];

    $searchPlaceholderText = sprintf(
        __('You can type a keyword or the ID to search the %s for which you want to manage its CSS/JS (e.g. unloading)', 'wp-asset-clean-up'),
        $postTypeLabel
    );

    if ($data['post_type'] === 'product') {
        $searchPlaceholderText = sprintf(
            __('You can type a keyword or the ID to search the %s for which you want to manage its CSS/JS (e.g. unloading)', 'wp-asset-clean-up'),
            'WooCommerce '.$postTypeLabel
        );
    }
    ?>
    <form id="wpacu-search-form-assets-manager">
        <?php esc_html_e('Load assets manager for:', 'wp-asset-clean-up'); ?>
        <input type="text"
               class="search-field"
               value=""
               data-wpacu-show-all-on-focus="<?php echo $showAllResultsOnFocus ? '1' : '0'; ?>"
               data-wpacu-show-all-limit="<?php echo esc_attr($showAllResultsIfCountIsUpTo); ?>"
               placeholder="<?php echo esc_attr($searchPlaceholderText); ?>"
               style="max-width: 800px; width: 100%; padding-right: 15px;" />
        * <small><?php esc_html_e('Once the entry is selected, the CSS & JS manager will load to manage its assets.', 'wp-asset-clean-up'); ?></small>
        <div style="display: none; padding: 10px; color: #cc0000;" id="wpacu-search-form-assets-manager-no-results">
            <span class="dashicons dashicons-warning"></span>
            <?php esc_html_e('There are no results based on your search.', 'wp-asset-clean-up'); ?>
            <?php echo esc_html(sprintf(__('Remember that you can also use the %s ID in the input.', 'wp-asset-clean-up'), $postTypeLabel)); ?>
        </div>
    </form>

    <div style="display: none;" id="wpacu-post-chosen-loading-assets">
        <img style="margin: 2px 0 4px;"
             src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/loader-horizontal.svg?x=<?php echo time(); ?>"
             align="top"
             width="120"
             alt="" />
    </div>
    <?php
}
