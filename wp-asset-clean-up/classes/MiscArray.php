<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp;

/**
 * Class MiscArray
 * contains various common functions that are used by the plugin
 * @package WpAssetCleanUp
 */
class MiscArray
{
    /**
     * Check if a scalar value is considered non-empty.
     *
     * Valid values include:
     * - 0
     * - '0'
     * - false
     * - true
     * - any non-empty string
     *
     * Invalid values:
     * - null
     * - ''
     *
     * @param mixed $value
     *
     * @return bool
     */
    public static function isNonEmptyValue($value)
    {
        return $value !== null && $value !== '';
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public static function hasNonEmptyValue($value)
    {
        if ( ! is_array($value) ) {
            return self::isNonEmptyValue($value);
        }

        foreach ($value as $childValue) {
            if (self::hasNonEmptyValue($childValue)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a value is non-empty.
     *
     * If $path is provided, the nested value is checked.
     *
     * Examples:
     * self::isNonEmpty($settings);
     * self::isNonEmpty($settings, 'dom_get_type');
     * self::isNonEmpty($settings, 'images.attr.data');
     * self::isNonEmpty($settings, '[images][attr][data]');
     * self::isNonEmpty($settings, 'images.attr.data', true); // must be array
     *
     * If $mustBeArray is true, the resolved value must be an array.
     *
     * @param mixed        $value
     * @param string|array $path
     * @param bool         $mustBeArray
     *
     * @return bool
     */
    public static function isNonEmpty($value, $path = null, $mustBeArray = false)
    {
        if ($path !== null) {
            $value = self::getNestedValue($value, $path);
        }

        if ($mustBeArray && ! is_array($value)) {
            return false;
        }

        return self::hasNonEmptyValue($value);
    }

    /**
     * Alias of isNonEmpty().
     *
     * @param mixed        $value
     * @param string|array $path
     * @param bool         $mustBeArray
     *
     * @return bool
     */
    public static function isValid($value, $path = null, $mustBeArray = false)
    {
        return self::isNonEmpty($value, $path, $mustBeArray);
    }

    /**
     * Opposite of isNonEmpty().
     *
     * @param mixed        $value
     * @param string|array $path
     * @param bool         $mustBeArray
     *
     * @return bool
     */
    public static function isEmpty($value, $path = null, $mustBeArray = false)
    {
        return ! self::isNonEmpty($value, $path, $mustBeArray);
    }

    /**
     * Get an array value by key/path.
     *
     * Supported formats:
     * - 'resource_loading'
     * - '[resource_loading]'
     * - '[resource_loading][images][attr]'
     * - 'images.attr.data'
     * - array('images', 'attr', 'data')
     *
     * @param array        $array
     * @param string|array $key
     * @param mixed|null   $default
     *
     * @return mixed|null
     */
    public static function getValue($array, $key, $default = null)
    {
        if ( ! is_array($array) ) {
            return $default;
        }

        if ( is_array($key) ) {
            return self::getNestedValue($array, $key, $default);
        }

        if ( ! is_string($key) ) {
            return $default;
        }

        $key = trim($key);

        if ($key === '') {
            return $default;
        }

        // e.g. [resource_loading] => resource_loading
        if (preg_match('#^\[([^\]]+)\]$#', $key, $matches)) {
            $key = $matches[1];
        }

        // Simple top-level key.
        if (strpos($key, '[') === false && strpos($key, '.') === false) {
            if ( ! array_key_exists($key, $array) ) {
                return $default;
            }

            return ($array[$key] === '') ? $default : $array[$key];
        }

        // Nested path.
        return self::getNestedValue($array, $key, $default);
    }

    /**
     * Get a nested array value by path.
     *
     * Supported path formats:
     * - [images][attr][data]
     * - images.attr.data
     * - array('images', 'attr', 'data')
     *
     * @param array        $array
     * @param string|array $path
     * @param mixed|null   $default
     *
     * @return mixed|null
     */
    public static function getNestedValue($array, $path, $default = null)
    {
        if ( ! is_array($array) ) {
            return $default;
        }

        if ( is_array($path) ) {
            $keys = $path;
        } else {
            $path = trim($path);

            if ($path === '') {
                return $default;
            }

            if ( strpos($path, '[') === 0 ) {
                preg_match_all('#\[([^\]]+)\]#', $path, $matches);
                $keys = isset($matches[1]) ? $matches[1] : array();
            } else {
                $keys = array_filter(explode('.', $path), 'strlen');
            }
        }

        if ( empty($keys) ) {
            return $default;
        }

        foreach ($keys as $key) {
            if ( ! is_array($array) || ! array_key_exists($key, $array) ) {
                return $default;
            }

            $array = $array[$key];
        }

        return ($array === '') ? $default : $array;
    }

    /**
     * @param $array
     * @param $prefix
     *
     * @return bool
     */
    public static function hasKeyStartingWith($array, $prefix)
    {
        foreach ($array as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $list
     * @param string $for
     *
     * @return array
     */
    public static function filterList($list, $for = 'empty_values')
    {
        if ( ! empty($list) && $for === 'empty_values' ) {
            $list = self::unsetRecursive($list);
        }

        return $list;
    }

    /**
     * Source: https://stackoverflow.com/questions/7696548/php-how-to-remove-empty-entries-of-an-array-recursively
     *
     * @param $array
     *
     * @return array
     */
    public static function unsetRecursive($array)
    {
        $array = (array)$array; // in case it's object, convert it to array

        foreach ($array as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $array[$key] = self::unsetRecursive($value);
            }

            // Values such as '0' are not considered empty values
            if (is_string($value) && trim($value) === '0') {
                continue;
            }

            // Clear it if it's empty
            if (empty($array[$key])) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    /**
     * @param $transientName
     * @param $value
     * @param $expiration
     *
     * @return void
     */
    public static function addToTransient($transientName, $value, $expiration = 0)
    {
        $data = get_transient($transientName);

        if ( ! is_array($data) ) {
            $data = array();
        }

        $data[] = $value;

        set_transient($transientName, $data, $expiration);
    }

    }
