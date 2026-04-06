<div class="tb-card tb-auth-card">
    <h1 class="mb-2">Registreren</h1>
    <p class="text-muted mb-2">Gebruik de invite code van je team.</p>
    
    <?php if (isset($error)): ?>
        <div class="tb-alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/register">
        <?= Csrf::renderInput() ?>
        <div>
            <label for="invite_code">Invite Code</label>
            <input type="text" id="invite_code" name="invite_code" required value="<?= e($_POST['invite_code'] ?? '') ?>">
        </div>
        <div>
            <label for="name">Volledige naam</label>
            <input type="text" id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>">
        </div>
        <div>
            <label for="username">Gebruikersnaam</label>
            <input type="text" id="username" name="username" required value="<?= e($_POST['username'] ?? '') ?>">
        </div>
        <div>
            <label for="password">Wachtwoord</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="tb-button tb-button--primary tb-auth-submit">Registreren</button>
    </form>

    <div class="text-center mt-2">
        <p>Heb je al een account? <a href="/login">Log in</a></p>
    </div>
</div>
