document.addEventListener('DOMContentLoaded', () => {
    const matchId = document.getElementById('match_id').value;
    const csrfToken = document.getElementById('csrf_token').value;
    const timerDisplay = document.getElementById('timer-display');
    const periodDisplay = document.getElementById('period-display');
    const timerBtn = document.getElementById('timer-btn');
    const scoreHomeDisplay = document.getElementById('score-home');
    const scoreAwayDisplay = document.getElementById('score-away');
    const modal = document.getElementById('action-modal');
    const actionForm = document.getElementById('action-form');
    const timelineList = document.getElementById('timeline-list');
    const timelineFilterControlsEl = document.getElementById('timeline-filter-controls');
    const liveFieldEl = document.getElementById('live-football-field');
    const liveBenchTokenListEl = document.getElementById('live-bench-token-list');
    const playerOutSelect = document.getElementById('player_out');
    const playerInSelect = document.getElementById('player_in');
    const fieldPlayersEl = document.getElementById('live-field-players');
    const fieldEmptyEl = document.getElementById('live-field-empty');
    const minutesSummaryContainerEl = document.getElementById('minutes-summary-container');
    const undoSubBtn = document.getElementById('undo-sub-btn');
    const orientationOverlay = document.getElementById('orientation-overlay');
    const viewportMeta = document.querySelector('meta[name="viewport"]');

    let state = parseJsonFromInput('initial_timer_state', {
        is_playing: false,
        current_period: 0,
        total_seconds: 0,
        total_minutes: 0,
        start_time: null
    });
    let liveState = parseJsonFromInput('initial_live_state', {
        period: 1,
        clock_seconds: 0,
        active_lineup: [],
        bench: [],
        minutes_summary: []
    });
    let scoreState = {
        home: Number.parseInt(scoreHomeDisplay ? scoreHomeDisplay.textContent : '0', 10) || 0,
        away: Number.parseInt(scoreAwayDisplay ? scoreAwayDisplay.textContent : '0', 10) || 0
    };
    let timelineEvents = parseJsonFromInput('initial_events', []);
    let timelineFilter = 'all';

    let timerInterval = null;
    let orientationRefreshTimeout = null;
    let dragPayload = null;
    let activeTouchDrag = null;
    let timerActionInFlight = false;
    let minutesSortState = {
        key: 'total',
        direction: 'desc'
    };

    const liveViewportContent = 'width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover';

    normalizeLiveState();
    normalizeTimelineState();
    markTimerStateSynced();

    function parseJsonFromInput(inputId, fallbackValue) {
        const el = document.getElementById(inputId);
        if (!el || !el.value) {
            return fallbackValue;
        }
        try {
            return JSON.parse(el.value);
        } catch (error) {
            return fallbackValue;
        }
    }

    function normalizeLiveState() {
        if (!liveState || typeof liveState !== 'object') {
            liveState = {};
        }
        liveState.period = Number.parseInt(liveState.period, 10) || 1;
        liveState.clock_seconds = Number.parseInt(liveState.clock_seconds, 10) || 0;
        if (!Array.isArray(liveState.active_lineup)) {
            liveState.active_lineup = [];
        }
        if (!Array.isArray(liveState.bench)) {
            liveState.bench = [];
        }
        if (!Array.isArray(liveState.minutes_summary)) {
            liveState.minutes_summary = [];
        }
    }

    function normalizeTimelineState() {
        if (!Array.isArray(timelineEvents)) {
            timelineEvents = [];
        }
        const allowedFilters = ['all', 'goals', 'subs'];
        if (!allowedFilters.includes(timelineFilter)) {
            timelineFilter = 'all';
        }
    }

    function enforceLiveViewport() {
        if (!viewportMeta) {
            return;
        }
        if (viewportMeta.getAttribute('content') !== liveViewportContent) {
            viewportMeta.setAttribute('content', liveViewportContent);
        }
    }

    function isLandscapeMode() {
        if (window.matchMedia) {
            return window.matchMedia('(orientation: landscape)').matches;
        }
        return window.innerWidth > window.innerHeight;
    }

    function shouldEnforcePortraitMode() {
        if (window.matchMedia && window.matchMedia('(pointer: coarse) and (max-width: 1024px)').matches) {
            return true;
        }
        return window.innerWidth <= 1024 && /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
    }

    function updateOrientationOverlay() {
        if (!orientationOverlay) {
            return;
        }
        if (!shouldEnforcePortraitMode()) {
            orientationOverlay.classList.remove('is-visible');
            orientationOverlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('live-landscape-blocked');
            return;
        }

        const landscape = isLandscapeMode();
        orientationOverlay.classList.toggle('is-visible', landscape);
        orientationOverlay.setAttribute('aria-hidden', landscape ? 'false' : 'true');
        document.body.classList.toggle('live-landscape-blocked', landscape);
    }

    async function lockPortraitOrientation() {
        if (!shouldEnforcePortraitMode()) {
            return;
        }
        if (!screen.orientation || typeof screen.orientation.lock !== 'function') {
            return;
        }
        try {
            await screen.orientation.lock('portrait');
        } catch (error) {
            // Browser can block orientation lock outside fullscreen/PWA.
        }
    }

    function refreshViewportAndOrientation() {
        if (orientationRefreshTimeout) {
            clearTimeout(orientationRefreshTimeout);
        }
        orientationRefreshTimeout = setTimeout(() => {
            enforceLiveViewport();
            updateOrientationOverlay();
            lockPortraitOrientation();
        }, 120);
    }

    function formatTime(seconds) {
        const m = Math.floor(seconds / 60);
        const s = Math.floor(seconds % 60);
        return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
    }

    function markTimerStateSynced() {
        state._client_synced_at = Math.floor(Date.now() / 1000);
    }

    function getDisplayedTotalSeconds() {
        const baseSeconds = Number.parseInt(state.total_seconds, 10) || 0;
        if (!state.is_playing) {
            return baseSeconds;
        }

        const now = Math.floor(Date.now() / 1000);
        const syncedAt = Number.parseInt(state._client_synced_at, 10) || now;
        const delta = Math.max(0, now - syncedAt);
        return baseSeconds + delta;
    }

    function updateTimerUI() {
        if (state.is_playing) {
            const totalSeconds = getDisplayedTotalSeconds();
            timerDisplay.textContent = formatTime(totalSeconds);
            timerBtn.textContent = 'Stop tijd';
            timerBtn.classList.remove('tb-button--primary');
            timerBtn.classList.add('tb-button--danger');
        } else {
            timerDisplay.textContent = formatTime(state.total_seconds);
            timerBtn.textContent = state.total_seconds > 0 ? 'Hervat tijd' : 'Start wedstrijd';
            timerBtn.classList.add('tb-button--primary');
            timerBtn.classList.remove('tb-button--danger');
        }

        periodDisplay.textContent = state.current_period > 0 ? `Periode ${state.current_period}` : 'Nog niet gestart';
    }

    function updateScoreUI() {
        if (scoreHomeDisplay) {
            scoreHomeDisplay.textContent = scoreState.home.toString();
        }
        if (scoreAwayDisplay) {
            scoreAwayDisplay.textContent = scoreState.away.toString();
        }
    }

    function startTicker() {
        if (timerInterval) {
            clearInterval(timerInterval);
        }
        if (state.is_playing) {
            timerInterval = setInterval(updateTimerUI, 1000);
        }
        updateTimerUI();
    }

    function getEffectivePeriod() {
        if (state.current_period && Number.parseInt(state.current_period, 10) > 0) {
            return Number.parseInt(state.current_period, 10);
        }
        if (liveState.period && Number.parseInt(liveState.period, 10) > 0) {
            return Number.parseInt(liveState.period, 10);
        }
        return 1;
    }

    function cleanEventDescription(rawDescription) {
        const description = String(rawDescription || '');
        return description.replace(/^\[\[sub:\d+\]\]\s*/, '').trim();
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function clampFieldPosition(value, fallback) {
        const parsed = Number.parseFloat(value);
        const candidate = Number.isFinite(parsed) ? parsed : fallback;
        return Math.min(98, Math.max(2, candidate));
    }

    function buildPlayerMarkerText(item) {
        const name = String(item.player_name || '').trim();
        if (!name) {
            return '?';
        }
        return name.charAt(0).toUpperCase();
    }

    function isGoalkeeperSlot(slotCode) {
        const normalized = String(slotCode || '').trim().toUpperCase();
        return normalized === 'GK' || normalized === 'K';
    }

    function buildPlayerTokenInnerHtml(item) {
        const markerText = escapeHtml(buildPlayerMarkerText(item));
        const playerName = escapeHtml(item.player_name || '');
        return `<div class="player-jersey"><svg viewBox="0 0 100 100" width="50" height="50"><path d="M15,30 L30,10 L70,10 L85,30 L75,40 L70,35 L70,90 L30,90 L30,35 L25,40 Z" fill="url(#striped-jersey)" stroke="white" stroke-width="2"></path><text x="50" y="65" font-family="Arial" font-size="30" fill="white" text-anchor="middle" font-weight="bold">${markerText}</text></svg></div><div class="player-name">${playerName}</div>`;
    }

    function renderFieldPlayers() {
        if (!fieldPlayersEl) {
            return;
        }

        fieldPlayersEl.innerHTML = '';
        const activeLineup = Array.isArray(liveState.active_lineup) ? liveState.active_lineup : [];

        if (fieldEmptyEl) {
            fieldEmptyEl.style.display = activeLineup.length > 0 ? 'none' : 'flex';
        }

        activeLineup.forEach((item, index) => {
            const fallbackX = 20 + ((index % 3) * 30);
            const fallbackY = 12 + (Math.floor(index / 3) * 14);
            const x = clampFieldPosition(item.position_x, fallbackX);
            const y = clampFieldPosition(item.position_y, fallbackY);

            const marker = document.createElement('div');
            const goalkeeperClass = isGoalkeeperSlot(item.slot_code) ? ' is-goalkeeper' : '';
            marker.className = `player-token on-field live-dnd-token${goalkeeperClass}`;
            marker.style.left = `${x}%`;
            marker.style.top = `${y}%`;
            marker.setAttribute('draggable', 'true');
            marker.dataset.source = 'field';
            marker.dataset.playerId = String(item.player_id || '');
            marker.dataset.slotCode = String(item.slot_code || '');
            marker.innerHTML = buildPlayerTokenInnerHtml(item);
            fieldPlayersEl.appendChild(marker);
        });
    }

    function renderBenchTokens() {
        if (!liveBenchTokenListEl) {
            return;
        }

        liveBenchTokenListEl.innerHTML = '';
        const benchPlayers = Array.isArray(liveState.bench) ? liveState.bench : [];
        if (benchPlayers.length === 0) {
            const placeholder = document.createElement('div');
            placeholder.className = 'drop-placeholder';
            placeholder.textContent = 'Geen bank';
            liveBenchTokenListEl.appendChild(placeholder);
            return;
        }

        benchPlayers.forEach((item) => {
            const token = document.createElement('div');
            token.className = 'player-token live-dnd-token';
            token.setAttribute('draggable', 'true');
            token.dataset.source = 'bench';
            token.dataset.playerId = String(item.player_id || '');
            token.innerHTML = buildPlayerTokenInnerHtml(item);
            liveBenchTokenListEl.appendChild(token);
        });
    }

    function formatMinutesValue(totalMinutesPlayed, totalSecondsPlayed) {
        const minutes = Number(totalMinutesPlayed);
        if (Number.isFinite(minutes) && minutes >= 0) {
            return `${minutes.toFixed(1)} min`;
        }
        const seconds = Number.parseInt(totalSecondsPlayed, 10) || 0;
        return `${(seconds / 60).toFixed(1)} min`;
    }

    function formatSecondsAsMinutes(seconds) {
        const totalSeconds = Number.parseInt(seconds, 10) || 0;
        return `${(totalSeconds / 60).toFixed(1)} min`;
    }

    function getPeriodSeconds(item, period) {
        if (!item || typeof item.seconds_per_period !== 'object') {
            return 0;
        }
        const perPeriod = item.seconds_per_period;
        return Number.parseInt(perPeriod[period] || perPeriod[String(period)] || 0, 10) || 0;
    }

    function getSortableMinutesValue(item, sortKey) {
        if (sortKey === 'player') {
            return String(item && item.player_name ? item.player_name : '').toLocaleLowerCase();
        }
        if (sortKey === 'total') {
            return Number.parseInt(item && item.total_seconds_played ? item.total_seconds_played : 0, 10) || 0;
        }
        if (sortKey.startsWith('q:')) {
            const period = Number.parseInt(sortKey.slice(2), 10) || 0;
            if (period <= 0) {
                return 0;
            }
            return getPeriodSeconds(item, period);
        }
        return 0;
    }

    function compareMinutesSummaryRows(a, b, sortKey, direction) {
        const aValue = getSortableMinutesValue(a, sortKey);
        const bValue = getSortableMinutesValue(b, sortKey);

        let cmp = 0;
        if (sortKey === 'player') {
            cmp = String(aValue).localeCompare(String(bValue), 'nl', { sensitivity: 'base' });
        } else {
            cmp = Number(aValue) - Number(bValue);
        }

        if (cmp === 0) {
            const nameCmp = String(a && a.player_name ? a.player_name : '').localeCompare(
                String(b && b.player_name ? b.player_name : ''),
                'nl',
                { sensitivity: 'base' }
            );
            return nameCmp;
        }

        return direction === 'asc' ? cmp : -cmp;
    }

    function setMinutesSort(sortKey) {
        if (minutesSortState.key === sortKey) {
            minutesSortState.direction = minutesSortState.direction === 'asc' ? 'desc' : 'asc';
            return;
        }

        minutesSortState.key = sortKey;
        minutesSortState.direction = sortKey === 'player' ? 'asc' : 'desc';
    }

    function buildMinutesSortHeader(label, sortKey) {
        const th = document.createElement('th');
        th.scope = 'col';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'live-minutes-sort-btn';
        button.textContent = label;
        button.addEventListener('click', () => {
            setMinutesSort(sortKey);
            renderLiveState();
        });

        if (minutesSortState.key === sortKey) {
            button.classList.add('is-active');
            button.classList.add(minutesSortState.direction === 'asc' ? 'is-asc' : 'is-desc');
            th.setAttribute('aria-sort', minutesSortState.direction === 'asc' ? 'ascending' : 'descending');
        } else {
            th.setAttribute('aria-sort', 'none');
        }

        th.appendChild(button);
        return th;
    }

    function applyLiveStateFromResponse(responseData) {
        if (Array.isArray(responseData.active_lineup)) {
            liveState.active_lineup = responseData.active_lineup;
        }
        if (Array.isArray(responseData.bench)) {
            liveState.bench = responseData.bench;
        }
        if (Array.isArray(responseData.minutes_summary)) {
            liveState.minutes_summary = responseData.minutes_summary;
        }
        if (typeof responseData.period !== 'undefined') {
            const parsedPeriod = Number.parseInt(responseData.period, 10);
            if (parsedPeriod > 0) {
                liveState.period = parsedPeriod;
            }
        }
        if (typeof responseData.clock_seconds !== 'undefined') {
            const parsedClock = Number.parseInt(responseData.clock_seconds, 10);
            if (parsedClock >= 0) {
                liveState.clock_seconds = parsedClock;
            }
        }
        normalizeLiveState();
        renderLiveState();

        if (Array.isArray(responseData.events)) {
            renderTimeline(responseData.events);
        }
    }

    function renderLiveState() {
        normalizeLiveState();
        renderFieldPlayers();
        renderBenchTokens();

        if (minutesSummaryContainerEl) {
            minutesSummaryContainerEl.innerHTML = '';
            if (liveState.minutes_summary.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'live-minutes-empty';
                empty.textContent = 'Nog geen speeltijdgegevens beschikbaar.';
                minutesSummaryContainerEl.appendChild(empty);
            } else {
                let maxPeriod = Number.parseInt(liveState.period, 10) || 1;
                liveState.minutes_summary.forEach((item) => {
                    if (!item || typeof item !== 'object' || typeof item.seconds_per_period !== 'object') {
                        return;
                    }
                    Object.keys(item.seconds_per_period).forEach((periodKey) => {
                        const parsedPeriod = Number.parseInt(periodKey, 10);
                        if (parsedPeriod > maxPeriod) {
                            maxPeriod = parsedPeriod;
                        }
                    });
                });
                if (maxPeriod < 1) {
                    maxPeriod = 1;
                }

                if (minutesSortState.key.startsWith('q:')) {
                    const requestedPeriod = Number.parseInt(minutesSortState.key.slice(2), 10) || 0;
                    if (requestedPeriod <= 0 || requestedPeriod > maxPeriod) {
                        minutesSortState = {
                            key: 'total',
                            direction: 'desc'
                        };
                    }
                }

                const sortedSummary = liveState.minutes_summary.slice().sort((a, b) => (
                    compareMinutesSummaryRows(a, b, minutesSortState.key, minutesSortState.direction)
                ));

                const wrapper = document.createElement('div');
                wrapper.className = 'live-minutes-summary-wrap';

                const table = document.createElement('table');
                table.className = 'live-minutes-table';

                const thead = document.createElement('thead');
                const headRow = document.createElement('tr');

                headRow.appendChild(buildMinutesSortHeader('Speler', 'player'));

                for (let period = 1; period <= maxPeriod; period += 1) {
                    headRow.appendChild(buildMinutesSortHeader(`Q${period}`, `q:${period}`));
                }

                headRow.appendChild(buildMinutesSortHeader('Totaal', 'total'));

                thead.appendChild(headRow);
                table.appendChild(thead);

                const tbody = document.createElement('tbody');
                sortedSummary.forEach((item) => {
                    const row = document.createElement('tr');

                    const playerCell = document.createElement('td');
                    playerCell.textContent = String(item.player_name || '');
                    row.appendChild(playerCell);

                    for (let period = 1; period <= maxPeriod; period += 1) {
                        const quarterCell = document.createElement('td');
                        quarterCell.textContent = formatSecondsAsMinutes(getPeriodSeconds(item, period));
                        row.appendChild(quarterCell);
                    }

                    const totalCell = document.createElement('td');
                    totalCell.textContent = formatMinutesValue(item.total_minutes_played, item.total_seconds_played);
                    row.appendChild(totalCell);

                    tbody.appendChild(row);
                });

                table.appendChild(tbody);
                wrapper.appendChild(table);
                minutesSummaryContainerEl.appendChild(wrapper);
            }
        }

        populateSubstitutionSelects();
    }

    function getClosestFieldToken(clientX, clientY) {
        if (!fieldPlayersEl) {
            return null;
        }

        const tokens = Array.from(fieldPlayersEl.querySelectorAll('.player-token.on-field'));
        if (tokens.length === 0) {
            return null;
        }

        let bestToken = null;
        let bestDistance = Number.POSITIVE_INFINITY;
        tokens.forEach((token) => {
            const rect = token.getBoundingClientRect();
            const centerX = rect.left + (rect.width / 2);
            const centerY = rect.top + (rect.height / 2);
            const dx = centerX - clientX;
            const dy = centerY - clientY;
            const distance = Math.sqrt((dx * dx) + (dy * dy));
            if (distance < bestDistance) {
                bestDistance = distance;
                bestToken = token;
            }
        });

        return bestToken;
    }

    async function performSubstitution(playerOutId, playerInId, slotCode) {
        if (playerOutId <= 0 || playerInId <= 0) {
            throw new Error('Ongeldige spelers voor wissel.');
        }

        const responseData = await requestJson('/matches/live/substitute', {
            match_id: Number.parseInt(matchId, 10),
            player_out_id: playerOutId,
            player_in_id: playerInId,
            slot_code: slotCode || '',
            csrf_token: csrfToken
        });
        applyLiveStateFromResponse(responseData);
    }

    function clearTouchDragState() {
        if (!activeTouchDrag) {
            return;
        }

        if (activeTouchDrag.sourceToken) {
            activeTouchDrag.sourceToken.style.opacity = '1';
        }
        if (activeTouchDrag.ghostEl && activeTouchDrag.ghostEl.parentNode) {
            activeTouchDrag.ghostEl.parentNode.removeChild(activeTouchDrag.ghostEl);
        }
        activeTouchDrag = null;
    }

    function populateSubstitutionSelects() {
        if (!playerOutSelect || !playerInSelect) {
            return;
        }

        playerOutSelect.innerHTML = '<option value="">-- Kies speler --</option>';
        liveState.active_lineup.forEach((item) => {
            const option = document.createElement('option');
            option.value = String(item.player_id);
            option.dataset.slotCode = String(item.slot_code || '');
            const numberLabel = typeof item.number === 'number' ? ` #${item.number}` : '';
            option.textContent = `${item.player_name}${numberLabel} (${item.slot_code})`;
            playerOutSelect.appendChild(option);
        });

        playerInSelect.innerHTML = '<option value="">-- Kies speler --</option>';
        liveState.bench.forEach((item) => {
            const option = document.createElement('option');
            option.value = String(item.player_id);
            const numberLabel = typeof item.number === 'number' ? ` #${item.number}` : '';
            option.textContent = `${item.player_name}${numberLabel}`;
            playerInSelect.appendChild(option);
        });
    }

    function isGoalEventType(eventType) {
        return eventType === 'goal' || eventType === 'goal_unknown';
    }

    function matchesTimelineFilter(eventType) {
        if (eventType === 'whistle') {
            return false;
        }
        if (timelineFilter === 'all') {
            return true;
        }
        if (timelineFilter === 'goals') {
            return isGoalEventType(eventType);
        }
        if (timelineFilter === 'subs') {
            return eventType === 'sub';
        }
        return true;
    }

    function updateTimelineFilterButtons() {
        if (!timelineFilterControlsEl) {
            return;
        }

        const buttons = Array.from(timelineFilterControlsEl.querySelectorAll('.timeline-filter-btn'));
        buttons.forEach((button) => {
            const buttonFilter = String(button.dataset.filter || 'all');
            const isActive = buttonFilter === timelineFilter;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function renderTimeline(events) {
        if (!timelineList) {
            return;
        }

        if (Array.isArray(events)) {
            timelineEvents = events;
        }
        normalizeTimelineState();
        updateTimelineFilterButtons();

        timelineList.innerHTML = '';
        const visibleEvents = timelineEvents.filter((event) => (
            matchesTimelineFilter(String(event && event.type ? event.type : ''))
        ));

        if (visibleEvents.length === 0) {
            const empty = document.createElement('li');
            empty.className = 'live-timeline-empty';
            empty.textContent = 'Geen gebeurtenissen voor dit filter.';
            timelineList.appendChild(empty);
            return;
        }

        visibleEvents.forEach((event) => {
            const eventType = String(event && event.type ? event.type : '');
            if (eventType === 'whistle') {
                return;
            }

            const li = document.createElement('li');
            li.className = 'tb-live-timeline-item';

            let label = '🔹 Gebeurtenis';
            if (eventType === 'goal') {
                label = event.player_id ? '⚽ Doelpunt' : '⚽ Tegendoelpunt';
            }
            if (eventType === 'goal_unknown') {
                label = '⚽ Doelpunt (Overig)';
            }
            if (eventType === 'card_yellow') {
                label = '🟨 Gele kaart';
            }
            if (eventType === 'card_red') {
                label = '🟥 Rode kaart';
            }
            if (eventType === 'sub') {
                label = '🔄 Wissel';
            }

            const description = cleanEventDescription(event.description);
            const descriptionText = description ? ` (${escapeHtml(description)})` : '';
            const playerText = event.player_name ? ` door <strong>${escapeHtml(event.player_name)}</strong>` : '';
            const minuteValue = Number.parseInt(event.minute, 10) || 0;
            li.innerHTML = `<strong>${minuteValue}'</strong> ${label}${playerText}${descriptionText}`;
            timelineList.appendChild(li);
        });
    }

    async function requestJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(payload)
        });

        const responseText = await response.text();
        let responseData = null;
        try {
            responseData = responseText ? JSON.parse(responseText) : {};
        } catch (error) {
            responseData = {};
        }

        if (!response.ok || !responseData || responseData.success === false) {
            throw new Error((responseData && responseData.error) ? responseData.error : 'Onbekende fout');
        }

        return responseData;
    }

    async function handleTimerToggle() {
        if (timerActionInFlight) {
            return;
        }

        timerActionInFlight = true;
        timerBtn.disabled = true;
        const action = state.is_playing ? 'stop' : 'start';
        try {
            const responseData = await requestJson('/matches/timer-action', {
                match_id: matchId,
                action,
                csrf_token: csrfToken
            });
            if (responseData.timerState) {
                state = responseData.timerState;
                markTimerStateSynced();
                startTicker();

                const effectivePeriod = getEffectivePeriod();
                if (liveState.period !== effectivePeriod) {
                    liveState.period = effectivePeriod;
                    renderLiveState();
                }
            }
        } catch (error) {
            alert(`Fout: ${error.message}`);
        } finally {
            timerActionInFlight = false;
            timerBtn.disabled = false;
        }
    }

    async function handleUndoSubstitution() {
        try {
            const responseData = await requestJson('/matches/live/substitute/undo', {
                match_id: Number.parseInt(matchId, 10),
                csrf_token: csrfToken
            });
            applyLiveStateFromResponse(responseData);
        } catch (error) {
            alert(`Fout: ${error.message}`);
        }
    }

    function getCurrentMinuteDisplay() {
        const currentMinute = Math.floor(getDisplayedTotalSeconds() / 60);
        return currentMinute + 1;
    }

    const modalTypeSelect = document.getElementById('modal-type-select');

    function updateModalForType(type) {
        const typeLabels = {
            goal: 'Doelpunt toevoegen',
            card: 'Kaart registreren',
            sub: 'Wissel doorvoeren',
            other: 'Notitie maken'
        };
        document.getElementById('modal-title').textContent = typeLabels[type] || 'Actie toevoegen';

        const playerSelectGroup = document.getElementById('player-select-group');
        const subGroup = document.getElementById('sub-group');

        if (type === 'sub') {
            playerSelectGroup.hidden = true;
            subGroup.hidden = false;
            populateSubstitutionSelects();
        } else {
            playerSelectGroup.hidden = type === 'other';
            subGroup.hidden = true;
        }
    }

    if (modalTypeSelect) {
        modalTypeSelect.addEventListener('change', () => {
            updateModalForType(modalTypeSelect.value);
        });
    }

    window.openActionModal = function openActionModal(type) {
        if (modalTypeSelect) {
            modalTypeSelect.value = type || 'goal';
        }
        updateModalForType(type || 'goal');
        document.getElementById('modal-minute').value = getCurrentMinuteDisplay();
        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');
    };

    window.closeModal = function closeModal() {
        modal.classList.remove('is-visible');
        modal.setAttribute('aria-hidden', 'true');
        actionForm.reset();
        populateSubstitutionSelects();
    };

    if (modal) {
        modal.addEventListener('click', (event) => {
            if (event.target.closest('[data-close-modal]')) {
                closeModal();
            }
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && modal.classList.contains('is-visible')) {
            closeModal();
        }
    });

    actionForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = new FormData(actionForm);
        const data = Object.fromEntries(formData.entries());

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

        if (data.type === 'card') {
            data.type = 'card_yellow';
            if (confirm('Is het een rode kaart? Klik OK voor Rood, Annuleren voor Geel.')) {
                data.type = 'card_red';
            }
        }

        if (data.type === 'sub') {
            const outPlayerId = Number.parseInt(playerOutSelect.value, 10) || 0;
            const inPlayerId = Number.parseInt(playerInSelect.value, 10) || 0;
            const selectedOutOption = playerOutSelect.options[playerOutSelect.selectedIndex];
            const slotCode = selectedOutOption ? String(selectedOutOption.dataset.slotCode || '') : '';

            if (outPlayerId <= 0 || inPlayerId <= 0) {
                alert('Kies een speler UIT en een speler IN.');
                return;
            }

            try {
                await performSubstitution(outPlayerId, inPlayerId, slotCode);
                closeModal();
            } catch (error) {
                alert(`Fout: ${error.message}`);
            }
            return;
        }

        data.action = 'add';
        data.period = getEffectivePeriod();
        data.csrf_token = csrfToken;

        try {
            const responseData = await requestJson('/matches/add-event', data);
            closeModal();
            if (typeof responseData.score_home !== 'undefined' && typeof responseData.score_away !== 'undefined') {
                scoreState.home = Number.parseInt(responseData.score_home, 10) || 0;
                scoreState.away = Number.parseInt(responseData.score_away, 10) || 0;
                updateScoreUI();
            }
            if (Array.isArray(responseData.events)) {
                renderTimeline(responseData.events);
            }
        } catch (error) {
            alert(`Fout: ${error.message}`);
        }
    });

    timerBtn.addEventListener('click', handleTimerToggle);
    if (undoSubBtn) {
        undoSubBtn.addEventListener('click', handleUndoSubstitution);
    }
    if (timelineFilterControlsEl) {
        timelineFilterControlsEl.addEventListener('click', (event) => {
            const target = event.target.closest('.timeline-filter-btn');
            if (!target) {
                return;
            }

            const nextFilter = String(target.dataset.filter || 'all');
            if (nextFilter === timelineFilter) {
                return;
            }

            timelineFilter = nextFilter;
            renderTimeline();
        });
    }

    document.addEventListener('dragstart', (event) => {
        const token = event.target.closest('.live-dnd-token');
        if (!token) {
            return;
        }

        const source = String(token.dataset.source || '');
        const playerId = Number.parseInt(token.dataset.playerId, 10) || 0;
        if (!source || playerId <= 0 || !event.dataTransfer) {
            return;
        }

        dragPayload = {
            source,
            playerId,
            slotCode: String(token.dataset.slotCode || '')
        };

        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(playerId));
        token.style.opacity = '0.5';
    });

    document.addEventListener('dragend', (event) => {
        const token = event.target.closest('.live-dnd-token');
        if (token) {
            token.style.opacity = '1';
        }
        dragPayload = null;
    });

    if (liveFieldEl) {
        liveFieldEl.addEventListener('dragover', (event) => {
            if (!dragPayload || (dragPayload.source !== 'bench' && dragPayload.source !== 'field')) {
                return;
            }
            event.preventDefault();
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'move';
            }
        });

        liveFieldEl.addEventListener('drop', async (event) => {
            if (!dragPayload || (dragPayload.source !== 'bench' && dragPayload.source !== 'field')) {
                return;
            }
            event.preventDefault();

            const dropTarget = event.target.closest('.player-token.on-field');
            const targetToken = dropTarget || getClosestFieldToken(event.clientX, event.clientY);
            if (!targetToken) {
                return;
            }

            try {
                if (dragPayload.source === 'bench') {
                    const playerOutId = Number.parseInt(targetToken.dataset.playerId, 10) || 0;
                    const playerInId = dragPayload.playerId;
                    const slotCode = String(targetToken.dataset.slotCode || '');
                    await performSubstitution(playerOutId, playerInId, slotCode);
                    return;
                }

                const playerOutId = dragPayload.playerId;
                const playerInId = Number.parseInt(targetToken.dataset.playerId, 10) || 0;
                const slotCode = dragPayload.slotCode || '';
                if (playerOutId <= 0 || playerInId <= 0 || playerOutId === playerInId) {
                    return;
                }
                await performSubstitution(playerOutId, playerInId, slotCode);
            } catch (error) {
                alert(`Fout: ${error.message}`);
            }
        });
    }

    if (liveBenchTokenListEl) {
        liveBenchTokenListEl.addEventListener('dragover', (event) => {
            if (!dragPayload || dragPayload.source !== 'field') {
                return;
            }
            const tokenTarget = event.target.closest('.player-token[data-source="bench"]');
            if (!tokenTarget) {
                return;
            }
            event.preventDefault();
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'move';
            }
        });

        liveBenchTokenListEl.addEventListener('drop', async (event) => {
            if (!dragPayload || dragPayload.source !== 'field') {
                return;
            }
            const benchToken = event.target.closest('.player-token[data-source="bench"]');
            if (!benchToken) {
                return;
            }
            event.preventDefault();

            const playerOutId = dragPayload.playerId;
            const playerInId = Number.parseInt(benchToken.dataset.playerId, 10) || 0;
            const slotCode = dragPayload.slotCode || '';
            try {
                await performSubstitution(playerOutId, playerInId, slotCode);
            } catch (error) {
                alert(`Fout: ${error.message}`);
            }
        });
    }

    document.addEventListener('touchstart', (event) => {
        const token = event.target.closest('.live-dnd-token');
        if (!token) {
            return;
        }

        const source = String(token.dataset.source || '');
        const playerId = Number.parseInt(token.dataset.playerId, 10) || 0;
        if (!source || playerId <= 0) {
            return;
        }

        event.preventDefault();
        const touch = event.touches[0];
        if (!touch) {
            return;
        }

        const rect = token.getBoundingClientRect();
        const ghost = token.cloneNode(true);
        ghost.style.position = 'fixed';
        ghost.style.left = `${rect.left}px`;
        ghost.style.top = `${rect.top}px`;
        ghost.style.width = `${rect.width}px`;
        ghost.style.zIndex = '5000';
        ghost.style.pointerEvents = 'none';
        ghost.style.opacity = '0.9';
        ghost.style.transform = 'scale(1.08)';
        document.body.appendChild(ghost);

        token.style.opacity = '0.3';
        activeTouchDrag = {
            source,
            playerId,
            slotCode: String(token.dataset.slotCode || ''),
            sourceToken: token,
            ghostEl: ghost,
            offsetX: touch.clientX - rect.left,
            offsetY: touch.clientY - rect.top
        };
    }, { passive: false });

    document.addEventListener('touchmove', (event) => {
        if (!activeTouchDrag) {
            return;
        }
        event.preventDefault();
        const touch = event.touches[0];
        if (!touch) {
            return;
        }

        activeTouchDrag.ghostEl.style.left = `${touch.clientX - activeTouchDrag.offsetX}px`;
        activeTouchDrag.ghostEl.style.top = `${touch.clientY - activeTouchDrag.offsetY}px`;
    }, { passive: false });

    document.addEventListener('touchend', async (event) => {
        if (!activeTouchDrag) {
            return;
        }

        const touch = event.changedTouches[0];
        if (!touch) {
            clearTouchDragState();
            return;
        }

        const dropElements = document.elementsFromPoint(touch.clientX, touch.clientY);
        try {
            if (activeTouchDrag.source === 'bench') {
                const dropTarget = dropElements.find((element) => element.classList && element.classList.contains('on-field') && element.dataset && element.dataset.source === 'field');
                const targetToken = dropTarget || getClosestFieldToken(touch.clientX, touch.clientY);
                if (targetToken) {
                    const playerOutId = Number.parseInt(targetToken.dataset.playerId, 10) || 0;
                    const slotCode = String(targetToken.dataset.slotCode || '');
                    await performSubstitution(playerOutId, activeTouchDrag.playerId, slotCode);
                }
            } else if (activeTouchDrag.source === 'field') {
                const fieldToken = dropElements.find((element) => (
                    element.classList &&
                    element.classList.contains('on-field') &&
                    element.dataset &&
                    element.dataset.source === 'field'
                ));
                if (fieldToken) {
                    const playerOutId = activeTouchDrag.playerId;
                    const playerInId = Number.parseInt(fieldToken.dataset.playerId, 10) || 0;
                    if (playerOutId > 0 && playerInId > 0 && playerOutId !== playerInId) {
                        await performSubstitution(playerOutId, playerInId, activeTouchDrag.slotCode || '');
                    }
                    return;
                }

                const benchToken = dropElements.find((element) => element.classList && element.classList.contains('player-token') && element.dataset && element.dataset.source === 'bench');
                if (benchToken) {
                    const playerInId = Number.parseInt(benchToken.dataset.playerId, 10) || 0;
                    await performSubstitution(activeTouchDrag.playerId, playerInId, activeTouchDrag.slotCode || '');
                }
            }
        } catch (error) {
            alert(`Fout: ${error.message}`);
        } finally {
            clearTouchDragState();
        }
    }, { passive: false });

    document.addEventListener('touchcancel', () => {
        clearTouchDragState();
    }, { passive: false });

    enforceLiveViewport();
    updateOrientationOverlay();
    lockPortraitOrientation();
    window.addEventListener('orientationchange', refreshViewportAndOrientation);
    window.addEventListener('resize', refreshViewportAndOrientation);
    window.addEventListener('pageshow', refreshViewportAndOrientation);
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', refreshViewportAndOrientation);
    }
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            refreshViewportAndOrientation();
        }
    });

    // ── Voice command (push-to-talk) ──

    const voiceBtn = document.getElementById('voice-btn');
    const voiceOverlay = document.getElementById('voice-overlay');
    const voiceStatusIcon = document.getElementById('voice-status-icon');
    const voiceIconMic = document.getElementById('voice-icon-mic');
    const voiceSpinner = document.getElementById('voice-spinner');
    const voiceStatusText = document.getElementById('voice-status-text');
    const voiceCancelBtn = document.getElementById('voice-cancel-btn');
    const voiceConfirmSheet = document.getElementById('voice-confirm-sheet');
    const voiceTranscriptEl = document.getElementById('voice-transcript');
    const voiceEventsListEl = document.getElementById('voice-events-list');
    const voiceAcceptBtn = document.getElementById('voice-accept-btn');
    const voiceRejectBtn = document.getElementById('voice-reject-btn');
    const voiceToast = document.getElementById('voice-toast');

    let voiceMediaRecorder = null;
    let voiceAudioChunks = [];
    let voiceRecording = false;
    let voiceProcessing = false;
    let voicePendingResult = null;
    let voiceToastTimeout = null;

    function showVoiceToast(message, type, durationMs) {
        if (!voiceToast) {
            return;
        }
        clearTimeout(voiceToastTimeout);
        voiceToast.textContent = message;
        voiceToast.className = 'voice-toast is-visible' + (type ? ' is-' + type : '');
        voiceToastTimeout = setTimeout(() => {
            voiceToast.classList.remove('is-visible');
        }, durationMs || 3000);
    }

    function setVoiceOverlayState(visibleState) {
        if (!voiceOverlay) {
            return;
        }

        if (visibleState === 'hidden') {
            voiceOverlay.classList.remove('is-visible');
            voiceOverlay.setAttribute('aria-hidden', 'true');
            return;
        }

        voiceOverlay.classList.add('is-visible');
        voiceOverlay.setAttribute('aria-hidden', 'false');

        voiceStatusIcon.className = 'voice-status-icon';
        voiceIconMic.hidden = true;
        voiceSpinner.hidden = true;
        voiceCancelBtn.hidden = true;

        if (visibleState === 'recording') {
            voiceStatusIcon.classList.add('is-recording');
            voiceIconMic.hidden = false;
            voiceStatusText.textContent = 'Opname loopt... Laat los om te stoppen';
        } else if (visibleState === 'processing') {
            voiceStatusIcon.classList.add('is-processing');
            voiceSpinner.hidden = false;
            voiceCancelBtn.hidden = false;
            voiceStatusText.textContent = 'Verwerken...';
        }
    }

    function setVoiceConfirmSheet(visible) {
        if (!voiceConfirmSheet) {
            return;
        }
        voiceConfirmSheet.classList.toggle('is-visible', visible);
        voiceConfirmSheet.setAttribute('aria-hidden', visible ? 'false' : 'true');
    }

    function getEventIcon(type) {
        const icons = {
            substitution: '🔄',
            goal: '⚽',
            card: '🟨',
            chance: '🎯',
            note: '📝'
        };
        return icons[type] || '🔹';
    }

    function getEventLabel(event) {
        const type = String(event.type || '');
        if (type === 'substitution') {
            const outName = escapeHtml(event.player_out_name || '?');
            const inName = escapeHtml(event.player_in_name || '?');
            return `Wissel: ${outName} eruit, ${inName} erin`;
        }
        if (type === 'goal') {
            const name = escapeHtml(event.player_name || '?');
            const assist = event.assist_player_name ? ` (assist: ${escapeHtml(event.assist_player_name)})` : '';
            return `Doelpunt: ${name}${assist}`;
        }
        if (type === 'card') {
            const cardType = event.card_type === 'red' ? 'Rode' : 'Gele';
            return `${cardType} kaart: ${escapeHtml(event.player_name || '?')}`;
        }
        if (type === 'chance') {
            return `Kans: ${escapeHtml(event.player_name || '?')}${event.detail ? ' - ' + escapeHtml(event.detail) : ''}`;
        }
        if (type === 'note') {
            return `Notitie: ${escapeHtml(event.text || '')}`;
        }
        return `Event: ${type}`;
    }

    function getConfidenceClass(confidence) {
        const c = Number.parseFloat(confidence) || 0;
        if (c >= 0.90) {
            return 'is-high';
        }
        if (c >= 0.75) {
            return 'is-medium';
        }
        return 'is-low';
    }

    function renderVoiceEvents(events) {
        if (!voiceEventsListEl) {
            return;
        }
        voiceEventsListEl.innerHTML = '';

        if (!Array.isArray(events) || events.length === 0) {
            voiceEventsListEl.innerHTML = '<p style="color:#999;">Geen events herkend.</p>';
            return;
        }

        events.forEach((event) => {
            const card = document.createElement('div');
            card.className = 'voice-event-card';

            const valid = event.valid !== false;
            if (!valid) {
                card.style.opacity = '0.5';
                card.style.borderColor = '#ef9a9a';
            }

            const confidence = Number.parseFloat(event.confidence) || 0;

            card.innerHTML = `<div class="voice-event-icon">${getEventIcon(event.type)}</div>`
                + `<div class="voice-event-details">${getEventLabel(event)}${!valid ? '<br><small style="color:#c62828;">' + escapeHtml(event.validation_error || 'Ongeldig') + '</small>' : ''}</div>`
                + `<div class="voice-event-confidence ${getConfidenceClass(confidence)}">${Math.round(confidence * 100)}%</div>`;

            voiceEventsListEl.appendChild(card);
        });
    }

    async function startVoiceRecording() {
        if (voiceRecording || voiceProcessing) {
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            voiceAudioChunks = [];

            let mimeType = 'audio/webm';
            if (MediaRecorder.isTypeSupported('audio/mp4')) {
                mimeType = 'audio/mp4';
            } else if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                mimeType = 'audio/webm;codecs=opus';
            }

            voiceMediaRecorder = new MediaRecorder(stream, { mimeType });

            voiceMediaRecorder.addEventListener('dataavailable', (e) => {
                if (e.data && e.data.size > 0) {
                    voiceAudioChunks.push(e.data);
                }
            });

            voiceMediaRecorder.addEventListener('stop', () => {
                stream.getTracks().forEach((t) => t.stop());
                if (voiceAudioChunks.length > 0 && voiceRecording) {
                    voiceRecording = false;
                    const blob = new Blob(voiceAudioChunks, { type: mimeType });
                    sendVoiceAudio(blob);
                } else {
                    voiceRecording = false;
                    setVoiceOverlayState('hidden');
                }
            });

            voiceMediaRecorder.start();
            voiceRecording = true;
            setVoiceOverlayState('recording');
            if (voiceBtn) {
                voiceBtn.classList.add('is-recording');
            }
        } catch (err) {
            showVoiceToast('Microfoon niet beschikbaar: ' + err.message, 'error', 4000);
        }
    }

    function stopVoiceRecording() {
        if (!voiceMediaRecorder || voiceMediaRecorder.state !== 'recording') {
            voiceRecording = false;
            setVoiceOverlayState('hidden');
            return;
        }
        voiceMediaRecorder.stop();
        if (voiceBtn) {
            voiceBtn.classList.remove('is-recording');
        }
    }

    function cancelVoiceProcessing() {
        voiceProcessing = false;
        voicePendingResult = null;
        setVoiceOverlayState('hidden');
    }

    async function sendVoiceAudio(blob) {
        voiceProcessing = true;
        setVoiceOverlayState('processing');

        const formData = new FormData();
        formData.append('match_id', matchId);
        formData.append('csrf_token', csrfToken);
        formData.append('audio_file', blob, 'recording.webm');

        try {
            const response = await fetch('/matches/live/voice-command', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken },
                body: formData
            });

            const data = await response.json();
            voiceProcessing = false;
            setVoiceOverlayState('hidden');

            if (!data.success) {
                showVoiceToast(data.error || 'Spraakherkenning mislukt.', 'error', 4000);
                return;
            }

            if (!Array.isArray(data.events) || data.events.length === 0) {
                showVoiceToast('Geen events herkend in opname.', 'error', 3000);
                return;
            }

            const validEvents = data.events.filter((e) => e.valid !== false);
            if (validEvents.length === 0) {
                showVoiceToast('Alle herkende events zijn ongeldig.', 'error', 3000);
                return;
            }

            voicePendingResult = {
                voiceLogId: data.voice_log_id,
                transcript: data.transcript || '',
                events: data.events,
                requiresConfirmation: data.requires_confirmation
            };

            showVoiceConfirmation();
        } catch (err) {
            voiceProcessing = false;
            setVoiceOverlayState('hidden');
            showVoiceToast('Netwerkfout: ' + err.message, 'error', 4000);
        }
    }

    function showVoiceConfirmation() {
        if (!voicePendingResult) {
            return;
        }

        if (voiceTranscriptEl) {
            voiceTranscriptEl.textContent = voicePendingResult.transcript
                ? `"${voicePendingResult.transcript}"`
                : '';
        }

        renderVoiceEvents(voicePendingResult.events);
        setVoiceConfirmSheet(true);
    }

    async function confirmVoiceEvents() {
        if (!voicePendingResult) {
            return;
        }

        const validEvents = voicePendingResult.events.filter((e) => e.valid !== false);
        if (validEvents.length === 0) {
            showVoiceToast('Geen geldige events om te bevestigen.', 'error', 3000);
            setVoiceConfirmSheet(false);
            voicePendingResult = null;
            return;
        }

        setVoiceConfirmSheet(false);

        try {
            const responseData = await requestJson('/matches/live/voice-command/confirm', {
                match_id: Number.parseInt(matchId, 10),
                voice_log_id: voicePendingResult.voiceLogId,
                events: validEvents,
                csrf_token: csrfToken
            });

            applyLiveStateFromResponse(responseData);

            if (typeof responseData.score_home !== 'undefined' && typeof responseData.score_away !== 'undefined') {
                scoreState.home = Number.parseInt(responseData.score_home, 10) || 0;
                scoreState.away = Number.parseInt(responseData.score_away, 10) || 0;
                updateScoreUI();
            }

            const count = Array.isArray(responseData.applied_events) ? responseData.applied_events.length : validEvents.length;
            showVoiceToast(`${count} event${count !== 1 ? 's' : ''} toegepast`, 'success', 3000);

            if (responseData.warning) {
                setTimeout(() => {
                    showVoiceToast(responseData.warning, 'error', 4000);
                }, 3200);
            }
        } catch (err) {
            showVoiceToast('Bevestiging mislukt: ' + err.message, 'error', 4000);
        }

        voicePendingResult = null;
    }

    function rejectVoiceEvents() {
        setVoiceConfirmSheet(false);
        voicePendingResult = null;
        showVoiceToast('Events verworpen', '', 2000);
    }

    // Push-to-talk: press and hold
    if (voiceBtn) {
        let voicePressTimer = null;
        let voiceStarted = false;

        function handleVoiceDown(e) {
            e.preventDefault();
            voiceStarted = false;
            voicePressTimer = setTimeout(() => {
                voiceStarted = true;
                startVoiceRecording();
            }, 150);
        }

        function handleVoiceUp(e) {
            e.preventDefault();
            clearTimeout(voicePressTimer);
            if (voiceStarted && voiceRecording) {
                stopVoiceRecording();
            } else if (!voiceStarted && !voiceRecording && !voiceProcessing) {
                // Short tap: toggle recording with tap-to-start / tap-to-stop
                startVoiceRecording();
            }
        }

        voiceBtn.addEventListener('mousedown', handleVoiceDown);
        voiceBtn.addEventListener('mouseup', handleVoiceUp);
        voiceBtn.addEventListener('mouseleave', () => {
            clearTimeout(voicePressTimer);
        });

        voiceBtn.addEventListener('touchstart', handleVoiceDown, { passive: false });
        voiceBtn.addEventListener('touchend', handleVoiceUp, { passive: false });
        voiceBtn.addEventListener('touchcancel', () => {
            clearTimeout(voicePressTimer);
            if (voiceRecording) {
                stopVoiceRecording();
            }
        }, { passive: false });
    }

    // Tap overlay while recording to stop
    if (voiceOverlay) {
        voiceOverlay.addEventListener('click', () => {
            if (voiceRecording) {
                stopVoiceRecording();
            }
        });
    }

    if (voiceCancelBtn) {
        voiceCancelBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            cancelVoiceProcessing();
        });
    }

    if (voiceAcceptBtn) {
        voiceAcceptBtn.addEventListener('click', confirmVoiceEvents);
    }

    if (voiceRejectBtn) {
        voiceRejectBtn.addEventListener('click', rejectVoiceEvents);
    }

    if (voiceConfirmSheet) {
        const backdrop = voiceConfirmSheet.querySelector('.voice-confirm-sheet-backdrop');
        if (backdrop) {
            backdrop.addEventListener('click', rejectVoiceEvents);
        }
    }

    startTicker();
    updateScoreUI();
    renderLiveState();
    renderTimeline(timelineEvents);
});
