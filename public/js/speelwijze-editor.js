/**
 * Speelwijze (formation template) editor.
 * Manages CRUD of formation templates with draggable position markers on a football field.
 */
document.addEventListener('DOMContentLoaded', () => {
    const editor = document.getElementById('speelwijze-editor');
    if (!editor) return;

    const csrfToken = document.getElementById('csrf_token')?.value || '';
    const teamId = parseInt(editor.dataset.teamId, 10) || 0;

    // DOM refs
    const listEl = document.getElementById('speelwijze-list');
    const detailEl = document.getElementById('speelwijze-detail');
    const nameInput = document.getElementById('speelwijze-name');
    const sharedCheckbox = document.getElementById('speelwijze-shared');
    const fieldEl = document.getElementById('speelwijze-field');
    const posCountEl = document.getElementById('speelwijze-position-count');
    const formatBadgeEl = document.getElementById('speelwijze-format-badge');
    const posEditEl = document.getElementById('speelwijze-pos-edit');
    const posEditCode = document.getElementById('pos-edit-code');
    const posEditLabel = document.getElementById('pos-edit-label');

    // State
    let speelwijzen = [];
    let currentId = null; // null = new
    let positions = []; // [{slot_code, label, x, y}]
    let selectedPosIndex = null;
    let dragState = null;

    function markDirty() { window._speelwijzeDirty = true; }
    function markClean() { window._speelwijzeDirty = false; }

    // Track edits on name and shared checkbox
    nameInput.addEventListener('input', markDirty);
    sharedCheckbox.addEventListener('change', markDirty);

    // --- Init ---
    try {
        const dataEl = document.getElementById('speelwijze-data');
        if (dataEl) {
            speelwijzen = JSON.parse(dataEl.textContent || '[]');
        }
    } catch (_) { /* ignore */ }
    renderList();

    // Show detail panel immediately (field visible by default)
    if (speelwijzen.length > 0) {
        loadSpeelwijze(speelwijzen[0].id);
    } else {
        detailEl.style.display = '';
        renderField();
    }

    // --- List rendering ---
    function renderList() {
        let html = '';
        speelwijzen.forEach(sw => {
            const active = sw.id == currentId ? ' active' : '';
            const posArr = typeof sw.positions === 'string' ? JSON.parse(sw.positions) : (sw.positions || []);
            const count = posArr.length;
            html += `<div class="speelwijze-list-item${active}" data-id="${sw.id}">
                <span class="speelwijze-item-name">${escHtml(sw.name)}</span>
                <span class="speelwijze-badge">${count}v${count}</span>
            </div>`;
        });

        if (!html) {
            html = '<div class="speelwijze-list-empty">Geen speelwijzen</div>';
        }
        listEl.innerHTML = html;

        // Bind click
        listEl.querySelectorAll('.speelwijze-list-item').forEach(el => {
            el.addEventListener('click', () => loadSpeelwijze(parseInt(el.dataset.id, 10)));
        });
    }

    function loadSpeelwijze(id) {
        const sw = speelwijzen.find(s => s.id == id);
        if (!sw) return;
        currentId = sw.id;
        nameInput.value = sw.name;
        sharedCheckbox.checked = !!parseInt(sw.is_shared, 10);
        positions = typeof sw.positions === 'string' ? JSON.parse(sw.positions) : [...(sw.positions || [])];
        selectedPosIndex = null;
        posEditEl.style.display = 'none';
        markClean();

        // Disable editing for shared templates not owned by this team
        const isOwn = sw.team_id && parseInt(sw.team_id, 10) === teamId;
        const isGlobalShared = !sw.team_id && parseInt(sw.is_shared, 10) === 1;
        const readOnly = isGlobalShared;
        setReadOnly(readOnly);

        detailEl.style.display = '';
        renderField();
        renderList();
    }

    function setReadOnly(ro) {
        nameInput.disabled = ro;
        sharedCheckbox.disabled = ro;
        document.getElementById('btn-add-position').style.display = ro ? 'none' : '';
        document.getElementById('btn-save-speelwijze').style.display = ro ? 'none' : '';
        document.getElementById('btn-delete-speelwijze').style.display = ro ? 'none' : '';
        fieldEl.classList.toggle('readonly', ro);
    }

    // --- New speelwijze ---
    document.getElementById('btn-new-speelwijze').addEventListener('click', () => {
        currentId = null;
        nameInput.value = '';
        sharedCheckbox.checked = false;
        positions = [
            { slot_code: 'K', label: 'Keeper', x: 50, y: 88 }
        ];
        selectedPosIndex = null;
        posEditEl.style.display = 'none';
        setReadOnly(false);
        markDirty();
        detailEl.style.display = '';
        renderField();
        renderList();
        nameInput.focus();
    });

    // --- Field rendering ---
    function renderField() {
        // Remove old markers
        fieldEl.querySelectorAll('.sw-marker').forEach(el => el.remove());

        positions.forEach((pos, idx) => {
            const marker = document.createElement('div');
            marker.className = 'sw-marker' + (idx === selectedPosIndex ? ' selected' : '');
            marker.style.left = pos.x + '%';
            marker.style.top = pos.y + '%';
            marker.dataset.index = idx;
            marker.innerHTML = `<svg viewBox="0 0 100 100" width="44" height="44">
                <path d="M15,30 L30,10 L70,10 L85,30 L75,40 L70,35 L70,90 L30,90 L30,35 L25,40 Z" />
                <text x="50" y="65">${escHtml(pos.slot_code)}</text>
            </svg>`;

            // Click to select
            marker.addEventListener('mousedown', (e) => onMarkerDown(e, idx));
            marker.addEventListener('touchstart', (e) => onMarkerDown(e, idx), { passive: false });

            fieldEl.appendChild(marker);
        });

        updateCountBadge();
    }

    function updateCountBadge() {
        const n = positions.length;
        posCountEl.textContent = n + ' positie' + (n !== 1 ? 's' : '');
        formatBadgeEl.textContent = n >= 5 ? n + 'v' + n : '';
    }

    // --- Drag logic ---
    function onMarkerDown(e, idx) {
        if (fieldEl.classList.contains('readonly')) return;
        e.preventDefault();
        e.stopPropagation();

        selectPosition(idx);

        const rect = fieldEl.getBoundingClientRect();
        const point = e.touches ? e.touches[0] : e;
        dragState = {
            idx: idx,
            startX: point.clientX,
            startY: point.clientY,
            origX: positions[idx].x,
            origY: positions[idx].y,
            moved: false,
        };

        document.addEventListener('mousemove', onDragMove);
        document.addEventListener('mouseup', onDragEnd);
        document.addEventListener('touchmove', onDragMove, { passive: false });
        document.addEventListener('touchend', onDragEnd);
    }

    function onDragMove(e) {
        if (!dragState) return;
        e.preventDefault();
        const point = e.touches ? e.touches[0] : e;
        const dx = point.clientX - dragState.startX;
        const dy = point.clientY - dragState.startY;

        if (!dragState.moved && Math.abs(dx) < 3 && Math.abs(dy) < 3) return;
        dragState.moved = true;

        const rect = fieldEl.getBoundingClientRect();
        const pctX = (dx / rect.width) * 100;
        const pctY = (dy / rect.height) * 100;

        positions[dragState.idx].x = Math.max(1, Math.min(99, Math.round(dragState.origX + pctX)));
        positions[dragState.idx].y = Math.max(1, Math.min(99, Math.round(dragState.origY + pctY)));

        // Update marker position directly
        const marker = fieldEl.querySelector(`.sw-marker[data-index="${dragState.idx}"]`);
        if (marker) {
            marker.style.left = positions[dragState.idx].x + '%';
            marker.style.top = positions[dragState.idx].y + '%';
        }
    }

    function onDragEnd() {
        if (dragState?.moved) markDirty();
        dragState = null;
        document.removeEventListener('mousemove', onDragMove);
        document.removeEventListener('mouseup', onDragEnd);
        document.removeEventListener('touchmove', onDragMove);
        document.removeEventListener('touchend', onDragEnd);
    }

    // --- Position selection & editing ---
    function selectPosition(idx) {
        selectedPosIndex = idx;
        renderField();
        showPosEdit(idx);
    }

    function showPosEdit(idx) {
        const pos = positions[idx];
        if (!pos) { posEditEl.style.display = 'none'; return; }
        posEditCode.value = pos.slot_code;
        posEditLabel.value = pos.label;
        posEditEl.style.display = 'flex';
    }

    document.getElementById('btn-pos-edit-ok').addEventListener('click', () => {
        if (selectedPosIndex === null) return;
        const code = posEditCode.value.toUpperCase().replace(/[^A-Z0-9_-]/g, '').substring(0, 5);
        const label = posEditLabel.value.trim().substring(0, 40);
        if (!code) { posEditCode.focus(); return; }
        positions[selectedPosIndex].slot_code = code;
        positions[selectedPosIndex].label = label;
        markDirty();
        posEditEl.style.display = 'none';
        selectedPosIndex = null;
        renderField();
    });

    document.getElementById('btn-pos-edit-remove').addEventListener('click', () => {
        if (selectedPosIndex === null) return;
        positions.splice(selectedPosIndex, 1);
        selectedPosIndex = null;
        posEditEl.style.display = 'none';
        markDirty();
        renderField();
    });

    // Click on field to deselect
    fieldEl.addEventListener('click', (e) => {
        if (e.target === fieldEl || e.target.classList.contains('field-line') || e.target.classList.contains('field-circle') || e.target.classList.contains('field-area')) {
            selectedPosIndex = null;
            posEditEl.style.display = 'none';
            renderField();
        }
    });

    // --- Add position ---
    document.getElementById('btn-add-position').addEventListener('click', () => {
        if (positions.length >= 11) return;
        const nextCode = getNextSlotCode();
        positions.push({ slot_code: nextCode, label: '', x: 50, y: 50 });
        selectedPosIndex = positions.length - 1;
        markDirty();
        renderField();
        showPosEdit(selectedPosIndex);
        posEditCode.focus();
    });

    function getNextSlotCode() {
        const used = new Set(positions.map(p => p.slot_code));
        const candidates = ['K', 'LV', 'RV', 'CV', 'M', 'LM', 'CM', 'RM', 'LA', 'RA', 'SP'];
        for (const c of candidates) {
            if (!used.has(c)) return c;
        }
        for (let i = 1; i <= 11; i++) {
            const code = 'P' + i;
            if (!used.has(code)) return code;
        }
        return 'X';
    }

    // --- Save ---
    document.getElementById('btn-save-speelwijze').addEventListener('click', async () => {
        const name = nameInput.value.trim();
        if (!name) { nameInput.focus(); return; }
        if (positions.length < 5) {
            alert('Een speelwijze moet minimaal 5 posities bevatten.');
            return;
        }

        const body = {
            csrf_token: csrfToken,
            name: name,
            is_shared: sharedCheckbox.checked,
            positions: positions,
        };
        if (currentId !== null) body.id = currentId;

        try {
            const resp = await fetch('/tactics/speelwijzen/save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
            if (!resp.ok && resp.redirected) {
                alert('Sessie verlopen. Vernieuw de pagina.');
                return;
            }
            const text = await resp.text();
            let data;
            try { data = JSON.parse(text); } catch (_) {
                console.error('Server response:', resp.status, text.substring(0, 500));
                alert('Onverwachte server-response (status ' + resp.status + '). Controleer de console.');
                return;
            }
            if (!data.success) {
                alert(data.error || 'Fout bij opslaan.');
                return;
            }
            speelwijzen = data.speelwijzen || [];
            currentId = data.id;
            markClean();
            renderList();
            // Re-select the saved item
            loadSpeelwijze(currentId);
        } catch (err) {
            alert('Netwerkfout bij opslaan.');
        }
    });

    // --- Delete ---
    document.getElementById('btn-delete-speelwijze').addEventListener('click', async () => {
        if (currentId === null) return;
        if (!confirm('Weet je zeker dat je deze speelwijze wilt verwijderen?')) return;

        try {
            const resp = await fetch('/tactics/speelwijzen/delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf_token: csrfToken, id: currentId }),
            });
            const text = await resp.text();
            let data;
            try { data = JSON.parse(text); } catch (_) {
                console.error('Server response:', resp.status, text.substring(0, 500));
                alert('Onverwachte server-response. Controleer de console.');
                return;
            }
            if (!data.success) {
                alert(data.error || 'Fout bij verwijderen.');
                return;
            }
            speelwijzen = data.speelwijzen || [];
            currentId = null;
            markClean();
            // Show next available speelwijze, or reset to empty field
            if (speelwijzen.length > 0) {
                loadSpeelwijze(speelwijzen[0].id);
            } else {
                nameInput.value = '';
                sharedCheckbox.checked = false;
                positions = [];
                selectedPosIndex = null;
                posEditEl.style.display = 'none';
                setReadOnly(false);
                renderField();
            }
            renderList();
        } catch (err) {
            alert('Netwerkfout bij verwijderen.');
        }
    });

    // --- Helpers ---
    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }
});
