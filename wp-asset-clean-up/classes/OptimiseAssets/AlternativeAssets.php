<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\OptimiseAssets;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\AssetsManager;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\Regex;

/**
 * Dashboard-managed replacements for enqueued CSS/JS assets.
 *
 * The database is the persistent source of truth. Generated files live in
 * Asset CleanUp's disposable cache and are recreated when missing.
 *
 * PHP 5.6 compatible.
 */
class AlternativeAssets
{
    const RULES_OPTION          = 'wpassetcleanup_alternative_assets_rules';
    const LAST_RULE_ID_OPTION   = 'wpassetcleanup_alternative_assets_last_id';
    const SOURCE_OPTION_PREFIX  = 'wpassetcleanup_alternative_asset_source_';
    const FILTER_PRIORITY       = 5;
    // Keep exported IDs portable to WordPress installations running 32-bit PHP.
    const MAX_RULE_ID           = 2147483647;

    /** Storage accepts both types; availability is checked separately. */
    public static function supportsAssetType($type)
    {
        return $type === 'css' || ($type === 'js' && defined('WPACU_PRO_PLUGIN_VERSION'));
    }

    /** Missing or obsolete output preferences follow the global settings. */
    public static function getOutputMode($rule)
    {
        return isset($rule['output_mode']) && in_array($rule['output_mode'], array('inherit', 'minified', 'unminified'), true)
            ? $rule['output_mode'] : 'inherit';
    }

    /**
     * Register request-time replacement filters after WordPress has resolved
     * the queried object and conditional tags are available.
     *
     * @return void
     */
    public function init()
    {
        if (is_admin()) {
            return;
        }

        add_action('wp', array($this, 'registerRuntimeFilters'), 20);
    }

    /**
     * @return array
     */
    public static function getRules()
    {
        $rules = get_option(self::RULES_OPTION, array());

        if ( ! is_array($rules) ) {
            return array();
        }

        $normalizedRules = array();

        foreach ($rules as $rule) {
            if ( ! is_array($rule) || empty($rule['id']) || empty($rule['type']) || empty($rule['handle']) ) {
                continue;
            }

            $rule['id']          = absint($rule['id']);
            $rule['type']        = self::sanitizeAssetType($rule['type']);
            $rule['handle']      = self::sanitizeHandle($rule['handle']);
            $rule['enabled']     = ! empty($rule['enabled']);
            $rule['targets']     = self::getRuleTargets($rule);
            $rule['output_mode'] = self::getOutputMode($rule);
            unset($rule['target'], $rule['target_value'], $rule['output']);
            $rule['priority']    = isset($rule['priority']) ? (int)$rule['priority'] : 10;
            $rule['source_hash'] = isset($rule['source_hash']) ? preg_replace('/[^a-f0-9]/i', '', (string)$rule['source_hash']) : '';
            $rule['source_size'] = isset($rule['source_size']) ? max(0, (int)$rule['source_size']) : 0;
            $rule['updated']     = isset($rule['updated']) ? (int)$rule['updated'] : 0;
            $rule['created']     = isset($rule['created']) ? (int)$rule['created'] : $rule['updated'];

            if ($rule['type'] === '' || $rule['handle'] === '') {
                continue;
            }

            $normalizedRules[] = $rule;
        }

        return $normalizedRules;
    }

    /**
     * @param array $rules
     *
     * @return bool|void
     */
    public static function saveRules($rules)
    {
        // Preserve the highest ID before replacing the index, including on an
        // existing installation's first deletion or import after this upgrade.
        $lastId = absint(get_option(self::LAST_RULE_ID_OPTION, 0));
        $highestId = $lastId;
        foreach (array_merge(self::getRules(), (array)$rules) as $rule) {
            if (is_array($rule) && isset($rule['id'])) {
                $highestId = max($highestId, absint($rule['id']));
            }
        }
        if ($highestId > $lastId) {
            update_option(self::LAST_RULE_ID_OPTION, $highestId, false);
        }

        // The compact rule index is required on front-end requests and is safe to autoload.
        // The potentially large CSS/JS source remains in separate non-autoloaded options.
        return Misc::addUpdateOption(self::RULES_OPTION, array_values((array)$rules), 'yes');
    }

    /**
     * Count enabled saved alternatives across CSS and JavaScript.
     * This is not a count of matches on the current admin request.
     *
     * @return int
     */
    public static function getActiveCount()
    {
        $count = 0;
        foreach (self::getRules() as $rule) {
            if ($rule['enabled'] && self::supportsAssetType($rule['type'])) {
                ++$count;
            }
        }
        return $count;
    }

    /**
     * @param int $ruleId
     *
     * @return array|false
     */
    public static function getRule($ruleId)
    {
        $ruleId = absint($ruleId);

        foreach (self::getRules() as $rule) {
            if ((int)$rule['id'] === $ruleId) {
                return $rule;
            }
        }

        return false;
    }

    /**
     * @param string $type
     *
     * @return array
     */
    public static function getRulesByType($type)
    {
        $type = self::sanitizeAssetType($type);

        return array_values(array_filter(self::getRules(), static function ($rule) use ($type) {
            return isset($rule['type']) && $rule['type'] === $type;
        }));
    }

    /** @return array All ON/OFF alternatives for this exact asset identity. */
    public static function getRulesForHandle($type, $handle)
    {
        return array_values(array_filter(self::getRulesByType($type), static function ($rule) use ($handle) {
            return $rule['handle'] === $handle;
        }));
    }

    /**
     * @return int|false
     */
    public static function getNextRuleId()
    {
        $highestId = absint(get_option(self::LAST_RULE_ID_OPTION, 0));

        foreach (self::getRules() as $rule) {
            $highestId = max($highestId, $rule['id']);
        }

        return $highestId >= self::MAX_RULE_ID ? false : $highestId + 1;
    }

    /**
     * @param int $ruleId
     *
     * @return string
     */
    public static function getSource($ruleId)
    {
        $source = get_option(self::SOURCE_OPTION_PREFIX . absint($ruleId), '');

        return is_string($source) ? $source : '';
    }

    /**
     * @param int    $ruleId
     * @param string $source
     *
     * @return bool|void
     */
    public static function saveSource($ruleId, $source)
    {
        $optionName = self::SOURCE_OPTION_PREFIX . absint($ruleId);
        $source     = (string)$source;

        if (get_option($optionName, false) === false) {
            return add_option($optionName, $source, '', 'no');
        }

        return update_option($optionName, $source, 'no');
    }

    /**
     * @param array  $rule
     * @param string $source
     *
     * @return array|false
     */
    public static function upsertRule($rule, $source)
    {
        $type = self::sanitizeAssetType(isset($rule['type']) ? $rule['type'] : '');
        $saved = ! empty($rule['id']) ? self::getRule($rule['id']) : false;
        if ( ! self::supportsAssetType($type) || ($saved && ! self::supportsAssetType($saved['type']))) {
            return false;
        }
        $rules      = self::getRules();
        $ruleId     = ! empty($rule['id']) ? absint($rule['id']) : self::getNextRuleId();
        if ( ! is_int($ruleId) || $ruleId < 1 || $ruleId > self::MAX_RULE_ID) {
            return false;
        }
        $existing   = self::getRule($ruleId);
        $now        = time();
        $source     = (string)$source;
        $created    = $existing && ! empty($existing['created']) ? (int)$existing['created'] : $now;

        $normalized = array(
            'id'           => $ruleId,
            'type'         => self::sanitizeAssetType(isset($rule['type']) ? $rule['type'] : ''),
            'handle'       => self::sanitizeHandle(isset($rule['handle']) ? $rule['handle'] : ''),
            'enabled'      => ! empty($rule['enabled']),
            'targets'      => self::getRuleTargets($rule),
            'output_mode'  => self::getOutputMode(array_key_exists('output_mode', $rule) ? $rule : ($existing ? $existing : array())),
            'priority'     => isset($rule['priority']) ? max(-9999, min(9999, (int)$rule['priority'])) : 10,
            'source_hash'  => sha1($source),
            'source_size'  => strlen($source),
            'created'      => $created,
            'updated'      => $now
        );

        $found = false;

        foreach ($rules as $index => $savedRule) {
            if ((int)$savedRule['id'] === $ruleId) {
                $rules[$index] = $normalized;
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            $rules[] = $normalized;
        }

        // Cached HTML may still reference any generated variant of the previous rule.
        self::retainGeneratedFiles($ruleId);
        self::saveSource($ruleId, $source);
        self::saveRules($rules);

        return $normalized;
    }

    /**
     * @param int $ruleId
     *
     * @return bool
     */
    public static function deleteRule($ruleId)
    {
        $ruleId = absint($ruleId);
        $rules  = self::getRules();
        $before = count($rules);

        $rules = array_values(array_filter($rules, static function ($rule) use ($ruleId) {
            return (int)$rule['id'] !== $ruleId;
        }));

        if ($before === count($rules)) {
            return false;
        }

        self::retainGeneratedFiles($ruleId);
        self::saveRules($rules);
        delete_option(self::SOURCE_OPTION_PREFIX . $ruleId);

        return true;
    }

    /**
     * @param int  $ruleId
     * @param bool $enabled
     *
     * @return array|false
     */
    public static function setRuleEnabled($ruleId, $enabled)
    {
        $ruleId = absint($ruleId);
        $rules  = self::getRules();
        $result = false;

        foreach ($rules as $index => $rule) {
            if ((int)$rule['id'] !== $ruleId || ! self::supportsAssetType($rule['type'])) {
                continue;
            }

            if ((bool)$rule['enabled'] !== (bool)$enabled) {
                self::retainGeneratedFiles($ruleId);
            }
            $rules[$index]['enabled'] = (bool)$enabled;
            $rules[$index]['updated'] = time();
            $result = $rules[$index];
            break;
        }

        if ($result === false) {
            return false;
        }

        self::saveRules($rules);

        return $result;
    }

    /**
     * @param string $type
     *
     * @return string
     */
    public static function sanitizeAssetType($type)
    {
        $type = strtolower(trim((string)$type));

        return in_array($type, array('css', 'js'), true) ? $type : '';
    }

    /**
     * WordPress handles are normally conservative strings, but dots are seen
     * in the wild and should not be stripped by sanitize_key().
     *
     * @param string $handle
     *
     * @return string
     */
    public static function sanitizeHandle($handle)
    {
        return preg_replace('/[^a-zA-Z0-9_.\-]/', '', trim((string)$handle));
    }

    /**
     * Detect a pasted HTML wrapper at the start of raw asset code. This is a
     * format check, not a sanitizer or syntax parser: strings, templates, regex
     * literals and comments elsewhere in valid source may contain tag text.
     *
     * @param string $type
     * @param string $source
     * @return bool
     */
    public static function sourceStartsWithHtmlWrapper($type, $source)
    {
        if ( ! in_array($type, array('css', 'js'), true) || ! is_string($source)) {
            return false;
        }
        $offset = substr($source, 0, 3) === "\xEF\xBB\xBF" ? 3 : 0;
        $leadingTrivia = $type === 'js'
            ? '~\G(?:\s+|/\*.*?\*/|//[^\r\n]*(?:\r\n|\r|\n|$))~s'
            : '~\G(?:\s+|/\*.*?\*/)~s';

        // Advance token by token to avoid nested repetitions/backtracking over
        // large whitespace or license comments before a pasted opening tag.
        while (preg_match($leadingTrivia, $source, $match, 0, $offset) === 1) {
            $offset += strlen($match[0]);
        }
        $tag = $type === 'css' ? 'style' : 'script';
        return preg_match('~\G<\s*' . $tag . '\b~i', $source, $match, 0, $offset) === 1;
    }

    /**
     * Missing settings (including upgrades) keep alternatives enabled.
     * @return bool
     */
    public static function isEnabled()
    {
        $settings = Main::instance()->settings;
        return ! isset($settings['alternative_assets_status']) || $settings['alternative_assets_status'] !== 'off';
    }

    /**
     * Register the winning rule for each handle/type independently of the CSS/JS master.
     * A custom PHP hook at priority 10 can still override this UI rule.
     * @return void
     */
    public function registerRuntimeFilters()
    {
        if (is_admin() || ! self::isEnabled() || Main::instance()->isGetAssetsCall || Main::instance()->preventAssetsSettings()) {
            return;
        }

        $winningRules = array();

        foreach (self::getRules() as $rule) {
            if ( ! self::supportsAssetType($rule['type']) || empty($rule['enabled']) || ! $this->matchesCurrentRequest($rule)) {
                continue;
            }

            $key = $rule['type'] . '|' . $rule['handle'];

            if ( ! isset($winningRules[$key]) || self::isRulePreferred($rule, $winningRules[$key]) ) {
                $winningRules[$key] = $rule;
            }
        }

        foreach ($winningRules as $rule) {
            $filterTag = 'wpacu_' . $rule['handle'] . '_' . $rule['type'] . '_handle_data';

            add_filter($filterTag, function ($data) use ($rule) {
                if ( ! is_array($data) || empty($data['src']) ) {
                    return $data;
                }

                // A saved alternative has no separate RTL source. Keep the
                // original RTL behavior rather than inventing a missing -rtl
                // cache file or silently removing the theme's RTL corrections.
                if ($rule['type'] === 'css' && ! empty($data['extra']['rtl'])
                    && wp_styles()->text_direction === 'rtl') {
                    return $data;
                }

                $originalSrc = ! empty($data['src_origin']) ? (string)$data['src_origin'] : (string)$data['src'];
                $generated   = self::getGeneratedAssetData($rule, $originalSrc);

                if ($generated === false || empty($generated['url'])) {
                    return $data;
                }

                $data['src'] = $generated['url'];
                $data['ver'] = null;

                return $data;
            }, self::FILTER_PRIORITY, 1);
        }
    }

    /**
     * @param array $candidate
     * @param array $current
     *
     * @return bool
     */
    private static function isRulePreferred($candidate, $current)
    {
        $candidatePriority = isset($candidate['priority']) ? (int)$candidate['priority'] : 10;
        $currentPriority   = isset($current['priority']) ? (int)$current['priority'] : 10;

        if ($candidatePriority !== $currentPriority) {
            return $candidatePriority > $currentPriority;
        }

        $candidateSpecificity = self::getMatchingSpecificity($candidate);
        $currentSpecificity   = self::getMatchingSpecificity($current);

        if ($candidateSpecificity !== $currentSpecificity) {
            return $candidateSpecificity > $currentSpecificity;
        }

        $candidateUpdated = isset($candidate['updated']) ? (int)$candidate['updated'] : 0;
        $currentUpdated   = isset($current['updated']) ? (int)$current['updated'] : 0;

        if ($candidateUpdated !== $currentUpdated) {
            return $candidateUpdated > $currentUpdated;
        }

        return (int)$candidate['id'] > (int)$current['id'];
    }

    /**
     * @param string $target
     *
     * @return int
     */
    private static function getTargetSpecificity($target)
    {
        $map = array(
            'everywhere'    => 0,
            'regex'         => 40,
            'logged_in'     => 30,
            'user_role'     => 40,
            'archive_types' => 50,
            'post_tax_term' => 80,
            'search'        => 50,
            'date'          => 50,
            '404'           => 50,
            'homepage'      => 60,
            'posts_page'    => 60,
            'post_type'     => 70,
            'taxonomy'      => 70,
            'author'        => 75,
            'cpt_archive'   => 75,
            'post_ids'      => 90,
            'taxonomy_term' => 90
        );

        return isset($map[$target]) ? $map[$target] : 0;
    }

    /**
     * @param array $rule
     *
     * @return bool
     */
    private function matchesCurrentRequest($rule)
    {
        return self::getMatchingSpecificity($rule) >= 0;
    }

    private static function getMatchingSpecificity($rule)
    {
        $specificity = -1;
        foreach (self::getRuleTargets($rule) as $target => $value) {
            if ($target === 'archive_types') {
                foreach (explode(',', $value) as $archiveType) {
                    $archiveTarget = strpos($archiveType, 'cpt_') === 0 ? 'cpt_archive' : $archiveType;
                    if (in_array($archiveTarget, array('posts_page', 'search', 'date', 'author', '404', 'cpt_archive'), true)
                        && self::matchesTarget($archiveTarget, $archiveTarget === 'cpt_archive' ? substr($archiveType, 4) : '')) {
                        $specificity = max($specificity, self::getTargetSpecificity($archiveTarget));
                    }
                }
                continue;
            }
            if (self::matchesTarget($target, $value)) {
                $specificity = max($specificity, self::getTargetSpecificity($target));
            }
        }
        return $specificity;
    }

    public static function getRuleTargets($rule)
    {
        $targets = isset($rule['targets']) && is_array($rule['targets']) ? $rule['targets'] : array();
        // Normalize legacy single-target rules while preserving their saved location.
        if ( ! isset($rule['targets']) && isset($rule['target']) && is_string($rule['target']) ) {
            $targets[$rule['target']] = isset($rule['target_value']) && is_string($rule['target_value']) ? $rule['target_value'] : '';
        }
        $normalized = array();
        foreach ($targets as $target => $value) {
            if ((is_string($target) || is_int($target)) && is_string($value)) {
                $normalized[sanitize_key((string)$target)] = trim($value);
            }
        }
        return isset($normalized['everywhere']) ? array('everywhere' => '') : $normalized;
    }

    private static function matchesTarget($target, $value)
    {

        switch ($target) {
            case 'everywhere':
                return true;

            case 'homepage':
                return is_front_page();

            case 'posts_page':
                return is_home();

            case 'post_type':
                return $value !== '' && is_singular(array_map('sanitize_key', array_map('trim', explode(',', $value))));

            case 'post_ids':
                $postIds = self::parseIntegerList($value);
                return ! empty($postIds) && is_singular() && in_array((int)get_queried_object_id(), $postIds, true);

            case 'post_tax_term':
                if ( ! is_singular() || strpos($value, 'tt:') !== 0 ) {
                    return false;
                }
                static $postTerms = array();
                $postId = (int)get_queried_object_id();
                if ( ! isset($postTerms[$postId]) ) {
                    $terms = wp_get_object_terms($postId, get_object_taxonomies(get_post_type($postId)), array('fields' => 'tt_ids'));
                    $postTerms[$postId] = is_wp_error($terms) ? array() : array_map('intval', $terms);
                }
                return (bool)array_intersect($postTerms[$postId], self::parseIntegerList(substr($value, 3)));

            case 'logged_in':
                return is_user_logged_in();

            case 'user_role':
                return is_user_logged_in() && (bool)array_intersect((array)wp_get_current_user()->roles, explode(',', $value));

            case 'archive_types':
                foreach (explode(',', $value) as $archiveType) {
                    if (in_array($archiveType, array('posts_page', 'search', 'date', 'author', '404'), true) && self::matchesTarget($archiveType, '')) {
                        return true;
                    }
                    if (strpos($archiveType, 'cpt_') === 0 && is_post_type_archive(substr($archiveType, 4))) {
                        return true;
                    }
                }
                return false;

            case 'taxonomy':
                foreach (explode(',', $value) as $taxonomy) {
                    $taxonomy = sanitize_key(trim($taxonomy));
                    if (($taxonomy === 'category' && is_category()) ||
                        ($taxonomy === 'post_tag' && is_tag()) ||
                        ($taxonomy !== '' && $taxonomy !== 'category' && $taxonomy !== 'post_tag' && is_tax($taxonomy))) {
                        return true;
                    }
                }
                return false;

            case 'taxonomy_term':
                if (strpos($value, 'tt:') === 0) {
                    if ( ! (is_category() || is_tag() || is_tax()) ) {
                        return false;
                    }
                    $term = get_queried_object();
                    return isset($term->term_taxonomy_id) && in_array((int)$term->term_taxonomy_id, self::parseIntegerList(substr($value, 3)), true);
                }
                $parts    = explode(':', $value, 2);
                $taxonomy = isset($parts[0]) ? sanitize_key($parts[0]) : '';
                $termIds  = isset($parts[1]) ? self::parseIntegerList($parts[1]) : array();

                if ($taxonomy === '' || empty($termIds)) {
                    return false;
                }
                if ($taxonomy === 'category') {
                    return is_category($termIds);
                }
                if ($taxonomy === 'post_tag') {
                    return is_tag($termIds);
                }
                return is_tax($taxonomy, $termIds);

            case 'author':
                $authorIds = self::parseIntegerList($value);
                return empty($authorIds) ? is_author() : is_author($authorIds);

            case 'search':
                return is_search();

            case 'date':
                return is_date();

            case '404':
                return is_404();

            case 'cpt_archive':
                return $value !== '' && is_post_type_archive(array_map('sanitize_key', array_map('trim', explode(',', $value))));

            case 'regex':
                if ($value === '') {
                    return false;
                }
                $requestUri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
                    ? wp_unslash($_SERVER['REQUEST_URI'])
                    : '';
                foreach (preg_split('/\r\n|\r|\n/', $value) as $uriRule) {
                    $uriRule = trim($uriRule);
                    if ($uriRule !== '' && Regex::matchesRule($uriRule, $requestUri, true, true)) {
                        return true;
                    }
                }
                return false;
        }

        return false;
    }

    /**
     * @param string $value
     *
     * @return array
     */
    private static function parseIntegerList($value)
    {
        $values = preg_split('/[\s,]+/', trim((string)$value));
        $result = array();

        foreach ((array)$values as $item) {
            $item = absint($item);
            if ($item > 0) {
                $result[] = $item;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @param array  $rule
     * @param string $originalSrc
     * @param bool   $forAdminPreview Use configured minification, without front-end request overrides.
     *
     * @return array|false
     */
    public static function getGeneratedAssetData($rule, $originalSrc, $forAdminPreview = false)
    {
        if (empty($rule['id']) || empty($rule['type']) || ! self::supportsAssetType($rule['type'])) {
            return false;
        }

        $ruleId     = absint($rule['id']);
        $type       = self::sanitizeAssetType($rule['type']);
        $sourceHash = isset($rule['source_hash']) ? (string)$rule['source_hash'] : '';

        if ($type === '') {
            return false;
        }

        // Generated alternatives live in the cache directory, which the normal
        // minification pass skips. Apply the alternative's explicit choice here,
        // or inherit the effective settings and exclusions for the original URL.
        $outputChoice = self::getOutputMode($rule);
        $minify = $outputChoice === 'minified';
        if ($outputChoice === 'inherit') {
            $settings = Main::instance()->settings;
            $scopeKey = 'minify_loaded_' . $type . '_for';
            $scope    = isset($settings[$scopeKey]) ? $settings[$scopeKey] : '';
            $handle   = isset($rule['handle']) ? $rule['handle'] : '';
            // The dashboard previews global settings; the normal checks deliberately disable
            // minification in wp-admin and cache that decision for the current request.
            // Do not change those request-level flags while preparing the list's download link.
            $minify   = $type === 'css'
                ? in_array($scope, array('href', 'all', ''), true)
                    && ($forAdminPreview
                        ? ! empty($settings['minify_loaded_css']) && ! OptimizeCss::isOptimizeCssEnabledByOtherParty('if_enabled')
                        : MinifyCss::isMinifyCssEnabled())
                    && ! MinifyCss::skipMinify($originalSrc, $handle)
                : in_array($scope, array('src', 'all', ''), true)
                    && ($forAdminPreview
                        ? ! empty($settings['minify_loaded_js']) && ! OptimizeJs::isOptimizeJsEnabledByOtherParty('if_enabled')
                        : MinifyJs::isMinifyJsEnabled())
                    && ! MinifyJs::skipMinify($originalSrc, $handle);
        }
        $outputMode = $minify ? 'minified' : 'as_is';

        if ($sourceHash === '') {
            $source = self::getSource($ruleId);
            if ($source === '') {
                return false;
            }
            $sourceHash = sha1($source);
        }

        if ($type === 'css') {
            $originalSrc = self::makeAssetUrlAbsolute($originalSrc);
        }

        // Version the CSS transformation as well as its source. Existing cached
        // alternatives may contain references generated by the old rewriter.
        $originalSrcForHash = $type === 'css' ? 'css-urls-v4|' . (string)$originalSrc : '';
        $fileHash           = substr(sha1($type . '|' . $sourceHash . '|' . $outputMode . '|' . $originalSrcForHash), 0, 16);
        $extension          = $type === 'css' ? 'css' : 'js';
        $minSuffix          = $outputMode === 'minified' ? '.min' : '';
        $fileName           = 'wpacu-alt-' . $type . '-' . $ruleId . '-' . $fileHash . $minSuffix . '.' . $extension;
        $relativeDir        = self::getRelativeCacheDirectory($type);
        $relativePath       = $relativeDir . $fileName;
        $absolutePath       = WP_CONTENT_DIR . $relativePath;

        if ( ! is_file($absolutePath) ) {
            $source = self::getSource($ruleId);

            if ($source === '') {
                return false;
            }

            $generatedContent = $source;

            if ($type === 'css') {
                $generatedContent = self::rebaseCssRelativeUrls($generatedContent, $originalSrc);
            }

            if ($outputMode === 'minified') {
                try {
                    $generatedContent = $type === 'css'
                        ? MinifyCss::applyMinification($generatedContent)
                        : MinifyJs::applyMinification($generatedContent);
                } catch (\Exception $e) {
                    return false;
                }
            }

            if ( ! is_string($generatedContent) || (trim($source) !== '' && trim($generatedContent) === '') ) {
                return false;
            }

            if ( ! self::writeGeneratedFile($absolutePath, $generatedContent) ) {
                return false;
            }
        }

        return array(
            'url'  => content_url($relativePath),
            'path' => $absolutePath,
            'minified' => $minify,
            'size' => is_file($absolutePath) ? (int)filesize($absolutePath) : 0
        );
    }

    /**
     * @param string $type
     *
     * @return string
     */
    private static function getRelativeCacheDirectory($type)
    {
        $base = $type === 'css' ? OptimizeCss::getRelPathCssCacheDir() : OptimizeJs::getRelPathJsCacheDir();

        return '/' . ltrim(rtrim($base, '/') . '/alternative-assets/', '/');
    }

    /**
     * @param string $absolutePath
     * @param string $contents
     *
     * @return bool
     */
    private static function writeGeneratedFile($absolutePath, $contents)
    {
        $directory = dirname($absolutePath);

        if ( ! is_dir($directory) && ! wp_mkdir_p($directory) ) {
            return false;
        }

        $temporaryPath = $absolutePath . '.tmp-' . str_replace('.', '', uniqid('', true));
        $writtenBytes  = @file_put_contents($temporaryPath, $contents, LOCK_EX);

        if ($writtenBytes === false) {
            @unlink($temporaryPath);
            return false;
        }

        if ( ! @rename($temporaryPath, $absolutePath) ) {
            if ( ! @copy($temporaryPath, $absolutePath) ) {
                @unlink($temporaryPath);
                return false;
            }
            @unlink($temporaryPath);
        }

        if (function_exists('chmod')) {
            @chmod($absolutePath, 0644);
        }

        return is_file($absolutePath);
    }

    /**
     * Rebase relative url() and @import references against the original CSS
     * file, because the generated file is served from WPACU's cache directory.
     *
     * @param string $css
     * @param string $originalSrc
     *
     * @return string
     */
    public static function rebaseCssRelativeUrls($css, $originalSrc)
    {
        $absoluteOriginalSrc = self::makeAssetUrlAbsolute($originalSrc);

        if ($absoluteOriginalSrc === '') {
            return $css;
        }

        return CssUrlRewriter::rebase($css, $absoluteOriginalSrc);
    }

    /**
     * @param string $src
     *
     * @return string
     */
    private static function makeAssetUrlAbsolute($src)
    {
        $src = trim((string)$src);

        if ($src === '') {
            return '';
        }

        if (strpos($src, '//') === 0) {
            $scheme = wp_parse_url(home_url('/'), PHP_URL_SCHEME);
            return ($scheme ? $scheme : 'https') . ':' . $src;
        }

        if (preg_match('#^https?://#i', $src)) {
            return $src;
        }

        // Registered styles use WP_Styles::base_url concatenation, including
        // leading-slash sources on subdirectory installations. CSS url(...)
        // references have different semantics and are resolved separately.
        $styles = wp_styles();

        if (empty($styles->content_url) || strpos($src, $styles->content_url) !== 0) {
            $src = $styles->base_url . $src;
        }

        return $src;
    }

    /**
     * Renew the grace period for all existing variants when a rule changes.
     * File contents/URLs remain immutable. Normal cache cleanup applies the
     * configured clear_cached_files_after period from this modification time,
     * so an old file is not removed immediately after its rule is retired.
     *
     * @param int $ruleId
     *
     * @return void
     */
    public static function retainGeneratedFiles($ruleId)
    {
        $ruleId = absint($ruleId);

        foreach (array('css', 'js') as $type) {
            $directory = WP_CONTENT_DIR . self::getRelativeCacheDirectory($type);
            $matches   = glob($directory . 'wpacu-alt-' . $type . '-' . $ruleId . '-*');

            if ( ! is_array($matches) ) {
                continue;
            }

            foreach ($matches as $filePath) {
                if (is_file($filePath) && ! is_link($filePath)
                    && preg_match('/\.(?:css|js)$/D', $filePath)) {
                    @touch($filePath);
                }
            }
        }
    }

    /**
     * @return array
     */
    public static function getDetectedAssets()
    {
        $registry = array();
        $allAssets = Main::getHandlesInfo();
        $typeMap = array('styles' => 'css', 'scripts' => 'js');

        foreach ($typeMap as $assetCollectionKey => $type) {
            if ( ! self::supportsAssetType($type) || empty($allAssets[$assetCollectionKey])) {
                continue;
            }

            foreach ((array)$allAssets[$assetCollectionKey] as $storedHandle => $assetObject) {
                if ( ! is_array($assetObject) && ! is_object($assetObject) ) {
                    continue;
                }
                $asset = is_object($assetObject) ? get_object_vars($assetObject) : (array)$assetObject;
                $handle = is_string($storedHandle) || is_int($storedHandle) ? (string)$storedHandle : '';

                if ( ! empty($asset['handleOriginal']) && is_string($asset['handleOriginal']) ) {
                    $handle = self::sanitizeHandle($asset['handleOriginal']);
                } elseif ( ! empty($asset['handle']) && is_string($asset['handle']) ) {
                    $handle = self::sanitizeHandle($asset['handle']);
                }

                // Replacements apply to enqueued files, not generated handles for hardcoded tags.
                if (strpos($handle, 'wpacu_hardcoded_') === 0) {
                    continue;
                }
                $handle = self::sanitizeHandle($handle);

                $src = '';
                foreach (array('src_origin', 'src') as $srcKey) {
                    if ( ! empty($asset[$srcKey]) && is_string($asset[$srcKey]) ) {
                        $src = Misc::getHrefFromSource($asset[$srcKey]);
                        break;
                    }
                }

                if ($handle === '' || $src === '') {
                    continue;
                }

                if (strpos($src, '//') === 0) {
                    $src = set_url_scheme($src);
                }
                if ( ! in_array(strtolower((string)wp_parse_url($src, PHP_URL_SCHEME)), array('http', 'https'), true) ) {
                    continue;
                }

                $owner = self::detectOwner($src);
                $key   = $type . '|' . $handle;
                $size  = 0;

                if (isset($asset['size_raw']) && is_numeric($asset['size_raw'])) {
                    $size = max(0, (int)$asset['size_raw']);
                } else {
                    // This existing helper checks local files; it does not download remote assets.
                    $localSize = AssetsManager::getAssetSize((object)array('src' => $src), 'raw');
                    $size = is_numeric($localSize) ? max(0, (int)$localSize) : 0;
                }

                $registry[$key] = array(
                    'type'          => $type,
                    'handle'        => $handle,
                    'src'           => esc_url_raw($src),
                    'size'          => $size,
                    'owner_type'    => $owner['type'],
                    'owner_name'    => $owner['name']
                );
            }
        }

        return $registry;
    }

    /**
     * @param string $src
     *
     * @return array
     */
    private static function detectOwner($src)
    {
        $path = wp_parse_url($src, PHP_URL_PATH);
        $path = is_string($path) ? $path : (string)$src;

        if (preg_match('#/wp-content/plugins/([^/]+)/#i', $path, $matches)) {
            static $pluginNamesBySlug = null;

            $slug = sanitize_key($matches[1]);
            $name = $slug;

            if (is_admin()) {
                if ($pluginNamesBySlug === null) {
                    $pluginNamesBySlug = array();

                    if ( ! function_exists('get_plugins') ) {
                        require_once ABSPATH . 'wp-admin/includes/plugin.php';
                    }

                    foreach (get_plugins() as $pluginFile => $pluginData) {
                        $pluginSlug = strpos($pluginFile, '/') !== false
                            ? substr($pluginFile, 0, strpos($pluginFile, '/'))
                            : basename($pluginFile, '.php');

                        if ($pluginSlug !== '' && ! empty($pluginData['Name'])) {
                            $pluginNamesBySlug[$pluginSlug] = $pluginData['Name'];
                        }
                    }
                }

                if (isset($pluginNamesBySlug[$slug])) {
                    $name = $pluginNamesBySlug[$slug];
                }
            }

            return array('type' => 'plugin', 'name' => $name);
        }

        if (preg_match('#/wp-content/themes/([^/]+)/#i', $path, $matches)) {
            static $themeNamesBySlug = array();

            $slug = sanitize_key($matches[1]);

            if ( ! isset($themeNamesBySlug[$slug]) ) {
                $theme = function_exists('wp_get_theme') ? wp_get_theme($slug) : false;
                $themeNamesBySlug[$slug] = $theme && $theme->exists() ? $theme->get('Name') : $slug;
            }

            return array('type' => 'theme', 'name' => $themeNamesBySlug[$slug]);
        }

        if (strpos($path, '/wp-includes/') !== false || strpos($path, '/wp-admin/') !== false) {
            return array('type' => 'wordpress', 'name' => 'WordPress');
        }

        if (strpos($path, '/wp-content/uploads/') !== false || strpos($path, '/wp-content/cache/') !== false) {
            return array('type' => 'generated', 'name' => __('Uploads / generated file', 'wp-asset-clean-up'));
        }

        $assetHost = wp_parse_url($src, PHP_URL_HOST);
        $siteHost  = wp_parse_url(home_url('/'), PHP_URL_HOST);

        if ($assetHost && $siteHost && strtolower($assetHost) !== strtolower($siteHost)) {
            return array('type' => 'external', 'name' => $assetHost);
        }

        return array('type' => 'unknown', 'name' => __('Unknown source', 'wp-asset-clean-up'));
    }

    /**
     * @param string $value
     *
     * @return array
     */
    public static function getTermTaxonomyIds($value)
    {
        if (strpos($value, 'tt:') === 0) {
            return self::parseIntegerList(substr($value, 3));
        }
        $parts = explode(':', $value, 2);
        $ids = array();
        if (count($parts) === 2) {
            foreach (self::parseIntegerList($parts[1]) as $termId) {
                $term = get_term($termId, $parts[0]);
                if ($term && ! is_wp_error($term)) {
                    $ids[] = (int)$term->term_taxonomy_id;
                }
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * @param array $rule
     * @return string
     */
    public static function getTargetLabel($rule)
    {
        $labels = array(
            'everywhere'    => __('Everywhere', 'wp-asset-clean-up'),
            'homepage'      => __('Homepage', 'wp-asset-clean-up'),
            'posts_page'    => __('Blog / Posts page', 'wp-asset-clean-up'),
            'post_type'     => __('Post type', 'wp-asset-clean-up'),
            'post_ids'      => __('Specific posts/pages', 'wp-asset-clean-up'),
            'taxonomy'      => __('Taxonomy archive', 'wp-asset-clean-up'),
            'taxonomy_term' => __('Specific taxonomy terms', 'wp-asset-clean-up'),
            'post_tax_term' => __('Entries assigned to taxonomy terms', 'wp-asset-clean-up'),
            'archive_types' => __('Archive and listing pages', 'wp-asset-clean-up'),
            'logged_in'     => __('Logged-in users', 'wp-asset-clean-up'),
            'user_role'     => __('User roles', 'wp-asset-clean-up'),
            'author'        => __('Author archive', 'wp-asset-clean-up'),
            'search'        => __('Search results', 'wp-asset-clean-up'),
            'date'          => __('Date archives', 'wp-asset-clean-up'),
            '404'           => __('404 pages', 'wp-asset-clean-up'),
            'cpt_archive'   => __('Custom post type archive', 'wp-asset-clean-up'),
            'regex'         => __('Request URI rule', 'wp-asset-clean-up')
        );

        $parts = array();
        foreach (self::getRuleTargets($rule) as $target => $value) {
            $label = isset($labels[$target]) ? $labels[$target] : $target;
            if (in_array($target, array('taxonomy_term', 'post_tax_term'), true) && strpos($value, 'tt:') === 0) {
                $termLabels = array();
                foreach (self::getTermTaxonomyIds($value) as $termTaxonomyId) {
                    $term = get_term_by('term_taxonomy_id', $termTaxonomyId);
                    $termLabels[] = $term ? $term->name . ' (' . $term->taxonomy . ')' : '#' . $termTaxonomyId;
                }
                $value = implode(', ', $termLabels);
            }
            $parts[] = $value !== '' ? $label . ': ' . $value : $label;
        }
        return implode(' · ', $parts);
    }
}
