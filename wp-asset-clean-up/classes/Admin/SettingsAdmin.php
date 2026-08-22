<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\Menu;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\MiscArray;
use WpAssetCleanUp\OptimiseAssets\FontsGooglePreloadScanner;
use WpAssetCleanUp\OptimiseAssets\FontsLocalPreloadScanner;
use WpAssetCleanUp\OptimiseAssets\FontPreloadScanner;
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;
use WpAssetCleanUp\OptimiseAssets\OptimizeCss;
use WpAssetCleanUp\OptimiseAssets\OptimizeJs;
use WpAssetCleanUp\OptimiseAssets\ResourceLoading;
use WpAssetCleanUp\Regex;
use WpAssetCleanUp\Settings;
use WpAssetCleanUp\Update;

/**
 * Class SettingsAdmin
 * @package WpAssetCleanUp
 */
class SettingsAdmin
{
    /**
     * @var string
     */
    public static $transientNameSubmitErrors = WPACU_PLUGIN_ID . '_settings_submit_errors';

    /**
     * @return void
     */
    public function init()
    {
        // This is triggered BEFORE "triggerAfterInit" from 'Main' class
        add_action('admin_init', array($this, 'saveSettings'), 9);

        if (Misc::getVar('get', 'page') === WPACU_PLUGIN_ID . '_settings') {
            add_action('wpacu_admin_notices', array($this, 'notices'));

            if (function_exists('curl_init')) {
                // Check if the website supports HTTP/2 protocol and based on that advise the admin that combining CSS/JS is likely unnecessary
                add_action( 'admin_footer', array($this, 'adminFooterVerifyHttp2Protocol') );
            }
        }

        add_action( 'wp_ajax_' . WPACU_PLUGIN_ID . '_do_verifications',  array( $this, 'ajaxDoVerifications' ) );

        // e.g. when "Contract All Groups" is used, the state is kept (the setting is updated in the background)
        add_action( 'wp_ajax_' . WPACU_PLUGIN_ID . '_update_settings', array($this, 'ajaxUpdateSpecificSettings') );

        // "Settings" -- "Plugin Usage Preferences" -- "Prevent features of Asset CleanUp Pro from triggering on certain pages" -- "Add New Rule"
        add_action( 'wp_ajax_' . WPACU_PLUGIN_ID . '_add_new_no_features_load_row', array($this, 'ajaxAddNewNoFeaturesLoadRow') );

        FontsLocalPreloadScanner::registerAdminHooks();
        FontsGooglePreloadScanner::registerAdminHooks();
        FontPreloadScanner::registerAdminHooks();
    }

    /**
     * Google Fonts delivery preferences are intentionally disabled at runtime
     * while full removal is active. Keep the raw stored values available to the
     * Settings screen and to background updates so an unrelated save cannot
     * erase preferences that should become active again later.
     *
     * @return array
     */
    private static function getGoogleFontsDeliverySettingKeys()
    {
        return array(
            'google_fonts_combine',
            'google_fonts_combine_type',
            'google_fonts_display',
            'google_fonts_display_overwrite',
            'google_fonts_preconnect',
            'google_fonts_preload_files'
        );
    }

    /**
     * @return array
     */
    private static function getStoredGoogleFontsSettings()
    {
        $rawSettingsOption = get_option(WPACU_PLUGIN_ID . '_settings');

        if (is_string($rawSettingsOption)) {
            $rawSettings = json_decode($rawSettingsOption, true);
        } elseif (is_array($rawSettingsOption)) {
            $rawSettings = $rawSettingsOption;
        } else {
            $rawSettings = array();
        }

        if (! is_array($rawSettings)) {
            return array();
        }

        $storedGoogleFontsSettings = array();

        foreach (self::getGoogleFontsDeliverySettingKeys() as $settingKey) {
            if (array_key_exists($settingKey, $rawSettings)) {
                $storedGoogleFontsSettings[$settingKey] = $rawSettings[$settingKey];
            }
        }

        return $storedGoogleFontsSettings;
    }

    /**
     * @param array $settings
     *
     * @return array
     */
    private static function restoreStoredGoogleFontsSettings($settings)
    {
        foreach (self::getStoredGoogleFontsSettings() as $settingKey => $settingValue) {
            $settings[$settingKey] = $settingValue;
        }

        return $settings;
    }

    /**
     *
     */
    public function settingsPage()
    {
        $settingsClass = new Settings();

        $data = $settingsClass->getAll();

        foreach ($settingsClass->settingsKeys as $settingKey) {
            // Special check for plugin versions < 1.2.4.4
            if ($settingKey === 'frontend_show') {
                $data['frontend_show'] = $this->showOnFrontEndLegacy();
            }
        }

        $globalUnloadList = Main::instance()->getGlobalUnload();

        // [CSS]
        if (in_array('dashicons', $globalUnloadList['styles'])) {
            $data['disable_dashicons_for_guests'] = 1;
        }

        if (in_array('wp-block-library', $globalUnloadList['styles'])) {
            $data['disable_wp_block_library'] = 1;
        }
        // [/CSS]

        // [JS]
        if (in_array('jquery-migrate', $globalUnloadList['scripts'])) {
            $data['disable_jquery_migrate'] = 1;
        }

        if (in_array('comment-reply', $globalUnloadList['scripts'])) {
            $data['disable_comment_reply'] = 1;
        }
        // [/JS]

        $data['is_optimize_css_enabled_by_other_party'] = OptimizeCss::isOptimizeCssEnabledByOtherParty();
        $data['is_optimize_js_enabled_by_other_party']  = OptimizeJs::isOptimizeJsEnabledByOtherParty();
        $data['local_fonts_preload_scan']  = FontsLocalPreloadScanner::getAdminConfig();
        $data['google_fonts_preload_scan'] = FontsGooglePreloadScanner::getAdminConfig();
        $data['critical_css_rule_stats']   = CriticalCssAdmin::getCriticalCssRuleStats();

        // Settings::filterSettings() intentionally disables Google Fonts
        // delivery preferences at runtime while full removal is active. Overlay
        // their stored values for the form so they remain visible and survive a
        // normal Settings save.
        $storedGoogleFontsSettings = self::getStoredGoogleFontsSettings();

        if (! empty($data['google_fonts_remove'])) {
            foreach ($storedGoogleFontsSettings as $settingKey => $settingValue) {
                $data[$settingKey] = $settingValue;
            }
        }

        $data['google_fonts_preload_files_raw'] = array_key_exists('google_fonts_preload_files', $storedGoogleFontsSettings)
            ? (string) $storedGoogleFontsSettings['google_fonts_preload_files']
            : (string) $data['google_fonts_preload_files'];

        MainAdmin::instance()->parseTemplate('admin-page-settings-plugin', $data, true);
    }

    /**
     * @return bool
     */
    public function showOnFrontEndLegacy()
    {
        $settingsClass = new Settings();
        $settings = $settingsClass->getAll();

        return $settings['frontend_show'] == 1;
    }

    /**
     *
     */
    public function saveSettings()
    {
        if ( ! Misc::getVar('post', 'wpacu_settings_nonce') ) {
            return;
        }

        if ( ! Menu::userCanAccessPlugin() ) {
            return;
        }

        check_admin_referer('wpacu_settings_update', 'wpacu_settings_nonce');

        $savedSettings = Misc::getVar('post', WPACU_PLUGIN_ID . '_settings', array());

        // Useful for altering the POST data (e.g. for migration purposes) before it's being saved in the database
        $savedSettings = apply_filters('wpacu_settings_form_submit_before_save', $savedSettings);
        $savedSettings = stripslashes_deep($savedSettings);

        // Hooks can be attached here,
        // e.g. from PluginTracking.php (check if "Allow Usage Tracking" has been enabled)
        do_action('wpacu_before_save_settings', $savedSettings);

        $this->update($savedSettings);
    }

    /**
     * @param $settings
     * @param bool $redirectAfterUpdate
     *
     * @return bool|void
     *
     * @noinspection NestedAssignmentsUsageInspection
     */
    public function update($settings, $redirectAfterUpdate = true)
    {
        $settingsNotNull = array();

        $settings = self::alwaysPurifyTheseSettingsBeforeUpdate($settings);

        foreach ($settings as $settingKey => $settingValue) {
            if ($settingValue !== '') {
                // Some validation
                if ($settingKey === 'clear_cached_files_after') {
                    $settingValue = max(1, (int)$settingValue);
                    $settings[$settingKey] = $settingValue;
                }

                $settingsNotNull[$settingKey] = $settingValue;
            }
        }

        $settingsClass = new Settings();

        if (wp_json_encode($settingsClass->defaultSettings) === wp_json_encode($settingsNotNull)) {
            // Do not keep a record in the database (no point of having an extra entry)
            // if the submitted values are the same as the default ones
            delete_option(WPACU_PLUGIN_ID . '_settings');

            if ($redirectAfterUpdate) {
                $this->redirectAfterUpdate(); // script ends here
            }
        }

        // The following code is only triggered IF the user submitted the form from "Settings" area
        if (Misc::getVar('post', 'wpacu_settings_nonce')) {
            $settings = self::onlyPurifyTheseSettingsAfterFormSubmit($settings);
        }

        $addUpdateStatus = Misc::addUpdateOption(WPACU_PLUGIN_ID . '_settings', wp_json_encode(MiscArray::filterList($settings)));

        Misc::w3TotalCacheFlushObjectCache();

        // New Plugin Update (since 6 April 2020): the cache is cleared after page load via AJAX
        // This is done in case the cache directory is large and more time is required to clear it
        // This offers the admin a better user experience (no one likes to wait too much until a page is reloaded, which sometimes could cause confusion)
        if ($redirectAfterUpdate) {
            $this->redirectAfterUpdate();
        }

        return $addUpdateStatus;
    }

    /**
     * Triggers whenever the method update() is triggered
     *
     * This could be:
     * 1) After the "Settings" form is submitted
     * 2) Via a custom code whenever the settings are updated
     *
     * @param $settings
     *
     * @return array
     */
    public static function alwaysPurifyTheseSettingsBeforeUpdate($settings)
    {
        $purifyTextAreaForSpecificKeys = array(
            'do_not_load_plugin_patterns',

            'minify_loaded_css_exceptions',
            'inline_css_files_list',
            'combine_loaded_css_exceptions',
            'cache_dynamic_loaded_css_exceptions',

            'minify_loaded_js_exceptions',
            'inline_js_files_list',
            'combine_loaded_js_exceptions',
            'move_scripts_to_body_exceptions',
            'cache_dynamic_loaded_js_exceptions'
        );

        foreach ($settings as $settingKey => $settingValue) {
            if ($settingKey === 'input_style') {
                $settings[$settingKey] = Settings::getInputStyle($settingValue);
            }

            if ($settingKey === 'allow_manage_assets_to') {
                $settings[$settingKey] = in_array($settingValue, array('any_admin', 'selected', 'chosen', 'selected_roles'), true)
                    ? $settingValue
                    : 'any_admin';
            }

            if ($settingKey === 'allow_manage_assets_to_roles') {
                $allowedRoleSlugs = array_keys(SettingsAdminOnlyForAdmin::getAllRolesWithPluginAccess());
                $submittedRoleSlugs = is_array($settingValue) ? array_map('sanitize_key', $settingValue) : array();
                $settings[$settingKey] = array_values(array_intersect($submittedRoleSlugs, $allowedRoleSlugs));
            }

            if ( $settingKey === 'announcements' ) {
                $settingValue = is_array($settingValue) ? $settingValue : array();
                $settings[$settingKey] = isset($settings[$settingKey]) && is_array($settings[$settingKey])
                    ? $settings[$settingKey]
                    : array();
                $settings[$settingKey]['global'] = isset($settings[$settingKey]['global']) && is_array($settings[$settingKey]['global'])
                    ? $settings[$settingKey]['global']
                    : array();

                $settings[$settingKey]['global']['enabled'] = isset($settingValue['global']['enabled'])
                    && (int)$settingValue['global']['enabled'] === 1
                    ? 1
                    : 0;

                if ( $settings[$settingKey]['global']['enabled'] === 1 ) {
                    unset($settings[$settingKey]['global']['never_show_any']);
                }

                if ( isset($settingValue['global']['never_show_any']) && (int)$settingValue['global']['never_show_any'] === 0 ) {
                    unset($settings[$settingKey]['global']['never_show_any']);
                }

                if ( MiscArray::isValid($settingValue, 'list', true) ) {
                    foreach ( $settingValue['list'] as $annId => $annStates ) {
                        if ( is_array($annStates) && array_key_exists('seen', $annStates) && ! empty($annStates['snoozed']) ) {
                            // If it's marked as "seen", there's no point in having it "snoozed"
                            unset($settings[$settingKey]['list'][$annId]['snoozed']);
                        }
                    }
                }
            }

            if ( in_array($settingKey, $purifyTextAreaForSpecificKeys) ) {
                $settings[$settingKey] = Regex::purifyTextareaRules($settingValue);
            }

            if ($settingKey === 'cdn_rewrite_enable' && (int)$settingValue === 1) {
                if ( trim($settings['cdn_rewrite_url_css']) === '' && trim($settings['cdn_rewrite_url_js']) === '' ) {
                    unset($settings[$settingKey]);
                }
            }

            if ($settingKey === 'resource_loading') {
                /*
                 * A Settings page opened before the plugin update can still submit the legacy
                 * resource_loading[images][x] structure after the new version is installed.
                 * Normalize that submitted payload before validating the current attr.data shape.
                 */
                if (isset($settingValue['images'])
                    && is_array($settingValue['images'])
                    && ! isset($settingValue['images']['attr'])
                ) {
                    $legacySubmittedSettings = ResourceLoading::normalizeResourceLoadingImagesSettings(array(
                        ResourceLoading::$settingKey => $settingValue
                    ));

                    if (isset($legacySubmittedSettings[ResourceLoading::$settingKey])
                        && is_array($legacySubmittedSettings[ResourceLoading::$settingKey])
                    ) {
                        $settingValue = $legacySubmittedSettings[ResourceLoading::$settingKey];
                        $settings[$settingKey] = $settingValue;
                    }
                }

                /*
                * [Image Attributes]
                */
                $cleanAttrRules = $errorAttrSourcesInvalidRegex = array();

                if ( MiscArray::isValid($settingValue, 'images.attr.data', true) ) {
                    $allowedImageAttributes  = ResourceLoading\ImageAttributes::getAllowedResourceLoadingImageAttributes();
                    $matchByAllowedOptions   = array_keys(ResourceLoading\ImageAttributes::getAllowedResourceLoadingImageAttributeMatchBy());
                    $matchTypeAllowedOptions = array_keys(ResourceLoading\ImageAttributes::getAllowedResourceLoadingImageAttributeMatchTypes());

                    foreach ($settingValue['images']['attr']['data'] as $rule) {
                        if ( ! is_array($rule) ) {
                            continue;
                        }

                        $matchBy = isset($rule['match_by']) && is_string($rule['match_by']) ? trim(wp_unslash($rule['match_by'])) : 'source';

                        if ( ! in_array($matchBy, $matchByAllowedOptions, true) ) {
                            $matchBy = $matchByAllowedOptions[0]; // e.g. 'source'
                        }

                        $matchValue = isset($rule['match_value']) && is_string($rule['match_value']) ? trim(wp_unslash($rule['match_value'])) : '';

                        // Backward compatibility with the older format:
                        // resource_loading[images][attr][data][x][source]

                        // This is in case the user updates the plugin, and the same old FORM is loaded in "Settings"
                        // having the fields using the old structure name
                        if ($matchValue === '' && isset($rule['source']) && is_string($rule['source'])) {
                            $matchBy    = 'source';
                            $matchValue = trim(wp_unslash($rule['source']));
                        }

                        $matchType = isset($rule['match_type']) && is_string($rule['match_type']) ? trim(wp_unslash($rule['match_type'])) : '';

                        if ($matchType === '') {
                            $matchType = ResourceLoading::startsWithRegexDelimiter($matchValue) ? 'regex' : 'contains';
                        }

                        if ( ! in_array($matchType, $matchTypeAllowedOptions, true) ) {
                            $matchType = $matchTypeAllowedOptions[0]; // e.g. 'contains'
                        }

                        $attrName  = isset($rule['attribute']) && is_string($rule['attribute']) ? trim(wp_unslash($rule['attribute'])) : '';
                        $attrValue = isset($rule['value']) && is_string($rule['value']) ? trim(wp_unslash($rule['value'])) : '';

                        if ( ! isset($allowedImageAttributes[$attrName]) || ! in_array($attrValue, $allowedImageAttributes[$attrName], true) ) {
                            continue;
                        }

                        if ($matchValue === '' || $attrName === '' || $attrValue === '') {
                            continue;
                        }

                        if (strlen($matchValue) > 500) {
                            continue;
                        }

                        if ($matchType === 'regex' && ! ResourceLoading::isValidRegex($matchValue)) {
                            $errorAttrSourcesInvalidRegex[] = $matchValue;
                            continue;
                        }

                        $cleanAttrRules[] = array(
                            // If there's a match,
                            'match_by'    => $matchBy,
                            'match_type'  => $matchType,
                            'match_value' => $matchValue,

                            // apply this:
                            'attribute'   => $attrName,
                            'value'       => $attrValue
                        );
                    }

                    // Save the data to be shown after form submit
                    if ( ! empty($errorAttrSourcesInvalidRegex) ) {
                        $transientValue = array(
                            'for'  => 'resource_loading_image_attr_invalid_regex_list',
                            'list' => $errorAttrSourcesInvalidRegex
                        );

                        MiscArray::addToTransient(self::$transientNameSubmitErrors, $transientValue, 30);
                    }
                }

                $settings[$settingKey]['images']['attr']['data'] = $cleanAttrRules;

                // An enabled state without a valid matching rule has no effect on the front end.
                if (empty($cleanAttrRules)) {
                    $settings[$settingKey]['images']['attr']['_enabled'] = '0';
                }

                /*
                 * [/Image Attributes]
                 */

                /*
                 * [Lazy Load]
                 */
                $mainPath = 'images.lazy_load';

                if ( MiscArray::isValid($settingValue, $mainPath, true) ) {
                    $lazyLoadFieldKeys = array(
                        'skip_via_source_keywords',
                        'skip_via_css_classes'
                    );

                    $lazyLoadFieldKeys = apply_filters('wpacu_settings_admin_lazy_load_field_keys', $lazyLoadFieldKeys);

                    foreach ($lazyLoadFieldKeys as $currentKey) {
                        $path = $mainPath . '.' . $currentKey;

                        $currentValue = MiscArray::getValue($settingValue, $path, '');

                        $sanitizedValue = $currentValue; // default

                        // [Exclusions]
                        if ($currentKey === 'skip_via_source_keywords') {
                            $sanitizedValue = SettingsAdminPurifier::sanitizeUrlKeywords($currentValue);
                        }

                        if ($currentKey === 'skip_via_css_classes') {
                            $sanitizedValue = SettingsAdminPurifier::sanitizeSkipClasses($currentValue);
                        }

                        $sanitizedValue = apply_filters(
                            'wpacu_settings_admin_sanitize_lazy_load_field_value',
                            $sanitizedValue,
                            $currentKey,
                            $currentValue
                        );
                        // [/Exclusions]

                        if ($sanitizedValue !== '') {
                            $settings[$settingKey]['images']['lazy_load'][$currentKey] = $sanitizedValue;
                        } else {
                            unset($settings[$settingKey]['images']['lazy_load'][$currentKey]);
                        }
                    }
                }
                /*
                 * [/Lazy Load]
                 */

                /*
                 * [Master Status]
                 *
                 * Do not keep Resource Loading enabled if none
                 * of its individual features are enabled.
                 */
                $imageAttributesEnabled = ! empty($settings[$settingKey]['images']['attr']['_enabled']);
                $lazyLoadEnabled        = ! empty($settings[$settingKey]['images']['lazy_load']['_enabled']);

                if ( ! $imageAttributesEnabled && ! $lazyLoadEnabled ) {
                    // Keep this as a string, just like a value submitted by the hidden input.
                    // MiscArray::filterList() removes an integer 0, but preserves the string '0'.
                    $settings[$settingKey]['_enabled'] = '0';
                }
                /*
                 * [/Master Status]
                 */
            }
        }

        // A user already covered by a selected role does not need to be stored
        // as an individual CSS/JS Manager visibility exception.
        if ( ! empty($settings['allow_manage_assets_via_roles']) && ! empty($settings['allow_manage_assets_to_roles']) && ! empty($settings['allow_manage_assets_to_list']) ) {
            $selectedRoleSlugs = (array)$settings['allow_manage_assets_to_roles'];
            $settings['allow_manage_assets_to_list'] = array_values(array_filter(
                array_map('absint', (array)$settings['allow_manage_assets_to_list']),
                static function ($userId) use ($selectedRoleSlugs) {
                    $user = get_user_by('id', $userId);

                    return $user instanceof \WP_User && empty(array_intersect($selectedRoleSlugs, (array)$user->roles));
                }
            ));
        }

        $rolesVisibilityEnabled = ! empty($settings['allow_manage_assets_via_roles']);
        $usersVisibilityEnabled = ! empty($settings['allow_manage_assets_via_users']);

        if ($rolesVisibilityEnabled && $usersVisibilityEnabled) {
            $settings['allow_manage_assets_to'] = 'selected';
        } elseif ($rolesVisibilityEnabled) {
            $settings['allow_manage_assets_to'] = 'selected_roles';
        } elseif ($usersVisibilityEnabled) {
            $settings['allow_manage_assets_to'] = 'chosen';
        } else {
            $settings['allow_manage_assets_to'] = 'any_admin';
        }

        return $settings;
    }


    /**
     * This is triggered ONLY after the "Settings" form was submitted
     *
     * @param $settings
     *
     * @return array
     */
    public static function onlyPurifyTheseSettingsAfterFormSubmit($settings)
    {
        // By default, these hidden settings are enabled; In case they do not exist (older database), add them
        // Only keep them enabled if WordPress version is >= 5.5
        $appendInlineCodeToCombinedAssets = Misc::isWpVersionAtLeast('5.5') ? 1 : '';

        if ( $appendInlineCodeToCombinedAssets === '' ) {
            // WordPress version < 5.5 (do not enable it)
            $settings['_combine_loaded_css_append_handle_extra'] = $settings['_combine_loaded_js_append_handle_extra'] = '';
        } else {
            // WordPress version >= 5.5 (make it enabled by default if not set)
            if ( ! isset( $settings['_combine_loaded_css_append_handle_extra'] ) ) {
                $settings['_combine_loaded_css_append_handle_extra'] = 1; // default
            }
            if ( ! isset( $settings['_combine_loaded_js_append_handle_extra'] ) ) {
                $settings['_combine_loaded_js_append_handle_extra'] = 1; // default
            }
        }

        // "Common Site-Wide Unloads" tab
        $disableGutenbergCssBlockLibrary = isset( $_POST[ WPACU_PLUGIN_ID . '_global_unloads' ]['disable_wp_block_library'] );
        $disableJQueryMigrate            = isset( $_POST[ WPACU_PLUGIN_ID . '_global_unloads' ]['disable_jquery_migrate'] );
        $disableCommentReply             = isset( $_POST[ WPACU_PLUGIN_ID . '_global_unloads' ]['disable_comment_reply'] );
        $disableDashiconsForGuests       = isset( $_POST[ WPACU_PLUGIN_ID . '_global_unloads' ]['disable_dashicons_for_guests'] );

        $settingsAdminClass = new self();
        $settingsAdminClass->updateSiteWideRuleForCommonAssets(array(
            'wp_block_library' => $disableGutenbergCssBlockLibrary,
            'dashicons'        => $disableDashiconsForGuests,
            'jquery_migrate'   => $disableJQueryMigrate,
            'comment_reply'    => $disableCommentReply
        ));

        // Some validation
        $stripTagsForList = array(
            'frontend_show_exceptions',
            'do_not_load_plugin_patterns',
            'minify_loaded_css_exceptions',
            'combine_loaded_css_exceptions',
            'inline_css_files_list',
            'cache_dynamic_loaded_css_exceptions',
            'minify_loaded_js_exceptions',
            'combine_loaded_js_exceptions',
            'cache_dynamic_loaded_js_exceptions',
            'cdn_rewrite_url_css',
            'cdn_rewrite_url_js',
            'remove_html_comments_exceptions',
            'local_fonts_preload_files',
            'google_fonts_preload_files'
        );

        $stripTagsForList = apply_filters('wpacu_settings_admin_strip_tags_for_list', $stripTagsForList);

        foreach ($stripTagsForList as $stripTagsFor) {
            $settings[$stripTagsFor] = strip_tags($settings[$stripTagsFor]);
        }

        // Keep the legacy manual font-preload fields in a predictable one-URL-per-line
        // format. Empty lines and surrounding whitespace have no meaning here and
        // should not be persisted back to the settings form.
        foreach (array('local_fonts_preload_files', 'google_fonts_preload_files') as $fontPreloadSetting) {
            if ( ! isset($settings[$fontPreloadSetting])) {
                continue;
            }

            $fontPreloadLines = preg_split('/\r\n|\r|\n/', (string)$settings[$fontPreloadSetting]);
            $cleanFontPreloadLines = array();

            foreach ($fontPreloadLines as $fontPreloadLine) {
                $fontPreloadLine = trim($fontPreloadLine);

                if ($fontPreloadLine !== '') {
                    $cleanFontPreloadLines[] = $fontPreloadLine;
                }
            }

            $settings[$fontPreloadSetting] = implode("\n", $cleanFontPreloadLines);
        }

        // Each non-empty row is a literal, case-sensitive URI substring. Normalising
        // the list prevents an accidental blank row from matching every request URI.
        if (isset($settings['frontend_show_exceptions'])) {
            $frontendShowExceptions = preg_split('/\r\n|\r|\n/', (string)$settings['frontend_show_exceptions']);
            $cleanFrontendShowExceptions = array();

            foreach ($frontendShowExceptions as $frontendShowException) {
                $frontendShowException = trim($frontendShowException);

                if ($frontendShowException === '' || in_array($frontendShowException, $cleanFrontendShowExceptions, true)) {
                    continue;
                }

                $cleanFrontendShowExceptions[] = $frontendShowException;
            }

            $settings['frontend_show_exceptions'] = implode("\n", $cleanFrontendShowExceptions);
        }

        // Apply 'Ignore dependency rule and keep the "children" loaded' for "dashicons" handle if Ninja Forms is active
        // because "nf-display" handle depends on the Dashicons, and it could break the forms' styling
        if ($disableDashiconsForGuests && wpacuIsPluginActive('ninja-forms/ninja-forms.php')) {
            $mainVarToUse = array();
            $mainVarToUse['wpacu_ignore_child']['styles']['dashicons'] = 1;
            Update::updateIgnoreChild($mainVarToUse);
        }

        if ($appendInlineCodeToCombinedAssets) {
            $settingsAdminClass = new SettingsAdmin();
            $settings = $settingsAdminClass::toggleAppendInlineAssocCodeHiddenSettings($settings);
        }

        // Pro: v1.2.4.2 | Lite: v1.3.9.4
        if ( ! empty($settings['do_not_load_plugin_features']) ) {
            $settings['do_not_load_plugin_features'] = MiscArray::filterList($settings['do_not_load_plugin_features']);
            if ( ! empty($settings['do_not_load_plugin_features']) ) {
                foreach ($settings['do_not_load_plugin_features'] as $rowKey => $setValues) {
                    if (empty($setValues['pattern']) || empty($setValues['list'])) {
                        unset($settings['do_not_load_plugin_features'][$rowKey]);
                    }
                }
            }

            }

        // [Only for admins]
        SettingsAdminOnlyForAdmin::filterSettingsOnFormSubmit();
        // [/Only for admins]

        return $settings;
    }

    /**
     * @param $settingsKey
     *
     * @return mixed
     */
    public function getOption($settingsKey)
    {
        $settingsClass = new Settings();
        $settings = $settingsClass->getAll();
        return $settings[$settingsKey];
    }

    /**
     * @param string|string[] $key
     * @param mixed           $value
     *
     * @return bool|void
     */
    public function updateOption($key, $value)
    {
        $settingsClass = new Settings();
        $settings      = self::restoreStoredGoogleFontsSettings($settingsClass->getAll(true));

        if ( ! is_array($key) ) {
            if ( ! in_array($key, $settingsClass->settingsKeys, true) ) {
                return;
            }

            $settings[$key] = $value;
        } else {
            if ( ! is_array($value) ) {
                return;
            }

            foreach ($key as $keyIndex => $keyValue) {
                if ( ! in_array($keyValue, $settingsClass->settingsKeys, true) ||
                     ! array_key_exists($keyIndex, $value) ) {
                    continue;
                }

                $settings[$keyValue] = $value[$keyIndex];
            }
        }

        return $this->update($settings, false);
    }

    /**
     * @param $key
     */
    public function deleteOption($key)
    {
        $settingsClass = new Settings();
        $settings = self::restoreStoredGoogleFontsSettings($settingsClass->getAll(true));

        $settings[$key] = '';

        $this->update($settings, false);
    }

    /**
     *
     */
    public function notices()
    {
        $settingsClass = new Settings();
        $settings = $settingsClass->getAll();

        // When no retrieval method for fetching the assets is enabled
        if ($settings['dashboard_show'] != 1 && $settings['frontend_show'] != 1) {
            ?>
            <div class="notice notice-warning">
                <p><span style="color: #ffb900;" class="dashicons dashicons-info"></span>&nbsp;<?php _e('It looks like you have both "Manage in the Dashboard?" and "Manage in the Front-end?" inactive. The plugin still works fine and any assets you have selected for unload are not loaded. However, if you want to manage the assets in any page, you need to have at least one of the view options enabled.', 'wp-asset-clean-up'); ?></p>
            </div>
            <?php
        }

        // After "Save changes" is clicked
        if (Misc::getVar('get', 'wpacu_selected_tab_area') ) {
            // "Settings" updated (from any tab/sub-tab)
            $transientName = WPACU_PLUGIN_ID . '_settings_updated';

            if (get_transient($transientName)) {
                delete_transient($transientName);
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><span class="dashicons dashicons-yes"></span> <?php _e('The settings were successfully updated.', 'wp-asset-clean-up'); ?></p>
                </div>
                <?php
            }

            // "Settings" updated but with errors
            $transientName = self::$transientNameSubmitErrors;

            $transientSubmitErrors = get_transient($transientName);

            if ( ! empty($transientSubmitErrors) && is_array($transientSubmitErrors) ) {
                delete_transient($transientName);
            ?>
                <style>
                    .wpacu-settings-errors-notice {
                        margin-top: 8px;
                    }

                    .wpacu-settings-errors-notice ul {
                        margin: 8px 0 10px 18px;
                        list-style: disc;
                    }

                    .wpacu-settings-errors-notice li {
                        margin-bottom: 4px;
                    }

                    .wpacu-settings-errors-notice > ul > li > ul {
                        list-style: circle;
                    }

                    .wpacu-settings-errors-notice code {
                        display: inline-block;
                        max-width: 100%;
                        padding: 2px 6px;
                        white-space: normal;
                        word-break: break-all;
                    }
                </style>

                <div class="notice notice-warning wpacu-settings-errors-notice">
                    <p>Some settings could not be saved. Please review the issues below.</p>

                    <ul>
                        <?php
                        foreach ($transientSubmitErrors as $submitErrorData) {
                            $errorsCount = count($submitErrorData['list']);

                            // "Settings" -- "Resource Loading" -- "Image Attributes" errors
                            if ($submitErrorData['for'] === 'resource_loading_image_attr_invalid_regex_list') {

                                    ?>
                                    <li>
                                        <p>Location: <em style="color: #004567;">"Resource Loading" -- "Image Attributes"</em></p>
                                        <p>
                                            <strong>
                                                <?php
                                                echo esc_html(
                                                    $errorsCount === 1
                                                    ? __('One image attribute rule was not added.', 'wp-asset-clean-up')
                                                    : __('Some image attribute rules were not added.', 'wp-asset-clean-up')
                                                );
                                                ?>
                                            </strong>

                                            <?php
                                            echo esc_html(
                                                $errorsCount === 1
                                                ? __('The source value contains an invalid regular expression.', 'wp-asset-clean-up')
                                                : __('The source values contain invalid regular expressions.', 'wp-asset-clean-up')
                                            );
                                            ?>
                                        </p>

                                        <ul>
                                            <?php foreach ($submitErrorData['list'] as $sourceWithError) : ?>
                                                <li><code><?php echo esc_html($sourceWithError); ?></code></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </li>
                                <?php
                            }

                            if ($submitErrorData['for'] === 'resource_loading_lazy_load_exclude_url_keyword_invalid_regex_list') {
                                ?>
                                <li>
                                    <p>Location: <em style="color: #004567;">"Resource Loading" -- "Lazy Load"</em></p>
                                    <p>
                                        <strong>
                                            <?php
                                            echo esc_html(
                                                $errorsCount === 1
                                                ? __('One line from the "URL Keywords" exclusion list was not added.', 'wp-asset-clean-up')
                                                : __('Some lines from the "URL Keywords" exclusion list were not added.', 'wp-asset-clean-up')
                                            );
                                            ?>
                                        </strong>

                                        <?php
                                        echo esc_html(
                                        $errorsCount === 1
                                            ? __('The line contains an invalid regular expression.', 'wp-asset-clean-up')
                                            : __('The lines contain invalid regular expressions.', 'wp-asset-clean-up')
                                        );
                                        ?>
                                    </p>

                                    <ul>
                                        <?php foreach ($submitErrorData['list'] as $sourceWithError) : ?>
                                            <li><code><?php echo esc_html($sourceWithError); ?></code></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </li>
                                <?php
                            }
                        }
                        ?>
                    </ul>

                    <p><?php esc_html_e('Please correct the entries listed above and try again.', 'wp-asset-clean-up'); ?></p>
                </div>
            <?php
            }
        }
    }

    /**
     *
     */
    public function ajaxDoVerifications()
    {
        $action = isset($_POST['action']) && is_string($_POST['action'])
            ? sanitize_key(wp_unslash($_POST['action']))
            : '';

        if ($action !== WPACU_PLUGIN_ID . '_do_verifications' || ! Menu::userCanAccessPlugin()) {
            return;
        }

        $nonce = isset($_POST['wpacu_nonce']) && is_string($_POST['wpacu_nonce'])
            ? wp_unslash($_POST['wpacu_nonce'])
            : '';

        if ( ! wp_verify_nonce($nonce, 'wpacu_do_verifications') ) {
            echo 'Error: The security check has failed. Location: '.__METHOD__;
            return;
        }

        $result = array();

        if ( ! function_exists('curl_init') ) {
            echo wp_json_encode($result);
            exit();
        }

        $ch = curl_init();

        if ($ch === false) {
            echo wp_json_encode($result);
            exit();
        }

        $curlParams = array(
            CURLOPT_URL            => get_site_url(),
            CURLOPT_HEADER         => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'WordPress/' . get_bloginfo('version') . '; Asset CleanUp connection protocol check',
        );

        if (defined('CURLOPT_NOSIGNAL')) {
            $curlParams[CURLOPT_NOSIGNAL] = true;
        }

        $curlVersionInfo = curl_version();
        $curlFeatures    = isset($curlVersionInfo['features']) ? (int) $curlVersionInfo['features'] : 0;
        $curlHasHttp2    = defined('CURL_VERSION_HTTP2')
            && (($curlFeatures & CURL_VERSION_HTTP2) === CURL_VERSION_HTTP2);

        if ($curlHasHttp2 && defined('CURLOPT_HTTP_VERSION') && defined('CURL_HTTP_VERSION_2TLS')) {
            // Negotiate HTTP/2 over HTTPS and allow HTTP/1.1 fallback. Forcing HTTP/3 here can fail on
            // server-side UDP/network restrictions even when the visitor-facing site supports HTTP/3.
            $curlParams[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2TLS;
        } elseif ($curlHasHttp2 && defined('CURLOPT_HTTP_VERSION') && defined('CURL_HTTP_VERSION_2_0')) {
            $curlParams[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2_0;
        }

        curl_setopt_array($ch, $curlParams);

        $response = curl_exec($ch);

        if ($response === false) {
            // A redirect target can be unreachable from the hosting server even though the original
            // site endpoint answered correctly. Retry that endpoint without following redirects.
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            $response = curl_exec($ch);
        }

        $curlErrorCode = curl_errno($ch);
        $effectiveUrl  = defined('CURLINFO_EFFECTIVE_URL') ? curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) : get_site_url();
        $responseCode  = defined('CURLINFO_RESPONSE_CODE') ? curl_getinfo($ch, CURLINFO_RESPONSE_CODE) : 0;
        $httpVersion   = defined('CURLINFO_HTTP_VERSION') ? curl_getinfo($ch, CURLINFO_HTTP_VERSION) : 0;

        // PHP 8.0+ releases cURL handles automatically; curl_close() is deprecated as of PHP 8.5.
        if (PHP_VERSION_ID < 80000) {
            curl_close($ch);
        }

        if ( ! is_string($response) || $response === '' ) {
            $result['check_status'] = 'failed';
            $result['error_code']   = (string) $curlErrorCode;
            echo wp_json_encode($result);
            exit();
        }

        $protocol = '';

        if (defined('CURL_HTTP_VERSION_3') && $httpVersion === CURL_HTTP_VERSION_3) {
            $protocol = 'http3';
        } elseif (defined('CURL_HTTP_VERSION_2_0') && $httpVersion === CURL_HTTP_VERSION_2_0) {
            $protocol = 'http2';
        } elseif (defined('CURL_HTTP_VERSION_1_1') && $httpVersion === CURL_HTTP_VERSION_1_1) {
            $protocol = 'http1_1';
        } elseif (defined('CURL_HTTP_VERSION_1_0') && $httpVersion === CURL_HTTP_VERSION_1_0) {
            $protocol = 'http1_0';
        } elseif (preg_match('/^HTTP\/3(?:\.\d+)?(?:\s|$)/mi', $response)) {
            $protocol = 'http3';
        } elseif (preg_match('/^HTTP\/2(?:\.\d+)?(?:\s|$)/mi', $response)) {
            $protocol = 'http2';
        } elseif (preg_match('/^HTTP\/1\.1(?:\s|$)/mi', $response)) {
            $protocol = 'http1_1';
        } elseif (preg_match('/^HTTP\/1\.0(?:\s|$)/mi', $response)) {
            $protocol = 'http1_0';
        }

        $result['check_status']  = $protocol === '' ? 'unknown' : 'complete';
        $result['protocol']      = $protocol;
        $result['effective_url'] = is_string($effectiveUrl) ? esc_url_raw($effectiveUrl) : '';
        $result['response_code'] = (string) ((int) $responseCode);
        $result['has_http2']     = in_array($protocol, array('http2', 'http3'), true) ? '1' : '0';
        $result['has_http3']     = $protocol === 'http3' ? '1' : '0';

        if (stripos($response, 'cf-cache-status:') !== false
            && stripos($response, 'cf-request-id:') !== false
            && stripos($response, 'cf-ray:') !== false) {
            $result['uses_cloudflare'] = '1'; // Uses Cloudflare
        }

        echo wp_json_encode($result);
        exit();
    }

    /**
     *
     */
    public function ajaxUpdateSpecificSettings()
    {
        // Option: "On Assets List Layout Load, keep the groups:"
        $action = isset($_POST['action']) && is_string($_POST['action'])
            ? sanitize_key(wp_unslash($_POST['action']))
            : '';
        $requestFlag = isset($_POST['wpacu_update_keep_the_groups']) && is_string($_POST['wpacu_update_keep_the_groups'])
            ? wp_unslash($_POST['wpacu_update_keep_the_groups'])
            : '';
        $nonce = isset($_POST['wpacu_nonce']) && is_string($_POST['wpacu_nonce'])
            ? wp_unslash($_POST['wpacu_nonce'])
            : '';
        $newKeepTheGroupsState = isset($_POST['wpacu_keep_the_groups_state']) && is_string($_POST['wpacu_keep_the_groups_state'])
            ? sanitize_key(wp_unslash($_POST['wpacu_keep_the_groups_state']))
            : '';

        if ($action !== WPACU_PLUGIN_ID . '_update_settings'
            || $requestFlag !== 'yes'
            || ! Menu::userCanAccessPlugin()
            || ! wp_verify_nonce($nonce, 'wpacu_update_specific_settings_nonce')
            || ! in_array($newKeepTheGroupsState, array('expanded', 'contracted'), true)) {
            echo 'Error: The request to update the asset-groups state is not valid.';
            exit();
        }

        $this->updateOption('assets_list_layout_areas_status', $newKeepTheGroupsState);

        echo 'done';
        exit();
    }

    /**
     * @return void
     */
    public function ajaxAddNewNoFeaturesLoadRow()
    {
        if ( ! isset( $_POST['action'] ) || ($_POST['action'] !== WPACU_PLUGIN_ID . '_add_new_no_features_load_row') ||
             ! Menu::userCanAccessPlugin() ) {
            exit();
        }

        check_ajax_referer('wpacu_add_new_no_features_load_row_nonce', 'wpacu_nonce');

        $settingsClass = new Settings();
        $settingsData = $settingsClass->getAll();

        // Match the interface used to render the open page. The JavaScript caller
        // sends this explicitly so an AJAX-created row cannot drift into a mixed mode.
        if (isset($_POST['wpacu_input_style'])) {
            $settingsData['input_style'] = Settings::getInputStyle(
                sanitize_key(wp_unslash($_POST['wpacu_input_style']))
            );
        }

        echo self::generateNewRuleNoFeatureAreaRow($settingsData);
        exit();
    }

    /**
     *
     */
    public function adminFooterVerifyHttp2Protocol()
    {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var protocolLabels = {
                    http3: 'HTTP/3',
                    http2: 'HTTP/2',
                    http1_1: 'HTTP/1.1',
                    http1_0: 'HTTP/1.0'
                };
                var http1Message = <?php echo wp_json_encode(__('The server-side check negotiated %s. This does not rule out HTTP/2 or HTTP/3 for visitors when a CDN, proxy, or different network path is used. Treat Combine as situational and compare measured results.', 'wp-asset-clean-up')); ?>;
                var failedMessage = <?php echo wp_json_encode(__('The automatic server-side protocol check could not be completed. This is not evidence that the public site uses HTTP/1. Use the external check for the visitor-facing connection, then compare measured results before enabling Combine.', 'wp-asset-clean-up')); ?>;

                function showDefaultNotice(message) {
                    $('.wpacu-combine-notice-default').removeClass('wpacu_hide');
                    $('.wpacu-http-protocol-check-status').text(message || '');
                }

                $.ajax({
                    url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                    method: 'POST',
                    dataType: 'json',
                    timeout: 15000,
                    data: {
                        action: '<?php echo WPACU_PLUGIN_ID; ?>_do_verifications',
                        wpacu_nonce: '<?php echo wp_create_nonce('wpacu_do_verifications'); ?>'
                    }
                }).done(function (result) {
                    var protocol = result && result.protocol ? result.protocol : '';

                    if (protocol === 'http2' || protocol === 'http3') {
                        $('.wpacu-http-protocol-label').text(protocolLabels[protocol]);
                        $('.wpacu-combine-notice-http-2-detected').removeClass('wpacu_hide');
                        $('#wpacu-js-optimization-settings')
                            .attr('data-modern-protocol-detected', '1')
                            .trigger('wpacuModernProtocolDetected');
                        $('#wpacu-css-optimization-settings')
                            .attr('data-modern-protocol-detected', '1')
                            .trigger('wpacuModernProtocolDetected');
                    } else if (protocol === 'http1_1' || protocol === 'http1_0') {
                        showDefaultNotice(http1Message.replace('%s', protocolLabels[protocol]));
                    } else {
                        showDefaultNotice(failedMessage);
                    }

                    if (result && result.uses_cloudflare === '1') {
                        $('#wpacu-site-uses-cloudflare').show();
                    }
                }).fail(function () {
                    showDefaultNotice(failedMessage);
                });
            });
        </script>
        <?php
    }

    /**
     * @param $value
     * @param $name
     *
     * @return false|string
     */
    public static function generateAssetsListLayoutDropDown($value, $name)
    {
        ob_start();
        ?>
        <select id="wpacu_assets_list_layout" style="max-width: inherit;" name="<?php echo esc_attr($name); ?>">
            <option <?php if ($value === 'by-location') { echo 'selected="selected"'; } ?> value="by-location"><?php esc_html_e('Grouped by location (themes, plugins, core &amp; external)', 'wp-asset-clean-up'); ?></option>
            <option <?php if ($value === 'by-position') { echo 'selected="selected"'; } ?> value="by-position"><?php esc_html_e('Grouped by tag position: &lt;head&gt; &amp; &lt;body&gt;', 'wp-asset-clean-up'); ?></option>
            <option <?php if ($value === 'by-preload') { echo 'selected="selected"'; } ?> value="by-preload"><?php esc_html_e('Grouped by preloaded or not-preloaded status', 'wp-asset-clean-up'); ?></option>
            <option <?php if ($value === 'by-parents') { echo 'selected="selected"'; } ?> value="by-parents"><?php esc_html_e('Grouped by dependencies: Parents, Children, Independent', 'wp-asset-clean-up'); ?></option>
            <option <?php if ($value === 'by-loaded-unloaded') { echo 'selected="selected"'; } ?> value="by-loaded-unloaded"><?php esc_html_e('Grouped by loaded or unloaded status', 'wp-asset-clean-up'); ?></option>
            <option <?php if ($value === 'by-size') { echo 'selected="selected"'; } ?> value="by-size"><?php esc_html_e('Grouped by their size (sorted in descending order)', 'wp-asset-clean-up'); ?></option>
            <option <?php if ($value === 'by-rules') { echo 'selected="selected"'; } ?> value="by-rules"><?php esc_html_e('Grouped by having at least one rule &amp; no rules', 'wp-asset-clean-up'); ?></option>
            <option <?php if (in_array($value, array('two-lists', 'default'))) { echo 'selected="selected"'; } ?> value="two-lists"><?php esc_html_e('All enqueued CSS, followed by all enqueued JavaScript', 'wp-asset-clean-up'); ?></option>
            <option <?php if ($value === 'all') { echo 'selected="selected"'; } ?> value="all"> <?php esc_html_e('All enqueues in one list', 'wp-asset-clean-up'); ?></option>
        </select>
        <?php
        return ob_get_clean();
    }

    /**
     * Return the optimization features that can be skipped on matching front-end requests.
     *
     * The list is shared by the initial page render and AJAX-created exception rows so the
     * interface cannot drift from the settings logic used by Lite/Pro extensions.
     *
     * @return array
     */
    public static function getNoLoadFeaturesOptionsGroups()
    {
        $allFeaturesSelectOptionsGroups = array(
            __('CSS &amp; Fonts', 'wp-asset-clean-up') => array(
                'minify_css'               => __('Minify CSS', 'wp-asset-clean-up'),
                'inline_css'               => __('Inline CSS', 'wp-asset-clean-up'),
                'combine_css'              => __('Combine CSS', 'wp-asset-clean-up'),
                'critical_css'             => __('Critical CSS', 'wp-asset-clean-up'),
                'cache_dynamic_loaded_css' => __('Cache Dynamic Loaded CSS', 'wp-asset-clean-up'),

                'local_fonts_display'      => __('Local Fonts: "font-display" update', 'wp-asset-clean-up'),
                'local_fonts_preload'      => __('Local Fonts: Manual preload (legacy)', 'wp-asset-clean-up'),

                'google_fonts_combine'     => __('Google Fonts: Combine', 'wp-asset-clean-up'),
                'google_fonts_display'     => __('Google Fonts: "font-display" update', 'wp-asset-clean-up'),
                'google_fonts_preconnect'  => __('Google Fonts: Preconnect', 'wp-asset-clean-up'),
                'google_fonts_preload'     => __('Google Fonts: Preload', 'wp-asset-clean-up'),
                'google_fonts_remove'      => __('Google Fonts: Remove', 'wp-asset-clean-up')
            ),

            __('JavaScript', 'wp-asset-clean-up') => array(
                'minify_js'                        => __('Minify JavaScript', 'wp-asset-clean-up'),
                'combine_js'                       => __('Combine JavaScript', 'wp-asset-clean-up'),
                'move_inline_jquery_after_src_tag' => __('Move jQuery Inline Code After jQuery library', 'wp-asset-clean-up'),
                'cache_dynamic_loaded_js'          => __('Cache Dynamic Loaded JavaScript', 'wp-asset-clean-up')
            )
        );

        return apply_filters(
            'wpacu_settings_admin_no_load_features_options_groups',
            $allFeaturesSelectOptionsGroups
        );
    }

    /**
     * @param array $data
     * @param array $setValues
     * @param bool  $initializeEnhancedSelect
     *
     * @return false|string
     */
    public static function generateNewRuleNoFeatureAreaRow($data, $setValues = array('pattern' => '', 'list' => array()), $initializeEnhancedSelect = false)
    {
        ob_start();

        $uniqueId = str_replace('.', '', uniqid('wpacu_rule_', true));
        $useEnhancedInputs = Settings::useEnhancedInputs($data);
        $enhancedSelectClass = $initializeEnhancedSelect
            ? 'wpacu_chosen_select'
            : 'wpacu_chosen_can_be_later_enabled';

        $patternValue = isset($setValues['pattern']) ? trim((string)$setValues['pattern']) : '';
        $selectedList = isset($setValues['list']) && is_array($setValues['list'])
            ? array_values($setValues['list'])
            : array();

        $allFeaturesSelectOptionsGroups = self::getNoLoadFeaturesOptionsGroups();
        $isComplete = $patternValue !== '' && ! empty($selectedList);

        if ($isComplete) {
            $statusText = __('Ready', 'wp-asset-clean-up');
            $statusClass = 'is-ready';
            $summaryText = sprintf(
                _n('%s feature will be skipped on matching pages.', '%s features will be skipped on matching pages.', count($selectedList), 'wp-asset-clean-up'),
                number_format_i18n(count($selectedList))
            );
        } elseif ($patternValue !== '') {
            $statusText = __('Choose features', 'wp-asset-clean-up');
            $statusClass = 'is-incomplete';
            $summaryText = __('The URL pattern is set; choose at least one feature to skip.', 'wp-asset-clean-up');
        } elseif (! empty($selectedList)) {
            $statusText = __('Add a pattern', 'wp-asset-clean-up');
            $statusClass = 'is-incomplete';
            $summaryText = __('Features are selected; add the page URL pattern where they should be skipped.', 'wp-asset-clean-up');
        } else {
            $statusText = __('Not configured', 'wp-asset-clean-up');
            $statusClass = 'is-empty';
            $summaryText = __('Add a URL pattern and select one or more features.', 'wp-asset-clean-up');
        }
        ?>
        <article class="wpacu-page-exclusion-rule wpacu-prevent-feature-rule-area <?php echo esc_attr($statusClass); ?>"
                 data-wpacu-page-exclusion-rule>
            <header class="wpacu-page-exclusion-rule-header">
                <span class="wpacu-page-exclusion-rule-number" data-wpacu-rule-number aria-hidden="true">1</span>

                <span class="wpacu-page-exclusion-rule-heading">
                    <strong data-wpacu-rule-title>
                        <?php echo $patternValue !== ''
                            ? esc_html($patternValue)
                            : esc_html__('New targeted exception', 'wp-asset-clean-up'); ?>
                    </strong>
                    <span data-wpacu-rule-summary><?php echo esc_html($summaryText); ?></span>
                </span>

                <div class="wpacu-page-exclusion-rule-tools">
                    <span class="wpacu-page-exclusion-rule-status <?php echo esc_attr($statusClass); ?>"
                          data-wpacu-rule-status
                          aria-live="polite">
                        <?php echo esc_html($statusText); ?>
                    </span>

                    <button type="button"
                            class="wpacu-page-exclusion-remove-rule"
                            data-wpacu-remove-page-exclusion-rule
                            aria-label="<?php esc_attr_e('Remove this targeted exception', 'wp-asset-clean-up'); ?>">
                        <span class="wpacu-page-exclusion-remove-rule-icon" aria-hidden="true">
                            <svg viewBox="0 0 16 16" focusable="false">
                                <path d="M4 4l8 8M12 4l-8 8"></path>
                            </svg>
                        </span>
                        <span class="wpacu-page-exclusion-remove-rule-label"><?php esc_html_e('Remove', 'wp-asset-clean-up'); ?></span>
                    </button>
                </div>
            </header>

            <div class="wpacu-page-exclusion-rule-fields">
                <div class="wpacu-page-exclusion-field">
                    <label for="<?php echo esc_attr($uniqueId); ?>_pattern">
                        <?php esc_html_e('Page URL pattern', 'wp-asset-clean-up'); ?>
                    </label>
                    <input id="<?php echo esc_attr($uniqueId); ?>_pattern"
                           type="text"
                           class="wpacu-input-pattern-element wpacu-input-element wpacu-page-exclusion-pattern"
                           data-wpacu-page-exclusion-pattern
                           name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[do_not_load_plugin_features][<?php echo esc_attr($uniqueId); ?>][pattern]"
                           placeholder="<?php esc_attr_e('Example: /course/, {homepage}, or #/contact|/about#', 'wp-asset-clean-up'); ?>"
                           value="<?php echo esc_attr($patternValue); ?>" />
                    <p class="wpacu-page-exclusion-field-help">
                        <?php esc_html_e('Use a plain URL path, the special {homepage} value, or an explicit regular expression.', 'wp-asset-clean-up'); ?>
                    </p>
                </div>

                <div class="wpacu-page-exclusion-field wpacu-page-exclusion-field--features">
                    <label for="<?php echo esc_attr($uniqueId); ?>_features">
                        <?php esc_html_e('Features to skip', 'wp-asset-clean-up'); ?>
                    </label>
                    <select id="<?php echo esc_attr($uniqueId); ?>_features"
                            multiple="multiple"
                            name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[do_not_load_plugin_features][<?php echo esc_attr($uniqueId); ?>][list][]"
                            class="wpacu-input-element wpacu-page-exclusion-feature-select<?php if ($useEnhancedInputs) { echo ' ' . esc_attr($enhancedSelectClass); } ?>"
                            data-wpacu-page-exclusion-features
                            <?php if ($useEnhancedInputs) { ?>
                                data-placeholder="<?php esc_attr_e('Choose one or more features to skip', 'wp-asset-clean-up'); ?>"
                            <?php } ?>>
                        <?php foreach ($allFeaturesSelectOptionsGroups as $optionsGroup => $allFeaturesSelectOptions) { ?>
                            <optgroup label="<?php echo esc_attr($optionsGroup); ?>">
                                <?php foreach ($allFeaturesSelectOptions as $selectOptionValue => $selectOptionText) { ?>
                                    <option value="<?php echo esc_attr($selectOptionValue); ?>"
                                        <?php selected(in_array($selectOptionValue, $selectedList, true)); ?>>
                                        <?php echo esc_html(wp_strip_all_tags($selectOptionText)); ?>
                                    </option>
                                <?php } ?>
                            </optgroup>
                        <?php } ?>
                    </select>
                    <p class="wpacu-page-exclusion-field-help" data-wpacu-selected-features-count>
                        <?php
                        echo esc_html(
                            empty($selectedList)
                                ? __('No features selected yet.', 'wp-asset-clean-up')
                                : sprintf(
                                    _n('%s feature selected.', '%s features selected.', count($selectedList), 'wp-asset-clean-up'),
                                    number_format_i18n(count($selectedList))
                                )
                        );
                        ?>
                    </p>
                </div>
            </div>

            <div class="wpacu-page-exclusion-rule-effect" data-wpacu-rule-effect>
                <span class="dashicons dashicons-randomize" aria-hidden="true"></span>
                <span>
                    <strong><?php esc_html_e('Effect on matching pages:', 'wp-asset-clean-up'); ?></strong>
                    <span data-wpacu-rule-effect-copy>
                        <?php esc_html_e('Asset CleanUp keeps running; only the selected features are skipped.', 'wp-asset-clean-up'); ?>
                    </span>
                </span>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }


    /**
     * @param $for
     *
     * @return array
     */
    public static function getSubTabsConfig($for = 'all')
    {
        $data = array(
            'sub_tabs' => array()
        );

        // "Settings" --- "Plugin Usage Preferences" (sub-tabs)
        $tabKey = 'wpacu-setting-plugin-usage-settings';

        if (in_array($for, array($tabKey, 'all'))) {
            $data['sub_tabs'][$tabKey] = array(
                'wpacu-plugin-usage-settings-assets-management' => array(
                    'label'        => 'CSS/JS Manager',
                    'include_path' => '{local_template_dir}/_{for}/_assets-management.php'
                ),

                'wpacu-plugin-usage-settings-plugins-manager' => array(
                    'label'        => 'Plugins Manager',
                    'include_path' => '{local_template_dir}/_{for}/_plugins-manager.php'
                ),

                'wpacu-plugin-usage-settings-accessibility' => array(
                    'label'        => 'Interface',
                    'include_path' => '{local_template_dir}/_{for}/_accessibility.php'
                ),
                'wpacu-plugin-usage-settings-visibility' => array(
                    'label'        => 'Menu Visibility',
                    'include_path' => '{local_template_dir}/_{for}/_visibility.php'
                ),
                'wpacu-plugin-usage-settings-analytics' => array(
                    'label'        => 'Analytics',
                    'include_path' => '{local_template_dir}/_{for}/_analytics.php'
                ),
                'wpacu-plugin-usage-settings-announcements' => array(
                    'label'        => 'Announcements',
                    'include_path' => '{local_template_dir}/_{for}/_announcements.php'
                ),
                'wpacu-plugin-usage-settings-no-load-on-specific-pages' => array(
                    'label'        => 'Page Exclusions',
                    'include_path' => '{local_template_dir}/_{for}/_no-load-on-specific-pages.php'
                ),
                'wpacu-plugin-usage-settings-access' => array(
                    'label'        => 'Access Control',
                    'include_path' => '{local_template_dir}/_{for}/_access.php'
                )
            );

            // Delegated plugin managers can use the settings they were granted,
            // but only administrators may inspect or change access assignments.
            if ( ! current_user_can(Menu::$defaultAccessRole)) {
                unset($data['sub_tabs'][$tabKey]['wpacu-plugin-usage-settings-access']);
            }
        }

        // "Settings" --- "Resource Loading" (sub-tabs)
        $tabKey = 'wpacu-setting-resource-loading';

        if (in_array($for, array($tabKey, 'all'))) {
            $data['sub_tabs'][$tabKey] = array(
                'wpacu-resource-loading-image-attr' => array(
                    'label'        => 'Image Attributes',
                    'include_path' => '{local_template_dir}/_{for}/_image-attr.php'
                ),
                'wpacu-resource-loading-image-lazy-load' => array(
                    'label'        => 'Lazy Load',
                    'include_path' => '{local_template_dir}/_{for}/_image-lazy-load.php'
                )
            );
        }

        // "Settings" --- "Google Fonts" (sub-tabs)
        $tabKey = 'wpacu-setting-google-fonts';

        if (in_array($for, array($tabKey, 'all'))) {
            $data['sub_tabs'][$tabKey] = array(
                'wpacu-google-fonts-optimize' => array(
                    'label'        => 'Optimize Font Delivery',
                    'include_path' => '{local_template_dir}/_{for}/_optimize-area.php'
                ),
                'wpacu-google-fonts-remove' => array(
                    'label'        => 'Remove All',
                    'include_path' => '{local_template_dir}/_{for}/_remove-area.php'
                )
            );
        }

        $maybeSelectedSubTabArea = isset($_REQUEST['wpacu_selected_sub_tab_area']) ? $_REQUEST['wpacu_selected_sub_tab_area'] : '';

        foreach ($data['sub_tabs'] as $tabKey => $subTabsValues) {
            if (isset($subTabsValues[$maybeSelectedSubTabArea])) {
                $data['sub_tabs'][$tabKey][$maybeSelectedSubTabArea]['selected'] = true;
                $data['sub_tab_selected'] = $maybeSelectedSubTabArea;
                break;
            }
        }

        return $data;
    }

    /**
     * @param $localTemplateDir
     * @param $data - this one is used inside the included file (sub-tab area)
     * @param $for
     *
     * @return void
     */
    public static function printSubTabsOutput($localTemplateDir, $data, $localTemplateFile = '')
    {
        if ($localTemplateFile === '') {
            echo 'Error: No local template file specified!';
            return;
        }

        $cleanFileName = ltrim(rtrim(basename($localTemplateFile), '.php'), '_');

        // "Settings" --- "Plugin Usage Preferences" (sub-tabs)
        if ($cleanFileName === 'plugin-usage-settings') {
            $tabKey = 'wpacu-setting-plugin-usage-settings';
        }

        // "Settings" --- "Resource Loading" (sub-tabs)
        elseif ($cleanFileName === 'resource-loading') {
            $tabKey = 'wpacu-setting-resource-loading';
        }

        // "Settings" --- "Google Fonts" (sub-tabs)
        elseif ($cleanFileName === 'fonts-google') {
            $tabKey = 'wpacu-setting-google-fonts';
        } else {
            return;
        }

        $subTabsConfig = self::getSubTabsConfig($tabKey);
        $subTabsConfigList = $subTabsConfig['sub_tabs'];

        foreach ($subTabsConfigList[$tabKey] as $subTabKey => $subTabValues) {
            $reps = array(
                '{local_template_dir}' => $localTemplateDir,
                '{for}'                => $cleanFileName
            );

            $subTabsConfigList[$tabKey][$subTabKey]['include_path'] = str_replace(
                array_keys($reps),
                array_values($reps),
                $subTabValues['include_path']
            );
        }

        $subTabs = $subTabsConfigList[$tabKey];

        if (empty($subTabs)) {
            return;
        }

        /*
        * Determine any already selected sub-tab
        */
        $selectedSubTabArea = key($subTabs); // First one by default

        foreach ($subTabs as $subTabKey => $subTabData) {
            if (isset($subTabData['selected']) && $subTabData['selected']) {
                $selectedSubTabArea = $subTabKey;
                break;
            }
        }

        $menuVisibilityChanged = ! empty($data['hide_from_admin_bar'])
            || ! empty($data['hide_from_side_bar']);
        $accessControlChanged = ! empty($data['access_via_non_admin_user_roles'])
            || ! empty($data['access_via_specific_non_admin_users']);

        ?>
        <div id="wpacu-settings-admin-sub-tabs-wrap" class="wpacu-sub-tabs-wrap wpacu-tabs-not-ready"> <!-- Sub-tabs wrap -->
            <!-- Sub-nav menu -->
            <?php foreach ($subTabs as $subTabArea => $subTabData) { ?>
                <input class="wpacu-nav-input wpacu-nav-input-sub-tab-area"
                       id="<?php echo esc_attr($subTabArea); ?>-tab-item"
                       type="radio"
                       name="<?php echo esc_attr($tabKey); ?>"
                       value="<?php echo esc_attr($subTabArea); ?>"
                       <?php if ($selectedSubTabArea === $subTabArea) { ?>checked="checked"<?php } ?> />
                <label class="wpacu-nav-label"
                       for="<?php echo esc_attr($subTabArea); ?>-tab-item"><?php
                    echo esc_html($subTabData['label']);

                    if ($subTabArea === 'wpacu-plugin-usage-settings-visibility') {
                        ?><span id="wpacu-menu-visibility-sub-tab-indicator"
                                class="wpacu-sub-tab-changed-indicator wpacu-is-attention<?php echo $menuVisibilityChanged ? ' is-visible' : ''; ?>"
                                aria-hidden="true"></span><?php
                    }

                    if ($subTabArea === 'wpacu-plugin-usage-settings-access') {
                        ?><span id="wpacu-access-control-sub-tab-indicator"
                                class="wpacu-sub-tab-changed-indicator wpacu-is-success<?php echo $accessControlChanged ? ' is-visible' : ''; ?>"
                                aria-hidden="true"></span><?php
                    }
                ?></label>
            <?php } ?>
            <!-- /Sub-nav menu -->

            <?php
            foreach ($subTabs as $subTabArea => $subTabData) {
                //echo "($selectedSubTabArea === $subTabArea)";
            ?>
                <section class="wpacu-sub-tabs-item <?php if ($selectedSubTabArea === $subTabArea) { echo 'wpacu-visible'; } ?>"
                         id="<?php echo esc_attr($subTabArea); ?>-tab-item-area">
                    <?php include_once $subTabData['include_path']; ?>
                </section>
            <?php } ?>
        </div> <!-- /Sub-tabs wrap -->
        <?php
    }

    /**
     * @return void
     */
    public function updateSettingsInDbWithDefaultValues()
    {
        $settingsClass = new Settings();
        $settingsDefaultValues = $settingsClass->defaultSettings;

        $this->update($settingsDefaultValues, false);
    }

    /**
     *
     */
    public function redirectAfterUpdate()
    {
        $tabArea    = Misc::getVar('post', 'wpacu_selected_tab_area', 'wpacu-setting-plugin-usage-settings');
        $subTabArea = Misc::getVar('post', 'wpacu_selected_sub_tab_area', '');

        set_transient(WPACU_PLUGIN_ID . '_settings_updated', 1, 30);

        $wpacuQueryString = array(
            'page' => 'wpassetcleanup_settings',
            'wpacu_selected_tab_area' => $tabArea,
            'wpacu_time' => time()
        );

        if ($subTabArea) {
            $wpacuQueryString['wpacu_selected_sub_tab_area'] = $subTabArea;
        }

        wp_redirect(add_query_arg($wpacuQueryString, esc_url(admin_url('admin.php'))));
        exit();
    }

    /**
     * @param $unloadsList
     */
    public function updateSiteWideRuleForCommonAssets($unloadsList)
    {
        $wpacuUpdate = new Update;

        $disableGutenbergCssBlockLibrary = $unloadsList['wp_block_library'];
        $disableJQueryMigrate            = $unloadsList['jquery_migrate'];
        $disableCommentReply             = $unloadsList['comment_reply'];
        $disableDashiconsForGuests       = $unloadsList['dashicons'];

        /*
         * Add element(s) to the global unload rules
         */
        if ($disableGutenbergCssBlockLibrary || $disableDashiconsForGuests) {
            $unloadList = array();

            if ($disableGutenbergCssBlockLibrary) {
                $unloadList[] = 'wp-block-library';
            }

            if ($disableDashiconsForGuests) {
                $unloadList[] = 'dashicons';
            }

            $wpacuUpdate->saveToEverywhereUnloads($unloadList);
        }

        if ($disableJQueryMigrate || $disableCommentReply) {
            $unloadList = array();

            // Add jQuery Migrate to the global unload rules
            if ($disableJQueryMigrate) {
                $unloadList[] = 'jquery-migrate';
            }

            // Add Comment Reply to the global unload rules
            if ($disableCommentReply) {
                $unloadList[] = 'comment-reply';
            }

            $wpacuUpdate->saveToEverywhereUnloads(array(), $unloadList);
        }

        /*
         * Remove element(s) from the global unload rules
         */

        // For Stylesheets (.CSS)
        if (! $disableGutenbergCssBlockLibrary || ! $disableDashiconsForGuests) {
            $removeFromUnloadList = array();

            if (! $disableGutenbergCssBlockLibrary) {
                $removeFromUnloadList['wp-block-library'] = 'remove';
            }

            if (! $disableDashiconsForGuests) {
                $removeFromUnloadList['dashicons'] = 'remove';
            }

            $wpacuUpdate->removeEverywhereUnloads($removeFromUnloadList);
        }

        // For JavaScript (.JS)
        if (! $disableJQueryMigrate || ! $disableCommentReply) {
            $removeFromUnloadList = array();

            // Remove jQuery Migrate from global unload rules
            if (! $disableJQueryMigrate) {
                $removeFromUnloadList['jquery-migrate'] = 'remove';
            }

            // Remove Comment Reply from global unload rules
            if (! $disableCommentReply) {
                $removeFromUnloadList['comment-reply'] = 'remove';
            }

            $wpacuUpdate->removeEverywhereUnloads(array(), $removeFromUnloadList);
        }
    }

    /**
     * @param $settings
     * @param false $doSettingUpdate (e.g. 'true' if called from a WP Cron)
     * @param false $isDebug (e.g. 'true' if requested via a query string such as 'wpacu_toggle_inline_code_to_combine_js' for debugging purposes)
     *
     * @return mixed
     */
    public static function toggleAppendInlineAssocCodeHiddenSettings($settings, $doSettingUpdate = false, $isDebug = false)
    {
        // Are there too many files in WP_CONTENT_DIR . WpAssetCleanUp\OptimiseAssets\OptimizeCommon::getRelPathPluginCacheDir() . '(css|js)/' directory?
        // Deactivate the appending of the inline CSS/JS code (extra, before or after)
        $mbLimitForFiles = array(
            'css' => 700,
            'js'  => 700 // This is the one that usually has non-unique inline JS code
        );

        foreach ( $mbLimitForFiles as $assetType => $mbLimit ) { // Go through both .css and .js
            $combineSettingsKey = 'combine_loaded_'.$assetType;
            $isCombineAssetsEnabled = isset($settings[$combineSettingsKey]) && $settings[$combineSettingsKey];

            if ( ! $isCombineAssetsEnabled ) {
                if ($isDebug) {
                    echo 'Combine '.strtoupper($assetType).' is not enabled.<br />';
                }
                continue; // Only do the checking if combine CSS/JS is enabled
            }

            $wpacuPathToCombineDirSize = Misc::getSizeOfDirectoryRootFiles(
                array(
                    WP_CONTENT_DIR . OptimizeCommon::getRelPathPluginCacheDir() . $assetType . '/',
                    WP_CONTENT_DIR . OptimizeCommon::getRelPathPluginCacheDir() . $assetType . '/logged-in/' // just in case "Apply it for all visitors (not recommended)" has been enabled
                ),
                '.' . $assetType
            );

            $preventAddingInlineCodeToCombinedAssets = isset( $wpacuPathToCombineDirSize['total_size_mb'] ) && $wpacuPathToCombineDirSize['total_size_mb'] > $mbLimit;

            if ( $preventAddingInlineCodeToCombinedAssets ) {
                $settings['_combine_loaded_'.$assetType.'_append_handle_extra'] = '';
            } else {
                $settings['_combine_loaded_'.$assetType.'_append_handle_extra'] = 1;
            }

            if ($isDebug) {
                if ($preventAddingInlineCodeToCombinedAssets) {
                    echo 'Adding inline code to combined '.strtoupper($assetType).' has been deactivated as the total size of combined assets is '.$wpacuPathToCombineDirSize['total_size_mb'].' MB.<br />';
                } else {
                    echo 'Adding inline code to combined '.strtoupper($assetType).' has been (re)activated as the total size of combined assets is '.$wpacuPathToCombineDirSize['total_size_mb'].' MB.<br />';
                }
            }

            if ($doSettingUpdate) {
                $settingsAdminClass = new self();
                $settingsAdminClass->updateOption(
                    '_combine_loaded_'.$assetType.'_append_handle_extra',
                    $settings['_combine_loaded_'.$assetType.'_append_handle_extra']
                );
            }
        }

        return $settings;
    }
}
