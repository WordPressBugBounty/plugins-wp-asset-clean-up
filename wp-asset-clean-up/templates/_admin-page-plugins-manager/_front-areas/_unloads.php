<?php
// This file is included from /templates/_admin-page-plugins-manager/_front.php
use WpAssetCleanUp\Admin\MiscAdmin;

if (! isset($data)) {
	exit;
}
?>
<div data-wpacu-plugin-path="<?php echo esc_attr($data['plugin_path']); ?>"
     class="wpacu_plugin_unload_rules_options_wrap">
	<div class="wpacu_plugin_rules_wrap">
		<fieldset>
			<legend><strong>Unload this plugin</strong> in the front-end:</legend>
			<ul class="wpacu_plugin_rules">
				<li>
					<label for="wpacu_global_unload_plugin_<?php echo MiscAdmin::sanitizeValueForHtmlAttr($data['plugin_path']); ?>">
						<input data-wpacu-plugin-path="<?php echo esc_attr($data['plugin_path']); ?>"
						       style="margin-right: 0;"
						       disabled="disabled"
						       class="disabled wpacu_plugin_unload_site_wide wpacu_plugin_unload_rule_input"
						       id="wpacu_global_unload_plugin_<?php echo MiscAdmin::sanitizeValueForHtmlAttr($data['plugin_path']); ?>"
						       type="checkbox"
						       value="unload_site_wide" />
						<a class="go-pro-link-no-style"
						   href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL . '?utm_source=manage_plugin&utm_medium=unload_plugin_site_wide'); ?>"><span class="wpacu-tooltip" style="width: 200px; margin-left: -108px;">This feature is locked for Pro users<br />Click here to upgrade!</span><img width="20" height="20" style="margin: 0; vertical-align: text-bottom;" src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/icon-lock.svg" alt="" /></a>&nbsp;
						<span>On all pages</span></label>
				</li>
				<li>
					<label for="wpacu_home_page_unload_plugin_<?php echo MiscAdmin::sanitizeValueForHtmlAttr($data['plugin_path']); ?>">
					    <input data-wpacu-plugin-path="<?php echo esc_attr($data['plugin_path']); ?>"
                               style="margin-right: 0;"
                               disabled="disabled"
                               class="disabled wpacu_plugin_unload_home_page wpacu_plugin_unload_rule_input"
                               id="wpacu_home_page_unload_plugin_<?php echo MiscAdmin::sanitizeValueForHtmlAttr($data['plugin_path']); ?>"
                               type="checkbox"
                               name="wpacu_plugins[<?php echo esc_attr($data['plugin_path']); ?>][status][]"
                               value="unload_home_page" />
                        <a class="go-pro-link-no-style"
                           href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL . '?utm_source=manage_plugin&utm_medium=unload_plugin_on_home_page'); ?>"><span class="wpacu-tooltip" style="width: 200px; margin-left: -108px;">This feature is locked for Pro users<br />Click here to upgrade!</span><img width="20" height="20" style="margin: 0; vertical-align: text-bottom;" src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/icon-lock.svg" alt="" /></a>&nbsp;
					<span>On the homepage</span></label>
				</li>
				<!-- [Unload based on the post type] -->
                <li>
                    <label for="wpacu_via_post_type_unload_plugin_<?php echo MiscAdmin::sanitizeValueForHtmlAttr($data['plugin_path']); ?>">
                        <input data-wpacu-plugin-path="<?php echo esc_attr($data['plugin_path']); ?>"
                               style="margin-right: 0;"
                               class="disabled wpacu_plugin_unload_via_post_type wpacu_plugin_unload_rule_input"
                               id="wpacu_via_post_type_unload_plugin_<?php echo MiscAdmin::sanitizeValueForHtmlAttr($data['plugin_path']); ?>"
                               type="checkbox"
                               disabled="disabled"
                               name="wpacu_plugins[<?php echo esc_attr($data['plugin_path']); ?>][status][]"
                               value="unload_via_post_type" />
                        <a class="go-pro-link-no-style"
                           href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL . '?utm_source=manage_plugin&utm_medium=unload_plugin_on_pages_of_post_types'); ?>"><span class="wpacu-tooltip" style="width: 200px; margin-left: -108px;">This feature is locked for Pro users<br />Click here to upgrade!</span><img width="20" height="20" style="margin: 0; vertical-align: text-bottom;" src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/icon-lock.svg" alt="" /></a>&nbsp;
                        <span>On pages of these post types:</span>
                    </label>
                    <a class="help_link"
                       target="_blank"
                       href="https://www.assetcleanup.com/docs/?p=1613"><span style="color: #74777b;" class="dashicons dashicons-editor-help"></span></a>
                </li>
                <!-- [/Unload based on the post type] -->

                <!-- [Unload on taxonomy pages] -->
                <li>
                    <label for="wpacu_via_taxonomy_unload_plugin_<?php echo MiscAdmin::sanitizeValueForHtmlAttr($data['plugin_path']); ?>">
                        <input data-wpacu-plugin-path="<?php echo esc_attr($data['plugin_path']); ?>"
                               style="margin-right: 0;"
                               disabled="disabled"
                               class="disabled wpacu_plugin_unload_via_tax wpacu_plugin_unload_rule_input"
                               id="wpacu_via_taxonomy_unload_plugin_<?php echo MiscAdmin::sanitizeValueForHtmlAttr($data['plugin_path']); ?>"
                               type="checkbox"
                               name="wpacu_plugins[<?php echo esc_attr($data['plugin_path']); ?>][status][]"
                               value="unload_via_tax" />
                        <a class="go-pro-link-no-style"
                           href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL . '?utm_source=manage_plugin&utm_medium=unload_plugin_on_taxonomy_pages'); ?>"><span class="wpacu-tooltip" style="width: 200px; margin-left: -108px;">This feature is locked for Pro users<br />Click here to upgrade!</span><img width="20" height="20" style="margin: 0; vertical-align: text-bottom;" src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/icon-lock.svg" alt="" /></a>&nbsp;
                        <span>On these taxonomy pages:</span></label>
                    <a class="help_link"
                       target="_blank"
                       href="https://www.assetcleanup.com/docs/?p=1579"><span style="color: #74777b;" class="dashicons dashicons-editor-help"></span></a>
                </li>
                <!-- [/Unload on taxonomy pages] -->

                <!-- [Unload on archive (post list) pages] -->
                <li>
                    <label for="wpacu_via_archive_type_unload_plugin_<?php echo MiscAdmin::sanitizeValueForHtmlAttr($data['plugin_path']); ?>">
                        <input data-wpacu-plugin-path="<?php echo esc_attr($data['plugin_path']); ?>"
                               style="margin-right: 0;"
                               disabled="disabled"
                               class="disabled wpacu_plugin_unload_via_archive wpacu_plugin_unload_rule_input"
                               id="wpacu_via_archive_type_unload_plugin_<?php echo MiscAdmin::sanitizeValueForHtmlAttr($data['plugin_path']); ?>"
                               type="checkbox"
                               name="wpacu_plugins[<?php echo esc_attr($data['plugin_path']); ?>][status][]"
                               value="unload_via_archive" />
                        <a class="go-pro-link-no-style"
                           href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL . '?utm_source=manage_plugin&utm_medium=unload_plugin_on_archive_pages'); ?>"><span class="wpacu-tooltip" style="width: 200px; margin-left: -108px;">This feature is locked for Pro users<br />Click here to upgrade!</span><img width="20" height="20" style="margin: 0; vertical-align: text-bottom;" src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/icon-lock.svg" alt="" /></a>&nbsp;
                        <span>On these archive (page list) pages:</span></label>
                    <a class="help_link"
                       target="_blank"
                       href="https://www.assetcleanup.com/docs/?p=1647"><span style="color: #74777b;" class="dashicons dashicons-editor-help"></span></a>
                </li>
                <!-- [/Unload on archive (post list) pages] -->

				<li>
					<label for="wpacu_unload_it_regex_option_<?php echo MiscAdmin::sanitizeValueForHtmlAttr($data['plugin_path']); ?>"
						   style="margin-right: 0;">
						<input data-wpacu-plugin-path="<?php echo esc_attr($data['plugin_path']); ?>"
						       style="margin-right: 0;"
						       disabled="disabled"
						       id="wpacu_unload_it_regex_option_<?php echo MiscAdmin::sanitizeValueForHtmlAttr($data['plugin_path']); ?>"
						       class="disabled wpacu_plugin_unload_regex_option wpacu_plugin_unload_rule_input"
						       type="checkbox"
						       value="unload_via_regex">
						<a class="go-pro-link-no-style"
						   href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL . '?utm_source=manage_plugin&utm_medium=unload_plugin_via_regex'); ?>"><span class="wpacu-tooltip" style="width: 200px; margin-left: -108px;">This feature is locked for Pro users<br />Click here to upgrade!</span><img width="20" height="20" style="margin: 0; vertical-align: text-bottom;" src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/icon-lock.svg" alt="" /></a>&nbsp;
                        <span>If request URI matches these RegEx(es):</span>
                    </label>
					<a class="help_link unload_it_regex"
					   target="_blank"
					   href="https://assetcleanup.com/docs/?p=372#wpacu-unload-plugins-via-regex"><span style="color: #74777b;" class="dashicons dashicons-editor-help"></span></a>
				</li>
				<li>
					<label for="wpacu_unload_it_logged_in_plugin_<?php echo MiscAdmin::sanitizeValueForHtmlAttr($data['plugin_path']); ?>" style="margin-right: 0;">
						<input data-wpacu-plugin-path="<?php echo esc_attr($data['plugin_path']); ?>"
						       style="margin-right: 0;"
						       disabled="disabled"
						       id="wpacu_unload_it_logged_in_plugin_<?php echo MiscAdmin::sanitizeValueForHtmlAttr($data['plugin_path']); ?>"
						       class="disabled wpacu_plugin_unload_logged_in"
						       type="checkbox"
						       name="wpacu_plugins[<?php echo esc_attr($data['plugin_path']); ?>][status][]"
						       value="unload_logged_in" />
						<a class="go-pro-link-no-style"
						   href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL . '?utm_source=manage_plugin&utm_medium=unload_plugin_if_logged_in'); ?>"><span class="wpacu-tooltip" style="width: 200px; margin-left: -108px;">This feature is locked for Pro users<br />Click here to upgrade!</span><img width="20" height="20" style="margin: 0; vertical-align: text-bottom;" src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/icon-lock.svg" alt="" /></a>&nbsp;
					    <span>If the user is logged in</span>
                    </label>
				</li>
                <li>
                    <label for="wpacu_unload_logged_in_via_role_plugin_<?php echo MiscAdmin::sanitizeValueForHtmlAttr($data['plugin_path']); ?>"
                           style="margin-right: 0;">
                        <input data-wpacu-plugin-path="<?php echo esc_attr($data['plugin_path']); ?>"
                               id="wpacu_unload_logged_in_via_role_plugin_<?php echo MiscAdmin::sanitizeValueForHtmlAttr($data['plugin_path']); ?>"
                               disabled="disabled"
                               class="disabled wpacu_plugin_unload_logged_in_via_role wpacu_plugin_unload_rule_input"
                               type="checkbox"
                               name="wpacu_plugins[<?php echo esc_attr($data['plugin_path']); ?>][status][]"
                               value="unload_logged_in_via_role" />
                        <a class="go-pro-link-no-style"
                           href="<?php echo apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL . '?utm_source=manage_plugin&utm_medium=unload_plugin_user_logged_in_via_role'); ?>"><span class="wpacu-tooltip" style="width: 200px; margin-left: -108px;">This feature is locked for Pro users<br />Click here to upgrade!</span><img width="20" height="20" style="margin: 0; vertical-align: text-bottom;" src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/icon-lock.svg" alt="" /></a>&nbsp;
                        <span>If the logged-in user has any of these roles:</span>
                    </label>
                    <a class="help_link"
                       target="_blank"
                       href="https://www.assetcleanup.com/docs/?p=1688"><span style="color: #74777b;" class="dashicons dashicons-editor-help"></span></a>
                </li>
			</ul>
			<div class="wpacu_clearfix"></div>
		</fieldset>
	</div>
</div>
