<?php

use WpAssetCleanUp\Misc;
use WpAssetCleanUp\OptimiseAssets\FontsGoogle;

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

<?php
$googleFontsLocalConfig = isset($data['google_fonts_local_config']) && is_array($data['google_fonts_local_config'])
    ? $data['google_fonts_local_config']
    : array();
$googleFontsLocalSummary = isset($googleFontsLocalConfig['summary']) && is_array($googleFontsLocalConfig['summary'])
    ? $googleFontsLocalConfig['summary']
    : array();
$googleFontsLocalEntries = isset($googleFontsLocalConfig['entries']) && is_array($googleFontsLocalConfig['entries'])
    ? $googleFontsLocalConfig['entries']
    : array();
$googleFontsLocalEdition = isset($googleFontsLocalConfig['edition']) ? (string) $googleFontsLocalConfig['edition'] : 'lite';
$googleFontsLocalAutomaticProcessingEnabled = $googleFontsLocalEdition !== 'pro'
    || ! isset($googleFontsLocalConfig['automation']['enabled'])
    || ! empty($googleFontsLocalConfig['automation']['enabled']);
$googleFontsLocalConfig['autoProcessPending'] = ! empty($data['google_fonts_local'])
    && empty($data['google_fonts_remove'])
    && $googleFontsLocalAutomaticProcessingEnabled
    && (! empty($googleFontsLocalConfig['keys']['pending'])
        || ! empty($googleFontsLocalConfig['keys']['outdated']));
$googleFontsPreconnectWarning = FontsGoogle::shouldWarnPreconnectForLocalHosting($data);
$googleFontsLocalStatusLabels = array(
    'pending'    => __('Pending', 'wp-asset-clean-up'),
    'processing' => __('Processing', 'wp-asset-clean-up'),
    'ready'      => __('Ready', 'wp-asset-clean-up'),
    'error'      => __('Needs attention', 'wp-asset-clean-up'),
);
$googleFontsLocalInventoryGroups = array(
    'pending' => array(
        'title'     => __('Pending configurations', 'wp-asset-clean-up'),
        'statuses'  => array('pending', 'processing'),
        'operation' => 'process-pending',
        'action'    => __('Process pending', 'wp-asset-clean-up'),
        'icon'      => 'dashicons-controls-play',
    ),
    'ready' => array(
        'title'     => __('Ready configurations', 'wp-asset-clean-up'),
        'statuses'  => array('ready'),
        'operation' => 'refresh-ready',
        'action'    => __('Refresh ready copies', 'wp-asset-clean-up'),
        'icon'      => 'dashicons-image-rotate',
    ),
    'error' => array(
        'title'     => __('Failed configurations', 'wp-asset-clean-up'),
        'statuses'  => array('error'),
        'operation' => 'retry-failed',
        'action'    => __('Retry failed', 'wp-asset-clean-up'),
        'icon'      => 'dashicons-update',
    ),
);
$googleFontsLocalJsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
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

<nav class="wpacu-google-fonts-quick-nav<?php echo $data['google_fonts_remove'] ? ' is-disabled-by-removal' : ''; ?>" aria-label="<?php esc_attr_e('Google Fonts optimization sections', 'wp-asset-clean-up'); ?>">
    <a href="#wpacu-google-fonts-local-card"><span class="dashicons dashicons-cloud-saved" aria-hidden="true"></span><?php esc_html_e('Local hosting', 'wp-asset-clean-up'); ?></a>
    <a href="#wpacu-google-fonts-delivery"><span class="dashicons dashicons-editor-textcolor" aria-hidden="true"></span><?php esc_html_e('Font delivery', 'wp-asset-clean-up'); ?></a>
    <a href="#wpacu-google-fonts-combine"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span><?php esc_html_e('Combine requests', 'wp-asset-clean-up'); ?></a>
    <a href="#wpacu-google-fonts-manual-preload"><span class="dashicons dashicons-download" aria-hidden="true"></span><?php esc_html_e('Manual preload', 'wp-asset-clean-up'); ?><span class="wpacu-google-fonts-quick-nav__legacy"><?php esc_html_e('Legacy', 'wp-asset-clean-up'); ?></span></a>
</nav>

<section id="wpacu-google-fonts-local-card"
         class="wpacu-google-fonts-local-card<?php echo ! empty($data['google_fonts_remove']) ? ' is-disabled-by-removal' : ''; ?><?php echo empty($data['google_fonts_local']) ? ' is-local-hosting-disabled' : ''; ?>"
         data-wpacu-google-fonts-local-card
         data-wpacu-google-fonts-local-saved-enabled="<?php echo ! empty($data['google_fonts_local']) ? '1' : '0'; ?>"
         aria-labelledby="wpacuGoogleFontsLocalTitle">
    <header class="wpacu-google-fonts-local-card__header">
        <span class="wpacu-google-fonts-local-card__icon" aria-hidden="true">
            <span class="dashicons dashicons-cloud-saved"></span>
        </span>
        <div class="wpacu-google-fonts-local-card__heading">
            <span class="wpacu-google-fonts-local-card__eyebrow"><?php esc_html_e('Privacy & delivery control', 'wp-asset-clean-up'); ?></span>
            <h3 id="wpacuGoogleFontsLocalTitle"><?php esc_html_e('Host Google Fonts locally', 'wp-asset-clean-up'); ?></h3>
            <p><?php esc_html_e('Mirror each detected Google Fonts stylesheet and every font file it declares into the WP Asset CleanUp cache. A remote URL is replaced only after its complete local copy has been validated and published.', 'wp-asset-clean-up'); ?></p>
        </div>
        <div class="wpacu-google-fonts-local-card__master">
            <input type="hidden" name="<?php echo esc_attr($settingsName); ?>[google_fonts_local]" value="0" />
            <label class="wpacu_switch" for="wpacu_google_fonts_local">
                <input id="wpacu_google_fonts_local"
                       type="checkbox"
                    <?php checked((int) $data['google_fonts_local'], 1); ?>
                       name="<?php echo esc_attr($settingsName); ?>[google_fonts_local]"
                       value="1" />
                <span class="wpacu_slider wpacu_round" aria-hidden="true"></span>
            </label>
            <label for="wpacu_google_fonts_local">
                <strong><?php esc_html_e('Serve ready copies from this site', 'wp-asset-clean-up'); ?></strong>
                <small><?php esc_html_e('Default: disabled', 'wp-asset-clean-up'); ?></small>
            </label>
        </div>
    </header>

    <?php if (empty($data['google_fonts_remove'])) : ?>
        <div class="wpacu-google-fonts-local-card__notice" role="note">
            <span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
            <p><?php esc_html_e('The first request for a newly discovered configuration still uses Google. This prevents missing text or broken icons while the complete local transaction is being prepared.', 'wp-asset-clean-up'); ?></p>
        </div>
    <?php endif; ?>

    <div class="wpacu-google-fonts-local-card__body">
        <div class="wpacu-google-fonts-local-summary" aria-label="<?php esc_attr_e('Local Google Fonts cache summary', 'wp-asset-clean-up'); ?>">
            <?php
            $summaryCards = array(
                'ready' => array(__('Ready', 'wp-asset-clean-up'), 'dashicons-yes-alt'),
                'pending' => array(__('Pending', 'wp-asset-clean-up'), 'dashicons-clock'),
                'error' => array(__('Needs attention', 'wp-asset-clean-up'), 'dashicons-warning'),
                'font_count' => array(__('Local font files', 'wp-asset-clean-up'), 'dashicons-media-default'),
            );
            foreach ($summaryCards as $summaryKey => $summaryCard) :
                $summaryValue = isset($googleFontsLocalSummary[$summaryKey]) ? (int) $googleFontsLocalSummary[$summaryKey] : 0;
                ?>
                <?php if (isset($googleFontsLocalInventoryGroups[$summaryKey])) : ?>
                <a class="wpacu-google-fonts-local-summary__item is-<?php echo esc_attr($summaryKey); ?>"
                   data-wpacu-google-fonts-local-summary-target="#wpacu-google-fonts-local-group-<?php echo esc_attr($summaryKey); ?>"<?php if ($summaryValue > 0) : ?>
                   href="#wpacu-google-fonts-local-group-<?php echo esc_attr($summaryKey); ?>"
                   data-wpacu-google-fonts-local-summary-link<?php endif; ?>>
                <?php else : ?>
                <div class="wpacu-google-fonts-local-summary__item is-<?php echo esc_attr($summaryKey); ?>">
                <?php endif; ?>
                    <span class="dashicons <?php echo esc_attr($summaryCard[1]); ?>" aria-hidden="true"></span>
                    <span>
                        <strong data-wpacu-google-fonts-local-count="<?php echo esc_attr($summaryKey); ?>"><?php echo esc_html(number_format_i18n($summaryValue)); ?></strong>
                        <small><?php echo esc_html($summaryCard[0]); ?></small>
                    </span>
                <?php echo isset($googleFontsLocalInventoryGroups[$summaryKey]) ? '</a>' : '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endforeach; ?>
            <div class="wpacu-google-fonts-local-summary__item is-bytes">
                <span class="dashicons dashicons-database" aria-hidden="true"></span>
                <span>
                    <strong data-wpacu-google-fonts-local-count="total_bytes"><?php echo esc_html(size_format(isset($googleFontsLocalSummary['total_bytes']) ? (int) $googleFontsLocalSummary['total_bytes'] : 0)); ?></strong>
                    <small><?php esc_html_e('Local font data', 'wp-asset-clean-up'); ?></small>
                </span>
            </div>
        </div>

        <div class="wpacu-google-fonts-local-actions" aria-label="<?php esc_attr_e('Local Google Fonts cache actions', 'wp-asset-clean-up'); ?>">
            <span class="wpacu-google-fonts-local-action" data-wpacu-google-fonts-local-action="reset">
                <button type="button" class="button" data-wpacu-google-fonts-local-operation="reset" <?php disabled(empty($googleFontsLocalSummary['total'])); ?>>
                    <span class="dashicons dashicons-backup" aria-hidden="true"></span>
                    <?php esc_html_e('Reset local copies safely', 'wp-asset-clean-up'); ?>
                </button>
                <a class="wpacu-google-fonts-local-action__help" data-wpacu-modal-target="wpacu-google-fonts-local-reset-info-target" href="#wpacu-google-fonts-local-reset-info" aria-label="<?php esc_attr_e('What does Reset local copies safely do?', 'wp-asset-clean-up'); ?>"><span class="dashicons dashicons-editor-help" aria-hidden="true"></span></a>
            </span>
            <span class="wpacu-google-fonts-local-action" data-wpacu-google-fonts-local-action="cleanup">
                <button type="button" class="button" data-wpacu-google-fonts-local-operation="cleanup">
                    <span class="dashicons dashicons-filter" aria-hidden="true"></span>
                    <?php esc_html_e('Delete expired unused files', 'wp-asset-clean-up'); ?>
                </button>
                <a class="wpacu-google-fonts-local-action__help" data-wpacu-modal-target="wpacu-google-fonts-local-cleanup-info-target" href="#wpacu-google-fonts-local-cleanup-info" aria-label="<?php esc_attr_e('What does Delete expired unused files do?', 'wp-asset-clean-up'); ?>"><span class="dashicons dashicons-editor-help" aria-hidden="true"></span></a>
            </span>
            <span class="wpacu-google-fonts-local-action" data-wpacu-google-fonts-local-action="clear">
                <button type="button" class="button button-link-delete" data-wpacu-google-fonts-local-operation="clear">
                    <?php esc_html_e('Delete files immediately', 'wp-asset-clean-up'); ?>
                </button>
                <a class="wpacu-google-fonts-local-action__help is-danger" data-wpacu-modal-target="wpacu-google-fonts-local-clear-info-target" href="#wpacu-google-fonts-local-clear-info" aria-label="<?php esc_attr_e('What does Delete files immediately do?', 'wp-asset-clean-up'); ?>"><span class="dashicons dashicons-editor-help" aria-hidden="true"></span></a>
            </span>
        </div>

        <div class="wpacu-google-fonts-local-feature-preview<?php echo empty($data['google_fonts_local']) ? ' is-collapsed' : ''; ?>"
             data-wpacu-google-fonts-local-feature-preview>
        <section class="wpacu-google-fonts-key-pages-scan<?php echo empty($googleFontsLocalConfig['enabled']) ? ' is-disabled' : ''; ?>"
                 data-wpacu-google-fonts-key-pages-scan
                 aria-disabled="<?php echo empty($googleFontsLocalConfig['enabled']) ? 'true' : 'false'; ?>"
                 aria-labelledby="wpacuGoogleFontsKeyPagesScanTitle">
            <div class="wpacu-google-fonts-key-pages-scan__disabled-notice" data-wpacu-google-fonts-scan-disabled-notice<?php echo ! empty($googleFontsLocalConfig['enabled']) ? ' hidden' : ''; ?>>
                <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                <span><?php esc_html_e('Enable and save local hosting before scanning key pages.', 'wp-asset-clean-up'); ?></span>
            </div>
            <div class="wpacu-google-fonts-key-pages-scan__intro">
                <span class="dashicons dashicons-search" aria-hidden="true"></span>
                <div>
                    <h4 id="wpacuGoogleFontsKeyPagesScanTitle"><?php esc_html_e('Discover fonts across key pages', 'wp-asset-clean-up'); ?></h4>
                    <p><?php esc_html_e('Scan a bounded set of representative public pages to update the Google Fonts inventory. Links are not crawled recursively.', 'wp-asset-clean-up'); ?></p>
                </div>
                <button type="button" class="button" data-wpacu-google-fonts-scan-start<?php disabled(empty($googleFontsLocalConfig['enabled'])); ?>>
                    <?php esc_html_e('Scan key pages', 'wp-asset-clean-up'); ?>
                </button>
                <button type="button" class="button button-link-delete" data-wpacu-google-fonts-scan-cancel hidden>
                    <?php esc_html_e('Cancel scan', 'wp-asset-clean-up'); ?>
                </button>
            </div>
            <details>
                <summary><?php esc_html_e('Add important URLs', 'wp-asset-clean-up'); ?></summary>
                <label>
                    <span><?php esc_html_e('Optional public URLs from this site, one per line', 'wp-asset-clean-up'); ?></span>
                    <textarea rows="3" data-wpacu-google-fonts-scan-extra-urls placeholder="<?php echo esc_attr(home_url('/important-page/')); ?>"<?php disabled(empty($googleFontsLocalConfig['enabled'])); ?>></textarea>
                </label>
                <small><?php esc_html_e('Automatic and custom pages are deduplicated. At most 30 same-site URLs are scanned.', 'wp-asset-clean-up'); ?></small>
            </details>
        </section>


        <div class="wpacu-google-fonts-local-progress" data-wpacu-google-fonts-local-progress hidden>
            <div class="wpacu-google-fonts-local-progress__track" aria-hidden="true"><span data-wpacu-google-fonts-local-progress-bar></span></div>
            <p data-wpacu-google-fonts-local-status role="status" aria-live="polite"></p>
        </div>

        <?php if ($googleFontsLocalEdition !== 'pro') : ?>
            <?php
            $googleFontsLocalComparisonUrl = 'https://www.assetcleanup.com/docs/how-google-fonts-optimization-works/#wpacu-google-fonts-comparison';
            ?>
            <section class="wpacu-google-fonts-local-lite-pro-preview" aria-labelledby="wpacuGoogleFontsLocalContinuousMonitoringTitle">
                <div class="wpacu-google-fonts-local-lite-pro-preview__heading">
                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                    <div>
                        <h4 id="wpacuGoogleFontsLocalContinuousMonitoringTitle"><?php esc_html_e('Continuous font monitoring', 'wp-asset-clean-up'); ?></h4>
                        <p><?php esc_html_e('Local hosting and automatic processing are included in Lite. Pro adds continuous browser discovery and maintenance controls for configurations that are harder to detect from PHP.', 'wp-asset-clean-up'); ?></p>
                    </div>
                </div>

                <div class="wpacu-google-fonts-local-lite-pro-preview__included">
                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                    <div>
                        <strong><?php esc_html_e('Automatically process newly detected Google Fonts', 'wp-asset-clean-up'); ?></strong>
                        <small><?php esc_html_e('Included in Lite — newly detected configurations are queued in the background.', 'wp-asset-clean-up'); ?></small>
                    </div>
                    <span class="wpacu-google-fonts-local-lite-pro-preview__badge is-lite"><?php esc_html_e('Lite', 'wp-asset-clean-up'); ?></span>
                </div>

                <div class="wpacu-google-fonts-local-lite-pro-preview__pro-list" aria-label="<?php esc_attr_e('Additional Google Fonts Local features available in Pro', 'wp-asset-clean-up'); ?>">
                    <div class="wpacu-google-fonts-local-lite-pro-preview__locked">
                        <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                        <div>
                            <strong><?php esc_html_e('Detect fonts injected after page load by JavaScript', 'wp-asset-clean-up'); ?></strong>
                            <small><?php esc_html_e('Browser-side discovery catches Google Fonts that appear after the initial PHP response.', 'wp-asset-clean-up'); ?></small>
                        </div>
                        <span class="wpacu-google-fonts-local-lite-pro-preview__badge is-pro"><?php esc_html_e('Pro', 'wp-asset-clean-up'); ?></span>
                    </div>
                    <div class="wpacu-google-fonts-local-lite-pro-preview__locked">
                        <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                        <div>
                            <strong><?php esc_html_e('Refresh local copies periodically', 'wp-asset-clean-up'); ?></strong>
                            <small><?php esc_html_e('Keep ready copies fresh automatically without manually rebuilding the local cache.', 'wp-asset-clean-up'); ?></small>
                        </div>
                        <span class="wpacu-google-fonts-local-lite-pro-preview__badge is-pro"><?php esc_html_e('Pro', 'wp-asset-clean-up'); ?></span>
                    </div>
                    <div class="wpacu-google-fonts-local-lite-pro-preview__locked">
                        <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                        <div>
                            <strong><?php esc_html_e('Local-hosting exclusions', 'wp-asset-clean-up'); ?></strong>
                            <small><?php esc_html_e('Keep selected configurations on their original Google Fonts URLs and exclude them from automatic processing.', 'wp-asset-clean-up'); ?></small>
                        </div>
                        <span class="wpacu-google-fonts-local-lite-pro-preview__badge is-pro"><?php esc_html_e('Pro', 'wp-asset-clean-up'); ?></span>
                    </div>
                </div>

                <div class="wpacu-google-fonts-local-lite-pro-preview__footer">
                    <span><?php esc_html_e('Need continuous discovery and maintenance?', 'wp-asset-clean-up'); ?></span>
                    <a class="button button-secondary" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url($googleFontsLocalComparisonUrl); ?>">
                        <?php esc_html_e('Compare Lite and Pro features', 'wp-asset-clean-up'); ?>
                    </a>
                </div>
            </section>
        <?php endif; ?>

        <div class="wpacu-google-fonts-local-inventory-shell">
            <div class="wpacu-google-fonts-local-inventory-loader" aria-hidden="true"></div>

            <details class="wpacu-google-fonts-local-inventory"<?php echo ! empty($googleFontsLocalEntries) ? ' open' : ''; ?>>
            <summary data-wpacu-google-fonts-local-inventory-count>
                <?php
                printf(
                    esc_html(_n('%s discovered configuration', '%s discovered configurations', count($googleFontsLocalEntries), 'wp-asset-clean-up')),
                    esc_html(number_format_i18n(count($googleFontsLocalEntries)))
                );
                ?>
            </summary>

                <div class="wpacu-google-fonts-local-empty" data-wpacu-google-fonts-local-empty<?php echo ! empty($googleFontsLocalEntries) ? ' hidden' : ''; ?>>
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <p><?php esc_html_e('No Google Fonts stylesheet has been recorded yet. Scan key pages or visit representative public pages to build the inventory.', 'wp-asset-clean-up'); ?></p>
                </div>
                <div class="wpacu-google-fonts-local-table-wrap" data-wpacu-google-fonts-local-table-wrap<?php echo empty($googleFontsLocalEntries) ? ' hidden' : ''; ?>>
                <?php foreach ($googleFontsLocalInventoryGroups as $googleFontsLocalGroupKey => $googleFontsLocalGroup) :
                    $googleFontsLocalGroupEntries = array_filter($googleFontsLocalEntries, static function ($googleFontsLocalEntry) use ($googleFontsLocalGroup) {
                        $entryStatus = isset($googleFontsLocalEntry['status']) ? $googleFontsLocalEntry['status'] : 'pending';
                        return in_array($entryStatus, $googleFontsLocalGroup['statuses'], true);
                    });
                    ?>
                    <section id="wpacu-google-fonts-local-group-<?php echo esc_attr($googleFontsLocalGroupKey); ?>" class="wpacu-google-fonts-local-inventory-group is-<?php echo esc_attr($googleFontsLocalGroupKey); ?>" data-wpacu-google-fonts-local-group="<?php echo esc_attr($googleFontsLocalGroupKey); ?>"<?php echo $googleFontsLocalGroupKey === 'pending' && empty($googleFontsLocalGroupEntries) ? ' hidden' : ''; ?>>
                        <header class="wpacu-google-fonts-local-inventory-group__header">
                            <span class="wpacu-google-fonts-local-inventory-group__loader" aria-hidden="true"></span>
                            <h4><?php echo esc_html($googleFontsLocalGroup['title']); ?> <span data-wpacu-google-fonts-local-group-count="<?php echo esc_attr($googleFontsLocalGroupKey); ?>"><?php echo esc_html(number_format_i18n(count($googleFontsLocalGroupEntries))); ?></span></h4>
                            <?php if (! empty($googleFontsLocalGroup['operation'])) : ?>
                                <button type="button"
                                        class="button<?php echo $googleFontsLocalGroupKey === 'pending' ? ' button-primary' : ''; ?>"
                                        data-wpacu-google-fonts-local-operation="<?php echo esc_attr($googleFontsLocalGroup['operation']); ?>"
                                    <?php disabled(empty($googleFontsLocalConfig['keys'][$googleFontsLocalGroupKey])); ?>>
                                    <span class="dashicons <?php echo esc_attr($googleFontsLocalGroup['icon']); ?>" aria-hidden="true"></span>
                                    <?php echo esc_html($googleFontsLocalGroup['action']); ?>
                                </button>
                                <?php if ($googleFontsLocalGroupKey === 'pending') : ?>
                                    <span class="wpacu-google-fonts-pending-scan-note" data-wpacu-google-fonts-pending-scan-note>
                                        <span>
                                            <?php if (! empty($googleFontsLocalConfig['enabled'])) : ?>
                                                <?php esc_html_e('Will be processed after the scan', 'wp-asset-clean-up'); ?>
                                            <?php else : ?>
                                                <?php esc_html_e('Review pending configurations after the scan', 'wp-asset-clean-up'); ?>
                                            <?php endif; ?>
                                        </span>
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </header>
                        <p class="wpacu-google-fonts-local-group-empty" data-wpacu-google-fonts-local-group-empty="<?php echo esc_attr($googleFontsLocalGroupKey); ?>"<?php echo ! empty($googleFontsLocalGroupEntries) ? ' hidden' : ''; ?>>
                            <?php printf(esc_html__('No %s configurations.', 'wp-asset-clean-up'), esc_html($googleFontsLocalGroupKey === 'error' ? __('failed', 'wp-asset-clean-up') : $googleFontsLocalGroupKey)); ?>
                        </p>
                    <table class="widefat striped wpacu-google-fonts-local-table" data-wpacu-google-fonts-local-group-table="<?php echo esc_attr($googleFontsLocalGroupKey); ?>"<?php echo empty($googleFontsLocalGroupEntries) ? ' hidden' : ''; ?>>
                        <thead>
                        <tr>
                            <th><?php esc_html_e('Status', 'wp-asset-clean-up'); ?></th>
                            <th><?php esc_html_e('Google Fonts configuration', 'wp-asset-clean-up'); ?></th>
                            <th><?php esc_html_e('Seen on', 'wp-asset-clean-up'); ?></th>
                            <th><?php esc_html_e('Local copy', 'wp-asset-clean-up'); ?></th>
                        </tr>
                        </thead>
                        <tbody data-wpacu-google-fonts-local-group-body="<?php echo esc_attr($googleFontsLocalGroupKey); ?>">
                        <?php foreach ($googleFontsLocalGroupEntries as $googleFontsLocalEntry) :
                            $entryStatus = isset($googleFontsLocalEntry['status']) ? $googleFontsLocalEntry['status'] : 'pending';
                            $entryStatusLabel = isset($googleFontsLocalStatusLabels[$entryStatus]) ? $googleFontsLocalStatusLabels[$entryStatus] : $googleFontsLocalStatusLabels['pending'];
                            ?>
                            <tr class="is-status-<?php echo esc_attr($entryStatus); ?>" data-wpacu-google-fonts-local-entry="<?php echo esc_attr($googleFontsLocalEntry['key']); ?>">
                                <td data-colname="<?php esc_attr_e('Status', 'wp-asset-clean-up'); ?>">
                                    <span class="wpacu-google-fonts-local-status is-<?php echo esc_attr($entryStatus); ?>">
                                        <?php echo esc_html($entryStatusLabel); ?>
                                    </span>
                                    <?php if ($entryStatus !== 'ready' && ! empty($googleFontsLocalEntry['attempts'])) : ?>
                                        <small class="wpacu-google-fonts-local-attempts">
                                            <?php printf(esc_html__('Attempts: %s', 'wp-asset-clean-up'), esc_html(number_format_i18n((int) $googleFontsLocalEntry['attempts']))); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td data-colname="<?php esc_attr_e('Configuration', 'wp-asset-clean-up'); ?>">
                                    <code class="wpacu-google-fonts-local-url" title="<?php echo esc_attr($googleFontsLocalEntry['url']); ?>"><?php echo esc_html($googleFontsLocalEntry['url']); ?></code>
                                    <?php if (! empty($googleFontsLocalEntry['lastError'])) : ?>
                                        <p class="wpacu-google-fonts-local-error"><span class="dashicons dashicons-warning" aria-hidden="true"></span><?php echo esc_html($googleFontsLocalEntry['lastError']); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td data-colname="<?php esc_attr_e('Seen on', 'wp-asset-clean-up'); ?>">
                                    <?php if (! empty($googleFontsLocalEntry['paths'])) : ?>
                                        <ul class="wpacu-google-fonts-local-paths">
                                            <?php foreach (array_slice($googleFontsLocalEntry['paths'], 0, 1) as $googleFontsLocalPath) : ?>
                                                <li><code><?php echo esc_html($googleFontsLocalPath); ?></code></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php $googleFontsLocalAdditionalPaths = array_slice($googleFontsLocalEntry['paths'], 1); ?>
                                        <?php if ($googleFontsLocalAdditionalPaths) : ?>
                                            <details class="wpacu-google-fonts-local-paths-more">
                                                <summary><?php printf(esc_html__('+%s more', 'wp-asset-clean-up'), esc_html(number_format_i18n(count($googleFontsLocalAdditionalPaths)))); ?></summary>
                                                <ul class="wpacu-google-fonts-local-paths">
                                                    <?php foreach ($googleFontsLocalAdditionalPaths as $googleFontsLocalPath) : ?>
                                                        <li><code><?php echo esc_html($googleFontsLocalPath); ?></code></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </details>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span aria-hidden="true">—</span>
                                    <?php endif; ?>
                                </td>
                                <td data-colname="<?php esc_attr_e('Local copy', 'wp-asset-clean-up'); ?>">
                                    <?php if ($entryStatus === 'ready') : ?>
                                        <strong><?php printf(esc_html__('%1$s files · %2$s', 'wp-asset-clean-up'), esc_html(number_format_i18n((int) $googleFontsLocalEntry['fontCount'])), esc_html(size_format((int) $googleFontsLocalEntry['totalBytes']))); ?></strong>
                                        <?php if (! empty($googleFontsLocalEntry['localCssUrl'])) : ?>
                                            <a class="wpacu-google-fonts-local-open-css" href="<?php echo esc_url($googleFontsLocalEntry['localCssUrl']); ?>" target="_blank" rel="noopener noreferrer"><span><?php esc_html_e('Open CSS', 'wp-asset-clean-up'); ?></span><span class="dashicons dashicons-external" aria-hidden="true"></span></a>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span aria-hidden="true">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </section>
                <?php endforeach; ?>
                </div>
            </details>
        </div>

        <?php
        /**
         * Pro appends browser discovery, periodic refresh and exclusion controls after the inventory.
         */
        do_action('wpacu_google_fonts_local_settings_extra', $data, $settingsName, $googleFontsLocalConfig);
        ?>
        </div>
    </div>

    <script type="application/json" id="wpacu-google-fonts-local-config"><?php echo wp_json_encode($googleFontsLocalConfig, $googleFontsLocalJsonFlags); ?></script>
</section>

<div class="wpacu-google-fonts-layout<?php echo $data['google_fonts_remove'] ? ' is-removal-enabled' : ''; ?>">
    <section id="wpacu-google-fonts-delivery" class="wpacu-google-fonts-card" aria-labelledby="wpacuGoogleFontsDeliveryTitle">
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
                               value="1"
                               />
                        <span class="wpacu_slider wpacu_round" aria-hidden="true"></span>
                    </label>
                    <div>
                        <strong><?php esc_html_e('Preconnect to fonts.gstatic.com', 'wp-asset-clean-up'); ?></strong>
                        <p><?php esc_html_e('Start DNS, TCP and TLS work before the stylesheet requests the font files.', 'wp-asset-clean-up'); ?></p>
                        <?php if ($googleFontsPreconnectWarning) : ?>
                            <div class="wpacu-google-fonts-preconnect-warning" role="note">
                                <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                                <div><strong><?php esc_html_e('Usually unnecessary while Google Fonts are hosted locally.', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('Keep it enabled only if some font files can still be requested from Google, for example from excluded or dynamically injected configurations.', 'wp-asset-clean-up'); ?></span></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div id="google_fonts_preconnect_wrap" class="wpacu-google-fonts-generated-output"<?php echo (! $data['google_fonts_preconnect']) ? ' style="opacity: 0.4;"' : ''; ?>>
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

    <section id="wpacu-google-fonts-combine" class="wpacu-google-fonts-card wpacu-google-fonts-card--legacy" aria-labelledby="wpacuGoogleFontsCombineTitle">
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

    <section id="wpacu-google-fonts-manual-preload" class="wpacu-google-fonts-manual-section" aria-label="<?php esc_attr_e('Manual Google font-file preloads', 'wp-asset-clean-up'); ?>">
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
            'enabled_id'           => 'wpacu_google_fonts_preload_files_enable',
            'enabled_name'         => $settingsName . '[google_fonts_preload_files_enable]',
            'enabled_value'        => ! empty($data['google_fonts_preload_files_enable']) ? 1 : 0,
            'enabled_label'        => __('Enable manual Google font-file preloading', 'wp-asset-clean-up'),
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

<div id="wpacu-google-fonts-local-reset-info" class="wpacu-modal wpacu-bulk-info-modal wpacu-google-fonts-cache-modal" role="dialog" aria-modal="true" aria-labelledby="wpacu-google-fonts-local-reset-info-title">
    <div class="wpacu-modal-content wpacu-bulk-info-modal__content wpacu-google-fonts-cache-modal__content">
        <button type="button" class="wpacu-close wpacu-bulk-info-modal__close" aria-label="<?php esc_attr_e('Close', 'wp-asset-clean-up'); ?>">&times;</button>
        <header class="wpacu-bulk-info-modal__header">
            <span class="wpacu-bulk-info-modal__eyebrow"><?php esc_html_e('Safe cache reset', 'wp-asset-clean-up'); ?></span>
            <h2 id="wpacu-google-fonts-local-reset-info-title"><?php esc_html_e('Reset local copies safely', 'wp-asset-clean-up'); ?></h2>
            <p><?php esc_html_e('Retire the active Google Fonts inventory without immediately breaking references held by cached pages.', 'wp-asset-clean-up'); ?></p>
        </header>
        <div class="wpacu-bulk-info-modal__body wpacu-google-fonts-cache-modal__body">
            <section class="wpacu-bulk-info-modal__summary"><span class="wpacu-bulk-info-modal__summary-icon"><span class="dashicons dashicons-backup" aria-hidden="true"></span></span><div><strong><?php esc_html_e('Clears the active inventory and stops publishing its current local-copy records', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('Keeps the physical CSS and font files temporarily available so previously cached HTML can continue loading them during the retention period.', 'wp-asset-clean-up'); ?></span></div></section>
            <ul>
                <li><strong><?php esc_html_e('Use it when:', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('you want to rebuild the inventory from representative pages or recover from stale local-copy records.', 'wp-asset-clean-up'); ?></li>
                <li><strong><?php esc_html_e('It does not:', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('immediately erase every generated CSS, manifest, or font file.', 'wp-asset-clean-up'); ?></li>
                <li><strong><?php esc_html_e('Next step:', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('Scan key pages or visit representative public pages when you are ready to rebuild the inventory.', 'wp-asset-clean-up'); ?></li>
            </ul>
        </div>
    </div>
</div>

<div id="wpacu-google-fonts-local-cleanup-info" class="wpacu-modal wpacu-bulk-info-modal wpacu-google-fonts-cache-modal" role="dialog" aria-modal="true" aria-labelledby="wpacu-google-fonts-local-cleanup-info-title">
    <div class="wpacu-modal-content wpacu-bulk-info-modal__content wpacu-google-fonts-cache-modal__content">
        <button type="button" class="wpacu-close wpacu-bulk-info-modal__close" aria-label="<?php esc_attr_e('Close', 'wp-asset-clean-up'); ?>">&times;</button>
        <header class="wpacu-bulk-info-modal__header">
            <span class="wpacu-bulk-info-modal__eyebrow"><?php esc_html_e('Routine housekeeping', 'wp-asset-clean-up'); ?></span>
            <h2 id="wpacu-google-fonts-local-cleanup-info-title"><?php esc_html_e('Delete expired unused files', 'wp-asset-clean-up'); ?></h2>
            <p><?php esc_html_e('Recover disk space conservatively while preserving every file that is still referenced by an active local copy.', 'wp-asset-clean-up'); ?></p>
        </header>
        <div class="wpacu-bulk-info-modal__body wpacu-google-fonts-cache-modal__body">
            <section class="wpacu-bulk-info-modal__summary"><span class="wpacu-bulk-info-modal__summary-icon"><span class="dashicons dashicons-filter" aria-hidden="true"></span></span><div><strong><?php esc_html_e('Removes only expired orphaned files', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('Deletes only unreferenced files that are older than the retention period configured for generated Asset CleanUp files.', 'wp-asset-clean-up'); ?></span></div></section>
            <ul>
                <li><strong><?php esc_html_e('Protected:', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('CSS, manifests, and font files referenced by Ready configurations remain untouched.', 'wp-asset-clean-up'); ?></li>
                <li><strong><?php esc_html_e('Use it when:', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('you want normal maintenance without resetting the current inventory.', 'wp-asset-clean-up'); ?></li>
                <li><strong><?php esc_html_e('Expected result:', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('recent retired files may remain until their retention period expires.', 'wp-asset-clean-up'); ?></li>
            </ul>
        </div>
    </div>
</div>

<div id="wpacu-google-fonts-local-clear-info" class="wpacu-modal wpacu-bulk-info-modal wpacu-google-fonts-cache-modal" role="dialog" aria-modal="true" aria-labelledby="wpacu-google-fonts-local-clear-info-title">
    <div class="wpacu-modal-content wpacu-bulk-info-modal__content wpacu-google-fonts-cache-modal__content">
        <button type="button" class="wpacu-close wpacu-bulk-info-modal__close" aria-label="<?php esc_attr_e('Close', 'wp-asset-clean-up'); ?>">&times;</button>
        <header class="wpacu-bulk-info-modal__header">
            <span class="wpacu-bulk-info-modal__eyebrow"><?php esc_html_e('Immediate destructive cleanup', 'wp-asset-clean-up'); ?></span>
            <h2 id="wpacu-google-fonts-local-clear-info-title"><?php esc_html_e('Delete files immediately', 'wp-asset-clean-up'); ?></h2>
            <p><?php esc_html_e('Remove every managed local Google Fonts CSS file, manifest, downloaded font file, and active inventory record now.', 'wp-asset-clean-up'); ?></p>
        </header>
        <div class="wpacu-bulk-info-modal__body wpacu-google-fonts-cache-modal__body">
            <div class="wpacu-google-fonts-cache-modal__warning"><span class="dashicons dashicons-warning" aria-hidden="true"></span><div><strong><?php esc_html_e('Purge page and CDN caches at the same time', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('Cached HTML or CDN pages may briefly reference files that no longer exist, which can cause fallback fonts or missing icons until those caches are refreshed.', 'wp-asset-clean-up'); ?></span></div></div>
            <ul>
                <li><strong><?php esc_html_e('Use it when:', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('you need an immediate clean slate and can purge every HTML/page/CDN cache that may contain old local URLs.', 'wp-asset-clean-up'); ?></li>
                <li><strong><?php esc_html_e('After deletion:', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('Google Fonts requests remain remote until you deliberately discover and rebuild local copies.', 'wp-asset-clean-up'); ?></li>
                <li><strong><?php esc_html_e('Rebuild:', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('Scan key pages or visit representative public pages when you are ready to rebuild the inventory.', 'wp-asset-clean-up'); ?></li>
            </ul>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    function initGoogleFontsQuickNav() {
        var nav = document.querySelector('.wpacu-google-fonts-quick-nav');

        if (! nav || nav.getAttribute('data-scrollspy-initialized') === '1') {
            return;
        }

        var items = Array.prototype.map.call(nav.querySelectorAll('a[href^="#"]'), function (link) {
            return { link: link, section: document.querySelector(link.getAttribute('href')) };
        }).filter(function (item) {
            return item.section;
        });

        if (! items.length) {
            return;
        }

        nav.setAttribute('data-scrollspy-initialized', '1');
        var isAnimatingScroll = false;
        var updateQueued = false;
        var scrollAnimationId = 0;

        function getNavAnchorLine() {
            var navStyles = window.getComputedStyle ? window.getComputedStyle(nav) : null;

            if (navStyles && navStyles.position === 'sticky') {
                return (parseFloat(navStyles.top) || 0) + nav.offsetHeight;
            }

            return nav.getBoundingClientRect().bottom;
        }

        function setActiveItem(activeItem) {
            items.forEach(function (item) {
                var isActive = item === activeItem;
                item.link.classList.toggle('is-active', isActive);

                if (isActive) {
                    item.link.setAttribute('aria-current', 'location');
                } else {
                    item.link.removeAttribute('aria-current');
                }
            });
        }

        function updateActiveLink() {
            if (isAnimatingScroll) {
                updateQueued = false;
                return;
            }

            var activationLine = getNavAnchorLine() + 12;
            var activeItem = items[0];

            items.forEach(function (item) {
                if (item.section.getBoundingClientRect().top <= activationLine) {
                    activeItem = item;
                }
            });

            setActiveItem(activeItem);
            updateQueued = false;
        }

        function queueUpdate() {
            if (! updateQueued) {
                updateQueued = true;
                window.requestAnimationFrame(updateActiveLink);
            }
        }

        function animateScrollTo(item) {
            var animationId = ++scrollAnimationId;
            var startY = window.pageYOffset;
            var targetY = Math.max(0, startY + item.section.getBoundingClientRect().top - getNavAnchorLine() - 12);
            var distance = targetY - startY;
            var duration = 280;
            var startTime = window.performance.now();
            var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (reduceMotion || distance === 0) {
                window.scrollTo(0, targetY);
                isAnimatingScroll = false;
                return;
            }

            isAnimatingScroll = true;

            function animate(currentTime) {
                if (animationId !== scrollAnimationId) {
                    return;
                }

                var progress = Math.min((currentTime - startTime) / duration, 1);
                var easedProgress = 1 - Math.pow(1 - progress, 3);
                window.scrollTo(0, startY + (distance * easedProgress));

                if (progress < 1) {
                    window.requestAnimationFrame(animate);
                } else {
                    isAnimatingScroll = false;
                    updateActiveLink();
                }
            }

            window.requestAnimationFrame(animate);
        }

        window.addEventListener('scroll', queueUpdate, { passive: true });
        window.addEventListener('resize', queueUpdate);
        items.forEach(function (item) {
            item.link.addEventListener('click', function (event) {
                event.preventDefault();
                setActiveItem(item);
                animateScrollTo(item);
            });
        });
        updateActiveLink();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGoogleFontsQuickNav);
    } else {
        initGoogleFontsQuickNav();
    }
}());
</script>
