<div class="app-bar">
    <div class="app-bar-start">
        <a href="/exercises" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title" style="font-size: 1.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;"><?= e($exercise['title']) ?></h1>
    </div>
    <div class="app-bar-actions">
        <?php if (!empty($canEdit)): ?>
            <a href="/exercises/edit?id=<?= $exercise['id'] ?>" class="btn-icon-round" title="Bewerken">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            </a>
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
            </div>
        </div>
    </div>
</div>



<?php if (!empty($exercise['drawing_data'])): ?>
<script src="/js/konva.min.js"></script>
<script src="/js/viewer.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/viewer.js') ?>"></script>
<?php endif; ?>
