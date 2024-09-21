<?php
if (! isset($data)) {
	exit;
}
?>
<table class="wpacu-form-table">
    <tr valign="top">
        <th scope="row" class="setting_title">
            <?php _e('Input Fields Style', 'wp-asset-clean-up'); ?>:
            <p class="wpacu_subtitle"><small><em><?php _e('How would you like to view the checkboxes / selectors?', 'wp-asset-clean-up'); ?></em></small></p>
            <p class="wpacu_read_more"><a href="https://assetcleanup.com/docs/?p=95" target="_blank"><?php _e('Read More', 'wp-asset-clean-up'); ?></a></p>
        </th>
        <td>
            <ul class="input_style_choices">
                <li>
                    <label for="input_style_enhanced">
                        <input id="input_style_enhanced"
                               <?php if (! $data['input_style'] || $data['input_style'] === 'enhanced') { ?>checked="checked"<?php } ?>
                               type="radio"
                               name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[input_style]"
                               value="enhanced"> <?php _e('Enhanced iPhone Style (Default)', 'wp-asset-clean-up'); ?>
                    </label>
                </li>
                <li>
                    <label for="input_style_standard">
                        <input id="input_style_standard"
                               <?php if ($data['input_style'] === 'standard') { ?>checked="checked"<?php } ?>
                               type="radio"
                               name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[input_style]"
                               value="standard"> <?php _e('Standard', 'wp-asset-clean-up'); ?>
                    </label>
                </li>
            </ul>
            <div class="wpacu_clearfix"></div>

            <p style="margin: 15px 0 0; line-height: 24px;"><?php _e('In case you prefer standard HTML checkboxes instead of the enhanced CSS3 iPhone style ones (on &amp; off) or you need a simple HTML layout in case you\'re using a screen reader software (e.g. for people with disabilities) which requires standard/clean HTML code, then you can choose "Standard" as an option.', 'wp-asset-clean-up'); ?> <span style="white-space: nowrap;"><span style="color: #004567;" class="dashicons dashicons-info"></span> <a href="https://assetcleanup.com/docs/?p=95" target="_blank">Read more</a></span></p>

            <p style="margin: 20px 0 0; line-height: 24px;"><?php _e('This also applies to specific drop-downs', 'wp-asset-clean-up'); ?>. <?php echo sprintf(__('%s is used to make them more user-friendly (e.g. ability to search within a drop-down)', 'wp-asset-clean-up'), '<a target="_blank" href="https://harvesthq.github.io/chosen/">Chosen jQuery plugin</a>'); ?>. <?php _e('If you have difficulties using these types of drop-downs, you can choose the "Standard" option and default (basic) HTML drop-downs (some with multiple options to select) will be shown instead.', 'wp-asset-clean-up'); ?></p>
        </td>
    </tr>
</table>