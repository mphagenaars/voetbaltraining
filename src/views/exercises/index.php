<div class="app-bar">
    <div class="app-bar-start">
        <a href="/" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">Oefenstof</h1>
    </div>
</div>

<div class="card" style="margin-bottom: 1rem;">
    <?php
    $teamTasksList = Exercise::getTeamTasks();
    $objectivesList = Exercise::getObjectives();
    $actionsList = Exercise::getFootballActions();
    ?>
    <form method="GET" action="/exercises">
        <div>
            <label for="q">Zoeken</label>
            <input type="text" id="q" name="q" value="<?= e($query ?? '') ?>" placeholder="Titel of beschrijving...">
        </div>
        
        <div>
            <label for="team_task">Teamtaak</label>
            <select id="team_task" name="team_task">
                <option value="">Alle teamtaken</option>
                <?php foreach ($teamTasksList as $task): ?>
                    <option value="<?= e($task) ?>" <?= ($teamTask ?? '') === $task ? 'selected' : '' ?>><?= e($task) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="training_objective">Doelstelling</label>
            <select id="training_objective" name="training_objective">
                <option value="">Alle doelstellingen</option>
                <?php foreach ($objectivesList as $obj): ?>
                    <option value="<?= e($obj) ?>" <?= ($trainingObjective ?? '') === $obj ? 'selected' : '' ?>><?= e($obj) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="football_action">Voetbalhandeling</label>
            <select id="football_action" name="football_action">
                <option value="">Alle handelingen</option>
                <?php foreach ($actionsList as $action): ?>
                    <option value="<?= e($action) ?>" <?= ($footballAction ?? '') === $action ? 'selected' : '' ?>><?= e($action) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-outline">Zoeken</button>
            <?php if (!empty($query) || !empty($teamTask) || !empty($trainingObjective) || !empty($footballAction)): ?>
                <a href="/exercises" class="btn btn-outline" style="border: none;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (empty($exercises)): ?>
    <div class="card">
        <p>Geen oefeningen gevonden.</p>
    </div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($exercises as $exercise): ?>
            <div class="card" onclick="location.href='/exercises/view?id=<?= $exercise['id'] ?>'" style="cursor: pointer; position: relative;">
                <?php if (!empty($exercise['image_path'])): ?>
                    <div style="margin-bottom: 1rem; text-align: center;">
                        <img src="/uploads/<?= e($exercise['image_path']) ?>" alt="<?= e($exercise['title']) ?>" style="max-width: 100%; max-height: 200px; border-radius: 4px;">
                    </div>
                <?php endif; ?>
                <h3><?= e($exercise['title']) ?></h3>
                <p><?= nl2br(e(strlen($exercise['description'] ?? '') > 100 ? substr($exercise['description'], 0, 100) . '...' : ($exercise['description'] ?? ''))) ?></p>
                
                <div style="margin-top: 1rem; font-size: 0.9rem; color: #666;">
                    <?php if (!empty($exercise['min_players']) || !empty($exercise['max_players'])): ?>
                        <span>👥 
                            <?php 
                            if (!empty($exercise['min_players']) && !empty($exercise['max_players'])) {
                                echo $exercise['min_players'] . ' - ' . $exercise['max_players'];
                            } elseif (!empty($exercise['min_players'])) {
                                echo $exercise['min_players'] . '+';
                            } elseif (!empty($exercise['max_players'])) {
                                echo 'max ' . $exercise['max_players'];
                            }
                            ?> spelers
                        </span>
                    <?php elseif (!empty($exercise['players'])): ?>
                         <span>👥 <?= $exercise['players'] ?> spelers</span>
                    <?php endif; ?>
                    <?php if ($exercise['duration']): ?>
                        <span style="margin-left: 0.5rem;">⏱️ <?= $exercise['duration'] ?> min</span>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 0.5rem; font-size: 0.85rem; color: #555;">
                    <?php if (!empty($exercise['team_task'])): ?>
                        <div><strong>Teamtaak:</strong> <?= e($exercise['team_task']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($exercise['training_objective'])): ?>
                        <div><strong>Doelstelling:</strong> 
                        <?php
                        $decoded = json_decode($exercise['training_objective'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            echo e(implode(', ', $decoded));
                        } else {
                            echo e($exercise['training_objective']);
                        }
                        ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($exercise['football_action'])): ?>
                        <div><strong>Voetbalhandeling:</strong> 
                        <?php
                        $decoded = json_decode($exercise['football_action'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $capitalized = array_map('ucfirst', $decoded);
                            echo e(implode(', ', $capitalized));
                        } else {
                            echo e(ucfirst($exercise['football_action']));
                        }
                        ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 1rem; display: flex; justify-content: flex-end; gap: 0.5rem;" onclick="event.stopPropagation();">
                    <a href="/exercises/view?id=<?= $exercise['id'] ?>" class="btn-icon" title="Bekijken">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </a>
                    <?php 
                    // Permission check: Admin or Creator
                    $canEdit = (Session::get('is_admin')) || 
                               (!empty($exercise['created_by']) && $exercise['created_by'] == Session::get('user_id'));
                    ?>
                    <?php if ($canEdit): ?>
                        <a href="/exercises/edit?id=<?= $exercise['id'] ?>" class="btn-icon" title="Bewerken">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        </a>
                        <form method="POST" action="/exercises/delete" onsubmit="return confirm('Weet je zeker dat je deze oefening wilt verwijderen?');" style="margin: 0;">
                            <?= Csrf::renderInput() ?>
                            <input type="hidden" name="id" value="<?= $exercise['id'] ?>">
                            <button type="submit" class="btn-icon" title="Verwijderen" style="color: var(--danger-color);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2-2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>



<a href="/exercises/create" class="fab" title="Nieuwe Oefening">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
</a>
