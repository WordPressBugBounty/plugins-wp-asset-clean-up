<?php
/*
 * Shared Settings card for legacy, site-wide manual font-file preloads.
 *
 * Expected variable: $fontPreloadScanner
 */

if ( ! isset($fontPreloadScanner) || ! is_array($fontPreloadScanner) ) {
    return;
}

$defaults = array(
    'provider'               => 'local',
    'root_id'                => 'wpacu-font-preload-legacy',
    'dom_prefix'             => 'wpacuFontPreload',
    'legacy_title'           => __('Manual, site-wide font preloading', 'wp-asset-clean-up'),
    'legacy_status'          => __('Legacy manual mode', 'wp-asset-clean-up'),
    'legacy_description'     => '',
    'warning_title'          => __('Use with care.', 'wp-asset-clean-up'),
    'warning_text'           => '',
    'field_label'            => __('Font file URLs', 'wp-asset-clean-up'),
    'textarea_id'            => 'wpacu_font_preload_files',
    'textarea_name'          => '',
    'textarea_value'         => '',
    'textarea_placeholder'   => '',
    'field_help'             => '',
    'scan_eyebrow'           => __('Browser-assisted preload audit', 'wp-asset-clean-up'),
    'scan_title'             => __('Check whether each URL still deserves a site-wide preload', 'wp-asset-clean-up'),
    'scan_description'       => '',
    'verification_note'      => __('Seeing a font file in DevTools on a normal page is not proof of natural usage while that URL remains saved here, because this setting itself forces the download. The audit and its temporary verification links suppress WPACU’s preload first.', 'wp-asset-clean-up'),
    'start_label'            => __('Audit Manual Font Preloads', 'wp-asset-clean-up'),
    'scope_items'            => array(),
    'extra_summary'          => __('Include important pages the automatic selection might miss', 'wp-asset-clean-up'),
    'extra_label'            => __('Optional public page URLs', 'wp-asset-clean-up'),
    'extra_placeholder'      => home_url('/important-landing-page/'),
    'extra_help'             => '',
    'technical_title'        => __('Technical examples and generated HTML', 'wp-asset-clean-up'),
    'example_urls'           => array(),
    'generated_examples'     => array(),
    'scanner_config'         => array(),
    'scanner_disabled'       => false,
    'scanner_disabled_text'  => ''
);

$fontPreloadScanner = array_merge($defaults, $fontPreloadScanner);
$domPrefix          = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $fontPreloadScanner['dom_prefix']);
$rootId             = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $fontPreloadScanner['root_id']);
$config             = is_array($fontPreloadScanner['scanner_config']) ? $fontPreloadScanner['scanner_config'] : array();
$config['provider'] = $fontPreloadScanner['provider'];
$configJson         = wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

if ( ! $configJson ) {
    $configJson = '{}';
}

$maxExtraUrls = isset($config['maxExtraUrls']) ? (int) $config['maxExtraUrls'] : 2;
$legacyPanelOpen = trim((string) $fontPreloadScanner['textarea_value']) !== '';
$forceRiskNoticeFor = isset($_GET['wpacu_force_font_preload_risk_notice'])
    ? sanitize_key(wp_unslash($_GET['wpacu_force_font_preload_risk_notice']))
    : '';
$forceRiskNotice = in_array(
    $forceRiskNoticeFor,
    array((string) $fontPreloadScanner['provider'], 'all'),
    true
);
$requiresRiskAcknowledgement = $forceRiskNotice || (! $legacyPanelOpen && empty($config['riskAcknowledged']));
?>
<details id="<?php echo esc_attr($rootId); ?>"
         class="wpacu-font-preload-legacy"
         data-wpacu-font-preload-scanner="<?php echo esc_attr($fontPreloadScanner['provider']); ?>"
         aria-labelledby="<?php echo esc_attr($domPrefix . 'LegacyTitle'); ?>"
         <?php if ($legacyPanelOpen || $forceRiskNotice) { echo 'open'; } ?>>
    <summary class="wpacu-font-preload-legacy__header">
        <div class="wpacu-font-preload-legacy__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.3 2.9 1.8 17a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 2.9a2 2 0 0 0-3.4 0Z"></path>
                <path d="M12 9v4"></path>
                <path d="M12 17h.01"></path>
            </svg>
        </div>
        <div class="wpacu-font-preload-legacy__heading">
            <div class="wpacu-font-preload-legacy__title-line">
                <h3 id="<?php echo esc_attr($domPrefix . 'LegacyTitle'); ?>"><?php echo esc_html($fontPreloadScanner['legacy_title']); ?></h3>
            </div>
            <?php if ($fontPreloadScanner['legacy_description'] !== '') : ?>
                <p><?php echo wp_kses_post($fontPreloadScanner['legacy_description']); ?></p>
            <?php endif; ?>
        </div>
        <span class="wpacu-font-preload-legacy__meta">
            <span class="wpacu-font-preload-legacy__status-wrap">
                <span class="wpacu-font-preload-legacy__status"><?php echo esc_html($fontPreloadScanner['legacy_status']); ?></span>
                <?php
                ?>
            </span>
            <span class="dashicons dashicons-arrow-right-alt2 wpacu-font-preload-legacy__arrow" aria-hidden="true"></span>
        </span>
    </summary>

    <div class="wpacu-font-preload-legacy__body js-wpacu-font-preload-legacy-body"<?php if ($requiresRiskAcknowledgement) { echo ' hidden'; } ?>>

    <?php if ($fontPreloadScanner['warning_text'] !== '') : ?>
        <div class="wpacu-font-preload-legacy__warning" role="note">
            <strong><?php echo esc_html($fontPreloadScanner['warning_title']); ?></strong>
            <span><?php echo wp_kses_post($fontPreloadScanner['warning_text']); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($fontPreloadScanner['scanner_disabled'] && $fontPreloadScanner['scanner_disabled_text'] !== '') : ?>
        <div class="wpacu-font-preload-legacy__preserved" role="note">
            <span class="dashicons dashicons-hidden" aria-hidden="true"></span>
            <div><?php echo wp_kses_post($fontPreloadScanner['scanner_disabled_text']); ?></div>
        </div>
    <?php endif; ?>

    <div class="wpacu-font-preload-legacy__field">
        <div class="wpacu-font-preload-legacy__field-heading">
            <label for="<?php echo esc_attr($fontPreloadScanner['textarea_id']); ?>"><?php echo esc_html($fontPreloadScanner['field_label']); ?></label>
            <span class="wpacu-font-preload-legacy__count js-wpacu-font-preload-count" aria-live="polite"></span>
        </div>
        <textarea id="<?php echo esc_attr($fontPreloadScanner['textarea_id']); ?>"
                  class="js-wpacu-font-preload-field"
                  rows="5"
                  spellcheck="false"
                  name="<?php echo esc_attr($fontPreloadScanner['textarea_name']); ?>"
                  placeholder="<?php echo esc_attr($fontPreloadScanner['textarea_placeholder']); ?>"><?php echo esc_textarea($fontPreloadScanner['textarea_value']); ?></textarea>
        <?php if ($fontPreloadScanner['field_help'] !== '') : ?>
            <p class="wpacu-font-preload-legacy__field-help"><?php echo wp_kses_post($fontPreloadScanner['field_help']); ?></p>
        <?php endif; ?>
    </div>

    <section class="wpacu-font-preload-scan" aria-labelledby="<?php echo esc_attr($domPrefix . 'ScanTitle'); ?>">
        <div class="wpacu-font-preload-scan__top">
            <?php if ( ! empty($fontPreloadScanner['scope_items']) ) : ?>
                <div class="wpacu-font-preload-scan__scope" aria-label="<?php esc_attr_e('Audit scope', 'wp-asset-clean-up'); ?>">
                    <?php foreach ($fontPreloadScanner['scope_items'] as $scopeItem) : ?>
                        <span><?php echo esc_html($scopeItem); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="wpacu-font-preload-scan__intro">
                <span class="wpacu-font-preload-scan__eyebrow"><?php echo esc_html($fontPreloadScanner['scan_eyebrow']); ?></span>
                <h4 id="<?php echo esc_attr($domPrefix . 'ScanTitle'); ?>"><?php echo esc_html($fontPreloadScanner['scan_title']); ?></h4>
                <?php if ($fontPreloadScanner['scan_description'] !== '') : ?>
                    <p><?php echo wp_kses_post($fontPreloadScanner['scan_description']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="wpacu-font-preload-scan__actions">
            <button type="button"
                    class="button button-secondary wpacu-font-preload-scan__start js-wpacu-font-preload-start"
                    <?php disabled((bool) $fontPreloadScanner['scanner_disabled']); ?>>
                <span class="wpacu-font-preload-scan__start-icon dashicons dashicons-search" aria-hidden="true"></span>
                <span class="wpacu-font-preload-scan__start-label"><?php echo esc_html($fontPreloadScanner['start_label']); ?></span>
            </button>
            <button type="button" class="button wpacu-font-preload-scan__cancel js-wpacu-font-preload-cancel" hidden>
                <?php esc_html_e('Cancel audit', 'wp-asset-clean-up'); ?>
            </button>
            <button type="button" class="button wpacu-font-preload-scan__retry js-wpacu-font-preload-retry-failed" hidden>
                <?php esc_html_e('Retry failed checks', 'wp-asset-clean-up'); ?>
            </button>
            <span class="wpacu-font-preload-scan__save-note"><?php esc_html_e('No settings are saved by the audit.', 'wp-asset-clean-up'); ?></span>
        </div>

        <div class="wpacu-font-preload-scan__options">
            <details class="wpacu-font-preload-scan__extra-pages">
                <summary><?php echo esc_html($fontPreloadScanner['extra_summary']); ?></summary>
                <div>
                    <label for="<?php echo esc_attr($domPrefix . 'ExtraUrls'); ?>"><?php echo esc_html($fontPreloadScanner['extra_label']); ?></label>
                    <textarea id="<?php echo esc_attr($domPrefix . 'ExtraUrls'); ?>"
                              class="js-wpacu-font-preload-extra-urls"
                              rows="2"
                              spellcheck="false"
                              placeholder="<?php echo esc_attr($fontPreloadScanner['extra_placeholder']); ?>"></textarea>
                    <p><?php
                        if ($fontPreloadScanner['extra_help'] !== '') {
                            echo wp_kses_post($fontPreloadScanner['extra_help']);
                        } else {
                            printf(
                                esc_html__('Add up to %d URLs from this WordPress site, one per line. These pages are prioritised in the audit.', 'wp-asset-clean-up'),
                                $maxExtraUrls
                            );
                        }
                    ?></p>
                </div>
            </details>

            <?php if ($fontPreloadScanner['verification_note'] !== '') : ?>
                <details class="wpacu-font-preload-scan__method">
                    <summary><?php esc_html_e('How the audit avoids false positives', 'wp-asset-clean-up'); ?></summary>
                    <p><?php echo wp_kses_post($fontPreloadScanner['verification_note']); ?></p>
                </details>
            <?php endif; ?>
        </div>

        <div class="wpacu-font-preload-scan__feedback js-wpacu-font-preload-feedback" role="status" aria-live="polite" hidden></div>

        <div class="wpacu-font-preload-scan__progress js-wpacu-font-preload-progress" hidden>
            <div class="wpacu-font-preload-scan__progress-heading">
                <span class="js-wpacu-font-preload-progress-text"></span>
                <strong class="js-wpacu-font-preload-progress-percent">0%</strong>
            </div>
            <div class="wpacu-font-preload-scan__progress-track" aria-hidden="true">
                <span class="js-wpacu-font-preload-progress-bar"></span>
            </div>
            <details class="wpacu-font-preload-scan__checks js-wpacu-font-preload-checks-details" open>
                <summary>
                    <span class="js-wpacu-font-preload-checks-summary"><?php esc_html_e('Page and viewport checks', 'wp-asset-clean-up'); ?></span>
                    <span><?php esc_html_e('View details', 'wp-asset-clean-up'); ?></span>
                </summary>
                <div class="wpacu-font-preload-scan__pages js-wpacu-font-preload-pages"></div>
            </details>
        </div>

        <div class="wpacu-font-preload-results js-wpacu-font-preload-results" hidden>
            <header class="wpacu-font-preload-results__header">
                <div>
                    <span class="wpacu-font-preload-results__eyebrow"><?php esc_html_e('Audit results', 'wp-asset-clean-up'); ?></span>
                    <h5><?php esc_html_e('Site-wide preload recommendation', 'wp-asset-clean-up'); ?></h5>
                </div>
                <div class="wpacu-font-preload-results__summary js-wpacu-font-preload-summary"></div>
            </header>

            <div class="wpacu-font-preload-results__notice js-wpacu-font-preload-global-notice" hidden></div>
            <div class="wpacu-font-preload-results__list js-wpacu-font-preload-results-list"></div>

            <div class="wpacu-font-preload-results__footer js-wpacu-font-preload-results-footer" hidden>
                <button type="button" class="button button-primary js-wpacu-font-preload-remove-selected" disabled>
                    <?php esc_html_e('Remove selected from the field', 'wp-asset-clean-up'); ?>
                </button>
                <span class="js-wpacu-font-preload-selected-count"></span>
                <small><?php esc_html_e('The field changes only after you select an eligible entry. Saving remains a separate action.', 'wp-asset-clean-up'); ?></small>
            </div>
        </div>

        <div class="wpacu-font-preload-scan__undo js-wpacu-font-preload-undo-notice" hidden>
            <span class="dashicons dashicons-saved" aria-hidden="true"></span>
            <span class="js-wpacu-font-preload-undo-text"></span>
            <button type="button" class="button-link js-wpacu-font-preload-undo"></button>
        </div>

        <div class="wpacu-font-preload-scan__frames js-wpacu-font-preload-frames" aria-hidden="true"></div>
    </section>

    <?php if ( ! empty($fontPreloadScanner['example_urls']) || ! empty($fontPreloadScanner['generated_examples']) ) : ?>
        <details class="wpacu-font-preload-legacy__technical">
            <summary><?php echo esc_html($fontPreloadScanner['technical_title']); ?></summary>
            <div class="wpacu-font-preload-legacy__technical-grid">
                <?php if ( ! empty($fontPreloadScanner['example_urls']) ) : ?>
                    <div>
                        <strong><?php esc_html_e('Example URLs', 'wp-asset-clean-up'); ?></strong>
                        <?php foreach ($fontPreloadScanner['example_urls'] as $exampleUrl) : ?>
                            <code><?php echo esc_html($exampleUrl); ?></code>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty($fontPreloadScanner['generated_examples']) ) : ?>
                    <div>
                        <strong><?php esc_html_e('Generated in the document head', 'wp-asset-clean-up'); ?></strong>
                        <?php foreach ($fontPreloadScanner['generated_examples'] as $generatedExample) : ?>
                            <code><?php echo esc_html($generatedExample); ?></code>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </details>
    <?php endif; ?>

        <script type="application/json" class="js-wpacu-font-preload-config"><?php echo $configJson; ?></script>
    </div>

    <?php if ($requiresRiskAcknowledgement) : ?>
        <div class="wpacu-font-risk-modal js-wpacu-font-risk-modal" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($domPrefix . 'RiskTitle'); ?>" hidden>
            <div class="wpacu-font-risk-modal__backdrop"></div>
            <div class="wpacu-font-risk-modal__dialog" role="document">
                <div class="wpacu-font-risk-modal__icon" aria-hidden="true"><span class="dashicons dashicons-warning"></span></div>
                <div class="wpacu-font-risk-modal__content">
                    <span class="wpacu-font-risk-modal__eyebrow"><?php esc_html_e('Legacy manual setting', 'wp-asset-clean-up'); ?></span>
                    <h3 id="<?php echo esc_attr($domPrefix . 'RiskTitle'); ?>"><?php esc_html_e('Understand the risks before continuing', 'wp-asset-clean-up'); ?></h3>
                    <?php if ($fontPreloadScanner['provider'] === 'google') : ?>
                        <p class="wpacu-font-risk-modal__preserved"><?php esc_html_e('This setting is preserved for experienced users maintaining existing manual Google font-file preloads. It is not recommended for new setups.', 'wp-asset-clean-up'); ?></p>
                        <p><?php esc_html_e('A fonts.gstatic.com URL identifies a generated Google Fonts response, not a stable font family or variant. It can change when the stylesheet URL, weights, browser, language, subset or character coverage changes.', 'wp-asset-clean-up'); ?></p>
                        <ul>
                            <li><?php esc_html_e('A stale URL can download an unused font on every page.', 'wp-asset-clean-up'); ?></li>
                            <li><?php esc_html_e('The old preloaded file may load alongside the font currently requested by Google Fonts.', 'wp-asset-clean-up'); ?></li>
                            <li><?php esc_html_e('Prefer automatic optimization or a stable, locally hosted WOFF2 file.', 'wp-asset-clean-up'); ?></li>
                        </ul>
                    <?php else : ?>
                        <p class="wpacu-font-risk-modal__preserved"><?php esc_html_e('This setting is preserved for experienced users maintaining existing site-wide font preloads. New setups should prefer automatic optimization or stable, locally hosted WOFF2 files.', 'wp-asset-clean-up'); ?></p>
                        <p><?php esc_html_e('A manually entered font URL is preloaded site-wide and is not synchronized when a theme, plugin, generated stylesheet, CDN path or font version changes.', 'wp-asset-clean-up'); ?></p>
                        <ul>
                            <li><?php esc_html_e('Only preload a stable WOFF2 file needed in the first viewport.', 'wp-asset-clean-up'); ?></li>
                            <li><?php esc_html_e('An outdated entry can waste bandwidth on every applicable page.', 'wp-asset-clean-up'); ?></li>
                            <li><?php esc_html_e('Review the setting again whenever its source CSS or delivery path changes.', 'wp-asset-clean-up'); ?></li>
                        </ul>
                    <?php endif; ?>
                    <div class="wpacu-font-risk-modal__error js-wpacu-font-risk-error" role="alert" hidden></div>
                </div>
                <div class="wpacu-font-risk-modal__actions">
                    <button type="button" class="button button-primary js-wpacu-font-risk-cancel"><?php esc_html_e('Keep manual preloading disabled', 'wp-asset-clean-up'); ?></button>
                    <button type="button" class="button wpacu-font-risk-modal__accept js-wpacu-font-risk-accept"><?php esc_html_e('I understand the risks — show manual field', 'wp-asset-clean-up'); ?></button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</details>
