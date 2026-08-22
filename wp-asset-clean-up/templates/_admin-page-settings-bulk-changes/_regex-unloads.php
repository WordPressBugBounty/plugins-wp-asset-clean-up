<?php
$wpacuBulkPreviewTitle = __('RegEx unload rules', 'wp-asset-clean-up');
$wpacuBulkPreviewDescription = __('Review and edit CSS/JS unload rules that match request URIs.', 'wp-asset-clean-up');
$wpacuBulkPreviewHelp = __('New rules are added from the CSS & JavaScript Load Manager for a page that loads the targeted asset.', 'wp-asset-clean-up');
$wpacuBulkPreviewMedium = 'regex_unloads';
require WPACU_PLUGIN_DIR . '/templates/_admin-page-settings-bulk-changes/_pro-feature-preview.php';
