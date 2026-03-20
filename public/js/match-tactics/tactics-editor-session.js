(function (window) {
    'use strict';

    if (window.MatchTacticsEditorSession) {
        return;
    }

    function createSession(config) {
        var options = config && typeof config === 'object' ? config : {};

        if (!window.MatchTacticsStore || typeof window.MatchTacticsStore.createStore !== 'function') {
            console.error('MatchTacticsStore module is required.');
            return null;
        }
        if (!window.MatchTacticsAutosaveQueue || typeof window.MatchTacticsAutosaveQueue.createQueue !== 'function') {
            console.error('MatchTacticsAutosaveQueue module is required.');
            return null;
        }

        var board = options.board || null;
        var apiClient = options.apiClient || null;
        var listEl = options.listElement || null;
        var titleEl = options.titleEl || null;
        var minuteEl = options.minuteEl || null;
        var retryBtn = options.retryBtn || null;
        var deleteBtn = options.deleteBtn || null;
        var newBtn = options.newBtn || null;
        var setStatus = typeof options.setStatus === 'function'
            ? options.setStatus
            : function () {};

        if (!board || typeof board.loadDrawingData !== 'function' || typeof board.exportDrawingData !== 'function') {
            console.error('MatchTacticsEditorSession requires a valid board instance.');
            return null;
        }
        if (!apiClient || typeof apiClient.saveTactic !== 'function' || typeof apiClient.deleteTacticById !== 'function') {
            console.error('MatchTacticsEditorSession requires a valid apiClient.');
            return null;
        }
        if (!listEl || !titleEl || !minuteEl || !deleteBtn || !newBtn) {
            console.error('MatchTacticsEditorSession requires form/list elements.');
            return null;
        }

        var contextMode = String(options.contextMode || 'match').trim();
        if (contextMode !== 'match' && contextMode !== 'team') {
            contextMode = 'match';
        }

        var listSortMode = String(options.listSortMode || 'sort_order').trim() || 'sort_order';
        var showSourceMeta = !!options.showSourceMeta;
        var debounceMs = Number(options.debounceMs);
        if (!Number.isFinite(debounceMs) || debounceMs < 0) {
            debounceMs = 900;
        }

        var store = null;
        var autosaveQueue = null;

        function tacticKey(tactic) {
            return store ? store.tacticKey(tactic) : '';
        }

        function getSelectedTactic() {
            return store ? store.getSelectedTactic() : null;
        }

        function readFormIntoTactic(tactic) {
            if (!tactic) {
                return;
            }
            tactic.title = String(titleEl.value || '').trim() || 'Nieuwe situatie';
            tactic.minute = store.normalizeMinute(minuteEl.value);
            tactic.drawingData = board.exportDrawingData();
        }

        function applyTacticToForm(tactic) {
            if (!tactic) {
                return;
            }
            titleEl.value = tactic.title;
            minuteEl.value = tactic.minute === null ? '' : String(tactic.minute);
            board.loadDrawingData(tactic.drawingData);
        }

        function onTacticContentChanged() {
            var selected = getSelectedTactic();
            if (!selected || !autosaveQueue) {
                return;
            }
            autosaveQueue.queueKey(tacticKey(selected));
        }

        function replaceWithServerTactics(serverTactics, fallbackSelectedId) {
            store.replaceWithServerTactics(serverTactics, fallbackSelectedId);
            autosaveQueue.cleanupQueue();
        }

        store = window.MatchTacticsStore.createStore({
            initialDataRaw: options.initialDataRaw,
            contextMode: contextMode,
            listSortMode: listSortMode,
            showSourceMeta: showSourceMeta,
            listElement: listEl,
            onBeforeSelect: function (current, currentKey) {
                readFormIntoTactic(current);
                if (autosaveQueue && autosaveQueue.isQueued(currentKey)) {
                    autosaveQueue.queueKey(currentKey, { immediate: true });
                }
            },
            onAfterSelect: function (selected) {
                applyTacticToForm(selected);
            }
        });

        autosaveQueue = window.MatchTacticsAutosaveQueue.createQueue({
            debounceMs: debounceMs,
            setStatus: setStatus,
            keyExists: function (key) {
                return !!store.getTacticByKey(key);
            },
            saveKey: function (key) {
                var tactic = store.getTacticByKey(key);
                if (!tactic) {
                    return Promise.resolve(null);
                }

                if (key === store.getSelectedKey()) {
                    readFormIntoTactic(tactic);
                }

                return apiClient.saveTactic(tactic)
                    .then(function (savedData) {
                        var keyMap = store.applyServerIdentityForTactic(tactic, savedData.tactic || null);
                        if (keyMap && keyMap.oldKey && keyMap.newKey && autosaveQueue) {
                            autosaveQueue.remapKey(keyMap.oldKey, keyMap.newKey);
                        }
                        return savedData;
                    });
            }
        });

        store.renderList();
        applyTacticToForm(getSelectedTactic());
        setStatus('Alle wijzigingen opgeslagen.', {
            tone: 'success',
            showRetry: false
        });

        if (retryBtn) {
            retryBtn.addEventListener('click', function () {
                autosaveQueue.retryNow();
            });
        }

        if (options.bindBeforeUnload !== false) {
            window.addEventListener('beforeunload', function (event) {
                if (!autosaveQueue.hasPendingWork()) {
                    return;
                }
                event.preventDefault();
                event.returnValue = '';
            });
        }

        titleEl.addEventListener('input', function () {
            var selected = getSelectedTactic();
            if (!selected) {
                return;
            }
            selected.title = String(titleEl.value || '').trim() || 'Nieuwe situatie';
            store.renderList();
            onTacticContentChanged();
        });

        minuteEl.addEventListener('change', function () {
            var selected = getSelectedTactic();
            if (!selected) {
                return;
            }
            selected.minute = store.normalizeMinute(minuteEl.value);
            store.renderList();
            onTacticContentChanged();
        });

        newBtn.addEventListener('click', function () {
            var current = getSelectedTactic();
            var previousKey = '';
            if (current) {
                previousKey = tacticKey(current);
                readFormIntoTactic(current);
            }
            if (previousKey !== '' && autosaveQueue.isQueued(previousKey)) {
                autosaveQueue.queueKey(previousKey, { immediate: true });
            }

            var tactic = store.addNewTactic();
            autosaveQueue.queueKey(tacticKey(tactic), { immediate: true });
        });

        deleteBtn.addEventListener('click', function () {
            var selected = getSelectedTactic();
            if (!selected) {
                setStatus('Selecteer eerst een situatie.', true);
                return;
            }
            var selectedTacticKey = tacticKey(selected);

            if (!confirm('Weet je zeker dat je deze situatie wilt verwijderen?')) {
                return;
            }

            autosaveQueue.removeKey(selectedTacticKey);

            if (!selected.id) {
                store.removeTacticByKey(selectedTacticKey);
                autosaveQueue.cleanupQueue();
                setStatus('Situatie verwijderd.', { tone: 'success' });
                return;
            }

            setStatus('Verwijderen...', false);

            apiClient.deleteTacticById(selected.id)
                .then(function (data) {
                    replaceWithServerTactics(data.tactics, null);
                    setStatus('Situatie verwijderd.', { tone: 'success' });
                })
                .catch(function (error) {
                    setStatus(error.message || 'Verwijderen mislukt.', true);
                });
        });

        return {
            onContentChanged: onTacticContentChanged,
            getSelectedTactic: getSelectedTactic,
            getStore: function () {
                return store;
            },
            getAutosaveQueue: function () {
                return autosaveQueue;
            }
        };
    }

    window.MatchTacticsEditorSession = {
        createSession: createSession
    };
}(window));
