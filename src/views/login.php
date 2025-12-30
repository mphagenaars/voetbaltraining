<div class="card" style="max-width: 400px; margin: 0 auto;">
    <h1 class="mb-2">Inloggen</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/login">
        <?= Csrf::renderInput() ?>
        <div>
            <label for="username">Gebruikersnaam</label>
            <input type="text" id="username" name="username" required autofocus>
        </div>
        <div>
            <label for="password">Wachtwoord</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div style="margin-bottom: 1rem;">
            <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: normal; cursor: pointer;">
                <input type="checkbox" name="remember_me" value="1"> Onthoud mij
            </label>
        </div>
        <button type="submit" class="btn" style="width: 100%;">Inloggen</button>
    </form>

    <div class="text-center mt-2">
        <a href="/" style="font-size: 0.875rem;">Terug naar home</a>
    </div>
</div>
