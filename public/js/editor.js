document.addEventListener('DOMContentLoaded', function() {
    const containerEl = document.getElementById('container');
    
    // Logical dimensions (Design resolution)
    let V_WIDTH = 400;
    let V_HEIGHT = 600;
    
    const fieldTypeInput = document.getElementById('field_type');
    let currentFieldType = fieldTypeInput ? fieldTypeInput.value : 'square';

    if (currentFieldType === 'landscape') {
        V_WIDTH = 600;
        V_HEIGHT = 400;
    } else if (currentFieldType === 'square') {
        V_WIDTH = 400;
        V_HEIGHT = 400;
    }

    // Calculate scale to fit container width
    const containerWidth = containerEl.offsetWidth;
    let scale = containerWidth / V_WIDTH;
    
    // Adjust container height to match aspect ratio
    let containerHeight = V_HEIGHT * scale;
    containerEl.style.height = containerHeight + 'px';

    // Initialize Konva Stage
    const stage = new Konva.Stage({
        container: 'container',
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

    // Helper to get logical pointer position
    function getPointerPosition() {
        const pos = stage.getPointerPosition();
        if (!pos) return null;
        const transform = stage.getAbsoluteTransform().copy().invert();
        return transform.point(pos);
    }

    // Draw Football Field
    function drawField() {
        fieldLayer.destroyChildren();

        // Grass
        const grass = new Konva.Rect({
            x: 0,
            y: 0,
            width: V_WIDTH,
            height: V_HEIGHT,
            fill: '#4CAF50'
        });
        fieldLayer.add(grass);

        // Lines style
        const linePaint = {
            stroke: 'rgba(255,255,255,0.8)',
            strokeWidth: 2
        };

        const padding = 20;

        if (currentFieldType === 'portrait') {
            // Outer lines
            fieldLayer.add(new Konva.Rect({
                x: padding,
                y: padding,
                width: V_WIDTH - 2 * padding,
                height: V_HEIGHT - 2 * padding,
                ...linePaint
            }));

        } else if (currentFieldType === 'landscape') {
            // Outer lines
            fieldLayer.add(new Konva.Rect({
                x: padding,
                y: padding,
                width: V_WIDTH - 2 * padding,
                height: V_HEIGHT - 2 * padding,
                ...linePaint
            }));

        } else if (currentFieldType === 'square') {
            // Half field / Box
            // Outer lines
            fieldLayer.add(new Konva.Rect({
                x: padding,
                y: padding,
                width: V_WIDTH - 2 * padding,
                height: V_HEIGHT - 2 * padding,
                ...linePaint
            }));
        }
        
        fieldLayer.batchDraw();
    }

    function updateFieldLayout(type) {
        currentFieldType = type;
        if (fieldTypeInput) fieldTypeInput.value = type;
        
        if (type === 'portrait') {
            V_WIDTH = 400;
            V_HEIGHT = 600;
        } else if (type === 'landscape') {
            V_WIDTH = 600;
            V_HEIGHT = 400;
        } else if (type === 'square') {
            V_WIDTH = 400;
            V_HEIGHT = 400;
        }

        // Recalculate scale
        scale = containerWidth / V_WIDTH;
        containerHeight = V_HEIGHT * scale;
        
        // Update container and stage
        containerEl.style.height = containerHeight + 'px';
        stage.width(containerWidth);
        stage.height(containerHeight);
        stage.scale({ x: scale, y: scale });

        drawField();
    }

    // Event Listeners for Field Layout
    const btnPortrait = document.getElementById('btn-field-portrait');
    if (btnPortrait) btnPortrait.addEventListener('click', () => updateFieldLayout('portrait'));

    const btnLandscape = document.getElementById('btn-field-landscape');
    if (btnLandscape) btnLandscape.addEventListener('click', () => updateFieldLayout('landscape'));

    const btnSquare = document.getElementById('btn-field-square');
    if (btnSquare) btnSquare.addEventListener('click', () => updateFieldLayout('square'));

    drawField();

    // Transformer for selection
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

    // Selection Rectangle
    const selectionRectangle = new Konva.Rect({
        fill: 'rgba(0,0,255,0.2)',
        visible: false,
        listening: false, // Don't catch events
        name: 'selection-rectangle'
    });
    uiLayer.add(selectionRectangle);

    // Selection Logic
    stage.on('click tap', function (e) {
        // do nothing if clicked NOT on our rectangles
        if (!e.target.hasName('item')) {
            return;
        }

        // If we are in selection mode (box selection just finished), don't handle click
        // But wait, click fires after mouseup.
        // If we just did a box selection, we don't want to select the item under cursor if it wasn't in the box?
        // Actually, standard behavior is:
        // - Click on item -> Select item (and deselect others unless shift)
        // - Drag on empty -> Box select
        
        // currently we only support single selection
        const metaPressed = e.evt.shiftKey || e.evt.ctrlKey || e.evt.metaKey;
        const isSelected = tr.nodes().indexOf(e.target) >= 0;

        if (!metaPressed && !isSelected) {
            // if no key pressed and the node is not selected
            // select just one
            tr.nodes([e.target]);
        } else if (metaPressed && isSelected) {
            // if we pressed keys and node was selected
            // we need to remove it from selection:
            const nodes = tr.nodes().slice(); // use slice to have new copy of array
            // remove node from array
            nodes.splice(nodes.indexOf(e.target), 1);
            tr.nodes(nodes);
        } else if (metaPressed && !isSelected) {
            // add the node into selection
            const nodes = tr.nodes().concat([e.target]);
            tr.nodes(nodes);
        }
        uiLayer.batchDraw();
    });

    // Drag and Drop from Toolbar
    let itemType = '';
    
    document.querySelectorAll('.draggable-item').forEach(item => {
        item.addEventListener('dragstart', function(e) {
            itemType = item.dataset.type;
        });
    });

    const container = stage.container();
    container.addEventListener('dragover', function(e) {
        e.preventDefault(); // Necessary to allow dropping
    });

    container.addEventListener('drop', function(e) {
        e.preventDefault();
        stage.setPointersPositions(e);
        const pos = getPointerPosition();

        if (itemType) {
            if (itemType === 'ball') {
                const text = new Konva.Text({
                    x: pos.x,
                    y: pos.y,
                    text: '⚽',
                    fontSize: 24,
                    draggable: true,
                    name: 'item'
                });
                text.offsetX(text.width() / 2);
                text.offsetY(text.height() / 2);
                mainLayer.add(text);
                mainLayer.batchDraw();
                itemType = '';
            } else {
                Konva.Image.fromURL(`/images/assets/${itemType}.svg`, function(image) {
                    let scaleX, scaleY;
                    
                    if (itemType.startsWith('shirt')) {
                        // Player: Larger and wider
                        const targetHeight = 50;
                        const baseScale = targetHeight / image.height();
                        scaleX = baseScale * 1.3; // 30% wider
                        scaleY = baseScale;
                    } else if (itemType === 'goal') {
                        // Goal: Larger
                        const targetWidth = 80;
                        const baseScale = targetWidth / image.width();
                        scaleX = baseScale;
                        scaleY = baseScale;
                    } else {
                        // Cones, pawns (default)
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
                        imageSrc: `/images/assets/${itemType}.svg`
                    });
                    mainLayer.add(image);
                    itemType = ''; // Reset
                });
            }
        }
    });

    // Load existing data
    const existingData = document.getElementById('drawing_data').value;
    if (existingData) {
        const data = JSON.parse(existingData);
        // We only want to load the mainLayer children (items), not the field
        // But Konva.Node.create loads the whole stage or layer structure.
        // Simpler approach: If we saved the whole stage, we might have issues with the field layer duplication.
        // Let's assume we only save the 'children' of the mainLayer (excluding transformer).
        
        // For now, let's just implement saving first to see how we structure the data.
    }

    // Save Data on Submit
    document.querySelector('form').addEventListener('submit', function(e) {
        // Remove transformer before saving
        tr.nodes([]);
        
        // Serialize mainLayer children (excluding transformer)
        // Actually, simpler: just save the whole mainLayer as JSON
        const json = mainLayer.toJSON();
        document.getElementById('drawing_data').value = json;

        // Generate Snapshot
        const dataURL = stage.toDataURL({ pixelRatio: 2 });
        document.getElementById('drawing_image').value = dataURL;
    });

    // Tool switching (Basic implementation)
    let currentTool = 'select';
    
    document.getElementById('tool-select').addEventListener('click', () => setTool('select'));
    document.getElementById('tool-arrow').addEventListener('click', () => setTool('arrow'));
    document.getElementById('tool-dashed').addEventListener('click', () => setTool('dashed'));
    document.getElementById('tool-zigzag').addEventListener('click', () => setTool('zigzag'));
    document.getElementById('tool-zone').addEventListener('click', () => setTool('zone'));
    document.getElementById('btn-clear').addEventListener('click', () => {
        if(confirm('Weet je zeker dat je alles wilt wissen?')) {
            mainLayer.destroyChildren();
            tr.nodes([]);
            uiLayer.batchDraw();
            mainLayer.batchDraw();
        }
    });

    // Delete selected items
    function deleteSelected() {
        const selectedNodes = tr.nodes();
        if (selectedNodes.length > 0) {
            selectedNodes.forEach(node => node.destroy());
            tr.nodes([]);
            mainLayer.batchDraw();
            uiLayer.batchDraw();
        }
    }

    document.getElementById('btn-delete-selected').addEventListener('click', deleteSelected);

    // Send to back
    document.getElementById('btn-to-back').addEventListener('click', function() {
        const selectedNodes = tr.nodes();
        if (selectedNodes.length > 0) {
            selectedNodes.forEach(node => node.moveToBottom());
            mainLayer.batchDraw();
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if ((e.key === 'Delete' || e.key === 'Backspace') && tr.nodes().length > 0) {
            // Prevent backspace from navigating back if not in an input
            if (document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                e.preventDefault();
                deleteSelected();
            }
        }
    });

    function setTool(tool) {
        currentTool = tool;
        // Update UI buttons
        document.querySelectorAll('.editor-toolbar button').forEach(b => {
            if (b.classList.contains('tool-btn')) {
                b.classList.remove('active');
            }
        });

        const btnId = tool === 'select' ? 'tool-select' : 
                      tool === 'arrow' ? 'tool-arrow' :
                      tool === 'dashed' ? 'tool-dashed' : 
                      tool === 'zigzag' ? 'tool-zigzag' : 'tool-zone';
        
        const btn = document.getElementById(btnId);
        if (btn) {
            if (btn.classList.contains('tool-btn')) {
                btn.classList.add('active');
            }
        }
        
        // Disable dragging for items if drawing
        const isDraggable = tool === 'select';
        mainLayer.find('.item').forEach(node => node.draggable(isDraggable));
    }

    // Drawing Logic (Lines)
    let isDrawing = false;
    let isSelecting = false;
    let lastLine;
    let startPos;
    let x1, y1, x2, y2;

    stage.on('mousedown touchstart', function (e) {
        // If clicking on transformer, do nothing
        if (e.target.getParent() instanceof Konva.Transformer) {
            return;
        }
        
        // If clicking on an item, do nothing (let Konva handle drag or click selection)
        if (e.target.hasName('item')) {
            return;
        }

        if (currentTool === 'select') {
            // Only start box selection if clicking on empty space (stage or field layer)
            // e.target is the shape under cursor.
            // If we are here, it's NOT an item or transformer.
            
            // Don't prevent default here, it might block click events
            // e.evt.preventDefault(); 
            
            const pos = getPointerPosition();
            x1 = pos.x;
            y1 = pos.y;
            x2 = x1;
            y2 = y1;

            selectionRectangle.width(0);
            selectionRectangle.height(0);
            selectionRectangle.visible(true);
            // Ensure selection rectangle is on top of everything except transformer
            selectionRectangle.moveToTop();
            tr.moveToTop();
            
            isSelecting = true;
            uiLayer.batchDraw();
            return;
        }
        
        isDrawing = true;
        const pos = getPointerPosition();
        startPos = pos;
        
        if (currentTool === 'zone') {
            lastLine = new Konva.Rect({
                x: pos.x,
                y: pos.y,
                width: 0,
                height: 0,
                fill: 'rgba(255, 255, 255, 0.2)',
                stroke: 'rgba(255, 255, 255, 0.5)',
                strokeWidth: 1,
                dash: [5, 5],
                name: 'item',
                draggable: false
            });
            mainLayer.add(lastLine);
            // Zones should be at the bottom by default
            lastLine.moveToBottom();
        } else {
            let points = [pos.x, pos.y, pos.x, pos.y];
            
            // All tools are arrows now
            let config = {
                points: points,
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
        }
        mainLayer.batchDraw();
    });

    stage.on('mousemove touchmove', function (e) {
        if (isSelecting) {
            const pos = getPointerPosition();
            x2 = pos.x;
            y2 = pos.y;

            selectionRectangle.setAttrs({
                visible: true,
                x: Math.min(x1, x2),
                y: Math.min(y1, y2),
                width: Math.abs(x2 - x1),
                height: Math.abs(y2 - y1),
            });
            uiLayer.batchDraw();
            return;
        }

        if (!isDrawing) return;
        const pos = getPointerPosition();
        
        if (currentTool === 'zone') {
            const width = pos.x - startPos.x;
            const height = pos.y - startPos.y;
            lastLine.width(width);
            lastLine.height(height);
        } else if (currentTool === 'zigzag') {
            const points = calculateZigzagPoints(startPos.x, startPos.y, pos.x, pos.y);
            lastLine.points(points);
        } else {
            const points = lastLine.points();
            points[2] = pos.x;
            points[3] = pos.y;
            lastLine.points(points);
        }
        mainLayer.batchDraw();
    });

    function calculateZigzagPoints(x1, y1, x2, y2) {
        const dx = x2 - x1;
        const dy = y2 - y1;
        const dist = Math.sqrt(dx*dx + dy*dy);
        const angle = Math.atan2(dy, dx);
        
        const step = 20;
        const segments = Math.floor(dist / step);
        
        if (segments < 2) return [x1, y1, x2, y2];
        
        const points = [];
        points.push(x1, y1);
        
        for (let i = 1; i < segments; i++) {
            const t = i / segments;
            const cx = x1 + dx * t;
            const cy = y1 + dy * t;
            
            // Offset perpendicular
            const offset = (i % 2 === 0 ? 1 : -1) * 10;
            const ox = cx + Math.cos(angle + Math.PI/2) * offset;
            const oy = cy + Math.sin(angle + Math.PI/2) * offset;
            
            points.push(ox, oy);
        }
        
        points.push(x2, y2);
        return points;
    }

    stage.on('mouseup touchend', function () {
        isDrawing = false;
        
        if (isSelecting) {
            isSelecting = false;
            selectionRectangle.visible(false);
            
            // Get the selection box coordinates directly from attributes
            // This avoids issues if getClientRect() returns 0 for invisible nodes
            const box = {
                x: selectionRectangle.x(),
                y: selectionRectangle.y(),
                width: selectionRectangle.width(),
                height: selectionRectangle.height()
            };
            
            if (box.width > 5 || box.height > 5) {
                const shapes = mainLayer.find('.item');
                const selected = shapes.filter((shape) => {
                    // Logic: Item must be FULLY contained within the selection box
                    
                    if (shape instanceof Konva.Arrow) {
                        const points = shape.points();
                        // Check if ALL points are inside the box
                        for (let i = 0; i < points.length; i += 2) {
                            const px = points[i];
                            const py = points[i+1];
                            // If any point is outside, the shape is not fully contained
                            if (!(px >= box.x && px <= box.x + box.width &&
                                  py >= box.y && py <= box.y + box.height)) {
                                return false;
                            }
                        }
                        return true;
                    } else {
                        // For other shapes (Images, Rects), check if client rect is fully inside
                        // Use relativeTo: mainLayer to get coordinates in the logical space (unscaled)
                        const shapeBox = shape.getClientRect({ relativeTo: mainLayer });
                        return (
                            shapeBox.x >= box.x &&
                            shapeBox.y >= box.y &&
                            shapeBox.x + shapeBox.width <= box.x + box.width &&
                            shapeBox.y + shapeBox.height <= box.y + box.height
                        );
                    }
                });
                
                tr.nodes(selected);
            } else {
                tr.nodes([]);
            }
            uiLayer.batchDraw();
        }
    });

    // Load Data Logic (Refined)
    if (existingData) {
        try {
            // We expect existingData to be the JSON of the mainLayer
            // We can't just replace the layer because we need the transformer.
            // So we create a temporary layer to parse, then move children.
            const tempLayer = Konva.Node.create(existingData);
            const children = tempLayer.getChildren().slice(); // Copy array to avoid issues while moving
            
            children.forEach(child => {
                child.moveTo(mainLayer);
                
                if (child.getClassName() === 'Image' && child.getAttr('imageSrc')) {
                    const imgObj = new Image();
                    imgObj.onload = function() {
                        child.image(imgObj);
                        mainLayer.batchDraw();
                    };
                    imgObj.src = child.getAttr('imageSrc');
                }

                if (child.name() === 'item') {
                    // Re-enable dragging if needed
                    child.draggable(true);
                }
            });
            mainLayer.batchDraw();
        } catch (e) {
            console.error("Error loading drawing data:", e);
        }
    }

});
