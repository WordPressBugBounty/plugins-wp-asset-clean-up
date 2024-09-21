<?php
/*
 * No direct access to this file
 */
if ( ! isset($data) ) {
    exit;
}

$assetsManagerTabs = array(
    'homepage'          => __('Homepage', 'wp-asset-clean-up'),
    'posts'             => __('Posts', 'wp-asset-clean-up'),
    'pages'             => __('Pages', 'wp-asset-clean-up'),
    'custom_post_types' => __('Custom Post Types', 'wp-asset-clean-up'),
    'media_attachment'  => __('Media', 'wp-asset-clean-up'),
    'category'          => __('Category', 'wp-asset-clean-up'),
    'tag'               => __('Tag', 'wp-asset-clean-up'),
    'custom_taxonomies' => __('Custom Taxonomy', 'wp-asset-clean-up'),
    'search'            => __('Search', 'wp-asset-clean-up'),
    'author'            => __('Author', 'wp-asset-clean-up'),
    'date'              => __('Date', 'wp-asset-clean-up'),
    '404_not_found'     => __('404 Not Found', 'wp-asset-clean-up')
);
?>
<nav class="wpacu-nav-tab-wrapper wpacu-nav-assets-manager">
    <?php
    foreach ($assetsManagerTabs as $assetsManagerTabKey => $assetsManagerTabTitle) {
        ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_for='.$assetsManagerTabKey)); ?>"
           class="wpacu-nav-tab <?php if ($data['for'] === $assetsManagerTabKey) { ?>wpacu-nav-tab-active<?php } ?>">
            <?php echo esc_html($assetsManagerTabTitle); ?>
        </a>
        <?php
    }
    ?>
</nav>
<div class="wpacu_clearfix"></div>
<?php
if (isset($data['for']) && $data['for']) {
    $adminPagesAssetsManagerTplDir = __DIR__ . '/_admin-pages-assets-manager-manage-css-js/';

    // e.g. 'custom_taxonomies' is the 'for' value
    // The file name would be '_custom-taxonomy.php'
    $formatNameToFileStandard = str_replace('_', '-', $data['for']);
    $maybeIncludeFile         = $adminPagesAssetsManagerTplDir . '_' . $formatNameToFileStandard . '.php';

    if (is_file($maybeIncludeFile)) {
        include_once $maybeIncludeFile;
    }
}
