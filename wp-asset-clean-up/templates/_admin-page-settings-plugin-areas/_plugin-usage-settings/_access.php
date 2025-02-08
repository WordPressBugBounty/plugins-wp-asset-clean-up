<?php
if (! isset($data)) {
    exit;
}

use WpAssetCleanUp\Admin\SettingsAdminOnlyForAdmin;

?>
<div style="margin: 0 0 22px;"><?php
echo sprintf(
        __('By default, for security reasons, %s can be accesed within the Dashboard by <strong>Super Admins</strong> (somebody with access to the site network administration features and all other features) and <strong>Administrators</strong> (somebody who has access to all the administration features within a single site).', 'wp-asset-clean-up'),
    WPACU_PLUGIN_TITLE
);
?></div>

<div class="wpacu-warning" style="font-size: inherit;">There are situations when non-admins (e.g. a developer that works on your website), might need access to <?php echo WPACU_PLUGIN_TITLE; ?> in order to optimize your website.
    <div style="margin: 10px 0 0;"><em>️<span class="dashicons dashicons-info"></span> The option below will allow you to give plugin access to other non-admin users.</em></div>
</div>

<fieldset class="wpacu-options-grouped-in-settings" style="margin-bottom: 30px;">
    <legend>Give access based on any other non-administrator user role</legend>
    <div style="margin: 5px 10px;">
    <?php
    $nonAdminRolesArray = SettingsAdminOnlyForAdmin::getAllNonAdminUserRolesWithAnyPluginAccessCap();

    if (empty($nonAdminRolesArray['non_admin_role_slugs'])) {
    ?>
        <p><em><span class="dashicons dashicons-info"></span> There are no other user roles besides the "<?php echo \WpAssetCleanUp\Menu::$defaultAccessRole; ?>" one. Thus, this feature is not relevant.</em></p>
    <?php
    } else {
        ?>
        <div style="vertical-align: top; display: inline-block;">Any other user roles that could access the plugin:&nbsp;&nbsp;</div>
        <select name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[access_via_non_admin_user_roles][]"
            <?php if ($data['input_style'] === 'enhanced') { ?>
                class="wpacu_chosen_select"
                data-placeholder="<?php esc_attr_e('You can choose more than one role', 'wp-asset-clean-up-pro'); ?>..."
            <?php } ?>
                multiple="multiple">
            <?php
            if (empty($data['access_via_non_admin_user_roles']) || is_string($data['access_via_non_admin_user_roles'])) {
                $data['access_via_non_admin_user_roles'] = array();
            }

            foreach ($nonAdminRolesArray['non_admin_role_slugs'] as $roleSlug) {
                $roleValues = $nonAdminRolesArray['roles']->roles[$roleSlug];
                ?>
                <option <?php if (in_array($roleSlug, $data['access_via_non_admin_user_roles'])) { ?>selected="selected"<?php } ?>
                        value="<?php echo $roleSlug; ?>"><?php echo $roleValues['name']; ?> (slug: <?php echo $roleSlug; ?>)</option>
                <?php
            }
            ?>
        </select>
    <?php
    }
    ?>
    </div>
</fieldset>

<?php
// Fetch the ones with the access capability
$nonAdminUsersWithCapIds = $data['access_via_specific_non_admin_users'];

if (is_string($nonAdminUsersWithCapIds)) {
    $nonAdminUsersWithCapIds = array();
}

// If the total number of users is above this number, the auto-complete is activated
// For instance, there could be tens of thousands of users on specific websites
// It's not effective to put all in one drop-down (too many resources would be used)
$totalNonAdminUsersLimitUntilAutoCompleteIsActivated = 200;
$allUsers = array();

global $wpdb;

$totalNonAdminUsers = SettingsAdminOnlyForAdmin::getTotalNonAdminUsers();

?>
<fieldset id="wpacu-area-option-give-access-specific-non-admin-users"
          class="wpacu-options-grouped-in-settings" style="margin-bottom: 30px;">
    <legend>Give access for specific non-administrator users</legend>
    <div style="margin: 0 0 15px;">This is useful if the option to give access via role is not for you. For instance, there could be several users that have a specific role, but you would rather give access to the plugin area to only one particular user.</div>

    <?php
    if ($totalNonAdminUsers > 0) {
        if ( ! SettingsAdminOnlyForAdmin::useAutoCompleteSearchForNonAdminUsersDd() ) {
            $allUsers = SettingsAdminOnlyForAdmin::getAllNonAdminUsers();
        ?>
            <div style="vertical-align: top; display: inline-block;">You can choose one or multiple users that from the drop-down:&nbsp;</div>

            <div style="display: inline-block; width: 100%; max-width: 600px;">
                <select name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[access_via_specific_non_admin_users][]"
                        id="wpacu-access-via-specific-users-dd"
                    <?php if ($data['input_style'] === 'enhanced') { ?>
                        class="wpacu_chosen_select wpacu_access_via_specific_users_dd"
                        data-placeholder="<?php esc_attr_e('You can choose more than one user', 'wp-asset-clean-up-pro'); ?>..."
                    <?php } ?>
                        multiple="multiple">
                    <?php
                    foreach ($allUsers as $user) {
                        $selected = '';

                        if (in_array($user->ID, $nonAdminUsersWithCapIds)) {
                            $selected = 'selected="selected"';
                        }
                        ?>
                        <option <?php echo $selected; ?>
                                value="<?php echo $user->ID; ?>">
                            <?php echo esc_html(SettingsAdminOnlyForAdmin::userOutputRelatedToPluginAccessDd($user)); ?>
                        </option>
                    <?php
                    }
                    ?>
                </select>
            </div>
        <?php
        } else {
            // There are plenty of users on this WordPress site
            // Use the auto-complete option to retrieve the results to avoid using too many resources
        ?>
            <?php
            if ($data['input_style'] === 'standard') {
                ?>
                <div class="wpacu-warning" style="margin: 0 0 20px; background: inherit; font-size: inherit;">
                    <span style="font-size: 20px;">⚠️</span> At the moment, there's no compatibility available in terms of accesibility when it comes to searching for the users in the database. This is in development, and it will available as soon as possible. Meanwhile, please use "<em>Give access based on any other non-administrator user role</em>", or set "<em>Input Fields Style</em>" to "<em>Enhanced iPhone Style (Default) Standard</em>" in "<em>Settings</em>" -- "<em>Plugin Usage Preferences</em>" -- "<em>Accessibility</em>" and ask for assistance from someone that has access to the website (e.g. your developer).
                </div>
            <?php } ?>

            <div data-wpacu-non-admin-chosen-users-list="1">
                <?php
                if ( ! empty($nonAdminUsersWithCapIds) ) {
                    // Autocomplete is activated
                    // Fill any chosen ones
                    foreach ($nonAdminUsersWithCapIds as $nonAdminUserWithCapId) {
                        $user = get_user_by('id', $nonAdminUserWithCapId);

                        if ( ! isset($user->ID) ) {
                            continue; // the user was most likely removed
                        }

                        SettingsAdminOnlyForAdmin::addedChosenNonAdminUserForPluginAccessOutput($user);
                    }
                }
                ?>
            </div>

            <div class="wpacu_clearfix"></div>

            <div style="display: inline-block; width: 100%; max-width: 600px;">
                <select id="wpacu-access-via-specific-users-dd-search"
                    <?php if ($data['input_style'] === 'enhanced') { ?>
                        class="wpacu_chosen_select wpacu_access_via_specific_users_dd_search"
                        data-placeholder="<?php esc_attr_e('Search for users and add them to the list', 'wp-asset-clean-up-pro'); ?>..."
                    <?php } ?>>
                    <option value=""></option>
                </select>
            </div>

            <div style="display: inline-block;" class="wpacu_hide" id="wpacu-access-via-specific-user-adding-notice">&nbsp; <img class="wpacu_ajax_loader" align="top" src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" alt="" />&nbsp; <em><?php _e('Adding'); ?></em>...</div>
            <div style="display: inline-block;" class="wpacu_hide" id="wpacu-access-via-specific-user-searching-notice">&nbsp; <img class="wpacu_ajax_loader" align="top" src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" alt="" />&nbsp; <em><?php _e('Searching'); ?></em>...</div>

            <div style="margin: 20px 0 0;" class="wpacu_clearfix"></div>

            <?php
            $updateAllSettingsBtnText = __('Update All Settings', 'wp-asset-clean-up');
            ?>

            <small><strong>Note:</strong> Don't forget to use the <em>"<?php echo esc_html($updateAllSettingsBtnText); ?>"</em> button below to save the changes you made.</small>
        <?php
        }
    } else { ?>
        <p><em><span class="dashicons dashicons-info"></span> There are no users found on this website that DO NOT have the "<?php echo \WpAssetCleanUp\Menu::$defaultAccessRole; ?>" role (e.g. editors, authors, contributors, subscribers, etc.). Thus, this feature is not relevant and can be ignored.</em></p>
    <?php } ?>
</fieldset>
