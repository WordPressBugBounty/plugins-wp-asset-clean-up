<?php
if ( ! isset($data) ) {
    exit;
}

use WpAssetCleanUp\Admin\SettingsAdminOnlyForAdmin;
use WpAssetCleanUp\Settings;

$settingsName      = WPACU_PLUGIN_ID . '_settings';
$inputStyle       = Settings::getInputStyle($data);
$useEnhancedInputs = Settings::useEnhancedInputs($inputStyle);

$nonAdminRolesArray = SettingsAdminOnlyForAdmin::getAllNonAdminUserRolesWithAnyPluginAccessCap();
$rolesObject        = isset($nonAdminRolesArray['roles']) ? $nonAdminRolesArray['roles'] : wp_roles();
$availableRoleSlugs = ! empty($nonAdminRolesArray['non_admin_role_slugs'])
    ? array_values($nonAdminRolesArray['non_admin_role_slugs'])
    : array();

$selectedRoleSlugs = isset($data['access_via_non_admin_user_roles']) && is_array($data['access_via_non_admin_user_roles'])
    ? array_values(array_unique(array_map('sanitize_key', $data['access_via_non_admin_user_roles'])))
    : array();

$selectedRoleSlugs = array_values(array_intersect($availableRoleSlugs, $selectedRoleSlugs));
$selectedRoleCount = count($selectedRoleSlugs);

$nonAdminUsersWithCapIds = isset($data['access_via_specific_non_admin_users']) && is_array($data['access_via_specific_non_admin_users'])
    ? array_values(array_unique(array_filter(array_map('absint', $data['access_via_specific_non_admin_users']))))
    : array();

$selectedNonAdminUsers = array();

foreach ($nonAdminUsersWithCapIds as $nonAdminUserWithCapId) {
    $selectedUser = get_user_by('id', $nonAdminUserWithCapId);

    if ( ! $selectedUser instanceof \WP_User ) {
        continue;
    }

    $selectedNonAdminUsers[] = $selectedUser;
}

$nonAdminUsersWithCapIds = array_map(
    static function ($user) {
        return (int) $user->ID;
    },
    $selectedNonAdminUsers
);

$selectedUserCount = count($selectedNonAdminUsers);
$totalNonAdminUsers = (int) SettingsAdminOnlyForAdmin::getTotalNonAdminUsers();
$useAjaxUserSearch  = $totalNonAdminUsers > 0 && SettingsAdminOnlyForAdmin::useAutoCompleteSearchForNonAdminUsersDd();

$defaultAccessLabel = is_multisite()
    ? __('Super Admins and Administrators', 'wp-asset-clean-up')
    : __('Administrators', 'wp-asset-clean-up');

$hasAdditionalAccess = ($selectedRoleCount + $selectedUserCount) > 0;
?>
<div id="wpacu-access-control-settings"
     class="wpacu-access-control"
     data-wpacu-access-state-configured="<?php esc_attr_e('Additional access is configured', 'wp-asset-clean-up'); ?>"
     data-wpacu-access-state-empty="<?php esc_attr_e('No additional access is configured', 'wp-asset-clean-up'); ?>">

    <header class="wpacu-access-header">
        <div class="wpacu-access-header__copy">
            <div class="wpacu-access-eyebrow"><?php esc_html_e('Security & permissions', 'wp-asset-clean-up'); ?></div>

            <h2 id="wpacuAccessControlTitle">
                <?php esc_html_e('Control who can manage the plugin', 'wp-asset-clean-up'); ?>
            </h2>

            <p>
                <?php echo esc_html(sprintf(
                    __('By default, %s retain access. Add roles or individual non-administrator accounts only when another trusted person needs to work with the plugin.', 'wp-asset-clean-up'),
                    $defaultAccessLabel
                )); ?>
            </p>
        </div>

        <span class="wpacu-access-header__badge">
            <span class="dashicons dashicons-shield" aria-hidden="true"></span>
            <?php esc_html_e('Restricted by default', 'wp-asset-clean-up'); ?>
        </span>
    </header>

    <section class="wpacu-access-summary" aria-labelledby="wpacuAccessSummaryTitle">
        <div class="wpacu-access-summary__icon" aria-hidden="true">
            <span class="dashicons dashicons-lock"></span>
        </div>

        <div class="wpacu-access-summary__copy">
            <span class="wpacu-access-summary__kicker"><?php esc_html_e('Current access', 'wp-asset-clean-up'); ?></span>
            <h3 id="wpacuAccessSummaryTitle"><?php esc_html_e('Default access remains active', 'wp-asset-clean-up'); ?></h3>
            <p>
                <?php echo esc_html(sprintf(
                    __('%s can always access %s from the WordPress Dashboard.', 'wp-asset-clean-up'),
                    $defaultAccessLabel,
                    WPACU_PLUGIN_TITLE
                )); ?>
            </p>
        </div>

        <div class="wpacu-access-summary__stats" aria-label="<?php esc_attr_e('Additional access summary', 'wp-asset-clean-up'); ?>">
            <div class="wpacu-access-stat">
                <strong data-wpacu-access-role-count><?php echo esc_html($selectedRoleCount); ?></strong>
                <span><?php esc_html_e('Additional roles', 'wp-asset-clean-up'); ?></span>
            </div>

            <div class="wpacu-access-stat">
                <strong data-wpacu-access-user-count><?php echo esc_html($selectedUserCount); ?></strong>
                <span><?php esc_html_e('Specific users', 'wp-asset-clean-up'); ?></span>
            </div>

            <div class="wpacu-access-summary__state<?php echo $hasAdditionalAccess ? ' is-configured' : ' is-empty'; ?>"
                 data-wpacu-access-state
                 role="status"
                 aria-live="polite"
                 aria-atomic="true">
                <span class="dashicons <?php echo $hasAdditionalAccess ? 'dashicons-unlock' : 'dashicons-lock'; ?>" aria-hidden="true"></span>
                <span data-wpacu-access-state-text>
                    <?php echo $hasAdditionalAccess
                        ? esc_html__('Additional access is configured', 'wp-asset-clean-up')
                        : esc_html__('No additional access is configured', 'wp-asset-clean-up'); ?>
                </span>
            </div>
        </div>
    </section>

    <aside class="wpacu-access-security-note">
        <span class="wpacu-access-security-note__icon dashicons dashicons-warning" aria-hidden="true"></span>

        <div>
            <strong><?php esc_html_e('Grant access only to trusted people.', 'wp-asset-clean-up'); ?></strong>
            <p style="margin-top: 8px;">
                <?php esc_html_e('Choose', 'wp-asset-clean-up'); ?>
                <a class="wpacu-access-method-link" href="#wpacu-access-by-user-role" style="color: #665a38; text-decoration: underline;"><strong style="display: inline; margin: 0; color: inherit; font-weight: inherit;"><?php esc_html_e('access by user role', 'wp-asset-clean-up'); ?></strong></a>
                <?php esc_html_e('to grant access to everyone with a selected role, or', 'wp-asset-clean-up'); ?>
                <a class="wpacu-access-method-link" href="#wpacu-area-option-give-access-specific-non-admin-users" style="color: #665a38; text-decoration: underline;"><strong style="display: inline; margin: 0; color: inherit; font-weight: inherit;"><?php esc_html_e('access for specific users', 'wp-asset-clean-up'); ?></strong></a>
                <?php esc_html_e('to grant it only to selected accounts. Anyone who receives access can change settings or optimization rules that may affect the entire website.', 'wp-asset-clean-up'); ?>
            </p>
        </div>
    </aside>

    <div class="wpacu-access-methods">
        <section id="wpacu-access-by-user-role"
                 class="wpacu-access-method wpacu-access-method--roles"
                 style="scroll-margin-top: 5px;"
                 aria-labelledby="wpacuAccessByRoleTitle">
            <header class="wpacu-access-method__header">
                <span class="wpacu-access-method__icon" aria-hidden="true">
                    <span class="dashicons dashicons-groups"></span>
                </span>

                <div class="wpacu-access-method__heading">
                    <div class="wpacu-access-method__title-row">
                        <h3 id="wpacuAccessByRoleTitle"><?php esc_html_e('Access by user role', 'wp-asset-clean-up'); ?></h3>
                        <span class="wpacu-access-method__badge wpacu-access-method__badge--broad">
                            <?php esc_html_e('Broad access', 'wp-asset-clean-up'); ?>
                        </span>
                    </div>

                    <p>
                        <?php esc_html_e('Give every account assigned to one or more selected roles access to the plugin.', 'wp-asset-clean-up'); ?>
                    </p>
                </div>
            </header>

            <div class="wpacu-access-method__body">
                <div class="wpacu-access-guidance">
                    <span><?php esc_html_e('Best for', 'wp-asset-clean-up'); ?></span>
                    <p><?php esc_html_e('A trusted team where everyone in a role, such as Content Editors, should be able to manage Asset CleanUp.', 'wp-asset-clean-up'); ?></p>
                </div>

                <?php if (empty($availableRoleSlugs)) { ?>
                    <div class="wpacu-access-empty-state wpacu-access-empty-state--compact">
                        <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                        <div>
                            <strong><?php esc_html_e('No additional roles are available', 'wp-asset-clean-up'); ?></strong>
                            <p>
                                <?php echo esc_html(sprintf(
                                    __('This site currently has no non-administrator roles besides the default %s role.', 'wp-asset-clean-up'),
                                    \WpAssetCleanUp\Menu::$defaultAccessRole
                                )); ?>
                            </p>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="wpacu-access-field">
                        <label for="wpacu-access-via-non-admin-roles">
                            <strong><?php esc_html_e('Roles with plugin access', 'wp-asset-clean-up'); ?></strong>
                            <span><?php esc_html_e('The permission follows the role. Existing users and anyone assigned to it later will receive access.', 'wp-asset-clean-up'); ?></span>
                        </label>

                        <div class="wpacu-access-select-wrap">
                            <select id="wpacu-access-via-non-admin-roles"
                                    name="<?php echo esc_attr($settingsName); ?>[access_via_non_admin_user_roles][]"
                                    class="wpacu-access-role-select<?php echo $useEnhancedInputs ? ' wpacu_chosen_select' : ' wpacu-access-native-multi-select'; ?>"
                                    data-wpacu-access-role-select="1"
                                    aria-describedby="wpacu-access-role-help"
                                    <?php if ($useEnhancedInputs) { ?>
                                        data-placeholder="<?php esc_attr_e('Search for roles to add', 'wp-asset-clean-up'); ?>"
                                    <?php } ?>
                                    multiple="multiple">
                                <?php foreach ($availableRoleSlugs as $roleSlug) {
                                    $roleValues = isset($rolesObject->roles[$roleSlug]) ? $rolesObject->roles[$roleSlug] : array();
                                    $roleName   = ! empty($roleValues['name']) ? translate_user_role($roleValues['name']) : $roleSlug;
                                    ?>
                                    <option value="<?php echo esc_attr($roleSlug); ?>"
                                        <?php selected(in_array($roleSlug, $selectedRoleSlugs, true)); ?>>
                                        <?php echo esc_html(sprintf(
                                            __('%1$s (slug: %2$s)', 'wp-asset-clean-up'),
                                            $roleName,
                                            $roleSlug
                                        )); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <p id="wpacu-access-role-help" class="wpacu-access-field__note">
                            <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                            <?php esc_html_e('Role-based access is combined with any individual users selected below.', 'wp-asset-clean-up'); ?>
                        </p>
                    </div>
                <?php } ?>
            </div>
        </section>

        <section id="wpacu-area-option-give-access-specific-non-admin-users"
                 class="wpacu-access-method wpacu-access-method--users"
                 style="scroll-margin-top: 5px;"
                 aria-labelledby="wpacuAccessByUserTitle">
            <header class="wpacu-access-method__header">
                <span class="wpacu-access-method__icon" aria-hidden="true">
                    <span class="dashicons dashicons-admin-users"></span>
                </span>

                <div class="wpacu-access-method__heading">
                    <div class="wpacu-access-method__title-row">
                        <h3 id="wpacuAccessByUserTitle"><?php esc_html_e('Access for specific users', 'wp-asset-clean-up'); ?></h3>
                        <span class="wpacu-access-method__badge wpacu-access-method__badge--precise">
                            <?php esc_html_e('More precise', 'wp-asset-clean-up'); ?>
                        </span>
                    </div>

                    <p>
                        <?php esc_html_e('Grant access to selected accounts without opening the plugin to everyone who shares their role.', 'wp-asset-clean-up'); ?>
                    </p>
                </div>
            </header>

            <div class="wpacu-access-method__body">
                <div class="wpacu-access-guidance">
                    <span><?php esc_html_e('Best for', 'wp-asset-clean-up'); ?></span>
                    <p><?php esc_html_e('A developer, contractor, editor, or support specialist who needs access independently of their WordPress role.', 'wp-asset-clean-up'); ?></p>
                </div>

                <?php if ($totalNonAdminUsers > 0) {
                    if ( ! $useAjaxUserSearch ) {
                        $allUsers = SettingsAdminOnlyForAdmin::getAllNonAdminUsers();
                        ?>
                        <div class="wpacu-access-field">
                            <label for="wpacu-access-via-specific-users-dd">
                                <strong><?php esc_html_e('Users with direct access', 'wp-asset-clean-up'); ?></strong>
                                <span><?php esc_html_e('Choose one or more existing non-administrator accounts. Administrators are not listed because they already have access.', 'wp-asset-clean-up'); ?></span>
                            </label>

                            <div class="wpacu-access-select-wrap wpacu-access-select-wrap--users">
                                <select id="wpacu-access-via-specific-users-dd"
                                        name="<?php echo esc_attr($settingsName); ?>[access_via_specific_non_admin_users][]"
                                        class="wpacu-access-users-select<?php echo $useEnhancedInputs ? ' wpacu_chosen_select wpacu_access_via_specific_users_dd' : ' wpacu-access-native-multi-select'; ?>"
                                        data-wpacu-access-users-select="1"
                                        data-placeholder="<?php esc_attr_e('Search for users to add', 'wp-asset-clean-up'); ?>"
                                        multiple="multiple">
                                    <?php foreach ($allUsers as $user) { ?>
                                        <option value="<?php echo esc_attr($user->ID); ?>"
                                            <?php selected(in_array((int) $user->ID, $nonAdminUsersWithCapIds, true)); ?>>
                                            <?php echo esc_html(SettingsAdminOnlyForAdmin::userOutputRelatedToPluginAccessDd($user)); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>

                            <p class="wpacu-access-field__note">
                                <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                                <?php esc_html_e('Direct access remains independent of the role-based selection above.', 'wp-asset-clean-up'); ?>
                            </p>
                        </div>
                    <?php } else { ?>
                        <div class="wpacu-access-user-management">
                            <section class="wpacu-access-user-search-panel" aria-labelledby="wpacuAccessAddUserTitle">
                                <div class="wpacu-access-user-search-panel__heading">
                                    <div>
                                        <h4 id="wpacuAccessAddUserTitle"><?php esc_html_e('Add a user', 'wp-asset-clean-up'); ?></h4>
                                        <p><?php esc_html_e('Search by name, username, or email address. Administrator accounts are excluded because they already have access.', 'wp-asset-clean-up'); ?></p>
                                    </div>

                                    <span class="wpacu-access-user-search-panel__badge">
                                        <span class="dashicons dashicons-search" aria-hidden="true"></span>
                                        <?php esc_html_e('Live search', 'wp-asset-clean-up'); ?>
                                    </span>
                                </div>

                                <div class="wpacu-specific-non-admin-user-search"
                                     data-wpacu-specific-non-admin-user-search="1"
                                     data-wpacu-input-style="<?php echo esc_attr($inputStyle); ?>">
                                    <?php if ($useEnhancedInputs) { ?>
                                        <div class="wpacu-enhanced-user-search">
                                            <label for="wpacu-access-via-specific-users-dd-search" class="screen-reader-text">
                                                <?php esc_html_e('Search for a non-administrator user', 'wp-asset-clean-up'); ?>
                                            </label>

                                            <select id="wpacu-access-via-specific-users-dd-search"
                                                    class="wpacu_chosen_select wpacu_access_via_specific_users_dd_search"
                                                    data-placeholder="<?php esc_attr_e('Search by name, username, or email address', 'wp-asset-clean-up'); ?>">
                                                <option value=""></option>
                                            </select>
                                        </div>
                                    <?php } else { ?>
                                        <div class="wpacu-native-user-search" data-wpacu-native-user-search="1">
                                            <label for="wpacu-access-via-specific-users-native-search">
                                                <strong><?php esc_html_e('Search for a non-administrator user', 'wp-asset-clean-up'); ?></strong>
                                            </label>

                                            <input type="search"
                                                   id="wpacu-access-via-specific-users-native-search"
                                                   class="regular-text"
                                                   autocomplete="off"
                                                   aria-controls="wpacu-access-via-specific-users-native-results"
                                                   aria-describedby="wpacu-access-via-specific-users-native-help wpacu-access-via-specific-users-search-status"
                                                   placeholder="<?php esc_attr_e('Name, username, or email address', 'wp-asset-clean-up'); ?>" />

                                            <p id="wpacu-access-via-specific-users-native-help" class="description">
                                                <?php esc_html_e('Type at least 2 characters. Results load without refreshing the page.', 'wp-asset-clean-up'); ?>
                                            </p>

                                            <div id="wpacu-access-via-specific-users-native-results-wrap"
                                                 class="wpacu-native-user-search-results wpacu_hide">
                                                <label for="wpacu-access-via-specific-users-native-results" class="screen-reader-text">
                                                    <?php esc_html_e('Matching non-administrator users', 'wp-asset-clean-up'); ?>
                                                </label>

                                                <select id="wpacu-access-via-specific-users-native-results"
                                                        size="6"
                                                        disabled="disabled"></select>

                                                <button type="button"
                                                        id="wpacu-access-via-specific-users-native-add"
                                                        class="button button-secondary"
                                                        disabled="disabled">
                                                    <?php esc_html_e('Add selected user', 'wp-asset-clean-up'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    <?php } ?>

                                    <p id="wpacu-access-via-specific-users-search-status"
                                       class="description wpacu-user-search-status"
                                       role="status"
                                       aria-live="polite"></p>
                                </div>

                                <div class="wpacu-access-activity-row" aria-live="polite">
                                    <span class="wpacu-access-activity wpacu_hide" id="wpacu-access-via-specific-user-searching-notice">
                                        <span class="wpacu-access-spinner" aria-hidden="true"></span>
                                        <?php esc_html_e('Searching', 'wp-asset-clean-up'); ?>&hellip;
                                    </span>

                                    <span class="wpacu-access-activity wpacu_hide" id="wpacu-access-via-specific-user-adding-notice">
                                        <span class="wpacu-access-spinner" aria-hidden="true"></span>
                                        <?php esc_html_e('Adding user', 'wp-asset-clean-up'); ?>&hellip;
                                    </span>
                                </div>

                                <div id="wpacu-access-via-specific-user-added-notice"
                                     class="wpacu-access-add-confirmation wpacu_hide"
                                     data-wpacu-access-added-user-message="<?php echo esc_attr(__('“%s” now appears under “Users with direct access” below. Click “Save Changes” to grant this permission.', 'wp-asset-clean-up')); ?>"
                                     data-wpacu-access-view-added-user-label="<?php echo esc_attr(__('View %s in the direct access list', 'wp-asset-clean-up')); ?>"
                                     data-wpacu-access-added-user-fallback="<?php esc_attr_e('The selected user', 'wp-asset-clean-up'); ?>"
                                     role="status"
                                     aria-live="polite"
                                     aria-atomic="true">
                                    <span class="wpacu-access-add-confirmation__icon" aria-hidden="true">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                    </span>

                                    <div class="wpacu-access-add-confirmation__copy">
                                        <strong><?php esc_html_e('User added — save required', 'wp-asset-clean-up'); ?></strong>
                                        <p data-wpacu-access-added-user-message-output></p>
                                    </div>

                                    <button type="button"
                                            class="button-link wpacu-access-add-confirmation__view"
                                            data-wpacu-access-view-added-user>
                                        <?php esc_html_e('View added user', 'wp-asset-clean-up'); ?>
                                    </button>
                                </div>
                            </section>

                            <section class="wpacu-access-selected-users" aria-labelledby="wpacuAccessSelectedUsersTitle">
                                <div class="wpacu-access-selected-users__heading">
                                    <div>
                                        <h4 id="wpacuAccessSelectedUsersTitle"><?php esc_html_e('Users with direct access', 'wp-asset-clean-up'); ?></h4>
                                        <p><?php esc_html_e('These accounts receive access independently of their WordPress role.', 'wp-asset-clean-up'); ?></p>
                                    </div>

                                    <span class="wpacu-access-selected-users__count" aria-live="polite" aria-atomic="true">
                                        <strong data-wpacu-access-user-count><?php echo esc_html($selectedUserCount); ?></strong>
                                        <?php esc_html_e('selected', 'wp-asset-clean-up'); ?>
                                    </span>
                                </div>

                                <div class="wpacu-access-empty-state wpacu-access-empty-state--users<?php echo $selectedUserCount > 0 ? ' wpacu_hide' : ''; ?>"
                                     data-wpacu-access-users-empty-state>
                                    <span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
                                    <div>
                                        <strong><?php esc_html_e('No specific users added', 'wp-asset-clean-up'); ?></strong>
                                        <p><?php esc_html_e('Use the search above to grant direct access to a trusted non-administrator account.', 'wp-asset-clean-up'); ?></p>
                                    </div>
                                </div>

                                <div class="wpacu-access-user-grid"
                                     data-wpacu-non-admin-chosen-users-list="1"
                                     role="list">
                                    <?php foreach ($selectedNonAdminUsers as $selectedNonAdminUser) {
                                        SettingsAdminOnlyForAdmin::addedChosenNonAdminUserForPluginAccessOutput($selectedNonAdminUser);
                                    } ?>
                                </div>
                            </section>
                        </div>
                    <?php }
                } else { ?>
                    <div class="wpacu-access-empty-state">
                        <span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
                        <div>
                            <strong><?php esc_html_e('No eligible non-administrator users were found', 'wp-asset-clean-up'); ?></strong>
                            <p><?php esc_html_e('This section becomes available after an editor, author, contributor, subscriber, or another non-administrator account exists on the site.', 'wp-asset-clean-up'); ?></p>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </section>
    </div>

    <details class="wpacu-access-details">
        <summary>
            <span class="wpacu-access-details__summary-copy">
                <span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
                <span>
                    <strong><?php esc_html_e('How access is evaluated', 'wp-asset-clean-up'); ?></strong>
                    <small><?php esc_html_e('Technical details for administrators', 'wp-asset-clean-up'); ?></small>
                </span>
            </span>

            <span class="dashicons dashicons-arrow-down-alt2 wpacu-access-details__arrow" aria-hidden="true"></span>
        </summary>

        <div class="wpacu-access-details__content">
            <ul>
                <li>
                    <strong><?php esc_html_e('Default access cannot be removed here.', 'wp-asset-clean-up'); ?></strong>
                    <?php echo esc_html(sprintf(
                        __('%s retain access to the plugin.', 'wp-asset-clean-up'),
                        $defaultAccessLabel
                    )); ?>
                </li>
                <li>
                    <strong><?php esc_html_e('Role and individual access are cumulative.', 'wp-asset-clean-up'); ?></strong>
                    <?php esc_html_e('A user can receive access through either method, and a direct user permission remains valid even when their role is not selected.', 'wp-asset-clean-up'); ?>
                </li>
                <li>
                    <strong><?php esc_html_e('WordPress roles are not changed.', 'wp-asset-clean-up'); ?></strong>
                    <?php esc_html_e('Removing an entry here removes only the Asset CleanUp access permission after the settings are saved.', 'wp-asset-clean-up'); ?>
                </li>
            </ul>
        </div>
    </details>

    <div class="wpacu-access-save-reminder">
        <span class="dashicons dashicons-saved" aria-hidden="true"></span>
        <p>
            <strong><?php esc_html_e('Save required:', 'wp-asset-clean-up'); ?></strong>
            <?php esc_html_e('Adding or removing roles and users is applied only after you click “Save Changes” below.', 'wp-asset-clean-up'); ?>
        </p>
    </div>
</div>
<script>
    /*
     * [START] Access Control live summary
     */
    ;(function ($) {
        'use strict';

        function wpacuInitAccessControlSummary() {
            var $accessArea = $('#wpacu-access-control-settings');

            if ($accessArea.length < 1) {
                return;
            }

            var $roleSelect = $accessArea.find('[data-wpacu-access-role-select="1"]');
            var $usersSelect = $accessArea.find('[data-wpacu-access-users-select="1"]');
            var $usersList = $accessArea.find('[data-wpacu-non-admin-chosen-users-list="1"]');

            function wpacuAccessSelectCount($select) {
                if ($select.length < 1) {
                    return 0;
                }

                var value = $select.val();

                if (Array.isArray(value)) {
                    return value.length;
                }

                return value ? 1 : 0;
            }

            function wpacuDirectUserCount() {
                if ($usersSelect.length > 0) {
                    return wpacuAccessSelectCount($usersSelect);
                }

                var count = 0;

                $usersList.find('[data-wpacu-non-admin-chosen-user-id]').each(function () {
                    if ($(this).find('input[type="hidden"]:disabled').length < 1) {
                        count++;
                    }
                });

                return count;
            }

            function wpacuUpdateAccessControlSummary() {
                var roleCount = wpacuAccessSelectCount($roleSelect);
                var userCount = wpacuDirectUserCount();
                var hasAdditionalAccess = (roleCount + userCount) > 0;
                var stateText = hasAdditionalAccess
                    ? $accessArea.attr('data-wpacu-access-state-configured')
                    : $accessArea.attr('data-wpacu-access-state-empty');
                var $state = $accessArea.find('[data-wpacu-access-state]');
                var $stateIcon = $state.find('.dashicons');
                var hasRenderedUserCards = $usersList.find('[data-wpacu-non-admin-chosen-user-id]').length > 0;

                $accessArea.find('[data-wpacu-access-role-count]').text(roleCount);
                $accessArea.find('[data-wpacu-access-user-count]').text(userCount);
                $accessArea.find('[data-wpacu-access-state-text]').text(stateText || '');

                $state
                    .toggleClass('is-configured', hasAdditionalAccess)
                    .toggleClass('is-empty', ! hasAdditionalAccess);

                $stateIcon
                    .toggleClass('dashicons-unlock', hasAdditionalAccess)
                    .toggleClass('dashicons-lock', ! hasAdditionalAccess);

                if ($usersList.length > 0) {
                    $accessArea
                        .find('[data-wpacu-access-users-empty-state]')
                        .toggleClass('wpacu_hide', hasRenderedUserCards);
                }
            }

            $accessArea
                .off('change.wpacuAccessControlSummary')
                .on(
                    'change.wpacuAccessControlSummary',
                    '[data-wpacu-access-role-select="1"], [data-wpacu-access-users-select="1"]',
                    wpacuUpdateAccessControlSummary
                );

            if ($usersList.length > 0 && typeof window.MutationObserver !== 'undefined') {
                var observer = new window.MutationObserver(wpacuUpdateAccessControlSummary);

                observer.observe($usersList.get(0), {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['disabled']
                });
            }

            wpacuUpdateAccessControlSummary();
        }

        $(wpacuInitAccessControlSummary);
    })(jQuery);
    /*
     * [END] Access Control live summary
     */

    /*
     * [START] Access Control dropdown layering and add confirmation
     */
    ;(function ($) {
        'use strict';

        function wpacuInitAccessControlEnhancements() {
            var $accessArea = $('#wpacu-access-control-settings');

            if ($accessArea.length < 1 || $accessArea.data('wpacuAccessEnhancementsReady')) {
                return;
            }

            $accessArea.data('wpacuAccessEnhancementsReady', true);

            var userCardSelector = '[data-wpacu-non-admin-chosen-user-id]';
            var $chosenSelects = $accessArea.find('select.wpacu_chosen_select');
            var $usersList = $accessArea.find('[data-wpacu-non-admin-chosen-users-list="1"]');
            var $addedNotice = $('#wpacu-access-via-specific-user-added-notice');
            var $addedMessage = $addedNotice.find('[data-wpacu-access-added-user-message-output]');
            var $viewAddedUser = $addedNotice.find('[data-wpacu-access-view-added-user]');
            var knownUserIds = {};
            var highlightTimer = null;
            var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            $accessArea.on('click.wpacuAccessMethodLink', 'a.wpacu-access-method-link[href^="#"]', function (event) {
                var target = document.getElementById(this.hash.substring(1));

                // Without JavaScript or scrollIntoView(), the link keeps its native anchor fallback.
                if ( ! target || typeof target.scrollIntoView !== 'function') {
                    return;
                }

                event.preventDefault();
                target.scrollIntoView({
                    behavior: prefersReducedMotion ? 'auto' : 'smooth',
                    block: 'start'
                });

                if (window.history && typeof window.history.pushState === 'function') {
                    window.history.pushState(null, '', this.hash);
                }
            });

            function wpacuGetUserCardId($card) {
                return String($card.attr('data-wpacu-non-admin-chosen-user-id') || '');
            }

            function wpacuFormatAccessMessage(template, value) {
                return String(template || '')
                    .replace('%1$s', value)
                    .replace('%s', value);
            }

            function wpacuRememberRenderedUsers() {
                if ($usersList.length < 1) {
                    return;
                }

                $usersList.find(userCardSelector).each(function () {
                    var userId = wpacuGetUserCardId($(this));

                    if (userId) {
                        knownUserIds[userId] = true;
                    }
                });
            }

            function wpacuHighlightUserCard($card, scrollToCard) {
                if ($card.length < 1) {
                    return;
                }

                window.clearTimeout(highlightTimer);
                $card.removeClass('is-newly-added');

                // Force a reflow so the animation also restarts after using "View added user".
                if ($card.get(0)) {
                    void $card.get(0).offsetWidth;
                }

                $card.addClass('is-newly-added');

                highlightTimer = window.setTimeout(function () {
                    $card.removeClass('is-newly-added');
                }, 2300);

                if (scrollToCard) {
                    var targetTop = Math.max(0, Math.round($card.offset().top - 110));

                    if (prefersReducedMotion) {
                        window.scrollTo(0, targetTop);
                    } else {
                        $('html, body').stop(true).animate({ scrollTop: targetTop }, 360);
                    }
                }
            }

            function wpacuShowAddedUserNotice($card) {
                if ($addedNotice.length < 1 || $card.length < 1) {
                    return;
                }

                var userId = wpacuGetUserCardId($card);
                var userName = $.trim($card.find('.wpacu-access-user-card__name').first().text());
                var messageTemplate = $addedNotice.attr('data-wpacu-access-added-user-message') ||
                    '“%s” now appears under “Users with direct access” below. Click “Save Changes” to grant this permission.';
                var viewLabelTemplate = $addedNotice.attr('data-wpacu-access-view-added-user-label') ||
                    'View %s in the direct access list';

                if ( ! userName) {
                    userName = $addedNotice.attr('data-wpacu-access-added-user-fallback') || 'The selected user';
                }

                $addedMessage.text(wpacuFormatAccessMessage(messageTemplate, userName));

                $viewAddedUser
                    .attr('data-wpacu-access-target-user-id', userId)
                    .attr('aria-label', wpacuFormatAccessMessage(viewLabelTemplate, userName));

                $addedNotice.removeClass('wpacu_hide');

                if (prefersReducedMotion) {
                    $addedNotice.show();
                } else {
                    $addedNotice.stop(true, true).hide().fadeIn(180);
                }

                wpacuHighlightUserCard($card, false);
            }

            $chosenSelects
                .off('.wpacuAccessControlDropdownLayer')
                .on('chosen:showing_dropdown.wpacuAccessControlDropdownLayer', function () {
                    $accessArea.find('.wpacu-access-method').removeClass('is-dropdown-open');

                    $(this)
                        .closest('.wpacu-access-method')
                        .addClass('is-dropdown-open');
                })
                .on('chosen:hiding_dropdown.wpacuAccessControlDropdownLayer', function () {
                    $(this)
                        .closest('.wpacu-access-method')
                        .removeClass('is-dropdown-open');
                });

            $viewAddedUser
                .off('click.wpacuAccessControlAddedUser')
                .on('click.wpacuAccessControlAddedUser', function (event) {
                    event.preventDefault();

                    var userId = String($(this).attr('data-wpacu-access-target-user-id') || '');

                    var $card = $usersList.find(
                        userCardSelector +
                        '[data-wpacu-non-admin-chosen-user-id="' +
                        userId +
                        '"]'
                    );

                    wpacuHighlightUserCard($card, true);
                });

            wpacuRememberRenderedUsers();

            if ($usersList.length > 0 && typeof window.MutationObserver !== 'undefined') {
                var observer = new window.MutationObserver(function (mutations) {
                    $.each(mutations, function (_mutationIndex, mutation) {
                        $(mutation.addedNodes).each(function () {
                            var $node = $(this);

                            var $addedCards = $node.is(userCardSelector)
                                ? $node
                                : $node.find(userCardSelector);

                            $addedCards.each(function () {
                                var $card = $(this);
                                var userId = wpacuGetUserCardId($card);

                                if ( ! userId || knownUserIds[userId]) {
                                    return;
                                }

                                knownUserIds[userId] = true;

                                wpacuShowAddedUserNotice($card);
                            });
                        });

                        $(mutation.removedNodes).each(function () {
                            var $node = $(this);

                            var $removedCards = $node.is(userCardSelector)
                                ? $node
                                : $node.find(userCardSelector);

                            $removedCards.each(function () {
                                var userId = wpacuGetUserCardId($(this));

                                if (userId) {
                                    delete knownUserIds[userId];
                                }

                                if (
                                    $viewAddedUser.attr('data-wpacu-access-target-user-id') === userId
                                ) {
                                    $addedNotice.addClass('wpacu_hide');
                                }
                            });
                        });
                    });
                });

                observer.observe($usersList.get(0), {
                    childList: true,
                    subtree: true
                });
            }
        }

        $(wpacuInitAccessControlEnhancements);
    })(jQuery);
    /*
     * [END] Access Control dropdown layering and add confirmation
     */
</script>
