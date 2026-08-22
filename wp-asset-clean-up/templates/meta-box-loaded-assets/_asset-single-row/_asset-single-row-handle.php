<?php
if (! isset($data, $assetType, $assetTypeS, $assetTypeAbbr, $isCoreFile, $hideCoreFiles, $childHandles)) {
    exit;
}

require WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/asset-row-handle.php';
