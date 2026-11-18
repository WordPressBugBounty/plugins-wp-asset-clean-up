<?php
namespace WpAssetCleanUp\OptimiseAssets;

/**
 * Remove cosmetic optimization markers without reinterpreting page content.
 */
class HtmlCleanup
{
    /**
     * Locate active BODY scripts without serializing the document. Offsets refer
     * to the original bytes. Unsupported markup cancels the complete operation.
     *
     * @param string $html
     *
     * @return array|false
     */
    public static function getBodyScriptTokens($html)
    {
        return self::scanScriptTokens($html, true);
    }

    /**
     * Locate active scripts in HEAD, BODY or an HTML fragment. Template and
     * raw-text contents remain excluded, without changing the original bytes.
     * Unsupported or incomplete regions stop this local rewrite scan; scripts
     * already located safely remain eligible without inferring execution order.
     *
     * @param string $html
     *
     * @return array|false
     */
    public static function getScriptTokens($html)
    {
        return self::scanScriptTokens($html, false);
    }

    /**
     * Locate HTML LINK and STYLE elements outside templates and raw text.
     * Preserve source offsets and exact attribute ranges for local CSS edits.
     *
     * @param string $html
     *
     * @return array|false
     */
    public static function getStyleTokens($html)
    {
        return self::scanScriptTokens($html, false, true);
    }

    /**
     * @param string $html
     * @param bool $bodyOnly Require a complete BODY for execution-order analysis.
     * @param bool $stylesOnly Collect HTML LINK/STYLE elements instead of scripts.
     *
     * @return array|false
     */
    private static function scanScriptTokens($html, $bodyOnly, $stylesOnly = false)
    {
        if (strpos($html, "\0") !== false || preg_match('~^scriptbody$~i', 'SCRIPTBODY') !== 1) {
            return false;
        }

        $space     = '[\x09\x0a\x0c\x0d\x20]';
        $attribute = '[^\x00\x09\x0a\x0c\x0d\x20\x22\x27<>/=]++(?:' . $space . '*=' . $space . '*(?:"[^"\x00]*"|\x27[^\x27\x00]*\x27|[^\x00\x09\x0a\x0c\x0d\x20\x22\x27`=<>]++))?';
        if ( ! $bodyOnly ) {
            // HTML retains = in an unquoted value, including URL query strings.
            // Execution-order analysis keeps its existing strict grammar.
            $attribute = str_replace('`=<>', '`<>', $attribute);
        }
        $ending    = '(?:' . $space . '++' . $attribute . ')*+' . $space . '*/?>';
        $special   = 'body|template|span|script|style|textarea|title|iframe|xmp|noembed|noframes|noscript|plaintext|select|svg|math|frameset';
        if ($stylesOnly) {
            $special .= '|link';
        }
        $wanted = $stylesOnly ? array('LINK', 'STYLE', 'SPAN') : array('SCRIPT', 'SPAN');
        // Skip complete ordinary tags inside PCRE instead of entering PHP for
        // every layout element. Incomplete tokens still reach the conservative checks.
        $pattern   = '~</?(?!(?:' . $special . ')(?=[\x09\x0a\x0c\x0d\x20/>]))[a-z][a-z0-9:_-]*' . $ending . '(*SKIP)(*F)|<~i';
        $offset    = 0;
        $body      = 0;
        $templates = 0;
        $manager   = false;
        $scripts   = array();

        while (($status = preg_match($pattern, $html, $token, PREG_OFFSET_CAPTURE, $offset)) === 1) {
            $start = $token[0][1];
            if (substr($html, $start, 4) === '<!--') {
                if ( ! preg_match('~\G<!--(?:-?>|[\s\S]*?(?:-->|--!>))~', $html, $comment, 0, $start) ) {
                    return $bodyOnly || preg_last_error() !== PREG_NO_ERROR ? false : $scripts;
                }

                $offset = $start + strlen($comment[0]);
                continue;
            }

            // HTML processing instructions and unknown declarations are bogus
            // comments ending at the first >, including an XML prefix in HTML.
            if ( ! $bodyOnly && (substr($html, $start, 2) === '<?'
                || (substr($html, $start, 2) === '<!' && stripos(substr($html, $start, 9), '<!doctype') !== 0)) ) {
                $end = strpos($html, '>', $start + 2);
                if ($end === false) {
                    return $scripts;
                }
                $offset = $end + 1;
                continue;
            }

            $quoted = '(?:"[^"\x00]*"|\x27[^\x27\x00]*\x27)';
            if (preg_match('~\G<!doctype' . $space . '+html(?:' . $space . '+(?:public' . $space . '+' . $quoted . '(?:' . $space . '+' . $quoted . ')?|system' . $space . '+' . $quoted . '))?' . $space . '*>~i', $html, $doctype, 0, $start)) {
                $offset = $start + strlen($doctype[0]);
                continue;
            }

            $malformed = false;
            if ( ! preg_match('~\G<(/?)([a-z][a-z0-9:_-]*)' . $ending . '~i', $html, $tag, 0, $start) ) {
                if ( ! isset($html[$start + 1]) || ! preg_match('~[a-z!?/]~i', $html[$start + 1]) ) {
                    $offset = $start + 1;
                    continue;
                }

                // Follow HTML attribute states to locate a complete token even
                // when whitespace is missing or quotes occur in an unquoted
                // value. Never search for scripts inside an unfinished value.
                if ($bodyOnly || preg_last_error() !== PREG_NO_ERROR
                    || ($tag = self::readRecoverableTag($html, $start)) === false) {
                    return $bodyOnly || preg_last_error() !== PREG_NO_ERROR ? false : $scripts;
                }
                $malformed = true;
            }

            $name    = strtr($tag[2], 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
            $closing = $tag[1] === '/';
            $offset  = $start + strlen($tag[0]);

            if ($malformed && $name === 'SPAN'
                && stripos($tag[0], 'data-wpacu-delimiter-frontend-form-') !== false) {
                // Its attributes can delimit the manager's excluded content.
                // Recovery locates boundaries, but does not decode attributes.
                return $scripts;
            }

            if ($name === 'TEMPLATE') {
                $templates += $closing ? -1 : 1;
                if ( ! $bodyOnly && $templates < 0 ) {
                    $templates = 0;
                }
                if ($templates < 0) {
                    return $bodyOnly || preg_last_error() !== PREG_NO_ERROR ? false : $scripts;
                }
                continue;
            }

            if ($bodyOnly && $name === 'BODY' && $templates === 0) {
                if (($closing && $body !== 1) || ( ! $closing && $body !== 0)) {
                    return $bodyOnly || preg_last_error() !== PREG_NO_ERROR ? false : $scripts;
                }
                $body = $closing ? 2 : 1;
                continue;
            }

            if ($closing) {
                continue;
            }

            if ( ! $bodyOnly && in_array($name, array('SVG', 'MATH'), true) ) {
                $region = self::scanForeignRegion($html, $start, $ending, $attribute, $manager, $stylesOnly);
                if ($region === false) {
                    return preg_last_error() !== PREG_NO_ERROR ? false : $scripts;
                }
                $offset = $region['offset'];
                if ($templates === 0) {
                    $manager = $region['manager'];
                    foreach ($region['scripts'] as $script) {
                        $scripts[] = $script;
                    }
                }
                continue;
            }

            if (in_array($name, array('MATH', 'FRAMESET', 'PLAINTEXT'), true)) {
                return $bodyOnly || preg_last_error() !== PREG_NO_ERROR ? false : $scripts;
            }

            if ($name === 'SVG') {
                $offset = self::skipNeutralRegion($html, $start, $name, $ending, true);
                if ($offset === false) {
                    return $bodyOnly || preg_last_error() !== PREG_NO_ERROR ? false : $scripts;
                }
                continue;
            }

            if (($bodyOnly || $stylesOnly) && $name === 'SELECT') {
                $select = '~\G(?:[^<]*+(?:</?(?:option|optgroup)' . $ending . '|<hr' . $ending . '))*+[^<]*+</select' . $space . '*>~i';
                if ( ! preg_match($select, $html, $contents, 0, $offset) ) {
                    return $bodyOnly || preg_last_error() !== PREG_NO_ERROR ? false : $scripts;
                }
                $offset += strlen($contents[0]);
                continue;
            }

            $contentStart = $offset;
            $closeAt      = $offset;
            if (in_array($name, array('SCRIPT', 'STYLE', 'TEXTAREA', 'TITLE', 'IFRAME', 'XMP', 'NOEMBED', 'NOFRAMES', 'NOSCRIPT'), true)) {
                $closeAt = $name === 'SCRIPT' ? self::findScriptCloser($html, $offset) : false;
                if ($name !== 'SCRIPT' && preg_match('~</' . $name . '(?=[\x09\x0a\x0c\x0d\x20/>])~i', $html, $close, PREG_OFFSET_CAPTURE, $offset) === 1) {
                    $closeAt = $close[0][1];
                }
                if ($closeAt === false || ! preg_match('~\G</' . $name . $ending . '~i', $html, $closer, 0, $closeAt)) {
                    return $bodyOnly || preg_last_error() !== PREG_NO_ERROR ? false : $scripts;
                }
                $offset = $closeAt + strlen($closer[0]);
            }

            if ($malformed || $templates > 0 || ! in_array($name, $wanted, true)) {
                continue;
            }
            if ($body !== 1) {
                // A parser can move scripts after </body> back into BODY. Do not
                // infer an execution order from a noncanonical document.
                if ($body === 2 && $name === 'SCRIPT') {
                    return $bodyOnly || preg_last_error() !== PREG_NO_ERROR ? false : $scripts;
                }
                if ($bodyOnly) {
                    continue;
                }
            }

            $attributes = array();
            $ranges     = array();
            if (preg_match_all('~' . $space . '++(' . $attribute . ')~', substr($tag[0], 1 + strlen($tag[2])), $matches, PREG_OFFSET_CAPTURE) === false) {
                return $bodyOnly || preg_last_error() !== PREG_NO_ERROR ? false : $scripts;
            }
            foreach ($matches[1] as $index => $match) {
                $raw = $match[0];
                preg_match('~^[^\x09\x0a\x0c\x0d\x20=]+~', $raw, $key);
                $key   = strtr($key[0], 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
                $equal = strpos($raw, '=');
                $value = $equal === false ? '' : trim(substr($raw, $equal + 1), "\x09\x0a\x0c\x0d\x20");
                if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                    $value = substr($value, 1, -1);
                }
                if ( ! array_key_exists($key, $attributes) ) {
                    $attributes[$key] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                $ranges[] = array('name' => $key, 'offset' => 1 + strlen($tag[2]) + $matches[0][$index][1], 'length' => strlen($matches[0][$index][0]));
            }

            if ($name === 'SPAN') {
                if (isset($attributes['data-wpacu-delimiter-frontend-form-start'])) {
                    $manager = true;
                }
                if (isset($attributes['data-wpacu-delimiter-frontend-form-end'])) {
                    $manager = false;
                }
                continue;
            }

            // PHP's entity decoder does not implement every HTML attribute
            // character reference (e.g. semicolonless numbers or encoded CR).
            // An undecoded type might execute in the browser: never overlook
            // that inline dependency boundary when deciding BODY defer order.
            if ($bodyOnly && isset($attributes['type']) && strpos($attributes['type'], '&') !== false) {
                return false;
            }

            $scripts[] = array('offset' => $start, 'length' => $offset - $start,
                'opening' => $tag[0], 'content' => substr($html, $contentStart, $closeAt - $contentStart),
                'attributes' => $attributes, 'attribute_ranges' => $ranges, 'manager' => $manager);
            if ($stylesOnly) {
                $scripts[count($scripts) - 1]['tag_name'] = $name;
            }
        }

        if ( ! $bodyOnly ) {
            return $status !== false ? $scripts : false;
        }

        return $status !== false && ($body === 2 || ( ! $bodyOnly && $body === 0))
            && $templates === 0 && ! $manager ? $scripts : false;
    }

    /**
     * Scan foreign content and its HTML integration points for local
     * rewrites only. Namespace changes, CDATA and HTML raw text are tokenized;
     * recoverable foreign end tags may close unclosed foreign descendants.
     * Repairs crossing HTML integration points stop the region. Pending scripts
     * are returned only after its boundary or a safe HTML breakout is known.
     *
     * @param string $html
     * @param int $start
     * @param string $ending
     * @param string $attribute
     * @param bool $manager
     * @param bool $stylesOnly Collect HTML LINK/STYLE elements instead of scripts.
     *
     * @return array|false
     */
    private static function scanForeignRegion($html, $start, $ending, $attribute, $manager, $stylesOnly = false)
    {
        $pattern   = '~\G<(/?)([a-z][a-z0-9:_-]*)' . str_replace('*/?>', '*(/?)>', $ending) . '~i';
        $space     = '[\x09\x0a\x0c\x0d\x20]';
        $breakout  = array('b', 'big', 'blockquote', 'body', 'br', 'center', 'code', 'dd', 'div', 'dl', 'dt',
            'em', 'embed', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'hr', 'i', 'img', 'li', 'listing',
            'menu', 'meta', 'nobr', 'ol', 'p', 'pre', 'ruby', 's', 'small', 'span', 'strong', 'strike',
            'sub', 'sup', 'table', 'tt', 'u', 'ul', 'var', 'font');
        $void      = array('area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta',
            'param', 'source', 'track', 'wbr');
        $rawText   = array('script', 'style', 'textarea', 'title', 'iframe', 'xmp', 'noembed', 'noframes', 'noscript');
        $stack     = array();
        $scripts   = array();
        $templates = 0;
        $offset    = $start;

        while ($offset !== false) {
            $parent = $stack ? end($stack) : array('name' => '', 'namespace' => 'html', 'integration' => false);

            if (substr($html, $offset, 9) === '<![CDATA[' && $parent['namespace'] !== 'html') {
                $end = strpos($html, ']]>', $offset + 9);
                if ($end === false) {
                    return false;
                }
                $offset = strpos($html, '<', $end + 3);
                continue;
            }

            if (substr($html, $offset, 4) === '<!--') {
                if ( ! preg_match('~\G<!--(?:-?>|[\s\S]*?(?:-->|--!>))~', $html, $comment, 0, $offset) ) {
                    return false;
                }
                $offset = strpos($html, '<', $offset + strlen($comment[0]));
                continue;
            }

            if (strncasecmp(substr($html, $offset, 9), '<!doctype', 9) === 0) {
                $quoted = '(?:"[^"\x00]*"|\x27[^\x27\x00]*\x27)';
                if ( ! preg_match('~\G<!doctype' . $space . '+html(?:' . $space . '+(?:public' . $space . '+' . $quoted . '(?:' . $space . '+' . $quoted . ')?|system' . $space . '+' . $quoted . '))?' . $space . '*>~i', $html, $doctype, 0, $offset) ) {
                    return false;
                }
                $offset = strpos($html, '<', $offset + strlen($doctype[0]));
                continue;
            }

            if (substr($html, $offset, 2) === '<?' || substr($html, $offset, 2) === '<!') {
                $end = strpos($html, '>', $offset + 2);
                if ($end === false) {
                    return false;
                }
                $offset = strpos($html, '<', $end + 1);
                continue;
            }

            if ( ! preg_match($pattern, $html, $tag, 0, $offset) ) {
                return false;
            }

            $name      = strtr($tag[2], 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
            $next      = $offset + strlen($tag[0]);
            $namespace = $parent['namespace'];

            if ($tag[1] === '/') {
                $unclosed = $parent;
                while ($stack && $unclosed['name'] !== $name) {
                    // A foreign end tag closes matching foreign ancestors, as
                    // in <svg><path></svg>. Do not cross an HTML integration
                    // point or infer repairs to HTML's open-element stack.
                    if ($unclosed['namespace'] === 'html' || $unclosed['integration']) {
                        return false;
                    }
                    array_pop($stack);
                    $unclosed = $stack ? end($stack) : false;
                }
                if ( ! $stack || ($unclosed['namespace'] === 'html' && $parent['name'] !== $name) ) {
                    return false;
                }
                array_pop($stack);
                if ($namespace === 'html' && $name === 'template') {
                    --$templates;
                }
            } else {
                if ($parent['integration'] === true
                    || ($parent['integration'] === 'math-text' && ! in_array($name, array('mglyph', 'malignmark'), true))) {
                    $namespace = 'html';
                }
                if ($namespace === 'html' && in_array($name, array('svg', 'math'), true)) {
                    $namespace = $name;
                } elseif ($namespace === 'math' && $parent['name'] === 'annotation-xml' && $name === 'svg') {
                    $namespace = 'svg';
                } elseif ($namespace !== 'html' && in_array($name, $breakout, true)) {
                    // FONT breaks out only with color/face/size attributes.
                    // Keep its existing conservative handling instead of
                    // treating every foreign FONT as an HTML element.
                    if ($name === 'font') {
                        return false;
                    }
                    // Reprocess the breakout as HTML only when all ancestors
                    // belong to foreign content. Integration points require
                    // their own HTML stack and remain conservative.
                    foreach ($stack as $ancestor) {
                        if ($ancestor['namespace'] === 'html' || $ancestor['integration']) {
                            return false;
                        }
                    }
                    return array('offset' => $offset, 'scripts' => $scripts, 'manager' => $manager);
                }

                if ($namespace === 'html' && in_array($name, array('plaintext', 'select', 'table', 'frameset', 'html', 'head', 'body'), true)) {
                    return false;
                }

                $attributes = array();
                if (in_array($name, array('annotation-xml', 'span'), true)) {
                    if (preg_match_all('~' . $space . '++(' . $attribute . ')~', substr($tag[0], 1 + strlen($tag[2])), $matches) === false) {
                        return false;
                    }
                    foreach ($matches[1] as $raw) {
                        preg_match('~^[^\x09\x0a\x0c\x0d\x20=]+~', $raw, $key);
                        $key   = strtr($key[0], 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
                        $equal = strpos($raw, '=');
                        $value = $equal === false ? '' : trim(substr($raw, $equal + 1), "\x09\x0a\x0c\x0d\x20");
                        if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                            $value = substr($value, 1, -1);
                        }
                        if ( ! array_key_exists($key, $attributes) ) {
                            $attributes[$key] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        }
                    }
                }

                $integration = ($namespace === 'svg' && in_array($name, array('foreignobject', 'title', 'desc'), true))
                    || ($namespace === 'math' && $name === 'annotation-xml' && isset($attributes['encoding'])
                        && in_array(strtr($attributes['encoding'], 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), array('text/html', 'application/xhtml+xml'), true));
                if ($namespace === 'math' && in_array($name, array('mi', 'mo', 'mn', 'ms', 'mtext'), true)) {
                    $integration = 'math-text';
                }

                if ($namespace === 'html' && $name === 'span' && $templates === 0) {
                    if (isset($attributes['data-wpacu-delimiter-frontend-form-start'])) {
                        $manager = true;
                    }
                    if (isset($attributes['data-wpacu-delimiter-frontend-form-end'])) {
                        $manager = false;
                    }
                }

                if ($namespace === 'html' && in_array($name, $rawText, true)) {
                    $closeAt = $name === 'script' ? self::findScriptCloser($html, $next) : false;
                    if ($name !== 'script' && preg_match('~</' . $name . '(?=[\x09\x0a\x0c\x0d\x20/>])~i', $html, $close, PREG_OFFSET_CAPTURE, $next) === 1) {
                        $closeAt = $close[0][1];
                    }
                    if ($closeAt === false || ! preg_match('~\G</' . $name . $ending . '~i', $html, $closer, 0, $closeAt)) {
                        return false;
                    }
                    $next = $closeAt + strlen($closer[0]);
                } elseif (($namespace !== 'html' && $tag[3] !== '/') || ($namespace === 'html' && ! in_array($name, $void, true))) {
                    // Bound memory for adversarially deep foreign markup.
                    if (count($stack) >= 256) {
                        return false;
                    }
                    $stack[] = array('name' => $name, 'namespace' => $namespace, 'integration' => $integration);
                    if ($namespace === 'html' && $name === 'template') {
                        ++$templates;
                    }
                }

                if ($namespace === 'html' && $templates === 0
                    && ($stylesOnly ? in_array($name, array('link', 'style'), true) : $name === 'script')) {
                    $found = self::scanScriptTokens(substr($html, $offset, $next - $offset), false, $stylesOnly);
                    if ( ! is_array($found) || count($found) !== 1 ) {
                        return false;
                    }
                    $found[0]['offset'] += $offset;
                    $found[0]['manager'] = $manager;
                    $scripts[]          = $found[0];
                }
            }

            if ( ! $stack ) {
                return array('offset' => $next, 'scripts' => $scripts, 'manager' => $manager);
            }
            $offset = strpos($html, '<', $next);
        }

        return false;
    }

    /**
     * Locate a malformed but complete HTML tag using attribute tokenizer states.
     * Returned attributes are deliberately not used for rewriting this tag.
     *
     * @param string $html
     * @param int $start
     *
     * @return array|false
     */
    private static function readRecoverableTag($html, $start)
    {
        $length = strlen($html);
        $offset = $start + 1;
        $close  = isset($html[$offset]) && $html[$offset] === '/' ? '/' : '';
        $offset += strlen($close);

        if ( $offset >= $length || ! preg_match('~[a-z]~i', $html[$offset]) ) {
            return false;
        }

        $space = "\x09\x0a\x0c\x0d\x20";
        $size  = strcspn($html, $space . '/>', $offset);
        $name  = substr($html, $offset, $size);
        $offset += $size;

        while ($offset < $length) {
            $offset += strspn($html, $space . '/', $offset);
            if ($offset >= $length) {
                return false;
            }
            if ($html[$offset] === '>') {
                return array(substr($html, $start, $offset + 1 - $start), $close, $name);
            }

            // In an attribute name, quotes are ordinary error characters. An
            // initial = also belongs to the name, rather than opening a value.
            if ($html[$offset] === '=') {
                ++$offset;
            }
            $offset += strcspn($html, $space . '/>=', $offset);
            $offset += strspn($html, $space, $offset);
            if ($offset >= $length) {
                return false;
            }
            if ($html[$offset] !== '=') {
                continue;
            }

            ++$offset;
            $offset += strspn($html, $space, $offset);
            if ($offset >= $length) {
                return false;
            }
            if ($html[$offset] === '"' || $html[$offset] === "'") {
                $end = strpos($html, $html[$offset], $offset + 1);
                if ($end === false) {
                    return false;
                }
                $offset = $end + 1;
            } else {
                // Quotes, <, = and / do not end an unquoted HTML value.
                $offset += strcspn($html, $space . '>', $offset);
            }
        }

        return false;
    }

    /**
     * @param string $htmlSource
     * @param string $assetType 'css', 'js', or empty for both in one pass.
     *
     * @return string
     */
    public static function removeTemporaryAssetAttributes($htmlSource, $assetType = '')
    {
        if (stripos($htmlSource, 'data-wpacu-') === false) {
            return $htmlSource;
        }

        $attributesByTag = array('LINK' => array());

        if ($assetType !== 'js') {
            $attributesByTag['LINK'] = array('data-wpacu-link-rel-href-before', 'data-wpacu-style-handle');
        }

        if ($assetType !== 'css') {
            $attributesByTag['SCRIPT'] = array(
                'data-wpacu-script-rel-src-before',
                'data-wpacu-script-handle',
                'data-wpacu-jquery-core-handle',
                'data-wpacu-jquery-migrate-handle'
            );
            $attributesByTag['LINK'][] = 'data-wpacu-script-rel-src-before';
        }

        return self::removeTemporaryAttributes($htmlSource, $attributesByTag);
    }

    /**
     * Scan a conservative HTML subset without rebuilding or serializing the DOM.
     * Ordinary tags are skipped inside PCRE; PHP handles only context boundaries
     * and asset tags. Unsupported syntax or a PCRE error discards all pending edits.
     * This is cosmetic cleanup, not an HTML validator or sanitizer.
     *
     * @param string $htmlSource
     * @param array $attributesByTag Uppercase LINK/SCRIPT names mapped to exact attributes.
     *
     * @return string
     */
    public static function removeTemporaryAttributes($htmlSource, $attributesByTag)
    {
        $found = false;

        foreach ($attributesByTag as $names) {
            foreach ($names as $name) {
                if ($name !== '' && stripos($htmlSource, $name) !== false) {
                    $found = true;
                    break 2;
                }
            }
        }

        // HTML uses ASCII case folding, independently of the host's LC_CTYPE.
        if ( ! $found || strpos($htmlSource, "\0") !== false || preg_match('~^scriptlink$~i', 'SCRIPTLINK') !== 1 ) {
            return $htmlSource;
        }

        $space     = '[\x09\x0a\x0c\x0d\x20]';
        $attribute = '[^\x00\x09\x0a\x0c\x0d\x20\x22\x27<>/=]++(?:' . $space . '*=' . $space . '*(?:"[^"\x00]*"|\x27[^\x27\x00]*\x27|[^\x00\x09\x0a\x0c\x0d\x20\x22\x27`=<>]++))?';
        // Complete attributes cannot overlap. Possessive repetition also avoids
        // one native recursion frame per attribute on PHP 5.6's PCRE1 engine.
        $ending    = '(?:' . $space . '++' . $attribute . ')*+' . $space . '*/?>';
        $special   = 'link|script|style|textarea|title|iframe|xmp|noembed|noframes|noscript|plaintext|select|svg|math|frameset';
        // The final < alternative exposes incomplete/unsupported tokens instead
        // of searching inside their attributes for something that resembles a tag.
        $pattern   = '~</?(?!(?:' . $special . ')(?=[\x09\x0a\x0c\x0d\x20/>]))[a-z][a-z0-9:_-]*' . $ending . '(*SKIP)(*F)|<~i';
        $offset    = 0;
        $copied    = 0;
        $parts     = array();

        while (($status = preg_match($pattern, $htmlSource, $match, PREG_OFFSET_CAPTURE, $offset)) === 1) {
            $start = $match[0][1];

            if (substr($htmlSource, $start, 4) === '<!--') {
                if ( ! preg_match('~\G<!--(?:-?>|[\s\S]*?(?:-->|--!>))~', $htmlSource, $comment, 0, $start) ) {
                    return $htmlSource;
                }

                $offset = $start + strlen($comment[0]);
                continue;
            }

            $quoted = '(?:"[^"\x00]*"|\x27[^\x27\x00]*\x27)';

            if (preg_match('~\G<!doctype' . $space . '+html(?:' . $space . '+(?:public' . $space . '+' . $quoted . '(?:' . $space . '+' . $quoted . ')?|system' . $space . '+' . $quoted . '))?' . $space . '*>~i', $htmlSource, $doctype, 0, $start)) {
                $offset = $start + strlen($doctype[0]);
                continue;
            }

            if ( ! preg_match('~\G<(/?)([a-z][a-z0-9:_-]*)' . $ending . '~i', $htmlSource, $tag, 0, $start) ) {
                if ( ! isset($htmlSource[$start + 1]) || ! preg_match('~[a-z!?/]~i', $htmlSource[$start + 1]) ) {
                    $offset = $start + 1;
                    continue;
                }

                // Incomplete/unsupported tokens must not expose quoted markup to cleanup.
                return $htmlSource;
            }

            $name   = strtr($tag[2], 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
            $offset = $start + strlen($tag[0]);

            if ($tag[1] === '/') {
                continue;
            }

            if (in_array($name, array('MATH', 'FRAMESET'), true)) {
                return $htmlSource;
            }

            if ($name === 'SVG' || $name === 'NOSCRIPT') {
                $offset = self::skipNeutralRegion($htmlSource, $start, $name, $ending);

                if ($offset === false) {
                    return $htmlSource;
                }

                continue;
            }

            if ($name === 'SELECT') {
                // Only the ordinary option-only form is unambiguous across parser modes.
                // Complete option tags cannot overlap the closing SELECT token.
                $select = '~\G(?:[^<]*+(?:</?(?:option|optgroup)' . $ending . '|<hr' . $ending . '))*+[^<]*+</select' . $space . '*>~i';

                if ( ! preg_match($select, $htmlSource, $body, 0, $offset) ) {
                    return $htmlSource;
                }

                $offset += strlen($body[0]);
                continue;
            }

            if ($name === 'PLAINTEXT') {
                break;
            }

            if (in_array($name, array('SCRIPT', 'STYLE', 'TEXTAREA', 'TITLE', 'IFRAME', 'XMP', 'NOEMBED', 'NOFRAMES'), true)) {
                if ($name === 'SCRIPT') {
                    $closeAt = self::findScriptCloser($htmlSource, $offset);
                } else {
                    $hasCloser = preg_match('~</' . $name . '(?=[\x09\x0a\x0c\x0d\x20/>])~i', $htmlSource, $close, PREG_OFFSET_CAPTURE, $offset);
                    $closeAt   = $hasCloser === 1 ? $close[0][1] : false;
                }

                if ( $closeAt === false || ! preg_match('~\G</' . $name . $ending . '~i', $htmlSource, $closer, 0, $closeAt) ) {
                    return $htmlSource;
                }

                $offset = $closeAt + strlen($closer[0]);
            }

            if ( ! isset($attributesByTag[$name]) || ! in_array($name, array('LINK', 'SCRIPT'), true) ) {
                continue;
            }

            $cleaned = preg_replace_callback(
                '~(' . $space . '++)(' . $attribute . ')~',
                static function ($attributeMatch) use ($attributesByTag, $name) {
                    preg_match('~^[^\x09\x0a\x0c\x0d\x20=]+~', $attributeMatch[2], $attributeName);

                    foreach ($attributesByTag[$name] as $wanted) {
                        if (strcasecmp($attributeName[0], $wanted) === 0) {
                            return $attributeMatch[1];
                        }
                    }

                    return $attributeMatch[0];
                },
                $tag[0]
            );

            if ($cleaned === null) {
                return $htmlSource;
            }

            if ($cleaned !== $tag[0]) {
                $parts[] = substr($htmlSource, $copied, $start - $copied);
                $parts[] = $cleaned;
                $copied  = $start + strlen($tag[0]);
            }
        }

        if ( $status === false || ! $parts ) {
            return $htmlSource;
        }

        $parts[] = substr($htmlSource, $copied);

        return implode('', $parts);
    }

    /**
     * SCRIPT has normal, escaped and double-escaped states. The first textual
     * </script> is not necessarily its closing tag (HTML script-data rules).
     *
     * @param string $html
     * @param int $offset
     *
     * @return int|false
     */
    private static function findScriptCloser($html, $offset)
    {
        $state = 'normal';

        while (preg_match('~<!--|-->|</?script(?=[\x09\x0a\x0c\x0d\x20/>])~i', $html, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $token  = strtr($match[0][0], 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
            $start  = $match[0][1];
            $offset = $start + strlen($token);

            if ($token === '-->') {
                $state = 'normal';
            } elseif ($token === '<!--' && $state === 'normal') {
                $offset += strspn($html, '-', $offset);

                if (isset($html[$offset]) && $html[$offset] === '>') {
                    $offset++;
                } else {
                    $state = 'escaped';
                }
            } elseif ($token === '<!--') {
                // In an escaped state this is text; its dashes may start an overlapping -->.
                $offset = $start + 1;
            } elseif ($token === '<script' && $state === 'escaped') {
                $state = 'double-escaped';
            } elseif ($token === '</script') {
                if ($state === 'double-escaped') {
                    $state = 'escaped';
                } else {
                    return $start;
                }
            }
        }

        return false;
    }

    /**
     * Accept only regions whose boundaries agree with HTML parsing in either
     * scripting mode. Complex foreign content is intentionally left untouched.
     *
     * @param string $html
     * @param int $start
     * @param string $name
     * @param string $ending
     * @param bool $forBodyDefer Allow additional SVG graphics without changing cleanup policy.
     *
     * @return int|false
     */
    private static function skipNeutralRegion($html, $start, $name, $ending, $forBodyDefer = false)
    {
        $tagPattern = '~\G<(/?)([a-z][a-z0-9:_-]*)' . str_replace('*/?>', '*(/?)>', $ending) . '~i';
        $offset     = $start;
        $stack      = array();
        $svgTags    = array('SVG', 'G', 'PATH', 'USE', 'DEFS', 'SYMBOL', 'RECT', 'CIRCLE', 'ELLIPSE', 'LINE', 'POLYGON', 'POLYLINE', 'TITLE', 'DESC', 'CLIPPATH', 'MASK', 'LINEARGRADIENT', 'RADIALGRADIENT', 'STOP');

        if ($name === 'SVG' && $forBodyDefer) {
            // These elements stay in the SVG namespace. SCRIPT, FOREIGNOBJECT
            // and HTML breakout elements remain unsupported: ignoring them could
            // hide executable scripts or change the surrounding BODY boundaries.
            $svgTags = array_merge($svgTags, array(
                'TEXT', 'TSPAN', 'TEXTPATH', 'STYLE', 'IMAGE', 'PATTERN', 'MARKER',
                'FILTER', 'FEBLEND', 'FECOLORMATRIX', 'FECOMPONENTTRANSFER',
                'FECOMPOSITE', 'FECONVOLVEMATRIX', 'FEDIFFUSELIGHTING',
                'FEDISPLACEMENTMAP', 'FEDISTANTLIGHT', 'FEDROPSHADOW', 'FEFLOOD',
                'FEFUNCA', 'FEFUNCB', 'FEFUNCG', 'FEFUNCR', 'FEGAUSSIANBLUR',
                'FEIMAGE', 'FEMERGE', 'FEMERGENODE', 'FEMORPHOLOGY', 'FEOFFSET',
                'FEPOINTLIGHT', 'FESPECULARLIGHTING', 'FESPOTLIGHT', 'FETILE',
                'FETURBULENCE'
            ));
        }

        if ($name === 'NOSCRIPT') {
            if ( ! preg_match('~</noscript(?=[\x09\x0a\x0c\x0d\x20/>])~i', $html, $closer, PREG_OFFSET_CAPTURE, $start) ) {
                return false;
            }

            $end = $closer[0][1];
        }

        while (true) {
            if ($name === 'SVG' && $forBodyDefer && substr($html, $offset, 9) === '<![CDATA[') {
                // CDATA is text only in foreign content, not at SVG's HTML
                // integration points. A fake closing tag inside it is not a boundary.
                if ( ! $stack || in_array(end($stack), array('TITLE', 'DESC'), true) ) {
                    return false;
                }

                $endCdata = strpos($html, ']]>', $offset + 9);
                if ($endCdata === false) {
                    return false;
                }

                $offset = strpos($html, '<', $endCdata + 3);
                if ($offset === false) {
                    return false;
                }

                continue;
            }

            if (substr($html, $offset, 4) === '<!--') {
                if ( ! preg_match('~\G<!--(?:-?>|[\s\S]*?(?:-->|--!>))~', $html, $comment, 0, $offset) ) {
                    return false;
                }

                $next   = $offset + strlen($comment[0]);
                $offset = strpos($html, '<', $next);

                if ($offset === false || ($name === 'NOSCRIPT' && ($next > $end || $offset > $end))) {
                    return false;
                }

                continue;
            }

            if ( ! preg_match($tagPattern, $html, $tag, 0, $offset) ) {
                return false;
            }

            $tagName = strtr($tag[2], 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
            $closing = $tag[1] === '/';
            $next    = $offset + strlen($tag[0]);

            if ($name === 'SVG') {
                if ( ! in_array($tagName, $svgTags, true) ) {
                    return false;
                }

                if ($closing) {
                    if (array_pop($stack) !== $tagName) {
                        return false;
                    }
                } else {
                    if (in_array(end($stack), array('TITLE', 'DESC', 'STYLE'), true)) {
                        return false;
                    }

                    if ($tag[3] !== '/') {
                        $stack[] = $tagName;
                    }
                }

                if ( ! $stack ) {
                    return $next;
                }
            } else {
                if ($offset === $end) {
                    return $next;
                }

                if ($next > $end || ($tagName === 'NOSCRIPT' && $offset !== $start)) {
                    return false;
                }

                if (in_array($tagName, array('SCRIPT', 'STYLE', 'TEXTAREA', 'TITLE', 'XMP', 'NOEMBED', 'NOFRAMES', 'PLAINTEXT', 'SVG', 'MATH', 'SELECT', 'FRAMESET', 'TEMPLATE'), true)) {
                    return false;
                }

                if ($tagName === 'IFRAME') {
                    if ( $closing || ! preg_match('~\G[^<]*</iframe' . $ending . '~i', $html, $iframe, 0, $next) ) {
                        return false;
                    }

                    $next += strlen($iframe[0]);

                    if ($next > $end) {
                        return false;
                    }
                }
            }

            $offset = strpos($html, '<', $next);

            if ($offset === false || ($name === 'NOSCRIPT' && $offset > $end)) {
                return false;
            }
        }

        return false;
    }
}

