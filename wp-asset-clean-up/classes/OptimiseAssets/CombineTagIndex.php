<?php

namespace WpAssetCleanUp\OptimiseAssets;

/**
 * Locate the source bytes of the elements selected by the combine DOM pass.
 */
class CombineTagIndex
{
    private $external = array();
    private $inlineScripts = array();
    private $inlineStyles = array();
    private $preloads = array();

    /**
     * @param string $html
     * @param string $type
     *
     * @return void
     */
    public function __construct($html, $type)
    {
        // Other scanner callers can safely edit a recognized prefix. A bundle
        // needs the complete document, including any later attached inline code.
        $tokens = $type === 'css'
            ? HtmlCleanup::getStyleTokens($html . '<link rel="stylesheet">')
            : HtmlCleanup::getScriptTokens($html . '<script></script>');
        if ( ! $tokens ) {
            return;
        }

        $end = array_pop($tokens);
        if ($end['offset'] !== strlen($html) || $end['manager']) {
            return;
        }

        foreach ($tokens as $token) {
            if ($token['manager']) {
                continue;
            }

            $attrs = $token['attributes'];
            if ($type === 'css' && $token['tag_name'] === 'STYLE' && isset($attrs['id'])) {
                $this->inlineStyles[$attrs['id']][] = $token;
            }
            if ($type === 'css' && $token['tag_name'] === 'LINK'
                && isset($attrs['rel'], $attrs['href'], $attrs['data-wpacu-preload-css-basic'])
                && $attrs['rel'] === 'preload') {
                $this->preloads[$attrs['href']][] = $token;
            }

            if ($type === 'js' && ! isset($attrs['src'])) {
                $handle = isset($attrs['data-wpacu-script-handle']) ? $attrs['data-wpacu-script-handle'] : '';
                if ($handle === '' && isset($attrs['id']) && preg_match('/^(.+)-js-(before|after|extra|translations)$/D', $attrs['id'], $match)) {
                    $handle = $match[1];
                }
                if ($handle !== '') {
                    $this->inlineScripts[$handle][] = $token;
                }
                continue;
            }

            if ($type === 'css' && ($token['tag_name'] !== 'LINK' || ! isset($attrs['rel']) || $attrs['rel'] !== 'stylesheet')) {
                continue;
            }

            $key = self::key($attrs);
            // Repeated identical active elements cannot be distinguished by cached attributes.
            $this->external[$key] = array_key_exists($key, $this->external) ? false : $token;
        }
    }

    /**
     * @param array $attributes
     *
     * @return string
     */
    public static function key($attributes)
    {
        $attributes = array_map('trim', $attributes);
        foreach (array('defer', 'async', 'nomodule', 'disabled') as $boolean) {
            if (isset($attributes[$boolean]) && $attributes[$boolean] === $boolean) {
                $attributes[$boolean] = '';
            }
        }
        ksort($attributes);
        return sha1(serialize($attributes));
    }

    /**
     * A conservative source scan may stop before the end of malformed HTML.
     * Also check uniqueness in the DOM that actually determines eligibility.
     *
     * @param \DOMDocument $document
     * @param string $tagName
     *
     * @return array
     */
    public static function getDuplicateKeys($document, $tagName)
    {
        $seen       = array();
        $duplicates = array();
        foreach ($document->getElementsByTagName($tagName) as $element) {
            if ( ! $element->hasAttribute($tagName === 'link' ? 'href' : 'src') ) {
                continue;
            }
            $attributes = array();
            foreach ($element->attributes as $attribute) {
                $attributes[$attribute->nodeName] = $attribute->nodeValue;
            }
            $key = self::key($attributes);
            if (isset($seen[$key])) {
                $duplicates[$key] = true;
            }
            $seen[$key] = true;
        }
        return $duplicates;
    }

    /**
     * Resolve every group member and verify its file before scheduling any edits.
     *
     * @param array $files
     * @param array $keys
     * @param string $type
     * @param array $sourceHashes
     *
     * @return array
     */
    public function getGroup($files, $keys, $type, &$sourceHashes)
    {
        if ( ! is_array($files) || ! is_array($keys) || count($files) !== count($keys) || count($files) < 2 ) {
            return array();
        }

        $group          = array();
        $previousOffset = -1;
        foreach ($files as $index => $source) {
            if ( ! is_string($source) || ! isset($keys[$index]) || ! is_string($keys[$index])
                || empty($this->external[$keys[$index]]) ) {
                return array();
            }
            $token = $this->external[$keys[$index]];
            $url   = $token['attributes'][$type === 'css' ? 'href' : 'src'];
            if ($token['offset'] <= $previousOffset
                || $source !== str_replace('{site_url}', '', OptimizeCommon::getSourceRelPath(trim($url), true))) {
                return array();
            }
            $previousOffset = $token['offset'];
            if (strpos($index, '_') !== false && strpos($source, OptimizeCommon::getRelPathPluginCacheDir()) === false) {
                list($expectedHash) = explode('_', $index, 2);
                $localPath          = OptimizeCommon::getLocalAssetPath($url, $type);
                if ($expectedHash !== OptimizeCommon::getCombineFileHash($localPath, $sourceHashes)) {
                    return array();
                }
            }
            $group[] = $token;
        }
        return $group;
    }

    /**
     * @param string $handle
     *
     * @return array
     */
    public function getInlineScripts($handle)
    {
        return isset($this->inlineScripts[$handle]) ? $this->inlineScripts[$handle] : array();
    }

    /**
     * @param string $handle
     *
     * @return array
     */
    public function getInlineStyles($handle)
    {
        $id = $handle . '-inline-css';
        return isset($this->inlineStyles[$id]) ? $this->inlineStyles[$id] : array();
    }

    /**
     * @param string $href
     *
     * @return array
     */
    public function getBasicPreloads($href)
    {
        return isset($this->preloads[$href]) ? $this->preloads[$href] : array();
    }

    /**
     * @param string $html
     * @param array $edits Original offsets mapped to a byte length and replacement.
     *
     * @return string
     */
    public static function applyEdits($html, $edits)
    {
        ksort($edits, SORT_NUMERIC);
        $result = '';
        $offset = 0;
        foreach ($edits as $start => $edit) {
            $result .= substr($html, $offset, $start - $offset) . $edit[1];
            $offset  = $start + $edit[0];
        }
        return $result . substr($html, $offset);
    }
}
