<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\Menu;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;
use WpAssetCleanUp\OptimiseAssets\OptimizeCss;
use WpAssetCleanUp\OptimiseAssets\OptimizeJs;
use WpAssetCleanUp\Settings;
use WpAssetCleanUp\Update;

/**
 * Class SettingsAdmin
 * @package WpAssetCleanUp
 */
class SettingsAdmin
{
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
        add_action( 'wp_ajax_nopriv_' . WPACU_PLUGIN_ID . '_update_settings', array($this, 'ajaxUpdateSpecificSettings') );

        // "Settings" -- "Plugin Usage Preferences" -- "Prevent features of Asset CleanUp Pro from triggering on certain pages" -- "Add New Rule"
        add_action( 'wp_ajax_' . WPACU_PLUGIN_ID . '_add_new_no_features_load_row', array($this, 'ajaxAddNewNoFeaturesLoadRow') );
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
        if (! Misc::getVar('post', 'wpacu_settings_nonce')) {
            return;
        }

        check_admin_referer('wpacu_settings_update', 'wpacu_settings_nonce');

        $savedSettings = Misc::getVar('post', WPACU_PLUGIN_ID . '_settings', array());
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

        foreach ($settings as $settingKey => $settingValue) {
            if ( $settingKey === 'announcements' ) {
                if ( isset($settingValue['global']['never_show_any']) && (int)$settingValue['global']['never_show_any'] === 0 ) {
                    unset($settings['announcements']['global']['never_show_any']);
                }

                if ( ! empty($settingValue['list']) ) {
                    foreach ( $settingValue['list'] as $annId => $annStates ) {
                        if ( ! empty($annStates)) {
                            foreach ( $annStates as $annState => $annStateValue ) {
                                if ( $annState === 'seen' && isset($settingValue['list'][$annId]['snoozed']) && $settingValue['list'][$annId]['snoozed'] ) {
                                    // If it's marked as "seen", there's no point in having it "snoozed"
                                    unset($settings['announcements']['list'][$annId]['snoozed']);
                                }
                            }
                        }
                    }
                }
            }

            if ($settingValue !== '') {
                // Some validation
                if ($settingKey === 'clear_cached_files_after') {
                    $settingValue = (int)$settingValue;
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

        // The following are only triggered IF the user submitted the form from "Settings" area
        if (Misc::getVar('post', 'wpacu_settings_nonce')) {
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

            // "Site-Wide Common Unloads" tab
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
                'minify_loaded_js_exceptions',
                'combine_loaded_js_exceptions',
                'cdn_rewrite_url_css',
                'cdn_rewrite_url_js',
                'remove_html_comments_exceptions',
                'local_fonts_preload_files',
                'google_fonts_preload_files'
            );

            foreach ($stripTagsForList as $stripTagsFor) {
                $settings[$stripTagsFor] = strip_tags($settings[$stripTagsFor]);
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

            // [Only for admins]
            SettingsAdminOnlyForAdmin::filterSettingsOnFormSubmit();
            // [/Only for admins]

            // Pro: v1.2.4.2 | Lite: v1.3.9.4
            if ( ! empty($settings['do_not_load_plugin_features']) ) {
                $settings['do_not_load_plugin_features'] = Misc::filterList($settings['do_not_load_plugin_features']);
                if ( ! empty($settings['do_not_load_plugin_features']) ) {
                    foreach ($settings['do_not_load_plugin_features'] as $rowKey => $setValues) {
                        if (empty($setValues['pattern']) || empty($setValues['list'])) {
                            unset($settings['do_not_load_plugin_features'][$rowKey]);
                        }
                    }
                }

                }
        }

        $addUpdateStatus = Misc::addUpdateOption(WPACU_PLUGIN_ID . '_settings', wp_json_encode(Misc::filterList($settings)));

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
     * @param $key
     * @param $value
     *
     * @return bool|void
     */
    public function updateOption($key, $value)
    {
        $settingsClass = new Settings();
        $settings = $settingsClass->getAll(true);

        if ( ! is_array($key) ) { // not an array (e.g. a string)
            $settings[ $key ] = $value;

            if ( ! in_array($key, $settingsClass->settingsKeys) ) {
                // The setting does not exist; Stop here!
                return;
            }
        } else {
            foreach ($key as $keyIndex => $keyValue) { // Array, loop through it

                if ( ! in_array($key, $settingsClass->settingsKeys) ) {
                    // The setting does not exist; Skip this one from the array
                    continue;
                }

                $settings[ $keyValue ] = $value[$keyIndex];
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
        $settings = $settingsClass->getAll(true);

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
        if (Misc::getVar('get', 'wpacu_selected_tab_area') && get_transient(WPACU_PLUGIN_ID . '_settings_updated')) {
            delete_transient(WPACU_PLUGIN_ID . '_settings_updated');
            ?>
            <div class="notice notice-success is-dismissible">
                <p><span class="dashicons dashicons-yes"></span> <?php _e('The settings were successfully updated.', 'wp-asset-clean-up'); ?></p>
            </div>
            <?php
        }
    }

    /**
     *
     */
    public function ajaxDoVerifications()
    {
        if ( ! isset($_POST['action']) || ! Menu::userCanAccessAssetCleanUp() ) {
            return;
        }

        if ( ! isset($_POST['wpacu_nonce']) ) {
            echo 'Error: The security nonce was not sent for verification. Location: '.__METHOD__;
            return;
        }

        if ( ! wp_verify_nonce($_POST['wpacu_nonce'], 'wpacu_do_verifications') ) {
            echo 'Error: The security check has failed. Location: '.__METHOD__;
            return;
        }

        $result = array();

        $ch = curl_init();

        $curlParams = array(
            CURLOPT_URL            => get_site_url(),
            CURLOPT_HEADER         => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true
        );

        if (defined('CURLOPT_HTTP_VERSION') && defined('CURL_HTTP_VERSION_2_0')) {
            // cURL will attempt to make an HTTP/2.0 request (can downgrade to HTTP/1.1)))
            $curlParams[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2_0;
        }

        curl_setopt_array($ch, $curlParams);

        $response = curl_exec($ch);

        if (! $response) {
            echo curl_error($ch); // something else happened causing the request to fail
        }

        if (strncmp($response, 'HTTP/2', 6) === 0) {
            $result['has_http2'] = '1'; // Has HTTP/2 Support
        }

        if ((strpos($response, 'cf-cache-status:') !== false) &&
            (strpos($response, 'cf-request-id:') !== false) &&
            (strpos($response, 'cf-ray:') !== false)) {
            $result['uses_cloudflare'] = '1'; // Uses Cloudflare
        }

        curl_close($ch);

        echo wp_json_encode($result);
        exit();
    }

    /**
     *
     */
    public function ajaxUpdateSpecificSettings()
    {
        // Option: "On Assets List Layout Load, keep the groups:"
        if (isset($_POST['wpacu_update_keep_the_groups'])) {
            if ( ! isset( $_POST['action'], $_POST['wpacu_keep_the_groups_state'] ) || ! Menu::userCanAccessAssetCleanUp() ) {
                return;
            }

            if ( $_POST['wpacu_update_keep_the_groups'] !== 'yes' ) {
                return;
            }

            if ( ! isset($_POST['wpacu_nonce']) ) {
                echo 'Error: The security nonce was not sent for verification. Location: '.__METHOD__;
                return;
            }

            if ( ! wp_verify_nonce($_POST['wpacu_nonce'], 'wpacu_update_specific_settings_nonce') ) {
                echo 'Error: The nonce security check has failed. Location: '.__METHOD__;
                return;
            }

            $newKeepTheGroupsState = $_POST['wpacu_keep_the_groups_state'];

            $this->updateOption( 'assets_list_layout_areas_status', $newKeepTheGroupsState );

            echo 'done';
        }

        exit();
    }

    /**
     * @return void
     */
    public function ajaxAddNewNoFeaturesLoadRow()
    {
        if ( ! isset( $_POST['action'] ) || ($_POST['action'] !== WPACU_PLUGIN_ID . '_add_new_no_features_load_row') ||
             ! Menu::userCanAccessAssetCleanUp() ) {
            exit();
        }

        $settingsClass = new Settings();
        $settingsData = $settingsClass->getAll();

        echo self::generateNewRuleNoFeatureAreaRow($settingsData);
        exit();
    }

    /**
     *
     */
    public function adminFooterVerifyHttp2Protocol()
    {
        if ( ! (defined('CURLOPT_HTTP_VERSION') && defined('CURL_HTTP_VERSION_2_0')) ) {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('.wpacu_verify_http2_protocol').removeClass('wpacu_hide');
                });
            </script>
            <?php
            return; // Stop here! "CURL_HTTP_VERSION_2_0" constant has to be defined
        }
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                    action: '<?php echo WPACU_PLUGIN_ID; ?>_do_verifications',
                    wpacu_nonce: '<?php echo wp_create_nonce('wpacu_do_verifications'); ?>'
                }, function (obj) {
                    let result = jQuery.parseJSON(obj);
                    console.log(result);

                    if (result.has_http2 === '1') {
                        $('.wpacu-combine-notice-http-2-detected').removeClass('wpacu_hide');
                    } else {
                        $('.wpacu-combine-notice-default').removeClass('wpacu_hide');
                    }

                    if (result.uses_cloudflare === '1') {
                        $('#wpacu-site-uses-cloudflare').show();
                    }
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
     * @param $data
     * @param $setValues
     *
     * @return false|string
     */
    public static function generateNewRuleNoFeatureAreaRow($data, $setValues = array('pattern' => '', 'list' => array()))
    {
        ob_start();

        $uniqueId = uniqid('', true);

        $allFeaturesSelectOptionsGroups = array(
            __('CSS &amp; Fonts', 'wp-asset-clean-up') => array(
                'minify_css'                       => __('Minify CSS', 'wp-asset-clean-up'),
                'inline_css'                       => __('Inline CSS', 'wp-asset-clean-up'),
                'combine_css'                      => __('Combine CSS', 'wp-asset-clean-up'),
                'critical_css'                     => __('Critical CSS', 'wp-asset-clean-up'),
                'cache_dynamic_loaded_css'         => __('Cache Dynamic Loaded CSS', 'wp-asset-clean-up'),

                'local_fonts_display'              => __('Local Fonts: "font-display" update', 'wp-asset-clean-up'),
                'local_fonts_preload'              => __('Local Fonts: Preload', 'wp-asset-clean-up'),

                'google_fonts_combine'             => __('Google Fonts: Combine', 'wp-asset-clean-up'),
                'google_fonts_display'             => __('Google Fonts: "font-display" update', 'wp-asset-clean-up'),
                'google_fonts_preconnect'          => __('Google Fonts: Preconnect', 'wp-asset-clean-up'),
                'google_fonts_preload'             => __('Google Fonts: Preload', 'wp-asset-clean-up'),
                'google_fonts_remove'              => __('Google Fonts: Remove', 'wp-asset-clean-up')
            ),

            __('JavaScript', 'wp-asset-clean-up') => array(
                'minify_js'                        => __('Minify JavaScript', 'wp-asset-clean-up'),
                'combine_js'                       => __('Combine JavaScript', 'wp-asset-clean-up'),
                'move_inline_jquery_after_src_tag' => __('Move jQuery Inline Code After jQuery library', 'wp-asset-clean-up'),
                'cache_dynamic_loaded_js'          => __('Cache Dynamic Loaded JavaScript', 'wp-asset-clean-up')
            )
        );
        ?>
        <div class="wpacu-prevent-feature-rule-area">
            &nbsp;<strong>When URI contains/matches:</strong>&nbsp;

            <input type="text"
                   class="wpacu-input-pattern-element wpacu-input-element"
                   name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[do_not_load_plugin_features][<?php echo $uniqueId; ?>][pattern]"
                   placeholder="<?php esc_attr_e('String or RegEx', 'wp-asset-clean-up'); ?>"
                   value="<?php if ($setValues['pattern']) { echo esc_attr($setValues['pattern']); } ?>" />

            &nbsp;&#x2192;&nbsp;&nbsp;<strong>DO NOT load these features:</strong>&nbsp;

            <select multiple="multiple"
                    name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[do_not_load_plugin_features][<?php echo $uniqueId; ?>][list][]"
                    class="wpacu-input-element <?php if ($data['input_style'] !== 'standard') { ?> wpacu_chosen_can_be_later_enabled <?php } ?>"
                <?php if ($data['input_style'] !== 'standard') { ?>
                    data-placeholder="<?php esc_attr_e('Choose the features to prevent from loading', 'wp-asset-clean-up'); ?>..."
                <?php } ?>
                    style="min-width: 300px;">
                <?php
                foreach ($allFeaturesSelectOptionsGroups as $optionsGroup => $allFeaturesSelectOptions) { ?>
                    <optgroup label="<?php echo esc_attr($optionsGroup); ?>">
                        <?php
                        foreach ($allFeaturesSelectOptions as $selectOptionValue => $selectOptionText) {
                            ?>
                            <option <?php if (in_array($selectOptionValue, $setValues['list'])) { echo 'selected="selected"'; } ?> value="<?php echo esc_attr($selectOptionValue); ?>"><?php echo $selectOptionText; ?></option>
                            <?php
                        }
                        ?>
                    </optgroup>
                    <?php
                }
                ?>
            </select> <a class="wpacu-delete-no-features-rule-row" href="#">&nbsp;<span class="dashicons dashicons-minus"></span> Clear Rule&nbsp;</a>&nbsp;&nbsp;<a class="wpacu-add-new-no-features-rule-row" href="#"><span class="dashicons dashicons-plus-alt"></span> Add New Rule</a> <span class="wpacu-add-new-no-features-rule-row-loader"><img src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" width="20" height="20" alt="" /></span>
        </div>
        <?php
        return ob_get_clean();
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
