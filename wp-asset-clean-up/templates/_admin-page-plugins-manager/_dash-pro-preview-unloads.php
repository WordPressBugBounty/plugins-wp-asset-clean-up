<?php
if ( ! isset($wpacuDashboardRuleData, $wpacuDashboardRoles, $wpacuPluginHtmlId)) {
    exit;
}

$wpacuUnloadRegexId = 'wpacu-lite-dash-unload-regex-' . $wpacuPluginHtmlId;
$wpacuUnloadSiteWideId = 'wpacu-lite-dash-unload-site-wide-' . $wpacuPluginHtmlId;
$wpacuUnloadRoleId = 'wpacu-lite-dash-unload-role-' . $wpacuPluginHtmlId;
?>
<div data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>"
     class="wpacu_plugin_unload_rules_options_wrap">
    <div class="wpacu_plugin_rules_wrap">
        <fieldset>
            <legend><strong><?php esc_html_e('Unload this plugin', 'wp-asset-clean-up'); ?></strong> <?php esc_html_e('within the Dashboard:', 'wp-asset-clean-up'); ?></legend>
            <ul class="wpacu_plugin_rules">
                <li>
                    <label for="<?php echo esc_attr($wpacuUnloadSiteWideId); ?>"<?php
                    echo $wpacuDashboardRuleData['is_unload_site_wide'] ? ' class="wpacu_plugin_unload_rule_input_checked"' : '';
                    ?>>
                        <input data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>"
                               class="wpacu_plugin_unload_site_wide wpacu_plugin_unload_rule_input"
                               id="<?php echo esc_attr($wpacuUnloadSiteWideId); ?>"
                               type="checkbox"
                               disabled="disabled"
                               aria-disabled="true"
                            <?php echo $wpacuDashboardRuleData['is_unload_site_wide'] ? ' checked="checked"' : ''; ?>
                               value="unload_site_wide" />
                        <?php esc_html_e('On all admin pages', 'wp-asset-clean-up'); ?>
                    </label>
                </li>

                <li>
                    <label for="<?php echo esc_attr($wpacuUnloadRegexId); ?>"<?php
                    echo $wpacuDashboardRuleData['is_unload_via_regex'] ? ' class="wpacu_plugin_unload_rule_input_checked"' : '';
                    ?> style="margin-right: 0;">
                        <input data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>"
                               id="<?php echo esc_attr($wpacuUnloadRegexId); ?>"
                               class="wpacu_plugin_unload_regex_option wpacu_plugin_unload_rule_input"
                               type="checkbox"
                               disabled="disabled"
                               aria-disabled="true"
                            <?php echo $wpacuDashboardRuleData['is_unload_via_regex'] ? ' checked="checked"' : ''; ?>
                               value="unload_via_regex" />&nbsp;
                        <span><?php esc_html_e('For admin URLs with the request URI matching any of these rules:', 'wp-asset-clean-up'); ?></span>
                    </label>
                    <a class="help_link unload_it_regex"
                       target="_blank"
                       rel="noopener noreferrer"
                       href="https://www.assetcleanup.com/docs/?p=372#wpacu-unload-plugins-via-regex">
                        <span style="color: #74777b;" class="dashicons dashicons-external" aria-hidden="true"></span>
                    </a>

                    <div data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>"
                         class="wpacu_plugin_unload_regex_input_wrap<?php echo $wpacuDashboardRuleData['is_unload_via_regex'] ? '' : ' wpacu_hide'; ?>">
                        <textarea class="wpacu_regex_rule_textarea wpacu_regex_unload_rule_textarea"
                                  data-wpacu-adapt-height="1"
                                  disabled="disabled"
                                  aria-disabled="true"><?php echo esc_textarea($wpacuDashboardRuleData['unload_via_regex_value']); ?></textarea>
                        <p><small><span style="font-weight: 500;"><?php esc_html_e('Note:', 'wp-asset-clean-up'); ?></span> <?php esc_html_e('Enter one rule per line. Plain URI strings and RegEx patterns are supported.', 'wp-asset-clean-up'); ?></small></p>
                    </div>
                </li>

                <li>
                    <label for="<?php echo esc_attr($wpacuUnloadRoleId); ?>"<?php
                    echo $wpacuDashboardRuleData['is_unload_logged_in_via_role'] ? ' class="wpacu_plugin_unload_rule_input_checked"' : '';
                    ?> style="margin-right: 0;">
                        <input data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>"
                               id="<?php echo esc_attr($wpacuUnloadRoleId); ?>"
                               class="wpacu_plugin_unload_logged_in_via_role wpacu_plugin_unload_rule_input"
                               type="checkbox"
                               disabled="disabled"
                               aria-disabled="true"
                            <?php echo $wpacuDashboardRuleData['is_unload_logged_in_via_role'] ? ' checked="checked"' : ''; ?>
                               value="unload_logged_in_via_role" />&nbsp;
                        <span><?php esc_html_e('If the logged-in user has any of these roles:', 'wp-asset-clean-up'); ?></span>
                    </label>
                    <a class="help_link"
                       target="_blank"
                       rel="noopener noreferrer"
                       href="https://www.assetcleanup.com/docs/?p=1688">
                        <span style="color: #74777b;" class="dashicons dashicons-external" aria-hidden="true"></span>
                    </a>

                    <div data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>"
                         class="wpacu_plugin_unload_logged_in_via_role_select_wrap<?php echo $wpacuDashboardRuleData['is_unload_logged_in_via_role'] ? '' : ' wpacu_hide'; ?>">
                        <select multiple="multiple"
                                style="width: 100%;"
                                class="wpacu_plugin_manage_logged_in_via_role_dd wpacu_plugin_manage_unload_logged_in_via_role"
                                disabled="disabled"
                                aria-disabled="true">
                            <?php foreach ($wpacuDashboardRoles as $roleKey => $roleLabel) { ?>
                                <option value="<?php echo esc_attr($roleKey); ?>"<?php
                                echo in_array($roleKey, $wpacuDashboardRuleData['unload_logged_in_via_role_chosen'], true)
                                    ? ' selected="selected"'
                                    : '';
                                ?>><?php echo esc_html($roleLabel . ' (' . $roleKey . ')'); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </li>
            </ul>
        </fieldset>
    </div>
    <div class="wpacu_clearfix"></div>
</div>
