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
     * If the total number of users is above this number, the auto-complete is activated
     * For instance, there could be tens of thousands of users on specific websites
     * It's not effective to put all in one drop-down (too many resources would be used)
     *
     * @var int
     */
    public static $activateSearchDdInPluginAccessIfTotalNonAdminUsersExceeds = 200;


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
            $_POST[WPACU_PLUGIN_ID . '_settings']['allow_manage_assets_to'] === 'chosen' &&
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
                $wpRole->add_cap(Menu::$pluginAccessCap);
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
     * @return void
     */
    public function ajaxSearchNonAdminUsersForDd()
    {
        check_ajax_referer('wpacu_search_non_admin_users_for_dd_nonce', 'wpacu_security');

        if ( ! ( isset($_POST['action'], $_POST['wpacu_query']) &&
                $_POST['action'] && trim($_POST['wpacu_query']) &&
                $_POST['action'] === WPACU_PLUGIN_ID . '_search_non_admin_users_for_dd' ) ||
             ! current_user_can(Menu::$defaultAccessRole) ) {
            exit();
        }

        global $wpdb;

        $queryTerm = $wpdb->esc_like(trim($_POST['wpacu_query']));

        $sqlSearchQuery = <<<SQL
SELECT DISTINCT u.ID as user_id FROM `{$wpdb->users}` u
LEFT JOIN `{$wpdb->usermeta}` um ON (u.ID = um.user_id)
WHERE u.ID          LIKE  '%{$queryTerm}%'
   OR u.user_login  LIKE  '%{$queryTerm}%'
   OR u.user_email  LIKE  '%{$queryTerm}%'
   OR (um.meta_key='first_name' AND um.meta_value LIKE '%{$queryTerm}%')
   OR (um.meta_key='last_name' AND um.meta_value LIKE '%{$queryTerm}%')
SQL;
        $rows = $wpdb->get_results($sqlSearchQuery, ARRAY_A);

        foreach ($rows as $row) {
            $user = new \WP_User($row['user_id']);

            if ($user->has_cap(Menu::$defaultAccessRole)) {
                continue;
            }
            ?>
            <option value="<?php echo $user->ID; ?>"><?php echo esc_html(self::userOutputRelatedToPluginAccessDd($user)); ?></option>
            <?php
        }

        exit();
    }

    /**
     * @return void
     */
    public function ajaxAddNonAdminUsersToChosenList()
    {
        if ( ! ( isset($_POST['action'], $_POST['wpacu_user_id']) &&
                 $_POST['action'] && $_POST['wpacu_user_id'] &&
                 $_POST['action'] === WPACU_PLUGIN_ID . '_add_non_admin_users_to_chosen_list' ) ||
             ! Menu::userCanAccessAssetCleanUp() ) {
            exit();
        }

        $userId = (int)$_POST['wpacu_user_id'];

        $user = new \WP_User($userId);

        if ($user->has_cap(Menu::$defaultAccessRole)) {
            // Something's not right (wrong ID perhaps)
            // Only non
            exit();
        }

        self::addedChosenNonAdminUserForPluginAccessOutput($user);

        exit();
    }

    /**
     * @param $data
     *
     * @return void
     */
    public static function addedChosenNonAdminUserForPluginAccessOutput($user)
    {
        ?>
        <div class="wpacu_non_admin_chosen_user_id_area"
             data-wpacu-non-admin-chosen-user-id="<?php echo $user->ID; ?>">

            <input type="hidden"
                   name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[access_via_specific_non_admin_users][]"
                   value="<?php echo $user->ID; ?>" />

            <?php echo esc_html(self::userOutputRelatedToPluginAccessDd($user)); ?>

            <a class="wpacu_remove_non_admin_access"
               data-clear-wpacu-non-admin-chosen-user-id="<?php echo $user->ID; ?>"
               title="<?php esc_attr_e('Revoke plugin access for this user'); ?>"
               href="#"><span class="dashicons dashicons-no-alt"></span></a>
        </div>
        <?php
    }

    /**
     * @param object $user
     *
     * @return string
     */
    public static function userOutputRelatedToPluginAccessDd($user)
    {
        $roleText = __('Role', 'wp-asset-clean-up');

        if (count($user->roles) > 1) {
            $roleText = __('Roles', 'wp-asset-clean-up');
        }

        $fullName = '';

        if ($user->first_name || $user->last_name) {
            $fullName = trim($user->first_name.' '.$user->last_name);
        }

        $optionTextArray = array();

        if ($fullName) {
            $optionTextArray[] = esc_html($fullName);
        }

        if ($user->user_login !== $user->user_email) {
            $optionTextArray[] = __('User', 'wp-asset-clean-up') . ': ' . esc_html($user->user_login);
        }

        $optionTextArray[] = __('Email', 'wp-asset-clean-up') . ': '.esc_html($user->user_email);

        if ( ! empty($user->roles) ) {
            $optionTextArray[] = $roleText . ': ' . implode(', ', $user->roles);
        }

        return implode(' / ', $optionTextArray);
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
        if ( isset($data['allow_manage_assets_to']) && $data['allow_manage_assets_to'] === 'any_admin' ) {
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
