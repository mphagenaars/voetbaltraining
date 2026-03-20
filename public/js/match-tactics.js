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
    var saveBtn = document.getElementById('tactics-save-btn');
    var deleteBtn = document.getElementById('tactics-delete-btn');
    var newBtn = document.getElementById('tactics-new-btn');

    if (
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

        var markerColor = '#ffffff';
        var markerStrokeStyle = 'solid';

        var markerColorBlackBtn = document.getElementById('tactics-marker-color-black');
        var markerColorRedBtn = document.getElementById('tactics-marker-color-red');
        var markerStyleSolidBtn = document.getElementById('tactics-marker-style-solid');
        var markerStyleDashedBtn = document.getElementById('tactics-marker-style-dashed');
        var clearBtn = document.getElementById('tactics-btn-clear');
        var deleteSelectedBtn = document.getElementById('tactics-btn-delete-selected');
        var toBackBtn = document.getElementById('tactics-btn-to-back');
        var tacticsMainEl = root.querySelector('.match-tactics-main');
        var editorShellEl = root.querySelector('.match-tactics-editor-shell');

        var ANIMATION_TIME_STEP_MS = 1000;
        var ANIMATION_KEYFRAME_EPSILON_MS = 200;
        var DEFAULT_ANIMATION_DURATION_MS = 1000;
        var MAX_ANIMATION_DURATION_MS = 60000;
        var DEFAULT_SLOW_MOTION_RATE = 0.5;
        var PLAYBACK_MODE_NORMAL = 'normal';
        var PLAYBACK_MODE_NORMAL_AND_SLOW = 'normal_slow';
        var PLAYBACK_MODE_SLOW_ONLY = 'slow_only';
        var ANIMATION_TRACK_PROPERTY_POSITION = 'position';
        var ANIMATION_TRACK_PROPERTY_HIGHLIGHT = 'highlight';
        var SHIRT_LONG_PRESS_MS = 450;
        var SHIRT_LONG_PRESS_MOVE_PX = 10;
        var SHIRT_ASSET_TYPE_ALIASES = {
            shirt_orange: 'shirt_yellow'
        };
        var SHIRT_COLOR_OPTIONS = [
            { assetType: 'shirt_red_black', label: 'Rood/Zwart', color: '#d32f2f', colorAlt: '#101010' },
            { assetType: 'shirt_red_white', label: 'Rood/Wit', color: '#d32f2f', colorAlt: '#ffffff' },
            { assetType: 'shirt_blue', label: 'Blauw', color: '#1e88e5', colorAlt: '' },
            { assetType: 'shirt_yellow', label: 'Geel', color: '#fdd835', colorAlt: '' }
        ];
        var EXPORT_RECORDING_FPS = 30;
        var EXPORT_VIDEO_BITS_PER_SECOND = 2500000;
        var EXPORT_SERVER_UPLOAD_HARD_LIMIT_BYTES = 100 * 1024 * 1024;
        var EXPORT_CONSERVATIVE_UPLOAD_LIMIT_BYTES = 7 * 1024 * 1024;
        var EXPORT_MIME_CANDIDATES = [
            'video/mp4;codecs=hvc1',
            'video/mp4;codecs=avc1',
            'video/mp4',
            'video/webm;codecs=vp9',
            'video/webm;codecs=vp8',
            'video/webm'
        ];
        var animationTrackSequence = 0;
        var defaultAnimationDurationLabel = (DEFAULT_ANIMATION_DURATION_MS / 1000).toFixed(1) + 's';

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

        function extractAssetTypeFromImageSrc(imageSrc) {
            var src = String(imageSrc || '').trim();
            if (src === '') {
                return '';
            }
            var fileName = src.split('/').pop() || '';
            return fileName.replace(/\.svg$/i, '').trim();
        }

        function normalizeShirtAssetType(assetType) {
            var normalized = String(assetType || '').trim();
            if (normalized === '') {
                return '';
            }
            return SHIRT_ASSET_TYPE_ALIASES[normalized] || normalized;
        }

        function isKnownShirtAssetType(assetType) {
            var normalized = normalizeShirtAssetType(assetType);
            if (normalized === '') {
                return false;
            }
            return SHIRT_COLOR_OPTIONS.some(function (option) {
                return option.assetType === normalized;
            });
        }

        function getShirtAssetTypeForNode(node) {
            if (!node || typeof node.getClassName !== 'function' || node.getClassName() !== 'Image') {
                return '';
            }
            var assetType = normalizeShirtAssetType(extractAssetTypeFromImageSrc(node.getAttr('imageSrc')));
            return isKnownShirtAssetType(assetType) ? assetType : '';
        }

        function isShirtItemNode(node) {
            return getShirtAssetTypeForNode(node) !== '';
        }

        function getClientPointFromDomEvent(domEvent) {
            if (!domEvent) {
                return null;
            }

            if (domEvent.touches && domEvent.touches.length > 0) {
                return {
                    x: Number(domEvent.touches[0].clientX),
                    y: Number(domEvent.touches[0].clientY)
                };
            }

            if (domEvent.changedTouches && domEvent.changedTouches.length > 0) {
                return {
                    x: Number(domEvent.changedTouches[0].clientX),
                    y: Number(domEvent.changedTouches[0].clientY)
                };
            }

            if (Number.isFinite(Number(domEvent.clientX)) && Number.isFinite(Number(domEvent.clientY))) {
                return {
                    x: Number(domEvent.clientX),
                    y: Number(domEvent.clientY)
                };
            }

            return null;
        }

        function buildShirtColorMenu() {
            var menuEl = document.createElement('div');
            menuEl.className = 'match-tactics-shirt-color-menu';
            menuEl.hidden = true;

            SHIRT_COLOR_OPTIONS.forEach(function (option) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'match-tactics-shirt-color-btn';
                button.dataset.assetType = option.assetType;
                button.title = option.label;
                button.setAttribute('aria-label', option.label);

                var swatch = document.createElement('span');
                swatch.className = 'match-tactics-shirt-color-swatch';
                if (option.colorAlt) {
                    swatch.style.background = 'linear-gradient(135deg, ' + option.color + ' 0 50%, ' + option.colorAlt + ' 50% 100%)';
                } else {
                    swatch.style.background = option.color;
                }
                button.appendChild(swatch);

                var label = document.createElement('span');
                label.className = 'match-tactics-shirt-color-label';
                label.textContent = option.label;
                button.appendChild(label);

                menuEl.appendChild(button);
            });

            document.body.appendChild(menuEl);
            return menuEl;
        }

        function positionShirtColorMenu(menuEl, clientPoint) {
            if (!menuEl || !clientPoint) {
                return;
            }

            var viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
            var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
            var menuWidth = menuEl.offsetWidth || 170;
            var menuHeight = menuEl.offsetHeight || 110;
            var margin = 8;

            var left = clientPoint.x + 10;
            var top = clientPoint.y + 10;

            if ((left + menuWidth + margin) > viewportWidth) {
                left = Math.max(margin, viewportWidth - menuWidth - margin);
            }
            if ((top + menuHeight + margin) > viewportHeight) {
                top = Math.max(margin, viewportHeight - menuHeight - margin);
            }

            menuEl.style.left = Math.round(left) + 'px';
            menuEl.style.top = Math.round(top) + 'px';
        }

        function getNodeRenderedImageSize(node) {
            if (!node) {
                return null;
            }

            var width = Number(node.width());
            var height = Number(node.height());
            var scaleX = Number(node.scaleX());
            var scaleY = Number(node.scaleY());

            if (
                Number.isFinite(width) &&
                Number.isFinite(height) &&
                Number.isFinite(scaleX) &&
                Number.isFinite(scaleY) &&
                width > 0 &&
                height > 0
            ) {
                return {
                    width: Math.abs(width * scaleX),
                    height: Math.abs(height * scaleY),
                    scaleXSign: scaleX < 0 ? -1 : 1,
                    scaleYSign: scaleY < 0 ? -1 : 1
                };
            }

            return null;
        }

        function applyShirtAssetTypeToNode(node, assetType) {
            var targetAssetType = normalizeShirtAssetType(assetType);
            if (!node || !isKnownShirtAssetType(targetAssetType)) {
                return;
            }

            var currentRawAssetType = extractAssetTypeFromImageSrc(node.getAttr('imageSrc'));
            var currentAssetType = normalizeShirtAssetType(currentRawAssetType);
            if (currentAssetType === targetAssetType && currentRawAssetType === targetAssetType) {
                return;
            }

            var currentRenderedSize = getNodeRenderedImageSize(node);
            var imageSrc = '/images/assets/' + targetAssetType + '.svg';
            Konva.Image.fromURL(imageSrc, function (loadedNode) {
                if (!loadedNode || typeof loadedNode.image !== 'function' || !node.getStage()) {
                    return;
                }

                var imageObj = loadedNode.image();
                if (!imageObj) {
                    return;
                }

                var naturalWidth = Number(imageObj.width || 0);
                var naturalHeight = Number(imageObj.height || 0);
                if (naturalWidth <= 0 || naturalHeight <= 0) {
                    return;
                }

                node.image(imageObj);
                node.offsetX(naturalWidth / 2);
                node.offsetY(naturalHeight / 2);

                if (currentRenderedSize && currentRenderedSize.width > 0 && currentRenderedSize.height > 0) {
                    node.scaleX(currentRenderedSize.scaleXSign * (currentRenderedSize.width / naturalWidth));
                    node.scaleY(currentRenderedSize.scaleYSign * (currentRenderedSize.height / naturalHeight));
                }

                node.setAttr('imageSrc', imageSrc);
                node.setAttr('shirtAssetType', targetAssetType);

                board.getMainLayer().batchDraw();
                board.getUiLayer().batchDraw();
                updateAnimationControls();
            });
        }

        var shirtColorMenuEl = buildShirtColorMenu();
        var shirtColorMenuTargetNode = null;
        var shirtLongPressTimerId = 0;
        var shirtLongPressStartPoint = null;
        var shirtLongPressTargetNode = null;

        function closeShirtColorMenu() {
            shirtColorMenuTargetNode = null;
            shirtColorMenuEl.hidden = true;
            shirtColorMenuEl.classList.remove('is-visible');
            shirtColorMenuEl.querySelectorAll('.match-tactics-shirt-color-btn').forEach(function (button) {
                button.classList.remove('is-active');
            });
        }

        function openShirtColorMenu(targetNode, clientPoint) {
            if (!targetNode || !isShirtItemNode(targetNode)) {
                closeShirtColorMenu();
                return;
            }

            shirtColorMenuTargetNode = targetNode;
            shirtColorMenuEl.hidden = false;
            shirtColorMenuEl.classList.add('is-visible');
            positionShirtColorMenu(shirtColorMenuEl, clientPoint);

            var currentAssetType = getShirtAssetTypeForNode(targetNode);
            shirtColorMenuEl.querySelectorAll('.match-tactics-shirt-color-btn').forEach(function (button) {
                button.classList.toggle('is-active', String(button.dataset.assetType || '') === currentAssetType);
            });
        }

        function clearShirtLongPressTimer() {
            if (shirtLongPressTimerId) {
                window.clearTimeout(shirtLongPressTimerId);
                shirtLongPressTimerId = 0;
            }
            shirtLongPressStartPoint = null;
            shirtLongPressTargetNode = null;
        }

        function scheduleShirtLongPress(targetNode, domEvent) {
            clearShirtLongPressTimer();
            if (!targetNode || !isShirtItemNode(targetNode)) {
                return;
            }

            var startPoint = getClientPointFromDomEvent(domEvent);
            if (!startPoint) {
                return;
            }

            shirtLongPressTargetNode = targetNode;
            shirtLongPressStartPoint = startPoint;
            shirtLongPressTimerId = window.setTimeout(function () {
                shirtLongPressTimerId = 0;
                if (!shirtLongPressTargetNode || !shirtLongPressTargetNode.getStage()) {
                    clearShirtLongPressTimer();
                    return;
                }

                openShirtColorMenu(shirtLongPressTargetNode, shirtLongPressStartPoint);
                clearShirtLongPressTimer();
            }, SHIRT_LONG_PRESS_MS);
        }

        function maybeCancelLongPressByMove(domEvent) {
            if (!shirtLongPressTimerId || !shirtLongPressStartPoint) {
                return;
            }

            var point = getClientPointFromDomEvent(domEvent);
            if (!point) {
                return;
            }

            if (Math.abs(point.x - shirtLongPressStartPoint.x) > SHIRT_LONG_PRESS_MOVE_PX || Math.abs(point.y - shirtLongPressStartPoint.y) > SHIRT_LONG_PRESS_MOVE_PX) {
                clearShirtLongPressTimer();
            }
        }

        shirtColorMenuEl.addEventListener('click', function (event) {
            var button = event.target instanceof HTMLElement
                ? event.target.closest('.match-tactics-shirt-color-btn')
                : null;
            if (!button) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            var assetType = String(button.dataset.assetType || '').trim();
            if (shirtColorMenuTargetNode && assetType !== '') {
                applyShirtAssetTypeToNode(shirtColorMenuTargetNode, assetType);
            }
            closeShirtColorMenu();
        });

        shirtColorMenuEl.addEventListener('mousedown', function (event) {
            event.stopPropagation();
        });
        shirtColorMenuEl.addEventListener('touchstart', function (event) {
            event.stopPropagation();
        }, { passive: true });

        document.addEventListener('mousedown', function (event) {
            if (!shirtColorMenuEl.classList.contains('is-visible')) {
                return;
            }
            if (event.target instanceof Node && shirtColorMenuEl.contains(event.target)) {
                return;
            }
            closeShirtColorMenu();
        });
        document.addEventListener('touchstart', function (event) {
            if (!shirtColorMenuEl.classList.contains('is-visible')) {
                return;
            }
            if (event.target instanceof Node && shirtColorMenuEl.contains(event.target)) {
                return;
            }
            closeShirtColorMenu();
        }, { passive: true });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeShirtColorMenu();
            }
        });
        window.addEventListener('resize', closeShirtColorMenu);
        window.addEventListener('scroll', closeShirtColorMenu, true);

        board.getStage().on('contextmenu.shirtColor', function (event) {
            var targetNode = event.target;
            if (!isShirtItemNode(targetNode)) {
                closeShirtColorMenu();
                return;
            }

            if (event.evt) {
                event.evt.preventDefault();
                event.evt.stopPropagation();
            }

            openShirtColorMenu(targetNode, getClientPointFromDomEvent(event.evt));
        });

        board.getStage().on('mousedown.shirtColor', function (event) {
            if (!(event.evt && event.evt.button === 2)) {
                closeShirtColorMenu();
            }
            clearShirtLongPressTimer();
        });

        board.getStage().on('touchstart.shirtColor', function (event) {
            if (board.getTool && board.getTool() !== 'select') {
                clearShirtLongPressTimer();
                return;
            }

            if (!isShirtItemNode(event.target)) {
                clearShirtLongPressTimer();
                closeShirtColorMenu();
                return;
            }

            scheduleShirtLongPress(event.target, event.evt);
        });

        board.getStage().on('touchmove.shirtColor', function (event) {
            maybeCancelLongPressByMove(event.evt);
        });
        board.getStage().on('touchend.shirtColor touchcancel.shirtColor', function () {
            clearShirtLongPressTimer();
        });

        board.getMainLayer().on('dragstart.shirtColor', '.item', function () {
            clearShirtLongPressTimer();
            closeShirtColorMenu();
        });
        board.getMainLayer().on('destroy.shirtColor', '.item', function () {
            if (shirtColorMenuTargetNode === this) {
                closeShirtColorMenu();
            }
            if (shirtLongPressTargetNode === this) {
                clearShirtLongPressTimer();
            }
        });

        var animationPanelApi = window.MatchTacticsAnimationPanel;
        if (!animationPanelApi || typeof animationPanelApi.createPanel !== 'function') {
            console.error('MatchTactics animation panel module is required.');
            return null;
        }

        var animationPanel = animationPanelApi.createPanel({
            tacticsMainEl: tacticsMainEl,
            editorShellEl: editorShellEl,
            defaultDurationLabel: defaultAnimationDurationLabel,
            defaultDurationMs: DEFAULT_ANIMATION_DURATION_MS,
            timeStepMs: ANIMATION_TIME_STEP_MS,
            playbackModeNormal: PLAYBACK_MODE_NORMAL,
            playbackModeNormalAndSlow: PLAYBACK_MODE_NORMAL_AND_SLOW,
            playbackModeSlowOnly: PLAYBACK_MODE_SLOW_ONLY
        });
        var animationToggleBtn = animationPanel.refs.toggleBtn;
        var animationPlayBtn = animationPanel.refs.playBtn;
        var animationRestartBtn = animationPanel.refs.restartBtn;
        var animationPrevFrameBtn = animationPanel.refs.prevFrameBtn;
        var animationNextFrameBtn = animationPanel.refs.nextFrameBtn;
        var animationAddKeyframeBtn = animationPanel.refs.addKeyframeBtn;
        var animationHighlightBtn = animationPanel.refs.highlightBtn;
        var animationDeleteKeyframeBtn = animationPanel.refs.deleteKeyframeBtn;
        var animationModeEl = animationPanel.refs.modeEl;
        var animationSlowRateEl = animationPanel.refs.slowRateEl;
        var animationExportBtn = animationPanel.refs.exportBtn;
        var animationRangeEl = animationPanel.refs.rangeEl;
        var animationTimeEl = animationPanel.refs.timeEl;
        var setAnimationButtonIcon = animationPanel.setButtonIcon;

        function createEmptyAnimationTimeline() {
            return {
                enabled: false,
                durationMs: DEFAULT_ANIMATION_DURATION_MS,
                fps: 60,
                tracks: []
            };
        }

        var animationTimeline = createEmptyAnimationTimeline();
        var animationCurrentTimeMs = 0;
        var animationIsPlaying = false;
        var animationIsExporting = false;
        var animationPlaybackMode = PLAYBACK_MODE_NORMAL_AND_SLOW;
        var animationSlowMotionRate = DEFAULT_SLOW_MOTION_RATE;
        var animationPlaybackElapsedMs = 0;
        var animationPlaybackSegmentIndex = 0;
        var animationPlaybackRate = 1;
        var activePlaybackPlan = null;
        var animationApplyingFrame = false;
        var animationHighlightOverlaysByItemId = {};
        var playbackPlanApi = window.MatchTacticsPlaybackPlan;
        var playbackRuntimeApi = window.MatchTacticsPlaybackRuntime;
        var videoExportApi = window.MatchTacticsVideoExport;
        if (
            !playbackPlanApi ||
            !playbackRuntimeApi ||
            !videoExportApi ||
            typeof playbackRuntimeApi.createRuntime !== 'function' ||
            typeof videoExportApi.createExporter !== 'function'
        ) {
            console.error('MatchTactics playback/export modules are required.');
            return null;
        }
        var playbackRuntime = playbackRuntimeApi.createRuntime();

        function nextAnimationTrackId() {
            animationTrackSequence += 1;
            return 'track-' + animationTrackSequence + '-' + Math.floor(Math.random() * 100000);
        }

        function clampAnimationTime(timeMs) {
            var value = Number(timeMs);
            if (!Number.isFinite(value)) {
                return 0;
            }
            return Math.max(0, Math.min(animationTimeline.durationMs, Math.round(value)));
        }

        function roundAnimationTime(timeMs) {
            var value = clampAnimationTime(timeMs);
            return Math.round(value / ANIMATION_TIME_STEP_MS) * ANIMATION_TIME_STEP_MS;
        }

        function normalizeAnimationDuration(durationMs) {
            var duration = Number(durationMs);
            if (!Number.isFinite(duration) || duration <= 0) {
                duration = DEFAULT_ANIMATION_DURATION_MS;
            }

            duration = Math.round(duration / ANIMATION_TIME_STEP_MS) * ANIMATION_TIME_STEP_MS;
            return Math.max(ANIMATION_TIME_STEP_MS, Math.min(MAX_ANIMATION_DURATION_MS, duration));
        }

        function normalizeAnimationPositionValue(value) {
            var source = value && typeof value === 'object' ? value : {};
            var x = Number(source.x);
            var y = Number(source.y);
            if (!Number.isFinite(x) || !Number.isFinite(y)) {
                return null;
            }
            return { x: x, y: y };
        }

        function normalizeAnimationHighlightValue(value) {
            if (typeof value === 'boolean') {
                return { active: value };
            }

            if (value && typeof value === 'object') {
                if (typeof value.active === 'boolean') {
                    return { active: value.active };
                }
                if (typeof value.enabled === 'boolean') {
                    return { active: value.enabled };
                }
                if (typeof value.on === 'boolean') {
                    return { active: value.on };
                }
                if (typeof value.highlight === 'boolean') {
                    return { active: value.highlight };
                }
            }

            return null;
        }

        function normalizeAnimationTrackProperty(property) {
            if (String(property || '').trim() === ANIMATION_TRACK_PROPERTY_HIGHLIGHT) {
                return ANIMATION_TRACK_PROPERTY_HIGHLIGHT;
            }
            return ANIMATION_TRACK_PROPERTY_POSITION;
        }

        function normalizeAnimationTrackValue(property, value) {
            if (property === ANIMATION_TRACK_PROPERTY_HIGHLIGHT) {
                return normalizeAnimationHighlightValue(value);
            }
            return normalizeAnimationPositionValue(value);
        }

        function sanitizeAnimationTimeline(raw) {
            var source = raw && typeof raw === 'object' ? raw : {};
            var duration = normalizeAnimationDuration(source.durationMs);

            var tracks = Array.isArray(source.tracks) ? source.tracks : [];
            var normalizedTracks = tracks
                .filter(function (track) {
                    return track && typeof track === 'object';
                })
                .map(function (track) {
                    var itemId = String(track.itemId || '').trim();
                    var property = normalizeAnimationTrackProperty(track.property);
                    var keyframes = Array.isArray(track.keyframes) ? track.keyframes : [];
                    var normalizedKeyframes = keyframes
                        .filter(function (frame) {
                            return frame && typeof frame === 'object';
                        })
                        .map(function (frame) {
                            var value = normalizeAnimationTrackValue(property, frame.value);
                            if (!value) {
                                return null;
                            }
                            var t = Number(frame.t);
                            if (!Number.isFinite(t)) {
                                return null;
                            }
                            return {
                                t: Math.max(0, Math.min(duration, Math.round(t))),
                                value: value
                            };
                        })
                        .filter(function (frame) {
                            return frame !== null;
                        });

                    normalizedKeyframes.sort(function (a, b) {
                        return a.t - b.t;
                    });

                    return {
                        id: String(track.id || '').trim() || nextAnimationTrackId(),
                        itemId: itemId,
                        property: property,
                        keyframes: normalizedKeyframes
                    };
                })
                .filter(function (track) {
                    return (
                        track.itemId !== '' &&
                        (
                            track.property === ANIMATION_TRACK_PROPERTY_POSITION ||
                            track.property === ANIMATION_TRACK_PROPERTY_HIGHLIGHT
                        ) &&
                        track.keyframes.length > 0
                    );
                });

            normalizedTracks.forEach(function (track) {
                normalizeHighlightTrackKeyframes(track);
            });
            normalizedTracks = normalizedTracks.filter(function (track) {
                return track.keyframes.length > 0;
            });

            return {
                enabled: source.enabled === true,
                durationMs: duration,
                fps: 60,
                tracks: normalizedTracks
            };
        }

        function buildItemNodeMap() {
            var nodeMap = {};
            board.getMainLayer().find('.item').forEach(function (node) {
                var itemId = String(node.getAttr('itemId') || '').trim();
                if (itemId !== '') {
                    nodeMap[itemId] = node;
                }
            });
            return nodeMap;
        }

        function removeStaleAnimationTracks() {
            var nodeMap = buildItemNodeMap();
            animationTimeline.tracks = animationTimeline.tracks.filter(function (track) {
                return !!nodeMap[track.itemId];
            });
        }

        function hasAnyAnimationKeyframes() {
            return animationTimeline.tracks.some(function (track) {
                return Array.isArray(track.keyframes) && track.keyframes.length > 0;
            });
        }

        function findAnimationMaxKeyframeTime() {
            var maxT = 0;
            animationTimeline.tracks.forEach(function (track) {
                if (!Array.isArray(track.keyframes)) {
                    return;
                }
                track.keyframes.forEach(function (frame) {
                    var t = Number(frame.t);
                    if (Number.isFinite(t) && t > maxT) {
                        maxT = t;
                    }
                });
            });
            return maxT;
        }

        function syncAnimationDurationToKeyframes() {
            if (!hasAnyAnimationKeyframes()) {
                animationTimeline.durationMs = DEFAULT_ANIMATION_DURATION_MS;
                animationCurrentTimeMs = clampAnimationTime(animationCurrentTimeMs);
                return;
            }

            var maxKeyframeTime = findAnimationMaxKeyframeTime();
            var targetDuration = Math.max(ANIMATION_TIME_STEP_MS, maxKeyframeTime + ANIMATION_TIME_STEP_MS);
            animationTimeline.durationMs = normalizeAnimationDuration(targetDuration);
            animationCurrentTimeMs = clampAnimationTime(animationCurrentTimeMs);
        }

        function ensureTimelineTrack(itemId, property) {
            var normalizedItemId = String(itemId || '').trim();
            if (normalizedItemId === '') {
                return null;
            }

            var normalizedProperty = normalizeAnimationTrackProperty(property);
            var existing = null;
            for (var i = 0; i < animationTimeline.tracks.length; i += 1) {
                if (
                    animationTimeline.tracks[i].itemId === normalizedItemId &&
                    animationTimeline.tracks[i].property === normalizedProperty
                ) {
                    existing = animationTimeline.tracks[i];
                    break;
                }
            }

            if (existing) {
                return existing;
            }

            var created = {
                id: nextAnimationTrackId(),
                itemId: normalizedItemId,
                property: normalizedProperty,
                keyframes: []
            };
            animationTimeline.tracks.push(created);
            return created;
        }

        function upsertTrackKeyframe(track, timeMs, value) {
            if (!track) {
                return;
            }

            var property = normalizeAnimationTrackProperty(track.property);
            var normalizedValue = normalizeAnimationTrackValue(property, value);
            if (!normalizedValue) {
                return;
            }

            var t = roundAnimationTime(timeMs);
            var updated = false;
            for (var i = 0; i < track.keyframes.length; i += 1) {
                if (Math.abs(track.keyframes[i].t - t) <= ANIMATION_KEYFRAME_EPSILON_MS) {
                    track.keyframes[i].t = t;
                    track.keyframes[i].value = normalizedValue;
                    updated = true;
                    break;
                }
            }

            if (!updated) {
                track.keyframes.push({
                    t: t,
                    value: normalizedValue
                });
            }

            track.keyframes.sort(function (a, b) {
                return a.t - b.t;
            });
        }

        function normalizeHighlightTrackKeyframes(track) {
            if (!track || normalizeAnimationTrackProperty(track.property) !== ANIMATION_TRACK_PROPERTY_HIGHLIGHT) {
                return;
            }

            var normalizedFrames = [];
            var previousState = null;

            track.keyframes.forEach(function (frame) {
                var active = !!(frame.value && frame.value.active === true);
                if (previousState === active) {
                    return;
                }

                normalizedFrames.push({
                    t: frame.t,
                    value: { active: active }
                });
                previousState = active;
            });

            while (normalizedFrames.length > 0 && normalizedFrames[0].value.active !== true) {
                normalizedFrames.shift();
            }

            track.keyframes = normalizedFrames;
        }

        function getTrackValueAtTime(track, timeMs) {
            if (!track || !Array.isArray(track.keyframes) || track.keyframes.length === 0) {
                return null;
            }

            var property = normalizeAnimationTrackProperty(track.property);
            var t = clampAnimationTime(timeMs);

            if (property === ANIMATION_TRACK_PROPERTY_HIGHLIGHT) {
                var active = false;
                for (var i = 0; i < track.keyframes.length; i += 1) {
                    var keyframe = track.keyframes[i];
                    if (t < keyframe.t) {
                        break;
                    }
                    active = !!(keyframe.value && keyframe.value.active === true);
                }
                return { active: active };
            }

            if (track.keyframes.length === 1) {
                return track.keyframes[0].value;
            }

            if (t <= track.keyframes[0].t) {
                return track.keyframes[0].value;
            }

            var last = track.keyframes[track.keyframes.length - 1];
            if (t >= last.t) {
                return last.value;
            }

            for (var i = 0; i < track.keyframes.length - 1; i += 1) {
                var from = track.keyframes[i];
                var to = track.keyframes[i + 1];
                if (t < from.t || t > to.t) {
                    continue;
                }

                if (to.t === from.t) {
                    return to.value;
                }

                var ratio = (t - from.t) / (to.t - from.t);
                return {
                    x: from.value.x + ((to.value.x - from.value.x) * ratio),
                    y: from.value.y + ((to.value.y - from.value.y) * ratio)
                };
            }

            return null;
        }

        function formatAnimationTime(timeMs) {
            return (Math.max(0, Number(timeMs) || 0) / 1000).toFixed(1) + 's';
        }

        function countAnimationKeyframes() {
            return animationTimeline.tracks.reduce(function (total, track) {
                return total + track.keyframes.length;
            }, 0);
        }

        function getSelectedAnimationItemNode() {
            var uiLayer = board.getUiLayer();
            if (!uiLayer || typeof uiLayer.find !== 'function') {
                return null;
            }

            var transformers = uiLayer.find('Transformer');
            if (!transformers || transformers.length === 0) {
                return null;
            }

            var selectedNodes = typeof transformers[0].nodes === 'function'
                ? transformers[0].nodes()
                : [];
            if (!Array.isArray(selectedNodes) || selectedNodes.length !== 1) {
                return null;
            }

            var selectedNode = selectedNodes[0];
            if (!selectedNode || typeof selectedNode.hasName !== 'function' || !selectedNode.hasName('item')) {
                return null;
            }

            return selectedNode;
        }

        function getSelectedAnimationItemId() {
            var selectedNode = getSelectedAnimationItemNode();
            if (!selectedNode) {
                return '';
            }
            return String(selectedNode.getAttr('itemId') || '').trim();
        }

        function isItemHighlightedAtTime(itemId, timeMs) {
            var normalizedItemId = String(itemId || '').trim();
            if (normalizedItemId === '') {
                return false;
            }

            for (var i = 0; i < animationTimeline.tracks.length; i += 1) {
                var track = animationTimeline.tracks[i];
                if (
                    track.itemId !== normalizedItemId ||
                    normalizeAnimationTrackProperty(track.property) !== ANIMATION_TRACK_PROPERTY_HIGHLIGHT
                ) {
                    continue;
                }

                var value = getTrackValueAtTime(track, timeMs);
                return !!(value && value.active === true);
            }

            return false;
        }

        function clearHighlightOverlays() {
            Object.keys(animationHighlightOverlaysByItemId).forEach(function (itemId) {
                var overlay = animationHighlightOverlaysByItemId[itemId];
                if (overlay && typeof overlay.destroy === 'function') {
                    overlay.destroy();
                }
            });
            animationHighlightOverlaysByItemId = {};
        }

        function upsertHighlightOverlay(itemId, node) {
            if (!node) {
                return;
            }

            var box = node.getClientRect({ relativeTo: board.getMainLayer() });
            if (
                !Number.isFinite(box.x) ||
                !Number.isFinite(box.y) ||
                !Number.isFinite(box.width) ||
                !Number.isFinite(box.height)
            ) {
                return;
            }

            var centerX = box.x + (box.width / 2);
            var centerY = box.y + (box.height / 2);
            var radius = Math.max(15, (Math.max(box.width, box.height) / 2) + 8);

            var overlay = animationHighlightOverlaysByItemId[itemId];
            if (!overlay) {
                overlay = new Konva.Circle({
                    x: centerX,
                    y: centerY,
                    radius: radius,
                    stroke: '#ffd54f',
                    strokeWidth: 3,
                    fill: 'rgba(255, 213, 79, 0.18)',
                    shadowColor: 'rgba(255, 193, 7, 0.42)',
                    shadowBlur: 16,
                    shadowOpacity: 1,
                    listening: false,
                    name: 'animation-highlight-overlay'
                });
                board.getUiLayer().add(overlay);
                animationHighlightOverlaysByItemId[itemId] = overlay;
            } else {
                overlay.setAttrs({
                    x: centerX,
                    y: centerY,
                    radius: radius
                });
                if (typeof overlay.show === 'function') {
                    overlay.show();
                }
            }

            if (typeof overlay.moveToBottom === 'function') {
                overlay.moveToBottom();
            }
        }

        function refreshHighlightOverlaysAtTime(timeMs) {
            if (!animationTimeline.enabled) {
                clearHighlightOverlays();
                board.getUiLayer().batchDraw();
                return;
            }

            var nodeMap = buildItemNodeMap();
            var activeItemMap = {};
            animationTimeline.tracks.forEach(function (track) {
                if (normalizeAnimationTrackProperty(track.property) !== ANIMATION_TRACK_PROPERTY_HIGHLIGHT) {
                    return;
                }

                var value = getTrackValueAtTime(track, timeMs);
                if (!(value && value.active === true)) {
                    return;
                }

                if (!nodeMap[track.itemId]) {
                    return;
                }

                activeItemMap[track.itemId] = true;
            });

            Object.keys(animationHighlightOverlaysByItemId).forEach(function (itemId) {
                if (activeItemMap[itemId]) {
                    return;
                }

                var overlay = animationHighlightOverlaysByItemId[itemId];
                if (overlay && typeof overlay.destroy === 'function') {
                    overlay.destroy();
                }
                delete animationHighlightOverlaysByItemId[itemId];
            });

            Object.keys(activeItemMap).forEach(function (itemId) {
                upsertHighlightOverlay(itemId, nodeMap[itemId]);
            });

            board.getUiLayer().batchDraw();
        }

        function normalizePlaybackRate(rate, fallbackRate) {
            return playbackPlanApi.normalizePlaybackRate(rate, fallbackRate, DEFAULT_SLOW_MOTION_RATE);
        }

        function normalizePlaybackMode(mode) {
            return playbackPlanApi.normalizePlaybackMode(
                mode,
                PLAYBACK_MODE_NORMAL,
                PLAYBACK_MODE_NORMAL_AND_SLOW,
                PLAYBACK_MODE_SLOW_ONLY
            );
        }

        function formatPlaybackRateLabel(rate) {
            return playbackPlanApi.formatPlaybackRateLabel(rate, DEFAULT_SLOW_MOTION_RATE);
        }

        function getPlaybackModeLabel(mode) {
            var normalizedMode = normalizePlaybackMode(mode);
            if (normalizedMode === PLAYBACK_MODE_NORMAL) {
                return 'alleen normaal';
            }
            if (normalizedMode === PLAYBACK_MODE_SLOW_ONLY) {
                return 'alleen slowmo';
            }
            return 'normaal + slowmo';
        }

        function buildPlaybackPlan(mode, slowRate) {
            return playbackPlanApi.buildPlaybackPlan({
                durationMs: animationTimeline.durationMs,
                mode: mode,
                slowRate: slowRate,
                normalMode: PLAYBACK_MODE_NORMAL,
                normalSlowMode: PLAYBACK_MODE_NORMAL_AND_SLOW,
                slowOnlyMode: PLAYBACK_MODE_SLOW_ONLY,
                defaultSlowRate: DEFAULT_SLOW_MOTION_RATE,
                normalizeDuration: normalizeAnimationDuration
            });
        }

        function resolvePlaybackMomentAtElapsed(plan, elapsedMs) {
            return playbackPlanApi.resolvePlaybackMomentAtElapsed(plan, elapsedMs);
        }

        function resolveElapsedFromLocalTime(plan, localTimeMs, preferredSegmentIndex) {
            return playbackPlanApi.resolveElapsedFromLocalTime(plan, localTimeMs, preferredSegmentIndex);
        }

        function stopPlaybackSession(reason) {
            playbackRuntime.stop(reason || 'manual-stop');
        }

        function runPlaybackPlan(plan, options) {
            return playbackRuntime.run(plan, options);
        }

        function updatePlaybackCursorForPlanMoment(moment) {
            if (!moment) {
                return;
            }

            animationPlaybackElapsedMs = moment.elapsedMs;
            animationPlaybackSegmentIndex = moment.segmentIndex;
            animationPlaybackRate = moment.segment && Number.isFinite(Number(moment.segment.rate))
                ? Number(moment.segment.rate)
                : 1;
        }

        function syncPlaybackCursorToCurrentTime(preferredSegmentIndex) {
            var plan = buildPlaybackPlan(animationPlaybackMode, animationSlowMotionRate);
            activePlaybackPlan = plan;
            animationPlaybackElapsedMs = resolveElapsedFromLocalTime(plan, animationCurrentTimeMs, preferredSegmentIndex);
            var moment = resolvePlaybackMomentAtElapsed(plan, animationPlaybackElapsedMs);
            updatePlaybackCursorForPlanMoment(moment);
        }

        function loadDrawingState(drawingJson) {
            var raw = drawingJson || '';
            board.loadDrawingData(raw);
            stopAnimationPlayback();
            animationTimeline = extractAnimationTimelineFromDrawing(raw);
            animationCurrentTimeMs = 0;
            removeStaleAnimationTracks();
            syncAnimationDurationToKeyframes();
            syncPlaybackCursorToCurrentTime(0);
            applyAnimationFrameAt(animationCurrentTimeMs);
        }

        function getActivePreviewPlan() {
            activePlaybackPlan = buildPlaybackPlan(animationPlaybackMode, animationSlowMotionRate);
            return activePlaybackPlan;
        }

        function updateAnimationControls() {
            if (!animationToggleBtn || !animationPlayBtn || !animationRestartBtn || !animationPrevFrameBtn || !animationNextFrameBtn || !animationAddKeyframeBtn || !animationDeleteKeyframeBtn || !animationRangeEl || !animationTimeEl) {
                return;
            }

            animationToggleBtn.classList.toggle('is-enabled', animationTimeline.enabled);
            animationToggleBtn.setAttribute('aria-label', animationTimeline.enabled ? 'Animatie uitzetten' : 'Animatie aanzetten');
            animationToggleBtn.setAttribute('title', animationTimeline.enabled ? 'Animatie uitzetten' : 'Animatie aanzetten');

            var controlsDisabled = !animationTimeline.enabled || animationIsExporting;
            animationPlayBtn.disabled = controlsDisabled;
            animationRestartBtn.disabled = controlsDisabled;
            animationPrevFrameBtn.disabled = controlsDisabled;
            animationNextFrameBtn.disabled = controlsDisabled;
            animationAddKeyframeBtn.disabled = controlsDisabled;
            animationDeleteKeyframeBtn.disabled = controlsDisabled;
            animationRangeEl.disabled = controlsDisabled;

            var selectedHighlightItemId = getSelectedAnimationItemId();
            var hasSingleSelectedItem = selectedHighlightItemId !== '';
            var selectedItemHighlighted = hasSingleSelectedItem && isItemHighlightedAtTime(selectedHighlightItemId, animationCurrentTimeMs);

            if (animationHighlightBtn) {
                animationHighlightBtn.disabled = controlsDisabled || !hasSingleSelectedItem;
                animationHighlightBtn.classList.toggle('is-active', selectedItemHighlighted);
                animationHighlightBtn.setAttribute(
                    'aria-label',
                    selectedItemHighlighted ? 'Highlight uitzetten' : 'Highlight aanzetten'
                );
                animationHighlightBtn.setAttribute(
                    'title',
                    hasSingleSelectedItem
                        ? (selectedItemHighlighted ? 'Highlight uitzetten' : 'Highlight aanzetten')
                        : 'Selecteer 1 speler om te highlighten'
                );
            }

            if (animationModeEl) {
                animationModeEl.disabled = animationIsPlaying || animationIsExporting;
                animationModeEl.value = animationPlaybackMode;
            }

            if (animationSlowRateEl) {
                animationSlowRateEl.disabled = animationIsPlaying || animationIsExporting;
                animationSlowRateEl.value = String(animationSlowMotionRate);
            }

            if (animationExportBtn) {
                animationExportBtn.disabled = animationIsExporting || !animationTimeline.enabled || countAnimationKeyframes() === 0;
                animationExportBtn.setAttribute(
                    'aria-label',
                    animationIsExporting ? 'Exporteren bezig' : 'Exporteer video (' + getPlaybackModeLabel(animationPlaybackMode) + ')'
                );
                animationExportBtn.setAttribute(
                    'title',
                    animationIsExporting ? 'Exporteren bezig' : 'Exporteer video (' + getPlaybackModeLabel(animationPlaybackMode) + ')'
                );
            }

            setAnimationButtonIcon(animationPlayBtn, animationIsPlaying ? 'pause' : 'play');
            animationPlayBtn.setAttribute('aria-label', animationIsPlaying ? 'Pauzeren' : 'Afspelen');
            animationPlayBtn.setAttribute('title', animationIsPlaying ? 'Pauzeren' : 'Afspelen');
            animationRangeEl.max = String(animationTimeline.durationMs);
            animationRangeEl.value = String(clampAnimationTime(animationCurrentTimeMs));

            var playbackPlan = activePlaybackPlan && Array.isArray(activePlaybackPlan.segments) && activePlaybackPlan.segments.length > 0
                ? activePlaybackPlan
                : getActivePreviewPlan();
            var segmentLabel = '';

            if (playbackPlan && playbackPlan.segments.length > 0) {
                var maxSegmentIndex = playbackPlan.segments.length - 1;
                var displaySegmentIndex = Math.max(0, Math.min(maxSegmentIndex, animationPlaybackSegmentIndex));
                var displaySegment = playbackPlan.segments[displaySegmentIndex];
                if (displaySegment) {
                    segmentLabel = ' · run ' + (displaySegmentIndex + 1) + '/' + playbackPlan.segments.length + ' @' + formatPlaybackRateLabel(displaySegment.rate);
                }
            }

            animationTimeEl.textContent = formatAnimationTime(animationCurrentTimeMs) + ' / ' + formatAnimationTime(animationTimeline.durationMs) + segmentLabel + ' · ' + countAnimationKeyframes() + ' keyframes';
        }

        function stopAnimationPlayback() {
            stopPlaybackSession('manual-stop');
            if (!playbackRuntime.hasActive()) {
                animationIsPlaying = false;
                updateAnimationControls();
            }
        }

        function applyAnimationFrameAt(timeMs) {
            var clampedTime = clampAnimationTime(timeMs);
            animationCurrentTimeMs = clampedTime;

            if (!animationTimeline.enabled) {
                refreshHighlightOverlaysAtTime(clampedTime);
                updateAnimationControls();
                return;
            }

            var nodeMap = buildItemNodeMap();
            animationApplyingFrame = true;
            animationTimeline.tracks.forEach(function (track) {
                if (normalizeAnimationTrackProperty(track.property) !== ANIMATION_TRACK_PROPERTY_POSITION) {
                    return;
                }

                var node = nodeMap[track.itemId];
                if (!node) {
                    return;
                }

                var value = getTrackValueAtTime(track, clampedTime);
                if (!value) {
                    return;
                }

                node.x(value.x);
                node.y(value.y);
            });
            animationApplyingFrame = false;

            board.getMainLayer().batchDraw();
            refreshHighlightOverlaysAtTime(clampedTime);
            updateAnimationControls();
        }

        function startAnimationPlayback() {
            if (!animationTimeline.enabled || animationIsExporting) {
                return;
            }

            var previewPlan = getActivePreviewPlan();
            if (!previewPlan || previewPlan.segments.length === 0) {
                return;
            }

            if (animationPlaybackElapsedMs >= previewPlan.totalElapsedMs - 1) {
                animationPlaybackElapsedMs = 0;
                animationPlaybackSegmentIndex = 0;
                animationPlaybackRate = previewPlan.segments[0].rate;
                applyAnimationFrameAt(0);
            }

            if (animationIsPlaying) {
                return;
            }

            animationIsPlaying = true;
            updateAnimationControls();

            runPlaybackPlan(previewPlan, {
                startElapsedMs: animationPlaybackElapsedMs,
                onFrame: function (moment) {
                    updatePlaybackCursorForPlanMoment(moment);
                    applyAnimationFrameAt(moment.localTimeMs);
                },
                onStop: function (result) {
                    animationIsPlaying = false;
                    if (result && result.moment) {
                        updatePlaybackCursorForPlanMoment(result.moment);
                    }

                    if (result && result.cancelled !== true) {
                        animationPlaybackElapsedMs = previewPlan.totalElapsedMs;
                        animationPlaybackSegmentIndex = previewPlan.segments.length - 1;
                        animationPlaybackRate = previewPlan.segments[previewPlan.segments.length - 1].rate;
                        applyAnimationFrameAt(previewPlan.durationMs);
                    } else {
                        updateAnimationControls();
                    }
                }
            });
        }

        function stepAnimationFrame(deltaMs) {
            if (!animationTimeline.enabled) {
                return;
            }

            stopAnimationPlayback();

            var nextTime = animationCurrentTimeMs + deltaMs;
            if (deltaMs > 0 && nextTime > animationTimeline.durationMs && animationTimeline.durationMs < MAX_ANIMATION_DURATION_MS) {
                animationTimeline.durationMs = normalizeAnimationDuration(animationTimeline.durationMs + ANIMATION_TIME_STEP_MS);
            }

            applyAnimationFrameAt(nextTime);
            syncPlaybackCursorToCurrentTime(0);
        }

        function finalizeExportStateToSafeIdle() {
            animationIsExporting = false;
            animationIsPlaying = false;
            animationPlaybackElapsedMs = 0;
            animationPlaybackSegmentIndex = 0;
            animationPlaybackRate = 1;
            activePlaybackPlan = buildPlaybackPlan(animationPlaybackMode, animationSlowMotionRate);
            applyAnimationFrameAt(0);
            updateAnimationControls();
        }

        var videoExporter = videoExportApi.createExporter({
            recordingFps: EXPORT_RECORDING_FPS,
            videoBitsPerSecond: EXPORT_VIDEO_BITS_PER_SECOND,
            serverUploadHardLimitBytes: EXPORT_SERVER_UPLOAD_HARD_LIMIT_BYTES,
            conservativeUploadLimitBytes: EXPORT_CONSERVATIVE_UPLOAD_LIMIT_BYTES,
            mimeCandidates: EXPORT_MIME_CANDIDATES,
            transcodeEndpoint: exportEndpoint,
            setStatus: setStatus,
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
            },
            getState: function () {
                return {
                    isExporting: animationIsExporting,
                    isAnimationEnabled: animationTimeline.enabled,
                    keyframeCount: countAnimationKeyframes(),
                    slowRate: animationSlowMotionRate,
                    playbackMode: animationPlaybackMode
                };
            },
            getExportMode: function () {
                return animationPlaybackMode;
            },
            getStage: function () {
                return board.getStage();
            },
            buildPlaybackPlan: function (mode, slowRate) {
                return buildPlaybackPlan(mode, slowRate);
            },
            stopAnimationPlayback: stopAnimationPlayback,
            beforeExportStart: function (context) {
                var exportPlan = context && context.exportPlan ? context.exportPlan : null;
                if (!exportPlan || !Array.isArray(exportPlan.segments) || exportPlan.segments.length === 0) {
                    return;
                }

                animationIsExporting = true;
                animationPlaybackElapsedMs = 0;
                animationPlaybackSegmentIndex = 0;
                animationPlaybackRate = exportPlan.segments[0].rate;
                activePlaybackPlan = exportPlan;
                board.clearSelection();
                updateAnimationControls();
            },
            runPlaybackPlan: runPlaybackPlan,
            onPlaybackMoment: function (moment) {
                updatePlaybackCursorForPlanMoment(moment);
                applyAnimationFrameAt(moment.localTimeMs);
            },
            onFinalizeExport: finalizeExportStateToSafeIdle
        });

        function exportAnimationVideo() {
            videoExporter.exportVideo();
        }

        function captureCurrentMomentForAllItems() {
            var nodes = board.getMainLayer().find('.item');
            if (nodes.length === 0) {
                updateAnimationControls();
                return;
            }

            var t = roundAnimationTime(animationCurrentTimeMs);
            nodes.forEach(function (node) {
                var itemId = String(node.getAttr('itemId') || '').trim();
                if (itemId === '') {
                    return;
                }

                var track = ensureTimelineTrack(itemId, ANIMATION_TRACK_PROPERTY_POSITION);
                upsertTrackKeyframe(track, t, { x: node.x(), y: node.y() });
            });

            removeStaleAnimationTracks();
            syncAnimationDurationToKeyframes();
            syncPlaybackCursorToCurrentTime(0);
            updateAnimationControls();
        }

        function toggleHighlightForSelectedItemAtCurrentMoment() {
            var selectedItemId = getSelectedAnimationItemId();
            if (selectedItemId === '') {
                updateAnimationControls();
                return;
            }

            var t = roundAnimationTime(animationCurrentTimeMs);
            var nextState = !isItemHighlightedAtTime(selectedItemId, t);
            var track = ensureTimelineTrack(selectedItemId, ANIMATION_TRACK_PROPERTY_HIGHLIGHT);
            upsertTrackKeyframe(track, t, { active: nextState });
            normalizeHighlightTrackKeyframes(track);

            animationTimeline.tracks = animationTimeline.tracks.filter(function (existingTrack) {
                return existingTrack.keyframes.length > 0;
            });

            syncAnimationDurationToKeyframes();
            syncPlaybackCursorToCurrentTime(0);
            applyAnimationFrameAt(animationCurrentTimeMs);
        }

        function removeCurrentMomentFromTimeline() {
            var t = roundAnimationTime(animationCurrentTimeMs);
            animationTimeline.tracks.forEach(function (track) {
                track.keyframes = track.keyframes.filter(function (frame) {
                    return Math.abs(frame.t - t) > ANIMATION_KEYFRAME_EPSILON_MS;
                });
                normalizeHighlightTrackKeyframes(track);
            });

            animationTimeline.tracks = animationTimeline.tracks.filter(function (track) {
                return track.keyframes.length > 0;
            });

            syncAnimationDurationToKeyframes();
            syncPlaybackCursorToCurrentTime(0);
            applyAnimationFrameAt(animationCurrentTimeMs);
        }

        function setAnimationEnabled(isEnabled) {
            if (animationIsExporting) {
                return;
            }

            animationTimeline.enabled = !!isEnabled;
            stopAnimationPlayback();

            if (animationTimeline.enabled) {
                removeStaleAnimationTracks();
                if (animationTimeline.tracks.length === 0) {
                    animationCurrentTimeMs = 0;
                    captureCurrentMomentForAllItems();
                }
                syncAnimationDurationToKeyframes();
                syncPlaybackCursorToCurrentTime(0);
                applyAnimationFrameAt(animationCurrentTimeMs);
            } else {
                animationCurrentTimeMs = 0;
                animationPlaybackElapsedMs = 0;
                animationPlaybackSegmentIndex = 0;
                animationPlaybackRate = 1;
                activePlaybackPlan = buildPlaybackPlan(animationPlaybackMode, animationSlowMotionRate);
                refreshHighlightOverlaysAtTime(0);
            }

            updateAnimationControls();
        }

        function parseLayerDrawing(raw) {
            if (typeof raw !== 'string' || raw.trim() === '') {
                return null;
            }
            try {
                var parsed = JSON.parse(raw);
                if (parsed && parsed.className === 'Layer') {
                    return parsed;
                }
            } catch (error) {
                return null;
            }
            return null;
        }

        function extractAnimationTimelineFromDrawing(raw) {
            var layer = parseLayerDrawing(raw);
            if (!layer || !layer.attrs || typeof layer.attrs !== 'object') {
                return createEmptyAnimationTimeline();
            }

            var doc = layer.attrs.vt_document && typeof layer.attrs.vt_document === 'object'
                ? layer.attrs.vt_document
                : null;
            if (!doc || !doc.animations || typeof doc.animations !== 'object') {
                return createEmptyAnimationTimeline();
            }

            return sanitizeAnimationTimeline(doc.animations);
        }

        function embedAnimationTimelineInDrawing(raw, timeline) {
            var layer = parseLayerDrawing(raw);
            if (!layer) {
                return raw;
            }

            layer.attrs = layer.attrs && typeof layer.attrs === 'object' ? layer.attrs : {};
            var doc = layer.attrs.vt_document && typeof layer.attrs.vt_document === 'object'
                ? layer.attrs.vt_document
                : {};

            var layout = board.getLayout();
            doc.kind = typeof doc.kind === 'string' && doc.kind !== ''
                ? doc.kind
                : String(window.KonvaSharedCore.DOC_MODEL_KIND || 'vt.konva.document');
            doc.version = Number.isFinite(Number(doc.version))
                ? Number(doc.version)
                : Number(window.KonvaSharedCore.DOC_MODEL_VERSION || 1);
            doc.field = doc.field && typeof doc.field === 'object'
                ? doc.field
                : {
                    key: layout.key,
                    width: layout.width,
                    height: layout.height
                };
            doc.animations = sanitizeAnimationTimeline(timeline);

            layer.attrs.vt_document = doc;
            layer.attrs.vt_document_kind = doc.kind;
            layer.attrs.vt_document_version = doc.version;

            return JSON.stringify(layer);
        }

        board.getMainLayer().on('destroy.animationAuthoring', '.item', function () {
            removeStaleAnimationTracks();
            syncAnimationDurationToKeyframes();
            syncPlaybackCursorToCurrentTime(0);
            refreshHighlightOverlaysAtTime(animationCurrentTimeMs);
            updateAnimationControls();
        });

        board.getMainLayer().on('dragmove.animationHighlight transform.animationHighlight', '.item', function () {
            if (animationApplyingFrame || !animationTimeline.enabled) {
                return;
            }
            refreshHighlightOverlaysAtTime(animationCurrentTimeMs);
            updateAnimationControls();
        });

        board.getStage().on('click.animationHighlight tap.animationHighlight', function () {
            updateAnimationControls();
        });

        animationPanel.bindHandlers({
            onToggle: function () {
                setAnimationEnabled(!animationTimeline.enabled);
            },
            onPlay: function () {
                if (!animationTimeline.enabled) {
                    return;
                }

                if (animationIsPlaying) {
                    stopAnimationPlayback();
                    return;
                }

                startAnimationPlayback();
            },
            onRestart: function () {
                if (!animationTimeline.enabled) {
                    return;
                }
                stopAnimationPlayback();
                applyAnimationFrameAt(0);
                syncPlaybackCursorToCurrentTime(0);
            },
            onPrevFrame: function () {
                stepAnimationFrame(-ANIMATION_TIME_STEP_MS);
            },
            onNextFrame: function () {
                stepAnimationFrame(ANIMATION_TIME_STEP_MS);
            },
            onAddKeyframe: function () {
                if (!animationTimeline.enabled) {
                    return;
                }
                stopAnimationPlayback();
                captureCurrentMomentForAllItems();
                applyAnimationFrameAt(animationCurrentTimeMs);
            },
            onToggleHighlight: function () {
                if (!animationTimeline.enabled) {
                    return;
                }
                stopAnimationPlayback();
                toggleHighlightForSelectedItemAtCurrentMoment();
            },
            onDeleteKeyframe: function () {
                if (!animationTimeline.enabled) {
                    return;
                }
                stopAnimationPlayback();
                removeCurrentMomentFromTimeline();
            },
            onRangeInput: function () {
                var nextTimeMs = Number(animationRangeEl.value || 0);
                stopAnimationPlayback();
                applyAnimationFrameAt(nextTimeMs);
                syncPlaybackCursorToCurrentTime(0);
            },
            onModeChange: function () {
                animationPlaybackMode = normalizePlaybackMode(animationModeEl.value);
                stopAnimationPlayback();
                syncPlaybackCursorToCurrentTime(0);
                updateAnimationControls();
            },
            onSlowRateChange: function () {
                animationSlowMotionRate = normalizePlaybackRate(animationSlowRateEl.value, DEFAULT_SLOW_MOTION_RATE);
                stopAnimationPlayback();
                syncPlaybackCursorToCurrentTime(0);
                updateAnimationControls();
            },
            onExport: function () {
                exportAnimationVideo();
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
                stopAnimationPlayback();
                animationTimeline = createEmptyAnimationTimeline();
                animationCurrentTimeMs = 0;
                animationPlaybackElapsedMs = 0;
                animationPlaybackSegmentIndex = 0;
                animationPlaybackRate = 1;
                activePlaybackPlan = buildPlaybackPlan(animationPlaybackMode, animationSlowMotionRate);
                refreshHighlightOverlaysAtTime(0);
                updateAnimationControls();
            });
        }

        if (deleteSelectedBtn) {
            deleteSelectedBtn.addEventListener('click', function () {
                board.clearArmedToolbarType();
                board.deleteSelected();
                removeStaleAnimationTracks();
                syncAnimationDurationToKeyframes();
                syncPlaybackCursorToCurrentTime(0);
                updateAnimationControls();
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
        if (animationModeEl) {
            animationPlaybackMode = normalizePlaybackMode(animationModeEl.value);
        }
        if (animationSlowRateEl) {
            animationSlowMotionRate = normalizePlaybackRate(animationSlowRateEl.value, DEFAULT_SLOW_MOTION_RATE);
        }
        activePlaybackPlan = buildPlaybackPlan(animationPlaybackMode, animationSlowMotionRate);
        syncPlaybackCursorToCurrentTime(0);
        updateAnimationControls();

        return {
            loadDrawingData: function (drawingJson) {
                loadDrawingState(drawingJson || '');
            },
            exportDrawingData: function () {
                stopAnimationPlayback();
                removeStaleAnimationTracks();
                syncAnimationDurationToKeyframes();
                syncPlaybackCursorToCurrentTime(0);
                var rawDrawing = board.exportDrawingData();
                return embedAnimationTimelineInDrawing(rawDrawing, animationTimeline);
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
            sortOrder: Number.isFinite(Number(raw.sort_order)) ? Number(raw.sort_order) : (index + 1),
            contextType: String(raw.context_type || (contextMode === 'team' ? 'team' : 'match')).trim(),
            sourceLabel: String(raw.source_label || '').trim()
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

    function buildContextPayload() {
        if (contextMode === 'team') {
            return { team_id: contextTeamId };
        }
        return { match_id: contextMatchId };
    }

    function setStatus(message, isError) {
        statusEl.textContent = message || '';
        statusEl.style.color = isError ? '#c62828' : '#6c757d';
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

    var tactics = safeJsonParse(tacticsDataEl.value).map(normalizeTactic);
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
            meta.textContent = buildMetaLabel(tactic);
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

        sortTacticsInPlace(normalized);

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

        var payload = Object.assign(buildContextPayload(), {
            tactic_id: selected.id,
            title: selected.title,
            phase: BOARD_PHASE,
            minute: selected.minute,
            field_type: BOARD_FIELD_TYPE,
            drawing_data: selected.drawingData,
            csrf_token: csrfTokenEl.value
        });

        fetch(saveEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfTokenEl.value
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        })
            .then(function (response) {
                return parseJsonResponse(response, 'Opslaan mislukt.');
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

        var deletePayload = Object.assign(buildContextPayload(), {
            tactic_id: Number(selected.id),
            csrf_token: csrfTokenEl.value
        });

        fetch(deleteEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfTokenEl.value
            },
            credentials: 'same-origin',
            body: JSON.stringify(deletePayload)
        })
            .then(function (response) {
                return parseJsonResponse(response, 'Verwijderen mislukt.');
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
