<?php
use WpAssetCleanUp\Admin\SettingsAdmin;
use WpAssetCleanUp\Admin\SettingsAdminOnlyForAdmin;
use WpAssetCleanUp\Menu;

if (! isset($data, $postTypesList)) {
	exit;
}
?>
<p><?php _e('Choose how the assets are retrieved and whether you would like to see them within the Dashboard / Front-end view', 'wp-asset-clean-up'); ?>; <?php _e('Decide how the management list of CSS &amp; JavaScript files will show up and get sorted, depending on your preferences.', 'wp-asset-clean-up'); ?></p>

<table class="wpacu-form-table">
    <tr valign="top">
        <th scope="row">
            <label for="wpacu_dashboard"><?php _e('Manage in the Dashboard', 'wp-asset-clean-up'); ?></label>
        </th>
        <td>
            <label class="wpacu_switch">
                <input id="wpacu_dashboard"
                       data-target-opacity="wpacu_manage_dashboard_assets_list"
                       type="checkbox"
                    <?php echo ($data['dashboard_show'] == 1) ? 'checked="checked"' : ''; ?>
                       name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[dashboard_show]"
                       value="1" /> <span class="wpacu_slider wpacu_round"></span> </label>
            &nbsp;
            <?php _e('This will show the list of assets in a meta box on edit the post (any type) / page within the Dashboard', 'wp-asset-clean-up'); ?>

            <div id="wpacu_manage_dashboard_assets_list" <?php if ($data['dashboard_show'] != 1) { echo 'style="opacity: 0.4;"'; } ?>>
                <p><?php _e('The assets would be retrieved via AJAX call(s) that will fetch the post/page URL and extract all the styles &amp; scripts that are enqueued.', 'wp-asset-clean-up'); ?></p>
                <p><?php _e('Note that sometimes the assets list is not loading within the Dashboard. That could be because "mod_security" Apache module is enabled or some security plugins are blocking the AJAX request. If this option doesn\'t work, consider managing the list in the front-end view.', 'wp-asset-clean-up'); ?></p>

                <div id="wpacu-settings-assets-retrieval-mode" <?php if (! ($data['dashboard_show'] == 1)) { echo 'style="display: none;"'; } ?>>
                    <ul id="wpacu-dom-get-type-selections">
                        <li>
                            <label><?php _e('Select a retrieval way', 'wp-asset-clean-up'); ?>:</label>
                        </li>
                        <li>
                            <label>
                                <input class="wpacu-dom-get-type-selection"
                                       data-target="wpacu-dom-get-type-direct-info"
                                       <?php if ($data['dom_get_type'] === 'direct') { ?>checked="checked"<?php } ?>
                                       type="radio" name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[dom_get_type]"
                                       value="direct" /> <?php _e('Direct', 'wp-asset-clean-up'); ?> * <small>as if the admin visits the page</small>
                            </label>
                        </li>
                        <li>
                            <label>
                                <input class="wpacu-dom-get-type-selection"
                                       data-target="wpacu-dom-get-type-wp-remote-post-info"
                                       <?php if ($data['dom_get_type'] === 'wp_remote_post') { ?>checked="checked"<?php } ?>
                                       type="radio" name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[dom_get_type]"
                                       value="wp_remote_post" /> WP Remote POST * <small>as if a guest visits the page</small>
                            </label>
                        </li>
                    </ul>

                    <div class="wpacu_clearfix" style="height: 0;"></div>

                    <ul id="wpacu-dom-get-type-infos">
                        <li <?php if ($data['dom_get_type'] !== 'direct') { ?>style="display: none;"<?php } ?>
                            class="wpacu-dom-get-type-info"
                            id="wpacu-dom-get-type-direct-info">
                            <strong><?php _e('Direct', 'wp-asset-clean-up'); ?></strong> - <?php _e('This one makes an AJAX call directly on the URL for which the assets are retrieved, then an extra WordPress AJAX call to process the list. Sometimes, due to some external factors (e.g. mod_security module from Apache, security plugin or the fact that non-http is forced for the front-end view and the AJAX request will be blocked), this might not work and another choice method might work better. This used to be the only option available, prior to version 1.2.4.4 and is set as default.', 'wp-asset-clean-up'); ?>
                        </li>
                        <li <?php if ($data['dom_get_type'] !== 'wp_remote_post') { ?>style="display: none;"<?php } ?>
                            class="wpacu-dom-get-type-info"
                            id="wpacu-dom-get-type-wp-remote-post-info">
                            <strong>WP Remote POST</strong> - <?php _e('It makes a WordPress AJAX call and gets the HTML source code through wp_remote_post(). This one is less likely to be blocked as it is made on the same protocol (no HTTP request from HTTPS). However, in some cases (e.g. a different load balancer configuration), this might not work when the call to fetch a domain\'s URL (your website) is actually made from the same domain.', 'wp-asset-clean-up'); ?>
                        </li>
                    </ul>
                </div>

                <hr /><div class="wpacu_clearfix" style="height: 0;"></div>

                <p style="line-height: 24px; margin-top: 0;"><span style="color: #ffc107;" class="dashicons dashicons-lightbulb"></span> <strong>Note:</strong> The option below only applies to the edit post/page/taxonomy area. By default, this has always been enabled. You can keep it disabled and only manage the CSS/JS within "CSS & JS MANAGER" (top menu), if you feel that the edit post/page/taxonomy area is too cluttered. Some people prefer to have that area cleaner, especially if there are plenty of other elements there (e.g. meta boxes generated by other plugins).</p>

                <input type="hidden" name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[show_assets_meta_box]" value="0" />
                <fieldset style="margin: 15px 0 0 0; padding: 10px; border: 1px solid #8c8f94; border-radius: 10px;">
                    <legend style="border: 1px solid #8c8f94; padding: 10px; border-radius: 10px;">
                        <label for="wpacu-show-assets-meta-box-checkbox" class="wpacu_switch">
                            <input <?php echo ($data['show_assets_meta_box'] == 1) ? 'checked="checked"' : ''; ?>
                                    id="wpacu-show-assets-meta-box-checkbox" type="checkbox"
                                    name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[show_assets_meta_box]"
                                    value="1"/> <span class="wpacu_slider wpacu_round"></span>
                        </label> &nbsp; Show "<?php echo WPACU_PLUGIN_TITLE; ?>: CSS &amp; JavaScript Manager / Page Options" meta box
                    </legend>

                    <div id="wpacu-show-assets-enabled-area" style="<?php echo (! $data['show_assets_meta_box']) ? 'display: none;' : ''; ?>">
                        <p style="margin-top: 8px;"><?php _e('When you are in the Dashboard and edit a post, page, custom post type, category or custom taxonomy and rarely manage loaded CSS/JS from the "Asset CleanUp: CSS & JavaScript Manager", you can choose to fetch the list when you click on a button. This will help declutter the edit page on load and also save resources as AJAX calls to the front-end won\'t be made to retrieve the assets\' list.', 'wp-asset-clean-up'); ?></p>
                        <ul style="margin-bottom: 0;">
                            <li>
                                <label for="assets_list_show_status_default">
                                    <input id="assets_list_show_status_default"
                                           <?php if (! $data['assets_list_show_status'] || $data['assets_list_show_status'] === 'default') { ?>checked="checked"<?php } ?>
                                           type="radio"
                                           name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[assets_list_show_status]"
                                           value="default" /> <?php _e('Fetch the assets automatically and show the list', 'wp-asset-clean-up'); ?> (<?php _e('Default', 'wp-asset-clean-up'); ?>)
                                </label>
                            </li>
                            <li>
                                <label for="assets_list_show_status_fetch_on_click">
                                    <input id="assets_list_show_status_fetch_on_click"
                                           <?php if ($data['assets_list_show_status'] === 'fetch_on_click') { ?>checked="checked"<?php } ?>
                                           type="radio"
                                           name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[assets_list_show_status]"
                                           value="fetch_on_click" /> <?php _e('Fetch the assets on a button click', 'wp-asset-clean-up'); ?>
                                </label>
                            </li>
                        </ul><div class="wpacu_clearfix" style="height: 0; clear: both;"></div>

                        <hr />

                        <div id="wpacu-settings-hide-meta-boxes">
                            <label for="wpacu-hide-meta-boxes-for-post-types">Hide the meta box for the following public post types (multiple selection drop-down):</label><br />
                            <select style="margin-top: 4px; min-width: 340px;"
                                    id="wpacu-hide-meta-boxes-for-post-types"
                                <?php if ($data['input_style'] !== 'standard') { ?>
                                    data-placeholder="Choose Post Type(s)..."
                                    class="wpacu_chosen_select"
                                <?php } ?>
                                    multiple="multiple"
                                    name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[hide_meta_boxes_for_post_types][]">
                                <?php foreach ($postTypesList as $postTypeKey => $postTypeValue) { ?>
                                    <option <?php if (in_array($postTypeKey, $data['hide_meta_boxes_for_post_types'])) { echo 'selected="selected"'; } ?>
                                        value="<?php echo esc_attr($postTypeKey); ?>"><?php echo esc_html($postTypeValue); ?></option>
                                <?php } ?>
                            </select>
                            <p id="wpacu-hide-meta-boxes-for-post-types-info" style="margin-top: 4px;"><small>Sometimes, you might have a post type marked as 'public', but it's not queryable or doesn't have a public URL of its own, making the assets list irrelevant. Or, you have finished optimising pages for a particular post type and you wish to have the assets list hidden. You can choose to hide the meta boxes for these particular post types.</small></p>
                        </div>
                    </div>

                    <div id="wpacu-show-assets-disabled-area" style="<?php echo ($data['show_assets_meta_box'] == 1) ? 'display: none;' : ''; ?>">
                        <p>In order to view the options related to the CSS &amp; JS manager meta box located within the edit post/page/taxonomy area, the above option needs to be enabled.</p>
                    </div>
                </fieldset>
            </div>
        </td>
    </tr>
    <tr valign="top">
        <th scope="row">
            <label for="wpacu_frontend"><?php _e('Manage in the Front-end', 'wp-asset-clean-up'); ?></label>
        </th>
        <td>
            <label class="wpacu_switch">
                <input id="wpacu_frontend"
                       data-target-opacity="wpacu_frontend_manage_assets_list"
                       type="checkbox"
                    <?php echo ($data['frontend_show'] == 1) ? 'checked="checked"' : ''; ?>
                       name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[frontend_show]"
                       value="1" /> <span class="wpacu_slider wpacu_round"></span> </label>
            &nbsp;
            If you are logged in, this will make the list of assets show below the page that you view (either home page, a post or a page).

            <div id="wpacu_frontend_manage_assets_list" <?php if ($data['frontend_show'] != 1) { echo 'style="opacity: 0.4;"'; } ?>>
                <p style="margin-top: 10px;">The area will be shown through the <code>wp_footer</code> action so in case you do not see the asset list at the bottom of the page, make sure the theme is using <a href="https://codex.wordpress.org/Function_Reference/wp_footer"><code>wp_footer()</code></a> function before the <code>&lt;/body&gt;</code> tag. Any theme that follows the standards should have it. If not, you will have to add it to make sure other plugins and code from functions.php will work fine.</p>

                <p style="margin-top: 18px;">&#10230; <strong>NOTE:</strong> This option has to be enabled if you would like to manage assets on the following pages: Search Results, Author &amp; Date Archives, 404 Not Found.</p>

                <div id="wpacu-settings-frontend-exceptions" <?php if (! ($data['frontend_show'] == 1)) { echo 'style="display: none;"'; } ?>>
                    <div style="margin: 0 0 10px;"><label for="wpacu_frontend_show_exceptions"><span class="dashicons dashicons-info"></span> In some situations, you might want to avoid showing the CSS/JS list at the bottom of the pages (e.g. you're using a page builder such as Divi, you often load specific pages as an admin and you don't need to manage assets there or you do it rarely etc.). If that's the case, you can use the following textarea to prevent the list from showing up on pages where the <strong>URI contains</strong> the specified strings (<?php _e('one per line', 'wp-asset-clean-up'); ?>):</label></div>
                    <textarea id="wpacu_frontend_show_exceptions"
                              name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[frontend_show_exceptions]"
                              rows="5"
                              style="width: 100%;"><?php echo esc_textarea($data['frontend_show_exceptions']); ?></textarea>
                    <p><strong>Example:</strong> If the URI contains <strong>et_fb=1</strong> which triggers the front-end Divi page builder, then you can specify it in the list above (it's added by default) to prevent the asset list from showing below the page builder area.</p>
                </div>
            </div>
        </td>
    </tr>

    <?php
    if (current_user_can(Menu::$defaultAccessRole)) {
        $allowManageAssetsText = __('Only the chosen administrators will have access to the plugin\'s CSS &amp; JS Manager.', 'wp-asset-clean-up');
    ?>
        <tr valign="top">
            <th scope="row" class="setting_title">
                <label for="wpacu-allow-manage-assets-to-select"><?php _e('Allow managing assets to:', 'wp-asset-clean-up'); ?></label>
                <p class="wpacu_subtitle"><small><em><?php echo esc_html($allowManageAssetsText); ?></em></small></p>
            </th>
            <td>
                <?php
                $currentUserId = get_current_user_id();

                $allAdminUsers = SettingsAdminOnlyForAdmin::getAllAdminUsers();
                ?>
                <select style="vertical-align: top;" id="wpacu-allow-manage-assets-to-select"
                        name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[allow_manage_assets_to]">
                    <option <?php if (in_array($data['allow_manage_assets_to'], array('', 'any_admin'))) { ?>selected="selected"<?php } ?> value="any_admin">any administrator</option>
                    <option <?php if ($data['allow_manage_assets_to'] === 'chosen') { ?>selected="selected"<?php } ?> value="chosen">only to the following admin(s):</option>
                </select>
                &nbsp;
                <div <?php if (in_array($data['allow_manage_assets_to'], array('', 'any_admin'))) { ?>class="wpacu_hide"<?php } ?>
                     id="wpacu-allow-manage-assets-to-select-list-area">
                    <select id="wpacu-allow-manage-assets-to-select-list"
                            name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[allow_manage_assets_to_list][]"
                        <?php if ($data['input_style'] !== 'standard') { ?>
                            class="wpacu_chosen_can_be_later_enabled"
                            data-placeholder="Choose the admin(s) who will access the list..."
                        <?php } ?>
                            multiple="multiple">
                        <?php
                        foreach ( $allAdminUsers as $user ) {
                            $appendText = $selected = '';

                            if ($currentUserId === $user->ID) {
                                $appendText = ' &#10141; yourself';
                            }

                            if (isset($data['allow_manage_assets_to_list']) && is_array($data['allow_manage_assets_to_list']) && in_array($user->ID, $data['allow_manage_assets_to_list'])) {
                                $selected = 'selected="selected"';
                            }

                            echo '<option '.$selected.' value="'.$user->ID.'">' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email ) . ')'.$appendText.'</option>';
                        }
                        ?>
                    </select>
                    <div style="margin: 2px 0 0;"><small>This is a multiple selection drop-down. If nothing is chosen from the list, it will default to "any administrator" from the list.</small></div>
                </div>

                <div style="margin: 10px 0 0;"><p>Some people that have admin access might be confused by the CSS/JS manager (which could be for the developer of the website). If they are mostly editing articles, updating WooCommerce products and so on, there's no point for them to keep seeing a cluttered edit post/page with CSS/JS assets that can even be changed by mistake. You can leave this only to the developers with "administrator" roles.</p></div>

                <div style="margin-top: 10px;">
                    <strong>Note: </strong> Anyone with access to this option, will be able to change it, including the restrictive users. If anyone with access to this plugin would want to enable the CSS/JS manager for any reason, they have the possiblity to do that.
                </div>
            </td>
        </tr>
    <?php
    }
    ?>
    <tr valign="top">
        <th scope="row" class="setting_title">
            <label for="wpacu_assets_list_layout"><?php _e('Assets List Layout', 'wp-asset-clean-up'); ?></label>
            <p class="wpacu_subtitle"><small><em><?php _e('You can decide how would you like to view the list of the enqueued CSS &amp; JavaScript', 'wp-asset-clean-up'); ?></em></small></p>
        </th>
        <td>
            <label>
                <?php echo SettingsAdmin::generateAssetsListLayoutDropDown($data['assets_list_layout'], WPACU_PLUGIN_ID . '_settings[assets_list_layout]' ); ?>
            </label>

            <div id="wpacu-assets-list-by-location-selected" style="margin: 10px 0; <?php if ($data['assets_list_layout'] !== 'by-location') { ?> display: none; <?php } ?>">
                <div style="margin-bottom: 6px;"><?php _e('When list is grouped by location, keep the assets from each of the plugins in the following state', 'wp-asset-clean-up'); ?>:</div>
                <ul class="assets_list_layout_areas_status_choices">
                    <li>
                        <label for="assets_list_layout_plugin_area_status_expanded">
                            <input id="assets_list_layout_plugin_area_status_expanded"
                                   <?php if (! $data['assets_list_layout_plugin_area_status'] || $data['assets_list_layout_plugin_area_status'] === 'expanded') { ?>checked="checked"<?php } ?>
                                   type="radio"
                                   name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[assets_list_layout_plugin_area_status]"
                                   value="expanded"> <?php _e('Expanded', 'wp-asset-clean-up'); ?> (<?php _e('Default', 'wp-asset-clean-up'); ?>)
                        </label>
                    </li>
                    <li>
                        <label for="assets_list_layout_plugin_area_status_contracted">
                            <input id="assets_list_layout_plugin_area_status_contracted"
                                   <?php if ($data['assets_list_layout_plugin_area_status'] === 'contracted') { ?>checked="checked"<?php } ?>
                                   type="radio"
                                   name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[assets_list_layout_plugin_area_status]"
                                   value="contracted"> <?php _e('Contracted', 'wp-asset-clean-up'); ?>
                        </label>
                    </li>
                </ul>
                <div class="clear"></div>
            </div>

            <div class="wpacu_clearfix"></div>

            <p style="margin-top: 8px;"><?php _e('These are various ways in which the list of assets that you will manage will show up. Depending on your preference, you might want to see the list of styles &amp; scripts first, or all together sorted in alphabetical order etc.', 'wp-asset-clean-up'); ?> <?php _e('Options that are disabled are available in the Pro version.', 'wp-asset-clean-up'); ?></p>
        </td>
    </tr>

    <tr valign="top">
        <th scope="row">
            <?php _e('On Assets List Layout Load, keep the groups:', 'wp-asset-clean-up'); ?>
        </th>
        <td>
            <ul class="assets_list_layout_areas_status_choices">
                <li>
                    <label for="assets_list_layout_areas_status_expanded">
                        <input id="assets_list_layout_areas_status_expanded"
                               <?php if (! $data['assets_list_layout_areas_status'] || $data['assets_list_layout_areas_status'] === 'expanded') { ?>checked="checked"<?php } ?>
                               type="radio"
                               name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[assets_list_layout_areas_status]"
                               value="expanded"> <?php _e('Expanded', 'wp-asset-clean-up'); ?> (<?php _e('Default', 'wp-asset-clean-up'); ?>)
                    </label>
                </li>
                <li>
                    <label for="assets_list_layout_areas_status_contracted">
                        <input id="assets_list_layout_areas_status_contracted"
                               <?php if ($data['assets_list_layout_areas_status'] === 'contracted') { ?>checked="checked"<?php } ?>
                               type="radio"
                               name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[assets_list_layout_areas_status]"
                               value="contracted"> <?php _e('Contracted', 'wp-asset-clean-up'); ?>
                    </label>
                </li>
            </ul>
            <div class="wpacu_clearfix"></div>

            <p><?php _e('Sometimes, when you have plenty of elements in the edit page, you might want to contract the list of assets when you\'re viewing the page as it will save space. This can be a good practice, especially when you finished optimising the pages and you don\'t want to keep seeing the long list of files every time you edit a page.', 'wp-asset-clean-up'); ?></p>
            <p><strong><?php _e('Note', 'wp-asset-clean-up'); ?>:</strong> <?php _e('This does not include the assets rows within the groups which are expanded &amp; contracted individually, depending on your preference.', 'wp-asset-clean-up'); ?></p>
        </td>
    </tr>

    <tr valign="top">
        <th scope="row">
            <?php _e('On Assets List Layout Load, keep "Inline code associated with this handle" area', 'wp-asset-clean-up'); ?>:
        </th>
        <td>
            <ul class="assets_list_inline_code_status_choices">
                <li>
                    <label for="assets_list_inline_code_status_contracted">
                        <input id="assets_list_inline_code_status_contracted"
                               <?php if ($data['assets_list_inline_code_status'] === 'contracted') { ?>checked="checked"<?php } ?>
                               type="radio"
                               name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[assets_list_inline_code_status]"
                               value="contracted"> <?php _e('Contracted', 'wp-asset-clean-up'); ?> (<?php _e('Default', 'wp-asset-clean-up'); ?>)
                    </label>
                </li>
                <li>
                    <label for="assets_list_inline_code_status_expanded">
                        <input id="assets_list_inline_code_status_expanded"
                               <?php if (! $data['assets_list_inline_code_status'] || $data['assets_list_inline_code_status'] === 'expanded') { ?>checked="checked"<?php } ?>
                               type="radio"
                               name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[assets_list_inline_code_status]"
                               value="expanded"> <?php _e('Expanded', 'wp-asset-clean-up'); ?>
                    </label>
                </li>
            </ul>
            <div class="wpacu_clearfix"></div>

            <p><?php echo sprintf(
                    __('Some assets (CSS &amp; JavaScript) have inline code associate with them and often, they are quite large, making the asset row bigger and requiring you to scroll more until you reach a specific area. By setting it to "%s", it will hide all the inline code by default and you can view it by clicking on the toggle link inside the asset row.', 'wp-asset-clean-up'),
                    __('Contracted', 'wp-asset-clean-up')
                ); ?></p>
        </td>
    </tr>

    <tr valign="top">
        <th scope="row">
            <label for="wpacu_hide_core_files"><?php _e('Hide WordPress Core Files From The Assets List?', 'wp-asset-clean-up'); ?></label>
        </th>
        <td>
            <label class="wpacu_switch">
                <input id="wpacu_hide_core_files"
                       type="checkbox"
                    <?php echo ($data['hide_core_files'] == 1) ? 'checked="checked"' : ''; ?>
                       name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[hide_core_files]"
                       value="1" /> <span class="wpacu_slider wpacu_round"></span> </label>
            &nbsp;
            <?php echo sprintf(__('WordPress Core Files have handles such as %s', 'wp-asset-clean-up'), "'jquery', 'wp-embed', 'comment-reply', 'dashicons'"); ?> etc.
            <p style="margin-top: 10px;"><?php _e('They should only be unloaded by experienced developers when they are convinced that are not needed in particular situations. It\'s better to leave them loaded if you have any doubts whether you need them or not. By hiding them in the assets management list, you will see a smaller assets list (easier to manage) and you will avoid updating by mistake any option (unload, async, defer) related to any core file.', 'wp-asset-clean-up'); ?></p>
        </td>
    </tr>
</table>
