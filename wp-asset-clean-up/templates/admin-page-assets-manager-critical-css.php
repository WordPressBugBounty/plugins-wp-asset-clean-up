<?php

use WpAssetCleanUp\Admin\CriticalCssAdmin;
use WpAssetCleanUp\Admin\MiscAdmin;
use WpAssetCleanUp\AssetsManager;
use WpAssetCleanUp\Misc;

/*
 * No direct access to this file
 */
if ( ! isset($data) ) {
    exit;
}

$criticalCssIsGlobalDisabled = $data['wpacu_settings']['critical_css_status'] === 'off';

$criticalCssConfigJson = get_option(WPACU_PLUGIN_ID . '_critical_css_config');
$criticalCssConfig     = json_decode($criticalCssConfigJson, true);

if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
    $criticalCssConfig = array(); // JSON has to be valid
}

$allEnabledLocations = ! empty($criticalCssConfig) ? CriticalCssAdmin::getAllEnabledLocations($criticalCssConfig) : array();

$data['critical_css_tabs_all_enabled_locations'] = $allEnabledLocations;
$data['critical_css_config'] = $criticalCssConfig;

if ($criticalCssIsGlobalDisabled) {
    ?>
    <p style="color: #cc0000"><span class="dashicons dashicons-warning"></span> Critical CSS is globally deactivated from <a style="text-decoration: underline; color: inherit;" target="_blank" href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_settings&wpacu_selected_tab_area=wpacu-setting-optimize-css#wpacu-critical-css-status')); ?>"><strong>"Settings" -&gt; "Optimize CSS" &gt; "Critical CSS Status"</strong></a>, thus any change done below would be saved, but not take effect in the front-end view, unless you re-activate it.</p>
    <?php
}

if (in_array($data['for'], array('posts', 'pages', 'custom_post_types'))) {
    ?>
    <div style="background: white; border: 1px solid #cdcdcd; padding: 10px; margin: 0 0 10px;"><p style="margin: 0;"><strong>Note:</strong> The changes below apply for page groups such as posts (the blog articles), pages (e.g. About, Contact), etc. For most websites, this works fine as the layout's styling (especially the above the fold where the critical CSS applies) is the same. However, there are sometimes exceptions (e.g. a landing page that has been customised differently) and for this, you can use the "Custom Pages" tab.</p></div>
    <?php
} else {
    ?>
    <div style="padding: 5px; margin: 0;">
        <ul style="display: inline-block; margin: 0;">
            <li style="float: left; margin-right: 10px;"><a target="_blank" style="text-decoration: none;" href="https://www.assetcleanup.com/docs/?p=608"><span class="dashicons dashicons-editor-help"></span> What's critical CSS &amp; and how to implement it?</a></li>
        </ul>
    </div>
    <?php
}
?>

<nav id="wpacu-critical-css-manager-tab-menu" class="wpacu-nav-tab-wrapper wpacu-nav-critical-css-manager">
    <?php
    $classToAppend = CriticalCssAdmin::classToAppendToCriticalCssNavTab($data, 'homepage');
    ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=homepage')); ?>"
       class="wpacu-nav-tab <?php echo $classToAppend; ?>">
        <?php _e('Homepage', 'wp-asset-clean-up'); ?>
        <span class="wpacu-circle-status"></span>
    </a>

    <?php
    $classToAppend = CriticalCssAdmin::classToAppendToCriticalCssNavTab($data, 'posts');
    ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=posts')); ?>"
       class="wpacu-nav-tab <?php echo $classToAppend; ?>">
        <?php _e('Posts', 'wp-asset-clean-up'); ?>
        <span class="wpacu-circle-status"></span>
    </a>

    <?php
    $classToAppend = CriticalCssAdmin::classToAppendToCriticalCssNavTab($data, 'pages');
    ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=pages')); ?>"
       class="wpacu-nav-tab <?php echo $classToAppend; ?>">
        <?php _e('Pages', 'wp-asset-clean-up'); ?>
        <span class="wpacu-circle-status"></span>
    </a>

    <?php
    $classToAppend = CriticalCssAdmin::classToAppendToCriticalCssNavTab($data, 'custom_post_types');
    ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=custom_post_types')); ?>"
       class="wpacu-nav-tab <?php echo $classToAppend; ?>">
        <?php _e('Custom Post Types', 'wp-asset-clean-up'); ?>
        <span class="wpacu-circle-status"></span>
    </a>

    <?php
    $classToAppend = CriticalCssAdmin::classToAppendToCriticalCssNavTab($data, 'media_attachment');
    ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=media_attachment')); ?>"
       class="wpacu-nav-tab <?php echo $classToAppend; ?>">
        <?php _e('Media', 'wp-asset-clean-up'); ?>
        <span class="wpacu-circle-status"></span>
    </a>

    <?php
    $classToAppend = CriticalCssAdmin::classToAppendToCriticalCssNavTab($data, 'category');
    ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=category')); ?>"
       class="wpacu-nav-tab <?php echo $classToAppend; ?>">
        <?php _e('Category', 'wp-asset-clean-up'); ?>
        <span class="wpacu-circle-status"></span>
    </a>

    <?php
    $classToAppend = CriticalCssAdmin::classToAppendToCriticalCssNavTab($data, 'tag');
    ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=tag')); ?>"
       class="wpacu-nav-tab <?php echo $classToAppend; ?>">
        <?php _e('Tag', 'wp-asset-clean-up'); ?>
        <span class="wpacu-circle-status"></span>
    </a>

    <?php
    $classToAppend = CriticalCssAdmin::classToAppendToCriticalCssNavTab($data, 'custom_taxonomies');
    ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=custom_taxonomies')); ?>"
       class="wpacu-nav-tab <?php echo $classToAppend; ?>">
        <?php _e('Custom Taxonomy', 'wp-asset-clean-up'); ?>
        <span class="wpacu-circle-status"></span>
    </a>

    <?php
    $classToAppend = CriticalCssAdmin::classToAppendToCriticalCssNavTab($data, 'search');
    ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=search')); ?>"
       class="wpacu-nav-tab <?php echo $classToAppend; ?>">
        <?php _e('Search', 'wp-asset-clean-up'); ?>
        <span class="wpacu-circle-status"></span>
    </a>

    <?php
    $classToAppend = CriticalCssAdmin::classToAppendToCriticalCssNavTab($data, 'author');
    ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=author')); ?>"
       class="wpacu-nav-tab <?php echo $classToAppend; ?>">
        <?php _e('Author', 'wp-asset-clean-up'); ?>
        <span class="wpacu-circle-status"></span>
    </a>

    <?php
    $classToAppend = CriticalCssAdmin::classToAppendToCriticalCssNavTab($data, 'date');
    ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=date')); ?>"
       class="wpacu-nav-tab <?php echo $classToAppend; ?>">
        <?php _e('Date', 'wp-asset-clean-up'); ?>
        <span class="wpacu-circle-status"></span>
    </a>

    <?php
    $classToAppend = CriticalCssAdmin::classToAppendToCriticalCssNavTab($data, '404_not_found');
    ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=404_not_found')); ?>"
       class="wpacu-nav-tab <?php echo $classToAppend; ?>">
        <?php _e('404 Not Found', 'wp-asset-clean-up'); ?>
        <span class="wpacu-circle-status"></span>
    </a>

    <a href="<?php echo esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=via_code')); ?>"
       class="wpacu-nav-tab <?php if ($data['for'] === 'via_code') { ?>wpacu-nav-tab-active<?php } ?>"
       style="padding: 6px 12px 6px 10px;">
            <span class="dashicons dashicons-editor-code"
                  style="vertical-align: middle; margin-right: -4px; margin-top: -1px;"></span>&nbsp;
        <?php _e('Via Code', 'wp-asset-clean-up'); ?>
    </a>
</nav>

<div class="wpacu_clearfix"></div>

<?php
if ( ! AssetsManager::instance()->currentUserCanViewAssetsList() ) {
?>
    <div class="wpacu-error" style="padding: 10px;">
        <?php echo sprintf(__('Only the administrators listed here can manage the critical CSS: %s"Settings" &#10141; "Plugin Usage Preferences" &#10141; "Allow managing assets to:"%s. If you believe you should have access to this page, you can add yourself to that list.', 'wp-asset-clean-up'), '<a target="_blank" href="'.esc_url(admin_url('admin.php?page=wpassetcleanup_settings&wpacu_selected_tab_area=wpacu-setting-plugin-usage-settings')).'">', '</a>'); ?>
    </div>
    <?php
} else {
    if (isset($data['for']) && $data['for']) {
        $data['show_critical_css_options'] = true;  // default (e.g. in specific situations, do not show the form to be submitted)
        $data['show_no_records_notice']    = false; // default (e.g. show a notice of no posts found)
        $locationKey                       = false;

        if ($data['for'] === 'homepage') {
            $locationKey = 'homepage';
        } elseif (in_array($data['for'], array('posts', 'pages', 'media_attachment'))) {
            if ($data['for'] === 'posts') {
                $postTypeToCheck   = 'post';
                $postStatusToCheck = array( 'publish', 'private' );
                $locationKey = 'posts';
            } elseif ($data['for'] === 'pages') {
                $postTypeToCheck   = 'page';
                $postStatusToCheck = array( 'publish', 'private' );
                $locationKey = 'pages';
            } else {
                $postTypeToCheck   = 'attachment';
                $postStatusToCheck = 'inherit';
                $locationKey       = 'media';
            }

            $queryDataByID = array(
                'post_type'        => $postTypeToCheck,
                'post_status'      => $postStatusToCheck,
                'posts_per_page'   => -1,
                'suppress_filters' => true
            );

            $query = new \WP_Query($queryDataByID);

            if ( ! $query->have_posts() ) {
                $data['show_no_records_notice'] = true;
            }
        } elseif ($data['for'] === 'custom_post_types') {
            $data['custom_post_types_list'] = MiscAdmin::getCustomPostTypesList();

            if ( ! empty($data['custom_post_types_list']) ) {
                $chosenPostType = (isset($_GET['wpacu_current_post_type']) && $_GET['wpacu_current_post_type'])
                    ? $_GET['wpacu_current_post_type']
                    : Misc::arrayKeyFirst($data['custom_post_types_list']);
                $data['chosen_post_type'] = $chosenPostType;

                $locationKey = 'custom_post_type_' . $chosenPostType;
            } else {
                $data['show_critical_css_options'] = false;
            }
        } elseif ($data['for'] === 'category') {
            $locationKey = 'category';
        } elseif ($data['for'] === 'tag') {
            $locationKey = 'tag';
        } elseif ($data['for'] === 'custom_taxonomies') {
            $data['custom_taxonomies_list'] = MiscAdmin::getCustomTaxonomyList();

            if ( ! empty($data['custom_taxonomies_list']) ) {
                $chosenTaxonomy = (isset($_GET['wpacu_current_taxonomy']) && $_GET['wpacu_current_taxonomy'])
                    ? $_GET['wpacu_current_taxonomy']
                    : Misc::arrayKeyFirst($data['custom_taxonomies_list']);
                $data['chosen_taxonomy'] = $chosenTaxonomy;

                $locationKey = 'custom_taxonomy_' . $chosenTaxonomy;
            } else {
                $data['show_critical_css_options'] = false;
            }
        } elseif ($data['for'] === 'search') {
            $locationKey = 'search';
        } elseif ($data['for'] === 'author') {
            $locationKey = 'author';
        } elseif ($data['for'] === 'date') {
            $locationKey = 'date';
        } elseif ($data['for'] === '404_not_found') {
            $locationKey = '404_not_found';
        } elseif ($data['for'] === 'via_code') {
            $locationKey = 'via_code';
            $data['show_critical_css_options'] = false; // Just informational
        }

        if ($locationKey === 'via_code') {
            include_once __DIR__ . '/_admin-pages-assets-manager-critical-css/_via-code.php';
        } else {
            ?>
            <div style="margin: 10px 0 0;" class="wpacu_clearfix"></div>

            <?php
            include_once __DIR__ . '/_admin-pages-assets-manager-critical-css/_common/_applies-to.php';
            ?>

            <form id="wpacu-critical-css-form" method="post" action="">
                <?php
                // Show notices when the critical CSS is updated (e.g. updated/disabled, new CSS syntax)
                do_action('wpacu_admin_notices');

                if ($locationKey !== 'via_code') {
                    include_once __DIR__ . '/_admin-pages-assets-manager-critical-css/_common/_settings.php';
                }

                if ($data['show_critical_css_options']) {
                    ?>
                    <div id="wpacu-update-critical-css-button-area">
                        <input type="submit"
                               name="submit"
                               class="button button-primary"
                               value="<?php echo esc_attr('UPDATE', 'wp-asset-clean-up'); ?>" />
                        <div id="wpacu-updating-critical-css" class="wpacu-hide">
                            <img src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" align="top" width="20" height="20" alt="" />
                        </div>
                        <?php wp_nonce_field('wpacu_critical_css_update', 'wpacu_critical_css_nonce'); ?>
                        <input type="hidden" name="wpacu_critical_css_submit" value="1" />
                    </div>
                    <?php
                }
                ?>
            </form>
            <?php
        }
    }
}
