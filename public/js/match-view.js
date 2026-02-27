document.addEventListener('DOMContentLoaded', () => {
    const playersList = document.getElementById('players-list'); // Bench
    const absentList = document.getElementById('absent-list'); // Absent
    const keepersList = document.getElementById('keepers-list');
    const field = document.getElementById('football-field');
    const MAX_KEEPERS = 2;
    // const saveBtn = document.getElementById('save-lineup'); // Removed
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
            { x: 50, y: 88, label: 'K' },  // Keeper
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
        // Player gets blue shirt if:
        // 1. Is an assigned keeper AND (is strictly on keeper position OR NOT on field)
        // 2. Used to be: Is an assigned keeper

        const pid = player.dataset.id;
        const isAssigned = keepersList.querySelector(`.player-token[data-id="${pid}"]`);
        
        // Check if player is on the field (either by class or context)
        // Note: x, y are passed for field players. For bench players, checkPlayerState isn't usually called with coords.
        const isOnField = player.classList.contains('on-field');
        
        let isPositionally = false;
        
        if (isOnField) {
            const keeperSlot = slots.find(s => s.label === 'K');
            if (keeperSlot && Math.abs(x - keeperSlot.x) < 10 && Math.abs(y - keeperSlot.y) < 15) {
                isPositionally = true;
            }
        }

        const jerseyPath = player.querySelector('.player-jersey path');
        
        let shouldBeBlue = false;
        if (isPositionally) {
            shouldBeBlue = true;
        } else if (isAssigned && !isOnField) {
            shouldBeBlue = true;
        } 
        
        if (shouldBeBlue) {
            player.classList.add('is-goalkeeper');
            if (jerseyPath) jerseyPath.setAttribute('fill', 'url(#blue-jersey)');
        } else {
            player.classList.remove('is-goalkeeper');
            if (jerseyPath) jerseyPath.setAttribute('fill', 'url(#striped-jersey)');
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

    const syncKeeperPlaceholders = () => {
        const keeperCount = keepersList.querySelectorAll('.player-token').length;
        const placeholdersNeeded = Math.max(0, MAX_KEEPERS - keeperCount);

        keepersList.querySelectorAll('.keeper-slot-empty').forEach(slot => slot.remove());
        for (let i = 0; i < placeholdersNeeded; i++) {
            const slot = document.createElement('div');
            slot.className = 'keeper-slot-empty';
            slot.innerText = 'Sleep speler';
            keepersList.appendChild(slot);
        }
    };

    // Check initial positions and set colors
    const refreshAllColors = () => {
        // Field players
        field.querySelectorAll('.player-token').forEach(player => {
            const x = parseFloat(player.style.left);
            const y = parseFloat(player.style.top);
            checkPlayerState(player, x, y);
        });

        // Bench players
        if (playersList) {
            playersList.querySelectorAll('.player-token').forEach(player => {
                const pid = player.dataset.id;
                const isAssigned = keepersList ? keepersList.querySelector(`.player-token[data-id="${pid}"]`) : false;
                const jerseyPath = player.querySelector('.player-jersey path');
                
                if (isAssigned) {
                    player.classList.add('is-goalkeeper');
                    if (jerseyPath) jerseyPath.setAttribute('fill', 'url(#blue-jersey)');
                } else {
                    player.classList.remove('is-goalkeeper');
                    if (jerseyPath) jerseyPath.setAttribute('fill', 'url(#striped-jersey)');
                }
            });
        }

        // Absent players
        if (absentList) {
            absentList.querySelectorAll('.player-token').forEach(player => {
                 const jerseyPath = player.querySelector('.player-jersey path');
                 player.classList.remove('is-goalkeeper');
                 if (jerseyPath) jerseyPath.setAttribute('fill', '#999');
            });
        }

        syncKeeperPlaceholders();
    };

    // Initial check
    refreshAllColors();

    // --- MOUSE DRAG EVENTS (Desktop) ---
    document.addEventListener('dragstart', (e) => {
        const target = e.target.closest('.player-token');
        if (target) {
            draggedItem = target;
            e.dataTransfer.setData('text/plain', target.dataset.id);
            // Allow both copy (for keepers) and move (for field/bench)
            e.dataTransfer.effectAllowed = 'copyMove';
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

        // If dragging FROM keepers list (the visual clone), ignore or handle specifically
        if (draggedItem.dataset.source === 'keepers') {
             return;
        }

        const rect = field.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        let xPercent = (x / rect.width) * 100;
        let yPercent = (y / rect.height) * 100;

        const pos = getSnappedPosition(xPercent, yPercent);

        if (draggedItem.parentElement !== field) {
            draggedItem.classList.add('on-field');
            field.appendChild(draggedItem);
        }

        draggedItem.style.left = pos.x + '%';
        draggedItem.style.top = pos.y + '%';
        
        refreshAllColors();
        debouncedSave();
    });

    // Bench Drop Zone
    playersList.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    });

    playersList.addEventListener('drop', (e) => {
        e.preventDefault();
        if (!draggedItem) return;
        
        // Allow removing keeper by dragging back to bench
        if (draggedItem.dataset.source === 'keepers') {
             draggedItem.remove();

             refreshAllColors();
             debouncedSave();
             return;
        }

        // Move to bench (from Field OR Absent)
        draggedItem.classList.remove('on-field');
        draggedItem.style.left = '';
        draggedItem.style.top = '';
        playersList.appendChild(draggedItem);
        
        refreshAllColors();
        debouncedSave();
    });
    
    // Absent Drop Zone
    if (absentList) {
        absentList.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });

        absentList.addEventListener('drop', (e) => {
            e.preventDefault();
            if (!draggedItem) return;
            if (draggedItem.dataset.source === 'keepers') return;

            // Move to absent
            draggedItem.classList.remove('on-field');
            draggedItem.style.left = '';
            draggedItem.style.top = '';
            absentList.appendChild(draggedItem);
            
            // Remove placeholder if present
            const placeholder = absentList.querySelector('.drop-placeholder');
            if (placeholder) placeholder.remove();
            
            debouncedSave();
            refreshAllColors();
        });
    }

    // Keepers List Drop Zone
    keepersList.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
    });

    keepersList.addEventListener('drop', (e) => {
        e.preventDefault();
        if (!draggedItem) return;
        
        const currentKeepers = keepersList.querySelectorAll('.player-token').length;
        
        // Prevent adding duplicate of SAME player
        const pid = draggedItem.dataset.id;
        const exists = keepersList.querySelector(`.player-token[data-id="${pid}"]`);
        if (exists) return; 

        // If dragging FROM keepers list (reordering/self-drop), ignore
        if (draggedItem.dataset.source === 'keepers') return;

        if (currentKeepers >= 2) {
            // Determine which keeper to replace
            // Check if dropped ON TOP of a specific keeper
            const targetKeeper = e.target.closest('.player-token');
            
            if (targetKeeper && targetKeeper.parentElement === keepersList) {
                // Remove the specific keeper we dropped onto
                targetKeeper.remove();
            } else {
                // Otherwise remove the first one to make room
                const firstKeeper = keepersList.querySelector('.player-token');
                if (firstKeeper) firstKeeper.remove();
            }
        }

        // Create Clone
        const clone = draggedItem.cloneNode(true);
        clone.classList.remove('on-field');
        clone.classList.add('is-goalkeeper');
        clone.style.left = '';
        clone.style.top = '';
        clone.style.position = 'relative'; 
        clone.style.transform = 'none';
        clone.style.opacity = '1';
        clone.setAttribute('data-source', 'keepers'); 

        keepersList.appendChild(clone);
        
        refreshAllColors();
        debouncedSave();
    });

    // Removing from Keepers List
    keepersList.addEventListener('click', (e) => {
        const token = e.target.closest('.player-token');
        if (token && token.dataset.source === 'keepers') {
            token.remove();
            refreshAllColors();
            debouncedSave();
        }
    });

    // --- TOUCH EVENTS (Mobile) --- (UPDATED FOR KEEPERS)
    let activeTouchItem = null;
    let touchOffsetX = 0;
    let touchOffsetY = 0;
    let originalParent = null;
    let originalLeft = '';
    let originalTop = '';

    const restoreTouchItem = () => {
        if (!activeTouchItem || !originalParent) return;
        originalParent.appendChild(activeTouchItem);
        activeTouchItem.style.left = originalLeft;
        activeTouchItem.style.top = originalTop;
    };

    const resetActiveTouchItemStyles = () => {
        if (!activeTouchItem) return;
        activeTouchItem.style.position = '';
        activeTouchItem.style.zIndex = '';
        activeTouchItem.style.width = '';
        activeTouchItem.style.transform = '';
        activeTouchItem.style.opacity = '';
        activeTouchItem.style.pointerEvents = '';
    };

    const clearTouchState = () => {
        activeTouchItem = null;
        originalParent = null;
        originalLeft = '';
        originalTop = '';
    };

    document.addEventListener('touchstart', (e) => {
        const target = e.target.closest('.player-token');
        if (!target) return;
        
        // Prevent default to stop scrolling and long-press delay
        e.preventDefault();
        
        activeTouchItem = target;
        originalParent = target.parentElement;
        originalLeft = target.style.left;
        originalTop = target.style.top;
        
        const touch = e.touches[0];
        const rect = target.getBoundingClientRect();
        
        // Calculate offset so we drag from where we touched
        touchOffsetX = touch.clientX - rect.left;
        touchOffsetY = touch.clientY - rect.top;
        
        // Prepare for dragging
        const width = rect.width;
        
        activeTouchItem.style.position = 'fixed';
        activeTouchItem.style.zIndex = '1000';
        activeTouchItem.style.width = width + 'px';
        activeTouchItem.style.left = (touch.clientX - touchOffsetX) + 'px';
        activeTouchItem.style.top = (touch.clientY - touchOffsetY) + 'px';
        activeTouchItem.style.pointerEvents = 'none'; 
        
        // Visual feedback
        activeTouchItem.style.opacity = '0.8';
        activeTouchItem.style.transform = 'scale(1.1)';
        
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
        
        // Use elementsFromPoint to identify targets through the dragged item
        const elements = document.elementsFromPoint(x, y);

        // Find potential drop zones
        // We look for the main containers in the list of elements under the finger
        const dropField = elements.find(el => el.id === 'football-field' || el === field);
        const dropBench = elements.find(el => el.id === 'players-list' || el === playersList);
        const dropKeepers = elements.find(el => el.id === 'keepers-list' || el === keepersList);
        const dropAbsent = (typeof absentList !== 'undefined' && absentList) ? elements.find(el => el.id === 'absent-list' || el === absentList) : null;
        
        // Reset styles for the dragged item
        resetActiveTouchItemStyles();
        
        if (dropKeepers) {
             // Keep touch behavior aligned with desktop:
             // allow replacing a keeper when list is full (max 2).
             const currentKeepers = keepersList.querySelectorAll('.player-token').length;
             const playerId = activeTouchItem.dataset.id;
             const exists = keepersList.querySelector(`.player-token[data-id="${playerId}"]`);
             
             // Don't add if already there or if dragging FROM keepers
             if (!exists && activeTouchItem.dataset.source !== 'keepers') {
                 if (currentKeepers >= MAX_KEEPERS) {
                     const targetKeeper = elements.find(el =>
                         el.classList &&
                         el.classList.contains('player-token') &&
                         el.dataset &&
                         el.dataset.source === 'keepers' &&
                         el.parentElement === keepersList
                     );
                     
                     if (targetKeeper) {
                         targetKeeper.remove();
                     } else {
                         const firstKeeper = keepersList.querySelector('.player-token');
                         if (firstKeeper) firstKeeper.remove();
                     }
                 }
 
                 const clone = activeTouchItem.cloneNode(true);
                 clone.classList.remove('on-field');
                 clone.classList.add('is-goalkeeper');
                 // Reset clone styles
                 clone.style.left = '';
                 clone.style.top = '';
                 clone.style.position = '';
                 clone.style.width = '';
                 clone.style.opacity = '';
                 clone.style.pointerEvents = '';
                 clone.style.transform = '';
                 clone.style.zIndex = '';
                 clone.setAttribute('data-source', 'keepers');
                 
                 keepersList.appendChild(clone);
             }
             
             // Return original to where it was
             restoreTouchItem();

        } else if (dropField) {
            if (activeTouchItem.dataset.source === 'keepers') {
                 // Deleting keeper by dragging to field? No, just return. Use click to delete.
                 restoreTouchItem();
            } else {
                const fieldRect = field.getBoundingClientRect();
                let xPercent = ((x - fieldRect.left) / fieldRect.width) * 100;
                let yPercent = ((y - fieldRect.top) / fieldRect.height) * 100;
                
                const pos = getSnappedPosition(xPercent, yPercent);
                
                activeTouchItem.classList.add('on-field');
                field.appendChild(activeTouchItem);
                activeTouchItem.style.left = pos.x + '%';
                activeTouchItem.style.top = pos.y + '%';
                checkPlayerState(activeTouchItem, pos.x, pos.y);
            }
            
        } else if (dropBench) {
            if (activeTouchItem.dataset.source === 'keepers') {
                 activeTouchItem.remove();
            } else {
                // Logic to place on bench
                activeTouchItem.classList.remove('on-field');
                activeTouchItem.style.left = '';
                activeTouchItem.style.top = '';
                dropBench.appendChild(activeTouchItem);
            }

        } else if (dropAbsent) {
             if (activeTouchItem.dataset.source === 'keepers') {
                 restoreTouchItem();
            } else {
                // Logic to place on absent
                activeTouchItem.classList.remove('on-field');
                activeTouchItem.style.left = '';
                activeTouchItem.style.top = '';
                dropAbsent.appendChild(activeTouchItem);
                
                 // Remove placeholder
                 const placeholder = dropAbsent.querySelector('.drop-placeholder');
                 if (placeholder) placeholder.remove();
            }

        } else {
            // Dropped nowhere valid
            restoreTouchItem();
        }
        
        refreshAllColors();
        debouncedSave();
        clearTouchState();
    });

    document.addEventListener('touchcancel', () => {
        if (!activeTouchItem) return;

        // Drag was interrupted by the OS/browser: restore safely, do not save.
        resetActiveTouchItemStyles();
        restoreTouchItem();
        refreshAllColors();
        clearTouchState();
    }, { passive: false });


    // --- AUTOSAVE LOGIC ---
    let saveTimeout = null;
    const saveStatusEl = document.getElementById('save-status');

    const updateSaveStatus = (status, message = '') => {
        if (!saveStatusEl) return;
        
        switch(status) {
            case 'saving':
                saveStatusEl.textContent = 'Opslaan...';
                saveStatusEl.style.color = '#666';
                break;
            case 'saved':
                saveStatusEl.textContent = 'Opgeslagen';
                saveStatusEl.style.color = '#2e7d32';
                setTimeout(() => {
                    if (saveStatusEl.textContent === 'Opgeslagen') saveStatusEl.textContent = '';
                }, 2000);
                break;
            case 'error':
                saveStatusEl.textContent = message || 'Fout bij opslaan';
                saveStatusEl.style.color = '#d32f2f';
                break;
            default:
                saveStatusEl.textContent = '';
        }
    };

    const saveLineup = async (manual = false) => {
        if (!manual) updateSaveStatus('saving');
        
        const players = [];
        const seenIds = new Set();
        
        const fieldPlayers = field.querySelectorAll('.player-token');
        const benchPlayers = playersList.querySelectorAll('.player-token');
        const absentPlayers = absentList ? absentList.querySelectorAll('.player-token') : [];
        const keeperTokens = keepersList.querySelectorAll('.player-token');
        
        // Get IDs of players marked as keeper
        const keeperIds = Array.from(keeperTokens).map(el => parseInt(el.dataset.id));

        // Add players on field
        fieldPlayers.forEach(player => {
            const pid = parseInt(player.dataset.id);
            if (seenIds.has(pid)) return;
            seenIds.add(pid);
            
            players.push({
                player_id: pid,
                x: parseFloat(player.style.left),
                y: parseFloat(player.style.top),
                is_substitute: 0,
                is_keeper: keeperIds.includes(pid) ? 1 : 0,
                is_absent: 0
            });
        });

        // Add players on bench (to save their keeper status if assigned but on bench)
         benchPlayers.forEach(player => {
            const pid = parseInt(player.dataset.id);
            if (seenIds.has(pid)) return;
            seenIds.add(pid);

            players.push({
                player_id: pid,
                x: 0,
                y: 0,
                is_substitute: 1,
                is_keeper: keeperIds.includes(pid) ? 1 : 0,
                is_absent: 0
            });
        });

        // Add absent players
         absentPlayers.forEach(player => {
            const pid = parseInt(player.dataset.id);
            if (seenIds.has(pid)) return;
            seenIds.add(pid);

            players.push({
                player_id: pid,
                x: 0,
                y: 0,
                is_substitute: 0,
                is_keeper: 0, // Absent players can't be active keepers
                is_absent: 1
            });
        });

        try {
            const response = await fetch('/matches/save-lineup', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.getElementById('csrf_token').value
                },
                body: JSON.stringify({
                    match_id: document.getElementById('match_id').value,
                    players: players,
                    csrf_token: document.getElementById('csrf_token').value
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                if (manual) alert('Opstelling opgeslagen!');
                else updateSaveStatus('saved');
            } else {
                if (manual) alert('Fout bij opslaan: ' + (data.error || 'Onbekend'));
                else updateSaveStatus('error', 'Opslaan mislukt');
            }
        } catch (error) {
            console.error('Error:', error);
            if (manual) alert('Er is een fout opgetreden.');
            else updateSaveStatus('error', 'Verbindingsfout');
        }
    };

    const debouncedSave = () => {
        clearTimeout(saveTimeout);
        updateSaveStatus('saving'); // Show saving immediately so user knows something is happening
        saveTimeout = setTimeout(() => {
            saveLineup(false);
        }, 1000); // 1 second debounce
    };

});
