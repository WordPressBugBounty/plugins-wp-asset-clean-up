<?php
// Exit if accessed directly
if ( ! defined('ABSPATH') ) {
	exit;
}

// Set the permission constants if not already set.
// Use @ in front of "fileperms" as sometimes (e.g. in WP CLI), because
// the user does not have the right permission to use this function for the targeted files
if ( ! defined('FS_CHMOD_DIR') ) {
    define('FS_CHMOD_DIR', @fileperms(ABSPATH) & 0777 | 0755);
}

if ( ! defined('FS_CHMOD_FILE') ) {
    define('FS_CHMOD_FILE', @fileperms(ABSPATH . 'index.php') & 0777 | 0644);
}

if ( ! defined('WPACU_PLUGIN_TITLE') ) {
    define('WPACU_PLUGIN_TITLE', 'Asset CleanUp'); // a short version of the plugin name
}

if ( isset($_GET['wpacu_clean_load']) && ! is_admin() ) {
    // Autoptimize
    $_GET['ao_noptimize'] = $_REQUEST['ao_noptimize'] = '1';

    // LiteSpeed Cache
    if ( ! defined('LITESPEED_DISABLE_ALL')) {
        define('LITESPEED_DISABLE_ALL', true);
    }

    add_action('litespeed_disable_all', static function ($reason) {
        do_action('litespeed_debug', '[API] Disabled_all due to: A clean load of the page was requested via ' . WPACU_PLUGIN_TITLE);
    });

    // No "WP-Optimize – Clean, Compress, Cache." minify
    add_filter('pre_option_wpo_minify_config', function () {
        return array();
    });
}


if ( ! function_exists('wpacuDefineConstant') ) {
    /**
     * @param $name
     * @param $value // (default is (bool) true)
     *
     * @return void
     */
    function wpacuDefineConstant($name, $value = true)
    {
        if ( ! defined($name) ) {
            define($name, $value);
        }
    }
}

if ( ! function_exists('wpacuIsDefinedConstant') ) {
    /**
     * @param $name
     * @param $value
     *
     * @return bool
     */
    function wpacuIsDefinedConstant($name, $value = true)
    {
        $condition = defined($name) && constant($name) === $value;

        if ($value === true) {
            // If the default value "true" is used, we will consider any value as valid
            // e.g. some people might put defined('WPACU_VALUE_HERE', 'true'), instead of: defined('WPACU_VALUE_HERE', true)
            // so, it will be validated (bool or string)
            $condition = $condition || (defined($name) && constant($name));
        }

        return $condition;
    }
}

if ( ! function_exists('wpacuGetConstant') ) {
    /**
     * Get the actual value of a constant
     * It also checks if the constant is defined
     * If it's not, false is returned
     *
     * @param $name
     *
     * @return bool
     */
    function wpacuGetConstant($name)
    {
        return defined($name) ? constant($name) : false;
    }
}

// In case JSON library is not enabled (rare cases)
wpacuDefineConstant('JSON_ERROR_NONE', 0);

if ( ! function_exists('wpacuJsonLastError') ) {
    /**
     * @return int
     */
    function wpacuJsonLastError()
    {
        if (function_exists('json_last_error')) {
            return json_last_error();
        }

        // This is added just in case (to avoid any PHP errors), as "json_last_error" should be available
        // starting from PHP 5.3 (which is below the minimum PHP version supported by Asset CleanUp)
        return JSON_ERROR_NONE;
    }
}

if ( ! function_exists('wpacuNoGlobalDataSet') ) {
    /**
     * @return void
     */
    function wpacuNoGlobalDataSet()
    {
        wpacuDefineConstant('WPACU_NO_ASSETS_PRELOADED');
        wpacuDefineConstant('WPACU_NO_REGEX_RULES_SET_FOR_ASSETS');
        wpacuDefineConstant('WPACU_NO_MEDIA_QUERIES_LOAD_RULES_SET_FOR_ASSETS');
        wpacuDefineConstant('WPACU_NO_POSITIONS_CHANGED_FOR_ASSETS');
        wpacuDefineConstant('WPACU_NO_IGNORE_CHILD_RULES_SET_FOR_ASSETS');
        wpacuDefineConstant('WPACU_NO_SITE_WIDE_SCRIPT_ATTRS_SET');
    }
}

if ( ! function_exists('wpacuGetGlobalData') ) {
    /**
     * @return array
     */
    function wpacuGetGlobalData()
    {
        if ( ! empty($GLOBALS['wpacu_global_data_json_decoded']) ) {
            return $GLOBALS['wpacu_global_data_json_decoded'];
        }

        $globalRulesDbListJson = get_option('wpassetcleanup_global_data');

        if ( ! $globalRulesDbListJson ) {
            wpacuNoGlobalDataSet();
            $GLOBALS['wpacu_global_data_json_decoded'] = array();
            return array();
        }

        $globalRulesDbList = @json_decode($globalRulesDbListJson, true);

        if (wpacuJsonLastError() !== JSON_ERROR_NONE) {
            wpacuNoGlobalDataSet();
            $GLOBALS['wpacu_global_data_json_decoded'] = array();
            return array();
        }

        // Backend Optimization: Prevent further PHP code from triggering if the following constants are set; suitable for the front-end view
        if ( ! is_admin() ) {
            if ( ! (isset($_GET['wpacu_preload_css']) || isset($_GET['wpacu_preload_css_async']) || isset($_GET['wpacu_preload_js'])) &&
                 ! (isset($globalRulesDbList['styles']['preloads']) || isset($globalRulesDbList['scripts']['preloads'])) ) {
                wpacuDefineConstant('WPACU_NO_ASSETS_PRELOADED');
            }

            // Matches both "unload_regex" and "load_regex"
            if ( ! (isset($globalRulesDbList['styles']  ['unload_regex']) ||
                    isset($globalRulesDbList['scripts'] ['unload_regex']) ||
                    isset($globalRulesDbList['styles']  ['load_regex']) ||
                    isset($globalRulesDbList['scripts'] ['load_regex'])) ) {
                wpacuDefineConstant('WPACU_NO_REGEX_RULES_SET_FOR_ASSETS');
            }

            if ( ! (isset($globalRulesDbList['styles']['media_queries_load']) || isset($globalRulesDbList['scripts']['media_queries_load'])) ) {
                wpacuDefineConstant('WPACU_NO_MEDIA_QUERIES_LOAD_RULES_SET_FOR_ASSETS');
            }

            if ( ! (isset($globalRulesDbList['styles']['positions']) || isset($globalRulesDbList['scripts']['positions'])) ) {
                wpacuDefineConstant('WPACU_NO_POSITIONS_CHANGED_FOR_ASSETS');
            }

            if ( ! (isset($globalRulesDbList['styles']['ignore_child']) || isset($globalRulesDbList['scripts']['ignore_child'])) ) {
                wpacuDefineConstant('WPACU_NO_IGNORE_CHILD_RULES_SET_FOR_ASSETS');
            }

            if ( strpos($globalRulesDbListJson, '"attributes":["') === false ) {
                wpacuDefineConstant('WPACU_NO_SITE_WIDE_SCRIPT_ATTRS_SET');
            }
        }

        $GLOBALS['wpacu_global_data_json_decoded'] = $globalRulesDbList;
        return $globalRulesDbList;
    }
}

if ( ! function_exists('assetCleanUpClearAutoptimizeCache') ) {
	/*
	 * By default Autoptimize Cache is cleared after certain Asset CleanUp actions
	 *
	 * To be set in wp-config.php if necessary to deactivate this behaviour
	 * define('WPACU_DO_NOT_CLEAR_AUTOPTIMIZE_CACHE', true);
	 *
	 * @return bool
	 */
	function assetCleanUpClearAutoptimizeCache()
	{
		return ! wpacuIsDefinedConstant('WPACU_DO_NOT_ALSO_CLEAR_AUTOPTIMIZE_CACHE');
	}
}

if ( ! function_exists('assetCleanUpClearCacheEnablerCache') ) {
	/*
	 * By default "Cache Enabler" Cache is cleared after certain Asset CleanUp actions
	 *
	 * To be set in wp-config.php if necessary to deactivate this behaviour
	 * define('WPACU_DO_NOT_ALSO_CLEAR_CACHE_ENABLER_CACHE', true);
	 *
	 * @return bool
	 */
	function assetCleanUpClearCacheEnablerCache()
	{
		return ! wpacuIsDefinedConstant('WPACU_DO_NOT_ALSO_CLEAR_CACHE_ENABLER_CACHE');
	}
}

if ( ! function_exists('assetCleanUpIsRestCall') ) {
	/**
	 *
	 * @return bool
	 */
	function assetCleanUpIsRestCall()
	{
		if ( ! isset($_SERVER['REQUEST_URI']) ) {
			return false;
		}

		$cleanRequestUri = trim( $_SERVER['REQUEST_URI'], '?' );
		if ( strpos( $cleanRequestUri, '?' ) !== false ) {
			list ( $cleanRequestUri ) = explode( '?', $cleanRequestUri );
		}

		$restUrlPrefix = function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json';

		// At least one of the following conditions has to match
		if ( wpacuIsDefinedConstant( 'REST_REQUEST' ) ||
             ( strpos( $_SERVER['REQUEST_URI'], '/' . $restUrlPrefix . '/' ) !== false ) ||
             ( strpos( $_SERVER['REQUEST_URI'], '/'.$restUrlPrefix.'/wp/v2/' ) !== false ) ||
             ( strpos( $cleanRequestUri, '/'.$restUrlPrefix.'/wc/' ) !== false ) ) {
			return true;
		}

		$parseUrl     = parse_url( get_site_url() );
		$parseUrlPath = isset( $parseUrl['path'] ) ? $parseUrl['path'] : '';

		// We want to make sure the RegEx rules will be working fine if certain characters (e.g. Thai ones) are used
		$requestUriAsItIs = rawurldecode( $_SERVER['REQUEST_URI'] );

		$targetUriAfterSiteUrl = trim( str_replace( array( get_site_url(), $parseUrlPath ), '', $requestUriAsItIs ), '/' );

		if ( strpos( $targetUriAfterSiteUrl, $restUrlPrefix. '/' ) === 0 ) {
			// WooCommerce, Thrive Ovation
			if ( strpos( $targetUriAfterSiteUrl, $restUrlPrefix.'/wc/' ) === 0 || strpos( $targetUriAfterSiteUrl, $restUrlPrefix.'/tvo/' ) === 0 ) {
				return true;
			}

			// Other plugins with a similar pattern
			if ( $targetUriAfterSiteUrl === $restUrlPrefix ||
			     $targetUriAfterSiteUrl === $restUrlPrefix.'/' ||
			     preg_match( '#'.$restUrlPrefix.'/(.*?)/v#', $targetUriAfterSiteUrl ) ||
			     preg_match( '#'.$restUrlPrefix.'/(|\?)#', $targetUriAfterSiteUrl ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists('wpacuUriHasAnyPublicWpQuery') ) {
    /**
     *
     * @param $parseTargetUriCleanQuery
     * @param $publicQueryVars
     *
     * @return bool
     */
    function wpacuUriHasAnyPublicWpQuery( $parseTargetUriCleanQuery, $publicQueryVars )
    {
        parse_str( $parseTargetUriCleanQuery, $outputStr );

        foreach ( $publicQueryVars as $publicQueryVar ) {
            if ( isset( $outputStr[ $publicQueryVar ] ) && $outputStr[ $publicQueryVar ] ) {
                return true;
            }
        }

        return false;
    }
}

if ( ! function_exists( 'wpacuUriHasOnlyCommonQueryStrings' ) ) {
    /**
     * @param $parseTargetUriCleanQuery
     * @param $ignoreQueryStrings
     *
     * @return bool
     */
    function wpacuUriHasOnlyCommonQueryStrings( $parseTargetUriCleanQuery, $ignoreQueryStrings )
    {
        parse_str( $parseTargetUriCleanQuery, $outputStr );

        // Nothing from the common list? Check if the homepage URL has common query strings
        // If it has, return true, otherwise return false, as it might not be a homepage, but a page performing an action from a certain plugin
        foreach ( array_keys( $outputStr ) as $currentQueryString ) {
            if ( ! in_array( $currentQueryString, $ignoreQueryStrings ) ) {
                return false;
            }
        }

        return true;
    }
}

if ( ! function_exists('wpacuGetAllActivePluginsFromDbOptionsTable') ) {
    /**
     * @return array|mixed|string
     */
    function wpacuGetAllActivePluginsFromDbOptionsTable()
    {
        if (isset($GLOBALS['wpacu_active_plugins_from_db'])) {
            return $GLOBALS['wpacu_active_plugins_from_db'];
        }

        global $wpdb;

        $sqlQuery                = <<<SQL
SELECT option_value FROM `{$wpdb->options}` WHERE option_name='active_plugins'
SQL;
        $activePluginsSerialized = $wpdb->get_var( $sqlQuery );

        $GLOBALS['wpacu_active_plugins_from_db'] = maybe_unserialize( $activePluginsSerialized ) ?: array();

        return $GLOBALS['wpacu_active_plugins_from_db'];
    }
}

if ( ! function_exists('wpacuWpmlGetAllActiveLangTags') ) {
    /**
     * @return array
     */
    function wpacuWpmlGetAllActiveLangTags()
    {
        if ( isset($GLOBALS['wpacu_wpml_all_active_lang_tags']) ) {
            return $GLOBALS['wpacu_wpml_all_active_lang_tags'];
        }

        // Only continue if "WPML Multilingual CMS" is active
        if ( ! in_array('sitepress-multilingual-cms/sitepress.php', wpacuGetAllActivePluginsFromDbOptionsTable()) ) {
            $GLOBALS['wpacu_wpml_all_active_lang_tags'] = array();
            return $GLOBALS['wpacu_wpml_all_active_lang_tags'];
        }

        global $wpdb;

        $sqlQuery = <<<SQL
SELECT l.code
  FROM `{$wpdb->prefix}icl_languages` l
  JOIN `{$wpdb->prefix}icl_languages_translations` nt ON ( nt.language_code = l.code AND nt.display_language_code = l.code )
  LEFT OUTER JOIN `{$wpdb->prefix}icl_languages_translations` lt ON ( l.code = lt.language_code )
WHERE l.active = 1
GROUP BY l.code
SQL;
        $GLOBALS['wpacu_wpml_all_active_lang_tags'] = $wpdb->get_col($sqlQuery);
        return $GLOBALS['wpacu_wpml_all_active_lang_tags'];
    }
}

if ( ! function_exists( 'wpmlRemoveLangTagFromUri') ) {
    /**
     * @param $parseTargetUriCleanPath
     *
     * @return mixed
     */
    function wpmlRemoveLangTagFromUri( $parseTargetUriCleanPath )
    {
        if ($parseTargetUriCleanPath === '/') {
            // No point in making calls to the database
            // It's the home page (default language)
            return $parseTargetUriCleanPath;
        }

        $activeLangTags = wpacuWpmlGetAllActiveLangTags();

        if (empty($activeLangTags)) {
            // WPML is inactive, just return the path as it is
            return $parseTargetUriCleanPath;
        }

        foreach ($activeLangTags as $langTag) {
            $endsWithLangTagWithoutSlash = wpacuEndsWith($parseTargetUriCleanPath,'/'.$langTag);
            $endsWithLangTagWithSlash    = wpacuEndsWith($parseTargetUriCleanPath,'/'.$langTag.'/');

            if ($endsWithLangTagWithSlash) {
                $parseTargetUriCleanPath = substr($parseTargetUriCleanPath, 0, -strlen('/'.$langTag.'/'));
                break;
            }

            if ($endsWithLangTagWithoutSlash) {
                $parseTargetUriCleanPath = substr($parseTargetUriCleanPath, 0, -strlen('/'.$langTag));
                break;
            }
        }

        if ($parseTargetUriCleanPath === '') {
            // Was the language tag stripped? Keep the "/" as it's always the only one from the URI
            $parseTargetUriCleanPath = '/';
        }

        return $parseTargetUriCleanPath;
    }
}

if ( ! function_exists( 'wpacuIsHomePageUrl') ) {
    /**
     * @param $requestUriAsItIs
     *
     * @return bool
     */
    function wpacuIsHomePageUrl($requestUriAsItIs)
    {
        if (defined('WPACU_IS_HOME_PAGE_URL_EARLY_CHECK')) {
            return WPACU_IS_HOME_PAGE_URL_EARLY_CHECK;
        }

        if (isset($_SERVER['REQUEST_URI'])) {
            $compareOne = parse_url(get_site_url(), PHP_URL_PATH);
            $compareOne = $compareOne ? rtrim($compareOne, '/') : $compareOne;
            $compareTwo = $_SERVER['REQUEST_URI'] ? rtrim($_SERVER['REQUEST_URI'], '/') : $_SERVER['REQUEST_URI'];

            if ( $_SERVER['REQUEST_URI'] === '/' ||
                 $compareOne === $compareTwo ) {
                // Obviously, the home page, no further checks necessary
                define( 'WPACU_IS_HOME_PAGE_URL_EARLY_CHECK', true );
                return true;
            }
        }

        // e.g. www.mydomain.com/?add-to-cart=.... - this is not a homepage
        foreach (array('edd_action', 'add-to-cart') as $commonAction) {
            if (isset($_REQUEST[$commonAction])) {
                define('WPACU_IS_HOME_PAGE_URL_EARLY_CHECK', false);
                return false;
            }
        }

        $wpacuIsAjaxRequest = ( ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest' );

        if ($wpacuIsAjaxRequest && ! array_key_exists(WPACU_PLUGIN_ID . '_load', $_GET)) {
            // External AJAX request on the home page
            // It could be from a different plugin, thus this will not be detected as the homepage
            // as it might be an action URL from a specific plugin such as Gravity Forms
            define('WPACU_IS_HOME_PAGE_URL_EARLY_CHECK', false);
            return false;
        }

        $publicQueryVars = apply_filters(
            'wpacu_public_query_strings',
            array( 'm', 'p', 'posts', 'w', 'cat', 'withcomments', 'withoutcomments', 's', 'search', 'exact', 'sentence', 'calendar', 'page', 'paged', 'more', 'tb', 'pb', 'author', 'order', 'orderby', 'year', 'monthnum', 'day', 'hour', 'minute', 'second', 'name', 'category_name', 'tag', 'feed', 'author_name', 'pagename', 'page_id', 'error', 'attachment', 'attachment_id', 'subpost', 'subpost_id', 'robots', 'favicon', 'taxonomy', 'term', 'cpage', 'post_type', 'embed' )
        );

        // These query strings could be skipped when checking the homepage as they do not signify specific actions
        // Some are coming from Facebook ads, or they contain strings specific for Google Analytics for tracking purposes
        // e.g. the homepage could be https://yoursite.com/?utm_source=[...] or https://yoursite.com/?utm_source=fbclid=[...]
        $skipQueryStringsForHomepageDetection = array(
            '_ga',
            '_ke',
            'adgroupid',
            'adid',
            'age-verified',
            'ao_noptimize',
            'campaignid',
            'ck_subscriber_id', // ConvertKit's query parameter
            'cn-reloaded',
            'dclid',
            'dm_i', // dotdigital
            'dm_t', // dotdigital
            'ef_id',
            'epik', // Pinterest
            'fb_action_ids',
            'fb_action_types',
            'fb_source',
            'fbclick',
            'fbclid',
            'gclid',
            'gclsrc',

            // GoDataFeed
            'gdfms',
            'gdftrk',
            'gdffi',

            // Mailchimp
            'mc_cid',
            'mc_eid',

            // Marketo (tracking users)
            'mkt_tok',

            // Microsoft Click ID
            'msclkid',

            // Matomo
            'mtm_campaign',
            'mtm_cid',
            'mtm_content',
            'mtm_keyword',
            'mtm_medium',
            'mtm_source',

            // Piwik PRO URL builder
            'pk_campaign',
            'pk_cid',
            'pk_content',
            'pk_keyword',
            'pk_medium',
            'pk_source',

            // Springbot
            'redirect_log_mongo_id',
            'redirect_mongo_id',
            'sb_referer_host',

            'ref',

            'SSAID',
            'sscid', // ShareASale

            'usqp',

            'utm_campaign',
            'utm_content',
            'utm_expid',
            'utm_expid',
            'utm_medium',
            'utm_referrer',
            'utm_source',
            'utm_term',
            'utm_source_platform',
            'utm_creative_format',
            'utm_marketing_tactic',

            's_kwcid',

            'nocache', // common one

            // "User Switching" plugin
            'user_switched',
            'switched_back',

            // Nextend Social Login and Register
            'nsl_bypass_cache',

            // Asset CleanUp's ones (e.g. after the form from the CSS/JS manager is submitted, within the front-end view, at the bottom of the page)

            'wpassetcleanup_load',
            'wpassetcleanup_time_r',
            '_',

            'wpacu_print',
            'wpacu_time',
            'wpacu_updated',
            'wpacu_updated',
            'wpacu_ignore_no_load_option',
            'wpacu_debug'
        );

        $ignoreQueryStrings = apply_filters('wpacu_skip_query_strings_for_homepage_detection', $skipQueryStringsForHomepageDetection);

        // [START] URI has public query string
        $parseTargetUriClean      = parse_url($requestUriAsItIs);
        $parseTargetUriCleanQuery = isset($parseTargetUriClean['query']) ? $parseTargetUriClean['query'] : '';

        if ($parseTargetUriCleanQuery && wpacuUriHasAnyPublicWpQuery($parseTargetUriCleanQuery, $publicQueryVars)) {
            // If any of the public queries are within the query string, then it's not a homepage
            define('WPACU_IS_HOME_PAGE_URL_EARLY_CHECK', false);
            return false;
        }
        // [END] URI has public query string

        $parseTargetUriCleanPath  = isset($parseTargetUriClean['path'])  ? $parseTargetUriClean['path']  : '/'; // default

        $parseSiteUrlClean        = parse_url(get_home_url());
        $parseSiteUrlCleanPath    = isset($parseSiteUrlClean['path']) ? $parseSiteUrlClean['path'] : '/'; // default

        $hasNoQueryOrTheQueryIsCommon = $parseTargetUriCleanQuery === '' || wpacuUriHasOnlyCommonQueryStrings($parseTargetUriCleanQuery, $ignoreQueryStrings);

        for ($i = 1; $i <= 2; $i++) {
            if ($i === 1) {
                $parsePossibleTargetUriCleanPath = $parseTargetUriCleanPath;
            } else {
                // This one has queries to the database, and to save resources
                // it is only called IF: 1) it was not deactivated through a hook/constant; 2) the previous one (when $i was equal to 1) failed to match
                if (wpacuIsDefinedConstant('WPACU_NO_HOMEPAGE_CHECK_FOR_PLUGIN_RULES_WPML')) {
                    // More information about this one here: https://assetcleanup.com/docs/?p=1774
                    break;
                }

                $parsePossibleTargetUriCleanPath = wpmlRemoveLangTagFromUri($parseTargetUriCleanPath);
            }
            // Condition 1: The request URI is / and the site URL is https://www.mydomain.com/
            // OR Condition 2: The request URI is /my-blog and the site URL is https://www.mydomain.com/my-blog | if there's a query string such as "utm_source" it will be ignored and the condition will match
            // e.g. if the requested URL is https://www.mydomain.com/my-blog?utm_source=... and the site's URL is https://www.mydomain.com/my-blog it will be considered to be the homepage

            // If either match, it's the homepage
            if ( ( $requestUriAsItIs === '/' && $parseSiteUrlCleanPath === '/' )
                 || ( rtrim($parsePossibleTargetUriCleanPath, '/') === rtrim($parseSiteUrlCleanPath, '/') && $hasNoQueryOrTheQueryIsCommon ) ) {
                // Obviously, the home page, no further checks necessary
                define( 'WPACU_IS_HOME_PAGE_URL_EARLY_CHECK', true );
                return true;
            }
        }

        define('WPACU_IS_HOME_PAGE_URL_EARLY_CHECK', false);
        return false;
    }
}

if ( ! function_exists('assetCleanUpRequestUriHasAnyPublicVar') ) {
	/**
	 * @param $targetUri
	 *
	 * @return bool
	 */
	function assetCleanUpRequestUriHasAnyPublicVar($targetUri)
	{
		$urlQuery = parse_url($targetUri, PHP_URL_QUERY);

		if ( ! $urlQuery ) {
			return false;
		}

		$publicQueryVars = array(
			'attachment',
			'attachment_id',
			'author',
			'author_name',
			'cat',
			'calendar',
			'category_name',
			'comments_popup',
			'cpage',
			'day',
			'error',
			'exact',
			'feed',
			'hour',
			'm',
			'minute',
			'monthnum',
			'more',
			'name',
			'order',
			'orderby',
			'p',
			'page_id',
			'page',
			'paged',
			'pagename',
			'pb',
			'post_type',
			'posts',
			'robots',
			's',
			'search',
			'second',
			'sentence',
			'static',
			'subpost',
			'subpost_id',
			'taxonomy',
			'tag',
			'tag_id',
			'tb',
			'term',
			'w',
			'withcomments',
			'withoutcomments',
			'year'
		);

		foreach ($publicQueryVars as $queryVar) {
			if (strpos('?'.$urlQuery, '&'.$queryVar.'=') !== false || strpos('?'.$urlQuery, '?'.$queryVar.'=') !== false) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists('assetCleanUpHasNoLoadMatches') ) {
	/**
	 * Any matches from "Settings" -> "Plugin Usage Preferences" -> "Do not load the plugin on certain pages"?
	 *
	 * @param string $targetUri
	 * @param bool $forceCheck
	 *
	 * @return bool
	 */
	function assetCleanUpHasNoLoadMatches($targetUri = '', $forceCheck = false)
	{
		if ( ! $forceCheck && isset( $_REQUEST['wpacu_ignore_no_load_option'] ) ) {
			return false;
		}

		if ($targetUri === '') {
			// When called from the Dashboard, it should never be empty
			if (is_admin()) {
				return false;
			}

			$targetUri = isset($_SERVER['REQUEST_URI']) ? rawurldecode($_SERVER['REQUEST_URI']) : ''; // Invalid request
		} else {
			// Passed from the Dashboard as a URL; Strip the prefix and hostname to keep only the URI
			$parseUrl  = parse_url(rawurldecode($targetUri));
			$targetUri = isset($parseUrl['path']) ? $parseUrl['path'] : '';
		}

		if ($targetUri === '') {
			return false; // Invalid request
		}

		// Already detected? Avoid duplicate queries
		if (isset($GLOBALS['wpacu_no_load_matches'][$targetUri])) {
			return $GLOBALS['wpacu_no_load_matches'][$targetUri];
		}

		$doNotLoadRegExps = array();

		$wpacuPluginSettingsJson = get_option( WPACU_PLUGIN_ID . '_settings' );
		$wpacuPluginSettings     = @json_decode( $wpacuPluginSettingsJson, ARRAY_A );
		$doNotLoadPatterns       = isset( $wpacuPluginSettings['do_not_load_plugin_patterns'] ) ? $wpacuPluginSettings['do_not_load_plugin_patterns'] : '';

		if ( $doNotLoadPatterns !== '' ) {
			$doNotLoadPatterns = trim( $doNotLoadPatterns );

			if ( strpos( $doNotLoadPatterns, "\n" ) ) {
				// Multiple values (one per line)
				foreach ( explode( "\n", $doNotLoadPatterns ) as $doNotLoadPattern ) {
					$doNotLoadPattern = trim( $doNotLoadPattern );
					if ( $doNotLoadPattern ) {
						$doNotLoadRegExps[] = '#' . $doNotLoadPattern . '#';
					}
				}
			} elseif ( $doNotLoadPatterns ) {
				// Only one value?
				$doNotLoadRegExps[] = '#' . $doNotLoadPatterns . '#';
			}
		}

		if ( ! empty( $doNotLoadRegExps ) ) {
			foreach ( $doNotLoadRegExps as $doNotLoadRegExp ) {
				if ( @preg_match( $doNotLoadRegExp, $targetUri ) || (strpos($targetUri, $doNotLoadRegExp) !== false) ) {
					// There's a match
					$GLOBALS['wpacu_no_load_matches'][$targetUri] = 'is_set_in_settings';
					return $GLOBALS['wpacu_no_load_matches'][$targetUri];
				}
			}
		}

		/*
		 * Page Options -> The following option might be checked "Do not load Asset CleanUp Pro on this page (this will disable any functionality of the plugin)"
		 * For homepage (e.g. the latest posts) or a page, post or custom post type
		 */
		$parseUrl       = parse_url(get_site_url());
		$rootUrl        = $parseUrl['scheme'].'://'.$parseUrl['host'];
		$homepageUri    = isset($parseUrl['path']) ? $parseUrl['path'] : '/';
		$cleanTargetUri = $targetUri;

		if (strpos($targetUri, '?') !== false) {
			list($cleanTargetUri) = explode('?', $cleanTargetUri);
		}

		/*
		 * First verification: If it's a homepage, but not a "page" homepage but a different one such as the latest posts
		 */
		$isHomePageUri = trim($homepageUri, '/') === trim($cleanTargetUri, '/') && ! assetCleanUpRequestUriHasAnyPublicVar($targetUri);
		$isSinglePageSetAsHomePage = ( get_option('show_on_front') === 'page' && get_option('page_on_front') > 0 );

		if ( $isHomePageUri && ! $isSinglePageSetAsHomePage ) {
			// Anything different from a page set as the homepage
            $globalPageOptionsList = wpacuGetGlobalData();

            if (isset($globalPageOptionsList['page_options']['homepage']['no_wpacu_load'])
                && $globalPageOptionsList['page_options']['homepage']['no_wpacu_load'] == 1) {
                $GLOBALS['wpacu_no_load_matches'][$targetUri] = 'is_set_in_page';
                return $GLOBALS['wpacu_no_load_matches'][$targetUri];
            }
		}

		/*
		 * Second verification: For any post, page, custom post type including any page set as the homepage in "Reading" -> "Your homepage displays" -> "A static page (select below)"
		 */
		if ($isHomePageUri && $isSinglePageSetAsHomePage) {
			$pageId = get_option('page_on_front');
			$pageOptionsJson = get_post_meta($pageId, '_' . WPACU_PLUGIN_ID . '_page_options', true);
			$pageOptions = @json_decode( $pageOptionsJson, ARRAY_A );

			if (isset($pageOptions['no_wpacu_load']) && $pageOptions['no_wpacu_load'] == 1) {
				$GLOBALS['wpacu_no_load_matches'][$targetUri] = 'is_set_in_page';
				return $GLOBALS['wpacu_no_load_matches'][$targetUri];
			}
		} else {
			$excludePostIds = array();

			if ($isSinglePageSetAsHomePage) {
				$excludePostIds[] = get_option('page_on_front');
			}

			// Visiting a post, page or custom post type but not the homepage
			global $wpdb;

			$anyPagesWithSpecialOptionsQuery = 'SELECT meta_value FROM `' . $wpdb->prefix . 'postmeta` WHERE ';

			if (! empty($excludePostIds)) {
				$anyPagesWithSpecialOptionsQuery .= ' post_id NOT IN ('.implode(',', $excludePostIds).') && ';
			}

			$anyPagesWithSpecialOptionsQuery .= ' meta_key=\'_wpassetcleanup_page_options\' && meta_value LIKE \'%no_wpacu_load%\'';

			$anyPagesWithSpecialOptions = $wpdb->get_col( $anyPagesWithSpecialOptionsQuery );

			if ( ! empty( $anyPagesWithSpecialOptions ) ) {
				foreach ( $anyPagesWithSpecialOptions as $metaValue ) {
					$postPageOptions = @json_decode($metaValue, ARRAY_A);

					if ( ! isset($postPageOptions['no_wpacu_load'], $postPageOptions['_page_uri']) ) {
						continue;
					}

					$dbPageUrl = $postPageOptions['_page_uri'];
					$dbPageUri = str_replace( $rootUrl, '', $dbPageUrl );

					// This is a post/page/custom post type check
					// If the homepage page root URL is detected, then it's a former static page set as homepage and its URI is outdated
					if (trim($dbPageUri, '/') === trim($homepageUri, '/')) {
						continue;
					}

					if ( ( $dbPageUri === $targetUri ) || ( strpos( $targetUri, $dbPageUri ) === 0 ) ) {
						$GLOBALS['wpacu_no_load_matches'][$targetUri] = 'is_set_in_page';
						return $GLOBALS['wpacu_no_load_matches'][$targetUri];
					}
				}
			}
		}

		$GLOBALS['wpacu_no_load_matches'][$targetUri] = false;

		return false;
	}
}

if ( ! function_exists('assetCleanUpNoLoad') ) {
	/**
	 * There are special cases when triggering "Asset CleanUp" is not relevant
	 * Thus, for maximum compatibility and backend processing speed, it's better to avoid running any of its code
	 *
	 * @return bool
	 */
	function assetCleanUpNoLoad()
	{
		if ( defined( 'WPACU_NO_LOAD_SET' ) ) {
			return true; // save resources in case the function is called several times
		}

		// Hide top WordPress admin bar on request for debugging purposes and a cleared view of the tested page
		if ( isset($_REQUEST['wpacu_no_admin_bar']) ) {
			add_filter( 'show_admin_bar', '__return_false', PHP_INT_MAX );
		}

		// On request: for debugging purposes - e.g. https://yourwebsite.com/?wpacu_no_load
		// Also make sure it's in the REQUEST URI and $_GET wasn't altered incorrectly before it's checked
		// Technically, it will be like the plugin is not activated: no global settings and unload rules will be applied
		if ( isset($_GET['wpacu_no_load'], $_SERVER['REQUEST_URI']) && strpos( $_SERVER['REQUEST_URI'], 'wpacu_no_load' ) !== false ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// Case 1: Needs to be called ideally from a MU plugin which always loads before Asset CleanUp
		// or from a different plugin that triggers before Asset CleanUp which is less reliable
		// Case 2: It could be called from /pro/early-triggers-pro.php (for Pro users)
		if ( apply_filters( 'wpacu_plugin_no_load', false ) ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

        // [START] Elementor Edit Mode
		// "Elementor" plugin Admin Area: Edit Mode
        // e.g. /wp-admin/post.php?post=[...]&action=elementor
		if ( isset( $_GET['post'], $_GET['action'] ) && $_GET['post'] && $_GET['action'] === 'elementor' && is_admin() ) {
            $loadPluginOnElementorBuilder = wpacuIsDefinedConstant('WPACU_LOAD_ON_ELEMENTOR_BUILDER');
            if ( ! $loadPluginOnElementorBuilder ) {
                define('WPACU_NO_LOAD_SET', true);

                return true;
            }
		}

		// "Elementor" plugin (Preview Mode within Page Builder)
        // This is the iFrame from the edit mode which is loaded in the front-end view
        // e.g. /about-us/?elementor-preview=[...]&ver=[...]
		if ( isset( $_GET['elementor-preview'], $_GET['ver'] ) && (int) $_GET['elementor-preview'] > 0 && $_GET['ver'] ) {
            $loadPluginOnElementorBuilder = wpacuIsDefinedConstant('WPACU_LOAD_ON_ELEMENTOR_BUILDER');
            if ( ! $loadPluginOnElementorBuilder ) {
                define('WPACU_NO_LOAD_SET', true);

                return true;
            }
		}
        // [END] Elementor Edit Mode

		// WPML Multilingual CMS plugin loading its JavaScript content (no need to trigger Asset CleanUp in this case)
		if ( isset($_GET['wpml-app']) && $_GET['wpml-app'] ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		$wpacuIsAjaxRequest = ( ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest' );

		// If an AJAX call is made to /wp-admin/admin-ajax.php and the action doesn't start with WPACU_PLUGIN_ID.'_
		// then do not trigger Asset CleanUp Pro as it's irrelevant
		$wpacuActionStartsWith = WPACU_PLUGIN_ID . '_';

		if ( $wpacuIsAjaxRequest && // Is AJAX request
		     isset( $_POST['action'] ) && // Has 'action' set as a POST parameter
		     strpos( $_POST['action'], $wpacuActionStartsWith ) !== 0 && // Doesn't start with $wpacuActionStartsWith
		     ( strpos( $_SERVER['REQUEST_URI'], 'admin-ajax.php' ) !== false ) && // The request URI contains 'admin-ajax.php'
		     is_admin() ) { // If /wp-admin/admin-ajax.php is called, then it will return true
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// On some hosts .css and .js files are loaded dynamically (e.g. through the WordPress environment)
		if (isset($_SERVER['REQUEST_URI']) && preg_match('#.(css|js)\?ver=#', $_SERVER['REQUEST_URI'])) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// Image Edit via Media Library
		if ( $wpacuIsAjaxRequest && isset( $_POST['action'], $_POST['postid'] ) && $_POST['action'] === 'image-editor' ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// "Elementor" plugin: Do not trigger the plugin on AJAX calls
		if ( $wpacuIsAjaxRequest && isset( $_POST['action'] ) && (strncmp($_POST['action'], 'elementor_', 10) === 0 ) ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// If some users want to have Asset CleanUp loaded on Oxygen Builder's page builder to avoid loading certain plugins (for a faster page editor)
		// they can do that by adding the following constant in wp-config.php
		// define('WPACU_LOAD_ON_OXYGEN_BUILDER_EDIT', true);
		$loadPluginOnOxygenEdit = wpacuIsDefinedConstant('WPACU_LOAD_ON_OXYGEN_BUILDER_EDIT');

		if ( ! $loadPluginOnOxygenEdit ) {
			// "Oxygen" plugin: Edit Mode
			$oxygenBuilderPluginDir = dirname( __DIR__ ) . '/oxygen';
			if ( isset( $_GET['ct_builder'] ) && $_GET['ct_builder'] === 'true' && is_dir( $oxygenBuilderPluginDir ) ) {
				define( 'WPACU_NO_LOAD_SET', true );

				return true;
			}

			// "Oxygen" plugin: Block Edit Mode
			if ( isset( $_GET['oxy_user_library'], $_GET['ct_builder'] ) && $_GET['oxy_user_library'] && $_GET['ct_builder'] ) {
				define( 'WPACU_NO_LOAD_SET', true );

				return true;
			}

			// "Oxygen" plugin (v2.4.1+): Edit Mode (Reusable Template)
			if ( isset( $_GET['ct_builder'], $_GET['ct_template'] ) && $_GET['ct_builder'] && $_GET['ct_template'] ) {
				define( 'WPACU_NO_LOAD_SET', true );

				return true;
			}
		} else {
			// Since the user added the constant WPACU_LOAD_ON_OXYGEN_BUILDER_EDIT, we'll check if the Oxygen Editor is ON
			// And if it is set the constant WPACU_ALLOW_ONLY_UNLOAD_RULES to true which will allow only unload rules, but do not trigger any other ones such as preload/defer, etc.
			$isOxygenBuilderLoaded = false;

			// "Oxygen" plugin: Edit Mode
			$oxygenBuilderPluginDir = dirname( __DIR__ ) . '/oxygen';
			if ( isset( $_GET['ct_builder'] ) && $_GET['ct_builder'] === 'true' && is_dir( $oxygenBuilderPluginDir ) ) {
				$isOxygenBuilderLoaded = true;
			}

			// "Oxygen" plugin: Block Edit Mode
			if ( isset( $_GET['oxy_user_library'], $_GET['ct_builder'] ) && $_GET['oxy_user_library'] && $_GET['ct_builder'] ) {
				$isOxygenBuilderLoaded = true;
			}

			// "Oxygen" plugin (v2.4.1+): Edit Mode (Reusable Template)
			if ( isset( $_GET['ct_builder'], $_GET['ct_template'] ) && $_GET['ct_builder'] && $_GET['ct_template'] ) {
				$isOxygenBuilderLoaded = true;
			}

			if ( $isOxygenBuilderLoaded && ! defined('WPACU_ALLOW_ONLY_UNLOAD_RULES') ) {
				define( 'WPACU_ALLOW_ONLY_UNLOAD_RULES', true );
			}
		}

		// If some users want to have Asset CleanUp loaded on Divi Builder to avoid loading certain plugins (for a faster page editor)
		// they can do that by adding the following constant in wp-config.php
		// define('WPACU_LOAD_ON_DIVI_BUILDER_EDIT', true);
		$loadPluginOnDiviBuilderEdit = wpacuIsDefinedConstant('WPACU_LOAD_ON_DIVI_BUILDER_EDIT');
		$isDiviBuilderLoaded = ( isset( $_GET['et_fb'] ) && $_GET['et_fb'] ) // e.g. /?et_fb=1&PageSpeed=off&et_tb=1
           || ( is_admin() && isset($_GET['page']) && $_GET['page'] === 'et_theme_builder' ) // e.g. /wp-admin/admin.php?page=et_theme_builder
           || ( isset($_GET['et_pb_preview'], $_GET['et_pb_preview_nonce']) && $_GET['et_pb_preview'] === 'true' && $_GET['et_pb_preview_nonce'] ) // /?et_pb_preview=true&et_pb_preview_nonce=[...]
           || ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], 'et_fb=1' ) !== false );

		if ( ! $loadPluginOnDiviBuilderEdit ) {
			// "Divi" theme builder: Front-end View Edit Mode
			if ( $isDiviBuilderLoaded ) {
				define( 'WPACU_NO_LOAD_SET', true );

				return true;
			}
		} elseif ($isDiviBuilderLoaded) {
			// Since the user added the constant WPACU_LOAD_ON_DIVI_BUILDER_EDIT, we'll check if the Divi Builder is ON
			// And if it is set the constant WPACU_ALLOW_ONLY_UNLOAD_RULES to true which will allow only unload rules, but do not trigger any other ones such as preload/defer, etc.
            wpacuDefineConstant( 'WPACU_ALLOW_ONLY_UNLOAD_RULES' );
		}

		// "Divi" theme builder: Do not trigger the plugin on AJAX calls
		if ( $wpacuIsAjaxRequest && isset( $_POST['action'] ) && (strncmp($_POST['action'], 'et_fb_', 6) === 0 ) ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// KALLYAS theme: Zion Page Builder
		// Dashboard/front-end edit
		if ( (isset($_GET['zn_pb_edit']) && in_array($_GET['zn_pb_edit'], array(1, 'true'))) ||
		     (isset($_GET['page']) && $_GET['page'] === 'zion_builder_active') ||
		     (isset($_GET['zion_template']) && $_GET['zion_template']) ||
		     (isset($_GET['zionbuilder-preview'], $_GET['zionbuilder_nonce']) && $_GET['zionbuilder-preview'] && $_GET['zionbuilder_nonce'])
		) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// Beaver Builder
		if ( isset( $_GET['fl_builder'] ) ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// Thrive Architect (Dashboard)
		if ( isset( $_GET['action'], $_GET['tve'] ) && $_GET['action'] === 'architect' && $_GET['tve'] === 'true' && is_admin() ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// Thrive Architect (iFrame)
		$tveFrameFlag = defined( 'TVE_FRAME_FLAG' ) ? TVE_FRAME_FLAG : 'tcbf';

		if ( isset( $_GET['tve'], $_GET[ $tveFrameFlag ] ) && $_GET['tve'] === 'true' ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// Page Builder by SiteOrigin
		if ( isset( $_GET['action'], $_GET['so_live_editor'] ) && $_GET['action'] === 'edit' && $_GET['so_live_editor'] && is_admin() ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// Brizy - Page Builder
		if ( (isset($_GET['brizy-edit']) || isset($_GET['brizy-edit-iframe']) || isset($_GET['is-editor-iframe']))
		     || (isset($_GET['action']) && $_GET['action'] === 'in-front-editor') ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// Fusion Builder Live: Avada
		if ( ( isset( $_GET['fb-edit'] ) && $_GET['fb-edit'] ) || isset( $_GET['builder'], $_GET['builder_id'] ) ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// WPBakery Page Builder
		if ( isset( $_GET['vc_editable'], $_GET['_vcnonce'] ) || ( is_admin() && isset( $_GET['vc_action'] ) ) ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// Themify Builder (iFrame)
		if ( isset( $_GET['tb-preview'] ) && $_GET['tb-preview'] ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// "Pro" (theme.co) (iFrame)
		if ( isset( $_POST['_cs_nonce'], $_POST['cs_preview_state'] ) && $_POST['_cs_nonce'] && $_POST['cs_preview_state'] ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// "Page Builder: Live Composer" plugin
		if ( wpacuIsDefinedConstant( 'DS_LIVE_COMPOSER_ACTIVE' ) ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// "WP Page Builder" plugin (By Themeum.com)
		if ( isset( $_GET['load_for'] ) && $_GET['load_for'] === 'wppb_editor_iframe' ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// "Product Designer for WooCommerce WordPress | Lumise" plugin
		if ( isset( $_GET['product_base'], $_GET['product_cms'] )
		     && in_array( 'lumise/lumise.php', apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ) ) ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// Perfmatters: Script Manager
		if ( isset( $_GET['perfmatters'] ) ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// Gravity Forms (called for uploading files)
		if ( ( ( isset($_GET['gf_page']) && $_GET['gf_page']) || isset($_GET['gf-download'], $_GET['form-id'] ) ) && is_file( WP_PLUGIN_DIR . '/gravityforms/gravityforms.php' ) ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// Custom CSS Pro: Editor
		if ( ( isset( $_GET['page'] ) && $_GET['page'] === 'ccp-editor' )
		     || ( isset( $_GET['ccp-iframe'] ) && $_GET['ccp-iframe'] === 'true' ) ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// Bricks – Visual Site Builder for WordPress
		$loadPluginOnBricksBuilder = wpacuIsDefinedConstant('WPACU_LOAD_ON_BRICKS_BUILDER');
		$isBricksBuilderLoaded     = isset( $_GET['bricks'] ) && $_GET['bricks'] === 'run';

		if ($loadPluginOnBricksBuilder) {
			// Since the user added the constant WPACU_LOAD_ON_BRICKS_BUILDER, we'll check if the Bricks Visual Site Builder is ON
			// And if it is set the constant WPACU_ALLOW_ONLY_UNLOAD_RULES to true which will allow only unload rules, but do not trigger any other ones such as preload/defer, etc.
			if ( $isBricksBuilderLoaded && ! defined('WPACU_ALLOW_ONLY_UNLOAD_RULES') ) {
				define( 'WPACU_ALLOW_ONLY_UNLOAD_RULES', true );
			}
		} elseif ($isBricksBuilderLoaded) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// TranslatePress Multilingual: Edit translation mode
		if ( isset( $_GET['trp-edit-translation'] ) && $_GET['trp-edit-translation'] === 'preview' ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// WordPress Customise Mode
		if ( ( isset( $_GET['customize_changeset_uuid'], $_GET['customize_theme'] ) && $_GET['customize_changeset_uuid'] && $_GET['customize_theme'] )
		     || ( strpos( $_SERVER['REQUEST_URI'], '/wp-admin/customize.php' ) !== false && isset( $_GET['url'] ) && $_GET['url'] ) ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// [wpacu_lite]
		// There's no point in loading the plugin on a REST API call
		// This is valid for the Lite version as the Pro version could work differently  / read more: https://www.assetcleanup.com/docs/?p=1469

        // Make exception and leave the oEmbed in case the feature is disabled
        // In "Settings" -- "Site-Wide Common Unloads" -- "Disable oEmbed (Embeds) Site-Wide"
        // Some functions has to be processed
        $restUrlPrefix = function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json';
        $isOembedRequest = strpos($_SERVER['REQUEST_URI'], '/' . $restUrlPrefix . '/oembed/') !== false;

		if ( ! $isOembedRequest && assetCleanUpIsRestCall() ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}
		// [/wpacu_lite]

		// WordPress AJAX Heartbeat
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'heartbeat' ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

        // EDD Plugin (Listener)
        if ( isset( $_GET['edd-listener'] ) && $_GET['edd-listener'] && ! wpacuIsDefinedConstant('WPACU_LOAD_ON_EDD_LISTENER') ) {
            define( 'WPACU_NO_LOAD_SET', true );

            return true;
        }

		// Knowledge Base for Documents and FAQs
		if ( isset($_GET['action']) && $_GET['action'] === 'epkb_load_editor' ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		if ( isset($_GET['epkb-editor-page-loaded']) && $_GET['epkb-editor-page-loaded'] ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// Give WP iFrame
		// Example URI: /give/donation-form?giveDonationFormInIframe=1
		if (isset($_GET['giveDonationFormInIframe'])) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

        // Code Profiler (View File)
        if (isset($_GET['page'], $_GET['ffile']) && $_GET['page'] === 'code-profiler-pro' && $_GET['ffile']) {
            define( 'WPACU_NO_LOAD_SET', true );

            return true;
        }

		// AJAX Requests from various plugins/themes
		if ( isset( $wpacuIsAjaxRequest, $_POST['action'] ) && $wpacuIsAjaxRequest
		     && (strncmp($_POST['action'], 'woocommerce', 11) === 0
                 || strncmp($_POST['action'], 'wc_', 3) === 0
                 || strncmp($_POST['action'], 'jetpack', 7) === 0
                 || strncmp($_POST['action'], 'wpfc_', 5) === 0
                 || strncmp($_POST['action'], 'oxygen_', 7) === 0
                 || strncmp($_POST['action'], 'oxy_', 4) === 0
                 || strncmp($_POST['action'], 'w3tc_', 5) === 0
                 || strncmp($_POST['action'], 'wpforms_', 8) === 0
                 || strncmp($_POST['action'], 'wdi_', 4) === 0
                 || strncmp($_POST['action'], 'brizy_update', 12) === 0
                 || strncmp($_POST['action'], 'brizy-update', 12) === 0
                 || in_array( $_POST['action'], array(
					  'brizy_heartbeat',
			          'contactformx',
					  'eckb_apply_editor_changes' // Knowledge Base for Documents and FAQs (save changes mode)
				  ))
		     ) ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// e.g. WooCommerce's AJAX call to /?wc-ajax=checkout | no need to trigger Asset CleanUp then, not only avoiding any errors, but also saving resources
		// "wc-ajax" could be one of the following: update_order_review, apply_coupon, checkout, etc.
		if ( isset( $_REQUEST['wc-ajax'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		// WooCommerce API call
		if ( (isset($_GET['wc-api']) && $_GET['wc-api']) || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/index.php?wc-api=') !== false) ) {
			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

        // The URI could be a sitemap generated one by a plugin such as Rank Math, and it's not needed to load Asset CleanUp here
        // e.g. /post-sitemap.xml | /page-sitemap.xml | /sitemap_index.xml
        if ( isset($_SERVER['REQUEST_URI']) &&
            ( strpos($_SERVER['REQUEST_URI'], '.xml') !== false ||
              strpos($_SERVER['REQUEST_URI'], '.xsl') !== false ||
              strpos($_SERVER['REQUEST_URI'], '.kml') !== false ) ) {
            $afterLastForwardSlash = strrchr($_SERVER['REQUEST_URI'], '.');

            if ( strpos($afterLastForwardSlash, 'sitemap') !== false ||
                 substr($afterLastForwardSlash, -4) === '.xml' ||
                 substr($afterLastForwardSlash, -4) === '.xsl' ||
                 substr($afterLastForwardSlash, -4) === '.kml' ) {
                return true;
            }
        }

        // e.g. /amp/ - /amp? - /amp/? - /?amp or ending in /amp
        $isAmpInRequestUri = ( (isset($_SERVER['REQUEST_URI']) && (preg_match('/(\/amp$|\/amp\?)|(\/amp\/|\/amp\/\?)/', $_SERVER['REQUEST_URI'])))
                               || isset($_GET['amp']) );

        if ($isAmpInRequestUri || isset($_GET['wpacu_clean_load'])) {
            add_filter( 'wpacu_prevent_any_frontend_optimization', '__return_true' );
        }

		// Stop triggering Asset CleanUp (completely) on specific front-end pages
		// Do the trigger here and if necessary, exit as early as possible to save resources via "registered_taxonomy" action hook
		$wpacuNoLoadMatchesStatus = assetCleanUpHasNoLoadMatches();

		if ( $wpacuNoLoadMatchesStatus ) {
			// Only use exit() when "wpassetcleanup_load" is used
			if ( isset( $_REQUEST['wpassetcleanup_load'] ) && $_REQUEST['wpassetcleanup_load'] ) {
				add_action( 'registered_taxonomy', function() use ($wpacuNoLoadMatchesStatus) {
					if ( current_user_can( 'administrator' ) || current_user_can( 'assetcleanup_manager' ) ) {
						if ( $wpacuNoLoadMatchesStatus === 'is_set_in_settings' ) {
							$msg = sprintf(
								__( 'This page\'s URL is matched by one of the RegEx rules you have in <em>"Settings"</em> -&gt; <em>"Plugin Usage Preferences"</em> -&gt; <em>"Do not load the plugin on certain pages"</em>, thus %s is not loaded on that page and no CSS/JS are to be managed. If you wish to view the CSS/JS manager, please remove the matching RegEx rule and the list of CSS/JS will be fetched.',
									'wp-asset-clean-up'
								),
								WPACU_PLUGIN_TITLE
							);
						} elseif ( $wpacuNoLoadMatchesStatus === 'is_set_in_page' ) {
							$msg = sprintf(
								__( 'This homepage\'s URI is matched by the rule you have in the "Page Options", thus %s is not loaded on that page and no CSS/JS are to be managed. If you wish to view the CSS/JS manager, please uncheck the option and reload this page.',
									'wp-asset-clean-up'
								), WPACU_PLUGIN_TITLE );
						}

						exit( $msg );
					}
				} );
			}

			define( 'WPACU_NO_LOAD_SET', true );

			return true;
		}

		return false;
	}
}

if ( ! function_exists('wpacuIsPluginActive') ) {
    /**
     * @param $plugin
     *
     * @return bool
     */
    function wpacuIsPluginActive($plugin)
    {
        // Site level check
        if (in_array($plugin, (array)get_option('active_plugins', array()), true)) {
            return true;
        }

        // Multisite check
        if ( ! is_multisite()) {
            return false;
        }

        $plugins = get_site_option('active_sitewide_plugins');

        if (isset($plugins[$plugin])) {
            return true;
        }

        return false;
    }
}
