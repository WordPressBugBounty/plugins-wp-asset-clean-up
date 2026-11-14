/* The parent owns the frame's placement; all manager interactions stay in its document. */
(function () {
    'use strict';

    if (window.frameElement && window.frameElement.id === 'wpacu-frontend-manager-frame') {
        // A mapped public domain can differ from site_url(). Use the actual
        // parent's origin and query string for collection and form submission.
        var pageUrl = window.parent.location.href.split('#')[0];
        window.wpacu_object.frontend_manager_url = pageUrl;
        window.wpacu_object.page_url = pageUrl;
        document.querySelector('base').href = pageUrl;
        var form = document.getElementById('wpacu-frontend-form');
        if (form && form.tagName === 'FORM') {
            form.action = pageUrl + '#wpacu_wrap_assets';
        }
        var returnUrl = document.querySelector('#wpacu-plugins-frontend-form [name="wpacu_plugins_frontend_return"]');
        if (returnUrl) { returnUrl.value = pageUrl; }
        // Domain-mapped public pages can have a different host from site_url().
        // Keep workspace navigation on the public host without re-encoding its query.
        Array.prototype.forEach.call(document.querySelectorAll('[data-wpacu-manager-tab]'), function (tab) {
            var target = new URL(tab.href, pageUrl);
            var current = new URL(pageUrl);
            target.protocol = current.protocol;
            target.host = current.host;
            tab.href = target.href;
        });
    }

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    ready(function () {
        var frame = document.getElementById('wpacu-frontend-manager-frame');
        if (!frame) {
            // Keep fragment-only UI links within the manager despite the _top base target.
            if (window.frameElement && window.frameElement.id === 'wpacu-frontend-manager-frame') {
                var assetsForm = document.getElementById('wpacu-frontend-form');
                if (assetsForm) {
                    var leaving = false;
                    function hasChanges() {
                        // Inspect the actual controls, including changes dispatched only
                        // through jQuery by Chosen, and changes subsequently reverted.
                        return Array.prototype.some.call(assetsForm.elements, function (field) {
                            if (!field.name || field.type === 'hidden' || field.tagName === 'BUTTON'
                                || field.type === 'submit') { return false; }
                            if (field.type === 'checkbox' || field.type === 'radio') {
                                return field.checked !== field.defaultChecked;
                            }
                            if (field.tagName === 'SELECT') {
                                var hasDefault = Array.prototype.some.call(field.options, function (option) { return option.defaultSelected; });
                                return Array.prototype.some.call(field.options, function (option, index) {
                                    return option.selected !== (option.defaultSelected || (!field.multiple && !hasDefault && index === 0));
                                });
                            }
                            return field.value !== field.defaultValue;
                        });
                    }
                    assetsForm.addEventListener('submit', function () { leaving = true; });
                    Array.prototype.forEach.call(document.querySelectorAll('[data-wpacu-manager-tab]'), function (tab) {
                        tab.addEventListener('click', function (event) {
                            // A new tab/window leaves this form open and still needs its guard.
                            if (event.defaultPrevented || event.button !== 0 || event.ctrlKey
                                || event.metaKey || event.shiftKey || event.altKey) { return; }
                            if (hasChanges() && !window.confirm(window.wpacu_object.frontend_unsaved_changes)) {
                                event.preventDefault();
                            } else { leaving = true; }
                        });
                    });
                    window.addEventListener('beforeunload', function (event) {
                        if (!leaving && hasChanges()) { event.preventDefault(); event.returnValue = ''; }
                    });
                }
                document.addEventListener('click', function (event) {
                    var link = event.target.closest ? event.target.closest('a[href]') : null;
                    if (!link || event.defaultPrevented) {
                        return;
                    }
                    var href = link.getAttribute('href');
                    if (href && href.charAt(0) === '#') {
                        event.preventDefault();
                        var target = document.getElementById(href.slice(1));
                        if (target) {
                            target.scrollIntoView({block: 'start'});
                        }
                    }
                });
            }
            return;
        }

        var host = frame.parentNode;
        // Themes sometimes call wp_footer() inside a constrained/transformed wrapper.
        if (host.parentNode !== document.body) {
            document.body.appendChild(host);
        }

        var resizeObserver;
        var scheduled = false;
        function updateViewport() {
            var doc = frame.contentDocument;
            var rect = frame.getBoundingClientRect();
            var viewport = window.visualViewport;
            var left = viewport ? viewport.offsetLeft : 0;
            var top = viewport ? viewport.offsetTop : 0;
            var right = left + (viewport ? viewport.width : document.documentElement.clientWidth);
            var bottom = top + (viewport ? viewport.height : window.innerHeight);
            var adminBar = document.getElementById('wpadminbar');
            if (adminBar && window.getComputedStyle(adminBar).position === 'fixed') {
                var barRect = adminBar.getBoundingClientRect();
                if (barRect.top <= top && barRect.bottom > top) {
                    top = Math.min(bottom, barRect.bottom);
                }
            }

            // Fixed elements inside srcdoc use the full iframe as their viewport.
            // Share only its visible intersection with the parent for dialog layout.
            var frameLeft = rect.left + frame.clientLeft;
            var frameTop = rect.top + frame.clientTop;
            var visibleLeft = Math.max(left, frameLeft);
            var visibleTop = Math.max(top, frameTop);
            var values = {
                left: Math.max(0, visibleLeft - frameLeft),
                top: Math.max(0, visibleTop - frameTop),
                width: Math.max(0, Math.min(right, frameLeft + frame.clientWidth) - visibleLeft),
                height: Math.max(0, Math.min(bottom, frameTop + frame.clientHeight) - visibleTop)
            };
            Object.keys(values).forEach(function (key) {
                var name = '--wpacu-visible-' + key;
                var value = values[key] + 'px';
                if (doc.documentElement.style.getPropertyValue(name) !== value) {
                    doc.documentElement.style.setProperty(name, value);
                }
            });

            var updateArea = doc.getElementById('wpacu-update-front-settings-area');
            var manager = doc.getElementById('wpacu_wrap_assets');
            if (updateArea && manager) {
                // Move the original controls, retaining their form, validation,
                // disabled state and submit handlers. The layout space at the
                // end of the form stays reserved so the last row is accessible.
                var previousOffset = parseFloat(updateArea.style.getPropertyValue('--wpacu-update-offset')) || 0;
                var areaRect = updateArea.getBoundingClientRect();
                var naturalTop = areaRect.top - previousOffset;
                var naturalBottom = areaRect.bottom - previousOffset;
                // Start floating only once the asset groups reach the viewport,
                // keeping the introductory page details clear of the controls.
                var firstGroup = manager.querySelector('.wpacu-assets-collapsible-wrap, .wpacu-pm-page-list');
                var firstGroupRect = firstGroup ? firstGroup.getBoundingClientRect() : null;
                var canFloat = values.height > 0 && firstGroupRect && firstGroupRect.height > 0
                    && firstGroupRect.top < values.top + values.height;
                var offset = canFloat
                    ? Math.min(0, values.top + values.height - naturalBottom) : 0;
                if (canFloat) {
                    offset = Math.max(firstGroupRect.top - naturalTop, offset);
                }
                if (previousOffset !== offset) {
                    updateArea.style.setProperty('--wpacu-update-offset', offset + 'px');
                }
            }
        }

        function resize() {
            scheduled = false;
            try {
                if (!frame.contentDocument || !frame.contentDocument.body) {
                    return;
                }
                // Match the natural document height so scrolling stays in the
                // parent page, including after AJAX and group expansion.
                var contentHeight = Math.ceil(frame.contentDocument.body.getBoundingClientRect().height) + 2;
                var height = Math.max(320, contentHeight) + 'px';
                if (frame.style.height !== height) {
                    frame.style.setProperty('height', height, 'important');
                }
                updateViewport();
            } catch (error) {
                // A separately targeted navigation must not break the host page.
            }
        }
        function scheduleResize() {
            if (!scheduled) {
                scheduled = true;
                window.requestAnimationFrame(resize);
            }
        }
        function observeFrame() {
            if (resizeObserver) {
                resizeObserver.disconnect();
            }
            try {
                if (window.ResizeObserver && frame.contentDocument && frame.contentDocument.body) {
                    resizeObserver = new ResizeObserver(scheduleResize);
                    resizeObserver.observe(frame.contentDocument.body);
                }
            } catch (error) {
                return;
            }
            scheduleResize();
        }
        frame.addEventListener('load', observeFrame);
        window.addEventListener('resize', scheduleResize);
        window.addEventListener('scroll', scheduleResize, true);
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', scheduleResize);
            window.visualViewport.addEventListener('scroll', scheduleResize);
        }
        observeFrame();
    });
}());
