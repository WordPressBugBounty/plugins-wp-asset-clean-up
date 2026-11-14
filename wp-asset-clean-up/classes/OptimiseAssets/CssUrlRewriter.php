<?php

namespace WpAssetCleanUp\OptimiseAssets;

/**
 * Rebase URL tokens without interpreting text inside CSS strings or comments.
 * This is a lexical pass, not a CSS validator or a minifier.
 */
class CssUrlRewriter
{
    /** @return string */
    public static function rebase($css, $baseUrl)
    {
        $length = strlen($css);
        $result = '';
        $functions = array();

        for ($offset = 0; $offset < $length;) {
            $start = $offset;
            $functionIndex = count($functions) - 1;

            if (substr($css, $offset, 2) === '/*') {
                self::skipComment($css, $offset);
            } elseif ($css[$offset] === '"' || $css[$offset] === "'") {
                $value = self::readString($css, $offset);

                // Only the first token of an image-set option can be a string
                // image URL. Strings in type(), other functions or descriptors
                // are literals, even when they contain commas or parentheses.
                if ($functionIndex >= 0 && $functions[$functionIndex]['imageSet']
                    && $functions[$functionIndex]['candidate']) {
                    $functions[$functionIndex]['candidate'] = false;
                    if ($value !== false) {
                        $result .= self::rewriteValue($value, substr($css, $start, $offset - $start), $baseUrl);
                        continue;
                    }
                }
            } elseif (preg_match('/\G@?(?:[a-zA-Z0-9_\x80-\xff-]|\\\\(?:[0-9a-fA-F]{1,6}(?:\r\n|[ \t\r\n\f])?|[^\r\n\f]))+/', $css, $match, 0, $offset)) {
                $name = strtolower(self::decodeEscapes($match[0]));
                $offset += strlen($match[0]);
                if ($functionIndex >= 0) {
                    $functions[$functionIndex]['candidate'] = false;
                }

                if ($name === '@import') {
                    self::skipSpaceAndComments($css, $offset);

                    if ($offset < $length && ($css[$offset] === '"' || $css[$offset] === "'")) {
                        $valueStart = $offset;
                        $value = self::readString($css, $offset);

                        if ($value !== false) {
                            $result .= substr($css, $start, $valueStart - $start)
                                . self::rewriteValue($value, substr($css, $valueStart, $offset - $valueStart), $baseUrl);
                            continue;
                        }
                    }
                } elseif ($name === 'url' && $offset < $length && $css[$offset] === '(') {
                    ++$offset;
                    self::skipSpace($css, $offset);
                    $valueStart = $offset;
                    $value = false;
                    $quoted = false;

                    if ($offset < $length && ($css[$offset] === '"' || $css[$offset] === "'")) {
                        $quoted = true;
                        $value = self::readString($css, $offset);
                    } else {
                        // An escaped closing parenthesis belongs to the URL. An
                        // unescaped quote or parenthesis makes it a bad URL token.
                        if (preg_match('/\G(?:\\\\(?:[0-9a-fA-F]{1,6}(?:\r\n|[ \t\r\n\f])?|[^\r\n\f])|[^\x00-\x20\x7f"\'()\\\\])*/', $css, $url, 0, $offset)) {
                            $value = $url[0];
                            $offset += strlen($value);
                        }
                    }

                    $valueEnd = $offset;
                    if ($quoted) {
                        self::skipSpaceAndComments($css, $offset);
                    } else {
                        self::skipSpace($css, $offset);
                    }

                    if ($value !== false && $offset < $length && $css[$offset] === ')') {
                        ++$offset;
                        $result .= substr($css, $start, $valueStart - $start)
                            . self::rewriteValue($value, substr($css, $valueStart, $valueEnd - $valueStart), $baseUrl)
                            . substr($css, $valueEnd, $offset - $valueEnd);
                        continue;
                    }

                    // Consume the remnants of a bad URL token without treating
                    // any text within it as another URL or an import.
                    while ($offset < $length) {
                        if ($css[$offset++] === ')') {
                            break;
                        }
                        if ($css[$offset - 1] === '\\' && $offset < $length) {
                            ++$offset;
                        }
                    }
                } elseif ($offset < $length && $css[$offset] === '(') {
                    // Track all function boundaries so nested descriptor/image
                    // functions cannot expose their strings as image-set URLs.
                    $functions[] = array('imageSet' => $name === 'image-set' || $name === '-webkit-image-set', 'candidate' => true);
                    ++$offset;
                }
            } else {
                $char = $css[$offset];
                if ($char === '(') {
                    if ($functionIndex >= 0) {
                        $functions[$functionIndex]['candidate'] = false;
                    }
                    $functions[] = array('imageSet' => false, 'candidate' => false);
                } elseif ($char === ')') {
                    array_pop($functions);
                } elseif ($functionIndex >= 0 && strpos(" \t\r\n\f", $char) === false) {
                    $functions[$functionIndex]['candidate'] = $char === ',';
                }
                ++$offset;
            }

            $result .= substr($css, $start, $offset - $start);
        }

        return $result;
    }

    /** Return the raw string contents, or false for an unterminated/bad string. */
    private static function readString($css, &$offset)
    {
        $quote = $css[$offset++];
        $start = $offset;
        $length = strlen($css);

        while ($offset < $length) {
            $char = $css[$offset++];

            if ($char === $quote) {
                return substr($css, $start, $offset - $start - 1);
            }
            if ($char === '\\' && $offset < $length) {
                if (preg_match('/\G[0-9a-fA-F]{1,6}(?:\r\n|[ \t\r\n\f])?/', $css, $escape, 0, $offset)) {
                    $offset += strlen($escape[0]);
                    continue;
                }
                if ($css[$offset] === "\r" && isset($css[$offset + 1]) && $css[$offset + 1] === "\n") {
                    ++$offset;
                }
                ++$offset;
            } elseif ($char === "\n" || $char === "\r" || $char === "\f") {
                return false;
            }
        }

        return false;
    }

    private static function skipSpace($css, &$offset)
    {
        $offset += strspn($css, " \t\r\n\f", $offset);
    }

    private static function skipComment($css, &$offset)
    {
        $end = strpos($css, '*/', $offset + 2);
        $offset = $end === false ? strlen($css) : $end + 2;
    }

    private static function skipSpaceAndComments($css, &$offset)
    {
        do {
            self::skipSpace($css, $offset);
            if (substr($css, $offset, 2) !== '/*') {
                return;
            }
            self::skipComment($css, $offset);
        } while ($offset < strlen($css));
    }

    private static function rewriteValue($value, $originalToken, $baseUrl)
    {
        // Match URL parsing: surrounding ASCII whitespace is not part of the
        // path, even when it originated from an escape in a quoted CSS string.
        $value = trim(self::decodeEscapes($value), "\x00..\x20");

        // A single leading slash uses the original stylesheet's origin. Keep
        // fragments, protocol-relative URLs and all explicit schemes intact.
        if ($value === '' || $value[0] === '#' || strpos($value, '//') === 0
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $value)) {
            return $originalToken;
        }

        $absolute = \WP_Http::make_absolute_url($value, $baseUrl);

        if ( ! is_string($absolute) || $absolute === '') {
            return $originalToken;
        }

        // Older WordPress resolvers omit fragments; newer ones can omit "0".
        // Keep every already-resolved URL unchanged, including an empty fragment.
        $fragmentOffset = strpos($value, '#');
        if ($fragmentOffset !== false && $fragmentOffset < strlen($value) - 1
            && strpos($absolute, '#') === false) {
            $absolute .= substr($value, $fragmentOffset);
        }

        // Always serialize a changed URL as a quoted CSS string.
        return '"' . str_replace(array('\\', '"', "\n", "\r", "\f"), array('\\\\', '\\"', '\\a ', '\\d ', '\\c '), $absolute) . '"';
    }

    private static function decodeEscapes($value)
    {
        return preg_replace_callback('/\\\\(?:([0-9a-fA-F]{1,6})(?:\r\n|[ \t\r\n\f])?|(\r\n|[\n\r\f])|(.))/s', static function ($match) {
            if ($match[1] !== '') {
                $code = hexdec($match[1]);
                if ($code === 0 || $code > 0x10ffff || ($code >= 0xd800 && $code <= 0xdfff)) {
                    return "\xef\xbf\xbd";
                }
                // Avoid requiring mbstring for CSS Unicode escapes.
                if ($code < 0x80) {
                    return chr($code);
                }
                if ($code < 0x800) {
                    return chr(0xc0 | ($code >> 6)) . chr(0x80 | ($code & 0x3f));
                }
                if ($code < 0x10000) {
                    return chr(0xe0 | ($code >> 12)) . chr(0x80 | (($code >> 6) & 0x3f)) . chr(0x80 | ($code & 0x3f));
                }
                return chr(0xf0 | ($code >> 18)) . chr(0x80 | (($code >> 12) & 0x3f))
                    . chr(0x80 | (($code >> 6) & 0x3f)) . chr(0x80 | ($code & 0x3f));
            }

            return isset($match[3]) ? $match[3] : '';
        }, $value);
    }
}
