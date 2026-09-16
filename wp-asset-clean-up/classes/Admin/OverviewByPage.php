<?php
namespace WpAssetCleanUp\Admin;

/** Presentation-only partitioning. Stored rules and POST field names are unchanged. */
class OverviewByPage
{
    public static $rendering = false;
    public static $originalHandles = array();
    private static $groups = array();
    private static $contextRendered = array();

    private static function group($key, $label, $order)
    {
        if (!isset(self::$groups[$key])) {
            self::$groups[$key] = array('label' => $label, 'order' => $order, 'handles' => array(), 'plugins_with_rules' => array(), 'page_options_results' => array(), 'critical_css_overview' => array());
        }
        return $key;
    }

    public static function scope($kind, $value = '')
    {
        if ($kind === 'post') {
            if ((int)$value && get_option('show_on_front') === 'page' && (int)get_option('page_on_front') === (int)$value) {
                return self::scope('home_page');
            }
            $post = get_post((int)$value);
            return $post ? self::scope('post_type', $post->post_type) : self::scope('missing');
        }
        if ($kind === 'post_type') {
            $type = get_post_type_object($value);
            $label = $type && !empty($type->labels->name) ? $type->labels->name : $value;
            return self::group('post_type:' . $value, $label, $value === 'post' ? 20 : ($value === 'page' ? 21 : 22));
        }
        if ($kind === 'term' || $kind === 'term_taxonomy') {
            $term = $kind === 'term' ? get_term((int)$value) : get_term_by('term_taxonomy_id', (int)$value);
            return $term && !is_wp_error($term) ? self::scope('taxonomy', $term->taxonomy) : self::scope('missing');
        }
        if ($kind === 'taxonomy' || $kind === 'taxonomy_all') {
            // Plugins Manager stores all-archive references with an internal "_all" suffix.
            $taxonomyName = $kind === 'taxonomy_all' ? substr($value, 0, -4) : $value;
            $tax = get_taxonomy($taxonomyName);
            $fallbackLabels = array(
                'category' => __('Categories', 'wp-asset-clean-up'),
                'post_tag' => __('Tags', 'wp-asset-clean-up'),
                'product_cat' => __('Product Categories', 'wp-asset-clean-up'),
                'product_tag' => __('Product Tags', 'wp-asset-clean-up'),
                'download_category' => __('Download Categories', 'wp-asset-clean-up'),
                'download_tag' => __('Download Tags', 'wp-asset-clean-up')
            );
            $label = $tax && !empty($tax->labels->name) ? $tax->labels->name : (isset($fallbackLabels[$taxonomyName]) ? $fallbackLabels[$taxonomyName] : $taxonomyName);
            if ($kind === 'taxonomy_all') {
                $label = sprintf(__('%s — All archives', 'wp-asset-clean-up'), $label);
            }
            $groupKey = self::group('taxonomy:' . $value, $label, 30);
            $menuPaths = array(
                'category' => __('Posts → Categories', 'wp-asset-clean-up'),
                'post_tag' => __('Posts → Tags', 'wp-asset-clean-up'),
                'product_cat' => __('Products → Categories (WooCommerce)', 'wp-asset-clean-up'),
                'product_tag' => __('Products → Tags (WooCommerce)', 'wp-asset-clean-up'),
                'download_category' => __('Downloads → Categories (Easy Digital Downloads)', 'wp-asset-clean-up'),
                'download_tag' => __('Downloads → Tags (Easy Digital Downloads)', 'wp-asset-clean-up')
            );
            self::$groups[$groupKey]['taxonomy_help'] = array(
                'location' => isset($menuPaths[$taxonomyName]) ? $menuPaths[$taxonomyName] : $label,
                'description' => $kind === 'taxonomy_all'
                    ? __('These rules target every archive page in this taxonomy. Each archive lists the posts or items assigned to a category or tag; it is not an individual post or product page.', 'wp-asset-clean-up')
                    : __('These rules target specific archive pages in this taxonomy. Each archive lists the posts or items assigned to a category or tag; check each rule for its exact target.', 'wp-asset-clean-up')
            );
            return $groupKey;
        }
        if (strpos($kind, 'custom_post_type_archive_') === 0) {
            $name = substr($kind, strlen('custom_post_type_archive_'));
            $type = get_post_type_object($name);
            return self::group($kind, sprintf(__('%s — Archives', 'wp-asset-clean-up'), $type && !empty($type->labels->name) ? $type->labels->name : $name), 35);
        }
        $known = array(
            'home_page' => array(__('Homepage', 'wp-asset-clean-up'), 10), 'homepage' => array(__('Homepage', 'wp-asset-clean-up'), 10),
            'author' => array(__('Author Archives', 'wp-asset-clean-up'), 40), 'user' => array(__('Author Archives', 'wp-asset-clean-up'), 40),
            'date' => array(__('Date Archives', 'wp-asset-clean-up'), 50), 'search' => array(__('Search Results', 'wp-asset-clean-up'), 60),
            '404' => array(__('404 Not Found', 'wp-asset-clean-up'), 70), 'global' => array(__('Global Rules', 'wp-asset-clean-up'), 80),
            'other' => array(__('Other Conditions', 'wp-asset-clean-up'), 90), 'missing' => array(__('Deleted or Missing Pages', 'wp-asset-clean-up'), 95),
            'dashboard' => array(__('WordPress Dashboard', 'wp-asset-clean-up'), 100)
        );
        if ($kind === 'homepage') { $kind = 'home_page'; }
        if ($kind === 'user') { $kind = 'author'; }
        $item = isset($known[$kind]) ? $known[$kind] : $known['other'];
        return self::group(isset($known[$kind]) ? $kind : 'other', $item[0], $item[1]);
    }

    private static function put($group, $type, $handle, $path, $value)
    {
        $target =& self::$groups[$group]['handles'][$type][$handle];
        foreach ($path as $part) { $target =& $target[$part]; }
        $target = $value;
    }

    public static function partition($data)
    {
        self::$groups = array();
        foreach (isset($data['handles']) ? $data['handles'] : array() as $type => $handles) {
            foreach ($handles as $handle => $rules) {
                foreach ($rules as $key => $value) {
                    if (in_array($key, array('unload_on_this_page', 'load_exception_on_this_page', 'script_attrs', 'attrs_no_load'), true)) {
                        foreach ($value as $scope => $items) {
                            if (in_array($scope, array('post', 'term', 'user'), true)) {
                                foreach ($items as $index => $item) {
                                    $id = in_array($key, array('script_attrs', 'attrs_no_load'), true) ? $index : $item;
                                    self::put(self::scope($scope, $id), $type, $handle, array($key, $scope, $index), $item);
                                }
                            } else {
                                self::put(self::scope($scope), $type, $handle, array($key, $scope), $items);
                            }
                        }
                    } elseif ($key === 'unload_bulk') {
                        foreach ($value as $scope => $items) {
                            if ($scope === 'post_type' || $scope === 'taxonomy') {
                                foreach ($items as $index => $item) {
                                    self::put(self::scope($scope === 'taxonomy' ? 'taxonomy_all' : $scope, $scope === 'taxonomy' ? $item . '_all' : $item), $type, $handle, array($key, $scope, $index), $item);
                                }
                            } elseif ($scope === 'post_type_via_tax') {
                                foreach ($items as $postType => $terms) {
                                    self::put(self::scope('post_type', $postType), $type, $handle, array($key, $scope, $postType), $terms);
                                }
                            } else {
                                self::put(self::scope($scope), $type, $handle, array($key, $scope), $items);
                            }
                        }
                    } elseif (in_array($key, array('load_exception_post_type', 'load_exception_via_tax_type'), true)) {
                        foreach ($value as $index => $item) {
                            self::put(self::scope($key === 'load_exception_post_type' ? 'post_type' : 'taxonomy_all', $key === 'load_exception_post_type' ? $item : $item . '_all'), $type, $handle, array($key, $index), $item);
                        }
                    } elseif ($key === 'load_exception_post_type_via_tax') {
                        foreach ($value as $postType => $terms) {
                            self::put(self::scope('post_type', $postType), $type, $handle, array($key, $postType), $terms);
                        }
                    } else {
                        $scope = strpos($key, 'home_page') !== false ? 'home_page' : ($key === 'load_exception_via_author_type' ? 'author' : (strpos($key, 'regex') !== false || $key === 'load_it_logged_in' ? 'other' : 'global'));
                        self::put(self::scope($scope), $type, $handle, array($key), $value);
                    }
                }
            }
        }
        foreach (isset($data['page_options_results']['posts']) ? $data['page_options_results']['posts'] : array() as $row) {
            self::$groups[self::scope('post', $row['post_id'])]['page_options_results']['posts'][] = $row;
        }
        if (!empty($data['page_options_results']['homepage']['options'])) {
            self::$groups[self::scope('home_page')]['page_options_results']['homepage'] = $data['page_options_results']['homepage'];
        }
        // Plugins Manager rules retain their original status, values and inactive-rule metadata.
        foreach (isset($data['plugins_with_rules']) ? $data['plugins_with_rules'] : array() as $location => $plugins) {
            foreach ($plugins as $index => $plugin) {
                if ($location !== 'plugins') {
                    self::$groups[self::scope('dashboard')]['plugins_with_rules'][$location][$index] = $plugin;
                    continue;
                }
                $rules = $plugin['rules'];
                $keys = array_unique(array_merge((array)$rules['status'], array_keys($rules)));
                foreach ($keys as $key) {
                    if (strpos($key, 'load_') !== 0 && strpos($key, 'unload_') !== 0) { continue; }
                    // Match the existing renderer: site-wide plugin unloading supersedes other unload conditions.
                    if ($key !== 'unload_site_wide' && strpos($key, 'unload_') === 0 && in_array('unload_site_wide', (array)$rules['status'], true)) { continue; }
                    $parts = array();
                    $rule = isset($rules[$key]) ? $rules[$key] : array();
                    if (isset($rule['values']) && is_array($rule['values'])) {
                        foreach ($rule['values'] as $i => $v) {
                            $scope = 'other';
                            if (substr($key, -14) === '_via_post_type') { $scope = self::scope('post_type', $v); }
                            elseif (substr($key, -9) === '_via_post') { $scope = self::scope('post', $v); }
                            elseif (substr($key, -13) === '_via_tax_term') { $scope = self::scope('term_taxonomy', $v); }
                            elseif (substr($key, -8) === '_via_tax') { $scope = self::scope(substr($v, -4) === '_all' ? 'taxonomy_all' : 'taxonomy', $v); }
                            elseif (strpos($key, 'author_archive') !== false) { $scope = self::scope('author'); }
                            elseif (substr($key, -12) === '_via_archive') { $scope = self::scope($v); }
                            else { $scope = self::scope('other'); }
                            $parts[$scope]['values'][$i] = $v;
                        }
                    } else {
                        $scope = strpos($key, 'home_page') !== false ? 'home_page' : (strpos($key, 'site_wide') !== false ? 'global' : 'other');
                        $parts[self::scope($scope)] = $rule;
                    }
                    foreach ($parts as $group => $part) {
                        if (!isset(self::$groups[$group]['plugins_with_rules'][$location][$index])) {
                            $copy = $plugin;
                            $copy['_overview_has_unloads'] = (bool)array_filter((array)$rules['status'], static function ($status) use ($rules) {
                                return strpos($status, 'unload_') === 0
                                    && !in_array($status, isset($rules['_wpacu_overview_inactive_rule_keys']) ? (array)$rules['_wpacu_overview_inactive_rule_keys'] : array(), true);
                            });
                            $copy['rules'] = array_filter($rules, static function ($key) { return $key !== 'status' && strpos($key, 'load_') !== 0 && strpos($key, 'unload_') !== 0; }, ARRAY_FILTER_USE_KEY);
                            $copy['rules']['status'] = array();
                            if (isset($rules['_wpacu_overview_inactive_rule_keys'])) { $copy['rules']['_wpacu_overview_inactive_rule_keys'] = $rules['_wpacu_overview_inactive_rule_keys']; }
                            self::$groups[$group]['plugins_with_rules'][$location][$index] = $copy;
                        }
                        $target =& self::$groups[$group]['plugins_with_rules'][$location][$index]['rules'];
                        if (in_array($key, (array)$rules['status'], true)) { $target['status'][] = $key; }
                        $target[$key] = array_replace($rule, $part);
                    }
                }
            }
        }
        foreach (isset($data['critical_css_overview']['locations']) ? $data['critical_css_overview']['locations'] : array() as $locationKey => $location) {
            foreach ($location['rules'] as $rule) {
                if (!empty($rule['object_id']) && $rule['storage_type'] === 'post_meta') { $group = self::scope('post', $rule['object_id']); }
                elseif (!empty($rule['object_id']) && $rule['storage_type'] === 'term_meta') { $group = self::scope('term', $rule['object_id']); }
                elseif (!empty($rule['object_id']) && $rule['storage_type'] === 'user_meta') { $group = self::scope('author'); }
                elseif (in_array($locationKey, array('posts', 'pages', 'media'), true)) { $map = array('posts' => 'post', 'pages' => 'page', 'media' => 'attachment'); $group = self::scope('post_type', $map[$locationKey]); }
                elseif ($locationKey === 'category' || $locationKey === 'tag') { $group = self::scope('taxonomy', $locationKey === 'tag' ? 'post_tag' : 'category'); }
                elseif (strpos($locationKey, 'custom_post_type_archive_') === 0) { $group = self::scope($locationKey); }
                elseif (strpos($locationKey, 'custom_post_type_') === 0) { $group = self::scope('post_type', substr($locationKey, strlen('custom_post_type_'))); }
                elseif (strpos($locationKey, 'custom_taxonomy_') === 0) { $group = self::scope('taxonomy', substr($locationKey, strlen('custom_taxonomy_'))); }
                else { $group = self::scope($locationKey === '404_not_found' ? '404' : $locationKey); }
                $target =& self::$groups[$group]['critical_css_overview'];
                if (!isset($target['locations'][$locationKey])) { $target['locations'][$locationKey] = $location; $target['locations'][$locationKey]['rules'] = array(); }
                $target['locations'][$locationKey]['rules'][] = $rule;
                $target['rules_count'] = isset($target['rules_count']) ? $target['rules_count'] + 1 : 1;
                $countKey = $rule['scope'] === 'general' ? 'general_count' : 'specific_count';
                $target[$countKey] = isset($target[$countKey]) ? $target[$countKey] + 1 : 1;
            }
        }
        uasort(self::$groups, static function ($a, $b) { return $a['order'] === $b['order'] ? strnatcasecmp($a['label'], $b['label']) : $a['order'] - $b['order']; });
        return self::$groups;
    }

    public static function contextualChanges($handleData, $outputs)
    {
        $key = $handleData['asset_type'] . '|' . $handleData['handle'];
        unset($outputs['has_redundant_unload_rules'], $outputs['load_exception_notice']);
        if (!isset(self::$contextRendered[$key])) {
            self::$contextRendered[$key] = true;
            $original = self::$originalHandles[$handleData['asset_type']][$handleData['handle']];
            $original['asset_type'] = $handleData['asset_type'];
            $original['handle'] = $handleData['handle'];
            self::$rendering = false;
            try { $complete = Overview::renderHandleChangesOutput($original); }
            finally { self::$rendering = true; }
            foreach (array('has_redundant_unload_rules', 'load_exception_notice') as $notice) {
                if (isset($complete[$notice])) { $outputs[$notice] = $complete[$notice]; }
            }
        }
        return $outputs;
    }

    public static function render($originalData)
    {
        self::$contextRendered = array();
        self::$originalHandles = isset($originalData['handles']) ? $originalData['handles'] : array();
        $groups = self::partition($originalData);
        self::$rendering = true;
        echo '<div class="wpacu-overview-page-groups">';
        echo '<p>' . esc_html__('Rules are grouped by the pages they target. Site-wide settings also apply to these pages unless an exception is set. Conditions on posts assigned to a category belong to the post type, not to the category archive.', 'wp-asset-clean-up') . '</p>';
        foreach ($groups as $key => $group) {
            $data = array_replace($originalData, $group);
            $groupCount = Overview::countPagesWithOptions($group['page_options_results']);
            foreach ($group['handles'] as $handles) { $groupCount += count($handles); }
            foreach ($group['plugins_with_rules'] as $plugins) { $groupCount += count($plugins); }
            $groupCount += isset($group['critical_css_overview']['rules_count']) ? (int)$group['critical_css_overview']['rules_count'] : 0;
            echo '<section data-wpacu-page-group="' . esc_attr($key) . '" data-wpacu-group-count="' . (int)$groupCount . '"><h2 data-wpacu-group-label="' . esc_attr($group['label']) . '" style="margin:28px 0 12px;padding:12px;background:#eaf3f5;border-left:4px solid #008c99;">' . esc_html($group['label']);
            if (!empty($group['taxonomy_help'])) {
                $helpId = 'wpacu-taxonomy-help-' . substr(md5($key), 0, 8);
                echo ' <span class="wpacu-overview-taxonomy-help" tabindex="0" role="button" aria-label="' . esc_attr__('About these archive pages', 'wp-asset-clean-up') . '" aria-describedby="' . esc_attr($helpId) . '"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><span class="wpacu-overview-taxonomy-tooltip" role="tooltip" id="' . esc_attr($helpId) . '"><strong>' . esc_html($group['taxonomy_help']['location']) . '</strong><span>' . esc_html($group['taxonomy_help']['description']) . '</span></span></span>';
            }
            echo '</h2>';
            ob_start();
            foreach (array('styles', 'scripts') as $type) {
                if (!empty($data['handles'][$type])) { include WPACU_PLUGIN_DIR . '/templates/_admin-page-overview-areas/_' . $type . '.php'; }
            }
            if (!empty($data['critical_css_overview'])) { include WPACU_PLUGIN_DIR . '/templates/_admin-page-overview-areas/_critical-css.php'; }
            if (!empty($data['plugins_with_rules'])) { include WPACU_PLUGIN_DIR . '/templates/_admin-page-overview-areas/_plugins-manager.php'; }
            if (!empty($data['page_options_results'])) { include WPACU_PLUGIN_DIR . '/templates/_admin-page-overview-areas/_page-options.php'; }
            $html = ob_get_clean();
            // Multiple groups can mention the same asset. Keep their UI IDs unique.
            preg_match_all('/\bid="([^"]+)"/', $html, $matches);
            $replace = array();
            foreach ($matches[1] as $id) { $replace[$id] = $id . '-group-' . substr(md5($key), 0, 8); }
            // Rewrite references only; class names and text can contain the same ID prefix.
            $html = preg_replace_callback('/(?<![\w-])(id|for|href|aria-controls|aria-labelledby|aria-describedby|data-wpacu-target|data-wpacu-modal-target)="([^"]*)"/', static function ($match) use ($replace) {
                $attribute = $match[1];
                $value = $match[2];
                if ($attribute === 'href') {
                    if (isset($value[0]) && $value[0] === '#' && isset($replace[substr($value, 1)])) {
                        $value = '#' . $replace[substr($value, 1)];
                    }
                } elseif (strpos($attribute, 'aria-') === 0) {
                    $value = preg_replace_callback('/\S+/', static function ($token) use ($replace) {
                        return isset($replace[$token[0]]) ? $replace[$token[0]] : $token[0];
                    }, $value);
                } elseif (isset($replace[$value])) {
                    $value = $replace[$value];
                } elseif ($attribute === 'data-wpacu-modal-target' && substr($value, -7) === '-target' && isset($replace[substr($value, 0, -7)])) {
                    $value = $replace[substr($value, 0, -7)] . '-target';
                }
                return $attribute . '="' . $value . '"';
            }, $html);
            $html = preg_replace('/id="(wpacu-plugins-load-manager-wrap|wpacu-page-options-wrap|wpacu-critical-css-overview)-group-[a-f0-9]+"/', '$0 data-wpacu-overview-original-id="$1"', $html);
            echo $html;
            echo '</section>';
        }
        $data = $originalData;
        include WPACU_PLUGIN_DIR . '/templates/_admin-page-overview-areas/_special-settings.php';
        echo '</div>';
        self::$rendering = false;
    }
}
