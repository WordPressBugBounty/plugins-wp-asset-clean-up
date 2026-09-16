<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\Main;
use WpAssetCleanUp\Menu;
use WpAssetCleanUp\OptimiseAssets\FontsGoogleLocalCache;
use WpAssetCleanUp\OptimiseAssets\FontsGoogleLocalRegistry;
use WpAssetCleanUp\OptimiseAssets\FontsGoogleRemove;

/**
 * Authenticated administrator workflow for the shared Google Fonts local cache.
 */
class FontsGoogleLocalAdmin
{
    const MAX_KEY_PAGE_SCAN_URLS = 30;
    const AJAX_ACTION = 'wpassetcleanup_google_fonts_local_manage';
    const NONCE_ACTION = 'wpacu_google_fonts_local_manage';
    const MANUAL_RETRY_AFTER = 3600;

    /**
     * @return void
     */
    public static function registerAdminHooks()
    {
        add_action('wp_ajax_' . self::AJAX_ACTION, array(__CLASS__, 'ajaxManage'));
    }

    /**
     * Build a bounded, presentation-ready snapshot for the Settings page.
     * Values remain unescaped here because every output context must escape them
     * according to whether it is text, an attribute or JSON.
     *
     * @return array
     */
    public static function getAdminConfig()
    {
        $entries = array();
        $entriesByKey = array();
        $keys = array(
            'pending'    => array(),
            'processing' => array(),
            'error'      => array(),
            'ready'      => array(),
            'outdated'   => array(),
        );

        foreach (FontsGoogleLocalRegistry::getEntries() as $key => $entry) {
            if (! is_string($key) || ! preg_match('/^[a-f0-9]{64}$/', $key) || ! is_array($entry)) {
                continue;
            }

            $status = isset($entry['status']) && in_array($entry['status'], array('pending', 'processing', 'ready', 'error'), true)
                ? $entry['status']
                : 'pending';

            $paths = array();
            if (! empty($entry['paths']) && is_array($entry['paths'])) {
                foreach (array_slice($entry['paths'], 0, FontsGoogleLocalRegistry::MAX_PATH_SAMPLES) as $path) {
                    if (! is_scalar($path)) {
                        continue;
                    }

                    $path = self::cleanPathForDisplay((string) $path);
                    if ($path !== '' && ! in_array($path, $paths, true)) {
                        $paths[] = $path;
                    }
                }
            }

            $row = array(
                'key'          => $key,
                'url'          => isset($entry['url']) ? self::cleanSingleLine($entry['url'], 10000) : '',
                'status'       => $status,
                'source'       => isset($entry['source']) ? self::cleanSingleLine($entry['source'], 20) : '',
                'paths'        => $paths,
                'attempts'     => isset($entry['attempts']) ? max(0, (int) $entry['attempts']) : 0,
                'firstSeen'    => isset($entry['first_seen']) ? max(0, (int) $entry['first_seen']) : 0,
                'lastSeen'     => isset($entry['last_seen']) ? max(0, (int) $entry['last_seen']) : 0,
                'processedAt'  => isset($entry['processed_at']) ? max(0, (int) $entry['processed_at']) : 0,
                'nextRetryAt'  => isset($entry['next_retry_at']) ? max(0, (int) $entry['next_retry_at']) : 0,
                'lastError'    => isset($entry['last_error']) ? self::cleanSingleLine($entry['last_error'], 1000) : '',
                'localCssUrl'  => isset($entry['local_css_url']) ? self::cleanSingleLine($entry['local_css_url'], 10000) : '',
                'fontCount'    => isset($entry['font_count']) ? max(0, (int) $entry['font_count']) : 0,
                'totalBytes'   => isset($entry['total_bytes']) ? max(0, (int) $entry['total_bytes']) : 0,
            );

            $entries[] = $row;
            $entriesByKey[$key] = $row;
            $keys[$status][] = $key;

            if ($status === 'ready' && FontsGoogleLocalCache::readyEntryNeedsRefresh($entry)) {
                $keys['outdated'][] = $key;
            }
        }

        $config = array(
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'action'       => self::AJAX_ACTION,
            'nonce'        => wp_create_nonce(self::NONCE_ACTION),
            'edition'      => 'lite',
            'enabled'      => ! empty(Main::instance()->settings['google_fonts_local']),
            'automaticProcessing' => true,
            'warmupUrls'   => self::getWarmupUrls(),
            'summary'      => FontsGoogleLocalRegistry::getSummary(),
            'entries'      => $entries,
            'entriesByKey' => $entriesByKey,
            'keys'         => $keys,
            'specificInventory' => self::buildSpecificInventoryPayload(),
            'labels'       => array(
                'processing' => __('Processing local Google Fonts…', 'wp-asset-clean-up'),
                'discovering' => __('Discovering Google Fonts on representative pages…', 'wp-asset-clean-up'),
                'warmupComplete' => __('Discovery finished. Local copies were prepared.', 'wp-asset-clean-up'),
                'waitingToSave' => __('Finishing the current operation before saving changes…', 'wp-asset-clean-up'),
                'waitingToSaveDetails' => __('Saving is waiting for the Google Fonts operation to finish. Your changes will be saved automatically.', 'wp-asset-clean-up'),
                'complete'   => __('The local Google Fonts operation finished.', 'wp-asset-clean-up'),
                'failed'     => __('The operation could not be completed.', 'wp-asset-clean-up'),
                'network'      => __('The request failed. Check the browser console and try again.', 'wp-asset-clean-up'),
                'confirmReset' => __('Reset the active local Google Fonts copies? New pages will use remote Google URLs until configurations are rediscovered and processed. Existing files will remain available for cached static HTML until the configured retention period expires.', 'wp-asset-clean-up'),
                'confirmClear' => __('Delete every local Google Fonts file immediately? Cached HTML or CSS may still reference deleted files, so use this only when page/CDN caches are disabled or will be purged now. The inventory will remain empty until you visit public pages or run Scan key pages.', 'wp-asset-clean-up'),
                'emptyInventory' => __('No Google Fonts stylesheet has been recorded yet.', 'wp-asset-clean-up'),
                'inventoryOne'   => __('%s discovered configuration', 'wp-asset-clean-up'),
                'inventoryMany'  => __('%s discovered configurations', 'wp-asset-clean-up'),
                'statusReady'    => __('Ready', 'wp-asset-clean-up'),
                'statusPending'  => __('Pending', 'wp-asset-clean-up'),
                'statusProcessing' => __('Processing', 'wp-asset-clean-up'),
                'statusError'    => __('Needs attention', 'wp-asset-clean-up'),
                'attempts'       => __('Attempts: %s', 'wp-asset-clean-up'),
                'openCss'        => __('Open CSS', 'wp-asset-clean-up'),
                'statusColumn'   => __('Status', 'wp-asset-clean-up'),
                'configurationColumn' => __('Configuration', 'wp-asset-clean-up'),
                'seenOnColumn'   => __('Seen on', 'wp-asset-clean-up'),
                'localCopyColumn' => __('Local copy', 'wp-asset-clean-up'),
            ),
        );

        $filteredConfig = apply_filters('wpacu_google_fonts_local_admin_config', $config);
        return is_array($filteredConfig) ? $filteredConfig : $config;
    }

    /**
     * Return a small, deduplicated set of public pages that represents the
     * site's most common content types during local-font discovery.
     *
     * @return array
     */
    public static function getWarmupUrls()
    {
        $urls = array(home_url('/'));

        if (get_option('show_on_front') === 'page') {
            foreach (array('page_on_front', 'page_for_posts') as $pageOption) {
                $pageId = (int) get_option($pageOption);
                if ($pageId > 0) {
                    $urls[] = get_permalink($pageId);
                }
            }
        }

        foreach (array('page', 'post', 'product') as $postType) {
            if (! post_type_exists($postType)) {
                continue;
            }

            $postIds = get_posts(array(
                'post_type'              => $postType,
                'post_status'            => 'publish',
                'posts_per_page'         => 1,
                'orderby'                => 'date',
                'order'                  => 'DESC',
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ));

            if (! empty($postIds[0])) {
                $urls[] = get_permalink($postIds[0]);
            }

            if ($postType !== 'page' && function_exists('get_post_type_archive_link')) {
                $urls[] = get_post_type_archive_link($postType);
            }
        }

        if (function_exists('wc_get_page_permalink')) {
            $urls[] = wc_get_page_permalink('shop');
        }

        $urls = array_values(array_unique(array_filter($urls, 'is_string')));
        return array_slice($urls, 0, self::MAX_KEY_PAGE_SCAN_URLS);
    }

    /**
     * Process exactly one requested administrator operation.
     *
     * @return void
     */
    public static function ajaxManage()
    {
        if (! Menu::userCanAccessPlugin()) {
            wp_send_json_error(array(
                'message' => __('You are not allowed to manage the local Google Fonts cache.', 'wp-asset-clean-up'),
            ), 403);
        }

        if (! check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('The security check failed. Refresh the Settings page and try again.', 'wp-asset-clean-up'),
            ), 403);
        }

        $operation = isset($_POST['operation']) && is_scalar($_POST['operation'])
            ? sanitize_key(wp_unslash($_POST['operation']))
            : '';
        $key = isset($_POST['key']) && is_scalar($_POST['key'])
            ? strtolower(trim((string) wp_unslash($_POST['key'])))
            : '';

        if ($operation === 'snapshot') {
            wp_send_json_success(self::buildOperationPayload(array(
                'message' => __('The local Google Fonts inventory was refreshed.', 'wp-asset-clean-up'),
            )));
        }

        if ($operation === 'process_next') {
            $candidates = self::getKeysByStatus('pending', 1);

            if (empty($candidates)) {
                wp_send_json_success(self::buildOperationPayload(array(
                    'processed' => false,
                    'message'   => __('There are no pending Google Fonts configurations to process.', 'wp-asset-clean-up'),
                )));
            }

            $result = FontsGoogleLocalCache::process($candidates[0], false, self::MANUAL_RETRY_AFTER);
            wp_send_json_success(self::buildOperationPayload(array(
                'processed' => true,
                'key'       => $candidates[0],
                'result'    => $result,
                'message'   => ! empty($result['success'])
                    ? __('One Google Fonts configuration was hosted locally.', 'wp-asset-clean-up')
                    : self::getResultError($result),
            )));
        }

        if ($operation === 'retry') {
            $entry = self::getValidEntryForOperation($key, 'error');
            FontsGoogleLocalRegistry::markPending($key);
            $result = FontsGoogleLocalCache::process($key, false, self::MANUAL_RETRY_AFTER);

            wp_send_json_success(self::buildOperationPayload(array(
                'processed' => true,
                'key'       => $key,
                'result'    => $result,
                'message'   => ! empty($result['success'])
                    ? __('The failed Google Fonts configuration was processed.', 'wp-asset-clean-up')
                    : self::getResultError($result),
                'previousStatus' => $entry['status'],
            ), true));
        }

        if ($operation === 'refresh') {
            self::getValidEntryForOperation($key, 'ready');
            $result = FontsGoogleLocalCache::process($key, true, self::MANUAL_RETRY_AFTER);

            wp_send_json_success(self::buildOperationPayload(array(
                'processed' => true,
                'key'       => $key,
                'result'    => $result,
                'message'   => ! empty($result['success'])
                    ? __('The local Google Fonts configuration was refreshed.', 'wp-asset-clean-up')
                    : self::getResultError($result),
            )));
        }

        if ($operation === 'cleanup') {
            $removed = FontsGoogleLocalCache::cleanupOrphans();
            wp_send_json_success(self::buildOperationPayload(array(
                'removedCount' => is_array($removed) ? count($removed) : 0,
                'message'      => __('Unused local Google Fonts cache files were cleaned.', 'wp-asset-clean-up'),
            )));
        }

        if ($operation === 'reset') {
            $reset = FontsGoogleLocalCache::resetActiveCopies();
            wp_send_json_success(self::buildOperationPayload(array(
                'reset'   => (bool) $reset,
                'message' => __('Active local Google Fonts copies were reset. Legacy files will remain available until the generated-file retention period expires.', 'wp-asset-clean-up'),
            )));
        }

        if ($operation === 'clear') {
            $cleared = FontsGoogleLocalCache::clearAll();
            wp_send_json_success(self::buildOperationPayload(array(
                'cleared' => (bool) $cleared,
                'specificInventory' => self::buildEmptySpecificInventoryPayload(),
                'message' => $cleared
                    ? __('All local Google Fonts files and the discovery registry were deleted immediately.', 'wp-asset-clean-up')
                    : __('The local Google Fonts cache could not be cleared completely.', 'wp-asset-clean-up'),
            )));
        }

        wp_send_json_error(array(
            'message' => __('The requested local Google Fonts operation is not valid.', 'wp-asset-clean-up'),
        ), 400);
    }

    /**
     * @param string $status
     * @param int    $limit
     *
     * @return array
     */
    private static function getKeysByStatus($status, $limit)
    {
        $keys = array();
        $limit = max(1, min(FontsGoogleLocalRegistry::MAX_ITEMS, (int) $limit));

        foreach (FontsGoogleLocalRegistry::getEntries() as $key => $entry) {
            if (is_array($entry) && isset($entry['status']) && $entry['status'] === $status) {
                $keys[] = $key;

                if (count($keys) >= $limit) {
                    break;
                }
            }
        }

        return $keys;
    }

    /**
     * @param string $key
     * @param string $requiredStatus
     *
     * @return array
     */
    private static function getValidEntryForOperation($key, $requiredStatus)
    {
        if (! preg_match('/^[a-f0-9]{64}$/', (string) $key)) {
            wp_send_json_error(array(
                'message' => __('The selected Google Fonts configuration is not valid.', 'wp-asset-clean-up'),
            ), 400);
        }

        $entry = FontsGoogleLocalRegistry::getEntry($key);
        if (empty($entry) || ! isset($entry['status']) || $entry['status'] !== $requiredStatus) {
            wp_send_json_error(array(
                'message' => __('The selected Google Fonts configuration is no longer in the expected state.', 'wp-asset-clean-up'),
            ), 409);
        }

        return $entry;
    }

    /**
     * @param array $extra
     * @param bool  $retryAttempted
     *
     * @return array
     */
    private static function buildOperationPayload($extra, $retryAttempted = false)
    {
        $config = self::getAdminConfig();
        $payload = array(
            'summary'           => $config['summary'],
            'keys'              => $config['keys'],
            'entries'           => $config['entries'],
            'specificInventory' => self::buildSpecificInventoryPayload($retryAttempted),
            'hasMore'           => ! empty($config['keys']['pending']) || ! empty($config['keys']['processing']),
        );

        return array_merge($payload, is_array($extra) ? $extra : array());
    }

    /**
     * Project the server-side selective-removal inventory into the stable shape
     * consumed by the initial template and AJAX snapshots.
     *
     * @param bool $retryAttempted
     *
     * @return array
     */
    private static function buildSpecificInventoryPayload($retryAttempted = false)
    {
        $inventory = FontsGoogleRemove::getSpecificVariantInventoryByStatus();
        $selectedRuleMap = array();
        foreach (FontsGoogleRemove::getSpecificRules() as $selectedRule) {
            $selectedRuleMap[strtolower($selectedRule)] = true;
        }
        $payload = array(
            'ready'                    => array(),
            'failed'                   => array(),
            'failedConfigurationCount' => isset($inventory['failed_configuration_count'])
                ? max(0, (int) $inventory['failed_configuration_count'])
                : 0,
            'retryAttempted'           => (bool) $retryAttempted,
            'labels'                   => array(
                'readyVariants'       => __('Ready variants', 'wp-asset-clean-up'),
                'failedVariants'      => __('Failed variants', 'wp-asset-clean-up'),
                'retryFailed'         => __('Retry failed', 'wp-asset-clean-up'),
                'selectAll'           => __('Select all', 'wp-asset-clean-up'),
                'clearSelection'      => __('Clear selection', 'wp-asset-clean-up'),
                'regular'             => __('Regular', 'wp-asset-clean-up'),
                'italic'              => __('Italic', 'wp-asset-clean-up'),
                'deliveredByGoogle'   => __('Delivered by Google', 'wp-asset-clean-up'),
                'localCopy'           => __('Local copy', 'wp-asset-clean-up'),
                'previouslyDiscovered'=> __('Previously discovered', 'wp-asset-clean-up'),
                'emptyTitle'          => __('No Google Fonts variants have been discovered yet.', 'wp-asset-clean-up'),
                'emptyDescription'    => __('Visit the front-end pages that use Google Fonts, then return here. Detected stylesheet configurations are used to build this list.', 'wp-asset-clean-up'),
                'selectionCount'      => __('variants selected for removal', 'wp-asset-clean-up'),
                'familyOne'           => __('%d family detected', 'wp-asset-clean-up'),
                'familyMany'          => __('%d families detected', 'wp-asset-clean-up'),
                'variantOne'          => __('%d variant', 'wp-asset-clean-up'),
                'variantMany'         => __('%d variants', 'wp-asset-clean-up'),
                    'failedIntro'         => __('Some detected Google Fonts variants could not be loaded and may no longer be needed. Review them before deciding whether to keep or remove them.', 'wp-asset-clean-up'),
                    'failedAdvice'        => __('Retry completed, but some stylesheet URLs still failed. Open the Google APIs URLs to confirm they are valid, then select and remove any variants you no longer need.', 'wp-asset-clean-up'),
            ),
        );

        foreach (array('ready', 'failed') as $status) {
            if (empty($inventory[$status]) || ! is_array($inventory[$status])) {
                continue;
            }

            foreach ($inventory[$status] as $family => $variants) {
                if (! is_scalar($family) || ! is_array($variants)) {
                    continue;
                }

                $family = self::cleanSingleLine((string) $family, 1000);
                if ($family === '') {
                    continue;
                }

                $sources = array();
                $stylesheetUrls = array();
                if ($status === 'failed' && ! empty($inventory['failed_urls'][$family]) && is_array($inventory['failed_urls'][$family])) {
                    foreach ($inventory['failed_urls'][$family] as $stylesheetUrl) {
                        if (! is_string($stylesheetUrl)) {
                            continue;
                        }
                        $stylesheetUrl = trim($stylesheetUrl);
                        $stylesheetUrlParts = wp_parse_url($stylesheetUrl);
                        if (is_array($stylesheetUrlParts)
                            && isset($stylesheetUrlParts['scheme'], $stylesheetUrlParts['host'])
                            && strtolower($stylesheetUrlParts['scheme']) === 'https'
                            && rtrim(strtolower($stylesheetUrlParts['host']), '.') === 'fonts.googleapis.com'
                            && empty($stylesheetUrlParts['user']) && empty($stylesheetUrlParts['pass'])
                            && (! isset($stylesheetUrlParts['port']) || (int) $stylesheetUrlParts['port'] === 443)) {
                            $stylesheetUrls[$stylesheetUrl] = true;
                        }
                    }
                }
                $normalizedVariants = array();
                foreach ($variants as $token => $variant) {
                    if (! is_string($token) || ! is_array($variant)) {
                        continue;
                    }

                    $decodedToken = FontsGoogleRemove::decodeSpecificRule($token);
                    if (empty($decodedToken)) {
                        continue;
                    }

                    $weight = self::cleanSingleLine($decodedToken['weight'], 20);
                    $style = $decodedToken['style'] === 'italic' ? 'italic' : 'normal';
                    if ($weight === '') {
                        continue;
                    }

                    if (! empty($variant['sources']) && is_array($variant['sources'])) {
                        foreach ($variant['sources'] as $source) {
                            if ($source === 'local' || $source === 'remote') {
                                $sources[$source] = true;
                            }
                        }
                    }

                    $normalizedVariants[] = array(
                        'token'        => $token,
                        'weight'       => $weight,
                        'style'        => $style,
                        'availability' => isset($variant['availability']) && $variant['availability'] === 'previously_discovered'
                            ? 'previously_discovered'
                            : 'discovered',
                        'selected'     => isset($selectedRuleMap[strtolower($token)]),
                    );
                }

                if (! empty($normalizedVariants)) {
                    $payload[$status][] = array(
                        'family'   => $family,
                        'sources'  => array_values(array_keys($sources)),
                        'urls'     => array_values(array_keys($stylesheetUrls)),
                        'variants' => $normalizedVariants,
                    );
                }
            }
        }

        return $payload;
    }

    /**
     * Keep the complete Remove Specific payload schema after immediate cache
     * deletion without re-projecting saved-only rules into the empty inventory.
     *
     * @return array
     */
    private static function buildEmptySpecificInventoryPayload()
    {
        $payload = self::buildSpecificInventoryPayload();
        $payload['ready'] = array();
        $payload['failed'] = array();
        $payload['failedConfigurationCount'] = 0;
        $payload['retryAttempted'] = false;

        return $payload;
    }

    /**
     * @param array $result
     *
     * @return string
     */
    private static function getResultError($result)
    {
        if (is_array($result) && ! empty($result['error'])) {
            return self::cleanSingleLine($result['error'], 1000);
        }

        return __('The Google Fonts configuration could not be hosted locally.', 'wp-asset-clean-up');
    }

    /**
     * @param string $value
     * @param int    $maxLength
     *
     * @return string
     */
    private static function cleanSingleLine($value, $maxLength)
    {
        $value = preg_replace('/[\x00-\x1f\x7f]+/', ' ', (string) $value);
        $value = preg_replace('/\s+/', ' ', (string) $value);
        return substr(trim((string) $value), 0, max(0, (int) $maxLength));
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private static function cleanPathForDisplay($path)
    {
        $path = self::cleanSingleLine($path, 1000);
        $questionMarkPosition = strpos($path, '?');

        if ($questionMarkPosition !== false) {
            $path = substr($path, 0, $questionMarkPosition);
        }

        return $path;
    }
}
