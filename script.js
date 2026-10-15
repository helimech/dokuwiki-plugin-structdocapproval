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
            var value = canonicalToken(raw);
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
            var value = (raw || '').trim().replace(/,+$/, '').trim();
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

    function init() {
        Array.prototype.forEach.call(
            document.querySelectorAll('.docapproval-token-source'),
            enhanceTokenPicker
        );
        bindTransitionSummarySave();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
