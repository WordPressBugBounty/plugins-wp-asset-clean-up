<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\CriticalCssAdmin;

if (! isset($data, $locationKey, $criticalCssConfig)) {
	exit;
}
?>
<div class="wpacu-wrap <?php if ($data['wpacu_settings']['input_style'] !== 'standard') { ?>wpacu-switch-enhanced<?php } else { ?>wpacu-switch-standard<?php } ?>">
    <?php
    if ($data['for'] === 'custom_post_types' && isset($data['custom_post_types_list']) && ! empty($data['custom_post_types_list'])) {
    ?>
        <div style="margin: 0 0 22px;">
            <p>Choose the custom post type for which you want to apply the critical CSS on the singular pages:</p>
            <?php
            CriticalCssAdmin::buildCustomPostTypesListLinks($data['custom_post_types_list'], $data['chosen_post_type'], $criticalCssConfig);
            ?>
        </div>
    <?php
    }

    if ($data['for'] === 'custom_taxonomies' && isset($data['custom_taxonomies_list']) && ! empty($data['custom_taxonomies_list'])) {
        ?>
        <div style="margin: 0 0 22px;">
            <p>Choose the custom taxonomy for which you want to apply the critical CSS on the singular pages:</p>
		    <?php
		    CriticalCssAdmin::buildTaxonomyListLinks($data['custom_taxonomies_list'], $data['chosen_taxonomy'], $criticalCssConfig);
		    ?>
        </div>
        <?php
    }

    // Only attempt to fetch values if there's at least a record of the page type requested
    // e.g. if "Custom Post Types" are chosen, and there are no custom types, do not show any options
    if ($data['show_critical_css_options'] && isset($locationKey) && $locationKey) {
        $enable     = isset($criticalCssConfig[$locationKey]['enable']) && $criticalCssConfig[$locationKey]['enable'];
        $showMethod = isset($criticalCssConfig[$locationKey]['show_method']) && $criticalCssConfig[$locationKey]['show_method']
            ? $criticalCssConfig[$locationKey]['show_method']
            : 'original';

        $contentDataJson = get_option(WPACU_PLUGIN_ID . '_critical_css_location_key_' . $locationKey);
        $contentData     = @json_decode($contentDataJson, ARRAY_A);

        $textareaContent = '';

        // Only the original content will show in the textarea as the admin has choices how to have it printed in the front-end view
        if (isset($contentData['content_original']) && $contentData['content_original']) {
            $textareaContent = stripslashes($contentData['content_original']);
        }
    ?>
        <label for="wpacu_critical_css_status" class="wpacu_switch wpacu_with_text">
            <input type="checkbox"
               data-wpacu-custom-page-type="<?php if ($data['for'] === 'custom_post_types') { echo esc_attr($data['chosen_post_type']).'_post_type'; } elseif ($data['for'] === 'custom_taxonomies') { echo esc_attr($data['chosen_taxonomy']).'_taxonomy'; } ?>"
               id="wpacu_critical_css_status"
            <?php if ($enable) { echo 'checked="checked"'; } ?>
               name="<?php echo WPACU_PLUGIN_ID . '_critical_css'; ?>[enable]"
               value="1" /> <span class="wpacu_slider wpacu_round"></span>
        </label> &nbsp; * you can enable/disable at any time the critical CSS functionality for all the pages from this group (e.g. disabling it won't remove the any current CSS content in case you will ever need it again); if you enable it, you have to provide the critical CSS content

        <div style="margin: 25px 0 0;" class="clearfix"></div>

        <div id="wpacu-critical-css-options-area" class="<?php if ( ! $enable ) { echo 'wpacu-faded'; } ?>">
            <div id="wpacu-css-editor-area">
                <textarea name="<?php echo WPACU_PLUGIN_ID . '_critical_css'; ?>[content]" id="wpacu-css-editor-textarea"><?php echo esc_textarea($textareaContent); ?></textarea>
            </div>

            <div style="margin: 25px 0 0;" class="clearfix"></div>

            <div>
                <strong>How to print it in the front-end view?</strong>
                <ul>
                    <li>
                        <label for="wpacu_show_critical_css_original_option">
                            <input id="wpacu_show_critical_css_original_option"
                                   <?php if ( $showMethod === 'original' ) { echo 'checked="checked"'; } ?>
                                   type="radio" name="<?php echo WPACU_PLUGIN_ID . '_critical_css'; ?>[show_method]"
                                   value="original"/>&nbsp;As it is (it will print exactly as it is showing in the textarea)
                        </label>
                    </li>
                    <li>
                        <label for="wpacu_show_critical_css_minified_option">
                            <input id="wpacu_show_critical_css_minified_option"
                                   <?php if ( $showMethod === 'minified' ) { echo 'checked="checked"'; } ?>
                                   type="radio" name="<?php echo WPACU_PLUGIN_ID . '_critical_css'; ?>[show_method]"
                                   value="minified"/>&nbsp;Minified (if it's not already minified, it's good to enable this option to save some KB)
                        </label>
                    </li>
                </ul>
            </div>
        </div>
        <input type="hidden" name="<?php echo WPACU_PLUGIN_ID . '_critical_css'; ?>[location_key]" value="<?php echo esc_attr($locationKey); ?>" />
    <?php
    }
    ?>
</div>
