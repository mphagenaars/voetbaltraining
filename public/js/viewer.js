document.addEventListener('DOMContentLoaded', function() {
    const containerEl = document.getElementById('container');
    if (!containerEl) return;

    // Logical resolution (matches editor.js)
    const V_WIDTH = 400;
    const V_HEIGHT = 600;
    
    const containerWidth = containerEl.offsetWidth;
    const scale = containerWidth / V_WIDTH;

    // Initialize Konva Stage
    const stage = new Konva.Stage({
        container: 'container',
        width: V_WIDTH * scale,
        height: V_HEIGHT * scale,
        scale: { x: scale, y: scale }
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

        // Outer lines
        const padding = 20;
        fieldLayer.add(new Konva.Rect({
            x: padding,
            y: padding,
            width: V_WIDTH - 2 * padding,
            height: V_HEIGHT - 2 * padding,
            ...linePaint
        }));

        // Center line (Horizontal for portrait)
        fieldLayer.add(new Konva.Line({
            points: [padding, V_HEIGHT / 2, V_WIDTH - padding, V_HEIGHT / 2],
            ...linePaint
        }));

        // Center circle
        fieldLayer.add(new Konva.Circle({
            x: V_WIDTH / 2,
            y: V_HEIGHT / 2,
            radius: 40,
            ...linePaint
        }));

        // Penalty areas (simplified)
        const boxWidth = 160; // Wider for portrait proportion
        const boxDepth = 60;
        
        // Top Goal Area
        fieldLayer.add(new Konva.Rect({
            x: (V_WIDTH - boxWidth) / 2,
            y: padding,
            width: boxWidth,
            height: boxDepth,
            ...linePaint
        }));

        // Bottom Goal Area
        fieldLayer.add(new Konva.Rect({
            x: (V_WIDTH - boxWidth) / 2,
            y: V_HEIGHT - padding - boxDepth,
            width: boxWidth,
            height: boxDepth,
            ...linePaint
        }));
    }

    drawField();

    // Load existing data
    const existingData = document.getElementById('drawing_data').value;
    if (existingData) {
        try {
            // We expect existingData to be the JSON of the mainLayer
            const tempLayer = Konva.Node.create(existingData);
            const children = tempLayer.getChildren().slice(); // Copy array
            
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

                // Ensure items are not draggable in view mode
                child.draggable(false);
            });
            mainLayer.batchDraw();
        } catch (e) {
            console.error("Error loading drawing data:", e);
        }
    }
});
