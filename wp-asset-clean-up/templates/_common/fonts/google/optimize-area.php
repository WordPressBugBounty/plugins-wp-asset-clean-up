<?php

use WpAssetCleanUp\Misc;

if (! isset($data)) {
    exit;
}

$ddOptions = $data['dd_options'];
$googleFontsPreloadScanConfig = isset($data['google_fonts_preload_scan']) && is_array($data['google_fonts_preload_scan'])
    ? $data['google_fonts_preload_scan']
    : array();
$googleFontsPreloadFilesValue = isset($data['google_fonts_preload_files_raw'])
    ? $data['google_fonts_preload_files_raw']
    : $data['google_fonts_preload_files'];
$settingsName = WPACU_PLUGIN_ID . '_settings';
?>

<?php if ($data['google_fonts_remove']) : ?>
    <div class="wpacu-google-fonts-disabled-notice" role="note">
        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
        <div>
            <strong><?php esc_html_e('Google Fonts removal is currently enabled.', 'wp-asset-clean-up'); ?></strong>
            <p><?php esc_html_e('The delivery options below are preserved but inactive while the site is intentionally preventing Google Fonts from loading. Turn off “Remove Google Fonts” and save before auditing the manual preload list.', 'wp-asset-clean-up'); ?></p>
        </div>
    </div>
<?php endif; ?>

<div class="wpacu-google-fonts-layout<?php echo $data['google_fonts_remove'] ? ' is-removal-enabled' : ''; ?>">
    <section class="wpacu-google-fonts-card" aria-labelledby="wpacuGoogleFontsDeliveryTitle">
        <header class="wpacu-google-fonts-card__header">
            <span class="wpacu-google-fonts-card__icon" aria-hidden="true">Aa</span>
            <div>
                <span class="wpacu-google-fonts-card__eyebrow"><?php esc_html_e('Core delivery', 'wp-asset-clean-up'); ?></span>
                <h3 id="wpacuGoogleFontsDeliveryTitle"><?php esc_html_e('Keep Google Fonts readable and connect efficiently', 'wp-asset-clean-up'); ?></h3>
                <p><?php esc_html_e('Choose the display strategy added to eligible Google Fonts requests and optionally warm up the font-file origin.', 'wp-asset-clean-up'); ?></p>
            </div>
        </header>

        <div class="wpacu-google-fonts-card__body wpacu-google-fonts-delivery-grid">
            <div class="wpacu-google-fonts-control">
                <div class="wpacu-google-fonts-control__field-row">
                    <label for="wpacu_google_fonts_display"><code>font-display</code> <?php esc_html_e('behavior', 'wp-asset-clean-up'); ?></label>
                    <select id="wpacu_google_fonts_display" name="<?php echo esc_attr($settingsName); ?>[google_fonts_display]">
                        <option value=""><?php esc_html_e('Do not apply (default)', 'wp-asset-clean-up'); ?></option>
                        <?php foreach ($ddOptions as $ddOptionValue => $ddOptionText) : ?>
                            <option value="<?php echo esc_attr($ddOptionValue); ?>" <?php selected($data['google_fonts_display'], $ddOptionValue); ?>><?php echo esc_html($ddOptionText); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p><?php esc_html_e('Asset CleanUp adds the selected display parameter when the request does not already define one. Choose Overwrite below to replace existing values too.', 'wp-asset-clean-up'); ?></p>
                <div class="wpacu-google-fonts-display-dependent<?php echo empty($data['google_fonts_display']) ? ' is-inactive' : ''; ?>">
                    <fieldset class="wpacu-google-fonts-display-existing">
                        <legend><?php esc_html_e('Existing values', 'wp-asset-clean-up'); ?></legend>
                        <span class="wpacu-google-fonts-display-choices">
                            <label for="wpacu_google_fonts_display_overwrite_no">
                                <input id="wpacu_google_fonts_display_overwrite_no" <?php checked(empty($data['google_fonts_display_overwrite'])); ?> type="radio" name="<?php echo esc_attr($settingsName); ?>[google_fonts_display_overwrite]" value="" />
                                <span><strong><?php esc_html_e('Preserve', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Keep values already defined', 'wp-asset-clean-up'); ?></small></span>
                            </label>
                            <label for="wpacu_google_fonts_display_overwrite_yes">
                                <input id="wpacu_google_fonts_display_overwrite_yes" <?php checked(! empty($data['google_fonts_display_overwrite'])); ?> type="radio" name="<?php echo esc_attr($settingsName); ?>[google_fonts_display_overwrite]" value="1" />
                                <span><strong><?php esc_html_e('Overwrite', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('Replace every existing value', 'wp-asset-clean-up'); ?></small></span>
                            </label>
                        </span>
                    </fieldset>
                    <a data-wpacu-modal-target="wpacu-google-fonts-display-info-target" href="#wpacu-google-fonts-display-info"><?php esc_html_e('Compare loading behaviors', 'wp-asset-clean-up'); ?></a>
                </div>
            </div>

            <div class="wpacu-google-fonts-control wpacu-google-fonts-control--switch">
                <div class="wpacu-google-fonts-control__switch-row">
                    <label class="wpacu_switch" for="wpacu_google_fonts_preconnect">
                        <input id="wpacu_google_fonts_preconnect"
                               type="checkbox"
                               data-target-opacity="#google_fonts_preconnect_wrap"
                            <?php checked((int) $data['google_fonts_preconnect'], 1); ?>
                               name="<?php echo esc_attr($settingsName); ?>[google_fonts_preconnect]"
                               value="1" />
                        <span class="wpacu_slider wpacu_round" aria-hidden="true"></span>
                    </label>
                    <div>
                        <strong><?php esc_html_e('Preconnect to fonts.gstatic.com', 'wp-asset-clean-up'); ?></strong>
                        <p><?php esc_html_e('Start DNS, TCP and TLS work before the stylesheet requests the font files.', 'wp-asset-clean-up'); ?></p>
                    </div>
                </div>
                <div id="google_fonts_preconnect_wrap" class="wpacu-google-fonts-generated-output"<?php echo ! $data['google_fonts_preconnect'] ? ' style="opacity: 0.4;"' : ''; ?>>
                    <span><?php esc_html_e('Generated in the document head', 'wp-asset-clean-up'); ?></span>
                    <code>&lt;link href="https://fonts.gstatic.com" crossorigin rel="preconnect" /&gt;</code>
                </div>
            </div>
        </div>

        <details class="wpacu-google-fonts-card__technical">
            <summary><?php esc_html_e('Generated request examples', 'wp-asset-clean-up'); ?></summary>
            <div class="wpacu-google-fonts-code-list">
                <code>&lt;link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto+Mono&amp;display=swap"&gt;</code>
                <code>&lt;link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&amp;display=swap"&gt;</code>
            </div>
        </details>
    </section>

    <section class="wpacu-google-fonts-card wpacu-google-fonts-card--legacy" aria-labelledby="wpacuGoogleFontsCombineTitle">
        <header class="wpacu-google-fonts-card__header">
            <span class="wpacu-google-fonts-card__icon is-legacy" aria-hidden="true"><span class="dashicons dashicons-admin-links"></span></span>
            <div>
                <div class="wpacu-google-fonts-card__title-line">
                    <span class="wpacu-google-fonts-card__eyebrow"><?php esc_html_e('Advanced request loading', 'wp-asset-clean-up'); ?></span>
                </div>
                <div class="wpacu-google-fonts-card__heading-line">
                    <h3 id="wpacuGoogleFontsCombineTitle"><?php esc_html_e('Combine compatible Google Fonts requests', 'wp-asset-clean-up'); ?></h3>
                    <span class="wpacu-google-fonts-card__badge"><?php esc_html_e('Legacy compatibility', 'wp-asset-clean-up'); ?></span>
                </div>
                <p><?php esc_html_e('Keep this for established configurations that still depend on the legacy Google Fonts CSS API. Modern CSS2, variable-font and icon requests are deliberately left outside unsafe combinations.', 'wp-asset-clean-up'); ?></p>
            </div>
        </header>

        <div class="wpacu-google-fonts-card__enable-row">
            <label class="wpacu_switch" for="wpacu_google_fonts_combine">
                <input id="wpacu_google_fonts_combine"
                       type="checkbox"
                       data-target-opacity="#google_fonts_combine_wrap"
                    <?php checked((int) $data['google_fonts_combine'], 1); ?>
                       name="<?php echo esc_attr($settingsName); ?>[google_fonts_combine]"
                       value="1" />
                <span class="wpacu_slider wpacu_round" aria-hidden="true"></span>
            </label>
            <label class="wpacu-google-fonts-card__enable-copy" for="wpacu_google_fonts_combine">
                <strong><?php esc_html_e('Enable legacy request combination', 'wp-asset-clean-up'); ?></strong>
            </label>
        </div>

        <div id="google_fonts_combine_wrap" class="wpacu-google-fonts-card__body"<?php echo ! $data['google_fonts_combine'] ? ' style="opacity: 0.4;"' : ''; ?>>
            <fieldset class="wpacu-google-fonts-loading-methods">
                <legend><?php esc_html_e('Loading method', 'wp-asset-clean-up'); ?></legend>

                <label class="wpacu-google-fonts-method" for="google_fonts_combine_type_rb">
                    <input id="google_fonts_combine_type_rb"
                           class="google_fonts_combine_type"
                           type="radio"
                           name="<?php echo esc_attr($settingsName); ?>[google_fonts_combine_type]"
                        <?php checked($data['google_fonts_combine_type'], ''); ?>
                           value="" />
                    <span>
                        <strong><?php esc_html_e('Render-blocking', 'wp-asset-clean-up'); ?></strong>
                        <small><?php esc_html_e('Default and least surprising behavior', 'wp-asset-clean-up'); ?></small>
                    </span>
                </label>

                <label class="wpacu-google-fonts-method" for="google_fonts_combine_type_async_preload">
                    <input id="google_fonts_combine_type_async_preload"
                           class="google_fonts_combine_type"
                           type="radio"
                           name="<?php echo esc_attr($settingsName); ?>[google_fonts_combine_type]"
                        <?php checked($data['google_fonts_combine_type'], 'async_preload'); ?>
                           value="async_preload" />
                    <span>
                        <strong><?php esc_html_e('Async CSS preload', 'wp-asset-clean-up'); ?></strong>
                        <small><?php esc_html_e('Preload the stylesheet, then apply it', 'wp-asset-clean-up'); ?></small>
                    </span>
                </label>

                <label class="wpacu-google-fonts-method" for="google_fonts_combine_type_async">
                    <input id="google_fonts_combine_type_async"
                           class="google_fonts_combine_type"
                           type="radio"
                           name="<?php echo esc_attr($settingsName); ?>[google_fonts_combine_type]"
                        <?php checked($data['google_fonts_combine_type'], 'async'); ?>
                           value="async" />
                    <span>
                        <strong><?php esc_html_e('Web Font Loader', 'wp-asset-clean-up'); ?></strong>
                        <small><?php esc_html_e('Legacy JavaScript-based loading', 'wp-asset-clean-up'); ?></small>
                    </span>
                </label>
            </fieldset>

            <details class="wpacu-google-fonts-card__technical wpacu-google-fonts-loading-details">
                <summary><?php esc_html_e('View the selected method output and cautions', 'wp-asset-clean-up'); ?></summary>
                <div class="wpacu-google-fonts-loading-details__body">
                    <div id="wpacu_google_fonts_combine_type_rb_info_area" class="wpacu_google_fonts_combine_type_area" <?php if ($data['google_fonts_combine_type']) { echo 'style="display: none;"'; } ?>>
                        <p><strong><?php esc_html_e('Render-blocking output', 'wp-asset-clean-up'); ?></strong></p>
                        <p><?php esc_html_e('Compatible legacy requests are merged and the resulting stylesheet remains render-blocking.', 'wp-asset-clean-up'); ?></p>
                        <code>&lt;link rel="stylesheet" id="wpacu-combined-google-fonts-css" href="https://fonts.googleapis.com/css?family=Droid+Sans%7CInconsolata:700"&gt;</code>
                    </div>

                    <div id="wpacu_google_fonts_combine_type_async_preload_info_area" class="wpacu_google_fonts_combine_type_area" <?php if ($data['google_fonts_combine_type'] !== 'async_preload') { echo 'style="display: none;"'; } ?>>
                        <p><strong><?php esc_html_e('Async CSS preload output', 'wp-asset-clean-up'); ?></strong></p>
                        <p><?php esc_html_e('The combined stylesheet is preloaded and changed to a stylesheet after it loads. A noscript fallback is kept.', 'wp-asset-clean-up'); ?></p>
                        <code><?php
                            $asyncPreloadSnippet = <<<HTML
&lt;link rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'" id="wpacu-combined-google-fonts-css-preload" href="https://fonts.googleapis.com/css?family=Droid+Sans%7CInconsolata:700"&gt;
&lt;noscript&gt;&lt;link rel="stylesheet" id="wpacu-combined-google-fonts-css" href="https://fonts.googleapis.com/css?family=Droid+Sans%7CInconsolata:700"&gt;&lt;/noscript&gt;
HTML;
                            echo nl2br($asyncPreloadSnippet);
                        ?></code>
                    </div>

                    <div id="wpacu_google_fonts_combine_type_async_info_area" class="wpacu_google_fonts_combine_type_area" <?php if ($data['google_fonts_combine_type'] !== 'async') { echo 'style="display: none;"'; } ?>>
                        <div class="wpacu-google-fonts-method-warning">
                            <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                            <p><?php esc_html_e('Test this legacy method carefully. Loading through webfont.js can delay the final font and may fail on restrictive third-party responses.', 'wp-asset-clean-up'); ?></p>
                        </div>
                        <p><strong><?php esc_html_e('Web Font Loader output', 'wp-asset-clean-up'); ?></strong></p>
                        <code><?php
                            $scriptType = Misc::getScriptTypeAttribute();
                            $asyncWebFontLoaderSnippet = <<<HTML
&lt;script id='wpacu-google-fonts-async-load' {$scriptType}&gt;
WebFontConfig = { google: { families: ['Droid+Sans', 'Inconsolata:700'] } };
(function(wpacuD) {
&nbsp;&nbsp;var wpacuWf = wpacuD.createElement('script'), wpacuS = wpacuD.scripts[0];
&nbsp;&nbsp;wpacuWf.src = 'https://ajax.googleapis.com/ajax/libs/webfont/1.6.26/webfont.js';
&nbsp;&nbsp;wpacuWf.async = true;
&nbsp;&nbsp;wpacuS.parentNode.insertBefore(wpacuWf, wpacuS);
})(document);
&lt;/script&gt;
HTML;
                            echo nl2br($asyncWebFontLoaderSnippet);
                        ?></code>
                    </div>
                </div>
            </details>
        </div>
    </section>

    <section class="wpacu-google-fonts-manual-section" aria-label="<?php esc_attr_e('Manual Google font-file preloads', 'wp-asset-clean-up'); ?>">
        <?php
        $siteWideCandidateThresholdPercent = isset($googleFontsPreloadScanConfig['siteWideCandidateMinCheckCoverage'])
            ? (int) round(((float) $googleFontsPreloadScanConfig['siteWideCandidateMinCheckCoverage']) * 100)
            : 80;

        $fontPreloadScanner = array(
            'provider'             => 'google',
            'root_id'              => 'wpacu-google-font-preload-legacy',
            'dom_prefix'           => 'wpacuGoogleFontPreload',
            'legacy_title'         => __('Manual, site-wide Google font-file preloading', 'wp-asset-clean-up'),
            'legacy_status'        => __('Legacy manual mode', 'wp-asset-clean-up'),
            'legacy_description'   => __('This setting is preserved for existing configurations. A copied <code>fonts.gstatic.com</code> URL is a generated file response, not a stable definition such as “Roboto, 400, italic”.', 'wp-asset-clean-up'),
            'warning_title'        => __('Generated URLs can become unsuitable.', 'wp-asset-clean-up'),
            'warning_text'         => __('Google can return different files for another browser, family variant, character subset, language, variable-font range, or icon selection. Every listed URL is nevertheless preloaded on every applicable page by Asset CleanUp.', 'wp-asset-clean-up'),
            'field_label'          => __('Google font file URLs', 'wp-asset-clean-up'),
            'textarea_id'          => 'wpacu_google_fonts_preload_files',
            'textarea_name'        => $settingsName . '[google_fonts_preload_files]',
            'textarea_value'       => $googleFontsPreloadFilesValue,
            'textarea_placeholder' => 'https://fonts.gstatic.com/s/font-family/version/font-file.woff2',
            'field_help'           => __('Removing an entry stops only Asset CleanUp’s site-wide preload. It does not remove the Google stylesheet or the font itself. Non-Google hosts remain protected as <strong>Review</strong>.', 'wp-asset-clean-up'),
            'scan_title'           => __('Audit whether each URL deserves a site-wide preload', 'wp-asset-clean-up'),
            'scan_description'     => sprintf(
                __('Asset CleanUp suppresses its manual preload, checks representative pages and resolves the Google stylesheet returned to this browser. URLs seen on every checked page and at least <strong>%d%% of checks</strong> are protected as likely site-wide candidates. Coverage findings are advisory, and incomplete or ambiguous Google evidence is never eligible for removal.', 'wp-asset-clean-up'),
                $siteWideCandidateThresholdPercent
            ),
            'start_label'          => __('Audit Google Font Preloads', 'wp-asset-clean-up'),
            'scope_items'          => array(
                __('Current browser', 'wp-asset-clean-up'),
                __('Desktop viewport', 'wp-asset-clean-up'),
                __('Mobile viewport', 'wp-asset-clean-up'),
                sprintf(
                    __('Up to %d pages', 'wp-asset-clean-up'),
                    isset($googleFontsPreloadScanConfig['maxPages']) ? (int) $googleFontsPreloadScanConfig['maxPages'] : 6
                )
            ),
            'extra_summary'        => __('Include important templates or translated pages', 'wp-asset-clean-up'),
            'extra_help'           => sprintf(
                __('Add up to %d public URLs from this WordPress site, one per line. Use the current Settings language when WPML is active.', 'wp-asset-clean-up'),
                isset($googleFontsPreloadScanConfig['maxExtraUrls']) ? (int) $googleFontsPreloadScanConfig['maxExtraUrls'] : 2
            ),
            'example_urls'         => array(
                'https://fonts.gstatic.com/s/roboto/v30/example-file.woff2',
                'https://fonts.gstatic.com/s/materialsymbolsrounded/v1/example-variable-file.woff2'
            ),
            'generated_examples'   => array(
                '<link rel="preload" as="font" href="https://fonts.gstatic.com/s/roboto/v30/example-file.woff2" data-wpacu-preload-google-font="1" crossorigin>'
            ),
            'scanner_config'        => $googleFontsPreloadScanConfig,
            'scanner_disabled'      => ! empty($data['google_fonts_remove']),
            'scanner_disabled_text' => __('“Remove Google Fonts” is enabled. The saved manual list remains visible but inactive until that option is disabled and the settings are saved.', 'wp-asset-clean-up')
        );

        require WPACU_PLUGIN_DIR . '/templates/_common/fonts/preload-scanner.php';
        unset($fontPreloadScanner, $siteWideCandidateThresholdPercent);
        ?>
    </section>
</div>
