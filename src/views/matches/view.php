<div class="container">
    <link rel="stylesheet" href="/css/match-view.css">
    
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

    <!-- Data passing for JS -->
    <input type="hidden" id="match_id" value="<?= $match['id'] ?>">

    <div class="app-bar">
        <div class="app-bar-start">
            <a href="/matches" class="btn-icon-round" title="Terug">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </a>
            <h1 class="app-bar-title"><?= e($match['opponent']) ?> (<?= $match['is_home'] ? 'Thuis' : 'Uit' ?>)</h1>
        </div>
        <div class="app-bar-end">
            <a href="/matches/live?id=<?= $match['id'] ?>" class="btn btn-primary" style="background-color: #2e7d32; color: white; display: flex; align-items: center; gap: 0.5rem; text-decoration: none; padding: 0.5rem 1rem; border-radius: 20px; font-weight: bold;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                LIVE MODE
            </a>
        </div>
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
                <h3>Startopstelling</h3>
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
                            <?php if (!empty($pos['is_substitute'])) continue; ?>
                            <div class="player-token on-field <?= !empty($pos['is_keeper']) ? 'is-goalkeeper' : '' ?>" draggable="true" data-id="<?= $pos['player_id'] ?>" style="left: <?= $pos['position_x'] ?>%; top: <?= $pos['position_y'] ?>%;">
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

                <div class="keepers-container">
                    <h4>Aangewezen Keepers</h4>
                    <div id="keepers-list" class="players-list keepers-list">
                        <!-- Render players marked as keepers -->
                         <?php foreach ($matchPlayers as $pos): ?>
                            <?php if (!empty($pos['is_keeper'])): ?>
                                <div class="player-token is-goalkeeper" draggable="true" data-id="<?= $pos['player_id'] ?>" data-source="keepers">
                                    <div class="player-jersey">
                                        <svg viewBox="0 0 100 100" width="50" height="50">
                                            <path d="M15,30 L30,10 L70,10 L85,30 L75,40 L70,35 L70,90 L30,90 L30,35 L25,40 Z" fill="url(#striped-jersey)" stroke="white" stroke-width="2"/>
                                            <text x="50" y="65" font-family="Arial" font-size="30" fill="white" text-anchor="middle" font-weight="bold"><?= strtoupper(substr($pos['player_name'], 0, 1)) ?></text>
                                        </svg>
                                    </div>
                                    <div class="player-name"><?= e($pos['player_name']) ?></div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <!-- Empty slots if less than 2 keepers -->
                         <?php 
                         $keeperCount = array_reduce($matchPlayers, fn($carry, $item) => $carry + (!empty($item['is_keeper']) ? 1 : 0), 0);
                         for($i = $keeperCount; $i < 2; $i++): ?>
                            <div class="keeper-slot-empty">Sleep speler</div>
                         <?php endfor; ?>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div class="bench-container" style="flex: 1; min-width: 200px;">
                        <h4>Wissels / Selectie</h4>
                        <div id="players-list" class="players-list">
                            <?php 
                            // Exclude players on field OR absent
                            $placedOnFieldIds = array_column(array_filter($matchPlayers, function($p) {
                                return empty($p['is_substitute']);
                            }), 'player_id');

                            $absentIds = array_column(array_filter($matchPlayers, function($p) {
                                return !empty($p['is_absent']);
                            }), 'player_id');
                            ?>
                            <?php foreach ($players as $player): ?>
                                <?php if (!in_array($player['id'], $placedOnFieldIds) && !in_array($player['id'], $absentIds)): ?>
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

                    <div class="bench-container" style="flex: 1; min-width: 200px;">
                        <h4>Afwezig / Ziek</h4>
                        <div id="absent-list" class="players-list">
                             <?php foreach ($players as $player): ?>
                                <?php if (in_array($player['id'], $absentIds)): ?>
                                    <div class="player-token" draggable="true" data-id="<?= $player['id'] ?>">
                                        <div class="player-jersey">
                                            <svg viewBox="0 0 100 100" width="50" height="50">
                                                <path d="M15,30 L30,10 L70,10 L85,30 L75,40 L70,35 L70,90 L30,90 L30,35 L25,40 Z" fill="#999" stroke="white" stroke-width="2"/>
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

<script src="/js/match-view.js"></script>
