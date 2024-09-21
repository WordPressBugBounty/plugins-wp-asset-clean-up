<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
	exit;
}

use WpAssetCleanUp\MetaBoxes;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;
use WpAssetCleanUp\PluginTracking;

$tabIdArea = 'wpacu-setting-plugin-usage-settings';
$styleTabContent = isset($selectedTabArea) && ($selectedTabArea === $tabIdArea) ? 'style="display: table-cell;"' : '';

if ( ! isset($selectedSubTabArea) ) {
    $selectedSubTabArea = 'wpacu-plugin-usage-settings-assets-management';
}

$postTypesList = get_post_types(array('public' => true));

// Hide hardcoded irrelevant post types
foreach (MetaBoxes::$noMetaBoxesForPostTypes as $noMetaBoxesForPostType) {
    unset($postTypesList[$noMetaBoxesForPostType]);
}
?>

<div id="<?php echo esc_attr($tabIdArea); ?>" class="wpacu-settings-tab-content" <?php echo wp_kses($styleTabContent, array('style' => array())); ?>>
    <!-- -->
    <div class="wpacu-sub-tabs-wrap"> <!-- Sub-tabs wrap -->
        <!-- Sub-nav menu -->
        <input class="wpacu-nav-input"
               id="wpacu-plugin-usage-settings-assets-management-tab-item"
               type="radio"
               name="wpacu_sub_tab_area"
               value="wpacu-plugin-usage-settings-assets-management"
               <?php if ($selectedSubTabArea === 'wpacu-plugin-usage-settings-assets-management') { ?>checked="checked"<?php } ?> />
        <label class="wpacu-nav-label"
               for="wpacu-plugin-usage-settings-assets-management-tab-item">CSS/JS Management</label>

        <input class="wpacu-nav-input"
               id="wpacu-plugin-usage-settings-cache-tab-item"
               type="radio"
               name="wpacu_sub_tab_area"
               value="wpacu-plugin-usage-settings-cache"
               <?php if ($selectedSubTabArea === 'wpacu-plugin-usage-settings-cache') { ?>checked="checked"<?php } ?> />
        <label class="wpacu-nav-label"
               for="wpacu-plugin-usage-settings-cache-tab-item">CSS/JS Cache</label>

        <input class="wpacu-nav-input"
               id="wpacu-plugin-usage-settings-accessibility-tab-item"
               type="radio"
               name="wpacu_sub_tab_area"
               value="wpacu-plugin-usage-settings-accessibility"
               <?php if ($selectedSubTabArea === 'wpacu-plugin-usage-settings-accessibility') { ?>checked="checked"<?php } ?> />
        <label class="wpacu-nav-label"
               for="wpacu-plugin-usage-settings-accessibility-tab-item">Accessibility</label>

        <input class="wpacu-nav-input"
               id="wpacu-plugin-usage-settings-visibility-tab-item"
               type="radio"
               name="wpacu_sub_tab_area"
               value="wpacu-plugin-usage-settings-visibility"
               <?php if ($selectedSubTabArea === 'wpacu-plugin-usage-settings-visibility') { ?>checked="checked"<?php } ?> />
        <label class="wpacu-nav-label"
               for="wpacu-plugin-usage-settings-visibility-tab-item">Visibility</label>

        <input class="wpacu-nav-input"
               id="wpacu-plugin-usage-settings-analytics-tab-item"
               type="radio"
               name="wpacu_sub_tab_area"
               value="wpacu-plugin-usage-settings-analytics"
               <?php if ($selectedSubTabArea === 'wpacu-plugin-usage-settings-analytics') { ?>checked="checked"<?php } ?> />
        <label class="wpacu-nav-label"
               for="wpacu-plugin-usage-settings-analytics-tab-item">Analytics</label>

        <input class="wpacu-nav-input"
               id="wpacu-plugin-usage-settings-no-load-on-specific-pages-tab-item"
               type="radio"
               name="wpacu_sub_tab_area"
               value="wpacu-plugin-usage-settings-no-load-on-specific-pages"
               <?php if ($selectedSubTabArea === 'wpacu-plugin-usage-settings-no-load-on-specific-pages') { ?>checked="checked"<?php } ?> />
        <label class="wpacu-nav-label"
               for="wpacu-plugin-usage-settings-no-load-on-specific-pages-tab-item">No load on certain frontend pages</label>

        <?php if (current_user_can(\WpAssetCleanUp\Menu::$defaultAccessRole)) { ?>
        <input class="wpacu-nav-input"
               id="wpacu-plugin-usage-settings-access-tab-item"
               type="radio"
               name="wpacu_sub_tab_area"
               value="wpacu-plugin-usage-settings-access"
               <?php if ($selectedSubTabArea === 'wpacu-plugin-usage-settings-access') { ?>checked="checked"<?php } ?> />
        <label class="wpacu-nav-label"
               for="wpacu-plugin-usage-settings-access-tab-item">Plugin Access</label>
        <?php } ?>
        <!-- /Sub-nav menu -->

        <section class="wpacu-sub-tabs-item <?php if ($selectedSubTabArea === 'wpacu-plugin-usage-settings-assets-management') { echo 'wpacu-visible'; } ?>" id="wpacu-plugin-usage-settings-assets-management-tab-item-area">
            <?php include_once __DIR__.'/_plugin-usage-settings/_assets-management.php'; ?>
        </section>

        <section class="wpacu-sub-tabs-item <?php if ($selectedSubTabArea === 'wpacu-plugin-usage-settings-cache') { echo 'wpacu-visible'; } ?>" id="wpacu-plugin-usage-settings-cache-tab-item-area">
            <?php include_once __DIR__.'/_plugin-usage-settings/_cache.php'; ?>
        </section>

        <section class="wpacu-sub-tabs-item <?php if ($selectedSubTabArea === 'wpacu-plugin-usage-settings-accessibility') { echo 'wpacu-visible'; } ?>" id="wpacu-plugin-usage-settings-accessibility-tab-item-area">
            <?php include_once __DIR__.'/_plugin-usage-settings/_accessibility.php'; ?>
        </section>

        <section class="wpacu-sub-tabs-item <?php if ($selectedSubTabArea === 'wpacu-plugin-usage-settings-visibility') { echo 'wpacu-visible'; } ?>" id="wpacu-plugin-usage-settings-visibility-tab-item-area">
            <?php include_once __DIR__.'/_plugin-usage-settings/_visibility.php'; ?>
        </section>

        <section class="wpacu-sub-tabs-item <?php if ($selectedSubTabArea === 'wpacu-plugin-usage-settings-analytics') { echo 'wpacu-visible'; } ?>" id="wpacu-plugin-usage-settings-analytics-tab-item-area">
            <?php include_once __DIR__.'/_plugin-usage-settings/_analytics.php'; ?>
        </section>

        <section class="wpacu-sub-tabs-item <?php if ($selectedSubTabArea === 'wpacu-plugin-usage-settings-no-load-on-specific-pages') { echo 'wpacu-visible'; } ?>" id="wpacu-plugin-usage-settings-no-load-on-specific-pages-tab-item-area">
            <?php include_once __DIR__.'/_plugin-usage-settings/_no-load-on-specific-pages.php'; ?>
        </section>

        <?php if (current_user_can(\WpAssetCleanUp\Menu::$defaultAccessRole)) { ?>
        <section class="wpacu-sub-tabs-item <?php if ($selectedSubTabArea === 'wpacu-plugin-usage-settings-access') { echo 'wpacu-visible'; } ?>" id="wpacu-plugin-usage-settings-access-tab-item-area">
            <?php include_once __DIR__.'/_plugin-usage-settings/_access.php'; ?>
        </section>
        <?php } ?>
    </div> <!-- /Sub-tabs wrap -->
</div>

<style <?php echo Misc::getStyleTypeAttribute(); ?>>
    #wpacu-show-tracked-data-list-modal {
        margin: 14px 0 0;
    }

    #wpacu-show-tracked-data-list-modal .table-striped {
        border: none;
        border-spacing: 0;
    }

    #wpacu-show-tracked-data-list-modal .table-striped tbody tr:nth-of-type(even) {
        background-color: rgba(0, 143, 156, 0.05);
    }

    #wpacu-show-tracked-data-list-modal .table-striped tbody tr td:first-child {
        font-weight: bold;
    }
</style>

<div id="wpacu-show-tracked-data-list-modal" class="wpacu-modal" style="padding-top: 100px;">
    <div class="wpacu-modal-content" style="max-width: 800px;">
        <span class="wpacu-close">&times;</span>
        <p>The following information will be sent to us, and it would be helpful to make the plugin better.</p>
        <p>e.g. see which themes and plugins are used the most and make the plugin as compatible as possible with them, see the most used plugin settings, determine the most used languages after English which is helpful to prioritise translations etc.</p>
        <?php
        $pluginTrackingClass = new PluginTracking();
        $pluginTrackingClass->setupData();
        $pluginTrackingClass::showSentInfoDataTable($pluginTrackingClass->data);
        ?>
    </div>
</div>

<div id="wpacu-fetch-assets-details-location-modal" class="wpacu-modal" style="padding-top: 100px;">
    <div class="wpacu-modal-content" style="max-width: 900px;">
        <span class="wpacu-close">&times;</span>
        <p>Any optimized files (e.g. minified, combined) have their caching information (such as original location, new optimized location, version) stored in the disk by default (in most cases, it's the most effective option) to avoid extra connections to the database for a few files' information.</p>
        <p>However, if you already have a light database and lots of Apache/NGINX resources already in use by your theme/other plugins, you can balance the usage of <?php echo WPACU_PLUGIN_TITLE; ?>'s resources and go for the "Database &amp; Disk (50% / 50%)" option (Example: If, for instance, on a page, there are 19 CSS/JS files which are optimized &amp; cached, 10 would have their caching information fetched from the database while 9 from the disk).</p>

        <p>The contents are stored like in the following example:</p>
        <p><code>/wp-content/plugins/plugin-title-here/assets/style.css<br />/wp-content/uploads/asset-cleanup/css/item/handle-title-here-v10-8683e3d8975dab70c7f368d58203e66e70fb3e06.css<br />10</code></p>

        <p>Once this information is retrieved, the file's original URL will be updated to match the optimized one for the file's content stored in <code><?php echo OptimizeCommon::getRelPathPluginCacheDir(); ?></code>.</p>

        <p><strong>Note:</strong> If you are using a plugin such as WP-Optimize, WP Fastest Cache or the caching system provided by your hosting company, then this fetching process would be significantly reduced as visitors will access static HTML pages read from the caching. Technically, no SQL queries should be made as the WordPress environment would not be loaded as it happens with a non-cached page (e.g. when you are logged-in and access the front-end pages).</p>
    </div>
</div>