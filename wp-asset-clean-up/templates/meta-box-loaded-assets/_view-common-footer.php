<?php
// no direct access
use WpAssetCleanUp\HardcodedAssets;

if (! isset($data)) {
    exit;
}

if ( ! empty( $data['all']['hardcoded'] ) ) {
    $data['print_outer_html'] = true; // AJAX call from the Dashboard
    include_once __DIR__ . '/view-hardcoded-'.HardcodedAssets::viewHardcodedModeLayout($data['plugin_settings']).'.php';
} elseif (isset($data['is_frontend_view']) && $data['is_frontend_view']) {
    echo HardcodedAssets::getHardCodedManageAreaForFrontEndView($data); // AJAX call from the front-end view
}

include_once __DIR__ . '/_page-options.php';

include_once __DIR__ . '/_inline_js.php';
