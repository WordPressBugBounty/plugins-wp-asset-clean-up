<?php
$wpacuBulkPreviewTitle = __('Site-wide async and defer attributes', 'wp-asset-clean-up');
$wpacuBulkPreviewDescription = __('Review JavaScript files whose <code>&lt;script&gt;</code> tags use async or defer site-wide.', 'wp-asset-clean-up');
$wpacuBulkPreviewMedium = 'script_attrs';
require WPACU_PLUGIN_DIR . '/templates/_admin-page-settings-bulk-changes/_pro-feature-preview.php';
