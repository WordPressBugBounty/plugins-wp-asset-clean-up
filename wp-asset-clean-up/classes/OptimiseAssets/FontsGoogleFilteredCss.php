<?php
namespace WpAssetCleanUp\OptimiseAssets;

/**
 * Build immutable, rule-addressed derivatives of validated local Google CSS.
 */
class FontsGoogleFilteredCss
{
    /**
     * @param array $entry
     * @param array $rules
     *
     * @return array
     */
    public static function resolve($entry, $rules)
    {
        $error = array('status' => 'error', 'url' => '', 'path' => '', 'error' => 'The local stylesheet is not available.');

        if (! is_array($entry)
            || empty($entry['local_css_path'])
            || ! is_string($entry['local_css_path'])
            || ! is_file($entry['local_css_path'])
            || empty($entry['local_css_url'])
            || ! is_string($entry['local_css_url'])
        ) {
            return $error;
        }

        $ruleMap = self::ruleMap($rules);
        if (empty($ruleMap)) {
            return array(
                'status' => 'unchanged',
                'url'    => $entry['local_css_url'],
                'path'   => $entry['local_css_path'],
                'error'  => '',
            );
        }

        $sourceCss = @file_get_contents($entry['local_css_path']);
        if (! is_string($sourceCss)) {
            return $error;
        }

        $filteredCss = preg_replace_callback(
            '/@font-face\\s*\\{.*?\\}/is',
            static function ($matches) use ($ruleMap) {
                $tuple = self::fontFaceTuple($matches[0]);
                return ! empty($tuple) && isset($ruleMap[$tuple]) ? '' : $matches[0];
            },
            $sourceCss
        );

        if (! is_string($filteredCss)) {
            return array('status' => 'error', 'url' => '', 'path' => '', 'error' => 'The local stylesheet could not be filtered.');
        }

        $meaningfulCss = trim(preg_replace('#/\\*.*?\\*/#s', '', $filteredCss));
        if ($meaningfulCss === '') {
            return array('status' => 'empty', 'url' => '', 'path' => '', 'error' => '');
        }

        if ($filteredCss === $sourceCss) {
            return array(
                'status' => 'unchanged',
                'url'    => $entry['local_css_url'],
                'path'   => $entry['local_css_path'],
                'error'  => '',
            );
        }

        $tokens = array_keys($ruleMap);
        sort($tokens);
        $sourceHash = hash('sha256', $sourceCss);
        $version = isset($entry['processed_at']) ? (string) max(0, (int) $entry['processed_at']) : '0';
        $hash = substr(hash('sha256', $sourceHash . '|' . $version . '|' . implode("\n", $tokens)), 0, 20);
        $sourceDirectory = dirname($entry['local_css_path']);
        $targetDirectory = $sourceDirectory;
        $extension = substr($entry['local_css_path'], -8) === '.min.css' ? '.min.css' : '.css';
        $targetPath = $targetDirectory . '/filtered-' . $hash . $extension;
        $targetUrl = rtrim(dirname($entry['local_css_url']), '/') . '/' . basename($targetPath);

        if (! is_file($targetPath)) {
            if (! is_dir($targetDirectory) && ! @mkdir($targetDirectory, 0755, true) && ! is_dir($targetDirectory)) {
                return array('status' => 'error', 'url' => '', 'path' => '', 'error' => 'The filtered stylesheet directory could not be created.');
            }

            $temporaryPath = @tempnam($targetDirectory, 'wpacu-gf-');
            if (! is_string($temporaryPath)
                || @file_put_contents($temporaryPath, $filteredCss, LOCK_EX) === false
                || ! @rename($temporaryPath, $targetPath)
            ) {
                if (is_string($temporaryPath) && is_file($temporaryPath)) {
                    @unlink($temporaryPath);
                }
                return array('status' => 'error', 'url' => '', 'path' => '', 'error' => 'The filtered stylesheet could not be published.');
            }
        }

        return array('status' => 'ready', 'url' => $targetUrl, 'path' => $targetPath, 'error' => '');
    }

    private static function ruleMap($rules)
    {
        $map = array();
        foreach ((array) $rules as $rule) {
            $decoded = FontsGoogleVariantInventory::decodeTuple($rule);
            if (empty($decoded)) {
                continue;
            }
            $map[self::lookupKey($decoded['family'], $decoded['weight'], $decoded['style'])] = true;
        }
        return $map;
    }

    private static function fontFaceTuple($block)
    {
        if (! preg_match('/font-family\\s*:\\s*(?:"([^"]+)"|\'([^\']+)\'|([^;}]+))/i', $block, $familyMatch)) {
            return '';
        }

        $family = '';
        foreach (array(1, 2, 3) as $index) {
            if (isset($familyMatch[$index]) && trim($familyMatch[$index]) !== '') {
                $family = trim($familyMatch[$index]);
                break;
            }
        }

        $weight = '400';
        if (preg_match('/font-weight\\s*:\\s*([^;}]+)/i', $block, $weightMatch)) {
            $weight = strtolower(trim($weightMatch[1]));
            $weight = $weight === 'normal' ? '400' : ($weight === 'bold' ? '700' : $weight);
        }
        if (! preg_match('/^[1-9]00$/', $weight)) {
            return '';
        }

        $style = 'normal';
        if (preg_match('/font-style\\s*:\\s*([^;}]+)/i', $block, $styleMatch)
            && strtolower(trim($styleMatch[1])) === 'italic'
        ) {
            $style = 'italic';
        }

        return self::lookupKey($family, $weight, $style);
    }

    private static function lookupKey($family, $weight, $style)
    {
        return strtolower(trim((string) $family)) . '|' . trim((string) $weight) . '|'
            . ($style === 'italic' ? 'italic' : 'normal');
    }
}
