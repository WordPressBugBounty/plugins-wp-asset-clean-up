<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\Menu;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\Regex;
use WpAssetCleanUp\Update;
use WpAssetCleanUp\OptimiseAssets\AlternativeAssets;
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;

/**
 * Admin controller for CSS/JS Manager -> Alternative Assets.
 *
 * PHP 5.6 compatible.
 */
class AlternativeAssetsAdmin
{
    const MANAGE_NONCE_ACTION = 'wpacu_alternative_assets_manage';
    const TOGGLE_NONCE_ACTION = 'wpacu_alternative_assets_toggle';
    const GLOBAL_STATUS_NONCE_ACTION = 'wpacu_alternative_assets_global_status';
    const FETCH_NONCE_ACTION  = 'wpacu_alternative_assets_fetch';
    const SOURCE_NONCE_ACTION = 'wpacu_alternative_assets_source';
    const SOURCE_MAX_BYTES = 2097152;

    /** @var AlternativeAssetsAdmin|null */
    private static $instance;

    /** @var bool */
    private $initialized = false;

    /** @var array */
    private $errors = array();

    /**
     * @return AlternativeAssetsAdmin
     */
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @return void
     */
    public function init()
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

        add_action('admin_init', array($this, 'handlePostActions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));

        add_action('wp_ajax_' . WPACU_PLUGIN_ID . '_alternative_assets_search_posts', array(AlternativeAssetsChoices::class, 'ajaxSearchSingularPosts'));
        add_action('wp_ajax_' . WPACU_PLUGIN_ID . '_alternative_assets_search_terms', array(AlternativeAssetsChoices::class, 'ajaxSearchTaxonomyTerms'));
        add_action('wp_ajax_' . WPACU_PLUGIN_ID . '_alternative_assets_search_authors', array(AlternativeAssetsChoices::class, 'ajaxSearchAuthorArchives'));
        add_action('wp_ajax_' . WPACU_PLUGIN_ID . '_alternative_assets_toggle', array($this, 'ajaxToggleRule'));
        add_action('wp_ajax_' . WPACU_PLUGIN_ID . '_alternative_assets_global_status', array($this, 'ajaxUpdateGlobalStatus'));
        add_action('wp_ajax_' . WPACU_PLUGIN_ID . '_alternative_assets_fetch', array($this, 'ajaxFetchAssets'));
        add_action('wp_ajax_' . WPACU_PLUGIN_ID . '_alternative_assets_source', array($this, 'ajaxLoadOriginalSource'));

    }

    /**
     * Save the independent master switch without changing individual rules.
     * @return void
     */
    public function ajaxUpdateGlobalStatus()
    {
        if (! Menu::userCanManageAlternativeAssets()) {
            wp_send_json_error(array('message' => __('You are not allowed to manage Alternative Assets.', 'wp-asset-clean-up')), 403);
        }

        $status = isset($_POST['status']) && is_string($_POST['status']) ? wp_unslash($_POST['status']) : '';
        $nonce = isset($_POST['nonce']) && is_string($_POST['nonce']) ? wp_unslash($_POST['nonce']) : '';
        if (! isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST'
            || ! in_array($status, array('on', 'off'), true)
            || ! wp_verify_nonce($nonce, self::GLOBAL_STATUS_NONCE_ACTION)) {
            wp_send_json_error(array('message' => __('The status could not be saved. Refresh the page and try again.', 'wp-asset-clean-up')), 400);
        }

        $settingsAdmin = new SettingsAdmin();
        $writeResult = $settingsAdmin->updateOption('alternative_assets_status', $status);
        // Read storage again: an update may fail, or a filter may reject it.
        $stored = (new \WpAssetCleanUp\Settings())->getAllStored(true);
        $savedStatus = isset($stored['alternative_assets_status']) && $stored['alternative_assets_status'] === 'off' ? 'off' : 'on';
        if ($savedStatus !== $status) {
            // Report only this switch's state, never the full settings or database errors.
            // This distinguishes a rejected write from a normalized/cached read mismatch.
            $rawSettings = get_option(WPACU_PLUGIN_ID . '_settings');
            $rawSettings = is_string($rawSettings) ? json_decode($rawSettings, true) : $rawSettings;
            $rawStatus = is_array($rawSettings) && isset($rawSettings['alternative_assets_status'])
                ? $rawSettings['alternative_assets_status'] : 'missing';
            if (! in_array($rawStatus, array('on', 'off', 'missing'), true)) {
                $rawStatus = 'invalid';
            }
            wp_send_json_error(array(
                'message' => __('The status could not be saved. Please try again.', 'wp-asset-clean-up'),
                'code' => 'alternative_assets_status_mismatch',
                'diagnostic' => array(
                    'requested_status' => $status,
                    'stored_status' => $rawStatus,
                    'read_status' => $savedStatus,
                    'setting_registered' => in_array('alternative_assets_status', (new \WpAssetCleanUp\Settings())->settingsKeys, true),
                    'write_result' => $writeResult
                )
            ), 500);
        }

        wp_send_json_success(array(
            'status' => $status,
            'message' => $status === 'off'
                ? __('Paused. Original assets load; your rules stay saved.', 'wp-asset-clean-up')
                : __('Active. Enabled rules replace matching assets.', 'wp-asset-clean-up')
        ));
    }

    /** @return bool */
    private function isAlternativeAssetsPage()
    {
        return Misc::getVar('get', 'page') === WPACU_PLUGIN_ID . '_assets_manager'
            && Misc::getVar('get', 'wpacu_sub_page', 'manage_css_js') === 'manage_alternative_assets';
    }

    /** Only the Lite JavaScript tab is public to plugin managers, not its editor. */
    private function isJavaScriptUpgradePage()
    {
        return ! AlternativeAssets::supportsAssetType('js')
            && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET'
            && Misc::getVar('get', 'asset_type', 'css') === 'js'
            && absint(Misc::getVar('get', 'edit_rule', 0)) === 0
            && Misc::getVar('get', 'wpacu_alt_view', 'list') === 'list';
    }

    /**
     * @return void
     */
    public function handlePostActions()
    {
        if ( ! $this->isAlternativeAssetsPage() ) {
            return;
        }

        if ( ! Menu::userCanManageAlternativeAssets() ) {
            // Allow the read-only permission notice, never a mutation or editor data.
            if (Menu::userCanAccessPlugin() && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
                return;
            }
            wp_die(esc_html__('You are not allowed to manage Alternative Assets.', 'wp-asset-clean-up'), '', array('response' => 403));
        }

        // Reject unsupported editor URLs before the admin header sends output.
        $requestedRule = AlternativeAssets::getRule(absint(Misc::getVar('get', 'edit_rule', 0)));
        $requestedType = $requestedRule ? $requestedRule['type'] : AlternativeAssets::sanitizeAssetType(Misc::getVar('get', 'asset_type', 'css'));
        if ($requestedType !== '' && ! AlternativeAssets::supportsAssetType($requestedType) && ! $this->isJavaScriptUpgradePage()) {
            wp_die(esc_html__('JavaScript alternatives require Asset CleanUp Pro.', 'wp-asset-clean-up'), '', array('response' => 403));
        }

        if ( ! isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            return;
        }

        $action = sanitize_key(Misc::getVar('post', 'wpacu_alt_assets_action', ''));

        if ( ! in_array($action, array('save', 'delete'), true) ) {
            return;
        }

        check_admin_referer(self::MANAGE_NONCE_ACTION);

        if ($action === 'delete') {
            $ruleId    = absint(Misc::getVar('post', 'rule_id', 0));
            $rule = AlternativeAssets::getRule($ruleId);
            if ($rule && ! AlternativeAssets::supportsAssetType($rule['type'])) {
                wp_die(esc_html__('JavaScript alternatives require Asset CleanUp Pro.', 'wp-asset-clean-up'), '', array('response' => 403));
            }
            $wasDeleted = $ruleId > 0 && AlternativeAssets::deleteRule($ruleId);
            $this->redirectAfterAction($wasDeleted ? 'deleted' : 'not_found');
        }

        $this->saveRuleFromRequest();
    }

    /**
     * @return void
     */
    private function saveRuleFromRequest()
    {
        $ruleId      = absint(Misc::getVar('post', 'rule_id', 0));
        $assetType   = AlternativeAssets::sanitizeAssetType(Misc::getVar('post', 'asset_type', 'css'));
        $handle      = AlternativeAssets::sanitizeHandle(Misc::getVar('post', 'handle', ''));
        $source      = Misc::getVar('post', 'source', '');
        $source      = is_string($source) ? wp_unslash($source) : '';
        $targets     = $this->getPostedTargets();
        $priority    = (int)Misc::getVar('post', 'priority', 10);
        $enabled     = (bool)Misc::getVar('post', 'enabled', false);

        if ($ruleId > 0) {
            $existingRule = AlternativeAssets::getRule($ruleId);
            if ( ! $existingRule ) {
                $this->errors[] = __('The requested Alternative Asset rule was not found.', 'wp-asset-clean-up');
                return;
            }
            // Editing changes the replacement and conditions, never the original asset identity.
            $assetType = $existingRule['type'];
            $handle    = $existingRule['handle'];
        }

        $outputMode = array_key_exists('output_mode', $_POST) ? $_POST['output_mode']
            : ($ruleId > 0 ? AlternativeAssets::getOutputMode($existingRule) : 'inherit');
        if ( ! in_array($outputMode, array('inherit', 'minified', 'unminified'), true)) {
            $this->errors[] = __('Choose a valid output option.', 'wp-asset-clean-up');
        }

        $allowedTargets = array(
            'everywhere', 'homepage', 'posts_page', 'post_type', 'post_ids',
            'taxonomy', 'taxonomy_term', 'author', 'search', 'date', '404',
            'cpt_archive', 'regex', 'post_tax_term', 'archive_types', 'logged_in', 'user_role'
        );

        if ($assetType !== '' && ! AlternativeAssets::supportsAssetType($assetType)) {
            $this->errors[] = __('JavaScript alternatives require Asset CleanUp Pro.', 'wp-asset-clean-up');
            return;
        }

        if ($assetType === '') {
            $this->errors[] = __('Choose CSS or JavaScript as the asset type.', 'wp-asset-clean-up');
        }

        if ($handle === '') {
            $this->errors[] = __('Enter the original WordPress asset handle.', 'wp-asset-clean-up');
        }

        if (trim($source) === '') {
            $this->errors[] = __('Paste the alternative asset source code.', 'wp-asset-clean-up');
        }

        if ($assetType === 'css' && AlternativeAssets::sourceStartsWithHtmlWrapper($assetType, $source)) {
            $this->errors[] = __('Paste CSS declarations only, without a <style> tag.', 'wp-asset-clean-up');
        }

        if ($assetType === 'js' && AlternativeAssets::sourceStartsWithHtmlWrapper($assetType, $source)) {
            $this->errors[] = __('Paste JavaScript code only, without a <script> tag.', 'wp-asset-clean-up');
        }

        if (empty($targets)) {
            $this->errors[] = __('Select at least one location where this rule should apply.', 'wp-asset-clean-up');
        }

        $targetsRequiringValue = array('post_type', 'post_ids', 'taxonomy', 'taxonomy_term', 'cpt_archive', 'regex', 'post_tax_term', 'archive_types', 'author', 'user_role');
        $detectedChoices = $this->getTargetChoices();

        foreach ($targets as $target => $targetValue) {
            $target = (string)$target;
            if ( ! in_array($target, $allowedTargets, true) ) {
                $this->errors[] = __('The selected location rule is not valid.', 'wp-asset-clean-up');
            }
            if (in_array($target, $targetsRequiringValue, true) && $targetValue === '') {
                $this->errors[] = __('Each selected location rule that requires a value must be filled in.', 'wp-asset-clean-up');
            }
            if (isset($detectedChoices[$target]) && $targetValue !== '') {
                foreach (explode(',', $targetValue) as $selectedValue) {
                    if ( ! array_key_exists($selectedValue, $detectedChoices[$target]) ) {
                        $this->errors[] = __('A selected content type, taxonomy or archive is no longer available. Review the selected locations.', 'wp-asset-clean-up');
                        break;
                    }
                }
            }

            if ($target === 'regex' && $targetValue !== '') {
                foreach (preg_split('/\r\n|\r|\n/', $targetValue) as $uriRule) {
                    if (trim($uriRule) !== '' && ! Regex::isValidRule(trim($uriRule), true)) {
                        $this->errors[] = __('The request URI rule is not a valid string or regular expression.', 'wp-asset-clean-up');
                        break;
                    }
                }
            }

            if (in_array($target, array('taxonomy_term', 'post_tax_term'), true) && ! preg_match('/^tt:[1-9][0-9]*(?:,[1-9][0-9]*)*$/D', $targetValue)) {
                $this->errors[] = __('Choose at least one taxonomy term using the selector.', 'wp-asset-clean-up');
            }
            if (in_array($target, array('post_ids', 'author'), true) && ! preg_match('/^[1-9][0-9]*(?:,[1-9][0-9]*)*$/D', $targetValue)) {
                $this->errors[] = __('Choose at least one entry using the selector.', 'wp-asset-clean-up');
            }
        }

        if ( ! empty($this->errors) ) {
            return;
        }

        $savedRule = AlternativeAssets::upsertRule(array(
            'id'           => $ruleId,
            'type'         => $assetType,
            'handle'       => $handle,
            'enabled'      => $enabled,
            'targets'      => $targets,
            'output_mode'  => $outputMode,
            'priority'     => $priority
        ), $source);

        if ($savedRule === false) {
            $this->errors[] = __('The Alternative Asset could not be saved because no valid rule ID is available.', 'wp-asset-clean-up');
            return;
        }

        $this->redirectAfterAction($ruleId > 0 ? 'updated' : 'created', $assetType, $ruleId);
    }

    public function getPostedTargets()
    {
        $selected = isset($_POST['targets']) && is_array($_POST['targets']) ? $_POST['targets'] : array();
        $values = isset($_POST['target_values']) && is_array($_POST['target_values']) ? $_POST['target_values'] : array();
        $targets = array();
        foreach ($selected as $target) {
            if ( ! is_string($target) ) {
                continue;
            }
            $key = sanitize_key(wp_unslash($target));
            $targets[$key] = isset($values[$key]) && is_string($values[$key]) ? trim(wp_unslash($values[$key])) : '';
            if (in_array($key, array('post_type', 'taxonomy', 'cpt_archive', 'archive_types', 'user_role'), true) && isset($values[$key]) && is_array($values[$key])) {
                $slugs = array();
                foreach ($values[$key] as $slug) {
                    if (is_string($slug) && $slug !== '') {
                        $slugs[] = sanitize_key(wp_unslash($slug));
                    }
                }
                $targets[$key] = implode(',', array_unique($slugs));
            }
        }
        return isset($targets['everywhere']) ? array('everywhere' => '') : $targets;
    }

    /**
     * @param string $notice
     * @param string $assetType
     * @param int    $editRuleId
     *
     * @return void
     */
    private function redirectAfterAction($notice, $assetType = '', $editRuleId = 0)
    {
        if ($assetType === '') {
            $assetType = AlternativeAssets::sanitizeAssetType(Misc::getVar('get', 'asset_type', 'css'));
        }

        if ($assetType === '') {
            $assetType = 'css';
        }

        $redirectUrl = add_query_arg(array(
            'page'             => WPACU_PLUGIN_ID . '_assets_manager',
            'wpacu_sub_page'   => 'manage_alternative_assets',
            'asset_type'       => $assetType,
            'wpacu_alt_notice' => sanitize_key($notice)
        ), admin_url('admin.php'));

        if ($notice === 'updated' && $editRuleId > 0) {
            $redirectUrl = add_query_arg(array('edit_rule' => absint($editRuleId)), $redirectUrl);
        }

        wp_safe_redirect($redirectUrl);
        exit();
    }

    /**
     * @param string $hookSuffix
     *
     * @return void
     */
    public function enqueueAssets($hookSuffix)
    {
        if ( ! $this->isAlternativeAssetsPage() || ! Menu::userCanManageAlternativeAssets() ) {
            return;
        }

        $assetSuffix = ((defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) || isset($_GET['wpacu_debug'])) ? '' : '.min';
        $assetBasePath = WPACU_PLUGIN_DIR . '/assets/wpacu-alternative-assets' . $assetSuffix;
        $cssVersion = WPACU_PLUGIN_VERSION;
        $jsVersion = WPACU_PLUGIN_VERSION;
        if (is_file($assetBasePath . '.css')) {
            $cssVersion .= '.' . filemtime($assetBasePath . '.css');
        }
        if (is_file($assetBasePath . '.js')) {
            $jsVersion .= '.' . filemtime($assetBasePath . '.js');
        }
        $editorSettings = false;
        $scriptDependencies = array('jquery');
        $isForm = Misc::getVar('get', 'wpacu_alt_view', '') === 'form' || absint(Misc::getVar('get', 'edit_rule', 0)) > 0;

        if ($isForm) {
            if (defined('WPACU_LITE_PLUGIN_VERSION') && ! defined('WPACU_PRO_PLUGIN_VERSION')) {
                wp_enqueue_script(
                    WPACU_PLUGIN_ID . '-css-beautify',
                    WPACU_PLUGIN_URL . '/assets/vendor/js-beautify/2.0.3/beautify-css' . $assetSuffix . '.js',
                    array(),
                    '2.0.3',
                    true
                );
                $scriptDependencies[] = WPACU_PLUGIN_ID . '-css-beautify';
            }
            wp_enqueue_style(WPACU_PLUGIN_ID . '-chosen-style', WPACU_PLUGIN_URL . '/assets/vendor/chosen/chosen.min.css', array(), '1.8.7');
            wp_enqueue_script(WPACU_PLUGIN_ID . '-chosen-script', WPACU_PLUGIN_URL . '/assets/vendor/chosen/chosen.jquery.min.js', array('jquery'), '1.8.7', true);
            $scriptDependencies[] = WPACU_PLUGIN_ID . '-chosen-script';
            wp_enqueue_style(WPACU_PLUGIN_ID . '-select2', WPACU_PLUGIN_URL . '/assets/vendor/select2/4.0.13/css/select2.min.css', array(), '4.0.13');
            wp_enqueue_script(WPACU_PLUGIN_ID . '-select2', WPACU_PLUGIN_URL . '/assets/vendor/select2/4.0.13/js/select2.full.min.js', array('jquery'), '4.0.13', true);
            $scriptDependencies[] = WPACU_PLUGIN_ID . '-select2';
        }

        if (function_exists('wp_enqueue_code_editor') && $isForm) {
            $editorSettings = wp_enqueue_code_editor(array(
                'type'       => 'text/css',
                'codemirror' => array('lint' => false)
            ));

            if ($editorSettings) {
                wp_enqueue_script(WPACU_PLUGIN_ID . '-css-tree', WPACU_PLUGIN_URL . '/assets/vendor/css-tree/3.2.1/csstree.min.js', array(), '3.2.1', true);
                $lintFile = '/assets/wpacu-alternative-assets-css-lint' . $assetSuffix . '.js';
                $lintVersion = WPACU_PLUGIN_VERSION;
                if (is_file(WPACU_PLUGIN_DIR . $lintFile)) {
                    $lintVersion .= '.' . filemtime(WPACU_PLUGIN_DIR . $lintFile);
                }
                wp_enqueue_script(WPACU_PLUGIN_ID . '-alternative-assets-css-lint', WPACU_PLUGIN_URL . $lintFile, array(WPACU_PLUGIN_ID . '-css-tree'), $lintVersion, true);
                $scriptDependencies[] = 'code-editor';
                $scriptDependencies[] = WPACU_PLUGIN_ID . '-alternative-assets-css-lint';
            }
        }

        wp_enqueue_style(
            WPACU_PLUGIN_ID . '-alternative-assets',
            WPACU_PLUGIN_URL . '/assets/wpacu-alternative-assets' . $assetSuffix . '.css',
            array(),
            $cssVersion
        );

        wp_enqueue_script(
            WPACU_PLUGIN_ID . '-alternative-assets',
            WPACU_PLUGIN_URL . '/assets/wpacu-alternative-assets' . $assetSuffix . '.js',
            $scriptDependencies,
            $jsVersion,
            true
        );

        wp_localize_script(
            WPACU_PLUGIN_ID . '-alternative-assets',
            'wpacuAlternativeAssetsData',
            array(
                'ajaxUrl'     => admin_url('admin-ajax.php'),
                'toggleNonce' => wp_create_nonce(self::TOGGLE_NONCE_ACTION),
                'globalStatusNonce' => wp_create_nonce(self::GLOBAL_STATUS_NONCE_ACTION),
                'globalStatusAction' => WPACU_PLUGIN_ID . '_alternative_assets_global_status',
                'fetchNonce'  => wp_create_nonce(self::FETCH_NONCE_ACTION),
                'sourceNonce' => wp_create_nonce(self::SOURCE_NONCE_ACTION),
                'sourceAction' => WPACU_PLUGIN_ID . '_alternative_assets_source',
                'toggleAction'=> WPACU_PLUGIN_ID . '_alternative_assets_toggle',
                'fetchAction' => WPACU_PLUGIN_ID . '_alternative_assets_fetch',
                'postSearchAction' => WPACU_PLUGIN_ID . '_alternative_assets_search_posts',
                'postSearchNonce'  => wp_create_nonce(WPACU_PLUGIN_ID . '_alternative_assets_search_posts'),
                'termSearchAction'=> WPACU_PLUGIN_ID . '_alternative_assets_search_terms',
                'termSearchNonce' => wp_create_nonce(WPACU_PLUGIN_ID . '_alternative_assets_search_terms'),
                'authorSearchAction' => WPACU_PLUGIN_ID . '_alternative_assets_search_authors',
                'authorSearchNonce' => wp_create_nonce(WPACU_PLUGIN_ID . '_alternative_assets_search_authors'),
                'authorsStrings' => array(
                    'chooseEntries' => __('Choose authors', 'wp-asset-clean-up'),
                    'editEntries' => __('Edit selected authors', 'wp-asset-clean-up'),
                    'noEntries' => __('No authors selected.', 'wp-asset-clean-up'),
                    'searchEntries' => __('Search authors by name, username or ID', 'wp-asset-clean-up'),
                    'searchMinimum' => __('Type at least one character or an author ID.', 'wp-asset-clean-up'),
                    'searchingEntries' => __('Searching authors…', 'wp-asset-clean-up'),
                    'noMatchingEntries' => __('No matching authors found.', 'wp-asset-clean-up'),
                    'loadingMoreEntries' => __('Loading more authors…', 'wp-asset-clean-up')
                ),
                'choiceStrings' => array(
                    'chooseEntries' => __('Choose options', 'wp-asset-clean-up'),
                    'editEntries' => __('Edit selected options', 'wp-asset-clean-up'),
                    'noEntries' => __('No options selected.', 'wp-asset-clean-up'),
                    'searchEntries' => __('Select or search available options', 'wp-asset-clean-up'),
                    'noMatchingEntries' => __('No matching options found.', 'wp-asset-clean-up')
                ),
                'uriStrings' => array(
                    'chooseEntries' => __('Edit URI rules', 'wp-asset-clean-up'),
                    'editEntries' => __('Edit URI rules', 'wp-asset-clean-up'),
                    'noEntries' => __('No URI rules entered.', 'wp-asset-clean-up')
                ),
                'termsStrings' => array(
                    'chooseEntries' => __('Choose taxonomy terms', 'wp-asset-clean-up'),
                    'editEntries' => __('Edit selected terms', 'wp-asset-clean-up'),
                    'noEntries' => __('No taxonomy terms selected.', 'wp-asset-clean-up'),
                    'searchEntries' => __('Search categories, tags and other taxonomy terms', 'wp-asset-clean-up'),
                    'searchMinimum' => __('Type at least one character or a term ID.', 'wp-asset-clean-up'),
                    'searchingEntries' => __('Searching taxonomy terms…', 'wp-asset-clean-up'),
                    'noMatchingEntries' => __('No matching taxonomy terms found.', 'wp-asset-clean-up'),
                    'loadingMoreEntries' => __('Loading more terms…', 'wp-asset-clean-up')
                ),
                'registry'    => array_values(AlternativeAssets::getDetectedAssets()),
                'codeEditor'  => $editorSettings,
                'cssLintMessages' => array(
                    'unexpectedClosing' => __('Unexpected closing token: %s', 'wp-asset-clean-up'),
                    'missingClosing' => __('Missing closing token: %s', 'wp-asset-clean-up'),
                    'unclosedString' => __('Unclosed or invalid CSS string.', 'wp-asset-clean-up'),
                    'unclosedComment' => __('Unclosed CSS comment.', 'wp-asset-clean-up'),
                    'invalidUrl' => __('Unclosed or invalid url().', 'wp-asset-clean-up')
                ),
                'strings'     => array(
                    'savingGlobalStatus' => __('Saving…', 'wp-asset-clean-up'),
                    'globalStatusFailed' => __('The status could not be saved. Please try again.', 'wp-asset-clean-up'),
                    'loadOriginal' => __('Copy original code', 'wp-asset-clean-up'),
                    'appendOriginal' => __('Append original code', 'wp-asset-clean-up'),
                    'appendUndone' => __('Append undone. The code before adding the original source has been restored.', 'wp-asset-clean-up'),
                    'loadOriginalHelp' => __('Copy the original file’s code into this editor, then remove what you don’t need. The original file stays unchanged.', 'wp-asset-clean-up'),
                    'appendOriginalHelp' => __('Add the original file’s code below your existing code. Nothing in the editor is replaced.', 'wp-asset-clean-up'),
                    'loadingOriginal' => __('Loading original code…', 'wp-asset-clean-up'),
                    'originalLoaded' => __('Original code copied into the editor. Remove what you don’t need, then save your alternative.', 'wp-asset-clean-up'),
                    'originalAppended' => __('Original code appended below your existing code. Review it, then save your alternative.', 'wp-asset-clean-up'),
                    'originalKeptEdits' => __('Your code was kept because you edited it while the original was loading. Use “Append original code” to add the original below it.', 'wp-asset-clean-up'),
                    'originalFailed' => __('The original content could not be loaded. Please try again.', 'wp-asset-clean-up'),
                    'originalChanged' => __('The selected asset changed. Load the original content for the new selection.', 'wp-asset-clean-up'),
                    'originalUnavailable' => __('Choose a detected handle, or use Find more assets to locate its original file.', 'wp-asset-clean-up'),
                    'noConditions' => __('No conditions selected', 'wp-asset-clean-up'),
                    'oneCondition' => __('1 condition selected', 'wp-asset-clean-up'),
                    'multipleConditions' => __('%d conditions selected', 'wp-asset-clean-up'),
                    'saving'             => __('Saving…', 'wp-asset-clean-up'),
                    'returningToList'    => __('Returning to the list…', 'wp-asset-clean-up'),
                    'enabled'            => __('Replacement enabled. The alternative asset will load where this rule matches.', 'wp-asset-clean-up'),
                    'disabled'           => __('Replacement disabled. The original full asset will load.', 'wp-asset-clean-up'),
                    'toggleFailed'       => __('The rule status could not be updated. Please try again.', 'wp-asset-clean-up'),
                    'fetching'           => __('Fetching CSS and JavaScript assets…', 'wp-asset-clean-up'),
                    'fetchFailed'        => __('The page assets could not be fetched.', 'wp-asset-clean-up'),
                    'fetchComplete'      => __('Assets were added to the detected asset list.', 'wp-asset-clean-up'),
                    'useHandlePrefix'    => __('Use this handle:', 'wp-asset-clean-up'),
                    'deleteConfirmation' => __('Permanently delete this Alternative Asset rule and its stored source code?', 'wp-asset-clean-up'),
                    'unknownSource'       => __('Unknown source', 'wp-asset-clean-up'),
                    'openOriginalFile'    => __('Open original file in a new tab', 'wp-asset-clean-up'),
                    'ruleCanStillApply'   => __('The rule will apply whenever this WordPress handle is enqueued.', 'wp-asset-clean-up'),
                    'alternativeCss'      => __('Alternative CSS', 'wp-asset-clean-up'),
                    'alternativeJs'       => __('Alternative JavaScript', 'wp-asset-clean-up'),
                    'enterPageUrl'        => __('Enter a page URL.', 'wp-asset-clean-up'),
                    'selectPages'         => __('Select a suggested page or enter a custom URL.', 'wp-asset-clean-up'),
                    'invalidPageUrl'      => __('Enter complete http:// or https:// URLs, one per line.', 'wp-asset-clean-up'),
                    'tooManyPages'        => __('Scan up to 20 pages at a time.', 'wp-asset-clean-up'),
                    'scanProgress'        => __('Scanning page %1$s of %2$s…', 'wp-asset-clean-up'),
                    'scanComplete'        => __('Scan complete: %1$s successful, %2$s failed.', 'wp-asset-clean-up'),
                    'scanStopped'         => __('Scan stopped: %1$s successful, %2$s failed.', 'wp-asset-clean-up'),
                    'scanStopping'        => __('Stopping after the current page…', 'wp-asset-clean-up'),
                    'chooseEntries'       => __('Choose entries', 'wp-asset-clean-up'),
                    'editEntries'         => __('Edit selected entries', 'wp-asset-clean-up'),
                    'noEntries'           => __('No entries selected.', 'wp-asset-clean-up'),
                    'searchEntries'       => __('Search posts, pages, products and other entries', 'wp-asset-clean-up'),
                    'searchMinimum'       => __('Type at least two characters to search.', 'wp-asset-clean-up'),
                    'searchingEntries'    => __('Searching entries…', 'wp-asset-clean-up'),
                    'noMatchingEntries'   => __('No matching entries found.', 'wp-asset-clean-up'),
                    'entriesSearchFailed' => __('The search could not be completed. Please try again.', 'wp-asset-clean-up'),
                    'loadingMoreEntries'  => __('Loading more entries…', 'wp-asset-clean-up'),
                    'targetHelp'          => array(
                        'everywhere'    => __('No value is required.', 'wp-asset-clean-up'),
                        'homepage'      => __('The WordPress front page only.', 'wp-asset-clean-up'),
                        'posts_page'    => __('The posts index selected in Settings → Reading.', 'wp-asset-clean-up'),
                        'post_type'     => __('Choose one or more detected post types.', 'wp-asset-clean-up'),
                        'post_ids'      => __('Choose individual entries by title or ID. Save the rule to keep your selection.', 'wp-asset-clean-up'),
                        'taxonomy'      => __('Choose one or more detected taxonomies.', 'wp-asset-clean-up'),
                        'taxonomy_term' => __('Choose term archive pages from any of the available taxonomies. This does not select posts assigned to those terms.', 'wp-asset-clean-up'),
                        'author'        => __('Choose the authors whose archive pages should use this alternative asset.', 'wp-asset-clean-up'),
                        'post_tax_term' => __('Applies to singular entries assigned to any selected term, not to the term archive itself.', 'wp-asset-clean-up'),
                        'archive_types' => __('Choose one or more archive and listing page types.', 'wp-asset-clean-up'),
                        'user_role'     => __('Applies when the logged-in user has any selected role.', 'wp-asset-clean-up'),
                        'search'        => __('All search result pages. No value is required.', 'wp-asset-clean-up'),
                        'date'          => __('All date archives. No value is required.', 'wp-asset-clean-up'),
                        '404'           => __('All 404 pages. No value is required.', 'wp-asset-clean-up'),
                        'cpt_archive'   => __('Choose one or more available custom post type archives.', 'wp-asset-clean-up'),
                        'regex'         => __('Enter one URI string or explicit RegEx per line, such as #^/landing/#. Any matching line applies.', 'wp-asset-clean-up')
                    )
                )
            )
        );
    }

    /**
     * @return void
     */
    public function ajaxToggleRule()
    {
        if ( ! Menu::userCanManageAlternativeAssets() ) {
            wp_send_json_error(array('message' => __('You are not allowed to update this rule.', 'wp-asset-clean-up')), 403);
        }

        if ( ! check_ajax_referer(self::TOGGLE_NONCE_ACTION, 'nonce', false) ) {
            wp_send_json_error(array('message' => __('The security check has failed.', 'wp-asset-clean-up')), 403);
        }

        $ruleId  = absint(Misc::getVar('post', 'rule_id', 0));
        $enabled = (int)Misc::getVar('post', 'enabled', 0) === 1;

        if ($ruleId <= 0) {
            wp_send_json_error(array('message' => __('The Alternative Asset rule is not valid.', 'wp-asset-clean-up')), 400);
        }

        $rule = AlternativeAssets::setRuleEnabled($ruleId, $enabled);

        if ($rule === false) {
            wp_send_json_error(array('message' => __('The Alternative Asset rule was not found.', 'wp-asset-clean-up')), 404);
        }

        $activeCount = AlternativeAssets::getActiveCount();
        wp_send_json_success(array(
            'rule_id' => $ruleId,
            'enabled' => ! empty($rule['enabled']),
            'active_count' => $activeCount,
            'active_count_text' => number_format_i18n($activeCount)
        ));
    }

    /**
     * @return void
     */
    public function ajaxLoadOriginalSource()
    {
        if ( ! Menu::userCanManageAlternativeAssets() ) {
            wp_send_json_error(array('message' => __('You are not allowed to read asset content.', 'wp-asset-clean-up')), 403);
        }
        if ( ! check_ajax_referer(self::SOURCE_NONCE_ACTION, 'nonce', false) ) {
            wp_send_json_error(array('message' => __('The security check has failed.', 'wp-asset-clean-up')), 403);
        }

        $type = AlternativeAssets::sanitizeAssetType(Misc::getVar('post', 'asset_type', ''));
        $handle = AlternativeAssets::sanitizeHandle(Misc::getVar('post', 'handle', ''));
        $assets = AlternativeAssets::getDetectedAssets();
        $key = $type . '|' . $handle;
        if ( ! AlternativeAssets::supportsAssetType($type) || $handle === '' || empty($assets[$key]['src'])) {
            wp_send_json_error(array('message' => __('Choose a detected handle, or use Find more assets to locate its original file.', 'wp-asset-clean-up')), 400);
        }

        // Resolve the URL server-side; never accept a file path or URL from the request.
        $source = $this->readOriginalSource($assets[$key]['src'], $type);
        if (is_wp_error($source)) {
            wp_send_json_error(array('message' => $source->get_error_message()), 400);
        }
        wp_send_json_success(array('source' => $source));
    }

    /** Read a detected source without executing it or changing the original file. */
    private function readOriginalSource($url, $type)
    {
        if ( ! in_array(strtolower((string)wp_parse_url($url, PHP_URL_SCHEME)), array('http', 'https'), true) ) {
            return new \WP_Error('invalid_source', __('The original file URL is not valid.', 'wp-asset-clean-up'));
        }

        $source = false;
        $localPath = OptimizeCommon::getLocalAssetPath($url, $type);
        if ($localPath) {
            $realPath = realpath($localPath);
            $allowedPath = false;
            foreach (array(ABSPATH, WP_CONTENT_DIR) as $root) {
                $realRoot = realpath($root);
                if ($realPath && $realRoot && strpos($realPath, rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0) {
                    $allowedPath = true;
                    break;
                }
            }
            if ($allowedPath && strtolower(pathinfo($realPath, PATHINFO_EXTENSION)) === $type && is_readable($realPath)) {
                $source = file_get_contents($realPath, false, null, 0, self::SOURCE_MAX_BYTES + 1);
            }
        }

        if ($source === false) {
            $response = wp_safe_remote_get($url, array(
                'timeout' => 20,
                'redirection' => 3,
                'limit_response_size' => self::SOURCE_MAX_BYTES + 1,
                'cookies' => array()
            ));
            if (is_wp_error($response)) {
                return new \WP_Error('source_fetch_failed', __('The original file could not be fetched. You can still open its link and paste the content manually.', 'wp-asset-clean-up'));
            }
            $status = (int)wp_remote_retrieve_response_code($response);
            if ($status !== 200) {
                return new \WP_Error('source_http_error', sprintf(__('The original file returned HTTP status %d.', 'wp-asset-clean-up'), $status));
            }
            $mime = strtolower(trim(explode(';', (string)wp_remote_retrieve_header($response, 'content-type'))[0]));
            $allowedMimes = $type === 'css' ? array('text/css') : array('text/javascript', 'application/javascript', 'application/x-javascript', 'text/ecmascript', 'application/ecmascript');
            // Static files are sometimes served without a specific content type.
            if (in_array($mime, array('', 'text/plain', 'application/octet-stream'), true)
                && strtolower(pathinfo((string)wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)) === $type) {
                $allowedMimes[] = $mime;
            }
            if ( ! in_array($mime, $allowedMimes, true) ) {
                return new \WP_Error('source_content_type', __('The response is not the expected CSS or JavaScript file.', 'wp-asset-clean-up'));
            }
            $source = (string)wp_remote_retrieve_body($response);
        }

        if (strlen($source) > self::SOURCE_MAX_BYTES) {
            return new \WP_Error('source_too_large', __('The original file exceeds the 2 MB editor import limit. Copy only the code you need into the editor.', 'wp-asset-clean-up'));
        }
        if (trim($source) === '') {
            return new \WP_Error('source_empty', __('The original file is empty.', 'wp-asset-clean-up'));
        }
        if (strpos($source, "\0") !== false || ! preg_match('//u', $source)
            || preg_match('/^\s*(?:\xEF\xBB\xBF)?\s*<(?:!doctype|html|head|body|script|style)\b/i', $source)) {
            return new \WP_Error('source_not_text', __('The response is not a valid CSS or JavaScript text file.', 'wp-asset-clean-up'));
        }
        return $source;
    }

    public function ajaxFetchAssets()
    {
        if ( ! Menu::userCanManageAlternativeAssets() ) {
            wp_send_json_error(array('message' => __('You are not allowed to fetch page assets.', 'wp-asset-clean-up')), 403);
        }

        if ( ! check_ajax_referer(self::FETCH_NONCE_ACTION, 'nonce', false) ) {
            wp_send_json_error(array('message' => __('The security check has failed.', 'wp-asset-clean-up')), 403);
        }

        $pageUrl = Misc::getVar('post', 'page_url', '');
        $pageUrl = is_string($pageUrl) ? esc_url_raw(wp_unslash($pageUrl)) : '';

        if ($pageUrl === '' || ! MainAdmin::isAllowedAjaxFetchUrl($pageUrl)) {
            wp_send_json_error(array('message' => __('Enter an allowed URL from this website.', 'wp-asset-clean-up')), 400);
        }

        $result = $this->fetchAssetsFromUrl($pageUrl);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        Update::updateHandlesInfo($result['all']);
        unset($GLOBALS['wpacu_global_data_json_decoded']);

        wp_send_json_success(array(
            'styles'   => isset($result['all']['styles']) ? count((array)$result['all']['styles']) : 0,
            'scripts'  => isset($result['all']['scripts']) ? count((array)$result['all']['scripts']) : 0,
            'finalUrl' => $result['final_url'],
            'registry' => array_values(AlternativeAssets::getDetectedAssets())
        ));
    }

    /**
     * Fetch the same encoded enqueue payload used by CSS/JS Manager, while
     * validating every redirect against the existing SSRF safeguards.
     *
     * @param string $pageUrl
     *
     * @return array|\WP_Error
     */
    private function fetchAssetsFromUrl($pageUrl)
    {
        $currentUrl   = $pageUrl;
        $responseBody = '';

        for ($redirectCount = 0; $redirectCount <= 3; $redirectCount++) {
            if ( ! MainAdmin::isAllowedAjaxFetchUrl($currentUrl) ) {
                return new \WP_Error('wpacu_alt_assets_url_not_allowed', __('The requested or redirected page URL is not allowed.', 'wp-asset-clean-up'));
            }

            $response = wp_safe_remote_post($currentUrl, array(
                'body'        => array(WPACU_LOAD_ASSETS_REQ_KEY => 1),
                'redirection' => 0,
                'timeout'     => 25
            ));

            if (is_wp_error($response)) {
                return $response;
            }

            $statusCode = (int)wp_remote_retrieve_response_code($response);
            $location   = wp_remote_retrieve_header($response, 'location');

            if ($statusCode >= 300 && $statusCode < 400 && is_string($location) && $location !== '') {
                $redirectUrl = \WP_Http::make_absolute_url($location, $currentUrl);

                if ($redirectUrl === '' || ! MainAdmin::isAllowedAjaxFetchUrl($redirectUrl)) {
                    return new \WP_Error('wpacu_alt_assets_redirect_not_allowed', __('The page redirected to a URL that cannot be fetched safely.', 'wp-asset-clean-up'));
                }

                $currentUrl = $redirectUrl;
                continue;
            }

            if ($statusCode < 200 || $statusCode >= 300) {
                return new \WP_Error(
                    'wpacu_alt_assets_http_error',
                    sprintf(__('The page returned HTTP status %d.', 'wp-asset-clean-up'), $statusCode)
                );
            }

            $responseBody = (string)wp_remote_retrieve_body($response);
            break;
        }

        if ($responseBody === '') {
            return new \WP_Error('wpacu_alt_assets_empty_response', __('The fetched page returned an empty response.', 'wp-asset-clean-up'));
        }

        if (strpos($responseBody, Main::START_DEL_ENQUEUED) === false || strpos($responseBody, Main::END_DEL_ENQUEUED) === false) {
            return new \WP_Error(
                'wpacu_alt_assets_payload_missing',
                __('Asset CleanUp could not find the encoded CSS/JS asset list in the fetched page.', 'wp-asset-clean-up')
            );
        }

        $encoded = Misc::extractBetween($responseBody, Main::START_DEL_ENQUEUED, Main::END_DEL_ENQUEUED);
        $decoded = base64_decode($encoded, true);
        $all     = $decoded !== false ? json_decode($decoded) : null;

        if ( ! is_object($all) && ! is_array($all) ) {
            return new \WP_Error('wpacu_alt_assets_payload_invalid', __('The fetched CSS/JS asset list is not valid.', 'wp-asset-clean-up'));
        }

        $data = array('all' => (array)$all);
        $data = MainAdmin::instance()->alterAssetObj($data, false);

        return array(
            'all'       => isset($data['all']) && is_array($data['all']) ? $data['all'] : array(),
            'final_url' => $currentUrl
        );
    }

    /**
     * @return array
     */
    public function getTargetChoices()
    {
        $choices = array('post_type' => array(), 'taxonomy' => array(), 'cpt_archive' => array());
        foreach (MiscAdmin::filterPostTypesList(get_post_types(array('public' => true))) as $slug => $unused) {
            $type = get_post_type_object($slug);
            if ($type && is_post_type_viewable($type)) {
                $choices['post_type'][$slug] = $type->labels->name . ' (' . $slug . ')';
            }
        }
        foreach (get_taxonomies(array('public' => true, 'show_ui' => true), 'objects') as $slug => $taxonomy) {
            $choices['taxonomy'][$slug] = $taxonomy->labels->name . ' (' . $slug . ')';
        }
        foreach (AssetsManagerAdmin::getCustomPostTypeArchives() as $slug => $archive) {
            $choices['cpt_archive'][$slug] = $archive['label'] . ' (' . $slug . ')';
        }
        $choices['archive_types'] = array(
            'posts_page' => __('Blog / Posts page', 'wp-asset-clean-up'),
            'search' => __('Search results', 'wp-asset-clean-up'),
            'date' => __('Date archives', 'wp-asset-clean-up'),
            'author' => __('All author archives', 'wp-asset-clean-up'),
            '404' => __('404 pages', 'wp-asset-clean-up')
        );
        foreach ($choices['cpt_archive'] as $slug => $label) {
            $choices['archive_types']['cpt_' . $slug] = $label;
        }
        $choices['user_role'] = array();
        foreach (wp_roles()->roles as $slug => $role) {
            $choices['user_role'][$slug] = translate_user_role($role['name']);
        }
        return $choices;
    }

    private function getSuggestedPages()
    {
        $pages = array();
        $addPage = static function ($url, $label) use (&$pages) {
            if ( ! is_string($url) || $url === '' ) {
                return;
            }
            $key = untrailingslashit($url);
            if ( ! isset($pages[$key]) ) {
                $pages[$key] = array('url' => $url, 'label' => $label);
            }
        };

        $addPage(home_url('/'), __('Homepage', 'wp-asset-clean-up'));
        $postsPage = (int)get_option('page_for_posts');
        if (get_option('show_on_front') === 'page' && $postsPage > 0 && get_post_status($postsPage) === 'publish') {
            $addPage(get_permalink($postsPage), __('Posts page', 'wp-asset-clean-up'));
        }

        if (function_exists('wc_get_page_id')) {
            foreach (array('shop', 'cart', 'checkout') as $pageName) {
                $pageId = (int)wc_get_page_id($pageName);
                if ($pageId > 0 && get_post_status($pageId) === 'publish') {
                    $addPage(get_permalink($pageId), get_the_title($pageId));
                }
            }
        }

        $postTypes = get_post_types(array('public' => true), 'objects');
        foreach ($postTypes as $postType) {
            if ($postType->name === 'attachment' || ! is_post_type_viewable($postType)) {
                continue;
            }
            $posts = get_posts(array(
                'post_type' => $postType->name, 'post_status' => 'publish',
                'numberposts' => 1, 'has_password' => false,
                'exclude' => array_filter(array((int)get_option('page_on_front'), $postsPage)),
                'orderby' => 'date', 'order' => 'DESC',
                'update_post_meta_cache' => false, 'update_post_term_cache' => false
            ));
            foreach ($posts as $post) {
                $addPage(get_permalink($post), $postType->labels->singular_name . ': ' . get_the_title($post));
            }
            if (count($pages) >= 8) {
                break;
            }
        }

        return array_values($pages);
    }

    /** Compare byte counts supplied by the caller, without generating or fetching assets. */
    public static function getSourceSizeComparison($originalBytes, $alternativeBytes)
    {
        $originalBytes = max(0, (int)$originalBytes);
        $alternativeBytes = max(0, (int)$alternativeBytes);
        $comparison = array(
            'original_bytes' => $originalBytes,
            'alternative_bytes' => $alternativeBytes,
            'state' => 'unknown',
            'label' => __('Comparison unavailable', 'wp-asset-clean-up')
        );
        if ($originalBytes === 0 || $alternativeBytes === 0) {
            return $comparison;
        }
        if ($originalBytes === $alternativeBytes) {
            $comparison['state'] = 'same';
            $comparison['label'] = __('Same size', 'wp-asset-clean-up');
            return $comparison;
        }

        $smaller = $alternativeBytes < $originalBytes;
        $percent = abs($originalBytes - $alternativeBytes) / $originalBytes * 100;
        $percentText = number_format_i18n($percent, 1);
        // Avoid displaying 0% for a real change, or 100% when some code remains.
        if ($percent < 0.1) {
            $percentText = '<' . number_format_i18n(0.1, 1);
        } elseif ($smaller && $percent > 99.9) {
            $percentText = '>' . number_format_i18n(99.9, 1);
        }
        $comparison['state'] = $smaller ? 'smaller' : 'larger';
        $comparison['label'] = sprintf($smaller
            ? __('%s%% smaller', 'wp-asset-clean-up')
            : __('%s%% larger', 'wp-asset-clean-up'), $percentText);
        return $comparison;
    }

    /**
     * @return array
     */
    public function getPageData()
    {
        if ( ! Menu::userCanManageAlternativeAssets() ) {
            wp_die(esc_html__('You are not allowed to manage Alternative Assets.', 'wp-asset-clean-up'), '', array('response' => 403));
        }

        $editRuleId = absint(Misc::getVar('get', 'edit_rule', 0));
        $editRule   = $editRuleId > 0 ? AlternativeAssets::getRule($editRuleId) : false;
        $assetType  = $editRule && ! empty($editRule['type'])
            ? $editRule['type']
            : AlternativeAssets::sanitizeAssetType(Misc::getVar('get', 'asset_type', 'css'));

        if ( ! $editRule && ! empty($this->errors) && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            $postedType = AlternativeAssets::sanitizeAssetType(Misc::getVar('post', 'asset_type', ''));
            if ($postedType !== '') {
                $assetType = $postedType;
            }
        }

        if ($assetType === '') {
            $assetType = 'css';
        }

        $isUpgradePage = $this->isJavaScriptUpgradePage();
        if ( ! AlternativeAssets::supportsAssetType($assetType) && ! $isUpgradePage) {
            wp_die(esc_html__('JavaScript alternatives require Asset CleanUp Pro.', 'wp-asset-clean-up'), '', array('response' => 403));
        }

        $mode = $isUpgradePage ? 'upgrade' : sanitize_key(Misc::getVar('get', 'wpacu_alt_view', 'list'));

        if ($editRule) {
            $mode = 'form';
        } elseif ($mode !== 'form' && ! $isUpgradePage) {
            $mode = 'list';
        }

        $rules = $isUpgradePage ? array() : AlternativeAssets::getRulesByType($assetType);

        usort($rules, static function ($a, $b) {
            $aPriority = isset($a['priority']) ? (int)$a['priority'] : 10;
            $bPriority = isset($b['priority']) ? (int)$b['priority'] : 10;

            if ($aPriority !== $bPriority) {
                return $aPriority > $bPriority ? -1 : 1;
            }

            $aUpdated = isset($a['updated']) ? (int)$a['updated'] : 0;
            $bUpdated = isset($b['updated']) ? (int)$b['updated'] : 0;

            if ($aUpdated === $bUpdated) {
                return 0;
            }

            return $aUpdated > $bUpdated ? -1 : 1;
        });

        $noticeKey = sanitize_key(Misc::getVar('get', 'wpacu_alt_notice', ''));
        $notices   = array(
            'created'   => __('The Alternative Asset rule was created.', 'wp-asset-clean-up'),
            'updated'   => __('The Alternative Asset was updated successfully.', 'wp-asset-clean-up'),
            'deleted'   => __('The Alternative Asset rule and its stored source were deleted.', 'wp-asset-clean-up'),
            'not_found' => __('The requested Alternative Asset rule was not found.', 'wp-asset-clean-up')
        );

        return array(
            'asset_type'     => $assetType,
            'mode'           => $mode,
            'rules'          => $rules,
            'edit_rule'      => $editRule,
            'edit_source'    => $editRule ? AlternativeAssets::getSource($editRule['id']) : '',
            'registry'       => $isUpgradePage ? array() : AlternativeAssets::getDetectedAssets(),
            'suggested_pages'=> $mode === 'form' ? $this->getSuggestedPages() : array(),
            'target_choices'=> $mode === 'form' ? $this->getTargetChoices() : array(),
            'target_groups' => array(
                __('Site scope', 'wp-asset-clean-up') => array('everywhere', 'homepage'),
                __('Posts, pages and individual content', 'wp-asset-clean-up') => array('post_ids', 'post_type', 'post_tax_term'),
                __('Archives and listing pages', 'wp-asset-clean-up') => array('taxonomy_term', 'taxonomy', 'archive_types', 'author'),
                __('Request and user conditions', 'wp-asset-clean-up') => array('regex', 'logged_in', 'user_role')
            ),
            'errors'         => $this->errors,
            'notice'         => ! $isUpgradePage && isset($notices[$noticeKey]) ? $notices[$noticeKey] : '',
            'notice_type'    => $noticeKey === 'not_found' ? 'error' : 'success',
            'base_url'       => admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_sub_page=manage_alternative_assets'),
            'target_options' => array(
                'everywhere'    => __('On all pages', 'wp-asset-clean-up'),
                'homepage'      => __('On the homepage', 'wp-asset-clean-up'),
                'posts_page'    => __('Blog / Posts page', 'wp-asset-clean-up'),
                'post_type'     => __('On all singular entries of these post types:', 'wp-asset-clean-up'),
                'post_tax_term' => __('On singular entries assigned to any of these taxonomy terms:', 'wp-asset-clean-up'),
                'archive_types' => __('On these archive and listing page types:', 'wp-asset-clean-up'),
                'logged_in'     => __('If the user is logged in', 'wp-asset-clean-up'),
                'user_role'     => __('If the logged-in user has any of these roles:', 'wp-asset-clean-up'),
                'post_ids'      => __('On specific posts, pages, products, or other entries:', 'wp-asset-clean-up'),
                'taxonomy'      => __('On all term archive pages from these taxonomies:', 'wp-asset-clean-up'),
                'taxonomy_term' => __('On these specific taxonomy term archive pages:', 'wp-asset-clean-up'),
                'author'        => __('On the archive pages of these authors:', 'wp-asset-clean-up'),
                'search'        => __('Search results', 'wp-asset-clean-up'),
                'date'          => __('Date archives', 'wp-asset-clean-up'),
                '404'           => __('404 pages', 'wp-asset-clean-up'),
                'cpt_archive'   => __('Custom post type archives', 'wp-asset-clean-up'),
                'regex'         => __('If the request URI matches any of these rules:', 'wp-asset-clean-up')
            )
        );
    }
}
