<?php include __DIR__ . '/../partials/header.php'; ?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1>Admin Dashboard</h1>
            <p class="lead">Beheer de applicatie instellingen en gebruikers.</p>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Users Card -->
        <div class="col-md-4">
            <div class="card text-white bg-primary mb-3">
                <div class="card-header">Gebruikers</div>
                <div class="card-body">
                    <h5 class="card-title"><?= $stats['users'] ?? 0 ?> Geregistreerd</h5>
                    <p class="card-text">Beheer accounts en rechten.</p>
                    <a href="/admin/users" class="btn btn-light btn-sm">Naar Gebruikers</a>
                </div>
            </div>
        </div>

        <!-- Exercises Card -->
        <div class="col-md-4">
            <div class="card text-white bg-success mb-3">
                <div class="card-header">Oefeningen</div>
                <div class="card-body">
                    <h5 class="card-title"><?= $stats['exercises'] ?? 0 ?> Totaal</h5>
                    <p class="card-text">Bekijk alle oefeningen in de database.</p>
                    <a href="/exercises" class="btn btn-light btn-sm">Naar Oefeningen</a>
                </div>
            </div>
        </div>

        <!-- Trainings Card -->
        <div class="col-md-4">
            <div class="card text-white bg-info mb-3">
                <div class="card-header">Trainingen</div>
                <div class="card-body">
                    <h5 class="card-title"><?= $stats['trainings'] ?? 0 ?> Aangemaakt</h5>
                    <p class="card-text">Overzicht van alle trainingen.</p>
                    <a href="/trainings" class="btn btn-light btn-sm">Naar Trainingen</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    Snelkoppelingen
                </div>
                <div class="list-group list-group-flush">
                    <a href="/admin/users/create" class="list-group-item list-group-item-action">Nieuwe gebruiker aanmaken</a>
                    <a href="/exercises/create" class="list-group-item list-group-item-action">Nieuwe oefening tekenen</a>
                    <a href="/profile" class="list-group-item list-group-item-action">Mijn profiel bewerken</a>
                </div>
            </div>

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
                            <li class="list-group-item">
                                <small class="text-muted"><?= htmlspecialchars($log['created_at']) ?></small><br>
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

<?php include __DIR__ . '/../partials/footer.php'; ?>
