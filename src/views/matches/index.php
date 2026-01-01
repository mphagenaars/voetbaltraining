<div class="header-actions">
    <h1>Wedstrijden</h1>
    <a href="/matches/create" class="btn btn-outline">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 4px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        Nieuwe Wedstrijd
    </a>
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
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
