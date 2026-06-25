(function () {
    'use strict';

    if (!window.wpokAdmin) {
        return;
    }

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
                    var message = data && data.message ? data.message : 'Request failed';
                    throw new Error(message);
                }

                return data;
            });
        });
    }

    function formatBytes(bytes) {
        var size = parseInt(bytes, 10) || 0;

        if (size < 1024) {
            return size + ' B';
        }

        if (size < 1024 * 1024) {
            return scaled(size / 1024, 'KB');
        }

        if (size < 1024 * 1024 * 1024) {
            return scaled(size / (1024 * 1024), 'MB');
        }

        return scaled(size / (1024 * 1024 * 1024), 'GB');
    }

    function scaled(value, unit) {
        var digits = value >= 100 ? 1 : 2;
        var formatted = value.toFixed(digits).replace(/\.0+$/, '').replace(/(\.\d*[1-9])0+$/, '$1');

        return formatted + ' ' + unit;
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

    function initRecentJobs() {
        var container = document.querySelector('[data-recent-jobs]');

        if (!container) {
            return;
        }

        var clearButton = container.querySelector('[data-action="clear-completed"]');
        var refreshButton = container.querySelector('[data-action="refresh-jobs"]');
        var state = {
            isRefreshing: false,
            refreshTimer: null
        };

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                clearButton.disabled = true;

                request('jobs/clear-completed', { method: 'POST', body: {} })
                    .then(function (response) {
                        renderRecentJobs(container, response.jobs || []);
                    })
                    .catch(function (error) {
                        setRecentJobsMessage(container, error.message);
                    })
                    .finally(function () {
                        clearButton.disabled = false;
                        syncRefreshButtons(container, state);
                    });
            });
        }

        container.addEventListener('click', function (event) {
            var cancelButton = event.target.closest('[data-action="cancel-job"]');

            if (!cancelButton) {
                return;
            }

            var jobId = cancelButton.getAttribute('data-job-id');

            if (!jobId) {
                return;
            }

            cancelButton.disabled = true;
            cancelButton.textContent = 'Cancelling...';

            request('jobs/' + jobId + '/cancel', { method: 'POST', body: {} })
                .then(function (response) {
                    refreshRecentJobs(container, state);
                })
                .catch(function (error) {
                    setRecentJobsMessage(container, error.message);
                    refreshRecentJobs(container, state);
                });
        });

        refreshRecentJobs(container, state);
    }

    function clearRecentJobsTimer(state) {
        if (state.refreshTimer) {
            window.clearTimeout(state.refreshTimer);
            state.refreshTimer = null;
        }
    }

    function refreshRecentJobs(container, state) {
        var limit = parseInt(container.getAttribute('data-limit'), 10) || 8;

        if (state && state.isRefreshing) {
            return;
        }

        if (state) {
            state.isRefreshing = true;
            syncRefreshButtons(container, state);
        }

        request('jobs?limit=' + limit)
            .then(function (response) {
                var jobs = response.jobs || [];
                var activeJobs = jobs.filter(function (job) {
                    var status = job.status || 'pending';
                    return status === 'pending' || status === 'processing' || status === 'cancelling';
                });

                if (!activeJobs.length) {
                    renderRecentJobs(container, jobs, state);
                    return null;
                }

                return tickRecentJobs(activeJobs).then(function () {
                    return request('jobs?limit=' + limit).then(function (updatedResponse) {
                        renderRecentJobs(container, updatedResponse.jobs || [], state);
                    });
                });
            })
            .catch(function (error) {
                setRecentJobsMessage(container, error.message);
            })
            .finally(function () {
                if (state) {
                    state.isRefreshing = false;
                    syncRefreshButtons(container, state);
                    scheduleRecentJobsRefresh(container, state);
                }
            });
    }

    function tickRecentJobs(jobs) {
        var chain = Promise.resolve();

        jobs.forEach(function (job) {
            chain = chain.then(function () {
                return request('jobs/' + job.id).catch(function () {
                    return null;
                });
            });
        });

        return chain;
    }

    function renderRecentJobs(container, jobs, state) {
        var content = container.querySelector('[data-role="recent-jobs-content"]');
        var clearButton = container.querySelector('[data-action="clear-completed"]');
        var hasCompleted = false;
        var hasActive = false;

        if (!jobs.length) {
            content.innerHTML = '<p class="description">No queue activity has been recorded yet.</p>';
            if (clearButton) {
                clearButton.disabled = true;
            }
            if (state) {
                state.nextRefreshMs = 10000;
            }
            return;
        }

        var html = '';
        html += '<table class="widefat striped wpok-job-table">';
        html += '<thead><tr><th>ID</th><th>Module</th><th>Type</th><th>Status</th><th>Progress</th><th>Updated</th><th class="wpok-job-actions-col">Action</th></tr></thead>';
        html += '<tbody>';

        jobs.forEach(function (job) {
            var total = parseInt(job.total_items, 10) || 0;
            var processed = parseInt(job.processed_items, 10) || 0;
            var percent = total > 0 ? Math.round((processed / total) * 100) : 0;
            var status = job.status || 'pending';

            if (status === 'pending' || status === 'processing' || status === 'cancelling') {
                hasActive = true;
            } else {
                hasCompleted = true;
            }

            html += '<tr>';
            html += '<td>#' + escapeHtml(job.id) + '</td>';
            html += '<td>' + escapeHtml(job.module) + '</td>';
            html += '<td>' + escapeHtml(job.job_type) + '</td>';
            html += '<td><span class="wpok-job-status is-' + escapeHtml(status) + '">' + escapeHtml(status) + '</span></td>';
            html += '<td><div class="wpok-inline-progress">';
            html += '<div class="wpok-progress-bar is-compact"><span style="width:' + percent + '%;"></span></div>';
            html += '<span class="wpok-progress-count">' + processed + ' / ' + total + '</span>';
            html += '</div></td>';
            html += '<td>' + escapeHtml(job.updated_at) + '</td>';
            html += '<td class="wpok-job-actions-cell">';
            if (status === 'pending' || status === 'processing' || status === 'cancelling') {
                html += '<button type="button" class="button button-secondary" data-action="cancel-job" data-job-id="' + escapeHtml(job.id) + '"' + (status === 'cancelling' ? ' disabled' : '') + '>';
                html += status === 'cancelling' ? 'Cancelling...' : 'Cancel';
                html += '</button>';
            } else {
                html += '<span class="description">-</span>';
            }
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';

        content.innerHTML = html;

        if (clearButton) {
            clearButton.disabled = !hasCompleted;
        }

        if (state) {
            state.nextRefreshMs = hasActive ? 2000 : 10000;
        }
    }

    function setRecentJobsMessage(container, message) {
        var content = container.querySelector('[data-role="recent-jobs-content"]');

        if (content) {
            content.innerHTML = '<p class="description">' + escapeHtml(message) + '</p>';
        }
    }

    function syncRefreshButtons(container, state) {
        var refreshButton = container.querySelector('[data-action="refresh-jobs"]');

        if (refreshButton) {
            refreshButton.disabled = !!state.isRefreshing;
        }
    }

    function scheduleRecentJobsRefresh(container, state) {
        clearRecentJobsTimer(state);
        state.refreshTimer = window.setTimeout(function () {
            refreshRecentJobs(container, state);
        }, state.nextRefreshMs || 10000);
    }

    window.WPOKUtils = {
        request: request,
        formatBytes: formatBytes
    };

    document.addEventListener('DOMContentLoaded', function () {
        initTabs();
        initRecentJobs();
    });
})();
