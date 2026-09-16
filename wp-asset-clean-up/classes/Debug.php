<?php
namespace WpAssetCleanUp;

use WpAssetCleanUp\Admin\MiscAdmin;
use WpAssetCleanUp\OptimiseAssets\OptimizeCommon;

/**
 * Class Debug
 * @package WpAssetCleanUp
 */
class Debug
{

    const PREVIEW_NONCE_ACTION = 'wpacu_debug_preview';
    const PREVIEW_NONCE_NAME = 'wpacu_debug_nonce';
    const PREVIEW_TOKEN_QUERY_KEY = 'wpacu_debug_preview';
    const PREVIEW_TRANSIENT_PREFIX = 'wpacu_debug_preview_';
    const PREVIEW_TOKEN_TTL = 900;

    public static function getPreviewPageOptionLabels()
    {
        return array(
            'no_css_minify' => __('Disable CSS minification', 'wp-asset-clean-up'),
            'no_css_optimize' => __('Disable CSS combination', 'wp-asset-clean-up'),
            'no_js_minify' => __('Disable JavaScript minification', 'wp-asset-clean-up'),
            'no_js_optimize' => __('Disable JavaScript combination', 'wp-asset-clean-up'),
            'no_assets_settings' => __('Disable all front-end optimizations', 'wp-asset-clean-up'),
            'no_wpacu_load' => sprintf(__('Do not load %s on this page', 'wp-asset-clean-up'), WPACU_PLUGIN_TITLE)
        );
    }

    public static function sanitizePreviewPageOptions($options)
    {
        $result = array();
        foreach (self::getPreviewPageOptionLabels() as $key => $label) {
            $result[$key] = is_array($options) && isset($options[$key])
                && in_array($options[$key], array('1', 1, true), true) ? '1' : '0';
        }
        return $result;
    }


	/**
	 * Debug constructor.
	 */
	public function __construct()
	{
        self::hydratePreviewRequestFromToken();
        add_action('init', array($this, 'handlePreviewFormSubmission'), -1000);

		if ( isset($_GET['wpacu_debug']) && ! is_admin() ) {
            add_action('wp_footer', array($this, 'showDebugOptionsFront'), PHP_INT_MAX);

            }

		foreach( array('wp', 'admin_init') as $wpacuActionHook ) {
			add_action( $wpacuActionHook, static function() {
				if (isset( $_GET['wpacu_get_cache_dir_size'] ) && Menu::userCanAccessPlugin()) {
					self::printCacheDirInfo();
				}

				// For debugging purposes
				if (isset($_GET['wpacu_get_already_minified']) && Menu::userCanAccessPlugin()) {
                    echo '<pre>'; print_r(OptimizeCommon::getAlreadyMarkedAsMinified()); echo '</pre>';
                    exit();
                }

				if (isset($_GET['wpacu_remove_already_minified']) && Menu::userCanAccessPlugin()) {
					echo '<pre>'; OptimizeCommon::removeAlreadyMarkedAsMinified(); echo '</pre>';
					exit();
				}

				if (isset($_GET['wpacu_limit_already_minified']) && Menu::userCanAccessPlugin()) {
					OptimizeCommon::limitAlreadyMarkedAsMinified();
					echo '<pre>'; print_r(OptimizeCommon::getAlreadyMarkedAsMinified()); echo '</pre>';
					exit();
				}
			} );
		}
	}

    /**
     * @param $wpacuCacheKey
     *
     * @return array
     */
    public static function getTimingValues($wpacuCacheKey)
    {
        $wpacuExecTiming = ObjectCache::wpacu_cache_get( $wpacuCacheKey, 'wpacu_exec_time' ) ?: 0;

        $wpacuExecTimingMs = $wpacuExecTiming;

        $wpacuTimingFormatMs = rtrim(rtrim(number_format($wpacuExecTimingMs, 2, '.', ''), '0'), '.');
        $wpacuTimingFormatS  = rtrim(rtrim(number_format(($wpacuExecTimingMs / 1000), 3, '.', ''), '0'), '.');

        return array('ms' => $wpacuTimingFormatMs, 's' => $wpacuTimingFormatS);
    }

    /**
     * @param $timingKey
     * @param $htmlSource
     *
     * @return string|string[]
     */
    public static function printTimingFor($timingKey, $htmlSource)
    {
        $wpacuCacheKey       = 'wpacu_' . $timingKey . '_exec_time';
        $timingValues        = self::getTimingValues( $wpacuCacheKey);
        $wpacuTimingFormatMs = $timingValues['ms'];
        $wpacuTimingFormatS  = $timingValues['s'];

        return str_replace(
            array(
                '{' . $wpacuCacheKey . '}',
                '{' . $wpacuCacheKey . '_sec}'
            ),

            array(
                $wpacuTimingFormatMs . 'ms',
                $wpacuTimingFormatS . 's',
            ), // clean it up

            $htmlSource
        );
    }

	/**
	 * @param $htmlSource
     *
	 * @return string|string[]
	 */
	public static function applyDebugTiming($htmlSource)
	{
		if (isset($_GET['wpacu_debug'])) {
            $htmlSource = str_replace('"optimizationDetails":"WPACU_OPTIMIZATION_DETAILS_PENDING"',
                '"optimizationDetails":' . wp_json_encode(DebugOptimizationDetails::getRows(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), $htmlSource);
		}
		$timingKeys = array(
			'prepare_optimize_files_css',
			'prepare_optimize_files_js',

			// All HTML alteration via "wp_loaded" action hook
			'alter_html_source',

            'alter_html_source_for_resource_loading',

			// HTML CleanUp
			'alter_html_source_cleanup',
			'alter_html_source_for_remove_html_comments',
			'alter_html_source_for_remove_meta_generators',

			// CSS
			'alter_html_source_for_optimize_css',
			'alter_html_source_unload_ignore_deps_css',
			'alter_html_source_for_google_fonts_optimization_removal',
			'alter_html_source_for_inline_css',

			'alter_html_source_original_to_optimized_css',
			'alter_html_source_for_preload_css',

			'alter_html_source_for_combine_css',
			'alter_html_source_for_minify_inline_style_tags',
            'alter_html_source_for_local_fonts_display_style_inline',
			'alter_html_source_for_optimize_css_final_cleanups',

			// JS
			'alter_html_source_for_optimize_js',
			'alter_html_source_maybe_move_jquery_after_body_tag',
			'alter_html_source_unload_ignore_deps_js',

			'alter_html_source_original_to_optimized_js',
			'alter_html_source_for_preload_js',

			'alter_html_source_for_combine_js',

			'alter_html_source_move_inline_jquery_after_src_tag',
			'alter_html_source_for_optimize_js_final_cleanups',

            'alter_html_source_strip_any_references_for_unloaded_assets',

            'fetch_strip_hardcoded_assets',
			'fetch_all_hardcoded_assets',

			'output_css_js_manager',

			'style_loader_tag',
			'script_loader_tag',

			'style_loader_tag_preload_css',
			'script_loader_tag_preload_js',

            'all_timings'
		);

        $timingKeys = apply_filters('wpacu_internal_debug_timing_keys', $timingKeys);

		foreach ( $timingKeys as $timingKey ) {
            $htmlSource = self::printTimingFor($timingKey, $htmlSource);
		}

		return $htmlSource;
	}

	/**
	 *
	 */
	public function renderLegacyDebugOptionsFront()
	{
	    if (! Menu::userCanAccessPlugin()) {
	        return;
        }

	    $markedCssListForUnload = array_unique(Main::instance()->allUnloadedAssets['styles']);
		$markedJsListForUnload  = array_unique(Main::instance()->allUnloadedAssets['scripts']);

		$allDebugOptions = array(
			// [For CSS]
			'wpacu_no_css_unload'  => 'Do not apply any CSS unload rules',
			'wpacu_no_css_minify'  => 'Do not minify any CSS',
			'wpacu_no_css_combine' => 'Do not combine any CSS',

			'wpacu_no_css_preload_basic' => 'Do not preload any CSS (Basic)',

            // [/For CSS]

			// [For JS]
			'wpacu_no_js_unload'  => 'Do not apply any JavaScript unload rules',
			'wpacu_no_js_minify'  => 'Do not minify any JavaScript',
			'wpacu_no_js_combine' => 'Do not combine any JavaScript',

			'wpacu_no_js_preload_basic' => 'Do not preload any JS (Basic)',
			// [/For JS]

			// Others
			'wpacu_no_frontend_show' => 'Do not show the bottom CSS/JS managing list',
			'wpacu_no_admin_bar'     => 'Do not show the admin bar',
			'wpacu_no_html_changes'  => 'Do not alter the HTML DOM (this will also load all assets non-minified and non-combined)',
		);

        $allDebugOptions = apply_filters('wpacu_internal_debug_options', $allDebugOptions);
		?>
		<style <?php echo Misc::getStyleTypeAttribute(); ?>>
			<?php echo file_get_contents(WPACU_PLUGIN_DIR.'/assets/wpacu-debug.css'); ?>
		</style>

        <script <?php echo Misc::getScriptTypeAttribute(); ?>>
	        <?php echo file_get_contents(WPACU_PLUGIN_DIR.'/assets/wpacu-debug.js'); ?>
        </script>

		<div id="wpacu-debug-options">
            <table>
                <tr>
                    <td style="vertical-align: top;">
                        <p class="wpacu-debug-options-intro">View the page with the following options <strong>disabled</strong> (for debugging purposes):</p>
                        <form method="post">
                            <ul class="wpacu-options">
                            <?php
                            foreach ($allDebugOptions as $debugKey => $debugText) {
                            ?>
                                <li>
                                    <label>
                                        <input type="checkbox"
                                           name="<?php echo esc_attr($debugKey); ?>"
                                           <?php if ( isset($_REQUEST[$debugKey]) ) { echo 'checked="checked"'; } ?> /> &nbsp;<?php echo esc_html($debugText); ?>
                                    </label>
                                </li>
                            <?php
                            }
                            ?>
                            </ul>

                            <?php do_action('wpacu_internal_debug_front_after_options'); ?>

                            <div>
                                <input type="submit"
                                       value="Preview this page with the changes made above" />
                            </div>
                            <input type="hidden" name="wpacu_debug" value="on" />
                        </form>
                    </td>
                    <td style="vertical-align: top;">
                        <?php do_action('wpacu_internal_debug_front_right_column_top'); ?>

	                    <div style="margin: 0 0 10px; padding: 10px 0;">
	                        <strong>CSS handles marked for unload:</strong>&nbsp;
	                        <?php
	                        if (! empty($markedCssListForUnload)) {
	                            sort($markedCssListForUnload);
		                        $markedCssListForUnloadFiltered = array_map(static function($handle) {
		                        	return '<span style="color: darkred;">'.esc_html($handle).'</span>';
		                        }, $markedCssListForUnload);
	                            echo implode(' &nbsp;/&nbsp; ', $markedCssListForUnloadFiltered);
	                        } else {
	                            echo 'None';
	                        }
	                        ?>
	                    </div>

	                    <div style="margin: 0 0 10px; padding: 10px 0;">
	                        <strong>JS handles marked for unload:</strong>&nbsp;
	                        <?php
	                        if (! empty($markedJsListForUnload)) {
	                            sort($markedJsListForUnload);
		                        $markedJsListForUnloadFiltered = array_map(static function($handle) {
			                        return '<span style="color: darkred;">'.esc_html($handle).'</span>';
		                        }, $markedJsListForUnload);

	                            echo implode(' &nbsp;/&nbsp; ', $markedJsListForUnloadFiltered);
	                        } else {
	                            echo 'None';
	                        }
	                        ?>
	                    </div>

	                    <hr />

                        <div style="margin: 0 0 10px; padding: 10px 0;">
							<ul class="wpacu-debug-timing-root" style="list-style: none; padding-left: 0;">
                                <script>
                                    jQuery(document).ready(function($) {
                                        let valueNum = 0;

                                        $('[data-wpacu-count-it]').each(function(index, value) {
                                            let extractedNumber = parseFloat($(this).attr("data-wpacu-count-it").replace('ms', ''));
                                            console.log(extractedNumber);

                                            valueNum += extractedNumber;
                                        });

                                        valueNum = valueNum.toFixed(2);

                                        $('#wpacu-total-all-timings').html(valueNum);
                                        });
                                </script>
                                <li style="margin-bottom: 15px; border-bottom: 1px solid #e7e7e7;"><strong>Total timing for all recorded actions:</strong> <span id="wpacu-total-all-timings"></span>ms</li>

                                <li style="margin-bottom: 10px;" data-wpacu-count-it="<?php echo self::printTimingFor('filter_dequeue_styles',  '{wpacu_filter_dequeue_styles_exec_time}'); ?>">Dequeue any chosen styles (.css): <?php echo self::printTimingFor('filter_dequeue_styles',  '{wpacu_filter_dequeue_styles_exec_time} ({wpacu_filter_dequeue_styles_exec_time_sec})'); ?></li>
                                <li style="margin-bottom: 20px;" data-wpacu-count-it="<?php echo self::printTimingFor('filter_dequeue_scripts',  '{wpacu_filter_dequeue_scripts_exec_time}'); ?>">Dequeue any chosen scripts (.js): <?php echo self::printTimingFor('filter_dequeue_scripts', '{wpacu_filter_dequeue_scripts_exec_time} ({wpacu_filter_dequeue_scripts_exec_time_sec})'); ?></li>

                                <li style="margin-bottom: 10px;" data-wpacu-count-it="{wpacu_prepare_optimize_files_css_exec_time}">Prepare CSS files to optimize: {wpacu_prepare_optimize_files_css_exec_time} ({wpacu_prepare_optimize_files_css_exec_time_sec})</li>
                                <li style="margin-bottom: 20px;" data-wpacu-count-it="{wpacu_prepare_optimize_files_js_exec_time}">Prepare JS files to optimize: {wpacu_prepare_optimize_files_js_exec_time} ({wpacu_prepare_optimize_files_js_exec_time_sec})</li>

                                <li style="margin-bottom: 10px;" data-wpacu-count-it="{wpacu_alter_html_source_exec_time}">OptimizeCommon - HTML alteration via <em>wp_loaded</em>: {wpacu_alter_html_source_exec_time} ({wpacu_alter_html_source_exec_time_sec})
                                    <ul id="wpacu-debug-timing">
                                        <li style="margin-top: 10px; margin-bottom: 10px;">&nbsp;OptimizeCSS: {wpacu_alter_html_source_for_optimize_css_exec_time} ({wpacu_alter_html_source_for_optimize_css_exec_time_sec})
                                            <ul>
                                                <li>Google Fonts Optimization/Removal: {wpacu_alter_html_source_for_google_fonts_optimization_removal_exec_time}</li>
                                                <li>From CSS file to Inline: {wpacu_alter_html_source_for_inline_css_exec_time}</li>
                                                <li>Update Original to Optimized: {wpacu_alter_html_source_original_to_optimized_css_exec_time}</li>
                                                <?php do_action('wpacu_internal_debug_timing_css_after_original_to_optimized'); ?>
                                                <li>Preloads: {wpacu_alter_html_source_for_preload_css_exec_time}</li>

                                                <?php do_action('wpacu_internal_debug_timing_css_after_preload'); ?>

                                                <!-- -->

                                                <li>Combine: {wpacu_alter_html_source_for_combine_css_exec_time}</li>
                                                <li>Minify Inline Tags: {wpacu_alter_html_source_for_minify_inline_style_tags_exec_time}</li>
                                                <li>Unload (ignore dependencies): {wpacu_alter_html_source_unload_ignore_deps_css_exec_time}</li>
                                                <li>Alter Inline CSS (font-display): {wpacu_alter_html_source_for_local_fonts_display_style_inline_exec_time}</li>

                                                <?php do_action('wpacu_internal_debug_timing_css_after_local_fonts_display'); ?>

                                                <li>Final Cleanups for the HTML source: {wpacu_alter_html_source_for_optimize_css_final_cleanups_exec_time}</li>
                                            </ul>
                                        </li>

                                        <li style="margin-top: 10px; margin-bottom: 10px;">OptimizeJs: {wpacu_alter_html_source_for_optimize_js_exec_time} ({wpacu_alter_html_source_for_optimize_js_exec_time_sec})
                                            <ul>

                                                <?php do_action('wpacu_internal_debug_timing_js_before_original_to_optimized'); ?>

                                                <li>Update Original to Optimized: {wpacu_alter_html_source_original_to_optimized_js_exec_time}</li>
                                                <li>Preloads: {wpacu_alter_html_source_for_preload_js_exec_time}</li>
                                                <!-- -->

                                                <li>Combine: {wpacu_alter_html_source_for_combine_js_exec_time}</li>

                                                <?php do_action('wpacu_internal_debug_timing_js_after_combine'); ?>

                                                <li>Move jQuery within the BODY tag: {wpacu_alter_html_source_maybe_move_jquery_after_body_tag_exec_time}</li>
                                                <li>Unload (ignore dependencies): {wpacu_alter_html_source_unload_ignore_deps_js_exec_time}</li>
                                                <li>Move any inline with jQuery code after jQuery library: {wpacu_alter_html_source_move_inline_jquery_after_src_tag_exec_time}</li>
                                                <li>Final Cleanups for the HTML source: {wpacu_alter_html_source_for_optimize_js_final_cleanups_exec_time}</li>
                                            </ul>
                                        </li>

                                        <li>Strip any references for unloaded assets: {wpacu_alter_html_source_strip_any_references_for_unloaded_assets_exec_time}</li>

                                        <li>Apply any Resource Loading rules: {wpacu_alter_html_source_for_resource_loading_exec_time}</li>

                                        <?php do_action('wpacu_internal_debug_timing_after_resource_loading'); ?>

                                        <li>HTML CleanUp: {wpacu_alter_html_source_cleanup_exec_time}
                                            <ul>
                                                <li>Strip HTML Comments: {wpacu_alter_html_source_for_remove_html_comments_exec_time}</li>
	                                            <li>Remove Generator META tags: {wpacu_alter_html_source_for_remove_meta_generators_exec_time}</li>
                                            </ul>
                                        </li>
                                    </ul>
                                </li>

								<li style="margin-bottom: 10px;" data-wpacu-count-it="{wpacu_output_css_js_manager_exec_time}">Output CSS &amp; JS Management List: {wpacu_output_css_js_manager_exec_time} ({wpacu_output_css_js_manager_exec_time_sec})</li>

                                <li style="margin-bottom: 10px;" data-wpacu-count-it="{wpacu_style_loader_tag_exec_time}">"style_loader_tag" filters: {wpacu_style_loader_tag_exec_time} ({wpacu_style_loader_tag_exec_time_sec})</li>
                                <li style="margin-bottom: 10px;" data-wpacu-count-it="{wpacu_script_loader_tag_exec_time}">"script_loader_tag" filters: {wpacu_script_loader_tag_exec_time} ({wpacu_script_loader_tag_exec_time_sec})</li>

								<li style="margin-bottom: 10px;" data-wpacu-count-it="{wpacu_style_loader_tag_preload_css_exec_time}">"style_loader_tag" filters (Preload CSS): {wpacu_style_loader_tag_preload_css_exec_time} ({wpacu_style_loader_tag_preload_css_exec_time_sec})</li>
								<li style="margin-bottom: 10px;" data-wpacu-count-it="{wpacu_script_loader_tag_preload_js_exec_time}">"script_loader_tag" filters (Preload JS): {wpacu_script_loader_tag_preload_js_exec_time} ({wpacu_script_loader_tag_preload_js_exec_time_sec})</li>

                                <?php do_action('wpacu_internal_debug_timing_after_script_loader_tag_preload_js'); ?>
							</ul>
	                    </div>
                    </td>
                </tr>
            </table>
		</div>
		<?php
	}

	/**
	 *
	 */
	public static function printCacheDirInfo()
    {
    	$assetCleanUpCacheDirRel = OptimizeCommon::getRelPathPluginCacheDir();
	    $assetCleanUpCacheDir  = WP_CONTENT_DIR . $assetCleanUpCacheDirRel;

	    echo '<h3>'.WPACU_PLUGIN_TITLE.': Caching Directory Stats</h3>';

	    if (is_dir($assetCleanUpCacheDir)) {
	    	$printCacheDirOutput = '<em>'.str_replace($assetCleanUpCacheDirRel, '<strong>'.$assetCleanUpCacheDirRel.'</strong>', $assetCleanUpCacheDir).'</em>';

	    	if (! is_writable($assetCleanUpCacheDir)) {
			    echo '<span style="color: red;">'.
			            'The '.wp_kses($printCacheDirOutput, array('em' => array(), 'strong' => array())).' directory is <em>not writable</em>.</span>'.
			         '<br /><br />';
		    } else {
			    echo '<span style="color: green;">The '.wp_kses($printCacheDirOutput, array('em' => array(), 'strong' => array())).' directory is <em>writable</em>.</span>' . '<br /><br />';
		    }

		    $dirItems = new \RecursiveDirectoryIterator( $assetCleanUpCacheDir, \RecursiveDirectoryIterator::SKIP_DOTS );

		    $totalFiles = 0;
		    $totalSize  = 0;

		    foreach (
			    new \RecursiveIteratorIterator( $dirItems, \RecursiveIteratorIterator::SELF_FIRST,
				    \RecursiveIteratorIterator::CATCH_GET_CHILD ) as $item
		    ) {
			    $appendAfter = '';

			    if ($item->isDir()) {
			    	echo '<br />';

				    $appendAfter = ' - ';

			    	if (is_writable($item)) {
					    $appendAfter .= ' <em><strong>writable</strong> directory</em>';
				    } else {
					    $appendAfter .= ' <em><strong style="color: red;">not writable</strong> directory</em>';
				    }
			    } elseif ($item->isFile()) {
			    	$appendAfter = '(<em>'.MiscAdmin::formatBytes($item->getSize()).'</em>)';

			    	echo '&nbsp;-&nbsp;';
			    }

			    echo wp_kses($item.' '.$appendAfter, array(
			            'em' => array(),
                        'strong' => array('style' => array()),
                        'br' => array(),
                        'span' => array('style' => array())
                    ))

                     .'<br />';

			    if ( $item->isFile() ) {
				    $totalSize += $item->getSize();
				    $totalFiles ++;
			    }
		    }

		    echo '<br />'.'Total Files: <strong>'.$totalFiles.'</strong> / Total Size: <strong>'.MiscAdmin::formatBytes($totalSize).'</strong>';
	    } else {
		    echo 'The directory does not exists.';
	    }

	    exit();
    }

    /**
     * Render the existing debug data inside an isolated, modern shell.
     * The legacy renderer remains the source of the diagnostic data so no
     * timings or plugin information are lost while the presentation evolves.
     *
     * @return void
     */
    public function showDebugOptionsFront()
    {
        ob_start();
        $this->renderLegacyDebugOptionsFront();
        $legacyHtml = ob_get_clean();

        if ( ! is_string($legacyHtml) || trim($legacyHtml) === '' ) {
            return;
        }

        $htmlAlterationsDisabled = isset($_REQUEST['wpacu_no_html_changes'])
            || wpacuIsDefinedConstant('WPACU_NO_HTML_CHANGES')
            || OptimizeCommon::preventAnyFrontendOptimization();
        $htmlAlterationsDisabledReason = __('A front-end optimization restriction', 'wp-asset-clean-up');
        if (MetaBoxes::hasNoFrontendOptimizationPageOption()) {
            $htmlAlterationsDisabledReason = __('"Disable all front-end optimizations" in Page Options', 'wp-asset-clean-up');
        } elseif (isset($_REQUEST['wpacu_no_html_changes'])) {
            $htmlAlterationsDisabledReason = __('Disable HTML source alterations', 'wp-asset-clean-up');
        } elseif (wpacuIsDefinedConstant('WPACU_NO_HTML_CHANGES')) {
            $htmlAlterationsDisabledReason = 'WPACU_NO_HTML_CHANGES';
        }
        if ($htmlAlterationsDisabled) {
            $timingsAvailableWithoutHtmlAlteration = array(
                'prepare_optimize_files_css',
                'prepare_optimize_files_js',
                'filter_dequeue_styles',
                'filter_dequeue_scripts',
                'style_loader_tag',
                'script_loader_tag',
                'style_loader_tag_preload_css',
                'script_loader_tag_preload_js',
                'style_loader_tag_pro_changes',
                'script_loader_tag_pro_changes'
            );
            $timingsAvailableWithoutHtmlAlteration = apply_filters(
                'wpacu_debug_timings_available_without_html_alteration',
                $timingsAvailableWithoutHtmlAlteration
            );

            foreach ((array)$timingsAvailableWithoutHtmlAlteration as $timingKey) {
                if ( ! is_string($timingKey) || $timingKey === '') {
                    continue;
                }

                $recordedTiming = ObjectCache::wpacu_cache_get(
                    'wpacu_' . $timingKey . '_exec_time',
                    'wpacu_exec_time'
                );
                if (is_numeric($recordedTiming)) {
                    $legacyHtml = self::printTimingFor($timingKey, $legacyHtml);
                }
            }

            // The HTML pipeline can be skipped by a preview option, page override,
            // or another frontend optimization guard. A missing timer outside it can
            // be inapplicable to the request and is therefore reported as N/A.
            $legacyHtml = preg_replace_callback(
                '/(data-wpacu-count-it=["\'])\{wpacu_[a-z0-9_]+\}(["\'])/i',
                static function ($matches) {
                    return $matches[1] . '0' . $matches[2];
                },
                $legacyHtml
            );
            $htmlPipelineTimingKeys = array(
                'alter_html_source',
                'alter_html_source_for_resource_loading',
                'alter_html_source_cleanup',
                'alter_html_source_for_remove_html_comments',
                'alter_html_source_for_remove_meta_generators',
                'alter_html_source_for_optimize_css',
                'alter_html_source_unload_ignore_deps_css',
                'alter_html_source_for_google_fonts_optimization_removal',
                'alter_html_source_for_inline_css',
                'alter_html_source_for_change_css_position',
                'alter_html_source_original_to_optimized_css',
                'alter_html_source_for_preload_css',
                'alter_html_source_for_add_async_preloads_noscript',
                'alter_html_source_for_combine_css',
                'alter_html_source_for_minify_inline_style_tags',
                'alter_html_source_for_local_fonts_display_style_inline',
                'alter_html_source_for_defer_footer_css',
                'alter_html_source_for_media_query_load_css',
                'alter_html_source_for_optimize_css_final_cleanups',
                'alter_html_source_for_optimize_js',
                'alter_html_source_maybe_move_jquery_after_body_tag',
                'alter_html_source_unload_ignore_deps_js',
                'alter_html_source_for_inline_js',
                'alter_html_source_for_media_query_load_js',
                'alter_html_source_original_to_optimized_js',
                'alter_html_source_for_preload_js',
                'alter_html_source_for_combine_js',
                'alter_html_source_move_scripts_to_body',
                'alter_html_source_for_minify_inline_script_tags',
                'alter_html_source_move_inline_jquery_after_src_tag',
                'alter_html_source_for_optimize_js_final_cleanups',
                'alter_html_source_strip_any_references_for_unloaded_assets',
                'fetch_strip_hardcoded_assets',
                'fetch_rules_hardcoded_assets',
                'fetch_all_hardcoded_assets',
                'move_alter_hardcoded_assets',
                'strip_marked_hardcoded_assets',
                'change_positions_hardcoded_assets',
                'preload_and_tag_changes_hardcoded_assets',
                'clear_positions_pro_html_signatures_hardcoded_assets'
            );
            $skippedTimingHtml = '<span class="wpacu-debug-timing-skipped">' . esc_html__('Skipped', 'wp-asset-clean-up') . '</span>';
            foreach ($htmlPipelineTimingKeys as $timingKey) {
                $quotedTimingKey = preg_quote('wpacu_' . $timingKey . '_exec_time', '/');
                $legacyHtml = preg_replace(
                    '/\{' . $quotedTimingKey . '\}\s*\(\{' . $quotedTimingKey . '_sec\}\)/i',
                    $skippedTimingHtml,
                    $legacyHtml
                );
                $legacyHtml = preg_replace('/\{' . $quotedTimingKey . '(?:_sec)?\}/i', $skippedTimingHtml, $legacyHtml);
            }

            $notApplicableTimingHtml = '<span class="wpacu-debug-timing-na">' . esc_html__('N/A', 'wp-asset-clean-up') . '</span>';
            $legacyHtml = preg_replace(
                '/\{(wpacu_[a-z0-9_]+_exec_time)\}\s*\(\{\1_sec\}\)/i',
                $notApplicableTimingHtml,
                $legacyHtml
            );
            $legacyHtml = preg_replace('/\{wpacu_[a-z0-9_]+\}/i', $notApplicableTimingHtml, $legacyHtml);
        }

        $legacyHtml = $this->preparePreviewFormHtml($legacyHtml);
        $instanceId = 'wpacu-debug-' . str_replace('.', '', uniqid('', true));
        $pluginRootFile = dirname(dirname(__FILE__)) . '/index.php';
        $cssFile = dirname(dirname(__FILE__)) . '/assets/wpacu-debug-panel.css';
        $jsFile  = dirname(dirname(__FILE__)) . '/assets/wpacu-debug-panel.js';
        $cssUrl = add_query_arg('ver', filemtime($cssFile), plugins_url('assets/wpacu-debug-panel.css', $pluginRootFile));
        $jsUrl  = add_query_arg('ver', filemtime($jsFile), plugins_url('assets/wpacu-debug-panel.js', $pluginRootFile));
        $previewPayload = isset($GLOBALS['wpacu_debug_preview_payload']) && is_array($GLOBALS['wpacu_debug_preview_payload'])
            ? $GLOBALS['wpacu_debug_preview_payload']
            : array();
        $selectedSwitches = array();
        foreach (array_keys(self::getPreviewSwitches()) as $switchKey) {
            if (isset($_REQUEST[$switchKey])) {
                $selectedSwitches[] = $switchKey;
            }
        }
        $requestChangePlugins = isset($GLOBALS['wpacu_filtered_plugins']) && is_array($GLOBALS['wpacu_filtered_plugins'])
            ? array_values(array_unique(array_map('strval', $GLOBALS['wpacu_filtered_plugins'])))
            : array();
        $requestChangeCssHandles = isset(Main::instance()->allUnloadedAssets['styles'])
            ? array_values(array_unique(array_map('strval', (array)Main::instance()->allUnloadedAssets['styles'])))
            : array();
        $requestChangeJsHandles = isset(Main::instance()->allUnloadedAssets['scripts'])
            ? array_values(array_unique(array_map('strval', (array)Main::instance()->allUnloadedAssets['scripts'])))
            : array();
        sort($requestChangePlugins);
        sort($requestChangeCssHandles);
        sort($requestChangeJsHandles);
        $savedPageOptions = array();
        $pageOptionsAvailable = defined('WPACU_CURRENT_PAGE_ID') && (MainFront::isSingularPage() || MainFront::isHomePage());
        if ($pageOptionsAvailable) {
            $savedPageOptions = MetaBoxes::getSavedPageOptions(WPACU_CURRENT_PAGE_ID, MainFront::isSingularPage() ? 'post' : 'front_page');
        }
		$optimizationDetails = 'WPACU_OPTIMIZATION_DETAILS_PENDING';
		if ($htmlAlterationsDisabled && isset($_GET['wpacu_debug'])) {
			$optimizationDetails = DebugOptimizationDetails::getRows();
		}

        $config = array(
            'pageOptions' => $pageOptionsAvailable ? self::getPreviewPageOptionLabels() : array(),
            'savedPageOptions' => self::sanitizePreviewPageOptions($savedPageOptions),
            'selectedPageOptions' => isset($GLOBALS['wpacu_debug_page_options']) ? $GLOBALS['wpacu_debug_page_options'] : self::sanitizePreviewPageOptions($savedPageOptions),
            'optimizationDetails' => $optimizationDetails,
            'instanceId'       => $instanceId,
            'productName'      => is_dir(dirname(dirname(__FILE__)) . '/pro') ? 'Asset CleanUp Pro' : 'Asset CleanUp',
            'switches'         => array_values(self::getPreviewSwitches()),
            'selectedSwitches' => $selectedSwitches,
            'selectedPlugins'  => isset($previewPayload['plugins']) && is_array($previewPayload['plugins']) ? array_values($previewPayload['plugins']) : array(),
            'previewApplied'   => ! empty($previewPayload),
            'htmlAlterationsDisabled' => $htmlAlterationsDisabled,
            'requestChanges'   => array(
                'plugins'    => $requestChangePlugins,
                'cssHandles' => $requestChangeCssHandles,
                'jsHandles'  => $requestChangeJsHandles
            ),
            'labels'           => array(
                'title'             => __('Live Debugging', 'wp-asset-clean-up'),
                'subtitle'          => __('Create an isolated preview of this page. These choices apply only to the preview request and are never saved to the database.', 'wp-asset-clean-up'),
                'requestOnly'       => __('Temporary preview', 'wp-asset-clean-up'),
                'previewApplied'    => __('Preview updated. Your temporary debugging choices are now active for this request.', 'wp-asset-clean-up'),
                'viewDebugControls' => __('View debugging controls', 'wp-asset-clean-up'),
                'goToPageTop'       => __('Go to page top', 'wp-asset-clean-up'),
                'css'               => __('Styles', 'wp-asset-clean-up'),
                'javascript'        => __('Scripts', 'wp-asset-clean-up'),
                'other'             => __('Page & Runtime', 'wp-asset-clean-up'),
                'plugins'           => __('Temporary plugin unloading', 'wp-asset-clean-up'),
                'pluginsHelp'       => __('Selected plugins will be skipped only in the validated preview request. Asset CleanUp itself cannot be selected.', 'wp-asset-clean-up'),
                'pluginsDisabled'   => __('Plugin unloading is disabled by “Ignore Plugins Manager unload rules”. The selections below will not apply to this preview.', 'wp-asset-clean-up'),
                'disabledByOption'  => __('disabled by option', 'wp-asset-clean-up'),
                'searchPlugins'     => __('Search active plugins', 'wp-asset-clean-up'),
                'clearSelection'    => __('Clear selected plugins', 'wp-asset-clean-up'),
                'selected'          => __('selected', 'wp-asset-clean-up'),
                'pluginSelected'    => __('plugin selected', 'wp-asset-clean-up'),
                'pluginsSelected'   => __('plugins selected', 'wp-asset-clean-up'),
                'reset'             => __('Reset all preview choices', 'wp-asset-clean-up'),
                'submit'            => __('Preview this page with selected changes', 'wp-asset-clean-up'),
                'details'           => __('Current request and performance details', 'wp-asset-clean-up'),
                'requestChanges'    => __('Request changes', 'wp-asset-clean-up'),
                'unloadedPlugins'   => __('Unloaded plugins', 'wp-asset-clean-up'),
                'cssHandles'        => __('Unloaded CSS handles', 'wp-asset-clean-up'),
                'jsHandles'         => __('Unloaded JS handles', 'wp-asset-clean-up'),
                'none'              => __('None', 'wp-asset-clean-up'),
                'performanceSummary'=> __('Performance summary', 'wp-asset-clean-up'),
                'totalRecorded'     => __('Total recorded', 'wp-asset-clean-up'),
                'cssProcessing'     => __('CSS processing', 'wp-asset-clean-up'),
                'pageOptionsTitle' => __('Page Options — temporary overrides', 'wp-asset-clean-up'),
                'pageOptionsHelp' => __('Preview changes to this page’s optimization controls. Your saved Page Options will not be changed. Unchecking a page option allows your global settings to apply. It does not enable features disabled globally.', 'wp-asset-clean-up'),
                'disabledByPageOptions' => __('Disabled by Page Options', 'wp-asset-clean-up'),
                'disabledByPageOptionsHelp' => __('This feature is disabled by the selected Page Options below. Remove that override to make this debugging control available again.', 'wp-asset-clean-up'),
                'pageOptionsNoLoadWarning' => __('Warning:', 'wp-asset-clean-up'),
                'pageOptionsNoLoadHelp' => __('When you apply this preview, Asset CleanUp will not load and the entire debugging panel will disappear. Use your browser’s Back button to return to these controls. Your saved settings will not be changed.', 'wp-asset-clean-up'),
                'pageOptionsOverridden' => __('This selection is retained, but the enabled option below takes precedence.', 'wp-asset-clean-up'),
                'optimizationDetails' => __('CSS/JS optimization details', 'wp-asset-clean-up'),
                'optimizationDetailsHelp' => __('Recorded file-level decisions for this request. An omitted operation is not proof that it succeeded. Filenames do not determine whether minification is needed. Up to 500 operation records are shown.', 'wp-asset-clean-up'),
                'optimizationDetailsEmpty' => __('No file-level exceptions were recorded. Processing may have been disabled or skipped for this request.', 'wp-asset-clean-up'),
                'optimizationStatusSkipped' => __('Skipped', 'wp-asset-clean-up'),
                'optimizationStatusUnchanged' => __('No change needed', 'wp-asset-clean-up'),
                'optimizationStatusFailed' => __('Failed', 'wp-asset-clean-up'),
                'optimizationStatusChanged' => __('Content changed', 'wp-asset-clean-up'),
                'optimizationStatusCached' => __('Cached', 'wp-asset-clean-up'),
                'optimizationOperations' => array(
                    'Font display' => __('Font display', 'wp-asset-clean-up'),
                    'Google Fonts display' => __('Google Fonts display', 'wp-asset-clean-up'),
                    'Google Fonts removal' => __('Google Fonts removal', 'wp-asset-clean-up'),
                    'Local Google Fonts' => __('Local Google Fonts', 'wp-asset-clean-up'),
                    'CSS imports' => __('CSS imports', 'wp-asset-clean-up')
                ),
                'optimizationMinification' => __('Minification', 'wp-asset-clean-up'),
                'optimizationFile' => __('File optimization', 'wp-asset-clean-up'),
                'optimizationGeneratedHandle' => __('Asset CleanUp generated this identifier from the file URL because this asset was not found in the WordPress enqueue list. It is not a registered WordPress handle.', 'wp-asset-clean-up'),
                'optimizationUnregistered' => __('Unregistered asset', 'wp-asset-clean-up'),
                'jsProcessing'      => __('JS processing', 'wp-asset-clean-up'),
                'htmlProcessing'    => __('HTML processing', 'wp-asset-clean-up'),
                'assetPreparation'  => __('Asset preparation', 'wp-asset-clean-up'),
                'loaderTagFilters'  => __('Loader tag filters', 'wp-asset-clean-up'),
                'hardcodedAssets'   => __('Hardcoded assets', 'wp-asset-clean-up'),
                'htmlCleanup'       => __('HTML cleanup', 'wp-asset-clean-up'),
                'showSecondaryTimings' => __('Show unavailable and zero-value details', 'wp-asset-clean-up'),
                'timingsUnavailableBefore' => __('Some timing values are marked “Skipped” because their operations are disabled by', 'wp-asset-clean-up'),
                'timingsUnavailableOption' => $htmlAlterationsDisabledReason,
                'timingsUnavailableAfter'  => __('Other values can show “N/A” when they do not apply to this request.', 'wp-asset-clean-up'),
                'unavailableOptions'=> __('Unavailable options', 'wp-asset-clean-up'),
                'noMatches'         => __('No plugins match this search.', 'wp-asset-clean-up')
            )
        );
        ?>
        <div id="<?php echo esc_attr($instanceId); ?>" class="wpacu-debug-shadow-host">
            <div class="wpacu-debug-light-content" data-wpacu-debug-content style="display:none;">
                <?php echo $legacyHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing trusted plugin diagnostic renderer. ?>
                <script type="application/json" data-wpacu-debug-config><?php echo wp_json_encode($config); ?></script>
            </div>
            <noscript><style>#<?php echo esc_attr($instanceId); ?> .wpacu-debug-light-content{display:block!important;}</style></noscript>
        </div>
        <script src="<?php echo esc_url($jsUrl); ?>" data-wpacu-debug-host="<?php echo esc_attr($instanceId); ?>" data-wpacu-debug-css="<?php echo esc_url($cssUrl); ?>"></script>
        <?php
    }

    /**
     * @param string $html
     *
     * @return string
     */
    private function preparePreviewFormHtml($html)
    {
        $hiddenFields = wp_nonce_field(self::PREVIEW_NONCE_ACTION, self::PREVIEW_NONCE_NAME, true, false)
            . '<input type="hidden" name="wpacu_debug_submit" value="1" />';

        $html = preg_replace('/(<form\\b[^>]*>)/i', '$1' . $hiddenFields, $html, 1);

        foreach (array_keys(self::getPreviewSwitches()) as $switchKey) {
            $html = str_replace(
                'name="' . $switchKey . '"',
                'name="wpacu_debug_options[' . $switchKey . ']"',
                $html
            );
            $html = str_replace(
                "name='" . $switchKey . "'",
                "name='wpacu_debug_options[" . $switchKey . "]'",
                $html
            );
        }

        $html = str_replace('name="wpacu_filter_plugins[]"', 'name="wpacu_debug_filter_plugins[]"', $html);
        $html = str_replace("name='wpacu_filter_plugins[]'", "name='wpacu_debug_filter_plugins[]'", $html);

        return $html;
    }

    /**
     * Explicit allowlist for request-scoped switches offered by the form.
     * Direct legacy URLs using these dedicated parameters continue to work.
     *
     * @return array
     */
    public static function getPreviewSwitches($evaluateRuntimeState = true)
    {
        $switches = array(
            'wpacu_no_css_unload' => array('key' => 'wpacu_no_css_unload', 'group' => 'css', 'label' => __('Ignore CSS unload rules', 'wp-asset-clean-up'), 'description' => __('Load stylesheets even when an Asset CleanUp unload rule matches.', 'wp-asset-clean-up')),
            'wpacu_no_css_minify' => array('key' => 'wpacu_no_css_minify', 'group' => 'css', 'label' => __('Disable CSS minification', 'wp-asset-clean-up'), 'description' => __('Serve CSS without Asset CleanUp minification for this preview.', 'wp-asset-clean-up')),
            'wpacu_no_css_combine' => array('key' => 'wpacu_no_css_combine', 'group' => 'css', 'label' => __('Disable CSS combining', 'wp-asset-clean-up'), 'description' => __('Do not combine loaded stylesheets in this preview.', 'wp-asset-clean-up')),
            'wpacu_no_css_preload_basic' => array('key' => 'wpacu_no_css_preload_basic', 'group' => 'css', 'label' => __('Disable basic CSS preloads', 'wp-asset-clean-up'), 'description' => __('Skip stylesheet preload hints added by Asset CleanUp.', 'wp-asset-clean-up')),
            'wpacu_no_css_preload_async' => array('key' => 'wpacu_no_css_preload_async', 'edition' => 'pro', 'group' => 'css', 'label' => __('Disable asynchronous CSS loading', 'wp-asset-clean-up'), 'description' => __('Do not apply the asynchronous stylesheet preload technique.', 'wp-asset-clean-up')),
            'wpacu_no_css_position_change' => array('key' => 'wpacu_no_css_position_change', 'edition' => 'pro', 'group' => 'css', 'label' => __('Disable CSS position changes', 'wp-asset-clean-up'), 'description' => __('Keep stylesheet tags in their original document positions.', 'wp-asset-clean-up')),
            'wpacu_skip_inline_css_files' => array('key' => 'wpacu_skip_inline_css_files', 'group' => 'css', 'label' => __('Disable CSS file inlining', 'wp-asset-clean-up'), 'description' => __('Keep eligible CSS files external instead of inlining them.', 'wp-asset-clean-up')),
            'wpacu_no_critical_css' => array('key' => 'wpacu_no_critical_css', 'group' => 'css', 'label' => __('Disable Critical CSS', 'wp-asset-clean-up'), 'description' => __('Skip Critical CSS output for this preview.', 'wp-asset-clean-up')),
            'wpacu_no_media_query_load_for_css' => array('key' => 'wpacu_no_media_query_load_for_css', 'edition' => 'pro', 'group' => 'css', 'label' => __('Disable CSS media-query loading rules', 'wp-asset-clean-up'), 'description' => __('Ignore conditional CSS loading based on media queries.', 'wp-asset-clean-up')),
            'wpacu_no_hd_css_unload' => array('key' => 'wpacu_no_hd_css_unload', 'edition' => 'pro', 'group' => 'css', 'label' => __('Disable hardcoded CSS unloading', 'wp-asset-clean-up'), 'description' => __('Keep hardcoded stylesheet tags that would otherwise be stripped.', 'wp-asset-clean-up')),

            'wpacu_no_js_unload' => array('key' => 'wpacu_no_js_unload', 'group' => 'javascript', 'label' => __('Ignore JS unload rules', 'wp-asset-clean-up'), 'description' => __('Load scripts even when an Asset CleanUp unload rule matches.', 'wp-asset-clean-up')),
            'wpacu_no_js_minify' => array('key' => 'wpacu_no_js_minify', 'group' => 'javascript', 'label' => __('Disable JS minification', 'wp-asset-clean-up'), 'description' => __('Serve JavaScript without Asset CleanUp minification for this preview.', 'wp-asset-clean-up')),
            'wpacu_no_js_combine' => array('key' => 'wpacu_no_js_combine', 'group' => 'javascript', 'label' => __('Disable JS combining', 'wp-asset-clean-up'), 'description' => __('Do not combine loaded scripts in this preview.', 'wp-asset-clean-up')),
            'wpacu_no_js_preload_basic' => array('key' => 'wpacu_no_js_preload_basic', 'group' => 'javascript', 'label' => __('Disable JS preloads', 'wp-asset-clean-up'), 'description' => __('Skip script preload hints added by Asset CleanUp.', 'wp-asset-clean-up')),
            'wpacu_no_async' => array('key' => 'wpacu_no_async', 'edition' => 'pro', 'group' => 'javascript', 'label' => __('Disable async', 'wp-asset-clean-up'), 'description' => __('Do not apply the async attribute through Asset CleanUp.', 'wp-asset-clean-up')),
            'wpacu_no_defer' => array('key' => 'wpacu_no_defer', 'edition' => 'pro', 'group' => 'javascript', 'label' => __('Disable defer', 'wp-asset-clean-up'), 'description' => __('Do not apply the defer attribute through Asset CleanUp.', 'wp-asset-clean-up')),
            'wpacu_no_js_position_change' => array('key' => 'wpacu_no_js_position_change', 'edition' => 'pro', 'group' => 'javascript', 'label' => __('Disable JS position changes', 'wp-asset-clean-up'), 'description' => __('Keep script tags in their original document positions.', 'wp-asset-clean-up')),
            'wpacu_skip_inline_js_files' => array('key' => 'wpacu_skip_inline_js_files', 'group' => 'javascript', 'label' => __('Disable JS file inlining', 'wp-asset-clean-up'), 'description' => __('Keep eligible JavaScript files external instead of inlining them.', 'wp-asset-clean-up')),
            'wpacu_no_media_query_load_for_js' => array('key' => 'wpacu_no_media_query_load_for_js', 'edition' => 'pro', 'group' => 'javascript', 'label' => __('Disable JS media-query loading rules', 'wp-asset-clean-up'), 'description' => __('Ignore conditional JavaScript loading based on media queries.', 'wp-asset-clean-up')),
            'wpacu_no_hd_js_unload' => array('key' => 'wpacu_no_hd_js_unload', 'edition' => 'pro', 'group' => 'javascript', 'label' => __('Disable hardcoded JS unloading', 'wp-asset-clean-up'), 'description' => __('Keep hardcoded script tags that would otherwise be stripped.', 'wp-asset-clean-up')),

            'wpacu_no_html_changes' => array('key' => 'wpacu_no_html_changes', 'group' => 'other', 'label' => __('Disable HTML source alterations', 'wp-asset-clean-up'), 'description' => __('Skip HTML processing, including CSS/JS replacement and combination. Normal WordPress dequeue rules still apply; unload operations that modify HTML are skipped. This temporary debugging interface remains visible.', 'wp-asset-clean-up')),
            'wpacu_no_plugin_unload' => array('key' => 'wpacu_no_plugin_unload', 'edition' => 'pro', 'group' => 'other', 'label' => __('Ignore Plugins Manager unload rules', 'wp-asset-clean-up'), 'description' => __('Load plugins normally instead of applying matching frontend unload rules.', 'wp-asset-clean-up')),
            'wpacu_no_cache' => array('key' => 'wpacu_no_cache', 'group' => 'other', 'label' => __('Bypass optimized-file cache', 'wp-asset-clean-up'), 'description' => __('Regenerate the request without reusing Asset CleanUp optimized-file mappings.', 'wp-asset-clean-up')),
            'wpacu_show_handle_names' => array('key' => 'wpacu_show_handle_names', 'group' => 'other', 'label' => __('Show asset handle names', 'wp-asset-clean-up'), 'description' => __('Add diagnostic handle-name markers to the HTML output.', 'wp-asset-clean-up')),
            'wpacu_load_original' => array('key' => 'wpacu_load_original', 'group' => 'other', 'label' => __('Load original asset URLs', 'wp-asset-clean-up'), 'description' => __('Prefer original CSS and JavaScript sources for comparison.', 'wp-asset-clean-up')),
            'wpacu_no_frontend_show' => array('key' => 'wpacu_no_frontend_show', 'group' => 'other', 'label' => __('Hide the frontend CSS/JS Manager', 'wp-asset-clean-up'), 'description' => __('Do not print the regular CSS/JS management list in the preview.', 'wp-asset-clean-up')),
            'wpacu_no_admin_bar' => array('key' => 'wpacu_no_admin_bar', 'group' => 'other', 'label' => __('Hide the WordPress admin bar', 'wp-asset-clean-up'), 'description' => __('Make the preview closer to the visitor-facing viewport.', 'wp-asset-clean-up'))
        );

        $isProEdition = is_dir(dirname(dirname(__FILE__)) . '/pro');
        if ( ! $isProEdition ) {
            foreach ($switches as $switchKey => $switchData) {
                if (isset($switchData['edition']) && $switchData['edition'] === 'pro') {
                    unset($switches[$switchKey]);
                }
            }
        }

        // Token hydration runs while active plugins are still loading, before
        // WordPress loads pluggable.php. At that stage only the allowlist is
        // needed; runtime checks such as is_admin_bar_showing() are not safe.
        if ( ! $evaluateRuntimeState || ! function_exists('is_user_logged_in') ) {
            return $switches;
        }

        if (isset(Main::instance()->settings['critical_css_status'])
            && Main::instance()->settings['critical_css_status'] === 'off'
            && isset($switches['wpacu_no_critical_css'])) {
            $switches['wpacu_no_critical_css']['disabled'] = true;
            $switches['wpacu_no_critical_css']['checked'] = true;
            $switches['wpacu_no_critical_css']['notice'] = __('Disabled globally', 'wp-asset-clean-up');
            $switches['wpacu_no_critical_css']['description'] = __('Critical CSS is globally disabled, so there is no Critical CSS output to disable in this preview.', 'wp-asset-clean-up');
        }

        $globallyControlledSwitches = array(
            'wpacu_no_css_minify' => array('setting' => 'minify_loaded_css', 'feature' => __('CSS minification', 'wp-asset-clean-up')),
            'wpacu_no_css_combine' => array('setting' => 'combine_loaded_css', 'feature' => __('CSS combining', 'wp-asset-clean-up')),
            'wpacu_skip_inline_css_files' => array('setting' => 'inline_css_files', 'feature' => __('CSS file inlining', 'wp-asset-clean-up')),
            'wpacu_no_js_minify' => array('setting' => 'minify_loaded_js', 'feature' => __('JS minification', 'wp-asset-clean-up')),
            'wpacu_no_js_combine' => array('setting' => 'combine_loaded_js', 'feature' => __('JS combining', 'wp-asset-clean-up')),
            'wpacu_skip_inline_js_files' => array('setting' => 'inline_js_files', 'feature' => __('JS file inlining', 'wp-asset-clean-up'))
        );

        foreach ($globallyControlledSwitches as $switchKey => $featureData) {
            $settingKey = $featureData['setting'];
            if (isset($switches[$switchKey])
                && empty(Main::instance()->settings[$settingKey])) {
                $switches[$switchKey]['disabled'] = true;
                $switches[$switchKey]['checked'] = true;
                $switches[$switchKey]['notice'] = __('Disabled globally', 'wp-asset-clean-up');
                $switches[$switchKey]['description'] = sprintf(
                    __('%s is disabled globally. Unchecking Page Options will not enable it; enable the feature in the plugin settings first.', 'wp-asset-clean-up'),
                    $featureData['feature']
                );
            }
        }

        $ruleControlledSwitches = array(
            'wpacu_no_css_preload_basic'        => array('state' => 'css_preload_basic', 'feature' => __('basic CSS preload', 'wp-asset-clean-up')),
            'wpacu_no_css_preload_async'        => array('state' => 'css_preload_async', 'feature' => __('asynchronous CSS loading', 'wp-asset-clean-up')),
            'wpacu_no_css_position_change'      => array('state' => 'css_position', 'feature' => __('CSS position-change', 'wp-asset-clean-up')),
            'wpacu_no_media_query_load_for_css' => array('state' => 'css_media_query', 'feature' => __('CSS media-query loading', 'wp-asset-clean-up')),
            'wpacu_no_hd_css_unload'            => array('state' => 'hardcoded_css_unload', 'feature' => __('hardcoded CSS unload', 'wp-asset-clean-up')),
            'wpacu_no_js_preload_basic'         => array('state' => 'js_preload_basic', 'feature' => __('JS preload', 'wp-asset-clean-up')),
            'wpacu_no_async'                    => array('state' => 'script_async', 'feature' => __('async attribute', 'wp-asset-clean-up')),
            'wpacu_no_defer'                    => array('state' => 'script_defer', 'feature' => __('defer attribute', 'wp-asset-clean-up')),
            'wpacu_no_js_position_change'       => array('state' => 'js_position', 'feature' => __('JS position-change', 'wp-asset-clean-up')),
            'wpacu_no_media_query_load_for_js'  => array('state' => 'js_media_query', 'feature' => __('JS media-query loading', 'wp-asset-clean-up')),
            'wpacu_no_hd_js_unload'             => array('state' => 'hardcoded_js_unload', 'feature' => __('hardcoded JS unload', 'wp-asset-clean-up'))
        );
        $savedRuleStates = DebugRuleState::getAll();

        foreach ($ruleControlledSwitches as $switchKey => $featureData) {
            if (isset($switches[$switchKey])
                && ! isset($_REQUEST[$switchKey])
                && empty($savedRuleStates[$featureData['state']])) {
                $switches[$switchKey]['disabled'] = true;
                $switches[$switchKey]['checked'] = true;
                $switches[$switchKey]['notice'] = __('No rules configured', 'wp-asset-clean-up');
                $switches[$switchKey]['description'] = sprintf(
                    __('No %s rules are currently configured, so this option has no effect on the preview.', 'wp-asset-clean-up'),
                    $featureData['feature']
                );
            }
        }

        if ( ! isset($_REQUEST['wpacu_no_frontend_show'])
            && isset($switches['wpacu_no_frontend_show'])
            && ! Main::showAssetsManagerInFrontend()) {
            $switches['wpacu_no_frontend_show']['disabled'] = true;
            $switches['wpacu_no_frontend_show']['checked'] = true;
            $switches['wpacu_no_frontend_show']['notice'] = __('Already hidden', 'wp-asset-clean-up');
            $switches['wpacu_no_frontend_show']['description'] = __('The frontend CSS/JS Manager is already hidden by its settings or by the conditions of this request.', 'wp-asset-clean-up');
        }

        if ( ! isset($_REQUEST['wpacu_no_admin_bar'])
            && isset($switches['wpacu_no_admin_bar'])
            && function_exists('is_admin_bar_showing')
            && ! is_admin_bar_showing()) {
            $switches['wpacu_no_admin_bar']['disabled'] = true;
            $switches['wpacu_no_admin_bar']['checked'] = true;
            $switches['wpacu_no_admin_bar']['notice'] = __('Already hidden', 'wp-asset-clean-up');
            $switches['wpacu_no_admin_bar']['description'] = __('The WordPress admin bar is already hidden for this request, so there is no admin bar to hide in the preview.', 'wp-asset-clean-up');
        }

        $filteredSwitches = apply_filters('wpacu_debug_preview_switches', $switches);

        return is_array($filteredSwitches) ? $filteredSwitches : $switches;
    }

    /**
     * Convert the nonce-protected POST into a short-lived preview token.
     * Raw form fields are never consumed by the early MU-plugin filter.
     *
     * @return void
     */
    public function handlePreviewFormSubmission()
    {
        if (strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') !== 'POST'
            || empty($_POST['wpacu_debug_submit'])) {
            return;
        }

        if ( ! Menu::userCanAccessPlugin() ) {
            wp_die(esc_html__('You are not allowed to use Asset CleanUp debugging tools.', 'wp-asset-clean-up'), '', array('response' => 403));
        }

        $nonce = isset($_POST[self::PREVIEW_NONCE_NAME]) && is_string($_POST[self::PREVIEW_NONCE_NAME])
            ? sanitize_text_field(wp_unslash($_POST[self::PREVIEW_NONCE_NAME]))
            : '';

        if ($nonce === '' || ! wp_verify_nonce($nonce, self::PREVIEW_NONCE_ACTION)) {
            wp_die(esc_html__('The Asset CleanUp debugging security check failed. Reload the debugging page and try again.', 'wp-asset-clean-up'), '', array('response' => 403));
        }

        $submittedOptions = isset($_POST['wpacu_debug_options']) && is_array($_POST['wpacu_debug_options'])
            ? wp_unslash($_POST['wpacu_debug_options'])
            : array();
        $supportedSwitches = self::getPreviewSwitches();
        $selectedSwitches = array();

        foreach ($submittedOptions as $submittedKey => $submittedValue) {
            $submittedKey = is_string($submittedKey) ? sanitize_key($submittedKey) : '';
            if ($submittedKey !== '' && isset($supportedSwitches[$submittedKey])) {
                $selectedSwitches[] = $submittedKey;
            }
        }

        $previewPageOptions = isset($_POST['wpacu_debug_page_options_present'])
            ? self::sanitizePreviewPageOptions(isset($_POST['wpacu_debug_page_options']) ? wp_unslash($_POST['wpacu_debug_page_options']) : array())
            : null;
        $submittedPlugins = isset($_POST['wpacu_debug_filter_plugins']) && is_array($_POST['wpacu_debug_filter_plugins'])
            ? wp_unslash($_POST['wpacu_debug_filter_plugins'])
            : array();
        $pluginsManagerPluginsRaw = isset($GLOBALS['wpacu_debug_preview_payload']['plugins_manager_plugins'])
            && is_array($GLOBALS['wpacu_debug_preview_payload']['plugins_manager_plugins'])
            ? $GLOBALS['wpacu_debug_preview_payload']['plugins_manager_plugins']
            : (isset($GLOBALS['wpacu_filtered_plugins']) && is_array($GLOBALS['wpacu_filtered_plugins'])
                ? $GLOBALS['wpacu_filtered_plugins']
                : array());
        $pluginsManagerPlugins = self::sanitizeKnownPluginPaths($pluginsManagerPluginsRaw);
        $previousPreviewPlugins = isset($GLOBALS['wpacu_debug_preview_payload']['plugins'])
            && is_array($GLOBALS['wpacu_debug_preview_payload']['plugins'])
            ? self::sanitizeKnownPluginPaths($GLOBALS['wpacu_debug_preview_payload']['plugins'])
            : array();
        $serverValidatedFilteredPlugins = isset($GLOBALS['wpacu_filtered_plugins'])
            && is_array($GLOBALS['wpacu_filtered_plugins'])
            ? self::sanitizeKnownPluginPaths($GLOBALS['wpacu_filtered_plugins'])
            : array();
        $allowedFilteredPlugins = array_values(array_unique(array_merge(
            $pluginsManagerPlugins,
            $previousPreviewPlugins,
            $serverValidatedFilteredPlugins
        )));
        $selectedPlugins = self::sanitizePreviewPluginSelection($submittedPlugins, $allowedFilteredPlugins);
        $sessionFingerprint = self::getPreviewSessionFingerprint();

        if ($sessionFingerprint === '') {
            wp_die(esc_html__('A valid logged-in WordPress session is required for this debugging preview.', 'wp-asset-clean-up'), '', array('response' => 403));
        }

        $token = strtolower(wp_generate_password(32, false, false));
        $payload = array(
            'version'             => 1,
            'created_at'          => time(),
            'expires_at'          => time() + self::PREVIEW_TOKEN_TTL,
            'user_id'             => get_current_user_id(),
            'session_fingerprint' => $sessionFingerprint,
            'target_path'         => self::getCurrentRequestPath(),
            'switches'            => array_values(array_unique($selectedSwitches)),
            'plugins'             => array_values(array_unique($selectedPlugins)),
            'plugins_manager_plugins' => array_values(array_unique($pluginsManagerPlugins))
        );

        if ($previewPageOptions !== null) {
            $payload['page_options'] = $previewPageOptions;
        }
        set_transient(self::PREVIEW_TRANSIENT_PREFIX . $token, $payload, self::PREVIEW_TOKEN_TTL);

        $redirectUrl = self::buildPreviewRedirectUrl($token, $selectedSwitches);
        if (! empty($previewPageOptions['no_wpacu_load'])) {
            $redirectUrl = add_query_arg('wpacu_no_load', '1', $redirectUrl);
        }
        wp_safe_redirect($redirectUrl, 303);
        exit;
    }

    /**
     * Hydrate only allowlisted dedicated switches from a validated token.
     *
     * @return void
     */
    public static function hydratePreviewRequestFromToken()
    {
        $payload = self::getValidatedPreviewPayload();
        if (empty($payload)) {
            return;
        }

        $supportedSwitches = self::getPreviewSwitches(false);
        foreach ((array)$payload['switches'] as $switchKey) {
            if (is_string($switchKey) && isset($supportedSwitches[$switchKey])) {
                $_GET[$switchKey] = '1';
                $_REQUEST[$switchKey] = '1';
            }
        }

        $GLOBALS['wpacu_debug_preview_payload'] = $payload;
        if (isset($payload['page_options']) && is_array($payload['page_options'])) {
            $GLOBALS['wpacu_debug_page_options'] = self::sanitizePreviewPageOptions($payload['page_options']);
        }

        if ( ! defined('DONOTCACHEPAGE') ) {
            define('DONOTCACHEPAGE', true);
        }
    }

    /**
     * @return array|false
     */
    public static function getValidatedPreviewPayload()
    {
        $token = isset($_GET[self::PREVIEW_TOKEN_QUERY_KEY]) && is_string($_GET[self::PREVIEW_TOKEN_QUERY_KEY])
            ? strtolower(stripslashes($_GET[self::PREVIEW_TOKEN_QUERY_KEY]))
            : '';

        if ($token === '' || ! preg_match('/^[a-z0-9]{32}$/', $token)) {
            return false;
        }

        $payload = get_transient(self::PREVIEW_TRANSIENT_PREFIX . $token);
        if ( ! self::isPreviewPayloadValid($payload) ) {
            return false;
        }

        return $payload;
    }

    /**
     * @param mixed $payload
     *
     * @return bool
     */
    private static function isPreviewPayloadValid($payload)
    {
        if ( ! is_array($payload)
            || empty($payload['expires_at'])
            || (int)$payload['expires_at'] < time()
            || empty($payload['session_fingerprint'])
            || ! is_string($payload['session_fingerprint'])
            || empty($payload['target_path'])
            || ! is_string($payload['target_path'])) {
            return false;
        }

        $currentFingerprint = self::getPreviewSessionFingerprint();
        if ($currentFingerprint === '' || ! hash_equals($payload['session_fingerprint'], $currentFingerprint)) {
            return false;
        }

        return hash_equals($payload['target_path'], self::getCurrentRequestPath());
    }

    /**
     * @return string
     */
    public static function getPreviewSessionFingerprint()
    {
        if ( ! defined('LOGGED_IN_COOKIE') || empty($_COOKIE[LOGGED_IN_COOKIE]) || ! is_string($_COOKIE[LOGGED_IN_COOKIE])) {
            return '';
        }

        $salt = defined('NONCE_SALT') ? NONCE_SALT : (defined('AUTH_SALT') ? AUTH_SALT : 'wpacu-debug-preview');

        return hash_hmac('sha256', stripslashes($_COOKIE[LOGGED_IN_COOKIE]), $salt);
    }

    /**
     * @return string
     */
    public static function getCurrentRequestPath()
    {
        $requestUri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
            ? stripslashes($_SERVER['REQUEST_URI'])
            : '/';
        $path = parse_url($requestUri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    /**
     * @param string $token
     * @param array  $selectedSwitches
     *
     * @return string
     */
    private static function buildPreviewRedirectUrl($token, $selectedSwitches)
    {
        $requestUri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
            ? wp_unslash($_SERVER['REQUEST_URI'])
            : '/';
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST'])
            ? preg_replace('/[^A-Za-z0-9.\\-:\\[\\]]/', '', wp_unslash($_SERVER['HTTP_HOST']))
            : parse_url(home_url('/'), PHP_URL_HOST);
        $currentUrl = $scheme . $host . $requestUri;
        $removeArgs = array_merge(
            array_keys(self::getPreviewSwitches()),
            array(
                self::PREVIEW_TOKEN_QUERY_KEY,
                self::PREVIEW_NONCE_NAME,
                'wpacu_debug_submit',
                'wpacu_debug_options',
                'wpacu_debug_filter_plugins',
                'wpacu_filter_plugins',
                'wpacu_only_load_plugins',
                'wpacu_settings',
                'wpacu_settings_query_nonce'
            )
        );
        $currentUrl = remove_query_arg($removeArgs, $currentUrl);
        $previewArgs = array(
            'wpacu_debug' => '1',
            self::PREVIEW_TOKEN_QUERY_KEY => $token
        );
        $supportedSwitches = self::getPreviewSwitches();
        foreach ((array)$selectedSwitches as $switchKey) {
            if (is_string($switchKey) && isset($supportedSwitches[$switchKey])) {
                $previewArgs[$switchKey] = '1';
            }
        }
        $currentUrl = add_query_arg($previewArgs, $currentUrl);

        return wp_validate_redirect($currentUrl, home_url('/'));
    }

    /**
     * @param array $submittedPlugins
     * @param array $allowedFilteredPlugins Server-validated paths removed from active_plugins earlier in this request.
     *
     * @return array
     */
    private static function sanitizePreviewPluginSelection($submittedPlugins, $allowedFilteredPlugins = array())
    {
        if (empty($submittedPlugins) || ! is_array($submittedPlugins)) {
            return array();
        }

        if ( ! function_exists('get_plugins') ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $knownPlugins = get_plugins();
        $activePlugins = (array)get_option('active_plugins', array());
        if (is_multisite()) {
            $activePlugins = array_merge($activePlugins, array_keys((array)get_site_option('active_sitewide_plugins', array())));
        }
        $activePlugins = array_unique(array_map('strval', $activePlugins));
        $allowedFilteredPlugins = array_unique(array_map('strval', (array)$allowedFilteredPlugins));
        $currentPluginDir = dirname(plugin_basename(dirname(dirname(__FILE__)) . '/index.php'));
        $cleanPlugins = array();

        foreach ($submittedPlugins as $submittedPlugin) {
            if ( ! is_string($submittedPlugin) ) {
                continue;
            }

            $pluginPath = plugin_basename(sanitize_text_field($submittedPlugin));
            if ($pluginPath === ''
                || ! isset($knownPlugins[$pluginPath])
                || ( ! in_array($pluginPath, $activePlugins, true)
                    && ! in_array($pluginPath, $allowedFilteredPlugins, true))
                || strpos($pluginPath, $currentPluginDir . '/') === 0) {
                continue;
            }

            $cleanPlugins[] = $pluginPath;
        }

        return $cleanPlugins;
    }

    /**
     * Validate server-derived plugin paths without requiring them to remain in
     * the already-filtered active_plugins option.
     *
     * @param array $pluginPaths
     *
     * @return array
     */
    private static function sanitizeKnownPluginPaths($pluginPaths)
    {
        if (empty($pluginPaths) || ! is_array($pluginPaths)) {
            return array();
        }

        if ( ! function_exists('get_plugins') ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $knownPlugins = get_plugins();
        $currentPluginDir = dirname(plugin_basename(dirname(dirname(__FILE__)) . '/index.php'));
        $cleanPlugins = array();

        foreach ($pluginPaths as $pluginPath) {
            if ( ! is_string($pluginPath)) {
                continue;
            }

            $pluginPath = plugin_basename($pluginPath);
            if ($pluginPath !== ''
                && isset($knownPlugins[$pluginPath])
                && strpos($pluginPath, $currentPluginDir . '/') !== 0) {
                $cleanPlugins[] = $pluginPath;
            }
        }

        return array_values(array_unique($cleanPlugins));
    }

    }
