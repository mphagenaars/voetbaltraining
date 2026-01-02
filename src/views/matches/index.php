<div class="header-actions">
    <h1>Wedstrijden</h1>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <?php $nextFilter = ($currentFilter ?? 'all') === 'all' ? 'upcoming' : 'all'; ?>
        <a href="/matches?filter=<?= $nextFilter ?>" class="btn <?= ($currentFilter ?? 'all') === 'upcoming' ? 'btn-primary' : 'btn-outline' ?>" title="<?= ($currentFilter ?? 'all') === 'all' ? 'Verberg gespeelde wedstrijden' : 'Toon alle wedstrijden' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
            </svg>
        </a>
        <?php $nextSort = ($currentSort ?? 'desc') === 'desc' ? 'asc' : 'desc'; ?>
        <a href="/matches?sort=<?= $nextSort ?>" class="btn btn-outline" title="<?= ($currentSort ?? 'desc') === 'desc' ? 'Sorteer: Oudste eerst' : 'Sorteer: Nieuwste eerst' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <?php if (($currentSort ?? 'desc') === 'desc'): ?>
                    <path d="M6 4v16"/><path d="M6 20l-3-3"/><path d="M6 20l3-3"/>
                    <path d="M12 6h8"/><path d="M12 12h6"/><path d="M12 18h4"/>
                <?php else: ?>
                    <path d="M6 4v16"/><path d="M6 20l-3-3"/><path d="M6 20l3-3"/>
                    <path d="M12 6h4"/><path d="M12 12h6"/><path d="M12 18h8"/>
                <?php endif; ?>
            </svg>
        </a>
        <a href="/matches/create" class="btn btn-outline">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Nieuwe Wedstrijd
        </a>
        <a href="/" class="btn btn-outline">Terug</a>
    </div>
</div>

<?php if (empty($matches)): ?>
    <div class="card">
        <p>Nog geen wedstrijden aangemaakt.</p>
    </div>
<?php else: ?>
    <div class="match-list">
        <?php foreach ($matches as $match): ?>
            <div class="action-card" onclick="location.href='/matches/view?id=<?= $match['id'] ?>'" style="display: flex; align-items: center; justify-content: space-between;">
                <div style="flex: 1;">
                    <h3><?= e($match['opponent']) ?> (<?= $match['is_home'] ? 'Thuis' : 'Uit' ?>)</h3>
                    <div style="font-size: 0.9rem; color: var(--text-muted); display: flex; gap: 1rem;">
                        <span>📅 <?= e(date('d-m-Y H:i', strtotime($match['date']))) ?></span>
                        <span>⚽ <?= $match['score_home'] ?> - <?= $match['score_away'] ?></span>
                    </div>
                </div>
                
                <div style="display: flex; align-items: center;">
                    <div style="display: flex; gap: 0.5rem;" onclick="event.stopPropagation();">
                        <a href="/matches/view?id=<?= $match['id'] ?>" class="btn-icon" title="Bekijken">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </a>
                        <?php if (Session::get('is_admin')): ?>
                            <form action="/matches/delete" method="POST" style="display: inline;" onsubmit="return confirm('Weet je zeker dat je deze wedstrijd wilt verwijderen?');">
                                <input type="hidden" name="csrf_token" value="<?= Csrf::getToken() ?>">
                                <input type="hidden" name="id" value="<?= $match['id'] ?>">
                                <button type="submit" class="btn-icon delete" title="Verwijderen" style="color: var(--danger-color);">
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
