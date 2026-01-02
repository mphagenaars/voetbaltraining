<?php include __DIR__ . '/../../layout/header.php'; ?>

<div class="container">
    <div class="header-actions">
        <div>
            <h1>Systeem Logs</h1>
            <p class="lead" style="margin-bottom: 0;">Inzicht in gebruik en activiteit.</p>
        </div>
        <a href="/admin" class="btn btn-outline">Terug</a>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">Populaire Oefeningen (Views)</div>
                <ul class="list-group list-group-flush">
                    <?php if (empty($stats['popular_exercises'])): ?>
                        <li class="list-group-item text-muted">Nog geen data.</li>
                    <?php else: ?>
                        <?php foreach ($stats['popular_exercises'] as $ex): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($ex['details'] ?? 'Onbekende oefening') ?>
                                <span class="badge bg-primary rounded-pill"><?= $ex['count'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">Recente Activiteit</div>
                <ul class="list-group list-group-flush">
                    <?php if (empty($stats['recent_activity'])): ?>
                        <li class="list-group-item text-muted">Geen activiteit gevonden.</li>
                    <?php else: ?>
                        <?php foreach ($stats['recent_activity'] as $log): ?>
                            <li class="list-group-item" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <small class="text-muted" style="margin-right: 0.5rem;"><?= htmlspecialchars($log['created_at']) ?></small>
                                <strong><?= htmlspecialchars($log['user_name'] ?? 'Onbekend') ?></strong>: 
                                <?= htmlspecialchars($log['action']) ?>
                                <?php if ($log['details']): ?>
                                    <span class="text-muted">- <?= htmlspecialchars($log['details']) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../layout/footer.php'; ?>
