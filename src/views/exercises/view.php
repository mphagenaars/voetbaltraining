<div class="app-bar">
    <div class="app-bar-start">
        <a href="/exercises" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title" style="font-size: 1.25rem;"><?= e($exercise['title']) ?></h1>
    </div>
    <div class="app-bar-actions">
        <?php if (!empty($canAddToTraining)): ?>
            <button type="button" class="btn-icon-round" title="Inplannen" onclick="document.getElementById('modal-add-to-training').style.display='flex'">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><line x1="12" y1="12" x2="12" y2="16"></line><line x1="10" y1="14" x2="14" y2="14"></line></svg>
            </button>
        <?php endif; ?>
        <?php if (!empty($canEdit)): ?>
            <a href="/exercises/edit?id=<?= $exercise['id'] ?>" class="btn-icon-round" title="Bewerken">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            </a>
            <form method="POST" action="/exercises/delete" onsubmit="return confirm('Weet je zeker dat je deze oefening wilt verwijderen?');" style="margin: 0; display: inline-block;">
                <?= Csrf::renderInput() ?>
                <input type="hidden" name="id" value="<?= $exercise['id'] ?>">
                <button type="submit" class="btn-icon-round" title="Verwijderen" style="color: var(--danger-color);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2-2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div style="display: flex; flex-wrap: wrap; gap: 2rem;">
        <div style="flex: 1; min-width: 240px;">
            <?php if (!empty($exercise['drawing_data'])): ?>
                <div class="editor-wrapper" style="border: none;">
                    <div id="container" class="editor-canvas-container"></div>
                </div>
                <input type="hidden" id="drawing_data" value="<?= e($exercise['drawing_data']) ?>">
                <input type="hidden" id="field_type" value="<?= e($exercise['field_type'] ?? 'portrait') ?>">
            <?php elseif (!empty($exercise['image_path'])): ?>
                <div style="text-align: center;">
                    <img src="/uploads/<?= e($exercise['image_path']) ?>" alt="<?= e($exercise['title']) ?>" style="max-width: 100%; border-radius: 4px;">
                </div>
            <?php else: ?>
                <div style="background: #eee; height: 300px; display: flex; align-items: center; justify-content: center; border-radius: 4px; color: #666;">
                    Geen afbeelding beschikbaar
                </div>
            <?php endif; ?>
        </div>

        <div style="flex: 1; min-width: 240px;">
            <div style="margin-bottom: 1.5rem;">
                <h3>Beschrijving</h3>
                <p><?= nl2br(e($exercise['description'] ?? '')) ?></p>
            </div>

            <?php if (!empty($exercise['variation'])): ?>
            <div style="margin-bottom: 1.5rem;">
                <h3>Moeilijker / makkelijker maken</h3>
                <p><?= nl2br(e($exercise['variation'])) ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty($exercise['coach_instructions'])): ?>
            <div style="margin-bottom: 1.5rem;">
                <h3>Coach instructies</h3>
                <p><?= nl2br(e($exercise['coach_instructions'])) ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty($exercise['source'])): ?>
            <div style="margin-bottom: 1.5rem;">
                <h3>Bron</h3>
                <p><?= formatSourceLink($exercise['source']) ?></p>
            </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <strong>Teamtaak:</strong><br>
                    <?= !empty($exercise['team_task']) ? e($exercise['team_task']) : '-' ?>
                </div>
                <div>
                    <strong>Doelstelling:</strong><br>
                    <?php
                    if (!empty($exercise['training_objective'])) {
                        $decoded = json_decode($exercise['training_objective'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            echo e(implode(', ', $decoded));
                        } else {
                            echo e($exercise['training_objective']);
                        }
                    } else {
                        echo '-';
                    }
                    ?>
                </div>
                <div>
                    <strong>Voetbalhandeling:</strong><br>
                    <?php
                    if (!empty($exercise['football_action'])) {
                        $decoded = json_decode($exercise['football_action'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $capitalized = array_map('ucfirst', $decoded);
                            echo e(implode(', ', $capitalized));
                        } else {
                            echo e(ucfirst($exercise['football_action']));
                        }
                    } else {
                        echo '-';
                    }
                    ?>
                </div>
                <div>
                    <strong>Aantal spelers:</strong><br>
                    <?php 
                    if (!empty($exercise['min_players']) && !empty($exercise['max_players'])) {
                        echo e((string)$exercise['min_players']) . ' - ' . e((string)$exercise['max_players']);
                    } elseif (!empty($exercise['min_players'])) {
                        echo e((string)$exercise['min_players']) . '+';
                    } elseif (!empty($exercise['max_players'])) {
                        echo 'max ' . e((string)$exercise['max_players']);
                    } elseif (!empty($exercise['players'])) {
                        echo e((string)$exercise['players']);
                    } else {
                        echo '-';
                    }
                    ?>
                </div>
                <div>
                    <strong>Duur:</strong><br>
                    <?= $exercise['duration'] ? e((string)$exercise['duration']) . ' min' : '-' ?>
                </div>
                <div>
                   <strong>Gemaakt door:</strong><br>
                   <?= !empty($exercise['creator_name']) ? e($exercise['creator_name']) : '-' ?>
               </div>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 2rem;">
    <h2>Reacties & Feedback</h2>
    
    <!-- Emoji Reactions -->
    <div style="display: flex; gap: 1rem; margin-bottom: 2rem; align-items: center;">
        <form method="POST" action="/exercises/react" style="margin: 0;">
            <?= Csrf::renderInput() ?>
            <input type="hidden" name="exercise_id" value="<?= $exercise['id'] ?>">
            <input type="hidden" name="type" value="rock">
            <button type="submit" class="reaction-btn <?= ($userReaction === 'rock') ? 'active' : '' ?>" title="Rock on!">
                🤘 <span class="count"><?= $reactionCounts['rock'] ?? 0 ?></span>
            </button>
        </form>

        <form method="POST" action="/exercises/react" style="margin: 0;">
            <?= Csrf::renderInput() ?>
            <input type="hidden" name="exercise_id" value="<?= $exercise['id'] ?>">
            <input type="hidden" name="type" value="middle_finger">
            <button type="submit" class="reaction-btn <?= ($userReaction === 'middle_finger') ? 'active' : '' ?>" title="Niks aan">
                🖕 <span class="count"><?= $reactionCounts['middle_finger'] ?? 0 ?></span>
            </button>
        </form>
    </div>

    <!-- Comments List -->
    <div class="comments-list" style="margin-bottom: 2rem;">
        <?php if (empty($comments)): ?>
            <p style="color: #666; font-style: italic;">Nog geen reacties geplaatst.</p>
        <?php else: ?>
            <?php foreach ($comments as $comment): ?>
                <div class="comment-item" style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #eee;">
                    <div style="font-weight: bold; margin-bottom: 0.25rem;">
                        <?= e($comment['user_name']) ?>
                        <small style="font-weight: normal; color: #888; margin-left: 0.5rem;"><?= date('d-m-Y H:i', strtotime($comment['created_at'])) ?></small>
                    </div>
                    <div><?= nl2br(e($comment['comment'])) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- New Comment Form -->
    <div class="new-comment">
        <h4>Plaats een reactie</h4>
        <form method="POST" action="/exercises/comment">
            <?= Csrf::renderInput() ?>
            <input type="hidden" name="exercise_id" value="<?= $exercise['id'] ?>">
            <div class="form-group">
                <textarea name="comment" rows="3" placeholder="Wat vind je van deze oefening?" required style="width: 100%;"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Plaats reactie</button>
        </form>
    </div>
</div>

<style>
.reaction-btn {
    background: #f0f2f5;
    border: 1px solid #ddd;
    border-radius: 20px;
    padding: 0.5rem 1rem;
    font-size: 1.25rem;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.reaction-btn:hover {
    background: #e4e6eb;
}
.reaction-btn.active {
    background: #e7f3ff;
    border-color: #1877f2;
    color: #1877f2;
}
.reaction-btn .count {
    font-size: 0.9rem;
    font-weight: bold;
}
</style>

<!-- Add to Training Modal -->
<?php if (!empty($canAddToTraining)): ?>
<div id="modal-add-to-training" class="modal-overlay" style="display: none;">
    <div class="modal card" style="max-width: 500px; width: 100%;">
        <h3>Inplannen in training</h3>
        
        <?php if (empty($selectableTrainings)): ?>
            <p>Er zijn geen aankomende trainingen gevonden voor jouw teams.</p>
            <p><a href="/trainings/create">Maak eerst een training aan</a> of controleer of je de juiste rechten hebt.</p>
            <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-add-to-training').style.display='none'">Sluiten</button>
            </div>
        <?php else: ?>
            <p>Voeg <strong><?= e($exercise['title']) ?></strong> toe aan een training:</p>
            
            <form method="POST" action="/exercises/add-to-training">
                <?= Csrf::renderInput() ?>
                <input type="hidden" name="exercise_id" value="<?= $exercise['id'] ?>">
                
                <div class="form-group">
                    <label for="training_id">Kies training</label>
                    <select name="training_id" id="training_id" required style="width: 100%; padding: 0.5rem;">
                        <option value="">-- Selecteer een training --</option>
                        <?php foreach ($selectableTrainings as $teamName => $trainings): ?>
                            <optgroup label="<?= e($teamName) ?>">
                                <?php foreach ($trainings as $training): ?>
                                    <option value="<?= $training['id'] ?>">
                                        <?= date('d-m-Y', strtotime($training['training_date'])) ?> 
                                        <?= $training['start_time'] ? '(' . e(substr($training['start_time'], 0, 5)) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="duration">Duur (optioneel)</label>
                    <input type="number" name="duration" id="duration" placeholder="Minuten" value="<?= e((string)($exercise['duration'] ?? '')) ?>" style="width: 100%;">
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-add-to-training').style.display='none'">Annuleren</button>
                    <button type="submit" class="btn btn-primary">Toevoegen</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($exercise['drawing_data'])): ?>
<script src="/js/konva.min.js"></script>
<script src="/js/viewer.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/viewer.js') ?>"></script>
<?php endif; ?>
