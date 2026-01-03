<div class="header-actions">
    <h1>Mijn Account</h1>
    <a href="/" class="btn btn-outline">Terug</a>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="grid-2">
    <!-- Blok 1: Profiel -->
    <div class="card">
        <h2>Profiel</h2>
        <form action="/account/update-profile" method="POST">
            <?= Csrf::renderInput() ?>
            
            <div class="form-group">
                <label>Gebruikersnaam</label>
                <input type="text" value="<?= e($user['username']) ?>" disabled class="form-control" style="background-color: #f0f0f0;">
                <small class="text-muted">Je gebruikersnaam kan niet gewijzigd worden.</small>
            </div>

            <div class="form-group">
                <label for="name">Naam</label>
                <input type="text" id="name" name="name" value="<?= e($user['name']) ?>" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Mijn Rollen</label>
                <ul class="list-group">
                    <?php foreach ($teams as $team): ?>
                        <?php
                            $roleParts = [];
                            if (!empty($team['is_coach'])) $roleParts[] = 'Coach';
                            if (!empty($team['is_trainer'])) $roleParts[] = 'Trainer';
                            $roleString = implode(' & ', $roleParts);
                        ?>
                        <li class="list-group-item">
                            <strong><?= e($team['name']) ?></strong>: 
                            <span class="badge"><?= e($roleString) ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($teams)): ?>
                        <li class="list-group-item text-muted">Je bent nog geen lid van een team.</li>
                    <?php endif; ?>
                </ul>
                <small class="text-muted">Rollen kunnen alleen door een team-beheerder gewijzigd worden.</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-outline">Opslaan</button>
            </div>
        </form>
    </div>

    <!-- Blok 2: Beveiliging -->
    <div class="card">
        <h2>Wachtwoord Wijzigen</h2>
        <form action="/account/update-password" method="POST">
            <?= Csrf::renderInput() ?>
            
            <div class="form-group">
                <label for="current_password">Huidig wachtwoord</label>
                <input type="password" id="current_password" name="current_password" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="new_password">Nieuw wachtwoord</label>
                <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8">
                <small class="text-muted">Minimaal 8 tekens.</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Bevestig nieuw wachtwoord</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-outline">Wachtwoord Wijzigen</button>
            </div>
        </form>
    </div>
</div>

