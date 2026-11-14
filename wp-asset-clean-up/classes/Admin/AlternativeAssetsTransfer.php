<?php
namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\Menu;
use WpAssetCleanUp\Regex;
use WpAssetCleanUp\OptimiseAssets\AlternativeAssets;

/** Transfer both asset types; runtime availability is enforced separately. */
class AlternativeAssetsTransfer
{
    public static function exportSection()
    {
        $rules = array();
        $sources = array();
        foreach (AlternativeAssets::getRules() as $rule) {
            $rules[] = $rule;
            $sources[$rule['id']] = AlternativeAssets::getSource($rule['id']);
        }
        return array('version' => 1, 'rules' => $rules, 'sources' => $sources);
    }

    /**
     * Validate the entire section before the common importer writes any options.
     * Missing sections from older exports leave existing alternatives untouched.
     *
     * @param string $error
     * @param array  $valuesArray
     * @return string
     */
    public static function validateImport($error, $valuesArray)
    {
        if ($error !== '' || ! array_key_exists('alternative_assets', $valuesArray)) {
            return $error;
        }

        $section = $valuesArray['alternative_assets'];
        $invalid = __('The Alternative Assets section is invalid or incomplete. No data was imported.', 'wp-asset-clean-up');
        if ( ! is_array($section) || ! isset($section['version'], $section['rules'], $section['sources'])
            || $section['version'] !== 1 || ! is_array($section['rules']) || ! is_array($section['sources'])) {
            return $invalid;
        }

        // An empty snapshot needs code-management permission only when it can
        // replace stored alternatives. Check the raw index to preserve even
        // records that runtime normalization would otherwise omit.
        if (( ! empty($section['rules']) || ! empty($section['sources'])
                || get_option(AlternativeAssets::RULES_OPTION, array()))
            && ! Menu::userCanManageAlternativeAssets()) {
            return __('Importing Alternative Assets requires permission to manage the plugin and the unfiltered_html capability. No data was imported.', 'wp-asset-clean-up');
        }

        $allowedTargets = array('everywhere', 'homepage', 'posts_page', 'post_type', 'post_ids',
            'taxonomy', 'taxonomy_term', 'author', 'search', 'date', '404', 'cpt_archive',
            'regex', 'post_tax_term', 'archive_types', 'logged_in', 'user_role');
        $requiresValue = array('post_type', 'post_ids', 'taxonomy', 'taxonomy_term', 'cpt_archive',
            'regex', 'post_tax_term', 'archive_types', 'user_role');
        $ids = array();
        foreach ($section['rules'] as $rule) {
            if ( ! is_array($rule) || ! isset($rule['id'], $rule['type'], $rule['handle'], $rule['enabled'], $rule['targets'])
                || ! is_int($rule['id']) || $rule['id'] < 1 || $rule['id'] > AlternativeAssets::MAX_RULE_ID || isset($ids[$rule['id']])
                || ! in_array($rule['type'], array('css', 'js'), true)
                || ! is_string($rule['handle']) || $rule['handle'] === ''
                || AlternativeAssets::sanitizeHandle($rule['handle']) !== $rule['handle']
                || ! is_bool($rule['enabled']) || ! is_array($rule['targets']) || empty($rule['targets'])) {
                return $invalid;
            }
            foreach (array('priority', 'created', 'updated') as $integerKey) {
                if ( ! isset($rule[$integerKey]) || ! is_int($rule[$integerKey])) {
                    return $invalid;
                }
            }
            if (array_key_exists('output_mode', $rule)
                && ! in_array($rule['output_mode'], array('inherit', 'minified', 'unminified'), true)) {
                return $invalid;
            }
            if ($rule['priority'] < -9999 || $rule['priority'] > 9999 || $rule['created'] < 0 || $rule['updated'] < 0) {
                return $invalid;
            }
            $id = $rule['id'];
            $ids[$id] = true;
            if ( ! isset($section['sources'][$id]) || ! is_string($section['sources'][$id])
                || trim($section['sources'][$id]) === '') {
                return $invalid;
            }
            $source = $section['sources'][$id];
            if (AlternativeAssets::sourceStartsWithHtmlWrapper($rule['type'], $source)) {
                return $invalid;
            }
            foreach ($rule['targets'] as $target => $value) {
                $target = (string)$target;
                if ( ! in_array($target, $allowedTargets, true) || ! is_string($value)
                    || (in_array($target, $requiresValue, true) && trim($value) === '')) {
                    return $invalid;
                }
                if ($target === 'post_tax_term'
                    && ! preg_match('/^tt:[1-9][0-9]*(?:,[1-9][0-9]*)*$/D', $value)) {
                    return $invalid;
                }
                // Runtime also supports the legacy taxonomy:term_ids format and
                // an empty author condition meaning all author archives.
                if ($target === 'taxonomy_term'
                    && ! preg_match('/^[a-z0-9_-]+:[1-9][0-9]*(?:,[1-9][0-9]*)*$/D', $value)) {
                    return $invalid;
                }
                if (($target === 'post_ids' || ($target === 'author' && $value !== ''))
                    && ! preg_match('/^[1-9][0-9]*(?:,[1-9][0-9]*)*$/D', $value)) {
                    return $invalid;
                }
                if ($target === 'regex') {
                    foreach (preg_split('/\r\n|\r|\n/', $value) as $uriRule) {
                        if (trim($uriRule) !== '' && ! Regex::isValidRule(trim($uriRule), true)) {
                            return $invalid;
                        }
                    }
                }
            }
        }
        // Never accept arbitrary option names, orphan sources or generated file paths.
        foreach ($section['sources'] as $id => $source) {
            if ( ! is_int($id) || ! isset($ids[$id])) {
                return $invalid;
            }
        }
        return '';
    }

    /**
     * Replace the complete CSS/JavaScript snapshot in either edition.
     * Return the common import-notice keys, or an empty array on rejected input.
     */
    public static function importSection($section)
    {
        if (self::validateImport('', array('alternative_assets' => $section)) !== '') {
            return array();
        }
        // Nothing to replace: do not create or rewrite a protected option for
        // an otherwise ordinary import by a user without code permission.
        if (empty($section['rules']) && ! get_option(AlternativeAssets::RULES_OPTION, array())) {
            return array('alternative_assets');
        }
        $previous = AlternativeAssets::getRules();
        $rules = array();
        $ids = array();
        foreach ($previous as $rule) {
            AlternativeAssets::retainGeneratedFiles($rule['id']);
        }
        $notices = array('alternative_assets');
        foreach ($section['rules'] as $rule) {
            if ( ! AlternativeAssets::supportsAssetType($rule['type'])) {
                $notices[] = 'alternative_assets_js_stored';
            }
            $source = $section['sources'][$rule['id']];
            $id = $rule['id'];
            $normalized = array_intersect_key($rule, array_flip(array('id', 'type', 'handle', 'enabled', 'priority', 'created', 'updated')));
            $normalized['id'] = $id;
            $normalized['targets'] = AlternativeAssets::getRuleTargets($rule);
            $normalized['output_mode'] = AlternativeAssets::getOutputMode($rule);
            $normalized['source_hash'] = sha1($source);
            $normalized['source_size'] = strlen($source);
            $rules[] = $normalized;
            $ids[$id] = true;
            AlternativeAssets::saveSource($id, $source);
        }
        AlternativeAssets::saveRules($rules);
        foreach ($previous as $rule) {
            if ( ! isset($ids[$rule['id']])) {
                delete_option(AlternativeAssets::SOURCE_OPTION_PREFIX . $rule['id']);
            }
        }
        return array_values(array_unique($notices));
    }
}
