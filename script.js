(function () {
    'use strict';

    function splitTokens(value) {
        var seen = {};
        return (value || '')
            .split(',')
            .map(function (v) { return v.trim(); })
            .filter(function (v) {
                var key = v.toLowerCase();
                if (!v || seen[key]) return false;
                seen[key] = true;
                return true;
            });
    }

    function enhanceTokenPicker(source) {
        if (!source || source.dataset.tokenPickerEnhanced === '1') return;
        source.dataset.tokenPickerEnhanced = '1';

        var listId = source.getAttribute('list');
        var dataList = listId ? document.getElementById(listId) : null;
        var suggestions = [];
        if (dataList) {
            Array.prototype.forEach.call(dataList.querySelectorAll('option'), function (option) {
                var value = (option.value || '').trim();
                if (!value) return;
                var display = (option.getAttribute('data-display-name') || value).trim();
                var label = (option.getAttribute('label') || value).trim();
                suggestions.push({ value: value, display: display || value, label: label || value });
            });
        }

        var tokens = splitTokens(source.value);
        var wrapper = document.createElement('div');
        wrapper.className = 'docapproval-token-picker';

        var chips = document.createElement('div');
        chips.className = 'docapproval-token-chips';

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'edit docapproval-token-input';
        input.placeholder = source.getAttribute('placeholder') || '';
        input.autocomplete = 'off';

        var menu = document.createElement('div');
        menu.className = 'docapproval-token-menu';
        menu.setAttribute('role', 'listbox');
        menu.hidden = true;

        wrapper.appendChild(chips);
        wrapper.appendChild(input);
        wrapper.appendChild(menu);
        source.insertAdjacentElement('afterend', wrapper);
        source.classList.add('docapproval-token-source-hidden');

        var activeIndex = -1;
        var visibleSuggestions = [];

        function syncSource() {
            source.value = tokens.join(', ');
        }

        function hasToken(value) {
            var needle = value.toLowerCase();
            return tokens.some(function (token) { return token.toLowerCase() === needle; });
        }

        function findSuggestion(value) {
            var needle = (value || '').trim().toLowerCase();
            if (!needle) return null;
            for (var i = 0; i < suggestions.length; i++) {
                var item = suggestions[i];
                if (item.value.toLowerCase() === needle ||
                    item.display.toLowerCase() === needle ||
                    item.label.toLowerCase() === needle) return item;
            }
            return null;
        }

        function canonicalToken(raw) {
            var value = (raw || '').trim().replace(/,+$/, '').trim();
            var matched = findSuggestion(value);
            return matched ? matched.value : value;
        }

        function tokenDisplay(value) {
            var matched = findSuggestion(value);
            return matched ? matched.display : value;
        }

        function renderChips() {
            chips.innerHTML = '';
            tokens.forEach(function (token, index) {
                var display = tokenDisplay(token);
                var chip = document.createElement('span');
                chip.className = 'docapproval-token-chip';
                if (display !== token) chip.title = token;

                var text = document.createElement('span');
                text.className = 'docapproval-token-chip-text';
                text.textContent = display;

                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'docapproval-token-remove';
                remove.setAttribute('aria-label', 'Remove ' + display);
                remove.textContent = '×';
                remove.addEventListener('click', function () {
                    tokens.splice(index, 1);
                    syncSource();
                    renderChips();
                    input.focus();
                    updateMenu();
                });

                chip.appendChild(text);
                chip.appendChild(remove);
                chips.appendChild(chip);
            });
        }

        function addToken(raw) {
            var value = canonicalToken(raw);
            if (!value) return false;
            if (!hasToken(value)) tokens.push(value);
            syncSource();
            renderChips();
            input.value = '';
            activeIndex = -1;
            updateMenu();
            return true;
        }

        function rankedSuggestions(query) {
            var q = query.toLowerCase();
            return suggestions
                .filter(function (item) {
                    if (hasToken(item.value)) return false;
                    return !q || item.value.toLowerCase().indexOf(q) !== -1 ||
                        item.display.toLowerCase().indexOf(q) !== -1 ||
                        item.label.toLowerCase().indexOf(q) !== -1;
                })
                .sort(function (a, b) {
                    function score(item) {
                        if (!q) return 1;
                        if (item.display.toLowerCase().indexOf(q) === 0) return 0;
                        if (item.value.toLowerCase().indexOf(q) === 0) return 0;
                        return 1;
                    }
                    var as = score(a);
                    var bs = score(b);
                    if (as !== bs) return as - bs;
                    return a.label.toLowerCase().localeCompare(b.label.toLowerCase());
                })
                .slice(0, 12);
        }

        function updateActive() {
            Array.prototype.forEach.call(menu.children, function (node, index) {
                node.classList.toggle('active', index === activeIndex);
            });
        }

        function updateMenu() {
            var query = input.value.trim();
            visibleSuggestions = rankedSuggestions(query);
            menu.innerHTML = '';

            visibleSuggestions.forEach(function (item, index) {
                var option = document.createElement('button');
                option.type = 'button';
                option.className = 'docapproval-token-option';
                option.setAttribute('role', 'option');
                option.textContent = item.label;
                option.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                });
                option.addEventListener('click', function () {
                    addToken(item.value);
                    input.focus();
                });
                menu.appendChild(option);
            });

            activeIndex = visibleSuggestions.length ? 0 : -1;
            updateActive();
            menu.hidden = visibleSuggestions.length === 0;
        }

        input.addEventListener('focus', updateMenu);
        input.addEventListener('input', updateMenu);

        input.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown' && visibleSuggestions.length) {
                event.preventDefault();
                activeIndex = (activeIndex + 1) % visibleSuggestions.length;
                updateActive();
                return;
            }

            if (event.key === 'ArrowUp' && visibleSuggestions.length) {
                event.preventDefault();
                activeIndex = (activeIndex - 1 + visibleSuggestions.length) % visibleSuggestions.length;
                updateActive();
                return;
            }

            if (event.key === 'Enter') {
                if (visibleSuggestions.length && activeIndex >= 0 && menu.hidden === false) {
                    event.preventDefault();
                    addToken(visibleSuggestions[activeIndex].value);
                    return;
                }
                if (input.value.trim()) {
                    event.preventDefault();
                    addToken(input.value);
                    return;
                }
            }

            if (event.key === ',') {
                event.preventDefault();
                addToken(input.value);
                return;
            }

            if (event.key === 'Backspace' && !input.value && tokens.length) {
                tokens.pop();
                syncSource();
                renderChips();
                updateMenu();
                return;
            }

            if (event.key === 'Escape') {
                menu.hidden = true;
                activeIndex = -1;
            }
        });

        input.addEventListener('blur', function () {
            window.setTimeout(function () {
                if (input.value.trim()) addToken(input.value);
                menu.hidden = true;
            }, 120);
        });

        var form = source.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                if (input.value.trim()) addToken(input.value);
                syncSource();
            });
        }

        renderChips();
        syncSource();
    }

    function bindTransitionSummarySave() {
        var summaryInput = document.querySelector('.docapproval-change-summary-input');
        if (!summaryInput) return;

        Array.prototype.forEach.call(document.querySelectorAll('form.docapproval-action-form'), function (form) {
            var carried = form.querySelector('.docapproval-transition-summary');
            if (!carried) return;
            form.addEventListener('submit', function () {
                carried.value = summaryInput.value;
            });
        });
    }

    function debounce(fn, wait) {
        var timer = null;
        return function () {
            var args = arguments;
            var self = this;
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                fn.apply(self, args);
            }, wait);
        };
    }

    function enhanceDashboardDocumentTable(table) {
        if (!table || table.dataset.docapprovalLiveTable === '1') return;
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.dataTable) return;

        var root = table.closest('.docapproval-dashboard-documents');
        if (!root) return;
        table.dataset.docapprovalLiveTable = '1';

        var $ = window.jQuery;
        var pageInput = root.querySelector('.docapproval-dashboard-page-filter');
        var statusSelect = root.querySelector('.docapproval-dashboard-status-filter');
        var assigneeInput = root.querySelector('.docapproval-dashboard-assignee-filter');
        var ackSelect = root.querySelector('.docapproval-dashboard-ack-filter');
        var lengthSelect = root.querySelector('.docapproval-dashboard-page-length');
        var resetButton = root.querySelector('.docapproval-dashboard-reset');

        function value(node) {
            return node ? String(node.value || '').trim().toLowerCase() : '';
        }

        function rowMatches(row, ignore) {
            if (!row) return false;

            var pageNeedle = value(pageInput);
            if (ignore !== 'page' && pageNeedle &&
                String(row.dataset.docapprovalPage || '').indexOf(pageNeedle) === -1) {
                return false;
            }

            var status = value(statusSelect) || 'all';
            if (ignore !== 'status' && status !== 'all' &&
                String(row.dataset.docapprovalStatus || '').toLowerCase() !== status) {
                return false;
            }

            var assignee = value(assigneeInput);
            if (ignore !== 'assignee' && assignee &&
                String(row.dataset.docapprovalAssignees || '').indexOf(assignee) === -1) {
                return false;
            }

            var ack = value(ackSelect) || 'all';
            var rowAck = String(row.dataset.docapprovalAck || '').toLowerCase();
            if (ignore !== 'ack' && ack !== 'all') {
                if (ack === 'none_required') {
                    if (rowAck !== 'none' && rowAck !== 'not_required') return false;
                } else if (ack === 'overdue') {
                    if (String(row.dataset.docapprovalOverdue || '') !== '1') return false;
                } else if (rowAck !== ack) {
                    return false;
                }
            }

            return true;
        }

        var liveFilter = function (settings, data, dataIndex) {
            if (settings.nTable !== table) return true;
            var row = settings.aoData && settings.aoData[dataIndex]
                ? settings.aoData[dataIndex].nTr
                : null;
            return rowMatches(row, '');
        };
        $.fn.dataTable.ext.search.push(liveFilter);

        function setupDataTable() {
            var dt;
            if ($.fn.dataTable.isDataTable(table)) {
                dt = $(table).DataTable();
            } else {
                dt = $(table).DataTable({
                    pageLength: 50,
                    lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
                    order: [[1, 'asc'], [3, 'asc']],
                    autoWidth: false,
                    dom: 'rt<"docapproval-dashboard-dt-footer"ip>'
                });
            }

            var container = dt.table().container();
            if (container) container.classList.add('docapproval-dashboard-dt-wrapper');

            // The dashboard owns its search and page-length controls. Clear any
            // generic DataTables search state, use an operational default sort,
            // and begin at 50 rows per page.
            dt.search('');
            dt.columns().search('');
            dt.page.len(50);
            dt.order([[1, 'asc'], [3, 'asc']]);

            function allRows() {
                return dt.rows().nodes();
            }

            function updateCards() {
                var counts = {
                    draft: 0,
                    awaiting_review: 0,
                    awaiting_training: 0,
                    ready_to_publish: 0,
                    published: 0,
                    ack_outstanding: 0,
                    ack_overdue: 0
                };
                var rows = allRows();

                for (var i = 0; i < rows.length; i++) {
                    var row = rows[i];
                    var status = String(row.dataset.docapprovalStatus || '');

                    // Workflow cards are faceted: page/assignee/ack filters apply,
                    // but the current workflow filter is ignored so all workflow
                    // buckets remain useful as one-click switches.
                    if (rowMatches(row, 'status') &&
                        Object.prototype.hasOwnProperty.call(counts, status)) {
                        counts[status]++;
                    }

                    // Outstanding acknowledgements are similarly faceted against
                    // all active filters except the acknowledgement-state filter.
                    if (rowMatches(row, 'ack')) {
                        var outstanding = parseInt(
                            row.dataset.docapprovalAckOutstanding || '0',
                            10
                        ) || 0;
                        counts.ack_outstanding += outstanding;
                        if (String(row.dataset.docapprovalOverdue || '') === '1') {
                            counts.ack_overdue += outstanding;
                        }
                    }
                }

                Object.keys(counts).forEach(function (key) {
                    var node = root.querySelector('[data-docapproval-count="' + key + '"]');
                    if (node) node.textContent = String(counts[key]);
                });

                Array.prototype.forEach.call(
                    root.querySelectorAll('[data-docapproval-status-card]'),
                    function (button) {
                        button.classList.toggle(
                            'active',
                            value(statusSelect) === button.dataset.docapprovalStatusCard
                        );
                    }
                );

                Array.prototype.forEach.call(
                    root.querySelectorAll('[data-docapproval-ack-card]'),
                    function (button) {
                        button.classList.toggle(
                            'active',
                            value(ackSelect) === button.dataset.docapprovalAckCard
                        );
                    }
                );
            }

            function redraw() {
                dt.page('first');
                dt.draw();
            }

            var redrawDebounced = debounce(redraw, 160);

            if (pageInput) pageInput.addEventListener('input', redrawDebounced);
            if (assigneeInput) assigneeInput.addEventListener('input', redrawDebounced);
            if (statusSelect) statusSelect.addEventListener('change', redraw);
            if (ackSelect) ackSelect.addEventListener('change', redraw);

            if (lengthSelect) {
                lengthSelect.value = '50';
                lengthSelect.addEventListener('change', function () {
                    var length = parseInt(lengthSelect.value, 10);
                    if ([25, 50, 100, -1].indexOf(length) === -1) length = 50;
                    dt.page.len(length).draw();
                });
            }

            if (resetButton) {
                resetButton.addEventListener('click', function () {
                    if (pageInput) pageInput.value = '';
                    if (statusSelect) statusSelect.value = 'all';
                    if (assigneeInput) assigneeInput.value = '';
                    if (ackSelect) ackSelect.value = 'all';
                    if (lengthSelect) lengthSelect.value = '50';
                    dt.search('');
                    dt.columns().search('');
                    dt.page.len(50);
                    dt.order([[1, 'asc'], [3, 'asc']]);
                    redraw();
                });
            }

            Array.prototype.forEach.call(
                root.querySelectorAll('[data-docapproval-status-card]'),
                function (button) {
                    button.addEventListener('click', function () {
                        if (!statusSelect) return;
                        var next = button.dataset.docapprovalStatusCard || 'all';
                        statusSelect.value = value(statusSelect) === next ? 'all' : next;
                        redraw();
                    });
                }
            );

            Array.prototype.forEach.call(
                root.querySelectorAll('[data-docapproval-ack-card]'),
                function (button) {
                    button.addEventListener('click', function () {
                        if (!ackSelect) return;
                        var next = button.dataset.docapprovalAckCard || 'all';
                        ackSelect.value = value(ackSelect) === next ? 'all' : next;
                        redraw();
                    });
                }
            );

            $(table).off('draw.dt.docapprovalDashboard')
                .on('draw.dt.docapprovalDashboard', updateCards);

            dt.draw();
            updateCards();
        }

        // Give the site's DataTables plugin its normal ready callback first. If it
        // has already initialized this table we attach to that instance; otherwise
        // we initialize the same library ourselves as a safe fallback.
        window.setTimeout(setupDataTable, 100);
    }

    function initDashboardDocumentTables() {
        Array.prototype.forEach.call(
            document.querySelectorAll('.docapproval-dashboard-documents-table'),
            enhanceDashboardDocumentTable
        );
    }

    function csvCell(value) {
        var text = value === null || value === undefined ? '' : String(value);
        return '"' + text.replace(/"/g, '""') + '"';
    }

    function downloadComplianceCsv(button) {
        var root = button.closest('.plugin-structdocapproval-compliance');
        if (!root) return;

        var dataNode = root.querySelector('.docapproval-compliance-export-data');
        if (!dataNode) return;

        var rows = [];
        try {
            rows = JSON.parse(dataNode.textContent || '[]');
        } catch (e) {
            return;
        }

        var columns = [
            ['page', 'Page'],
            ['title', 'Title'],
            ['version', 'Version'],
            ['publication_revision', 'Publication Revision'],
            ['content_revision', 'Content Revision'],
            ['published_at', 'Published'],
            ['ack_due_at', 'Acknowledgement Due'],
            ['published_by', 'Published By Login'],
            ['published_by_name', 'Published By'],
            ['current', 'Current Publication'],
            ['change_summary', 'Change Summary'],
            ['acknowledge_audience', 'Acknowledgement Audience'],
            ['reminder_batches', 'Reminder Batches'],
            ['last_reminder_at', 'Last Reminder At'],
            ['last_reminder_by', 'Last Reminder By'],
            ['required_user', 'Required User Login'],
            ['required_name', 'Required User'],
            ['status', 'Acknowledgement Status'],
            ['acknowledged_at', 'Acknowledged At']
        ];

        var lines = [
            columns.map(function (column) { return csvCell(column[1]); }).join(',')
        ];

        rows.forEach(function (row) {
            lines.push(columns.map(function (column) {
                return csvCell(row[column[0]]);
            }).join(','));
        });

        var blob = new Blob(
            ['\ufeff' + lines.join('\r\n')],
            { type: 'text/csv;charset=utf-8' }
        );
        var url = window.URL.createObjectURL(blob);
        var link = document.createElement('a');
        var now = new Date();
        var stamp = [
            now.getFullYear(),
            String(now.getMonth() + 1).padStart(2, '0'),
            String(now.getDate()).padStart(2, '0')
        ].join('-');

        link.href = url;
        link.download = 'docapproval-compliance-' + stamp + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.setTimeout(function () {
            window.URL.revokeObjectURL(url);
        }, 0);
    }

    function initComplianceExports() {
        Array.prototype.forEach.call(
            document.querySelectorAll('.docapproval-compliance-export'),
            function (button) {
                if (button.dataset.docapprovalExportBound === '1') return;
                button.dataset.docapprovalExportBound = '1';
                button.addEventListener('click', function () {
                    downloadComplianceCsv(button);
                });
            }
        );
    }

    function initAcknowledgementDueControls() {
        Array.prototype.forEach.call(
            document.querySelectorAll('.docapproval-action-form'),
            function (form) {
                var checkbox = form.querySelector(
                    'input[name="structdocapproval[acknowledge_enabled]"]'
                );
                var due = form.querySelector('.docapproval-ack-due');
                if (!checkbox || !due) return;

                function sync() {
                    due.disabled = !checkbox.checked;
                }
                checkbox.addEventListener('change', sync);
                sync();
            }
        );
    }

    function init() {
        Array.prototype.forEach.call(
            document.querySelectorAll('.docapproval-token-source'),
            enhanceTokenPicker
        );
        bindTransitionSummarySave();
        initDashboardDocumentTables();
        initComplianceExports();
        initAcknowledgementDueControls();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
