<?php
/*
 * No direct access to this file
 */

if ( ! isset($data, $storageContext, $viewData, $specificRules, $rulesCount, $singularLabel, $pluralLabel, $specificUrl) ) {
    exit;
}

$manageObjectsUrl      = isset($viewData['manage_objects_url']) ? $viewData['manage_objects_url'] : '';
$placeholder           = isset($viewData['placeholder']) ? $viewData['placeholder'] : '';
$loadSearchForm        = ! empty($viewData['load_search_form']);
$totalObjects          = isset($viewData['total_objects']) ? (int)$viewData['total_objects'] : 0;
$showAllLimit          = isset($viewData['show_all_limit']) ? (int)$viewData['show_all_limit'] : 0;
$showAllOnFocus        = ! empty($viewData['show_all_on_focus']);
$showSearchInitially   = ! empty($viewData['show_search_initially']);
$mediaPagesDeactivated = ! empty($viewData['media_permalink_disabled']);

if ($data['for'] === 'posts') {
    $addCriticalCssButtonLabel = __('Add Critical CSS for a Post', 'wp-asset-clean-up');
} elseif ($data['for'] === 'pages') {
    $addCriticalCssButtonLabel = __('Add Critical CSS for a Page', 'wp-asset-clean-up');
} elseif ($data['for'] === 'media_attachment') {
    $addCriticalCssButtonLabel = __('Add Critical CSS for an Attachment Page', 'wp-asset-clean-up');
} elseif ($data['for'] === 'custom_post_types') {
    $addCriticalCssButtonLabel = sprintf(
        __('Add Critical CSS for a %s page', 'wp-asset-clean-up'),
        $singularLabel
    );
} elseif (in_array($data['for'], array('category', 'tag', 'custom_taxonomies'), true)) {
    $addCriticalCssButtonLabel = sprintf(
        __('Add Critical CSS for a %s archive', 'wp-asset-clean-up'),
        $singularLabel
    );
} elseif ($data['for'] === 'author') {
    $addCriticalCssButtonLabel = __('Add Critical CSS for an Author archive', 'wp-asset-clean-up');
} else {
    $addCriticalCssButtonLabel = sprintf(
        __('Add Critical CSS for a specific %s', 'wp-asset-clean-up'),
        $singularLabel
    );
}

$manageObjectsButtonLabel = sprintf(
    __('Manage %s in WordPress', 'wp-asset-clean-up'),
    $pluralLabel
);
?>
<div class="wpacu-critical-css-classic-specific-header">
    <div>
        <strong><?php echo esc_html(sprintf(__('Specific %s Critical CSS', 'wp-asset-clean-up'), $singularLabel)); ?></strong>
        <span>
            <?php echo esc_html(sprintf(
                _n('%1$d saved rule, %2$d enabled', '%1$d saved rules, %2$d enabled', $rulesCount, 'wp-asset-clean-up'),
                $rulesCount,
                isset($viewData['enabled_count']) ? (int)$viewData['enabled_count'] : 0
            )); ?>
        </span>
    </div>

    <div class="wpacu-critical-css-classic-specific-actions">
        <button type="button"
                class="button button-primary"
                <?php if ( ! $loadSearchForm ) { echo 'disabled="disabled"'; } ?>
                data-wpacu-critical-css-show-search>
            <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
            <?php echo esc_html($addCriticalCssButtonLabel); ?>
        </button>

        <?php if ($manageObjectsUrl) { ?>
            <a class="button" target="_blank" href="<?php echo esc_url($manageObjectsUrl); ?>">
                <?php echo esc_html($manageObjectsButtonLabel); ?>
                <span class="dashicons dashicons-external" aria-hidden="true"></span>
            </a>
        <?php } ?>
    </div>
</div>

<?php if ($mediaPagesDeactivated) { ?>
    <p class="wpacu-critical-css-classic-message">
        <span class="dashicons dashicons-info" aria-hidden="true"></span>
        <?php esc_html_e('Attachment pages are disabled, so another individual attachment cannot be selected.', 'wp-asset-clean-up'); ?>
    </p>
<?php } elseif ( ! $loadSearchForm && $totalObjects < 1) { ?>
    <p class="wpacu-critical-css-classic-message">
        <span class="dashicons dashicons-info" aria-hidden="true"></span>
        <?php esc_html_e('There are no individual items available in this page type yet.', 'wp-asset-clean-up'); ?>
    </p>
<?php } ?>

<?php if ($loadSearchForm) { ?>
    <div id="wpacu-critical-css-object-search-panel"
         class="wpacu-critical-css-classic-search-panel"
         <?php if ( ! $showSearchInitially ) { echo 'style="display: none;"'; } ?>>
        <form id="wpacu-search-form-assets-manager">
            <div class="wpacu-critical-css-classic-search-heading">
                <label for="wpacu-critical-css-object-search">
                    <?php echo esc_html($addCriticalCssButtonLabel); ?>
                </label>
                <button type="button" class="button-link" data-wpacu-critical-css-hide-search>
                    <?php esc_html_e('Close', 'wp-asset-clean-up'); ?>
                </button>
            </div>

            <input type="text"
                   id="wpacu-critical-css-object-search"
                   class="search-field"
                   value=""
                   autocomplete="off"
                   data-wpacu-show-all-on-focus="<?php echo $showAllOnFocus ? '1' : '0'; ?>"
                   data-wpacu-show-all-limit="<?php echo esc_attr($showAllLimit); ?>"
                   placeholder="<?php echo esc_attr($placeholder); ?>" />

            <div style="display: none;" id="wpacu-search-form-assets-manager-no-results" class="wpacu-critical-css-classic-no-results">
                <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                <?php esc_html_e('No matching items were found.', 'wp-asset-clean-up'); ?>
            </div>
        </form>

        <div style="display: none;" id="wpacu-post-chosen-loading-assets" class="wpacu-critical-css-classic-loading">
            <img src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/loader-horizontal.svg?x=<?php echo time(); ?>"
                 width="120"
                 alt="" />
        </div>
    </div>
<?php } ?>

<?php if (empty($storageContext['is_valid'])) { ?>
    <div class="wpacu-notice wpacu-warning wpacu-critical-css-classic-target-notice">
        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
        <?php echo esc_html($storageContext['error']); ?>
        <a href="<?php echo esc_url($specificUrl); ?>"><?php esc_html_e('Return to the rule list', 'wp-asset-clean-up'); ?></a>
    </div>
<?php } elseif ($isSelectedObject) { ?>
    <div class="wpacu-critical-css-classic-target-notice">
        <div>
            <strong><?php echo $selectedRuleExists ? esc_html__('Editing:', 'wp-asset-clean-up') : esc_html__('New rule:', 'wp-asset-clean-up'); ?></strong>
            <?php echo esc_html($storageContext['label']); ?>
            <code>ID: <?php echo (int)$storageContext['object_id']; ?></code>
        </div>
        <div class="wpacu-critical-css-classic-specific-actions">
            <?php if ($storageContext['url']) { ?>
                <a style="text-decoration: none;" target="_blank" href="<?php echo esc_url($storageContext['url']); ?>">
                    <?php esc_html_e('View', 'wp-asset-clean-up'); ?>
                    <span class="dashicons dashicons-external" aria-hidden="true"></span>
                </a>
            <?php } ?>
            <a class="button" href="<?php echo esc_url($specificUrl); ?>">
                <?php esc_html_e('Close editor', 'wp-asset-clean-up'); ?>
                <span class="dashicons dashicons-exit" aria-hidden="true"></span>
            </a>
        </div>
    </div>
<?php } ?>
