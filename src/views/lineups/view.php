<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="container">
    <svg width="0" height="0" style="position: absolute;">
      <defs>
        <pattern id="striped-jersey" patternUnits="userSpaceOnUse" width="100" height="20">
          <rect width="100" height="10" fill="black"/>
          <rect y="10" width="100" height="10" fill="#d32f2f"/>
        </pattern>
      </defs>
    </svg>
    <div class="header-actions">
        <h1><?= htmlspecialchars($lineup['name']) ?> (<?= htmlspecialchars($lineup['formation']) ?>)</h1>
        <div class="actions">
            <button id="save-lineup" class="btn btn-primary">Opslaan</button>
            <a href="/lineups" class="btn btn-secondary">Terug</a>
        </div>
    </div>

    <div class="lineup-editor">
        <div class="field-container">
            <div id="football-field" class="football-field">
                <!-- Field Markings -->
                <div class="field-line center-line"></div>
                <div class="field-circle center-circle"></div>
                <div class="field-area penalty-area-top"></div>
                <div class="field-area penalty-area-bottom"></div>
                <div class="field-area goal-area-top"></div>
                <div class="field-area goal-area-bottom"></div>
                <div class="field-goal goal-top"></div>
                <div class="field-goal goal-bottom"></div>
                <div class="corner corner-tl"></div>
                <div class="corner corner-tr"></div>
                <div class="corner corner-bl"></div>
                <div class="corner corner-br"></div>

                <!-- Placed players will be rendered here -->
                <?php foreach ($positions as $pos): ?>
                    <div class="player-token on-field" draggable="true" data-id="<?= $pos['player_id'] ?>" style="left: <?= $pos['position_x'] ?>%; top: <?= $pos['position_y'] ?>%;">
                        <div class="player-jersey">
                            <svg viewBox="0 0 100 100" width="50" height="50">
                                <path d="M15,30 L30,10 L70,10 L85,30 L75,40 L70,35 L70,90 L30,90 L30,35 L25,40 Z" fill="url(#striped-jersey)" stroke="white" stroke-width="2"/>
                                <text x="50" y="65" font-family="Arial" font-size="30" fill="white" text-anchor="middle" font-weight="bold"><?= strtoupper(substr($pos['player_name'], 0, 1)) ?></text>
                            </svg>
                        </div>
                        <div class="player-name"><?= htmlspecialchars($pos['player_name']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bench-container">
            <h3>Wissels / Selectie</h3>
            <div id="players-list" class="players-list">
                <?php 
                $placedPlayerIds = array_column($positions, 'player_id');
                ?>
                <?php foreach ($players as $player): ?>
                    <?php if (!in_array($player['id'], $placedPlayerIds)): ?>
                        <div class="player-token" draggable="true" data-id="<?= $player['id'] ?>">
                            <div class="player-jersey">
                                <svg viewBox="0 0 100 100" width="50" height="50">
                                    <path d="M15,30 L30,10 L70,10 L85,30 L75,40 L70,35 L70,90 L30,90 L30,35 L25,40 Z" fill="url(#striped-jersey)" stroke="white" stroke-width="2"/>
                                    <text x="50" y="65" font-family="Arial" font-size="30" fill="white" text-anchor="middle" font-weight="bold"><?= strtoupper(substr($player['name'], 0, 1)) ?></text>
                                </svg>
                            </div>
                            <div class="player-name"><?= htmlspecialchars($player['name']) ?></div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
.lineup-editor {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-top: 20px;
    max-width: 450px; /* Reduced from 600px to make field smaller */
    margin-left: auto;
    margin-right: auto;
}

.field-container {
    position: relative;
    width: 100%;
    background: #45a049; /* Grass green */
    border-radius: 8px;
    padding: 10px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}

.football-field {
    width: 100%;
    padding-bottom: 140%; /* Vertical Aspect Ratio */
    position: relative;
    border: 2px solid rgba(255,255,255,0.8);
    overflow: hidden;
    background-color: #4CAF50;
    /* Subtle grass pattern */
    background-image: repeating-linear-gradient(0deg, transparent, transparent 20px, rgba(0,0,0,0.03) 20px, rgba(0,0,0,0.03) 40px);
}

/* Field Markings */
.field-line, .field-circle, .field-area, .field-goal, .corner {
    position: absolute;
    border: 2px solid rgba(255,255,255,0.7);
}

.center-line {
    top: 50%;
    left: 0;
    right: 0;
    height: 0;
    border-top: 2px solid rgba(255,255,255,0.7);
}

.center-circle {
    top: 50%;
    left: 50%;
    width: 20%;
    padding-bottom: 20%; /* Make it a circle based on width */
    height: 0;
    border-radius: 50%;
    transform: translate(-50%, -50%);
}

.penalty-area-top {
    top: 0;
    left: 20%;
    right: 20%;
    height: 16%;
    border-top: none;
}

.penalty-area-bottom {
    bottom: 0;
    left: 20%;
    right: 20%;
    height: 16%;
    border-bottom: none;
}

.goal-area-top {
    top: 0;
    left: 35%;
    right: 35%;
    height: 6%;
    border-top: none;
}

.goal-area-bottom {
    bottom: 0;
    left: 35%;
    right: 35%;
    height: 6%;
    border-bottom: none;
}

.goal-top {
    top: -2%;
    left: 42%;
    right: 42%;
    height: 2%;
    border: 2px solid rgba(255,255,255,0.9);
    border-bottom: none;
}

.goal-bottom {
    bottom: -2%;
    left: 42%;
    right: 42%;
    height: 2%;
    border: 2px solid rgba(255,255,255,0.9);
    border-top: none;
}

.corner {
    width: 3%;
    padding-bottom: 3%;
    height: 0;
    border-radius: 50%;
}

.corner-tl { top: -1.5%; left: -1.5%; }
.corner-tr { top: -1.5%; right: -1.5%; }
.corner-bl { bottom: -1.5%; left: -1.5%; }
.corner-br { bottom: -1.5%; right: -1.5%; }

/* Player Token */
.player-token {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: grab;
    width: 70px; /* Increased from 60px */
    z-index: 10;
    transition: transform 0.1s;
}

.player-token:active {
    cursor: grabbing;
    transform: scale(1.1);
}

.player-token.on-field {
    position: absolute;
    transform: translate(-50%, -50%);
}

.player-jersey {
    filter: drop-shadow(0 2px 3px rgba(0,0,0,0.3));
}

.player-name {
    margin-top: 2px;
    font-size: 12px;
    font-weight: bold;
    color: #333;
    background: rgba(255,255,255,0.8);
    padding: 2px 6px;
    border-radius: 10px;
    white-space: nowrap;
    text-align: center;
    max-width: 100px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.on-field .player-name {
    color: white;
    background: none;
    text-shadow: 0 1px 2px rgba(0,0,0,0.8);
}

/* Bench */
.bench-container {
    background: #f1f1f1;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #ddd;
}

.players-list {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    min-height: 80px;
    align-items: flex-start;
}

.players-list .player-token {
    position: relative; /* Reset absolute positioning */
    left: auto !important;
    top: auto !important;
    transform: none !important;
}

.players-list .player-name {
    color: #333;
    background: none;
    text-shadow: none;
}

</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const playersList = document.getElementById('players-list');
    const field = document.getElementById('football-field');
    const saveBtn = document.getElementById('save-lineup');
    let draggedItem = null;

    // Drag Events
    document.addEventListener('dragstart', (e) => {
        // Find the closest player-token in case we dragged the image or text
        const target = e.target.closest('.player-token');
        if (target) {
            draggedItem = target;
            e.dataTransfer.setData('text/plain', target.dataset.id);
            e.dataTransfer.effectAllowed = 'move';
            // Use a transparent image for drag ghost if possible, or just opacity
            setTimeout(() => target.style.opacity = '0.5', 0);
        }
    });

    document.addEventListener('dragend', (e) => {
        const target = e.target.closest('.player-token');
        if (target) {
            target.style.opacity = '1';
            draggedItem = null;
        }
    });

    // Field Drop Zone
    field.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    });

    field.addEventListener('drop', (e) => {
        e.preventDefault();
        if (!draggedItem) return;

        const rect = field.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        // Calculate percentage positions
        let xPercent = (x / rect.width) * 100;
        let yPercent = (y / rect.height) * 100;

        // Snap to grid (nearest 5%) to help alignment
        xPercent = Math.round(xPercent / 5) * 5;
        yPercent = Math.round(yPercent / 5) * 5;

        // If item comes from sidebar, move it to field
        if (draggedItem.parentElement === playersList) {
            draggedItem.classList.add('on-field');
            field.appendChild(draggedItem);
        }

        // Update position
        draggedItem.style.left = xPercent + '%';
        draggedItem.style.top = yPercent + '%';
    });

    // Bench Drop Zone (to remove from field)
    playersList.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    });

    playersList.addEventListener('drop', (e) => {
        e.preventDefault();
        if (!draggedItem) return;

        if (draggedItem.parentElement === field) {
            draggedItem.classList.remove('on-field');
            draggedItem.style.left = '';
            draggedItem.style.top = '';
            playersList.appendChild(draggedItem);
        }
    });

    // Save Functionality
    saveBtn.addEventListener('click', () => {
        const positions = [];
        const fieldPlayers = field.querySelectorAll('.player-token');
        
        fieldPlayers.forEach(player => {
            positions.push({
                player_id: parseInt(player.dataset.id),
                x: parseFloat(player.style.left),
                y: parseFloat(player.style.top)
            });
        });

        fetch('/lineups/save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                lineup_id: <?= $lineup['id'] ?>,
                positions: positions
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Opstelling opgeslagen!');
            } else {
                alert('Fout bij opslaan.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Er is een fout opgetreden.');
        });
    });
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
