<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;

if (! isset($data)) {
	exit;
}

$tabIdArea = 'wpacu-setting-local-fonts';
$styleTabContent = isset($selectedTabArea) && ($selectedTabArea === $tabIdArea) ? 'style="display: table-cell;"' : '';

$ddOptions = array(
	'swap'     => 'swap (most used)',
	'auto'     => 'auto',
	'block'    => 'block',
	'fallback' => 'fallback',
	'optional' => 'optional'
);

$localFontsPreloadScanConfig = isset($data['local_fonts_preload_scan']) && is_array($data['local_fonts_preload_scan'])
    ? $data['local_fonts_preload_scan']
    : array();

?>
<div id="<?php echo esc_attr($tabIdArea); ?>" class="wpacu-settings-tab-content" <?php echo wp_kses($styleTabContent, array('style' => array())); ?>>
    <header class="wpacu-local-fonts-header">
        <div class="wpacu-local-fonts-eyebrow"><?php esc_html_e('Font performance', 'wp-asset-clean-up'); ?></div>
        <h2><?php esc_html_e('Optimize local fonts for faster, more stable rendering', 'wp-asset-clean-up'); ?></h2>
        <p><?php esc_html_e('Control how self-hosted fonts appear while loading and review manual preload URLs to prevent outdated or unnecessary font downloads.', 'wp-asset-clean-up'); ?></p>
    </header>
    <table class="wpacu-form-table">
        <tr valign="top" class="wpacu-local-fonts-display-row">
            <th scope="row" class="setting_title">
				    <?php esc_html_e('Font rendering behavior', 'wp-asset-clean-up'); ?>
                <span class="wpacu-local-fonts-display-row__badge"><?php esc_html_e('Site-wide', 'wp-asset-clean-up'); ?></span>
            </th>
            <td>
                <section class="wpacu-local-fonts-display" aria-labelledby="wpacuLocalFontsDisplayTitle">
                    <header class="wpacu-local-fonts-display__header">
                        <span class="wpacu-local-fonts-display__icon" aria-hidden="true">Aa</span>
                        <div>
                            <h3 id="wpacuLocalFontsDisplayTitle"><?php esc_html_e('Keep text visible while local fonts load', 'wp-asset-clean-up'); ?></h3>
                            <p><?php esc_html_e('Choose the browser rendering strategy applied to @font-face rules across the site.', 'wp-asset-clean-up'); ?></p>
                        </div>
                    </header>

                    <div class="wpacu-local-fonts-display__controls">
                        <div class="wpacu-local-fonts-display__control">
                            <label for="wpacu_local_fonts_display"><code>font-display</code> <?php esc_html_e('value', 'wp-asset-clean-up'); ?></label>
                            <select id="wpacu_local_fonts_display" name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[local_fonts_display]">
                                <option value=""><?php esc_html_e('Do not apply (default)', 'wp-asset-clean-up'); ?></option>
							    <?php foreach ($ddOptions as $ddOptionValue => $ddOptionText) : ?>
                                    <option value="<?php echo esc_attr($ddOptionValue); ?>" <?php selected($data['local_fonts_display'], $ddOptionValue); ?>><?php echo esc_html($ddOptionText); ?></option>
							    <?php endforeach; ?>
                            </select>
                            <span><?php esc_html_e('“swap” is the most common choice for keeping text immediately readable.', 'wp-asset-clean-up'); ?></span>
                        </div>

                        <fieldset class="wpacu-local-fonts-display__control">
                            <legend><?php esc_html_e('Existing values', 'wp-asset-clean-up'); ?></legend>
                            <span class="wpacu-local-fonts-display__choices">
                                <label for="wpacu_local_fonts_display_overwrite_no">
                                    <input id="wpacu_local_fonts_display_overwrite_no" <?php checked(! $data['local_fonts_display_overwrite']); ?> type="radio" name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[local_fonts_display_overwrite]" value="" />
                                    <span><strong><?php esc_html_e('Preserve', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Keep values already defined', 'wp-asset-clean-up'); ?></small></span>
                                </label>
                                <label for="wpacu_local_fonts_display_overwrite_yes">
                                    <input id="wpacu_local_fonts_display_overwrite_yes" <?php checked($data['local_fonts_display_overwrite']); ?> type="radio" name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[local_fonts_display_overwrite]" value="1" />
                                    <span><strong><?php esc_html_e('Overwrite', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Replace every existing value', 'wp-asset-clean-up'); ?></small></span>
                                </label>
                            </span>
                        </fieldset>
                    </div>

                    <div class="wpacu-local-fonts-display__notice">
                        <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                        <p><?php esc_html_e('Asset CleanUp processes the loaded CSS without modifying the original theme or plugin files.', 'wp-asset-clean-up'); ?> <a data-wpacu-modal-target="wpacu-local-fonts-display-info-target" href="#wpacu-local-fonts-display-info"><?php esc_html_e('How it works', 'wp-asset-clean-up'); ?></a></p>
                    </div>

                    <div class="wpacu-local-fonts-display__output">
                        <span><?php esc_html_e('Generated CSS location', 'wp-asset-clean-up'); ?></span>
                        <code><?php echo esc_html(OptimizeCommon::getRelPathPluginCacheDir()); ?></code>
                    </div>

                    <footer class="wpacu-local-fonts-display__footer">
                        <strong><?php esc_html_e('Learn more', 'wp-asset-clean-up'); ?></strong>
                        <nav aria-label="<?php esc_attr_e('Font display resources', 'wp-asset-clean-up'); ?>">
                            <a target="_blank" rel="noopener noreferrer" href="https://chrome.dev/f/learn_performance_fonts/?attributionHidden=true&amp;previewSize=100&amp;sidebarCollapsed=true"><?php esc_html_e('Interactive loading demo', 'wp-asset-clean-up'); ?></a>
                            <a target="_blank" rel="noopener noreferrer" href="https://www.sitepoint.com/css-font-display-future-font-rendering-web/"><?php esc_html_e('Slow-network video examples', 'wp-asset-clean-up'); ?></a>
                            <a target="_blank" rel="noopener noreferrer" href="https://web.dev/learn/performance/optimize-web-fonts"><?php esc_html_e('Choose a loading strategy', 'wp-asset-clean-up'); ?></a>
                            <a target="_blank" rel="noopener noreferrer" href="https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/%40font-face/font-display"><?php esc_html_e('MDN reference', 'wp-asset-clean-up'); ?></a>
                        </nav>
                    </footer>
                </section>
            </td>
        </tr>
        <tr valign="top" class="wpacu-fonts-legacy-table-row wpacu-local-fonts-legacy-table-row">
            <th scope="row" class="setting_title">
                <?php esc_html_e('Manual Font Preload URLs', 'wp-asset-clean-up'); ?>
                <span class="wpacu-fonts-legacy-badge"><?php esc_html_e('Legacy', 'wp-asset-clean-up'); ?></span>
                <p class="wpacu_subtitle"><small><em><?php esc_html_e('One font URL per line', 'wp-asset-clean-up'); ?></em></small></p>
            </th>
            <td>
                <?php
                $siteWideCandidateThresholdPercent = isset($localFontsPreloadScanConfig['siteWideCandidateMinCheckCoverage'])
                    ? (int) round(((float) $localFontsPreloadScanConfig['siteWideCandidateMinCheckCoverage']) * 100)
                    : 80;

                $fontPreloadScanner = array(
                    'provider'             => 'local',
                    'root_id'              => 'wpacu-local-font-preload-legacy',
                    'dom_prefix'           => 'wpacuLocalFontPreload',
                    'legacy_title'         => __('Manual, site-wide font preloading', 'wp-asset-clean-up'),
                    'legacy_status'        => __('Legacy manual mode', 'wp-asset-clean-up'),
                    'legacy_description'   => __('This setting is kept for backward compatibility. The listed URLs are not synchronized automatically when a theme, plugin, generated CSS file, cache layer, or CDN changes its font files.', 'wp-asset-clean-up'),
                    'warning_title'        => __('Use with care.', 'wp-asset-clean-up'),
                    'warning_text'         => __('Every listed URL is preloaded on every applicable page. A font can still be valid and used on one landing page while wasting bandwidth as a high-priority preload everywhere else.', 'wp-asset-clean-up'),
                    'field_label'          => __('Font file URLs', 'wp-asset-clean-up'),
                    'textarea_id'          => 'wpacu_local_fonts_preload_files',
                    'textarea_name'        => WPACU_PLUGIN_ID . '_settings[local_fonts_preload_files]',
                    'textarea_value'       => $data['local_fonts_preload_files'],
                    'textarea_placeholder' => '/wp-content/themes/your-theme/fonts/font-file.woff2',
                    'field_help'           => __('Removing an entry stops only Asset CleanUp’s site-wide preload. It does not remove the font or its <code>@font-face</code> rule. Review and broadly used fonts remain protected from removal.', 'wp-asset-clean-up'),
                    'scan_title'           => __('Audit whether each URL deserves a site-wide preload', 'wp-asset-clean-up'),
                    'scan_description'     => sprintf(
                        __('Asset CleanUp suppresses its manual preload and checks representative pages in the current browser. Exact requests, deterministic rendered usage and uniquely attributable loaded faces protect against cache-related false negatives. Fonts confirmed on every checked page and at least <strong>%d%% of checks</strong> are protected as likely site-wide candidates. Coverage findings are advisory. A cleanup checkbox is shown only for deterministic issues such as a duplicate, invalid URL, missing local file, replaced version URL, or an identical preload already supplied everywhere.', 'wp-asset-clean-up'),
                        $siteWideCandidateThresholdPercent
                    ),
                    'start_label'          => __('Audit Manual Font Preloads', 'wp-asset-clean-up'),
                    'scope_items'          => array(
                        __('Current browser', 'wp-asset-clean-up'),
                        __('Desktop viewport', 'wp-asset-clean-up'),
                        __('Mobile viewport', 'wp-asset-clean-up'),
                        sprintf(
                            __('Up to %d pages', 'wp-asset-clean-up'),
                            isset($localFontsPreloadScanConfig['maxPages']) ? (int) $localFontsPreloadScanConfig['maxPages'] : 6
                        )
                    ),
                    'extra_summary'        => __('Include important templates the automatic selection might miss', 'wp-asset-clean-up'),
                    'extra_help'           => sprintf(
                        __('Add up to %d public URLs from this WordPress site, one per line. These pages are prioritised in the audit.', 'wp-asset-clean-up'),
                        isset($localFontsPreloadScanConfig['maxExtraUrls']) ? (int) $localFontsPreloadScanConfig['maxExtraUrls'] : 2
                    ),
                    'example_urls'         => array(
                        '/wp-content/themes/your-theme/fonts/lato.woff2',
                        '/wp-content/plugins/plugin-name/fonts/icons.woff2?ver=4.5.0'
                    ),
                    'generated_examples'   => array(
                        '<link rel="preload" as="font" href="/fonts/lato.woff2" data-wpacu-preload-local-font="1" crossorigin>'
                    ),
                    'scanner_config'       => $localFontsPreloadScanConfig
                );

                require WPACU_PLUGIN_DIR . '/templates/_common/fonts/preload-scanner.php';
                unset($fontPreloadScanner, $siteWideCandidateThresholdPercent);
                ?>
            </td>
        </tr>
    </table>
</div>

<?php
$fontDisplayModalVariant = 'local';
require WPACU_PLUGIN_DIR . '/templates/_common/modals/font-display-reference.php';
unset($fontDisplayModalVariant);
?>
