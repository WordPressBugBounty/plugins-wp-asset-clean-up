<?php
namespace WpAssetCleanUpLite;

use WpAssetCleanUp\Misc;

/**
 * Read-only presentation data for the completed front-end query. No Pro runtime is required.
 */
class FrontendPluginsPreviewData
{
    /**
     * @return array
     */
    public static function context()
    {
        $url       = explode('#', Misc::getCurrentPageUrl(false), 2);
        $parts     = explode('?', $url[0], 2);
        $arguments = array();
        $internal  = array(
            'wpacu_manager', 'wpacu_plugins_updated', 'wpacu_switched_to', 'lite_enabled', 'pro_enabled',
            'wpacu_updated', 'wpacu_time', 'wpacu_no_cache', 'wpacu_debug', 'wpacu_load', 'wpacu_unload',
            'wpacu_skip_test_mode', 'wpacu_ignore_nocache', 'wpacu_no_frontend_show'
        );
        if (isset($parts[1])) {
            foreach (explode('&', $parts[1]) as $argument) {
                $pair = explode('=', $argument, 2);
                if (!in_array(urldecode($pair[0]), $internal, true)) { $arguments[] = $argument; }
            }
        }
        // Keep visitor arguments verbatim: dotted names, duplicates and encoding can be significant.
        $context = array(
            'valid'=>false, 'label'=>'', 'url'=>$parts[0] . ($arguments ? '?' . implode('&', $arguments) : ''),
            'post_id'=>0, 'post_type'=>'', 'taxonomy'=>'', 'term_name'=>'', 'term_taxonomy_ids'=>array(),
            'archive_type'=>'', 'author_id'=>0, 'is_homepage'=>false, 'unload_key'=>'', 'load_key'=>'',
            'values'=>null, 'selection'=>array('wpacu_scope'=>'specific')
        );
        if (is_404()) { return $context; }

        $object = get_queried_object();
        $suffix = '';
        if (is_front_page()) {
            $suffix                 = 'home_page';
            $context['is_homepage'] = true;
            $context['label']       = __('Homepage', 'wp-asset-clean-up');
            if (get_option('show_on_front') === 'page') {
                $context['post_id'] = (int)get_option('page_on_front');
            }
        } elseif (is_singular() || (is_home() && get_option('page_for_posts'))) {
            $suffix             = 'via_post';
            $context['post_id'] = is_home() ? (int)get_option('page_for_posts') : (int)get_queried_object_id();
            if (!$context['post_id']) { return $context; }
            $context['values'] = self::postIds($context['post_id']);
            $context['label']  = get_the_title($context['post_id']) ?: __('(no title)', 'wp-asset-clean-up');
        } elseif (is_category() || is_tag() || is_tax()) {
            if (!$object || empty($object->taxonomy) || empty($object->term_taxonomy_id)) { return $context; }
            $suffix               = 'via_tax_term';
            $context['taxonomy']  = $object->taxonomy;
            $context['term_name'] = $object->name;
            $context['label']     = $object->name;
            $context['values']    = self::termIds($object->term_taxonomy_id);
        } elseif (is_author()) {
            $suffix               = 'via_author_archive';
            $context['author_id'] = (int)get_queried_object_id();
            if (!$context['author_id']) { return $context; }
            $context['archive_type'] = 'author';
            $context['values']       = array($context['author_id']);
            $context['label']        = $object->display_name . ' — ' . __('author archive', 'wp-asset-clean-up');
            $context['author_slug']  = $object->user_nicename;
        } elseif (is_post_type_archive()) {
            if (!$object || empty($object->name)) { return $context; }
            $suffix                  = 'via_archive';
            $context['archive_type'] = 'custom_post_type_archive_' . $object->name;
            $context['values']       = array($context['archive_type']);
            $context['label']        = $object->labels->name . ' — ' . __('archive', 'wp-asset-clean-up');
        } elseif (is_search() || is_date()) {
            $suffix                  = 'via_archive';
            $context['archive_type'] = is_search() ? 'search' : 'date';
            $context['values']       = array($context['archive_type']);
            $context['label']        = is_search() ? __('All search results pages', 'wp-asset-clean-up') : __('All date archives', 'wp-asset-clean-up');
        }
        if ($suffix === '') { return $context; }
        if ($context['post_id']) {
            $context['post_type']         = get_post_type($context['post_id']);
            $terms                        = wp_get_object_terms($context['post_id'], get_object_taxonomies($context['post_type']), array('fields'=>'tt_ids'));
            $context['term_taxonomy_ids'] = is_wp_error($terms) ? array() : array_map('intval', $terms);
        }
        $context['unload_key'] = 'unload_' . $suffix;
        $context['load_key']   = 'load_' . $suffix;
        $context['valid']      = true;
        return $context;
    }

    /**
     * @param int $id
     * @return array
     */
    private static function postIds($id)
    {
        $ids = apply_filters('wpacu_get_all_assoc_post_ids', $id);
        return array_values(array_unique(array_filter(array_map('absint', array_merge(array($id), is_array($ids) ? $ids : array())))));
    }

    /**
     * @param int $id
     * @return array
     */
    private static function termIds($id)
    {
        return array_values(array_unique(array_filter(array_map('absint', array_merge(array($id), (array)apply_filters('wpacu_plugins_manager_expand_term_taxonomy_ids', array($id)))))));
    }

    /**
     * @param string $taxonomy
     * @return string
     */
    private static function taxonomyValue($taxonomy)
    {
        $common = array('category', 'post_tag', 'product_cat', 'product_tag', 'download_category', 'download_tag');
        return in_array($taxonomy, $common, true) ? $taxonomy . '_all' : $taxonomy;
    }

    /**
     * @param array $context
     * @return array
     */
    public static function controls($context)
    {
        if (empty($context['valid'])) { return array(); }
        $scopes  = array(substr($context['unload_key'], 7)=>$context['values'], 'site_wide'=>null);
        $choices = array();
        if (!empty($context['post_type'])) {
            $scopes['via_post_type'] = array($context['post_type']);
            foreach (get_object_taxonomies($context['post_type'], 'objects') as $taxonomy) {
                if (!$taxonomy->public || !$taxonomy->show_ui) { continue; }
                $choices[$context['post_type'] . ':' . $taxonomy->name] = $taxonomy->labels->name . ' (' . $taxonomy->name . ')';
            }
        }
        if (!empty($context['is_homepage'])) { $scopes['home_page'] = null; }
        if (!empty($context['taxonomy'])) { $scopes['via_tax'] = array(self::taxonomyValue($context['taxonomy'])); }
        if (!empty($context['author_id'])) { $scopes['via_archive'] = array('author'); }
        $out = array();
        foreach (array('unload', 'load') as $action) {
            foreach ($scopes as $suffix => $values) {
                if ($action === 'load' && $suffix === 'site_wide') { continue; }
                $out[$action . '_' . $suffix] = array('kind'=>'fixed', 'values'=>$values);
            }
            if (!empty($context['post_type'])) {
                $out[$action . '_via_post_tax_term'] = array('kind'=>'terms');
                if ($choices) {
                    $out[$action . '_via_post_tax'] = array('kind'=>'taxonomies', 'choices'=>$choices, 'post_type'=>$context['post_type']);
                }
            }
            $out[$action . '_via_regex']          = array('kind'=>'text');
            $out[$action . '_logged_in']          = array('kind'=>'fixed', 'values'=>null);
            $out[$action . '_logged_in_via_role'] = array('kind'=>'roles');
        }
        return $out;
    }

    /**
     * @param array $rule
     * @return array
     */
    public static function statuses($rule)
    {
        $status = isset($rule['status']) ? array_values(array_filter((array)$rule['status'], 'is_string')) : array();
        foreach (array('load_via_regex', 'load_logged_in') as $legacyKey) {
            if (!empty($rule[$legacyKey]['enable'])) { $status[] = $legacyKey; }
        }
        return array_values(array_unique($status));
    }

    /**
     * @param array $rule
     * @param string $key
     * @param array|null $values
     * @return bool
     */
    public static function isSelected($rule, $key, $values)
    {
        if (!in_array($key, self::statuses($rule), true)) { return false; }
        return $values === null || !empty(array_intersect((array)$values, isset($rule[$key]['values']) ? (array)$rule[$key]['values'] : array()));
    }

    /**
     * Saved values exposed by a contextual chooser, without activating dormant payloads.
     * @param array $rule
     * @param string $key
     * @param array $definition
     * @return array
     */
    public static function selectedValues($rule, $key, $definition)
    {
        if (!in_array($key, self::statuses($rule), true)) { return array(); }
        $values = isset($rule[$key]['values']) ? array_filter((array)$rule[$key]['values'], 'is_scalar') : array();
        if ($definition['kind'] === 'taxonomies') {
            $values = array_intersect($values, array_keys($definition['choices']));
        }
        return array_values($values);
    }

    /**
     * Matching fixed scopes not already represented by the page controls.
     * @param array $rule
     * @param array $context
     * @return array
     */
    public static function additionalRules($rule, $context)
    {
        if (empty($context['valid'])) { return array(); }
        $matches = array();
        $scopes  = array();
        if ($context['post_id']) {
            $scopes['via_post'] = array(self::postIds($context['post_id']), __('Selected page', 'wp-asset-clean-up'));
        }
        $controls = self::controls($context);
        foreach (array('unload', 'load') as $action) {
            foreach ($scopes as $suffix => $scope) {
                $key = $action . '_' . $suffix;
                if (!isset($controls[$key]) && self::isSelected($rule, $key, $scope[0])) {
                    $matches[$key] = sprintf($action === 'load' ? __('Load exception: %s', 'wp-asset-clean-up') : __('Unload rule: %s', 'wp-asset-clean-up'), $scope[1]);
                }
            }
        }
        return $matches;
    }

    /**
     * Counts saved controls displayed for this context, not simulated runtime effects.
     * @param array $rule
     * @param array $context
     * @return array
     */
    public static function counts($rule, $context)
    {
        $counts   = array('unload'=>0, 'load'=>0);
        $selected = self::additionalRules($rule, $context);
        foreach (self::controls($context) as $key => $definition) {
            if ($definition['kind'] === 'fixed') {
                $active = self::isSelected($rule, $key, $definition['values']);
            } elseif ($definition['kind'] === 'taxonomies') {
                $active = (bool)self::selectedValues($rule, $key, $definition);
            } else {
                $active = in_array($key, self::statuses($rule), true);
            }
            if ($active) { $selected[$key] = true; }
        }
        foreach ($selected as $key => $unused) {
            $counts[strpos($key, 'unload_') === 0 ? 'unload' : 'load']++;
        }
        return $counts;
    }

    /**
     * @param string $key
     * @param array $context
     * @return string
     */
    public static function controlLabel($key, $context)
    {
        $unload = strpos($key, 'unload_') === 0;
        $suffix = substr($key, $unload ? 7 : 5);
        $prefix = $unload ? __('Unload', 'wp-asset-clean-up') . ' ' : '';
        if ($suffix === 'home_page' && !empty($context['is_homepage'])) {
            return $unload ? __('Unload on this homepage', 'wp-asset-clean-up') : __('On this homepage', 'wp-asset-clean-up');
        }
        if ($suffix === 'via_author_archive') {
            $label = $unload ? __('Unload on this author archive page', 'wp-asset-clean-up') : __('On this author archive page', 'wp-asset-clean-up');
            return $label . (!empty($context['author_slug']) ? ' / ' . $context['author_slug'] : '');
        }
        if ($suffix === 'via_archive' && $context['archive_type'] === 'search') {
            return $unload
                ? __('Unload on this page type (any Search Results page)', 'wp-asset-clean-up')
                : __('On this page type (any Search Results page)', 'wp-asset-clean-up');
        }
        if ($suffix === 'via_archive' && $context['archive_type'] === 'author') {
            return $unload ? __('Unload on All Author Archive Pages', 'wp-asset-clean-up') : __('On All Author Archive Pages', 'wp-asset-clean-up');
        }
        if ($key === 'unload_via_post_type') {
            switch ($context['post_type']) {
                case 'product': return __('Unload plugin on all WooCommerce "Product" pages', 'wp-asset-clean-up');
                case 'download': return __('Unload plugin on all Easy Digital Downloads "Download" pages', 'wp-asset-clean-up');
                default: return sprintf(__('Unload plugin on all pages of "%s" post type', 'wp-asset-clean-up'), $context['post_type']);
            }
        }
        if ($key === 'unload_via_tax') {
            return sprintf(__('Unload plugin on all pages of "%s" taxonomy type', 'wp-asset-clean-up'), $context['taxonomy']);
        }
        if ($suffix === 'via_tax_term' && $context['term_name'] !== '') {
            return sprintf($unload ? __('Unload on this specific %s taxonomy page', 'wp-asset-clean-up') : __('On this specific %s taxonomy page', 'wp-asset-clean-up'), $context['taxonomy']) . ': `' . sanitize_text_field($context['term_name']) . '`';
        }
        if ($suffix === 'via_archive' && strpos($context['archive_type'], 'custom_post_type_archive_') === 0) {
            return sprintf($unload ? __('Unload on this archive page: `%s`', 'wp-asset-clean-up') : __('On this archive page: `%s`', 'wp-asset-clean-up'), substr($context['archive_type'], strlen('custom_post_type_archive_')));
        }
        if ($suffix === 'via_archive' && $context['archive_type'] === 'date') {
            return $unload ? __('Unload on all date archive pages', 'wp-asset-clean-up') : __('On all date archive pages', 'wp-asset-clean-up');
        }
        if ($suffix === 'via_post_tax') {
            return sprintf($unload ? __('Unload plugin on all pages of "%s" post type assigned to any term in these taxonomies', 'wp-asset-clean-up') : __('On all "%s" entries assigned to any term in these taxonomies', 'wp-asset-clean-up'), $context['post_type']);
        }
        if ($key === $context[$unload ? 'unload_key' : 'load_key']) {
            $label = __('on this page', 'wp-asset-clean-up');
        } else {
            $labels = array(
                'site_wide'=>__('site-wide • everywhere', 'wp-asset-clean-up'),
                'home_page'=>__('on the homepage', 'wp-asset-clean-up'),
                'via_post_type'=>sprintf(__('on all pages of "%s" post type', 'wp-asset-clean-up'), $context['post_type']),
                'via_tax'=>sprintf(__('on all "%s" taxonomy archives', 'wp-asset-clean-up'), $context['taxonomy']),
                'via_archive'=>__('on all author archives', 'wp-asset-clean-up')
            );
            $label = isset($labels[$suffix]) ? $labels[$suffix] : '';
        }
        $conditional = array(
            'via_regex'=>__('if the request URI matches any of these rules', 'wp-asset-clean-up'),
            'logged_in'=>__('if the user is logged in', 'wp-asset-clean-up'),
            'logged_in_via_role'=>__('if the logged-in user has any of these roles', 'wp-asset-clean-up')
        );
        if (isset($conditional[$suffix])) { $label = $conditional[$suffix]; }
        if ($suffix === 'via_post_tax_term') {
            return $unload ? sprintf(__('Unload plugin on all pages of "%s" post type assigned to any of these specific taxonomy terms', 'wp-asset-clean-up'), $context['post_type']) : __('On entries assigned to any of these specific taxonomy terms', 'wp-asset-clean-up');
        }
        return ucfirst($prefix . $label);
    }
}
