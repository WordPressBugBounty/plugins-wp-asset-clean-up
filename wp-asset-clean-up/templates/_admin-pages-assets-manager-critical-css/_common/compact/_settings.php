<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\CriticalCssAdmin;
use WpAssetCleanUp\Settings;

if ( ! isset($data, $locationKey, $criticalCssConfig) ) {
    exit;
}

$inputStyle = Settings::getInputStyle($data['wpacu_settings']);
?>
<div class="wpacu-wrap <?php echo esc_attr(Settings::getInputStyleCssClasses($inputStyle)); ?>"
     data-wpacu-input-style="<?php echo esc_attr($inputStyle); ?>">
    <?php
    // Only attempt to fetch values if there is a valid page-group or object context.
    if ($data['show_critical_css_options'] && $locationKey) {
        $storageContext = isset($data['critical_css_storage'])
            ? $data['critical_css_storage']
            : array(
                'storage_type' => 'option',
                'object_id'    => 0,
                'is_granular'  => false
            );

        $storedData = CriticalCssAdmin::getStoredCriticalCssData($storageContext, $locationKey, $criticalCssConfig);
        $enable     = ! empty($storedData['enable']);
        $showMethod = ! empty($storedData['show_method']) ? $storedData['show_method'] : 'original';

        $textareaContent = '';

        if (isset($storedData['content_original']) && is_string($storedData['content_original'])) {
            $textareaContent = ! empty($storedData['content_was_stored_in_options'])
                ? stripslashes($storedData['content_original'])
                : $storedData['content_original'];
        }

        $isGranular = ! empty($storageContext['is_granular']);

        if ($isGranular) {
            $ruleTypeLabel = __('Individual rule', 'wp-asset-clean-up');
        } elseif ($data['for'] === 'custom_post_type_archives') {
            $ruleTypeLabel = __('Archive rule', 'wp-asset-clean-up');
        } else {
            $ruleTypeLabel = __('General rule', 'wp-asset-clean-up');
        }
        ?>
        <div class="wpacu-critical-css-editor-panel">
            <div class="wpacu-critical-css-editor-header">
                <div class="wpacu-critical-css-editor-title">
                    <h2><?php esc_html_e('Critical CSS', 'wp-asset-clean-up'); ?></h2>
                    <span><?php echo esc_html($ruleTypeLabel); ?></span>
                </div>

                <div class="wpacu-critical-css-rule-status">
                    <span class="wpacu-critical-css-field-label" id="wpacu-critical-css-rule-label">
                        <?php esc_html_e('Rule', 'wp-asset-clean-up'); ?>
                    </span>

                    <label for="wpacu_critical_css_status" class="wpacu_switch wpacu_with_text">
                        <input type="checkbox"
                               data-wpacu-custom-page-type="<?php if ($data['for'] === 'custom_post_types') { echo esc_attr($data['chosen_post_type']) . '_post_type'; } elseif ($data['for'] === 'custom_post_type_archives') { echo esc_attr($data['chosen_post_type']) . '_post_type_archive'; } elseif ($data['for'] === 'custom_taxonomies') { echo esc_attr($data['chosen_taxonomy']) . '_taxonomy'; } ?>"
                               data-wpacu-is-granular="<?php echo $isGranular ? '1' : '0'; ?>"
                               id="wpacu_critical_css_status"
                               aria-labelledby="wpacu-critical-css-rule-label"
                               aria-describedby="wpacu-critical-css-rule-status-text"
                               <?php checked($enable); ?>
                               name="<?php echo WPACU_PLUGIN_ID . '_critical_css'; ?>[enable]"
                               value="1" />
                        <span class="wpacu_slider wpacu_round"></span>
                    </label>

                    <strong id="wpacu-critical-css-rule-status-text"
                            class="<?php echo $enable ? 'wpacu-enabled' : 'wpacu-disabled'; ?>">
                        <?php echo $enable
                            ? esc_html__('Enabled', 'wp-asset-clean-up')
                            : esc_html__('Disabled', 'wp-asset-clean-up'); ?>
                    </strong>
                </div>
            </div>

            <div id="wpacu-critical-css-options-area" class="<?php if ( ! $enable ) { echo 'wpacu-faded'; } ?>">
                <div class="wpacu-critical-css-editor-toolbar">
                    <span class="wpacu-critical-css-field-label">
                        <?php esc_html_e('CSS code', 'wp-asset-clean-up'); ?>
                    </span>

                    <fieldset class="wpacu-critical-css-output-choice">
                        <legend class="screen-reader-text"><?php esc_html_e('Output', 'wp-asset-clean-up'); ?></legend>
                        <span aria-hidden="true"><?php esc_html_e('Output', 'wp-asset-clean-up'); ?></span>

                        <label class="<?php if ($showMethod === 'original') { echo 'wpacu-active'; } ?>"
                               for="wpacu_show_critical_css_original_option">
                            <input id="wpacu_show_critical_css_original_option"
                                   <?php checked($showMethod, 'original'); ?>
                                   type="radio"
                                   name="<?php echo WPACU_PLUGIN_ID . '_critical_css'; ?>[show_method]"
                                   value="original" />
                            <?php esc_html_e('As entered', 'wp-asset-clean-up'); ?>
                        </label>

                        <label class="<?php if ($showMethod === 'minified') { echo 'wpacu-active'; } ?>"
                               for="wpacu_show_critical_css_minified_option"
                               title="<?php echo esc_attr__('Minify the CSS before printing it in the front-end HTML.', 'wp-asset-clean-up'); ?>">
                            <input id="wpacu_show_critical_css_minified_option"
                                   <?php checked($showMethod, 'minified'); ?>
                                   type="radio"
                                   name="<?php echo WPACU_PLUGIN_ID . '_critical_css'; ?>[show_method]"
                                   value="minified" />
                            <?php esc_html_e('Minified', 'wp-asset-clean-up'); ?>
                        </label>
                    </fieldset>
                </div>

                <div id="wpacu-css-editor-area">
                    <textarea name="<?php echo WPACU_PLUGIN_ID . '_critical_css'; ?>[content]"
                              id="wpacu-css-editor-textarea"><?php echo esc_textarea($textareaContent); ?></textarea>
                </div>
            </div>

            <input type="hidden"
                   name="<?php echo WPACU_PLUGIN_ID . '_critical_css'; ?>[location_key]"
                   value="<?php echo esc_attr($locationKey); ?>" />
            <input type="hidden"
                   name="<?php echo WPACU_PLUGIN_ID . '_critical_css'; ?>[storage_type]"
                   value="<?php echo esc_attr(isset($storageContext['storage_type']) ? $storageContext['storage_type'] : 'option'); ?>" />
            <input type="hidden"
                   name="<?php echo WPACU_PLUGIN_ID . '_critical_css'; ?>[object_id]"
                   value="<?php echo isset($storageContext['object_id']) ? (int)$storageContext['object_id'] : 0; ?>" />
        </div>
        <?php
    }
    ?>
</div>
