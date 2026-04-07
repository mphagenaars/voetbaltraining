<div class="tb-card tb-auth-card">
    <div class="tb-auth-logo tb-auth-logo--large">
        <img src="/images/logo.png" alt="Trainer Bobby – Train Smarter, Play Better">
    </div>
    <h1 class="mb-2">Inloggen</h1>
    
    <?php if (isset($error)): ?>
        <div class="tb-alert"><?= e($error) ?></div>
    <?php endif; ?>

    <?php $action = '/login' . (isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''); ?>
    <form method="POST" action="<?= $action ?>">
        <?= Csrf::renderInput() ?>
        <div>
            <label for="username">Gebruikersnaam</label>
            <input type="text" id="username" name="username" required autofocus>
        </div>
        <div>
            <label for="password">Wachtwoord</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="tb-auth-remember">
            <label class="tb-checkbox-inline">
                <input type="checkbox" name="remember_me" value="1"> Onthoud mij
            </label>
        </div>
        <button type="submit" class="tb-button tb-button--primary tb-auth-submit">Inloggen</button>
    </form>

    <div class="text-center tb-auth-hint">
        Wachtwoord vergeten? Neem contact op met je beheerder.
    </div>
</div>
