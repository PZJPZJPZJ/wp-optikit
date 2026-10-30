/**
 * Visitor country detection for the OptiKit Elementor Country Code field.
 */
(function () {
    'use strict';

    var selector = 'select[data-wpok-country-code="1"]';
    var states = new WeakMap();
    var countryRequest;
    var hooksBound = false;

    function isEditMode() {
        return window.elementorFrontend
            && typeof window.elementorFrontend.isEditMode === 'function'
            && window.elementorFrontend.isEditMode();
    }

    function hasCountry(select, code) {
        return Array.prototype.some.call(select.options, function (option) {
            return option.value === code;
        });
    }

    function detectCountry() {
        if (countryRequest) {
            return countryRequest;
        }

        // Resolve on timeout even if a browser cannot abort the underlying request.
        countryRequest = new Promise(function (resolve) {
            var controller = window.AbortController ? new window.AbortController() : null;
            var timer = window.setTimeout(function () {
                resolve('');
                if (controller) {
                    controller.abort();
                }
            }, 4000);

            window.fetch('/cdn-cgi/trace', {
                cache: 'no-store',
                credentials: 'same-origin',
                signal: controller ? controller.signal : undefined
            }).then(function (response) {
                return response.ok ? response.text() : '';
            }).then(function (body) {
                var match = body.match(/^loc=([A-Z]{2})\r?$/m);
                var code = match ? match[1] : '';
                resolve(code === 'XX' ? '' : code);
            }).catch(function () {
                // Detection is optional; the configured default remains usable.
                resolve('');
            }).finally(function () {
                window.clearTimeout(timer);
            });
        });

        return countryRequest;
    }

    function initializeSelect(select) {
        if (states.has(select)) {
            return;
        }

        var defaultCountry = select.getAttribute('data-default-country') || 'US';
        var state = { touched: false, initialValue: select.value };
        states.set(select, state);

        // Preserve restored browser values as well as explicit user interaction.
        if (select.value !== defaultCountry) {
            state.touched = true;
        }

        ['pointerdown', 'keydown', 'input', 'change'].forEach(function (eventName) {
            select.addEventListener(eventName, function () {
                state.touched = true;
            });
        });

        if (select.getAttribute('data-use-cf-ipcountry') !== '1' || isEditMode() || !window.fetch) {
            return;
        }

        detectCountry().then(function (code) {
            if (!code || isEditMode() || !select.isConnected || state.touched || select.value !== state.initialValue || !hasCountry(select, code)) {
                return;
            }

            if (select.value !== code) {
                select.value = code;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    function initialize(scope) {
        var root = scope && scope.querySelectorAll ? scope : document;

        if (root.matches && root.matches(selector)) {
            initializeSelect(root);
        }

        root.querySelectorAll(selector).forEach(initializeSelect);
    }

    function bindElementorHooks() {
        if (hooksBound || !window.elementorFrontend || !window.elementorFrontend.hooks) {
            return;
        }

        window.elementorFrontend.hooks.addAction('frontend/element_ready/form.default', function (scope) {
            initialize(scope && scope[0] ? scope[0] : scope);
        });
        hooksBound = true;
    }

    function start() {
        initialize(document);
        bindElementorHooks();

        // Covers forms inserted by popups or other dynamic page content.
        if (window.MutationObserver) {
            var observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node.nodeType === 1) {
                            initialize(node);
                        }
                    });
                });
            });
            observer.observe(document.documentElement, { childList: true, subtree: true });
        }
    }

    if (window.jQuery) {
        window.jQuery(window).on('elementor/frontend/init', bindElementorHooks);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
