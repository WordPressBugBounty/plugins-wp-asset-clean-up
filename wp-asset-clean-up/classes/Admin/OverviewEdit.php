<?php
namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\Misc;

/**
 *
 * Class OverviewEdit
 * @package WpAssetCleanUp
 */
class OverviewEdit
{
    /**
     * @var string
     */
    public static $mainPostNameToClear = 'wpacu_overview_data_to_clear';

    /**
     * @var string
     */
    public static $mainPostNameToEdit = 'wpacu_overview_data_to_edit';

    /**
     *
     */
    public function __construct()
    {
        // The code initiated in this function is relevant only in the "Overview" page in edit mode
        if ( ! (isset($_GET['wpacu_edit_mode']) && $_GET['wpacu_edit_mode'] && Misc::getVar('request', 'page') === WPACU_PLUGIN_ID . '_overview') ) {
            return;
        }

        new OverviewEditUpdate();

        add_action('admin_head', function() {
        ?>
            <style>
            .wpacu-with-textarea-edit .wpacu-edit-area.wpacu-edit-area-disabled {
                opacity: 0.65;
                background-color: #f6f7f7;
                cursor: not-allowed;
            }

            .wpacu-overview-notice-value-multiline {
                display: inline-block;
                white-space: pre-wrap;
            }
            </style>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.wpacu-with-textarea-edit').forEach(function (wrap) {
                    var checkbox = wrap.querySelector('.wpacu-delete-checkbox');
                    var textarea = wrap.querySelector('.wpacu-edit-area');

                    if ( ! checkbox || ! textarea ) {
                        return;
                    }

                    function maybeLockTextarea() {
                        textarea.disabled = checkbox.checked;
                        textarea.classList.toggle('wpacu-edit-area-disabled', checkbox.checked);
                    }

                    checkbox.addEventListener('change', maybeLockTextarea);

                    maybeLockTextarea();
                });
            });
            </script>
        <?php
        });
    }

    /**
     * @param $output
     * @param $infoData
     * @param $ruleKey
     * @param $value
     * @param $parentValue
     *
     *
     * @return mixed|string
     */
    public static function renderMaybeEditSettingChangesWrapOutputRule($output, $infoData, $ruleKey, $value = 1, $parentValue = '')
    {
        if ( ! Overview::isEditMode() ) {
            return $output;
        }

        $ruleActionsAllPossible = array();

        $ruleAction = 'delete'; // most common type (clearing checkbox)

        $ruleActionsAllPossible[] = $ruleAction;

        // Use only characters allowed in IDs
        $safeValue = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $value);
        $safeParentValue = '';

        if ($parentValue) {
            $safeParentValue = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $parentValue);
        }

        $refId = $checkboxName = $refIdEdit = $inputNameEdit = '';

        if (isset($infoData['handle'])) {
            $ruleKeysForEditing = self::getAllRuleKeysJustForEditing();

            if (in_array($ruleKey, $ruleKeysForEditing)) {
                $ruleAction               = 'edit';
                $ruleActionsAllPossible[] = $ruleAction;
            }

            $ruleKeysForEditingAndDeleting = self::getAllRuleKeysForEditingAndDeleting();

            if (in_array($ruleKey, $ruleKeysForEditingAndDeleting)) {
                $ruleAction               = 'edit_delete';
                $ruleActionsAllPossible[] = $ruleAction;
            }

            $handle    = $infoData['handle'];
            $assetType = $infoData['asset_type'];

            // Defaults for "delete" action
            $refId        = 'wpacu_handle_rule_to_clear_' . $assetType . '_' . $handle . '_' . $ruleKey . '_' . $safeValue;
            $checkboxName = self::$mainPostNameToClear . '[handle][' . esc_attr($assetType) . '][' . $handle . '][' . esc_attr($ruleKey) . ']';

            if ($parentValue !== '') {
                $checkboxName .= '[' . esc_attr($parentValue) . ']';
            } else {
                $checkboxName .= '[]';
            }

            // Special situations where the same parent can have multiple checked values
            $ruleKeysWithMultipleValuesPerParent = array(
                'unload_on_all_post_types_via_tax_term',
                'custom_post_type_archive_script_attr', // It can have multiple values such as "async", "defer"
                'post_script_attr_no_load',
                'taxonomy_term_script_attr_no_load',
                'author_archive_script_attr_no_load',
                'bulk_script_attr_no_load'
            );

            if ($parentValue !== '' && in_array($ruleKey, $ruleKeysWithMultipleValuesPerParent, true)) {
                $checkboxName .= '[]';
            }

            if (in_array($ruleAction, $ruleActionsAllPossible)) {
                $refIdEdit     = 'wpacu_handle_rule_to_edit_' . $assetType . '_' . $handle . '_' . $ruleKey . '_' . $safeValue;
                $inputNameEdit = self::$mainPostNameToEdit.'[handle][' . esc_attr($assetType) . '][' . $handle . '][' . esc_attr($ruleKey) . ']';
            }
        }

        if (isset($infoData['plugin'])) {
            $ruleKeysForEditingAndDeleting = apply_filters('wpacu_internal_overview_edit_plugin_rule_keys_for_edit_delete', array());

            if (in_array($ruleKey, $ruleKeysForEditingAndDeleting)) {
                // It has both a checkbox to clear it, and an input to edit it
                $ruleAction = 'edit_delete';
            }

            $refIdPrefix = 'wpacu_plugin_rule_to_clear';

            $plugin   = $infoData['plugin'];
            $location = $infoData['location']; // e.g. "front", "dash"

            $refId        = $refIdPrefix . '_ ' . $location . $plugin . $ruleKey . '_' . $safeValue;
            $checkboxName = self::$mainPostNameToClear . '[plugin][' . $plugin . '][' . $location . '][' . $ruleKey . '][' . $parentValue . ']';

            if (in_array($ruleAction, array('edit', 'edit_delete'))) {
                $refIdEdit     = 'wpacu_plugin_rule_to_edit_ ' . $location . $plugin . $ruleKey . '_' . $safeValue;
                $inputNameEdit = self::$mainPostNameToEdit . '[plugin][' . $plugin . '][' . $location . '][' . $ruleKey . ']';
            }
        }

        if (isset($infoData['page_option'], $infoData['page_type'])) {
            $refIdPrefix = 'wpacu_page_option_rule_to_clear';

            if (isset($infoData['post_id']) && $infoData['post_id'] && $infoData['page_type'] === 'post') {
                $refId        = $refIdPrefix . '_' . $infoData['post_id'] . '_' . $infoData['page_type'] . '_' . $ruleKey . '_' . $safeValue;
                $postId       = $infoData['post_id'];
                $checkboxName = self::$mainPostNameToClear . '[page_option][' . $infoData['page_type'] . '][' . $postId . '][' . $ruleKey . ']';
            } elseif(isset($infoData['page_type']) && $infoData['page_type'] === 'homepage') {
                $refId        = $refIdPrefix . '_' . $infoData['page_type'] . '_' . $ruleKey . '_' . $safeValue;
                $checkboxName = self::$mainPostNameToClear . '[page_option][' . $infoData['page_type'] . '][' . $ruleKey . ']';
            }
        }

        if (isset($infoData['critical_css'])) {
            $scope       = isset($infoData['scope']) ? sanitize_key($infoData['scope']) : '';
            $locationKey = isset($infoData['location_key']) ? sanitize_key($infoData['location_key']) : '';
            $storageType = isset($infoData['storage_type']) ? sanitize_key($infoData['storage_type']) : '';
            $objectId    = isset($infoData['object_id']) ? (int)$infoData['object_id'] : 0;

            if ($scope === 'general' && $locationKey !== '' && $storageType === 'option') {
                $refId = 'wpacu_critical_css_rule_to_clear_general_' . $locationKey;
                $checkboxName = self::$mainPostNameToClear
                    . '[critical_css][general][' . $locationKey . ']';
            } elseif ($scope === 'specific'
                && $locationKey !== ''
                && in_array($storageType, array('post_meta', 'term_meta', 'user_meta'), true)
                && $objectId > 0) {
                $refId = 'wpacu_critical_css_rule_to_clear_specific_'
                    . $storageType . '_' . $locationKey . '_' . $objectId;
                $checkboxName = self::$mainPostNameToClear
                    . '[critical_css][specific][' . $storageType . '][' . $locationKey . '][' . $objectId . ']';
            }
        }

        if ($safeParentValue && $refId) {
            $refId .= '_' . $safeParentValue;
        }

        if ( ! $refId ) {
            return ''; // something's funny (incomplete parameters)
        }

        if ( $ruleAction === 'delete' && isset($infoData['html_output_value']) && $infoData['html_output_value'] ) {
            $output .= ' ' . $infoData['html_output_value'];
        }

        ob_start();

        if ($ruleAction === 'delete') {
        ?>
            <label class="wpacu-delete-label" for="<?php echo esc_attr($refId); ?>">
                <input type="checkbox"
                       class="wpacu-delete-checkbox"
                       id="<?php echo esc_attr($refId); ?>"
                       name="<?php echo esc_attr($checkboxName); ?>"
                       value="<?php echo esc_attr($value); ?>"/>
                <span><?php echo $output; ?></span>
            </label>
        <?php
        }

        if ($ruleAction === 'edit') {
        ?>
            <div style="clear: both;">
                <label class="wpacu-edit-label" for="<?php echo esc_attr($refId); ?>">
                    <div style="margin: 5px 5px 0 0; float: left;"><span><?php echo $output; ?></span></div>

                    <div style="margin: 8px 0 0;">
                            <textarea class="wpacu-edit-area"
                                      style="min-width: 300px; border: 1px solid green;"
                                      data-wpacu-adapt-height="1"
                                      id="<?php echo esc_attr($refIdEdit); ?>"
                                      name="<?php echo esc_attr($inputNameEdit); ?>"><?php echo esc_textarea($value); ?></textarea>
                    </div>
                </label>
            </div>
        <?php
        }

        $outputRule = ob_get_clean();

        $outputRule = apply_filters(
            'wpacu_internal_overview_edit_rule_output',
            $outputRule,
            $ruleAction,
            $output,
            array(
                // Clear
                'ref_id'          => $refId,
                'checkbox_name'   => $checkboxName,

                // Edit
                'ref_id_edit'     => $refIdEdit,
                'input_name_edit' => $inputNameEdit
            ),
            $infoData,
            $ruleKey,
            $value,
            $parentValue
        );

        $noWrapSet = isset($infoData['no_wrap']) && $infoData['no_wrap'];

        if ($ruleAction === 'delete' && ! $noWrapSet) {
            return Overview::wrapRuleViewChangeOutput($outputRule, $ruleKey);
        }

        return $outputRule;
    }

    /**
     * @param string $ruleKey
     *
     * @return bool
     */
    public static function isEditableHandleRuleKey($ruleKey)
    {
        $editableHandleRulekeys = array_merge(self::getAllRuleKeysJustForEditing(), self::getAllRuleKeysForEditingAndDeleting());

        return in_array($ruleKey, $editableHandleRulekeys, true);
    }

    /**
     * action: edit_delete
     *
     * @return array
     */
    public static function getAllRuleKeysForEditingAndDeleting()
    {
        $ruleKeys = array();

        return apply_filters('wpacu_internal_overview_edit_handle_rule_keys_for_edit_delete', $ruleKeys);
    }

    /**
     * action: edit
     *
     * @return array
     */
    public static function getAllRuleKeysJustForEditing()
    {
        $ruleKeys = array('note');

        return apply_filters('wpacu_internal_overview_edit_handle_rule_keys_just_edit', $ruleKeys);
    }
}
