<?php
if (! defined('ABSPATH')) {
    exit;
}

$wpacuOptimizeJsIsPro = false;
require WPACU_PLUGIN_DIR . '/templates/_common/settings/optimize-js.php';
unset($wpacuOptimizeJsIsPro);
