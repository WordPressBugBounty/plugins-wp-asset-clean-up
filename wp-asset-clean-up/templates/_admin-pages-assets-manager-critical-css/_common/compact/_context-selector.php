<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\AssetsManagerAdmin;
use WpAssetCleanUp\Admin\CriticalCssAdmin;
use WpAssetCleanUp\Admin\MiscAdmin;

if ( ! isset($data) ) {
    exit;
}

$baseQueryArgs = array(
    'page'           => WPACU_PLUGIN_ID . '_assets_manager',
    'wpacu_sub_page' => 'manage_critical_css'
);

$buildContextUrl = static function($wpacuFor, $extraArgs = array()) use ($baseQueryArgs) {
    return add_query_arg(
        array_merge($baseQueryArgs, array('wpacu_for' => $wpacuFor), $extraArgs),
        admin_url('admin.php')
    );
};

$customPostTypes         = MiscAdmin::getCustomPostTypesList();
$customPostTypeArchives  = AssetsManagerAdmin::getCustomPostTypeArchives();
$customTaxonomies        = MiscAdmin::getCustomTaxonomyList();
$currentFor              = isset($data['for']) ? $data['for'] : 'homepage';
$currentTaxonomy         = isset($data['chosen_taxonomy']) ? $data['chosen_taxonomy'] : '';

$contextGroups = array(
    __('Main', 'wp-asset-clean-up') => array(
        array(
            'label'    => __('Homepage', 'wp-asset-clean-up'),
            'url'      => $buildContextUrl('homepage'),
            'selected' => $currentFor === 'homepage'
        )
    ),
    __('WordPress content', 'wp-asset-clean-up') => array(
        array(
            'label'    => __('Posts', 'wp-asset-clean-up'),
            'url'      => $buildContextUrl('posts'),
            'selected' => $currentFor === 'posts'
        ),
        array(
            'label'    => __('Pages', 'wp-asset-clean-up'),
            'url'      => $buildContextUrl('pages'),
            'selected' => $currentFor === 'pages'
        ),
        array(
            'label'    => __('Media attachment pages', 'wp-asset-clean-up'),
            'url'      => $buildContextUrl('media_attachment'),
            'selected' => $currentFor === 'media_attachment'
        )
    ),
    __('Custom content', 'wp-asset-clean-up') => array(),
    __('WordPress taxonomy archives', 'wp-asset-clean-up') => array(
        array(
            'label'    => __('Categories', 'wp-asset-clean-up'),
            'url'      => $buildContextUrl('category'),
            'selected' => $currentFor === 'category'
        ),
        array(
            'label'    => __('Tags', 'wp-asset-clean-up'),
            'url'      => $buildContextUrl('tag'),
            'selected' => $currentFor === 'tag'
        )
    ),
    __('Custom taxonomies', 'wp-asset-clean-up') => array(),
    __('Other page types', 'wp-asset-clean-up') => array(
        array(
            'label'    => __('Search results', 'wp-asset-clean-up'),
            'url'      => $buildContextUrl('search'),
            'selected' => $currentFor === 'search'
        ),
        array(
            'label'    => __('Author archives', 'wp-asset-clean-up'),
            'url'      => $buildContextUrl('author'),
            'selected' => $currentFor === 'author'
        ),
        array(
            'label'    => __('Date archives', 'wp-asset-clean-up'),
            'url'      => $buildContextUrl('date'),
            'selected' => $currentFor === 'date'
        ),
        array(
            'label'    => __('404 Not Found', 'wp-asset-clean-up'),
            'url'      => $buildContextUrl('404_not_found'),
            'selected' => $currentFor === '404_not_found'
        )
    ),
    __('Advanced', 'wp-asset-clean-up') => array(
        array(
            'label'    => __('Via Code', 'wp-asset-clean-up'),
            'url'      => $buildContextUrl('via_code'),
            'selected' => $currentFor === 'via_code'
        )
    )
);

$customPostTypesGroupLabel = __('Custom content', 'wp-asset-clean-up');

if ( ! empty($customPostTypes) || ! empty($customPostTypeArchives) ) {
    $currentPostTypeView = isset($data['critical_css_post_type_view']) ? $data['critical_css_post_type_view'] : '';
    $defaultPostTypeView = in_array($currentPostTypeView, array('singular', 'archives'), true)
        ? $currentPostTypeView
        : ( ! empty($customPostTypes) ? 'singular' : 'archives' );

    $contextGroups[$customPostTypesGroupLabel][] = array(
        'label'    => __('Custom Post Types', 'wp-asset-clean-up'),
        'url'      => CriticalCssAdmin::getCustomPostTypesManagementUrl($defaultPostTypeView),
        'selected' => in_array($currentFor, array('custom_post_types', 'custom_post_type_archives'), true)
    );
}

$customTaxonomiesGroupLabel = __('Custom taxonomies', 'wp-asset-clean-up');

foreach ($customTaxonomies as $taxonomyKey => $unusedTaxonomyValue) {
    $taxonomyObject = get_taxonomy($taxonomyKey);
    $taxonomyLabel  = ($taxonomyObject && isset($taxonomyObject->labels->name))
        ? $taxonomyObject->labels->name
        : $taxonomyKey;

    $contextGroups[$customTaxonomiesGroupLabel][] = array(
        'label'    => $taxonomyLabel,
        'url'      => $buildContextUrl('custom_taxonomies', array('wpacu_current_taxonomy' => $taxonomyKey)),
        'selected' => $currentFor === 'custom_taxonomies' && $currentTaxonomy === $taxonomyKey
    );
}
?>
<div class="wpacu-critical-css-context-bar">
    <div class="wpacu-critical-css-context-choice">
        <label for="wpacu-critical-css-context-choice">
            <?php esc_html_e('Page type', 'wp-asset-clean-up'); ?>
        </label>

        <select id="wpacu-critical-css-context-choice">
            <?php foreach ($contextGroups as $groupLabel => $contextOptions) {
                if (empty($contextOptions)) {
                    continue;
                }
                ?>
                <optgroup label="<?php echo esc_attr($groupLabel); ?>">
                    <?php foreach ($contextOptions as $contextOption) { ?>
                        <option value="<?php echo esc_url($contextOption['url']); ?>"
                            <?php selected($contextOption['selected']); ?>>
                            <?php echo esc_html($contextOption['label']); ?>
                        </option>
                    <?php } ?>
                </optgroup>
            <?php } ?>
        </select>
    </div>

    <div class="wpacu-critical-css-context-help">
        <span><?php esc_html_e('Individual rules take priority over the general rule.', 'wp-asset-clean-up'); ?></span>
        <a target="_blank" href="https://www.assetcleanup.com/docs/?p=608">
            <?php esc_html_e('Critical CSS guide', 'wp-asset-clean-up'); ?>
            <span class="dashicons dashicons-external" aria-hidden="true"></span>
        </a>
    </div>
</div>
