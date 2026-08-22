<?php
if (! defined('ABSPATH') || ! isset($data, $assetHandleHasSrc, $assetPosition, $assetPositionNew, $assetType, $assetTypeS)) {
    exit;
}

if (! isset($data['row']['obj']->position) || $data['row']['obj']->position === '') {
    return;
}

$wpacuAssetPositionIsPro = defined('WPACU_PRO_PLUGIN_VERSION');
$wpacuAssetPositionLabel = $assetPosition === 'head' ? 'HEAD' : 'BODY';

ob_start();

if (! $wpacuAssetPositionIsPro) {
    if ($assetHandleHasSrc) {
        $wpacuAssetPositionKind = $assetTypeS === 'style' ? 'CSS' : 'JavaScript';
        $wpacuAssetPositionUtm  = $assetTypeS === 'style' ? 'change_css_position' : 'change_js_position';
        ?>
        <span class="wpacu-asset-position-preview">
            <?php esc_html_e('Position:', 'wp-asset-clean-up'); ?> <strong><?php echo esc_html($wpacuAssetPositionLabel); ?></strong>
            <a class="go-pro-link-no-style"
               href="<?php echo esc_url(apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL . '?utm_source=manage_asset&utm_medium=' . $wpacuAssetPositionUtm)); ?>">
                <span class="wpacu-tooltip" style="width: 322px; margin-left: -146px;"><?php echo esc_html(sprintf(__('Upgrade to Pro to move this %s asset between HEAD and BODY site-wide.', 'wp-asset-clean-up'), $wpacuAssetPositionKind)); ?></span>
                <img width="20" height="20" src="<?php echo esc_url(WPACU_PLUGIN_URL . '/assets/icons/icon-lock.svg'); ?>" valign="top" alt="" /> <?php esc_html_e('Change it?', 'wp-asset-clean-up'); ?>
            </a>
        </span>
        <?php
    } else {
        if ($assetTypeS === 'style' && isset($data['row']['obj']->extra->after) && ! empty($data['row']['obj']->extra->after)) {
            $wpacuAssetPositionNoSrcText = __('This inline CSS can be viewed using the "Show/Hide" button below and it is loaded in:', 'wp-asset-clean-up');
        } elseif ($assetTypeS === 'style') {
            $wpacuAssetPositionNoSrcText = __('This handle is not for an external stylesheet (most likely inline CSS) and it is loaded in:', 'wp-asset-clean-up');
        } else {
            $wpacuAssetPositionNoSrcText = __('This handle is not for external JavaScript (most likely inline JavaScript) and it is loaded in:', 'wp-asset-clean-up');
        }

        echo esc_html($wpacuAssetPositionNoSrcText) . ' <strong>' . esc_html($wpacuAssetPositionLabel) . '</strong>';
    }
} elseif ($assetType === 'scripts' || $assetHandleHasSrc) {
    ?>
    <div class="wpacu-wrap-choose-position">
        <?php esc_html_e('Location:', 'wp-asset-clean-up'); ?>
        <select data-wpacu-input="position-select"
                name="<?php echo esc_attr(WPACU_FORM_ASSETS_POST_KEY); ?>[<?php echo esc_attr($assetType); ?>][<?php echo htmlentities(esc_attr($data['row']['obj']->handle), ENT_QUOTES); ?>][position]"
                style="<?php if ($assetPosition !== $assetPositionNew) {
                    echo 'background: #f2faf2 url(\'data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M5%206l5%205%205-5%202%201-7%207-7-7%202-1z%22%20fill%3D%22%23555%22%2F%3E%3C%2Fsvg%3E\') no-repeat right 5px top 55%; padding-right: 30px; color: black;';
                } ?>">
            <option <?php selected($assetPositionNew, 'head'); ?> value="<?php echo $assetPosition === 'head' ? 'initial' : 'head'; ?>">&lt;HEAD&gt;<?php if ($assetPosition === 'head') { ?> * initial<?php } ?></option>
            <option <?php selected($assetPositionNew, 'body'); ?> value="<?php echo $assetPosition === 'body' ? 'initial' : 'body'; ?>">&lt;BODY&gt;<?php if ($assetPosition === 'body') { ?> * initial<?php } ?></option>
        </select>
        <small>* <?php esc_html_e('applies site-wide', 'wp-asset-clean-up'); ?></small>
    </div>
    <?php
} else {
    if (isset($data['row']['obj']->extra->after) && ! empty($data['row']['obj']->extra->after)) {
        $wpacuAssetPositionNoSrcText = __('This inline CSS can be viewed using the "Show/Hide" button below and it is loaded in:', 'wp-asset-clean-up');
    } else {
        $wpacuAssetPositionNoSrcText = __('This handle is not for an external stylesheet (most likely inline CSS) and it is loaded in:', 'wp-asset-clean-up');
    }

    echo esc_html($wpacuAssetPositionNoSrcText) . ' <strong>' . esc_html($wpacuAssetPositionLabel) . '</strong>';
}

$wpacuAssetPositionHtml = ob_get_clean();

if ($wpacuAssetPositionHtml !== '') {
    $extraInfo[] = $wpacuAssetPositionHtml;
}

unset(
    $wpacuAssetPositionHtml,
    $wpacuAssetPositionIsPro,
    $wpacuAssetPositionKind,
    $wpacuAssetPositionLabel,
    $wpacuAssetPositionNoSrcText,
    $wpacuAssetPositionUtm
);
