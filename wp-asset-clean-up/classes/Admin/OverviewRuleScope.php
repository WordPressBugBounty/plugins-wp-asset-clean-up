<?php
namespace WpAssetCleanUp\Admin;

/** Classify the saved target, not how many URLs happen to match it today. */
class OverviewRuleScope
{
    public static function classify($info, $key, $value = null, $parent = '')
    {
        if (!empty($info['page_option'])) { return 'page'; }
        if (in_array($key, array('inactive_rules', 'unload_redundant', 'load_exceptions_clear_all', 'clear_all_rules'), true)) { return 'shared'; }
        if (in_array($key, array(
            'unload_on_home_page', 'load_exception_on_home_page', 'home_page_script_attr',
            'unload_on_this_page', 'unload_on_this_post', 'load_exception_on_this_post', 'load_exception_on_this_user',
            'unload_on_taxonomy_page', 'load_exception_on_this_page_tax_id',
            'unload_on_these_author_pages', 'load_exception_on_this_page_author_id',
            'post_script_attr', 'taxonomy_term_script_attr', 'author_archive_script_attr',
            'post_script_attr_no_load', 'taxonomy_term_script_attr_no_load', 'author_archive_script_attr_no_load',
            'unload_home_page', 'load_home_page', 'unload_via_post', 'load_via_post',
            'unload_via_tax_term', 'load_via_tax_term', 'unload_via_author_archive', 'load_via_author_archive'
        ), true)) { return 'page'; }
        if ($key === 'bulk_script_attr_no_load' && $parent === 'home_page') { return 'page'; }
        if (in_array($key, array(
            'unload_site_wide', 'site_wide_script_attr', 'preloads',
            'positions', 'media_query', 'note', 'ignore_child'
        ), true)) { return 'sitewide'; }
        return 'bulk';
    }

    public static function wrap($output, $info, $key, $value = null, $parent = '')
    {
        if ($output === '') { return $output; }
        $scope = self::classify($info, $key, $value, $parent);
        $state = 'active';
        if (!empty($info['handle']) && !empty($info['asset_type'])) {
            $rules = $info;
            if (OverviewByPage::$rendering && isset(OverviewByPage::$originalHandles[$info['asset_type']][$info['handle']])) {
                $rules = OverviewByPage::$originalHandles[$info['asset_type']][$info['handle']];
            }
            $hasUnload = false;
            foreach (array_keys($rules) as $ruleKey) {
                if (strpos($ruleKey, 'unload') === 0) { $hasUnload = true; break; }
            }
            // A broader unload can make another unload redundant without disabling it.
            if (strpos($key, 'load') === 0 && !$hasUnload) {
                $state = 'inactive';
            }
        }
        $attributes = ' data-wpacu-rule-scope="' . $scope . '" data-wpacu-rule-state="' . $state . '"';
        if (preg_match('/^<(ul|label)\b/', $output)) {
            return preg_replace('/^<(ul|label)\b/', '<$1' . $attributes, $output, 1);
        }
        return '<span' . $attributes . ' style="display:contents;">' . $output . '</span>';
    }
}
