<?php
/*
 * No direct access to this file
 */

if ( ! isset($data, $specificRules, $rulesCount, $selectedObjectId) ) {
    exit;
}
?>
<div class="wpacu-critical-css-existing-rules">
    <div class="wpacu-critical-css-existing-rules-header">
        <div>
            <h2><?php esc_html_e('Saved specific rules', 'wp-asset-clean-up'); ?></h2>
            <span><?php echo esc_html(sprintf(_n('%d entry', '%d entries', $rulesCount, 'wp-asset-clean-up'), $rulesCount)); ?></span>
        </div>

        <?php if ($rulesCount > 4) { ?>
            <label class="wpacu-critical-css-existing-rules-filter">
                <span class="dashicons dashicons-search" aria-hidden="true"></span>
                <input type="search"
                       id="wpacu-critical-css-existing-rules-filter"
                       placeholder="<?php echo esc_attr__('Filter saved rules…', 'wp-asset-clean-up'); ?>" />
            </label>
        <?php } ?>
    </div>

    <?php if ($rulesCount > 0) { ?>
        <div class="wpacu-critical-css-existing-rules-list">
            <?php foreach ($specificRules as $specificRule) {
                $isCurrent = $selectedObjectId > 0 && (int)$specificRule['object_id'] === $selectedObjectId;
                $searchText = strtolower($specificRule['label'] . ' ' . $specificRule['object_id'] . ' ' . $specificRule['url']);
                ?>
                <div class="wpacu-critical-css-rule-row <?php echo $isCurrent ? 'wpacu-current' : ''; ?>"
                     data-wpacu-rule-search="<?php echo esc_attr($searchText); ?>">
                    <span class="wpacu-critical-css-rule-dot <?php echo ! empty($specificRule['enable']) ? 'wpacu-enabled' : 'wpacu-disabled'; ?>"
                          aria-hidden="true"></span>

                    <div class="wpacu-critical-css-rule-main">
                        <div class="wpacu-critical-css-rule-name">
                            <strong><?php echo esc_html($specificRule['label']); ?></strong>
                            <code>ID: <?php echo (int)$specificRule['object_id']; ?></code>
                            <?php if ($isCurrent) { ?>
                                <span class="wpacu-critical-css-editing-badge"><?php esc_html_e('Editing', 'wp-asset-clean-up'); ?></span>
                            <?php } ?>
                        </div>
                        <?php if ($specificRule['url']) { ?>
                            <span class="wpacu-critical-css-rule-url"><?php echo esc_html($specificRule['url']); ?></span>
                        <?php } ?>
                    </div>

                    <div class="wpacu-critical-css-rule-meta">
                        <span class="<?php echo ! empty($specificRule['enable']) ? 'wpacu-enabled' : 'wpacu-disabled'; ?>">
                            <?php echo ! empty($specificRule['enable'])
                                ? esc_html__('Enabled', 'wp-asset-clean-up')
                                : esc_html__('Disabled', 'wp-asset-clean-up'); ?>
                        </span>
                        <span><?php echo $specificRule['show_method'] === 'minified'
                            ? esc_html__('Minified', 'wp-asset-clean-up')
                            : esc_html__('As entered', 'wp-asset-clean-up'); ?></span>
                    </div>

                    <div class="wpacu-critical-css-rule-actions">
                        <a class="button button-small" href="<?php echo esc_url($specificRule['edit_url']); ?>">
                            <?php esc_html_e('Edit', 'wp-asset-clean-up'); ?>
                        </a>
                        <?php if ($specificRule['url']) { ?>
                            <a target="_blank" href="<?php echo esc_url($specificRule['url']); ?>">
                                <?php esc_html_e('View', 'wp-asset-clean-up'); ?>
                                <span class="dashicons dashicons-external" aria-hidden="true"></span>
                            </a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>

        <div id="wpacu-critical-css-existing-rules-no-match" class="wpacu-critical-css-empty-state" style="display: none;">
            <?php esc_html_e('No saved rules match this filter.', 'wp-asset-clean-up'); ?>
        </div>
    <?php } else { ?>
        <div class="wpacu-critical-css-empty-state">
            <span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
            <strong><?php esc_html_e('No specific Critical CSS rules yet.', 'wp-asset-clean-up'); ?></strong>
            <span><?php echo esc_html(sprintf(__('Use “Add %s” to create the first one.', 'wp-asset-clean-up'), $singularLabel)); ?></span>
        </div>
    <?php } ?>
</div>
