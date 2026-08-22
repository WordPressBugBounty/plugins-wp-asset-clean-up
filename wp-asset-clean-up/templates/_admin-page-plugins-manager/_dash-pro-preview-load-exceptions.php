<?php
if ( ! isset($wpacuDashboardRuleData, $wpacuDashboardRoles, $wpacuPluginHtmlId)) {
    exit;
}

$wpacuLoadRegexId = 'wpacu-lite-dash-load-regex-' . $wpacuPluginHtmlId;
$wpacuLoadRoleId = 'wpacu-lite-dash-load-role-' . $wpacuPluginHtmlId;
$wpacuShowLoadExceptions = $wpacuDashboardRuleData['is_unload_site_wide'] || $wpacuDashboardRuleData['is_unload_via_regex'];
?>
<div data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>"
     class="wpacu_plugin_load_exception_options_wrap<?php echo $wpacuShowLoadExceptions ? '' : ' wpacu_hide'; ?>">
    <div class="wpacu_plugin_rules_wrap">
        <fieldset>
            <legend><?php esc_html_e('Make an exception from any unload rule &', 'wp-asset-clean-up'); ?> <strong><?php esc_html_e('always load it', 'wp-asset-clean-up'); ?></strong>:</legend>
            <ul class="wpacu_plugin_rules wpacu_exception_options_area">
                <li>
                    <label for="<?php echo esc_attr($wpacuLoadRegexId); ?>" style="margin-right: 0;">
                        <input data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>"
                               id="<?php echo esc_attr($wpacuLoadRegexId); ?>"
                               class="wpacu_plugin_load_exception_regex_option wpacu_plugin_load_rule_input"
                               type="checkbox"
                               disabled="disabled"
                               aria-disabled="true"
                            <?php echo $wpacuDashboardRuleData['is_load_via_regex'] ? ' checked="checked"' : ''; ?>
                               value="load_via_regex" />&nbsp;
                        <span><?php esc_html_e('Make an exception and always load it if the request URI matches any of these rules:', 'wp-asset-clean-up'); ?></span>
                    </label>&nbsp;
                    <a style="color: #74777b;"
                       class="help_link"
                       target="_blank"
                       rel="noopener noreferrer"
                       href="https://www.assetcleanup.com/docs/?p=372#wpacu-unload-plugins-via-regex">
                        <span class="dashicons dashicons-external" aria-hidden="true"></span>
                    </a>&nbsp;

                    <div class="wpacu_load_regex_input_wrap<?php echo $wpacuDashboardRuleData['is_load_via_regex'] ? '' : ' wpacu_hide'; ?>"
                         data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>">
                        <textarea class="wpacu_regex_rule_textarea wpacu_regex_load_rule_textarea"
                                  data-wpacu-adapt-height="1"
                                  disabled="disabled"
                                  aria-disabled="true"><?php echo esc_textarea($wpacuDashboardRuleData['load_via_regex_value']); ?></textarea>
                        <p><small><span style="font-weight: 500;"><?php esc_html_e('Note:', 'wp-asset-clean-up'); ?></span> <?php esc_html_e('Enter one rule per line. Plain URI strings and RegEx patterns are supported.', 'wp-asset-clean-up'); ?></small></p>
                    </div>
                </li>

                <li>
                    <label for="<?php echo esc_attr($wpacuLoadRoleId); ?>" style="margin-right: 0;">
                        <input data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>"
                               id="<?php echo esc_attr($wpacuLoadRoleId); ?>"
                               class="wpacu_plugin_load_logged_in_via_role wpacu_plugin_load_rule_input"
                               type="checkbox"
                               disabled="disabled"
                               aria-disabled="true"
                            <?php echo $wpacuDashboardRuleData['is_load_logged_in_via_role'] ? ' checked="checked"' : ''; ?>
                               value="load_logged_in_via_role" />&nbsp;
                        <span><?php esc_html_e('If the logged-in user has any of these roles:', 'wp-asset-clean-up'); ?></span>
                    </label>
                    <a class="help_link"
                       target="_blank"
                       rel="noopener noreferrer"
                       href="https://www.assetcleanup.com/docs/?p=1688">
                        <span style="color: #74777b;" class="dashicons dashicons-external" aria-hidden="true"></span>
                    </a>

                    <div data-wpacu-plugin-path="<?php echo esc_attr($wpacuPluginPath); ?>"
                         class="wpacu_plugin_load_logged_in_via_role_select_wrap<?php echo $wpacuDashboardRuleData['is_load_logged_in_via_role'] ? '' : ' wpacu_hide'; ?>">
                        <select multiple="multiple"
                                style="width: 100%;"
                                class="wpacu_plugin_manage_logged_in_via_role_dd wpacu_plugin_manage_load_logged_in_via_role"
                                disabled="disabled"
                                aria-disabled="true">
                            <?php foreach ($wpacuDashboardRoles as $roleKey => $roleLabel) { ?>
                                <option value="<?php echo esc_attr($roleKey); ?>"<?php
                                echo in_array($roleKey, $wpacuDashboardRuleData['load_logged_in_via_role_chosen'], true)
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
</div>
