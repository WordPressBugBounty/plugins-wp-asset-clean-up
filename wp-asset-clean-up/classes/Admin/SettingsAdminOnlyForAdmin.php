<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\Menu;
use WpAssetCleanUp\ObjectCache;

/**
 * Only administrators can have access to these methods:
 *
 * 1) Adding user roles that could access the plugin area in "Settings" -- "Plugin Usage Preferences" -- "Plugin Access"
 * 2) "Settings" -- "Plugin Usage Preferences" -- "Allow managing assets to:"
 *
 * Class SettingsAdminOnlyForAdmin
 * @package WpAssetCleanUp
 */
class SettingsAdminOnlyForAdmin
{
    /**
     * If the total number of users is above this number, the AJAX search interface is activated
     * For instance, there could be tens of thousands of users on specific websites
     * It's not effective to put all in one drop-down (too many resources would be used)
     *
     * @var int
     */
    public static $activateSearchDdInPluginAccessIfTotalNonAdminUsersExceeds = 200;

    /**
     * Keep the AJAX response small on websites with a large users table.
     *
     * @var int
     */
    public static $nonAdminUsersSearchResultsLimit = 30;


    /**
     * @return void
     */
    public function init()
    {
        // "Settings" -- "Plugin Usage Preferences" -- "Plugin Access" -- "Give access for specific non-administrator users"
        // This is relevant for large number of users
        add_action('wp_ajax_' . WPACU_PLUGIN_ID . '_search_non_admin_users_for_dd',      array($this, 'ajaxSearchNonAdminUsersForDd'));
        add_action('wp_ajax_' . WPACU_PLUGIN_ID . '_add_non_admin_users_to_chosen_list', array($this, 'ajaxAddNonAdminUsersToChosenList'));
    }

    /**
     * @return void
     */
    public static function filterSettingsOnFormSubmit()
    {
        $triggerIf = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && ! empty($_POST['wpacu_selected_tab_area']) && current_user_can(Menu::$defaultAccessRole);

        if ( ! $triggerIf ) {
            return;
        }

        // This is considered a special setting as the values are saved in the plugin's settings, but the drop-down is populated dynamically also from users table
        // The values are stored via add_cap() and removed via remove_cap() and this can be done via 3rd party plugins as well
        // The verification is made via current_user_can('capability_name_here');
        // This action should be taken when ONLY the admin (e.g. not a "subscriber" powered to access the plugin area) submits the actual "Settings" form
        // This is because the list is cleared if there are no records, and this should happen only in "Settings" area

        // [START] Allow managing assets
        global $wpdb;

        $metaKeyTargeted = WPACU_PLUGIN_ID . '_user_chosen_for_access_to_assets_manager';

        $clearQuery = <<<SQL
DELETE FROM `{$wpdb->usermeta}` WHERE meta_key='{$metaKeyTargeted}';
SQL;
        $wpdb->query($clearQuery);

        if (isset($_POST[WPACU_PLUGIN_ID . '_settings']['allow_manage_assets_to'], $_POST[WPACU_PLUGIN_ID . '_settings']['allow_manage_assets_to_list']) &&
            in_array($_POST[WPACU_PLUGIN_ID . '_settings']['allow_manage_assets_to'], array('chosen', 'selected'), true) &&
            ! empty($_POST[WPACU_PLUGIN_ID . '_settings']['allow_manage_assets_to_list'])) {
            $allowManageAssetsTo = $_POST[WPACU_PLUGIN_ID . '_settings']['allow_manage_assets_to_list'];

            foreach ($allowManageAssetsTo as $specificUserId) {
                delete_user_meta($specificUserId, $metaKeyTargeted);
                add_user_meta($specificUserId, $metaKeyTargeted, 1);
            }
        }
        // [END] Allow managing assets

        // [START] plugin access via user roles
        // Here, the drop-down is already populated with any extra users (e.g. the capability could be added by a 3rd party plugin such as "User Role Editor")
        // First, clear any existing user roles
        self::removePluginAccessCapabilityForAllExtraRoles();

        // Add any set user roles
        if (isset($_POST[WPACU_PLUGIN_ID . '_settings']['access_via_non_admin_user_roles']) &&
            ! empty($_POST[WPACU_PLUGIN_ID . '_settings']['access_via_non_admin_user_roles'])) {
            $accessViaOtherUserRoles = $_POST[WPACU_PLUGIN_ID . '_settings']['access_via_non_admin_user_roles'];

            foreach ($accessViaOtherUserRoles as $userRole) {
                $userRole = sanitize_text_field($userRole);

                $wpRole = get_role($userRole);

                if ($wpRole) {
                    $wpRole->add_cap(Menu::$pluginAccessCap);
                }
            }
        }
        // [END] plugin access via user roles

        // [START] plugin access for certain user(s)
        // First, clear any existing capabilities added to certain users
        self::removePluginAccessCapForAllSpecificUsers();

        // Add capability to chosen users
        if (isset($_POST[WPACU_PLUGIN_ID . '_settings']['access_via_specific_non_admin_users']) &&
            ! empty($_POST[WPACU_PLUGIN_ID . '_settings']['access_via_specific_non_admin_users'])) {
            $accessViaSpecificUsers = $_POST[WPACU_PLUGIN_ID . '_settings']['access_via_specific_non_admin_users'];

            foreach ($accessViaSpecificUsers as $userId) {
                $specificUser = new \WP_User($userId);
                $specificUser->add_cap(Menu::$pluginAccessCap);
            }
        }
        // [END] plugin access for certain user(s)
    }

    /**
     * @return array
     */
    public static function getAllNonAdminUserRolesWithAnyPluginAccessCap()
    {
        $wpRoles = wp_roles();

        $allWpRolesSlugs = array_keys($wpRoles->roles);

        if (empty($allWpRolesSlugs) || count($allWpRolesSlugs) === 1 && $allWpRolesSlugs[0] === 'administrator') {
            return array();
        }

        sort($allWpRolesSlugs);

        // Clear the "admimnistrator" role as we are only interested in the non-admin roles
        foreach ($allWpRolesSlugs as $roleKey => $rolesSlug) {
            if ($rolesSlug === 'administrator') {
                unset($allWpRolesSlugs[$roleKey]);
            }
        }

        if (empty($allWpRolesSlugs)) {
            return array();
        }

        // Current saved roles
        // They are fetched differently (not just taking the values from "access_via_non_admin_user_roles"
        // because the capability might be added to other roles such as
        $userRolesWithPluginAccessCap = array();

        foreach ($allWpRolesSlugs as $roleSlug) {
            $roleWp = get_role($roleSlug);

            if ($roleWp->has_cap(Menu::$pluginAccessCap)) {
                $userRolesWithPluginAccessCap[] = $roleSlug;
            }
        }

        return array(
            'roles'                         => $wpRoles,
            'non_admin_role_slugs'          => $allWpRolesSlugs,
            'non_admin_role_slugs_with_cap' => $userRolesWithPluginAccessCap
        );
    }

    /**
     * @return array
     */
    public static function getAllAdminUsers()
    {
        $args = array(
            'role'    => Menu::$defaultAccessRole,
            'orderby' => 'user_nicename',
            'order'   => 'ASC',
            'number'  => -1
        );

        return get_users($args);
    }

    /**
     * Return every user who can access Asset CleanUp and is therefore eligible
     * to be included in the CSS/JS Manager visibility list.
     *
     * Administrators are included through the default access role. Other users
     * can receive access directly or through a role in the Access Control tab.
     *
     * @return \WP_User[]
     */
    public static function getAllUsersWithPluginAccess()
    {
        $eligibleUsers = array();
        $usersWithPluginCapability = get_users(array(
            'capability' => Menu::$pluginAccessCap,
            'orderby'    => 'user_nicename',
            'order'      => 'ASC',
            'number'     => -1
        ));

        foreach (array_merge(self::getAllAdminUsers(), $usersWithPluginCapability) as $user) {
            if ( ! $user instanceof \WP_User ) {
                continue;
            }

            $eligibleUsers[(int)$user->ID] = $user;
        }

        uasort($eligibleUsers, static function ($firstUser, $secondUser) {
            return strcasecmp($firstUser->user_nicename, $secondUser->user_nicename);
        });

        return array_values($eligibleUsers);
    }

    /**
     * @return array<string,string> Role slug => display name.
     */
    public static function getAllRolesWithPluginAccess()
    {
        $eligibleRoles = array();

        foreach (wp_roles()->roles as $roleSlug => $roleData) {
            $role = get_role($roleSlug);

            if ($roleSlug !== Menu::$defaultAccessRole && ( ! $role || ! $role->has_cap(Menu::$pluginAccessCap) )) {
                continue;
            }

            $eligibleRoles[$roleSlug] = translate_user_role($roleData['name']);
        }

        asort($eligibleRoles, SORT_NATURAL | SORT_FLAG_CASE);

        return $eligibleRoles;
    }

    /**
     * @return void
     */
    public function ajaxSearchNonAdminUsersForDd()
    {
        check_ajax_referer('wpacu_search_non_admin_users_for_dd_nonce', 'wpacu_security');

        $action = isset($_POST['action']) ? sanitize_key(wp_unslash($_POST['action'])) : '';

        if ( ! isset($_POST['wpacu_query']) ||
             $action !== WPACU_PLUGIN_ID . '_search_non_admin_users_for_dd' ||
             ! current_user_can(Menu::$defaultAccessRole) ) {
            wp_send_json_error(array('message' => __('You are not allowed to search for users.', 'wp-asset-clean-up')), 403);
        }

        $queryTerm = trim(sanitize_text_field(wp_unslash($_POST['wpacu_query'])));

        if (strlen($queryTerm) < 2) {
            wp_send_json_success(array('results' => array()));
        }

        if (strlen($queryTerm) > 100) {
            $queryTerm = substr($queryTerm, 0, 100);
        }

        $excludedUserIds = array();

        if (isset($_POST['wpacu_exclude_user_ids']) && is_array($_POST['wpacu_exclude_user_ids'])) {
            $excludedUserIds = array_slice(
                array_values(array_unique(array_filter(array_map('absint', wp_unslash($_POST['wpacu_exclude_user_ids']))))),
                0,
                500
            );
        }

        global $wpdb;

        $queryLike = '%' . $wpdb->esc_like($queryTerm) . '%';
        $candidateLimit = self::$nonAdminUsersSearchResultsLimit * 5;
        $excludedUsersSql = '';
        $prepareValues = array(
            $queryLike,
            $queryLike,
            $queryLike,
            $queryLike,
            $queryLike
        );

        if ( ! empty($excludedUserIds) ) {
            $excludedUsersSql = ' AND u.ID NOT IN (' . implode(', ', array_fill(0, count($excludedUserIds), '%d')) . ')';
            $prepareValues = array_merge($prepareValues, $excludedUserIds);
        }

        $prepareValues[] = $candidateLimit;

        $sqlSearchQuery = $wpdb->prepare(
            "SELECT DISTINCT u.ID AS user_id, u.display_name, u.user_login
             FROM `{$wpdb->users}` u
             LEFT JOIN `{$wpdb->usermeta}` um_names
                    ON (u.ID = um_names.user_id AND um_names.meta_key IN ('first_name', 'last_name'))
             WHERE (
                    CAST(u.ID AS CHAR) LIKE %s
                 OR u.user_login LIKE %s
                 OR u.user_email LIKE %s
                 OR u.display_name LIKE %s
                 OR um_names.meta_value LIKE %s
             ){$excludedUsersSql}
             ORDER BY u.display_name ASC, u.user_login ASC
             LIMIT %d",
            $prepareValues
        );

        $rows = $wpdb->get_results($sqlSearchQuery, ARRAY_A);
        $results = array();

        foreach ($rows as $row) {
            $userId = (int)$row['user_id'];

            if (in_array($userId, $excludedUserIds, true)) {
                continue;
            }

            $user = get_user_by('id', $userId);

            if ( ! $user instanceof \WP_User || $user->has_cap(Menu::$defaultAccessRole) ) {
                continue;
            }

            $results[] = array(
                'id'   => (int)$user->ID,
                'text' => html_entity_decode(
                    wp_strip_all_tags(self::userOutputRelatedToPluginAccessDd($user)),
                    ENT_QUOTES,
                    get_bloginfo('charset') ?: 'UTF-8'
                )
            );

            if (count($results) >= self::$nonAdminUsersSearchResultsLimit) {
                break;
            }
        }

        wp_send_json_success(array('results' => $results));
    }

    /**
     * @return void
     */
    public function ajaxAddNonAdminUsersToChosenList()
    {
        check_ajax_referer('wpacu_search_non_admin_users_for_dd_nonce', 'wpacu_security');

        $action = isset($_POST['action']) ? sanitize_key(wp_unslash($_POST['action'])) : '';

        if ( ! isset($_POST['wpacu_user_id']) ||
             $action !== WPACU_PLUGIN_ID . '_add_non_admin_users_to_chosen_list' ||
             ! current_user_can(Menu::$defaultAccessRole) ) {
            wp_send_json_error(array('message' => __('You are not allowed to add users to the plugin access list.', 'wp-asset-clean-up')), 403);
        }

        $userId = absint(wp_unslash($_POST['wpacu_user_id']));

        $user = get_user_by('id', $userId);

        if ( ! $user instanceof \WP_User || $user->has_cap(Menu::$defaultAccessRole) ) {
            wp_send_json_error(array('message' => __('The selected non-administrator user is not valid.', 'wp-asset-clean-up')), 400);
        }

        ob_start();
        self::addedChosenNonAdminUserForPluginAccessOutput($user);
        $output = ob_get_clean();

        wp_send_json_success(array(
            'user_id' => (int)$user->ID,
            'html'    => $output
        ));
    }

    /**
     * Print one selected non-administrator account in the Access Control user list.
     *
     * The same markup is used for the initial page output and for users added through
     * the AJAX search interface, so the list remains visually and functionally consistent.
     *
     * @param \WP_User $user
     *
     * @return void
     */
    public static function addedChosenNonAdminUserForPluginAccessOutput($user)
    {
        if ( ! $user instanceof \WP_User ) {
            return;
        }

        $displayName = self::getUserDisplayNameForPluginAccess($user);
        $roleNames   = self::getUserRoleNamesForPluginAccess($user);
        $revokeLabel = sprintf(
            __('Revoke plugin access for %s', 'wp-asset-clean-up'),
            $displayName
        );
        ?>
        <article class="wpacu-access-user-card wpacu_non_admin_chosen_user_id_area"
                 data-wpacu-non-admin-chosen-user-id="<?php echo esc_attr($user->ID); ?>"
                 role="listitem">

            <input type="hidden"
                   name="<?php echo esc_attr(WPACU_PLUGIN_ID . '_settings'); ?>[access_via_specific_non_admin_users][]"
                   value="<?php echo esc_attr($user->ID); ?>" />

            <span class="wpacu-access-user-card__avatar" aria-hidden="true">
                <span class="dashicons dashicons-admin-users"></span>
            </span>

            <div class="wpacu-access-user-card__identity">
                <strong class="wpacu-access-user-card__name" title="<?php echo esc_attr($displayName); ?>">
                    <?php echo esc_html($displayName); ?>
                </strong>

                <div class="wpacu-access-user-card__meta">
                    <?php if ( ! empty($user->user_login) ) { ?>
                        <span class="wpacu-access-user-card__username" title="<?php echo esc_attr($user->user_login); ?>">
                            @<?php echo esc_html($user->user_login); ?>
                        </span>
                    <?php } ?>

                    <?php if ( ! empty($user->user_login) && ! empty($user->user_email) ) { ?>
                        <span class="wpacu-access-user-card__separator" aria-hidden="true">&bull;</span>
                    <?php } ?>

                    <?php if ( ! empty($user->user_email) ) { ?>
                        <span class="wpacu-access-user-card__email" title="<?php echo esc_attr($user->user_email); ?>">
                            <?php echo esc_html($user->user_email); ?>
                        </span>
                    <?php } ?>
                </div>

                <div class="wpacu-access-user-card__roles"
                     aria-label="<?php echo esc_attr(_n('User role', 'User roles', max(1, count($roleNames)), 'wp-asset-clean-up')); ?>">
                    <?php if (empty($roleNames)) { ?>
                        <span class="wpacu-access-user-role wpacu-access-user-role--muted">
                            <?php esc_html_e('No role assigned', 'wp-asset-clean-up'); ?>
                        </span>
                    <?php } else {
                        foreach ($roleNames as $roleName) { ?>
                            <span class="wpacu-access-user-role"><?php echo esc_html($roleName); ?></span>
                        <?php }
                    } ?>
                </div>
            </div>

            <button type="button"
                    class="wpacu-access-user-card__remove wpacu_remove_non_admin_access"
                    data-clear-wpacu-non-admin-chosen-user-id="<?php echo esc_attr($user->ID); ?>"
                    title="<?php echo esc_attr($revokeLabel); ?>"
                    aria-label="<?php echo esc_attr($revokeLabel); ?>">
                <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                <span class="screen-reader-text"><?php echo esc_html($revokeLabel); ?></span>
            </button>
        </article>
        <?php
    }

    /**
     * Build the compact user label used in the standard multi-select and AJAX results.
     *
     * @param \WP_User $user
     *
     * @return string
     */
    public static function userOutputRelatedToPluginAccessDd($user)
    {
        if ( ! $user instanceof \WP_User ) {
            return '';
        }

        $optionTextArray = array();
        $displayName     = self::getUserDisplayNameForPluginAccess($user);
        $roleNames       = self::getUserRoleNamesForPluginAccess($user);

        if ($displayName !== '') {
            $optionTextArray[] = $displayName;
        }

        if ( ! empty($user->user_login) ) {
            $optionTextArray[] = '@' . $user->user_login;
        }

        if ( ! empty($user->user_email) ) {
            $optionTextArray[] = $user->user_email;
        }

        if ( ! empty($roleNames) ) {
            $roleText = count($roleNames) > 1
                ? __('Roles', 'wp-asset-clean-up')
                : __('Role', 'wp-asset-clean-up');

            $optionTextArray[] = $roleText . ': ' . implode(', ', $roleNames);
        }

        return implode(' · ', array_values(array_unique($optionTextArray)));
    }

    /**
     * @param \WP_User $user
     *
     * @return string
     */
    private static function getUserDisplayNameForPluginAccess($user)
    {
        $fullName = trim($user->first_name . ' ' . $user->last_name);

        if ($fullName !== '') {
            return $fullName;
        }

        if ( ! empty($user->display_name) ) {
            return $user->display_name;
        }

        return (string) $user->user_login;
    }

    /**
     * @param \WP_User $user
     *
     * @return array
     */
    private static function getUserRoleNamesForPluginAccess($user)
    {
        $roleNames = array();
        $wpRoles   = wp_roles();

        foreach ((array) $user->roles as $roleSlug) {
            if (isset($wpRoles->roles[$roleSlug]['name'])) {
                $roleNames[] = translate_user_role($wpRoles->roles[$roleSlug]['name']);
            } else {
                $roleNames[] = $roleSlug;
            }
        }

        return array_values(array_unique($roleNames));
    }

    /**
     * @return string|null
     */
    public static function getTotalNonAdminUsers()
    {
        // Slow query for websites with lots of websites
        // Cache for the result for one day (it won't affect the functionality of the plugin)
        $transientKeyCache = WPACU_PLUGIN_ID.'_total_non_admin_users';

        $totalNonAdminUsers = get_transient($transientKeyCache);

        if ( $totalNonAdminUsers !== false ) {
            return (int)$totalNonAdminUsers;
        }

        global $wpdb;

        $defaultAccessRole = Menu::$defaultAccessRole;

        $totalNonAdminUsersQuery = <<<SQL
SELECT COUNT(ID) FROM `{$wpdb->users}` u
LEFT JOIN `{$wpdb->usermeta}` um ON (u.ID = um.user_id)
WHERE um.meta_key='{$wpdb->prefix}capabilities' AND um.meta_value NOT LIKE '%"{$defaultAccessRole}"%';
SQL;
        $totalNonAdminUsers      = (int)$wpdb->get_var($totalNonAdminUsersQuery);

        set_transient($transientKeyCache, $totalNonAdminUsers, 60 * 60 * 24);

        return $totalNonAdminUsers;
    }

    /**
     * @return bool
     */
    public static function useAutoCompleteSearchForNonAdminUsersDd()
    {
        return SettingsAdminOnlyForAdmin::getTotalNonAdminUsers() > self::$activateSearchDdInPluginAccessIfTotalNonAdminUsersExceeds;
    }

    /**
     * @return array
     */
    public static function getAllNonAdminUsers()
    {
        $argsAllUsers = array(
            'role__not_in' => Menu::$defaultAccessRole,
            'orderby'      => 'user_nicename',
            'order'        => 'ASC',
            'number'       => -1
        );

        return get_users($argsAllUsers);
    }

    /**
     *
     * e.g. "Settings" -- "Plugin Usage Preferences" -- "Plugin Access" -- "Give access for specific non-administrator users"
     *
     * @return array
     */
    public static function getSpecificNonAdminUsersWithPluginAccessCap()
    {
        // Run a low-consuming query first; It makes a difference in websites with lots of users
        global $wpdb;

        $pluginAccessCap = Menu::$pluginAccessCap;

        $sqlQuery = <<<SQL
SELECT umeta_id FROM `{$wpdb->usermeta}` WHERE `meta_key`='{$wpdb->prefix}capabilities' AND `meta_value` LIKE '%"{$pluginAccessCap}"%' LIMIT 1
SQL;
        $anyUserWithPluginAccessCap = $wpdb->get_var($sqlQuery);

        if ($anyUserWithPluginAccessCap) {
            // Finally, trigger the main query (more resources are used)
            $argsAllUsers = array(
                'role'         => \WpAssetCleanUp\Menu::$pluginAccessCap,
                'role__not_in' => 'administrator',
                'orderby'      => 'user_nicename',
                'order'        => 'ASC',
                'number'       => -1
            );

            return get_users($argsAllUsers);
        }

        return array(); // default
    }

    /**
     * @return void
     */
    public static function removePluginAccessCapabilityForAllExtraRoles()
    {
        $wpRoles         = wp_roles();
        $allWpRolesSlugs = array_keys($wpRoles->roles);

        foreach ($allWpRolesSlugs as $wpRoleSlug) {
            $wpRole = get_role($wpRoleSlug);
            $wpRole->remove_cap(Menu::$pluginAccessCap);
        }
    }

    /**
     * @return void
     */
    public static function removePluginAccessCapForAllSpecificUsers()
    {
        $allSpecificUsersWithCap = self::getSpecificNonAdminUsersWithPluginAccessCap();

        if ( ! empty($allSpecificUsersWithCap) ) {
            foreach ($allSpecificUsersWithCap as $specificUserWithCap) {
                $specificUserWithCap->remove_cap(Menu::$pluginAccessCap);
            }
        }
    }

    /**
     * @since v1.2.5.6 (Pro), v1.3.9.5 (Lite)
     *
     * @param $data
     *
     * @return array
     */
    public static function filterAnySpecifiedAdminsForAccessToAssetsManager($data = array())
    {
        if ( isset($data['allow_manage_assets_to']) && in_array($data['allow_manage_assets_to'], array('any_admin', 'selected', 'selected_roles'), true) ) {
            return $data;
        }

        $metaKeyTargeted = WPACU_PLUGIN_ID . '_user_chosen_for_access_to_assets_manager';

        // [Old plugin version fallback]
        if ( isset($data['allow_manage_assets_to'], $data['allow_manage_assets_to_list'])
             && $data['allow_manage_assets_to'] === 'chosen'
             && ! empty($data['allow_manage_assets_to_list']) && ! self::checkForAnyMetaKeyForAllowManageAssets($metaKeyTargeted) ) {
            $data['allow_manage_assets_to_list'] = array_unique($data['allow_manage_assets_to_list']);

            foreach ($data['allow_manage_assets_to_list'] as $specificUserId) {
                $user = get_user_by('id', $specificUserId);

                if ( ! isset($user->ID) ) {
                    $userIdNotActive = array_search($specificUserId, $data['allow_manage_assets_to_list']);
                    unset($data['allow_manage_assets_to_list'][$userIdNotActive]);
                    continue;
                }

                update_user_meta($specificUserId, $metaKeyTargeted, 1);
            }

            if ( empty($data['allow_manage_assets_to_list']) ) {
                // In case the chosen user(s) are not there anymore (the ID is in the settings, but the user was removed from WordPress)
                $data['allow_manage_assets_to']      = 'any_admin';
                $data['allow_manage_assets_to_list'] = array();
            }

            return $data;
        }
        // [/Old plugin version fallback]

        // Refill the values for $data['allow_manage_assets_to'] and $data['allow_manage_assets_to_list']
        // In order to use the same code as before in [...]/templates/_admin-page-settings-plugin-areas/_plugin-usage-settings/_assets-management.php
        global $wpdb;

        $objectCacheKey = 'wpacu_allow_manage_assets_to_list';

        $userIds = ObjectCache::wpacu_cache_get($objectCacheKey);

        if ($userIds === false) {
            $query   = <<<SQL
SELECT `user_id` FROM `{$wpdb->usermeta}` WHERE `meta_key`='{$metaKeyTargeted}'
SQL;
            $userIds = $wpdb->get_col($query);

            ObjectCache::wpacu_cache_set($objectCacheKey, $userIds);
        }

        if ( ! empty($userIds) ) {
            $data['allow_manage_assets_to']      = 'chosen';
            $data['allow_manage_assets_to_list'] = $userIds;
        } else {
            $data['allow_manage_assets_to']      = 'any_admin';
            $data['allow_manage_assets_to_list'] = array();
        }

        return $data;
    }

    /**
     * @param string $metaKeyTargeted
     *
     * @return string|null
     */
    public static function checkForAnyMetaKeyForAllowManageAssets($metaKeyTargeted)
    {
        global $wpdb;

        $objectCacheKey = 'wpacu_check_for_any_meta_key_for_allow_manage_assets';

        $result = ObjectCache::wpacu_cache_get($objectCacheKey);

        if ($result !== false) {
            return $result;
        }

        $sqlQuery = <<<SQL
SELECT `user_id` FROM `{$wpdb->usermeta}` WHERE `meta_key`='{$metaKeyTargeted}' LIMIT 1
SQL;
        $result = $wpdb->get_var($sqlQuery);

        ObjectCache::wpacu_cache_set($objectCacheKey, $result);

        return $result;
    }

    /**
     * e.g. "Settings" -- "Plugin Usage Preferences" -- "Plugin Access" -- "Give access for specific non-administrator users"
     *
     * @return array
     */
    public static function getSpecificNonAdminUsersIdsWithPluginAccessCap()
    {
        $objectCacheKey = 'wpacu_specific_non_admin_users_with_plugin_access_cap';

        $usersWithCapIds = ObjectCache::wpacu_cache_get($objectCacheKey);

        if ($usersWithCapIds !== false) {
            return $usersWithCapIds;
        }

        $specificUsersWithCap = self::getSpecificNonAdminUsersWithPluginAccessCap();

        if (empty($specificUsersWithCap)) {
            ObjectCache::wpacu_cache_set($objectCacheKey, array());
            return array();
        }

        $usersWithCapIds = array();

        foreach ($specificUsersWithCap as $specificUserWithCap) {
            $usersWithCapIds[] = $specificUserWithCap->ID;
        }

        ObjectCache::wpacu_cache_set($objectCacheKey, $usersWithCapIds);

        return $usersWithCapIds;
    }
}
