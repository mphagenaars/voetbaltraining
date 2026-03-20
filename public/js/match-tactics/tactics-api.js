(function (window) {
    'use strict';

    if (window.MatchTacticsApi) {
        return;
    }

    function parseJsonResponse(response, fallbackErrorMessage) {
        return response.text().then(function (text) {
            var payload = null;
            var raw = String(text || '').trim();

            if (raw !== '') {
                try {
                    payload = JSON.parse(raw);
                } catch (error) {
                    if (!response.ok) {
                        throw new Error(fallbackErrorMessage || 'Serverfout.');
                    }
                    throw new Error('Ongeldige serverrespons.');
                }
            }

            return {
                ok: response.ok,
                data: payload
            };
        });
    }

    function createClient(config) {
        var options = config && typeof config === 'object' ? config : {};

        var saveEndpoint = String(options.saveEndpoint || '/matches/tactics/save').trim() || '/matches/tactics/save';
        var deleteEndpoint = String(options.deleteEndpoint || '/matches/tactics/delete').trim() || '/matches/tactics/delete';
        var boardPhase = String(options.boardPhase || 'open_play').trim() || 'open_play';
        var boardFieldType = String(options.boardFieldType || 'standard_30x42_5').trim() || 'standard_30x42_5';

        var getCsrfToken = typeof options.getCsrfToken === 'function'
            ? options.getCsrfToken
            : function () { return ''; };
        var getContextPayload = typeof options.getContextPayload === 'function'
            ? options.getContextPayload
            : function () { return {}; };

        function buildSavePayload(tactic) {
            var source = tactic && typeof tactic === 'object' ? tactic : {};
            return Object.assign(getContextPayload(), {
                tactic_id: source.id || null,
                title: String(source.title || '').trim() || 'Nieuwe situatie',
                phase: boardPhase,
                minute: source.minute === null || source.minute === undefined ? null : source.minute,
                field_type: boardFieldType,
                drawing_data: typeof source.drawingData === 'string' ? source.drawingData : '',
                csrf_token: String(getCsrfToken() || '').trim()
            });
        }

        function saveTactic(tactic) {
            return fetch(saveEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': String(getCsrfToken() || '').trim()
                },
                credentials: 'same-origin',
                body: JSON.stringify(buildSavePayload(tactic))
            })
                .then(function (response) {
                    return parseJsonResponse(response, 'Opslaan mislukt.');
                })
                .then(function (result) {
                    if (!result.ok || !result.data || result.data.success !== true) {
                        var errorMessage = result.data && result.data.error
                            ? result.data.error
                            : 'Opslaan mislukt.';
                        throw new Error(errorMessage);
                    }
                    return result.data;
                });
        }

        function deleteTacticById(tacticId) {
            var normalizedId = Number(tacticId);
            if (!Number.isFinite(normalizedId) || normalizedId <= 0) {
                return Promise.reject(new Error('Ongeldig situatie-ID.'));
            }

            var payload = Object.assign(getContextPayload(), {
                tactic_id: Math.round(normalizedId),
                csrf_token: String(getCsrfToken() || '').trim()
            });

            return fetch(deleteEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': String(getCsrfToken() || '').trim()
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            })
                .then(function (response) {
                    return parseJsonResponse(response, 'Verwijderen mislukt.');
                })
                .then(function (result) {
                    if (!result.ok || !result.data || result.data.success !== true) {
                        var errorMessage = result.data && result.data.error
                            ? result.data.error
                            : 'Verwijderen mislukt.';
                        throw new Error(errorMessage);
                    }
                    return result.data;
                });
        }

        return {
            saveTactic: saveTactic,
            deleteTacticById: deleteTacticById
        };
    }

    window.MatchTacticsApi = {
        createClient: createClient,
        parseJsonResponse: parseJsonResponse
    };
}(window));
