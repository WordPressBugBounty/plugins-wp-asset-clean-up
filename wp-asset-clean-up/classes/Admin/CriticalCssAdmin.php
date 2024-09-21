<?php
namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\Misc;
use WpAssetCleanUp\OptimiseAssets\MinifyCss;

/**
 * Class CriticalCssAdmin
 */
class CriticalCssAdmin
{
	/**
     * These are stored as keys with values in the database
	 * This will be later filled with custom post types & custom taxonomies (if any)
	 *
	 * @var string[]
	 */
	public static $allDbLocationKeyPages = array(
		'homepage', 'posts', 'pages', 'media', 'category', 'tag', 'search', 'author', 'date', '404_not_found'
	);

	/**
	 * CriticalCssAdmin constructor.
	 */
	public function __construct()
	{
	    // Dashboard's management: "CSS & JS Manager" -> "Manage Critical CSS"
        $wpacuSubPage = isset($_GET['wpacu_sub_page']) ? $_GET['wpacu_sub_page'] : '';

        if ( $wpacuSubPage === 'manage_critical_css' ) {
            add_action( 'admin_init', function() {
                self::$allDbLocationKeyPages = CriticalCssAdmin::fillAllDbLocationKeyPages( self::$allDbLocationKeyPages );
            }, 1 );
        }

        add_action('admin_init', array($this, 'updateCriticalCss'));
	}

    /**
	 * @param $criticalCssConfig
	 *
	 * @return array
	 */
	public static function getAllEnabledLocations($criticalCssConfig)
	{
		$allEnabledLocations = array();

		foreach (self::$allDbLocationKeyPages as $locationKey) {
			if ( is_string($locationKey) && isset( $criticalCssConfig[$locationKey]['enable'] ) && $criticalCssConfig[$locationKey]['enable'] ) {
				$allEnabledLocations[] = $locationKey;
			}
		}

		return $allEnabledLocations;
	}

	/**
	 * @param $allPossibleKeys
	 */
	public static function fillAllDbLocationKeyPages($allPossibleKeys)
	{
		// Any custom post types
        if ( ! empty(MiscAdmin::getCustomPostTypesList()) ) {
            $allPossibleKeys[] = 'custom_post_types';
        }

		// Any custom taxonomies
        if ( ! empty(MiscAdmin::getCustomTaxonomyList()) ) {
            $allPossibleKeys[] = 'custom_taxonomies';
        }

		return $allPossibleKeys;
	}

	/**
	 * @param $postTypesList
	 * @param $chosenPostType
     * @param $criticalCssConfig
	 */
	public static function buildCustomPostTypesListLinks($postTypesList, $chosenPostType, $criticalCssConfig)
	{
		?>
		<ul id="wpacu_custom_pages_nav_links">
			<?php
			foreach ($postTypesList as $postTypeKey => $postTypeValue) {
			    $liClass = ($chosenPostType === $postTypeKey) ? 'wpacu-current' : '';
			    $navLink = esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=custom_post_types&wpacu_current_post_type='.$postTypeKey));
			    $wpacuStatus = (isset($criticalCssConfig['custom_post_type_'.$postTypeKey]['enable']) && $criticalCssConfig['custom_post_type_'.$postTypeKey]['enable']) ? 'wpacu-on' : 'wpacu-off';
			?>
                <li class="<?php echo esc_attr($liClass); ?>">
                    <a href="<?php echo esc_url($navLink); ?>"><?php echo esc_html($postTypeValue); ?><span data-wpacu-custom-page-type="<?php echo esc_attr($postTypeKey); ?>_post_type" class="wpacu-circle-status <?php echo esc_attr($wpacuStatus); ?>"></span></a>
                </li>
			<?php
			}
			?>
		</ul>
		<?php
	}

	/**
	 * @param $taxonomyList
	 * @param $chosenTaxonomy
	 * @param $criticalCssConfig
	 */
	public static function buildTaxonomyListLinks($taxonomyList, $chosenTaxonomy, $criticalCssConfig)
	{
		?>
        <ul id="wpacu_custom_pages_nav_links">
			<?php
			foreach ($taxonomyList as $taxonomyKey => $taxonomyValue) {
				$liClass = ($chosenTaxonomy === $taxonomyKey) ? 'wpacu-current' : '';
				$navLink = esc_url(admin_url('admin.php?page=wpassetcleanup_assets_manager&wpacu_sub_page=manage_critical_css&wpacu_for=custom_taxonomies&wpacu_current_taxonomy='.$taxonomyKey));
				$wpacuStatus = (isset($criticalCssConfig['custom_taxonomy_'.$taxonomyKey]['enable']) && $criticalCssConfig['custom_taxonomy_'.$taxonomyKey]['enable']) ? 'wpacu-on' : 'wpacu-off';
				?>
                <li class="<?php echo esc_attr($liClass); ?>">
                    <a href="<?php echo esc_url($navLink); ?>"><?php echo esc_html($taxonomyValue); ?><span data-wpacu-custom-page-type="<?php echo esc_attr($taxonomyKey); ?>_taxonomy" class="wpacu-circle-status <?php echo esc_attr($wpacuStatus); ?>"></span></a>
                </li>
				<?php
			}
			?>
        </ul>
		<?php
	}

	/**
	 * @param $criticalCssConfig
	 * @param $dbKeyPrefix
	 *
	 * @return bool
     *
     * @noinspection PhpUndefinedVariableInspection
     */
	public static function isEnabledForAtLeastOnePageType($criticalCssConfig, $dbKeyPrefix)
    {
        // Fix: There might be dormant custom post types or taxonomies (not used anymore on the website)
	    // That have traces left / These will not count as it would confuse the admin

        if ($dbKeyPrefix === 'custom_taxonomy') {
	        $allCustomTaxonomies = MiscAdmin::getCustomTaxonomyList();
        } elseif ($dbKeyPrefix === 'custom_post_type') {
	        $allCustomPostTypes = MiscAdmin::getCustomPostTypesList();
        }
	    foreach ($criticalCssConfig as $locationConfigKey => $locationConfigValue) {
	        if ($dbKeyPrefix === 'custom_taxonomy') {
                $savedTax = str_replace($dbKeyPrefix.'_', '', $locationConfigKey);

                if ( ! in_array($savedTax, $allCustomTaxonomies) ) {
                    continue; // Custom taxonomy not used anymore
                }
            } elseif ($dbKeyPrefix === 'custom_post_type') {
		        $savedCustomPostType = str_replace($dbKeyPrefix.'_', '', $locationConfigKey);

		        if ( ! in_array($savedCustomPostType, $allCustomPostTypes) ) {
			        continue; // Custom post type not used anymore
		        }
	        }

            if (isset($locationConfigValue['enable']) && $locationConfigValue['enable'] && strpos($locationConfigKey, $dbKeyPrefix.'_') === 0) {
                return true;
            }
        }

        return false;
    }

	/**
	 *
	 */
	public function updateCriticalCss()
	{
		if ( ! Misc::getVar('post', 'wpacu_critical_css_submit') ) {
			return;
		}

		$mainKeyForm = WPACU_PLUGIN_ID . '_critical_css';

		check_admin_referer('wpacu_critical_css_update', 'wpacu_critical_css_nonce');

		$locationKey = isset($_POST[$mainKeyForm]['location_key']) ? $_POST[$mainKeyForm]['location_key'] : false;

		if (! $locationKey) {
			return;
		}

		$enable     = isset($_POST[$mainKeyForm]['enable'])      ? $_POST[$mainKeyForm]['enable']  : false;
		$content    = isset($_POST[$mainKeyForm]['content'])     ? $_POST[$mainKeyForm]['content'] : '';
		$showMethod = isset($_POST[$mainKeyForm]['show_method']) ? $_POST[$mainKeyForm]['show_method'] : 'original';

		$optionToUpdate = WPACU_PLUGIN_ID . '_critical_css_config';

		$existingListEmpty = array();
		$existingListJson  = get_option($optionToUpdate);

		$existingListData = Main::instance()->existingList($existingListJson, $existingListEmpty);
		$existingList = $existingListData['list'];

		if ($enable && $content) {
			$existingList[$locationKey]['enable'] = true;
		} elseif (! $enable) {
			$existingList[$locationKey]['enable'] = false;
		}

		$existingList[$locationKey]['show_method'] = $showMethod;

		Misc::addUpdateOption($optionToUpdate, wp_json_encode(Misc::filterList($existingList)));

		$optionToUpdateForCssContent = WPACU_PLUGIN_ID . '_critical_css_location_key_'.$locationKey;

		if ($content) {
			$contentToSaveArray = array();
			$contentOriginal = $content;

			$contentToSaveArray['content_original'] = $contentOriginal;

			if ($showMethod === 'minified') {
				$contentToSaveArray['content_minified'] = MinifyCss::applyMinification($contentOriginal, true);

				if ($contentToSaveArray['content_minified'] === $contentToSaveArray['content_original']) {
					// No change? The content is already minified and there's no point in saving duplicate contents
					unset($contentToSaveArray['content_minified']);
				}
			}

			$optionValue = wp_json_encode($contentToSaveArray);
			Misc::addUpdateOption($optionToUpdateForCssContent, $optionValue);
		} else {
			delete_option($optionToUpdateForCssContent);
		}
	}

    /**
     * @param $data
     * @param $wpacuFor
     *
     * @return string
     */
    public static function classToAppendToCriticalCssNavTab($data, $wpacuFor)
    {
        $classToAppend = '';

        if ($data['for'] === $wpacuFor) {
            $classToAppend .= ' wpacu-nav-tab-active ';
        }

        // $wpacuFor for some options does not have the same value as the keys from the database for the following option name: WPACU_PLUGIN_ID . '_critical_css_config' (from the WordPress `options` table)
        // e.g. "media_attachment" would be "media" in the database (it was saved like this from the beginning of the critical CSS feature)
        // e.g. "custom_post_types" would sometimes be in the database "custom_post_type_product"
        // Make sure the proper checks are performed so that everything is matched

        if ($wpacuFor === 'media_attachment') {
            $dbKeyPrefix = 'media';
        } elseif ($wpacuFor === 'custom_post_types') {
            $dbKeyPrefix = 'custom_post_type';
        } elseif ($wpacuFor === 'custom_taxonomies') {
            $dbKeyPrefix = 'custom_taxonomy';
        } else {
            $dbKeyPrefix = $wpacuFor;
        }

        if (in_array($wpacuFor, array('custom_post_types', 'custom_taxonomies'))) {
            if ($wpacuFor === 'custom_post_types') {
                $dbKeyPrefix = 'custom_post_type';
            } elseif ($wpacuFor === 'custom_taxonomies') {
                $dbKeyPrefix = 'custom_taxonomy';
            }

            $condition = self::isEnabledForAtLeastOnePageType($data['critical_css_config'], $dbKeyPrefix);
        } elseif ($wpacuFor === 'media_attachment') {
            $condition = in_array('media', $data['critical_css_tabs_all_enabled_locations']);
        } else {
            $condition = in_array($wpacuFor, $data['critical_css_tabs_all_enabled_locations']);
        }

        if ($condition) {
            $classToAppend .= ' wpacu-on ';
        } else {
            $classToAppend .= ' wpacu-off ';
        }

        return $classToAppend;
    }
}
