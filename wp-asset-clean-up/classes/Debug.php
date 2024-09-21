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
	/**
	 * Debug constructor.
	 */
	public function __construct()
	{
		if ( isset($_GET['wpacu_debug']) && ! is_admin() ) {
            add_action('wp_footer', array($this, 'showDebugOptionsFront'), PHP_INT_MAX);

            }

		foreach( array('wp', 'admin_init') as $wpacuActionHook ) {
			add_action( $wpacuActionHook, static function() {
				if (isset( $_GET['wpacu_get_cache_dir_size'] ) && Menu::userCanAccessAssetCleanUp()) {
					self::printCacheDirInfo();
				}

				// For debugging purposes
				if (isset($_GET['wpacu_get_already_minified']) && Menu::userCanAccessAssetCleanUp()) {
                    echo '<pre>'; print_r(OptimizeCommon::getAlreadyMarkedAsMinified()); echo '</pre>';
                    exit();
                }

				if (isset($_GET['wpacu_remove_already_minified']) && Menu::userCanAccessAssetCleanUp()) {
					echo '<pre>'; OptimizeCommon::removeAlreadyMarkedAsMinified(); echo '</pre>';
					exit();
				}

				if (isset($_GET['wpacu_limit_already_minified']) && Menu::userCanAccessAssetCleanUp()) {
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

        $wpacuTimingFormatMs = str_replace('.00', '', number_format($wpacuExecTimingMs, 2));
        $wpacuTimingFormatS  = str_replace(array('.00', ','), '', number_format(($wpacuExecTimingMs / 1000), 3));

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
		$timingKeys = array(
			'prepare_optimize_files_css',
			'prepare_optimize_files_js',

			// All HTML alteration via "wp_loaded" action hook
			'alter_html_source',

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

			'style_loader_tag_pro_changes',
			'script_loader_tag_pro_changes',

            'all_timings'
		);

		foreach ( $timingKeys as $timingKey ) {
            $htmlSource = self::printTimingFor($timingKey, $htmlSource);
		}

		return $htmlSource;
	}

	/**
	 *
	 */
	public function showDebugOptionsFront()
	{
	    if (! Menu::userCanAccessAssetCleanUp()) {
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
                        <p>View the page with the following options <strong>disabled</strong> (for debugging purposes):</p>
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

                            <div>
                                <input type="submit"
                                       value="Preview this page with the changes made above" />
                            </div>
                            <input type="hidden" name="wpacu_debug" value="on" />
                        </form>
                    </td>
                    <td style="vertical-align: top;">
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
							<ul style="list-style: none; padding-left: 0;">
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
                                                <li>Preloads: {wpacu_alter_html_source_for_preload_css_exec_time}</li>

                                                <!-- -->

                                                <li>Combine: {wpacu_alter_html_source_for_combine_css_exec_time}</li>
                                                <li>Minify Inline Tags: {wpacu_alter_html_source_for_minify_inline_style_tags_exec_time}</li>
                                                <li>Unload (ignore dependencies): {wpacu_alter_html_source_unload_ignore_deps_css_exec_time}</li>
                                                <li>Alter Inline CSS (font-display): {wpacu_alter_html_source_for_local_fonts_display_style_inline_exec_time}</li>
                                                <li>Final Cleanups for the HTML source: {wpacu_alter_html_source_for_optimize_css_final_cleanups_exec_time}</li>
                                            </ul>
                                        </li>

                                        <li style="margin-top: 10px; margin-bottom: 10px;">OptimizeJs: {wpacu_alter_html_source_for_optimize_js_exec_time} ({wpacu_alter_html_source_for_optimize_js_exec_time_sec})
                                            <ul>
                                                <li>Update Original to Optimized: {wpacu_alter_html_source_original_to_optimized_js_exec_time}</li>
                                                <li>Preloads: {wpacu_alter_html_source_for_preload_js_exec_time}</li>
                                                <!-- -->

                                                <li>Combine: {wpacu_alter_html_source_for_combine_js_exec_time}</li>

                                                <li>Move jQuery within the BODY tag: {wpacu_alter_html_source_maybe_move_jquery_after_body_tag_exec_time}</li>
                                                <li>Unload (ignore dependencies): {wpacu_alter_html_source_unload_ignore_deps_js_exec_time}</li>
                                                <li>Move any inline with jQuery code after jQuery library: {wpacu_alter_html_source_move_inline_jquery_after_src_tag_exec_time}</li>
                                                <li>Final Cleanups for the HTML source: {wpacu_alter_html_source_for_optimize_js_final_cleanups_exec_time}</li>
                                            </ul>
                                        </li>

                                        <li>Strip any references for unloaded assets: {wpacu_alter_html_source_strip_any_references_for_unloaded_assets_exec_time}</li>

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

    }
