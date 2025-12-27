<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <h1>Trainingen</h1>
    <a href="/trainings/create" class="btn">Nieuwe Training</a>
</div>

<?php if (empty($trainings)): ?>
    <div class="card">
        <p>Er zijn nog geen trainingen voor dit team.</p>
    </div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($trainings as $training): ?>
            <div class="card">
                <h3><?= htmlspecialchars($training['title']) ?></h3>
                <p><?= nl2br(htmlspecialchars(substr($training['description'] ?? '', 0, 100))) ?>...</p>
                
                <div style="margin-top: 1rem; font-size: 0.9rem; color: #666;">
                    <span>📝 <?= $training['exercise_count'] ?> oefeningen</span>
                    <?php if ($training['total_duration']): ?>
                        <span style="margin-left: 0.5rem;">⏱️ <?= $training['total_duration'] ?> min</span>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                    <a href="/trainings/view?id=<?= $training['id'] ?>" class="btn btn-sm">Bekijken</a>
                    <form method="POST" action="/trainings/delete" onsubmit="return confirm('Weet je zeker dat je deze training wilt verwijderen?');" style="margin: 0;">
                        <?= Csrf::renderInput() ?>
                        <input type="hidden" name="id" value="<?= $training['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline" style="color: var(--danger-color); border-color: var(--danger-color);">Verwijderen</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>


