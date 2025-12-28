document.addEventListener('DOMContentLoaded', function() {
    const containerEl = document.getElementById('container');
    if (!containerEl) return;

    const fieldTypeInput = document.getElementById('field_type');
    const currentFieldType = fieldTypeInput ? fieldTypeInput.value : 'square';

    // Logical resolution (matches editor.js)
    let V_WIDTH = 400;
    let V_HEIGHT = 600;

    if (currentFieldType === 'landscape') {
        V_WIDTH = 600;
        V_HEIGHT = 400;
    } else if (currentFieldType === 'square') {
        V_WIDTH = 400;
        V_HEIGHT = 400;
    }
    
    const containerWidth = containerEl.offsetWidth;
    const scale = containerWidth / V_WIDTH;
    
    // Adjust container height
    const containerHeight = V_HEIGHT * scale;
    containerEl.style.height = containerHeight + 'px';

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
            // Outer lines
            fieldLayer.add(new Konva.Rect({
                x: padding,
                y: padding,
                width: V_WIDTH - 2 * padding,
                height: V_HEIGHT - 2 * padding,
                ...linePaint
            }));
        }
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
