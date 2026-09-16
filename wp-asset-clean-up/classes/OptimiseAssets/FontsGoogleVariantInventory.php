<?php
namespace WpAssetCleanUp\OptimiseAssets;

/**
 * Normalize and aggregate Google Fonts variants independently of their delivery.
 */
class FontsGoogleVariantInventory
{
    const MAX_VARIANTS = 1000;
    const MAX_PATHS_PER_VARIANT = 10;

    /**
     * @param string $css
     *
     * @return array
     */
    public static function extractFromCss($css)
    {
        if (! is_string($css) || trim($css) === ''
            || ! preg_match_all('/@font-face\\s*\\{(.*?)\\}/is', $css, $blockMatches)
            || empty($blockMatches[1])
        ) {
            return array();
        }

        $variants = array();

        foreach ($blockMatches[1] as $block) {
            if (! preg_match('/font-family\\s*:\\s*(?:"([^"]+)"|\'([^\']+)\'|([^;}]+))/i', $block, $familyMatch)
                || ! preg_match('/font-weight\\s*:\\s*([^;}]+)/i', $block, $weightMatch)
            ) {
                continue;
            }

            $family = '';
            foreach (array(1, 2, 3) as $index) {
                if (isset($familyMatch[$index]) && trim($familyMatch[$index]) !== '') {
                    $family = trim($familyMatch[$index]);
                    break;
                }
            }

            $weight = strtolower(trim($weightMatch[1]));
            $weight = $weight === 'normal' ? '400' : ($weight === 'bold' ? '700' : $weight);
            if ($family === '' || ! preg_match('/^[1-9]00$/', $weight)) {
                continue;
            }

            $style = 'normal';
            if (preg_match('/font-style\\s*:\\s*([^;}]+)/i', $block, $styleMatch)
                && strtolower(trim($styleMatch[1])) === 'italic'
            ) {
                $style = 'italic';
            }

            $lookupKey = self::lookupKey($family, $weight, $style);
            if (! isset($variants[$lookupKey])) {
                $variants[$lookupKey] = array(
                    'family'      => $family,
                    'weight'      => $weight,
                    'style'       => $style,
                    'raw_variant' => $weight . ($style === 'italic' ? 'italic' : ''),
                );
            }

            if (count($variants) >= self::MAX_VARIANTS) {
                break;
            }
        }

        return array_values($variants);
    }

    /**
     * @param array $variants
     *
     * @return array
     */
    public static function normalize($variants)
    {
        $normalized = array();

        foreach ((array) $variants as $variant) {
            if (! is_array($variant)) {
                continue;
            }

            $family = isset($variant['family']) ? trim((string) $variant['family']) : '';
            $weight = isset($variant['weight']) ? strtolower(trim((string) $variant['weight'])) : '';
            $weight = $weight === 'normal' ? '400' : ($weight === 'bold' ? '700' : $weight);
            $style = isset($variant['style']) && strtolower(trim((string) $variant['style'])) === 'italic'
                ? 'italic'
                : 'normal';

            if ($family === '' || ! preg_match('/^[1-9]00$/', $weight)) {
                continue;
            }

            $key = self::lookupKey($family, $weight, $style);
            if (! isset($normalized[$key])) {
                $normalized[$key] = array(
                    'family'      => $family,
                    'weight'      => $weight,
                    'style'       => $style,
                    'raw_variant' => isset($variant['raw_variant'])
                        ? (string) $variant['raw_variant']
                        : $weight . ($style === 'italic' ? 'italic' : ''),
                );
            }

            if (count($normalized) >= self::MAX_VARIANTS) {
                break;
            }
        }

        return array_values($normalized);
    }

    public static function encodeTuple($family, $weight, $style)
    {
        $family = trim((string) $family);
        $weight = trim((string) $weight);
        $style = strtolower(trim((string) $style)) === 'italic' ? 'italic' : 'normal';

        return ($family === '' || ! preg_match('/^[1-9]00$/', $weight))
            ? ''
            : rawurlencode($family) . '|' . rawurlencode($weight) . '|' . $style;
    }

    public static function decodeTuple($token)
    {
        if (! is_string($token) || substr_count($token, '|') !== 2) {
            return array();
        }

        $parts = explode('|', $token, 3);
        $family = trim(rawurldecode($parts[0]));
        $weight = trim(rawurldecode($parts[1]));
        $style = strtolower(trim($parts[2]));

        if ($family === '' || ! preg_match('/^[1-9]00$/', $weight)
            || ! in_array($style, array('normal', 'italic'), true)
        ) {
            return array();
        }

        return array('family' => $family, 'weight' => $weight, 'style' => $style);
    }

    /**
     * @param array $entries
     * @param array $savedRules
     *
     * @return array
     */
    public static function aggregate($entries, $savedRules = array())
    {
        $families = array();
        $familyNames = array();

        foreach ((array) $entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $sources = self::normalizeSources(isset($entry['variant_sources']) ? $entry['variant_sources'] : array());
            $paths = self::normalizePaths(isset($entry['paths']) ? $entry['paths'] : array());

            foreach (self::normalize(isset($entry['validated_variants']) ? $entry['validated_variants'] : array()) as $variant) {
                self::mergeVariant($families, $familyNames, $variant, $sources, $paths, 'discovered');
            }
        }

        foreach ((array) $savedRules as $savedRule) {
            $decoded = self::decodeTuple($savedRule);
            if (! empty($decoded)) {
                self::mergeVariant($families, $familyNames, $decoded, array(), array(), 'previously_discovered');
            }
        }

        $result = array();
        foreach ($families as $familyKey => $variants) {
            $family = $familyNames[$familyKey];
            uasort($variants, static function ($a, $b) {
                $weight = strnatcasecmp($a['weight'], $b['weight']);
                return $weight !== 0 ? $weight : strcmp($a['style'], $b['style']);
            });
            $result[$family] = array('family' => $family, 'variants' => $variants);
        }
        uksort($result, 'strnatcasecmp');

        return $result;
    }

    private static function mergeVariant(&$families, &$familyNames, $variant, $sources, $paths, $availability)
    {
        $familyKey = strtolower(trim($variant['family']));
        $token = self::encodeTuple($variant['family'], $variant['weight'], $variant['style']);
        if ($familyKey === '' || $token === '') {
            return;
        }

        if (! isset($families[$familyKey])) {
            $families[$familyKey] = array();
            $familyNames[$familyKey] = $variant['family'];
        }

        $canonicalToken = self::encodeTuple($familyNames[$familyKey], $variant['weight'], $variant['style']);
        if (! isset($families[$familyKey][$canonicalToken])) {
            $families[$familyKey][$canonicalToken] = array(
                'family'       => $familyNames[$familyKey],
                'weight'       => $variant['weight'],
                'style'        => $variant['style'],
                'raw_variant'  => isset($variant['raw_variant']) ? $variant['raw_variant'] : $variant['weight'],
                'sources'      => array(),
                'paths'        => array(),
                'availability' => $availability,
            );
        }

        $current =& $families[$familyKey][$canonicalToken];
        $current['sources'] = self::normalizeSources(array_merge($current['sources'], $sources));
        $current['paths'] = self::normalizePaths(array_merge($current['paths'], $paths));
        if ($availability === 'discovered') {
            $current['availability'] = 'discovered';
        }
        unset($current);
    }

    private static function normalizeSources($sources)
    {
        $valid = array();
        foreach ((array) $sources as $source) {
            $source = strtolower(trim((string) $source));
            if (in_array($source, array('local', 'remote'), true)) {
                $valid[$source] = true;
            }
        }
        $sources = array_keys($valid);
        sort($sources);
        return $sources;
    }

    private static function normalizePaths($paths)
    {
        $valid = array();
        foreach ((array) $paths as $path) {
            $path = trim((string) $path);
            if ($path !== '') {
                $valid[$path] = true;
            }
            if (count($valid) >= self::MAX_PATHS_PER_VARIANT) {
                break;
            }
        }
        return array_keys($valid);
    }

    private static function lookupKey($family, $weight, $style)
    {
        return strtolower(trim((string) $family)) . '|' . trim((string) $weight) . '|'
            . (strtolower(trim((string) $style)) === 'italic' ? 'italic' : 'normal');
    }
}

