<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\AssetsManagerAdmin;

if (! isset($data)) {
    exit;
}

$loadSearchForm            = true;

$taxonomy                  = isset($data['archive_taxonomy']) ? $data['archive_taxonomy'] : '';

$showAllTermsIfCountIsUpTo = 5;
$showAllTermsOnFocus       = false;

if (isset($data['for']) && $data['for'] === 'custom_taxonomies') {
    $taxonomiesList = AssetsManagerAdmin::getCustomTaxonomiesWithTerms();

    if (empty($taxonomiesList)) {
        $loadSearchForm = false;
        ?>
        <div style="padding: 10px; background: white; border-radius: 10px; border: 1px solid #c3c4c7;">
            <span class="dashicons dashicons-warning" style="color: #004567;"></span> <?php esc_html_e('There are no public custom taxonomies with terms available.', 'wp-asset-clean-up'); ?>
        </div>
        <?php
    } else {
        if ((empty($taxonomy) || ! isset($taxonomiesList[$taxonomy])) && ! empty($taxonomiesList)) {
            $taxonomy = AssetsManagerAdmin::getDefaultCustomTaxonomyWithTerms();
        }

        if (isset($taxonomiesList[$taxonomy]['count']) && $taxonomiesList[$taxonomy]['count'] > 0) {
            $showAllTermsOnFocus = ((int) $taxonomiesList[$taxonomy]['count'] <= $showAllTermsIfCountIsUpTo);
        }
        ?>
        <form id="wpacu-custom-taxonomy-form" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin: 0 0 15px;">
            <input type="hidden" name="page" value="<?php echo esc_attr(WPACU_PLUGIN_ID); ?>_assets_manager" />
            <input type="hidden" name="wpacu_for" value="custom_taxonomies" />
            <?php esc_html_e('Choose the custom taxonomy first, then search within its terms:', 'wp-asset-clean-up'); ?>
            <select id="wpacu-custom-taxonomy-choice" name="wpacu_taxonomy" onchange="this.form.submit();">
                <?php foreach ($taxonomiesList as $taxonomyKey => $taxonomyData) { ?>
                    <?php
                    $termsCountText = sprintf(
                        _n('%s term', '%s terms', $taxonomyData['count'], 'wp-asset-clean-up'),
                        number_format_i18n($taxonomyData['count'])
                    );
                    ?>
                    <option <?php selected($taxonomy, $taxonomyKey); ?> value="<?php echo esc_attr($taxonomyKey); ?>">
                        <?php echo esc_html($taxonomyData['label']); ?> (<?php echo esc_html($taxonomyKey); ?>, <?php echo esc_html($termsCountText); ?>)
                    </option>
                <?php } ?>
            </select>
        </form>
        <?php
    }
}

if ($loadSearchForm && in_array($taxonomy, array('category', 'post_tag'), true)) {
    $termsCount = wp_count_terms($taxonomy, array(
        'hide_empty' => false
    ));

    if (! is_wp_error($termsCount) && (int) $termsCount < 1) {
        $loadSearchForm = false;

        if ($taxonomy === 'category') {
            $noTermsMessage = __('There are no categories available. The search bar will be available after at least one category is added.', 'wp-asset-clean-up');
        } else {
            $noTermsMessage = __('There are no tags available. The search bar will be available after at least one tag is added.', 'wp-asset-clean-up');
        }
        ?>
        <div style="padding: 10px; background: white; border-radius: 10px; border: 1px solid #c3c4c7;">
            <span class="dashicons dashicons-warning" style="color: #004567;"></span> <?php echo esc_html($noTermsMessage); ?>
        </div>
        <?php
    }
}

if ($loadSearchForm && ! $showAllTermsOnFocus && $taxonomy) {
    if (! isset($termsCount)) {
        $termsCount = wp_count_terms($taxonomy, array(
            'hide_empty' => false
        ));
    }

    if (! is_wp_error($termsCount) && (int) $termsCount > 0 && (int) $termsCount <= $showAllTermsIfCountIsUpTo) {
        $showAllTermsOnFocus = true;
    }
}

if ($loadSearchForm) {
    $placeholder = esc_attr__('Type a keyword or the term ID to search the taxonomy term', 'wp-asset-clean-up');

    if ($taxonomy === 'category') {
        $placeholder = esc_attr__('Type a keyword or the category ID to search for the term', 'wp-asset-clean-up');
    } elseif ($taxonomy === 'post_tag') {
        $placeholder = esc_attr__('Type a keyword or the tag ID to search for the term', 'wp-asset-clean-up');
    } elseif ($taxonomy === 'product_cat') {
        $placeholder = esc_attr__('Type a keyword or the WooCommerce product category ID to search for the term', 'wp-asset-clean-up');
    } elseif ($taxonomy === 'product_tag') {
        $placeholder = esc_attr__('Type a keyword or the WooCommerce product tag ID to search for the term', 'wp-asset-clean-up');
    }
    ?>
    <form id="wpacu-search-form-assets-manager">
        <?php esc_html_e('Load assets manager for:', 'wp-asset-clean-up'); ?>
        <input type="text"
               class="search-field"
               value=""
               data-wpacu-show-all-on-focus="<?php echo $showAllTermsOnFocus ? '1' : '0'; ?>"
               data-wpacu-show-all-limit="<?php echo esc_attr($showAllTermsIfCountIsUpTo); ?>"
               placeholder="<?php echo $placeholder; ?>"
               style="max-width: 800px; width: 100%; padding-right: 15px;" />
        * <small><?php $wpacuAssetManagerFormContext = 'taxonomy'; require WPACU_PLUGIN_DIR . '/templates/_common/asset-manager/form-context-note.php'; ?></small>
        <div style="display: none; padding: 10px; color: #cc0000;" id="wpacu-search-form-assets-manager-no-results"><span class="dashicons dashicons-warning"></span> <?php esc_html_e('There are no results based on your search.', 'wp-asset-clean-up'); ?></div>
    </form>

    <div style="display: none;" id="wpacu-post-chosen-loading-assets">
        <img style="margin: 2px 0 4px;"
             src="<?php echo esc_url(WPACU_PLUGIN_URL); ?>/assets/icons/loader-horizontal.svg?x=<?php echo time(); ?>"
             align="top"
             width="120"
             alt="" />
    </div>
    <?php
}
