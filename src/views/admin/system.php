<?php include __DIR__ . '/../../layout/header.php'; ?>

<div class="container">
    <div class="app-bar">
    <div class="app-bar-start">
        <a href="/admin" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">Systeem Logs</h1>
    </div>
</div>
<p class="tb-system-lead">Inzicht in gebruik en activiteit.</p>

    <div class="tb-system-grid">
        <section class="card tb-system-card">
            <h2 class="tb-system-card-title">Populaire Oefeningen (Views)</h2>
            <ul class="tb-system-list">
                <?php if (empty($stats['popular_exercises'])): ?>
                    <li class="tb-system-list-item tb-muted">Nog geen data.</li>
                <?php else: ?>
                    <?php foreach ($stats['popular_exercises'] as $ex): ?>
                        <li class="tb-system-list-item">
                            <span><?= htmlspecialchars($ex['details'] ?? 'Onbekende oefening') ?></span>
                            <span class="tb-system-pill"><?= $ex['count'] ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </section>

        <section class="card tb-system-card">
            <h2 class="tb-system-card-title">Recente Activiteit</h2>
            <ul class="tb-system-list">
                <?php if (empty($stats['recent_activity'])): ?>
                    <li class="tb-system-list-item tb-muted">Geen activiteit gevonden.</li>
                <?php else: ?>
                    <?php foreach ($stats['recent_activity'] as $log): ?>
                        <li class="tb-system-activity-item">
                            <small class="tb-muted tb-system-activity-date"><?= htmlspecialchars($log['created_at']) ?></small>
                            <strong><?= htmlspecialchars($log['user_name'] ?? 'Onbekend') ?></strong>:
                            <?= htmlspecialchars($log['action']) ?>
                            <?php if ($log['details']): ?>
                                <span class="tb-muted">- <?= htmlspecialchars($log['details']) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </section>
    </div>
</div>

<?php include __DIR__ . '/../../layout/footer.php'; ?>
