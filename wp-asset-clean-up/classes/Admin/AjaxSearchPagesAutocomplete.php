<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\OwnAssets;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\MetaBoxes;
use WpAssetCleanUp\Menu;

/**
 * Class AjaxSearchPagesAutocomplete
 * @package WpAssetCleanUp
 */
class AjaxSearchPagesAutocomplete
{
    /**
     * @var int[]
     */
    public static $showAllResultsIfCountIsUpToArray = array(
        'posts'             => 8,
        'pages'             => 8,
        'media'             => 8,
        'custom_post_types' => 8,
        'taxonomies'        => 8,
        'users'             => 8
    );

	/**
	 * AjaxSearchAutocomplete constructor.
	 */
	public function __construct()
	{
		add_action('admin_enqueue_scripts',                               array($this, 'adminEnqueueScripts'));
		add_action('wp_ajax_' . WPACU_PLUGIN_ID . '_autocomplete_search', array($this, 'wpAdminAjaxSearch'));

		self::maybePreventWpmlPluginFromFiltering();
	}

	/**
	 * @return array
	 */
	private static function getRawSearchRequest()
	{
		$rawSearch = isset($_REQUEST['wpacu_search']) ? wp_unslash($_REQUEST['wpacu_search']) : array();

		if (is_string($rawSearch)) {
			$decodedSearch = json_decode($rawSearch, true);
			$rawSearch     = is_array($decodedSearch) ? $decodedSearch : array();
		}

		if (! is_array($rawSearch)) {
			$rawSearch = array();
		}

		return $rawSearch;
	}

	/**
	 * @return array
	 */
	private static function getSanitizedSearchRequest()
	{
		$rawSearch = self::getRawSearchRequest();

		// Backward compatibility in case an old cached JS file still sends the previous flat request format.
		$legacyContextType = '';
		$legacyContextValue = '';

		if (isset($_REQUEST['wpacu_search_mode'])) {
			$legacySearchMode = sanitize_text_field(wp_unslash($_REQUEST['wpacu_search_mode']));

			if ($legacySearchMode === 'term') {
				$legacyContextType  = 'taxonomy';
				$legacyContextValue = isset($_REQUEST['wpacu_taxonomy']) ? sanitize_key(wp_unslash($_REQUEST['wpacu_taxonomy'])) : '';
			} elseif ($legacySearchMode === 'user') {
				$legacyContextType  = 'user';
				$legacyContextValue = 'author';
			} else {
				$legacyContextType  = 'post_type';
				$legacyContextValue = isset($_REQUEST['wpacu_post_type']) ? sanitize_key(wp_unslash($_REQUEST['wpacu_post_type'])) : '';
			}
		}

		$contextType  = isset($rawSearch['context_type'])  ? sanitize_key($rawSearch['context_type'])  : $legacyContextType;
		$contextValue = isset($rawSearch['context_value']) ? sanitize_key($rawSearch['context_value']) : $legacyContextValue;
		$keyword      = isset($rawSearch['keyword'])       ? sanitize_text_field($rawSearch['keyword']) : (isset($_REQUEST['wpacu_term']) ? sanitize_text_field(wp_unslash($_REQUEST['wpacu_term'])) : '');
		$showAll      = isset($rawSearch['show_all'])      ? ((int) $rawSearch['show_all'] === 1)       : (isset($_REQUEST['wpacu_show_all']) && (int) $_REQUEST['wpacu_show_all'] === 1);

		return array(
			'context_type'  => $contextType,
			'context_value' => $contextValue,
			'keyword'       => $keyword,
			'show_all'      => $showAll
		);
	}

	/**
	 * "WPML Multilingual CMS" prevents the AJAX loader from "Load assets manager for:" from loading the results as they are
	 * If a specific ID is put there, the post with that ID should be returned and not one of its translated posts with a different ID
	 *
	 * @return void
	 */
	public static function maybePreventWpmlPluginFromFiltering()
	{
		$searchRequest = self::getSanitizedSearchRequest();
		$searchKeyword = $searchRequest['keyword'];
		$isShowAll     = $searchRequest['show_all'];

		if ( ! ( isset($_REQUEST['action'], $GLOBALS['sitepress']) &&
		    $_REQUEST['action'] === WPACU_PLUGIN_ID . '_autocomplete_search' &&
		    ($searchKeyword !== '' || $isShowAll) &&
		    wpacuIsPluginActive('sitepress-multilingual-cms/sitepress.php') &&
            class_exists('\WPML_URL_Filters') ) ) {
			return;
		}

		// This is called before "WPML Multilingual CMS" loads as we need to avoid any filtering of the search results
		// to avoid confusing the admin when managing CSS/JS assets or object-level Critical CSS

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
	 * Only valid for "CSS & JS Manager" -- "Manage CSS/JS" or "Manage Critical CSS" -- singular and archive contexts
     */
	public function adminEnqueueScripts()
    {
	    if ( ! isset($_REQUEST['wpacu_for']) ) {
			return;
	    }

		$isAssetsManagerDash = isset($_GET['page']) && $_GET['page'] === WPACU_PLUGIN_ID . '_assets_manager';
		$subPage            = isset($_GET['wpacu_sub_page']) ? $_GET['wpacu_sub_page'] : 'manage_css_js';
		$isSupportedSubPage = in_array($subPage, array('manage_css_js', 'manage_critical_css'), true);
		$wpacuFor           = sanitize_text_field($_REQUEST['wpacu_for']);

		if ( ! ($isAssetsManagerDash && $isSupportedSubPage)
		     || ! in_array($wpacuFor, array('posts', 'pages', 'media_attachment', 'custom_post_types', 'category', 'tag', 'custom_taxonomies', 'author'), true) ) {
			return;
		}

        if ($wpacuFor === 'media_attachment' && MetaBoxes::isMediaWithPermalinkDeactivated()) {
            return;
        }

	    $contextType       = 'post_type';
        $contextValue      = '';
        $isCriticalCssPage = ($subPage === 'manage_critical_css');

        // The autocomplete is only used by the separate Specific Critical CSS sub-tab.
        if ($isCriticalCssPage && CriticalCssAdmin::getManagementScope($wpacuFor) !== 'specific') {
            return;
        }

        if ($isCriticalCssPage) {
            $redirectTo = esc_url(admin_url(
                'admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=' . rawurlencode($wpacuFor) . '&wpacu_critical_css_scope=specific&wpacu_post_id=post_id_here'
            ));
        } else {
            $redirectTo = esc_url(admin_url(
                'admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_for=' . rawurlencode($wpacuFor) . '&wpacu_post_id=post_id_here'
            ));
        }

	    switch ($wpacuFor) {
		    case 'posts':
			    $contextValue = 'post';
			    break;
		    case 'pages':
			    $contextValue = 'page';
			    break;
		    case 'media_attachment':
		    	$contextValue = 'attachment';
		    	break;
		    case 'custom_post_types':
                $contextValue = sanitize_key(Misc::getVar('get', $isCriticalCssPage ? 'wpacu_current_post_type' : 'wpacu_post_type', ''));

                if ($contextValue === '') {
                    if ($isCriticalCssPage) {
                        $customPostTypesList = MiscAdmin::getCustomPostTypesList();
                        $contextValue = ! empty($customPostTypesList) ? Misc::arrayKeyFirst($customPostTypesList) : '';
                    } else {
                        $contextValue = AssetsManagerAdmin::getDefaultCustomPostTypeWithPosts();
                    }
                }

                if ($isCriticalCssPage) {
                    $redirectTo = esc_url(admin_url(
                        'admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=custom_post_types&wpacu_current_post_type=' . rawurlencode($contextValue) . '&wpacu_critical_css_scope=specific&wpacu_post_id=post_id_here'
                    ));
                } else {
                    $redirectTo = esc_url(admin_url(
                        'admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_for=custom_post_types&wpacu_post_type=' . rawurlencode($contextValue) . '&wpacu_post_id=post_id_here'
                    ));
                }
                break;
            case 'category':
                $contextType  = 'taxonomy';
                $contextValue = 'category';

                if ($isCriticalCssPage) {
                    $redirectTo = esc_url(admin_url(
                        'admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=category&wpacu_critical_css_scope=specific&wpacu_term_id=item_id_here'
                    ));
                } else {
                    $redirectTo = esc_url(admin_url(
                        'admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_for=category&wpacu_taxonomy=category&wpacu_term_id=item_id_here'
                    ));
                }
                break;
            case 'tag':
                $contextType  = 'taxonomy';
                $contextValue = 'post_tag';

                if ($isCriticalCssPage) {
                    $redirectTo = esc_url(admin_url(
                        'admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=tag&wpacu_critical_css_scope=specific&wpacu_term_id=item_id_here'
                    ));
                } else {
                    $redirectTo = esc_url(admin_url(
                        'admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_for=tag&wpacu_taxonomy=post_tag&wpacu_term_id=item_id_here'
                    ));
                }
                break;
            case 'custom_taxonomies':
                $contextType  = 'taxonomy';
                $contextValue = sanitize_key(Misc::getVar('get', $isCriticalCssPage ? 'wpacu_current_taxonomy' : 'wpacu_taxonomy', ''));

                if ($contextValue === '') {
                    if ($isCriticalCssPage) {
                        $customTaxonomiesList = MiscAdmin::getCustomTaxonomyList();
                        $contextValue = ! empty($customTaxonomiesList) ? Misc::arrayKeyFirst($customTaxonomiesList) : '';
                    } else {
                        $contextValue = AssetsManagerAdmin::getDefaultCustomTaxonomyWithTerms();
                    }
                }

                if ($isCriticalCssPage) {
                    $redirectTo = esc_url(admin_url(
                        'admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=custom_taxonomies&wpacu_current_taxonomy=' . rawurlencode($contextValue) . '&wpacu_critical_css_scope=specific&wpacu_term_id=item_id_here'
                    ));
                } else {
                    $redirectTo = esc_url(admin_url(
                        'admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_for=custom_taxonomies&wpacu_taxonomy=' . rawurlencode($contextValue) . '&wpacu_term_id=item_id_here'
                    ));
                }
                break;
            case 'author':
                $contextType  = 'user';
                $contextValue = 'author';

                if ($isCriticalCssPage) {
                    $redirectTo = esc_url(admin_url(
                        'admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=author&wpacu_critical_css_scope=specific&wpacu_author_id=item_id_here'
                    ));
                } else {
                    $redirectTo = esc_url(admin_url(
                        'admin.php?page=' . WPACU_PLUGIN_ID . '_assets_manager&wpacu_for=author&wpacu_author_id=item_id_here'
                    ));
                }
                break;
	    }

	    if ( ! $contextValue ) {
	    	return;
	    }

        if ($contextType === 'taxonomy' && ! taxonomy_exists($contextValue)) {
            return;
        }

        if ($contextType === 'post_type' && ! post_type_exists($contextValue)) {
            return;
        }

        wp_enqueue_script(
            OwnAssets::$ownAssets['scripts']['autocomplete_search']['handle'],
            plugins_url(OwnAssets::$ownAssets['scripts']['autocomplete_search']['rel_path'], WPACU_PLUGIN_FILE),
            array('jquery', 'jquery-ui-autocomplete'),
            OwnAssets::assetVer(OwnAssets::$ownAssets['scripts']['autocomplete_search']['rel_path'])
        );

	    wp_localize_script(OwnAssets::$ownAssets['scripts']['autocomplete_search']['handle'], 'wpacu_autocomplete_search_obj', array(
		    'ajax_url'                 => esc_url(admin_url('admin-ajax.php')),
		    'ajax_nonce'               => wp_create_nonce('wpacu_autocomplete_search_nonce'),
		    'ajax_action'              => WPACU_PLUGIN_ID . '_autocomplete_search',
            'context_type'            => $contextType,
            'context_value'           => $contextValue,
		    'redirect_to'              => $redirectTo
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

body.asset-cleanup_page_wpassetcleanup_assets_manager .ui-autocomplete,
body.asset-cleanup-pro_page_wpassetcleanup_assets_manager .ui-autocomplete {
	z-index: 1000001;
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

        if ( ! Menu::userCanAccessPlugin() ) {
            wp_send_json_error(array('message' => 'You are not allowed to access this area.'));
        }

        $search = self::getSanitizedSearchRequest();

        if ($search['keyword'] === '' && ! $search['show_all']) {
            echo wp_json_encode(array());
            wp_die();
        }

        if ($search['context_type'] === 'taxonomy') {
            $this->ajaxSearchTerms($search);
        }

        if ($search['context_type'] === 'user') {
            $this->ajaxSearchUsers($search);
        }

        if ($search['context_type'] === 'post_type') {
            if ($search['context_value'] === 'attachment' && MetaBoxes::isMediaWithPermalinkDeactivated()) {
                echo 'no_results';
                wp_die();
            }

            $this->ajaxSearchPosts($search);
        }

        echo 'no_results';
        wp_die();
	}

    /**
     * @param array $search
     *
     * @return void
     */
    private function ajaxSearchTerms(array $search)
    {
        $taxonomy   = $search['context_value'];
        $searchTerm = $search['keyword'];
        $showAll    = $search['show_all'];
        $results    = array();

        if (! taxonomy_exists($taxonomy)) {
            echo 'no_results';
            wp_die();
        }

        $maxTermsToShowAll = self::$showAllResultsIfCountIsUpToArray['taxonomies'];

        $termsArgs = array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'number'     => 20
        );

        if ($searchTerm !== '') {
            $termsArgs['search'] = $searchTerm;
        } else {
            if (! $showAll) {
                echo 'no_results';
                wp_die();
            }

            /*
             * Show all terms only for small taxonomies.
             * Do not rely only on the JS flag, as the request can be modified.
             */
            $termsCount = wp_count_terms($taxonomy, array(
                'hide_empty' => false
            ));

            if (is_wp_error($termsCount) || (int) $termsCount < 1 || (int) $termsCount > $maxTermsToShowAll) {
                echo 'no_results';
                wp_die();
            }

            $termsArgs['number']  = $maxTermsToShowAll;
            $termsArgs['orderby'] = 'name';
            $termsArgs['order']   = 'ASC';
        }

        $terms = get_terms($termsArgs);

        if (is_wp_error($terms)) {
            $terms = array();
        }

        if ($searchTerm !== '' && (int) $searchTerm > 0) {
            $termById = get_term((int) $searchTerm, $taxonomy);

            if ($termById && ! is_wp_error($termById)) {
                $terms[] = $termById;
            }
        }

        if (! empty($terms)) {
            $seenTermIds = array();

            foreach ($terms as $term) {
                if (isset($seenTermIds[$term->term_id])) {
                    continue;
                }

                $seenTermIds[$term->term_id] = true;
                $termLink = get_term_link($term, $taxonomy);

                $results[] = array(
                    'id'       => $term->term_id,
                    'taxonomy' => $taxonomy,
                    'label'    => $term->name . ' / ID: ' . $term->term_id . ' / Taxonomy: ' . $taxonomy,
                    'link'     => (! is_wp_error($termLink) ? $termLink : '')
                );
            }
        }

        if (empty($results)) {
            echo 'no_results';
            wp_die();
        }

        echo wp_json_encode($results);
        wp_die();
    }

    /**
     * @param array $search
     *
     * @return void
     */
    private function ajaxSearchUsers(array $search)
    {
        $searchTerm = $search['keyword'];
        $showAll    = $search['show_all'];
        $results    = array();

        $maxUsersToShowAll = self::$showAllResultsIfCountIsUpToArray['users'];

        $usersArgs = array(
            'number' => 20
        );

        if ($searchTerm !== '') {
            $usersArgs['search']         = '*' . $searchTerm . '*';
            $usersArgs['search_columns'] = array('user_login', 'user_nicename', 'display_name', 'user_email');
        } else {
            if (! $showAll) {
                echo 'no_results';
                wp_die();
            }

            /*
             * Show all users only when the site has a small number of users.
             * Do not rely only on the JS flag, as the request can be modified.
             */
            $usersCountData = count_users();
            $usersCount     = isset($usersCountData['total_users']) ? (int) $usersCountData['total_users'] : 0;

            if ($usersCount < 1 || $usersCount > $maxUsersToShowAll) {
                echo 'no_results';
                wp_die();
            }

            $usersArgs['number']  = $maxUsersToShowAll;
            $usersArgs['orderby'] = 'display_name';
            $usersArgs['order']   = 'ASC';
        }

        $users = get_users($usersArgs);

        if ($searchTerm !== '' && (int) $searchTerm > 0) {
            $userById = get_userdata((int) $searchTerm);

            if ($userById) {
                $users[] = $userById;
            }
        }

        if (! empty($users)) {
            $seenUserIds = array();

            foreach ($users as $user) {
                if (isset($seenUserIds[$user->ID])) {
                    continue;
                }

                $seenUserIds[$user->ID] = true;

                $results[] = array(
                    'id'    => $user->ID,
                    'label' => $user->display_name . ' / ID: ' . $user->ID,
                    'link'  => get_author_posts_url($user->ID)
                );
            }
        }

        if (empty($results)) {
            echo 'no_results';
            wp_die();
        }

        echo wp_json_encode($results);
        wp_die();
    }

    /**
     * @param array $search
     *
     * @return void
     */
    private function ajaxSearchPosts(array $search)
    {
        global $wpdb;

        $postType   = $search['context_value'];
        $searchTerm = $search['keyword'];
        $showAll    = $search['show_all'];
        $results    = array();

        if (! post_type_exists($postType)) {
            echo 'no_results';
            wp_die();
        }

        // 'post', 'page', 'attachment', custom post types
        $showAllLimitKey = 'custom_post_types';

        if ($postType === 'post') {
            $showAllLimitKey = 'posts';
        } elseif ($postType === 'page') {
            $showAllLimitKey = 'pages';
        } elseif ($postType === 'attachment') {
            $showAllLimitKey = 'media';
        }

        $maxPostsToShowAll = self::$showAllResultsIfCountIsUpToArray[$showAllLimitKey];

        $postStatuses = ($postType === 'attachment')
            ? array('inherit')
            : array('publish', 'private');

        $queryDataByKeyword = array(
            'post_type'        => $postType,
            'post_status'      => $postStatuses,
            'posts_per_page'   => -1,
            'suppress_filters' => ($postType === 'attachment')
        );

        if ($searchTerm !== '') {
            $queryDataByKeyword['s'] = $searchTerm;

            if ($postType === 'attachment') {
                $queryDataByKeyword['orderby'] = 'date';
                $queryDataByKeyword['order']   = 'DESC';
            }
        } else {
            if ( ! $showAll) {
                echo 'no_results';
                wp_die();
            }

            /*
             * Show all entries only when the number of entries does not exceed
             * the configured limit for the selected post type.
             */
            $postCounts = wp_count_posts($postType);

            $postsCount = 0;

            foreach ($postStatuses as $postStatus) {
                if (isset($postCounts->{$postStatus})) {
                    $postsCount += (int) $postCounts->{$postStatus};
                }
            }

            if ($postsCount < 1 || $postsCount > $maxPostsToShowAll) {
                echo 'no_results';
                wp_die();
            }

            $queryDataByKeyword['posts_per_page'] = $maxPostsToShowAll;
            $queryDataByKeyword['orderby']        = ($postType === 'attachment')
                ? 'date'
                : 'title';
            $queryDataByKeyword['order']          = ($postType === 'attachment')
                ? 'DESC'
                : 'ASC';
        }

		// Standard search
		$query = new \WP_Query($queryDataByKeyword);

		// No results? Search by ID in case the admin put the post/page ID in the search box
	    if ($searchTerm !== '' && (int) $searchTerm > 0 && ! $query->have_posts()) {
	    	// This one works for any post type, including 'attachment'
		    $queryDataByID = array(
			    'post_type'        => $postType,
			    'post_status'      => array( 'publish', 'private' ),
			    'posts_per_page'   => -1,
			    'post__in'         => array((int) $searchTerm),
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
