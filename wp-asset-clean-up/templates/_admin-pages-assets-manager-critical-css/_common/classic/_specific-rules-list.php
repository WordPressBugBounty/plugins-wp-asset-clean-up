<?php
/*
 * No direct access to this file
 */

if ( ! isset($specificRules, $rulesCount, $selectedObjectId, $singularLabel) ) {
    exit;
}

$emptyStateAddLabel = isset($addCriticalCssButtonLabel) && $addCriticalCssButtonLabel
    ? $addCriticalCssButtonLabel
    : sprintf(__('Add Critical CSS for a specific %s', 'wp-asset-clean-up'), $singularLabel);
?>
<div class="wpacu-critical-css-classic-rules">
    <div class="wpacu-critical-css-classic-rules-heading">
        <div>
            <strong><?php esc_html_e('Saved specific rules', 'wp-asset-clean-up'); ?></strong>
            <span><?php echo esc_html(sprintf(_n('%d entry', '%d entries', $rulesCount, 'wp-asset-clean-up'), $rulesCount)); ?></span>
        </div>

        <?php if ($rulesCount > 4) { ?>
            <label class="wpacu-critical-css-classic-filter">
                <span class="dashicons dashicons-search" aria-hidden="true"></span>
                <input type="search"
                       id="wpacu-critical-css-existing-rules-filter"
                       placeholder="<?php echo esc_attr__('Filter saved rules…', 'wp-asset-clean-up'); ?>" />
            </label>
        <?php } ?>
    </div>

    <?php if ($rulesCount > 0) { ?>
        <div class="wpacu-critical-css-classic-table-wrap">
            <table class="widefat striped wpacu-critical-css-classic-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html($singularLabel); ?></th>
                        <th><?php esc_html_e('Status', 'wp-asset-clean-up'); ?></th>
                        <th><?php esc_html_e('Output', 'wp-asset-clean-up'); ?></th>
                        <th><?php esc_html_e('Actions', 'wp-asset-clean-up'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($specificRules as $specificRule) {
                        $isCurrent  = $selectedObjectId > 0 && (int)$specificRule['object_id'] === $selectedObjectId;
                        $searchText = strtolower($specificRule['label'] . ' ' . $specificRule['object_id'] . ' ' . $specificRule['url']);
                        ?>
                        <tr class="wpacu-critical-css-rule-row <?php echo $isCurrent ? 'wpacu-current' : ''; ?>"
                            data-wpacu-rule-search="<?php echo esc_attr($searchText); ?>">
                            <td>
                                <div class="wpacu-critical-css-classic-rule-title">
                                    <span class="wpacu-critical-css-classic-status-dot <?php echo ! empty($specificRule['enable']) ? 'wpacu-enabled' : 'wpacu-disabled'; ?>"
                                          aria-hidden="true"></span>
                                    <strong><?php echo esc_html($specificRule['label']); ?></strong>
                                    <code>ID: <?php echo (int)$specificRule['object_id']; ?></code>
                                    <?php if ($isCurrent) { ?>
                                        <span class="wpacu-critical-css-classic-editing"><?php esc_html_e('Editing', 'wp-asset-clean-up'); ?></span>
                                    <?php } ?>
                                </div>
                                <?php if ($specificRule['url']) { ?>
                                    <span class="wpacu-critical-css-classic-rule-url"><?php echo esc_html($specificRule['url']); ?></span>
                                <?php } ?>
                            </td>
                            <td>
                                <span class="wpacu-critical-css-classic-status-text <?php echo ! empty($specificRule['enable']) ? 'wpacu-enabled' : 'wpacu-disabled'; ?>">
                                    <?php echo ! empty($specificRule['enable'])
                                        ? esc_html__('Enabled', 'wp-asset-clean-up')
                                        : esc_html__('Disabled', 'wp-asset-clean-up'); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $specificRule['show_method'] === 'minified'
                                    ? esc_html__('Minified', 'wp-asset-clean-up')
                                    : esc_html__('As entered', 'wp-asset-clean-up'); ?>
                            </td>
                            <td class="wpacu-critical-css-classic-row-actions">
                                <a class="button button-small" href="<?php echo esc_url($specificRule['edit_url']); ?>">
                                    <?php esc_html_e('Edit', 'wp-asset-clean-up'); ?>
                                </a>
                                <?php if ($specificRule['url']) { ?>
                                    <a target="_blank" href="<?php echo esc_url($specificRule['url']); ?>">
                                        <?php esc_html_e('View', 'wp-asset-clean-up'); ?>
                                        <span class="dashicons dashicons-external" aria-hidden="true"></span>
                                    </a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div id="wpacu-critical-css-existing-rules-no-match" class="wpacu-critical-css-classic-empty" style="display: none;">
            <?php esc_html_e('No saved rules match this filter.', 'wp-asset-clean-up'); ?>
        </div>
    <?php } else { ?>
        <div class="wpacu-critical-css-classic-empty">
            <span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
            <strong><?php esc_html_e('No specific Critical CSS rules yet.', 'wp-asset-clean-up'); ?></strong>
            <span><?php echo esc_html(sprintf(__('Use “%s” to create the first one.', 'wp-asset-clean-up'), $emptyStateAddLabel)); ?></span>
        </div>
    <?php } ?>
</div>
