<div class="app-bar">
    <div class="app-bar-start">
        <a href="/admin" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">E-mailinstellingen</h1>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="tb-alert tb-alert--success"><?= e($success) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="tb-alert tb-alert--danger"><?= e($error) ?></div>
<?php endif; ?>

<?php
$host       = e($settings['smtp_host'] ?? '');
$port       = e($settings['smtp_port'] ?? '587');
$encryption = $settings['smtp_encryption'] ?? 'tls';
$username   = e($settings['smtp_username'] ?? '');
$fromAddr   = e($settings['smtp_from_address'] ?? '');
$fromName   = e($settings['smtp_from_name'] ?? '');
$hasPassword = !empty($hasPassword);
?>

<div class="tb-card tb-mail-card">
    <h2 class="ai-admin-card-title">SMTP-configuratie</h2>

    <form action="/admin/mail/save" method="POST">
        <?= Csrf::renderInput() ?>

        <div class="tb-mail-grid">
            <div class="form-group tb-compact-form-group">
                <label>SMTP-host <span class="tb-required">*</span></label>
                <input type="text" name="smtp_host" class="form-control" value="<?= $host ?>"
                       placeholder="smtp.gmail.com" required>
            </div>
            <div class="form-group tb-compact-form-group">
                <label>Poort <span class="tb-required">*</span></label>
                <input type="number" name="smtp_port" class="form-control" value="<?= $port ?>"
                       placeholder="587" min="1" max="65535" required>
            </div>
        </div>

        <div class="form-group">
            <label>Beveiliging</label>
            <div class="tb-mail-security-options">
                <?php foreach (['tls' => 'STARTTLS (poort 587)', 'ssl' => 'SSL/TLS (poort 465)', 'none' => 'Geen'] as $val => $label): ?>
                    <label class="tb-checkbox-inline">
                        <input type="radio" name="smtp_encryption" value="<?= $val ?>"
                               <?= $encryption === $val ? 'checked' : '' ?>>
                        <?= $label ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="tb-mail-grid">
            <div class="form-group tb-compact-form-group">
                <label>Gebruikersnaam</label>
                <input type="text" name="smtp_username" class="form-control" value="<?= $username ?>"
                       placeholder="jouw@gmail.com" autocomplete="off">
            </div>
            <div class="form-group tb-compact-form-group">
                <label>Wachtwoord</label>
                <?php if ($hasPassword): ?>
                    <div class="tb-mail-password-row">
                        <input type="password" name="smtp_password" class="form-control"
                               placeholder="Laat leeg om huidig te behouden" autocomplete="new-password">
                        <button
                            type="submit"
                            class="tb-icon-button tb-icon-button--danger"
                            title="Wachtwoord verwijderen"
                            aria-label="Wachtwoord verwijderen"
                            formaction="/admin/mail/delete-password"
                            formmethod="POST"
                            formnovalidate
                            onclick="return confirm('Wachtwoord verwijderen?')"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    </div>
                    <small class="tb-mail-password-hint">Wachtwoord is ingesteld. Laat leeg om het te bewaren.</small>
                <?php else: ?>
                    <input type="password" name="smtp_password" class="form-control"
                           placeholder="SMTP-wachtwoord of app-wachtwoord" autocomplete="new-password">
                <?php endif; ?>
            </div>
        </div>

        <div class="tb-mail-grid">
            <div class="form-group tb-compact-form-group">
                <label>Afzenderadres <span class="tb-required">*</span></label>
                <input type="email" name="smtp_from_address" class="form-control" value="<?= $fromAddr ?>"
                       placeholder="noreply@jouwclub.nl" required>
            </div>
            <div class="form-group tb-compact-form-group">
                <label>Afzendernaam</label>
                <input type="text" name="smtp_from_name" class="form-control" value="<?= $fromName ?>"
                       placeholder="Voetbaltraining">
            </div>
        </div>

        <div class="tb-mail-actions">
            <button type="submit" class="tb-button tb-button--primary btn-inline-icon" title="Opslaan">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1-2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                Opslaan
            </button>

            <details class="tb-mail-provider-dropdown">
                <summary class="tb-icon-button" title="Populaire providers" aria-label="Populaire providers">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                </summary>
                <div class="tb-mail-provider-panel">
                    <table class="tb-mail-provider-table">
                        <thead>
                            <tr>
                                <th>Provider</th>
                                <th>Host</th>
                                <th>Poort</th>
                                <th>Beveiliging</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>Gmail</td><td>smtp.gmail.com</td><td>587</td><td>STARTTLS</td></tr>
                            <tr><td>Outlook/Hotmail</td><td>smtp-mail.outlook.com</td><td>587</td><td>STARTTLS</td></tr>
                            <tr><td>Brevo</td><td>smtp-relay.brevo.com</td><td>587</td><td>STARTTLS</td></tr>
                            <tr><td>Mailgun</td><td>smtp.mailgun.org</td><td>587</td><td>STARTTLS</td></tr>
                        </tbody>
                    </table>
                    <p class="tb-mail-provider-help">
                        Gmail vereist een app-wachtwoord: Google Account → Beveiliging → App-wachtwoorden.
                    </p>
                </div>
            </details>
        </div>
    </form>
</div>

<div class="tb-card">
    <h2 class="ai-admin-card-title">Testmail versturen</h2>
    <p class="tb-mail-test-note">
        Verstuur een testbericht om te controleren of de instellingen correct zijn.
    </p>
    <form action="/admin/mail/test" method="POST" class="tb-mail-test-form">
        <?= Csrf::renderInput() ?>
        <div class="form-group tb-mail-test-field">
            <label>Stuur testmail naar</label>
            <input type="email" name="test_email" class="form-control"
                   placeholder="jouw@emailadres.nl" required>
        </div>
        <button type="submit" class="tb-button tb-button--secondary btn-inline-icon" title="Verstuur testmail">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
            Versturen
        </button>
    </form>
</div>
