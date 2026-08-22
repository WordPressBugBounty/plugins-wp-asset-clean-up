<?php
if (! defined('ABSPATH')) {
    exit;
}

$googleFontsTemplateDir = __DIR__;
$googleFontsTemplateFile = __FILE__;
require WPACU_PLUGIN_DIR . '/templates/_common/fonts/google.php';
unset($googleFontsTemplateDir, $googleFontsTemplateFile);
