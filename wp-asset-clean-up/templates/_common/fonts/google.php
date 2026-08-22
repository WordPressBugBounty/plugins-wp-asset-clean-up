<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
    exit;
}

$tabIdArea = 'wpacu-setting-google-fonts';
$styleTabContent = isset($selectedTabArea) && ($selectedTabArea === $tabIdArea) ? 'style="display: table-cell;"' : '';

$data['dd_options'] = array(
    'swap'     => 'swap (most used)',
    'auto'     => 'auto',
    'block'    => 'block',
    'fallback' => 'fallback',
    'optional' => 'optional'
);
?>
<div id="<?php echo esc_attr($tabIdArea); ?>" class="wpacu-settings-tab-content wpacu-google-fonts-settings" <?php echo wp_kses($styleTabContent, array('style' => array())); ?>>
    <header class="wpacu-google-fonts-header">
        <div class="wpacu-google-fonts-eyebrow"><?php esc_html_e('Google Fonts control', 'wp-asset-clean-up'); ?></div>
        <h2><?php esc_html_e('Control Google Fonts delivery and removal', 'wp-asset-clean-up'); ?></h2>
        <p><?php esc_html_e('Tune request loading and rendering, audit legacy manual preloads, or prevent Google-hosted font delivery from one focused settings area.', 'wp-asset-clean-up'); ?></p>
    </header>

    <?php
    \WpAssetCleanUp\Admin\SettingsAdmin::printSubTabsOutput($googleFontsTemplateDir, $data, $googleFontsTemplateFile);
    ?>
</div>

<?php
$fontDisplayModalVariant = 'google';
require WPACU_PLUGIN_DIR . '/templates/_common/modals/font-display-reference.php';
unset($fontDisplayModalVariant);
?>
