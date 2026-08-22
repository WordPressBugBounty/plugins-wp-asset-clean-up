<?php
if (! defined('ABSPATH')) {
    exit;
}

$isMarkedForPostTypeViaTaxUnload = false;
$isMarkedForTaxUnload = false;
$isMarkedForRegExUnload = false;

if (defined('WPACU_PRO_PLUGIN_VERSION')) {
    $isMarkedForPostTypeViaTaxUnload = isset($data['handle_unload_post_type_via_tax'][$assetType][$data['row']['obj']->handle]['enable'], $data['handle_unload_post_type_via_tax'][$assetType][$data['row']['obj']->handle]['values'])
        && $data['handle_unload_post_type_via_tax'][$assetType][$data['row']['obj']->handle]['enable']
        && ! empty($data['handle_unload_post_type_via_tax'][$assetType][$data['row']['obj']->handle]['values']);
    $isMarkedForTaxUnload = ! empty($data['handle_unload_via_tax'][$assetType])
        && in_array($data['row']['obj']->handle, $data['handle_unload_via_tax'][$assetType]);
    $isMarkedForRegExUnload = ! empty($data['handle_unload_regex'][$assetType][$data['row']['obj']->handle]['enable']);
}
