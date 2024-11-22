<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp;

use WpAssetCleanUp\Admin\AssetsManagerAdmin;
use WpAssetCleanUp\Admin\SettingsAdminOnlyForAdmin;

/**
 * Class OwnAssets
 *
 * These are plugin's own assets (CSS, JS etc.),
 * and they are used only when you're logged in and do not show in the list for unloading
 *
 * @package WpAssetCleanUp
 */
class OwnAssets
{
	/**
	 * @var array[]
	 */
	public static $ownAssets = array('styles' => array(), 'scripts' => array());

	/**
	 *
	 */
	public function __construct()
    {
        self::prepareVars();
    }

	/**
	 *
	 */
	public static function prepareVars()
    {
        self::$ownAssets['styles'] = array(
            'style_core' => array(
	            'handle'   => WPACU_PLUGIN_ID . '-style',
	            'rel_path' => '/assets/style.min.css'
            ),

            'chosen' => array(
                'handle'   => WPACU_PLUGIN_ID . '-chosen-style',
                'rel_path' => '/assets/chosen/chosen.min.css'
            ),

            'tooltipster' => array(
                'handle'   => WPACU_PLUGIN_ID . '-tooltipster-style',
                'rel_path' => '/assets/tooltipster/tooltipster.bundle.min.css'
            ),

            'sweetalert2' => array(
                'handle'   => WPACU_PLUGIN_ID . '-sweetalert2-style',
                'rel_path' => '/assets/sweetalert2/dist/sweetalert2.min.css'
            ),

            'autocomplete_search_jquery_ui_custom' => array(
                'handle' => WPACU_PLUGIN_ID.'-autocomplete-jquery-ui-custom',
                'rel_path' => '/assets/auto-complete/smoothness/jquery-ui-custom.min.css'
            )
        );

        self::$ownAssets['scripts'] = array(
            'script_core' => array(
                'handle'   => WPACU_PLUGIN_ID . '-script',
                'rel_path' => '/assets/script.min.js'
            ),

            'chosen' => array(
                'handle'   => WPACU_PLUGIN_ID . '-chosen-script',
                'rel_path' => '/assets/chosen/chosen.jquery.min.js'
            ),

            'tooltipster' => array(
	            'handle'   => WPACU_PLUGIN_ID . '-tooltipster-script',
	            'rel_path' => '/assets/tooltipster/tooltipster.bundle.min.js'
            ),

            'sweetalert2' => array(
	            'handle'   => WPACU_PLUGIN_ID . '-sweetalert2-js',
	            'rel_path' => '/assets/sweetalert2/dist/sweetalert2.min.js'
            ),

            'autocomplete_search' => array(
                'handle'   => WPACU_PLUGIN_ID . '-autocomplete-search',
                'rel_path' => '/assets/auto-complete/assets-manager-search-pages.min.js'
            )
        );

        // If script debugging is enabled, load the non-minified versions of the plugin's assets
        // Read more: https://wordpress.org/support/article/debugging-in-wordpress/#script_debug
	    if ( (defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG) || isset($_GET['wpacu_debug']) ) {
		    self::$ownAssets['styles']['style_core']['rel_path']   = '/assets/style.css';
		    self::$ownAssets['scripts']['script_core']['rel_path'] = '/assets/script.js';

		    self::$ownAssets['styles']['chosen']['rel_path']       = '/assets/chosen/chosen.css';
		    self::$ownAssets['scripts']['chosen']['rel_path']      = '/assets/chosen/chosen.jquery.js';

		    self::$ownAssets['styles']['sweetalert2']['rel_path']  = '/assets/sweetalert2/dist/sweetalert2.css';
		    self::$ownAssets['scripts']['sweetalert2']['rel_path'] = '/assets/sweetalert2/dist/sweetalert2.js';

		    self::$ownAssets['styles']
             ['autocomplete_search_jquery_ui_custom']['rel_path']  = '/assets/auto-complete/smoothness/jquery-ui-custom.css';
		    self::$ownAssets['scripts']
             ['autocomplete_search']['rel_path']                   = '/assets/auto-complete/assets-manager-search-pages.js';
	    }
    }

    /**
     * @return void
     */
    public static function adminChosenScriptInline()
    {
        // Only in specific plugin's pages
        if ( ! (isset($_GET['page']) && $_GET['page'] && is_string($_GET['page']) && strpos($_GET['page'], 'wpassetcleanup_') !== false) ) {
            return;
        }

        // Only in "Settings" and "Plugins Manager" plugin pages
        if ( ! in_array($_GET['page'], array('wpassetcleanup_settings', 'wpassetcleanup_plugins_manager'))) {
            return;
        }

        $chosenScriptInline = <<<JS
jQuery(document).ready(function($) {
    $('.wpacu_chosen_select').each(function() {
        if ($(this).hasClass('wpacu_access_via_specific_users_dd_search')) {
            /*
            * [Access via specific users search DD]
            */
            $('.wpacu_chosen_select.wpacu_access_via_specific_users_dd_search').chosen({
                'width'                                       : '100%',
                'no_results_text'                             : '&nbsp;',
                'reset_search_field_on_update'                : false,
                'reset_multiple_search_field_on_focus_change' : false
            });
            /*
            * [/Access via specific users search DD]
            */
        } else if ($(this).hasClass('wpacu_access_via_specific_users_dd')) {
            /*
            * [Access via specific users DD]
            */
            $('.wpacu_chosen_select.wpacu_access_via_specific_users_dd').chosen({
                'width' : '100%',
            });
            /*
            * [/Access via specific users DD]
            */
        }
        /* [wpacu_pro] */
        else if ($(this).hasClass('wpacu_plugin_manage_via_post_type_dd')) {
            /*
            * [Post Types DD]
            */
            $('.wpacu_chosen_select.wpacu_plugin_manage_via_post_type_dd').chosen({'width':'100%'});
            /*
            * [/Post Types DD]
            */
        } else if ($(this).hasClass('wpacu_plugin_manage_via_archive_dd')) {
            /*
            * [Archive Types DD]
            */
            $('.wpacu_chosen_select.wpacu_plugin_manage_via_archive_dd').chosen({'width':'100%'});
            /*
            * [/Archive Types DD]
            */
        } else if ($(this).hasClass('wpacu_plugin_manage_logged_in_via_role_dd')) {
            /*
            * [User Roles DD]
            */
            $('.wpacu_chosen_select.wpacu_plugin_manage_logged_in_via_role_dd').chosen({'width':'100%'});
            /*
            * [/User Roles DD]
            */
        } else if ($(this).hasClass('wpacu-input-element')) {
            $('select.wpacu-input-element.wpacu_chosen_select').chosen();
        } else {
        /* [/wpacu_pro] */
            
            /*
            * Default (only having the class "wpacu_chosen_select")
             */
            $('select[class="wpacu_chosen_select"]').chosen();
            
        /* [wpacu_pro] */
        }
        /* [/wpacu_pro] */
    });
});
JS;
        wp_add_inline_script(OwnAssets::$ownAssets['scripts']['chosen']['handle'], $chosenScriptInline);
    }

    /**
	 * @return array[]
     * @noinspection NestedAssignmentsUsageInspection
     */
	public static function getOwnAssetsHandles($assetType = '')
    {
        if ( ! Menu::userCanAccessAssetCleanUp() ) {
            return array();
        }

        self::prepareVars();

	    $allPluginStyleHandles = $allPluginScriptHandles = array();

        foreach (self::$ownAssets['styles'] as $assetValues) {
            if (isset($assetValues['handle']) && $assetValues['handle']) {
	            $allPluginStyleHandles[] = $assetValues['handle'];
            }
        }

	    foreach (self::$ownAssets['scripts'] as $assetValues) {
		    if (isset($assetValues['handle']) && $assetValues['handle']) {
			    $allPluginScriptHandles[] = $assetValues['handle'];
		    }
	    }

	    if ($assetType !== '') {
            if ($assetType === 'styles') {
                return $allPluginStyleHandles;
            }

            return $allPluginScriptHandles;
	    }

        return array_merge($allPluginStyleHandles, $allPluginScriptHandles);
    }

	/**
	 *
	 */
	public function init()
    {
        add_action('admin_enqueue_scripts', array($this, 'stylesAndScriptsForAdmin'));
        add_action('wp_enqueue_scripts',    array($this, 'stylesAndScriptsForPublic'));

	    // Code only for the Dashboard
	    add_action('admin_head',   array($this, 'inlineAdminHeadCode'));
	    add_action('admin_footer', array($this, 'inlineAdminFooterCode'));

	    // Code for both the Dashboard and the Front-end view
	    add_action('admin_head',   array($this, 'inlineCodeHead'));
	    add_action('wp_head',      array($this, 'inlineCodeHead'));

        add_action('admin_footer',   array($this, 'inlineCodeFooter'), PHP_INT_MAX);
        add_action('wp_footer',      array($this, 'inlineCodeFooter'), PHP_INT_MAX);

	    // Rename ?ver= to ?wpacuversion to prevent other plugins from stripping "ver"
	    // This is valid in the front-end and the Dashboard
	    add_filter('script_loader_src', array($this, 'ownAssetLoaderSrc'), 10, 2);
	    add_filter('style_loader_src',  array($this, 'ownAssetLoaderSrc'), 10, 2);

        add_filter('style_loader_tag',  array($this, 'ownAssetLoaderTag'), 10, 2);
	    add_filter('script_loader_tag', array($this, 'ownAssetLoaderTag'), 10, 2);
    }

    /**
     * @return bool
     */
    public static function isPluginClearCacheLinkAccessible()
    {
        $isAdminWithClearCacheLink =   is_admin() && (Menu::isPluginPage() || (is_admin_bar_showing() && ! Main::instance()->settings['hide_from_admin_bar']));
        $isFrontWithClearCacheLink = ! is_admin() && is_admin_bar_showing() && ! Main::instance()->settings['hide_from_admin_bar'];

        return $isAdminWithClearCacheLink || $isFrontWithClearCacheLink;
    }

	/**
	 * @return void
	 */
	public function inlineCodeHead()
    {
	    if (wp_style_is(self::$ownAssets['styles']['style_core']['handle'])) {
		    echo Misc::preloadAsyncCssFallbackOutput();
	    }
    }

    /**
     * @return void
     */
    public function inlineCodeFooter()
    {
        if (self::isPluginClearCacheLinkAccessible()) {
            global $wp_styles, $wp_scripts;

            if ( ! in_array(self::$ownAssets['styles']['style_core']['handle'], $wp_styles->done) ||
                 ! in_array(self::$ownAssets['scripts']['script_core']['handle'], $wp_scripts->done) ) {
                return;
            }
            ?>
            <div id="wpacu-main-loading-spinner" class="wpacu_hide">
                <div id="wpacu-main-loading-spinner-content">
                    <div>
                        <img src="<?php echo WPACU_PLUGIN_URL; ?>/assets/icons/loader-horizontal.svg" alt="" />
                        <!-- Depending on the situation, the text will be shown from one of the DIVs below -->
                        <div id="wpacu-main-loading-spinner-text"></div>

                        <div data-wpacu-clear-cache-text="1" class="wpacu_hide">
                            <?php esc_attr_e('Clearing CSS/JS assets\' cache'); ?>... <?php esc_attr_e('Please wait until this notice disappears'); ?>...
                        </div>
                        <div data-wpacu-updating-text="1" class="wpacu_hide">
                            <?php _e('Updating'); ?>... <?php _e('Please wait'); ?>...
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    }

	/**
	 *
	 */
	public function inlineAdminHeadCode()
	{
		?>
        <style <?php echo Misc::getStyleTypeAttribute(); ?> data-wpacu-own-inline-style="true">
            <?php
            // For the main languages, leave the style it was always set as it worked well
            $applyDefaultStyleForCurrentLang = (strpos(get_locale(), 'en_') !== false)
                || (strpos(get_locale(), 'es_') !== false)
                || (strpos(get_locale(), 'fr_') !== false)
                || (strpos(get_locale(), 'de_') !== false);

            if ( (! $applyDefaultStyleForCurrentLang) || wpacuIsPluginActive('WPShapere/wpshapere.php') ) {
                // This would also work well if the language is Arabic (the text shown right to left)
            ?>
                /* Compatibility with "Wordpress Admin Theme - WPShapere" plugin - make sure Asset CleanUp's icon is not misaligned */
                .menu-top.toplevel_page_wpassetcleanup_getting_started .wp-menu-image > img { width: 26px; height: auto; }
            <?php
            } else {
            ?>
                .menu-top.toplevel_page_wpassetcleanup_getting_started .wp-menu-image > img { width: 26px; height: auto; position: absolute; left: 8px; top: -4px; }
            <?php
            }

            if (Main::instance()->settings['hide_from_side_bar']) {
                // Just hide the menu without removing any of its pages from the menu (for sidebar cleanup purposes)
                ?>
                #toplevel_page_wpassetcleanup_getting_started { display: none !important; }
                <?php
            } elseif (Menu::isPluginPage()) {
                // The menu is shown: make the sidebar area a bit larger so the whole "Asset CleanUp Pro" menu text is seen properly when viewing its pages
                ?>
                #adminmenuback, #adminmenuwrap, #adminmenu, #adminmenu .wp-submenu { width: 172px; }
                #wpcontent, #wpfooter { margin-left: 172px; }
                <?php
            }
            ?>
        </style>
        <?php
    }

	/**
	 *
	 */
	public function inlineAdminFooterCode()
	{
		if (isset($_GET['page']) && $_GET['page'] === WPACU_PLUGIN_ID.'_settings') {
			// Only relevant in the "Settings" area
			?>
            <script type="text/javascript">
                // Tab Area | Keep selected tab after page reload
                if (location.href.indexOf('#') !== -1) {
                    var hashFromUrl = location.href.substr(location.href.indexOf('#'));
                    //wpacuTabOpenSettingsArea(event, hashFromUrl.substring(1));
                    //console.log(hashFromUrl);
                    jQuery('a[href="'+ hashFromUrl +'"]').trigger('click');
                    //console.log(hashFromUrl.substring(1));
                }
            </script>
			<?php
		}
	}

    /**
     *
     */
    public function stylesAndScriptsForAdmin()
    {
		if (! Menu::userCanAccessAssetCleanUp()) {
			return;
		}

        $this->_enqueueAdminStyles();
		$this->_enqueueAdminScripts();
	}

	/**
	 *
	 */
	public function stylesAndScriptsForPublic()
    {
		// Do not print it when an AJAX call is made from the Dashboard
		if (WPACU_GET_LOADED_ASSETS_ACTION === true) {
			return;
		}

		// Only for the administrator with the right permission
		if (! Menu::userCanAccessAssetCleanUp()) {
			return;
		}

	    // Do not load any CSS & JS belonging to Asset CleanUp if in "Elementor" preview
	    if (isset($_GET['elementor-preview']) && $_GET['elementor-preview'] && Main::instance()->isFrontendEditView) {
	        return;
	    }

	    if ( isset($_GET['wpacu_clean_load']) ) {
	        return;
        }

        $this->enqueuePublicStyles();
        $this->enqueuePublicScripts();
    }

	/**
	 *
	 */
	private function _enqueueAdminStyles()
    {
        wp_enqueue_style(
            self::$ownAssets['styles']['style_core']['handle'],
            plugins_url(self::$ownAssets['styles']['style_core']['rel_path'], WPACU_PLUGIN_FILE),
            array(),
            self::assetVer(self::$ownAssets['styles']['style_core']['rel_path'])
        );
    }

	/**
	 *
     * @noinspection NestedAssignmentsUsageInspection
     */
	private function _enqueueAdminScripts()
    {
        $page = Misc::getVar('get', 'page');
        $postIdRequested = isset( $_GET['wpacu_post_id'] ) && $_GET['wpacu_post_id'] ? (int)$_GET['wpacu_post_id'] : 0;
        $pageRequestFor = Misc::getVar('get', 'wpacu_for');

        if ( ! $pageRequestFor ) {
            // e.g. /wp-admin/admin.php?page=wpassetcleanup_assets_manager&wpacu_post_id=17193 (no "wpacu_for" was mentioned)
            if ($postIdRequested) {
                $pageRequestFor = AssetsManagerAdmin::detectPostTypeTypeFromRequestedPostId($postIdRequested);
            } else {
                $pageRequestFor = 'homepage';
            }
        }

	    $currentPostId = AssetsManager::getCurrentPostIdForCssJsManager($page, $pageRequestFor);

        wp_register_script(
	        self::$ownAssets['scripts']['script_core']['handle'],
            plugins_url(self::$ownAssets['scripts']['script_core']['rel_path'], WPACU_PLUGIN_FILE),
            array('jquery', 'jquery-ui-autocomplete'),
            self::assetVer(self::$ownAssets['scripts']['script_core']['rel_path'])
        );

        $dataForObject = array(
            'post_id'          => $currentPostId,
            'page'             => $page,
            'page_request_for' => $pageRequestFor
        );
        $wpacuObjectData = self::generateObjectData($dataForObject);

        wp_localize_script(
	        self::$ownAssets['scripts']['script_core']['handle'],
			'wpacu_object',
			apply_filters('wpacu_object_data', $wpacuObjectData)
		);

		wp_enqueue_script(self::$ownAssets['scripts']['script_core']['handle']);

		// Load jQuery Chosen on "Settings", "CSS & JS Manager" -> "Manage CSS/JS" (homepage & any post type page)
	    $isDashManageAssetsPage = false;

        if ($page === WPACU_PLUGIN_ID . '_assets_manager') {
	        $manageCssJsSubPage      = ( isset( $_GET['wpacu_sub_page'] ) && $_GET['wpacu_sub_page'] ) ? $_GET['wpacu_sub_page'] : 'manage_css_js';
	        $isDashManageAssetsPage = ( $manageCssJsSubPage === 'manage_css_js' ) &&
                  // if 'wpacu_for' is not used, it will be defaulted to either homepage or single post page
                  // if it's used, it has to be in the list specified below, other jQuery Chosen would be irrelevant
                  ( ! isset( $_GET['wpacu_for'] )
                    || ( isset( $_GET['wpacu_for'] ) && in_array( $_GET['wpacu_for'], array(
                              'homepage',
                              'pages',
                              'posts',
                              'custom_post_types',
                              'media_attachment'
                          ) ) ) );
        }

		// Standard edit post page
	    global $pagenow;

	    $isEditPostArea = ($pagenow === 'post.php' && Misc::getVar('get', 'post') && Misc::getVar('get', 'action') === 'edit');

		if ( $page === WPACU_PLUGIN_ID . '_settings' || $isDashManageAssetsPage || $isEditPostArea ) {
		    $this->loadjQueryChosen();
        }

        if ($isEditPostArea || in_array($page, array(WPACU_PLUGIN_ID . '_assets_manager', WPACU_PLUGIN_ID . '_plugins_manager'))) {
			// [Start] SweetAlert
			wp_enqueue_style(
				self::$ownAssets['styles']['sweetalert2']['handle'],
				plugins_url(self::$ownAssets['styles']['sweetalert2']['rel_path'], WPACU_PLUGIN_FILE),
				array(),
				1
			);

			add_action('admin_head', static function() {
			?>
				<style <?php echo Misc::getStyleTypeAttribute(); ?> data-wpacu-own-inline-style="true">
                body[class*='asset-cleanup'] .swal2-container {
                    z-index: 1000000;
                }

                /* [wpacu_lite] */
                body.wp-admin.post-php .swal2-popup.swal2-modal,
                body.wp-admin.asset-cleanup_page_wpassetcleanup_assets_manager .swal2-popup.swal2-modal {
                    padding: 1.25em 1.25em 2em 1.25em;
                }

                body.wp-admin.post-php #swal2-content,
                body.asset-cleanup_page_wpassetcleanup_assets_manager #swal2-content {
                    line-height: 30px;
                }
                /* [/wpacu_lite] */

				.wpacu-swal2-overlay {
					z-index: 10000000;
				}

                .wpacu-swal2-container {
                    z-index: 100000000;
                }

                .wpacu-swal2-html-container {
                    line-height: 30px;
                }

                .wpacu-swal2-title {
                    margin: 0 0 20px;
                    font-size: 1.2em;
                }

				.wpacu-swal2-text {
					line-height: 24px;
				}

				.wpacu-swal2-footer {
					text-align: center;
					padding: 13px 16px 20px;
				}

				.wpacu-swal2-button.wpacu-swal2-button--confirm {
					background-color: #008f9c;
				}

				.wpacu-swal2-button.wpacu-swal2-button--confirm:hover {
					background-color: #006e78;
				}
				</style>
			<?php
			});

			// Changed "Swal" to "wpacuSwal" to avoid conflicts with other plugins using SweetAlert
			wp_enqueue_script(
				self::$ownAssets['scripts']['sweetalert2']['handle'],
				plugins_url(self::$ownAssets['scripts']['sweetalert2']['rel_path'], WPACU_PLUGIN_FILE),
				array('jquery'),
				1.1
			);

			// [wpacu_lite]
			$upgradeToProLinkHardcodedAssets = apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL.'?utm_source=manage_hardcoded_assets&utm_medium=go_pro_modal');
			$upgradeToProLinkMediaQueryLoad = apply_filters('wpacu_go_pro_affiliate_link', WPACU_PLUGIN_GO_PRO_URL.'?utm_source=media_query_load&utm_medium=go_pro_modal');

			$sweetAlertTwoScriptInline = <<<JS
jQuery(document).ready(function($) { 
    /* [Hardcoded Assets] */
    $(document).on('click', '.wpacu-manage-hardcoded-assets-requires-pro-popup', function(e) {
       e.preventDefault();
       wpacuSwal.fire({
            text: "Managing hardcoded (non-enqueued) LINK/STYLE/SCRIPT tags is a feature available for Pro users.",
            icon: "info",
            showCancelButton: true,
            confirmButtonText: 'Upgrade to the Pro version',
            cancelButtonText: 'Maybe later',
            width: '600px'
        }).then((result) => {
            if (result.isConfirmed) {
              window.location.replace("{$upgradeToProLinkHardcodedAssets}");
            }
        });
    });
    /* [/Hardcoded Assets] */
    
    /* [Media Query Load] */
    $(document).on('click', '.wpacu-media-query-load-requires-pro-popup', function(e) {
       e.preventDefault();
       wpacuSwal.fire({
            text: "Instructing the browser to load a file based on the screen size of the visitor's device (e.g. desktop or mobile view) is a feature available for Pro users.",
            icon: "info",
            showCancelButton: true,
            confirmButtonText: 'Upgrade to the Pro version',
            cancelButtonText: 'Maybe later',
            width: '600px'
        }).then((result) => {
            if (result.isConfirmed) {
              window.location.replace("{$upgradeToProLinkMediaQueryLoad}");
            }
        });
    });
    /* [/Media Query Load] */
});
JS;
			wp_add_inline_script(WPACU_PLUGIN_ID . '-sweetalert2-js', $sweetAlertTwoScriptInline);
			// [wpacu_lite]
			// [End] SweetAlert
        }

		if (in_array($page, array(WPACU_PLUGIN_ID . '_plugins_manager', WPACU_PLUGIN_ID . '_overview', WPACU_PLUGIN_ID . '_bulk_unloads'))) {
			// [Start] Tooltipster Style
			wp_enqueue_style(
				self::$ownAssets['styles']['tooltipster']['handle'],
				plugins_url(self::$ownAssets['styles']['tooltipster']['rel_path'], WPACU_PLUGIN_FILE),
				array(),
				1
			);
			// [End] Tooltipster Style

			// [Start] Tooltipster Script
			wp_enqueue_script(
				self::$ownAssets['scripts']['tooltipster']['handle'],
				plugins_url(self::$ownAssets['scripts']['tooltipster']['rel_path'], WPACU_PLUGIN_FILE),
				array('jquery'),
				1
			);

			$tooltipsterScriptInline = <<<JS
jQuery(document).ready(function($) { $('.wpacu-tooltip').tooltipster({ contentCloning: true, delay: 0 }); });
JS;
			wp_add_inline_script(self::$ownAssets['scripts']['tooltipster']['handle'], $tooltipsterScriptInline);
			// [End] Tooltipster Script
        }

        /*
         * [START] Critical CSS
         */
        if (isset($_GET['page'], $_GET['wpacu_sub_page']) &&
            $_GET['page'] === WPACU_PLUGIN_ID . '_assets_manager' &&
            $_GET['wpacu_sub_page'] === 'manage_critical_css') {
            wp_enqueue_script( 'wp-theme-plugin-editor' );
            wp_enqueue_style( 'wp-codemirror' );

            $cm_settings = array();
            $cm_settings['codeEditor'] = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
            $customPagesInlineJS = ''; // only fills if the "Custom Pages" tab is used

            if (isset($_GET['wpacu_for']) && $_GET['wpacu_for'] === 'custom-pages') {
                $cm_settings_custom_pages = array();
                $cm_settings_custom_pages['codeEditor'] = wp_enqueue_code_editor( array( 'type' => 'text/x-php' ) );
                wp_localize_script( 'jquery', 'wpacu_cm_settings_custom_pages', $cm_settings_custom_pages );

                $customPagesInlineJS = <<<JS
// Custom Pages
wp.codeEditor.initialize($('#wpacu-php-editor-textarea'), wpacu_cm_settings_custom_pages);
JS;
            }

            wp_localize_script( 'jquery', 'wpacu_cm_settings', $cm_settings );

            $wpacuCodeMirrorInlineScript = <<<JS
jQuery(document).ready(function($) {
  // Editable CSS
  var wpacuEditor = wp.codeEditor.initialize($('#wpacu-css-editor-textarea'), wpacu_cm_settings);
  
  {$customPagesInlineJS}
  
  $(document).on('change', '#wpacu_critical_css_status', function() {
      var \$wpacuTargetElement     = $('#wpacu-critical-css-options-area'),
          \$wpacuTargetTabMenuItem = $('#wpacu-critical-css-manager-tab-menu').find('.wpacu-nav-tab-active > .wpacu-circle-status'),
          wpacuAnyCustomPageType   = $(this).attr('data-wpacu-custom-page-type');
      
      if ($(this).prop('checked')) {
          \$wpacuTargetElement.removeClass('wpacu-faded');
          
          if (wpacuAnyCustomPageType !== '') {
              $('.wpacu-circle-status[data-wpacu-custom-page-type="'+ wpacuAnyCustomPageType +'"]')
                .removeClass('wpacu-off').addClass('wpacu-on');
          }
          
          \$wpacuTargetTabMenuItem.removeClass('wpacu-off').addClass('wpacu-on');
      } else {
          \$wpacuTargetElement.addClass('wpacu-faded');
          
          if (wpacuAnyCustomPageType !== '') {
              $('.wpacu-circle-status[data-wpacu-custom-page-type="'+ wpacuAnyCustomPageType +'"]')
                .removeClass('wpacu-on').addClass('wpacu-off');
          }
          
          /* In case there are any other custom post types with critical CSS enabled, then keep the green circle for the "Custom Posty Types" main tab */
          if ($('#wpacu_custom_pages_nav_links').find('.wpacu-circle-status.wpacu-on').length === 0) {
              \$wpacuTargetTabMenuItem.removeClass('wpacu-on').addClass('wpacu-off');
          }
      }
  });
  
  $(document).on('submit', '#wpacu-critical-css-form', function() {
      if (wpacuEditor.codemirror.getValue() === '' && $('#wpacu_critical_css_status').prop('checked')) {
          alert('You have chosen to activate the critical CSS. You need to provide the CSS content before submitting this form.');
          return false;
      }
      
      $('#wpacu-updating-critical-css').addClass('wpacu-show').removeClass('wpacu-hide');
      $('#wpacu-update-critical-css-button-area').find('.button').prop('disabled', true).attr('value', 'UPDATING...');
  });
})
JS;
            $wpacuCodeMirrorInlineStyle = <<<CSS
.CodeMirror {
  border: 1px solid #ddd;
}

/* "CSS & JS Manager" -- "Manage Critical CSS" -- "Custom Pages" */
#wpacu-critical-css-custom-pages .CodeMirror {
    height: auto;
}

#wpacu-critical-css-options-area.wpacu-faded {
    opacity: 0.4;
}

#wpacu-css-editor-textarea {
    width: 100%;
    min-height: 600px;
}

#wpacu-update-critical-css-button-area {
    display: inline-block;
    margin: 14px 0 0 0;
}

#wpacu-update-critical-css-button-area input {
    padding: 5px 18px;
    height: 45px;
    font-size: 15px;
}

#wpacu-updating-critical-css.wpacu-hide {
    display: none;
}

#wpacu-updating-critical-css.wpacu-show {
    display: inline-block;
    margin: 13px 0 0 8px;
}
CSS;
            wp_add_inline_script('wp-theme-plugin-editor', $wpacuCodeMirrorInlineScript);
            wp_add_inline_style('wp-codemirror', $wpacuCodeMirrorInlineStyle);
        }
        /*
         * [END] Critical CSS
         */
    }

    /**
     * @param array $data
     *
     * @return array
     * @noinspection NestedAssignmentsUsageInspection
     */
    public static function generateObjectData($data = array())
    {
        if ( isset($data['post_id']) ) {
            $postId = $data['post_id'];
        } else {
            // If 'post_id' is not set, then it's a front-end view
            $postId = Main::instance()->getCurrentPostId();
        }

        if (is_admin()) {
            // Dashboard View
            if (self::checkForFetchUrlInDashboardView()) {
                // e.g. edit category page (where the CSS/JS manager is loading)
                $pageUrl = self::getFetchUrlDashboardView($postId);

                if ($pageUrl && Misc::isHttpsSecure()) {
                    $pageUrl = str_replace('http://', 'https://', $pageUrl);
                }
            } else {
                // The cache is cleared most likely outside the CSS/JS manager
                // e.g. after a theme switch
                $pageUrl = site_url(); // get main website URL
            }
        } else {
            // Front-end view
            // Get the post ID if not is set (it will be '0' if not a singular page)
            $pageUrl = Misc::getCurrentPageUrl();
        }

        // If the post status is 'private' only direct method can be used to fetch the assets
        // as the remote post one will return a 404 error since the page is accessed as a guest visitor
        $postStatus      = $postId > 0 ? get_post_status($postId) : false;
        $wpacuDomGetType = ($postStatus === 'private') ? 'direct' : Main::$domGetType;

        $svgReloadIcon = <<<HTML
<svg aria-hidden="true" role="img" focusable="false" class="dashicon dashicons-cloud" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path d="M14.9 9c1.8.2 3.1 1.7 3.1 3.5 0 1.9-1.6 3.5-3.5 3.5h-10C2.6 16 1 14.4 1 12.5 1 10.7 2.3 9.3 4.1 9 4 8.9 4 8.7 4 8.5 4 7.1 5.1 6 6.5 6c.3 0 .7.1.9.2C8.1 4.9 9.4 4 11 4c2.2 0 4 1.8 4 4 0 .4-.1.7-.1 1z"></path></svg>
HTML;

        $wpacuObjectData = array(
            'plugin_prefix'    => WPACU_PLUGIN_ID, // the same for both Lite & Pro
            'plugin_slug'      => WPACU_PLUGIN_SLUG,
            'plugin_title'     => WPACU_PLUGIN_TITLE,

            'reload_icon'      => $svgReloadIcon,
            'reload_msg'       => sprintf(__('Reloading %s area', 'wp-asset-clean-up'), '<strong style="margin: 0 4px;">' . WPACU_PLUGIN_TITLE . '</strong>'),
            'dom_get_type'     => $wpacuDomGetType,
            'list_show_status' => Main::instance()->settings['assets_list_show_status'],

            'start_del_e'      => Main::START_DEL_ENQUEUED,
            'end_del_e'        => Main::END_DEL_ENQUEUED,

            'start_del_h'      => Main::START_DEL_HARDCODED,
            'end_del_h'        => Main::END_DEL_HARDCODED,

            'post_id'          => $postId, // if any
            'ajax_url'         => esc_url( admin_url( 'admin-ajax.php' ) ),
            'page_url'         => $pageUrl // post, page, custom post type, homepage etc.
        );

        if ( ! is_admin() ) {
            $wpacuObjectData['is_frontend_view'] = true;
        } else {
            // Assets' List Show Status only applies for edit post/page/custom post type/category/custom taxonomy
            // Dashboard pages such as "Homepage" from plugin's "CSS/JavaScript Load Manager" will fetch the list on loading
            if (isset($data['page']) && $data['page'] === WPACU_PLUGIN_ID.'_assets_manager' && in_array($data['page_request_for'], array('homepage', 'pages', 'posts', 'custom_post_types', 'media_attachment'))) {
                $wpacuObjectData['override_assets_list_load'] = true;
            }

            if ( isset($_GET['wpacu_manage_dash']) ) {
                $wpacuObjectData['force_manage_dash'] = true;
            }

            if (get_transient(WPACU_PLUGIN_ID.'_clear_assets_cache')) {
                $wpacuObjectData['clear_cache'] = true;
                delete_transient(WPACU_PLUGIN_ID.'_clear_assets_cache');
            }
        }

        $wpacuObjectData['source_load_error_msg'] = __('The source might not be reachable', 'wp-asset-clean-up');

        $wpacuObjectData['current_post_type'] = false;

        if ( $postId > 0 ) {
            $wpacuObjectData['current_post_type']                    = get_post_type($postId);
            $wpacuObjectData['wpacu_ajax_get_post_type_terms_nonce'] = wp_create_nonce('wpacu_ajax_get_post_type_terms_nonce');
        }

        // After homepage/post/page is saved and the page is reloaded, clear the cache
        // Cache clearing default values
        $wpacuObjectData['clear_cache_via_ajax'] = $wpacuObjectData['clear_other_caches'] = false; // default

        /*
         * [Start] Trigger plugin cache and other plugins'/system caches
         */
        if (self::clearCacheViaAjax()) {
            // Instruct the script to trigger clearing the cache via AJAX
            $wpacuObjectData['clear_cache_via_ajax'] = true;
        }
        /*
         * [End] Trigger plugin cache and other plugins'/system caches
         */

        /*
         * [Start] Trigger ONLY other plugins'/system caches
         */
        // When click the "Clear CSS/JS Files Cache" link within the Dashboard (e.g. toolbar or quick action areas)
        // Cache was already cleared; Do not clear it again (save resources); Clear other caches
        // Make sure the referrer (it needs to have one) is the same URI as the currently loaded one (without any extra parameters)
        $wpacuClearOtherCaches = false;
        $wpacuReferrer         = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

        if ($wpacuReferrer) {
            list(,$wpacuUriFromReferrer ) = explode('//' . parse_url($wpacuReferrer, PHP_URL_HOST), $wpacuReferrer);
            $wpacuRequestUri              = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $wpacuClearOtherCaches        = ($wpacuUriFromReferrer === $wpacuRequestUri);
        }

        if ($wpacuClearOtherCaches && get_transient(WPACU_PLUGIN_ID . '_clear_assets_cache_via_link')) {
            delete_transient(WPACU_PLUGIN_ID . '_clear_assets_cache_via_link');
            $wpacuObjectData['clear_other_caches'] = true;
        }
        /*
         * [End] Trigger ONLY other plugins'/system caches
         */

        $wpacuObjectData['server_returned_404_not_found'] = sprintf(
            __('When accessing this page the server responded with a status of %s404 (Not Found)%s. If this page is meant to return this status, you can ignore this message, otherwise you might have a problem with this page if it is meant to return a standard 200 OK status.', 'wp-asset-clean-up'),
            '<strong>',
            '</strong>'
        );

        /*
         * Whether to clear Autoptimize Cache or not (if the plugin is enabled)
         */
        if ( ! wpacuIsPluginActive('autoptimize/autoptimize.php') ) {
            $wpacuObjectData['autoptimize_not_active'] = 1;
        } else {
            $wpacuObjectData['clear_autoptimize_cache'] = assetCleanUpClearAutoptimizeCache() ? 'true' : 'false';
        }

        /*
         * Whether to clear "Cache Enabler" Cache or not (if the plugin is enabled)
         */
        if ( ! wpacuIsPluginActive('cache-enabler/cache-enabler.php') ) {
            $wpacuObjectData['cache_enabler_not_active'] = 1;
        } else {
            $wpacuObjectData['clear_cache_enabler_cache'] = assetCleanUpClearCacheEnablerCache() ? 'true' : 'false';

            if ( assetCleanUpClearCacheEnablerCache() ) {
                $wpacuObjectData['wpacu_ajax_clear_cache_enabler_cache_nonce'] = wp_create_nonce( 'wpacu_ajax_clear_cache_enabler_cache_nonce' );
            }
        }

        $wpacuObjectData = self::wpacuObjectSecurityNonces($wpacuObjectData);
        $wpacuObjectData = self::wpacuObjectDataFetchErrors($wpacuObjectData);
        $wpacuObjectData = self::wpacuObjectDataConfirmsAlertsMsg($wpacuObjectData);

        return $wpacuObjectData;
    }

    /**
     * @return bool
     */
    public static function clearCacheViaAjax()
    {
        // After editing post/page within the Dashboard
        $dashUnloadAssetsSubmit = (isset($_POST['wpacu_unload_assets_area_loaded']) && $_POST['wpacu_unload_assets_area_loaded']);
        if ($dashUnloadAssetsSubmit) {
            return true;
        }

        // After updating the CSS/JS manager within the front-end view (when "Manage in the front-end" is enabled)
        $frontendViewPageAssetsJustUpdated = ! is_admin() &&
                                             (isset($_GET['wpacu_time']) && $_GET['wpacu_time']) &&
                                             get_transient(WPACU_PLUGIN_ID . '_frontend_assets_manager_just_updated');
        if ($frontendViewPageAssetsJustUpdated) {
            return true;
        }

        // After updating the "Settings" within the Dashboard
        $wpacuSettingsWithinDashboardJustUpdated = is_admin() &&
                                                   Misc::getVar('request', 'page') === WPACU_PLUGIN_ID . '_settings' &&
                                                   Misc::getVar('get', 'wpacu_selected_tab_area') &&
                                                   get_transient(WPACU_PLUGIN_ID . '_settings_updated');
        if ($wpacuSettingsWithinDashboardJustUpdated) {
            return true;
        }

        return false;
    }

	/**
	 *
	 */
	public function loadjQueryChosen()
    {
        // [Start] Chosen Style
		wp_register_style(
			self::$ownAssets['styles']['chosen']['handle'],
			plugins_url(self::$ownAssets['styles']['chosen']['rel_path'], WPACU_PLUGIN_FILE),
			array(),
			'1.8.7'
		);

	    wp_enqueue_style(self::$ownAssets['styles']['chosen']['handle']);

		$chosenStyleInline = <<<CSS
#wpacu_hide_meta_boxes_for_post_types_chosen { margin-top: 5px; min-width: 320px; }
CSS;
		wp_add_inline_style(self::$ownAssets['styles']['chosen']['handle'], $chosenStyleInline);
		// [End] Chosen Style

		// [Start] Chosen Script
		wp_register_script(
			self::$ownAssets['scripts']['chosen']['handle'],
			plugins_url(self::$ownAssets['scripts']['chosen']['rel_path'], WPACU_PLUGIN_FILE),
			array('jquery'),
			'1.8.7'
		);

		wp_enqueue_script(self::$ownAssets['scripts']['chosen']['handle']);

        if (is_admin()) {
            self::adminChosenScriptInline();
        }
		// [End] Chosen Script
	}

    /**
     *
     */
    private function enqueuePublicStyles()
    {
        wp_enqueue_style(
            self::$ownAssets['styles']['style_core']['handle'],
            plugins_url(self::$ownAssets['styles']['style_core']['rel_path'], WPACU_PLUGIN_FILE),
            array(),
            self::assetVer(self::$ownAssets['styles']['style_core']['rel_path'])
        );
    }

    /**
     *
     */
    public function enqueuePublicScripts()
    {
        wp_register_script(
            self::$ownAssets['scripts']['script_core']['handle'],
            plugins_url(self::$ownAssets['scripts']['script_core']['rel_path'], WPACU_PLUGIN_FILE),
            array('jquery'),
            self::assetVer(self::$ownAssets['scripts']['script_core']['rel_path']),
            true
        );

	    wp_localize_script(
		    self::$ownAssets['scripts']['script_core']['handle'],
		    'wpacu_object',
		    apply_filters('wpacu_object_data', self::generateObjectData())
	    );

	    wp_enqueue_script(self::$ownAssets['scripts']['script_core']['handle']);
    }

	/**
	 * @param $relativePath
	 *
	 * @return false|string
	 */
	public static function assetVer($relativePath)
    {
		return @filemtime(dirname(WPACU_PLUGIN_FILE) . $relativePath) ?: date('dmYHi');
	}

	/**
	 * Prevent "?ver=" or "&ver=" from being stripped when loading plugin's own assets
	 * It will force them to refresh whenever there's a change in either of the files
	 *
	 * @param $src
	 * @param $handle
	 *
	 * @return mixed
	 */
	public function ownAssetLoaderSrc($src, $handle)
	{
	    if (in_array($handle, self::getOwnAssetsHandles())) {
			$src = str_replace(
				array('?ver=',          '&ver='),
				array('?wpacuversion=', '&wpacuversion='),
                $src
            );
		}

		return $src;
	}

	/**
	 * @param $tag
	 * @param $handle
	 *
	 * @return mixed
	 */
	public function ownAssetLoaderTag($tag, $handle)
    {
        // "data-wpacu-skip": Prevent anyh asset alteration by any option set in "Settings"
        if (in_array($handle, self::getOwnAssetsHandles('styles'))) {
            $tag = str_replace(' href=', ' data-wpacu-skip href=', $tag);
        }

		// "data-wpacu-plugin-script": Useful in case jQuery library is deferred too (rare situations)
		if (in_array($handle, self::getOwnAssetsHandles('scripts'))) {
			$tag = str_replace(' src=', ' data-wpacu-skip data-wpacu-plugin-script src=', $tag);

            if ($handle === WPACU_PLUGIN_ID . '-chosen-script') {
                $tag = str_replace(' src=', ' defer="defer" src=', $tag);
            }
		}

		return $tag;
	}

    /**
     * What's the URL to be checked for the assets if CSS/JS manager is loaded within the Dashboard?
     *
     * @param $postId - could be empty if it's not for a post page (e.g. a "category" page)
     *
     * @return string
     */
    public static function getFetchUrlDashboardView($postId)
    {
        // A post/page/custom post type, or it can also be the front page URL ("Settings" -- "Reading" -- "Your homepage displays" -- "A static page")
        if ( $postId > 0 )  {
            return Misc::getPageUrl($postId);
        }

        // Homepage, last possible option for the Dashboard view
        return Misc::getPageUrl(0);
    }

    /**
     * @return bool
     */
    public static function checkForFetchUrlInDashboardView()
    {
        global $pagenow;

        // Edit taxonomy
        if ($pagenow === 'term.php' && Misc::getVar('get', 'taxonomy') && Misc::getVar('get', 'tag_ID')) {
            return true;
        }

        // Edit post/page/custom post type
        // 1) Edit it in its edit page
        if ($pagenow === 'post.php' && isset($_GET['post'], $_GET['action']) && $_GET['post'] && $_GET['action'] === 'edit') {
            return true;
        }

        // 2) Edit it the plugin's CSS/JS manager area ("CSS & JS MANAGER" -- "MANAGE CSS/JS")
        $wpacuFor = Misc::getVar('get', 'wpacu_for') ?: 'homepage';

        if (Misc::getVar('get', 'page') === WPACU_PLUGIN_ID . '_assets_manager' && in_array($wpacuFor, array('homepage', 'posts', 'pages', 'custom_post_types'))) {
            return true;
        }

        return false;
    }

    /**
     * @param $wpacuObjectData
     *
     * @return mixed
     */
    public static function wpacuObjectDataFetchErrors($wpacuObjectData)
    {
        // [wpacu_lite]
        $submitTicketLink = 'https://wordpress.org/support/plugin/wp-asset-clean-up';
        // [/wpacu_lite]

        $wpacuObjectData['ajax_direct_fetch_error'] = <<<HTML
<div class="ajax-direct-call-error-area">
    <p class="note"><strong>Note:</strong> The checked URL returned an error when fetching the assets via AJAX call. This could be because of a firewall that is blocking the AJAX call, a redirect loop or an error in the script that is retrieving the output which could be due to an incompatibility between the plugin and the WordPress setup you are using.</p>
    <p>Here is the response from the call:</p>

    <table>
        <tr>
            <td width="135"><strong>Status Code Error:</strong></td>
            <td><span class="error-code">{wpacu_status_code_error}</span> * for more information about client and server errors, <a target="_blank" href="https://en.wikipedia.org/wiki/List_of_HTTP_status_codes">check this link</a></td>
        </tr>
        <tr>
            <td valign="top"><span class="dashicons dashicons-lightbulb" style="color: orange;"></span> <strong>Suggestion:</strong></td>
            <td>Select "WP Remote POST" as a method of retrieving the assets from the "Settings" page. If that doesn't fix the issue, just use "Manage in Front-end" option which should always work and <a target="_blank" href="{$submitTicketLink}">submit a ticket</a> about your problem.</td>
        </tr>
        <tr>
            <td valign="top"><strong>Output:</strong></td>
            <td valign="top">{wpacu_output}</td>
        </tr>
    </table>
</div>
HTML;

        // Sometimes, 200 OK (success) is returned, but due to an issue with the page, the assets list is not retrieved
        $wpacuObjectData['ajax_direct_fetch_error_with_success_response'] = <<<HTML
<div style="overflow-y: scroll; max-height: 290px;" class="ajax-direct-call-error-area">
    <p class="note"><strong>Note:</strong> The assets could not be fetched via the AJAX call. Here is the response:</p>
    <table>
        <tr>
            <td valign="top"><strong>Suggestion:</strong></td>
            <td>Select "WP Remote POST" as a method of retrieving the assets from the "Settings" page. If that doesn't fix the issue, just use "Manage in Front-end" option which should always work and <a target="_blank" href="{$submitTicketLink}">submit a ticket</a> about your problem.</td>
        </tr>
        <tr>
            <td valign="top"><strong>Output:</strong></td>
            <td valign="top">{wpacu_output}</td>
        </tr>
    </table>
</div>
HTML;
        return $wpacuObjectData;
    }

    /**
     * @param $wpacuObjectData
     *
     * @return array
     */
    public static function wpacuObjectDataConfirmsAlertsMsg($wpacuObjectData)
    {
        $wpacuObjectData['jquery_migration_disable_confirm_msg'] =
            __('Make sure to properly test your website if you unload the jQuery migration library.', 'wp-asset-clean-up')."\n\n".
            __('In some cases, due to old jQuery code triggered from plugins or the theme, unloading this migration library could cause those scripts not to function anymore and break some of the front-end functionality.', 'wp-asset-clean-up')."\n\n".
            __('If you are not sure about whether activating this option is right or not, it is better to leave it as it is (to be loaded by default) and consult with a developer.', 'wp-asset-clean-up')."\n\n".
            __('Confirm this action to enable the unloading or cancel to leave it loaded by default.', 'wp-asset-clean-up');

        $wpacuObjectData['comment_reply_disable_confirm_msg'] =
            __('This is worth disabling if you are NOT using the default WordPress comment system (e.g. you are using the website for business purposes, to showcase your products and you are not using it as a blog where people leave comments to your posts).', 'wp-asset-clean-up')."\n\n".
            __('If you are not sure about whether activating this option is right or not, it is better to leave it as it is (to be loaded by default).', 'wp-asset-clean-up')."\n\n".
            __('Confirm this action to enable the unloading or cancel to leave it loaded by default.', 'wp-asset-clean-up');

        // "Tools" - "Reset"
        $wpacuObjectData['reset_settings_confirm_msg'] =
            __('Are you sure you want to reset the settings to their default values?', 'wp-asset-clean-up')."\n\n".
            __('This is an irreversible action.', 'wp-asset-clean-up')."\n\n".
            __('Please confirm to continue or "Cancel" to abort it', 'wp-asset-clean-up');

        $wpacuObjectData['reset_critical_css_confirm_msg'] =
            __('Are you sure you want to remove all the critical CSS information?', 'wp-asset-clean-up-pro')."\n\n".
            __('This is an irreversible action.', 'wp-asset-clean-up')."\n\n".
            __('Please confirm to continue or "Cancel" to abort it', 'wp-asset-clean-up-pro');

        $wpacuObjectData['reset_everything_except_settings_confirm_msg'] =
            __('Are you sure you want to reset everything (unloads, load exceptions etc.) except settings?', 'wp-asset-clean-up')."\n\n".
            __('This is an irreversible action.', 'wp-asset-clean-up')."\n\n".
            __('Please confirm to continue or "Cancel" to abort it.', 'wp-asset-clean-up');

        $wpacuObjectData['reset_everything_confirm_msg'] =
            __('Are you sure you want to reset everything (settings, unloads, load exceptions etc.) to the same point it was when you first activated the plugin?', 'wp-asset-clean-up')."\n\n".
            __('This is an irreversible action.', 'wp-asset-clean-up')."\n\n".
            __('Please confirm to continue or "Cancel" to abort it.', 'wp-asset-clean-up');

        // "Tools" - "Import & Export"
        $wpacuObjectData['import_confirm_msg'] =
            __('This process is NOT reversible.', 'wp-asset-clean-up')."\n\n".
            __('Please make sure you have a backup (e.g. an exported JSON file) before proceeding.', 'wp-asset-clean-up')."\n\n".
            __('Please confirm to continue or "Cancel" to abort it.', 'wp-asset-clean-up');

        $wpacuObjectData['jquery_unload_alert'] = 'jQuery library is a WordPress library that it is used in WordPress plugins/themes most of the time.' . "\n\n" .
                                                  'There are currently other JavaScript "children" files connected to it, that will stop working, if this library is unloaded' . "\n\n" .
                                                  'If you are positive this page does not require jQuery (very rare cases), then you can continue by pressing "OK"' . "\n\n" .
                                                  'Otherwise, it is strongly recommended to keep this library loaded by pressing "Cancel" to avoid breaking the functionality of the website.';
        // js-cookie
        $wpacuObjectData['woo_js_cookie_unload_alert'] = 'Please be careful when unloading "js-cookie" as there are other JS files that depend on it which will also be unloaded, including "wc-cart-fragments" which is required for the functionality of the WooCommerce mini cart.' . "\n\n" .
                                                         'Click "OK" to continue or "Cancel" if you have any doubts about unloading this file';

        // wc-cart-fragments
        $wpacuObjectData['woo_wc_cart_fragments_unload_alert'] = 'Please be careful when unloading "wc-cart-fragments" as it\'s required for the functionality of the WooCommerce mini cart. Unless you are sure you do not need it on this page, it is advisable to leave it loaded.' . "\n\n" .
                                                                 'Click "OK" to continue or "Cancel" if you have any doubts about unloading this file.';

        // backbone, underscore, etc.
        $wpacuObjectData['sensitive_library_unload_alert'] = 'Please make sure to properly test this page after this particular JavaScript file is unloaded as it is usually loaded for a reason.' . "\n\n" .
                                                             'If you are not sure whether it is used or not, then consider using the "Cancel" button to avoid taking ay chances in breaking the website\'s functionality.' . "\n\n" .
                                                             'It is advised to check the browser\'s console via right-click and "Inspect" to check for any reported errors.';

        $wpacuObjectData['dashicons_unload_alert_ninja_forms_alert'] = 'It looks like you are using "Ninja Forms" plugin which is sometimes loading Dashicons for the forms\' styling.' . "\n\n" .
                                                                       'If you are sure your forms do not use Dashicons, please use the following option \'Ignore dependency rule and keep the "children" loaded\' to avoid the unloading of the "nf-display" handle.' . "\n\n" .
                                                                       'Click "OK" to continue or "Cancel" if you have any doubts about unloading the Dashicons. It is better to have Dashicons loaded, then take a chance and break the forms\' layout.';
        return $wpacuObjectData;
    }

    /**
     * @param $wpacuObjectData
     *
     * @return mixed
     */
    public static function wpacuObjectSecurityNonces($wpacuObjectData)
    {
        // Security nonces for AJAX calls
        $wpacuObjectData['wpacu_update_specific_settings_nonce']       = wp_create_nonce('wpacu_update_specific_settings_nonce');
        $wpacuObjectData['wpacu_update_asset_row_state_nonce']         = wp_create_nonce('wpacu_update_asset_row_state_nonce');
        $wpacuObjectData['wpacu_area_update_assets_row_state_nonce']   = wp_create_nonce('wpacu_area_update_assets_row_state_nonce');
        $wpacuObjectData['wpacu_print_loaded_hardcoded_assets_nonce']  = wp_create_nonce('wpacu_print_loaded_hardcoded_assets_nonce');
        $wpacuObjectData['wpacu_ajax_check_remote_file_size_nonce']    = wp_create_nonce('wpacu_ajax_check_remote_file_size_nonce');
        $wpacuObjectData['wpacu_ajax_check_external_urls_nonce']       = wp_create_nonce('wpacu_ajax_check_external_urls_nonce');
        $wpacuObjectData['wpacu_ajax_get_loaded_assets_nonce']         = wp_create_nonce('wpacu_ajax_get_loaded_assets_nonce');
        $wpacuObjectData['wpacu_ajax_load_page_restricted_area_nonce'] = wp_create_nonce('wpacu_ajax_load_page_restricted_area_nonce');
        $wpacuObjectData['wpacu_ajax_clear_cache_nonce']               = wp_create_nonce('wpacu_ajax_clear_cache_nonce');
        $wpacuObjectData['wpacu_ajax_preload_url_nonce']               = wp_create_nonce('wpacu_ajax_preload_url_nonce'); // After the CSS/JS manager's form is submitted (e.g. on an edit post/page)

        if (Menu::isPluginPage() === 'settings' && SettingsAdminOnlyForAdmin::useAutoCompleteSearchForNonAdminUsersDd()) {
            $wpacuObjectData['wpacu_search_non_admin_users_for_dd_nonce'] = wp_create_nonce('wpacu_search_non_admin_users_for_dd_nonce');
        }

        return $wpacuObjectData;
    }
}
