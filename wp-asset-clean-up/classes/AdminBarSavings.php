<?php
namespace WpAssetCleanUp;

/** Lazy, administrator-only estimates of unloaded CSS/JS response bodies. */
class AdminBarSavings
{
    private static $removed = array();

    /** Separate requested resources from rules targeting assets absent on this page. */
    public static function partitionUnloads($lists)
    {
        $groups = array('active' => array('styles' => array(), 'scripts' => array()), 'inactive' => array('styles' => array(), 'scripts' => array()));
        foreach (array('styles' => 'wpAllStyles', 'scripts' => 'wpAllScripts') as $type => $property) {
            $assets = Main::instance()->$property;
            $pending = isset($assets['queue']) ? $assets['queue'] : array();
            $queued = array();
            while ($pending) {
                $handle = array_pop($pending);
                if (isset($queued[$handle])) { continue; }
                $queued[$handle] = true;
                if (!empty($assets['registered'][$handle]->deps)) {
                    $pending = array_merge($pending, $assets['registered'][$handle]->deps);
                }
            }
            foreach (array_unique($lists[$type]) as $handle) {
                // Hardcoded tags use the HTML removal pipeline, not the WP queue.
                $active = isset($queued[$handle]) || strpos($handle, 'wpacu_hardcoded_') === 0;
                $groups[$active ? 'active' : 'inactive'][$type][] = $handle;
            }
        }
        return $groups;
    }

    public static function addInactiveMenu($bar, $lists)
    {
        $total = count($lists['styles']) + count($lists['scripts']);
        if (!$total) { return; }
        $explanation = __('These rules had no effect on this page because the targeted assets were not found in the page output or were no longer queued for loading. Their plugin or theme may have become inactive. They are excluded from estimated savings.', 'wp-asset-clean-up');
        $bar->add_menu(array('parent' => 'assetcleanup-parent', 'id' => 'assetcleanup-inactive-unload-rules',
            'title' => sprintf(_n('%d inactive unload rule on this page', '%d inactive unload rules on this page', $total, 'wp-asset-clean-up'), $total)
                . ' <span id="wpacu-inactive-help-slot" data-explanation="' . esc_attr($explanation) . '" data-label="' . esc_attr__('Why are these rules inactive?', 'wp-asset-clean-up') . '"></span>',
            'href' => '#'));
        foreach (array('styles' => 'CSS', 'scripts' => 'JavaScript') as $type => $label) {
            if (empty($lists[$type])) { continue; }
            $groupId = 'assetcleanup-inactive-unload-rules-' . ($type === 'styles' ? 'css' : 'js');
            sort($lists[$type]);
            $bar->add_menu(array('parent' => 'assetcleanup-inactive-unload-rules', 'id' => $groupId,
                'title' => esc_html($label . ' (' . count($lists[$type]) . ')'), 'href' => '#'));
            foreach ($lists[$type] as $index => $handle) {
                $bar->add_menu(array('parent' => $groupId, 'id' => 'assetcleanup-inactive-' . $type . '-' . $index,
                    'title' => esc_html($handle),
                    'href' => esc_url(admin_url('admin.php?page=wpassetcleanup_overview#wpacu-overview-' . ($type === 'styles' ? 'css-' : 'js-') . rawurlencode($handle)))));
            }
        }
        add_action('wp_after_admin_bar_render', static function () {
            wp_register_script('wpacu-admin-bar-inactive-help', plugins_url('assets/wpacu-admin-bar-inactive-help.min.js', WPACU_PLUGIN_FILE), array(), WPACU_PLUGIN_VERSION . '.' . filemtime(__DIR__ . '/../assets/wpacu-admin-bar-inactive-help.min.js'), true);
            wp_print_scripts('wpacu-admin-bar-inactive-help');
        });
    }

    /** Keep the original data in memory before WordPress deregisters the handle. */
    public static function capture($type, $handle, $asset, $inlineRemoved = true)
    {
        if (!is_admin_bar_showing() || !Menu::userCanAccessPlugin() || !is_object($asset)) { return; }
        self::$removed[$type][$handle] = clone $asset;
        if (!$inlineRemoved) { self::$removed[$type][$handle]->extra = array(); }
    }

    private static function inlineContent($type, $asset)
    {
        $parts = array();
        foreach ($type === 'styles' ? array('after') : array('data', 'before', 'after') as $key) {
            if (empty($asset->extra[$key])) { continue; }
            foreach ((array)$asset->extra[$key] as $part) {
                if (is_string($part) && $part !== '') { $parts[] = $part; }
            }
        }
        return implode("\n", $parts);
    }

    /** Hardcoded assets are saved as tags, not registered WP dependencies. */
    private static function savedAsset($type, $info)
    {
        $asset = (object)array('src' => isset($info['src']) && is_string($info['src']) ? $info['src'] : '', 'extra' => array());
        $output = isset($info['output']) && is_string($info['output']) ? $info['output'] : '';
        if ($output === '') { return $asset; }
        $tag = $type === 'styles' ? 'link' : 'script';
        $attribute = $type === 'styles' ? 'href' : 'src';
        if (preg_match('~<' . $tag . '\\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>~is', $output, $tagMatch)
            && preg_match('~\\s' . $attribute . '\\s*=\\s*(?:"([^"]*)"|\'([^\']*)\'|([^\\s>]+))~i', $tagMatch[1], $match)) {
            $asset->src = !empty($match[1]) ? $match[1] : (!empty($match[2]) ? $match[2] : (isset($match[3]) ? $match[3] : ''));
        }
        $inlineTag = $type === 'styles' ? 'style' : 'script';
        if ($asset->src === '' && preg_match('~<' . $inlineTag . '\\b[^>]*>(.*?)</' . $inlineTag . '\\s*>~is', $output, $match)) {
            $asset->extra['after'] = array($match[1]);
        }
        return $asset;
    }

    public static function measure($url)
    {
        $path = self::localPath($url);
        $key = WPACU_PLUGIN_ID . '_saving_' . md5($url . ($path ? '|' . filemtime($path) . '|' . filesize($path) : ''));
        $cached = get_transient($key);
        if (is_array($cached) && array_key_exists('bytes', $cached)) {
            return $cached['bytes'];
        }
        $bytes = false;
        if ($path) {
            if (filesize($path) <= 5 * MB_IN_BYTES && function_exists('gzencode')) {
                $body = file_get_contents($path, false, null, 0, 5 * MB_IN_BYTES + 1);
                if ($body !== false && strlen($body) <= 5 * MB_IN_BYTES) {
                    $compressed = gzencode($body, 6);
                    $bytes = $compressed === false ? false : strlen($compressed);
                }
            }
        } else {
            // Dynamic URLs and CDN assets use the same validated remote transport.
            $bytes = AssetsManager::getRemoteGzipSize($url);
        }
        set_transient($key, array('bytes' => $bytes), $bytes === false ? 5 * MINUTE_IN_SECONDS : HOUR_IN_SECONDS);
        return $bytes;
    }

    private static function localPath($url)
    {
        $mappings = array(site_url() => ABSPATH);
        if (defined('WP_CONTENT_DIR')) {
            $mappings = array(content_url() => WP_CONTENT_DIR) + $mappings;
        }
        $urlPath = wp_parse_url($url, PHP_URL_PATH);
        foreach ($mappings as $baseUrl => $root) {
            if (wp_parse_url($url, PHP_URL_HOST) !== wp_parse_url($baseUrl, PHP_URL_HOST)
                || wp_parse_url($url, PHP_URL_PORT) !== wp_parse_url($baseUrl, PHP_URL_PORT)) {
                continue;
            }
            $prefix = rtrim((string)wp_parse_url($baseUrl, PHP_URL_PATH), '/') . '/';
            if (strpos((string)$urlPath, $prefix) !== 0) { continue; }
            $relative = rawurldecode(substr($urlPath, strlen($prefix)));
            if (!preg_match('/\.(css|js)$/i', $relative)) { continue; }
            $rootPath = realpath($root);
            $file = realpath($root . '/' . $relative);
            if ($rootPath && $file && strpos($file, $rootPath . DIRECTORY_SEPARATOR) === 0 && is_file($file) && is_readable($file)) {
                return $file;
            }
        }
        return false;
    }

    public static function ajaxMeasure()
    {
        if (!Menu::userCanAccessPlugin() || !check_ajax_referer('wpacu_unloaded_asset_size', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Access denied.'), 403);
            return;
        }
        $url = isset($_POST['url']) && is_string($_POST['url']) ? wp_unslash($_POST['url']) : '';
        $signature = isset($_POST['signature']) && is_string($_POST['signature']) ? wp_unslash($_POST['signature']) : '';
        if (strlen($url) > 8192 || !hash_equals(wp_hash(get_current_user_id() . '|' . $url, 'nonce'), $signature)) {
            wp_send_json_error(array('message' => 'Invalid asset.'), 400);
            return;
        }
        $transfer = $url !== '' ? self::measureTransfer($url) : false;
        $bytes = is_array($transfer) ? $transfer['bytes'] : false;
        $inlineBytes = null;
        $token = isset($_POST['inline_token']) && is_string($_POST['inline_token']) ? $_POST['inline_token'] : '';
        $id = isset($_POST['id']) && is_scalar($_POST['id']) ? (string)$_POST['id'] : '';
        if (preg_match('/^[a-zA-Z0-9]{32}$/D', $token) && ctype_digit($id)) {
            $snapshot = get_transient(WPACU_PLUGIN_ID . '_inline_saving_' . get_current_user_id() . '_' . $token);
            if (is_array($snapshot) && isset($snapshot[$id]) && is_string($snapshot[$id]) && function_exists('gzencode')) {
                $compressed = gzencode($snapshot[$id], 6);
                if ($compressed !== false) { $inlineBytes = strlen($compressed); }
            }
        }
        wp_send_json_success(array('bytes' => $bytes === false ? null : $bytes, 'encoding' => is_array($transfer) ? $transfer['encoding'] : '', 'inlineBytes' => $inlineBytes));
    }

    /** Measure a bounded group of assets in one WordPress request. */
    public static function ajaxMeasureBatch()
    {
        if (!Menu::userCanAccessPlugin() || !check_ajax_referer('wpacu_unloaded_asset_size', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Access denied.'), 403);
            return;
        }
        $rawItems = isset($_POST['items']) && is_string($_POST['items']) ? wp_unslash($_POST['items']) : '';
        $items = json_decode($rawItems, true);
        if (!is_array($items) || count($items) > 100) {
            wp_send_json_error(array('message' => 'Invalid asset batch.'), 400);
            return;
        }
        $results = array();
        foreach ($items as $item) {
            if (!is_array($item)) { continue; }
            $url = isset($item['url']) && is_string($item['url']) ? $item['url'] : '';
            $signature = isset($item['signature']) && is_string($item['signature']) ? $item['signature'] : '';
            $id = isset($item['id']) && is_scalar($item['id']) ? (string)$item['id'] : '';
            if ($id === '' || strlen($url) > 8192 || !hash_equals(wp_hash(get_current_user_id() . '|' . $url, 'nonce'), $signature)) {
                $results[$id] = array();
                continue;
            }
            $transfer = $url !== '' ? self::measureTransfer($url) : false;
            $inlineBytes = null;
            $token = isset($_POST['inline_token']) && is_string($_POST['inline_token']) ? $_POST['inline_token'] : '';
            if (preg_match('/^[a-zA-Z0-9]{32}$/D', $token) && ctype_digit($id)) {
                $snapshot = get_transient(WPACU_PLUGIN_ID . '_inline_saving_' . get_current_user_id() . '_' . $token);
                if (is_array($snapshot) && isset($snapshot[$id]) && is_string($snapshot[$id]) && function_exists('gzencode')) {
                    $compressed = gzencode($snapshot[$id], 6);
                    if ($compressed !== false) { $inlineBytes = strlen($compressed); }
                }
            }
            $results[$id] = array(
                'bytes' => is_array($transfer) ? $transfer['bytes'] : null,
                'encoding' => is_array($transfer) ? $transfer['encoding'] : '',
                'inlineBytes' => $inlineBytes
            );
        }
        wp_send_json_success(array('results' => $results));
    }

    public static function measureTransfer($url)
    {
        $path = self::localPath($url);
        $key = WPACU_PLUGIN_ID . '_transfer_' . md5($url . ($path ? '|' . filemtime($path) . '|' . filesize($path) : ''));
        $cached = get_transient($key);
        if (is_array($cached) && array_key_exists('result', $cached)) { return $cached['result']; }
        $result = AssetsManager::getRemoteGzipSize($url, true);
        // If a local/private development URL cannot be requested safely, only
        // the uncompressed local size is known; never claim server compression.
        if ($result === false && $path) { $result = array('bytes' => filesize($path), 'encoding' => ''); }
        set_transient($key, array('result' => $result), $result === false ? 5 * MINUTE_IN_SECONDS : HOUR_IN_SECONDS);
        return $result;
    }

    public static function addMenu($bar, $lists)
    {
        if (is_admin() || !Menu::userCanAccessPlugin()) { return; }
        $items = array();
        $inlineSnapshot = array();
        $inlineSize = 0;
        $savedInfo = null;
        foreach (array('styles' => 'wpAllStyles', 'scripts' => 'wpAllScripts') as $type => $property) {
            $assets = Main::instance()->$property;
            foreach (array_unique($lists[$type]) as $handle) {
                $captured = isset(self::$removed[$type][$handle]) ? self::$removed[$type][$handle] : null;
                $asset = $captured ?: (isset($assets['registered'][$handle]) ? $assets['registered'][$handle] : null);
                $inline = $captured ? self::inlineContent($type, $captured) : '';
                if (!$asset) {
                    if ($savedInfo === null) { $savedInfo = Main::getHandlesInfo(); }
                    if (isset($savedInfo[$type][$handle]) && is_array($savedInfo[$type][$handle])) {
                        $asset = self::savedAsset($type, $savedInfo[$type][$handle]);
                        $inline = self::inlineContent($type, $asset);
                    }
                }
                $id = count($items);
                if ($inline !== '') {
                    $inlineSize += strlen($inline);
                    // Do not expose removed script content in the rendered page.
                    // The short-lived server snapshot is accessible only to this user.
                    $inlineSnapshot[$id] = $inlineSize <= 5 * MB_IN_BYTES ? $inline : false;
                }
                $url = !empty($asset->src) ? html_entity_decode($asset->src, ENT_QUOTES, 'UTF-8') : '';
                if (strpos($url, '//') === 0) {
                    $url = (is_ssl() ? 'https:' : 'http:') . $url;
                } elseif ($url !== '' && !preg_match('#^https?://#i', $url)) {
                    $url = preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) ? '' : \WP_Http::make_absolute_url($url, site_url() . '/');
                }
                $url = preg_replace('/#.*$/', '', $url);
                $version = isset($asset->ver) ? $asset->ver : null;
                $registryKey = $type === 'styles' ? 'wp_styles' : 'wp_scripts';
                if ($version === false && isset($GLOBALS[$registryKey]->default_version)) {
                    $version = $GLOBALS[$registryKey]->default_version;
                }
                if ($url !== '' && $version) { $url = add_query_arg('ver', $version, $url); }
                $items[] = array('id' => $id, 'url' => $url, 'type' => $type, 'inline' => $inline !== '',
                    'signature' => wp_hash(get_current_user_id() . '|' . $url, 'nonce'));
            }
        }
        if (!$items) { return; }
        $inlineToken = '';
        if ($inlineSnapshot) {
            $inlineToken = wp_generate_password(32, false, false);
            set_transient(WPACU_PLUGIN_ID . '_inline_saving_' . get_current_user_id() . '_' . $inlineToken, $inlineSnapshot, 30 * MINUTE_IN_SECONDS);
        }
        $bar->add_menu(array('parent' => 'assetcleanup-parent', 'id' => 'assetcleanup-savings',
            'title' => '<span id="wpacu-savings-label">' . esc_html__('Estimated CSS/JS savings: calculate', 'wp-asset-clean-up') . '</span>'
                . ' <span id="wpacu-savings-help-slot" data-label="' . esc_attr__('How are savings measured?', 'wp-asset-clean-up') . '" data-explanation="'
                . esc_attr__('Measured response sizes for unloaded files, with Brotli or GZIP indicated when returned by the server. Mixed compression totals have no single compression label. If a local URL cannot be measured, its uncompressed size is used as an estimate. Inline code is estimated separately using GZIP; its contribution to compressed HTML varies. Browser cache, Brotli and other optimizations can change actual savings. Excludes images, fonts and plugin savings.', 'wp-asset-clean-up') . '"></span>',
            'href' => '#'));
        foreach (array('styles' => 'CSS files', 'scripts' => 'JavaScript files', 'inline' => 'Inline CSS/JS', 'coverage' => __('Waiting for calculation', 'wp-asset-clean-up')) as $key => $name) {
            $bar->add_menu(array('parent' => 'assetcleanup-savings', 'id' => 'assetcleanup-savings-' . $key,
                'title' => '<span id="wpacu-savings-' . $key . '">' . esc_html($name) . '</span>'));
        }
        $config = array('url' => admin_url('admin-ajax.php'), 'action' => WPACU_PLUGIN_ID . '_unloaded_asset_size', 'batchAction' => WPACU_PLUGIN_ID . '_unloaded_asset_sizes',
            'nonce' => wp_create_nonce('wpacu_unloaded_asset_size'), 'items' => $items, 'inlineToken' => $inlineToken,
            'labels' => array('loading' => __('Estimating CSS/JS savings…', 'wp-asset-clean-up'),
                'total' => __('Estimated transfer savings', 'wp-asset-clean-up'),
                'unknown' => __('unavailable', 'wp-asset-clean-up'), 'assets' => __('assets measured', 'wp-asset-clean-up'),
                'asset' => __('asset measured', 'wp-asset-clean-up'),
                'failed' => __('Estimate unavailable', 'wp-asset-clean-up')));
        add_action('wp_after_admin_bar_render', static function () use ($config) {
            $version = WPACU_PLUGIN_VERSION . '.' . filemtime(__DIR__ . '/../assets/wpacu-admin-bar-savings.min.js');
            wp_register_script('wpacu-admin-bar-savings', plugins_url('assets/wpacu-admin-bar-savings.min.js', WPACU_PLUGIN_FILE), array(), $version, true);
            wp_add_inline_script('wpacu-admin-bar-savings', 'window.wpacuAdminBarSavings = ' . wp_json_encode($config) . ';', 'before');
            wp_print_scripts('wpacu-admin-bar-savings');
            wp_register_script('wpacu-admin-bar-inactive-help', plugins_url('assets/wpacu-admin-bar-inactive-help.min.js', WPACU_PLUGIN_FILE), array(), WPACU_PLUGIN_VERSION . '.' . filemtime(__DIR__ . '/../assets/wpacu-admin-bar-inactive-help.min.js'), true);
            wp_print_scripts('wpacu-admin-bar-inactive-help');
        });
    }
}
