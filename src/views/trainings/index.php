<div class="app-bar">
    <div class="app-bar-start">
        <a href="/" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">Trainingen</h1>
    </div>
    <div class="app-bar-actions">
        <?php $nextFilter = ($currentFilter ?? 'all') === 'all' ? 'upcoming' : 'all'; ?>
        <a href="/trainings?filter=<?= $nextFilter ?>" class="btn-icon-round" title="<?= ($currentFilter ?? 'all') === 'all' ? 'Verberg oude trainingen' : 'Toon alle trainingen' ?>" style="<?= ($currentFilter ?? 'all') === 'upcoming' ? 'color: var(--primary); background-color: rgba(46, 125, 50, 0.1);' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
            </svg>
        </a>
        <?php $nextSort = ($currentSort ?? 'asc') === 'desc' ? 'asc' : 'desc'; ?>
        <a href="/trainings?sort=<?= $nextSort ?>" class="btn-icon-round" title="<?= ($currentSort ?? 'asc') === 'desc' ? 'Sorteer: Oudste eerst' : 'Sorteer: Nieuwste eerst' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <?php if (($currentSort ?? 'asc') === 'desc'): ?>
                    <path d="M6 4v16"/><path d="M6 20l-3-3"/><path d="M6 20l3-3"/>
                    <path d="M12 6h8"/><path d="M12 12h6"/><path d="M12 18h4"/>
                <?php else: ?>
                    <path d="M6 4v16"/><path d="M6 20l-3-3"/><path d="M6 20l3-3"/>
                    <path d="M12 6h4"/><path d="M12 12h6"/><path d="M12 18h8"/>
                <?php endif; ?>
            </svg>
        </a>
    </div>
</div>

<?php if (empty($trainings)): ?>
    <div class="card">
        <p>Er zijn nog geen trainingen voor dit team.</p>
    </div>
<?php else: ?>
    <div class="training-list">
        <?php foreach ($trainings as $training): ?>
            <?php
                $displayTitle = e($training['title']);
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
                    <p class="text-muted" style="margin-bottom: 0.5rem;"><?= nl2br(e(strlen($training['description'] ?? '') > 100 ? substr($training['description'], 0, 100) . '...' : ($training['description'] ?? ''))) ?></p>
                    
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

<a href="/trainings/create" class="fab" title="Nieuwe Training">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
</a>


