(function () {
    'use strict';

    var labels, activeWatches = {};

    function init() {
        if (!window.WPOKUtils || !window.wpokAdmin || !window.wpokAdmin.labels) {
            return;
        }

        labels = window.wpokAdmin.labels;

        /* Bind each job panel independently */
        document.querySelectorAll('[data-job-panel]').forEach(function (panel) {
            bindPanel(panel);
        });

        initImageJobs();
        initOrphanPanel();
    }

    /* ============================================================
       JOB PANELS (Batch Convert + Re-compress)
       ============================================================ */

    function bindPanel(panel) {
        panel.addEventListener('click', function (event) {
            var target = event.target;

            if (target.closest('[data-action="scan"]')) {
                scanPanel(panel);
                return;
            }

            if (target.closest('[data-action="start"]')) {
                startJob(panel);
                return;
            }

            var directoryHead = target.closest('.wpok-job-directory-head');

            if (directoryHead && !target.closest('input[type="checkbox"]')) {
                directoryHead.parentElement.classList.toggle('is-open');
                var toggle = directoryHead.querySelector('.wpok-job-directory-toggle');
                toggle.textContent = directoryHead.parentElement.classList.contains('is-open') ? '-' : '+';
            }
        });

        panel.addEventListener('change', function (event) {
            var target = event.target;

            if (target.matches('[data-role="select-all"]')) {
                panel.querySelectorAll('.wpok-job-item input[type="checkbox"], .wpok-job-directory-head input[type="checkbox"]').forEach(function (checkbox) {
                    checkbox.checked = target.checked;
                });
                updateActionLabel(panel);
                return;
            }

            if (target.matches('.wpok-job-directory-head input[type="checkbox"]')) {
                var directory = target.closest('.wpok-job-directory');
                directory.querySelectorAll('.wpok-job-item input[type="checkbox"]').forEach(function (checkbox) {
                    checkbox.checked = target.checked;
                });
                updateActionLabel(panel);
                return;
            }

            if (target.matches('.wpok-job-item input[type="checkbox"]')) {
                updateActionLabel(panel);
            }
        });
    }

    function scanPanel(panel) {
        var endpoint = panel.getAttribute('data-scan-endpoint');
        var results = panel.querySelector('[data-role="results"]');
        var toolbar = panel.querySelector('[data-role="toolbar"]');

        results.innerHTML = buildScanProgressHtml();
        toolbar.hidden = true;

        window.WPOKUtils.request(endpoint, { method: 'POST', body: {} })
            .then(function (response) {
                renderScanResults(panel, response.directories || {});
            })
            .catch(function (error) {
                results.innerHTML = '<div class="wpok-job-empty">' + escapeHtml(error.message) + '</div>';
            });
    }

    function renderScanResults(panel, directories) {
        var results = panel.querySelector('[data-role="results"]');
        var toolbar = panel.querySelector('[data-role="toolbar"]');
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
        updateActionLabel(panel);
    }

    function startJob(panel) {
        var ids = Array.prototype.map.call(
            panel.querySelectorAll('.wpok-job-item input[type="checkbox"]:checked'),
            function (checkbox) {
                return parseInt(checkbox.closest('.wpok-job-item').getAttribute('data-attachment-id'), 10);
            }
        ).filter(Boolean);

        if (ids.length === 0) {
            panel.querySelector('[data-role="results"]').insertAdjacentHTML('afterbegin', '<div class="wpok-job-empty">' + escapeHtml(labels.selectOne || 'Select at least one attachment first.') + '</div>');
            return;
        }

        window.WPOKUtils.request('jobs', {
            method: 'POST',
            body: {
                module: 'image',
                job_type: panel.getAttribute('data-job-type'),
                attachment_ids: ids
            }
        }).then(function (response) {
            addNewJobToList(response.job);
        }).catch(function (error) {
            panel.querySelector('[data-role="results"]').insertAdjacentHTML('afterbegin', '<div class="wpok-job-empty">' + escapeHtml(error.message) + '</div>');
        });
    }

    function updateActionLabel(panel) {
        var button = panel.querySelector('[data-action="start"]');

        if (!button) {
            return;
        }

        var total = panel.querySelectorAll('.wpok-job-item').length;
        var selected = panel.querySelectorAll('.wpok-job-item input[type="checkbox"]:checked').length;
        var isRecompress = panel.getAttribute('data-job-type') === 'image_recompress';
        var baseLabel = isRecompress ? (labels.qRecompress || 'Queue Re-compression Job') : (labels.qConvert || 'Queue Conversion Job');

        button.textContent = baseLabel + ' (' + selected + '/' + total + ')';
    }

    /* ============================================================
       WORKSPACE JOBS LIST
       ============================================================ */

    function initImageJobs() {
        var container = document.querySelector('[data-image-jobs]');

        if (!container) {
            return;
        }

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
       TREE ITEM UPDATES (polling)
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

                row.querySelector('.wpok-job-item-size').innerHTML =
                    '<span class="wpok-item-size-old">' + escapeHtml(window.WPOKUtils.formatBytes(oldSize)) + '</span>' +
                    '<span class="wpok-item-size-arrow">' + escapeHtml(labels.sizeArrow || '->') + '</span>' +
                    '<span class="wpok-item-size-new">' + escapeHtml(window.WPOKUtils.formatBytes(newSize)) + '</span>';
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
       ORPHAN FILES PANEL
       ============================================================ */

    function initOrphanPanel() {
        var panel = document.querySelector('[data-orphan-panel]');

        if (!panel) {
            return;
        }

        panel.addEventListener('click', function (event) {
            var target = event.target;

            if (target.closest('[data-action="scan"]')) {
                scanOrphanFiles(panel);
                return;
            }

            if (target.closest('[data-action="delete-orphans"]')) {
                deleteOrphanFiles(panel);
                return;
            }

            var directoryHead = target.closest('.wpok-job-directory-head');

            if (directoryHead && !target.closest('input[type="checkbox"]')) {
                directoryHead.parentElement.classList.toggle('is-open');
                var toggle = directoryHead.querySelector('.wpok-job-directory-toggle');
                toggle.textContent = directoryHead.parentElement.classList.contains('is-open') ? '-' : '+';
            }
        });

        panel.addEventListener('change', function (event) {
            var target = event.target;

            if (target.matches('[data-role="select-all"]')) {
                panel.querySelectorAll('.wpok-job-item input[type="checkbox"], .wpok-job-directory-head input[type="checkbox"]').forEach(function (checkbox) {
                    checkbox.checked = target.checked;
                });
                updateOrphanDeleteBtn(panel);
                return;
            }

            if (target.matches('.wpok-job-directory-head input[type="checkbox"]')) {
                var directory = target.closest('.wpok-job-directory');
                directory.querySelectorAll('.wpok-job-item input[type="checkbox"]').forEach(function (checkbox) {
                    checkbox.checked = target.checked;
                });
                updateOrphanDeleteBtn(panel);
                return;
            }

            if (target.matches('.wpok-job-item input[type="checkbox"]')) {
                updateOrphanDeleteBtn(panel);
            }
        });
    }

    function scanOrphanFiles(panel) {
        var endpoint = panel.getAttribute('data-orphan-endpoint');
        var results = panel.querySelector('[data-role="results"]');
        var toolbar = panel.querySelector('[data-role="toolbar"]');

        results.innerHTML = buildScanProgressHtml();
        toolbar.hidden = true;

        window.WPOKUtils.request(endpoint + '/scan', { method: 'POST', body: {} })
            .then(function (response) {
                renderOrphanResults(panel, response.directories || {});
            })
            .catch(function (error) {
                results.innerHTML = '<div class="wpok-job-empty">' + escapeHtml(error.message) + '</div>';
            });
    }

    function renderOrphanResults(panel, directories) {
        var results = panel.querySelector('[data-role="results"]');
        var toolbar = panel.querySelector('[data-role="toolbar"]');
        var directoryNames = Object.keys(directories || {});

        if (directoryNames.length === 0) {
            results.innerHTML = '<div class="wpok-job-empty">' + escapeHtml(labels.noOrphans || 'No orphan files were found.') + '</div>';
            toolbar.hidden = true;
            return;
        }

        var totalItems = 0;
        var html = '<div class="wpok-job-tree">';
        html += '<div class="wpok-job-tree-toolbar">';
        html += '<label><input type="checkbox" data-role="select-all" checked> ' + escapeHtml(labels.selectAll || 'Select all') + '</label>';
        html += '<span>' + directoryNames.length + ' ' + escapeHtml(labels.directories || 'directories') + '<span class="wpok-orphan-total"> | ' + escapeHtml(labels.totalLower || 'total') + ' ';

        directoryNames.forEach(function (dn) {
            totalItems += (directories[dn] || []).length;
        });

        html += String(totalItems) + ' ' + escapeHtml(labels.items || 'items') + '</span></span>';
        html += '</div>';
        html += '<ul class="wpok-job-tree-list">';

        directoryNames.forEach(function (directoryName) {
            var items = directories[directoryName] || [];
            html += '<li class="wpok-job-directory">';
            html += '<div class="wpok-job-directory-head">';
            html += '<input type="checkbox" checked>';
            html += '<span class="wpok-job-directory-toggle">+</span>';
            html += '<span class="wpok-job-directory-name">' + escapeHtml(directoryName) + '</span>';
            html += '<span class="wpok-job-directory-meta">' + items.length + ' ' + escapeHtml(labels.items || 'items') + '</span>';
            html += '</div>';
            html += '<ul class="wpok-job-item-list">';

            items.forEach(function (item) {
                html += '<li class="wpok-job-item" data-file-path="' + escapeHtml(item.path || '') + '" data-size-bytes="' + item.size + '">';
                html += '<input type="checkbox" checked>';
                html += '<span class="wpok-job-item-name">' + escapeHtml(item.name) + '</span>';
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

        toolbar.innerHTML = '<button type="button" class="button wpok-btn-danger" data-action="delete-orphans">' + escapeHtml(labels.deletePermanent || 'Delete Permanently') + ' (' + totalItems + '/' + totalItems + ')</button>';
    }

    function updateOrphanDeleteBtn(panel) {
        var total = panel.querySelectorAll('.wpok-job-item').length;
        var selected = panel.querySelectorAll('.wpok-job-item input[type="checkbox"]:checked').length;
        var delBtn = panel.querySelector('[data-action="delete-orphans"]');

        if (delBtn) {
            delBtn.textContent = (labels.deletePermanent || 'Delete Permanently') + ' (' + selected + '/' + total + ')';
        }
    }

    function deleteOrphanFiles(panel) {
        var items = Array.prototype.map.call(
            panel.querySelectorAll('.wpok-job-item input[type="checkbox"]:checked'),
            function (checkbox) {
                return { file: checkbox.closest('.wpok-job-item').getAttribute('data-file-path') || '' };
            }
        ).filter(function (i) { return i.file !== ''; });

        if (items.length === 0) {
            return;
        }

        var msg = labels.confirmOrphan || 'Permanently delete %d orphan files? This cannot be undone.';

        if (!confirm(msg.replace(/%d/g, String(items.length)))) {
            return;
        }

        var results = panel.querySelector('[data-role="results"]');
        var toolbar = panel.querySelector('[data-role="toolbar"]');

        toolbar.hidden = true;
        results.innerHTML = '<div class="wpok-job-empty">' + escapeHtml(labels.deleting || 'Deleting...') + '</div>';

        window.WPOKUtils.request('media-orphan/delete', {
            method: 'POST',
            body: { items: items }
        }).then(function (response) {
            var success = response.success || 0;
            var fail = response.fail || 0;

            results.innerHTML = '<div class="wpok-job-empty">'
                + '<strong>' + escapeHtml(labels.deleteResult || 'Delete completed.') + '</strong><br>'
                + escapeHtml(String(success)) + ' ' + escapeHtml(labels.successLower || 'succeeded') + ', '
                + escapeHtml(String(fail)) + ' ' + escapeHtml(labels.failedLower || 'failed') + '.'
                + '</div>';
        }).catch(function (error) {
            results.innerHTML = '<div class="wpok-job-empty">' + escapeHtml(error.message) + '</div>';
        });
    }

    /* ============================================================
       HELPERS
       ============================================================ */

    function buildScanProgressHtml(label) {
        label = label || escapeHtml(labels.scanning || 'Scanning...');

        return '<div class="wpok-scan-progress">'
            + '<div class="wpok-scan-progress-bar"><span></span></div>'
            + '<span>' + label + '</span>'
            + '</div>';
    }

    function escapeHtml(value) {
        return window.WPOKUtils.escapeHtml(value);
    }

    document.addEventListener('DOMContentLoaded', init);
})();
