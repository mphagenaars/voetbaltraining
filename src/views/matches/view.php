<div class="container">
    <svg width="0" height="0" style="position: absolute;">
      <defs>
        <pattern id="striped-jersey" patternUnits="userSpaceOnUse" width="100" height="20">
          <rect width="100" height="10" fill="black"/>
          <rect y="10" width="100" height="10" fill="#d32f2f"/>
        </pattern>
        <pattern id="blue-jersey" patternUnits="userSpaceOnUse" width="100" height="20">
          <rect width="100" height="10" fill="#1565c0"/>
          <rect y="10" width="100" height="10" fill="#1976d2"/>
        </pattern>
      </defs>
    </svg>

    <div class="header-actions">
        <h1><?= e($match['opponent']) ?> (<?= $match['is_home'] ? 'Thuis' : 'Uit' ?>)</h1>
        <a href="/matches" class="btn btn-outline">Terug</a>
    </div>
    
    <div class="card" style="margin-bottom: 1rem;">
        <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
            <p><strong>Datum:</strong> <?= e(date('d-m-Y H:i', strtotime($match['date']))) ?></p>
            <p><strong>Formatie:</strong> <?= e($match['formation']) ?></p>
        </div>
    </div>

    <div class="match-grid">
        <!-- Card 1: Opstelling -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3>Opstelling</h3>
                <button id="save-lineup" class="btn-icon" title="Opslaan">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                </button>
                <input type="hidden" id="csrf_token" value="<?= Csrf::getToken() ?>">
            </div>

            <div class="lineup-editor">
                <div class="field-container">
                    <div id="football-field" class="football-field" data-formation="<?= e($match['formation']) ?>">
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

                        <!-- Placed players -->
                        <?php foreach ($matchPlayers as $pos): ?>
                            <div class="player-token on-field" draggable="true" data-id="<?= $pos['player_id'] ?>" style="left: <?= $pos['position_x'] ?>%; top: <?= $pos['position_y'] ?>%;">
                                <div class="player-jersey">
                                    <svg viewBox="0 0 100 100" width="50" height="50">
                                        <path d="M15,30 L30,10 L70,10 L85,30 L75,40 L70,35 L70,90 L30,90 L30,35 L25,40 Z" fill="url(#striped-jersey)" stroke="white" stroke-width="2"/>
                                        <text x="50" y="65" font-family="Arial" font-size="30" fill="white" text-anchor="middle" font-weight="bold"><?= strtoupper(substr($pos['player_name'], 0, 1)) ?></text>
                                    </svg>
                                </div>
                                <div class="player-name"><?= e($pos['player_name']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bench-container">
                    <h4>Wissels / Selectie</h4>
                    <div id="players-list" class="players-list">
                        <?php 
                        $placedPlayerIds = array_column($matchPlayers, 'player_id');
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
                                    <div class="player-name"><?= e($player['name']) ?></div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Wedstrijdverloop -->
        <div class="card">
            <h3>Wedstrijdverloop</h3>
            
            <form action="/matches/add-event" method="POST" style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid #eee;">
                <?= Csrf::renderInput() ?>
                <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; margin-top: 1rem;">
                    <h4 style="margin: 0;">Gebeurtenis toevoegen</h4>
                    <button type="submit" class="btn-icon" title="Toevoegen">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                    </button>
                </div>
                
                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <div style="width: 80px;">
                        <label style="font-size: 0.8rem;">Minuut</label>
                        <input type="number" name="minute" required min="1" max="120" style="width: 100%;">
                    </div>
                    <div style="flex-grow: 1;">
                        <label style="font-size: 0.8rem;">Type</label>
                        <select name="type" required style="width: 100%;">
                            <option value="goal">Doelpunt</option>
                            <option value="card_yellow">Gele kaart</option>
                            <option value="card_red">Rode kaart</option>
                            <option value="sub">Wissel</option>
                            <option value="other">Anders</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label style="font-size: 0.8rem;">Speler (optioneel)</label>
                    <select name="player_id" style="width: 100%;">
                        <option value="">-- Selecteer speler --</option>
                        <?php foreach ($players as $player): ?>
                            <option value="<?= $player['id'] ?>"><?= e($player['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label style="font-size: 0.8rem;">Omschrijving</label>
                    <input type="text" name="description" placeholder="Bijv. Assist van..." style="width: 100%;">
                </div>
            </form>

            <ul class="timeline" style="list-style: none; padding: 0;">
                <?php foreach ($events as $event): ?>
                    <li style="border-bottom: 1px solid #eee; padding: 0.5rem 0;">
                        <strong><?= $event['minute'] ?>'</strong> 
                        
                        <?php 
                        $typeLabel = match($event['type']) {
                            'goal' => '⚽ Doelpunt',
                            'card_yellow' => '🟨 Gele kaart',
                            'card_red' => '🟥 Rode kaart',
                            'sub' => '🔄 Wissel',
                            default => 'Gebeurtenis'
                        };
                        echo $typeLabel;
                        ?>
                        
                        <?php if ($event['player_name']): ?>
                            door <strong><?= e($event['player_name']) ?></strong>
                        <?php endif; ?>
                        
                        <?php if ($event['description']): ?>
                            (<?= e($event['description']) ?>)
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Card 3: Eindstand & Evaluatie -->
        <div class="card">
            <form action="/matches/update-details" method="POST">
                <?= Csrf::renderInput() ?>
                <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3>Details</h3>
                    <button type="submit" class="btn-icon" title="Opslaan">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    </button>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="font-weight: bold; display: block; margin-bottom: 0.5rem;">Eindstand</label>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="number" name="score_home" value="<?= $match['score_home'] ?>" min="0" style="width: 60px; text-align: center;">
                        <span>-</span>
                        <input type="number" name="score_away" value="<?= $match['score_away'] ?>" min="0" style="width: 60px; text-align: center;">
                    </div>
                </div>

                <div class="form-group">
                    <label style="font-weight: bold; display: block; margin-bottom: 0.5rem;">Evaluatie & Opmerkingen</label>
                    <textarea name="evaluation" rows="6" placeholder="Schrijf hier je evaluatie van de wedstrijd..." style="width: 100%;"><?= e($match['evaluation'] ?? '') ?></textarea>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.match-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

@media (min-width: 900px) {
    .match-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        align-items: start;
    }
    /* Make the lineup card span full height or take left column */
    .match-grid > div:nth-child(1) {
        grid-row: span 2;
    }
}

.lineup-editor {
    display: flex;
    flex-direction: column;
    gap: 20px;
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
    width: 60px;
    z-index: 10;
    transition: transform 0.1s;
    touch-action: none; /* Prevents browser scrolling/zooming on the token */
}

.player-token:active {
    cursor: grabbing;
    transform: scale(1.1);
}

.player-token.on-field {
    position: absolute;
    transform: translate(-50%, -25px);
}

.player-token.on-field:active {
    transform: translate(-50%, -25px) scale(1.1);
}

.player-jersey {
    filter: drop-shadow(0 2px 3px rgba(0,0,0,0.3));
}

/* Goalkeeper Jersey Override */
.player-token.is-goalkeeper path {
    fill: url(#blue-jersey) !important;
}

.player-name {
    margin-top: 2px;
    font-size: 10px;
    font-weight: bold;
    color: #333;
    background: rgba(255,255,255,0.8);
    padding: 2px 6px;
    border-radius: 10px;
    white-space: nowrap;
    text-align: center;
    max-width: 90px;
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
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #ddd;
}

.players-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    min-height: 60px;
    align-items: flex-start;
}

.players-list .player-token {
    position: relative;
    left: auto !important;
    top: auto !important;
    transform: none !important;
}

.players-list .player-name {
    color: #333;
    background: none;
    text-shadow: none;
}

.position-slot {
    position: absolute;
    width: 50px;
    height: 50px;
    transform: translate(-50%, -50%);
    pointer-events: none;
    z-index: 0;
    opacity: 0.6;
}

.position-slot path {
    fill: none;
    stroke: rgba(255, 255, 255, 0.8);
    stroke-width: 2;
    stroke-dasharray: 5, 5;
}

.position-slot text {
    fill: rgba(255, 255, 255, 0.8);
    font-family: Arial;
    font-size: 30px;
    font-weight: bold;
    text-anchor: middle;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const playersList = document.getElementById('players-list');
    const field = document.getElementById('football-field');
    const saveBtn = document.getElementById('save-lineup');
    let draggedItem = null;

    // Define formations and their slot coordinates (in percentages)
    const formations = {
        '6-vs-6': [
            { x: 50, y: 88, label: 'K' },
            { x: 20, y: 65, label: 'V' },
            { x: 80, y: 65, label: 'V' },
            { x: 50, y: 45, label: 'M' },
            { x: 20, y: 20, label: 'A' },
            { x: 80, y: 20, label: 'A' }
        ],
        '8-vs-8': [
            { x: 50, y: 85, label: 'K' },  // Keeper
            { x: 30, y: 75, label: 'V' },  // Linksachter
            { x: 70, y: 75, label: 'V' },  // Rechtsachter
            { x: 20, y: 50, label: 'M' },  // Linksmidden
            { x: 50, y: 50, label: 'M' },  // Centraal midden
            { x: 80, y: 50, label: 'M' },  // Rechtsmidden
            { x: 35, y: 25, label: 'A' },  // Linksvoor
            { x: 65, y: 25, label: 'A' }   // Rechtsvoor
        ],
        '4-3-3': [
            { x: 50, y: 90, label: 'K' },
            { x: 15, y: 75, label: 'LA' },
            { x: 38, y: 75, label: 'CV' },
            { x: 62, y: 75, label: 'CV' },
            { x: 85, y: 75, label: 'RA' },
            { x: 30, y: 50, label: 'CM' },
            { x: 50, y: 55, label: 'VM' },
            { x: 70, y: 50, label: 'CM' },
            { x: 15, y: 25, label: 'LB' },
            { x: 50, y: 20, label: 'SP' },
            { x: 85, y: 25, label: 'RB' }
        ]
    };

    // Render slots for current formation
    const currentFormation = field.dataset.formation;
    const slots = formations[currentFormation] || [];
    
    slots.forEach(slot => {
        const slotEl = document.createElement('div');
        slotEl.className = 'position-slot';
        slotEl.style.left = slot.x + '%';
        slotEl.style.top = slot.y + '%';
        
        slotEl.innerHTML = `
            <svg viewBox="0 0 100 100" width="50" height="50">
                <path d="M15,30 L30,10 L70,10 L85,30 L75,40 L70,35 L70,90 L30,90 L30,35 L25,40 Z" />
                <text x="50" y="65">${slot.label}</text>
            </svg>
        `;
        
        field.insertBefore(slotEl, field.firstChild);
    });

    // Helper to check if a player is on the keeper slot and update state
    const checkPlayerState = (player, x, y) => {
        // Keeper check
        const keeperSlot = slots.find(s => s.label === 'K');
        if (keeperSlot && Math.abs(x - keeperSlot.x) < 10 && Math.abs(y - keeperSlot.y) < 15) {
            player.classList.add('is-goalkeeper');
        } else {
            player.classList.remove('is-goalkeeper');
        }
    };

    // Helper for snapping logic (shared between mouse and touch)
    const getSnappedPosition = (xPercent, yPercent) => {
        let finalX = xPercent;
        let finalY = yPercent;
        let snapped = false;
        const snapRange = 10;

        if (slots.length > 0) {
            let closestSlot = null;
            let minDistance = Infinity;

            slots.forEach(slot => {
                const dx = xPercent - slot.x;
                const dy = yPercent - slot.y;
                const distance = Math.sqrt(dx*dx + dy*dy);
                
                if (distance < minDistance) {
                    minDistance = distance;
                    closestSlot = slot;
                }
            });

            if (closestSlot && minDistance <= snapRange) {
                finalX = closestSlot.x;
                finalY = closestSlot.y;
                snapped = true;
            }
        }

        if (!snapped) {
            finalX = Math.round(finalX / 5) * 5;
            finalY = Math.round(finalY / 5) * 5;
        }
        return { x: finalX, y: finalY };
    };

    // Check initial positions
    field.querySelectorAll('.player-token').forEach(player => {
        const x = parseFloat(player.style.left);
        const y = parseFloat(player.style.top);
        checkPlayerState(player, x, y);
    });

    // --- MOUSE DRAG EVENTS (Desktop) ---
    document.addEventListener('dragstart', (e) => {
        const target = e.target.closest('.player-token');
        if (target) {
            draggedItem = target;
            e.dataTransfer.setData('text/plain', target.dataset.id);
            e.dataTransfer.effectAllowed = 'move';
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
        
        let xPercent = (x / rect.width) * 100;
        let yPercent = (y / rect.height) * 100;

        const pos = getSnappedPosition(xPercent, yPercent);

        if (draggedItem.parentElement === playersList) {
            draggedItem.classList.add('on-field');
            field.appendChild(draggedItem);
        }

        draggedItem.style.left = pos.x + '%';
        draggedItem.style.top = pos.y + '%';
        
        checkPlayerState(draggedItem, pos.x, pos.y);
    });

    // Bench Drop Zone
    playersList.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    });

    playersList.addEventListener('drop', (e) => {
        e.preventDefault();
        if (!draggedItem) return;

        if (draggedItem.parentElement === field) {
            draggedItem.classList.remove('on-field');
            draggedItem.classList.remove('is-goalkeeper');
            draggedItem.style.left = '';
            draggedItem.style.top = '';
            playersList.appendChild(draggedItem);
        }
    });

    // --- TOUCH EVENTS (Mobile) ---
    let activeTouchItem = null;
    let touchOffsetX = 0;
    let touchOffsetY = 0;
    let originalParent = null;
    let originalNextSibling = null; // To restore position if needed

    document.addEventListener('touchstart', (e) => {
        const target = e.target.closest('.player-token');
        if (!target) return;
        
        // Prevent default to stop scrolling and long-press delay
        e.preventDefault();
        
        activeTouchItem = target;
        originalParent = target.parentElement;
        originalNextSibling = target.nextElementSibling;
        
        const touch = e.touches[0];
        const rect = target.getBoundingClientRect();
        
        // Calculate offset so we drag from where we touched
        touchOffsetX = touch.clientX - rect.left;
        touchOffsetY = touch.clientY - rect.top;
        
        // Prepare for dragging: Move to body and set fixed position
        // We clone the width to prevent resizing
        const width = rect.width;
        
        activeTouchItem.style.position = 'fixed';
        activeTouchItem.style.zIndex = '1000';
        activeTouchItem.style.width = width + 'px';
        activeTouchItem.style.left = (touch.clientX - touchOffsetX) + 'px';
        activeTouchItem.style.top = (touch.clientY - touchOffsetY) + 'px';
        activeTouchItem.style.pointerEvents = 'none'; // Allow touch to pass through to check element below
        
        // Visual feedback
        activeTouchItem.style.opacity = '0.8';
        activeTouchItem.style.transform = 'scale(1.1)';
        
        // Append to body so it floats over everything
        document.body.appendChild(activeTouchItem);
    }, { passive: false });

    document.addEventListener('touchmove', (e) => {
        if (!activeTouchItem) return;
        e.preventDefault(); // Stop scrolling
        
        const touch = e.touches[0];
        activeTouchItem.style.left = (touch.clientX - touchOffsetX) + 'px';
        activeTouchItem.style.top = (touch.clientY - touchOffsetY) + 'px';
    }, { passive: false });

    document.addEventListener('touchend', (e) => {
        if (!activeTouchItem) return;
        
        const touch = e.changedTouches[0];
        const x = touch.clientX;
        const y = touch.clientY;
        
        // Check what is underneath the finger
        // We temporarily hid pointer events on the item so this works
        const elementBelow = document.elementFromPoint(x, y);
        
        // Reset styles
        activeTouchItem.style.position = '';
        activeTouchItem.style.zIndex = '';
        activeTouchItem.style.width = '';
        activeTouchItem.style.transform = '';
        activeTouchItem.style.opacity = '';
        activeTouchItem.style.pointerEvents = '';
        
        // Check drop zone
        const dropField = elementBelow ? elementBelow.closest('#football-field') : null;
        const dropBench = elementBelow ? elementBelow.closest('#players-list') : null;
        
        if (dropField) {
            // Logic to place on field
            const rect = dropField.getBoundingClientRect();
            let xPercent = ((x - rect.left) / rect.width) * 100;
            let yPercent = ((y - rect.top) / rect.height) * 100;
            
            const pos = getSnappedPosition(xPercent, yPercent);
            
            activeTouchItem.classList.add('on-field');
            dropField.appendChild(activeTouchItem);
            activeTouchItem.style.left = pos.x + '%';
            activeTouchItem.style.top = pos.y + '%';
            checkPlayerState(activeTouchItem, pos.x, pos.y);
            
        } else if (dropBench) {
            // Logic to place on bench
            activeTouchItem.classList.remove('on-field');
            activeTouchItem.classList.remove('is-goalkeeper');
            activeTouchItem.style.left = '';
            activeTouchItem.style.top = '';
            dropBench.appendChild(activeTouchItem);
        } else {
            // Dropped nowhere valid? Return to original place
            if (originalParent === field) {
                // If it was on field, keep it there (or put back to bench?)
                // Let's put it back on bench to be safe, or try to restore.
                // Restoring to field without coords is hard.
                // Default to bench.
                activeTouchItem.classList.remove('on-field');
                activeTouchItem.classList.remove('is-goalkeeper');
                activeTouchItem.style.left = '';
                activeTouchItem.style.top = '';
                playersList.appendChild(activeTouchItem);
            } else {
                // Put back in list at original position if possible
                if (originalNextSibling) {
                    originalParent.insertBefore(activeTouchItem, originalNextSibling);
                } else {
                    originalParent.appendChild(activeTouchItem);
                }
            }
        }
        
        activeTouchItem = null;
        originalParent = null;
        originalNextSibling = null;
    });

    // Save Functionality
    saveBtn.addEventListener('click', () => {
        const players = [];
        const fieldPlayers = field.querySelectorAll('.player-token');
        
        fieldPlayers.forEach(player => {
            players.push({
                player_id: parseInt(player.dataset.id),
                x: parseFloat(player.style.left),
                y: parseFloat(player.style.top)
            });
        });

        fetch('/matches/save-lineup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.getElementById('csrf_token').value
            },
            body: JSON.stringify({
                match_id: <?= $match['id'] ?>,
                players: players,
                csrf_token: document.getElementById('csrf_token').value
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Opstelling opgeslagen!');
            } else {
                alert('Fout bij opslaan: ' + (data.error || 'Onbekend'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Er is een fout opgetreden.');
        });
    });
});
</script>
