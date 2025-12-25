document.addEventListener('DOMContentLoaded', function() {
    const containerEl = document.getElementById('container');
    const width = containerEl.offsetWidth;
    const height = containerEl.offsetHeight;

    // Initialize Konva Stage
    const stage = new Konva.Stage({
        container: 'container',
        width: width,
        height: height
    });

    const fieldLayer = new Konva.Layer();
    const mainLayer = new Konva.Layer();
    stage.add(fieldLayer);
    stage.add(mainLayer);

    // Draw Football Field
    function drawField() {
        // Grass
        const grass = new Konva.Rect({
            x: 0,
            y: 0,
            width: width,
            height: height,
            fill: '#4CAF50'
        });
        fieldLayer.add(grass);

        // Lines style
        const linePaint = {
            stroke: 'rgba(255,255,255,0.8)',
            strokeWidth: 2
        };

        // Outer lines
        const padding = 20;
        fieldLayer.add(new Konva.Rect({
            x: padding,
            y: padding,
            width: width - 2 * padding,
            height: height - 2 * padding,
            ...linePaint
        }));

        // Center line (Horizontal for portrait)
        fieldLayer.add(new Konva.Line({
            points: [padding, height / 2, width - padding, height / 2],
            ...linePaint
        }));

        // Center circle
        fieldLayer.add(new Konva.Circle({
            x: width / 2,
            y: height / 2,
            radius: 40,
            ...linePaint
        }));

        // Penalty areas (simplified)
        const boxWidth = 160; // Wider for portrait proportion
        const boxDepth = 60;
        
        // Top Goal Area
        fieldLayer.add(new Konva.Rect({
            x: (width - boxWidth) / 2,
            y: padding,
            width: boxWidth,
            height: boxDepth,
            ...linePaint
        }));

        // Bottom Goal Area
        fieldLayer.add(new Konva.Rect({
            x: (width - boxWidth) / 2,
            y: height - padding - boxDepth,
            width: boxWidth,
            height: boxDepth,
            ...linePaint
        }));
    }

    drawField();

    // Transformer for selection
    const tr = new Konva.Transformer();
    mainLayer.add(tr);

    // Selection Logic
    stage.on('click tap', function (e) {
        // if click on empty area - remove all selections
        if (e.target === stage || e.target.getParent() === fieldLayer) {
            tr.nodes([]);
            return;
        }

        // do nothing if clicked NOT on our rectangles
        if (!e.target.hasName('item')) {
            return;
        }

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
        const pos = stage.getPointerPosition();

        if (itemType) {
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
                } else if (itemType === 'ball') {
                    // Ball: Smaller relative to others
                    const targetSize = 20;
                    const baseScale = targetSize / Math.max(image.width(), image.height());
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
                    name: 'item'
                });
                mainLayer.add(image);
                itemType = ''; // Reset
            });
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
    document.getElementById('btn-clear').addEventListener('click', () => {
        if(confirm('Weet je zeker dat je alles wilt wissen?')) {
            mainLayer.destroyChildren();
            mainLayer.add(tr);
        }
    });

    function setTool(tool) {
        currentTool = tool;
        // Update UI buttons
        document.querySelectorAll('.editor-toolbar button').forEach(b => {
            if (b.classList.contains('tool-btn')) {
                b.classList.remove('active');
            } else if (b.id === 'tool-select') {
                b.classList.add('btn-outline');
            }
        });

        const btnId = tool === 'select' ? 'tool-select' : 
                      tool === 'arrow' ? 'tool-arrow' :
                      tool === 'dashed' ? 'tool-dashed' : 'tool-zigzag';
        
        const btn = document.getElementById(btnId);
        if (btn) {
            if (btn.classList.contains('tool-btn')) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('btn-outline');
            }
        }
        
        // Disable dragging for items if drawing
        const isDraggable = tool === 'select';
        mainLayer.find('.item').forEach(node => node.draggable(isDraggable));
    }

    // Drawing Logic (Lines)
    let isDrawing = false;
    let lastLine;
    let startPos;

    stage.on('mousedown touchstart', function (e) {
        if (currentTool === 'select') return;
        
        isDrawing = true;
        const pos = stage.getPointerPosition();
        startPos = pos;
        
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
        }
        
        lastLine = new Konva.Arrow(config);
        mainLayer.add(lastLine);
        mainLayer.batchDraw();
    });

    stage.on('mousemove touchmove', function (e) {
        if (!isDrawing) return;
        const pos = stage.getPointerPosition();
        
        if (currentTool === 'zigzag') {
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
    });

    // Load Data Logic (Refined)
    if (existingData) {
        // We expect existingData to be the JSON of the mainLayer
        const layerData = JSON.parse(existingData);
        // We can't just replace the layer because we need the transformer.
        // So we create a temporary layer to parse, then move children.
        const tempLayer = Konva.Node.create(existingData);
        const children = tempLayer.getChildren();
        children.forEach(child => {
            child.moveTo(mainLayer);
            if (child.name() === 'item') {
                // Re-enable dragging if needed
                child.draggable(true);
            }
        });
        // Ensure transformer is on top
        tr.moveToTop();
    }

});
