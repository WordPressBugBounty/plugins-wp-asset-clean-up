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
        $isGranular = ! empty($storageContext['is_granular']);

        $textareaContent = '';

        if (isset($storedData['content_original']) && is_string($storedData['content_original'])) {
            $textareaContent = ! empty($storedData['content_was_stored_in_options'])
                ? stripslashes($storedData['content_original'])
                : $storedData['content_original'];
        }

        $customPageTypeData = '';

        if ($data['for'] === 'custom_post_types' && ! empty($data['chosen_post_type'])) {
            $customPageTypeData = $data['chosen_post_type'] . '_post_type';
        } elseif ($data['for'] === 'custom_post_type_archives' && ! empty($data['chosen_post_type'])) {
            $customPageTypeData = $data['chosen_post_type'] . '_post_type_archive';
        } elseif ($data['for'] === 'custom_taxonomies' && ! empty($data['chosen_taxonomy'])) {
            $customPageTypeData = $data['chosen_taxonomy'] . '_taxonomy';
        }
        ?>
        <label for="wpacu_critical_css_status" class="wpacu_switch wpacu_with_text">
            <input type="checkbox"
                   data-wpacu-custom-page-type="<?php echo esc_attr($customPageTypeData); ?>"
                   data-wpacu-is-granular="<?php echo $isGranular ? '1' : '0'; ?>"
                   id="wpacu_critical_css_status"
                   <?php checked($enable); ?>
                   name="<?php echo WPACU_PLUGIN_ID . '_critical_css'; ?>[enable]"
                   value="1" />
            <span class="wpacu_slider wpacu_round"></span>
        </label>
        &nbsp;
        <?php if ($isGranular) { ?>
            * <?php esc_html_e('you can enable or disable this Critical CSS rule for the selected item at any time; disabling it will keep the CSS content and allow the General rule to be used as fallback.', 'wp-asset-clean-up'); ?>
        <?php } else { ?>
            * <?php esc_html_e('you can enable/disable at any time the Critical CSS functionality for all the pages from this group (e.g. disabling it will not remove any current CSS content in case you will ever need it again); if you enable it, you have to provide the Critical CSS content.', 'wp-asset-clean-up'); ?>
        <?php } ?>

        <div style="margin: 25px 0 0;" class="clearfix"></div>

        <div id="wpacu-critical-css-options-area" class="<?php if ( ! $enable ) { echo 'wpacu-faded'; } ?>">
            <div id="wpacu-css-editor-area">
                <textarea name="<?php echo WPACU_PLUGIN_ID . '_critical_css'; ?>[content]" id="wpacu-css-editor-textarea"><?php echo esc_textarea($textareaContent); ?></textarea>
            </div>

            <div style="margin: 25px 0 0;" class="clearfix"></div>

            <div>
                <strong><?php esc_html_e('How to print it in the front-end view?', 'wp-asset-clean-up'); ?></strong>
                <ul>
                    <li>
                        <label for="wpacu_show_critical_css_original_option">
                            <input id="wpacu_show_critical_css_original_option"
                                   <?php checked($showMethod, 'original'); ?>
                                   type="radio"
                                   name="<?php echo WPACU_PLUGIN_ID . '_critical_css'; ?>[show_method]"
                                   value="original" />&nbsp;<?php esc_html_e('As it is (it will print exactly as it is showing in the textarea)', 'wp-asset-clean-up'); ?>
                        </label>
                    </li>
                    <li>
                        <label for="wpacu_show_critical_css_minified_option">
                            <input id="wpacu_show_critical_css_minified_option"
                                   <?php checked($showMethod, 'minified'); ?>
                                   type="radio"
                                   name="<?php echo WPACU_PLUGIN_ID . '_critical_css'; ?>[show_method]"
                                   value="minified" />&nbsp;<?php esc_html_e("Minified (if it's not already minified, it's good to enable this option to save some KB)", 'wp-asset-clean-up'); ?>
                        </label>
                    </li>
                </ul>
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
        <?php
    }
    ?>
</div>
