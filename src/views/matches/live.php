<div class="container" style="max-width: 600px; margin: 0 auto; padding-top: 1rem;">
    <!-- Data for JS -->
    <input type="hidden" id="match_id" value="<?= $match['id'] ?>">
    <input type="hidden" id="csrf_token" value="<?= Csrf::getToken() ?>">
    <input type="hidden" id="initial_timer_state" value="<?= e(json_encode($timerState)) ?>">

    <div class="app-bar" style="margin-bottom: 1rem;">
         <div class="app-bar-start">
            <a href="/matches/view?id=<?= $match['id'] ?>" class="btn-icon-round" title="Terug">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </a>
            <h1 class="app-bar-title">Live: <?= e($match['opponent']) ?></h1>
        </div>
    </div>

    <!-- Timer Section -->
    <div class="card" style="text-align: center; margin-bottom: 1rem; padding: 2rem 1rem;">
        <h2 id="period-display" style="font-size: 1.2rem; color: #666; margin-bottom: 0.5rem;">
            <?= $timerState['current_period'] > 0 ? "Periode " . $timerState['current_period'] : "Nog niet gestart" ?>
        </h2>
        <div id="timer-display" style="font-size: 4rem; font-weight: bold; font-family: monospace; line-height: 1;">
            <?= sprintf("%02d:00", $timerState['total_minutes']) ?>
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
            <button class="action-btn card-btn" onclick="openActionModal('card')" style="background-color: #ff9800; color: white; padding: 1.5rem; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: bold; display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
                <span style="font-size: 2rem;">🟨🟥</span>
                Kaart
            </button>
            <button class="action-btn sub-btn" onclick="openActionModal('sub')" style="background-color: #2196f3; color: white; padding: 1.5rem; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: bold; display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
                <span style="font-size: 2rem;">🔄</span>
                Wissel
            </button>
             <button class="action-btn note-btn" onclick="openActionModal('other')" style="background-color: #9e9e9e; color: white; padding: 1.5rem; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: bold; display: flex; flex-direction: column; align-items: center; gap: 0.5rem;">
                <span style="font-size: 2rem;">📝</span>
                Notitie
            </button>
        </div>
    </div>

    <!-- Last Events -->
    <div class="card">
        <h3>Recente Gebeurtenissen</h3>
        <ul id="timeline-list" class="timeline" style="list-style: none; padding: 0;">
            <!-- JS will populate/update this -->
             <?php foreach ($events as $event): ?>
                    <?php if($event['type'] === 'whistle') continue; ?>
                    <li style="border-bottom: 1px solid #eee; padding: 0.5rem 0;">
                        <strong><?= $event['minute'] ?>'</strong> 
                        <?= match($event['type']) {
                            'goal' => '⚽ Doelpunt',
                            'card_yellow' => '🟨 Gele kaart',
                            'card_red' => '🟥 Rode kaart',
                            'sub' => '🔄 Wissel',
                            default => 'Gebeurtenis'
                        }; ?>
                        <?php if ($event['player_name']): ?>
                            door <strong><?= e($event['player_name']) ?></strong>
                        <?php endif; ?>
                    </li>
             <?php endforeach; ?>
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

<script src="/js/live-match.js"></script>
