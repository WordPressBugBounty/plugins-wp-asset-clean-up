<?php
if (! isset($data, $assetHandleHasSrc, $assetPosition, $assetPositionNew, $assetType, $assetTypeS)) {
    exit;
}

require WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/asset-row-position.php';
