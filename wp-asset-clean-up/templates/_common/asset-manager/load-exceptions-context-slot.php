<?php
if (! defined('ABSPATH')) {
    exit;
}

if (defined('WPACU_PRO_PLUGIN_VERSION') && in_array($data['bulk_unloaded_type'], array('taxonomy', 'author'))) {
    do_action('wpacu_pro_bulk_load_output', $data, $data['row']['obj'], $assetTypeS);
}
