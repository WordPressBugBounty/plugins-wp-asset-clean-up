<?php
if (! defined('ABSPATH')) {
    exit;
}

$wpacuOptimizeCssIsPro = false;
require WPACU_PLUGIN_DIR . '/templates/_common/settings/optimize-css.php';
unset($wpacuOptimizeCssIsPro);
