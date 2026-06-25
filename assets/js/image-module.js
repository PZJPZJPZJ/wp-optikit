(function () {
    'use strict';

    function init() {
        if (!window.WPOKUtils || !window.wpokAdmin || !window.wpokAdmin.labels) {
            return;
        }

        var app = document.querySelector('[data-wpok-image-app]');

        if (!app) {
            return;
        }

        app.querySelectorAll('[data-job-panel]').forEach(function (panel) {
            bindPanel(panel);
        });
    }

    var labels = window.wpokAdmin.labels;

    function bindPanel(panel) {
        var state = {
            jobId: null,
            poller: null,
            freezeCount: null
        };

        panel.addEventListener('click', function (event) {
            var target = event.target;

            if (target.closest('[data-action="scan"]')) {
                scanPanel(panel);
                return;
            }

            if (target.closest('[data-action="start"]')) {
                startJob(panel, state);
                return;
            }

            if (target.closest('[data-action="cancel"]')) {
                cancelJob(panel, state);
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

        results.innerHTML = '<div class="wpok-job-empty">' + escapeHtml(labels.scanning || 'Scanning...') + '</div>';
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

        directoryNames.forEach(function (directoryName, index) {
            var items = directories[directoryName] || [];
            total += items.length;
            html += '<li class="wpok-job-directory ' + (index === 0 ? 'is-open' : '') + '">';
            html += '<div class="wpok-job-directory-head">';
            html += '<input type="checkbox" checked>';
            html += '<span class="wpok-job-directory-toggle">' + (index === 0 ? '-' : '+') + '</span>';
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

    function startJob(panel, state) {
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
            state.jobId = response.job.id;
            state.freezeCount = null;
            setProgress(panel, response.job, labels.jobCreated || 'Job created. Waiting for worker.');
            panel.querySelector('[data-role="progress"]').hidden = false;
            watchJob(panel, state);
        }).catch(function (error) {
            setMessage(panel, error.message);
        });
    }

    function watchJob(panel, state) {
        if (state.poller) {
            window.clearInterval(state.poller);
        }

        state.poller = window.setInterval(function () {
            window.WPOKUtils.request('jobs/' + state.jobId)
            .then(function (response) {
                if (state.freezeCount !== null && (response.job.status === 'cancelling' || response.job.status === 'cancelled')) {
                    response.job.processed_items = state.freezeCount;
                }

                setProgress(panel, response.job, labels.processingMsg || 'Processing through the background queue.');

                return window.WPOKUtils.request('jobs/' + state.jobId + '/items');
                })
                .then(function (payload) {
                    updateItems(panel, payload.items || []);

                    var status = payload.job.status;

                    if (status === 'succeeded' || status === 'failed' || status === 'cancelled') {
                        window.clearInterval(state.poller);
                        state.poller = null;
                        var finishedMsg = (labels.jobFinished || 'Job finished with status: %s.').replace('%s', status);
                        setProgress(panel, payload.job, finishedMsg);
                    }
                })
                .catch(function (error) {
                    window.clearInterval(state.poller);
                    state.poller = null;
                    setMessage(panel, error.message);
                });
        }, 2000);
    }

    function cancelJob(panel, state) {
        if (!state.jobId) {
            return;
        }

        var countText = panel.querySelector('[data-role="job-count"]').textContent || '0 / 0';
        state.freezeCount = parseInt(countText.split('/')[0], 10) || 0;

        window.WPOKUtils.request('jobs/' + state.jobId + '/cancel', { method: 'POST', body: {} })
            .then(function (response) {
                if (state.freezeCount !== null) {
                    response.job.processed_items = state.freezeCount;
                }
                setProgress(panel, response.job, labels.cancelReq || 'Job cancellation requested.');
            })
            .catch(function (error) {
                setMessage(panel, error.message);
            });
    }

    function updateItems(panel, items) {
        var isRecompressPanel = panel.getAttribute('data-job-type') === 'image_recompress';

        items.forEach(function (item) {
            var row = panel.querySelector('.wpok-job-item[data-attachment-id="' + item.object_id + '"]');

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

                if (isRecompressPanel && item.status === 'succeeded' && delta < 1024) {
                    row.classList.add('is-unchanged');
                    statusNode.textContent = labels.noChange || 'No change';
                    newSizeClass = 'wpok-item-size-new wpok-item-size-neutral';
                }

                row.setAttribute('data-size-bytes', item.result.new_size_bytes);
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

    function setProgress(panel, job, message) {
        var progress = panel.querySelector('[data-role="progress"]');
        progress.hidden = false;
        panel.querySelector('[data-role="job-status"]').textContent = window.WPOKUtils.humanizeJobStatus(job.status);
        panel.querySelector('[data-role="job-count"]').textContent = job.processed_items + ' / ' + job.total_items;
        panel.querySelector('[data-role="job-message"]').textContent = message;

        var percent = job.total_items > 0 ? Math.round((job.processed_items / job.total_items) * 100) : 0;
        panel.querySelector('[data-role="job-bar"]').style.width = percent + '%';
    }

    function setMessage(panel, message) {
        panel.querySelector('[data-role="progress"]').hidden = false;
        panel.querySelector('[data-role="job-message"]').textContent = message;
    }

    function updateActionLabel(panel) {
        var button = panel.querySelector('[data-action="start"]');

        if (!button) {
            return;
        }

        var total = panel.querySelectorAll('.wpok-job-item').length;
        var selected = panel.querySelectorAll('.wpok-job-item input[type="checkbox"]:checked').length;
        var baseLabel = panel.getAttribute('data-job-type') === 'image_convert'
            ? (labels.qConvert || 'Queue Conversion Job')
            : (labels.qRecompress || 'Queue Re-compression Job');

        button.textContent = baseLabel + ' (' + selected + '/' + total + ')';
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

    function escapeHtml(value) {
        return window.WPOKUtils.escapeHtml(value);
    }

    document.addEventListener('DOMContentLoaded', init);
})();
