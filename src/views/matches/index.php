<div class="container">
    <div class="header-actions">
        <h1>Wedstrijden</h1>
        <a href="/matches/create" class="btn btn-primary">Nieuwe Wedstrijd</a>
    </div>

    <div class="grid-container">
        <?php if (empty($matches)): ?>
            <p>Nog geen wedstrijden aangemaakt.</p>
        <?php else: ?>
            <?php foreach ($matches as $match): ?>
                <div class="card">
                    <h3><?= htmlspecialchars($match['opponent']) ?> (<?= $match['is_home'] ? 'Thuis' : 'Uit' ?>)</h3>
                    <p>Datum: <?= htmlspecialchars(date('d-m-Y H:i', strtotime($match['date']))) ?></p>
                    <p>Uitslag: <?= $match['score_home'] ?> - <?= $match['score_away'] ?></p>
                    <div class="actions">
                        <a href="/matches/view?id=<?= $match['id'] ?>" class="btn btn-secondary">Bekijken</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
