<?php
$initialTimerSeconds = (int)($timerState['total_seconds'] ?? 0);
$initialTimerIsPlaying = !empty($timerState['is_playing']);
$initialTimerLabel = $initialTimerIsPlaying
    ? 'Stop tijd'
    : ($initialTimerSeconds > 0 ? 'Hervat tijd' : 'Start wedstrijd');
$timerButtonVariantClass = $initialTimerIsPlaying ? ' tb-button--danger' : ' tb-button--primary';
?>

<div id="live-match-root" class="container tb-live-page">
    <link rel="stylesheet" href="/css/match-view.css?v=<?= time() ?>">

    <svg width="0" height="0" class="tb-live-svg-defs" aria-hidden="true" focusable="false">
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

    <input type="hidden" id="match_id" value="<?= $match['id'] ?>">
    <input type="hidden" id="csrf_token" value="<?= Csrf::getToken() ?>">
    <input type="hidden" id="initial_timer_state" value="<?= e(json_encode($timerState)) ?>">
    <input type="hidden" id="initial_live_state" value="<?= e(json_encode($liveState ?? [])) ?>">
    <input type="hidden" id="initial_events" value="<?= e(json_encode($events ?? [])) ?>">

    <div class="app-bar tb-live-app-bar">
         <div class="app-bar-start">
            <a href="/matches/view?id=<?= $match['id'] ?>" class="btn-icon-round" title="Terug" aria-label="Terug">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </a>
            <h1 class="app-bar-title">Live: <?= e($match['opponent']) ?></h1>
        </div>
    </div>

    <div class="card live-timer-card tb-live-timer-card">
        <h2 id="period-display" class="tb-live-period-label">
            <?= $timerState['current_period'] > 0 ? 'Periode ' . $timerState['current_period'] : 'Nog niet gestart' ?>
        </h2>
        <div id="timer-display" class="tb-live-timer-value">
            <?= sprintf('%02d:00', $timerState['total_minutes']) ?>
        </div>
        <div class="live-scoreboard" aria-live="polite">
            <span class="live-score-team-value" id="score-home"><?= (int)$match['score_home'] ?></span>
            <div class="live-score-separator" aria-hidden="true">-</div>
            <span class="live-score-team-value" id="score-away"><?= (int)$match['score_away'] ?></span>
        </div>
        <div class="tb-live-primary-action">
            <button id="timer-btn" class="tb-button<?= $timerButtonVariantClass ?> tb-button--lg tb-live-timer-btn" type="button">
                <?= e($initialTimerLabel) ?>
            </button>
        </div>
    </div>

    <div class="card tb-live-support-card">
        <h3 class="tb-live-card-title">Snelle acties</h3>
        <div class="tb-live-support-grid<?= !empty($liveVoiceEnabled) ? ' is-two' : '' ?>">
            <button class="tb-button tb-button--secondary tb-live-support-btn" type="button" onclick="openActionModal('goal')">
                <span class="tb-live-support-icon" aria-hidden="true">✏️</span>
                <span>Handmatig event</span>
            </button>
            <?php if (!empty($liveVoiceEnabled)): ?>
            <button id="voice-btn" class="tb-button tb-button--secondary tb-live-support-btn" type="button">
                <span class="tb-live-support-icon" aria-hidden="true">🎤</span>
                <span>Spraak event</span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card tb-live-lineup-card">
        <h3 class="tb-live-card-title">Actieve opstelling</h3>
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
                <h4 class="tb-live-bench-title">Bank</h4>
                <div id="live-bench-token-list" class="players-list"></div>
                <p class="live-drag-hint">Sleep bank naar veld om te wisselen, of veld op veld om van positie te wisselen.</p>
            </div>

        </div>
        <div class="tb-live-support-inline">
            <button id="undo-sub-btn" type="button" class="tb-button tb-button--secondary tb-button--sm">
                Undo laatste wissel
            </button>
        </div>
    </div>

    <div class="card tb-live-minutes-card">
        <h3 class="tb-live-card-title">Speeltijd (live)</h3>
        <div id="minutes-summary-container"></div>
    </div>

    <div class="card tb-live-timeline-card">
        <h3 class="tb-live-card-title">Recente gebeurtenissen</h3>
        <div id="timeline-filter-controls" class="live-timeline-filters" role="group" aria-label="Filter gebeurtenissen">
            <button type="button" class="timeline-filter-btn tb-chip is-active" data-filter="all" aria-pressed="true">
                📋 Alles
            </button>
            <button type="button" class="timeline-filter-btn tb-chip" data-filter="goals" aria-pressed="false">
                ⚽ Doelpunten
            </button>
            <button type="button" class="timeline-filter-btn tb-chip" data-filter="subs" aria-pressed="false">
                🔄 Wissels
            </button>
        </div>
        <ul id="timeline-list" class="timeline tb-live-timeline-list">
            <!-- JS populates timeline from initial_events -->
        </ul>
    </div>
</div>

<div id="action-modal" class="tb-live-modal" aria-hidden="true">
    <div class="tb-live-modal-backdrop" data-close-modal></div>
    <div class="card tb-live-modal-panel" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <h3 id="modal-title" class="tb-live-modal-title">Actie toevoegen</h3>
        <form id="action-form">
            <input type="hidden" name="match_id" value="<?= $match['id'] ?>">

            <div class="form-group">
                <label for="modal-type-select">Type event</label>
                <select name="type" id="modal-type-select">
                    <option value="goal">Doelpunt</option>
                    <option value="sub">Wissel</option>
                    <option value="card">Kaart</option>
                    <option value="other">Notitie</option>
                </select>
            </div>

            <div class="form-group">
                <label for="modal-minute">Minuut (automatisch)</label>
                <input type="number" name="minute" id="modal-minute">
            </div>

            <div class="form-group" id="player-select-group">
                <label>Speler (optioneel)</label>
                <select name="player_id">
                    <option value="">-- Kies speler --</option>
                    <?php foreach ($players as $player): ?>
                        <option value="<?= $player['id'] ?>"><?= e($player['name']) ?></option>
                    <?php endforeach; ?>
                    <option value="unknown">Overig (eigen doelpunt tegenstander)</option>
                    <option value="opponent">Tegendoelpunt</option>
                </select>
            </div>

            <div id="sub-group" hidden>
                <div class="form-group">
                    <label for="player_out">Speler UIT</label>
                    <select id="player_out">
                        <option value="">-- Kies speler --</option>
                        <?php foreach ($players as $player): ?>
                            <option value="<?= $player['id'] ?>"><?= e($player['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="player_in">Speler IN</label>
                    <select id="player_in">
                        <option value="">-- Kies speler --</option>
                        <?php foreach ($players as $player): ?>
                            <option value="<?= $player['id'] ?>"><?= e($player['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="modal-description">Omschrijving</label>
                <input type="text" id="modal-description" name="description">
            </div>

            <div class="tb-live-modal-actions">
                <button type="button" onclick="closeModal()" class="tb-button tb-button--secondary">Annuleren</button>
                <button type="submit" class="tb-button tb-button--primary">Event opslaan</button>
            </div>
        </form>
    </div>
</div>

<div id="voice-overlay" class="voice-overlay" aria-hidden="true">
    <div class="voice-overlay-backdrop"></div>
    <div class="voice-overlay-content">
        <div id="voice-status-icon" class="voice-status-icon is-idle">
            <svg id="voice-icon-mic" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line></svg>
            <div id="voice-spinner" class="voice-spinner" hidden></div>
        </div>
        <p id="voice-status-text" class="voice-status-text">Houd ingedrukt om te spreken</p>
        <button id="voice-cancel-btn" type="button" class="tb-button tb-button--secondary tb-live-voice-cancel" hidden>Annuleren</button>
    </div>
</div>

<div id="voice-confirm-sheet" class="voice-confirm-sheet" aria-hidden="true">
    <div class="voice-confirm-sheet-backdrop"></div>
    <div class="voice-confirm-sheet-content card">
        <h3 class="tb-live-confirm-title">Herkende events</h3>
        <p id="voice-transcript" class="voice-transcript"></p>
        <div id="voice-events-list" class="voice-events-list"></div>
        <div class="voice-confirm-actions">
            <button id="voice-reject-btn" type="button" class="tb-button tb-button--secondary">Verwerp</button>
            <button id="voice-accept-btn" type="button" class="tb-button tb-button--primary">Bevestig</button>
        </div>
    </div>
</div>

<div id="voice-toast" class="voice-toast" aria-live="polite"></div>

<div id="orientation-overlay" class="orientation-lock-overlay" aria-hidden="true">
    <div class="orientation-lock-overlay-card">
        <h2>Live modus in portret</h2>
        <p>Draai je telefoon terug naar portretstand om verder te gaan.</p>
    </div>
</div>

<script src="/js/live-match.js?v=<?= time() ?>"></script>
