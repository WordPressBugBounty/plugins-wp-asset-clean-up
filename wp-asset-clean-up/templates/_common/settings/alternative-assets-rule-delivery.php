<?php
if (! isset($data, $settingsName)) { exit; }
$alternativeAssetsEnabled   = ! isset($data['alternative_assets_status']) || $data['alternative_assets_status'] !== 'off';
$canManageAlternativeAssets = \WpAssetCleanUp\Menu::userCanManageAlternativeAssets();
$alternativeAssetsUrl       = admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_sub_page=manage_alternative_assets');
?>
<header class="wpacu-cssjs-header" aria-labelledby="wpacuAlternativeAssetsSettingsTitle">
    <div class="wpacu-cssjs-header__copy">
        <div class="wpacu-cssjs-eyebrow"><?php esc_html_e('Alternative Assets setup', 'wp-asset-clean-up'); ?></div>
        <h2 id="wpacuAlternativeAssetsSettingsTitle"><?php esc_html_e('Control when alternative assets replace original files', 'wp-asset-clean-up'); ?></h2>
        <p><?php esc_html_e('Enable or pause your saved CSS and JavaScript alternatives independently of Manage CSS/JS and Critical CSS. Create and edit replacements in the Alternative Assets manager.', 'wp-asset-clean-up'); ?></p>
    </div>
    <div class="wpacu-cssjs-header__actions">
        <span class="wpacu-cssjs-header__badge"><?php esc_html_e('Rule delivery preferences', 'wp-asset-clean-up'); ?></span>
        <a class="wpacu-cssjs-header__action wpacu-new-style-external-link" href="<?php echo esc_url($alternativeAssetsUrl); ?>" target="_blank" rel="noopener noreferrer">
            <span class="wpacu-new-style-external-link__text"><?php esc_html_e('Open Alternative Assets', 'wp-asset-clean-up'); ?></span>
            <span class="dashicons dashicons-external" aria-hidden="true"></span>
        </a>
    </div>
</header>
<section id="wpacu-cssjs-alternative-assets" class="wpacu-cssjs-section" aria-labelledby="wpacuAlternativeAssetsDeliveryTitle">
    <div class="wpacu-cssjs-scope-heading wpacu-cssjs-scope-heading--manager">
        <span class="wpacu-cssjs-scope-heading__icon" aria-hidden="true"><span class="dashicons dashicons-editor-code"></span></span>
        <div>
            <span class="wpacu-cssjs-scope-heading__eyebrow"><?php esc_html_e('Alternative Assets', 'wp-asset-clean-up'); ?></span>
            <h3 id="wpacuAlternativeAssetsDeliveryTitle"><?php esc_html_e('Alternative asset delivery', 'wp-asset-clean-up'); ?></h3>
            <p><?php esc_html_e('Pause replacements without deleting or changing individual rules.', 'wp-asset-clean-up'); ?></p>
        </div>
    </div>
    <article class="wpacu-cssjs-setting-card wpacu-cssjs-critical-css-card <?php echo $alternativeAssetsEnabled ? 'is-active' : 'is-paused'; ?>">
        <div class="wpacu-cssjs-master-row wpacu-cssjs-critical-css-master-row">
            <div class="wpacu-cssjs-master-row__control">
                <?php if ($canManageAlternativeAssets) { ?>
                <input type="hidden" name="<?php echo esc_attr($settingsName); ?>[alternative_assets_status]" value="off" />
                <?php } ?>
                <label class="wpacu_switch" for="wpacu_alternative_assets_status">
                    <input id="wpacu_alternative_assets_status" type="checkbox" name="<?php echo esc_attr($settingsName); ?>[alternative_assets_status]" value="on" <?php checked($alternativeAssetsEnabled); disabled(!$canManageAlternativeAssets); ?> aria-describedby="wpacuAlternativeAssetsHelp" />
                    <span class="wpacu_slider wpacu_round" aria-hidden="true"></span>
                </label>
                <label class="wpacu-cssjs-control-label" for="wpacu_alternative_assets_status"><strong><?php esc_html_e('Apply Alternative Asset rules', 'wp-asset-clean-up'); ?></strong><span><?php esc_html_e('Enabled by default. Save after changing this switch.', 'wp-asset-clean-up'); ?></span></label>
            </div>
            <div class="wpacu-cssjs-master-row__copy">
                <span class="wpacu-cssjs-kicker"><?php esc_html_e('Main setting', 'wp-asset-clean-up'); ?></span>
                <h4><?php esc_html_e('Keep saved alternative assets enabled', 'wp-asset-clean-up'); ?></h4>
                <p id="wpacuAlternativeAssetsHelp"><?php esc_html_e('When paused, original files load in place of their alternatives. Your replacement code and individual rule settings stay saved.', 'wp-asset-clean-up'); ?></p>
                <p><?php esc_html_e('CSS/JS unload rules still apply: an unloaded asset will not load its alternative. Test Mode and page exclusions are also respected.', 'wp-asset-clean-up'); ?></p>
                <?php if (!$canManageAlternativeAssets) { ?><p><?php esc_html_e('Managing Alternative Assets requires permission to save unfiltered HTML and JavaScript.', 'wp-asset-clean-up'); ?></p><?php } ?>
                <div class="wpacu-cssjs-critical-css-meta">
                    <span class="wpacu-cssjs-critical-css-status <?php echo $alternativeAssetsEnabled ? 'is-active' : 'is-paused'; ?>" aria-live="polite" data-active-label="<?php esc_attr_e('Active', 'wp-asset-clean-up'); ?>" data-paused-label="<?php esc_attr_e('Paused', 'wp-asset-clean-up'); ?>"><?php echo $alternativeAssetsEnabled ? esc_html__('Active', 'wp-asset-clean-up') : esc_html__('Paused', 'wp-asset-clean-up'); ?></span>
                    <span class="wpacu-cssjs-critical-css-saved"><span class="dashicons dashicons-saved" aria-hidden="true"></span><?php esc_html_e('Rules stay saved', 'wp-asset-clean-up'); ?></span>
                </div>
            </div>
        </div>
    </article>
</section>
<script>
(function () {
    var section = document.getElementById('wpacu-cssjs-alternative-assets');
    var input = document.getElementById('wpacu_alternative_assets_status');
    var card = section.querySelector('article');
    var status = section.querySelector('.wpacu-cssjs-critical-css-status');
    input.addEventListener('change', function () {
        [card, status].forEach(function (element) {
            element.classList.toggle('is-active', input.checked);
            element.classList.toggle('is-paused', !input.checked);
        });
        status.textContent = status.getAttribute(input.checked ? 'data-active-label' : 'data-paused-label');
    });
}());
</script>
