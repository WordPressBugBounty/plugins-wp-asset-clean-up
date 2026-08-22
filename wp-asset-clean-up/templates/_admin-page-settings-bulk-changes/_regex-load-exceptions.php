<?php
$wpacuBulkPreviewTitle = __('RegEx load exceptions', 'wp-asset-clean-up');
$wpacuBulkPreviewDescription = __('Review exceptions that restore a bulk-unloaded CSS/JS asset when a request URI matches.', 'wp-asset-clean-up');
$wpacuBulkPreviewHelp = __('New exceptions are added from the CSS & JavaScript Load Manager after an asset has a bulk unload rule.', 'wp-asset-clean-up');
$wpacuBulkPreviewMedium = 'regex_load_exceptions';
require WPACU_PLUGIN_DIR . '/templates/_admin-page-settings-bulk-changes/_pro-feature-preview.php';
