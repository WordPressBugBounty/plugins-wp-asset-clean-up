(function () {
    function wpAssetCleanUpClearCache() {
        const wpacuSpinnerElId = '#wpacu-main-loading-spinner';
        const wpacuSpinnerTextEl = '#wpacu-main-loading-spinner-text';
        let wpacuInitialized = false;
        let wpacuClearCacheInProgress = false;
        let wpacuClearCachePending = false;
        let wpacuClearCacheRequest = null;
        let wpacuClearCacheFallbackForm = null;
        let wpacuClearCacheTrigger = null;
        let wpacuClearCacheConfirmed = false;
        let wpacuRestoreCacheLabel = null;

        function wpacuShowClearCacheSuccess() {
            const control = wpacuClearCacheTrigger;
            wpacuClearCacheTrigger = null;
            if (!control || !wpacuClearCacheConfirmed) {
                return;
            }
            const originalNodes = Array.from(control.childNodes);
            const status = document.createElement('span');
            status.setAttribute('role', 'status');
            status.setAttribute('aria-live', 'polite');
            while (control.firstChild) {
                control.removeChild(control.firstChild);
            }
            control.appendChild(status);
            status.textContent = wpacu_object.cache_cleared_message || 'Cache cleared.';
            const restoreTimer = setTimeout(function () {
                wpacuRestoreCacheLabel();
            }, 4000);
            wpacuRestoreCacheLabel = function () {
                clearTimeout(restoreTimer);
                status.remove();
                originalNodes.forEach(function (node) { control.appendChild(node); });
                wpacuRestoreCacheLabel = null;
            };
        }

        function wpacuIsEnabled(value) {
            return value === true || value === 1 || value === '1' || value === 'true';
        }

        // Keep the requests independent of jQuery, including its AJAX setup and event handlers.
        function wpacuAjaxRequest(method, url, sendParams, callback, timeout = 0) {
            const request = new XMLHttpRequest();
            const params = Object.keys(sendParams).map(function (name) {
                const value = sendParams[name];
                return encodeURIComponent(name) + '=' + encodeURIComponent(value == null ? '' : value);
            }).join('&');
            let completed = false;

            function complete(success, response = '') {
                if (completed) {
                    return;
                }
                completed = true;
                if (callback) {
                    callback(success, response);
                }
            }

            try {
                if (method === 'GET') {
                    url = url.split('#')[0];
                    url += (url.indexOf('?') === -1 ? '?' : '&') + params;
                }
                request.open(method, url, true);
                request.timeout = timeout;
                if (method === 'POST') {
                    request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
                }
                request.onload = function () {
                    complete((request.status >= 200 && request.status < 300) || request.status === 304, request.responseText);
                };
                request.onerror = request.ontimeout = request.onabort = function () {
                    complete(false);
                };
                request.send(method === 'POST' ? params : null);
            } catch (e) {
                complete(false);
            }

            return request;
        }

        function wpacuFinishClearCache() {
            wpacuClearCacheInProgress = false;
            wpacuClearCacheRequest = null;
            wpacuClearCacheFallbackForm = null;
            const wpacuSpinnerEl = document.querySelector(wpacuSpinnerElId);
            if (wpacuSpinnerEl) {
                // As the caching has been cleared, hide the notice from the screen
                wpacuSpinnerEl.classList.add('wpacu_hide');
            }

            // A save during preloading may have changed the rules again; clear once more.
            if (wpacuClearCachePending) {
                wpacuClearCachePending = false;
                self.wpacuAjaxClearCache();
            } else {
                wpacuShowClearCacheSuccess();
            }
        }

        function wpacuSubmitFallbackForm(form) {
            // Fall back only for an explicit click, using the existing nonce-protected POST.
            wpacuClearCachePending = false;
            wpacuFinishClearCache();
            if (form) {
                HTMLFormElement.prototype.submit.call(form);
            }
        }

        const self = {
            init: function () {
                if (wpacuInitialized) {
                    return;
                }
                wpacuInitialized = true;

                // Clear cache if "clear_cache" is set in the "wpacu_object"
                // e.g. after a WP theme is changed
                // The assets of a page just had rules applied (e.g. assets were unloaded)
                const triggeredClearCacheIncludingOtherPluginsClearing = wpacuIsEnabled(wpacu_object.clear_cache_via_ajax);
                if (triggeredClearCacheIncludingOtherPluginsClearing) {
                    self.wpacuAjaxClearCache(true);
                }

                document.addEventListener('click', function (e) {
                    const clearCacheLink = e.target.closest && e.target.closest('.wpacu-clear-cache-link');
                    if (!clearCacheLink) {
                        return;
                    }
                    const wpacuFallbackForm = clearCacheLink.closest('form');
                    e.preventDefault();

                    try {
                        if (wpacuRestoreCacheLabel) {
                            wpacuRestoreCacheLabel();
                        }
                        wpacuClearCacheTrigger = clearCacheLink.matches('a, button')
                            ? clearCacheLink : clearCacheLink.querySelector('a, button');
                        const wpacuSpinnerEl = document.querySelector(wpacuSpinnerElId);
                        const wpacuSpinnerText = document.querySelector(wpacuSpinnerTextEl);
                        const wpacuClearCacheText = document.querySelector('div[data-wpacu-clear-cache-text="1"]');
                        if (wpacuSpinnerEl) {
                            if (wpacuSpinnerText && wpacuClearCacheText) {
                                wpacuSpinnerText.innerHTML = wpacuClearCacheText.innerHTML;
                            }
                            wpacuSpinnerEl.classList.remove('wpacu_hide');
                        }

                        self.wpacuAjaxClearCache(true, wpacuFallbackForm);
                    } catch (e) {
                        wpacuSubmitFallbackForm(wpacuFallbackForm);
                    }
                });

                // Do not trigger other plugins' cache again if already cleared (save resources)
                if (wpacuIsEnabled(wpacu_object.clear_other_caches) && !triggeredClearCacheIncludingOtherPluginsClearing) {
                    setTimeout(function () {
                        self.wpacuClearAutoptimizeCache();
                        self.wpacuClearCacheEnablerCache();
                    }, 150);
                }
            },

            afterSubmit: function () {
                try {
                    let httpRefererFieldTargetName = 'input[type="hidden"][name="_wp_http_referer"]',
                        wpacuHttpRefererFieldVal;

                    const wpacuHttpRefererField = document.querySelector(httpRefererFieldTargetName);
                    if (wpacuHttpRefererField) {
                        wpacuHttpRefererFieldVal = wpacuHttpRefererField.value;

                        // Edit Taxonomy page (after submit)
                        if (wpacuHttpRefererFieldVal.includes('term.php?taxonomy=') && wpacuHttpRefererFieldVal.includes('message=')) {
                            self.wpacuAjaxClearCache();
                        }

                        // Edit (Post/Page/Custom Post type) page (after submit)
                        if (wpacuHttpRefererFieldVal.includes('post.php?post=') && wpacuHttpRefererFieldVal.includes('message=')) {
                            self.wpacuAjaxClearCache();
                        }
                    }
                } catch (e) {
                    console.log(e);
                }
            },

            wpacuAjaxClearCache: function (forceCacheClear = false, wpacuFallbackForm = null) {
                // Do these verifications if the default value is used which is for triggering the cache in certain situations
                // If it's set to "true" then it's the obvious intention of the user to clear the cache such as clicking the link from the top admin bar
                if (forceCacheClear === false) {
                    /**
                     * Called after a post/page is saved (WordPress AJAX call)
                     */
                    if (typeof wpacu_object.wpacu_ajax_preload_url_nonce === 'undefined') {
                        return;
                    }

                    // Is the post status a "draft" one? Do not do any cache clearing and preloading as it's useless
                    const wpacuHiddenPostStatusEl = document.querySelector('#hidden_post_status');
                    if (wpacuHiddenPostStatusEl && wpacuHiddenPostStatusEl.value === 'draft') {
                        return;
                    }
                }

                if (wpacuClearCacheInProgress) {
                    // Keep the user's fallback even if an automatic clear started first.
                    if (wpacuFallbackForm) {
                        wpacuClearCacheFallbackForm = wpacuFallbackForm;
                        if (wpacuClearCacheRequest && wpacuClearCacheRequest.readyState !== 4) {
                            wpacuClearCacheRequest.timeout = 30000;
                        }
                    }
                    if (forceCacheClear === false) {
                        wpacuClearCachePending = true;
                    }
                    return;
                }
                wpacuClearCacheInProgress = true;
                wpacuClearCacheConfirmed = false;
                wpacuClearCacheFallbackForm = wpacuFallbackForm;

                wpacuClearCacheRequest = wpacuAjaxRequest('GET', wpacu_object.ajax_url, {
                    'action':       wpacu_object.plugin_prefix + '_clear_cache',
                    'wpacu_time_r': new Date().getTime(),
                    'wpacu_nonce':  wpacu_object.wpacu_ajax_clear_cache_nonce
                }, function (success, response) {
                    const wpacuAssetsReloadingEl = document.querySelector('#wpacu-assets-reloading-in-edit-post-area');
                    if (wpacuAssetsReloadingEl) {
                        wpacuAssetsReloadingEl.remove();
                    }
                    if (!success || /^(?:Error:|0$|-1$)/.test(response.trim())) {
                        if (wpacuClearCacheFallbackForm) {
                            wpacuSubmitFallbackForm(wpacuClearCacheFallbackForm);
                        } else {
                            wpacuFinishClearCache();
                        }
                        return;
                    }
                    wpacuClearCacheRequest = null;
                    wpacuClearCacheFallbackForm = null;
                    wpacuClearCacheConfirmed = true;
                    setTimeout(function () {
                        self.wpacuClearAutoptimizeCache(); // Autoptimize (if active)

                        // "Cache Enabler" (if active) cache was already cleared in classes/Update::ajaxClearCache() during the AJAX call
                        if (wpacuIsEnabled(wpacu_object.is_frontend_view)) {
                            // Preload (for the guest)
                            // The preload for the admin is not needed as the user is managing the CSS/JS in the front-end view and the page has been already visited
                            self.wpacuPreloadForGuest();
                        } else {
                            // Preload (for the admin)
                            wpacuAjaxRequest('GET', wpacu_object.page_url, {
                                'wpacu_preload': 1,
                                'wpacu_no_frontend_show': 1,
                                'wpacu_time_r': new Date().getTime()
                            }, function () {
                                // Then, preload (for the guest)
                                self.wpacuPreloadForGuest();
                            });
                        }
                    }, 150);
                }, wpacuFallbackForm ? 30000 : 0);
            },

            wpacuPreloadForGuest: function () {
                wpacuAjaxRequest('POST', wpacu_object.ajax_url, {
                    'action': wpacu_object.plugin_prefix + '_preload',
                    'page_url': wpacu_object.page_url,
                    'wpacu_nonce': wpacu_object.wpacu_ajax_preload_url_nonce,
                    'wpacu_time_r': new Date().getTime()
                }, wpacuFinishClearCache);
            },

            wpacuClearAutoptimizeCache: function () {
                if (typeof wpacu_object.autoptimize_not_active !== 'undefined') {
                    return; // Autoptimize is not activated, thus do not continue
                }

                if (wpacu_object.clear_autoptimize_cache == 'false') {
                    console.log(wpacu_object.plugin_title + ': Autoptimize cache clearing is deactivated via "WPACU_DO_NOT_ALSO_CLEAR_AUTOPTIMIZE_CACHE" constant.');
                    return;
                }

                let wpacuAutoptimizeClickEl = '#wp-admin-bar-autoptimize-default li';

                // Autoptimize elements & variables: make sure they are all initialized
                if (document.querySelector(wpacuAutoptimizeClickEl)
                    && typeof autoptimize_ajax_object !== 'undefined' && autoptimize_ajax_object
                    && typeof autoptimize_ajax_object.ajaxurl !== 'undefined'
                    && typeof autoptimize_ajax_object.nonce !== 'undefined') {
                    wpacuAjaxRequest('GET', autoptimize_ajax_object.ajaxurl, {
                        'action': 'autoptimize_delete_cache',
                        'nonce': autoptimize_ajax_object.nonce,
                        '_': new Date().getTime()
                    }, null, 9000);
                }
            },

            wpacuClearCacheEnablerCache: function () {
                if (typeof wpacu_object.cache_enabler_not_active !== 'undefined') {
                    return; // Cache Enabler is not activated, thus do not continue
                }

                if (wpacu_object.clear_cache_enabler_cache == 'false') {
                    console.log(wpacu_object.plugin_title + ': "Cache Enabler" cache clearing is deactivated via "WPACU_DO_NOT_ALSO_CLEAR_CACHE_ENABLER_CACHE" constant.');
                    return;
                }

                let sendParams = {
                    'action': wpacu_object.plugin_prefix + '_cache_enabler_clear_cache',
                    'wpacu_time_r': new Date().getTime(), // avoid any caching
                    'wpacu_nonce': wpacu_object.wpacu_ajax_clear_cache_enabler_cache_nonce
                };

                wpacuAjaxRequest('GET', wpacu_object.ajax_url, sendParams);
            }
        };

        return self;
    }

    function wpacuInitCacheManager() {
        if (window.wpacuCacheManager || typeof wpacu_object === 'undefined') {
            return;
        }
        window.wpacuCacheManager = wpAssetCleanUpClearCache();
        window.wpacuCacheManager.init();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wpacuInitCacheManager);
    } else {
        wpacuInitCacheManager();
    }
})();

