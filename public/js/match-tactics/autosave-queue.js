(function (window) {
    'use strict';

    if (window.MatchTacticsAutosaveQueue) {
        return;
    }

    function createQueue(config) {
        var options = config && typeof config === 'object' ? config : {};
        var debounceMs = Number(options.debounceMs);
        if (!Number.isFinite(debounceMs) || debounceMs < 0) {
            debounceMs = 900;
        }

        var setStatus = typeof options.setStatus === 'function' ? options.setStatus : function () {};
        var saveKey = typeof options.saveKey === 'function' ? options.saveKey : function () { return Promise.resolve(); };
        var keyExists = typeof options.keyExists === 'function'
            ? options.keyExists
            : function () { return true; };

        var queue = [];
        var queuedSet = {};
        var timerId = 0;
        var inFlight = false;
        var savingKey = '';
        var api = null;

        function clearTimer() {
            if (timerId) {
                window.clearTimeout(timerId);
                timerId = 0;
            }
        }

        function hasPendingWork() {
            return inFlight || timerId !== 0 || queue.length > 0;
        }

        function isQueued(key) {
            var target = String(key || '').trim();
            if (target === '') {
                return false;
            }
            return !!queuedSet[target];
        }

        function removeKey(key) {
            var target = String(key || '').trim();
            if (target === '') {
                return;
            }
            delete queuedSet[target];
            queue = queue.filter(function (queuedKey) {
                return queuedKey !== target;
            });
            if (queue.length === 0 && !inFlight) {
                clearTimer();
            }
        }

        function remapKey(oldKey, newKey) {
            var fromKey = String(oldKey || '').trim();
            var toKey = String(newKey || '').trim();
            if (fromKey === '' || toKey === '' || fromKey === toKey) {
                return;
            }

            var wasQueued = !!queuedSet[fromKey];
            delete queuedSet[fromKey];
            queue = queue.map(function (queuedKey) {
                return queuedKey === fromKey ? toKey : queuedKey;
            });
            if (wasQueued) {
                queuedSet[toKey] = true;
            }

            var deduped = [];
            var seen = {};
            queue.forEach(function (queuedKey) {
                var key = String(queuedKey || '').trim();
                if (key === '' || seen[key]) {
                    return;
                }
                seen[key] = true;
                deduped.push(key);
            });
            queue = deduped;
            queuedSet = seen;
        }

        function cleanupQueue() {
            var deduped = [];
            var seen = {};
            queue.forEach(function (queuedKey) {
                var key = String(queuedKey || '').trim();
                if (key === '' || seen[key]) {
                    return;
                }
                if (!keyExists(key)) {
                    return;
                }
                seen[key] = true;
                deduped.push(key);
            });
            queue = deduped;
            queuedSet = seen;
        }

        function popNextKey() {
            while (queue.length > 0) {
                var nextKey = String(queue.shift() || '').trim();
                if (nextKey === '') {
                    continue;
                }
                delete queuedSet[nextKey];
                if (keyExists(nextKey)) {
                    return nextKey;
                }
            }
            return '';
        }

        function schedule(delayMs) {
            clearTimer();

            var delay = Number(delayMs);
            if (!Number.isFinite(delay) || delay < 0) {
                delay = debounceMs;
            }

            timerId = window.setTimeout(function () {
                timerId = 0;
                flush().catch(function () {});
            }, delay);
        }

        function queueKey(key, optionsForQueue) {
            var target = String(key || '').trim();
            if (target === '') {
                return;
            }

            if (!queuedSet[target]) {
                queuedSet[target] = true;
                queue.push(target);
            }

            setStatus('Wijzigingen nog niet opgeslagen.', { showRetry: false });

            if (inFlight) {
                return;
            }

            var immediate = !!(optionsForQueue && optionsForQueue.immediate);
            if (immediate) {
                clearTimer();
                flush().catch(function () {});
                return;
            }

            schedule(debounceMs);
        }

        function flush() {
            if (inFlight) {
                return Promise.resolve(false);
            }

            cleanupQueue();
            var nextKey = popNextKey();
            if (nextKey === '') {
                setStatus('Alle wijzigingen opgeslagen.', {
                    tone: 'success',
                    showRetry: false
                });
                return Promise.resolve(true);
            }

            inFlight = true;
            savingKey = nextKey;
            setStatus('Opslaan...', { showRetry: false });

            return Promise.resolve()
                .then(function () {
                    return saveKey(nextKey);
                })
                .then(function (result) {
                    inFlight = false;
                    savingKey = '';
                    if (typeof options.onSaveSuccess === 'function') {
                        options.onSaveSuccess(nextKey, result, api);
                    }
                    return flush();
                })
                .catch(function (error) {
                    var failedKey = savingKey;
                    inFlight = false;
                    savingKey = '';

                    if (failedKey !== '' && keyExists(failedKey) && !queuedSet[failedKey]) {
                        queuedSet[failedKey] = true;
                        queue.unshift(failedKey);
                    }

                    setStatus((error && error.message ? error.message : 'Opslaan mislukt.') + ' Wijzigingen blijven lokaal bewaard.', {
                        isError: true,
                        showRetry: true
                    });

                    if (typeof options.onSaveError === 'function') {
                        options.onSaveError(failedKey, error, api);
                    }

                    return Promise.reject(error);
                });
        }

        function retryNow() {
            clearTimer();
            return flush().catch(function () {});
        }

        api = {
            queueKey: queueKey,
            flush: function () {
                return flush().catch(function () {});
            },
            retryNow: retryNow,
            removeKey: removeKey,
            remapKey: remapKey,
            cleanupQueue: cleanupQueue,
            isQueued: isQueued,
            hasPendingWork: hasPendingWork,
            getSavingKey: function () {
                return savingKey;
            }
        };

        return api;
    }

    window.MatchTacticsAutosaveQueue = {
        createQueue: createQueue
    };
}(window));
