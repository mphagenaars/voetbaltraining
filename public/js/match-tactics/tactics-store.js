(function (window) {
    'use strict';

    if (window.MatchTacticsStore) {
        return;
    }

    function safeJsonParse(raw) {
        try {
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function normalizeMinute(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        var minute = Number(value);
        if (!Number.isFinite(minute)) {
            return null;
        }

        var intMinute = Math.round(minute);
        if (intMinute < 0 || intMinute > 130) {
            return null;
        }

        return intMinute;
    }

    function createClientId() {
        return 'local-' + Date.now() + '-' + Math.floor(Math.random() * 100000);
    }

    function createStore(config) {
        var options = config && typeof config === 'object' ? config : {};
        var listEl = options.listElement || null;
        if (!listEl || typeof listEl.appendChild !== 'function') {
            throw new Error('MatchTacticsStore requires listElement.');
        }

        var contextMode = String(options.contextMode || 'match').trim();
        if (contextMode !== 'match' && contextMode !== 'team') {
            contextMode = 'match';
        }
        var listSortMode = String(options.listSortMode || 'sort_order').trim() || 'sort_order';
        var showSourceMeta = !!options.showSourceMeta;
        var onBeforeSelect = typeof options.onBeforeSelect === 'function' ? options.onBeforeSelect : null;
        var onAfterSelect = typeof options.onAfterSelect === 'function' ? options.onAfterSelect : null;

        function normalizeTactic(raw, index) {
            var source = raw && typeof raw === 'object' ? raw : {};
            var id = Number(source.id);
            var title = String(source.title || '').trim();
            return {
                id: Number.isFinite(id) && id > 0 ? id : null,
                clientId: createClientId(),
                title: title !== '' ? title : 'Nieuwe situatie',
                minute: normalizeMinute(source.minute),
                drawingData: typeof source.drawing_data === 'string' ? source.drawing_data : '',
                sortOrder: Number.isFinite(Number(source.sort_order)) ? Number(source.sort_order) : (index + 1),
                contextType: String(source.context_type || (contextMode === 'team' ? 'team' : 'match')).trim(),
                sourceLabel: String(source.source_label || '').trim()
            };
        }

        function tacticKey(tactic) {
            return tactic && tactic.id ? ('id:' + tactic.id) : ('local:' + String(tactic && tactic.clientId ? tactic.clientId : ''));
        }

        function createEmptyTactic(nextNumber) {
            return {
                id: null,
                clientId: createClientId(),
                title: 'Situatie ' + nextNumber,
                minute: null,
                drawingData: '',
                sortOrder: nextNumber,
                contextType: contextMode === 'team' ? 'team' : 'match',
                sourceLabel: contextMode === 'team' ? 'Fictief' : ''
            };
        }

        function sortTacticsInPlace(list) {
            if (!Array.isArray(list) || listSortMode === 'server') {
                return;
            }

            list.sort(function (a, b) {
                if (a.sortOrder !== b.sortOrder) {
                    return a.sortOrder - b.sortOrder;
                }
                return (a.id || 0) - (b.id || 0);
            });
        }

        function buildMetaLabel(tactic) {
            var minuteLabel = tactic.minute === null ? 'zonder minuut' : ('minuut ' + tactic.minute);
            if (!showSourceMeta) {
                return minuteLabel;
            }

            var sourceLabel = String(tactic.sourceLabel || '').trim();
            if (sourceLabel === '') {
                sourceLabel = tactic.contextType === 'match' ? 'Wedstrijd' : 'Fictief';
            }

            return sourceLabel + ' · ' + minuteLabel;
        }

        var tactics = safeJsonParse(String(options.initialDataRaw || '')).map(normalizeTactic);
        sortTacticsInPlace(tactics);
        if (tactics.length === 0) {
            tactics.push(createEmptyTactic(1));
        }
        var selectedKey = tacticKey(tactics[0]);

        function getSelectedIndex() {
            return tactics.findIndex(function (tactic) {
                return tacticKey(tactic) === selectedKey;
            });
        }

        function getSelectedTactic() {
            var index = getSelectedIndex();
            if (index < 0) {
                return null;
            }
            return tactics[index];
        }

        function getTacticByKey(key) {
            var targetKey = String(key || '').trim();
            if (targetKey === '') {
                return null;
            }
            for (var i = 0; i < tactics.length; i += 1) {
                if (tacticKey(tactics[i]) === targetKey) {
                    return tactics[i];
                }
            }
            return null;
        }

        function renderList() {
            listEl.innerHTML = '';

            tactics.forEach(function (tactic) {
                var key = tacticKey(tactic);
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'match-tactic-item' + (key === selectedKey ? ' is-active' : '');
                button.dataset.key = key;

                var title = document.createElement('span');
                title.className = 'match-tactic-item-title';
                title.textContent = tactic.title;
                button.appendChild(title);

                var meta = document.createElement('span');
                meta.className = 'match-tactic-item-meta';
                meta.textContent = buildMetaLabel(tactic);
                button.appendChild(meta);

                button.addEventListener('click', function () {
                    var previous = getSelectedTactic();
                    var previousKey = previous ? tacticKey(previous) : '';
                    if (previous && onBeforeSelect) {
                        onBeforeSelect(previous, previousKey);
                    }

                    selectedKey = String(button.dataset.key || '');
                    var selected = getSelectedTactic();
                    if (selected && onAfterSelect) {
                        onAfterSelect(selected, tacticKey(selected), previousKey);
                    }
                    renderList();
                });

                listEl.appendChild(button);
            });
        }

        function ensureValidSelection() {
            if (getSelectedIndex() >= 0) {
                return;
            }
            if (tactics.length === 0) {
                tactics.push(createEmptyTactic(1));
            }
            selectedKey = tacticKey(tactics[0]);
        }

        function replaceWithServerTactics(serverTactics, fallbackSelectedId) {
            var previousSelectedKey = selectedKey;
            var normalized = Array.isArray(serverTactics)
                ? serverTactics.map(normalizeTactic)
                : [];

            sortTacticsInPlace(normalized);
            tactics = normalized;

            if (tactics.length === 0) {
                tactics.push(createEmptyTactic(1));
                selectedKey = tacticKey(tactics[0]);
                renderList();
                if (onAfterSelect) {
                    onAfterSelect(tactics[0], selectedKey, '');
                }
                return;
            }

            if (previousSelectedKey !== '') {
                var stillSelected = tactics.find(function (item) {
                    return tacticKey(item) === previousSelectedKey;
                });
                if (stillSelected) {
                    selectedKey = tacticKey(stillSelected);
                }
            }

            if (fallbackSelectedId && Number.isFinite(Number(fallbackSelectedId)) && !getTacticByKey(selectedKey)) {
                var fallback = tactics.find(function (item) {
                    return item.id === Number(fallbackSelectedId);
                });
                if (fallback) {
                    selectedKey = tacticKey(fallback);
                }
            }

            ensureValidSelection();
            renderList();
            var selected = getSelectedTactic();
            if (selected && onAfterSelect) {
                onAfterSelect(selected, tacticKey(selected), previousSelectedKey);
            }
        }

        function addNewTactic() {
            var nextNumber = tactics.length + 1;
            var tactic = createEmptyTactic(nextNumber);
            tactics.push(tactic);
            selectedKey = tacticKey(tactic);
            renderList();
            if (onAfterSelect) {
                onAfterSelect(tactic, selectedKey, '');
            }
            return tactic;
        }

        function removeTacticByKey(key) {
            var targetKey = String(key || '').trim();
            if (targetKey === '') {
                return null;
            }

            tactics = tactics.filter(function (item) {
                return tacticKey(item) !== targetKey;
            });
            ensureValidSelection();
            renderList();
            var selected = getSelectedTactic();
            if (selected && onAfterSelect) {
                onAfterSelect(selected, tacticKey(selected), targetKey);
            }
            return selected;
        }

        function applyServerIdentityForTactic(tactic, savedRaw) {
            if (!tactic || !savedRaw || typeof savedRaw !== 'object') {
                return null;
            }

            var oldKey = tacticKey(tactic);
            var savedId = Number(savedRaw.id);
            if (!Number.isFinite(savedId) || savedId <= 0 || tactic.id) {
                return null;
            }

            tactic.id = savedId;
            if (Number.isFinite(Number(savedRaw.sort_order))) {
                tactic.sortOrder = Number(savedRaw.sort_order);
            }

            var newKey = tacticKey(tactic);
            if (selectedKey === oldKey) {
                selectedKey = newKey;
            }
            renderList();
            return { oldKey: oldKey, newKey: newKey };
        }

        return {
            normalizeMinute: normalizeMinute,
            tacticKey: tacticKey,
            renderList: renderList,
            getSelectedKey: function () {
                return selectedKey;
            },
            getSelectedIndex: getSelectedIndex,
            getSelectedTactic: getSelectedTactic,
            getTacticByKey: getTacticByKey,
            replaceWithServerTactics: replaceWithServerTactics,
            addNewTactic: addNewTactic,
            removeTacticByKey: removeTacticByKey,
            applyServerIdentityForTactic: applyServerIdentityForTactic
        };
    }

    window.MatchTacticsStore = {
        createStore: createStore,
        normalizeMinute: normalizeMinute
    };
}(window));
