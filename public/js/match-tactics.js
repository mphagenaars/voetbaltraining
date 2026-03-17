document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('match-tactics-root');
    if (!root) {
        return;
    }

    if (typeof Konva === 'undefined') {
        console.error('Konva is required for match tactics editor.');
        return;
    }

    const BOARD_PHASE = 'open_play';
    const BOARD_FIELD_TYPE = 'standard_30x42_5';

    const matchIdEl = document.getElementById('match_id');
    const csrfTokenEl = document.getElementById('csrf_token');
    const tacticsDataEl = document.getElementById('match_tactics_data');
    const listEl = document.getElementById('tactics-list');
    const titleEl = document.getElementById('tactics-title');
    const minuteEl = document.getElementById('tactics-minute');
    const statusEl = document.getElementById('tactics-save-status');
    const saveBtn = document.getElementById('tactics-save-btn');
    const deleteBtn = document.getElementById('tactics-delete-btn');
    const newBtn = document.getElementById('tactics-new-btn');

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

    const MIN_STABLE_CONTAINER_WIDTH = 120;

    function toStableWidth(value) {
        const width = Number(value);
        if (!Number.isFinite(width) || width < MIN_STABLE_CONTAINER_WIDTH) {
            return 0;
        }

        return width;
    }

    function createTacticsBoard() {
        const containerEl = document.getElementById('tactics-container');
        if (!containerEl) {
            return null;
        }

        // Logical board: pitch is exactly 30m x 42.5m (1m = 10 units).
        const PITCH = {
            x: 20,
            y: 30,
            width: 300,
            height: 425
        };
        const GOAL_WIDTH = 50; // 5m
        const GOAL_DEPTH = 20; // 2m
        const V_WIDTH = 340;
        const V_HEIGHT = 485;

        function resolveContainerWidth() {
            const directWidth = toStableWidth(containerEl.getBoundingClientRect().width || containerEl.offsetWidth);
            if (directWidth > 0) {
                return directWidth;
            }

            const parentWidth = toStableWidth(
                containerEl.parentElement
                    ? (containerEl.parentElement.getBoundingClientRect().width || containerEl.parentElement.offsetWidth)
                    : 0
            );
            if (parentWidth > 0) {
                return Math.min(parentWidth, V_WIDTH);
            }

            return V_WIDTH;
        }

        let containerWidth = resolveContainerWidth();
        let scale = containerWidth / V_WIDTH;
        let containerHeight = V_HEIGHT * scale;
        containerEl.style.height = containerHeight + 'px';

        const stage = new Konva.Stage({
            container: 'tactics-container',
            width: containerWidth,
            height: containerHeight,
            scale: { x: scale, y: scale }
        });

        const fieldLayer = new Konva.Layer();
        const mainLayer = new Konva.Layer();
        const uiLayer = new Konva.Layer();
        stage.add(fieldLayer);
        stage.add(mainLayer);
        stage.add(uiLayer);

        function syncStageToContainer() {
            const nextWidth = resolveContainerWidth();
            if (!Number.isFinite(nextWidth) || nextWidth <= 0) {
                return;
            }

            containerWidth = nextWidth;
            scale = containerWidth / V_WIDTH;
            containerHeight = V_HEIGHT * scale;

            containerEl.style.height = containerHeight + 'px';
            stage.width(containerWidth);
            stage.height(containerHeight);
            stage.scale({ x: scale, y: scale });
            stage.batchDraw();
        }

        function getPointerPosition() {
            const pos = stage.getPointerPosition();
            if (!pos) {
                return null;
            }
            const transform = stage.getAbsoluteTransform().copy().invert();
            return transform.point(pos);
        }

        function clampPointToPitch(point) {
            return {
                x: Math.max(PITCH.x, Math.min(PITCH.x + PITCH.width, point.x)),
                y: Math.max(PITCH.y, Math.min(PITCH.y + PITCH.height, point.y))
            };
        }

        function isInsidePitch(point) {
            return (
                point.x >= PITCH.x &&
                point.x <= (PITCH.x + PITCH.width) &&
                point.y >= PITCH.y &&
                point.y <= (PITCH.y + PITCH.height)
            );
        }

        function drawField() {
            fieldLayer.destroyChildren();

            fieldLayer.add(new Konva.Rect({
                x: 0,
                y: 0,
                width: V_WIDTH,
                height: V_HEIGHT,
                fill: '#3f9f4c'
            }));

            const stripeCount = 10;
            const stripeHeight = PITCH.height / stripeCount;
            for (let i = 0; i < stripeCount; i++) {
                fieldLayer.add(new Konva.Rect({
                    x: PITCH.x,
                    y: PITCH.y + (i * stripeHeight),
                    width: PITCH.width,
                    height: stripeHeight,
                    fill: i % 2 === 0 ? 'rgba(255,255,255,0.045)' : 'rgba(0,0,0,0.03)'
                }));
            }

            const line = {
                stroke: 'rgba(255,255,255,0.9)',
                strokeWidth: 2
            };

            fieldLayer.add(new Konva.Rect({
                x: PITCH.x,
                y: PITCH.y,
                width: PITCH.width,
                height: PITCH.height,
                ...line
            }));

            const centerX = PITCH.x + (PITCH.width / 2);
            const centerY = PITCH.y + (PITCH.height / 2);

            fieldLayer.add(new Konva.Line({
                points: [PITCH.x, centerY, PITCH.x + PITCH.width, centerY],
                ...line
            }));

            fieldLayer.add(new Konva.Circle({
                x: centerX,
                y: centerY,
                radius: 40,
                ...line
            }));

            fieldLayer.add(new Konva.Circle({
                x: centerX,
                y: centerY,
                radius: 2,
                fill: 'rgba(255,255,255,0.95)'
            }));

            const penaltyAreaWidth = 160;
            const penaltyAreaDepth = 60;
            const goalAreaWidth = 80;
            const goalAreaDepth = 25;
            const penaltySpotDistance = 40;

            fieldLayer.add(new Konva.Rect({
                x: centerX - (penaltyAreaWidth / 2),
                y: PITCH.y,
                width: penaltyAreaWidth,
                height: penaltyAreaDepth,
                ...line
            }));

            fieldLayer.add(new Konva.Rect({
                x: centerX - (penaltyAreaWidth / 2),
                y: PITCH.y + PITCH.height - penaltyAreaDepth,
                width: penaltyAreaWidth,
                height: penaltyAreaDepth,
                ...line
            }));

            fieldLayer.add(new Konva.Rect({
                x: centerX - (goalAreaWidth / 2),
                y: PITCH.y,
                width: goalAreaWidth,
                height: goalAreaDepth,
                ...line
            }));

            fieldLayer.add(new Konva.Rect({
                x: centerX - (goalAreaWidth / 2),
                y: PITCH.y + PITCH.height - goalAreaDepth,
                width: goalAreaWidth,
                height: goalAreaDepth,
                ...line
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
                ...line
            }));

            fieldLayer.add(new Konva.Rect({
                x: centerX - (GOAL_WIDTH / 2),
                y: PITCH.y + PITCH.height,
                width: GOAL_WIDTH,
                height: GOAL_DEPTH,
                ...line
            }));

            fieldLayer.batchDraw();
        }

        const tr = new Konva.Transformer({
            borderStroke: '#2196F3',
            borderStrokeWidth: 2,
            anchorStroke: '#2196F3',
            anchorFill: '#ffffff',
            anchorSize: 10,
            anchorCornerRadius: 5,
            padding: 5
        });
        uiLayer.add(tr);

        const selectionRectangle = new Konva.Rect({
            fill: 'rgba(0,0,255,0.2)',
            visible: false,
            listening: false,
            name: 'selection-rectangle'
        });
        uiLayer.add(selectionRectangle);

        function clampNodeToField(node) {
            if (!node || typeof node.getClientRect !== 'function') {
                return;
            }

            const box = node.getClientRect({ relativeTo: mainLayer });
            if (!Number.isFinite(box.x) || !Number.isFinite(box.y) || !Number.isFinite(box.width) || !Number.isFinite(box.height)) {
                return;
            }

            let dx = 0;
            let dy = 0;

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

        function attachItemConstraints(node) {
            if (!node || typeof node.hasName !== 'function' || !node.hasName('item')) {
                return;
            }

            clampNodeToField(node);

            node.on('dragmove.editorBounds', function () {
                clampNodeToField(node);
                mainLayer.batchDraw();
            });

            node.on('dragend.editorBounds', function () {
                clampNodeToField(node);
                mainLayer.batchDraw();
                uiLayer.batchDraw();
            });
        }

        tr.on('transformend.editorBounds', function () {
            tr.nodes().forEach(clampNodeToField);
            tr.forceUpdate();
            mainLayer.batchDraw();
            uiLayer.batchDraw();
        });

        let currentTool = 'select';
        let itemType = '';
        let isDrawing = false;
        let isSelecting = false;
        let lastLine;
        let startPos;
        let x1;
        let y1;
        let x2;
        let y2;

        function setTool(tool) {
            currentTool = tool;

            const toolButtons = {
                select: 'tactics-tool-select',
                arrow: 'tactics-tool-arrow',
                dashed: 'tactics-tool-dashed',
                zigzag: 'tactics-tool-zigzag'
            };

            Object.keys(toolButtons).forEach(function (key) {
                const btn = document.getElementById(toolButtons[key]);
                if (!btn) {
                    return;
                }

                if (key === tool) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });

            const isDraggable = tool === 'select';
            mainLayer.find('.item').forEach(function (node) {
                node.draggable(isDraggable);
            });
        }

        function calculateZigzagPoints(startX, startY, endX, endY) {
            const dx = endX - startX;
            const dy = endY - startY;
            const dist = Math.sqrt(dx * dx + dy * dy);
            const angle = Math.atan2(dy, dx);

            const step = 20;
            const segments = Math.floor(dist / step);
            if (segments < 2) {
                return [startX, startY, endX, endY];
            }

            const points = [startX, startY];
            for (let i = 1; i < segments; i++) {
                const t = i / segments;
                const cx = startX + dx * t;
                const cy = startY + dy * t;
                const offset = (i % 2 === 0 ? 1 : -1) * 10;
                const ox = cx + Math.cos(angle + Math.PI / 2) * offset;
                const oy = cy + Math.sin(angle + Math.PI / 2) * offset;
                points.push(ox, oy);
            }
            points.push(endX, endY);
            return points;
        }

        function loadDrawingData(dataJson) {
            if (!dataJson || typeof dataJson !== 'string') {
                mainLayer.destroyChildren();
                tr.nodes([]);
                mainLayer.batchDraw();
                uiLayer.batchDraw();
                return;
            }

            try {
                const tempLayer = Konva.Node.create(dataJson);
                const children = tempLayer.getChildren().slice();

                mainLayer.destroyChildren();
                tr.nodes([]);

                children.forEach(function (child) {
                    child.moveTo(mainLayer);

                    if (child.getClassName() === 'Image' && child.getAttr('imageSrc')) {
                        const imgObj = new Image();
                        imgObj.onload = function () {
                            child.image(imgObj);
                            mainLayer.batchDraw();
                        };
                        imgObj.src = child.getAttr('imageSrc');
                    }

                    if (child.name() === 'item') {
                        child.draggable(currentTool === 'select');
                        attachItemConstraints(child);
                    }
                });

                mainLayer.batchDraw();
                uiLayer.batchDraw();
            } catch (error) {
                console.error('Error loading tactics drawing:', error);
                mainLayer.destroyChildren();
                tr.nodes([]);
                mainLayer.batchDraw();
                uiLayer.batchDraw();
            }
        }

        stage.on('click tap', function (event) {
            if (!event.target.hasName('item')) {
                return;
            }

            const metaPressed = event.evt.shiftKey || event.evt.ctrlKey || event.evt.metaKey;
            const isSelected = tr.nodes().indexOf(event.target) >= 0;

            if (!metaPressed && !isSelected) {
                tr.nodes([event.target]);
            } else if (metaPressed && isSelected) {
                const nodes = tr.nodes().slice();
                nodes.splice(nodes.indexOf(event.target), 1);
                tr.nodes(nodes);
            } else if (metaPressed && !isSelected) {
                tr.nodes(tr.nodes().concat([event.target]));
            }
            uiLayer.batchDraw();
        });

        stage.on('mousedown touchstart', function (event) {
            if (event.target.getParent() instanceof Konva.Transformer) {
                return;
            }

            if (event.target.hasName('item')) {
                return;
            }

            const pos = getPointerPosition();
            if (!pos) {
                return;
            }

            if (currentTool === 'select') {
                if (!isInsidePitch(pos)) {
                    tr.nodes([]);
                    uiLayer.batchDraw();
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
                uiLayer.batchDraw();
                return;
            }

            if (!isInsidePitch(pos)) {
                return;
            }

            isDrawing = true;
            startPos = pos;

            const config = {
                points: [pos.x, pos.y, pos.x, pos.y],
                stroke: 'white',
                fill: 'white',
                strokeWidth: 3,
                pointerLength: 15,
                pointerWidth: 15,
                name: 'item'
            };

            if (currentTool === 'dashed') {
                config.dash = [10, 10];
            } else if (currentTool === 'zigzag') {
                config.tension = 0.4;
            }

            lastLine = new Konva.Arrow(config);
            mainLayer.add(lastLine);
            attachItemConstraints(lastLine);

            mainLayer.batchDraw();
        });

        stage.on('mousemove touchmove', function () {
            if (isSelecting) {
                const pos = getPointerPosition();
                if (!pos) {
                    return;
                }

                const clamped = clampPointToPitch(pos);
                x2 = clamped.x;
                y2 = clamped.y;

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

            if (!isDrawing) {
                return;
            }

            const pos = getPointerPosition();
            if (!pos || !lastLine) {
                return;
            }

            const clamped = clampPointToPitch(pos);

            if (currentTool === 'zigzag') {
                lastLine.points(calculateZigzagPoints(startPos.x, startPos.y, clamped.x, clamped.y));
            } else {
                const points = lastLine.points();
                points[2] = clamped.x;
                points[3] = clamped.y;
                lastLine.points(points);
            }

            mainLayer.batchDraw();
        });

        stage.on('mouseup touchend', function () {
            isDrawing = false;

            if (!isSelecting) {
                return;
            }

            isSelecting = false;
            selectionRectangle.visible(false);

            const box = {
                x: selectionRectangle.x(),
                y: selectionRectangle.y(),
                width: selectionRectangle.width(),
                height: selectionRectangle.height()
            };

            if (box.width > 5 || box.height > 5) {
                const shapes = mainLayer.find('.item');
                const selected = shapes.filter(function (shape) {
                    if (shape instanceof Konva.Arrow) {
                        const points = shape.points();
                        for (let i = 0; i < points.length; i += 2) {
                            const px = points[i];
                            const py = points[i + 1];
                            if (!(px >= box.x && px <= box.x + box.width && py >= box.y && py <= box.y + box.height)) {
                                return false;
                            }
                        }
                        return true;
                    }

                    const shapeBox = shape.getClientRect({ relativeTo: mainLayer });
                    return (
                        shapeBox.x >= box.x &&
                        shapeBox.y >= box.y &&
                        shapeBox.x + shapeBox.width <= box.x + box.width &&
                        shapeBox.y + shapeBox.height <= box.y + box.height
                    );
                });
                tr.nodes(selected);
            } else {
                tr.nodes([]);
            }

            uiLayer.batchDraw();
        });

        document.querySelectorAll('.tactics-draggable-item').forEach(function (item) {
            item.addEventListener('dragstart', function () {
                itemType = item.dataset.type || '';
            });
        });

        const stageContainer = stage.container();
        stageContainer.addEventListener('dragover', function (event) {
            event.preventDefault();
        });

        stageContainer.addEventListener('drop', function (event) {
            event.preventDefault();
            stage.setPointersPositions(event);
            const pos = getPointerPosition();
            if (!pos || !itemType || !isInsidePitch(pos)) {
                return;
            }

            if (itemType === 'ball') {
                const text = new Konva.Text({
                    x: pos.x,
                    y: pos.y,
                    text: '⚽',
                    fontSize: 20,
                    draggable: true,
                    name: 'item'
                });
                text.offsetX(text.width() / 2);
                text.offsetY(text.height() / 2);
                mainLayer.add(text);
                attachItemConstraints(text);
                mainLayer.batchDraw();
                itemType = '';
                return;
            }

            const imageSrc = '/images/assets/' + itemType + '.svg';
            Konva.Image.fromURL(imageSrc, function (image) {
                let scaleX;
                let scaleY;

                if (itemType.indexOf('shirt') === 0) {
                    const targetHeight = 38;
                    const baseScale = targetHeight / image.height();
                    scaleX = baseScale * 1.18;
                    scaleY = baseScale;
                } else {
                    const targetSize = 25;
                    const baseScale = targetSize / Math.max(image.width(), image.height());
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

                mainLayer.add(image);
                attachItemConstraints(image);
                mainLayer.batchDraw();
                itemType = '';
            });
        });

        function placeToolbarItemAtPosition(type, pos) {
            if (!type || !pos || !isInsidePitch(pos)) {
                return;
            }

            if (type === 'ball') {
                const text = new Konva.Text({
                    x: pos.x,
                    y: pos.y,
                    text: '⚽',
                    fontSize: 20,
                    draggable: true,
                    name: 'item'
                });
                text.offsetX(text.width() / 2);
                text.offsetY(text.height() / 2);
                mainLayer.add(text);
                attachItemConstraints(text);
                mainLayer.batchDraw();
                return;
            }

            const imageSrc = '/images/assets/' + type + '.svg';
            Konva.Image.fromURL(imageSrc, function (image) {
                let scaleX;
                let scaleY;

                if (type.indexOf('shirt') === 0) {
                    const targetHeight = 38;
                    const baseScale = targetHeight / image.height();
                    scaleX = baseScale * 1.18;
                    scaleY = baseScale;
                } else {
                    const targetSize = 25;
                    const baseScale = targetSize / Math.max(image.width(), image.height());
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

                mainLayer.add(image);
                attachItemConstraints(image);
                mainLayer.batchDraw();
            });
        }

        // Touch fallback for mobile/tablet: place toolbar items without relying on HTML5 DnD.
        let activeToolbarTouch = null;

        function updateToolbarGhostPosition(touch) {
            if (!activeToolbarTouch || !activeToolbarTouch.ghost || !touch) {
                return;
            }

            activeToolbarTouch.ghost.style.left = touch.clientX + 'px';
            activeToolbarTouch.ghost.style.top = touch.clientY + 'px';
        }

        function clearActiveToolbarTouch() {
            if (activeToolbarTouch && activeToolbarTouch.ghost && activeToolbarTouch.ghost.parentNode) {
                activeToolbarTouch.ghost.parentNode.removeChild(activeToolbarTouch.ghost);
            }
            activeToolbarTouch = null;
        }

        function getLogicalPositionFromViewportPoint(clientX, clientY) {
            const rect = stageContainer.getBoundingClientRect();
            if (
                clientX < rect.left ||
                clientX > rect.right ||
                clientY < rect.top ||
                clientY > rect.bottom
            ) {
                return null;
            }

            const stageX = clientX - rect.left;
            const stageY = clientY - rect.top;
            const scaleX = stage.scaleX() || 1;
            const scaleY = stage.scaleY() || 1;

            return {
                x: stageX / scaleX,
                y: stageY / scaleY
            };
        }

        document.querySelectorAll('.tactics-draggable-item').forEach(function (item) {
            item.addEventListener('touchstart', function (event) {
                if (!event.touches || event.touches.length === 0) {
                    return;
                }

                const type = item.dataset.type || '';
                if (!type) {
                    return;
                }

                event.preventDefault();

                const ghost = item.cloneNode(true);
                ghost.style.position = 'fixed';
                ghost.style.left = '0px';
                ghost.style.top = '0px';
                ghost.style.pointerEvents = 'none';
                ghost.style.opacity = '0.82';
                ghost.style.zIndex = '2147483647';
                ghost.style.transform = 'translate(-50%, -50%) scale(1.08)';
                document.body.appendChild(ghost);

                activeToolbarTouch = {
                    type: type,
                    ghost: ghost
                };

                updateToolbarGhostPosition(event.touches[0]);
            }, { passive: false });
        });

        document.addEventListener('touchmove', function (event) {
            if (!activeToolbarTouch) {
                return;
            }

            if (!event.touches || event.touches.length === 0) {
                return;
            }

            event.preventDefault();
            updateToolbarGhostPosition(event.touches[0]);
        }, { passive: false });

        document.addEventListener('touchend', function (event) {
            if (!activeToolbarTouch) {
                return;
            }

            const touch = event.changedTouches && event.changedTouches[0] ? event.changedTouches[0] : null;
            if (touch) {
                const pos = getLogicalPositionFromViewportPoint(touch.clientX, touch.clientY);
                placeToolbarItemAtPosition(activeToolbarTouch.type, pos);
            }

            clearActiveToolbarTouch();
        }, { passive: false });

        document.addEventListener('touchcancel', function () {
            if (!activeToolbarTouch) {
                return;
            }
            clearActiveToolbarTouch();
        }, { passive: false });

        const selectBtn = document.getElementById('tactics-tool-select');
        const arrowBtn = document.getElementById('tactics-tool-arrow');
        const dashedBtn = document.getElementById('tactics-tool-dashed');
        const zigzagBtn = document.getElementById('tactics-tool-zigzag');
        const clearBtn = document.getElementById('tactics-btn-clear');
        const deleteSelectedBtn = document.getElementById('tactics-btn-delete-selected');
        const toBackBtn = document.getElementById('tactics-btn-to-back');

        function deleteSelected() {
            const selectedNodes = tr.nodes();
            if (selectedNodes.length === 0) {
                return;
            }

            selectedNodes.forEach(function (node) {
                node.destroy();
            });
            tr.nodes([]);
            mainLayer.batchDraw();
            uiLayer.batchDraw();
        }

        if (selectBtn) {
            selectBtn.addEventListener('click', function () { setTool('select'); });
        }
        if (arrowBtn) {
            arrowBtn.addEventListener('click', function () { setTool('arrow'); });
        }
        if (dashedBtn) {
            dashedBtn.addEventListener('click', function () { setTool('dashed'); });
        }
        if (zigzagBtn) {
            zigzagBtn.addEventListener('click', function () { setTool('zigzag'); });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (!confirm('Weet je zeker dat je deze tekening wilt wissen?')) {
                    return;
                }
                mainLayer.destroyChildren();
                tr.nodes([]);
                mainLayer.batchDraw();
                uiLayer.batchDraw();
            });
        }

        if (deleteSelectedBtn) {
            deleteSelectedBtn.addEventListener('click', deleteSelected);
        }

        if (toBackBtn) {
            toBackBtn.addEventListener('click', function () {
                const selectedNodes = tr.nodes();
                if (selectedNodes.length === 0) {
                    return;
                }
                selectedNodes.forEach(function (node) {
                    node.moveToBottom();
                });
                mainLayer.batchDraw();
            });
        }

        drawField();
        setTool('select');

        window.addEventListener('resize', function () {
            syncStageToContainer();
        });

        if (typeof ResizeObserver === 'function' && containerEl.parentElement) {
            const resizeObserver = new ResizeObserver(function () {
                syncStageToContainer();
            });
            resizeObserver.observe(containerEl.parentElement);
        }

        return {
            loadDrawingData: function (drawingJson) {
                loadDrawingData(drawingJson || '');
            },
            exportDrawingData: function () {
                tr.nodes([]);
                return mainLayer.toJSON();
            }
        };
    }

    const board = createTacticsBoard();
    if (!board) {
        return;
    }

    function safeJsonParse(raw) {
        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function normalizeMinute(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        const minute = Number(value);
        if (!Number.isFinite(minute)) {
            return null;
        }

        const intMinute = Math.round(minute);
        if (intMinute < 0 || intMinute > 130) {
            return null;
        }

        return intMinute;
    }

    function createClientId() {
        return 'local-' + Date.now() + '-' + Math.floor(Math.random() * 100000);
    }

    function normalizeTactic(raw, index) {
        const id = Number(raw.id);
        const title = String(raw.title || '').trim();

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

    let tactics = safeJsonParse(tacticsDataEl.value).map(normalizeTactic);
    tactics.sort(function (a, b) {
        if (a.sortOrder !== b.sortOrder) {
            return a.sortOrder - b.sortOrder;
        }
        return (a.id || 0) - (b.id || 0);
    });

    if (tactics.length === 0) {
        tactics.push(createEmptyTactic(1));
    }

    let selectedKey = tacticKey(tactics[0]);

    function getSelectedIndex() {
        return tactics.findIndex(function (tactic) {
            return tacticKey(tactic) === selectedKey;
        });
    }

    function getSelectedTactic() {
        const index = getSelectedIndex();
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
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'match-tactic-item' + (tacticKey(tactic) === selectedKey ? ' is-active' : '');
            button.dataset.key = tacticKey(tactic);

            const title = document.createElement('span');
            title.className = 'match-tactic-item-title';
            title.textContent = tactic.title;
            button.appendChild(title);

            const meta = document.createElement('span');
            meta.className = 'match-tactic-item-meta';
            meta.textContent = tactic.minute === null ? 'zonder minuut' : ('minuut ' + tactic.minute);
            button.appendChild(meta);

            button.addEventListener('click', function () {
                const current = getSelectedTactic();
                if (current) {
                    readFormIntoTactic(current);
                }

                selectedKey = String(button.dataset.key || '');
                const selected = getSelectedTactic();
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
        const normalized = Array.isArray(serverTactics)
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
            const found = tactics.find(function (item) {
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
        const selected = getSelectedTactic();
        if (selected) {
            applyTacticToForm(selected);
        }
    }

    titleEl.addEventListener('input', function () {
        const selected = getSelectedTactic();
        if (!selected) {
            return;
        }
        selected.title = String(titleEl.value || '').trim() || 'Nieuwe situatie';
        renderList();
    });

    minuteEl.addEventListener('change', function () {
        const selected = getSelectedTactic();
        if (!selected) {
            return;
        }
        selected.minute = normalizeMinute(minuteEl.value);
        renderList();
    });

    newBtn.addEventListener('click', function () {
        const current = getSelectedTactic();
        if (current) {
            readFormIntoTactic(current);
        }

        const nextNumber = tactics.length + 1;
        const tactic = createEmptyTactic(nextNumber);
        tactics.push(tactic);
        selectedKey = tacticKey(tactic);

        renderList();
        applyTacticToForm(tactic);
        setStatus('Nieuwe situatie toegevoegd (nog niet opgeslagen).', false);
    });

    saveBtn.addEventListener('click', function () {
        const selected = getSelectedTactic();
        if (!selected) {
            setStatus('Selecteer eerst een situatie.', true);
            return;
        }

        readFormIntoTactic(selected);
        setStatus('Opslaan...', false);

        const payload = {
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
                    const errorMessage = result.data && result.data.error ? result.data.error : 'Opslaan mislukt.';
                    throw new Error(errorMessage);
                }

                const selectedId = result.data.tactic && result.data.tactic.id ? Number(result.data.tactic.id) : null;
                replaceWithServerTactics(result.data.tactics, selectedId);
                setStatus('Opgeslagen.', false);
            })
            .catch(function (error) {
                setStatus(error.message || 'Opslaan mislukt.', true);
            });
    });

    deleteBtn.addEventListener('click', function () {
        const selected = getSelectedTactic();
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
                    const errorMessage = result.data && result.data.error ? result.data.error : 'Verwijderen mislukt.';
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
