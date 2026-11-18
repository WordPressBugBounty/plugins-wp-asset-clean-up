<?php
namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\Main;

/**
 * Shared settings and runtime bridge for selective Google Fonts removal.
 */
class FontsGoogleRemoveSpecific
{
    /** @var bool */
    private static $registered = false;

    /**
     * @return void
     */
    public static function registerHooks()
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        // Historical hook name: it now indicates availability in both editions.
        add_filter('wpacu_google_fonts_remove_specific_is_pro', '__return_true');
        add_filter('wpacu_google_fonts_remove_specific_rules', array(__CLASS__, 'filterRules'));
        add_filter('wpacu_internal_settings_keys', array(__CLASS__, 'settingsKeys'));
        add_filter('wpacu_internal_settings_default_values', array(__CLASS__, 'defaultValues'));
        add_filter('wpacu_internal_filter_settings_after_common', array(__CLASS__, 'normalizeSettings'));
        add_filter('wpacu_settings_form_submit_before_save', array(__CLASS__, 'sanitizeSettings'));
    }

    /**
     * @param array $keys
     *
     * @return array
     */
    public static function settingsKeys($keys)
    {
        if ( ! in_array('google_fonts_remove_specific', $keys, true) ) {
            $keys[] = 'google_fonts_remove_specific';
        }

        return $keys;
    }

    /**
     * @param array $values
     *
     * @return array
     */
    public static function defaultValues($values)
    {
        $values['google_fonts_remove_specific'] = '';

        return $values;
    }

    /**
     * @param array $settings
     *
     * @return array
     */
    public static function normalizeSettings($settings)
    {
        if ( ! isset($settings['google_fonts_remove_specific']) || ! is_string($settings['google_fonts_remove_specific']) ) {
            $settings['google_fonts_remove_specific'] = '';
        }

        return $settings;
    }

    /**
     * @param array $rules
     *
     * @return array
     */
    public static function filterRules($rules)
    {
        $settings = Main::instance()->settings;
        $stored   = isset($settings['google_fonts_remove_specific']) ? $settings['google_fonts_remove_specific'] : '';

        if ( ! is_string($stored) || trim($stored) === '' ) {
            return array();
        }

        return self::normalizeRules(preg_split('/\r\n|\r|\n/', $stored));
    }

    /**
     * Accept the compact form snapshot or the checkbox array, never nested values.
     *
     * @param mixed $settings
     *
     * @return array
     */
    public static function sanitizeSettings($settings)
    {
        if ( ! is_array($settings) ) {
            return array();
        }

        if (array_key_exists('google_fonts_remove_specific_serialized', $settings)) {
            $serialized = $settings['google_fonts_remove_specific_serialized'];
            $settings['google_fonts_remove_specific'] = is_string($serialized)
                ? preg_split('/\r\n|\r|\n/', $serialized) : array();
            unset($settings['google_fonts_remove_specific_serialized']);
        }

        if (array_key_exists('google_fonts_remove_specific', $settings)) {
            $submitted = $settings['google_fonts_remove_specific'];

            if (is_string($submitted)) {
                $submitted = preg_split('/\r\n|\r|\n/', $submitted);
            }

            $settings['google_fonts_remove_specific'] = implode("\n", self::normalizeRules($submitted));
        }

        return $settings;
    }

    /**
     * Validate and deduplicate the same tokens used by the shared runtime.
     *
     * @param mixed $rules
     *
     * @return array
     */
    private static function normalizeRules($rules)
    {
        $valid = array();

        foreach (is_array($rules) ? $rules : array() as $rule) {
            if ( ! is_string($rule) ) {
                continue;
            }

            $decoded = FontsGoogleRemove::decodeSpecificRule(trim(strip_tags($rule)));

            if ( ! empty($decoded) ) {
                $valid[FontsGoogleRemove::encodeSpecificRule($decoded['family'], $decoded['weight'], $decoded['style'])] = true;
            }

            if (count($valid) >= 200) {
                break;
            }
        }

        return array_keys($valid);
    }
}
