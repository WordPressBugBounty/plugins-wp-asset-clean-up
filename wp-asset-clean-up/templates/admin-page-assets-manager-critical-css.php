<?php

use WpAssetCleanUp\Admin\CriticalCssAdmin;

/*
 * No direct access to this file
 */
if ( ! isset($data) ) {
    exit;
}

$data = CriticalCssAdmin::prepareManagerViewData($data);

$criticalCssLayout = isset($data['critical_css_layout']) ? $data['critical_css_layout'] : 'compact';
$criticalCssConfig = isset($data['critical_css_config']) && is_array($data['critical_css_config'])
    ? $data['critical_css_config']
    : array();
$locationKey = isset($data['critical_css_location_key']) ? $data['critical_css_location_key'] : false;
$layoutFile  = __DIR__ . '/_admin-pages-assets-manager-critical-css/_common/' . $criticalCssLayout . '/_layout.php';

if ( ! is_file($layoutFile) ) {
    $layoutFile = __DIR__ . '/_admin-pages-assets-manager-critical-css/_common/compact/_layout.php';
}

include $layoutFile;
