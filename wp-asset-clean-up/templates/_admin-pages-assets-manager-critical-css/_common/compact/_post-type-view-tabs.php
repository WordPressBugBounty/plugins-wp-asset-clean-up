<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\CriticalCssAdmin;

if ( ! isset($data) ) {
    exit;
}

if ( ! in_array($data['for'], array('custom_post_types', 'custom_post_type_archives'), true) ) {
    return;
}

$postTypeView    = isset($data['critical_css_post_type_view']) ? $data['critical_css_post_type_view'] : 'singular';
$currentPostType = isset($data['chosen_post_type']) ? $data['chosen_post_type'] : '';
?>
<nav class="wpacu-critical-css-scope-tabs wpacu-critical-css-post-type-view-tabs"
     aria-label="<?php echo esc_attr__('Custom Post Types page view', 'wp-asset-clean-up'); ?>">
    <a class="<?php echo $postTypeView === 'singular' ? 'wpacu-active' : ''; ?>"
       <?php if ($postTypeView === 'singular') { echo 'aria-current="page"'; } ?>
       href="<?php echo esc_url(CriticalCssAdmin::getCustomPostTypesManagementUrl('singular', $currentPostType)); ?>">
        <strong><?php esc_html_e('Singular', 'wp-asset-clean-up'); ?></strong>
        <span><?php esc_html_e('Individual custom post type entries', 'wp-asset-clean-up'); ?></span>
    </a>

    <a class="<?php echo $postTypeView === 'archives' ? 'wpacu-active' : ''; ?>"
       <?php if ($postTypeView === 'archives') { echo 'aria-current="page"'; } ?>
       href="<?php echo esc_url(CriticalCssAdmin::getCustomPostTypesManagementUrl('archives', $currentPostType)); ?>">
        <strong><?php esc_html_e('Archives', 'wp-asset-clean-up'); ?></strong>
        <span><?php esc_html_e('Main archive and pagination pages', 'wp-asset-clean-up'); ?></span>
    </a>
</nav>
