<?php
if ( ! isset($data)) {
    exit;
}

$wpacuLitePreviewLocation = 'front';
$wpacuLitePreviewContext = 'plugins_manager_front_bottom';
$wpacuLitePreviewCtaLabel = __('Unlock front-end plugin rules with Pro', 'wp-asset-clean-up');

include __DIR__ . '/_plugin-list.php';
