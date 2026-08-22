<?php
namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\MiscArray;
use WpAssetCleanUp\OptimiseAssets\ResourceLoading;

/**
 *
 */
class SettingsAdminPurifier
{
    /**
     * @param $value
     *
     * @return string
     */
    public static function sanitizeSkipClasses($value)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string)$value);
        $clean = array();

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // Allow user to paste ".class-name", but store "class-name"
            $line = ltrim($line, '.');

            // If they pasted multiple classes like ".hero.large", keep only valid individual classes
            $parts = preg_split('/\s+|\./', $line);

            foreach ($parts as $part) {
                $part = trim($part);

                if ($part === '') {
                    continue;
                }

                if (preg_match('/^[A-Za-z_-][A-Za-z0-9_-]*$/', $part)) {
                    $clean[] = $part;
                }
            }
        }

        return implode("\n", array_unique($clean));
    }

    /**
     * @param $value
     *
     * @return string
     */
    public static function sanitizeUrlKeywords($value)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string)$value);
        $clean = $errorExcludeUrlKeywordsInvalidRegex = array();

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (ResourceLoading::startsWithRegexDelimiter($line)) {
                if (ResourceLoading::isValidRegex($line)) {
                    // RegEx
                    $clean[] = $line; // add it to the list
                } else {
                    // Invalid regex that started with # ~ ! %
                    $errorExcludeUrlKeywordsInvalidRegex[] = $line;
                }

                continue;
            }

            // Plain keyword
            $clean[] = sanitize_text_field($line);
        }

        // Save the data to be shown after form submit
        if ( ! empty($errorExcludeUrlKeywordsInvalidRegex) ) {
            $transientValue = array(
                'for'  => 'resource_loading_lazy_load_exclude_url_keyword_invalid_regex_list',
                'list' => $errorExcludeUrlKeywordsInvalidRegex
            );

            MiscArray::addToTransient(SettingsAdmin::$transientNameSubmitErrors, $transientValue, 30);
        }

        return implode("\n", array_unique($clean));
    }
}
