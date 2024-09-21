<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\OwnAssets;

/**
 * Class AjaxSearchPagesAutocomplete
 * @package WpAssetCleanUp
 */
class AjaxSearchPagesAutocomplete
{
	/**
	 * AjaxSearchAutocomplete constructor.
	 */
	public function __construct()
	{
		add_action('admin_enqueue_scripts', array($this, 'adminEnqueueScripts'));
		add_action('wp_ajax_' . WPACU_PLUGIN_ID . '_autocomplete_search', array($this, 'wpAdminAjaxSearch'));

		self::maybePreventWpmlPluginFromFiltering();
	}

	/**
	 * "WPML Multilingual CMS" prevents the AJAX loader from "Load assets manager for:" from loading the results as they are
	 * If a specific ID is put there, the post with that ID should be returned and not one of its translated posts with a different ID
	 *
	 * @return void
	 */
	public static function maybePreventWpmlPluginFromFiltering()
	{
		if ( ! ( isset($_REQUEST['action'], $_REQUEST['wpacu_term'], $GLOBALS['sitepress']) &&
		    $_REQUEST['action'] === WPACU_PLUGIN_ID . '_autocomplete_search' &&
		    $_REQUEST['wpacu_term'] &&
		    wpacuIsPluginActive('sitepress-multilingual-cms/sitepress.php') &&
            class_exists('\WPML_URL_Filters') ) ) {
			return;
		}

		// This is called before "WPML Multilingual CMS" loads as we need to avoid any filtering of the search results
		// to avoid confusing the admin when managing the assets within "CSS & JS Manager" -- "Manage CSS/JS"

		// Avoid retrieving the wrong (language related) post ID and title
		global $sitepress;
		remove_action( 'parse_query', array( $sitepress, 'parse_query' ) );

		// Avoid retrieving the wrong (language related) permalink
		global $wp_filter;

		if ( ! isset( $wp_filter['page_link']->callbacks ) ) {
			return;
		}

		foreach ( $wp_filter['page_link']->callbacks as $key => $values ) {
			if ( ! empty( $wp_filter['page_link']->callbacks ) ) {
				foreach ( $values as $values2 ) {
					if ( isset( $values2['function'][0] ) && $values2['function'][0] instanceof \WPML_URL_Filters ) {
						unset( $wp_filter['page_link']->callbacks[ $key ] );
					}
				}
			}
		}

		}

	/**
	 * Only valid for "CSS & JS Manager" -- "Manage CSS/JS" -- ("Posts" | "Pages" | "Custom Post Types" | "Media")
     */
	public function adminEnqueueScripts()
    {
	    if (! isset($_REQUEST['wpacu_for'])) {
			return;
	    }

		$isManageCssJsDash = isset($_GET['page']) && $_GET['page'] === WPACU_PLUGIN_ID.'_assets_manager';
		$subPage = isset($_GET['wpacu_sub_page']) ? $_GET['wpacu_sub_page'] : 'manage_css_js';

		$loadAutoCompleteOnManageCssJsDash = ($isManageCssJsDash && $subPage === 'manage_css_js') &&
			in_array($_REQUEST['wpacu_for'], array('posts', 'pages', 'media_attachment', 'custom_post_types'));

		if ( ! $loadAutoCompleteOnManageCssJsDash ) {
			return;
		}

	    $wpacuFor = sanitize_text_field($_REQUEST['wpacu_for']);

	    switch ($wpacuFor) {
		    case 'posts':
			    $forPostType = 'post';
			    break;
		    case 'pages':
			    $forPostType = 'page';
			    break;
		    case 'media_attachment':
		    	$forPostType = 'attachment';
		    	break;
		    case 'custom_post_types':
		    	$forPostType = 'wpacu_custom_post_types';
		    	break;
		    default:
			    $forPostType = '';
	    }

	    if ( ! $forPostType ) {
	    	return;
	    }

        wp_enqueue_script(
            OwnAssets::$ownAssets['scripts']['autocomplete_search']['handle'],
            plugins_url(OwnAssets::$ownAssets['scripts']['autocomplete_search']['rel_path'], WPACU_PLUGIN_FILE),
            array('jquery', 'jquery-ui-autocomplete'),
            OwnAssets::assetVer(OwnAssets::$ownAssets['scripts']['autocomplete_search']['rel_path'])
        );

	    wp_localize_script(OwnAssets::$ownAssets['scripts']['autocomplete_search']['handle'], 'wpacu_autocomplete_search_obj', array(
		    'ajax_url'       => esc_url(admin_url('admin-ajax.php')),
		    'ajax_nonce'     => wp_create_nonce('wpacu_autocomplete_search_nonce'),
		    'ajax_action'    => WPACU_PLUGIN_ID . '_autocomplete_search',
		    'post_type'      => $forPostType,
		    'redirect_to'    => esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_for='.$wpacuFor.'&wpacu_post_id=post_id_here'))
	    ));

	    wp_enqueue_style(
			OwnAssets::$ownAssets['styles']['autocomplete_search_jquery_ui_custom']['handle'],
		    plugins_url(OwnAssets::$ownAssets['styles']['autocomplete_search_jquery_ui_custom']['rel_path'], WPACU_PLUGIN_FILE),
		    false, null, false
	    );

	    $jqueryUiCustom = <<<CSS
#wpacu-search-form-assets-manager input[type=text].ui-autocomplete-loading {
	background-position: 99% 6px;
}
CSS;
	    wp_add_inline_style(OwnAssets::$ownAssets['styles']['autocomplete_search_jquery_ui_custom']['handle'], $jqueryUiCustom);
    }

	/**
     * @noinspection NestedAssignmentsUsageInspection
     */
	public function wpAdminAjaxSearch()
    {
		check_ajax_referer('wpacu_autocomplete_search_nonce', 'wpacu_security');

		global $wpdb;

		$searchTerm = isset($_REQUEST['wpacu_term'])      ? sanitize_text_field($_REQUEST['wpacu_term']) : '';
		$postType   = isset($_REQUEST['wpacu_post_type']) ? sanitize_text_field($_REQUEST['wpacu_post_type']) : '';

		if ( $searchTerm === '' ) {
			echo wp_json_encode(array());
		}

		$results = array();

	    if ($postType !== 'attachment') {
	    	// 'post', 'page', custom post types
		    $queryDataByKeyword = array(
			    'post_type'        => $postType,
			    's'                => $searchTerm,
			    'post_status'      => array( 'publish', 'private' ),
			    'posts_per_page'   => -1,
			    'suppress_filters' => true
		    );
	    } else {
	    	// 'attachment'
		    $postIdsFromQuery = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT ID FROM `{$wpdb->posts}` WHERE post_title='%s'", $searchTerm ) );
		    $queryDataByKeyword = array(
			    'post_type'        => 'attachment',
			    'post_status'      => 'inherit',
			    'orderby'          => 'date',
			    'order'            => 'DESC',
			    'post__in'         => $postIdsFromQuery,
			    'suppress_filters' => true
		    );
	    }

		// Standard search
		$query = new \WP_Query($queryDataByKeyword);

		// No results? Search by ID in case the admin put the post/page ID in the search box
	    if ((int)$searchTerm > 0 && ! $query->have_posts()) {
	    	// This one works for any post type, including 'attachment'
		    $queryDataByID = array(
			    'post_type'        => $postType,
			    'post_status'      => array( 'publish', 'private' ),
			    'posts_per_page'   => -1,
			    'post__in'         => array((int)$searchTerm),
			    'suppress_filters' => true
		    );

		    $query = new \WP_Query($queryDataByID);
	    }

		if ($query->have_posts()) {
			$pageOnFront = $pageForPosts = false;

			if ($postType === 'page' && get_option('show_on_front') === 'page') {
				$pageOnFront  = (int)get_option('page_on_front');
				$pageForPosts = (int)get_option('page_for_posts');
			}

			while ($query->have_posts()) {
				$query->the_post();
				$resultPostId = get_the_ID();
				$resultPostStatus = get_post_status($resultPostId);

				$resultToShow = get_the_title() . ' / ID: '.$resultPostId;

				if ($resultPostStatus === 'private') {
					$iconPrivate = '<span class="dashicons dashicons-lock"></span>';
					$resultToShow .= ' / '.$iconPrivate.' Private';
				}

				// This is a page, and it was set as the homepage (point this out)
				if ($pageOnFront === $resultPostId) {
					$iconHome = '<span class="dashicons dashicons-admin-home"></span>';
					$resultToShow .= ' / '.$iconHome.' Homepage';
				}

				if ($pageForPosts === $resultPostId) {
					$iconPost = '<span class="dashicons dashicons-admin-post"></span>';
					$resultToShow .= ' / '.$iconPost.' Posts page';
				}

				$results[] = array(
					'id'    => $resultPostId,
					'label' => $resultToShow,
					'link'  => get_the_permalink()
                );
			}
			wp_reset_postdata();
		}

		if (empty($results)) {
			echo 'no_results';
			wp_die();
		}

		echo wp_json_encode($results);
		wp_die();
	}
}
