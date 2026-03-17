document.addEventListener('DOMContentLoaded', function () {
    var containerEl = document.getElementById('container');
    if (!containerEl) {
        return;
    }

    if (typeof Konva === 'undefined') {
        console.error('Konva is required for exercise editor.');
        return;
    }

    if (!window.KonvaSharedCore || typeof window.KonvaSharedCore.createBoard !== 'function') {
        console.error('KonvaSharedCore is required for exercise editor.');
        return;
    }

    var toolbarEl = document.getElementById('toolbar');
    var fieldTypeInput = document.getElementById('field_type');
    var drawingDataInput = document.getElementById('drawing_data');
    var drawingImageInput = document.getElementById('drawing_image');

    var formEl = containerEl.closest('form');
    if (!formEl) {
        console.error('Exercise editor form not found.');
        return;
    }

    function normalizeFieldType(type) {
        if (type === 'portrait' || type === 'landscape' || type === 'square') {
            return type;
        }
        return 'square';
    }

    function createExerciseLayout(type) {
        var normalizedType = normalizeFieldType(type);
        var width = 400;
        var height = 600;

        if (normalizedType === 'landscape') {
            width = 600;
            height = 400;
        } else if (normalizedType === 'square') {
            width = 400;
            height = 400;
        }

        return {
            key: normalizedType,
            width: width,
            height: height,
            drawField: function (fieldLayer) {
                fieldLayer.add(new Konva.Rect({
                    x: 0,
                    y: 0,
                    width: width,
                    height: height,
                    fill: '#4CAF50'
                }));

                var linePaint = {
                    stroke: 'rgba(255,255,255,0.8)',
                    strokeWidth: 2
                };

                var padding = 20;
                fieldLayer.add(new Konva.Rect({
                    x: padding,
                    y: padding,
                    width: width - (2 * padding),
                    height: height - (2 * padding),
                    stroke: linePaint.stroke,
                    strokeWidth: linePaint.strokeWidth
                }));
            },
            isPointInside: function (point) {
                return !!(
                    point &&
                    point.x >= 0 &&
                    point.x <= width &&
                    point.y >= 0 &&
                    point.y <= height
                );
            },
            clampPoint: function (point) {
                return {
                    x: Math.max(0, Math.min(width, point.x)),
                    y: Math.max(0, Math.min(height, point.y))
                };
            }
        };
    }

    var currentFieldType = normalizeFieldType(fieldTypeInput ? fieldTypeInput.value : 'square');
    if (fieldTypeInput) {
        fieldTypeInput.value = currentFieldType;
    }

    var board = window.KonvaSharedCore.createBoard({
        moduleKey: 'exercise',
        rootElement: containerEl.closest('.editor-wrapper') || containerEl,
        containerElement: containerEl,
        toolbarElement: toolbarEl,
        toolbarItemSelector: '.draggable-item[draggable="true"]',
        layout: createExerciseLayout(currentFieldType),
        defaultTool: 'select',
        toolButtons: {
            select: 'tool-select',
            arrow: 'tool-arrow',
            dashed: 'tool-dashed',
            zigzag: 'tool-zigzag',
            marker: 'tool-marker',
            zone: 'tool-zone'
        },
        resolveDrawConfig: function (context) {
            if (context.tool === 'arrow') {
                return {
                    kind: 'arrow',
                    attrs: {
                        stroke: '#ffffff',
                        fill: '#ffffff',
                        strokeWidth: 3,
                        pointerLength: 15,
                        pointerWidth: 15,
                        name: 'item'
                    }
                };
            }

            if (context.tool === 'dashed') {
                return {
                    kind: 'arrow',
                    attrs: {
                        stroke: '#ffffff',
                        fill: '#ffffff',
                        strokeWidth: 3,
                        pointerLength: 15,
                        pointerWidth: 15,
                        dash: [10, 10],
                        name: 'item'
                    }
                };
            }

            if (context.tool === 'zigzag') {
                return {
                    kind: 'zigzag-arrow',
                    step: 20,
                    amplitude: 10,
                    attrs: {
                        stroke: '#ffffff',
                        fill: '#ffffff',
                        strokeWidth: 3,
                        pointerLength: 15,
                        pointerWidth: 15,
                        name: 'item'
                    }
                };
            }

            if (context.tool === 'marker') {
                return {
                    kind: 'poly-arrow',
                    minPointDistance: 2.4,
                    minPoints: 6,
                    minLength: 6,
                    attrs: {
                        stroke: '#ffffff',
                        fill: '#ffffff',
                        strokeWidth: 4.5,
                        pointerLength: 13,
                        pointerWidth: 13,
                        lineCap: 'round',
                        lineJoin: 'round',
                        tension: 0.5,
                        name: 'item'
                    }
                };
            }

            if (context.tool === 'zone') {
                return {
                    kind: 'rect-zone',
                    toBottom: true,
                    attrs: {
                        fill: 'rgba(255, 255, 255, 0.2)',
                        stroke: 'rgba(255, 255, 255, 0.5)',
                        strokeWidth: 1,
                        dash: [5, 5],
                        name: 'item',
                        isZone: true
                    }
                };
            }

            return null;
        },
        placeItem: function (context) {
            var type = context.type;
            var pos = context.position;

            if (type === 'ball') {
                var text = new Konva.Text({
                    x: pos.x,
                    y: pos.y,
                    text: '⚽',
                    fontSize: 24,
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
                    var targetHeight = 50;
                    var baseScaleShirt = targetHeight / image.height();
                    scaleX = baseScaleShirt * 1.3;
                    scaleY = baseScaleShirt;
                } else if (type === 'goal') {
                    var targetWidth = 80;
                    var baseScaleGoal = targetWidth / image.width();
                    scaleX = baseScaleGoal;
                    scaleY = baseScaleGoal;
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
            hintClassName: 'editor-touch-hint',
            toolbarItemActiveClassName: 'is-touch-active',
            toolbarItemArmedClassName: 'is-touch-armed',
            containerArmedClassName: 'is-touch-armed',
            containerDropTargetClassName: 'is-touch-drop-target',
            containerPlaceFeedbackClassName: 'is-touch-place-feedback',
            containerDraggingClassName: 'is-touch-dragging',
            rootDraggingClassName: 'is-touch-dragging',
            bodyScrollLockClassName: 'editor-touch-scroll-lock',
            dragThresholdPx: 8,
            placementFeedbackDurationMs: 200,
            hintMessages: {
                dragging: 'Sleep naar het veld en laat los om te plaatsen.',
                armed: 'Tik op het veld om te plaatsen. Tik opnieuw op het icoon om te stoppen.'
            }
        }
    });

    function setFieldType(type) {
        currentFieldType = normalizeFieldType(type);
        if (fieldTypeInput) {
            fieldTypeInput.value = currentFieldType;
        }
        board.setLayout(createExerciseLayout(currentFieldType));
    }

    var btnPortrait = document.getElementById('btn-field-portrait');
    if (btnPortrait) {
        btnPortrait.addEventListener('click', function () {
            setFieldType('portrait');
        });
    }

    var btnLandscape = document.getElementById('btn-field-landscape');
    if (btnLandscape) {
        btnLandscape.addEventListener('click', function () {
            setFieldType('landscape');
        });
    }

    var btnSquare = document.getElementById('btn-field-square');
    if (btnSquare) {
        btnSquare.addEventListener('click', function () {
            setFieldType('square');
        });
    }

    var clearBtn = document.getElementById('btn-clear');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            board.clearArmedToolbarType();
            if (!confirm('Weet je zeker dat je alles wilt wissen?')) {
                return;
            }
            board.clearAll();
        });
    }

    var deleteSelectedBtn = document.getElementById('btn-delete-selected');
    if (deleteSelectedBtn) {
        deleteSelectedBtn.addEventListener('click', function () {
            board.clearArmedToolbarType();
            board.deleteSelected();
        });
    }

    var toBackBtn = document.getElementById('btn-to-back');
    if (toBackBtn) {
        toBackBtn.addEventListener('click', function () {
            board.clearArmedToolbarType();
            board.sendSelectedToBack();
        });
    }

    var existingData = drawingDataInput ? drawingDataInput.value : '';
    if (existingData) {
        board.loadDrawingData(existingData);
    }

    formEl.addEventListener('submit', function () {
        var drawingJson = board.exportDrawingData();
        if (drawingDataInput) {
            drawingDataInput.value = drawingJson;
        }

        if (drawingImageInput) {
            drawingImageInput.value = board.exportImageDataUrl({ pixelRatio: 2 });
        }
    });

    window.exerciseEditorApi = {
        setFieldType: function (type) {
            setFieldType(type);
        },
        loadDrawingData: function (drawingJson, fieldType) {
            if (fieldType) {
                setFieldType(fieldType);
            }
            board.loadDrawingData(drawingJson || '');
        },
        refreshLayout: function () {
            board.refreshLayout();
        },
        exportDrawingData: function () {
            return board.exportDrawingData();
        }
    };
});
