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

    function parseContextId(value) {
        var id = Number(value);
        if (!Number.isFinite(id)) {
            return 0;
        }
        return Math.max(0, Math.round(id));
    }

    var contextMode = String(root.dataset.contextMode || 'match').trim();
    if (contextMode !== 'match' && contextMode !== 'team') {
        contextMode = 'match';
    }

    var contextTeamId = parseContextId(root.dataset.teamId);
    var contextMatchId = parseContextId(root.dataset.matchId);
    var saveEndpoint = String(root.dataset.saveEndpoint || '/matches/tactics/save').trim() || '/matches/tactics/save';
    var deleteEndpoint = String(root.dataset.deleteEndpoint || '/matches/tactics/delete').trim() || '/matches/tactics/delete';
    var exportEndpoint = typeof root.dataset.exportEndpoint === 'string'
        ? String(root.dataset.exportEndpoint).trim()
        : '/matches/tactics/export-video';
    var listSortMode = String(root.dataset.listSort || 'sort_order').trim() || 'sort_order';
    var showSourceMeta = String(root.dataset.showSourceMeta || '').trim() === '1';

    var csrfTokenEl = document.getElementById('csrf_token');
    var tacticsDataEl = document.getElementById('match_tactics_data');
    var listEl = document.getElementById('tactics-list');
    var titleEl = document.getElementById('tactics-title');
    var minuteEl = document.getElementById('tactics-minute');
    var statusEl = document.getElementById('tactics-save-status');
    var retryBtn = document.getElementById('tactics-save-retry-btn');
    var deleteBtn = document.getElementById('tactics-delete-btn');
    var newBtn = document.getElementById('tactics-new-btn');

    if (
        !csrfTokenEl ||
        !tacticsDataEl ||
        !listEl ||
        !titleEl ||
        !minuteEl ||
        !statusEl ||
        !deleteBtn ||
        !newBtn
    ) {
        console.error('Missing match tactics DOM elements.');
        return;
    }

    if (contextMode === 'match' && contextMatchId <= 0) {
        console.error('Missing match tactics context ID.');
        return;
    }

    if (contextMode === 'team' && contextTeamId <= 0) {
        console.error('Missing team tactics context ID.');
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

        var markerColorBlackBtn = document.getElementById('tactics-marker-color-black');
        var markerColorRedBtn = document.getElementById('tactics-marker-color-red');
        var markerStyleSolidBtn = document.getElementById('tactics-marker-style-solid');
        var markerStyleDashedBtn = document.getElementById('tactics-marker-style-dashed');
        var clearBtn = document.getElementById('tactics-btn-clear');
        var deleteSelectedBtn = document.getElementById('tactics-btn-delete-selected');
        var toBackBtn = document.getElementById('tactics-btn-to-back');
        var tacticsMainEl = root.querySelector('.match-tactics-main');
        var editorShellEl = root.querySelector('.match-tactics-editor-shell');

        if (!window.MatchTacticsBoardControls || typeof window.MatchTacticsBoardControls.create !== 'function') {
            console.error('MatchTactics board controls module is required.');
            return null;
        }
        if (!window.MatchTacticsShirtColorMenu || typeof window.MatchTacticsShirtColorMenu.create !== 'function') {
            console.error('MatchTactics shirt color module is required.');
            return null;
        }

        var boardControls = window.MatchTacticsBoardControls.create({
            markerColorBlackBtn: markerColorBlackBtn,
            markerColorRedBtn: markerColorRedBtn,
            markerStyleSolidBtn: markerStyleSolidBtn,
            markerStyleDashedBtn: markerStyleDashedBtn,
            clearBtn: clearBtn,
            deleteSelectedBtn: deleteSelectedBtn,
            toBackBtn: toBackBtn
        });
        if (!boardControls) {
            return null;
        }

        var animationAuthoring = null;
        var handleBoardContentChangeBridge = function (reason) {
            onTacticContentChanged(reason || 'board');
        };

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
                return boardControls.resolveDrawConfig(context);
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
            },
            onContentChange: function (eventContext) {
                var reason = eventContext && typeof eventContext.reason === 'string'
                    ? eventContext.reason
                    : '';
                handleBoardContentChangeBridge(reason);
            }
        });


        var shirtColorMenu = window.MatchTacticsShirtColorMenu.create({
            board: board,
            onShirtColorApplied: function (reason) {
                if (animationAuthoring && typeof animationAuthoring.handleExternalVisualChange === 'function') {
                    animationAuthoring.handleExternalVisualChange(reason || 'shirt-color');
                    return;
                }

                onTacticContentChanged(reason || 'shirt-color');
            }
        });
        if (!shirtColorMenu) {
            return null;
        }
        if (!window.MatchTacticsAnimationAuthoring || typeof window.MatchTacticsAnimationAuthoring.create !== 'function') {
            console.error('MatchTactics animation authoring module is required.');
            return null;
        }

        animationAuthoring = window.MatchTacticsAnimationAuthoring.create({
            board: board,
            tacticsMainEl: tacticsMainEl,
            editorShellEl: editorShellEl,
            exportEndpoint: exportEndpoint,
            setStatus: setStatus,
            onContentChanged: function (reason) {
                onTacticContentChanged(reason);
            },
            getTitle: function () {
                return titleEl && titleEl.value ? titleEl.value : '';
            },
            getCsrfToken: function () {
                return csrfTokenEl && csrfTokenEl.value ? csrfTokenEl.value : '';
            },
            getMatchId: function () {
                return contextMode === 'match' ? contextMatchId : 0;
            },
            getExportContext: function () {
                return buildContextPayload();
            }
        });
        if (!animationAuthoring) {
            return null;
        }

        handleBoardContentChangeBridge = function (reason) {
            animationAuthoring.handleBoardContentChange(reason);
        };

        boardControls.bindBoardActions({
            board: board,
            onClearConfirmed: function () {
                animationAuthoring.handleBoardCleared();
            },
            onDeleteSelected: function () {
                animationAuthoring.handleDeleteSelected();
            }
        });

        return {
            loadDrawingData: function (drawingJson) {
                animationAuthoring.loadDrawingData(drawingJson || '');
            },
            exportDrawingData: function () {
                var rawDrawing = board.exportDrawingData();
                return animationAuthoring.exportDrawingData(rawDrawing);
            }
        };
    }


    function buildContextPayload() {
        if (contextMode === 'team') {
            return { team_id: contextTeamId };
        }
        return { match_id: contextMatchId };
    }

    function setStatus(message, options) {
        var opts = {};
        if (typeof options === 'boolean') {
            opts = { isError: options };
        } else if (options && typeof options === 'object') {
            opts = options;
        }

        statusEl.textContent = message || '';
        if (opts.isError) {
            statusEl.style.color = '#c62828';
        } else if (opts.tone === 'success') {
            statusEl.style.color = '#2e7d32';
        } else {
            statusEl.style.color = '#6c757d';
        }

        if (retryBtn) {
            retryBtn.hidden = !opts.showRetry;
            retryBtn.disabled = !!opts.disableRetry;
        }
    }

    if (!window.MatchTacticsApi || typeof window.MatchTacticsApi.createClient !== 'function') {
        console.error('MatchTacticsApi module is required.');
        return;
    }
    if (!window.MatchTacticsEditorSession || typeof window.MatchTacticsEditorSession.createSession !== 'function') {
        console.error('MatchTacticsEditorSession module is required.');
        return;
    }

    var editorSession = null;

    function onTacticContentChanged() {
        if (!editorSession || typeof editorSession.onContentChanged !== 'function') {
            return;
        }

        editorSession.onContentChanged();
    }

    var board = createTacticsBoard();
    if (!board) {
        return;
    }

    var tacticsApi = window.MatchTacticsApi.createClient({
        saveEndpoint: saveEndpoint,
        deleteEndpoint: deleteEndpoint,
        boardPhase: BOARD_PHASE,
        boardFieldType: BOARD_FIELD_TYPE,
        getCsrfToken: function () {
            return csrfTokenEl && csrfTokenEl.value ? csrfTokenEl.value : '';
        },
        getContextPayload: buildContextPayload
    });

    editorSession = window.MatchTacticsEditorSession.createSession({
        board: board,
        apiClient: tacticsApi,
        initialDataRaw: tacticsDataEl.value,
        contextMode: contextMode,
        listSortMode: listSortMode,
        showSourceMeta: showSourceMeta,
        listElement: listEl,
        titleEl: titleEl,
        minuteEl: minuteEl,
        retryBtn: retryBtn,
        deleteBtn: deleteBtn,
        newBtn: newBtn,
        setStatus: setStatus,
        debounceMs: 900,
        bindBeforeUnload: true
    });
    if (!editorSession) {
        return;
    }
});
