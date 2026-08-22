<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Shared extension point for edition-specific bulk unload controls.
 *
 * The Lite build keeps the layout seam but does not register the Pro callback.
 */
do_action('wpacu_pro_bulk_unload_output', $data, $data['row']['obj'], $assetTypeS);
