<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Shared extension point rendered immediately before the front-end asset list.
 *
 * Lite intentionally publishes the same hook. With no Pro callback attached,
 * WordPress simply continues without producing output.
 */
do_action('wpacu_pro_frontend_before_asset_list');
