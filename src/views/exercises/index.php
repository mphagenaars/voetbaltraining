<div class="header-actions">
    <h1>Oefenstof</h1>
    <a href="/exercises/create" class="btn btn-outline">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        Nieuwe Oefening
    </a>
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
            <input type="text" id="q" name="q" value="<?= htmlspecialchars($query ?? '') ?>" placeholder="Titel of beschrijving...">
        </div>
        
        <div>
            <label for="team_task">Teamtaak</label>
            <select id="team_task" name="team_task">
                <option value="">Alle teamtaken</option>
                <?php foreach ($teamTasksList as $task): ?>
                    <option value="<?= $task ?>" <?= ($teamTask ?? '') === $task ? 'selected' : '' ?>><?= $task ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="training_objective">Doelstelling</label>
            <select id="training_objective" name="training_objective">
                <option value="">Alle doelstellingen</option>
                <?php foreach ($objectivesList as $obj): ?>
                    <option value="<?= $obj ?>" <?= ($trainingObjective ?? '') === $obj ? 'selected' : '' ?>><?= $obj ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="football_action">Voetbalhandeling</label>
            <select id="football_action" name="football_action">
                <option value="">Alle handelingen</option>
                <?php foreach ($actionsList as $action): ?>
                    <option value="<?= $action ?>" <?= ($footballAction ?? '') === $action ? 'selected' : '' ?>><?= $action ?></option>
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
            <div class="card">
                <?php if (!empty($exercise['image_path'])): ?>
                    <div style="margin-bottom: 1rem; text-align: center;">
                        <img src="/uploads/<?= htmlspecialchars($exercise['image_path']) ?>" alt="<?= htmlspecialchars($exercise['title']) ?>" style="max-width: 100%; max-height: 200px; border-radius: 4px;">
                    </div>
                <?php endif; ?>
                <h3><?= htmlspecialchars($exercise['title']) ?></h3>
                <p><?= nl2br(htmlspecialchars(substr($exercise['description'] ?? '', 0, 100))) ?>...</p>
                
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
                        <div><strong>Teamtaak:</strong> <?= htmlspecialchars($exercise['team_task']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($exercise['training_objective'])): ?>
                        <div><strong>Doelstelling:</strong> 
                        <?php
                        $decoded = json_decode($exercise['training_objective'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            echo htmlspecialchars(implode(', ', $decoded));
                        } else {
                            echo htmlspecialchars($exercise['training_objective']);
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
                            echo htmlspecialchars(implode(', ', $capitalized));
                        } else {
                            echo htmlspecialchars(ucfirst($exercise['football_action']));
                        }
                        ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                    <a href="/exercises/view?id=<?= $exercise['id'] ?>" class="btn btn-sm btn-outline">Bekijken</a>
                    <a href="/exercises/edit?id=<?= $exercise['id'] ?>" class="btn btn-sm btn-outline">Bewerken</a>
                    <form method="POST" action="/exercises/delete" onsubmit="return confirm('Weet je zeker dat je deze oefening wilt verwijderen?');" style="margin: 0;">
                        <?= Csrf::renderInput() ?>
                        <input type="hidden" name="id" value="<?= $exercise['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline" style="color: var(--danger-color); border-color: var(--danger-color);">Verwijderen</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>


