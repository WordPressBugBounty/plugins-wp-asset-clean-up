<?php
use WpAssetCleanUp\Admin\AlternativeAssetsAdmin;
use WpAssetCleanUp\OptimiseAssets\AlternativeAssets;

if ( ! defined('ABSPATH') ) {
    exit;
}

if ( ! \WpAssetCleanUp\Menu::userCanManageAlternativeAssets() ) {
    if ( ! \WpAssetCleanUp\Menu::userCanAccessPlugin() ) {
        wp_die(esc_html__('You are not allowed to manage Alternative Assets.', 'wp-asset-clean-up'), '', array('response' => 403));
    }
    ?>
    <div class="notice notice-warning inline">
        <h2><?php esc_html_e('Alternative Assets requires additional permissions', 'wp-asset-clean-up'); ?></h2>
        <p><?php
            printf(
                /* translators: %s: WordPress capability name, displayed as code. */
                esc_html__('Your account can access Asset CleanUp, but WordPress does not currently allow it to save unfiltered code (%s). This permission is required to manage alternative CSS and JavaScript assets.', 'wp-asset-clean-up'),
                '<code>unfiltered_html</code>'
            );
        ?></p>
        <p><?php
            if (defined('DISALLOW_UNFILTERED_HTML') && DISALLOW_UNFILTERED_HTML) {
                printf(
                    /* translators: %s: WordPress configuration constant, displayed as code. */
                    esc_html__('This website disables this permission through %s.', 'wp-asset-clean-up'),
                    '<code>DISALLOW_UNFILTERED_HTML</code>'
                );
            } elseif (is_multisite() && ! is_super_admin()) {
                esc_html_e('On WordPress Multisite, this feature requires a Super Admin account.', 'wp-asset-clean-up');
            } else {
                esc_html_e('Your account\'s permissions or a security policy may restrict this capability.', 'wp-asset-clean-up');
            }
        ?></p>
    </div>
    <?php
    return;
}

$altData       = AlternativeAssetsAdmin::instance()->getPageData();
$assetType     = $altData['asset_type'];
$isJs          = $assetType === 'js';
$editRule      = $altData['edit_rule'];
$isFormView    = $altData['mode'] === 'form';
$isUpgradePage = $altData['mode'] === 'upgrade';
$baseUrl       = $altData['base_url'];
$cssUrl        = add_query_arg('asset_type', 'css', $baseUrl);
$jsUrl         = add_query_arg('asset_type', 'js', $baseUrl);
$addUrl        = add_query_arg(array('asset_type' => $assetType, 'wpacu_alt_view' => 'form'), $baseUrl);

$cssCount = count(AlternativeAssets::getRulesByType('css'));
$jsCount  = AlternativeAssets::supportsAssetType('js') ? count(AlternativeAssets::getRulesByType('js')) : 0;

$formRuleId     = $editRule ? (int)$editRule['id'] : 0;
$formType       = $editRule ? $editRule['type'] : $assetType;
$formHandle     = $editRule ? $editRule['handle'] : '';
$formSource     = $editRule ? $altData['edit_source'] : '';
$formTargets    = $editRule ? AlternativeAssets::getRuleTargets($editRule) : array('everywhere' => '');
$formPriority   = $editRule ? (int)$editRule['priority'] : 10;
$formEnabled    = $editRule ? ! empty($editRule['enabled']) : true;
$formOutputMode = $editRule ? AlternativeAssets::getOutputMode($editRule) : 'inherit';

if ( ! empty($altData['errors']) && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $postedType   = AlternativeAssets::sanitizeAssetType(isset($_POST['asset_type']) ? wp_unslash($_POST['asset_type']) : '');
    $formType     = ! $editRule && $postedType !== '' ? $postedType : $formType;
    $formHandle   = $editRule ? $editRule['handle'] : AlternativeAssets::sanitizeHandle(isset($_POST['handle']) ? wp_unslash($_POST['handle']) : '');
    $formSource   = isset($_POST['source']) && is_string($_POST['source']) ? wp_unslash($_POST['source']) : '';
    $formTargets  = AlternativeAssetsAdmin::instance()->getPostedTargets();
    $formPriority = isset($_POST['priority']) ? (int)$_POST['priority'] : 10;
    $formEnabled  = ! empty($_POST['enabled']);
    if (array_key_exists('output_mode', $_POST)) {
        $formOutputMode = AlternativeAssets::getOutputMode(array('output_mode' => $_POST['output_mode']));
    }
    $isFormView   = true;
}
// Show previously selected archive locations in the combined archive selector.
$archiveSelections = isset($formTargets['archive_types']) ? explode(',', $formTargets['archive_types']) : array();
foreach (array('posts_page', 'search', 'date', '404', 'cpt_archive') as $archiveTarget) {
    if (array_key_exists($archiveTarget, $formTargets)) {
        $archiveSelections = array_merge($archiveSelections, $archiveTarget === 'cpt_archive'
            ? array_map(static function ($slug) { return 'cpt_' . $slug; }, explode(',', $formTargets[$archiveTarget]))
            : array($archiveTarget));
        unset($formTargets[$archiveTarget]);
    }
}
if ($editRule && empty($altData['errors']) && isset($formTargets['author']) && $formTargets['author'] === '') {
    $archiveSelections[] = 'author';
    unset($formTargets['author']);
}
if ($archiveSelections) {
    $formTargets['archive_types'] = implode(',', array_unique($archiveSelections));
}
?>

<?php $alternativeAssetsEnabled = AlternativeAssets::isEnabled(); ?>
<div class="wpacu-alt-assets<?php echo $alternativeAssetsEnabled ? '' : ' is-globally-paused'; ?>" data-wpacu-current-asset-type="<?php echo esc_attr($assetType); ?>">
    <input id="wpacu-alt-global-status" type="checkbox" data-wpacu-master-input hidden tabindex="-1" aria-hidden="true" <?php checked($alternativeAssetsEnabled); ?> />
    <?php
    $wpacuMasterBox = array('input' => 'wpacu-alt-global-status', 'feedback' => 'wpacu-alt-global-feedback',
        'label' => __('Alternative Assets', 'wp-asset-clean-up'), 'enabled' => $alternativeAssetsEnabled);
    require __DIR__ . '/_common/manager-status-box.php';
    ?>
    <p id="wpacu-alt-global-feedback" class="wpacu-css-js-master-feedback" role="status" aria-live="polite"<?php if ($alternativeAssetsEnabled) { ?> hidden<?php } ?>><?php echo $alternativeAssetsEnabled
        ? esc_html__('Active. Enabled rules replace matching assets.', 'wp-asset-clean-up')
        : esc_html__('Paused. Original assets load; your rules stay saved.', 'wp-asset-clean-up'); ?></p>
    <fieldset class="wpacu-alt-assets__content" aria-label="<?php esc_attr_e('Alternative Assets configuration', 'wp-asset-clean-up'); ?>">
        <legend class="wpacu-alt-assets__paused-legend"><span><?php esc_html_e('Paused', 'wp-asset-clean-up'); ?></span></legend>
    <div class="wpacu-alt-assets__header">
        <div class="wpacu-alt-assets__intro">
            <span class="dashicons dashicons-editor-code wpacu-alt-assets__intro-icon" aria-hidden="true"></span>
            <div>
                <div class="wpacu-alt-assets__intro-heading">
                    <h2><?php esc_html_e('Keep the code you need. Load a lighter asset.', 'wp-asset-clean-up'); ?></h2>
                    <span class="wpacu-alt-assets__advanced-badge"><?php esc_html_e('Advanced', 'wp-asset-clean-up'); ?></span>
                    <button type="button" class="button-link wpacu-alt-assets__guide-link" data-wpacu-alt-guide-open aria-haspopup="dialog" aria-controls="wpacu-alt-assets-guide" aria-expanded="false">
                        <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                        <?php esc_html_e('How it works', 'wp-asset-clean-up'); ?>
                    </button>
                </div>
                <p>
                    <?php esc_html_e('For CSS or JavaScript that still needs to load, supply a smaller replacement with the code required by your selected pages.', 'wp-asset-clean-up'); ?>
                </p>
                <p class="wpacu-alt-assets__intro-requirement"><?php esc_html_e('Requires CSS or JavaScript knowledge. You choose which code to remove.', 'wp-asset-clean-up'); ?></p>
                <p class="wpacu-alt-assets__intro-note"><?php esc_html_e('The original source stays unchanged. Its WordPress handle, dependencies and associated inline data are preserved.', 'wp-asset-clean-up'); ?></p>
            </div>
        </div>

        <?php if ( ! $isFormView && ! $isUpgradePage ) { ?>
            <a class="button button-primary wpacu-alt-assets__add" href="<?php echo esc_url($addUrl); ?>">
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                <?php esc_html_e('Add Alternative Asset', 'wp-asset-clean-up'); ?>
            </a>
        <?php } ?>
    </div>

    <?php if ($altData['notice'] !== '') { ?>
        <div class="notice notice-<?php echo esc_attr($altData['notice_type']); ?> is-dismissible inline">
            <p><?php echo esc_html($altData['notice']); ?></p>
        </div>
    <?php } ?>

    <?php if ( ! empty($altData['errors']) ) { ?>
        <div class="notice notice-error inline">
            <p><strong><?php esc_html_e('The rule was not saved:', 'wp-asset-clean-up'); ?></strong></p>
            <ul class="wpacu-alt-assets__errors">
                <?php foreach ($altData['errors'] as $errorMessage) { ?>
                    <li><?php echo esc_html($errorMessage); ?></li>
                <?php } ?>
            </ul>
        </div>
    <?php } ?>

    <div class="wpacu-alt-assets__type-tabs" role="tablist" aria-label="<?php esc_attr_e('Alternative asset type', 'wp-asset-clean-up'); ?>">
        <a class="wpacu-alt-assets__type-tab <?php echo ! $isJs ? 'is-active' : ''; ?>" href="<?php echo esc_url($cssUrl); ?>">
            <?php esc_html_e('CSS Assets', 'wp-asset-clean-up'); ?>
            <span><?php echo (int)$cssCount; ?></span>
        </a>
        <a class="wpacu-alt-assets__type-tab <?php echo $isJs ? 'is-active' : ''; ?>" href="<?php echo esc_url($jsUrl); ?>">
            <?php esc_html_e('JavaScript Assets', 'wp-asset-clean-up'); ?>
            <?php if (AlternativeAssets::supportsAssetType('js')) { ?>
            <span><?php echo (int)$jsCount; ?></span>
            <?php } else { ?>
            <span class="wpacu-alt-assets__pro-badge"><?php esc_html_e('Pro', 'wp-asset-clean-up'); ?></span>
            <?php } ?>
        </a>
    </div>

    <div class="wpacu-alt-assets__warning" <?php if ( ! ($isJs || $formType === 'js') ) { ?>hidden<?php } ?>>
        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
        <div>
            <strong><?php esc_html_e('Advanced JavaScript replacement', 'wp-asset-clean-up'); ?></strong>
            <p><?php esc_html_e('Test clicks, delayed events, AJAX flows, responsive behavior and logged-in/logged-out states. Turning a rule off immediately restores the original full script.', 'wp-asset-clean-up'); ?></p>
        </div>
    </div>

    <?php if ($isUpgradePage) { ?>
        <section class="wpacu-alt-assets__panel wpacu-alt-assets__pro-notice">
            <div class="wpacu-alt-assets__pro-content">
                <span class="wpacu-alt-assets__pro-icon dashicons dashicons-lock" aria-hidden="true"></span>
                <h3><?php esc_html_e('Manage JavaScript alternatives with Asset CleanUp Pro', 'wp-asset-clean-up'); ?></h3>
                <p class="wpacu-alt-assets__pro-description"><?php esc_html_e('Create, edit and choose where your JavaScript alternatives load directly from the Dashboard. CSS alternatives are included in Lite.', 'wp-asset-clean-up'); ?></p>
                <div class="wpacu-alt-assets__code-option">
                    <span class="wpacu-alt-assets__code-icon dashicons dashicons-editor-code" aria-hidden="true"></span>
                    <div>
                        <h4><?php esc_html_e('Custom code in Lite', 'wp-asset-clean-up'); ?></h4>
                        <p><?php esc_html_e('You have the option to load alternative JavaScript files in Lite using custom PHP code. This requires WordPress coding knowledge and a replacement file you prepare and test.', 'wp-asset-clean-up'); ?></p>
                        <a class="wpacu-alt-assets__code-guide" href="https://www.assetcleanup.com/docs/how-to-load-an-alternative-lighter-version-of-a-file/" target="_blank" rel="noopener noreferrer">
                            <span class="dashicons dashicons-book-alt" aria-hidden="true"></span>
                            <span><?php esc_html_e('Read the custom PHP code guide', 'wp-asset-clean-up'); ?></span>
                            <span class="screen-reader-text"> <?php esc_html_e('(opens in a new tab)', 'wp-asset-clean-up'); ?></span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    <?php } elseif ($isFormView) { ?>
        <section class="wpacu-alt-assets__panel wpacu-alt-assets__form-panel">
            <div class="wpacu-alt-assets__panel-heading">
                <div>
                    <h3><?php echo $editRule ? esc_html__('Edit Alternative Asset', 'wp-asset-clean-up') : esc_html__('Add Alternative Asset', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('The source code is stored persistently in the database. The generated file is disposable and recreated automatically if the Asset CleanUp cache is cleared.', 'wp-asset-clean-up'); ?></p>
                </div>
                <a class="button" href="<?php echo esc_url(add_query_arg('asset_type', $formType, $baseUrl)); ?>">
                    <span aria-hidden="true">←</span> <?php esc_html_e('Back to Alternative Assets', 'wp-asset-clean-up'); ?>
                </a>
            </div>

            <form method="post" class="wpacu-alt-assets__form<?php echo ! $formEnabled ? ' is-inactive' : ''; ?>" autocomplete="off">
                <?php wp_nonce_field(AlternativeAssetsAdmin::MANAGE_NONCE_ACTION); ?>
                <input type="hidden" name="wpacu_alt_assets_action" value="save" />
                <input type="hidden" name="rule_id" value="<?php echo (int)$formRuleId; ?>" />

                <div class="wpacu-alt-assets__field wpacu-alt-assets__active-field">
                    <label for="wpacu-alt-assets-enabled"><?php esc_html_e('Enable this alternative:', 'wp-asset-clean-up'); ?></label>
                    <label class="wpacu-alt-assets__switch wpacu-alt-assets__switch--form wpacu-alt-assets__switch--with-text">
                        <input id="wpacu-alt-assets-enabled" type="checkbox" name="enabled" value="1" aria-describedby="wpacu-alt-assets-enabled-help" <?php checked($formEnabled); ?> />
                        <span class="wpacu-alt-assets__slider" aria-hidden="true"></span>
                    </label>
                    <small id="wpacu-alt-assets-enabled-help"><?php esc_html_e('Turn off to load the original asset without deleting this rule. Save the form to apply this change.', 'wp-asset-clean-up'); ?></small>
                </div>

                <div class="wpacu-alt-assets__form-content">
                <?php if ($editRule) {
                    $originalKey = $formType . '|' . $formHandle;
                    $original    = isset($altData['registry'][$originalKey]) ? $altData['registry'][$originalKey] : array();
                    $originalUrl = ! empty($original['src']) ? $original['src'] : '';
                ?>
                <input id="wpacu-alt-asset-type" name="asset_type" type="hidden" value="<?php echo esc_attr($formType); ?>" />
                <input id="wpacu-alt-asset-handle" name="handle" type="hidden" value="<?php echo esc_attr($formHandle); ?>" />
                <div class="wpacu-alt-assets__form-grid">
                    <div class="wpacu-alt-assets__field">
                        <span class="wpacu-alt-assets__field-label"><?php esc_html_e('Asset type', 'wp-asset-clean-up'); ?></span>
                        <p><?php echo $formType === 'js' ? esc_html__('JavaScript asset', 'wp-asset-clean-up') : esc_html__('CSS asset', 'wp-asset-clean-up'); ?></p>
                    </div>
                    <div class="wpacu-alt-assets__field">
                        <span class="wpacu-alt-assets__field-label"><?php esc_html_e('Original handle', 'wp-asset-clean-up'); ?></span>
                        <div id="wpacu-alt-assets-selected-info" class="wpacu-alt-assets__selected-info">
                            <strong><?php echo esc_html($formHandle); ?></strong>
                            <?php if ($originalUrl !== '') { ?>
                                <a class="wpacu-alt-assets__file-link" href="<?php echo esc_url($originalUrl, array('http', 'https')); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Open original file in a new tab', 'wp-asset-clean-up'); ?>" title="<?php esc_attr_e('Open original file in a new tab', 'wp-asset-clean-up'); ?>"><span class="dashicons dashicons-external" aria-hidden="true"></span></a>
                                <code><?php echo esc_html($originalUrl); ?></code>
                            <?php } else { ?>
                                <p class="description"><?php esc_html_e('No original file URL is available for this handle.', 'wp-asset-clean-up'); ?></p>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <?php } else { ?>
                <div class="wpacu-alt-assets__form-grid">
                    <div class="wpacu-alt-assets__field">
                        <label for="wpacu-alt-asset-type"><?php esc_html_e('Asset type', 'wp-asset-clean-up'); ?></label>
                        <select id="wpacu-alt-asset-type" name="asset_type">
                            <option value="css" <?php selected($formType, 'css'); ?>><?php esc_html_e('CSS asset', 'wp-asset-clean-up'); ?></option>
                            <?php if (AlternativeAssets::supportsAssetType('js')) { ?><option value="js" <?php selected($formType, 'js'); ?>><?php esc_html_e('JavaScript asset', 'wp-asset-clean-up'); ?></option><?php } ?>
                        </select>
                    </div>

                    <div class="wpacu-alt-assets__field wpacu-alt-assets__field--handle">
                        <label for="wpacu-alt-asset-handle"><?php esc_html_e('Search or enter the original handle', 'wp-asset-clean-up'); ?></label>
                        <input id="wpacu-alt-asset-handle"
                               name="handle"
                               type="text"
                               value="<?php echo esc_attr($formHandle); ?>"
                               placeholder="<?php echo esc_attr($formType === 'js' ? 'elementor-frontend' : 'astra-theme-css'); ?>"
                               required />
                        <div id="wpacu-alt-assets-search-results" class="wpacu-alt-assets__search-results" hidden></div>
                        <p class="description">
                            <?php esc_html_e('The handle can be used even if Asset CleanUp has not detected it yet.', 'wp-asset-clean-up'); ?>
                            <button type="button" class="button-link wpacu-alt-assets__find-more" id="wpacu-alt-assets-find-more" aria-expanded="false" aria-controls="wpacu-alt-assets-fetch-box"><?php esc_html_e('Find more assets', 'wp-asset-clean-up'); ?></button>
                        </p>
                    </div>
                </div>

                <div id="wpacu-alt-assets-selected-info" class="wpacu-alt-assets__selected-info" hidden></div>
                <?php } ?>

                <div class="wpacu-alt-assets__fetch-box" id="wpacu-alt-assets-fetch-box" hidden>
                    <fieldset class="wpacu-alt-assets__suggested-pages">
                        <legend><?php esc_html_e('Suggested pages', 'wp-asset-clean-up'); ?></legend>
                        <?php foreach ($altData['suggested_pages'] as $suggestedPage) { ?>
                            <label><input type="checkbox" class="wpacu-alt-assets__scan-page" value="<?php echo esc_attr($suggestedPage['url']); ?>" checked /> <span><?php echo esc_html($suggestedPage['label']); ?><small><?php echo esc_html($suggestedPage['url']); ?></small></span></label>
                        <?php } ?>
                    </fieldset>
                    <label for="wpacu-alt-assets-custom-urls" class="wpacu-alt-assets__field-label"><?php esc_html_e('Custom URLs', 'wp-asset-clean-up'); ?></label>
                    <textarea id="wpacu-alt-assets-custom-urls" rows="3" class="large-text" placeholder="<?php echo esc_attr(home_url('/example-page/')); ?>" aria-describedby="wpacu-alt-assets-custom-urls-help"></textarea>
                    <p class="description" id="wpacu-alt-assets-custom-urls-help"><?php esc_html_e('One full page URL per line. Scan up to 20 pages at a time.', 'wp-asset-clean-up'); ?></p>
                    <div class="wpacu-alt-assets__fetch-controls">
                        <button id="wpacu-alt-assets-fetch-button" type="button" class="button">
                            <?php esc_html_e('Scan selected pages', 'wp-asset-clean-up'); ?>
                        </button>
                        <span class="spinner" id="wpacu-alt-assets-fetch-spinner"></span>
                        <button id="wpacu-alt-assets-fetch-stop" type="button" class="button-link" hidden><?php esc_html_e('Stop scanning', 'wp-asset-clean-up'); ?></button>
                    </div>
                    <p id="wpacu-alt-assets-fetch-message" class="description" aria-live="polite">
                        <?php esc_html_e('Scanning starts only when you click the button. Detected assets become available in the handle search; your replacement code and rules stay unchanged.', 'wp-asset-clean-up'); ?>
                    </p>
                    <ol id="wpacu-alt-assets-fetch-results" class="wpacu-alt-assets__fetch-results"></ol>
                </div>

                <div class="wpacu-alt-assets__field">
                    <div class="wpacu-alt-assets__source-heading">
                        <label for="wpacu-alt-assets-source">
                            <?php echo $formType === 'js' ? esc_html__('Alternative JavaScript', 'wp-asset-clean-up') : esc_html__('Alternative CSS', 'wp-asset-clean-up'); ?>
                        </label>
                        <div class="wpacu-alt-assets__source-actions">
                            <button id="wpacu-alt-assets-beautify" type="button" class="button button-small" disabled title="<?php esc_attr_e('Format the code for easier editing. Save your alternative to keep the changes.', 'wp-asset-clean-up'); ?>">
                                <?php echo $formType === 'js' ? esc_html__('Beautify JavaScript', 'wp-asset-clean-up') : esc_html__('Beautify CSS', 'wp-asset-clean-up'); ?>
                            </button>
                            <button id="wpacu-alt-assets-undo-beautify" type="button" class="button button-small" hidden disabled title="<?php esc_attr_e('Restore the code before formatting. Available when the code matches the last Beautify result.', 'wp-asset-clean-up'); ?>">
                                <?php esc_html_e('Undo Beautify', 'wp-asset-clean-up'); ?>
                            </button>
                            <button id="wpacu-alt-assets-load-source" type="button" class="button button-small" hidden disabled title="<?php esc_attr_e('Choose a detected handle, or use Find more assets to locate its original file.', 'wp-asset-clean-up'); ?>">
                                <?php echo trim($formSource) === '' ? esc_html__('Copy original code', 'wp-asset-clean-up') : esc_html__('Append original code', 'wp-asset-clean-up'); ?>
                            </button>
                            <button id="wpacu-alt-assets-undo-append" type="button" class="button button-small" hidden disabled title="<?php esc_attr_e('Restore the code before appending the original source. Available when the code matches the result of that append.', 'wp-asset-clean-up'); ?>">
                                <?php esc_html_e('Undo Append', 'wp-asset-clean-up'); ?>
                            </button>
                            <span id="wpacu-alt-assets-source-spinner" class="spinner" aria-hidden="true"></span>
                        </div>
                    </div>
                    <div id="wpacu-alt-assets-source-message" role="status" aria-live="polite" hidden>
                        <span class="dashicons dashicons-info" aria-hidden="true"></span>
                        <span class="wpacu-alt-assets__source-message-text"></span>
                    </div>
                    <textarea id="wpacu-alt-assets-source"
                              name="source"
                              rows="20"
                              class="large-text code"
                              spellcheck="false"
                              required><?php echo esc_textarea($formSource); ?></textarea>
                    <p class="description wpacu-alt-assets__source-help" data-css-text="<?php echo esc_attr__('Paste CSS declarations only, without a <style> tag. Relative url() and @import references are rebased against the original stylesheet URL. On right-to-left (RTL) pages, stylesheets with separate RTL versions keep their original files.', 'wp-asset-clean-up'); ?>" data-js-text="<?php echo esc_attr__('Paste JavaScript code only, without a <script> tag. The existing WordPress handle, dependencies and inline data remain associated with the replacement.', 'wp-asset-clean-up'); ?>">
                        <?php echo $formType === 'js'
                            ? esc_html__('Paste JavaScript code only, without a <script> tag. The existing WordPress handle, dependencies and inline data remain associated with the replacement.', 'wp-asset-clean-up')
                            : esc_html__('Paste CSS declarations only, without a <style> tag. Relative url() and @import references are rebased against the original stylesheet URL. On right-to-left (RTL) pages, stylesheets with separate RTL versions keep their original files.', 'wp-asset-clean-up'); ?>
                    </p>
                    <p class="description"><?php esc_html_e('Your saved code stays unchanged. Choose how the generated file is served using Output below.', 'wp-asset-clean-up'); ?></p>
                </div>

                <div class="wpacu-alt-assets__settings-grid">
                    <fieldset class="wpacu-alt-assets__field wpacu-alt-assets__scope-field" disabled aria-busy="true">
                        <legend><?php esc_html_e('Where should this alternative load?', 'wp-asset-clean-up'); ?></legend>
                        <p class="description wpacu-alt-assets__targets-description"><?php esc_html_e('Use this alternative when any selected condition matches.', 'wp-asset-clean-up'); ?></p>
                        <div class="wpacu-alt-assets__targets">
                            <div class="wpacu-alt-assets__scope-loading" role="status">
                                <span class="spinner is-active" aria-hidden="true"></span>
                                <span class="screen-reader-text"><?php esc_html_e('Loading conditions…', 'wp-asset-clean-up'); ?></span>
                            </div>
                            <?php foreach ($altData['target_groups'] as $groupLabel => $groupTargets) { ?>
                            <section class="wpacu-alt-assets__target-group <?php echo in_array('everywhere', $groupTargets, true) ? 'wpacu-alt-assets__target-group--site' : ''; ?>">
                                <header class="wpacu-alt-assets__target-group-header"><h3><?php echo esc_html($groupLabel); ?></h3><span class="wpacu-alt-assets__scope-count" aria-live="polite"><?php esc_html_e('No conditions selected', 'wp-asset-clean-up'); ?></span></header>
                                <div class="wpacu-alt-assets__target-group-body">
                            <?php foreach ($groupTargets as $targetValue) {
                                $targetLabel     = $altData['target_options'][$targetValue];
                                $targetSelected  = array_key_exists($targetValue, $formTargets);
                                $detectedChoices = isset($altData['target_choices'][$targetValue]) ? $altData['target_choices'][$targetValue] : null;
                                if ($detectedChoices === array() && ! $targetSelected) { continue; }
                                $requiresValue = in_array($targetValue, array('post_type', 'post_ids', 'taxonomy', 'taxonomy_term', 'cpt_archive', 'regex', 'post_tax_term', 'archive_types', 'user_role', 'author'), true);
                                $hasValue      = $requiresValue || $targetValue === 'author';
                                $targetInputId = 'wpacu-alt-assets-target-' . $targetValue;
                                ?>
                                <div class="wpacu-alt-assets__target" data-target="<?php echo esc_attr($targetValue); ?>">
                                    <label><input type="checkbox" name="targets[]" value="<?php echo esc_attr($targetValue); ?>" <?php checked($targetSelected); ?> /> <span><?php echo esc_html($targetLabel); ?></span></label>
                                    <?php if ($hasValue) { ?>
                                        <div class="wpacu-alt-assets__target-details" <?php if ( ! $targetSelected ) { echo 'hidden'; } ?>>
                                            <?php if (in_array($targetValue, array('taxonomy', 'archive_types'), true) && is_array($detectedChoices)) {
                                                $selectedChoices = $targetSelected ? array_filter(explode(',', $formTargets[$targetValue])) : array();
                                                $inlineChoices   = $detectedChoices;
                                                foreach ($selectedChoices as $selectedChoice) {
                                                    if (!isset($inlineChoices[$selectedChoice])) {
                                                        $inlineChoices[$selectedChoice] = sprintf(__('%s (not currently available)', 'wp-asset-clean-up'), $selectedChoice);
                                                    }
                                                }
                                            ?>
                                            <label class="screen-reader-text" for="<?php echo esc_attr($targetInputId); ?>"><?php echo esc_html($targetLabel); ?></label>
                                            <select id="<?php echo esc_attr($targetInputId); ?>" class="wpacu-alt-assets__condition-select" name="target_values[<?php echo esc_attr($targetValue); ?>][]" multiple data-placeholder="<?php echo $targetValue === 'taxonomy' ? esc_attr__('Choose taxonomies…', 'wp-asset-clean-up') : esc_attr__('Choose archive and listing page types…', 'wp-asset-clean-up'); ?>" aria-describedby="<?php echo esc_attr($targetInputId . '-help'); ?>" <?php disabled( ! $targetSelected ); ?>>
                                                <?php foreach ($inlineChoices as $choiceValue => $choiceLabel) { ?>
                                                    <option value="<?php echo esc_attr($choiceValue); ?>" <?php selected(in_array((string)$choiceValue, $selectedChoices, true)); ?>><?php echo esc_html($choiceLabel); ?></option>
                                                <?php } ?>
                                            </select>
                                            <?php } elseif (in_array($targetValue, array('post_ids', 'taxonomy_term', 'post_tax_term', 'author', 'regex'), true) || is_array($detectedChoices)) {
                                                $isUriPicker       = $targetValue === 'regex';
                                                $isChoicePicker    = is_array($detectedChoices);
                                                $isTermPicker      = in_array($targetValue, array('taxonomy_term', 'post_tax_term'), true);
                                                $pickerKinds       = array('post_ids' => 'posts', 'taxonomy_term' => 'terms', 'post_tax_term' => 'assigned-terms', 'author' => 'authors', 'post_type' => 'post-types', 'taxonomy' => 'taxonomies', 'archive_types' => 'archives', 'user_role' => 'roles', 'regex' => 'uris');
                                                $pickerKind        = $pickerKinds[$targetValue];
                                                $pickerValuePrefix = $isTermPicker ? 'tt:' : '';
                                                $selectedValue     = $targetSelected ? $formTargets[$targetValue] : '';
                                                $selectedPostIds   = $isUriPicker ? array() : ($isChoicePicker ? array_filter(explode(',', $selectedValue)) : ($isTermPicker
                                                    ? AlternativeAssets::getTermTaxonomyIds($selectedValue)
                                                    : array_values(array_unique(array_filter(array_map('absint', preg_split('/[^0-9]+/', $selectedValue)))))));
                                                $selectedPosts = ($isChoicePicker || $isUriPicker) ? array() : ($isTermPicker
                                                    ? \WpAssetCleanUp\Admin\AlternativeAssetsChoices::getSelectedTaxonomyTermOptions($selectedPostIds)
                                                    : ($targetValue === 'author'
                                                        ? \WpAssetCleanUp\Admin\AlternativeAssetsChoices::getSelectedAuthorArchiveOptions($selectedPostIds)
                                                        : \WpAssetCleanUp\Admin\AlternativeAssetsChoices::getSelectedSingularPostOptions($selectedPostIds)));
                                                if ($isChoicePicker) {
                                                    foreach ($detectedChoices as $choiceValue => $choiceLabel) {
                                                        $selectedPosts[$choiceValue] = array('option_label' => $choiceLabel);
                                                    }
                                                    foreach ($selectedPostIds as $selectedChoice) {
                                                        if (!isset($selectedPosts[$selectedChoice])) {
                                                            $selectedPosts[$selectedChoice] = array('option_label' => sprintf(__('%s (not currently available)', 'wp-asset-clean-up'), $selectedChoice));
                                                        }
                                                    }
                                                }
                                                include WPACU_PLUGIN_DIR . '/templates/_admin-page-assets-manager-alternative-assets-picker.php';
                                            } else { ?>
                                            <label class="screen-reader-text" for="<?php echo esc_attr($targetInputId); ?>"><?php echo esc_html($targetLabel); ?></label>
                                            <textarea id="<?php echo esc_attr($targetInputId); ?>" rows="4" name="target_values[<?php echo esc_attr($targetValue); ?>]" aria-describedby="<?php echo esc_attr($targetInputId . '-help'); ?>" <?php disabled( ! $targetSelected ); ?> <?php if ($targetSelected && $requiresValue) { echo 'required'; } ?>><?php echo esc_textarea($targetSelected ? $formTargets[$targetValue] : ''); ?></textarea>
                                            <?php } ?>
                                            <p id="<?php echo esc_attr($targetInputId . '-help'); ?>" class="description wpacu-alt-assets__target-help"></p>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                                <?php if (in_array('everywhere', $groupTargets, true)) { ?>
                                    <p class="wpacu-alt-assets__scope-note"><?php esc_html_e('Exclusive rule: “On all pages” supersedes and clears narrower conditions for this alternative asset.', 'wp-asset-clean-up'); ?></p>
                                <?php } ?>
                                </div>
                            </section>
                            <?php } ?>
                        </div>
                    </fieldset>

                    <div class="wpacu-alt-assets__field wpacu-alt-assets__priority-field">
                        <label for="wpacu-alt-assets-priority"><?php esc_html_e('Priority', 'wp-asset-clean-up'); ?></label>
                        <input id="wpacu-alt-assets-priority" name="priority" type="number" min="-9999" max="9999" value="<?php echo (int)$formPriority; ?>" aria-describedby="wpacu-alt-assets-priority-help" />
                        <p class="description" id="wpacu-alt-assets-priority-help"><?php esc_html_e('When multiple matching rules target the same handle, the highest priority wins. More specific and more recently updated rules break ties.', 'wp-asset-clean-up'); ?></p>
                    </div>

                </div>
                </div>

                <?php include __DIR__ . '/_admin-page-assets-manager-alternative-assets-output.php'; ?>

                <div class="wpacu-alt-assets__form-actions">
                    <?php submit_button($editRule ? __('Update Alternative Asset', 'wp-asset-clean-up') : __('Add Alternative Asset', 'wp-asset-clean-up'), 'primary', 'submit', false); ?>
                    <a class="button" href="<?php echo esc_url(add_query_arg('asset_type', $formType, $baseUrl)); ?>"><?php esc_html_e('Cancel', 'wp-asset-clean-up'); ?></a>
                    <span class="spinner wpacu-alt-assets__action-spinner" role="status" aria-live="polite" hidden><span class="screen-reader-text"></span></span>
                </div>
            </form>
        </section>
    <?php } else { ?>
        <section class="wpacu-alt-assets__panel">
            <?php if (count($altData['rules']) >= 2) { ?>
            <div class="wpacu-alt-assets__toolbar">
                <input id="wpacu-alt-assets-rule-filter" type="search" placeholder="<?php esc_attr_e('Search existing entries by handle, URL or location...', 'wp-asset-clean-up'); ?>" />
                <span><?php esc_html_e('Toggle OFF keeps the rule and source code, but loads the original full asset.', 'wp-asset-clean-up'); ?></span>
            </div>
            <?php } ?>

            <div id="wpacu-alt-assets-status-message" class="wpacu-alt-assets__status-message" hidden aria-live="polite"></div>

            <?php if (empty($altData['rules'])) { ?>
                <div class="wpacu-alt-assets__empty">
                    <span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
                    <h3><?php echo $isJs ? esc_html__('No alternative JavaScript assets yet', 'wp-asset-clean-up') : esc_html__('No alternative CSS assets yet', 'wp-asset-clean-up'); ?></h3>
                    <p><?php echo $isJs ? esc_html__('Create a lighter version of an existing JavaScript asset and choose where it should load.', 'wp-asset-clean-up') : esc_html__('Create a lighter version of an existing CSS asset and choose where it should load.', 'wp-asset-clean-up'); ?></p>
                    <a class="button button-primary" href="<?php echo esc_url($addUrl); ?>"><?php echo $isJs ? esc_html__('Add Alternative JavaScript', 'wp-asset-clean-up') : esc_html__('Add Alternative CSS', 'wp-asset-clean-up'); ?></a>
                </div>
            <?php } else { ?>
                <div class="wpacu-alt-assets__rules" role="list">
                    <?php foreach ($altData['rules'] as $rule) {
                        $registryKey     = $rule['type'] . '|' . $rule['handle'];
                        $registryEntry   = isset($altData['registry'][$registryKey]) ? $altData['registry'][$registryKey] : false;
                        $originalSrc     = $registryEntry && ! empty($registryEntry['src']) ? $registryEntry['src'] : '';
                        $ownerName       = $registryEntry && ! empty($registryEntry['owner_name']) ? $registryEntry['owner_name'] : __('Unknown source', 'wp-asset-clean-up');
                        $originalSize    = $registryEntry && ! empty($registryEntry['size']) ? size_format((int)$registryEntry['size'], 1) : '';
                        $alternativeFile = ($originalSrc !== '' || $rule['type'] === 'js')
                            ? AlternativeAssets::getGeneratedAssetData($rule, $originalSrc, true) : false;
                        $alternativeBytes = $alternativeFile && isset($alternativeFile['size']) ? (int)$alternativeFile['size'] : 0;
                        $alternativeSize  = $alternativeBytes > 0 ? size_format($alternativeBytes, 1) : __('Size unavailable', 'wp-asset-clean-up');
                        $alternativeUrl   = $alternativeFile ? esc_url($alternativeFile['url'], array('http', 'https')) : '';
                        $editUrl          = add_query_arg(array('asset_type' => $rule['type'], 'edit_rule' => (int)$rule['id']), $baseUrl);
                        $searchText       = strtolower($rule['handle'] . ' ' . $originalSrc . ' ' . AlternativeAssets::getTargetLabel($rule));
                        ?>
                        <article class="wpacu-alt-assets__rule <?php echo empty($rule['enabled']) ? 'is-inactive' : ''; ?>"
                                 data-rule-id="<?php echo (int)$rule['id']; ?>"
                                 data-search="<?php echo esc_attr($searchText); ?>"
                                 role="listitem">
                            <div class="wpacu-alt-assets__original">
                                <div class="wpacu-alt-assets__asset-title">
                                    <span class="wpacu-alt-assets__badge"><?php echo esc_html(strtoupper($rule['type'])); ?></span>
                                    <code><?php echo esc_html($rule['handle']); ?></code>
                                </div>
                                <div class="wpacu-alt-assets__url" title="<?php echo esc_attr($originalSrc); ?>">
                                    <?php echo $originalSrc !== '' ? esc_html($originalSrc) : esc_html__('No original file URL is available for this handle.', 'wp-asset-clean-up'); ?>
                                </div>
                                <div class="wpacu-alt-assets__meta">
                                    <?php echo esc_html($ownerName); ?>
                                    <?php if ($originalSize !== '') { ?> · <?php printf(esc_html__('Original %s', 'wp-asset-clean-up'), esc_html($originalSize)); ?><?php } ?>
                                    <?php if (esc_url($originalSrc, array('http', 'https')) !== '') { ?>
                                        <a class="wpacu-alt-assets__file-link" href="<?php echo esc_url($originalSrc, array('http', 'https')); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Open original file in a new tab', 'wp-asset-clean-up'); ?>" title="<?php esc_attr_e('Open original file in a new tab', 'wp-asset-clean-up'); ?>"><span class="dashicons dashicons-external" aria-hidden="true"></span></a>
                                    <?php } ?>
                                </div>
                            </div>

                            <div class="wpacu-alt-assets__alternative">
                                <span class="wpacu-alt-assets__arrow" aria-hidden="true">→</span>
                                <div>
                                    <strong><?php echo $rule['type'] === 'js' ? esc_html__('Generated alternative JavaScript', 'wp-asset-clean-up') : esc_html__('Generated alternative CSS', 'wp-asset-clean-up'); ?></strong>
                                    <div class="wpacu-alt-assets__meta">
                                        <?php echo esc_html($alternativeSize); ?>
                                        <?php if ($alternativeFile && ! empty($alternativeFile['minified'])) { ?> · <?php esc_html_e('Minified', 'wp-asset-clean-up'); ?><?php } ?>
                                        <?php if ($alternativeUrl !== '') { ?>
                                            <a class="wpacu-alt-assets__file-link" href="<?php echo $alternativeUrl; ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Open alternative file in a new tab', 'wp-asset-clean-up'); ?>" title="<?php esc_attr_e('Open alternative file in a new tab', 'wp-asset-clean-up'); ?>"><span class="dashicons dashicons-external" aria-hidden="true"></span></a>
                                        <?php } ?>
                                    </div>
                                    <?php
                                    $comparison = AlternativeAssetsAdmin::getSourceSizeComparison(
                                        $registryEntry && isset($registryEntry['size']) ? $registryEntry['size'] : 0,
                                        $alternativeBytes
                                    );
                                    include __DIR__ . '/_admin-page-assets-manager-alternative-assets-savings.php';
                                    ?>
                                    <div class="wpacu-alt-assets__inactive-copy">
                                        <?php esc_html_e('Original full asset currently loads', 'wp-asset-clean-up'); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="wpacu-alt-assets__scope">
                                <strong><?php echo esc_html(AlternativeAssets::getTargetLabel($rule)); ?></strong>
                                <span><?php printf(esc_html__('Priority %d', 'wp-asset-clean-up'), (int)$rule['priority']); ?></span>
                            </div>

                            <div class="wpacu-alt-assets__state">
                                <label class="wpacu-alt-assets__switch wpacu-alt-assets__switch--with-text" title="<?php esc_attr_e('Turn this replacement on or off', 'wp-asset-clean-up'); ?>">
                                    <input class="wpacu-alt-assets__toggle"
                                           type="checkbox"
                                           data-rule-id="<?php echo (int)$rule['id']; ?>"
                                           <?php checked(! empty($rule['enabled'])); ?>
                                           aria-label="<?php echo esc_attr(sprintf(__('Enable alternative for %s', 'wp-asset-clean-up'), $rule['handle'])); ?>" />
                                    <span class="wpacu-alt-assets__slider" aria-hidden="true"></span>
                                </label>
                            </div>

                            <div class="wpacu-alt-assets__actions">
                                <a class="wpacu-alt-assets__row-action wpacu-alt-assets__edit" href="<?php echo esc_url($editUrl); ?>" aria-label="<?php echo esc_attr(sprintf(__('Edit alternative for %s', 'wp-asset-clean-up'), $rule['handle'])); ?>">
                                    <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                                    <?php esc_html_e('Edit', 'wp-asset-clean-up'); ?>
                                </a>
                                <form method="post" class="wpacu-alt-assets__delete-form" data-confirmation="<?php echo esc_attr(sprintf(__('Permanently delete the alternative asset for "%s" and its stored source code? The original asset will not be deleted.', 'wp-asset-clean-up'), $rule['handle'])); ?>">
                                    <?php wp_nonce_field(AlternativeAssetsAdmin::MANAGE_NONCE_ACTION); ?>
                                    <input type="hidden" name="wpacu_alt_assets_action" value="delete" />
                                    <input type="hidden" name="rule_id" value="<?php echo (int)$rule['id']; ?>" />
                                    <button type="submit" class="wpacu-alt-assets__row-action wpacu-alt-assets__delete" aria-label="<?php echo esc_attr(sprintf(__('Delete alternative for %s', 'wp-asset-clean-up'), $rule['handle'])); ?>">
                                        <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                        <?php esc_html_e('Delete', 'wp-asset-clean-up'); ?>
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>

        <p class="wpacu-alt-assets__footer-note">
            <strong><?php esc_html_e('Persistent configuration:', 'wp-asset-clean-up'); ?></strong>
            <?php esc_html_e('rule settings and source code remain in the database. Generated files are disposable and recreated automatically after the Asset CleanUp cache is cleared.', 'wp-asset-clean-up'); ?>
        </p>
    <?php } ?>
    </fieldset>
    <?php include WPACU_PLUGIN_DIR . '/templates/_admin-page-assets-manager-alternative-assets-guide.php'; ?>
</div>
