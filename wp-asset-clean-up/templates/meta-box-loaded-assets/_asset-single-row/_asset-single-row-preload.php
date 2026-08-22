<?php
if (! isset($data, $assetType, $isAssetPreload)) {
    exit;
}

require WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/asset-row-preload.php';
