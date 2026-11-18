<?php
namespace WpAssetCleanUpLite;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\Menu;
use WpAssetCleanUp\Update;

/**
 * CSS/JS rules for WordPress's built-in category and tag archives.
 * Uses the same storage as Pro without enabling other Pro archive rules.
 */
class ArchiveRules
{
    /**
     * @return void
     */
    public static function registerHooks()
    {
        add_filter('wpacu_internal_main_front_for_type', array(__CLASS__, 'pageType'));
        add_filter('wpacu_internal_assets_unloaded_page_level_context_key', array(__CLASS__, 'contextKey'));
        add_filter('wpacu_internal_pro_get_assets_unloaded_page_level', array(__CLASS__, 'pageUnloads'));
        add_filter('wpacu_internal_pro_load_exceptions_page_level_json', array(__CLASS__, 'pageExceptions'), 10, 3);
        add_filter('wpacu_internal_data_for_non_singular_asset_management', array(__CLASS__, 'managerData'), 30);
        add_filter('wpacu_internal_data_var_template', array(__CLASS__, 'managerData'), 30);
        add_filter('wpacu_internal_assets_manager_text_rules_to_show_in_css_js_manager', array(__CLASS__, 'ruleLabels'));
        add_filter('wpacu_internal_object_data', array(__CLASS__, 'objectData'), 30);
        add_action('wpacu_internal_dashboard_archive_page_update', array(__CLASS__, 'saveDashboard'), 10, 2);
        add_action('wpacu_internal_frontend_update_before_common_updates', array(__CLASS__, 'saveFrontend'));
        add_action('wpacu_pro_frontend_before_asset_list', array(__CLASS__, 'frontendContextNote'));
        add_action('wpacu_pro_bulk_unload_output', array(__CLASS__, 'bulkUnloadControl'), 10, 3);
        add_action('wpacu_internal_bulk_load_context_output', array(__CLASS__, 'bulkLoadControl'), 10, 3);
        add_filter('wpacu_internal_asset_row_load_exception', array(__CLASS__, 'rowLoadException'), 10, 3);

        foreach (array('styles', 'scripts') as $assetType) {
            add_filter('wpacu_filter_' . $assetType . '_list_unload', static function ($list) use ($assetType) {
                return self::bulkUnloads($list, $assetType);
            });
            add_filter('wpacu_filter_' . $assetType . '_list_load_exception', static function ($list) use ($assetType) {
                return self::bulkExceptions($list, $assetType);
            });
        }
    }

    /**
     * @param mixed $taxonomy
     *
     * @return bool
     */
    public static function supports($taxonomy)
    {
        return in_array($taxonomy, array('category', 'post_tag'), true);
    }

    /**
     * Validate both the stored term and its requested taxonomy.
     *
     * @param mixed $id
     * @param mixed $taxonomy
     *
     * @return \WP_Term|false
     */
    public static function term($id, $taxonomy)
    {
        if ( ! self::supports($taxonomy) || ! is_scalar($id)
             || ! preg_match('/^[1-9][0-9]*$/D', (string)$id) ) {
            return false;
        }

        $term = get_term((int)$id, $taxonomy);

        return $term && ! is_wp_error($term) && isset($term->taxonomy, $term->term_id)
            && $term->taxonomy === $taxonomy && (int)$term->term_id === (int)$id ? $term : false;
    }

    /**
     * Front-end context comes from WordPress, never from URL/POST overrides.
     * Admin AJAX context must identify an actual supported term.
     *
     * @return \WP_Term|false
     */
    public static function context()
    {
        if ( ! is_admin() ) {
            if ( ! (is_category() || is_tag()) ) {
                return false;
            }

            $term = get_queried_object();

            return $term && isset($term->taxonomy, $term->term_id)
                && self::supports($term->taxonomy) && (int)$term->term_id > 0 ? $term : false;
        }

        return self::term(
            isset($_POST['tag_id']) ? $_POST['tag_id'] : null,
            isset($_POST['wpacu_taxonomy']) ? $_POST['wpacu_taxonomy'] : null
        );
    }

    /**
     * @return \WP_Term|false
     */
    public static function dashboardTerm()
    {
        if ( ! isset($_POST['wpacu_archive_page_type']) || $_POST['wpacu_archive_page_type'] !== 'taxonomy' ) {
            return false;
        }

        return self::term(
            isset($_POST['wpacu_archive_term_id']) ? $_POST['wpacu_archive_term_id'] : null,
            isset($_POST['wpacu_archive_taxonomy']) ? $_POST['wpacu_archive_taxonomy'] : null
        );
    }

    /**
     * @param mixed $type
     *
     * @return mixed
     */
    public static function pageType($type)
    {
        return self::context() ? 'for_pro' : $type;
    }

    /**
     * @param mixed $key
     *
     * @return mixed
     */
    public static function contextKey($key)
    {
        $term = self::context();

        return $term ? 'taxonomy_' . $term->taxonomy . '_' . $term->term_id : $key;
    }

    /**
     * @param mixed $json
     *
     * @return mixed
     */
    public static function pageUnloads($json)
    {
        $term = self::context();

        if ( ! $term ) {
            return $json;
        }

        $list = self::assetLists(self::decode(get_term_meta($term->term_id, '_' . WPACU_PLUGIN_ID . '_no_load', true)));

        foreach ($list as $type => $handles) {
            foreach ($handles as $handle) {
                Main::addAssetToUnloadReasonList($handle, $type, array(
                    'rule_label' => 'Unload on this ' . esc_html($term->taxonomy) . ' archive: ' . esc_html($term->name)
                ));
            }
        }

        return wp_json_encode($list);
    }

    /**
     * @param mixed $json
     * @param mixed $type
     * @param int $postId
     *
     * @return mixed
     */
    public static function pageExceptions($json, $type, $postId)
    {
        $term = $type === 'for_pro' ? self::context() : false;

        return $term ? wp_json_encode(self::assetLists(self::decode(get_term_meta(
            $term->term_id, '_' . WPACU_PLUGIN_ID . '_load_exceptions', true
        )))) : $json;
    }

    /**
     * @param array $list
     * @param string $assetType
     *
     * @return array
     */
    public static function bulkUnloads($list, $assetType)
    {
        $term = self::context();

        if ( ! $term ) {
            return $list;
        }

        $bulk    = Main::instance()->getBulkUnload('taxonomy', $term->taxonomy);
        $handles = self::handles(isset($bulk[$assetType]) ? $bulk[$assetType] : array());

        foreach ($handles as $handle) {
            Main::addAssetToUnloadReasonList($handle, $assetType, array(
                'rule_label' => 'Unload on all ' . esc_html($term->taxonomy) . ' archive pages'
            ));
        }

        return array_values(array_unique(array_merge($list, $handles)));
    }

    /**
     * @param array $list
     * @param string $assetType
     *
     * @return array
     */
    public static function bulkExceptions($list, $assetType)
    {
        $term = self::context();

        if ( ! $term ) {
            return $list;
        }

        $all        = self::decode(get_option(WPACU_PLUGIN_ID . '_tax_type_load_exceptions'));
        $exceptions = self::assetLists(isset($all[$term->taxonomy]) ? $all[$term->taxonomy] : array());

        return array_values(array_diff($list, $exceptions[$assetType]));
    }

    /**
     * @param bool $isException
     * @param string $handle
     * @param string $assetType
     *
     * @return bool
     */
    public static function rowLoadException($isException, $handle, $assetType)
    {
        return $isException || (self::context() && ! self::bulkExceptions(array($handle), $assetType));
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public static function managerData($data)
    {
        $term = self::context();

        if ( ! $term ) {
            return $data;
        }

        $data['lite_pro_preview_mode']    = false;
        $data['tax_name']                 = $term->taxonomy;
        $data['is_bulk_unloadable']        = true;
        $data['bulk_unloaded_type']       = 'taxonomy';
        $data['bulk_unloaded']['taxonomy'] = Main::instance()->getBulkUnload('taxonomy', $term->taxonomy);

        return $data;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public static function ruleLabels($data)
    {
        $term = self::context();

        if ($term) {
            $label = $term->taxonomy === 'category' ? __('category', 'wp-asset-clean-up') : __('tag', 'wp-asset-clean-up');

            $data['page_unload_text'] = esc_html(sprintf(__('Unload on this %s archive', 'wp-asset-clean-up'), $label) . ': ' . $term->name);
            $data['page_load_text']   = esc_html(sprintf(__('On this %s archive', 'wp-asset-clean-up'), $label) . ': ' . $term->name);
        }

        return $data;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public static function objectData($data)
    {
        $term = ! is_admin() ? self::context() : Admin\ArchiveEditor::currentTerm();

        if ($term) {
            $data['page_type']                    = 'taxonomy';
            $data['tag_id']                       = (int)$term->term_id;
            $data['wpacu_taxonomy']               = $data['tax_name'] = $term->taxonomy;
            $data['is_tax_page']                  = true;
            $data['selected_context_is_homepage'] = false;
        }

        return $data;
    }

    /**
     * @return void
     */
    public static function frontendContextNote()
    {
        $term = ! is_admin() ? self::context() : false;

        if ( ! $term ) {
            return;
        }

        $label = $term->taxonomy === 'category' ? __('Category', 'wp-asset-clean-up') : __('Tag', 'wp-asset-clean-up');
        $icon  = $term->taxonomy === 'category' ? 'dashicons-category' : 'dashicons-tag';

        echo '<p><strong><span aria-hidden="true" style="color: #0f6cab;" class="dashicons ' . esc_attr($icon) . '"></span> ';
        echo esc_html(sprintf(__('This is the WordPress %1$s archive for "%2$s". Changes also apply to its pagination pages.', 'wp-asset-clean-up'), $label, $term->name));
        echo '</strong></p>';
    }

    /**
     * Save only after WordPress has successfully updated the same editable term.
     *
     * @param int $termId
     * @param int $termTaxonomyId
     * @param string $taxonomy
     *
     * @return void
     */
    public static function saveEditor($termId, $termTaxonomyId, $taxonomy)
    {
        $term  = self::term($termId, $taxonomy);
        $nonce = isset($_POST['wpacu_archive_editor_nonce']) ? $_POST['wpacu_archive_editor_nonce'] : null;

        if ( ! is_admin() || ! $term || ! Admin\ArchiveEditor::enabled()
            || ! current_user_can('edit_term', $termId)
            || ! isset($_POST['action'], $_POST['tag_ID'], $_POST['taxonomy'], $_POST['wpacu_unload_assets_area_loaded'])
            || $_POST['action'] !== 'editedtag' || $_POST['taxonomy'] !== $taxonomy
            || ! is_scalar($_POST['tag_ID']) || (string)$_POST['tag_ID'] !== (string)$termId
            || $_POST['wpacu_unload_assets_area_loaded'] !== '1'
            || ! is_string($nonce) || ! wp_verify_nonce($nonce, 'wpacu_archive_editor_' . $taxonomy . '_' . $termId) ) {
            return;
        }

        self::save($term, isset($_POST[WPACU_PLUGIN_ID]) ? $_POST[WPACU_PLUGIN_ID] : array());
        Update::updateSettingsChangedOutsideTheMainArea();
        Update::updatePreloads();
        Update::updateHandleNotes();
        Update::saveGlobalLoadExceptions();
        Update::updateIgnoreChild();
        Update::clearTransients();
    }

    /**
     * Display feedback only after an authorized archive form was saved.
     *
     * @return void
     */
    public static function dashboardUpdated()
    {
        ?>
        <div class="updated notice wpacu-notice is-dismissible">
            <p><?php esc_html_e('The CSS/JS rules for this archive have been updated.', 'wp-asset-clean-up'); ?></p>
        </div>
        <?php
    }

    /**
     * @param bool $dashboard
     *
     * @return bool
     */
    private static function authorized($dashboard = false)
    {
        $field  = $dashboard ? WPACU_PLUGIN_ID . '_dash_assets_page_update_nonce_name' : Update::NONCE_FIELD_NAME;
        $action = $dashboard ? WPACU_PLUGIN_ID . '_dash_assets_page_update_nonce_action' : Update::NONCE_ACTION_NAME;

        return Menu::userCanAccessPlugin() && isset($_POST[$field]) && is_string($_POST[$field])
            && wp_verify_nonce($_POST[$field], $action);
    }

    /**
     * @param string $for
     * @param mixed $assets
     *
     * @return void
     */
    public static function saveDashboard($for, $assets)
    {
        $term = self::dashboardTerm();

        if ( ! self::authorized(true) || ! $term
             || $for !== ($term->taxonomy === 'category' ? 'category' : 'tag') ) {
            return;
        }

        self::save($term, $assets);
        Update::updateSettingsChangedOutsideTheMainArea();
        Update::updatePreloads();
        Update::updateHandleNotes();
        Update::saveGlobalLoadExceptions();
        Update::updateIgnoreChild();
        Update::clearTransients();
        add_action('wpacu_admin_notices', array(__CLASS__, 'dashboardUpdated'));
    }

    /**
     * @return void
     */
    public static function saveFrontend()
    {
        $term = self::context();

        if ( is_admin() || ! $term || ! self::authorized()
            || ! Main::showAssetsManagerInFrontend()
            || ! isset($_POST['wpacu_update_asset_frontend']) || $_POST['wpacu_update_asset_frontend'] !== '1' ) {
            return;
        }

        self::save($term, isset($_POST[WPACU_PLUGIN_ID]) ? $_POST[WPACU_PLUGIN_ID] : array());
    }

    /**
     * Update only this supported term/taxonomy; retain every other edition's data.
     *
     * @param \WP_Term $term
     * @param mixed $assets
     *
     * @return void
     */
    private static function save($term, $assets)
    {
        $update = new Update();
        $update->updateEverywhereUnloads();

        $exceptions = array();
        $form       = isset($_POST[WPACU_FORM_ASSETS_POST_KEY]) && is_array($_POST[WPACU_FORM_ASSETS_POST_KEY]) ? $_POST[WPACU_FORM_ASSETS_POST_KEY] : array();

        foreach (array('styles', 'scripts') as $type) {
            $exceptions[$type] = self::handles(isset($_POST['wpacu_' . $type . '_load_it']) ? $_POST['wpacu_' . $type . '_load_it'] : array());
        }

        $lists   = array('_no_load' => self::assetLists($assets), '_load_exceptions' => $exceptions);
        $termIds = array($term->term_id);

        if (wpacuIsPluginActive('sitepress-multilingual-cms/sitepress.php')
            && apply_filters('wpacu_manage_assets_update_sync_wpml_translated_tax', true)) {
            $termIds = Update::getAllAssocTermIdsForWpml($term->term_id, $term->taxonomy);
        }

        foreach (array_unique($termIds) as $termId) {
            foreach ($lists as $suffix => $list) {
                $key    = '_' . WPACU_PLUGIN_ID . $suffix;
                $stored = self::decode(get_term_meta($termId, $key, true));

                // Preserve rules whose controls are absent or covered by a bulk unload.
                foreach (array('styles', 'scripts') as $type) {
                    $represented = self::representedHandles($form, $type, $suffix === '_no_load');

                    foreach (isset($stored[$type]) && is_array($stored[$type]) ? $stored[$type] : array() as $handle) {
                        if ( is_string($handle) && (strpos($handle, 'wpacu_hardcoded_') === 0 || ! in_array($handle, $represented, true)) ) {
                            $list[$type][] = $handle;
                        }
                    }

                    $list[$type] = array_values(array_unique($list[$type]));
                }

                if (empty($list['styles']) && empty($list['scripts'])) {
                    delete_term_meta($termId, $key);
                } else {
                    update_term_meta($termId, $key, wp_slash(wp_json_encode($list)));
                }
            }
        }

        $bulk          = self::decode(get_option(WPACU_PLUGIN_ID . '_bulk_unload'));
        $allExceptions = self::decode(get_option(WPACU_PLUGIN_ID . '_tax_type_load_exceptions'));

        foreach (array('styles', 'scripts') as $type) {
            if ( ! isset($bulk[$type]) || ! is_array($bulk[$type]) ) {
                $bulk[$type] = array();
            }

            if ( ! isset($bulk[$type]['taxonomy']) || ! is_array($bulk[$type]['taxonomy']) ) {
                $bulk[$type]['taxonomy'] = array();
            }

            if ( ! isset($allExceptions[$term->taxonomy]) || ! is_array($allExceptions[$term->taxonomy]) ) {
                $allExceptions[$term->taxonomy] = array();
            }

            $existing = isset($bulk[$type]['taxonomy'][$term->taxonomy]) && is_array($bulk[$type]['taxonomy'][$term->taxonomy])
                ? array_values(array_filter($bulk[$type]['taxonomy'][$term->taxonomy], 'is_string')) : array();
            $added    = isset($_POST['wpacu_bulk_unload_' . $type]['taxonomy'][$term->taxonomy]) ? $_POST['wpacu_bulk_unload_' . $type]['taxonomy'][$term->taxonomy] : array();
            $existing = array_unique(array_merge($existing, self::handles($added)));
            $options  = isset($_POST['wpacu_options_taxonomy_' . $type]) && is_array($_POST['wpacu_options_taxonomy_' . $type]) ? $_POST['wpacu_options_taxonomy_' . $type] : array();

            foreach ($options as $handle => $action) {
                if ($action === 'remove' && strpos((string)$handle, 'wpacu_hardcoded_') !== 0) {
                    $existing = array_diff($existing, array($handle));
                }
            }

            $bulk[$type]['taxonomy'][$term->taxonomy] = array_values($existing);

            $savedExceptions = self::handles(isset($form['load_it_tax_type'][$type]) ? $form['load_it_tax_type'][$type] : array());
            $represented     = self::representedHandles($form, $type);

            foreach (isset($allExceptions[$term->taxonomy][$type]) && is_array($allExceptions[$term->taxonomy][$type]) ? $allExceptions[$term->taxonomy][$type] : array() as $handle) {
                if ( is_string($handle) && (strpos($handle, 'wpacu_hardcoded_') === 0 || ! in_array($handle, $represented, true)) ) {
                    $savedExceptions[] = $handle;
                }
            }

            $allExceptions[$term->taxonomy][$type] = array_values(array_unique($savedExceptions));
        }

        update_option(WPACU_PLUGIN_ID . '_bulk_unload', wp_json_encode($bulk));
        update_option(WPACU_PLUGIN_ID . '_tax_type_load_exceptions', wp_json_encode($allExceptions));
    }

    /**
     * A row reference does not mean its individual unload control was editable.
     * PHP casts numeric handle keys to integers; stored handle values stay strings.
     *
     * @param array $form
     * @param string $type
     * @param bool $requireEditableUnload
     *
     * @return array
     */
    private static function representedHandles($form, $type, $requireEditableUnload = false)
    {
        $handles = array();
        $rows    = isset($form[$type]) && is_array($form[$type]) ? $form[$type] : array();

        foreach ($rows as $handle => $row) {
            if ( $requireEditableUnload && ( ! is_array($row) || ! isset($row['unload_editable']) || $row['unload_editable'] !== '1') ) {
                continue;
            }

            $handles[] = (string)$handle;
        }

        return $handles;
    }

    /**
     * @param mixed $value
     *
     * @return array
     */
    private static function decode($value)
    {
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : array();
    }

    /**
     * @param mixed $value
     *
     * @return array
     */
    private static function handles($value)
    {
        $handles = array();

        foreach (is_array($value) ? $value : array() as $handle) {
            if (is_string($handle) && $handle !== '' && strpos($handle, 'wpacu_hardcoded_') !== 0) {
                $handles[] = $handle;
            }
        }

        return array_values(array_unique($handles));
    }

    /**
     * @param mixed $value
     *
     * @return array
     */
    private static function assetLists($value)
    {
        $result = array();

        foreach (array('styles', 'scripts') as $type) {
            $result[$type] = self::handles(isset($value[$type]) ? $value[$type] : array());
        }

        return $result;
    }

    /**
     * @param array $data
     * @param object $obj
     * @param string $assetTypeS
     *
     * @return void
     */
    public static function bulkUnloadControl($data, $obj, $assetTypeS)
    {
        $term = self::context();

        if ($term) {
            include WPACU_PLUGIN_DIR . '/lite/templates/archive-bulk-unload.php';
        }
    }

    /**
     * @param array $data
     * @param object $obj
     * @param string $assetTypeS
     *
     * @return void
     */
    public static function bulkLoadControl($data, $obj, $assetTypeS)
    {
        $term = self::context();

        if ($term) {
            $allExceptions = self::decode(get_option(WPACU_PLUGIN_ID . '_tax_type_load_exceptions'));

            include WPACU_PLUGIN_DIR . '/lite/templates/archive-bulk-load.php';
        }
    }
}
