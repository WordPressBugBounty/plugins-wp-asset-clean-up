<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\CriticalCssAdmin;

if ( ! isset($data, $locationKey, $criticalCssConfig) ) {
    exit;
}

if ( ! CriticalCssAdmin::supportsGranularManagement($data['for']) ) {
    return;
}

if ($data['for'] === 'custom_post_types' && empty($data['custom_post_types_list'])) {
    ?>
    <div class="wpacu-critical-css-inline-message wpacu-critical-css-inline-message-warning">
        <span class="dashicons dashicons-info" aria-hidden="true"></span>
        <?php esc_html_e('No public custom post types were detected.', 'wp-asset-clean-up'); ?>
    </div>
    <?php
    return;
}

if ($data['for'] === 'custom_taxonomies' && empty($data['custom_taxonomies_list'])) {
    ?>
    <div class="wpacu-critical-css-inline-message wpacu-critical-css-inline-message-warning">
        <span class="dashicons dashicons-info" aria-hidden="true"></span>
        <?php esc_html_e('No public custom taxonomies were detected.', 'wp-asset-clean-up'); ?>
    </div>
    <?php
    return;
}

$storageContext = isset($data['critical_css_storage']) ? $data['critical_css_storage'] : array();
$scope          = isset($data['critical_css_scope']) ? $data['critical_css_scope'] : 'general';
$specificRules  = isset($data['critical_css_specific_rules']) && is_array($data['critical_css_specific_rules'])
    ? $data['critical_css_specific_rules']
    : array();
$viewData       = isset($data['critical_css_granular_view']) && is_array($data['critical_css_granular_view'])
    ? $data['critical_css_granular_view']
    : array();

$pluralLabel          = isset($viewData['plural_label']) ? $viewData['plural_label'] : '';
$singularLabel        = isset($viewData['singular_label']) ? $viewData['singular_label'] : '';
$generalLabel         = isset($viewData['general_label']) ? $viewData['general_label'] : '';
$manageObjectsUrl     = isset($viewData['manage_objects_url']) ? $viewData['manage_objects_url'] : '';
$placeholder          = isset($viewData['placeholder']) ? $viewData['placeholder'] : '';
$generalUrl           = isset($viewData['general_url']) ? $viewData['general_url'] : '';
$specificUrl          = isset($viewData['specific_url']) ? $viewData['specific_url'] : '';
$rulesCount           = isset($viewData['rules_count']) ? (int)$viewData['rules_count'] : count($specificRules);
$enabledCount         = isset($viewData['enabled_count']) ? (int)$viewData['enabled_count'] : 0;
$isSelectedObject     = ! empty($viewData['is_selected_object']);
$selectedObjectId     = isset($viewData['selected_object_id']) ? (int)$viewData['selected_object_id'] : 0;
$selectedRuleExists   = ! empty($viewData['selected_rule_exists']);
$loadSearchForm       = ! empty($viewData['load_search_form']);
$totalObjects         = isset($viewData['total_objects']) ? (int)$viewData['total_objects'] : 0;
$showAllLimit         = isset($viewData['show_all_limit']) ? (int)$viewData['show_all_limit'] : 0;
$showAllOnFocus       = ! empty($viewData['show_all_on_focus']);
$showSearchInitially  = ! empty($viewData['show_search_initially']);
$mediaPagesDeactivated = ! empty($viewData['media_permalink_disabled']);
?>
<nav class="wpacu-critical-css-scope-tabs" aria-label="<?php echo esc_attr__('Critical CSS rule scope', 'wp-asset-clean-up'); ?>">
    <a class="<?php echo $scope === 'general' ? 'wpacu-active' : ''; ?>"
       <?php if ($scope === 'general') { echo 'aria-current="page"'; } ?>
       href="<?php echo esc_url($generalUrl); ?>">
        <strong><?php esc_html_e('General', 'wp-asset-clean-up'); ?></strong>
        <span><?php echo esc_html($generalLabel); ?></span>
    </a>

    <a class="<?php echo $scope === 'specific' ? 'wpacu-active' : ''; ?>"
       <?php if ($scope === 'specific') { echo 'aria-current="page"'; } ?>
       href="<?php echo esc_url($specificUrl); ?>">
        <strong><?php esc_html_e('Specific', 'wp-asset-clean-up'); ?></strong>
        <span>
            <?php echo esc_html(sprintf(_n('%d saved rule', '%d saved rules', $rulesCount, 'wp-asset-clean-up'), $rulesCount)); ?>
        </span>
    </a>
</nav>

<?php
if ($scope !== 'specific') {
    return;
}
?>

<div class="wpacu-critical-css-specific-manager <?php echo ! empty($data['critical_css_show_editor']) ? 'wpacu-has-editor' : ''; ?>">
    <div class="wpacu-critical-css-specific-manager-header">
        <div>
            <h2><?php echo esc_html(sprintf(__('Specific %s Rules', 'wp-asset-clean-up'), $singularLabel)); ?></h2>
            <p>
                <?php
                echo esc_html(sprintf(
                    _n('%1$d rule saved, %2$d enabled.', '%1$d rules saved, %2$d enabled.', $rulesCount, 'wp-asset-clean-up'),
                    $rulesCount,
                    $enabledCount
                ));
                ?>
            </p>
        </div>

        <div class="wpacu-critical-css-specific-manager-actions">
            <?php if ($manageObjectsUrl) { ?>
                <a class="button" target="_blank" href="<?php echo esc_url($manageObjectsUrl); ?>">
                    <?php echo esc_html(sprintf(__('Manage %s', 'wp-asset-clean-up'), $pluralLabel)); ?>
                    <span class="dashicons dashicons-external" aria-hidden="true"></span>
                </a>
            <?php } ?>

            <button type="button"
                    class="button button-primary"
                    <?php if ( ! $loadSearchForm ) { echo 'disabled="disabled"'; } ?>
                    data-wpacu-critical-css-show-search>
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                <?php echo esc_html(sprintf(__('Add %s', 'wp-asset-clean-up'), $singularLabel)); ?>
            </button>
        </div>
    </div>

    <?php if ($mediaPagesDeactivated) { ?>
        <div class="wpacu-critical-css-inline-message">
            <span class="dashicons dashicons-info" aria-hidden="true"></span>
            <?php esc_html_e('Attachment pages are disabled, so another individual attachment cannot be selected.', 'wp-asset-clean-up'); ?>
        </div>
    <?php } elseif ( ! $loadSearchForm && $totalObjects < 1) { ?>
        <div class="wpacu-critical-css-inline-message">
            <span class="dashicons dashicons-info" aria-hidden="true"></span>
            <?php esc_html_e('There are no individual items available in this page type yet.', 'wp-asset-clean-up'); ?>
        </div>
    <?php } ?>

    <?php if ($loadSearchForm) { ?>
        <div id="wpacu-critical-css-object-search-panel"
             class="wpacu-critical-css-object-search-panel"
             <?php if ( ! $showSearchInitially ) { echo 'style="display: none;"'; } ?>>
            <form id="wpacu-search-form-assets-manager">
                <div class="wpacu-critical-css-add-search-header">
                    <label for="wpacu-critical-css-object-search">
                        <?php echo esc_html(sprintf(__('Add Critical CSS for a %s', 'wp-asset-clean-up'), $singularLabel)); ?>
                    </label>
                    <button type="button" class="button-link" data-wpacu-critical-css-hide-search>
                        <?php esc_html_e('Close', 'wp-asset-clean-up'); ?>
                    </button>
                </div>

                <div class="wpacu-critical-css-object-search-field">
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <input type="text"
                           id="wpacu-critical-css-object-search"
                           class="search-field"
                           value=""
                           autocomplete="off"
                           data-wpacu-show-all-on-focus="<?php echo $showAllOnFocus ? '1' : '0'; ?>"
                           data-wpacu-show-all-limit="<?php echo esc_attr($showAllLimit); ?>"
                           placeholder="<?php echo esc_attr($placeholder); ?>" />
                </div>

                <div style="display: none;" id="wpacu-search-form-assets-manager-no-results" class="wpacu-critical-css-search-no-results">
                    <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                    <?php esc_html_e('No matching items were found.', 'wp-asset-clean-up'); ?>
                </div>
            </form>

            <div style="display: none;" id="wpacu-post-chosen-loading-assets" class="wpacu-critical-css-search-loading">
                <img src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/loader-horizontal.svg?x=<?php echo time(); ?>"
                     width="120"
                     alt="" />
            </div>
        </div>
    <?php } ?>

    <?php if (empty($storageContext['is_valid'])) { ?>
        <div class="wpacu-critical-css-inline-message wpacu-critical-css-inline-message-warning">
            <span class="dashicons dashicons-warning" aria-hidden="true"></span>
            <span><?php echo esc_html($storageContext['error']); ?></span>
            <a href="<?php echo esc_url($specificUrl); ?>"><?php esc_html_e('Return to the rule list', 'wp-asset-clean-up'); ?></a>
        </div>
    <?php } elseif ($isSelectedObject) { ?>
        <div class="wpacu-critical-css-selected-target">
            <span class="dashicons dashicons-edit" aria-hidden="true"></span>

            <div class="wpacu-critical-css-selected-target-details">
                <span><?php echo $selectedRuleExists ? esc_html__('Editing rule', 'wp-asset-clean-up') : esc_html__('New rule', 'wp-asset-clean-up'); ?></span>
                <strong><?php echo esc_html($storageContext['label']); ?></strong>
                <code>ID: <?php echo (int)$storageContext['object_id']; ?></code>
            </div>

            <div class="wpacu-critical-css-selected-target-actions">
                <?php if ($storageContext['url']) { ?>
                    <a target="_blank" href="<?php echo esc_url($storageContext['url']); ?>">
                        <?php esc_html_e('View', 'wp-asset-clean-up'); ?>
                        <span class="dashicons dashicons-external" aria-hidden="true"></span>
                    </a>
                <?php } ?>
                <a href="<?php echo esc_url($specificUrl); ?>"><?php esc_html_e('Close editor', 'wp-asset-clean-up'); ?></a>
            </div>
        </div>
    <?php } ?>

    <?php if (empty($data['critical_css_show_editor'])) { ?>
        </div>
    <?php } ?>
