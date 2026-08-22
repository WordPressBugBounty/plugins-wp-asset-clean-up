<?php
if ( ! isset($data)) {
    exit;
}

$wpacuLitePreviewLocation = 'dash';
$wpacuLitePreviewContext = 'plugins_manager_dash_bottom';
$wpacuLitePreviewCtaLabel = __('Unlock Dashboard plugin rules with Pro', 'wp-asset-clean-up');

include __DIR__ . '/_plugin-list.php';
