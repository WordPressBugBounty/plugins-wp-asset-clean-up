<?php
if (! defined('ABSPATH') || ! isset($data, $assetType, $assetTypeS)) {
    exit;
}

require WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/script-attribute-controls.php';

$childHandles = isset($data['all_deps']['parent_to_child'][$assetType][$data['row']['obj']->handle])
    ? $data['all_deps']['parent_to_child'][$assetType][$data['row']['obj']->handle]
    : array();
$handleAllStatuses = array();

if (! empty($childHandles)) {
    $handleAllStatuses[] = 'is_parent';
}

if (! empty($data['row']['obj']->deps)) {
    $handleAllStatuses[] = 'is_child';
}

if (empty($handleAllStatuses)) {
    $handleAllStatuses[] = 'is_independent';
}

$showMatchMediaFeature = $assetType === 'styles'
    || in_array('is_independent', $handleAllStatuses)
    || (in_array('is_child', $handleAllStatuses) && ! in_array('is_parent', $handleAllStatuses));

if (! $showMatchMediaFeature) {
    return;
}

$assetHasDistinctiveMediaAttr = isset($data['row']['obj']->args)
    && $data['row']['obj']->args
    && $data['row']['obj']->args !== 'all';
$showMatchMediaAlertForParentCss = $assetType === 'styles' && in_array('is_parent', $handleAllStatuses);

require WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/media-query-load-control.php';

unset($assetHasDistinctiveMediaAttr, $childHandles, $handleAllStatuses, $showMatchMediaAlertForParentCss, $showMatchMediaFeature);
