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
            ? __('Select an author to manage CSS/JS on their archive page.', 'wp-asset-clean-up')
            : __('Select an author for a read-only preview of CSS/JS on their archive page.', 'wp-asset-clean-up');
        break;

    case 'taxonomy':
        $wpacuAssetManagerContextNote = ($wpacuAssetManagerIsPro || (isset($taxonomy) && in_array($taxonomy, array('category', 'post_tag'), true)))
            ? __('Select a term to manage CSS/JS on its archive page.', 'wp-asset-clean-up')
            : __('Select a term for a read-only preview of CSS/JS on its archive page.', 'wp-asset-clean-up');
        break;

    default:
        $wpacuAssetManagerContextNote = '';
}

echo esc_html($wpacuAssetManagerContextNote);

unset($wpacuAssetManagerContextNote, $wpacuAssetManagerIsPro, $wpacuAssetManagerFormContext);
