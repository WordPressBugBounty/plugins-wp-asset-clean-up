<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\CriticalCssAdmin;

if ( ! isset($data) ) {
    exit;
}

$tabs = array(
    'homepage'                  => __('Homepage', 'wp-asset-clean-up'),
    'posts'                     => __('Posts', 'wp-asset-clean-up'),
    'pages'                     => __('Pages', 'wp-asset-clean-up'),
    'custom_post_types'         => __('Custom Post Types', 'wp-asset-clean-up'),
    'media_attachment'          => __('Media', 'wp-asset-clean-up'),
    'category'                  => __('Category', 'wp-asset-clean-up'),
    'tag'                       => __('Tag', 'wp-asset-clean-up'),
    'custom_taxonomies'         => __('Custom Taxonomy', 'wp-asset-clean-up'),
    'search'                    => __('Search', 'wp-asset-clean-up'),
    'author'                    => __('Author', 'wp-asset-clean-up'),
    'date'                      => __('Date', 'wp-asset-clean-up'),
    '404_not_found'             => __('404 Not Found', 'wp-asset-clean-up')
);
?>
<nav id="wpacu-critical-css-manager-tab-menu" class="wpacu-nav-tab-wrapper wpacu-nav-critical-css-manager">
    <?php foreach ($tabs as $wpacuFor => $tabLabel) {
        $classToAppend = CriticalCssAdmin::classToAppendToCriticalCssNavTab($data, $wpacuFor);
        $tabUrl = $wpacuFor === 'custom_post_types'
            ? CriticalCssAdmin::getCustomPostTypesManagementUrl('singular')
            : admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=' . rawurlencode($wpacuFor));
        ?>
        <a href="<?php echo esc_url($tabUrl); ?>"
           class="wpacu-nav-tab <?php echo esc_attr($classToAppend); ?>">
            <?php echo esc_html($tabLabel); ?>
            <span class="wpacu-circle-status"></span>
        </a>
    <?php } ?>

    <a href="<?php echo esc_url(admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=via_code')); ?>"
       class="wpacu-nav-tab <?php if ($data['for'] === 'via_code') { ?>wpacu-nav-tab-active<?php } ?>"
       style="padding: 6px 12px 6px 10px;">
        <span class="dashicons dashicons-editor-code"
              style="vertical-align: middle; margin-right: -4px; margin-top: -1px;"></span>&nbsp;
        <?php esc_html_e('Via Code', 'wp-asset-clean-up'); ?>
    </a>
</nav>
