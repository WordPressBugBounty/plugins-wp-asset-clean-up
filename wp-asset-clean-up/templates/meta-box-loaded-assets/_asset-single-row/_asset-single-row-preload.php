<?php
if ( ! isset($data, $assetType, $isAssetPreload) ) {
    exit(); // no direct access
}
?>
<div class="wpacu_hide_if_handle_row_contracted">
    &nbsp;&#10230;&nbsp;
    Preload?
    <!-- [wpacu_lite] -->
        <?php
        if (isset($data['row']['is_hardcoded']) && $data['row']['is_hardcoded']) {
            ?>
            <a class="go-pro-link-no-style"
               href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL . '?utm_source=manage_asset&utm_medium=change_css_position'); ?>">
                <span class="wpacu-tooltip" style="width: auto; margin-left: -110px; padding: 8px 10px;">Upgrade to Pro to preload this hardcoded asset</span>
                <img width="20" height="20" src="<?php echo WPACU_PLUGIN_URL . '/assets/icons/icon-lock.svg'; ?>" valign="top" alt="" />
            </a>
            <?php
        } else {
        ?>
    <!-- [/wpacu_lite] -->
    &nbsp;<select style="display: inline-block; width: auto; <?php if ($isAssetPreload) {
        echo 'background: #f2faf2 url(\'data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M5%206l5%205%205-5%202%201-7%207-7-7%202-1z%22%20fill%3D%22%23555%22%2F%3E%3C%2Fsvg%3E\') no-repeat right 5px top 55%; padding-right: 30px; color: black;';
    } ?>"
        data-wpacu-input="preload"
        name="<?php echo WPACU_FORM_ASSETS_POST_KEY; ?>[<?php echo $assetType; ?>][<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>][preload]">
        <option value="">No (default)</option>

        <option <?php if ($isAssetPreload === 'basic') { ?>selected="selected"<?php } ?> value="basic">Yes, basic</option>

        <?php if ($assetType === 'styles') { ?>
            <option <?php if ($isAssetPreload === 'async') { ?>selected="selected"<?php } ?> value="async">Yes, async</option>
        <?php } ?>

    </select>
    <!-- [wpacu_lite] -->
        <?php
        }
        ?>
    <!-- [/wpacu_lite] -->
    <small>* applies site-wide</small> <small><a style="text-decoration: none; color: inherit;" target="_blank" href="https://assetcleanup.com/docs/?p=202"><span class="dashicons dashicons-editor-help"></span></a></small>
</div>