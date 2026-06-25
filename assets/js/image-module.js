(function () {
    'use strict';

    var labels, activeWatches = {}, currentPanel = null;

    function init() {
        if (!window.WPOKUtils || !window.wpokAdmin || !window.wpokAdmin.labels) {
            return;
        }

        labels = window.wpokAdmin.labels;

        currentPanel = document.querySelector('[data-scan-panel]');

        if (currentPanel) {
            bindModeToggle();
            bindScanPanelEvents();
        }

        initImageJobs();
    }

    /* ============================================================
       MODE TOGGLE
       ============================================================ */

    function bindModeToggle() {
        var toggle = document.querySelector('[data-scan-mode-toggle]');

        if (!toggle) {
            return;
        }

        toggle.addEventListener('click', function (event) {
            var btn = event.target.closest('[data-scan-mode]');

            if (!btn || btn.classList.contains('is-active')) {
                return;
            }

            /* Update active state */
            toggle.querySelectorAll('[data-scan-mode]').forEach(function (b) {
                b.classList.remove('is-active');
            });
            btn.classList.add('is-active');

            /* Clear previous results */
            var results = currentPanel.querySelector('[data-role="results"]');
            var toolbar = currentPanel.querySelector('[data-role="toolbar"]');

            results.innerHTML = '';
            toolbar.hidden = true;

            /* Update title, description, scan button from data attributes */
            var title = btn.getAttribute('data-mode-title');
            var desc = btn.getAttribute('data-mode-desc');
            var scanBtn = btn.getAttribute('data-scan-btn');

            if (title) {
                currentPanel.querySelector('[data-mode-title]').textContent = title;
            }

            if (desc) {
                currentPanel.querySelector('[data-mode-desc]').textContent = desc;
            }

            if (scanBtn) {
                currentPanel.querySelector('[data-action="scan"]').textContent = scanBtn;
            }
        });
    }

    function getActiveMode() {
        var active = document.querySelector('[data-scan-mode-toggle] [data-scan-mode].is-active');

        return active ? active.getAttribute('data-scan-mode') : 'convert';
    }

    function getActiveEndpoint() {
        var active = document.querySelector('[data-scan-mode-toggle] [data-scan-mode].is-active');

        return active ? active.getAttribute('data-scan-endpoint') : 'images/scans/non-webp';
    }

    function getActiveJobType() {
        var active = document.querySelector('[data-scan-mode-toggle] [data-scan-mode].is-active');

        return active ? active.getAttribute('data-job-type') : 'image_convert';
    }

    function getActiveQueueLabel() {
        return getActiveMode() === 'recompress'
            ? (labels.qRecompress || 'Queue Re-compression Job')
            : (labels.qConvert || 'Queue Conversion Job');
    }

    /* ============================================================
       PANEL EVENTS (scan + queue submission)
       ============================================================ */

    function bindScanPanelEvents() {
        currentPanel.addEventListener('click', function (event) {
            var target = event.target;

            if (target.closest('[data-action="scan"]')) {
                scanPanel();
                return;
            }

            if (target.closest('[data-action="start"]')) {
                startJob();
                return;
            }

            var directoryHead = target.closest('.wpok-job-directory-head');

            if (directoryHead && !target.closest('input[type="checkbox"]')) {
                directoryHead.parentElement.classList.toggle('is-open');
                var toggle = directoryHead.querySelector('.wpok-job-directory-toggle');
                toggle.textContent = directoryHead.parentElement.classList.contains('is-open') ? '-' : '+';
            }
        });

        currentPanel.addEventListener('change', function (event) {
            var target = event.target;

            if (target.matches('[data-role="select-all"]')) {
                currentPanel.querySelectorAll('.wpok-job-item input[type="checkbox"], .wpok-job-directory-head input[type="checkbox"]').forEach(function (checkbox) {
                    checkbox.checked = target.checked;
                });
                updateActionLabel();
                return;
            }

            if (target.matches('.wpok-job-directory-head input[type="checkbox"]')) {
                var directory = target.closest('.wpok-job-directory');
                directory.querySelectorAll('.wpok-job-item input[type="checkbox"]').forEach(function (checkbox) {
                    checkbox.checked = target.checked;
                });
                updateActionLabel();
                return;
            }

            if (target.matches('.wpok-job-item input[type="checkbox"]')) {
                updateActionLabel();
            }
        });
    }

    function scanPanel() {
        var endpoint = getActiveEndpoint();
        var results = currentPanel.querySelector('[data-role="results"]');
        var toolbar = currentPanel.querySelector('[data-role="toolbar"]');

        results.innerHTML = '<div class="wpok-job-empty">' + escapeHtml(labels.scanning || 'Scanning...') + '</div>';
        toolbar.hidden = true;

        window.WPOKUtils.request(endpoint, { method: 'POST', body: {} })
            .then(function (response) {
                renderScanResults(response.directories || {});
            })
            .catch(function (error) {
                results.innerHTML = '<div class="wpok-job-empty">' + escapeHtml(error.message) + '</div>';
            });
    }

    function renderScanResults(directories) {
        var results = currentPanel.querySelector('[data-role="results"]');
        var toolbar = currentPanel.querySelector('[data-role="toolbar"]');
        var directoryNames = Object.keys(directories || {});

        if (directoryNames.length === 0) {
            results.innerHTML = '<div class="wpok-job-empty">' + escapeHtml(labels.noAttachments || 'No matching attachments were found.') + '</div>';
            toolbar.hidden = true;
            return;
        }

        var total = 0;
        var html = '<div class="wpok-job-tree">';
        html += '<div class="wpok-job-tree-toolbar">';
        html += '<label><input type="checkbox" data-role="select-all" checked> ' + escapeHtml(labels.selectAll || 'Select all') + '</label>';
        html += '<span>' + directoryNames.length + ' ' + escapeHtml(labels.directories || 'directories') + '</span>';
        html += '</div>';
        html += '<ul class="wpok-job-tree-list">';

        directoryNames.forEach(function (directoryName) {
            var items = directories[directoryName] || [];
            total += items.length;
            html += '<li class="wpok-job-directory">';
            html += '<div class="wpok-job-directory-head">';
            html += '<input type="checkbox" checked>';
            html += '<span class="wpok-job-directory-toggle">+</span>';
            html += '<span class="wpok-job-directory-name">' + escapeHtml(directoryName) + '</span>';
            html += '<span class="wpok-job-directory-meta">' + items.length + ' ' + escapeHtml(labels.items || 'items') + '</span>';
            html += '</div>';
            html += '<ul class="wpok-job-item-list">';

            items.forEach(function (item) {
                html += '<li class="wpok-job-item" data-attachment-id="' + item.id + '" data-size-bytes="' + item.size + '">';
                html += '<input type="checkbox" checked>';
                html += '<span class="wpok-job-item-name">' + escapeHtml(item.name) + '</span>';
                html += '<span class="wpok-job-item-status">' + escapeHtml(labels.itemReady || 'ready') + '</span>';
                html += '<span class="wpok-job-item-size">' + escapeHtml(window.WPOKUtils.formatBytes(item.size)) + '</span>';
                html += '</li>';
            });

            html += '</ul>';
            html += '</li>';
        });

        html += '</ul>';
        html += '</div>';

        results.innerHTML = html;
        toolbar.hidden = false;
        updateActionLabel();
    }

    function startJob() {
        var ids = Array.prototype.map.call(
            currentPanel.querySelectorAll('.wpok-job-item input[type="checkbox"]:checked'),
            function (checkbox) {
                return parseInt(checkbox.closest('.wpok-job-item').getAttribute('data-attachment-id'), 10);
            }
        ).filter(Boolean);

        if (ids.length === 0) {
            currentPanel.querySelector('[data-role="results"]').insertAdjacentHTML('afterbegin', '<div class="wpok-job-empty">' + escapeHtml(labels.selectOne || 'Select at least one attachment first.') + '</div>');
            return;
        }

        window.WPOKUtils.request('jobs', {
            method: 'POST',
            body: {
                module: 'image',
                job_type: getActiveJobType(),
                attachment_ids: ids
            }
        }).then(function (response) {
            addNewJobToList(response.job);
        }).catch(function (error) {
            currentPanel.querySelector('[data-role="results"]').insertAdjacentHTML('afterbegin', '<div class="wpok-job-empty">' + escapeHtml(error.message) + '</div>');
        });
    }

    function updateActionLabel() {
        var button = currentPanel.querySelector('[data-action="start"]');

        if (!button) {
            return;
        }

        var total = currentPanel.querySelectorAll('.wpok-job-item').length;
        var selected = currentPanel.querySelectorAll('.wpok-job-item input[type="checkbox"]:checked').length;

        button.textContent = getActiveQueueLabel() + ' (' + selected + '/' + total + ')';
    }

    /* ============================================================
       WORKSPACE JOBS LIST
       ============================================================ */

    function initImageJobs() {
        var container = document.querySelector('[data-image-jobs]');

        if (!container) {
            return;
        }

        /* Clear completed */
        var clearBtn = container.querySelector('[data-action="clear-completed"]');

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                clearBtn.disabled = true;

                window.WPOKUtils.request('jobs/clear-completed', { method: 'POST', body: {} })
                    .then(function (response) {
                        var jobs = response.jobs || [];
                        var imageJobs = jobs.filter(function (j) {
                            return j.module === 'image';
                        });

                        stopAllWatches();

                        if (imageJobs.length > 0) {
                            renderJobsList(container, imageJobs);
                            imageJobs.forEach(function (job) {
                                if (isActiveStatus(job.status)) {
                                    startWatching(job.id);
                                }
                            });
                        } else {
                            showEmpty(container);
                        }
                    })
                    .finally(function () {
                        clearBtn.disabled = false;
                    });
            });
        }

        /* Load existing jobs on page load */
        window.WPOKUtils.request('jobs?limit=20').then(function (response) {
            var jobs = response.jobs || [];
            var imageJobs = jobs.filter(function (j) {
                return j.module === 'image';
            });

            if (imageJobs.length > 0) {
                renderJobsList(container, imageJobs);
                imageJobs.forEach(function (job) {
                    if (isActiveStatus(job.status)) {
                        startWatching(job.id);
                    }
                });
            } else {
                showEmpty(container);
            }
        });
    }

    function addNewJobToList(job) {
        var container = document.querySelector('[data-image-jobs]');

        if (!container) {
            return;
        }

        var empty = container.querySelector('.wpok-image-jobs-empty');

        if (empty) {
            empty.remove();
        }

        var table = container.querySelector('.wpok-image-jobs-table');
        var content = container.querySelector('[data-role="image-jobs-content"]');

        if (!table) {
            content.innerHTML = ''
                + '<table class="wpok-image-jobs-table">'
                + '<thead><tr>'
                + '<th>' + escapeHtml(labels.tableId || 'ID') + '</th>'
                + '<th>' + escapeHtml(labels.tableType || 'Type') + '</th>'
                + '<th>' + escapeHtml(labels.tableStatus || 'Status') + '</th>'
                + '<th>' + escapeHtml(labels.tableProgress || 'Progress') + '</th>'
                + '<th class="wpok-job-actions-col">' + escapeHtml(labels.tableAction || 'Action') + '</th>'
                + '</tr></thead>'
                + '<tbody></tbody>'
                + '</table>';
            table = content.querySelector('.wpok-image-jobs-table');
        }

        var tbody = table.querySelector('tbody');
        tbody.insertAdjacentHTML('afterbegin', buildJobRow(job));

        if (isActiveStatus(job.status)) {
            startWatching(job.id);
        }
    }

    function buildJobRow(job) {
        var total = parseInt(job.total_items, 10) || 0;
        var processed = parseInt(job.processed_items, 10) || 0;
        var percent = total > 0 ? Math.round((processed / total) * 100) : 0;
        var active = isActiveStatus(job.status);

        return '<tr data-job-id="' + job.id + '">'
            + '<td>#' + escapeHtml(String(job.id)) + '</td>'
            + '<td>' + escapeHtml(job.job_type) + '</td>'
            + '<td><span class="wpok-job-status is-' + escapeHtml(job.status) + '">' + escapeHtml(window.WPOKUtils.humanizeJobStatus(job.status)) + '</span></td>'
            + '<td><div class="wpok-inline-progress">'
            + '<div class="wpok-progress-bar is-compact"><span style="width:' + percent + '%;"></span></div>'
            + '<span class="wpok-progress-count">' + processed + ' / ' + total + '</span>'
            + '</div></td>'
            + '<td class="wpok-job-actions-cell">'
            + (active
                ? '<button type="button" class="button button-secondary" data-action="cancel-image-job" data-job-id="' + job.id + '">' + escapeHtml(labels.btnCancel || 'Cancel') + '</button>'
                : '<span class="description">' + escapeHtml(labels.noAction || '-') + '</span>')
            + '</td>'
            + '</tr>';
    }

    function updateJobRow(job) {
        var container = document.querySelector('[data-image-jobs]');

        if (!container) {
            return;
        }

        var row = container.querySelector('tr[data-job-id="' + job.id + '"]');

        if (!row) {
            return;
        }

        var total = parseInt(job.total_items, 10) || 0;
        var processed = parseInt(job.processed_items, 10) || 0;
        var percent = total > 0 ? Math.round((processed / total) * 100) : 0;

        var badge = row.querySelector('.wpok-job-status');

        if (badge) {
            badge.className = 'wpok-job-status is-' + escapeHtml(job.status);
            badge.textContent = window.WPOKUtils.humanizeJobStatus(job.status);
        }

        var barSpan = row.querySelector('.wpok-progress-bar span');

        if (barSpan) {
            barSpan.style.width = percent + '%';
        }

        var count = row.querySelector('.wpok-progress-count');

        if (count) {
            count.textContent = processed + ' / ' + total;
        }

        if (!isActiveStatus(job.status)) {
            var actionCell = row.querySelector('.wpok-job-actions-cell');

            if (actionCell) {
                actionCell.innerHTML = '<span class="description">' + escapeHtml(labels.noAction || '-') + '</span>';
            }
        }
    }

    /* ============================================================
       TREE ITEM UPDATES
       ============================================================ */

    function updateScanResults(items) {
        items.forEach(function (item) {
            var row = document.querySelector('.wpok-job-item[data-attachment-id="' + item.object_id + '"]');

            if (!row) {
                return;
            }

            row.classList.remove('is-pending', 'is-processing', 'is-succeeded', 'is-failed', 'is-skipped', 'is-cancelled', 'is-unchanged');
            row.classList.add('is-' + item.status);

            var statusNode = row.querySelector('.wpok-job-item-status');
            statusNode.textContent = humanizeItemStatus(item.status);

            if (item.result && typeof item.result.new_size_bytes !== 'undefined') {
                var oldSize = parseInt(item.result.old_size_bytes || 0, 10) || 0;
                var newSize = parseInt(item.result.new_size_bytes || 0, 10) || 0;
                var delta = Math.abs(oldSize - newSize);
                var newSizeClass = 'wpok-item-size-new';

                if (getActiveJobType() === 'image_recompress' && item.status === 'succeeded' && delta < 1024) {
                    row.classList.add('is-unchanged');
                    statusNode.textContent = labels.noChange || 'No change';
                    newSizeClass = 'wpok-item-size-new wpok-item-size-neutral';
                }

                row.querySelector('.wpok-job-item-size').innerHTML =
                    '<span class="wpok-item-size-old">' + escapeHtml(window.WPOKUtils.formatBytes(oldSize)) + '</span>' +
                    '<span class="wpok-item-size-arrow">' + escapeHtml(labels.sizeArrow || '->') + '</span>' +
                    '<span class="' + newSizeClass + '">' + escapeHtml(window.WPOKUtils.formatBytes(newSize)) + '</span>';
            }

            if (item.last_error) {
                statusNode.textContent = humanizeItemStatus(item.status) + ': ' + item.last_error;
            }
        });
    }

    function humanizeItemStatus(status) {
        var map = {
            pending: labels.statusPending,
            processing: labels.statusProcessing,
            succeeded: labels.statusSucceeded,
            failed: labels.statusFailed,
            skipped: labels.statusSkipped,
            cancelled: labels.statusCancelled
        };

        return map[status] || status;
    }

    function renderJobsList(container, jobs) {
        var content = container.querySelector('[data-role="image-jobs-content"]');

        if (!jobs.length) {
            showEmpty(container);
            return;
        }

        var html = '<table class="wpok-image-jobs-table">'
            + '<thead><tr>'
            + '<th>' + escapeHtml(labels.tableId || 'ID') + '</th>'
            + '<th>' + escapeHtml(labels.tableType || 'Type') + '</th>'
            + '<th>' + escapeHtml(labels.tableStatus || 'Status') + '</th>'
            + '<th>' + escapeHtml(labels.tableProgress || 'Progress') + '</th>'
            + '<th class="wpok-job-actions-col">' + escapeHtml(labels.tableAction || 'Action') + '</th>'
            + '</tr></thead>'
            + '<tbody>';

        jobs.forEach(function (job) {
            html += buildJobRow(job);
        });

        html += '</tbody></table>';
        content.innerHTML = html;
    }

    function showEmpty(container) {
        var content = container.querySelector('[data-role="image-jobs-content"]');

        if (content) {
            content.innerHTML = '<div class="wpok-image-jobs-empty">' + escapeHtml(labels.noActivity || 'No queue activity has been recorded yet.') + '</div>';
        }
    }

    /* ============================================================
       POLLING
       ============================================================ */

    function startWatching(jobId) {
        if (activeWatches[jobId]) {
            return;
        }

        activeWatches[jobId] = window.setInterval(function () {
            window.WPOKUtils.request('jobs/' + jobId + '/items').then(function (payload) {
                var job = payload.job || {};

                updateJobRow(job);
                updateScanResults(payload.items || []);

                if (!isActiveStatus(job.status)) {
                    stopWatching(jobId);
                }
            }).catch(function () {
                stopWatching(jobId);
            });
        }, 2000);
    }

    function stopWatching(jobId) {
        if (activeWatches[jobId]) {
            window.clearInterval(activeWatches[jobId]);
            delete activeWatches[jobId];
        }
    }

    function stopAllWatches() {
        for (var id in activeWatches) {
            if (activeWatches.hasOwnProperty(id)) {
                window.clearInterval(activeWatches[id]);
            }
        }

        activeWatches = {};
    }

    function isActiveStatus(status) {
        return status === 'pending' || status === 'processing' || status === 'cancelling';
    }

    /* ============================================================
       CANCEL — delegated click
       ============================================================ */

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-action="cancel-image-job"]');

        if (!btn) {
            return;
        }

        var jobId = btn.getAttribute('data-job-id');

        if (!jobId) {
            return;
        }

        btn.disabled = true;

        window.WPOKUtils.request('jobs/' + jobId + '/cancel', { method: 'POST', body: {} })
            .then(function (response) {
                updateJobRow(response.job);

                if (!isActiveStatus(response.job.status)) {
                    stopWatching(jobId);
                }
            })
            .catch(function () {
                btn.disabled = false;
            });
    });

    /* ============================================================
       HELPERS
       ============================================================ */

    function escapeHtml(value) {
        return window.WPOKUtils.escapeHtml(value);
    }

    document.addEventListener('DOMContentLoaded', init);
})();
