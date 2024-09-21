<?php
if (! isset($data)) {
    exit;
}
?>
<table class="wpacu-form-table">
    <tr valign="top">
        <th scope="row" class="setting_title">
            <?php echo sprintf(__('Hide %s menus', 'wp-asset-clean-up'), '"'.WPACU_PLUGIN_TITLE.'"'); ?>
            <p class="wpacu_subtitle"><small><em><?php _e('Are you rarely using the plugin and want to make some space in the admin menus?', 'wp-asset-clean-up'); ?></em></small></p>
        </th>
        <td>
            <ul style="padding: 0;">
                <li style="margin-bottom: 14px;">
                    <label for="wpacu_hide_from_admin_bar">
                        <input id="wpacu_hide_from_admin_bar"
                               type="checkbox"
                            <?php echo (($data['hide_from_admin_bar'] == 1) ? 'checked="checked"' : ''); ?>
                               name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[hide_from_admin_bar]"
                               value="1" /> <span class="wpacu_slider wpacu_round"></span>
                        <span>Hide it from the top admin bar</span> / This could be useful if your top admin bar is filled with too many items and you rarely use the plugin.</label> <span style="color: #004567;" class="dashicons dashicons-info"></span> <a href="https://assetcleanup.com/docs/?p=187" target="_blank">Read more</a>
                </li>
                <li>
                    <label for="wpacu_hide_from_side_bar">
                        <input id="wpacu_hide_from_side_bar"
                               type="checkbox"
                            <?php echo (($data['hide_from_side_bar'] == 1) ? 'checked="checked"' : ''); ?>
                               name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[hide_from_side_bar]"
                               value="1" /> <span class="wpacu_slider wpacu_round"></span>
                        <span>Hide it from the left sidebar within the Dashboard</span> / The only access will be from <em>"Settings" -&gt; "<?php echo WPACU_PLUGIN_TITLE; ?>"</em>.</label> <span style="color: #004567;" class="dashicons dashicons-info"></span> <a href="https://assetcleanup.com/docs/?p=584" target="_blank">Read more</a>
                </li>
            </ul>
        </td>
    </tr>
</table>