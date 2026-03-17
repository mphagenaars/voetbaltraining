document.addEventListener('DOMContentLoaded', function () {
    var root = document.getElementById('match-tactics-root');
    if (!root) {
        return;
    }

    if (typeof Konva === 'undefined') {
        console.error('Konva is required for match tactics editor.');
        return;
    }

    if (!window.KonvaSharedCore || typeof window.KonvaSharedCore.createBoard !== 'function') {
        console.error('KonvaSharedCore is required for match tactics editor.');
        return;
    }

    var BOARD_PHASE = 'open_play';
    var BOARD_FIELD_TYPE = 'standard_30x42_5';

    var matchIdEl = document.getElementById('match_id');
    var csrfTokenEl = document.getElementById('csrf_token');
    var tacticsDataEl = document.getElementById('match_tactics_data');
    var listEl = document.getElementById('tactics-list');
    var titleEl = document.getElementById('tactics-title');
    var minuteEl = document.getElementById('tactics-minute');
    var statusEl = document.getElementById('tactics-save-status');
    var saveBtn = document.getElementById('tactics-save-btn');
    var deleteBtn = document.getElementById('tactics-delete-btn');
    var newBtn = document.getElementById('tactics-new-btn');

    if (
        !matchIdEl ||
        !csrfTokenEl ||
        !tacticsDataEl ||
        !listEl ||
        !titleEl ||
        !minuteEl ||
        !statusEl ||
        !saveBtn ||
        !deleteBtn ||
        !newBtn
    ) {
        console.error('Missing match tactics DOM elements.');
        return;
    }

    function createMatchLayout() {
        var PITCH = {
            x: 20,
            y: 30,
            width: 300,
            height: 425
        };
        var GOAL_WIDTH = 50;
        var GOAL_DEPTH = 20;

        return {
            key: BOARD_FIELD_TYPE,
            width: 340,
            height: 485,
            drawField: function (fieldLayer) {
                fieldLayer.add(new Konva.Rect({
                    x: 0,
                    y: 0,
                    width: 340,
                    height: 485,
                    fill: '#3f9f4c'
                }));

                var stripeCount = 10;
                var stripeHeight = PITCH.height / stripeCount;
                for (var i = 0; i < stripeCount; i += 1) {
                    fieldLayer.add(new Konva.Rect({
                        x: PITCH.x,
                        y: PITCH.y + (i * stripeHeight),
                        width: PITCH.width,
                        height: stripeHeight,
                        fill: i % 2 === 0 ? 'rgba(255,255,255,0.045)' : 'rgba(0,0,0,0.03)'
                    }));
                }

                var line = {
                    stroke: 'rgba(255,255,255,0.9)',
                    strokeWidth: 2
                };

                fieldLayer.add(new Konva.Rect({
                    x: PITCH.x,
                    y: PITCH.y,
                    width: PITCH.width,
                    height: PITCH.height,
                    stroke: line.stroke,
                    strokeWidth: line.strokeWidth
                }));

                var centerX = PITCH.x + (PITCH.width / 2);
                var centerY = PITCH.y + (PITCH.height / 2);

                fieldLayer.add(new Konva.Line({
                    points: [PITCH.x, centerY, PITCH.x + PITCH.width, centerY],
                    stroke: line.stroke,
                    strokeWidth: line.strokeWidth
                }));

                fieldLayer.add(new Konva.Circle({
                    x: centerX,
                    y: centerY,
                    radius: 40,
                    stroke: line.stroke,
                    strokeWidth: line.strokeWidth
                }));

                fieldLayer.add(new Konva.Circle({
                    x: centerX,
                    y: centerY,
                    radius: 2,
                    fill: 'rgba(255,255,255,0.95)'
                }));

                var penaltyAreaWidth = 160;
                var penaltyAreaDepth = 60;
                var goalAreaWidth = 80;
                var goalAreaDepth = 25;
                var penaltySpotDistance = 40;

                fieldLayer.add(new Konva.Rect({
                    x: centerX - (penaltyAreaWidth / 2),
                    y: PITCH.y,
                    width: penaltyAreaWidth,
                    height: penaltyAreaDepth,
                    stroke: line.stroke,
                    strokeWidth: line.strokeWidth
                }));

                fieldLayer.add(new Konva.Rect({
                    x: centerX - (penaltyAreaWidth / 2),
                    y: PITCH.y + PITCH.height - penaltyAreaDepth,
                    width: penaltyAreaWidth,
                    height: penaltyAreaDepth,
                    stroke: line.stroke,
                    strokeWidth: line.strokeWidth
                }));

                fieldLayer.add(new Konva.Rect({
                    x: centerX - (goalAreaWidth / 2),
                    y: PITCH.y,
                    width: goalAreaWidth,
                    height: goalAreaDepth,
                    stroke: line.stroke,
                    strokeWidth: line.strokeWidth
                }));

                fieldLayer.add(new Konva.Rect({
                    x: centerX - (goalAreaWidth / 2),
                    y: PITCH.y + PITCH.height - goalAreaDepth,
                    width: goalAreaWidth,
                    height: goalAreaDepth,
                    stroke: line.stroke,
                    strokeWidth: line.strokeWidth
                }));

                fieldLayer.add(new Konva.Circle({
                    x: centerX,
                    y: PITCH.y + penaltySpotDistance,
                    radius: 2,
                    fill: 'rgba(255,255,255,0.95)'
                }));

                fieldLayer.add(new Konva.Circle({
                    x: centerX,
                    y: PITCH.y + PITCH.height - penaltySpotDistance,
                    radius: 2,
                    fill: 'rgba(255,255,255,0.95)'
                }));

                fieldLayer.add(new Konva.Rect({
                    x: centerX - (GOAL_WIDTH / 2),
                    y: PITCH.y - GOAL_DEPTH,
                    width: GOAL_WIDTH,
                    height: GOAL_DEPTH,
                    stroke: line.stroke,
                    strokeWidth: line.strokeWidth
                }));

                fieldLayer.add(new Konva.Rect({
                    x: centerX - (GOAL_WIDTH / 2),
                    y: PITCH.y + PITCH.height,
                    width: GOAL_WIDTH,
                    height: GOAL_DEPTH,
                    stroke: line.stroke,
                    strokeWidth: line.strokeWidth
                }));
            },
            isPointInside: function (point) {
                return !!(
                    point &&
                    point.x >= PITCH.x &&
                    point.x <= (PITCH.x + PITCH.width) &&
                    point.y >= PITCH.y &&
                    point.y <= (PITCH.y + PITCH.height)
                );
            },
            clampPoint: function (point) {
                return {
                    x: Math.max(PITCH.x, Math.min(PITCH.x + PITCH.width, point.x)),
                    y: Math.max(PITCH.y, Math.min(PITCH.y + PITCH.height, point.y))
                };
            },
            clampNode: function (node, context) {
                if (!node || typeof node.getClientRect !== 'function') {
                    return;
                }

                var box = node.getClientRect({ relativeTo: context.mainLayer });
                if (
                    !Number.isFinite(box.x) ||
                    !Number.isFinite(box.y) ||
                    !Number.isFinite(box.width) ||
                    !Number.isFinite(box.height)
                ) {
                    return;
                }

                var dx = 0;
                var dy = 0;

                if (box.x < PITCH.x) {
                    dx = PITCH.x - box.x;
                } else if ((box.x + box.width) > (PITCH.x + PITCH.width)) {
                    dx = (PITCH.x + PITCH.width) - (box.x + box.width);
                }

                if (box.y < PITCH.y) {
                    dy = PITCH.y - box.y;
                } else if ((box.y + box.height) > (PITCH.y + PITCH.height)) {
                    dy = (PITCH.y + PITCH.height) - (box.y + box.height);
                }

                if (dx !== 0 || dy !== 0) {
                    node.x(node.x() + dx);
                    node.y(node.y() + dy);
                }
            }
        };
    }

    function createTacticsBoard() {
        var containerEl = document.getElementById('tactics-container');
        var toolbarEl = document.getElementById('tactics-toolbar');
        if (!containerEl || !toolbarEl) {
            return null;
        }

        var markerColor = '#ffffff';
        var markerStrokeStyle = 'solid';

        var markerColorBlackBtn = document.getElementById('tactics-marker-color-black');
        var markerColorRedBtn = document.getElementById('tactics-marker-color-red');
        var markerStyleSolidBtn = document.getElementById('tactics-marker-style-solid');
        var markerStyleDashedBtn = document.getElementById('tactics-marker-style-dashed');
        var clearBtn = document.getElementById('tactics-btn-clear');
        var deleteSelectedBtn = document.getElementById('tactics-btn-delete-selected');
        var toBackBtn = document.getElementById('tactics-btn-to-back');

        function applyMarkerButtonState() {
            if (markerColorBlackBtn) {
                markerColorBlackBtn.classList.toggle('active', markerColor === '#ffffff');
            }
            if (markerColorRedBtn) {
                markerColorRedBtn.classList.toggle('active', markerColor === '#d32f2f');
            }
            if (markerStyleSolidBtn) {
                markerStyleSolidBtn.classList.toggle('active', markerStrokeStyle === 'solid');
            }
            if (markerStyleDashedBtn) {
                markerStyleDashedBtn.classList.toggle('active', markerStrokeStyle === 'dashed');
            }
        }

        var board = window.KonvaSharedCore.createBoard({
            moduleKey: 'match_tactics',
            rootElement: root,
            containerElement: containerEl,
            toolbarElement: toolbarEl,
            toolbarItemSelector: '.tactics-draggable-item',
            layout: createMatchLayout(),
            defaultTool: 'select',
            toolButtons: {
                select: 'tactics-tool-select',
                marker: 'tactics-tool-marker'
            },
            resolveDrawConfig: function (context) {
                if (context.tool !== 'marker') {
                    return null;
                }

                var attrs = {
                    stroke: markerColor,
                    fill: markerColor,
                    strokeWidth: 4.5,
                    pointerLength: 13,
                    pointerWidth: 13,
                    lineCap: 'round',
                    lineJoin: 'round',
                    tension: 0.5,
                    name: 'item'
                };

                if (markerStrokeStyle === 'dashed') {
                    attrs.dash = [24, 18];
                    attrs.lineCap = 'butt';
                    attrs.tension = 0;
                    attrs.strokeWidth = 4;
                }

                return {
                    kind: 'poly-arrow',
                    attrs: attrs,
                    minPointDistance: markerStrokeStyle === 'dashed' ? 6.5 : 2.4,
                    minPoints: 6,
                    minLength: 6
                };
            },
            placeItem: function (context) {
                var type = context.type;
                var pos = context.position;

                if (type === 'ball') {
                    var text = new Konva.Text({
                        x: pos.x,
                        y: pos.y,
                        text: '⚽',
                        fontSize: 20,
                        draggable: true,
                        name: 'item'
                    });
                    text.offsetX(text.width() / 2);
                    text.offsetY(text.height() / 2);
                    context.addItemNode(text);
                    return;
                }

                var imageSrc = '/images/assets/' + type + '.svg';
                Konva.Image.fromURL(imageSrc, function (image) {
                    var scaleX;
                    var scaleY;

                    if (type.indexOf('shirt') === 0) {
                        var targetHeight = 38;
                        var baseScaleShirt = targetHeight / image.height();
                        scaleX = baseScaleShirt * 1.18;
                        scaleY = baseScaleShirt;
                    } else {
                        var targetSize = 25;
                        var baseScale = targetSize / Math.max(image.width(), image.height());
                        scaleX = baseScale;
                        scaleY = baseScale;
                    }

                    image.setAttrs({
                        x: pos.x,
                        y: pos.y,
                        scaleX: scaleX,
                        scaleY: scaleY,
                        offsetX: image.width() / 2,
                        offsetY: image.height() / 2,
                        draggable: true,
                        name: 'item',
                        imageSrc: imageSrc
                    });

                    context.addItemNode(image);
                });
            },
            touch: {
                hintClassName: 'match-tactics-touch-hint',
                toolbarItemActiveClassName: 'is-touch-active',
                toolbarItemArmedClassName: 'is-touch-armed',
                containerArmedClassName: 'is-touch-armed',
                containerDropTargetClassName: 'is-touch-drop-target',
                containerPlaceFeedbackClassName: 'is-touch-place-feedback',
                containerDraggingClassName: 'is-touch-dragging',
                rootDraggingClassName: 'is-touch-dragging',
                bodyScrollLockClassName: 'tactics-touch-scroll-lock',
                dragThresholdPx: 8,
                placementFeedbackDurationMs: 200,
                hintMessages: {
                    dragging: 'Sleep naar het veld en laat los om te plaatsen.',
                    armed: 'Tik op het veld om te plaatsen. Tik opnieuw op het icoon om te stoppen.'
                }
            }
        });

        if (markerColorBlackBtn) {
            markerColorBlackBtn.addEventListener('click', function () {
                markerColor = '#ffffff';
                applyMarkerButtonState();
                board.setTool('marker');
            });
        }

        if (markerColorRedBtn) {
            markerColorRedBtn.addEventListener('click', function () {
                markerColor = '#d32f2f';
                applyMarkerButtonState();
                board.setTool('marker');
            });
        }

        if (markerStyleSolidBtn) {
            markerStyleSolidBtn.addEventListener('click', function () {
                markerStrokeStyle = 'solid';
                applyMarkerButtonState();
                board.setTool('marker');
            });
        }

        if (markerStyleDashedBtn) {
            markerStyleDashedBtn.addEventListener('click', function () {
                markerStrokeStyle = 'dashed';
                applyMarkerButtonState();
                board.setTool('marker');
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                board.clearArmedToolbarType();
                if (!confirm('Weet je zeker dat je deze tekening wilt wissen?')) {
                    return;
                }
                board.clearAll();
            });
        }

        if (deleteSelectedBtn) {
            deleteSelectedBtn.addEventListener('click', function () {
                board.clearArmedToolbarType();
                board.deleteSelected();
            });
        }

        if (toBackBtn) {
            toBackBtn.addEventListener('click', function () {
                board.clearArmedToolbarType();
                board.sendSelectedToBack();
            });
        }

        applyMarkerButtonState();
        board.setTool('select');

        return {
            loadDrawingData: function (drawingJson) {
                board.loadDrawingData(drawingJson || '');
            },
            exportDrawingData: function () {
                return board.exportDrawingData();
            }
        };
    }

    var board = createTacticsBoard();
    if (!board) {
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

    function normalizeTactic(raw, index) {
        var id = Number(raw.id);
        var title = String(raw.title || '').trim();

        return {
            id: Number.isFinite(id) && id > 0 ? id : null,
            clientId: createClientId(),
            title: title !== '' ? title : 'Nieuwe situatie',
            minute: normalizeMinute(raw.minute),
            drawingData: typeof raw.drawing_data === 'string' ? raw.drawing_data : '',
            sortOrder: Number.isFinite(Number(raw.sort_order)) ? Number(raw.sort_order) : (index + 1)
        };
    }

    function tacticKey(tactic) {
        return tactic.id ? ('id:' + tactic.id) : ('local:' + tactic.clientId);
    }

    function createEmptyTactic(nextNumber) {
        return {
            id: null,
            clientId: createClientId(),
            title: 'Situatie ' + nextNumber,
            minute: null,
            drawingData: '',
            sortOrder: nextNumber
        };
    }

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.style.color = isError ? '#c62828' : '#6c757d';
    }

    var tactics = safeJsonParse(tacticsDataEl.value).map(normalizeTactic);
    tactics.sort(function (a, b) {
        if (a.sortOrder !== b.sortOrder) {
            return a.sortOrder - b.sortOrder;
        }
        return (a.id || 0) - (b.id || 0);
    });

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

    function readFormIntoTactic(tactic) {
        tactic.title = String(titleEl.value || '').trim() || 'Nieuwe situatie';
        tactic.minute = normalizeMinute(minuteEl.value);
        tactic.drawingData = board.exportDrawingData();
    }

    function applyTacticToForm(tactic) {
        titleEl.value = tactic.title;
        minuteEl.value = tactic.minute === null ? '' : String(tactic.minute);
        board.loadDrawingData(tactic.drawingData);
    }

    function renderList() {
        listEl.innerHTML = '';

        tactics.forEach(function (tactic) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'match-tactic-item' + (tacticKey(tactic) === selectedKey ? ' is-active' : '');
            button.dataset.key = tacticKey(tactic);

            var title = document.createElement('span');
            title.className = 'match-tactic-item-title';
            title.textContent = tactic.title;
            button.appendChild(title);

            var meta = document.createElement('span');
            meta.className = 'match-tactic-item-meta';
            meta.textContent = tactic.minute === null ? 'zonder minuut' : ('minuut ' + tactic.minute);
            button.appendChild(meta);

            button.addEventListener('click', function () {
                var current = getSelectedTactic();
                if (current) {
                    readFormIntoTactic(current);
                }

                selectedKey = String(button.dataset.key || '');
                var selected = getSelectedTactic();
                if (selected) {
                    applyTacticToForm(selected);
                }
                renderList();
            });

            listEl.appendChild(button);
        });
    }

    renderList();
    applyTacticToForm(tactics[0]);

    function replaceWithServerTactics(serverTactics, fallbackSelectedId) {
        var normalized = Array.isArray(serverTactics)
            ? serverTactics.map(normalizeTactic)
            : [];

        normalized.sort(function (a, b) {
            if (a.sortOrder !== b.sortOrder) {
                return a.sortOrder - b.sortOrder;
            }
            return (a.id || 0) - (b.id || 0);
        });

        tactics = normalized;

        if (tactics.length === 0) {
            tactics.push(createEmptyTactic(1));
            selectedKey = tacticKey(tactics[0]);
            renderList();
            applyTacticToForm(tactics[0]);
            return;
        }

        if (fallbackSelectedId && Number.isFinite(Number(fallbackSelectedId))) {
            var found = tactics.find(function (item) {
                return item.id === Number(fallbackSelectedId);
            });
            if (found) {
                selectedKey = tacticKey(found);
            }
        }

        if (getSelectedIndex() < 0) {
            selectedKey = tacticKey(tactics[0]);
        }

        renderList();
        var selected = getSelectedTactic();
        if (selected) {
            applyTacticToForm(selected);
        }
    }

    titleEl.addEventListener('input', function () {
        var selected = getSelectedTactic();
        if (!selected) {
            return;
        }
        selected.title = String(titleEl.value || '').trim() || 'Nieuwe situatie';
        renderList();
    });

    minuteEl.addEventListener('change', function () {
        var selected = getSelectedTactic();
        if (!selected) {
            return;
        }
        selected.minute = normalizeMinute(minuteEl.value);
        renderList();
    });

    newBtn.addEventListener('click', function () {
        var current = getSelectedTactic();
        if (current) {
            readFormIntoTactic(current);
        }

        var nextNumber = tactics.length + 1;
        var tactic = createEmptyTactic(nextNumber);
        tactics.push(tactic);
        selectedKey = tacticKey(tactic);

        renderList();
        applyTacticToForm(tactic);
        setStatus('Nieuwe situatie toegevoegd (nog niet opgeslagen).', false);
    });

    saveBtn.addEventListener('click', function () {
        var selected = getSelectedTactic();
        if (!selected) {
            setStatus('Selecteer eerst een situatie.', true);
            return;
        }

        readFormIntoTactic(selected);
        setStatus('Opslaan...', false);

        var payload = {
            match_id: Number(matchIdEl.value),
            tactic_id: selected.id,
            title: selected.title,
            phase: BOARD_PHASE,
            minute: selected.minute,
            field_type: BOARD_FIELD_TYPE,
            drawing_data: selected.drawingData,
            csrf_token: csrfTokenEl.value
        };

        fetch('/matches/tactics/save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfTokenEl.value
            },
            body: JSON.stringify(payload)
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok || !result.data || result.data.success !== true) {
                    var errorMessage = result.data && result.data.error ? result.data.error : 'Opslaan mislukt.';
                    throw new Error(errorMessage);
                }

                var selectedId = result.data.tactic && result.data.tactic.id ? Number(result.data.tactic.id) : null;
                replaceWithServerTactics(result.data.tactics, selectedId);
                setStatus('Opgeslagen.', false);
            })
            .catch(function (error) {
                setStatus(error.message || 'Opslaan mislukt.', true);
            });
    });

    deleteBtn.addEventListener('click', function () {
        var selected = getSelectedTactic();
        if (!selected) {
            setStatus('Selecteer eerst een situatie.', true);
            return;
        }

        if (!confirm('Weet je zeker dat je deze situatie wilt verwijderen?')) {
            return;
        }

        if (!selected.id) {
            tactics = tactics.filter(function (item) {
                return tacticKey(item) !== tacticKey(selected);
            });

            if (tactics.length === 0) {
                tactics.push(createEmptyTactic(1));
            }

            selectedKey = tacticKey(tactics[0]);
            renderList();
            applyTacticToForm(tactics[0]);
            setStatus('Situatie verwijderd.', false);
            return;
        }

        setStatus('Verwijderen...', false);

        fetch('/matches/tactics/delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfTokenEl.value
            },
            body: JSON.stringify({
                match_id: Number(matchIdEl.value),
                tactic_id: Number(selected.id),
                csrf_token: csrfTokenEl.value
            })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok || !result.data || result.data.success !== true) {
                    var errorMessage = result.data && result.data.error ? result.data.error : 'Verwijderen mislukt.';
                    throw new Error(errorMessage);
                }

                replaceWithServerTactics(result.data.tactics, null);
                setStatus('Situatie verwijderd.', false);
            })
            .catch(function (error) {
                setStatus(error.message || 'Verwijderen mislukt.', true);
            });
    });
});
