(function () {
    'use strict';

    if (!window.wpokAdmin) {
        return;
    }

    var labels = window.wpokAdmin.labels || {};

    function request(path, options) {
        var config = options || {};
        var method = config.method || 'GET';
        var url = window.wpokAdmin.restRoot + path;
        var headers = Object.assign(
            {
                'X-WP-Nonce': window.wpokAdmin.restNonce,
                'Content-Type': 'application/json'
            },
            config.headers || {}
        );

        if (method === 'GET') {
            url += (url.indexOf('?') === -1 ? '?' : '&') + '_wpok_ts=' + Date.now();
        }

        return fetch(url, {
            method: method,
            credentials: 'same-origin',
            cache: 'no-store',
            headers: headers,
            body: config.body ? JSON.stringify(config.body) : undefined
        }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok) {
                    var message = data && data.message ? data.message : (labels.requestFailed || 'Request failed');
                    throw new Error(message);
                }

                return data;
            });
        });
    }

    var UNITS = [
        labels.bytesB || ' B',
        labels.bytesKB || ' KB',
        labels.bytesMB || ' MB',
        labels.bytesGB || ' GB'
    ];

    function formatBytes(bytes) {
        var size = parseInt(bytes, 10) || 0;

        if (size < 1024) {
            return size + UNITS[0];
        }

        if (size < 1024 * 1024) {
            return scaled(size / 1024, UNITS[1]);
        }

        if (size < 1024 * 1024 * 1024) {
            return scaled(size / (1024 * 1024), UNITS[2]);
        }

        return scaled(size / (1024 * 1024 * 1024), UNITS[3]);
    }

    function scaled(value, unit) {
        var digits = value >= 100 ? 1 : 2;
        var formatted = value.toFixed(digits).replace(/\.0+$/, '').replace(/(\.\d*[1-9])0+$/, '$1');

        return formatted + ' ' + unit;
    }

    function humanizeJobStatus(status) {
        var map = {
            pending: labels.statusPending,
            processing: labels.statusProcessing,
            cancelling: labels.statusCancelling,
            succeeded: labels.statusSucceeded,
            failed: labels.statusFailed,
            cancelled: labels.statusCancelled,
            skipped: labels.statusSkipped
        };

        return map[status] || status;
    }

    function initTabs() {
        var triggers = document.querySelectorAll('[data-tab-trigger]');
        var panels = document.querySelectorAll('[data-tab-panel]');

        if (!triggers.length || !panels.length) {
            return;
        }

        var desiredTab = normalizeTab((window.location.hash || '').replace(/^#/, '')) || window.wpokAdmin.activeTab || triggers[0].getAttribute('data-tab-trigger');
        activateTab(desiredTab, triggers, panels, false);

        triggers.forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                activateTab(trigger.getAttribute('data-tab-trigger'), triggers, panels, true);
            });
        });

        window.addEventListener('hashchange', function () {
            var hashTab = normalizeTab((window.location.hash || '').replace(/^#/, ''));

            if (hashTab) {
                activateTab(hashTab, triggers, panels, false);
            }
        });
    }

    function activateTab(tabId, triggers, panels, updateHash) {
        var found = false;

        triggers.forEach(function (trigger) {
            var isActive = trigger.getAttribute('data-tab-trigger') === tabId;
            trigger.classList.toggle('is-active', isActive);
            trigger.setAttribute('aria-pressed', isActive ? 'true' : 'false');

            if (isActive) {
                found = true;
            }
        });

        if (!found) {
            return;
        }

        panels.forEach(function (panel) {
            var isActive = panel.getAttribute('data-tab-panel') === tabId;
            panel.classList.toggle('is-active', isActive);
            panel.hidden = !isActive;
        });

        if (updateHash) {
            window.history.replaceState(null, '', window.location.pathname + window.location.search + '#' + tabId);
        }
    }

    function normalizeTab(value) {
        return /^[a-z0-9_-]+$/.test(value) ? value : '';
    }

    function initHelpTooltips() {
        if (typeof HTMLElement.prototype.showPopover !== 'function') {
            return;
        }

        document.querySelectorAll('.wpok-help-trigger').forEach(function (trigger) {
            var popover = document.getElementById(trigger.getAttribute('popovertarget'));
            var hideTimer;

            function show() {
                window.clearTimeout(hideTimer);
                popover.showPopover();
                positionHelpTooltip(trigger, popover);
            }

            function scheduleHide() {
                window.clearTimeout(hideTimer);
                hideTimer = window.setTimeout(function () {
                    if (!trigger.matches(':hover') && !popover.matches(':hover') && document.activeElement !== trigger) {
                        popover.hidePopover();
                    }
                }, 150);
            }

            trigger.addEventListener('pointerenter', function (event) {
                if (event.pointerType === 'mouse') {
                    show();
                }
            });
            trigger.addEventListener('pointerleave', scheduleHide);
            trigger.addEventListener('focus', function () {
                if (trigger.matches(':focus-visible')) {
                    show();
                }
            });
            trigger.addEventListener('blur', scheduleHide);
            popover.addEventListener('pointerenter', function () {
                window.clearTimeout(hideTimer);
            });
            popover.addEventListener('pointerleave', scheduleHide);
            popover.addEventListener('toggle', function (event) {
                if (event.newState === 'open') {
                    positionHelpTooltip(trigger, popover);
                }
            });
        });

        function repositionTooltips(event) {
            if (event.target instanceof Element && event.target.closest('.wpok-help-popover')) {
                return;
            }
            document.querySelectorAll('.wpok-help-trigger').forEach(function (trigger) {
                var popover = document.getElementById(trigger.getAttribute('popovertarget'));
                if (!popover.matches(':popover-open')) {
                    return;
                }
                var anchor = trigger.getBoundingClientRect();
                if (!trigger.getClientRects().length || anchor.bottom < 0 || anchor.top > document.documentElement.clientHeight) {
                    popover.hidePopover();
                } else {
                    positionHelpTooltip(trigger, popover);
                }
            });
        }

        window.addEventListener('resize', repositionTooltips);
        window.addEventListener('scroll', repositionTooltips, { capture: true, passive: true });
    }

    function positionHelpTooltip(trigger, popover) {
        var anchor = trigger.getBoundingClientRect();
        var box = popover.getBoundingClientRect();
        var viewportWidth = document.documentElement.clientWidth;
        var viewportHeight = document.documentElement.clientHeight;
        var left = Math.max(12, Math.min(anchor.left, viewportWidth - box.width - 12));
        var top = anchor.bottom + 8;

        if (top + box.height > viewportHeight - 12) {
            top = anchor.top - box.height - 8;
        }
        popover.style.inset = 'auto';
        popover.style.margin = '0';
        popover.style.left = left + 'px';
        popover.style.top = Math.max(12, top) + 'px';
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    window.WPOKUtils = {
        request: request,
        formatBytes: formatBytes,
        humanizeJobStatus: humanizeJobStatus,
        escapeHtml: escapeHtml
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-range-display]').forEach(function (slider) {
            var display = slider.parentElement.querySelector('[data-role="quality-value"]');
            if (display) {
                slider.addEventListener('input', function () {
                    display.textContent = slider.value;
                });
            }
        });

        initTabs();
        initHelpTooltips();
    });
})();
