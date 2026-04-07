<?php
$backUrl = $backUrl ?? '/exercises';
$backTitle = $backUrl === '/exercises' ? 'Terug' : 'Terug naar training';
$fromTrainingId = isset($fromTrainingId) ? (int)$fromTrainingId : 0;
?>

<div class="app-bar">
    <div class="app-bar-start">
        <a href="<?= e($backUrl) ?>" class="btn-icon-round" title="<?= e($backTitle) ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title tb-app-bar-title-sm"><?= e($exercise['title']) ?></h1>
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
            <form method="POST" action="/exercises/delete" onsubmit="return confirm('Weet je zeker dat je deze oefening wilt verwijderen?');" class="tb-inline tb-m-0">
                <?= Csrf::renderInput() ?>
                <input type="hidden" name="id" value="<?= $exercise['id'] ?>">
                <button type="submit" class="btn-icon-round tb-text-danger" title="Verwijderen">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2-2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="tb-card">
    <div class="tb-two-col">
        <div class="tb-two-col-item">
            <?php if (!empty($exercise['drawing_data'])): ?>
                <div class="editor-wrapper" style="border:none;">
                    <div id="container" class="editor-canvas-container"></div>
                </div>
                <input type="hidden" id="drawing_data" value="<?= e($exercise['drawing_data']) ?>">
                <input type="hidden" id="field_type" value="<?= e($exercise['field_type'] ?? 'portrait') ?>">
            <?php elseif (!empty($exercise['image_path'])): ?>
                <div class="tb-text-center">
                    <img src="/uploads/<?= e($exercise['image_path']) ?>" alt="<?= e($exercise['title']) ?>" class="tb-img-responsive">
                </div>
            <?php else: ?>
                <div class="tb-placeholder">
                    Geen afbeelding beschikbaar
                </div>
            <?php endif; ?>
        </div>

        <div class="tb-two-col-item">
            <div class="tb-content-section">
                <h3>Beschrijving</h3>
                <p><?= nl2br(e($exercise['description'] ?? '')) ?></p>
            </div>

            <?php if (!empty($exercise['variation'])): ?>
            <div class="tb-content-section">
                <h3>Moeilijker / makkelijker maken</h3>
                <p><?= nl2br(e($exercise['variation'])) ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty($exercise['coach_instructions'])): ?>
            <div class="tb-content-section">
                <h3>Coach instructies</h3>
                <p><?= nl2br(e($exercise['coach_instructions'])) ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty($exercise['source'])): ?>
            <div class="tb-content-section">
                <h3>Bron</h3>
                <p><?= formatSourceLink($exercise['source']) ?></p>
            </div>
            <?php endif; ?>

            <div class="tb-info-grid">
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

<div class="tb-card tb-mt-lg">
    <h2>Reacties & Feedback</h2>
    
    <!-- Emoji Reactions -->
    <div class="tb-flex tb-gap-md tb-mb-lg tb-items-center">
        <form method="POST" action="/exercises/react" class="tb-m-0">
            <?= Csrf::renderInput() ?>
            <input type="hidden" name="exercise_id" value="<?= $exercise['id'] ?>">
            <?php if ($fromTrainingId > 0): ?>
                <input type="hidden" name="from_training" value="<?= $fromTrainingId ?>">
            <?php endif; ?>
            <input type="hidden" name="type" value="rock">
            <button type="submit" class="reaction-btn <?= ($userReaction === 'rock') ? 'active' : '' ?>" title="Rock on!">
                🤘 <span class="count"><?= $reactionCounts['rock'] ?? 0 ?></span>
            </button>
        </form>

        <form method="POST" action="/exercises/react" class="tb-m-0">
            <?= Csrf::renderInput() ?>
            <input type="hidden" name="exercise_id" value="<?= $exercise['id'] ?>">
            <?php if ($fromTrainingId > 0): ?>
                <input type="hidden" name="from_training" value="<?= $fromTrainingId ?>">
            <?php endif; ?>
            <input type="hidden" name="type" value="middle_finger">
            <button type="submit" class="reaction-btn <?= ($userReaction === 'middle_finger') ? 'active' : '' ?>" title="Niks aan">
                🖕 <span class="count"><?= $reactionCounts['middle_finger'] ?? 0 ?></span>
            </button>
        </form>
    </div>

    <!-- Comments List -->
    <div class="comments-list tb-mb-lg">
        <?php if (empty($comments)): ?>
            <p class="tb-comment-empty">Nog geen reacties geplaatst.</p>
        <?php else: ?>
            <?php foreach ($comments as $comment): ?>
                <div class="comment-item tb-comment-item">
                    <div class="tb-comment-author">
                        <?= e($comment['user_name']) ?>
                        <small class="tb-comment-date"><?= date('d-m-Y H:i', strtotime($comment['created_at'])) ?></small>
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
            <?php if ($fromTrainingId > 0): ?>
                <input type="hidden" name="from_training" value="<?= $fromTrainingId ?>">
            <?php endif; ?>
            <div class="form-group">
                <textarea name="comment" rows="3" placeholder="Wat vind je van deze oefening?" required class="tb-w-full"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Plaats reactie</button>
        </form>
    </div>
</div>

<!-- Add to Training Modal -->
<?php if (!empty($canAddToTraining)): ?>
<div id="modal-add-to-training" class="tb-modal-overlay" onclick="if(event.target===this)this.style.display='none'">
    <div class="tb-modal tb-modal-card">
        <h3>Inplannen in training</h3>
        
        <?php if (empty($selectableTrainings)): ?>
            <p>Er zijn geen aankomende trainingen gevonden voor jouw teams.</p>
            <p><a href="/trainings/create">Maak eerst een training aan</a> of controleer of je de juiste rechten hebt.</p>
            <div class="tb-modal-actions">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-add-to-training').style.display='none'">Sluiten</button>
            </div>
        <?php else: ?>
            <p>Voeg <strong><?= e($exercise['title']) ?></strong> toe aan een training:</p>
            
            <form method="POST" action="/exercises/add-to-training">
                <?= Csrf::renderInput() ?>
                <input type="hidden" name="exercise_id" value="<?= $exercise['id'] ?>">
                <?php if ($fromTrainingId > 0): ?>
                    <input type="hidden" name="from_training" value="<?= $fromTrainingId ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="training_id">Kies training</label>
                    <select name="training_id" id="training_id" required class="tb-w-full">
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
                    <input type="number" name="duration" id="duration" placeholder="Minuten" value="<?= e((string)($exercise['duration'] ?? '')) ?>" class="tb-w-full">
                </div>
                
                <div class="tb-modal-actions">
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
