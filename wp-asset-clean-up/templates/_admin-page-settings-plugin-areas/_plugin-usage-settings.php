<?php
/*
 * No direct access to this file
 */
if (! isset($data)) {
	exit;
}

use WpAssetCleanUp\MetaBoxes;
use WpAssetCleanUp\Misc;

$tabIdArea = 'wpacu-setting-plugin-usage-settings';
$styleTabContent = isset($selectedTabArea) && ($selectedTabArea === $tabIdArea) ? 'style="display: table-cell;"' : '';

$data['post_types_list'] = get_post_types(array('public' => true));

// Hide hardcoded irrelevant post types
foreach (MetaBoxes::$noMetaBoxesForPostTypes as $noMetaBoxesForPostType) {
    unset($data['post_types_list'][$noMetaBoxesForPostType]);
}
?>

<div id="<?php echo esc_attr($tabIdArea); ?>" class="wpacu-settings-tab-content" <?php echo wp_kses($styleTabContent, array('style' => array())); ?>>
    <!-- -->

    <?php
    \WpAssetCleanUp\Admin\SettingsAdmin::printSubTabsOutput(__DIR__, $data, __FILE__);
    ?>
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
