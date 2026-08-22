<?php
namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\Maintenance;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\MiscArray;
use WpAssetCleanUp\Regex;
use WpAssetCleanUp\Update;

/**
 *
 * Class OverviewEditUpdate
 *
 * Process form submit in the Overview area (edit mode)
 *
 * @package WpAssetCleanUp
 */
class OverviewEditUpdate
{
    /**
     * @var Update
     */
    public $update;

    /**
     *
     */
    public function __construct()
    {
        $this->update = new Update();

        if (isset($_POST['wpacu-main-edit-form-submit']) && $_POST['wpacu-main-edit-form-submit']) {
            $this->processMainEditForm();
        }

        add_action('admin_notices', array($this, 'renderOverviewEditAdminNotice'));
    }

    /**
     * @return void
     */
    public function processMainEditForm()
    {
        if ( ! current_user_can('manage_options') ) {
            return;
        }

        check_admin_referer('wpacu_overview_edit_form', 'wpacu_overview_edit_form_nonce');

        /*
         * [START] Clear Rules
         *
         * For: Handles,
         *      Plugins,
         *      Page Options,
         *      Critical CSS
         */
        $mainPostNameToClear = OverviewEdit::$mainPostNameToClear;

        $dataToClear = isset($_POST[$mainPostNameToClear]) ? $_POST[$mainPostNameToClear] : array();

        // Either cleared or edited
        $updatedRules = array(
            'cleared' => array(
                'handles'      => array(),
                'plugins'      => array(),
                'page_options' => array(),
                'critical_css' => array()
            ),
            'edited' => array(
                'handles'      => array()
            )
        );

        if ( ! empty($dataToClear['handle']) && is_array($dataToClear['handle'])) {
            $updatedRules['cleared'] = $this->processHandlesDataToClear($dataToClear['handle'], $updatedRules['cleared']);
        }

        if ( ! empty($dataToClear['plugin']) && is_array($dataToClear['plugin']) ) {
            $updatedRules['cleared'] = $this->processPluginsDataToClear($dataToClear['plugin'], $updatedRules['cleared']);
        }

        if ( ! empty($dataToClear['page_option']) && is_array($dataToClear['page_option']) ) {
            $updatedRules['cleared'] = $this->processPageOptionsDataToClear($dataToClear['page_option'], $updatedRules['cleared']);
        }

        if ( ! empty($dataToClear['critical_css']) && is_array($dataToClear['critical_css']) ) {
            $updatedRules['cleared'] = $this->processCriticalCssDataToClear($dataToClear['critical_css'], $updatedRules['cleared']);
        }
        /*
         * [END] Clear Rules
         */

        /*
         * [START] Edit Rules
         *
         * For: Handles (e.g. Media Query, Note, RegEx)
         *      Plugins (RegEx)
         */
        $mainPostNameToEdit = OverviewEdit::$mainPostNameToEdit;

        $dataToEdit = isset($_POST[$mainPostNameToEdit]) ? $_POST[$mainPostNameToEdit] : array();

        if ( isset($dataToEdit['handle']) && ! empty($dataToEdit['handle']) ) {
            $updatedRules = $this->processHandlesDataToEdit($updatedRules);
        }

        $updatedRules = apply_filters(
            'wpacu_internal_overview_edit_updated_rules',
            $updatedRules,
            $dataToEdit
        );

        $hasUpdatedRules = ! empty(
            array_filter($updatedRules, static function ( $ruleGroups ) {
                return ! empty( array_filter( $ruleGroups ) );
            })
        );

        if ( $hasUpdatedRules ) {
            set_transient(WPACU_PLUGIN_ID . '_overview_edit_updated_rules', $updatedRules, 30);
        }
        /*
         * [END] Edit Rules
         */

        wp_redirect(admin_url('admin.php?page=' . WPACU_PLUGIN_ID . '_overview&wpacu_edit_mode=1&wpacu_overview_edit_updated=1'));

        exit();
    }

    /**
     * @param $updatedRules
     *
     * @return array
     */
    public function processHandlesDataToEdit($updatedRules)
    {
        $mainPostNameToEdit = OverviewEdit::$mainPostNameToEdit;

        $dataToEdit = isset($_POST[$mainPostNameToEdit]['handle']) ? $_POST[$mainPostNameToEdit]['handle'] : array();

        if (empty($dataToEdit) || ! is_array($dataToEdit)) {
            return $updatedRules;
        }

        foreach ($dataToEdit as $assetType => $handlesData) {
            if ( ! in_array($assetType, array('styles', 'scripts'), true) || empty($handlesData) || ! is_array($handlesData) ) {
                continue;
            }

            foreach ($handlesData as $handle => $rulesData) {
                if (empty($rulesData) || ! is_array($rulesData)) {
                    continue;
                }

                $handle = sanitize_text_field(wp_unslash($handle));

                if ($handle === '') {
                    continue;
                }

                foreach ($rulesData as $ruleKey => $newValue) {
                    $ruleKey = sanitize_key($ruleKey);

                    if ( ! OverviewEdit::isEditableHandleRuleKey($ruleKey) ) {
                        continue;
                    }

                    if (is_array($newValue)) {
                        continue;
                    }

                    $newValue = wp_unslash($newValue);
                    $newValue = $this->cleanEditableHandleRuleValue($ruleKey, $newValue);

                    $oldValue = $this->getCurrentHandleRuleValue($assetType, $handle, $ruleKey);
                    $oldValue = $this->cleanEditableHandleRuleValue($ruleKey, $oldValue);

                    // Is the input/textarea empty? Clear the rule
                    if ($newValue === '') {
                        if ($oldValue === '') {
                            continue;
                        }

                        $isCleared = $this->clearSingleAssetRule($assetType, $handle, $ruleKey, wp_slash($oldValue));

                        if ($isCleared) {
                            $updatedRules['cleared']['handles'][] = array(
                                'asset_type' => $assetType,
                                'handle'     => $handle,
                                'rule_key'   => $ruleKey,
                                'values'     => array($oldValue)
                            );
                        }

                        continue;
                    }

                    if ($oldValue === $newValue) {
                        continue;
                    }

                    $isEdited = $this->editSingleHandleRule($assetType, $handle, $ruleKey, $oldValue, $newValue);

                    if ($isEdited) {
                        $updatedRules['edited']['handles'][] = array(
                            'asset_type' => $assetType,
                            'handle'     => $handle,
                            'rule_key'   => $ruleKey,
                            'old_value'  => $oldValue,
                            'new_value'  => $newValue
                        );
                    }
                }
            }
        }

        return $updatedRules;
    }

    /**
     * @param string $ruleKey
     *
     * @return string
     */
    private function getHandleRuleGlobalKey($ruleKey)
    {
        if ($ruleKey === 'note') {
            return 'notes';
        }

        $maybeGlobalKay = apply_filters('wpacu_internal_overview_edit_handle_rule_global_key', $ruleKey);

        if ($maybeGlobalKay !== $ruleKey) {
            return $maybeGlobalKay;
        }

        return $ruleKey;
    }

    /**
     * @param string $assetType
     * @param string $handle
     * @param string $ruleKey
     *
     * @return string
     */
    private function getCurrentHandleRuleValue($assetType, $handle, $ruleKey)
    {
        if ( ! in_array($assetType, array('styles', 'scripts'), true) || ! OverviewEdit::isEditableHandleRuleKey($ruleKey) ) {
            return '';
        }

        $globalKey = $this->getHandleRuleGlobalKey($ruleKey);

        $existingListJson = get_option(WPACU_PLUGIN_ID . '_global_data');
        $existingListData = Main::instance()->existingList($existingListJson, array(
            'styles'  => array(),
            'scripts' => array()
        ));

        $existingList = $existingListData['list'];

        if ($ruleKey === 'note') {
            return isset($existingList[$assetType][$globalKey][$handle])
                ? (string)$existingList[$assetType][$globalKey][$handle]
                : '';
        }

        return isset($existingList[$assetType][$globalKey][$handle]['value'])
            ? (string)$existingList[$assetType][$globalKey][$handle]['value']
            : '';
    }

    /**
     * @param string $ruleKey
     * @param string $value
     *
     * @return string
     */
    private function cleanEditableHandleRuleValue($ruleKey, $value)
    {
        $value = trim($value);

        $filteredValue = apply_filters(
            'wpacu_internal_overview_edit_clean_handle_rule_value',
            null,
            $ruleKey,
            $value
        );

        if (is_string($filteredValue)) {
            return $filteredValue;
        }

        if ($ruleKey === 'note') {
            return trim(wp_strip_all_tags($value));
        }

        return trim(wp_strip_all_tags($value)); // default
    }

    /**
     * @param string $assetType
     * @param string $handle
     * @param string $ruleKey
     * @param string $oldValue
     * @param string $newValue
     *
     * @return bool
     */
    private function editSingleHandleRule($assetType, $handle, $ruleKey, $oldValue, $newValue)
    {
        if ( ! in_array($assetType, array('styles', 'scripts'), true) || ! OverviewEdit::isEditableHandleRuleKey($ruleKey) ) {
            return false;
        }

        if ($newValue === '') {
            return false;
        }

        $globalKey = $this->getHandleRuleGlobalKey($ruleKey);

        $optionToUpdate   = WPACU_PLUGIN_ID . '_global_data';
        $existingListJson = get_option($optionToUpdate);

        $existingListData = Main::instance()->existingList($existingListJson, array(
            'styles'  => array($globalKey => array()),
            'scripts' => array($globalKey => array())
        ));

        $existingList = $existingListData['list'];

        if ($ruleKey === 'note') {
            if ( ! isset($existingList[$assetType][$globalKey][$handle]) ) {
                return false;
            }

            $currentValue = (string)$existingList[$assetType][$globalKey][$handle];
            $currentValue = $this->cleanEditableHandleRuleValue($ruleKey, $currentValue);

            if ($currentValue !== $oldValue || $currentValue === $newValue) {
                return false;
            }

            $existingList[$assetType][$globalKey][$handle] = $newValue;
        }

        if ($ruleKey !== 'note') {
            $existingListFilteredByHook = apply_filters(
                'wpacu_internal_overview_edit_single_handle_rule',
                false,
                $assetType,
                $handle,
                $ruleKey,
                $oldValue,
                $newValue,
                $globalKey,
                $existingList
            );

            if ( ! is_array($existingListFilteredByHook) ) {
                return false;
            }

            $existingList = $existingListFilteredByHook;
        }

        $existingListFiltered = MiscArray::filterList($existingList);
        $newJson              = wp_json_encode($existingListFiltered);

        Misc::addUpdateOption($optionToUpdate, $newJson);

        // Very important: keep the in-request cache in sync
        $GLOBALS['wpacu_global_data_json_decoded'] = $existingListFiltered;

        return true;
    }

    /**
     * @param $pluginsData
     * @param $clearedRules
     *
     * @return array
     */
    private function processPluginsDataToClear($pluginsData, $clearedRules)
    {
        if (empty($pluginsData) || ! is_array($pluginsData)) {
            return array();
        }

        foreach ($pluginsData as $pluginPath => $pluginRulesByView) {
            $pluginPath = wp_unslash($pluginPath);
            $pluginPath = sanitize_text_field($pluginPath);

            if (empty($pluginPath) || empty($pluginRulesByView) || ! is_array($pluginRulesByView)) {
                continue;
            }

            foreach (array('front', 'dash') as $loadLocation) {
                if (empty($pluginRulesByView[$loadLocation]) || ! is_array($pluginRulesByView[$loadLocation])) {
                    continue;
                }

                $rulesToClear = $pluginRulesByView[$loadLocation];

                if (isset($rulesToClear['inactive_rules'])) {
                    Maintenance::removeAllPluginRules($pluginPath, $loadLocation);

                    $clearedRules['plugins'][] = array(
                        'plugin_title' => self::getPluginTitleFromPath($pluginPath),
                        'plugin_path'  => $pluginPath,
                        'location'     => $loadLocation,
                        'rule_key'     => 'inactive_rules',
                        'values'       => array('1')
                    );

                    continue;
                }

                foreach ($rulesToClear as $ruleKey => $values) {
                    $ruleKey = sanitize_key($ruleKey);

                    if (empty($values) || ! is_array($values)) {
                        continue;
                    }

                    $valuesClean = array();

                    foreach ($values as $value) {
                        $valuesClean[] = sanitize_text_field(wp_unslash($value));
                    }

                    if (self::clearSinglePluginRule($pluginPath, $loadLocation, $ruleKey, $valuesClean)) {
                        $clearedRules['plugins'][] = array(
                            'plugin_title' => self::getPluginTitleFromPath($pluginPath),
                            'plugin_path'  => $pluginPath,
                            'location'     => $loadLocation,
                            'rule_key'     => $ruleKey,
                            'values'       => $valuesClean
                        );
                    }
                }
            }
        }

        return $clearedRules;
    }

    /**
     * @param $pluginPath
     * @param $clearFor
     * @param $ruleKey
     * @param $valuesToClear
     *
     * @return bool
     */
    public static function clearSinglePluginRule($pluginPath, $clearFor, $ruleKey, $valuesToClear)
    {
        if (empty($pluginPath) || empty($clearFor) || empty($ruleKey)) {
            return false;
        }

        if ( ! in_array($clearFor, array('front', 'dash'), true)) {
            return false;
        }

        $pluginPath = sanitize_text_field(wp_unslash($pluginPath));
        $ruleKey    = sanitize_key($ruleKey);

        $isTextareaRule = in_array($ruleKey, array('unload_via_regex', 'load_via_regex'), true);

        if ( ! is_array($valuesToClear)) {
            $valuesToClear = array($valuesToClear);
        }

        $valuesToClearClean = array();

        foreach ($valuesToClear as $valueToClear) {
            $valueToClear = wp_unslash($valueToClear);

            if ($isTextareaRule) {
                $valueToClear = Regex::purifyTextareaRegexValue($valueToClear, true);
            } else {
                $valueToClear = sanitize_text_field($valueToClear);
            }

            if ($valueToClear !== '') {
                $valuesToClearClean[] = $valueToClear;
            }
        }

        $mainGlobalKey = ($clearFor === 'dash') ? 'plugins_dash' : 'plugins';

        $optionToUpdate    = WPACU_PLUGIN_ID . '_global_data';
        $existingListEmpty = array($mainGlobalKey => array());
        $existingListJson  = get_option($optionToUpdate);

        $existingListData = Main::instance()->existingList($existingListJson, $existingListEmpty);
        $existingList     = $existingListData['list'];

        if (empty($existingList[$mainGlobalKey][$pluginPath]) || ! is_array($existingList[$mainGlobalKey][$pluginPath])) {
            return false;
        }

        $pluginRuleData =& $existingList[$mainGlobalKey][$pluginPath];

        $clearWholeValueRule = empty($valuesToClearClean)
            && isset($pluginRuleData[$ruleKey]['value'])
            && is_string($pluginRuleData[$ruleKey]['value']);

        if (empty($valuesToClearClean) && ! $clearWholeValueRule) {
            return false;
        }

        $changed = false;

        /*
         * 1) Remove the rule from the "status" list.
         *
         * For simple rules such as:
         * - unload_site_wide
         * - unload_home_page
         * - unload_logged_in
         * - load_home_page
         * - load_logged_in
         *
         * the rule might only exist in "status".
         */
        if ( ! empty($pluginRuleData['status']) && is_array($pluginRuleData['status'])) {
            foreach ($pluginRuleData['status'] as $statusIndex => $statusValue) {
                if ($statusValue === $ruleKey && in_array('1', $valuesToClearClean, true)) {
                    unset($pluginRuleData['status'][$statusIndex]);
                    $changed = true;
                }
            }

            $pluginRuleData['status'] = array_values($pluginRuleData['status']);

            if (empty($pluginRuleData['status'])) {
                unset($pluginRuleData['status']);
            }
        }

        /*
         * 2) Remove selected values from rules stored as arrays:
         *
         * - unload_via_post_type
         * - load_via_post_type
         * - unload_via_tax
         * - load_via_tax
         * - unload_via_archive
         * - load_via_archive
         * - unload_logged_in_via_role
         * - load_logged_in_via_role
         */
        if (isset($pluginRuleData[$ruleKey]['values']) && is_array($pluginRuleData[$ruleKey]['values'])) {
            $oldValues = $pluginRuleData[$ruleKey]['values'];

            $pluginRuleData[$ruleKey]['values'] = array_values(array_diff(
                $pluginRuleData[$ruleKey]['values'],
                $valuesToClearClean
            ));

            if ($oldValues !== $pluginRuleData[$ruleKey]['values']) {
                $changed = true;
            }

            if (empty($pluginRuleData[$ruleKey]['values'])) {
                unset($pluginRuleData[$ruleKey]);

                if ( ! empty($pluginRuleData['status']) && is_array($pluginRuleData['status'])) {
                    $pluginRuleData['status'] = array_values(array_diff($pluginRuleData['status'], array($ruleKey)));

                    if (empty($pluginRuleData['status'])) {
                        unset($pluginRuleData['status']);
                    }
                }
            }
        }

        /*
         * 3) Remove textarea/input-based rules completely when an empty value is sent.
         *
         * This is used when an editable textarea/input is submitted empty. In this case,
         * the user's intent is to clear the whole rule, regardless of the previous value.
         *
         * - unload_via_regex
         * - load_via_regex
         */
        if ($clearWholeValueRule) {
            unset($pluginRuleData[$ruleKey]);

            if ( ! empty($pluginRuleData['status']) && is_array($pluginRuleData['status'])) {
                $pluginRuleData['status'] = array_values(array_diff($pluginRuleData['status'], array($ruleKey)));

                if (empty($pluginRuleData['status'])) {
                    unset($pluginRuleData['status']);
                }
            }

            $changed = true;
        }

        /*
         * 4) Remove selected lines from textarea-based rules:
         *
         * - unload_via_regex
         * - load_via_regex
         *
         * In DB they are stored in ['value'] as a newline-separated string.
         */
        if ( ! $clearWholeValueRule && isset($pluginRuleData[$ruleKey]['value']) && is_string($pluginRuleData[$ruleKey]['value'])) {
            $oldTextareaValue = $pluginRuleData[$ruleKey]['value'];

            $existingLines = preg_split('/\r\n|\r|\n/', $oldTextareaValue);
            $newLines      = array();

            foreach ($existingLines as $existingLine) {
                $existingLineTrimmed = trim($existingLine);

                if ($existingLineTrimmed === '') {
                    continue;
                }

                if (in_array($existingLineTrimmed, $valuesToClearClean, true)) {
                    $changed = true;
                    continue;
                }

                $newLines[] = $existingLineTrimmed;
            }

            if (empty($newLines)) {
                unset($pluginRuleData[$ruleKey]);

                if ( ! empty($pluginRuleData['status']) && is_array($pluginRuleData['status'])) {
                    $pluginRuleData['status'] = array_values(array_diff($pluginRuleData['status'], array($ruleKey)));

                    if (empty($pluginRuleData['status'])) {
                        unset($pluginRuleData['status']);
                    }
                }
            } else {
                $pluginRuleData[$ruleKey]['value'] = implode("\n", $newLines);
            }
        }

        /*
         * 5) Remove enable-only keys if Overview sends value "1".
         *
         * Example possible structure:
         * load_logged_in => array('enable' => 1)
         */
        if (isset($pluginRuleData[$ruleKey]['enable']) &&
            in_array('1', $valuesToClearClean, true)) {
            unset($pluginRuleData[$ruleKey]);
            $changed = true;
        }

        /*
         * 6) If the plugin has no meaningful rules left, remove the plugin entry.
         */
        if (empty($pluginRuleData)) {
            unset($existingList[$mainGlobalKey][$pluginPath]);
            $changed = true;
        }

        if ( ! $changed) {
            return false;
        }

        update_option($optionToUpdate, wp_json_encode(MiscArray::filterList($existingList)));

        return true;
    }

    /**
     * @param $pluginPath
     *
     * @return mixed
     */
    public static function getPluginTitleFromPath($pluginPath)
    {
        if ( ! function_exists('get_plugins') ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $allPlugins = function_exists('get_plugins') ? get_plugins() : array();

        if (isset($allPlugins[$pluginPath]['Name']) && $allPlugins[$pluginPath]['Name']) {
            return $allPlugins[$pluginPath]['Name'];
        }

        return $pluginPath;
    }

    /**
     * @param $pageOptionsToClear
     * @param $clearedRules
     *
     * @return mixed
     */
    public function processPageOptionsDataToClear($pageOptionsToClear, $clearedRules)
    {
        if ( ! is_array($pageOptionsToClear) ) {
            return $clearedRules;
        }

        if (isset($pageOptionsToClear['homepage']) && is_array($pageOptionsToClear['homepage'])) {
            foreach ($pageOptionsToClear['homepage'] as $optionKey => $value) {
                $this->clearSinglePageOptionRule('front_page', 'homepage', $optionKey);

                $clearedRules['page_options'][] = array(
                    'page_type'    => 'front_page',
                    'page_id'      => 'homepage',
                    'option_key'   => $optionKey,
                    'option_value' => $value
                );
            }
        }

        if (isset($pageOptionsToClear['post']) && is_array($pageOptionsToClear['post'])) {
            foreach ($pageOptionsToClear['post'] as $postId => $postPageOptionsToClear) {
                if ( ! is_array($postPageOptionsToClear) ) {
                    continue;
                }

                foreach ($postPageOptionsToClear as $optionKey => $value) {
                    $this->clearSinglePageOptionRule('post', $postId, $optionKey);

                    $clearedRules['page_options'][] = array(
                        'page_type'    => 'post',
                        'page_id'      => $postId,
                        'option_key'   => $optionKey,
                        'option_value' => $value
                    );
                }
            }
        }

        return $clearedRules;
    }

    /**
     * @param array $criticalCssDataToClear
     * @param array $clearedRules
     *
     * @return array
     */
    private function processCriticalCssDataToClear($criticalCssDataToClear, $clearedRules)
    {
        if (empty($criticalCssDataToClear) || ! is_array($criticalCssDataToClear)) {
            return $clearedRules;
        }

        $clearedCriticalCssRules = CriticalCssAdmin::clearSelectedCriticalCssOverviewRules(
            $criticalCssDataToClear
        );

        if ( ! empty($clearedCriticalCssRules)) {
            $clearedRules['critical_css'] = array_merge(
                $clearedRules['critical_css'],
                $clearedCriticalCssRules
            );
        }

        return $clearedRules;
    }

    /**
     * @param $handlesData
     * @param $clearedRules
     *
     * @return array $clearedRules
     */
    private function processHandlesDataToClear($handlesData, $clearedRules)
    {
        // [START] Batch "Unload site-wide"
        $ruleKey = 'unload_site_wide';

        $siteWideUnloadsToRemove = array(
            'styles'  => array(),
            'scripts' => array()
        );

        foreach ($handlesData as $assetType => $handlesDataByType) {
            foreach ($handlesDataByType as $handle => $rulesData) {
                if (isset($rulesData[$ruleKey])) {
                    $siteWideUnloadsToRemove[$assetType][$handle] = 'remove';
                    unset($handlesData[$assetType][$handle][$ruleKey]);
                }
            }
        }

        if ( ! empty($siteWideUnloadsToRemove['styles']) || ! empty($siteWideUnloadsToRemove['scripts']) ) {
            $isUpdated = $this->update->removeEverywhereUnloads($siteWideUnloadsToRemove['styles'], $siteWideUnloadsToRemove['scripts']);

            if ($isUpdated) {
                foreach (array('styles', 'scripts') as $assetType) {
                    if ( ! empty($siteWideUnloadsToRemove[$assetType]) ) {
                        foreach (array_keys($siteWideUnloadsToRemove[$assetType]) as $handle) {
                            $clearedRules['handles'][] = array(
                                'asset_type' => $assetType,
                                'handle'     => $handle,
                                'rule_key'   => $ruleKey,
                                'values'     => array(1)
                            );
                        }
                    }
                }
            }
        }
        // [END] Batch "Unload site-wide"

        // [START] Batch "Bulk unloads"
        $bulkUnloadsToRemove = array(
            'styles'  => array(),
            'scripts' => array()
        );

        $addBulkUnloadToRemove = function($assetType, $bulkType, $value, $handle, $ruleKey, $feedbackValuesArray) use (&$bulkUnloadsToRemove) {
            if ( ! isset($bulkUnloadsToRemove[$assetType][$bulkType][$value]) ) {
                $bulkUnloadsToRemove[$assetType][$bulkType][$value] = array();
            }

            $bulkUnloadsToRemove[$assetType][$bulkType][$value][$handle] = array(
                'action'   => 'remove',
                'rule_key' => $ruleKey,
                'values'   => $feedbackValuesArray
            );
        };

        foreach ($handlesData as $assetType => $handlesDataByType) {
            foreach ($handlesDataByType as $handle => $rulesData) {
                foreach ($rulesData as $ruleKey => $ruleValue) {
                    $ruleWasHandled = true;

                    if ($ruleKey === 'unload_bulk_post_type') {
                        foreach ((array)$ruleValue as $postType) {
                            $addBulkUnloadToRemove($assetType, 'post_type', $postType, $handle, $ruleKey, array($postType));
                        }
                    } elseif ($ruleKey === 'unload_on_search_page') {
                        $addBulkUnloadToRemove($assetType, 'search', '', $handle, $ruleKey, array(1));
                    } elseif ($ruleKey === 'unload_on_404_page') {
                        $addBulkUnloadToRemove($assetType, '404', '', $handle, $ruleKey, array(1));
                    } elseif ($ruleKey === 'unload_all_author_pages') {
                        $addBulkUnloadToRemove($assetType, 'author', 'all', $handle, $ruleKey, array(1));
                    } elseif ($ruleKey === 'unload_on_all_tax_archive_pages') {
                        foreach ((array)$ruleValue as $taxonomy) {
                            $addBulkUnloadToRemove($assetType, 'taxonomy', $taxonomy, $handle, $ruleKey, array($taxonomy));
                        }
                    } elseif ($ruleKey === 'unload_on_archive_pages') {
                        $ruleWasHandled = false;

                        if (is_array($ruleValue)) {
                            foreach ($ruleValue as $ruleParentValue => $value) {
                                if ($ruleParentValue === 'custom_post_type') {
                                    $addBulkUnloadToRemove($assetType, 'custom_post_type_archive_' . $value, '', $handle, $ruleKey, array($value));
                                    $ruleWasHandled = true;
                                } elseif ($value === 'date') {
                                    $addBulkUnloadToRemove($assetType, 'date', '', $handle, $ruleKey, array('date'));
                                    $ruleWasHandled = true;
                                }
                            }
                        } elseif ($ruleValue === 'date') {
                            $addBulkUnloadToRemove($assetType, 'date', '', $handle, $ruleKey, array('date'));
                            $ruleWasHandled = true;
                        }
                    } else {
                        $ruleWasHandled = false;
                    }

                    if ($ruleWasHandled) {
                        unset($handlesData[$assetType][$handle][$ruleKey]);
                    }
                }
            }
        }

        foreach (array('styles', 'scripts') as $assetType) {
            if (empty($bulkUnloadsToRemove[$assetType])) {
                continue;
            }

            foreach ($bulkUnloadsToRemove[$assetType] as $bulkType => $values) {
                foreach ($values as $value => $handlesToRemove) {
                    $handlesActionsToRemove = array();

                    foreach ($handlesToRemove as $handle => $handleData) {
                        $handlesActionsToRemove[$handle] = $handleData['action'];
                    }

                    $stylesToRemove  = ($assetType === 'styles') ? $handlesActionsToRemove : array();
                    $scriptsToRemove = ($assetType === 'scripts') ? $handlesActionsToRemove : array();

                    $isUpdated = $this->update->removeBulkUnloads(
                        $stylesToRemove,
                        $scriptsToRemove,
                        $bulkType,
                        $value
                    );

                    if ($isUpdated) {
                        foreach ($handlesToRemove as $handle => $handleData) {
                            $clearedRules['handles'][] = array(
                                'asset_type' => $assetType,
                                'handle'     => $handle,
                                'rule_key'   => $handleData['rule_key'],
                                'values'     => $handleData['values']
                            );
                        }
                    }
                }
            }
        }
        // [END] Batch "Bulk unloads"

        // Other rules
        foreach (array('styles', 'scripts') as $assetType) {
            if (empty($handlesData[$assetType]) || ! is_array($handlesData[$assetType])) {
                continue;
            }

            foreach ($handlesData[$assetType] as $handle => $rulesToClear) {
                if (empty($rulesToClear) || ! is_array($rulesToClear)) {
                    continue;
                }

                $handle = sanitize_text_field($handle);

                if (isset($rulesToClear['clear_all_rules'])) {
                    Maintenance::removeAllRulesFor($handle, $assetType);

                    $clearedRules['handles'][] = array(
                        'asset_type' => $assetType,
                        'handle'     => $handle,
                        'rule_key'   => 'clear_all_rules',
                        'values'     => array('1')
                    );

                    continue;
                }

                if (isset($rulesToClear['load_exceptions_clear_all'])) {
                    Maintenance::removeAllLoadExceptionsFor($handle, $assetType);

                    $clearedRules['handles'][] = array(
                        'asset_type' => $assetType,
                        'handle'     => $handle,
                        'rule_key'   => 'load_exceptions_clear_all',
                        'values'     => array('1')
                    );

                    continue;
                }

                $ruleKey = 'unload_redundant';

                if (isset($rulesToClear[$ruleKey])) {
                    Maintenance::removeAllRedundantUnloadRulesFor($handle, $assetType);

                    $clearedRules['handles'][] = array(
                        'asset_type' => $assetType,
                        'handle'     => $handle,
                        'rule_key'   => $ruleKey,
                        'values'     => array('1')
                    );

                    continue;
                }

                foreach ($rulesToClear as $ruleKey => $values) {
                    $ruleKey = sanitize_key($ruleKey);

                    if (empty($values) || ! is_array($values)) {
                        continue;
                    }

                    foreach ($this->normalizeAssetRuleValuesToClear($ruleKey, $values) as $ruleData) {
                        if ($this->clearSingleAssetRule($assetType, $handle, $ruleKey, $ruleData['value'], $ruleData['parent_value'])) {
                            $clearedRules['handles'][] = array(
                                'asset_type'   => $assetType,
                                'handle'       => $handle,
                                'rule_key'     => $ruleKey,
                                'values'       => array($ruleData['value']),
                                'parent_value' => $ruleData['parent_value']
                            );
                        }
                    }
                }
            }
        }

        return $clearedRules;
    }

    /**
     * @param $ruleKey
     * @param $values
     *
     * @return array
     */
    private function normalizeAssetRuleValuesToClear($ruleKey, $values)
    {
        $normalized = array();

        $parentValueAwareRules = array(
            'unload_on_archive_page',
            'unload_on_all_post_types_via_tax_term',
            'load_exception_post_type_via_tax',

            'post_script_attr',
            'taxonomy_term_script_attr',
            'bulk_script_attr',
            'custom_post_type_archive_script_attr',

            'post_script_attr_no_load',
            'taxonomy_term_script_attr_no_load',
            'author_archive_script_attr_no_load',
            'bulk_script_attr_no_load'
        );

        $isParentValueAwareRule = in_array($ruleKey, $parentValueAwareRules, true);

        foreach ($values as $parentValue => $value) {
            $parentValue = $isParentValueAwareRule
                ? sanitize_text_field(wp_unslash($parentValue))
                : '';

            if ($parentValue === '0') {
                $parentValue = '';
            }

            if (is_array($value)) {
                foreach ($value as $singleValue) {
                    $normalized[] = array(
                        'value'        => sanitize_text_field(wp_unslash($singleValue)),
                        'parent_value' => $parentValue
                    );
                }

                continue;
            }

            $normalized[] = array(
                'value'        => sanitize_text_field(wp_unslash($value)),
                'parent_value' => $parentValue
            );
        }

        return $normalized;
    }

    /**
     * @param $assetType
     * @param $handle
     * @param $ruleKey
     * @param $value
     * @param $ruleParentValue
     *
     * @return bool
     */
    private function clearSingleAssetRule($assetType, $handle, $ruleKey, $value, $ruleParentValue = '')
    {
        $assetType       = ($assetType === 'styles') ? 'styles' : 'scripts';
        $handle          = sanitize_text_field($handle);
        $ruleKey         = sanitize_key($ruleKey);
        $value           = sanitize_text_field(wp_unslash($value));
        $ruleParentValue = sanitize_text_field(wp_unslash($ruleParentValue));

        if ($ruleKey === 'unload_on_home_page') {
            return $this->removeHandleFromOptionList(WPACU_PLUGIN_ID . '_front_page_no_load', $assetType, $handle);
        }

        if ($ruleKey === 'load_exception_on_home_page') {
            return $this->removeHandleFromOptionList(WPACU_PLUGIN_ID . '_front_page_load_exceptions', $assetType, $handle);
        }

        if ($ruleKey === 'unload_on_this_post') {
            return $this->removeHandleFromPostMetaList((int)$value, '_' . WPACU_PLUGIN_ID . '_no_load', $assetType, $handle);
        }

        if ($ruleKey === 'unload_on_taxonomy_page') {
            return $this->removeHandleFromTermMetaList((int)$value, '_' . WPACU_PLUGIN_ID . '_no_load', $assetType, $handle);
        }

        if ($ruleKey === 'unload_on_archive_page' && $value === 'author' && $ruleParentValue) {
            return $this->removeHandleFromUserMetaList((int)$ruleParentValue, '_' . WPACU_PLUGIN_ID . '_no_load', $assetType, $handle);
        }

        if ($ruleKey === 'unload_on_all_post_types_via_tax_term') {
            return $this->removePostTypeViaTaxUnload($assetType, $handle, $ruleParentValue, $value);
        }

        if ($ruleKey === 'load_exception_on_this_post') {
            return $this->removeHandleFromPostMetaList((int)$value, '_' . WPACU_PLUGIN_ID . '_load_exceptions', $assetType, $handle);
        }

        if ($ruleKey === 'load_exception_on_this_page_tax_id') {
            return $this->removeHandleFromTermMetaList((int)$value, '_' . WPACU_PLUGIN_ID . '_load_exceptions', $assetType, $handle);
        }

        if ($ruleKey === 'load_exception_on_this_user') {
            return $this->removeHandleFromUserMetaList((int)$value, '_' . WPACU_PLUGIN_ID . '_load_exceptions', $assetType, $handle);
        }

        if ($ruleKey === 'load_exception_post_type') {
            return $this->removeHandleKeyFromNestedOptionList(
                WPACU_PLUGIN_ID . '_post_type_load_exceptions',
                array($value, $assetType),
                $handle
            );
        }

        if ($ruleKey === 'load_exception_via_taxonomy_type') {
            return $this->removeHandleFromNestedOptionList(
                WPACU_PLUGIN_ID . '_tax_type_load_exceptions',
                array($value, $assetType),
                $handle
            );
        }

        if ($ruleKey === 'load_exception_via_author_type') {
            return $this->removeHandleFromOptionList(WPACU_PLUGIN_ID . '_author_type_load_exceptions', $assetType, $handle);
        }

        if ($ruleKey === 'load_exception_post_type_via_tax') {
            return $this->removePostTypeViaTaxLoadException($assetType, $handle, $value, $ruleParentValue);
        }

        if (in_array($ruleKey, array('unload_regex', 'load_regex', 'load_it_logged_in', 'ignore_child', 'positions', 'preloads', 'note', 'media_query'), true)) {
            $globalKey = $this->getHandleRuleGlobalKey($ruleKey);
            return $this->removeHandleFromGlobalData($assetType, $globalKey, $handle);
        }

        if ($ruleKey === 'site_wide_script_attr') {
            return $this->removeScriptAttributeFromGlobalData('everywhere', $handle, $value);
        }

        if ($ruleKey === 'post_script_attr') {
            return $this->removeScriptAttributeFromPost((int)$ruleParentValue, $handle, $value);
        }

        if ($ruleKey === 'taxonomy_term_script_attr') {
            return $this->removeScriptAttributeFromTerm((int)$ruleParentValue, $handle, $value);
        }

        if ($ruleKey === 'bulk_script_attr') {
            return $this->removeScriptAttributeFromGlobalData($ruleParentValue, $handle, $value);
        }

        if ($ruleKey === 'custom_post_type_archive_script_attr') {
            return $this->removeScriptAttributeFromGlobalData('custom_post_type_archive_' . $ruleParentValue, $handle, $value);
        }

        if ($ruleKey === 'post_script_attr_no_load') {
            return $this->removeScriptAttributeNoLoadFromPost((int)$ruleParentValue, $handle, $value);
        }

        if ($ruleKey === 'taxonomy_term_script_attr_no_load') {
            return $this->removeScriptAttributeNoLoadFromTerm((int)$ruleParentValue, $handle, $value);
        }

        if ($ruleKey === 'author_archive_script_attr_no_load') {
            return $this->removeScriptAttributeNoLoadFromUser((int)$ruleParentValue, $handle, $value);
        }

        // Date, Search, 404 Not Found, Custom Post Type Archive
        if ($ruleKey === 'bulk_script_attr_no_load') {
            return $this->removeScriptAttributeNoLoadFromGlobalData($ruleParentValue, $handle, $value);
        }

        return false;
    }

    /**
     * @param $assetType
     * @param $handle
     * @param $postType
     * @param $termId
     *
     * @return bool
     */
    private function removePostTypeViaTaxLoadException($assetType, $handle, $postType, $termId)
    {
        $optionName = WPACU_PLUGIN_ID . '_post_type_via_tax_load_exceptions';
        $json       = get_option($optionName);

        if (! $json) {
            return false;
        }

        $list = json_decode($json, true);

        if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
            return false;
        }

        if (empty($list[$postType][$assetType][$handle]['values'])
            || ! is_array($list[$postType][$assetType][$handle]['values'])
        ) {
            return false;
        }

        $targetKey = false;

        foreach ($list[$postType][$assetType][$handle]['values'] as $key => $storedTermId) {
            if ((string)$storedTermId === (string)$termId) {
                $targetKey = $key;
                break;
            }
        }

        if ($targetKey === false) {
            return false;
        }

        unset($list[$postType][$assetType][$handle]['values'][$targetKey]);

        if (empty($list[$postType][$assetType][$handle]['values'])) {
            unset($list[$postType][$assetType][$handle]);
        }

        Misc::addUpdateOption($optionName, wp_json_encode(MiscArray::filterList($list)));

        return true;
    }

    /**
     * @param $assetType
     * @param $handle
     * @param $postType
     * @param $termId
     *
     * @return bool
     */
    private function removePostTypeViaTaxUnload($assetType, $handle, $postType, $termId)
    {
        $optionName = WPACU_PLUGIN_ID . '_bulk_unload';
        $json       = get_option($optionName);

        if (! $json) {
            return false;
        }

        $list = json_decode($json, true);

        if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
            return false;
        }

        if (empty($list[$assetType]['post_type_via_tax'][$postType][$handle]['values'])
            || ! is_array($list[$assetType]['post_type_via_tax'][$postType][$handle]['values'])
        ) {
            return false;
        }

        $values = $list[$assetType]['post_type_via_tax'][$postType][$handle]['values'];

        $targetKey = false;

        foreach ($values as $key => $storedTermId) {
            if ((string)$storedTermId === (string)$termId) {
                $targetKey = $key;
                break;
            }
        }

        if ($targetKey === false) {
            return false;
        }

        unset($list[$assetType]['post_type_via_tax'][$postType][$handle]['values'][$targetKey]);

        if (empty($list[$assetType]['post_type_via_tax'][$postType][$handle]['values'])) {
            unset($list[$assetType]['post_type_via_tax'][$postType][$handle]);
        }

        Misc::addUpdateOption($optionName, wp_json_encode(MiscArray::filterList($list)));

        return true;
    }

    /**
     * @param $optionName
     * @param $assetType
     * @param $handle
     *
     * @return bool
     */
    private function removeHandleFromOptionList($optionName, $assetType, $handle)
    {
        $json = get_option($optionName);

        if (! $json) {
            return false;
        }

        $list = json_decode($json, true);

        if (wpacuJsonLastError() !== JSON_ERROR_NONE || empty($list[$assetType]) || ! is_array($list[$assetType])) {
            return false;
        }

        $key = array_search($handle, $list[$assetType], true);

        if ($key === false) {
            return false;
        }

        unset($list[$assetType][$key]);

        Misc::addUpdateOption($optionName, wp_json_encode(MiscArray::filterList($list)));

        return true;
    }

    /**
     * @param $optionName
     * @param $path
     * @param $handle
     *
     * @return bool
     */
    private function removeHandleFromNestedOptionList($optionName, $path, $handle)
    {
        $json = get_option($optionName);

        if (! $json) {
            return false;
        }

        $list = json_decode($json, true);

        if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
            return false;
        }

        $target =& $list;

        foreach ($path as $pathPart) {
            if (! isset($target[$pathPart]) || ! is_array($target[$pathPart])) {
                return false;
            }

            $target =& $target[$pathPart];
        }

        $key = array_search($handle, $target, true);

        if ($key === false) {
            return false;
        }

        unset($target[$key]);

        Misc::addUpdateOption($optionName, wp_json_encode(MiscArray::filterList($list)));

        return true;
    }

    /**
     * @param $optionName
     * @param $path
     * @param $handle
     *
     * @return bool
     */
    private function removeHandleKeyFromNestedOptionList($optionName, $path, $handle)
    {
        $json = get_option($optionName);

        if (! $json) {
            return false;
        }

        $list = json_decode($json, true);

        if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
            return false;
        }

        $target =& $list;

        foreach ($path as $pathPart) {
            if (! isset($target[$pathPart]) || ! is_array($target[$pathPart])) {
                return false;
            }

            $target =& $target[$pathPart];
        }

        if (! array_key_exists($handle, $target)) {
            return false;
        }

        unset($target[$handle]);

        Misc::addUpdateOption($optionName, wp_json_encode(MiscArray::filterList($list)));

        return true;
    }

    /**
     * @param $postId
     * @param $metaKey
     * @param $assetType
     * @param $handle
     *
     * @return bool
     */
    private function removeHandleFromPostMetaList($postId, $metaKey, $assetType, $handle)
    {
        $json = get_post_meta($postId, $metaKey, true);

        return $this->removeHandleFromMetaList($json, $assetType, $handle, function($newJson) use ($postId, $metaKey) {
            update_post_meta($postId, $metaKey, $newJson);
        });
    }

    /**
     * @param $termId
     * @param $metaKey
     * @param $assetType
     * @param $handle
     *
     * @return bool
     */
    private function removeHandleFromTermMetaList($termId, $metaKey, $assetType, $handle)
    {
        $json = get_term_meta($termId, $metaKey, true);

        return $this->removeHandleFromMetaList($json, $assetType, $handle, function($newJson) use ($termId, $metaKey) {
            update_term_meta($termId, $metaKey, $newJson);
        });
    }

    /**
     * @param $userId
     * @param $metaKey
     * @param $assetType
     * @param $handle
     *
     * @return bool
     */
    private function removeHandleFromUserMetaList($userId, $metaKey, $assetType, $handle)
    {
        $json = get_user_meta($userId, $metaKey, true);

        return $this->removeHandleFromMetaList($json, $assetType, $handle, function($newJson) use ($userId, $metaKey) {
            update_user_meta($userId, $metaKey, $newJson);
        });
    }

    /**
     * @param $json
     * @param $assetType
     * @param $handle
     * @param $updateCallback
     *
     * @return bool
     */
    private function removeHandleFromMetaList($json, $assetType, $handle, $updateCallback)
    {
        if (! $json) {
            return false;
        }

        $list = json_decode($json, true);

        if (wpacuJsonLastError() !== JSON_ERROR_NONE || empty($list[$assetType]) || ! is_array($list[$assetType])) {
            return false;
        }

        $key = array_search($handle, $list[$assetType], true);

        if ($key === false) {
            return false;
        }

        unset($list[$assetType][$key]);
        $updateCallback(wp_json_encode(MiscArray::filterList($list)));

        return true;
    }

    /**
     * @param $assetType
     * @param $globalKey
     * @param $handle
     *
     * @return bool
     */
    private function removeHandleFromGlobalData($assetType, $globalKey, $handle)
    {
        $optionName = WPACU_PLUGIN_ID . '_global_data';
        $json       = get_option($optionName);

        if (! $json) {
            return false;
        }

        $list = json_decode($json, true);

        if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
            return false;
        }

        if (empty($list[$assetType][$globalKey]) || ! is_array($list[$assetType][$globalKey])) {
            return false;
        }

        if (! array_key_exists($handle, $list[$assetType][$globalKey])) {
            return false;
        }

        unset($list[$assetType][$globalKey][$handle]);

        $newJson = wp_json_encode(MiscArray::filterList($list));

        Misc::addUpdateOption($optionName, $newJson);

        // Very important: keep the in-request cache in sync
        $GLOBALS['wpacu_global_data_json_decoded'] = MiscArray::filterList($list);

        return true;
    }

    /**
     * @param $postId
     * @param $handle
     * @param $attribute
     *
     * @return bool
     */
    private function removeScriptAttributeFromPost($postId, $handle, $attribute)
    {
        $json = get_post_meta($postId, '_' . WPACU_PLUGIN_ID . '_data', true);

        return $this->removeScriptAttributeFromJson($json, $handle, $attribute, function($newJson) use ($postId) {
            update_post_meta($postId, '_' . WPACU_PLUGIN_ID . '_data', $newJson);
        });
    }

    /**
     * @param $termId
     * @param $handle
     * @param $attribute
     *
     * @return bool
     */
    private function removeScriptAttributeFromTerm($termId, $handle, $attribute)
    {
        $json = get_term_meta($termId, '_' . WPACU_PLUGIN_ID . '_data', true);

        return $this->removeScriptAttributeFromJson($json, $handle, $attribute, function($newJson) use ($termId) {
            update_term_meta($termId, '_' . WPACU_PLUGIN_ID . '_data', $newJson);
        });
    }

    /**
     * @param $bulkType
     * @param $handle
     * @param $attribute
     *
     * @return bool
     */
    private function removeScriptAttributeFromGlobalData($bulkType, $handle, $attribute)
    {
        $json = get_option(WPACU_PLUGIN_ID . '_global_data');

        return $this->removeScriptAttributeFromJson($json, $handle, $attribute, function($newJson) {
            Misc::addUpdateOption(WPACU_PLUGIN_ID . '_global_data', $newJson);
        }, $bulkType);
    }

    /**
     * @param $json
     * @param $handle
     * @param $attribute
     * @param $updateCallback
     * @param $bulkType
     *
     * @return bool
     */
    private function removeScriptAttributeFromJson($json, $handle, $attribute, $updateCallback, $bulkType = '')
    {
        if (! $json) {
            return false;
        }

        $list = json_decode($json, true);

        if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
            return false;
        }

        if ($bulkType) {
            if (empty($list['scripts'][$bulkType][$handle]['attributes'])) {
                return false;
            }

            $target =& $list['scripts'][$bulkType][$handle]['attributes'];
        } else {
            if (empty($list['scripts'][$handle]['attributes'])) {
                return false;
            }

            $target =& $list['scripts'][$handle]['attributes'];
        }

        $key = array_search($attribute, $target, true);

        if ($key === false) {
            return false;
        }

        unset($target[$key]);

        $updateCallback(wp_json_encode(MiscArray::filterList($list)));

        return true;
    }

    /**
     * @param $postId
     * @param $handle
     * @param $attribute
     *
     * @return bool
     */
    private function removeScriptAttributeNoLoadFromPost($postId, $handle, $attribute)
    {
        $json = get_post_meta($postId, '_' . WPACU_PLUGIN_ID . '_data', true);

        return $this->removeScriptAttributeNoLoadFromJson($json, $handle, $attribute, function($newJson) use ($postId) {
            update_post_meta($postId, '_' . WPACU_PLUGIN_ID . '_data', $newJson);
        });
    }

    /**
     * @param $termId
     * @param $handle
     * @param $attribute
     *
     * @return bool
     */
    private function removeScriptAttributeNoLoadFromTerm($termId, $handle, $attribute)
    {
        $json = get_term_meta($termId, '_' . WPACU_PLUGIN_ID . '_data', true);

        return $this->removeScriptAttributeNoLoadFromJson($json, $handle, $attribute, function($newJson) use ($termId) {
            update_term_meta($termId, '_' . WPACU_PLUGIN_ID . '_data', $newJson);
        });
    }

    /**
     * @param $userId
     * @param $handle
     * @param $attribute
     *
     * @return bool
     */
    private function removeScriptAttributeNoLoadFromUser($userId, $handle, $attribute)
    {
        $json = get_user_meta($userId, '_' . WPACU_PLUGIN_ID . '_data', true);

        return $this->removeScriptAttributeNoLoadFromJson($json, $handle, $attribute, function($newJson) use ($userId) {
            update_user_meta($userId, '_' . WPACU_PLUGIN_ID . '_data', $newJson);
        });
    }

    /**
     * @param $json
     * @param $handle
     * @param $attribute
     * @param $updateCallback
     *
     * @return bool
     */
    private function removeScriptAttributeNoLoadFromJson($json, $handle, $attribute, $updateCallback)
    {
        if (! $json) {
            return false;
        }

        $list = json_decode($json, true);

        if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
            return false;
        }

        if (empty($list['scripts_attributes_no_load'][$handle])
            || ! is_array($list['scripts_attributes_no_load'][$handle])
        ) {
            return false;
        }

        $targetKey = false;

        foreach ($list['scripts_attributes_no_load'][$handle] as $key => $storedAttribute) {
            if ((string)$storedAttribute === (string)$attribute) {
                $targetKey = $key;
                break;
            }
        }

        if ($targetKey === false) {
            return false;
        }

        unset($list['scripts_attributes_no_load'][$handle][$targetKey]);

        if (empty($list['scripts_attributes_no_load'][$handle])) {
            unset($list['scripts_attributes_no_load'][$handle]);
        }

        $updateCallback(wp_json_encode(MiscArray::filterList($list)));

        return true;
    }

    /**
     * @param $scopeKey
     * @param $handle
     * @param $attribute
     *
     * @return bool
     */
    private function removeScriptAttributeNoLoadFromGlobalData($scopeKey, $handle, $attribute)
    {
        $optionName = WPACU_PLUGIN_ID . '_global_data';
        $json       = get_option($optionName);

        if (! $json) {
            return false;
        }

        $list = json_decode($json, true);

        if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
            return false;
        }

        if (empty($list['scripts_attributes_no_load'][$scopeKey][$handle])
            || ! is_array($list['scripts_attributes_no_load'][$scopeKey][$handle])
        ) {
            return false;
        }

        $targetKey = false;

        foreach ($list['scripts_attributes_no_load'][$scopeKey][$handle] as $key => $storedAttribute) {
            if ((string)$storedAttribute === (string)$attribute) {
                $targetKey = $key;
                break;
            }
        }

        if ($targetKey === false) {
            return false;
        }

        unset($list['scripts_attributes_no_load'][$scopeKey][$handle][$targetKey]);

        if (empty($list['scripts_attributes_no_load'][$scopeKey][$handle])) {
            unset($list['scripts_attributes_no_load'][$scopeKey][$handle]);
        }

        $newList = MiscArray::filterList($list);

        Misc::addUpdateOption($optionName, wp_json_encode($newList));
        $GLOBALS['wpacu_global_data_json_decoded'] = $newList;

        return true;
    }

    /**
     * @param string     $pageType
     * @param int|string $pageId
     * @param string     $optionKey
     *
     * @return bool
     */
    public function clearSinglePageOptionRule($pageType, $pageId, $optionKey)
    {
        if ($pageType === '' || $pageId === '' || $optionKey === '') {
            return false;
        }

        if ($pageType === 'front_page') {
            $existingListJson = get_option(WPACU_PLUGIN_ID . '_global_data');
            $existingListData = Main::instance()->existingList($existingListJson, array());
            $existingList     = $existingListData['list'];

            if ( ! isset($existingList['page_options'][$pageId][$optionKey]) ) {
                return false;
            }

            unset($existingList['page_options'][$pageId][$optionKey]);

            if (empty($existingList['page_options'][$pageId])) {
                unset($existingList['page_options'][$pageId]);
            }

            if (empty($existingList['page_options'])) {
                unset($existingList['page_options']);
            }

            Misc::addUpdateOption(
                WPACU_PLUGIN_ID . '_global_data',
                wp_json_encode(MiscArray::filterList($existingList))
            );

            return true;
        }

        if ($pageType === 'post') {
            $postId = (int)$pageId;

            if ($postId < 1) {
                return false;
            }

            $metaKey = '_' . WPACU_PLUGIN_ID . '_page_options';

            $pageOptionsJson = get_post_meta($postId, $metaKey, true);
            $pageOptionsData = Main::instance()->existingList($pageOptionsJson, array());
            $pageOptions     = $pageOptionsData['list'];

            if (empty($pageOptions) || ! is_array($pageOptions) || ! array_key_exists($optionKey, $pageOptions)) {
                return false;
            }

            unset($pageOptions[$optionKey]);

            $pageOptionsWithoutInternalData = $pageOptions;
            unset($pageOptionsWithoutInternalData['_page_uri']);

            if (empty($pageOptionsWithoutInternalData)) {
                delete_post_meta($postId, $metaKey);
                return true;
            }

            update_post_meta(
                $postId,
                $metaKey,
                wp_json_encode(MiscArray::filterList($pageOptions))
            );

            return true;
        }

        return false;
    }

    /**
     * Render the Overview Edit admin notice after rules were cleared/updated
     *
     * @return void
     */
    public function renderOverviewEditAdminNotice()
    {
        $transientKey = WPACU_PLUGIN_ID . '_overview_edit_updated_rules';
        $noticeData   = get_transient($transientKey);

        if (empty($noticeData) || ! is_array($noticeData)) {
            return;
        }

        delete_transient($transientKey);

        $getList = function ($data, $group, $key) {
            return (isset($data[$group][$key]) && is_array($data[$group][$key]))
                ? $data[$group][$key]
                : array();
        };

        // Cleared
        $clearedHandles     = $getList($noticeData, 'cleared', 'handles');
        $clearedPlugins     = $getList($noticeData, 'cleared', 'plugins');
        $clearedPageOptions = $getList($noticeData, 'cleared', 'page_options');
        $clearedCriticalCss = $getList($noticeData, 'cleared', 'critical_css');

        // Edited
        $editedHandles      = $getList($noticeData, 'edited', 'handles');

        $details     = array();
        $appendItems = function ($items, $type, $for) use (&$details) {
            foreach ($items as $item) {
                if ( ! is_array($item)) {
                    continue;
                }

                $item['_type'] = $type;
                $item['_for']  = $for;
                $details[]     = $item;
            }
        };

        // Cleared
        $appendItems($clearedHandles,     'cleared', 'handle');
        $appendItems($clearedPlugins,     'cleared', 'plugin');
        $appendItems($clearedPageOptions, 'cleared', 'page_option');
        $appendItems($clearedCriticalCss, 'cleared', 'critical_css');

        // Edited
        $appendItems($editedHandles,      'edited',  'handle');

        $details = apply_filters(
            'wpacu_internal_overview_edit_notice_details',
            $details,
            $noticeData
        );

        if (empty($details)) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php echo WPACU_PLUGIN_TITLE; ?>:</strong>
                    <?php esc_html_e('No matching Overview rules were found to clear or update.', 'wp-asset-clean-up'); ?>
                </p>
            </div>
            <?php
            return;
        }

        $totalChanges = count($details);

        // NOTE: _n() must keep its literal strings at the call site, otherwise the .pot
        // extractor (wp i18n make-pot) can no longer find them.
        $summaryLines = array();

        $totalClearedHandles = count($clearedHandles);
        if ($totalClearedHandles > 0) {
            $summaryLines[] = sprintf(
                _n('%d CSS/JS handle rule was cleared.', '%d CSS/JS handle rules were cleared.', $totalClearedHandles, 'wp-asset-clean-up'),
                $totalClearedHandles
            );
        }

        $totalClearedPlugins = count($clearedPlugins);
        if ($totalClearedPlugins > 0) {
            $summaryLines[] = sprintf(
                _n('%d plugin rule was cleared.', '%d plugin rules were cleared.', $totalClearedPlugins, 'wp-asset-clean-up'),
                $totalClearedPlugins
            );
        }

        $totalClearedPageOptions = count($clearedPageOptions);
        if ($totalClearedPageOptions > 0) {
            $summaryLines[] = sprintf(
                _n('%d page option rule was cleared.', '%d page option rules were cleared.', $totalClearedPageOptions, 'wp-asset-clean-up'),
                $totalClearedPageOptions
            );
        }

        $totalClearedCriticalCss = count($clearedCriticalCss);
        if ($totalClearedCriticalCss > 0) {
            $summaryLines[] = sprintf(
                _n('%d Critical CSS rule was cleared.', '%d Critical CSS rules were cleared.', $totalClearedCriticalCss, 'wp-asset-clean-up'),
                $totalClearedCriticalCss
            );
        }

        $totalEditedHandles = count($editedHandles);
        if ($totalEditedHandles > 0) {
            $summaryLines[] = sprintf(
                _n('%d CSS/JS handle setting was updated.', '%d CSS/JS handle settings were updated.', $totalEditedHandles, 'wp-asset-clean-up'),
                $totalEditedHandles
            );
        }

        $summaryLines = apply_filters(
            'wpacu_internal_overview_edit_notice_summary_lines',
            $summaryLines,
            $noticeData
        );

        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong><?php echo WPACU_PLUGIN_TITLE; ?>:</strong>
                <?php
                printf(
                    esc_html(
                        _n(
                            '%d Overview change was processed successfully.',
                            '%d Overview changes were processed successfully.',
                            $totalChanges,
                            'wp-asset-clean-up'
                        )
                    ),
                    $totalChanges
                );
                ?>
            </p>

            <ul style="list-style: disc; margin-left: 25px; margin-top: 6px;">
                <?php foreach ($summaryLines as $summaryLine) { ?>
                    <li><?php echo esc_html($summaryLine); ?></li>
                <?php } ?>
            </ul>

            <?php $this->renderOverviewEditAdminNoticeDetails($details); ?>
        </div>
        <?php
    }

    /**
     * Render the Overview Edit admin notice processed items details.
     *
     * @param array $details
     *
     * @return void
     */
    private function renderOverviewEditAdminNoticeDetails($details)
    {
        if (empty($details) || ! is_array($details)) {
            return;
        }

        $detailsLimit = 30;

        $allowedDetailTags = array(
            'em'     => array(),
            'strong' => array(
                'style' => array()
            ),
            'code'   => array(
                'class' => array()
            ),
            'br'     => array(),
            'span'   => array(
                'style' => array()
            )
        );
        ?>
        <details>
            <summary style="cursor: pointer;">
                &nbsp;<?php esc_html_e('Show processed items', 'wp-asset-clean-up'); ?>
            </summary>

            <ul style="list-style: disc; margin-left: 25px; margin-top: 6px;">
                <?php foreach (array_slice($details, 0, $detailsLimit) as $detailData) { ?>
                    <li>
                        <?php
                        echo wp_kses(
                            $this->formatOverviewEditNoticeDetail($detailData),
                            $allowedDetailTags
                        );
                        ?>
                    </li>
                <?php } ?>
            </ul>

            <?php if (count($details) > $detailsLimit) { ?>
                <p>
                    <?php
                    printf(
                        esc_html__('Showing first %1$d processed items out of %2$d.', 'wp-asset-clean-up'),
                        $detailsLimit,
                        count($details)
                    );
                    ?>
                </p>
            <?php } ?>
        </details>
        <?php
    }

    /**
     * @param array $detailData
     *
     * @return string
     */
    private function formatOverviewEditNoticeDetail($detailData)
    {
        if ($detailData['_type'] === 'cleared') {
            if ($detailData['_for'] === 'handle') {
                $assetType   = isset($detailData['asset_type'])   ? $detailData['asset_type'] : '';
                $assetTypeS  = substr($assetType, 0, -1);
                $handle      = isset($detailData['handle'])       ? $detailData['handle']     : '';
                $ruleKey     = isset($detailData['rule_key'])     ? $detailData['rule_key']   : '';
                $values      = isset($detailData['values'])       ? $detailData['values']     : '';
                $parentValue = isset($detailData['parent_value']) ? $detailData['parent_value'] : '';

                $ruleLabel = $this->formatOverviewRuleKeyLabel($ruleKey);
                $values    = $this->formatOverviewNoticeValue($values);

                $outputHandleProcess = 'Handle <span style="color: #CC0000;">rule cleared</span>: '
                                    . esc_html($assetTypeS)
                                    . ' / <strong>' . esc_html($handle) . '</strong>'
                                    . ' / ' . $ruleLabel;

                if ($parentValue !== '') {
                    $parentValueOutput = $parentValue; // default

                    $toFind = 'custom_post_type_archive_';

                    if (strpos($parentValue, $toFind) !== false) {
                        $archivePostType = str_replace($toFind, '', $parentValue);

                        $parentValueOutput = 'Archive page for the following post type: <strong>' . esc_html($archivePostType) . '</strong>';
                    } else {
                        $parentValueOutput = '<strong>'.esc_html($parentValueOutput).'</strong>';
                    }

                    $outputHandleProcess .= ': '.$parentValueOutput;
                }

                if ( ! in_array($ruleKey, self::getRuleKeysNoValuePrint()) ) {
                    $outputHandleProcess .= ($values !== '' ? ' / Value: ' . trim($values) : '');
                }

                return $outputHandleProcess;
            }

            if ($detailData['_for'] === 'plugin') {
                $pluginTitle = isset($detailData['plugin_title']) ? $detailData['plugin_title'] : '';
                $location    = isset($detailData['location'])     ? $detailData['location']     : '';
                $ruleKey     = isset($detailData['rule_key'])     ? $detailData['rule_key']     : '';
                $values      = isset($detailData['values'])       ? $detailData['values']       : '';

                $ruleLabel = $this->formatOverviewRuleKeyLabel($ruleKey, $detailData['_for']);

                $values = $this->formatOverviewNoticeValue($values, $ruleKey);

                $outputPluginProcess = 'Plugin <span style="color: #CC0000;">rule cleared</span>: <em>' . esc_html($pluginTitle) . '</em>'
                                    . ' / Location: <em>' . esc_html(self::getPluginRuleLocationLabel($location)) . '</em>'
                                    . ' / ' . $ruleLabel;

                if ( ! in_array($ruleKey, self::getRuleKeysNoValuePrint()) ) {
                    $outputPluginProcess .= ($values !== '' ? ' / Value: ' . $values : '');
                }

                return $outputPluginProcess;
            }

            if ($detailData['_for'] === 'page_option') {
                $pageType  = isset($detailData['page_type'])  ? $detailData['page_type']  : '';
                $pageId    = isset($detailData['page_id'])    ? $detailData['page_id']    : '';
                $optionKey = isset($detailData['option_key']) ? $detailData['option_key'] : '';

                return trim(
                    'Page option <span style="color: #cc0000;">cleared</span>: '
                    . esc_html($pageType)
                    . ' / ' . esc_html($pageId)
                    . ' / ' . $this->formatOverviewRuleKeyLabel($optionKey, $detailData['_for'])
                );
            }

            if ($detailData['_for'] === 'critical_css') {
                $scopeLabel    = isset($detailData['scope_label']) ? $detailData['scope_label'] : '';
                $locationLabel = isset($detailData['location_label']) ? $detailData['location_label'] : '';
                $ruleLabel     = isset($detailData['label']) ? $detailData['label'] : '';
                $typeLabel     = isset($detailData['type_label']) ? $detailData['type_label'] : '';
                $objectId      = isset($detailData['object_id']) ? (int)$detailData['object_id'] : 0;

                $outputCriticalCssProcess = 'Critical CSS <span style="color: #cc0000;">rule cleared</span>: '
                    . '<strong>' . esc_html($ruleLabel) . '</strong>';

                if ($scopeLabel !== '') {
                    $outputCriticalCssProcess .= ' / Scope: <em>' . esc_html($scopeLabel) . '</em>';
                }

                if ($locationLabel !== '') {
                    $outputCriticalCssProcess .= ' / Page type / group: <em>' . esc_html($locationLabel) . '</em>';
                }

                if ($typeLabel !== '') {
                    $outputCriticalCssProcess .= ' / Type: <em>' . esc_html($typeLabel) . '</em>';
                }

                if ($objectId > 0) {
                    $outputCriticalCssProcess .= ' / ID: <code>' . $objectId . '</code>';
                }

                return $outputCriticalCssProcess;
            }
        }

        if ($detailData['_type'] === 'edited') {
            if ($detailData['_for'] === 'handle') {
                $assetType  = isset($detailData['asset_type']) ? $detailData['asset_type'] : '';
                $assetTypeS = substr($assetType, 0, -1);
                $handle     = isset($detailData['handle'])     ? $detailData['handle']     : '';
                $ruleKey    = isset($detailData['rule_key'])   ? $detailData['rule_key']   : '';

                $oldValue   = isset($detailData['old_value']) ? $detailData['old_value'] : '';
                $newValue   = isset($detailData['new_value']) ? $detailData['new_value'] : '';

                return trim(
                    'Handle <span style="color: green;">rule edited</span>: '
                    . esc_html($assetTypeS)
                    . ' / <strong>' . esc_html($handle) . '</strong>'
                    . ' / ' . $this->formatOverviewRuleKeyLabel($ruleKey)
                    . ' / Old Value: ' . $this->formatOverviewNoticeValue($oldValue)
                    . ' / <strong style="color: green;">New Value:</strong> ' . $this->formatOverviewNoticeValue($newValue)
                );
            }

            $filteredOutput = apply_filters(
                'wpacu_internal_overview_edit_notice_detail_output',
                '',
                $detailData,
                $this
            );

            if (is_string($filteredOutput) && $filteredOutput !== '') {
                return $filteredOutput;
            }
        }

        return implode(' / ', array_map('strval', $detailData));
    }

    /**
     * e.g. a position was restored to its original location
     * the value for this one was 1 as it had to be something in the checkbox (e.g. could be 'true' or 'on')
     * there's no point in printing "Value: 1" in this case
     *
     * @return string[]
     */
    public static function getRuleKeysNoValuePrint()
    {
        $ruleKeys = array(
            'handle' => array(
                'unload_site_wide',
                'unload_on_home_page',
                'unload_on_search_page',
                'unload_on_404_page',
                'unload_all_author_pages',
                'load_exception_via_author_type',
                'load_exception_on_search_page',
                'load_exception_on_404_page',
                'site_wide_script_attr',
                'positions',
            ),
            'plugin' => array(
                'inactive_rules',
                'unload_site_wide',
                'unload_home_page',
                'unload_logged_in',
                'load_home_page',
                'load_logged_in',
            ),
        );

        $ruleKeysAll = array_unique(array_merge(
            $ruleKeys['handle'],
            $ruleKeys['plugin']
        ));

        return $ruleKeysAll;
    }

    /**
     * For a better structure and for showing easily the leftovers in case the user returns to the Lite version
     *
     * @return array[]
     */
    public function getOverviewRuleKeyLabelsPro()
    {
        return array(
            'handle' => array(
                // Pro unload rules
                'unload_on_all_post_types_via_tax_term'  => 'Unloaded on all pages of a post type associated with selected taxonomy terms',
                'unload_on_all_post_types_with_tax_term' => 'Unloaded on all pages of a post type associated with selected taxonomy terms',
                'unload_on_all_tax_archive_pages'        => 'Unloaded on all archive pages of selected taxonomies',
                'unload_on_archive_pages'                => 'Unloaded on selected archive pages',
                'unload_on_search_page'                  => 'Unloaded on search results pages',
                'unload_on_404_page'                     => 'Unloaded on 404 Not Found pages',
                'unload_all_author_pages'                => 'Unloaded on all author archive pages',
                'unload_on_date_archive_pages'           => 'Unloaded on date archive pages',
                'unload_on_taxonomy_page'                => 'Unloaded on selected taxonomy pages',
                'unload_on_these_author_pages'           => 'Unloaded on these author archive pages',
                'unload_regex'                           => 'Unloaded if the request URI matches selected RegEx rules',

                // Pro load exception rules
                'load_exception_post_type_via_tax'           => 'Loaded as an exception on pages of a post type associated with selected taxonomy terms',
                'load_exception_on_this_page_tax_id'         => 'Loaded as an exception on selected taxonomy pages',
                'load_exception_via_taxonomy_type'           => 'Loaded as an exception on all pages belonging to selected taxonomies',
                'load_exception_via_author_type'             => 'Loaded as an exception on all author archive pages',
                'load_exception_on_this_user'                => 'Loaded as an exception on selected user archive pages',
                'load_exception_on_search_page'              => 'Loaded as an exception on search results pages',
                'load_exception_on_404_page'                 => 'Loaded as an exception on 404 Not Found pages',
                'load_exception_on_these_author_pages'       => 'Loaded as an exception on these author archive pages',
                'load_exception_on_date_archive_page'        => 'Loaded as an exception on date archive pages',
                'load_exception_on_custom_post_type_archive' => 'Loaded as an exception on selected custom post type archive pages',
                'load_regex'                                 => 'Loaded as an exception if the request URI matches selected RegEx rules',

                // Pro defer, async attributes
                'site_wide_script_attr'                  => 'Apply attribute site-wide',
                'post_script_attr'                       => 'Apply attribute on this post page',
                'taxonomy_term_script_attr'              => 'Apply attribute on this taxonomy page',
                'custom_post_type_archive_script_attr'   => 'Apply attribute on this custom post type archive page',
                'bulk_script_attr'                       => 'Apply attribute on this page type',

                'post_script_attr_no_load'               => 'Prevent applying the site-wide attribute on this post page',
                'taxonomy_term_script_attr_no_load'      => 'Prevent applying the site-wide attribute on this taxonomy page',
                'author_archive_script_attr_no_load'     => 'Prevent applying the site-wide attribute on this author page',
                'bulk_script_attr_no_load'               => 'Prevent applying the site-wide attribute on this page type',

                // Extra handle settings that also use renderRuleOutput()
                'positions'                              => 'Restored to its original HTML position',
                'media_query'                            => 'Downloads only if the media query matches',
            ),
            'plugin' => array(
                'inactive_rules'             => 'Clear all rules for this inactive plugin',

                'unload_site_wide'           => 'Unloaded on all pages',
                'unload_home_page'           => 'Unloaded on the homepage',
                'unload_via_post'    => 'Unloaded on selected singular pages',
                'unload_via_post_type'       => 'Unloaded on all pages belonging to selected post types',
                'unload_via_post_tax_term'   => 'Unloaded on pages that have selected taxonomy terms',
                'unload_via_tax_term'        => 'Unloaded on selected taxonomy term pages',
                'unload_via_tax'             => 'Unloaded on selected taxonomy page types',
                'unload_via_archive'         => 'Unloaded on selected archive page types',
                'unload_via_regex'           => 'Unloaded if the request URI matches selected RegEx rules',
                'unload_logged_in'           => 'Unloaded if the user is logged in',
                'unload_logged_in_via_role'  => 'Unloaded if the user is logged in and has selected roles',

                'load_home_page'             => 'Loaded as an exception on the homepage',
                'load_via_post_type'         => 'Loaded as an exception on selected post types',
                'load_via_post'              => 'Loaded as an exception on selected singular pages',
                'load_via_post_tax_term'     => 'Loaded as an exception on pages that have selected taxonomy terms',
                'load_via_tax_term'          => 'Loaded as an exception on selected taxonomy term pages',
                'load_via_tax'               => 'Loaded as an exception on selected taxonomy page types',
                'load_via_archive'           => 'Loaded as an exception on selected archive page types',
                'load_via_regex'             => 'Loaded as an exception if the request URI matches selected RegEx rules',
                'load_logged_in'             => 'Loaded as an exception if the user is logged in',
                'load_logged_in_via_role'    => 'Loaded as an exception if the user is logged in and has selected roles',
            )
        );
    }

    /**
     * @return string
     */
    private function getOverviewRuleKeyLabels()
    {
        $ruleKeyLabels = array(
            'handle' => array(
                // Lite / common
                'unload_site_wide'                       => 'Unloaded site-wide (everywhere)',
                'unload_bulk_post_type'                  => 'Unloaded on all pages belonging to selected post types',
                'unload_on_home_page'                    => 'Unloaded on the homepage',
                'unload_on_this_post'                    => 'Unloaded on selected singular pages',

                'unload_redundant'                       => 'Clear all redundant unload rules for this handle',
                'ignore_child'                           => 'If unloaded by any rule, ignore dependencies and keep its children loaded',

                'load_exception_on_home_page'            => 'Loaded as an exception on the homepage',
                'load_exception_on_this_post'            => 'Loaded as an exception on selected posts',
                'load_exception_post_type'               => 'Loaded as an exception on selected post types',
                'load_it_logged_in'                      => 'Loaded as an exception if the user is logged in',

                'load_exceptions_clear_all'              => 'Clear all load exceptions for this handle',

                // Extra handle settings that also use renderRuleOutput()
                'preloads'                               => 'Preloaded',
                'note'                                   => 'Note',

                'clear_all_rules'                        => 'Clear all rules for this inactive handle',
            ),
            'page_option' => Overview::getPageOptionsToText(),
        );

        foreach ($this->getOverviewRuleKeyLabelsPro() as $group => $proRuleKeyLabels) {
            if ( ! isset($ruleKeyLabels[$group]) || ! is_array($ruleKeyLabels[$group]) ) {
                $ruleKeyLabels[$group] = array();
            }

            $ruleKeyLabels[$group] = array_merge(
                $ruleKeyLabels[$group],
                $proRuleKeyLabels
            );
        }

        return apply_filters('wpacu_internal_overview_rule_key_labels', $ruleKeyLabels);
    }

    /**
     * @param $for
     *
     * @return string
     */
    public static function getPluginRuleLocationLabel($for)
    {
        $labels = array(
            'front' => 'Front-end view',
            'dash'  => 'Dashboard view'
        );

        return $labels[$for];
    }

    /**
     * @param $ruleKey
     * @param $for
     *
     * @return string
     */
    public function formatOverviewRuleKeyLabel($ruleKey, $for = 'handle')
    {
        $ruleKeyLabels = $this->getOverviewRuleKeyLabels();

        if (isset($ruleKeyLabels[$for][$ruleKey])) {
            $ruleLabel = html_entity_decode($ruleKeyLabels[$for][$ruleKey], ENT_QUOTES, get_bloginfo('charset'));

            return esc_html($ruleLabel);
        }

        return '<code>' . esc_html($ruleKey) . '</code>';
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    public function formatOverviewNoticeValue($value, $ruleKey = '')
    {
        if (in_array($ruleKey, array('unload_via_post', 'load_via_post'), true)) {
            $postIds = is_array($value)
                ? $value
                : preg_split('/[\r\n,]+/', (string)$value, -1, PREG_SPLIT_NO_EMPTY);

            $postIds    = array_values(array_unique(array_filter(array_map('absint', $postIds))));
            $postLabels = Overview::getPostLabelsByIds($postIds);

            if ( ! empty($postLabels)) {
                $postValues = array();

                foreach ($postIds as $postId) {
                    $postValues[] = isset($postLabels[$postId])
                        ? $postLabels[$postId]
                        : sprintf(__('Unknown or deleted singular page — ID: %d', 'wp-asset-clean-up'), $postId);
                }

                $value = implode("\n", $postValues);
            }
        } elseif (in_array($ruleKey, array('unload_via_post_tax_term', 'load_via_post_tax_term', 'unload_via_tax_term', 'load_via_tax_term'), true)) {
            $termTaxonomyIds = is_array($value)
                ? $value
                : preg_split('/[\r\n,]+/', (string)$value, -1, PREG_SPLIT_NO_EMPTY);

            $termLabels = Overview::getTaxonomyTermLabelsByTermTaxonomyIds($termTaxonomyIds);

            if ( ! empty($termLabels)) {
                $value = implode("\n", $termLabels);
            }
        } elseif (is_array($value)) {
            $value = implode("\n", array_map('strval', $value));
        }

        $value = trim((string)$value);

        if ($value === '') {
            return '';
        }

        $value = str_replace(array("\r\n", "\r"), "\n", $value);

        if (strpos($value, "\n") !== false) {
            return '<code class="wpacu-overview-notice-value-multiline">' . esc_html($value) . '</code>';
        }

        if (in_array($ruleKey, array('unload_via_tax', 'load_via_tax')) && substr($value, -4) === '_all') {
            $replaceThis = substr($value, 0, -4);
            $replaceWith = '<strong>'.$replaceThis.'</strong>';

            $value = str_replace($replaceThis, $replaceWith, $value);
        }

        return '<code>' . wp_kses($value, array('strong' => array())) . '</code>';
    }
}
