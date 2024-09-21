<?php
if (! isset($data)) {
    exit;
}
?>
<table class="wpacu-form-table">
    <tr valign="top" id="wpacu-settings-allow-usage-tracking">
        <th scope="row">
            <label for="wpacu_allow_usage_tracking"><?php _e('Allow Usage Tracking', 'wp-asset-clean-up'); ?></label>
        </th>
        <td>
            <label class="wpacu_switch">
                <input id="wpacu_allow_usage_tracking"
                       type="checkbox"
                    <?php echo (($data['allow_usage_tracking'] == 1) ? 'checked="checked"' : ''); ?>
                       name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[allow_usage_tracking]"
                       value="1" /> <span class="wpacu_slider wpacu_round"></span> </label>
            &nbsp;
            Allow <?php echo WPACU_PLUGIN_TITLE; ?> to anonymously track plugin usage to help us make the plugin better? No sensitive or personal data is collected. <span style="color: #004567;" class="dashicons dashicons-info"></span> <a data-wpacu-modal-target="wpacu-show-tracked-data-list-modal-target" href="#wpacu-show-tracked-data-list-modal">What kind of data will be sent for the tracking?</a>
        </td>
    </tr>
</table>