<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\CriticalCssAdmin;

if ( ! isset($data, $criticalCssConfig) ) {
    exit;
}

$scope = isset($data['critical_css_scope']) ? $data['critical_css_scope'] : 'general';
$items = array();
$intro = '';

if ($data['for'] === 'custom_post_types' && ! empty($data['custom_post_types_list'])) {
    $intro = __('Choose the custom post type for which you want to manage Critical CSS on singular pages:', 'wp-asset-clean-up');

    foreach ($data['custom_post_types_list'] as $postTypeKey => $postTypeLabel) {
        $locationKeyForItem = 'custom_post_type_' . $postTypeKey;
        $items[$postTypeKey] = array(
            'label'      => $postTypeLabel,
            'url'        => $scope === 'specific'
                ? CriticalCssAdmin::getSpecificManagementUrl('custom_post_types', $locationKeyForItem)
                : CriticalCssAdmin::getGeneralManagementUrl('custom_post_types', $locationKeyForItem),
            'is_current' => isset($data['chosen_post_type']) && $data['chosen_post_type'] === $postTypeKey,
            'is_enabled' => (isset($criticalCssConfig[$locationKeyForItem]['enable']) && $criticalCssConfig[$locationKeyForItem]['enable'])
                || CriticalCssAdmin::hasEnabledGranularCriticalCssForLocation($locationKeyForItem),
            'status_key' => $postTypeKey . '_post_type'
        );
    }
} elseif ($data['for'] === 'custom_post_type_archives' && ! empty($data['custom_post_type_archives_list'])) {
    $intro = __('Choose the custom post type archive for which you want to manage Critical CSS:', 'wp-asset-clean-up');

    foreach ($data['custom_post_type_archives_list'] as $postTypeKey => $archiveData) {
        $locationKeyForItem = 'custom_post_type_archive_' . $postTypeKey;
        $archiveLabel = isset($archiveData['label']) && $archiveData['label']
            ? sprintf(__('%s Archive', 'wp-asset-clean-up'), $archiveData['label'])
            : $postTypeKey;

        $items[$postTypeKey] = array(
            'label'      => $archiveLabel,
            'url'        => CriticalCssAdmin::getGeneralManagementUrl('custom_post_type_archives', $locationKeyForItem),
            'is_current' => isset($data['chosen_post_type']) && $data['chosen_post_type'] === $postTypeKey,
            'is_enabled' => isset($criticalCssConfig[$locationKeyForItem]['enable']) && $criticalCssConfig[$locationKeyForItem]['enable'],
            'status_key' => $postTypeKey . '_post_type_archive'
        );
    }
} elseif ($data['for'] === 'custom_taxonomies' && ! empty($data['custom_taxonomies_list'])) {
    $intro = __('Choose the custom taxonomy for which you want to manage Critical CSS:', 'wp-asset-clean-up');

    foreach ($data['custom_taxonomies_list'] as $taxonomyKey => $taxonomyLabel) {
        $locationKeyForItem = 'custom_taxonomy_' . $taxonomyKey;
        $items[$taxonomyKey] = array(
            'label'      => $taxonomyLabel,
            'url'        => $scope === 'specific'
                ? CriticalCssAdmin::getSpecificManagementUrl('custom_taxonomies', $locationKeyForItem)
                : CriticalCssAdmin::getGeneralManagementUrl('custom_taxonomies', $locationKeyForItem),
            'is_current' => isset($data['chosen_taxonomy']) && $data['chosen_taxonomy'] === $taxonomyKey,
            'is_enabled' => (isset($criticalCssConfig[$locationKeyForItem]['enable']) && $criticalCssConfig[$locationKeyForItem]['enable'])
                || CriticalCssAdmin::hasEnabledGranularCriticalCssForLocation($locationKeyForItem),
            'status_key' => $taxonomyKey . '_taxonomy'
        );
    }
}

if (empty($items)) {
    return;
}
?>
<div class="wpacu-critical-css-classic-subtype-selector">
    <p><?php echo esc_html($intro); ?></p>
    <ul id="wpacu_custom_pages_nav_links">
        <?php foreach ($items as $item) { ?>
            <li class="<?php echo $item['is_current'] ? 'wpacu-current' : ''; ?>">
                <a href="<?php echo esc_url($item['url']); ?>">
                    <?php echo wp_kses_post($item['label']); ?>
                    <span data-wpacu-custom-page-type="<?php echo esc_attr($item['status_key']); ?>"
                          class="wpacu-circle-status <?php echo $item['is_enabled'] ? 'wpacu-on' : 'wpacu-off'; ?>"></span>
                </a>
            </li>
        <?php } ?>
    </ul>
</div>
