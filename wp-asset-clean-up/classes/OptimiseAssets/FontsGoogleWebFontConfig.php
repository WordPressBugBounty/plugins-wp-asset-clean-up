<?php
namespace WpAssetCleanUp\OptimiseAssets;

/**
 * Rewrite literal Google family lists and stylesheet URLs without evaluating JavaScript.
 * Ambiguous syntax (including template literals) is left unchanged.
 */
class FontsGoogleWebFontConfig
{
    /**
     * Bound work on unusually large inline scripts and optimized bundles.
     */
    const MAX_SOURCE_BYTES = 1048576;

    /**
     *
     */
    const MAX_TOKENS = 50000;

    /**
     * @param string $source
     * @param callable $rewriteFamilies
     *
     * @return string
     */
    public static function rewrite($source, $rewriteFamilies)
    {
        if (strpos($source, 'WebFont') === false || strlen($source) > self::MAX_SOURCE_BYTES) {
            return $source;
        }

        $tokens = self::tokens($source);
        if ($tokens === false) {
            return $source;
        }

        $pairs = array();
        $stack = array();
        $closing = array(')' => '(', ']' => '[', '}' => '{');
        foreach ($tokens as $i => $token) {
            $value = $token[0];
            if (in_array($value, array('(', '[', '{'), true)) {
                $stack[] = $i;
            } elseif (isset($closing[$value])) {
                $open = array_pop($stack);
                if ($open === null || $tokens[$open][0] !== $closing[$value]) {
                    return $source;
                }
                $pairs[$open] = $i;
            }
        }
        if ( ! empty($stack) ) {
            return $source;
        }

        $edits = array();
        $count = count($tokens);
        for ($i = 0; $i < $count; ++$i) {
            $name = $tokens[$i][0];
            if ( ! in_array($name, array('WebFont', 'WebFontConfig'), true) ) {
                continue;
            }
            if ($i > 0 && $tokens[$i - 1][0] === '.') {
                if ($i < 2 || $tokens[$i - 2][0] !== 'window'
                    || ($i > 2 && $tokens[$i - 3][0] === '.') ) {
                    continue;
                }
            }

            $open = null;
            if ($name === 'WebFont' && $i + 4 < $count
                && $tokens[$i + 1][0] === '.' && $tokens[$i + 2][0] === 'load'
                && $tokens[$i + 3][0] === '(' && $tokens[$i + 4][0] === '{') {
                $open = $i + 4;
            } elseif ($name === 'WebFontConfig' && $i + 2 < $count
                && $tokens[$i + 1][0] === '=' && $tokens[$i + 2][0] === '{') {
                $open = $i + 2;
            }
            if ($open === null) {
                continue;
            }
            $after = $pairs[$open] + 1;
            if ($name === 'WebFont' && (! isset($tokens[$after]) || $tokens[$after][0] !== ')'
                || $pairs[$i + 3] !== $after)) {
                continue;
            }
            if ($name === 'WebFontConfig' && isset($tokens[$after]) && $tokens[$after][0] !== ';') {
                continue;
            }

            $google = self::property($tokens, $pairs, $open, 'google');
            if ($google === null || $tokens[$google][0] !== '{') {
                continue;
            }
            $families = self::property($tokens, $pairs, $google, 'families');
            if ($families === null || $tokens[$families][0] !== '[') {
                continue;
            }
            // api/text and other Google options alter the requested URL. Only
            // the plain families provider has the registry identity used here.
            $afterFamilies = $pairs[$families] + 1;
            if ($families !== $google + 3
                || ($afterFamilies !== $pairs[$google]
                    && ! ($tokens[$afterFamilies][0] === ',' && $afterFamilies + 1 === $pairs[$google]))) {
                continue;
            }

            $end    = $pairs[$families];
            $values = array();
            for ($j = $families + 1; $j < $end; $j += 2) {
                $literal = $tokens[$j][0];
                if ( ! in_array(substr($literal, 0, 1), array('"', "'"), true)
                    || strpos($literal, '\\') !== false
                    || ($j + 1 < $end && $tokens[$j + 1][0] !== ',') ) {
                    $values = false;
                    break;
                }
                $values[] = substr($literal, 1, -1);
            }
            if ($values === false || empty($values)) {
                continue;
            }

            $rewritten = call_user_func($rewriteFamilies, $values);
            if ($rewritten === $values) {
                continue;
            }
            if (empty($rewritten)) {
                if ($name === 'WebFontConfig') {
                    // Later scripts may copy, inspect or extend this provider.
                    // Retain the original list: an empty list makes WebFont throw,
                    // while hiding it or inventing another provider changes the
                    // global configuration's public shape and lifecycle.
                    continue;
                }

                // With no providers, Web Font Loader emits loading but never
                // active/inactive. Keep Google unless another supported literal
                // provider remains; do not guess the value of a dynamic one.
                if ( ! self::hasOtherLiteralProvider($tokens, $pairs, $open) ) {
                    continue;
                }

                // The real loader throws for google.families: []. Remove that
                // provider while retaining another provider and event callbacks.
                $first = $google - 2;
                $last  = $pairs[$google];
                if ($last + 1 < $pairs[$open] && $tokens[$last + 1][0] === ',') {
                    ++$last;
                } elseif ($first > $open + 1 && $tokens[$first - 1][0] === ',') {
                    --$first;
                }
                $start = $tokens[$first][1];
                $edits[$start] = array($tokens[$last][1] + strlen($tokens[$last][0]) - $start, '');
                continue;
            }
            $encoded = array();
            foreach ($rewritten as $value) {
                $encoded[] = json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            }
            $start = $tokens[$families][1];
            $edits[$start] = array($tokens[$end][1] + 1 - $start, '[' . implode(', ', $encoded) . ']');
        }

        krsort($edits);
        foreach ($edits as $start => $edit) {
            $source = substr_replace($source, $edit[1], $start, $edit[0]);
        }
        return $source;
    }

    /**
     * Read a unique, direct object property. Reject spreads and computed keys.
     *
     * @param array $tokens
     * @param array $pairs
     * @param int $open
     * @param string $name
     *
     * @return int|null
     */
    private static function property($tokens, $pairs, $open, $name)
    {
        $found = null;
        $end   = $pairs[$open];
        for ($i = $open + 1; $i < $end;) {
            $key = $tokens[$i][0];
            if ( ! preg_match('/^(?:[a-zA-Z_$][a-zA-Z0-9_$]*|"[a-zA-Z_$][a-zA-Z0-9_$]*"|\'[a-zA-Z_$][a-zA-Z0-9_$]*\')$/D', $key)
                || $i + 2 >= $end || $tokens[$i + 1][0] !== ':' ) {
                return null;
            }
            $i += 2;
            if (trim($key, '"\'') === $name) {
                if ($found !== null) {
                    return null;
                }
                $found = $i;
                // The property must be a literal, not an expression starting with one.
                if ( ! isset($pairs[$i]) || ($pairs[$i] + 1 < $end && $tokens[$pairs[$i] + 1][0] !== ',') ) {
                    return null;
                }
            }
            while ($i < $end && $tokens[$i][0] !== ',') {
                $i = isset($pairs[$i]) ? $pairs[$i] + 1 : $i + 1;
            }
            ++$i;
        }
        return $found;
    }

    /**
     * Require a provider understood by the standard loader with a direct object
     * configuration. Dynamic, false or unknown providers cannot prove that the
     * loading lifecycle will still have a module after removing Google.
     *
     * @param array $tokens
     * @param array $pairs
     * @param int $open
     *
     * @return bool
     */
    private static function hasOtherLiteralProvider($tokens, $pairs, $open)
    {
        foreach (array('custom', 'typekit', 'monotype', 'fontdeck') as $provider) {
            $value = self::property($tokens, $pairs, $open, $provider);
            if ($value !== null && $tokens[$value][0] === '{') {
                return true;
            }
        }

        return false;
    }

    /**
     * Retain offsets and string tokens; comments cannot introduce code matches.
     *
     * @param string $source
     *
     * @return array|false
     */
    private static function tokens($source)
    {
        $tokens         = array();
        $parentheses    = array();
        $expressionNext = true;
        $offset         = 0;
        $length         = strlen($source);
        $pattern        = '~\G(?:\s+|//[^\r\n]*|/\*.*?\*/|"(?:\\\\[^\r\n]|[^"\\\\\r\n])*"|\'(?:\\\\[^\r\n]|[^\'\\\\\r\n])*\'|[a-zA-Z_$][a-zA-Z0-9_$]*|[0-9]+|\+\+|--|[{}\[\]().,:;=+*!?<>%&|^\\-])~s';
        while ($offset < $length) {
            if ($source[$offset] === '/' && substr($source, $offset, 2) !== '//'
                && substr($source, $offset, 2) !== '/*') {
                // A closing brace can finish either an object expression or a
                // statement block. Leave that ambiguous slash goal unchanged.
                if ($expressionNext === null) {
                    return false;
                }
                if ($expressionNext) {
                    $end = self::regexEnd($source, $offset);
                    if ($end === false) {
                        return false;
                    }
                    $value = substr($source, $offset, $end - $offset);
                } else {
                    $value = substr($source, $offset, 2) === '/=' ? '/=' : '/';
                }
            } else {
                if ( ! preg_match($pattern, $source, $match, 0, $offset) ) {
                    return false;
                }
                $value = $match[0];
            }
            if ( ! ctype_space($value) && substr($value, 0, 2) !== '//' && substr($value, 0, 2) !== '/*' ) {
                $previous = empty($tokens) ? '' : $tokens[count($tokens) - 1][0];
                $tokens[] = array($value, $offset);
                if (count($tokens) > self::MAX_TOKENS) {
                    return false;
                }
                if ($value === '(') {
                    $parentheses[] = self::isControlParenthesis($tokens, count($tokens) - 1);
                    $expressionNext = true;
                } elseif ($value === ')') {
                    $expressionNext = array_pop($parentheses);
                } elseif ($value === 'default' && $previous === 'export') {
                    // An export default expression can start with a regex.
                    // Keep its body opaque rather than treating / as division.
                    $expressionNext = true;
                } elseif ($value === '}' || ($previous !== '.' && in_array($value, array(
                    'yield', 'await', 'of', 'break', 'continue', 'debugger'
                ), true))) {
                    // Contextual keywords and ASI after a jump need grammar
                    // beyond this bounded lexer to select the slash goal.
                    $expressionNext = null;
                } else {
                    $expressionNext = in_array($value, array(
                        '[', '{', ',', ':', ';', '=', '+', '-', '*', '/', '/=',
                        '!', '?', '<', '>', '%', '&', '|', '^'
                    ), true) || ($previous !== '.' && in_array($value, array(
                        'return', 'throw', 'case', 'delete', 'void', 'typeof',
                        'instanceof', 'in', 'new', 'else', 'do'
                    ), true));
                }
            }
            $offset += strlen($value);
        }
        return $tokens;
    }

    /**
     * Read a regular expression as one opaque token, including escaped slashes
     * and character classes. Nested Unicode sets remain unsupported.
     *
     * @param string $source
     * @param int $offset
     *
     * @return int|false
     */
    private static function regexEnd($source, $offset)
    {
        $length  = strlen($source);
        $inClass = false;
        for ($i = $offset + 1; $i < $length; ++$i) {
            $char = $source[$i];
            if ($char === "\r" || $char === "\n" || substr($source, $i, 2) === "\xe2\x80") {
                return false;
            }
            if ($char === '\\') {
                ++$i;
                if ($i >= $length || $source[$i] === "\r" || $source[$i] === "\n"
                    || substr($source, $i, 2) === "\xe2\x80") {
                    return false;
                }
            } elseif ($char === '[') {
                if ($inClass) {
                    return false;
                }
                $inClass = true;
            } elseif ($char === ']') {
                $inClass = false;
            } elseif ($char === '/' && ! $inClass) {
                ++$i;
                while ($i < $length && preg_match('/[a-zA-Z]/', $source[$i])) {
                    if ($source[$i] === 'v') {
                        return false;
                    }
                    ++$i;
                }
                return $i;
            }
        }
        return false;
    }

    /**
     * Distinguish statement conditions from function calls and grouped values.
     *
     * @param array $tokens
     * @param int $open
     *
     * @return bool
     */
    private static function isControlParenthesis($tokens, $open)
    {
        if ($open > 1 && $tokens[$open - 1][0] === 'await' && $tokens[$open - 2][0] === 'for') {
            return true;
        }

        return $open > 0
            && in_array($tokens[$open - 1][0], array('if', 'while', 'for', 'with', 'switch', 'catch'), true)
            && ($open < 2 || $tokens[$open - 2][0] !== '.');
    }

    /**
     * Rewrite complete literal URLs in standalone loadCSS requests only.
     * A removed request becomes an undefined expression. URL constructors,
     * stored strings, method expressions and return-value consumers are kept
     * intact because their later URL transformations cannot be inferred here.
     * Comments, embedded examples and ambiguous syntax are untouched.
     *
     * @param string $source
     * @param callable $rewriteUrl
     *
     * @return string
     */
    public static function rewriteStylesheetUrls($source, $rewriteUrl)
    {
        if (strlen($source) > self::MAX_SOURCE_BYTES) {
            return $source;
        }

        $tokens = self::tokens($source);
        if ($tokens === false) {
            return $source;
        }

        $expressionContexts = array();
        $controlClosings    = array();
        $stack              = array();
        $closing            = array(')' => '(', ']' => '[', '}' => '{');
        foreach ($tokens as $i => $token) {
            $expressionContexts[$i] = ! empty($stack) && $tokens[end($stack)][0] !== '{';
            if (in_array($token[0], array('(', '[', '{'), true)) {
                $stack[] = $i;
            } elseif (isset($closing[$token[0]])) {
                $open = array_pop($stack);
                if ($open === null || $tokens[$open][0] !== $closing[$token[0]]) {
                    return $source;
                }
                if ($token[0] === ')' && self::isControlParenthesis($tokens, $open)
                    && in_array($tokens[$open - 1][0], array('if', 'while', 'for', 'with'), true)) {
                    $controlClosings[$i] = true;
                }
            }
        }
        if ( ! empty($stack) ) {
            return $source;
        }

        $edits = array();
        foreach ($tokens as $i => $token) {
            $literal = $token[0];
            if ( ! preg_match('~^(["\'])((?:https?:)?//fonts\.googleapis\.com/(?:css2?|icon)\?[^"\'\\\\]+)\1$~iD', $literal, $match) ) {
                continue;
            }

            // A URL literal is not necessarily a complete font request. It may
            // initialize a URL object or a variable that is extended later.
            // Only handle a known loader with one complete literal argument
            // and no consumer of its return value; do not infer string dataflow.
            if ($i < 2 || $tokens[$i - 2][0] !== 'loadCSS' || $tokens[$i - 1][0] !== '(') {
                continue;
            }

            $callStart = $i - 2;
            if ($callStart >= 2 && $tokens[$callStart - 1][0] === '.'
                && $tokens[$callStart - 2][0] === 'window') {
                $callStart -= 2;
            }

            $before = $callStart > 0 ? $tokens[$callStart - 1][0] : '';
            $after  = isset($tokens[$i + 2]) ? $tokens[$i + 2][0] : '';
            if ( ! isset($tokens[$i + 1]) || $tokens[$i + 1][0] !== ')'
                || $expressionContexts[$callStart]
                || ( ! in_array($before, array('', ';', '{', '}', 'else', 'do'), true)
                    && ! isset($controlClosings[$callStart - 1]))
                || ! in_array($after, array('', ';', '}'), true) ) {
                continue;
            }

            $url = call_user_func($rewriteUrl, $match[2]);
            if ($url === $match[2]) {
                continue;
            }

            $start       = $token[1];
            $length      = strlen($literal);
            $replacement = json_encode($url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

            if ($url === '') {
                $start       = $tokens[$callStart][1];
                $length      = $tokens[$i + 1][1] + 1 - $start;
                // A leading parenthesis could continue the preceding expression
                // across a newline instead of allowing automatic semicolon insertion.
                $replacement = 'void 0';
            }

            $edits[$start] = array($length, $replacement);
        }

        krsort($edits);
        foreach ($edits as $start => $edit) {
            $source = substr_replace($source, $edit[1], $start, $edit[0]);
        }

        return $source;
    }
}
