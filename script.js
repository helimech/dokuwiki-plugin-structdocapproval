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
                if (value) suggestions.push(value);
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

        function renderChips() {
            chips.innerHTML = '';
            tokens.forEach(function (token, index) {
                var chip = document.createElement('span');
                chip.className = 'docapproval-token-chip';

                var text = document.createElement('span');
                text.className = 'docapproval-token-chip-text';
                text.textContent = token;

                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'docapproval-token-remove';
                remove.setAttribute('aria-label', 'Remove ' + token);
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
                    return !hasToken(item) && (!q || item.toLowerCase().indexOf(q) !== -1);
                })
                .sort(function (a, b) {
                    var al = a.toLowerCase();
                    var bl = b.toLowerCase();
                    var as = q && al.indexOf(q) === 0 ? 0 : 1;
                    var bs = q && bl.indexOf(q) === 0 ? 0 : 1;
                    if (as !== bs) return as - bs;
                    return al.localeCompare(bl);
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

            visibleSuggestions.forEach(function (value, index) {
                var option = document.createElement('button');
                option.type = 'button';
                option.className = 'docapproval-token-option';
                option.setAttribute('role', 'option');
                option.textContent = value;
                option.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                });
                option.addEventListener('click', function () {
                    addToken(value);
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
                    addToken(visibleSuggestions[activeIndex]);
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

    function init() {
        Array.prototype.forEach.call(
            document.querySelectorAll('.docapproval-token-source'),
            enhanceTokenPicker
        );
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
