<?php
namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\Menu;
use WpAssetCleanUp\OptimiseAssets\AlternativeAssets;

/** Selective, site-local cleanup. Never infer ownership from a broad plugin prefix. */
class ProDataCleanup
{
    public static function canAccess()
    {
        return ! defined('WPACU_PRO_PLUGIN_VERSION')
            && Menu::userCanAccessPlugin() && current_user_can('manage_options') && ! is_network_admin();
    }

    /** Whether the cleanup preview contains saved Pro configuration to remove. */
    public static function hasDataToClear()
    {
        $plan = self::plan();
        return array_sum($plan['counts']) > 0;
    }

    private static function settingsKeys()
    {
        // Mirrors Pro-only SettingsPro keys; shared font hosting and Critical CSS stay intact.
        return array('defer_css_loaded_body', 'google_fonts_local_auto_process',
            'google_fonts_local_browser_discovery', 'google_fonts_local_refresh_days',
            'google_fonts_local_exceptions', 'google_fonts_remove_specific', 'inline_js_files',
            'move_scripts_to_body', 'plugins_manager_front_disable', 'plugins_manager_front_layout',
            'plugins_manager_front_homepage_detect_extra_ignore_query_string_list',
            'plugins_manager_dash_disable', 'plugins_manage_dash_restore_left_sidebar',
            'plugins_manage_dash_restore_left_sidebar_options');
    }

    private static function optionKeys()
    {
        return array('post_type_via_tax_load_exceptions', 'tax_type_load_exceptions',
            'author_type_load_exceptions', 'extras_load_exceptions', 'pro_license_key',
            'pro_license_status', 'pro_license_response', 'pro_license_renewal_link',
            'plugins_request_context_map_rebuild_required', 'google_fonts_local_pro_lock');
    }

    /** Remove only recognized Pro fields, retaining unknown extension data. */
    private static function filterData($data, $kind)
    {
        if ($kind === 'settings') {
            foreach (self::settingsKeys() as $key) { unset($data[$key]); }
            if (isset($data['do_not_load_plugin_features']) && is_array($data['do_not_load_plugin_features'])) {
                $changed = false;
                foreach ($data['do_not_load_plugin_features'] as $i => $rule) {
                    if (isset($rule['list']) && is_array($rule['list'])) {
                        $remaining = array_diff($rule['list'], array('defer_css_body', 'inline_js', 'move_scripts_to_body'));
                        if ($remaining === $rule['list']) { continue; }
                        $changed = true;
                        $data['do_not_load_plugin_features'][$i]['list'] = array_values($remaining);
                        if ( ! $data['do_not_load_plugin_features'][$i]['list']) { unset($data['do_not_load_plugin_features'][$i]); }
                    }
                }
                if ($changed) { $data['do_not_load_plugin_features'] = array_values($data['do_not_load_plugin_features']); }
            }
            return $data;
        }
        if ($kind === 'global_data') {
            foreach (array('plugins', 'plugins_dash', 'plugins_request_context_map', 'plugin_row_contracted') as $key) { unset($data[$key]); }
        }
        unset($data['scripts_attributes_no_load']);
        foreach (array('styles', 'scripts') as $type) {
            if ( ! isset($data[$type]) || ! is_array($data[$type])) { continue; }
            $originalValues = $data[$type];
            if ($kind === 'bulk_unload') {
                foreach (array_keys($data[$type]) as $key) {
                    if (in_array((string)$key, array('taxonomy', 'author', 'post_type_via_tax', 'date', 'search', '404'), true)
                        || strpos((string)$key, 'custom_post_type_archive_') === 0) { unset($data[$type][$key]); }
                }
            } else {
                foreach (array('positions', 'media_queries_load', 'load_regex', 'unload_regex') as $key) { unset($data[$type][$key]); }
                // Global data nests handles under "everywhere"; per-object data does not.
                if ($kind === 'global_data') {
                    foreach (array_keys($data[$type]) as $scope) {
                        if ((in_array((string)$scope, array('everywhere', 'search', 'date', '404'), true)
                            || strpos((string)$scope, 'custom_post_type_archive_') === 0) && is_array($data[$type][$scope])) {
                            $handles = self::removeAttributes($data[$type][$scope]);
                            if ($handles !== $data[$type][$scope]) {
                                $data[$type][$scope] = $handles;
                                if ( ! $handles) { unset($data[$type][$scope]); }
                            }
                        }
                    }
                } else {
                    $data[$type] = self::removeAttributes($data[$type]);
                }
            }
            if ( ! $data[$type] && $data[$type] !== $originalValues) { unset($data[$type]); }
        }
        return $data;
    }

    private static function removeAttributes($handles)
    {
        foreach ($handles as $handle => $value) {
            if ( ! is_array($value) || ! isset($value['attributes']) || ! is_array($value['attributes'])) { continue; }
            $remaining = array_diff($value['attributes'], array('async', 'defer'));
            if ($remaining === $value['attributes']) { continue; }
            $value['attributes'] = array_values($remaining);
            if ( ! $value['attributes']) { unset($value['attributes']); }
            if ($value) { $handles[$handle] = $value; } else { unset($handles[$handle]); }
        }
        return $handles;
    }

    public static function preview()
    {
        $plan = self::plan();
        return array('counts' => $plan['counts'], 'errors' => $plan['errors'],
            'token' => self::token($plan), 'shared_user_meta_preserved' => is_multisite(),
            'allowed' => self::canAccess() && ( ! $plan['counts']['javascript_alternatives'] || Menu::userCanManageAlternativeAssets()));
    }

    private static function token($plan)
    {
        return wp_hash(get_current_blog_id() . ':' . get_current_user_id() . ':' . wp_json_encode($plan));
    }

    private static function plan()
    {
        global $wpdb;
        $plan = array('options' => array(), 'meta' => array(), 'js' => array(), 'errors' => array(),
            'counts' => array('options' => 0, 'metadata' => 0, 'javascript_alternatives' => 0));
        foreach (self::optionKeys() as $suffix) {
            $key = WPACU_PLUGIN_ID . '_' . $suffix;
            $old = get_option($key, null);
            if ($old !== null) { $plan['options'][$key] = array('old' => $old, 'new' => null); }
        }
        foreach (array('settings', 'global_data', 'bulk_unload', 'front_page_data') as $kind) {
            $key = WPACU_PLUGIN_ID . '_' . $kind;
            $old = get_option($key, null);
            if ($old === null || $old === '') { continue; }
            $data = is_string($old) ? json_decode($old, true) : null;
            if ( ! is_array($data)) {
                $plan['errors'][] = sprintf(__('Cannot safely read %s. No data will be removed.', 'wp-asset-clean-up'), $key);
                continue;
            }
            $new = self::filterData($data, $kind);
            if ($new !== $data) { $plan['options'][$key] = array('old' => $old, 'new' => $new ? wp_json_encode($new) : null); }
        }
        $tables = array('post' => $wpdb->postmeta, 'term' => $wpdb->termmeta);
        // These user keys have no blog ID: another site may use them even if currently inactive.
        if ( ! is_multisite()) { $tables['user'] = $wpdb->usermeta; }
        foreach ($tables as $type => $table) {
            $keys = array('_' . WPACU_PLUGIN_ID . '_data');
            if ($type !== 'post') { $keys[] = '_' . WPACU_PLUGIN_ID . '_no_load'; $keys[] = '_' . WPACU_PLUGIN_ID . '_load_exceptions'; }
            $idColumn = $type === 'user' ? 'umeta_id' : 'meta_id';
            $rows = $wpdb->get_results($wpdb->prepare("SELECT {$idColumn} AS id, meta_key, meta_value FROM `{$table}` WHERE meta_key IN (" . implode(',', array_fill(0, count($keys), '%s')) . ") ORDER BY {$idColumn}", $keys), ARRAY_A);
            if ($wpdb->last_error) { $plan['errors'][] = __('The metadata preview could not be read. No data will be removed.', 'wp-asset-clean-up'); }
            foreach ((array)$rows as $row) {
                $new = null;
                if ($row['meta_key'] === '_' . WPACU_PLUGIN_ID . '_data') {
                    $data = json_decode($row['meta_value'], true);
                    if ( ! is_array($data)) {
                        $plan['errors'][] = __('Some advanced asset metadata could not be read safely. No data will be removed.', 'wp-asset-clean-up');
                        continue;
                    }
                    $filtered = self::filterData($data, 'object');
                    if ($filtered === $data) { continue; }
                    $new = $filtered ? wp_json_encode($filtered) : null;
                }
                $plan['meta'][] = array('type' => $type, 'id' => (int)$row['id'], 'old' => $row['meta_value'], 'new' => $new);
            }
        }
        $rawRules = get_option(AlternativeAssets::RULES_OPTION, array());
        if ( ! is_array($rawRules)) {
            $plan['errors'][] = __('The Alternative Assets index could not be read safely.', 'wp-asset-clean-up');
        } else {
            foreach ($rawRules as $rule) {
                if (is_array($rule) && isset($rule['type']) && $rule['type'] === 'js') {
                    if (empty($rule['id'])) { $plan['errors'][] = __('A JavaScript alternative has an invalid ID.', 'wp-asset-clean-up'); continue; }
                    $plan['js'][] = array('id' => (int)$rule['id'], 'rule' => $rule, 'source_hash' => sha1(AlternativeAssets::getSource($rule['id'])));
                }
            }
        }
        $plan['counts']['options'] = count($plan['options']);
        $plan['counts']['metadata'] = count($plan['meta']);
        $plan['counts']['javascript_alternatives'] = count($plan['js']);
        return $plan;
    }

    public static function execute($previewToken)
    {
        if (defined('WPACU_PRO_PLUGIN_VERSION')) {
            return new \WP_Error('forbidden', __('Clearing Pro data is available only while Asset CleanUp Lite is active. No data was removed.', 'wp-asset-clean-up'));
        }
        if ( ! self::canAccess()) { return new \WP_Error('forbidden', __('You need administrator permission to clear Pro data.', 'wp-asset-clean-up')); }
        $plan = self::plan();
        if ($plan['js'] && ! Menu::userCanManageAlternativeAssets()) {
            return new \WP_Error('forbidden', __('Removing saved JavaScript alternatives requires the unfiltered_html capability. No data was removed.', 'wp-asset-clean-up'));
        }
        if ($plan['errors']) { return new \WP_Error('invalid_data', implode(' ', $plan['errors'])); }
        if ( ! is_string($previewToken) || ! hash_equals(self::token($plan), $previewToken)) {
            return new \WP_Error('stale_preview', __('The saved data has changed. Reload Reset and review the new preview before confirming. No data was removed.', 'wp-asset-clean-up'));
        }
        $result = array('deleted_options' => 0, 'updated_options' => 0, 'deleted_meta_keys' => 0,
            'updated_meta_keys' => 0, 'deleted_alternatives' => 0, 'errors' => array());
        foreach ($plan['options'] as $key => $change) {
            if ($change['new'] === null) {
                delete_option($key); $ok = get_option($key, null) === null; $counter = 'deleted_options';
            } else {
                update_option($key, $change['new']); $ok = get_option($key) === $change['new']; $counter = 'updated_options';
            }
            if ($ok) { ++$result[$counter]; } else { $result['errors'][] = __('Some Pro options could not be cleared. Review the remaining data and retry.', 'wp-asset-clean-up'); }
        }
        foreach ($plan['meta'] as $change) {
            if ($change['new'] === null) {
                $ok = delete_metadata_by_mid($change['type'], $change['id']); $counter = 'deleted_meta_keys';
            } else {
                // Unlike update_post_meta(), the by-mid API does not strip slashes.
                $ok = update_metadata_by_mid($change['type'], $change['id'], $change['new']); $counter = 'updated_meta_keys';
            }
            if ($ok) { ++$result[$counter]; } else { $result['errors'][] = __('Some Pro metadata could not be cleared. Review the remaining data and retry.', 'wp-asset-clean-up'); }
        }
        if ($plan['js']) {
            $rules = get_option(AlternativeAssets::RULES_OPTION, array());
            $removeIds = array_column($plan['js'], 'id');
            foreach ($removeIds as $id) { AlternativeAssets::retainGeneratedFiles($id); }
            $keep = array_values(array_filter($rules, static function ($rule) use ($removeIds) {
                return ! (isset($rule['type'], $rule['id']) && $rule['type'] === 'js' && in_array((int)$rule['id'], $removeIds, true));
            }));
            AlternativeAssets::saveRules($keep);
            if (get_option(AlternativeAssets::RULES_OPTION) === $keep) {
                foreach ($removeIds as $id) {
                    delete_option(AlternativeAssets::SOURCE_OPTION_PREFIX . $id);
                    if (get_option(AlternativeAssets::SOURCE_OPTION_PREFIX . $id, null) === null) { ++$result['deleted_alternatives']; }
                    else { $result['errors'][] = __('A JavaScript alternative source could not be removed.', 'wp-asset-clean-up'); }
                }
            } else { $result['errors'][] = __('The JavaScript alternatives could not be removed.', 'wp-asset-clean-up'); }
        }
        self::clearOperationalData();
        unset($GLOBALS['wpacu_global_data_json_decoded']);
        $result['errors'] = array_values(array_unique($result['errors']));
        return $result;
    }

    private static function clearOperationalData()
    {
        global $wpdb;
        // Local updater caches only: never touch unrelated plugins or network license records.
        $patterns = array(WPACU_PLUGIN_ID . '_edd_sl_', '_transient_' . WPACU_PLUGIN_ID . '_plugin_updater_forced_',
            '_transient_' . WPACU_PLUGIN_ID . '_license_connection_error_report_', '_transient_wpacu_gfl_rate_');
        foreach ($patterns as $prefix) {
            $keys = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM `{$wpdb->options}` WHERE option_name LIKE %s", $wpdb->esc_like($prefix) . '%'));
            foreach ($keys as $key) {
                if (strpos($key, '_transient_') === 0) { delete_transient(substr($key, strlen('_transient_'))); }
                else { delete_option($key); }
            }
        }
        foreach (array('plugins_manager_updated', 'no_activations_left_upgrades_output', 'license_activation_failed_msg',
            'license_just_activated', 'license_just_deactivated', 'last_time_check_outside_license_page') as $suffix) {
            delete_transient(WPACU_PLUGIN_ID . '_' . $suffix);
        }
        delete_transient('wpacu_google_fonts_local_pro_maintenance');
        wp_clear_scheduled_hook('wpassetcleanup_google_fonts_local_process_queue');
    }
}
