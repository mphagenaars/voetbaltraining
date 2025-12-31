<div class="header-actions">
    <h1>Trainingen</h1>
    <a href="/trainings/create" class="btn btn-outline">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        Nieuwe Training
    </a>
</div>

<?php if (empty($trainings)): ?>
    <div class="card">
        <p>Er zijn nog geen trainingen voor dit team.</p>
    </div>
<?php else: ?>
    <div class="training-list">
        <?php foreach ($trainings as $training): ?>
            <?php
                $displayTitle = htmlspecialchars($training['title']);
                if (!empty($training['training_date'])) {
                    $ts = strtotime($training['training_date']);
                    $days = ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag'];
                    $months = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
                    $displayTitle = $days[date('w', $ts)] . ', ' . date('j', $ts) . ' ' . $months[date('n', $ts) - 1] . ' ' . date('Y', $ts);
                }
            ?>
            <div class="action-card" onclick="location.href='/trainings/view?id=<?= $training['id'] ?>'" style="display: flex; align-items: center; justify-content: space-between;">
                <div style="flex: 1;">
                    <h3><?= $displayTitle ?></h3>
                    <p class="text-muted" style="margin-bottom: 0.5rem;"><?= nl2br(htmlspecialchars(substr($training['description'] ?? '', 0, 100))) ?>...</p>
                    
                    <div style="font-size: 0.9rem; color: var(--text-muted); display: flex; gap: 1rem;">
                        <span>📝 <?= $training['exercise_count'] ?> oefeningen</span>
                        <?php if ($training['total_duration']): ?>
                            <span>⏱️ <?= $training['total_duration'] ?> min</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display: flex; align-items: center;">
                    <div style="display: flex; gap: 0.5rem;" onclick="event.stopPropagation();">
                        <a href="/trainings/edit?id=<?= $training['id'] ?>" class="btn-icon" title="Bewerken">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        </a>
                        <form method="POST" action="/trainings/delete" onsubmit="return confirm('Weet je zeker dat je deze training wilt verwijderen?');" style="margin: 0;">
                            <?= Csrf::renderInput() ?>
                            <input type="hidden" name="id" value="<?= $training['id'] ?>">
                            <button type="submit" class="btn-icon" title="Verwijderen" style="color: #c62828;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2-2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>


