<?php
namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\Menu;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\Settings;
use WpAssetCleanUp\Update;

/**
 * Class AssetsManagerAdmin
 * @package WpAssetCleanUp
 *
 * Actions taken within the Dashboard, inside the plugin area: "CSS & JS MANAGER" (main top menu) -- "MANAGE CSS/JS" (main tab)
 */
class AssetsManagerAdmin
{
    /**
     * @var array
     */
    public $data = array();

	/**
	 * AssetsManagerAdmin constructor.
	 */
	public function __construct()
    {
		if ( Misc::getVar('get', 'page') !== WPACU_PLUGIN_ID . '_assets_manager' ) {
			return;
		}

	    $wpacuSubPage = (isset($_GET['wpacu_sub_page']) && $_GET['wpacu_sub_page']) ? $_GET['wpacu_sub_page'] : 'manage_css_js';

	    $this->data = array(
		    'for'          => 'homepage', // default
		    'nonce_action' => WPACU_PLUGIN_ID . '_dash_assets_page_update_nonce_action',
		    'nonce_name'   => WPACU_PLUGIN_ID . '_dash_assets_page_update_nonce_name'
	    );

	    $this->data['site_url'] = get_site_url();

	    if (isset($_GET['wpacu_for']) && $_GET['wpacu_for'] !== '') {
		    $this->data['for'] = sanitize_text_field($_GET['wpacu_for']);
	    }

	    $this->data['wpacu_post_id'] = (isset($_GET['wpacu_post_id']) && $_GET['wpacu_post_id']) ? (int)$_GET['wpacu_post_id'] : false;

		if ($this->data['wpacu_post_id'] && $this->data['for'] === 'homepage') {
			// URI is like: /wp-admin/admin.php?page=wpassetcleanup_assets_manager&wpacu_post_id=POST_ID_HERE (without any "wpacu_for")
			// Proceed to detect the post type
            $this->data['for'] = self::detectPostTypeTypeFromRequestedPostId($this->data['wpacu_post_id']);
        }

	    if (Menu::isPluginPage()) {
		    $this->data['page'] = sanitize_text_field($_GET['page']);
	    }

	    $wpacuSettings = new Settings;
	    $this->data['wpacu_settings'] = $wpacuSettings->getAll();
	    $this->data['show_on_front']  = Misc::getShowOnFront();

	    if ($wpacuSubPage === 'manage_css_js' && (self::isSingularManageType($this->data['for']) || self::isArchiveManageType($this->data['for']) || self::isCustomPostTypeArchiveManageRequest($this->data['for']))) {
		    Misc::w3TotalCacheFlushObjectCache();

            if (self::isArchiveManageType($this->data['for']) || self::isCustomPostTypeArchiveManageRequest($this->data['for'])) {
                $this->archivePageActions();
                return;
            }

		    // Front page displays: A Static Page
		    if ($this->data['for'] === 'homepage' && $this->data['show_on_front'] === 'page') {
			    $this->data['show_on_front'] = get_option('show_on_front');
			    $this->data['page_on_front'] = get_option('page_on_front');

			    if ($this->data['show_on_front'] === 'page' && $this->data['page_on_front']) {
				    $this->data['page_on_front_title'] = get_the_title($this->data['page_on_front']);
			    }

			    $this->data['page_for_posts'] = get_option('page_for_posts');

			    if ($this->data['page_for_posts']) {
				    $this->data['page_for_posts_title'] = get_the_title($this->data['page_for_posts']);
			    }
		    }

		    // e.g., It could be the homepage tab loading a singular page set as the homepage in "Settings" -> "Reading"
		    $anyPostId = (int)Misc::getVar('post', 'wpacu_manage_singular_page_id');

		    if ($this->data['for'] === 'homepage' && ! $anyPostId) {
			    // "CSS & JS MANAGER" -- "Homepage" (e.g. "Your homepage displays" set as "Your latest posts")
			    $this->homepageActions();
		    } else {
			    // "CSS & JS MANAGER" --> "MANAGE CSS/JS"
			    // Case 1: "Homepage", if singular page set as the homepage in "Settings" -> "Reading")
			    // Case 2: "Posts"
			    // Case 3: "Pages"
			    // Case 4: "Custom Post Types" (e.g. WooCommerce product)
			    // Case 5: "Media" (attachment pages, rarely used)
			    $this->singularPageActions();
		    }
	    }
    }

    /**
     * @param $requestedPostId
     *
     * @return string
     */
    public static function detectPostTypeTypeFromRequestedPostId($requestedPostId)
    {
        global $wpdb;

        $query = $wpdb->prepare("SELECT `post_type` FROM `{$wpdb->posts}` WHERE `ID`='%d'", $requestedPostId);
        $requestedPostType = $wpdb->get_var($query);

        if ($requestedPostType === 'post') {
            $for = 'posts';
        } elseif ($requestedPostType === 'page') {
            $for = 'pages';
        } elseif ($requestedPostType === 'attachment') {
            $for = 'media_attachment';
        } elseif ($requestedPostType !== '') {
            $for = 'custom_post_types';
        } else {
            $for = '';
        }

        return $for;
    }

	/**
	 *
	 */
    public function homepageActions()
    {
        // Only continue if we are on the plugin's homepage edit mode
        if ( ! ( $this->data['for'] === 'homepage' && Misc::getVar('get', 'page') === WPACU_PLUGIN_ID . '_assets_manager' ) ) {
            return;
        }

        // Update action?
        if (! empty($_POST) && Misc::getVar( 'post', 'wpacu_manage_home_page_assets', false ) ) {
	        $wpacuNoLoadAssets = Misc::getVar( 'post', WPACU_PLUGIN_ID, array() );

	        $wpacuUpdate = new Update;

	        if ( ! (isset($_REQUEST[$this->data['nonce_name']])
                && wp_verify_nonce($_REQUEST[$this->data['nonce_name']], $this->data['nonce_action'])) ) {
		        add_action('wpacu_admin_notices', array($wpacuUpdate, 'changesNotMadeInvalidNonce'));
		        return;
	        }

	        // All good with the nonce? Do the changes!
	        $wpacuUpdate->updateFrontPage( $wpacuNoLoadAssets );
        }
    }

	/**
	 * Any post type, including the custom ones
	 */
	public function singularPageActions()
    {
	    $postId = (int)Misc::getVar('post', 'wpacu_manage_singular_page_id');

	    $isSingularPageAdminOwnPluginPageEdit = $postId > 0 &&
			( Misc::getVar('get', 'page') === WPACU_PLUGIN_ID . '_assets_manager' &&
			in_array( $this->data['for'], array('homepage', 'pages', 'posts', 'custom_post_types', 'media_attachment' ) ) );

	    // Only continue if the form was submitted for a singular page
	    // e.g. a post, a page (could be the homepage), a WooCommerce product page, any public custom post type
	    if ( ! $isSingularPageAdminOwnPluginPageEdit ) {
		    return;
	    }

	    if ( ! empty($_POST) ) {
		    // Update action?
		    $wpacuNoLoadAssets   = Misc::getVar( 'post', WPACU_PLUGIN_ID, array() );
		    $wpacuSingularPageUpdate = Misc::getVar( 'post', 'wpacu_manage_singular_page_assets', false );

		    // Could Be an Empty Array as Well so just is_array() is enough to use
		    if ( is_array( $wpacuNoLoadAssets ) && $wpacuSingularPageUpdate ) {
			    $wpacuUpdate = new Update;

			    if ( ! (isset($_REQUEST[$this->data['nonce_name']])
			            && wp_verify_nonce($_REQUEST[$this->data['nonce_name']], $this->data['nonce_action'])) ) {
				    add_action('wpacu_admin_notices', array($wpacuUpdate, 'changesNotMadeInvalidNonce'));
				    return;
			    }

			    if ($postId > 0) {
				    $wpacuUpdate = new Update;
				    $wpacuUpdate->savePosts($postId);
			    }
		    }
	    }
    }



    /**
     * Gets the selected archive-like page data from the current Dashboard request.
     * Used by the CSS/JS Manager for contexts that do not have a singular post ID:
     * taxonomy archives, author archives, search results, date archives and 404.
     *
     * @param string $for
     *
     * @return array
     */
    public static function getArchivePageDataFromRequest($for)
    {
        $archiveData = array(
            'is_valid' => false,
            'type'     => '',
            'label'    => '',
            'url'      => ''
        );

        if (! self::isArchiveManageType($for)) {
            if (! self::isCustomPostTypeArchiveManageRequest($for)) {
                return $archiveData;
            }
        }

        if (self::isCustomPostTypeArchiveManageRequest($for)) {
            $postType = sanitize_key(Misc::getVar('get', 'wpacu_post_type', ''));
            $archives = self::getCustomPostTypeArchives();

            if ($postType === '' || ! isset($archives[$postType])) {
                $postType = self::getDefaultCustomPostTypeArchive();
            }

            if ($postType !== '' && isset($archives[$postType])) {
                $archive = $archives[$postType];

                return array_merge($archiveData, array(
                    'is_valid'         => true,
                    'type'             => 'custom_post_type_archive',
                    'post_type'        => $postType,
                    'label'            => sprintf(__('%s Archive', 'wp-asset-clean-up'), $archive['label']),
                    'url'              => $archive['url'],
                    'notice'           => __('The rules set here apply to this custom post type archive and its pagination pages, not to the individual posts belonging to the post type.', 'wp-asset-clean-up')
                ));
            }

            $archiveData['error'] = __('No public custom post type archive is available on this website.', 'wp-asset-clean-up');

            return $archiveData;
        }

        if (in_array($for, array('category', 'tag', 'custom_taxonomies'), true)) {
            if ($for === 'category') {
                $taxonomy = 'category';
            } elseif ($for === 'tag') {
                $taxonomy = 'post_tag';
            } else {
                $taxonomy = sanitize_text_field(Misc::getVar('get', 'wpacu_taxonomy', ''));
            }

            $termId = (int)Misc::getVar('get', 'wpacu_term_id', 0);

            if ($taxonomy && $termId > 0) {
                $term = get_term($termId, $taxonomy);

                if ($term && ! is_wp_error($term)) {
                    $termLink = get_term_link($term, $taxonomy);

                    if (! is_wp_error($termLink)) {
                        $archiveData = array_merge($archiveData, array(
                            'is_valid' => true,
                            'type'     => 'taxonomy',
                            'taxonomy' => $taxonomy,
                            'term_id'  => $termId,
                            'label'    => sprintf('%s / ID: %d / Taxonomy: %s', $term->name, $termId, $taxonomy),
                            'url'      => $termLink
                        ));
                    }
                }
            }

            return $archiveData;
        }

        if ($for === 'author') {
            $authorId = (int)Misc::getVar('get', 'wpacu_author_id', 0);

            if ($authorId < 1) {
                $authorId = self::getSingleUserIdForAuthorArchive();
            }

            if ($authorId > 0) {
                $user = get_user_by('id', $authorId);

                if ($user) {
                    $archiveData = array_merge($archiveData, array(
                        'is_valid'  => true,
                        'type'      => 'author',
                        'author_id' => $authorId,
                        'label'     => sprintf('%s / ID: %d', $user->display_name, $authorId),
                        'url'       => get_author_posts_url($authorId),
                        'notice'    => __('The URL above is used to retrieve the loaded CSS/JS files. The rules set here apply to author archive pages.', 'wp-asset-clean-up')
                    ));
                }
            }

            return $archiveData;
        }

        if ($for === 'search') {
            $searchQuery = sanitize_text_field(Misc::getVar('get', 'wpacu_search_query', ''));

            if ($searchQuery === '') {
                $searchQuery = 'any-keyword-here';
            }

            return array_merge($archiveData, array(
                'is_valid'     => true,
                'type'         => 'search',
                'search_query' => $searchQuery,
                'label'        => sprintf(__('Search results for: %s', 'wp-asset-clean-up'), $searchQuery),
                'url'          => add_query_arg('s', $searchQuery, home_url('/'))
            ));
        }

        if ($for === 'date') {
            $latestDateArchiveUrl = self::getLatestValidDateArchiveUrl();

            if ($latestDateArchiveUrl) {
                $archiveData = array_merge($archiveData, array(
                    'is_valid' => true,
                    'type'     => 'date',
                    'label'    => __('Date Archives', 'wp-asset-clean-up'),
                    'url'      => $latestDateArchiveUrl,
                    'notice'   => __('The URL above is the latest valid date archive found on this website and is used only to retrieve the loaded CSS/JS files. The rules set here apply to all date archive pages.', 'wp-asset-clean-up')
                ));
            } else {
                $archiveData['error'] = __('No valid date archive could be found because there are no published posts available.', 'wp-asset-clean-up');
            }

            return $archiveData;
        }

        if ($for === '404_not_found') {
            $clean404Url = self::get404TestUrl(false);
            $fetch404Url = self::get404TestUrl(true);

            $archiveData = array_merge($archiveData, array(
                'is_valid'  => true,
                'type'      => '404',
                'label'     => __('404 Not Found', 'wp-asset-clean-up'),
                'url'       => $clean404Url,
                'fetch_url' => $fetch404Url,
                'notice'    => __('The URL above is used only to retrieve the loaded CSS/JS files from the active theme\'s 404 template. The rules set here apply to all 404 Not Found pages.', 'wp-asset-clean-up')
            ));
        }

        return $archiveData;
    }

    /**
     * Gets the latest valid date archive URL available on this website.
     * The returned URL is used only as a real sample URL for retrieving the loaded CSS/JS files;
     * the saved rules apply to all date archive pages.
     *
     * @return string|false
     */
    public static function getLatestValidDateArchiveUrl()
    {
        $latestPostIds = get_posts(array(
            'post_type'              => 'post',
            'post_status'            => 'publish',
            'posts_per_page'         => 1,
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ));

        if (empty($latestPostIds)) {
            return false;
        }

        $postId = (int)$latestPostIds[0];
        $year   = (int)get_the_date('Y', $postId);
        $month  = (int)get_the_date('m', $postId);

        if ($year < 1 || $month < 1) {
            return false;
        }

        return get_month_link($year, $month);
    }

    /**
    * Gets a test URL for the 404 page.
    *
    * @param bool $forFetch If true, append the internal force-404 query argument.
    *
    * @return string
    */
   public static function get404TestUrl($forFetch = false)
   {
       $languageCode = apply_filters('wpml_current_language', null);

       if ( ! is_string($languageCode) || $languageCode === '' || $languageCode === 'all') {
           $languageCode = isset($_REQUEST['lang']) && is_string($_REQUEST['lang'])
               ? sanitize_key(wp_unslash($_REQUEST['lang']))
               : '';
       }

       if ($languageCode === '') {
           $languageCode = apply_filters('wpml_default_language', null);
       }

       $wpmlSettings = get_option('icl_sitepress_settings', array());

       if (($languageCode === '' || $languageCode === 'all')
           && is_array($wpmlSettings)
           && ! empty($wpmlSettings['default_language'])) {
           $languageCode = (string)$wpmlSettings['default_language'];
       }

       $languageCode = is_string($languageCode) ? sanitize_key($languageCode) : '';
       $slug = 'wpacu-test-404-page-' . substr(md5(home_url() . '|' . $languageCode), 0, 12);
       $url  = home_url('/' . $slug . '/');

       if ($languageCode !== '') {
           // In Dashboard requests WPML can leave home_url() at the unprefixed
           // site root. Resolve the artificial 404 URL for the selected language
           // explicitly so directory/domain/query-string language modes are kept.
           $localizedUrl = apply_filters('wpml_permalink', $url, $languageCode, true);

           if (is_string($localizedUrl) && esc_url_raw($localizedUrl) !== '') {
               $url = $localizedUrl;
           }

           $url = self::applyWpmlUrlFallback($url, $slug, $languageCode, $wpmlSettings);
       }

       $url = apply_filters('wpacu_404_test_url', $url, $languageCode);

       if (url_to_postid($url)) {
           $fallbackSlug = 'wpacu-test-404-page-' . substr(md5(home_url() . '|' . $languageCode . '|' . time()), 0, 12);
           $url = home_url('/' . $fallbackSlug . '/');

           if ($languageCode !== '') {
               $localizedUrl = apply_filters('wpml_permalink', $url, $languageCode, true);

               if (is_string($localizedUrl) && esc_url_raw($localizedUrl) !== '') {
                   $url = $localizedUrl;
               }

               $url = self::applyWpmlUrlFallback($url, $fallbackSlug, $languageCode, $wpmlSettings);
           }

           $url = apply_filters('wpacu_404_test_url', $url, $languageCode);
       }

       if ($forFetch) {
           $url = add_query_arg('wpacu_force_404_template', 1, $url);
       }

       return $url;
   }

   /**
    * Build the WPML URL directly when its filters are unavailable in an admin request.
    *
    * @param string $url
    * @param string $slug
    * @param string $languageCode
    * @param mixed  $wpmlSettings
    *
    * @return string
    */
   private static function applyWpmlUrlFallback($url, $slug, $languageCode, $wpmlSettings)
   {
       if ($languageCode === '' || ! is_array($wpmlSettings)) {
           return $url;
       }

       $negotiationType = isset($wpmlSettings['language_negotiation_type'])
           ? (int)$wpmlSettings['language_negotiation_type']
           : 0;

       if ($negotiationType === 3) {
           return add_query_arg('lang', $languageCode, $url);
       }

       if ($negotiationType === 2) {
           $languageDomains = isset($wpmlSettings['language_domains']) && is_array($wpmlSettings['language_domains'])
               ? $wpmlSettings['language_domains']
               : array();
           $languageDomain = isset($languageDomains[$languageCode]) && is_string($languageDomains[$languageCode])
               ? trim($languageDomains[$languageCode])
               : '';

           if ($languageDomain === '') {
               return $url;
           }

           $urlParts = wp_parse_url($url);

           if ( ! is_array($urlParts) || empty($urlParts['host'])) {
               return $url;
           }

           $urlScheme = isset($urlParts['scheme']) && in_array(strtolower($urlParts['scheme']), array('http', 'https'), true)
               ? strtolower($urlParts['scheme'])
               : 'https';

           if (strpos($languageDomain, '//') === 0) {
               $languageDomain = $urlScheme . ':' . $languageDomain;
           } elseif ( ! preg_match('#^https?://#i', $languageDomain)) {
               if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $languageDomain)) {
                   return $url;
               }

               $languageDomain = $urlScheme . '://' . ltrim($languageDomain, '/');
           }

           $domainParts = wp_parse_url($languageDomain);

           if ( ! is_array($domainParts) || empty($domainParts['host'])) {
               return $url;
           }

           $targetScheme = isset($domainParts['scheme']) ? strtolower($domainParts['scheme']) : $urlScheme;

           if ( ! in_array($targetScheme, array('http', 'https'), true)) {
               return $url;
           }

           $targetUrl = $targetScheme . '://' . $domainParts['host'];

           if (isset($domainParts['port'])) {
               $targetUrl .= ':' . (int)$domainParts['port'];
           }

           $targetUrl .= isset($urlParts['path']) ? $urlParts['path'] : '/';

           if (isset($urlParts['query']) && $urlParts['query'] !== '') {
               $targetUrl .= '?' . $urlParts['query'];
           }

           if (isset($urlParts['fragment']) && $urlParts['fragment'] !== '') {
               $targetUrl .= '#' . $urlParts['fragment'];
           }

           return esc_url_raw($targetUrl) !== '' ? $targetUrl : $url;
       }

       if ($negotiationType !== 1) {
           return $url;
       }

       $defaultLanguage = ! empty($wpmlSettings['default_language'])
           ? sanitize_key((string)$wpmlSettings['default_language'])
           : '';
       $directoryForDefaultLanguage = ! empty($wpmlSettings['urls']['directory_for_default_language']);
       $shouldUseDirectory = $languageCode !== $defaultLanguage || $directoryForDefaultLanguage;

       if ( ! $shouldUseDirectory) {
           return $url;
       }

       $homeUrl  = trailingslashit(home_url('/'));
       $homePath = (string)parse_url($homeUrl, PHP_URL_PATH);
       $urlPath  = (string)parse_url($url, PHP_URL_PATH);
       $relativePath = ltrim(substr($urlPath, strlen(rtrim($homePath, '/'))), '/');

       if (basename(rtrim($homePath, '/')) === $languageCode) {
           return $url;
       }

       if (strpos($relativePath, $languageCode . '/') === 0) {
           return $url;
       }

       return trailingslashit($homeUrl . $languageCode) . trailingslashit($slug);
   }

    /**
     * If the website has exactly one user, use it automatically as a sample author archive.
     *
     * @return int
     */
    public static function getSingleUserIdForAuthorArchive()
    {
        $users = get_users(array(
            'number' => 2,
            'fields' => array('ID')
        ));

        if (count($users) !== 1) {
            return 0;
        }

        return (int)$users[0]->ID;
    }

    /**
     * Returns all public and publicly queryable custom post types that have at least one
     * published or private entry in the current Dashboard language context.
     * Used by the Dashboard CSS/JS Manager so the post type dropdown,
     * selected URL context and autocomplete use the same source.
     *
     * @return array
     */
    public static function getCustomPostTypesWithPosts()
    {
        $postTypes = get_post_types(
            array(
                'public'             => true,
                'publicly_queryable' => true,
                '_builtin'           => false
            ),
            'objects'
        );

        $postTypesList = array();

        if (empty($postTypes)) {
            return $postTypesList;
        }

        foreach ($postTypes as $postTypeKey => $postTypeObj) {
            $query = new \WP_Query(
                array(
                    'post_type'              => $postTypeKey,
                    'post_status'            => array('publish', 'private'),
                    'posts_per_page'         => 1,
                    'fields'                 => 'ids',

                    // Needed because $query->found_posts is used below.
                    'no_found_rows'          => false,

                    // Allow WPML and similar plugins to filter the query.
                    'suppress_filters'       => false,

                    'ignore_sticky_posts'    => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false
                )
            );

            $postsCount = (int)$query->found_posts;

            if ($postsCount < 1) {
                continue;
            }

            $postTypesList[$postTypeKey] = array(
                'label' => isset($postTypeObj->labels->name)
                    ? $postTypeObj->labels->name
                    : $postTypeKey,
                'count' => $postsCount
            );
        }

        return $postTypesList;
    }

    /**
     * Returns the first public custom post type that has at least one published or private entry.
     *
     * @return string
     */
    public static function getDefaultCustomPostTypeWithPosts()
    {
        $postTypesList = self::getCustomPostTypesWithPosts();

        if (empty($postTypesList)) {
            return '';
        }

        reset($postTypesList);

        return key($postTypesList);
    }

    /**
     * Returns public custom post types that expose a real archive URL.
     *
     * @return array
     */
    public static function getCustomPostTypeArchives()
    {
        $postTypes = get_post_types(array(
            'public'             => true,
            'publicly_queryable' => true,
            '_builtin'           => false
        ), 'objects');

        $archives = array();

        foreach ($postTypes as $postTypeKey => $postTypeObj) {
            if (empty($postTypeObj->has_archive)) {
                continue;
            }

            $archiveUrl = get_post_type_archive_link($postTypeKey);

            if (! $archiveUrl) {
                continue;
            }

            $archives[$postTypeKey] = array(
                'label' => isset($postTypeObj->labels->name) ? $postTypeObj->labels->name : $postTypeKey,
                'url'   => $archiveUrl
            );
        }

        return $archives;
    }

    /**
     * @return string
     */
    public static function getDefaultCustomPostTypeArchive()
    {
        $archives = self::getCustomPostTypeArchives();

        if (empty($archives)) {
            return '';
        }

        reset($archives);

        return key($archives);
    }



    /**
     * Returns all public custom taxonomies that have at least one term.
     * Used by the Dashboard CSS/JS Manager so the taxonomy dropdown and autocomplete
     * use the same default source on the initial Custom Taxonomy tab load.
     *
     * @return array
     */
    public static function getCustomTaxonomiesWithTerms()
    {
        $taxonomies = get_taxonomies(array('public' => true), 'objects');

        $taxonomiesList = array();

        if (empty($taxonomies)) {
            return $taxonomiesList;
        }

        foreach ($taxonomies as $taxonomyKey => $taxonomyObj) {
            if (in_array($taxonomyKey, array('category', 'post_tag'), true)) {
                continue;
            }

            $termsCount = wp_count_terms($taxonomyKey, array(
                'hide_empty' => false
            ));

            if (is_wp_error($termsCount)) {
                continue;
            }

            $termsCount = (int)$termsCount;

            if ($termsCount < 1) {
                continue;
            }

            $taxonomiesList[$taxonomyKey] = array(
                'label' => isset($taxonomyObj->labels->name) ? $taxonomyObj->labels->name : $taxonomyKey,
                'count' => $termsCount
            );
        }

        return $taxonomiesList;
    }

    /**
     * Returns the first public custom taxonomy that has at least one term.
     *
     * @return string
     */
    public static function getDefaultCustomTaxonomyWithTerms()
    {
        $taxonomiesList = self::getCustomTaxonomiesWithTerms();

        if (empty($taxonomiesList)) {
            return '';
        }

        reset($taxonomiesList);

        return key($taxonomiesList);
    }

    /**
     * @param string $for
     *
     * @return bool
     */
    public static function isSingularManageType($for)
    {
        return in_array($for, array(
            'homepage',
            'pages',
            'posts',
            'custom_post_types',
            'media_attachment'
        ), true);
    }

    /**
     * @param string $for
     *
     * @return bool
     */
    public static function isArchiveManageType($for)
    {
        return in_array($for, array(
            'category',
            'tag',
            'custom_taxonomies',
            'search',
            'author',
            'date',
            '404_not_found'
        ), true);
    }

    /**
     * @param string $for
     *
     * @return bool
     */
    public static function isCustomPostTypeArchiveManageRequest($for)
    {
        return $for === 'custom_post_types'
            && Misc::getVar('get', 'wpacu_post_type_view', 'singular') === 'archives';
    }

    /**
     * Handles updates from the plugin's own Dashboard CSS/JS Manager for archive-like pages
     * that do not have a singular post ID: taxonomy, author, search, date and 404.
     *
     * @return void
     */
    public function archivePageActions()
    {
        if (Misc::getVar('get', 'page') !== WPACU_PLUGIN_ID . '_assets_manager') {
            return;
        }

        if (! self::isArchiveManageType($this->data['for']) && ! self::isCustomPostTypeArchiveManageRequest($this->data['for'])) {
            return;
        }

        if (empty($_POST)) {
            return;
        }

        $wpacuArchivePageUpdate = Misc::getVar('post', 'wpacu_manage_archive_page_assets', false);

        if (! $wpacuArchivePageUpdate) {
            return;
        }

        $wpacuNoLoadAssets = Misc::getVar('post', WPACU_PLUGIN_ID, array());
        $wpacuUpdate       = new Update;

        if ( ! (isset($_REQUEST[$this->data['nonce_name']])
                && wp_verify_nonce($_REQUEST[$this->data['nonce_name']], $this->data['nonce_action'])) ) {
            add_action('wpacu_admin_notices', array($wpacuUpdate, 'changesNotMadeInvalidNonce'));
            return;
        }

        do_action('wpacu_internal_dashboard_archive_page_update', $this->data['for'], $wpacuNoLoadAssets);
    }

	/**
	 * Called in Menu.php (within "admin_menu" hook via "activeMenu" method)
	 */
	public function renderPage()
    {
	    MainAdmin::instance()->parseTemplate('admin-page-assets-manager', $this->data, true);
    }
}
