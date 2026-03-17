(function (window) {
    'use strict';

    if (window.KonvaSharedCore) {
        return;
    }

    var DOC_MODEL_KIND = 'vt.konva.document';
    var DOC_MODEL_VERSION = 1;
    var MIN_STABLE_CONTAINER_WIDTH = 120;
    var DEFAULT_TOUCH_DRAG_THRESHOLD_PX = 8;

    function isFiniteNumber(value) {
        return Number.isFinite(Number(value));
    }

    function isNonEmptyString(value) {
        return typeof value === 'string' && value.trim() !== '';
    }

    function clamp(value, minValue, maxValue) {
        return Math.max(minValue, Math.min(maxValue, value));
    }

    function shallowObject(value) {
        return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
    }

    function toStableWidth(value) {
        var width = Number(value);
        if (!Number.isFinite(width) || width < MIN_STABLE_CONTAINER_WIDTH) {
            return 0;
        }
        return width;
    }

    function createIdFactory(prefix) {
        var sequence = 0;
        var safePrefix = String(prefix || 'item').replace(/[^a-z0-9_-]/gi, '').toLowerCase() || 'item';

        return function nextId() {
            sequence += 1;
            return safePrefix + '-' + Date.now().toString(36) + '-' + sequence.toString(36) + '-' + Math.floor(Math.random() * 1679616).toString(36);
        };
    }

    function sanitizeAnimationTimeline(value) {
        var source = shallowObject(value);
        var tracks = Array.isArray(source.tracks) ? source.tracks : [];
        var normalizedTracks = tracks
            .filter(function (track) {
                return track && typeof track === 'object';
            })
            .map(function (track) {
                var srcTrack = shallowObject(track);
                var keyframes = Array.isArray(srcTrack.keyframes) ? srcTrack.keyframes : [];
                return {
                    id: isNonEmptyString(srcTrack.id) ? srcTrack.id.trim() : '',
                    itemId: isNonEmptyString(srcTrack.itemId) ? srcTrack.itemId.trim() : '',
                    property: isNonEmptyString(srcTrack.property) ? srcTrack.property.trim() : '',
                    keyframes: keyframes
                        .filter(function (frame) {
                            return frame && typeof frame === 'object';
                        })
                        .map(function (frame) {
                            var srcFrame = shallowObject(frame);
                            return {
                                t: isFiniteNumber(srcFrame.t) ? Number(srcFrame.t) : 0,
                                value: srcFrame.value
                            };
                        })
                };
            })
            .filter(function (track) {
                return track.id !== '' || track.itemId !== '';
            });

        return {
            enabled: source.enabled === true,
            durationMs: isFiniteNumber(source.durationMs) && Number(source.durationMs) > 0
                ? Math.round(Number(source.durationMs))
                : 0,
            fps: isFiniteNumber(source.fps) && Number(source.fps) > 0
                ? Math.round(Number(source.fps))
                : 60,
            tracks: normalizedTracks
        };
    }

    function sanitizeItemAnimation(value) {
        var source = shallowObject(value);
        var keyframes = Array.isArray(source.keyframes) ? source.keyframes : [];

        return {
            enabled: source.enabled === true,
            easing: isNonEmptyString(source.easing) ? source.easing.trim() : 'linear',
            keyframes: keyframes
                .filter(function (frame) {
                    return frame && typeof frame === 'object';
                })
                .map(function (frame) {
                    var srcFrame = shallowObject(frame);
                    return {
                        t: isFiniteNumber(srcFrame.t) ? Number(srcFrame.t) : 0,
                        x: isFiniteNumber(srcFrame.x) ? Number(srcFrame.x) : null,
                        y: isFiniteNumber(srcFrame.y) ? Number(srcFrame.y) : null,
                        rotation: isFiniteNumber(srcFrame.rotation) ? Number(srcFrame.rotation) : null,
                        opacity: isFiniteNumber(srcFrame.opacity) ? Number(srcFrame.opacity) : null
                    };
                })
        };
    }

    function extractLayerObjectFromRaw(raw) {
        if (typeof raw !== 'string' || raw.trim() === '') {
            return null;
        }

        try {
            var parsed = JSON.parse(raw);
            if (parsed && parsed.className === 'Layer') {
                return parsed;
            }

            if (parsed && parsed.kind === DOC_MODEL_KIND && parsed.layer && parsed.layer.className === 'Layer') {
                return parsed.layer;
            }

            if (parsed && parsed.layer && parsed.layer.className === 'Layer') {
                return parsed.layer;
            }
        } catch (error) {
            return null;
        }

        return null;
    }

    function cloneAttrValue(value) {
        if (Array.isArray(value)) {
            return value.slice();
        }

        if (value && typeof value === 'object') {
            try {
                return JSON.parse(JSON.stringify(value));
            } catch (error) {
                return null;
            }
        }

        return value;
    }

    function snapshotAttrsForDocument(attrs) {
        var source = shallowObject(attrs);
        var keys = [
            'x',
            'y',
            'width',
            'height',
            'rotation',
            'scaleX',
            'scaleY',
            'offsetX',
            'offsetY',
            'points',
            'dash',
            'stroke',
            'fill',
            'strokeWidth',
            'lineCap',
            'lineJoin',
            'tension',
            'pointerLength',
            'pointerWidth',
            'text',
            'fontSize',
            'imageSrc',
            'opacity'
        ];

        var snapshot = {};
        keys.forEach(function (key) {
            if (!Object.prototype.hasOwnProperty.call(source, key)) {
                return;
            }

            var copied = cloneAttrValue(source[key]);
            if (copied !== null && copied !== undefined) {
                snapshot[key] = copied;
            }
        });

        return snapshot;
    }

    function inferItemType(node) {
        var className = typeof node.getClassName === 'function' ? node.getClassName() : '';

        if (className === 'Image') {
            var imageSrc = String(node.getAttr('imageSrc') || '').trim();
            if (imageSrc !== '') {
                var cleaned = imageSrc.split('/').pop() || '';
                return cleaned.replace(/\.svg$/i, '').trim() || 'image';
            }
            return 'image';
        }

        if (className === 'Text') {
            var text = String(node.getAttr('text') || '').trim();
            if (text === '⚽') {
                return 'ball';
            }
            return text !== '' ? 'text' : 'text';
        }

        if (className === 'Rect') {
            return node.getAttr('isZone') ? 'zone' : 'rect';
        }

        if (className === 'Arrow') {
            var dash = node.getAttr('dash');
            if (Array.isArray(dash) && dash.length > 0) {
                return 'arrow_dashed';
            }

            var tension = Number(node.getAttr('tension') || 0);
            if (Number.isFinite(tension) && tension !== 0) {
                return 'arrow_curved';
            }
            return 'arrow';
        }

        return className ? className.toLowerCase() : 'unknown';
    }

    function normalizeLayout(layout) {
        if (!layout || typeof layout !== 'object') {
            return null;
        }

        var width = Number(layout.width);
        var height = Number(layout.height);

        if (!Number.isFinite(width) || !Number.isFinite(height) || width <= 0 || height <= 0) {
            return null;
        }

        var normalized = {
            key: isNonEmptyString(layout.key) ? layout.key.trim() : 'default',
            width: width,
            height: height,
            drawField: typeof layout.drawField === 'function' ? layout.drawField : function () {},
            isPointInside: typeof layout.isPointInside === 'function'
                ? layout.isPointInside
                : function (point) {
                    return !!(point && point.x >= 0 && point.x <= width && point.y >= 0 && point.y <= height);
                },
            clampPoint: typeof layout.clampPoint === 'function'
                ? layout.clampPoint
                : function (point) {
                    return {
                        x: clamp(point.x, 0, width),
                        y: clamp(point.y, 0, height)
                    };
                },
            clampNode: typeof layout.clampNode === 'function' ? layout.clampNode : null
        };

        return normalized;
    }

    function findTouchByIdentifier(touchList, identifier) {
        if (!touchList || !Number.isInteger(identifier)) {
            return null;
        }

        for (var i = 0; i < touchList.length; i += 1) {
            if (touchList[i].identifier === identifier) {
                return touchList[i];
            }
        }

        return null;
    }

    function createBoard(options) {
        var config = shallowObject(options);
        var containerEl = config.containerElement;

        if (!containerEl || !(containerEl instanceof HTMLElement)) {
            throw new Error('KonvaSharedCore.createBoard requires containerElement.');
        }

        if (typeof Konva === 'undefined') {
            throw new Error('Konva is required for KonvaSharedCore.');
        }

        var activeLayout = normalizeLayout(config.layout);
        if (!activeLayout) {
            throw new Error('KonvaSharedCore.createBoard requires a valid layout.');
        }

        var moduleKey = isNonEmptyString(config.moduleKey) ? config.moduleKey.trim() : 'shared';
        var rootElement = config.rootElement instanceof HTMLElement
            ? config.rootElement
            : (containerEl.closest('.editor-wrapper') || containerEl.parentElement || containerEl);
        var toolbarElement = config.toolbarElement instanceof HTMLElement ? config.toolbarElement : null;
        var toolbarItemSelector = isNonEmptyString(config.toolbarItemSelector)
            ? config.toolbarItemSelector
            : '.draggable-item[draggable="true"]';

        function resolveContainerWidth() {
            var directWidth = toStableWidth(containerEl.getBoundingClientRect().width || containerEl.offsetWidth);
            if (directWidth > 0) {
                return directWidth;
            }

            var parentWidth = toStableWidth(
                containerEl.parentElement
                    ? (containerEl.parentElement.getBoundingClientRect().width || containerEl.parentElement.offsetWidth)
                    : 0
            );
            if (parentWidth > 0) {
                return Math.min(parentWidth, activeLayout.width);
            }

            return activeLayout.width;
        }

        var containerWidth = resolveContainerWidth();
        var scale = containerWidth / activeLayout.width;
        var containerHeight = activeLayout.height * scale;
        containerEl.style.height = containerHeight + 'px';

        var stage = new Konva.Stage({
            container: containerEl,
            width: containerWidth,
            height: containerHeight,
            scale: { x: scale, y: scale }
        });

        var fieldLayer = new Konva.Layer();
        var mainLayer = new Konva.Layer();
        var uiLayer = new Konva.Layer();
        stage.add(fieldLayer);
        stage.add(mainLayer);
        stage.add(uiLayer);

        function syncStageToContainer() {
            var nextWidth = resolveContainerWidth();
            if (!Number.isFinite(nextWidth) || nextWidth <= 0) {
                return;
            }

            containerWidth = nextWidth;
            scale = containerWidth / activeLayout.width;
            containerHeight = activeLayout.height * scale;

            containerEl.style.height = containerHeight + 'px';
            stage.width(containerWidth);
            stage.height(containerHeight);
            stage.scale({ x: scale, y: scale });
            stage.batchDraw();
        }

        function drawField() {
            fieldLayer.destroyChildren();
            activeLayout.drawField(fieldLayer, {
                layout: activeLayout,
                stage: stage,
                fieldLayer: fieldLayer,
                mainLayer: mainLayer,
                uiLayer: uiLayer
            });
            fieldLayer.batchDraw();
        }

        function getPointerPosition() {
            var pos = stage.getPointerPosition();
            if (!pos) {
                return null;
            }

            var transform = stage.getAbsoluteTransform().copy().invert();
            return transform.point(pos);
        }

        function isPointInsideLayout(point) {
            return !!activeLayout.isPointInside(point, activeLayout);
        }

        function clampPointToLayout(point) {
            return activeLayout.clampPoint(point, activeLayout);
        }

        var nextItemId = createIdFactory(moduleKey + '-item');
        var loadedDocumentMeta = null;

        function ensureNodeItemId(node) {
            if (!node || typeof node.hasName !== 'function' || !node.hasName('item')) {
                return '';
            }

            var existingId = String(node.getAttr('itemId') || node.getAttr('item_id') || '').trim();
            if (existingId === '') {
                existingId = nextItemId();
            }

            node.setAttr('itemId', existingId);
            node.setAttr('item_id', existingId);
            return existingId;
        }

        function defaultClampNodeToLayout(node) {
            if (!node || typeof node.getClientRect !== 'function') {
                return;
            }

            var box = node.getClientRect({ relativeTo: mainLayer });
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
            if (box.x < 0) {
                dx = -box.x;
            } else if ((box.x + box.width) > activeLayout.width) {
                dx = activeLayout.width - (box.x + box.width);
            }

            if (box.y < 0) {
                dy = -box.y;
            } else if ((box.y + box.height) > activeLayout.height) {
                dy = activeLayout.height - (box.y + box.height);
            }

            if (dx !== 0 || dy !== 0) {
                node.x(node.x() + dx);
                node.y(node.y() + dy);
            }
        }

        function clampNodeToLayout(node) {
            if (!node || typeof node.hasName !== 'function' || !node.hasName('item')) {
                return;
            }

            if (typeof activeLayout.clampNode === 'function') {
                activeLayout.clampNode(node, {
                    layout: activeLayout,
                    mainLayer: mainLayer,
                    stage: stage,
                    fieldLayer: fieldLayer,
                    uiLayer: uiLayer
                });
                return;
            }

            defaultClampNodeToLayout(node);
        }

        function isToolDraggable(tool) {
            if (typeof config.isToolDraggable === 'function') {
                return config.isToolDraggable(tool);
            }
            return tool === 'select';
        }

        function attachItemConstraints(node) {
            if (!node || typeof node.hasName !== 'function' || !node.hasName('item')) {
                return;
            }

            ensureNodeItemId(node);
            clampNodeToLayout(node);

            node.off('.editorBounds');
            node.on('dragmove.editorBounds', function () {
                clampNodeToLayout(node);
                mainLayer.batchDraw();
            });

            node.on('dragend.editorBounds', function () {
                clampNodeToLayout(node);
                mainLayer.batchDraw();
                uiLayer.batchDraw();
            });
        }

        function registerItemNode(node) {
            if (!node || typeof node.hasName !== 'function' || !node.hasName('item')) {
                return;
            }

            ensureNodeItemId(node);
            node.draggable(isToolDraggable(currentTool));
            attachItemConstraints(node);
        }

        function addItemNode(node, optionsForNode) {
            if (!node) {
                return;
            }

            mainLayer.add(node);
            if (optionsForNode && optionsForNode.toBottom) {
                node.moveToBottom();
            }
            registerItemNode(node);
            mainLayer.batchDraw();
        }

        var tr = new Konva.Transformer({
            borderStroke: '#2196F3',
            borderStrokeWidth: 2,
            anchorStroke: '#2196F3',
            anchorFill: '#ffffff',
            anchorSize: 10,
            anchorCornerRadius: 5,
            padding: 5
        });
        uiLayer.add(tr);

        tr.on('transformend.editorBounds', function () {
            tr.nodes().forEach(clampNodeToLayout);
            tr.forceUpdate();
            mainLayer.batchDraw();
            uiLayer.batchDraw();
        });

        var selectionRectangle = new Konva.Rect({
            fill: 'rgba(0,0,255,0.2)',
            visible: false,
            listening: false,
            name: 'selection-rectangle'
        });
        uiLayer.add(selectionRectangle);

        var currentTool = isNonEmptyString(config.defaultTool) ? config.defaultTool.trim() : 'select';
        var toolButtons = shallowObject(config.toolButtons);
        var toolActiveClass = isNonEmptyString(config.toolActiveClass) ? config.toolActiveClass.trim() : 'active';
        var bindToolButtons = config.bindToolButtons !== false;

        function setNodesDraggableForTool(tool) {
            var draggable = isToolDraggable(tool);
            mainLayer.find('.item').forEach(function (node) {
                node.draggable(draggable);
            });
        }

        function updateToolButtons() {
            Object.keys(toolButtons).forEach(function (tool) {
                var buttonId = toolButtons[tool];
                if (!isNonEmptyString(buttonId)) {
                    return;
                }
                var button = document.getElementById(buttonId);
                if (!button) {
                    return;
                }
                button.classList.toggle(toolActiveClass, tool === currentTool);
            });
        }

        function clearSelection() {
            if (tr.nodes().length > 0) {
                tr.nodes([]);
                uiLayer.batchDraw();
            }
        }

        var isDrawing = false;
        var isSelecting = false;
        var activeDrawConfig = null;
        var activeShape = null;
        var startPos = null;
        var x1 = 0;
        var y1 = 0;
        var x2 = 0;
        var y2 = 0;

        function resetDrawingState() {
            isDrawing = false;
            activeDrawConfig = null;
            activeShape = null;
            startPos = null;
        }

        function resetSelectionState() {
            isSelecting = false;
            selectionRectangle.visible(false);
            uiLayer.batchDraw();
        }

        function resetTransientState() {
            resetDrawingState();
            if (isSelecting) {
                resetSelectionState();
            }
        }

        function notifyToolChange() {
            if (typeof config.onToolChange === 'function') {
                config.onToolChange({
                    tool: currentTool,
                    layout: activeLayout,
                    stage: stage,
                    mainLayer: mainLayer,
                    fieldLayer: fieldLayer,
                    uiLayer: uiLayer,
                    clearSelection: clearSelection
                });
            }
        }

        function setTool(nextTool) {
            if (!isNonEmptyString(nextTool)) {
                return;
            }

            currentTool = nextTool.trim();
            clearArmedToolbarType();
            resetTransientState();

            setNodesDraggableForTool(currentTool);
            if (currentTool !== 'select') {
                clearSelection();
            }
            updateToolButtons();
            notifyToolChange();
        }

        if (bindToolButtons) {
            Object.keys(toolButtons).forEach(function (tool) {
                var buttonId = toolButtons[tool];
                if (!isNonEmptyString(buttonId)) {
                    return;
                }
                var button = document.getElementById(buttonId);
                if (!button) {
                    return;
                }

                button.addEventListener('click', function () {
                    setTool(tool);
                });
            });
        }

        function isTouchKonvaEvent(event) {
            return !!(
                event &&
                event.evt &&
                typeof event.evt.type === 'string' &&
                event.evt.type.indexOf('touch') === 0
            );
        }

        function resolveDrawConfigForCurrentTool() {
            if (typeof config.resolveDrawConfig !== 'function') {
                return null;
            }

            var nextConfig = config.resolveDrawConfig({
                tool: currentTool,
                layout: activeLayout,
                stage: stage,
                mainLayer: mainLayer,
                fieldLayer: fieldLayer,
                uiLayer: uiLayer
            });

            return nextConfig && typeof nextConfig === 'object' ? nextConfig : null;
        }

        function createShapeForDrawConfig(drawConfig, point) {
            var kind = isNonEmptyString(drawConfig.kind) ? drawConfig.kind.trim() : 'arrow';

            if (kind === 'rect-zone') {
                var rectAttrs = Object.assign({
                    x: point.x,
                    y: point.y,
                    width: 0,
                    height: 0,
                    fill: 'rgba(255,255,255,0.2)',
                    stroke: 'rgba(255,255,255,0.5)',
                    strokeWidth: 1,
                    dash: [5, 5],
                    draggable: false,
                    name: 'item',
                    isZone: true
                }, shallowObject(drawConfig.attrs));

                return new Konva.Rect(rectAttrs);
            }

            var arrowAttrs = Object.assign({
                points: [point.x, point.y, point.x, point.y],
                stroke: '#ffffff',
                fill: '#ffffff',
                strokeWidth: 3,
                pointerLength: 15,
                pointerWidth: 15,
                lineCap: 'round',
                lineJoin: 'round',
                name: 'item'
            }, shallowObject(drawConfig.attrs));

            return new Konva.Arrow(arrowAttrs);
        }

        function calculateZigzagPoints(xStart, yStart, xEnd, yEnd, step, amplitude) {
            var dx = xEnd - xStart;
            var dy = yEnd - yStart;
            var distance = Math.sqrt((dx * dx) + (dy * dy));
            var angle = Math.atan2(dy, dx);
            var segmentStep = Number(step) > 0 ? Number(step) : 20;
            var segmentCount = Math.floor(distance / segmentStep);
            if (segmentCount < 2) {
                return [xStart, yStart, xEnd, yEnd];
            }

            var zigzagAmplitude = Number(amplitude) > 0 ? Number(amplitude) : 10;
            var points = [xStart, yStart];
            for (var i = 1; i < segmentCount; i += 1) {
                var t = i / segmentCount;
                var cx = xStart + (dx * t);
                var cy = yStart + (dy * t);
                var offset = (i % 2 === 0 ? 1 : -1) * zigzagAmplitude;
                var ox = cx + (Math.cos(angle + (Math.PI / 2)) * offset);
                var oy = cy + (Math.sin(angle + (Math.PI / 2)) * offset);
                points.push(ox, oy);
            }
            points.push(xEnd, yEnd);
            return points;
        }

        function finalizeDrawingShape() {
            if (!activeShape || !activeDrawConfig) {
                return;
            }

            if (activeDrawConfig.kind === 'poly-arrow') {
                var points = activeShape.points();
                var minPoints = isFiniteNumber(activeDrawConfig.minPoints)
                    ? Math.max(4, Math.round(Number(activeDrawConfig.minPoints)))
                    : 6;
                var minLength = isFiniteNumber(activeDrawConfig.minLength)
                    ? Math.max(0, Number(activeDrawConfig.minLength))
                    : 6;

                var hasEnoughPoints = points.length >= minPoints;
                var lineLength = hasEnoughPoints
                    ? Math.hypot(points[points.length - 2] - points[0], points[points.length - 1] - points[1])
                    : 0;

                if (!hasEnoughPoints || lineLength < minLength) {
                    activeShape.destroy();
                    mainLayer.batchDraw();
                }
            }
        }

        function selectNodesInsideBox(box) {
            if (box.width <= 5 && box.height <= 5) {
                tr.nodes([]);
                uiLayer.batchDraw();
                return;
            }

            var selected = mainLayer.find('.item').filter(function (shape) {
                if (shape instanceof Konva.Arrow) {
                    var arrowPoints = shape.points();
                    for (var i = 0; i < arrowPoints.length; i += 2) {
                        var px = arrowPoints[i];
                        var py = arrowPoints[i + 1];
                        if (!(px >= box.x && px <= box.x + box.width && py >= box.y && py <= box.y + box.height)) {
                            return false;
                        }
                    }
                    return true;
                }

                var shapeBox = shape.getClientRect({ relativeTo: mainLayer });
                return (
                    shapeBox.x >= box.x &&
                    shapeBox.y >= box.y &&
                    shapeBox.x + shapeBox.width <= box.x + box.width &&
                    shapeBox.y + shapeBox.height <= box.y + box.height
                );
            });

            tr.nodes(selected);
            uiLayer.batchDraw();
        }

        var armedToolbarType = '';

        stage.on('click tap', function (event) {
            if (armedToolbarType && isTouchKonvaEvent(event)) {
                return;
            }

            if (currentTool !== 'select') {
                return;
            }

            if (!event.target.hasName('item')) {
                return;
            }

            var metaPressed = event.evt.shiftKey || event.evt.ctrlKey || event.evt.metaKey;
            var isSelected = tr.nodes().indexOf(event.target) >= 0;

            if (!metaPressed && !isSelected) {
                tr.nodes([event.target]);
            } else if (metaPressed && isSelected) {
                var currentNodes = tr.nodes().slice();
                currentNodes.splice(currentNodes.indexOf(event.target), 1);
                tr.nodes(currentNodes);
            } else if (metaPressed && !isSelected) {
                tr.nodes(tr.nodes().concat([event.target]));
            }

            uiLayer.batchDraw();
        });

        stage.on('mousedown touchstart', function (event) {
            if (armedToolbarType && isTouchKonvaEvent(event)) {
                return;
            }

            if (event.target.getParent() instanceof Konva.Transformer) {
                return;
            }

            if (currentTool === 'select' && event.target.hasName('item')) {
                return;
            }

            var pos = getPointerPosition();
            if (!pos) {
                return;
            }

            if (currentTool === 'select') {
                if (!isPointInsideLayout(pos)) {
                    clearSelection();
                    return;
                }

                x1 = pos.x;
                y1 = pos.y;
                x2 = x1;
                y2 = y1;

                selectionRectangle.width(0);
                selectionRectangle.height(0);
                selectionRectangle.visible(true);
                selectionRectangle.moveToTop();
                tr.moveToTop();

                isSelecting = true;
                resetDrawingState();
                uiLayer.batchDraw();
                return;
            }

            if (!isPointInsideLayout(pos)) {
                return;
            }

            var drawConfig = resolveDrawConfigForCurrentTool();
            if (!drawConfig) {
                return;
            }

            var clampedStart = clampPointToLayout(pos);
            var shape = createShapeForDrawConfig(drawConfig, clampedStart);
            if (!shape) {
                return;
            }

            isDrawing = true;
            activeDrawConfig = drawConfig;
            activeShape = shape;
            startPos = clampedStart;

            mainLayer.add(shape);
            if (drawConfig.kind === 'rect-zone' || drawConfig.toBottom === true) {
                shape.moveToBottom();
            }
            registerItemNode(shape);
            mainLayer.batchDraw();
        });

        stage.on('mousemove touchmove', function (event) {
            if (armedToolbarType && isTouchKonvaEvent(event)) {
                return;
            }

            if (isSelecting) {
                var selectPos = getPointerPosition();
                if (!selectPos) {
                    return;
                }

                var clampedSelect = clampPointToLayout(selectPos);
                x2 = clampedSelect.x;
                y2 = clampedSelect.y;

                selectionRectangle.setAttrs({
                    visible: true,
                    x: Math.min(x1, x2),
                    y: Math.min(y1, y2),
                    width: Math.abs(x2 - x1),
                    height: Math.abs(y2 - y1)
                });
                uiLayer.batchDraw();
                return;
            }

            if (!isDrawing || !activeShape || !activeDrawConfig || !startPos) {
                return;
            }

            var movePos = getPointerPosition();
            if (!movePos) {
                return;
            }

            var clampedPos = clampPointToLayout(movePos);

            if (activeDrawConfig.kind === 'rect-zone') {
                activeShape.width(clampedPos.x - startPos.x);
                activeShape.height(clampedPos.y - startPos.y);
            } else if (activeDrawConfig.kind === 'poly-arrow') {
                var polyPoints = activeShape.points().slice();
                var minPointDistance = isFiniteNumber(activeDrawConfig.minPointDistance)
                    ? Math.max(1, Number(activeDrawConfig.minPointDistance))
                    : 2.4;

                if (polyPoints.length < 2) {
                    polyPoints.push(clampedPos.x, clampedPos.y);
                } else {
                    var lastX = polyPoints[polyPoints.length - 2];
                    var lastY = polyPoints[polyPoints.length - 1];
                    if (Math.hypot(clampedPos.x - lastX, clampedPos.y - lastY) >= minPointDistance) {
                        polyPoints.push(clampedPos.x, clampedPos.y);
                    } else {
                        polyPoints[polyPoints.length - 2] = clampedPos.x;
                        polyPoints[polyPoints.length - 1] = clampedPos.y;
                    }
                }

                activeShape.points(polyPoints);
            } else if (activeDrawConfig.kind === 'zigzag-arrow') {
                var zigzagStep = isFiniteNumber(activeDrawConfig.step) ? Number(activeDrawConfig.step) : 20;
                var zigzagAmplitude = isFiniteNumber(activeDrawConfig.amplitude) ? Number(activeDrawConfig.amplitude) : 10;
                activeShape.points(calculateZigzagPoints(startPos.x, startPos.y, clampedPos.x, clampedPos.y, zigzagStep, zigzagAmplitude));
            } else {
                var points = activeShape.points();
                points[2] = clampedPos.x;
                points[3] = clampedPos.y;
                activeShape.points(points);
            }

            mainLayer.batchDraw();
        });

        stage.on('mouseup touchend', function (event) {
            if (armedToolbarType && isTouchKonvaEvent(event)) {
                resetTransientState();
                return;
            }

            if (isDrawing) {
                finalizeDrawingShape();
            }
            resetDrawingState();

            if (!isSelecting) {
                return;
            }

            isSelecting = false;
            selectionRectangle.visible(false);

            selectNodesInsideBox({
                x: selectionRectangle.x(),
                y: selectionRectangle.y(),
                width: selectionRectangle.width(),
                height: selectionRectangle.height()
            });
        });

        stage.on('touchcancel', function () {
            resetTransientState();
        });

        function placeToolbarItemAtPosition(type, pos) {
            if (!isNonEmptyString(type) || !pos || !isPointInsideLayout(pos)) {
                return;
            }

            if (typeof config.placeItem !== 'function') {
                return;
            }

            config.placeItem({
                type: type,
                position: pos,
                layout: activeLayout,
                stage: stage,
                mainLayer: mainLayer,
                fieldLayer: fieldLayer,
                uiLayer: uiLayer,
                addItemNode: addItemNode,
                registerItemNode: registerItemNode,
                ensureNodeItemId: ensureNodeItemId,
                clampNodeToLayout: clampNodeToLayout,
                getCurrentTool: function () {
                    return currentTool;
                }
            });
        }

        var toolbarQueryRoot = toolbarElement || document;
        var toolbarItems = Array.prototype.slice.call(toolbarQueryRoot.querySelectorAll(toolbarItemSelector));
        var itemType = '';

        toolbarItems.forEach(function (item) {
            item.addEventListener('dragstart', function () {
                itemType = String(item.dataset.type || '').trim();
            });
            item.addEventListener('dragend', function () {
                itemType = '';
            });
        });

        var stageContainer = stage.container();
        stageContainer.addEventListener('dragover', function (event) {
            event.preventDefault();
        });

        stageContainer.addEventListener('drop', function (event) {
            event.preventDefault();
            stage.setPointersPositions(event);
            var pos = getPointerPosition();
            if (!pos || !itemType || !isPointInsideLayout(pos)) {
                itemType = '';
                return;
            }

            placeToolbarItemAtPosition(itemType, pos);
            itemType = '';
        });

        var touchConfig = Object.assign({
            enabled: true,
            dragThresholdPx: DEFAULT_TOUCH_DRAG_THRESHOLD_PX,
            hintClassName: 'konva-touch-hint',
            toolbarItemActiveClassName: 'is-touch-active',
            toolbarItemArmedClassName: 'is-touch-armed',
            containerArmedClassName: 'is-touch-armed',
            containerDropTargetClassName: 'is-touch-drop-target',
            containerPlaceFeedbackClassName: 'is-touch-place-feedback',
            containerDraggingClassName: 'is-touch-dragging',
            rootDraggingClassName: 'is-touch-dragging',
            bodyScrollLockClassName: 'konva-touch-scroll-lock',
            placementFeedbackDurationMs: 200,
            hintMessages: {
                dragging: 'Sleep naar het veld en laat los om te plaatsen.',
                armed: 'Tik op het veld om te plaatsen. Tik opnieuw op het icoon om te stoppen.'
            }
        }, shallowObject(config.touch));

        var prefersCoarseTouch = typeof window.matchMedia === 'function'
            ? window.matchMedia('(hover: none) and (pointer: coarse)').matches
            : false;

        var touchHintEl = null;
        if (touchConfig.enabled && toolbarElement) {
            touchHintEl = document.createElement('div');
            touchHintEl.className = touchConfig.hintClassName;
            touchHintEl.setAttribute('aria-live', 'polite');
            toolbarElement.appendChild(touchHintEl);
        }

        var activeToolbarTouch = null;
        var touchPlacementFeedbackTimeoutId = null;

        function setTouchHint(message) {
            if (!touchHintEl) {
                return;
            }

            if (!prefersCoarseTouch || !message) {
                touchHintEl.textContent = '';
                touchHintEl.classList.remove('is-visible');
                return;
            }

            touchHintEl.textContent = message;
            touchHintEl.classList.add('is-visible');
        }

        function setTouchScrollLock(isLocked) {
            if (!isNonEmptyString(touchConfig.bodyScrollLockClassName)) {
                return;
            }
            document.body.classList.toggle(touchConfig.bodyScrollLockClassName, !!isLocked);
        }

        function setCanvasDropTargetState(isActive) {
            if (!isNonEmptyString(touchConfig.containerDropTargetClassName)) {
                return;
            }
            stageContainer.classList.toggle(touchConfig.containerDropTargetClassName, !!isActive);
        }

        function setCanvasArmedState(isActive) {
            if (!isNonEmptyString(touchConfig.containerArmedClassName)) {
                return;
            }
            stageContainer.classList.toggle(touchConfig.containerArmedClassName, !!isActive);
        }

        function updateTouchHintForState() {
            if (activeToolbarTouch && activeToolbarTouch.isDragging) {
                setTouchHint(touchConfig.hintMessages.dragging || 'Sleep naar het veld en laat los om te plaatsen.');
                return;
            }

            if (armedToolbarType) {
                setTouchHint(touchConfig.hintMessages.armed || 'Tik op het veld om te plaatsen. Tik opnieuw op het icoon om te stoppen.');
                return;
            }

            setTouchHint('');
        }

        function updateArmedToolbarVisualState() {
            var armedClass = isNonEmptyString(touchConfig.toolbarItemArmedClassName)
                ? touchConfig.toolbarItemArmedClassName
                : 'is-touch-armed';

            toolbarItems.forEach(function (item) {
                var isArmed = String(item.dataset.type || '').trim() === armedToolbarType;
                item.classList.toggle(armedClass, isArmed);
            });

            setCanvasArmedState(Boolean(armedToolbarType));
            updateTouchHintForState();
        }

        function setArmedToolbarType(nextType) {
            armedToolbarType = String(nextType || '').trim();
            updateArmedToolbarVisualState();
        }

        function clearArmedToolbarType() {
            if (!armedToolbarType) {
                return;
            }
            setArmedToolbarType('');
        }

        function toggleArmedToolbarType(type) {
            if (!type) {
                setArmedToolbarType('');
                return;
            }

            if (armedToolbarType === type) {
                setArmedToolbarType('');
                return;
            }

            setArmedToolbarType(type);
        }

        function flashTouchPlacementFeedback() {
            if (!isNonEmptyString(touchConfig.containerPlaceFeedbackClassName)) {
                return;
            }

            stageContainer.classList.add(touchConfig.containerPlaceFeedbackClassName);
            if (touchPlacementFeedbackTimeoutId !== null) {
                window.clearTimeout(touchPlacementFeedbackTimeoutId);
            }

            var duration = isFiniteNumber(touchConfig.placementFeedbackDurationMs)
                ? Math.max(50, Math.round(Number(touchConfig.placementFeedbackDurationMs)))
                : 200;

            touchPlacementFeedbackTimeoutId = window.setTimeout(function () {
                stageContainer.classList.remove(touchConfig.containerPlaceFeedbackClassName);
                touchPlacementFeedbackTimeoutId = null;
            }, duration);
        }

        function createGhostForTouchItem(itemEl) {
            var ghost = itemEl.cloneNode(true);
            ghost.style.position = 'fixed';
            ghost.style.left = '0px';
            ghost.style.top = '0px';
            ghost.style.pointerEvents = 'none';
            ghost.style.opacity = '0.82';
            ghost.style.zIndex = '2147483647';
            ghost.style.transform = 'translate(-50%, -50%) scale(1.08)';
            document.body.appendChild(ghost);
            return ghost;
        }

        function requestGhostFrame() {
            if (!activeToolbarTouch || !activeToolbarTouch.ghost || activeToolbarTouch.rafId !== null) {
                return;
            }

            activeToolbarTouch.rafId = window.requestAnimationFrame(function () {
                if (!activeToolbarTouch || !activeToolbarTouch.ghost) {
                    return;
                }

                activeToolbarTouch.ghost.style.left = activeToolbarTouch.lastX + 'px';
                activeToolbarTouch.ghost.style.top = activeToolbarTouch.lastY + 'px';
                activeToolbarTouch.rafId = null;
            });
        }

        function getLogicalPositionFromViewportPoint(clientX, clientY) {
            var rect = stageContainer.getBoundingClientRect();
            if (
                clientX < rect.left ||
                clientX > rect.right ||
                clientY < rect.top ||
                clientY > rect.bottom
            ) {
                return null;
            }

            var stageX = clientX - rect.left;
            var stageY = clientY - rect.top;
            var scaleX = stage.scaleX() || 1;
            var scaleY = stage.scaleY() || 1;

            return {
                x: stageX / scaleX,
                y: stageY / scaleY
            };
        }

        function updateToolbarGhostPosition(touch) {
            if (!activeToolbarTouch || !touch) {
                return;
            }

            activeToolbarTouch.lastX = touch.clientX;
            activeToolbarTouch.lastY = touch.clientY;

            if (activeToolbarTouch.isDragging) {
                var pos = getLogicalPositionFromViewportPoint(touch.clientX, touch.clientY);
                setCanvasDropTargetState(Boolean(pos && isPointInsideLayout(pos)));
            }

            requestGhostFrame();
        }

        function startToolbarTouchDrag(touch) {
            if (!activeToolbarTouch || activeToolbarTouch.isDragging) {
                return;
            }

            activeToolbarTouch.isDragging = true;
            activeToolbarTouch.ghost = createGhostForTouchItem(activeToolbarTouch.itemEl);
            setTouchScrollLock(true);

            if (rootElement && isNonEmptyString(touchConfig.rootDraggingClassName)) {
                rootElement.classList.add(touchConfig.rootDraggingClassName);
            }
            if (isNonEmptyString(touchConfig.containerDraggingClassName)) {
                stageContainer.classList.add(touchConfig.containerDraggingClassName);
            }

            updateTouchHintForState();
            updateToolbarGhostPosition(touch);
        }

        function clearActiveToolbarTouch() {
            if (!activeToolbarTouch) {
                return;
            }

            if (activeToolbarTouch.rafId !== null) {
                window.cancelAnimationFrame(activeToolbarTouch.rafId);
            }

            if (activeToolbarTouch.ghost && activeToolbarTouch.ghost.parentNode) {
                activeToolbarTouch.ghost.parentNode.removeChild(activeToolbarTouch.ghost);
            }

            if (activeToolbarTouch.itemEl && isNonEmptyString(touchConfig.toolbarItemActiveClassName)) {
                activeToolbarTouch.itemEl.classList.remove(touchConfig.toolbarItemActiveClassName);
            }

            activeToolbarTouch = null;
            setCanvasDropTargetState(false);
            setTouchScrollLock(false);

            if (rootElement && isNonEmptyString(touchConfig.rootDraggingClassName)) {
                rootElement.classList.remove(touchConfig.rootDraggingClassName);
            }
            if (isNonEmptyString(touchConfig.containerDraggingClassName)) {
                stageContainer.classList.remove(touchConfig.containerDraggingClassName);
            }

            updateTouchHintForState();
        }

        function clearTouchState() {
            clearActiveToolbarTouch();
            clearArmedToolbarType();
        }

        if (touchConfig.enabled) {
            toolbarItems.forEach(function (item) {
                item.addEventListener('touchstart', function (event) {
                    if (!event.changedTouches || event.changedTouches.length === 0) {
                        return;
                    }

                    var type = String(item.dataset.type || '').trim();
                    if (!type) {
                        return;
                    }

                    if (activeToolbarTouch) {
                        clearActiveToolbarTouch();
                    }

                    var touch = event.changedTouches[0];
                    if (!touch) {
                        return;
                    }

                    if (isNonEmptyString(touchConfig.toolbarItemActiveClassName)) {
                        item.classList.add(touchConfig.toolbarItemActiveClassName);
                    }

                    activeToolbarTouch = {
                        type: type,
                        itemEl: item,
                        ghost: null,
                        identifier: touch.identifier,
                        startX: touch.clientX,
                        startY: touch.clientY,
                        lastX: touch.clientX,
                        lastY: touch.clientY,
                        isDragging: false,
                        rafId: null
                    };

                    updateTouchHintForState();
                }, { passive: false });
            });

            document.addEventListener('touchmove', function (event) {
                if (!activeToolbarTouch) {
                    return;
                }

                var touch = findTouchByIdentifier(event.touches, activeToolbarTouch.identifier);
                if (!touch) {
                    return;
                }

                var dx = touch.clientX - activeToolbarTouch.startX;
                var dy = touch.clientY - activeToolbarTouch.startY;
                var threshold = isFiniteNumber(touchConfig.dragThresholdPx)
                    ? Math.max(1, Number(touchConfig.dragThresholdPx))
                    : DEFAULT_TOUCH_DRAG_THRESHOLD_PX;

                if (!activeToolbarTouch.isDragging && Math.hypot(dx, dy) >= threshold) {
                    startToolbarTouchDrag(touch);
                }

                if (!activeToolbarTouch.isDragging) {
                    return;
                }

                event.preventDefault();
                updateToolbarGhostPosition(touch);
            }, { passive: false });

            document.addEventListener('touchend', function (event) {
                if (!activeToolbarTouch) {
                    return;
                }

                var touch = findTouchByIdentifier(event.changedTouches, activeToolbarTouch.identifier);
                if (!touch) {
                    return;
                }

                if (activeToolbarTouch.isDragging) {
                    event.preventDefault();
                    var dropPos = getLogicalPositionFromViewportPoint(touch.clientX, touch.clientY);
                    if (dropPos && isPointInsideLayout(dropPos)) {
                        placeToolbarItemAtPosition(activeToolbarTouch.type, dropPos);
                        flashTouchPlacementFeedback();
                    }
                } else {
                    toggleArmedToolbarType(activeToolbarTouch.type);
                }

                clearActiveToolbarTouch();
            }, { passive: false });

            document.addEventListener('touchcancel', function () {
                if (!activeToolbarTouch) {
                    return;
                }
                clearActiveToolbarTouch();
            }, { passive: false });

            stageContainer.addEventListener('touchstart', function (event) {
                if (!armedToolbarType || activeToolbarTouch) {
                    return;
                }

                event.preventDefault();
            }, { passive: false });

            stageContainer.addEventListener('touchmove', function (event) {
                if (!armedToolbarType || activeToolbarTouch) {
                    return;
                }

                event.preventDefault();
            }, { passive: false });

            stageContainer.addEventListener('touchend', function (event) {
                if (!armedToolbarType || activeToolbarTouch) {
                    return;
                }

                var touch = event.changedTouches && event.changedTouches.length > 0
                    ? event.changedTouches[0]
                    : null;
                if (!touch) {
                    return;
                }

                var pos = getLogicalPositionFromViewportPoint(touch.clientX, touch.clientY);
                if (!pos || !isPointInsideLayout(pos)) {
                    return;
                }

                event.preventDefault();
                placeToolbarItemAtPosition(armedToolbarType, pos);
                flashTouchPlacementFeedback();
            }, { passive: false });

            document.addEventListener('touchstart', function (event) {
                if (!armedToolbarType) {
                    return;
                }

                if (rootElement && rootElement.contains(event.target)) {
                    return;
                }

                clearArmedToolbarType();
            }, { passive: true });
        }

        function handleVisibilityInterrupt() {
            resetTransientState();
            clearTouchState();
        }

        window.addEventListener('blur', handleVisibilityInterrupt);
        window.addEventListener('pagehide', handleVisibilityInterrupt);
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                handleVisibilityInterrupt();
            }
        });

        function loadImagesForLayerChildren(children) {
            children.forEach(function (child) {
                if (child.getClassName() === 'Image' && child.getAttr('imageSrc')) {
                    var imgObj = new Image();
                    imgObj.onload = function () {
                        child.image(imgObj);
                        mainLayer.batchDraw();
                    };
                    imgObj.src = child.getAttr('imageSrc');
                }
            });
        }

        function clearLayerData() {
            resetTransientState();
            mainLayer.destroyChildren();
            clearSelection();
            mainLayer.batchDraw();
            uiLayer.batchDraw();
        }

        function buildDocumentModel() {
            var nowIso = new Date().toISOString();
            var previousMeta = shallowObject(loadedDocumentMeta);
            var items = mainLayer.find('.item').map(function (node) {
                var itemId = ensureNodeItemId(node);
                var attrs = shallowObject(node.getAttrs ? node.getAttrs() : {});
                var animation = sanitizeItemAnimation(attrs.animation);
                return {
                    id: itemId,
                    className: typeof node.getClassName === 'function' ? node.getClassName() : '',
                    type: inferItemType(node),
                    zIndex: typeof node.getZIndex === 'function' ? node.getZIndex() : 0,
                    attrs: snapshotAttrsForDocument(attrs),
                    animation: animation,
                    animationTrackId: isNonEmptyString(attrs.animationTrackId) ? attrs.animationTrackId.trim() : null
                };
            });

            return {
                kind: DOC_MODEL_KIND,
                version: DOC_MODEL_VERSION,
                module: isNonEmptyString(previousMeta.module) ? previousMeta.module : moduleKey,
                createdAt: isNonEmptyString(previousMeta.createdAt) ? previousMeta.createdAt : nowIso,
                updatedAt: nowIso,
                field: {
                    key: activeLayout.key,
                    width: activeLayout.width,
                    height: activeLayout.height
                },
                items: items,
                animations: sanitizeAnimationTimeline(previousMeta.animations)
            };
        }

        function exportDrawingData() {
            clearSelection();
            mainLayer.find('.item').forEach(ensureNodeItemId);

            var rawJson = mainLayer.toJSON();
            try {
                var parsed = JSON.parse(rawJson);
                parsed.attrs = shallowObject(parsed.attrs);
                parsed.attrs.vt_document = buildDocumentModel();
                parsed.attrs.vt_document_kind = DOC_MODEL_KIND;
                parsed.attrs.vt_document_version = DOC_MODEL_VERSION;
                return JSON.stringify(parsed);
            } catch (error) {
                return rawJson;
            }
        }

        function loadDrawingData(raw) {
            if (!isNonEmptyString(raw)) {
                loadedDocumentMeta = null;
                clearLayerData();
                return;
            }

            var layerObject = extractLayerObjectFromRaw(raw);
            if (!layerObject) {
                loadedDocumentMeta = null;
                clearLayerData();
                return;
            }

            try {
                var jsonString = JSON.stringify(layerObject);
                var tempLayer = Konva.Node.create(jsonString);
                var children = tempLayer.getChildren().slice();

                mainLayer.destroyChildren();
                clearSelection();

                children.forEach(function (child) {
                    child.moveTo(mainLayer);
                    if (child.name && child.name() === 'item') {
                        registerItemNode(child);
                    }
                });

                loadImagesForLayerChildren(children);

                var attrs = shallowObject(layerObject.attrs);
                loadedDocumentMeta = attrs.vt_document && typeof attrs.vt_document === 'object'
                    ? attrs.vt_document
                    : null;

                mainLayer.batchDraw();
                uiLayer.batchDraw();
            } catch (error) {
                loadedDocumentMeta = null;
                clearLayerData();
            }
        }

        function deleteSelected() {
            var selectedNodes = tr.nodes();
            if (selectedNodes.length === 0) {
                return;
            }

            selectedNodes.forEach(function (node) {
                node.destroy();
            });
            clearSelection();
            mainLayer.batchDraw();
            uiLayer.batchDraw();
        }

        function sendSelectedToBack() {
            var selectedNodes = tr.nodes();
            if (selectedNodes.length === 0) {
                return;
            }

            selectedNodes.forEach(function (node) {
                node.moveToBottom();
            });
            mainLayer.batchDraw();
        }

        function clearAll() {
            clearArmedToolbarType();
            loadedDocumentMeta = null;
            clearLayerData();
        }

        function refreshLayout() {
            syncStageToContainer();
            drawField();
            mainLayer.find('.item').forEach(function (node) {
                clampNodeToLayout(node);
            });
            tr.forceUpdate();
            mainLayer.batchDraw();
            uiLayer.batchDraw();
        }

        function setLayout(nextLayout) {
            var normalized = normalizeLayout(nextLayout);
            if (!normalized) {
                return;
            }

            activeLayout = normalized;
            refreshLayout();
        }

        document.addEventListener('keydown', function (event) {
            if ((event.key !== 'Delete' && event.key !== 'Backspace') || tr.nodes().length === 0) {
                return;
            }

            var activeElement = document.activeElement;
            var activeTag = activeElement && activeElement.tagName ? activeElement.tagName.toUpperCase() : '';
            var isEditable = !!(
                activeElement &&
                (activeElement.isContentEditable || activeTag === 'INPUT' || activeTag === 'TEXTAREA' || activeTag === 'SELECT')
            );

            if (isEditable) {
                return;
            }

            if (event.key === 'Backspace') {
                event.preventDefault();
            }

            deleteSelected();
        });

        drawField();
        setNodesDraggableForTool(currentTool);
        updateToolButtons();
        notifyToolChange();
        syncStageToContainer();

        window.addEventListener('resize', syncStageToContainer);

        if (typeof ResizeObserver === 'function' && containerEl.parentElement) {
            var resizeObserver = new ResizeObserver(function () {
                syncStageToContainer();
            });
            resizeObserver.observe(containerEl.parentElement);
        }

        return {
            getStage: function () {
                return stage;
            },
            getMainLayer: function () {
                return mainLayer;
            },
            getFieldLayer: function () {
                return fieldLayer;
            },
            getUiLayer: function () {
                return uiLayer;
            },
            getLayout: function () {
                return activeLayout;
            },
            setLayout: setLayout,
            refreshLayout: refreshLayout,
            getTool: function () {
                return currentTool;
            },
            setTool: setTool,
            clearSelection: clearSelection,
            deleteSelected: deleteSelected,
            sendSelectedToBack: sendSelectedToBack,
            clearAll: clearAll,
            loadDrawingData: loadDrawingData,
            exportDrawingData: exportDrawingData,
            exportDocumentModel: buildDocumentModel,
            exportImageDataUrl: function (optionsForDataUrl) {
                clearSelection();
                return stage.toDataURL(optionsForDataUrl || {});
            },
            placeItemAt: function (type, pos) {
                placeToolbarItemAtPosition(type, pos);
            },
            clearArmedToolbarType: clearArmedToolbarType,
            registerItemNode: registerItemNode,
            ensureNodeItemId: ensureNodeItemId
        };
    }

    window.KonvaSharedCore = {
        createBoard: createBoard,
        DOC_MODEL_KIND: DOC_MODEL_KIND,
        DOC_MODEL_VERSION: DOC_MODEL_VERSION
    };
}(window));
