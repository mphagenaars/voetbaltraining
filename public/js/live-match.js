document.addEventListener('DOMContentLoaded', () => {
    const matchId = document.getElementById('match_id').value;
    const csrfToken = document.getElementById('csrf_token').value;
    const timerDisplay = document.getElementById('timer-display');
    const periodDisplay = document.getElementById('period-display');
    const timerBtn = document.getElementById('timer-btn');
    const modal = document.getElementById('action-modal');
    const actionForm = document.getElementById('action-form');
    const timelineList = document.getElementById('timeline-list');
    
    // Initial State
    let state = JSON.parse(document.getElementById('initial_timer_state').value);
    let timerInterval = null;

    function formatTime(seconds) {
        const m = Math.floor(seconds / 60);
        const s = Math.floor(seconds % 60);
        return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
    }

    function updateTimerUI() {
        if (state.is_playing) {
            const now = Math.floor(Date.now() / 1000);
            const currentSessionSeconds = now - state.start_time;
            const totalSeconds = state.total_seconds + currentSessionSeconds;
            timerDisplay.textContent = formatTime(totalSeconds);
            timerBtn.textContent = 'Stop Tijd';
            timerBtn.classList.remove('btn-primary');
            timerBtn.classList.add('btn-danger');
            timerBtn.style.backgroundColor = '#d32f2f';
        } else {
            timerDisplay.textContent = formatTime(state.total_seconds);
            timerBtn.textContent = state.total_seconds > 0 ? 'Hervat Tijd' : 'Start Wedstrijd';
            timerBtn.classList.add('btn-primary');
            timerBtn.classList.remove('btn-danger');
            timerBtn.style.backgroundColor = ''; // Reset
        }
        
        periodDisplay.textContent = state.current_period > 0 ? `Periode ${state.current_period}` : 'Nog niet gestart';
    }

    function startTicker() {
        if (timerInterval) clearInterval(timerInterval);
        if (state.is_playing) {
            timerInterval = setInterval(updateTimerUI, 1000);
        }
        updateTimerUI();
    }

    timerBtn.addEventListener('click', () => {
        const text = timerBtn.textContent;
        // Optimization: immediately toggle UI state for responsiveness? 
        // Better wait for server confirmation to avoid de-sync.
        
        const action = state.is_playing ? 'stop' : 'start';
        
        fetch('/matches/timer-action', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                match_id: matchId,
                action: action
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                state = data.timerState;
                startTicker();
            }
        });
    });

    // Start ticker on load
    startTicker();

    // --- Modal Logic ---
    window.openActionModal = function(type) {
        document.getElementById('modal-type').value = type;
        
        const typeLabels = {
            'goal': 'Doelpunt Toevoegen',
            'card': 'Kaart Geven',
            'sub': 'Wissel Doorvoeren',
            'other': 'Notitie Maken'
        };
        document.getElementById('modal-title').textContent = typeLabels[type] || 'Actie Toevoegen';

        // Toggle visibility of fields based on type
        const playerSelectGroup = document.getElementById('player-select-group');
        const subGroup = document.getElementById('sub-group');
        
        if (type === 'sub') {
            playerSelectGroup.style.display = 'none';
            subGroup.style.display = 'block';
            
            // Required attribute toggle for HTML5 validation would be nice but not strictly required if we validate in JS
        } else {
            playerSelectGroup.style.display = 'block';
            subGroup.style.display = 'none';
        }
        
        // Calculate current minute
        let currentMinute = Math.floor(state.total_seconds / 60);
        if (state.is_playing) {
            const now = Math.floor(Date.now() / 1000);
            currentMinute = Math.floor((state.total_seconds + (now - state.start_time)) / 60);
        }
        // Always round up to next minute for display (0:01 = 1st minute)
        currentMinute += 1; 

        document.getElementById('modal-minute').value = currentMinute;
        
        // Toggle player select for 'card'
        if (type === 'card') {
             // Maybe show radio buttons for yellow/red?
             // For simplicity, just use description or assume user selects type in specific buttons if I split them.
             // The view has one "Kaart" button. I should probably let them choose Yellow/Red in the modal?
             // Or update modal to have a sub-type selector?
             // Existing code uses 'card_yellow', 'card_red'.
             // Simplification: Let's assume the user selects correct type via description or I add a selector.
             // But 'type' is hidden input. 
             // To fix: Split buttons in View or Add selector in Modal.
             // View has "Kaart" button which passes 'card'.
             // Let's change hidden input to a select for cards.
        }
        
        modal.style.display = 'flex';
    };

    window.closeModal = function() {
        modal.style.display = 'none';
        actionForm.reset();
    };

    actionForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        const formData = new FormData(actionForm);
        const data = Object.fromEntries(formData.entries());
        
        // Handle special player values for Goal
        if (data.type === 'goal') {
             const playerSelect = actionForm.querySelector('select[name="player_id"]');
             if (playerSelect) {
                 if (playerSelect.value === 'unknown') {
                     data.type = 'goal_unknown';
                     data.player_id = ''; 
                 } else if (playerSelect.value === 'opponent') {
                     data.type = 'goal'; 
                     data.player_id = '';
                 }
             }
        }

        // Handle 'card' generic type -> prompt or default to yellow?
        // Ideally I should have added buttons for Yellow/Red.
        // Let's rely on description for now or hardcode specific logic not implemented completely.
        // Actually, Controller expects 'card_yellow' or 'card_red' in enum?
        // Views/matches/view.php select options: card_yellow, card_red.
        // If I send 'card', it might fail validation or just be 'card'.
        // Let's assume 'card_yellow' as default if 'card' is passed, or user types "Rood" in desc.
        // Better: Add select in modal if type is 'card'.
        
        // Quick fix: Map 'card' to 'card_yellow' by default.
        if (data.type === 'card') {
             // Ask user or default?
             data.type = 'card_yellow'; // Default
             if (confirm("Is het een Rode kaart? Klik OK voor Rood, Annuleren voor Geel.")) {
                 data.type = 'card_red';
             }
        }

        if (data.type === 'sub') {
             const inPlayer = document.getElementById('player_in').options[document.getElementById('player_in').selectedIndex].text;
             const outPlayer = document.getElementById('player_out').options[document.getElementById('player_out').selectedIndex].text;
             data.description = `UIT: ${outPlayer} -> IN: ${inPlayer}`;
             
             // Optionally send player_id as the one coming IN? Or just use description.
             // Let's set player_id to the incoming player so it's linked to him in stats if needed
             data.player_id = document.getElementById('player_in').value;
        }
        
        data.action = 'add';
        data.csrf_token = csrfToken;
        
        fetch('/matches/add-event', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(resData => {
            if (resData.success) {
                closeModal();
                // Refresh timeline
                // Simplified: just reload list mostly or append.
                // Since `resData.events` returns full list, simpler to rebuild list.
                renderTimeline(resData.events);
            } else {
                alert('Fout: ' + (resData.error || 'Onbekend'));
            }
        });
    });

    function renderTimeline(events) {
        timelineList.innerHTML = '';
        events.forEach(event => {
            if (event.type === 'whistle') return;
            
            const li = document.createElement('li');
            li.style.borderBottom = '1px solid #eee';
            li.style.padding = '0.5rem 0';
            
            let icon = '🔹';
            if(event.type === 'goal') {
                icon = event.player_id ? '⚽ Doelpunt' : '⚽ Tegendoelpunt';
            }
            if(event.type === 'goal_unknown') icon = '⚽ Doelpunt (Overig)';
            if(event.type === 'card_yellow') icon = '🟨 Gele kaart';
            if(event.type === 'card_red') icon = '🟥 Rode kaart';
            if(event.type === 'sub') icon = '🔄 Wissel';
            
            li.innerHTML = `<strong>${event.minute}'</strong> ${icon} ${event.player_name ? 'door <strong>' + event.player_name + '</strong>' : ''}`;
            timelineList.appendChild(li);
        });
    }
});
