<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <h1><?= htmlspecialchars($exercise['title']) ?></h1>
    <div>
        <a href="/exercises/edit?id=<?= $exercise['id'] ?>" class="btn btn-outline">Bewerken</a>
        <a href="/exercises" class="btn btn-outline">Terug</a>
    </div>
</div>

<div class="card">
    <div style="display: flex; flex-wrap: wrap; gap: 2rem;">
        <div style="flex: 1; min-width: 300px;">
            <?php if (!empty($exercise['drawing_data'])): ?>
                <div class="editor-wrapper" style="border: none;">
                    <div id="container" class="editor-canvas-container"></div>
                </div>
                <input type="hidden" id="drawing_data" value="<?= htmlspecialchars($exercise['drawing_data']) ?>">
                <input type="hidden" id="field_type" value="<?= htmlspecialchars($exercise['field_type'] ?? 'portrait') ?>">
            <?php elseif (!empty($exercise['image_path'])): ?>
                <div style="text-align: center;">
                    <img src="/uploads/<?= htmlspecialchars($exercise['image_path']) ?>" alt="<?= htmlspecialchars($exercise['title']) ?>" style="max-width: 100%; border-radius: 4px;">
                </div>
            <?php else: ?>
                <div style="background: #eee; height: 300px; display: flex; align-items: center; justify-content: center; border-radius: 4px; color: #666;">
                    Geen afbeelding beschikbaar
                </div>
            <?php endif; ?>
        </div>

        <div style="flex: 1; min-width: 300px;">
            <div style="margin-bottom: 1.5rem;">
                <h3>Beschrijving</h3>
                <p><?= nl2br(htmlspecialchars($exercise['description'] ?? '')) ?></p>
            </div>

            <?php if (!empty($exercise['variation'])): ?>
            <div style="margin-bottom: 1.5rem;">
                <h3>Moeilijker / makkelijker maken</h3>
                <p><?= nl2br(htmlspecialchars($exercise['variation'])) ?></p>
            </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <strong>Teamtaak:</strong><br>
                    <?= !empty($exercise['team_task']) ? htmlspecialchars($exercise['team_task']) : '-' ?>
                </div>
                <div>
                    <strong>Doelstelling:</strong><br>
                    <?php
                    if (!empty($exercise['training_objective'])) {
                        $decoded = json_decode($exercise['training_objective'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            echo htmlspecialchars(implode(', ', $decoded));
                        } else {
                            echo htmlspecialchars($exercise['training_objective']);
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
                            echo htmlspecialchars(implode(', ', $capitalized));
                        } else {
                            echo htmlspecialchars(ucfirst($exercise['football_action']));
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
                        echo htmlspecialchars((string)$exercise['min_players']) . ' - ' . htmlspecialchars((string)$exercise['max_players']);
                    } elseif (!empty($exercise['min_players'])) {
                        echo htmlspecialchars((string)$exercise['min_players']) . '+';
                    } elseif (!empty($exercise['max_players'])) {
                        echo 'max ' . htmlspecialchars((string)$exercise['max_players']);
                    } elseif (!empty($exercise['players'])) {
                        echo htmlspecialchars((string)$exercise['players']);
                    } else {
                        echo '-';
                    }
                    ?>
                </div>
                <div>
                    <strong>Duur:</strong><br>
                    <?= $exercise['duration'] ? htmlspecialchars((string)$exercise['duration']) . ' min' : '-' ?>
                </div>
            </div>
        </div>
    </div>
</div>



<?php if (!empty($exercise['drawing_data'])): ?>
<script src="/js/konva.min.js"></script>
<script src="/js/viewer.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/viewer.js') ?>"></script>
<?php endif; ?>
