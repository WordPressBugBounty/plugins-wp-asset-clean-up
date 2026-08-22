<?php
if (! defined('ABSPATH')) {
    exit;
}

$wpacuMediaLoadIsPro = defined('WPACU_PRO_PLUGIN_VERSION');
$matchMediaLoadStatus = false;
$matchMediaLoadCustomValue = '';
$matchMediaLoadCustomValueToPrint = '';

if ($wpacuMediaLoadIsPro && ! empty($data['media_queries_load'][$assetType][$data['row']['obj']->handle])) {
    $matchMediaLoadArray = $data['media_queries_load'][$assetType][$data['row']['obj']->handle];
    $matchMediaLoadStatus = isset($matchMediaLoadArray['enable']) ? (int)$matchMediaLoadArray['enable'] : false;
    $matchMediaLoadCustomValue = ! empty($matchMediaLoadArray['value']) ? $matchMediaLoadArray['value'] : '';

    if ($matchMediaLoadStatus === 1) {
        $matchMediaLoadCustomValueToPrint = $matchMediaLoadCustomValue;
    } elseif ($matchMediaLoadStatus === 2 && ! $assetHasDistinctiveMediaAttr) {
        $matchMediaLoadStatus = false;
    }

    if ($matchMediaLoadCustomValue && in_array($matchMediaLoadStatus, array(1, 2))) {
        $data['row']['at_least_one_rule_set'] = true;
    }
}

$wpacuDataForSelectId = 'wpacu_handle_media_query_load_select_' . $assetTypeS . '_' . $data['row']['obj']->handle;
$wpacuDataForTextAreaId = 'wpacu_handle_media_query_load_textarea_' . $assetTypeS . '_' . $data['row']['obj']->handle;
?>
<div class="wpacu-only-when-kept-loaded">
    <div style="margin: 0 0 15px;">
        <?php esc_html_e('Make the browser download the file', 'wp-asset-clean-up'); ?>&nbsp;
        <select id="<?php echo esc_attr($wpacuDataForSelectId); ?>"
                data-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>"
                data-wpacu-input="media-query-select"
            <?php if ($wpacuMediaLoadIsPro && $assetType === 'styles' && $showMatchMediaAlertForParentCss) { ?> data-wpacu-show-parent-alert<?php } ?>
                name="<?php echo esc_attr(WPACU_FORM_ASSETS_POST_KEY); ?>[<?php echo esc_attr($assetType); ?>][<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>][media_query_load][enable]"
                class="wpacu-screen-size-load wpacu-for-<?php echo esc_attr($assetTypeS); ?>">
            <option <?php selected(! $matchMediaLoadStatus); ?> value=""><?php esc_html_e('on any screen size (default)', 'wp-asset-clean-up'); ?></option>
            <?php if ($assetHasDistinctiveMediaAttr) { ?>
                <option <?php selected($matchMediaLoadStatus, 2); ?> <?php if (! $wpacuMediaLoadIsPro) { ?>disabled="disabled"<?php } ?> value="2"><?php esc_html_e('only if its current media query is matched', 'wp-asset-clean-up'); ?><?php if (! $wpacuMediaLoadIsPro) { ?> (Pro)<?php } ?></option>
            <?php } ?>
            <option <?php selected($matchMediaLoadStatus, 1); ?> <?php if (! $wpacuMediaLoadIsPro) { ?>disabled="disabled"<?php } ?> value="1"><?php esc_html_e('only if this media query is matched:', 'wp-asset-clean-up'); ?><?php if (! $wpacuMediaLoadIsPro) { ?> (Pro)<?php } ?></option>
        </select>

        <?php if ($wpacuMediaLoadIsPro) { ?>
            <div data-<?php echo esc_attr($assetTypeS); ?>-handle="<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>" class="wpacu-handle-media-queries-load-field <?php if ($matchMediaLoadStatus === 1) { echo 'wpacu-is-visible'; } ?> wpacu-fade-in">
                <textarea id="<?php echo esc_attr($wpacuDataForTextAreaId); ?>"
                          style="min-height: 40px;"
                          class="wpacu-handle-media-queries-load-field-input"
                          data-wpacu-adapt-height="1"
                          data-wpacu-is-empty-on-page-load="<?php echo ! $matchMediaLoadCustomValueToPrint ? 'true' : 'false'; ?>"
                    <?php if (! $matchMediaLoadCustomValueToPrint) { ?> disabled="disabled"<?php } ?>
                          name="<?php echo esc_attr(WPACU_FORM_ASSETS_POST_KEY); ?>[<?php echo esc_attr($assetType); ?>][<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>][media_query_load][value]"><?php echo esc_textarea($matchMediaLoadCustomValueToPrint); ?></textarea> &nbsp;<small style="vertical-align: top;">e.g. <em style="vertical-align: top;">screen and (max-width: 767px)</em></small>
                <div class="wpacu_clearfix"></div>
            </div>
        <?php } ?>
        <div class="wpacu-helper-area"><a style="text-decoration: none; color: inherit;" target="_blank" rel="noopener noreferrer" href="https://www.assetcleanup.com/docs/?p=1023"><span class="dashicons dashicons-editor-help"></span></a></div>
    </div>
</div>
<div class="wpacu_clearfix"></div>
<?php unset($matchMediaLoadArray, $matchMediaLoadCustomValue, $matchMediaLoadCustomValueToPrint, $matchMediaLoadStatus, $wpacuDataForSelectId, $wpacuDataForTextAreaId, $wpacuMediaLoadIsPro); ?>
