<?php
namespace WpAssetCleanUp;

/**
 * Reports whether rule-driven features have at least one saved rule anywhere.
 *
 * The result is request-cached because checking script attributes also requires
 * reading the per-page, taxonomy and author data stores.
 */
class DebugRuleState
{
    /** @var array|null */
    private static $states;

    /**
     * @return array
     */
    public static function getAll()
    {
        if (is_array(self::$states)) {
            return self::$states;
        }

        $globalData = wpacuGetGlobalData();
        $stylePreloads = isset($globalData['styles']['preloads']) && is_array($globalData['styles']['preloads'])
            ? array_filter($globalData['styles']['preloads']) : array();
        $scriptPreloads = isset($globalData['scripts']['preloads']) && is_array($globalData['scripts']['preloads'])
            ? array_filter($globalData['scripts']['preloads']) : array();

        self::$states = array(
            'css_preload_basic' => in_array('basic', $stylePreloads, true),
            'css_preload_async' => in_array('async', $stylePreloads, true),
            'js_preload_basic'  => ! empty($scriptPreloads),
            'css_position'      => ! empty($globalData['styles']['positions']),
            'js_position'       => ! empty($globalData['scripts']['positions']),
            'css_media_query'   => self::hasEnabledMediaQueryRule($globalData, 'styles'),
            'js_media_query'    => self::hasEnabledMediaQueryRule($globalData, 'scripts'),
            'hardcoded_css_unload' => false,
            'hardcoded_js_unload'  => false,
            'script_async'      => false,
            'script_defer'      => false
        );

        self::collectScriptAttributeStates($globalData);
        self::collectScriptAttributeStates(wpacuJsonDecodeToArray((string)get_option(WPACU_PLUGIN_ID . '_front_page_data')));
        self::collectScriptAttributeStatesFromMeta();
        self::collectHardcodedUnloadStates();

        return self::$states;
    }

    /**
     * @param array  $data
     * @param string $assetType
     *
     * @return bool
     */
    private static function hasEnabledMediaQueryRule($data, $assetType)
    {
        if (empty($data[$assetType]['media_queries_load']) || ! is_array($data[$assetType]['media_queries_load'])) {
            return false;
        }

        foreach ($data[$assetType]['media_queries_load'] as $rule) {
            if (is_array($rule) && ! empty($rule['enable']) && ! empty($rule['value'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $data
     *
     * @return void
     */
    private static function collectScriptAttributeStates($data)
    {
        if (empty($data['scripts']) || ! is_array($data['scripts'])) {
            return;
        }

        self::walkForScriptAttributes($data['scripts']);
    }

    /**
     * @param array $data
     *
     * @return void
     */
    private static function walkForScriptAttributes($data)
    {
        foreach ($data as $key => $value) {
            if ($key === 'attributes' && is_array($value)) {
                self::$states['script_async'] = self::$states['script_async'] || in_array('async', $value, true);
                self::$states['script_defer'] = self::$states['script_defer'] || in_array('defer', $value, true);
            } elseif (is_array($value)) {
                self::walkForScriptAttributes($value);
            }
        }
    }

    /**
     * @return void
     */
    private static function collectScriptAttributeStatesFromMeta()
    {
        global $wpdb;

        $metaKey = '_' . WPACU_PLUGIN_ID . '_data';
        foreach (array($wpdb->postmeta, $wpdb->termmeta, $wpdb->usermeta) as $tableName) {
            $values = $wpdb->get_col($wpdb->prepare(
                "SELECT meta_value FROM `{$tableName}` WHERE meta_key=%s AND meta_value LIKE %s",
                $metaKey,
                '%"attributes"%'
            ));

            foreach ((array)$values as $value) {
                self::collectScriptAttributeStates(wpacuJsonDecodeToArray($value));
                if (self::$states['script_async'] && self::$states['script_defer']) {
                    return;
                }
            }
        }
    }

    /**
     * Check every storage location that can contain page-level or bulk unload
     * rules. Other hardcoded rules (preload, position, attributes) must not make
     * these two unload-specific states active.
     *
     * @return void
     */
    private static function collectHardcodedUnloadStates()
    {
        self::walkForHardcodedUnloadHandles(
            wpacuJsonDecodeToArray((string)get_option(WPACU_PLUGIN_ID . '_bulk_unload'))
        );
        self::walkForHardcodedUnloadHandles(
            wpacuJsonDecodeToArray((string)get_option(WPACU_PLUGIN_ID . '_front_page_no_load'))
        );

        if (self::$states['hardcoded_css_unload'] && self::$states['hardcoded_js_unload']) {
            return;
        }

        global $wpdb;
        $metaKey = '_' . WPACU_PLUGIN_ID . '_no_load';

        foreach (array($wpdb->postmeta, $wpdb->termmeta, $wpdb->usermeta) as $tableName) {
            $values = $wpdb->get_col($wpdb->prepare(
                "SELECT meta_value FROM `{$tableName}` WHERE meta_key=%s AND meta_value LIKE %s",
                $metaKey,
                '%wpacu_hardcoded_%'
            ));

            foreach ((array)$values as $value) {
                self::walkForHardcodedUnloadHandles(wpacuJsonDecodeToArray($value));
                if (self::$states['hardcoded_css_unload'] && self::$states['hardcoded_js_unload']) {
                    return;
                }
            }
        }
    }

    /**
     * @param mixed $data
     *
     * @return void
     */
    private static function walkForHardcodedUnloadHandles($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                self::walkForHardcodedUnloadHandles($key);
                self::walkForHardcodedUnloadHandles($value);
            }
            return;
        }

        if ( ! is_string($data)) {
            return;
        }

        if (strpos($data, 'wpacu_hardcoded_link_') !== false
            || strpos($data, 'wpacu_hardcoded_style_') !== false) {
            self::$states['hardcoded_css_unload'] = true;
        }

        if (strpos($data, 'wpacu_hardcoded_script_') !== false
            || strpos($data, 'wpacu_hardcoded_noscript_') !== false) {
            self::$states['hardcoded_js_unload'] = true;
        }
    }
}
