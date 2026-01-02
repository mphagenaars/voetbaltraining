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
            <div class="card">
                <div class="card-header">
                    Snelkoppelingen
                </div>
                <div class="list-group list-group-flush">
                    <a href="/admin/users/create" class="list-group-item list-group-item-action">Nieuwe gebruiker aanmaken</a>
                    <a href="/exercises/create" class="list-group-item list-group-item-action">Nieuwe oefening tekenen</a>
                    <a href="/profile" class="list-group-item list-group-item-action">Mijn profiel bewerken</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
