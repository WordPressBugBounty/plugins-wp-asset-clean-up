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

$scope           = isset($data['critical_css_scope']) ? $data['critical_css_scope'] : 'general';
$postTypeView    = isset($data['critical_css_post_type_view']) ? $data['critical_css_post_type_view'] : 'singular';
$currentPostType = isset($data['chosen_post_type']) ? $data['chosen_post_type'] : '';
$options         = array();
$selectorLabel   = '';
$helpText        = '';

if ($postTypeView === 'archives') {
    $selectorLabel = __('Custom Post Type Archive', 'wp-asset-clean-up');
    $helpText      = __('The archive rule is separate from Critical CSS used on singular entries.', 'wp-asset-clean-up');

    foreach ((array)(isset($data['custom_post_type_archives_list']) ? $data['custom_post_type_archives_list'] : array()) as $postTypeKey => $archiveData) {
        $archiveLabel = isset($archiveData['label']) && $archiveData['label']
            ? $archiveData['label']
            : $postTypeKey;
        $locationKeyForItem = 'custom_post_type_archive_' . $postTypeKey;

        $options[$postTypeKey] = array(
            'label' => sprintf(__('%s Archive', 'wp-asset-clean-up'), $archiveLabel),
            'url'   => CriticalCssAdmin::getGeneralManagementUrl('custom_post_type_archives', $locationKeyForItem)
        );
    }
} else {
    $selectorLabel = __('Custom Post Type', 'wp-asset-clean-up');
    $helpText      = __('Choose the singular content type before managing its General or Specific rules.', 'wp-asset-clean-up');

    foreach ((array)(isset($data['custom_post_types_list']) ? $data['custom_post_types_list'] : array()) as $postTypeKey => $postTypeLabel) {
        $locationKeyForItem = 'custom_post_type_' . $postTypeKey;

        $options[$postTypeKey] = array(
            'label' => $postTypeLabel,
            'url'   => $scope === 'specific'
                ? CriticalCssAdmin::getSpecificManagementUrl('custom_post_types', $locationKeyForItem)
                : CriticalCssAdmin::getGeneralManagementUrl('custom_post_types', $locationKeyForItem)
        );
    }
}

if (empty($options)) {
    ?>
    <div class="wpacu-critical-css-inline-message wpacu-critical-css-inline-message-warning">
        <span class="dashicons dashicons-info" aria-hidden="true"></span>
        <?php echo $postTypeView === 'archives'
            ? esc_html__('No public custom post type archives were detected.', 'wp-asset-clean-up')
            : esc_html__('No public custom post types were detected.', 'wp-asset-clean-up'); ?>
    </div>
    <?php
    return;
}
?>
<div class="wpacu-critical-css-context-bar wpacu-critical-css-post-type-selector-bar">
    <div class="wpacu-critical-css-context-choice">
        <label for="wpacu-critical-css-post-type-choice">
            <?php echo esc_html($selectorLabel); ?>
        </label>

        <select id="wpacu-critical-css-post-type-choice"
                onchange="if (this.value) { window.location.href = this.value; }">
            <?php foreach ($options as $postTypeKey => $optionData) { ?>
                <option value="<?php echo esc_url($optionData['url']); ?>"
                    <?php selected($currentPostType, $postTypeKey); ?>>
                    <?php echo esc_html($optionData['label']); ?>
                </option>
            <?php } ?>
        </select>
    </div>

    <div class="wpacu-critical-css-context-help">
        <span><?php echo esc_html($helpText); ?></span>
    </div>
</div>
