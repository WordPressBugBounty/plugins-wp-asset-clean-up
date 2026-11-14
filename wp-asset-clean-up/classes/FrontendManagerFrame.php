<?php
namespace WpAssetCleanUp;

/**
 * Renders the existing front-end manager in its own document.
 * Asset collection and updates continue to use the original page request.
 */
class FrontendManagerFrame
{
    public static function isEnabled()
    {
        return self::activeManager() !== '';
    }

    public static function managers()
    {
        $managers = array();
        if (Main::showAssetsManagerInFrontend(false)) {
            $managers['assets'] = __('CSS/JS Manager', 'wp-asset-clean-up');
        }
        return apply_filters('wpacu_internal_frontend_workspaces', $managers);
    }

    public static function activeManager()
    {
        $managers = self::managers();
        $requested = isset($_GET['wpacu_manager']) && is_string($_GET['wpacu_manager']) ? $_GET['wpacu_manager'] : '';
        if (isset($managers[$requested])) { return $requested; }
        $keys = array_keys($managers);
        return $keys ? $keys[0] : '';
    }

    public static function managerUrl($url, $workspace, $updated = false)
    {
        $url = explode('#', $url, 2)[0];
        $url = function_exists('wpacuPluginManagerRequestUri')
            ? wpacuPluginManagerRequestUri($url) : $url;
        return $url.(strpos($url, '?') === false ? '?' : '&').'wpacu_manager='.rawurlencode($workspace)
            .($updated ? '&wpacu_plugins_updated=1' : '').'#wpacu_wrap_assets';
    }

    public static function render($managerHtml, $workspace = 'assets', $extra = array())
    {
        $pageUrl = Misc::getCurrentPageUrl(false);
        $objectData = OwnAssets::applyInternalFilterWithFallback(
            'wpacu_internal_object_data', 'wpacu_object_data', OwnAssets::generateObjectData()
        );
        $objectData['frontend_manager_url'] = $pageUrl;
        $objectData['page_url'] = $pageUrl;
        $objectData['frontend_unsaved_changes'] = __('You have unsaved changes. Leave this manager without saving?', 'wp-asset-clean-up');
        // The toolbar in the parent owns post-update cache clearing.
        $objectData['clear_cache'] = false;
        $objectData['clear_cache_via_ajax'] = false;
        $objectData['clear_other_caches'] = false;

        // Match wp_localize_script(), including the empty string used for false
        // by the existing Lite cache manager and string-valued IDs/messages.
        foreach ($objectData as $key => $value) {
            if (is_scalar($value)) {
                $objectData[$key] = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
            }
        }

        $start = '<span data-wpacu-delimiter-frontend-form-start="1" style="display:none;"></span>';
        $end = '<span data-wpacu-delimiter-frontend-form-end="1" style="display:none;"></span>';
        $managerHtml = str_replace(array($start, $end), '', $managerHtml);
        $rtl = is_rtl() ? '-rtl' : '';
        $styles = array(
            includes_url('css/dashicons.min.css'),
            includes_url('css/buttons' . $rtl . '.min.css'),
            admin_url('css/common' . $rtl . '.min.css'),
            admin_url('css/forms' . $rtl . '.min.css'),
            self::assetUrl(OwnAssets::$ownAssets['styles']['style_core']['rel_path'])
        );
        $scripts = array(
            OwnAssets::$ownAssets['scripts']['script_cache_manager']['handle'] => self::assetUrl(OwnAssets::$ownAssets['scripts']['script_cache_manager']['rel_path'])
        );
        if ($workspace === 'assets') {
            $scripts[OwnAssets::$ownAssets['scripts']['script_core']['handle']] = self::assetUrl(OwnAssets::$ownAssets['scripts']['script_core']['rel_path']);
        }

        if ($workspace === 'assets' && Settings::getInputStyle(Main::instance()->settings) === 'enhanced') {
            $styles[] = self::assetUrl(OwnAssets::$ownAssets['styles']['chosen']['rel_path']);
            $scripts[OwnAssets::$ownAssets['scripts']['chosen']['handle']] = self::assetUrl(OwnAssets::$ownAssets['scripts']['chosen']['rel_path']);
        }

        $styles[] = self::assetUrl(OwnAssets::$ownAssets['styles']['sweetalert2']['rel_path']);
        $scripts[OwnAssets::$ownAssets['scripts']['sweetalert2']['handle']] = self::assetUrl(OwnAssets::$ownAssets['scripts']['sweetalert2']['rel_path']);
        $debug = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) || isset($_GET['wpacu_debug']);
        $styles[] = self::assetUrl('/assets/frontend-manager' . ($debug ? '' : '.min') . '.css');
        $scripts[OwnAssets::$ownAssets['scripts']['frontend_manager']['handle']] = self::assetUrl('/assets/frontend-manager' . ($debug ? '' : '.min') . '.js');
        $styles = array_merge($styles, isset($extra['styles']) ? $extra['styles'] : array());
        $scripts = array_merge($scripts, isset($extra['scripts']) ? $extra['scripts'] : array());
        $managers = self::managers();
        $title    = isset($managers[$workspace]) ? $managers[$workspace] : __('Asset CleanUp Manager', 'wp-asset-clean-up');
        $colors   = array('assets' => '#006584', 'plugins' => '#7254a3');
        $accent   = isset($colors[$workspace]) ? $colors[$workspace] : '#52616b';
        $tabs     = '';
        if (count($managers) > 1) {
            $tabs = '<nav class="wpacu-frontend-workspaces" aria-label="'.esc_attr__('Management workspace', 'wp-asset-clean-up').'">';
            foreach ($managers as $key => $label) {
                $url   = self::managerUrl($pageUrl, $key);
                $icon  = $key === 'assets' ? 'editor-code' : ($key === 'plugins' ? 'admin-plugins' : 'screenoptions');
                if ($key === 'plugins' && !defined('WPACU_PRO_PLUGIN_VERSION')) {
                    $icon = 'lock';
                }
                $color = isset($colors[$key]) ? $colors[$key] : '#52616b';
                $tabs .= '<a data-wpacu-manager-tab="'.esc_attr($key).'" style="--wpacu-workspace-accent:'.esc_attr($color).'" href="'.esc_url($url).'"'
                    .($key === $workspace ? ' class="is-current" aria-current="page"' : '').'>'
                    .'<span class="dashicons dashicons-'.esc_attr($icon).'" aria-hidden="true"></span>'
                    .'<span>'.esc_html($label).'</span></a>';
            }
            $tabs .= '</nav>';
        }

        // Use WordPress' own jQuery, independent of deregistrations/replacements by the theme.
        $jquery = is_file(ABSPATH . WPINC . '/js/jquery/jquery.min.js')
            ? 'js/jquery/jquery.min.js' : 'js/jquery/jquery.js';
        $document = '<!doctype html><html ' . get_language_attributes() . '><head>'
            . '<meta charset="' . esc_attr(get_bloginfo('charset')) . '">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . esc_html($title) . '</title>'
            . '<base href="' . esc_url($pageUrl) . '" target="_top">';

        foreach ($styles as $url) {
            $document .= '<link rel="stylesheet" href="' . esc_url($url) . '">';
        }

        $objectScript = 'var wpacu_object = ' . wp_json_encode(
            $objectData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) . ';';
        $objectAttributes = array('id' => OwnAssets::$ownAssets['scripts']['script_cache_manager']['handle'] . '-js-extra');
        $document .= self::scriptTag('jquery-core', includes_url($jquery))
            . (function_exists('wp_get_inline_script_tag')
                ? wp_get_inline_script_tag($objectScript, $objectAttributes)
                : '<script' . self::scriptAttributes(apply_filters('wp_inline_script_attributes', $objectAttributes, $objectScript)) . '>' . $objectScript . '</script>')
            . '</head><body class="wp-admin wp-core-ui wpacu-frontend-manager-document">'
            . '<div class="wpacu-frontend-manager-shell" style="--wpacu-manager-accent:'.esc_attr($accent).'">'
            . $tabs . '<div class="wpacu-frontend-manager-content">' . $managerHtml . '</div></div>';

        if (!empty($extra['inline'])) {
            $document .= OwnAssets::getInlineScriptTag($extra['inline']);
        }

        foreach ($scripts as $handle => $url) {
            $document .= self::scriptTag($handle, $url);
        }
        $document .= '</body></html>';

        // Double-encode entities: the outer attribute parser must not decode the
        // manager's escaped source previews into executable HTML in srcdoc.
        $srcdoc = htmlspecialchars($document, ENT_QUOTES, get_bloginfo('charset'), true);

        return $start
            . '<div id="wpacu_wrap_assets" data-wpacu-frontend-frame-host="1" style="all:initial!important;display:block!important;width:auto!important;max-width:none!important;margin:24px 16px!important;padding:0!important;clear:both!important;">'
            . '<iframe id="wpacu-frontend-manager-frame" title="' . esc_attr($title) . '"'
            . ' style="all:initial!important;display:block!important;width:100%!important;max-width:none!important;height:75vh!important;min-height:320px!important;max-height:none!important;box-sizing:border-box!important;border:0!important;background:transparent!important;"'
            . ' srcdoc="' . $srcdoc . '"></iframe></div>'
            . $end;
    }

    private static function assetUrl($relativePath)
    {
        return add_query_arg('wpacuversion', OwnAssets::assetVer($relativePath), plugins_url($relativePath, WPACU_PLUGIN_FILE));
    }

    private static function scriptTag($handle, $url)
    {
        $attributes = array('src' => $url, 'id' => $handle . '-js');
        $tag = function_exists('wp_get_script_tag')
            ? wp_get_script_tag($attributes)
            : '<script' . self::scriptAttributes(apply_filters('wp_script_attributes', $attributes)) . '></script>';
        $nonce = self::scriptNonce($tag, $handle, $url);

        // Prefer the modern attribute integration. Older CSP integrations use
        // script_loader_tag and need the real handle and URL, too.
        if ($nonce === null && has_filter('script_loader_tag')) {
            global $wpacu_object_cache;
            $cache = isset($wpacu_object_cache) && $wpacu_object_cache instanceof ObjectCache
                ? $wpacu_object_cache : null;
            $saved = array();
            if ($cache) {
                // These filters also capture the parent page's tags/media rules.
                // Rendering a private dependency must not replace that state.
                foreach (array('wpacu_script_loader_tag_' . $handle, 'wpacu_js_media_queries_load_current_page') as $key) {
                    $found = false;
                    $value = $cache->get($key, '', false, $found);
                    $saved[$key] = array($found, $value);
                }
            }
            try {
                $nonce = self::scriptNonce(apply_filters('script_loader_tag', $tag, $handle, $url), $handle, $url);
            } finally {
                foreach ($saved as $key => $entry) {
                    if ($entry[0]) {
                        $cache->set($key, $entry[1]);
                    } else {
                        $cache->delete($key);
                    }
                }
            }
        }

        // Only carry the nonce across. Defer, async, delayed types/URLs, preload
        // wrappers and injected scripts can break the frame's dependency order.
        if ($nonce !== null) {
            $attributes['nonce'] = $nonce;
        }
        return '<script' . self::scriptAttributes($attributes) . '></script>';
    }

    private static function scriptNonce($tag, $handle, $url)
    {
        if (! is_string($tag)) {
            return null;
        }
        $candidates = array();
        if (class_exists('WP_HTML_Tag_Processor')) {
            $processor = new \WP_HTML_Tag_Processor($tag);
            $templateDepth = 0;
            while ($processor->next_tag(array('tag_closers' => 'visit'))) {
                if ($processor->get_tag() === 'TEMPLATE') {
                    $templateDepth += $processor->is_tag_closer() ? -1 : 1;
                    $templateDepth = max(0, $templateDepth);
                }
                if ($templateDepth || $processor->is_tag_closer() || $processor->get_tag() !== 'SCRIPT') {
                    continue;
                }
                $id = $processor->get_attribute('id');
                if ($id === $handle . '-js' || (($id === null || $id === '') && $processor->get_attribute('src') === $url)) {
                    $candidates[] = $processor->get_attribute('nonce');
                }
            }
        } elseif (class_exists('DOMDocument') && $tag !== '') {
            // Older WordPress can still receive a preload link before the tag.
            // Parse without fetching any external resources or changing libxml's
            // global error settings, and ignore scripts inside inert containers.
            $document = new \DOMDocument();
            $document->loadHTML($tag, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            foreach ($document->getElementsByTagName('script') as $script) {
                for ($parent = $script->parentNode; $parent; $parent = $parent->parentNode) {
                    if (in_array(strtolower($parent->nodeName), array('template', 'noscript', 'textarea', 'title', 'style', 'xmp', 'iframe', 'noembed', 'noframes', 'plaintext'), true)) {
                        continue 2;
                    }
                }
                $id = $script->getAttribute('id');
                if ($id === $handle . '-js' || ($id === '' && $script->getAttribute('src') === $url)) {
                    $candidates[] = $script->hasAttribute('nonce') ? $script->getAttribute('nonce') : null;
                }
            }
        } elseif (preg_match('~^\s*<script\b((?:[^>\'\"]|\'[^\']*\'|\"[^\"]*\")*)>\s*</script\s*>\s*$~iD', $tag, $match)) {
            // Older WordPress: conservatively accept one complete empty script
            // tag only. KSES parses actual attributes, not nonce-like text in
            // comments, data attributes, or another attribute's quoted value.
            $parsed = array();
            // Older KSES preserves attribute-name case. HTML names are case
            // insensitive, and the first occurrence wins even with mixed case.
            foreach (wp_kses_hair($match[1], wp_allowed_protocols()) as $name => $attribute) {
                $name = strtolower($name);
                if (! isset($parsed[$name])) {
                    $parsed[$name] = $attribute;
                }
            }
            $id = isset($parsed['id']) ? $parsed['id']['value'] : '';
            $src = isset($parsed['src']) ? html_entity_decode($parsed['src']['value'], ENT_QUOTES, 'UTF-8') : '';
            if ($id === $handle . '-js' || ($id === '' && $src === $url)) {
                $candidates[] = isset($parsed['nonce']) ? html_entity_decode($parsed['nonce']['value'], ENT_QUOTES, 'UTF-8') : null;
            }
        }
        return count($candidates) === 1 && is_string($candidates[0]) && $candidates[0] !== '' ? $candidates[0] : null;
    }

    private static function scriptAttributes($attributes)
    {
        // WordPress versions before 5.7 do not provide the script tag helpers.
        $html = '';
        foreach ($attributes as $name => $value) {
            if (is_bool($value)) {
                $html .= $value ? ' ' . esc_attr($name) : '';
            } else {
                $html .= ' ' . esc_attr($name) . '="' . esc_attr($value) . '"';
            }
        }
        return $html;
    }
}
