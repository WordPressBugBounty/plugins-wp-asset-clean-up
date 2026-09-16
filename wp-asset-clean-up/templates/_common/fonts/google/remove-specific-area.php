<?php
if (! isset($data)) {
    exit;
}

$settingsName = WPACU_PLUGIN_ID . '_settings';
$isPro = (bool) apply_filters('wpacu_google_fonts_remove_specific_is_pro', false);
$removeAllEnabled = ! empty($data['google_fonts_remove']);
$specificInventory = isset($data['google_fonts_local_config']['specificInventory'])
    && is_array($data['google_fonts_local_config']['specificInventory'])
    ? $data['google_fonts_local_config']['specificInventory']
    : array();
$specificLabels = isset($specificInventory['labels']) && is_array($specificInventory['labels'])
    ? $specificInventory['labels']
    : array();
$readyFamilies = isset($specificInventory['ready']) && is_array($specificInventory['ready'])
    ? $specificInventory['ready']
    : array();
$failedFamilies = isset($specificInventory['failed']) && is_array($specificInventory['failed'])
    ? $specificInventory['failed']
    : array();
$specificFamilyKeys = array();
foreach (array($readyFamilies, $failedFamilies) as $specificFamilies) {
    foreach ($specificFamilies as $familyData) {
        if (is_array($familyData) && isset($familyData['family']) && is_scalar($familyData['family'])) {
            $specificFamilyKeys[strtolower(trim((string) $familyData['family']))] = true;
        }
    }
}
$specificFamilyCount = count($specificFamilyKeys);
$failedConfigurationCount = isset($specificInventory['failedConfigurationCount'])
    ? max(0, (int) $specificInventory['failedConfigurationCount'])
    : 0;
$hasSpecificFamilies = ! empty($readyFamilies) || ! empty($failedFamilies);
$hasSpecificContent = $hasSpecificFamilies || $failedConfigurationCount > 0;
$showFailedGroup = ! empty($failedFamilies) || $failedConfigurationCount > 0;
$readyVariantCount = 0;
$failedVariantCount = 0;
foreach ($readyFamilies as $familyData) {
    $readyVariantCount += isset($familyData['variants']) && is_array($familyData['variants']) ? count($familyData['variants']) : 0;
}
foreach ($failedFamilies as $familyData) {
    $failedVariantCount += isset($familyData['variants']) && is_array($familyData['variants']) ? count($familyData['variants']) : 0;
}
$retryAttempted = ! empty($specificInventory['retryAttempted']);
$specificLabel = function($key, $fallback) use ($specificLabels) {
    return isset($specificLabels[$key]) && is_scalar($specificLabels[$key])
        ? (string) $specificLabels[$key]
        : $fallback;
};
$renderSpecificFamilyCards = function($families) use ($isPro, $settingsName, $specificLabel) {
    foreach ($families as $familyData) :
        if (! is_array($familyData) || empty($familyData['family']) || empty($familyData['variants']) || ! is_array($familyData['variants'])) {
            continue;
        }
        $family = (string) $familyData['family'];
        $variants = $familyData['variants'];
        $familySources = array();
        $familyUrls = array();
        foreach (isset($familyData['sources']) ? (array) $familyData['sources'] : array() as $source) {
            if ($source === 'remote' || $source === 'local') {
                $familySources[$source] = true;
            }
        }
        $hasPreviouslyDiscovered = false;
        foreach ($variants as $variant) {
            if (is_array($variant) && isset($variant['availability']) && $variant['availability'] === 'previously_discovered') {
                $hasPreviouslyDiscovered = true;
            }
        }
        $familySourceLabels = array();
        if (isset($familySources['remote'])) {
            $familySourceLabels[] = $specificLabel('deliveredByGoogle', __('Delivered by Google', 'wp-asset-clean-up'));
        }
        if (isset($familySources['local'])) {
            $familySourceLabels[] = $specificLabel('localCopy', __('Local copy', 'wp-asset-clean-up'));
        }
        if ($hasPreviouslyDiscovered) {
            $familySourceLabels[] = $specificLabel('previouslyDiscovered', __('Previously discovered', 'wp-asset-clean-up'));
        }
        foreach (isset($familyData['urls']) ? (array) $familyData['urls'] : array() as $familyUrl) {
            if (! is_string($familyUrl)) {
                continue;
            }
            $familyUrlParts = wp_parse_url($familyUrl);
            if (is_array($familyUrlParts)
                && isset($familyUrlParts['scheme'], $familyUrlParts['host'])
                && strtolower($familyUrlParts['scheme']) === 'https'
                && rtrim(strtolower($familyUrlParts['host']), '.') === 'fonts.googleapis.com') {
                $familyUrls[$familyUrl] = true;
            }
        }
        ?>
        <section class="wpacu-google-fonts-specific__family" data-family="<?php echo esc_attr(strtolower($family)); ?>">
            <header>
                <div>
                    <strong><?php echo esc_html($family); ?></strong>
                    <span><?php echo esc_html(sprintf(count($variants) === 1 ? $specificLabel('variantOne', __('%d variant', 'wp-asset-clean-up')) : $specificLabel('variantMany', __('%d variants', 'wp-asset-clean-up')), count($variants))); ?></span>
                    <?php if ($familySourceLabels || $familyUrls) : ?>
                        <small class="wpacu-google-fonts-specific__provenance">
                            <?php echo esc_html(implode(' · ', $familySourceLabels)); ?>
                            <?php foreach (array_keys($familyUrls) as $familyUrlIndex => $familyUrl) : ?>
                                <?php echo $familySourceLabels || $familyUrlIndex > 0 ? ' · ' : ''; ?><a href="<?php echo esc_url($familyUrl); ?>" target="_blank" rel="noopener noreferrer" data-wpacu-google-fonts-specific-stylesheet-link>fonts.googleapis.com<?php echo count($familyUrls) > 1 ? ' #' . (int) ($familyUrlIndex + 1) : ''; ?></a>
                            <?php endforeach; ?>
                        </small>
                    <?php endif; ?>
                </div>
                <?php if ($isPro) : ?>
                    <div class="wpacu-google-fonts-specific__family-actions">
                        <button type="button" class="button-link wpacu-google-fonts-specific__family-select-all"><?php echo esc_html($specificLabel('selectAll', __('Select all', 'wp-asset-clean-up'))); ?></button>
                        <span aria-hidden="true">|</span>
                        <button type="button" class="button-link wpacu-google-fonts-specific__family-clear"><?php echo esc_html($specificLabel('clearSelection', __('Clear selection', 'wp-asset-clean-up'))); ?></button>
                    </div>
                <?php endif; ?>
            </header>
            <div class="wpacu-google-fonts-specific__variants">
                <?php foreach ($variants as $variant) :
                    if (! is_array($variant) || empty($variant['token']) || empty($variant['weight'])) {
                        continue;
                    }
                    $token = (string) $variant['token'];
                    $isSelected = ! empty($variant['selected']);
                    $variant['style'] = isset($variant['style']) && $variant['style'] === 'italic' ? 'italic' : 'normal';
                    $styleLabel = $variant['style'] === 'italic'
                        ? $specificLabel('italic', __('Italic', 'wp-asset-clean-up'))
                        : $specificLabel('regular', __('Regular', 'wp-asset-clean-up'));
                    ?>
                    <label class="wpacu-google-fonts-specific__variant<?php echo ! $isPro ? ' is-locked' : ''; ?>">
                        <input type="checkbox"
                               value="<?php echo esc_attr($token); ?>"
                            <?php if ($isPro) : ?>
                               name="<?php echo esc_attr($settingsName); ?>[google_fonts_remove_specific][]"
                            <?php endif; ?>
                            <?php checked($isSelected); ?>
                            <?php disabled(! $isPro); ?>>
                        <span class="wpacu-google-fonts-specific__check" aria-hidden="true"></span>
                        <span class="wpacu-google-fonts-specific__variant-name">
                            <strong><?php echo esc_html($family . ' — ' . $variant['weight']); ?></strong>
                        </span>
                        <span class="wpacu-google-fonts-specific__style-badge is-<?php echo esc_attr($variant['style']); ?>"><?php echo esc_html($styleLabel); ?></span>
                        <?php if (! $isPro) : ?>
                            <span class="wpacu-google-fonts-specific__action"><span class="dashicons dashicons-lock" aria-hidden="true"></span></span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach;
};
?>
<?php if (! $isPro) : ?>
    <div class="wpacu-google-fonts-specific__upgrade">
        <span class="dashicons dashicons-lock" aria-hidden="true"></span>
        <div>
            <strong><?php esc_html_e('Selective Google Fonts removal is available in Pro', 'wp-asset-clean-up'); ?></strong>
            <p><?php esc_html_e('Lite can optimize Google Fonts or remove all of them. Pro lets you keep the variants you need and remove only selected weights or styles.', 'wp-asset-clean-up'); ?></p>
        </div>
    </div>
<?php endif; ?>

<?php if ($isPro && $removeAllEnabled) : ?>
    <div class="wpacu-google-fonts-disabled-notice" role="note">
        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
        <div>
            <strong><?php esc_html_e('Google Fonts removal is currently enabled.', 'wp-asset-clean-up'); ?></strong>
            <p><?php esc_html_e('“Remove All” takes priority. Your selective removal choices remain saved but inactive, and become active again after “Remove All” is disabled and the settings are saved.', 'wp-asset-clean-up'); ?></p>
        </div>
    </div>
<?php endif; ?>

<div class="wpacu-google-fonts-specific<?php echo $isPro ? ' is-pro' : ' is-lite'; echo $removeAllEnabled ? ' is-disabled-by-remove-all' : ''; ?>">
    <section class="wpacu-google-fonts-specific__intro">
        <div>
            <span class="wpacu-google-fonts-specific__eyebrow"><?php esc_html_e('Granular removal', 'wp-asset-clean-up'); ?></span>
            <h3><?php esc_html_e('Remove only the Google Fonts variants you do not need', 'wp-asset-clean-up'); ?></h3>
            <p><?php esc_html_e('Select individual font weights and styles. Other variants from the same family remain available.', 'wp-asset-clean-up'); ?></p>
        </div>
        <span class="wpacu-google-fonts-specific__pro-badge">PRO</span>
    </section>

    <?php if ($isPro) : ?>
        <input type="hidden" name="<?php echo esc_attr($settingsName); ?>[google_fonts_remove_specific][]" value="">
        <input type="hidden"
               class="wpacu-google-fonts-specific__serialized"
               data-name="<?php echo esc_attr($settingsName); ?>[google_fonts_remove_specific_serialized]"
               value="">
    <?php endif; ?>

    <div data-wpacu-google-fonts-specific-inventory>
        <div class="wpacu-google-fonts-specific__empty" data-wpacu-google-fonts-specific-empty<?php echo $hasSpecificFamilies ? ' hidden' : ''; ?>>
            <span class="dashicons dashicons-search" aria-hidden="true"></span>
            <div>
                <strong><?php echo esc_html($specificLabel('emptyTitle', __('No Google Fonts variants have been discovered yet.', 'wp-asset-clean-up'))); ?></strong>
                <p><?php echo esc_html($specificLabel('emptyDescription', __('Visit the front-end pages that use Google Fonts, then return here. Detected stylesheet configurations are used to build this list.', 'wp-asset-clean-up'))); ?></p>
            </div>
        </div>
        <div data-wpacu-google-fonts-specific-content<?php echo ! $hasSpecificContent ? ' hidden' : ''; ?>>
        <div class="wpacu-google-fonts-specific__toolbar">
            <label>
                <span class="screen-reader-text"><?php esc_html_e('Search detected Google Fonts', 'wp-asset-clean-up'); ?></span>
                <input type="search" class="wpacu-google-fonts-specific__search" placeholder="<?php esc_attr_e('Search font families…', 'wp-asset-clean-up'); ?>" <?php disabled(! $isPro); ?>>
            </label>
            <span><?php echo esc_html(sprintf($hasSpecificFamilies && $specificFamilyCount === 1 ? $specificLabel('familyOne', __('%d family detected', 'wp-asset-clean-up')) : $specificLabel('familyMany', __('%d families detected', 'wp-asset-clean-up')), $specificFamilyCount)); ?></span>
        </div>

        <div class="wpacu-google-fonts-specific__families">
            <section class="wpacu-google-fonts-specific__group is-ready" data-wpacu-google-fonts-specific-group="ready"<?php echo empty($readyFamilies) ? ' hidden' : ''; ?>>
                <header class="wpacu-google-fonts-specific__group-header">
                    <h4><?php echo esc_html($specificLabel('readyVariants', __('Ready variants', 'wp-asset-clean-up'))); ?> <span class="wpacu-google-fonts-specific__group-count"><?php echo (int) $readyVariantCount; ?></span></h4>
                </header>
                <?php $renderSpecificFamilyCards($readyFamilies); ?>
            </section>
            <section class="wpacu-google-fonts-specific__group is-failed" data-wpacu-google-fonts-specific-group="failed"<?php echo ! $showFailedGroup ? ' hidden' : ''; ?>>
                <header class="wpacu-google-fonts-specific__group-header">
                    <h4><?php echo esc_html($specificLabel('failedVariants', __('Failed variants', 'wp-asset-clean-up'))); ?> <span class="wpacu-google-fonts-specific__group-count"><?php echo (int) $failedVariantCount; ?></span></h4>
                    <button type="button" class="button" data-wpacu-google-fonts-specific-retry-failed <?php disabled(! $isPro); ?>><?php echo esc_html($specificLabel('retryFailed', __('Retry failed', 'wp-asset-clean-up'))); ?></button>
                </header>
                <div class="wpacu-google-fonts-specific__failed-intro" data-wpacu-google-fonts-specific-failed-intro>
                    <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                    <span><?php echo esc_html($specificLabel('failedIntro', __('Some detected Google Fonts variants could not be loaded and may no longer be needed. Review them before deciding whether to keep or remove them.', 'wp-asset-clean-up'))); ?></span>
                </div>
                <div class="wpacu-google-fonts-specific__failed-advice" data-wpacu-google-fonts-specific-failed-advice<?php echo ! ($retryAttempted && $failedConfigurationCount > 0) ? ' hidden' : ''; ?>>
                    <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                    <span><?php echo esc_html($specificLabel('failedAdvice', __('Retry completed, but some stylesheet URLs still failed. Open the Google APIs URLs to confirm they are valid, then select and remove any variants you no longer need.', 'wp-asset-clean-up'))); ?></span>
                </div>
                <?php $renderSpecificFamilyCards($failedFamilies); ?>
            </section>
        </div>

        <?php if ($isPro) : ?>
            <div class="wpacu-google-fonts-specific__footer">
                <div>
                    <strong class="wpacu-google-fonts-specific__selected-count">0</strong>
                    <span><?php echo esc_html($specificLabel('selectionCount', __('variants selected for removal', 'wp-asset-clean-up'))); ?></span>
                </div>
                <span><?php esc_html_e('Save Changes to apply the selection.', 'wp-asset-clean-up'); ?></span>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>
<script>
(function() {
    var root = document.querySelector('.wpacu-google-fonts-specific.is-pro');
    if (!root) return;
    function getBoxes() {
        return Array.prototype.slice.call(root.querySelectorAll('.wpacu-google-fonts-specific__variant input[type="checkbox"]'));
    }
    function syncSerialized() {
        var serialized = root.querySelector('.wpacu-google-fonts-specific__serialized');
        if (!serialized) return;
        serialized.value = getBoxes().filter(function(box) { return box.checked; }).map(function(box) { return box.value; }).join('\n');
        if (!serialized.name) serialized.name = serialized.getAttribute('data-name');
    }
    function updateCount() {
        var boxes = getBoxes();
        var count = root.querySelector('.wpacu-google-fonts-specific__selected-count');
        var selectedCount = boxes.filter(function(box) { return box.checked; }).length;
        var subTabIndicator = typeof document.getElementById === 'function'
            ? document.getElementById('wpacu-google-fonts-remove-specific-sub-tab-indicator')
            : null;
        syncSerialized();
        boxes.forEach(function(box) {
            var variant = box.closest ? box.closest('.wpacu-google-fonts-specific__variant') : null;
            if (variant) variant.classList.toggle('is-selected', box.checked);
        });
        if (count) count.textContent = selectedCount;
        if (subTabIndicator) subTabIndicator.classList.toggle('is-visible', selectedCount > 0);
    }
    root.addEventListener('click', function(event) {
        var selectAllButton = event.target.closest ? event.target.closest('.wpacu-google-fonts-specific__family-select-all') : null;
        var clearButton = event.target.closest ? event.target.closest('.wpacu-google-fonts-specific__family-clear') : null;
        var button = selectAllButton || clearButton;
        if (!button) return;
        var family = button.closest('.wpacu-google-fonts-specific__family');
        var familyBoxes = Array.prototype.slice.call(family.querySelectorAll('input[type="checkbox"]'));
        familyBoxes.forEach(function(box) { box.checked = !!selectAllButton; });
        updateCount();
    });
    function toggleSelectAllPreview(event, enabled) {
        var button = event.target.closest ? event.target.closest('.wpacu-google-fonts-specific__family-select-all') : null;
        if (!button) return;
        var family = button.closest('.wpacu-google-fonts-specific__family');
        if (family) family.classList.toggle('is-select-all-preview', enabled);
    }
    root.addEventListener('mouseover', function(event) { toggleSelectAllPreview(event, true); });
    root.addEventListener('mouseout', function(event) { toggleSelectAllPreview(event, false); });
    root.addEventListener('focusin', function(event) { toggleSelectAllPreview(event, true); });
    root.addEventListener('focusout', function(event) { toggleSelectAllPreview(event, false); });
    root.addEventListener('change', updateCount);
    var form = root.closest ? root.closest('form') : null;
    if (form) form.addEventListener('submit', syncSerialized);
    function applySearchFilter() {
        var search = root.querySelector('.wpacu-google-fonts-specific__search');
        var needle = search ? search.value.toLowerCase().trim() : '';
        Array.prototype.forEach.call(root.querySelectorAll('.wpacu-google-fonts-specific__family'), function(family) {
            family.hidden = needle !== '' && family.getAttribute('data-family').indexOf(needle) === -1;
        });
    }
    root.addEventListener('input', function(event) {
        var search = event.target.closest ? event.target.closest('.wpacu-google-fonts-specific__search') : null;
        if (search) applySearchFilter();
    });
    root.addEventListener('wpacu:google-fonts-specific-rendered', function() {
        updateCount();
        applySearchFilter();
    });
    updateCount();
    applySearchFilter();
})();
</script>
