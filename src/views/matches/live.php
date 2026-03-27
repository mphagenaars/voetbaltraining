<div id="live-match-root" class="container" style="max-width: 600px; margin: 0 auto; padding-top: 1rem;">
    <link rel="stylesheet" href="/css/match-view.css?v=<?= time() ?>">

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

    <!-- Data for JS -->
    <input type="hidden" id="match_id" value="<?= $match['id'] ?>">
    <input type="hidden" id="csrf_token" value="<?= Csrf::getToken() ?>">
    <input type="hidden" id="initial_timer_state" value="<?= e(json_encode($timerState)) ?>">
    <input type="hidden" id="initial_live_state" value="<?= e(json_encode($liveState ?? [])) ?>">
    <input type="hidden" id="initial_events" value="<?= e(json_encode($events ?? [])) ?>">

    <div class="app-bar" style="margin-bottom: 1rem;">
         <div class="app-bar-start">
            <a href="/matches/view?id=<?= $match['id'] ?>" class="btn-icon-round" title="Terug">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </a>
            <h1 class="app-bar-title">Live: <?= e($match['opponent']) ?></h1>
        </div>
    </div>

    <!-- Timer Section -->
    <div class="card live-timer-card" style="text-align: center; margin-bottom: 1rem; padding: 2rem 1rem;">
        <h2 id="period-display" style="font-size: 1.2rem; color: #666; margin-bottom: 0.5rem;">
            <?= $timerState['current_period'] > 0 ? "Periode " . $timerState['current_period'] : "Nog niet gestart" ?>
        </h2>
        <div id="timer-display" style="font-size: 4rem; font-weight: bold; font-family: monospace; line-height: 1;">
            <?= sprintf("%02d:00", $timerState['total_minutes']) ?>
        </div>
        <div class="live-scoreboard" aria-live="polite">
            <span class="live-score-team-value" id="score-home"><?= (int)$match['score_home'] ?></span>
            <div class="live-score-separator" aria-hidden="true">-</div>
            <span class="live-score-team-value" id="score-away"><?= (int)$match['score_away'] ?></span>
        </div>
        <div style="margin-top: 1.5rem;">
            <button id="timer-btn" class="btn btn-primary" style="font-size: 1.2rem; padding: 0.8rem 2rem; min-width: 150px;">
                Start
            </button>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card" style="margin-bottom: 1rem;">
        <h3>Snelle Actie</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <button class="action-btn goal-btn" onclick="openActionModal('goal')" style="background-color: #4caf50; color: white; padding: 1.5rem; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: bold; display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
                <span style="font-size: 2rem;">⚽</span>
                Doelpunt
            </button>
            <button class="action-btn note-btn" onclick="openActionModal('other')" style="background-color: #9e9e9e; color: white; padding: 1.5rem; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: bold; display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
                <span style="font-size: 2rem;">📝</span>
                Notitie
            </button>
        </div>
    </div>

    <div class="card" style="margin-bottom: 1rem;">
        <h3 style="margin-top: 0; margin-bottom: 0.75rem;">Actieve Opstelling</h3>
        <div class="lineup-editor live-lineup-editor">
            <div class="field-container live-field-container">
                <div id="live-football-field" class="football-field" aria-label="Posities op het veld">
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
                    <div id="live-field-players"></div>
                    <div id="live-field-empty" class="live-field-empty">Geen actieve opstelling</div>
                </div>
            </div>
            <div class="bench-container live-bench-container">
                <h4 style="margin-top: 0; margin-bottom: 0.45rem; font-size: 0.95rem;">Bank</h4>
                <div id="live-bench-token-list" class="players-list"></div>
                <p class="live-drag-hint">Sleep bank naar veld om te wisselen, of veld op veld om van positie te wisselen.</p>
            </div>

        </div>
        <div style="margin-top: 0.75rem;">
            <button id="undo-sub-btn" type="button" class="btn btn-secondary" style="width: 100%;">
                Undo laatste wissel
            </button>
        </div>
    </div>

    <div class="card" style="margin-bottom: 1rem;">
        <h3 style="margin-bottom: 0.6rem;">Speeltijd (live)</h3>
        <div id="minutes-summary-container"></div>
    </div>

    <!-- Last Events -->
    <div class="card">
        <h3>Recente Gebeurtenissen</h3>
        <div id="timeline-filter-controls" class="live-timeline-filters" role="group" aria-label="Filter gebeurtenissen">
            <button type="button" class="timeline-filter-btn is-active" data-filter="all" aria-pressed="true">
                📋 Alles
            </button>
            <button type="button" class="timeline-filter-btn" data-filter="goals" aria-pressed="false">
                ⚽ Doelpunten
            </button>
            <button type="button" class="timeline-filter-btn" data-filter="subs" aria-pressed="false">
                🔄 Wissels
            </button>
        </div>
        <ul id="timeline-list" class="timeline" style="list-style: none; padding: 0;">
            <!-- JS populates timeline from initial_events -->
        </ul>
    </div>
</div>

<!-- Modal -->
<div id="action-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 90%; max-width: 400px; max-height: 90vh; overflow-y: auto;">
        <h3 id="modal-title">Actie Toevoegen</h3>
        <form id="action-form">
            <input type="hidden" name="type" id="modal-type">
            <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
            
            <div class="form-group">
                <label>Minuut (automatisch)</label>
                <input type="number" name="minute" id="modal-minute" class="form-control" style="width: 100%;">
            </div>

            <div class="form-group" id="player-select-group">
                <label>Speler (optioneel)</label>
                <select name="player_id" class="form-control" style="width: 100%; padding: 0.5rem;">
                    <option value="">-- Kies speler --</option>
                    <?php foreach ($players as $player): ?>
                        <option value="<?= $player['id'] ?>"><?= e($player['name']) ?></option>
                    <?php endforeach; ?>
                    <option value="unknown">Overig (Eigen doelpunt tegenstander)</option>
                    <option value="opponent">Tegendoelpunt</option>
                </select>
            </div>

            <div id="sub-group" style="display: none;">
                <div class="form-group">
                    <label>Speler UIT</label>
                    <select id="player_out" class="form-control" style="width: 100%; padding: 0.5rem; margin-bottom: 0.5rem;">
                        <option value="">-- Kies speler --</option>
                        <?php foreach ($players as $player): ?>
                            <option value="<?= $player['id'] ?>"><?= e($player['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Speler IN</label>
                    <select id="player_in" class="form-control" style="width: 100%; padding: 0.5rem;">
                        <option value="">-- Kies speler --</option>
                        <?php foreach ($players as $player): ?>
                            <option value="<?= $player['id'] ?>"><?= e($player['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Omschrijving</label>
                <input type="text" name="description" class="form-control" style="width: 100%;">
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="button" onclick="closeModal()" class="btn btn-secondary" style="flex: 1;">Annuleren</button>
                <button type="submit" class="btn btn-primary" style="flex: 1;">Opslaan</button>
            </div>
        </form>
    </div>
</div>

<div id="orientation-overlay" class="orientation-lock-overlay" aria-hidden="true">
    <div class="orientation-lock-overlay-card">
        <h2>Live modus in portret</h2>
        <p>Draai je telefoon terug naar portretstand om verder te gaan.</p>
    </div>
</div>

<script src="/js/live-match.js?v=<?= time() ?>"></script>
