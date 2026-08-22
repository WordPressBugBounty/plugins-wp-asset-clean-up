<?php
namespace WpAssetCleanUp;

/**
 * Handles rules that can be either:
 * - plain strings, e.g. "contact-form-7" or "/wp-content/plugins/plugin/file.js"
 * - explicit RegEx patterns, e.g. "#/wp-content/plugins/(plugin-a|plugin-b)/#i"
 * - legacy loose RegEx patterns, e.g. "/wd-instagram-feed/(.*?).js"
 *
 * Important:
 * * - "/" is not treated as a RegEx delimiter by default because asset URLs often start with "/wp-content/..."
 * * - For request URI / Plugins Manager rules, "/" can be allowed via $allowSlashDelimiter.
 * * - For new RegEx rules, prefer "#" as delimiter.
 *
 * PHP 5.6 compatible.
 */
class Regex
{
    /**
     * Preferred delimiter when wrapping legacy loose RegEx patterns.
     *
     * @var string
     */
    const DEFAULT_DELIMITER = '#';

    /**
     * Match one rule against a subject.
     *
     * The rule can be:
     * - plain string
     * - explicitly delimited RegEx
     * - legacy loose RegEx
     *
     * @param string $rule
     * @param string $subject
     * @param bool $allowSlashDelimiter
     * @param bool $logInvalidPattern
     *
     * @return bool
     */
    public static function matchesRule($rule, $subject, $allowSlashDelimiter = false, $logInvalidPattern = false)
    {
        $rule    = trim((string)$rule);
        $subject = (string)$subject;

        if ($rule === '' || $subject === '') {
            return false;
        }

        if (self::isDelimitedPattern($rule, $allowSlashDelimiter)) {
            return self::matchesPattern($rule, $subject, $logInvalidPattern);
        }

        if (self::startsLikeExplicitRegex($rule)) {
            if ($logInvalidPattern && function_exists('error_log')) {
                error_log('"' . WPACU_PLUGIN_TITLE . '" / Invalid RegEx: ' . $rule . ' / Error: malformed explicit RegEx rule');
            }

            return false;
        }

        if (self::looksLikeRegex($rule)) {
            $wrappedPattern = self::wrapLoosePattern($rule);

            return self::matchesPattern($wrappedPattern, $subject, $logInvalidPattern);
        }

        return strpos($subject, $rule) !== false;
    }

    /**
     * Match multiple rules against a subject.
     *
     * @param string|array $rules
     * @param string       $subject
     * @param bool         $logInvalidPattern
     *
     * @return bool
     */
    public static function matchesAnyRule($rules, $subject, $allowSlashDelimiter = false, $logInvalidPattern = false)
    {
        foreach (self::splitRules($rules) as $rule) {
            if (self::matchesRule($rule, $subject, $allowSlashDelimiter, $logInvalidPattern)) {
                return $rule;
            }
        }

        return false;
    }

    /**
     * Alias for older code that expects RegEx matching terminology.
     *
     * @param string|array $rules
     * @param string       $subject
     *
     * @return bool
     */
    public static function isRegExMatch($rules, $subject)
    {
        return self::matchesAnyRule($rules, $subject);
    }

    /**
     * Check whether a rule is valid.
     *
     * Plain strings are always valid.
     * Explicit RegEx and legacy loose RegEx must compile successfully.
     *
     * @param string $rule
     * @param bool $allowSlashDelimiter
     *
     * @return bool
     */
    public static function isValidRule($rule, $allowSlashDelimiter = false)
    {
        $rule = trim((string)$rule);

        if ($rule === '') {
            return false;
        }

        if (self::isDelimitedPattern($rule, $allowSlashDelimiter)) {
            return self::isValidPattern($rule);
        }

        // If it starts like an explicit RegEx but failed delimiter validation,
        // do not keep it as a plain string.
        // "/" is intentionally ignored here because it is too ambiguous.
        if (self::startsLikeExplicitRegex($rule)) {
            return false;
        }

        if (self::looksLikeRegex($rule)) {
            return self::isValidPattern(self::wrapLoosePattern($rule));
        }

        return true;
    }

    /**
     * Validate a PCRE pattern.
     *
     * @param string $pattern
     *
     * @return bool
     */
    public static function isValidPattern($pattern)
    {
        $pattern = trim((string)$pattern);

        if ($pattern === '') {
            return false;
        }

        $hasWarning = false;

        set_error_handler(function () use (&$hasWarning) {
            $hasWarning = true;
        });

        $result = preg_match($pattern, '');

        restore_error_handler();

        return ! $hasWarning && $result !== false && preg_last_error() === PREG_NO_ERROR;
    }

    /**
     * @param string $pattern
     * @param string $subject
     * @param bool   $logInvalidPattern
     *
     * @return bool
     */
    public static function matchesPattern($pattern, $subject, $logInvalidPattern = false)
    {
        $pattern = trim((string)$pattern);

        if ($pattern === '') {
            return false;
        }

        $hasWarning   = false;
        $errorMessage = '';
        $errorFile    = '';
        $errorLine    = '';

        set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$hasWarning, &$errorMessage, &$errorFile, &$errorLine) {
            $hasWarning   = true;
            $errorMessage = $errstr;
            $errorFile    = $errfile;
            $errorLine    = $errline;
        });

        $result = preg_match($pattern, (string)$subject);

        restore_error_handler();

        if ($hasWarning || $result === false || preg_last_error() !== PREG_NO_ERROR) {
            if ($logInvalidPattern && function_exists('error_log')) {
                $pregErrorMessage = function_exists('preg_last_error_msg') ? preg_last_error_msg() : 'PREG error code: ' . preg_last_error();

                error_log(
                    '"' . WPACU_PLUGIN_TITLE . '" / Invalid RegEx: ' . $pattern .
                    ' / Error: ' . ($errorMessage !== '' ? $errorMessage : $pregErrorMessage) .
                    ($errorFile !== '' ? ' / File: ' . $errorFile : '') .
                    ($errorLine !== '' ? ' / Line: ' . $errorLine : '')
                );
            }

            return false;
        }

        return $result === 1;
    }

    /**
     * Get the delimiters accepted for explicit RegEx rules.
     *
     * "/" is intentionally excluded because asset URLs often start with "/".
     * Paired delimiters such as "()", "[]", "{}" are intentionally excluded
     * because rules such as "(plugin-a|plugin-b)" should be treated as legacy loose RegEx.
     *
     * @param bool $allowSlashDelimiter
     *
     * @return array
     */
    protected static function getAllowedExplicitDelimiters($allowSlashDelimiter = false)
    {
        $delimiters = array('#', '~', '@', '!', '%');

        if ($allowSlashDelimiter) {
            $delimiters[] = '/';
        }

        return $delimiters;
    }

    /**
     * Detect rules that appear to start as explicit RegEx patterns,
     * but might be malformed.
     *
     * Example:
     * - #valid#
     * - #invalid#123
     * - ~something~
     *
     * "/" is intentionally ignored because asset URLs often start with "/".
     *
     * @param string $value
     *
     * @return bool
     */
    public static function startsLikeExplicitRegex($value)
    {
        $value = trim((string)$value);

        if ($value === '') {
            return false;
        }

        $delimiter = substr($value, 0, 1);

        // Never treat "/" as a malformed explicit RegEx starter.
        // It is too ambiguous because request URIs and asset URLs commonly start with "/".
        if ($delimiter === '/') {
            return false;
        }

        return in_array($delimiter, self::getAllowedExplicitDelimiters(), true);
    }

    /**
     * Detect explicit PCRE delimiters.
     *
     * Important:
     * * "/" is intentionally not accepted as a delimiter by default because normal asset URLs
     * * often start with "/wp-content/...". It can be allowed via $allowSlashDelimiter
     * * for request URI / Plugins Manager rules.
     *
     * Examples considered RegEx:
     * - #something#i
     * - ~something~i
     * - @something@
     * - !something!
     *
     * Examples considered plain strings:
     * - /wp-content/plugins/plugin/file.js
     * - /contact-form-7/
     *
     * @param string $value
     * @param bool   $allowSlashDelimiter
     *
     * @return bool
     */
    public static function isDelimitedPattern($value, $allowSlashDelimiter = false)
    {
        $value = trim((string)$value);

        if (strlen($value) < 3) {
            return false;
        }

        $delimiter = substr($value, 0, 1);

        if (! in_array($delimiter, self::getAllowedExplicitDelimiters($allowSlashDelimiter), true)) {
            return false;
        }

        $closingDelimiterPosition = self::findClosingDelimiterPosition($value, $delimiter);

        if ($closingDelimiterPosition === false || $closingDelimiterPosition < 2) {
            return false;
        }

        $modifiers = substr($value, $closingDelimiterPosition + 1);

        if ($modifiers !== '' && preg_match('/^[a-zA-Z]*$/', $modifiers) !== 1) {
            return false;
        }

        return self::isValidPattern($value);
    }

    /**
     * Detect legacy loose RegEx rules.
     *
     * Do NOT treat dots alone as RegEx because many asset URLs end in ".js" or ".css".
     *
     * Examples returning true:
     * - /wd-instagram-feed/(.*?).js
     * - wp-content/plugins/(plugin-a|plugin-b)
     * - ^/wp-content/
     * - \.min\.js$
     *
     * Examples returning false:
     * - /wp-content/plugins/plugin/file.js
     * - contact-form-7
     *
     * @param string $value
     *
     * @return bool
     */
    public static function looksLikeRegex($value)
    {
        $value = trim((string)$value);

        if ($value === '') {
            return false;
        }

        // Strong RegEx indicators.
        // Do not include "?", "*" or "+" here because they can appear in URLs/query strings
        // or filenames and should not automatically convert a plain string into RegEx.
        if (preg_match('/[\[\]\(\)\{\}\|\^\$]/', $value) === 1) {
            return true;
        }

        // Common wildcard-style RegEx sequences.
        if (strpos($value, '.*') !== false || strpos($value, '.+') !== false || strpos($value, '.?') !== false) {
            return true;
        }

        // Escaped RegEx tokens such as \.js, \d, \w, \s, etc.
        if (preg_match('/\\\\[.dDsSwWbBAZzGQE]/', $value) === 1) {
            return true;
        }

        return false;
    }

    /**
     * Wrap a legacy loose RegEx pattern using the default delimiter.
     *
     * @param string $pattern
     *
     * @return string
     */
    public static function wrapLoosePattern($pattern)
    {
        $pattern = trim((string)$pattern);

        if ($pattern === '') {
            return '';
        }

        return self::DEFAULT_DELIMITER . self::escapeDelimiter($pattern, self::DEFAULT_DELIMITER) . self::DEFAULT_DELIMITER;
    }

    /**
     * Split textarea/string/array rules into clean non-empty rows.
     *
     * @param string|array $rules
     *
     * @return array
     */
    public static function splitRules($rules)
    {
        if (is_array($rules)) {
            $rows = $rules;
        } else {
            $rules = str_replace(array("\r\n", "\r"), "\n", (string)$rules);
            $rows  = explode("\n", $rules);
        }

        $cleanRules = array();

        foreach ($rows as $row) {
            $row = trim((string)$row);

            if ($row !== '') {
                $cleanRules[] = $row;
            }
        }

        return $cleanRules;
    }

    /**
     * Purify textarea rules.
     *
     * Keeps:
     * - plain strings
     * - valid explicit RegEx rules
     * - valid legacy loose RegEx rules
     *
     * Removes:
     * - invalid explicit RegEx rules
     * - invalid legacy loose RegEx rules
     *
     * It does not rewrite valid loose legacy rules for storage.
     *
     * @param string $textareaValue
     * @param bool $allowSlashDelimiter
     *
     * @return string
     */
    public static function purifyTextareaRules($textareaValue, $allowSlashDelimiter = false)
    {
        $validRules = array();

        foreach (self::splitRules($textareaValue) as $rule) {
            if (self::isValidRule($rule, $allowSlashDelimiter)) {
                $validRules[] = trim($rule);
            }
        }

        return implode("\n", $validRules);
    }

    /**
     * Backward-compatible alias for old naming.
     *
     * @param string $textareaValue
     * @param bool $allowSlashDelimiter
     *
     * @return string
     */
    public static function purifyTextareaRegexValue($textareaValue, $allowSlashDelimiter = false)
    {
        return self::purifyTextareaRules($textareaValue, $allowSlashDelimiter);
    }

    /**
     * Find the real closing delimiter, ignoring escaped delimiters.
     *
     * @param string $pattern
     * @param string $delimiter
     *
     * @return int|false
     */
    protected static function findClosingDelimiterPosition($pattern, $delimiter)
    {
        $length = strlen($pattern);

        for ($i = $length - 1; $i > 0; $i--) {
            if ($pattern[$i] !== $delimiter) {
                continue;
            }

            if (! self::isEscapedPosition($pattern, $i)) {
                return $i;
            }
        }

        return false;
    }

    /**
     * Check whether a character at a given offset is escaped.
     *
     * @param string $string
     * @param int    $position
     *
     * @return bool
     */
    protected static function isEscapedPosition($string, $position)
    {
        $backslashes = 0;

        for ($i = $position - 1; $i >= 0; $i--) {
            if ($string[$i] !== '\\') {
                break;
            }

            $backslashes++;
        }

        return ($backslashes % 2) === 1;
    }

    /**
     * Escape the delimiter when wrapping a loose pattern.
     *
     * @param string $pattern
     * @param string $delimiter
     *
     * @return string
     */
    protected static function escapeDelimiter($pattern, $delimiter)
    {
        return str_replace($delimiter, '\\' . $delimiter, $pattern);
    }
}
