<div class="app-bar">
    <div class="app-bar-start">
        <a href="/" class="btn-icon-round" title="Terug" aria-label="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">Wedstrijden</h1>
    </div>
    <div class="app-bar-actions">
        <a href="/matches/reports" class="btn-icon-round" title="Rapportage" aria-label="Rapportage">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="20" x2="18" y2="10"></line>
                <line x1="12" y1="20" x2="12" y2="4"></line>
                <line x1="6" y1="20" x2="6" y2="14"></line>
            </svg>
        </a>
        <?php $nextFilter = ($currentFilter ?? 'all') === 'all' ? 'upcoming' : 'all'; ?>
        <a href="/matches?filter=<?= $nextFilter ?>" class="btn-icon-round<?= ($currentFilter ?? 'all') === 'upcoming' ? ' tb-filter-active' : '' ?>" title="<?= ($currentFilter ?? 'all') === 'all' ? 'Verberg gespeelde wedstrijden' : 'Toon alle wedstrijden' ?>" aria-label="<?= ($currentFilter ?? 'all') === 'all' ? 'Verberg gespeelde wedstrijden' : 'Toon alle wedstrijden' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
            </svg>
        </a>
        <?php $nextSort = ($currentSort ?? 'asc') === 'desc' ? 'asc' : 'desc'; ?>
        <a href="/matches?sort=<?= $nextSort ?>" class="btn-icon-round" title="<?= ($currentSort ?? 'asc') === 'desc' ? 'Sorteer: Oudste eerst' : 'Sorteer: Nieuwste eerst' ?>" aria-label="<?= ($currentSort ?? 'asc') === 'desc' ? 'Sorteer: Oudste eerst' : 'Sorteer: Nieuwste eerst' ?>">
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

<?php if (empty($matches)): ?>
    <div class="card">
        <p>Nog geen wedstrijden aangemaakt.</p>
    </div>
<?php else: ?>
    <div class="match-list">
        <?php foreach ($matches as $match): ?>
            <div class="action-card tb-list-card" onclick="location.href='/matches/view?id=<?= $match['id'] ?>'">
                <div class="tb-flex-1">
                    <h3><?= e($match['opponent']) ?> (<?= $match['is_home'] ? 'Thuis' : 'Uit' ?>)</h3>
                    <div class="tb-list-card-meta">
                        <span>📅 <?= e(date('d-m-Y H:i', strtotime($match['date']))) ?></span>
                        <span>⚽ <?= $match['score_home'] ?> - <?= $match['score_away'] ?></span>
                    </div>
                </div>
                
                <div class="tb-list-card-actions">
                    <div class="tb-list-card-actions-inner" onclick="event.stopPropagation();">
                        <a href="/matches/view?id=<?= $match['id'] ?>" class="btn-icon" title="Bekijken" aria-label="Bekijken">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </a>
                        <a href="/matches/edit?id=<?= $match['id'] ?>" class="btn-icon" title="Bewerken" aria-label="Bewerken">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"></path></svg>
                        </a>
                        <?php if (Session::get('is_admin')): ?>
                            <form action="/matches/delete" method="POST" class="tb-inline" onsubmit="return confirm('Weet je zeker dat je deze wedstrijd wilt verwijderen?');">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::getToken() ?>">
                                <input type="hidden" name="id" value="<?= $match['id'] ?>">
                                <button type="submit" class="btn-icon delete tb-text-danger" title="Verwijderen" aria-label="Verwijderen">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<a href="/matches/create" class="tb-fab" title="Nieuwe Wedstrijd" aria-label="Nieuwe wedstrijd">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
</a>
