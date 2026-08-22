<?php
if (! defined('ABSPATH')) {
    exit;
}

$wpacuAssetManagerIsPro = defined('WPACU_PRO_PLUGIN_VERSION');

switch ($wpacuAssetManagerFormContext) {
    case 'search':
        $wpacuAssetManagerContextNote = $wpacuAssetManagerIsPro
            ? __('This keyword is used only to generate the preview URL. The asset rules configured below apply to all WordPress search results pages.', 'wp-asset-clean-up')
            : __('This keyword is used only to generate the preview URL. In Pro, rules configured for this context apply to all WordPress search results pages.', 'wp-asset-clean-up');
        break;

    case 'author':
        $wpacuAssetManagerContextNote = $wpacuAssetManagerIsPro
            ? __('Once the author is selected, the CSS & JS manager will load to manage the assets for the chosen author archive.', 'wp-asset-clean-up')
            : __('Once the author is selected, the CSS & JS manager will load the real assets for a read-only preview of that author archive.', 'wp-asset-clean-up');
        break;

    case 'taxonomy':
        $wpacuAssetManagerContextNote = $wpacuAssetManagerIsPro
            ? __('Once the term is selected, the CSS & JS manager will load to manage the assets for the chosen taxonomy archive.', 'wp-asset-clean-up')
            : __('Once the term is selected, the CSS & JS manager will load the real assets for a read-only preview of that taxonomy archive.', 'wp-asset-clean-up');
        break;

    default:
        $wpacuAssetManagerContextNote = '';
}

echo esc_html($wpacuAssetManagerContextNote);

unset($wpacuAssetManagerContextNote, $wpacuAssetManagerIsPro, $wpacuAssetManagerFormContext);
