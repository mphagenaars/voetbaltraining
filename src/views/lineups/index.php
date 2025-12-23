<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="container">
    <div class="header-actions">
        <h1>Opstellingen</h1>
        <a href="/lineups/create" class="btn btn-primary">Nieuwe Opstelling</a>
    </div>

    <div class="grid-container">
        <?php if (empty($lineups)): ?>
            <p>Nog geen opstellingen aangemaakt.</p>
        <?php else: ?>
            <?php foreach ($lineups as $lineup): ?>
                <div class="card">
                    <h3><?= htmlspecialchars($lineup['name']) ?></h3>
                    <p>Formatie: <?= htmlspecialchars($lineup['formation']) ?></p>
                    <p><small>Aangemaakt op: <?= htmlspecialchars($lineup['created_at']) ?></small></p>
                    <div class="actions">
                        <a href="/lineups/view?id=<?= $lineup['id'] ?>" class="btn btn-secondary">Bekijken/Bewerken</a>
                        <form action="/lineups/delete" method="POST" style="display:inline;" onsubmit="return confirm('Weet je zeker dat je deze opstelling wilt verwijderen?');">
                            <input type="hidden" name="id" value="<?= $lineup['id'] ?>">
                            <button type="submit" class="btn btn-danger">Verwijderen</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
