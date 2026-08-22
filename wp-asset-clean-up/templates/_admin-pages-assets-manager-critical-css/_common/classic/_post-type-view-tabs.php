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
$singularUrl     = CriticalCssAdmin::getCustomPostTypesManagementUrl('singular', $currentPostType);
$archivesUrl     = CriticalCssAdmin::getCustomPostTypesManagementUrl('archives', $currentPostType);
?>
<nav class="wpacu-nav-tab-wrapper"
     style="margin: 15px 0 20px;"
     aria-label="<?php echo esc_attr__('Custom Post Types page view', 'wp-asset-clean-up'); ?>">
    <a href="<?php echo esc_url($singularUrl); ?>"
       class="wpacu-nav-tab <?php echo $postTypeView === 'singular' ? 'wpacu-nav-tab-active' : ''; ?>"
       <?php if ($postTypeView === 'singular') { echo 'aria-current="page"'; } ?>>
        <?php esc_html_e('Singular', 'wp-asset-clean-up'); ?>
    </a>

    <a href="<?php echo esc_url($archivesUrl); ?>"
       class="wpacu-nav-tab <?php echo $postTypeView === 'archives' ? 'wpacu-nav-tab-active' : ''; ?>"
       <?php if ($postTypeView === 'archives') { echo 'aria-current="page"'; } ?>>
        <?php esc_html_e('Archives', 'wp-asset-clean-up'); ?>
    </a>
</nav>
