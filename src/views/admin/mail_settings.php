<div class="app-bar">
    <div class="app-bar-start">
        <a href="/admin" class="btn-icon-round" title="Terug">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>
        <h1 class="app-bar-title">E-mailinstellingen</h1>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
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

<div class="card" style="margin-bottom: 1.5rem;">
    <h2 class="ai-admin-card-title">SMTP-configuratie</h2>

    <form action="/admin/mail/save" method="POST">
        <?= Csrf::renderInput() ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
            <div class="form-group" style="margin: 0;">
                <label>SMTP-host <span style="color: #e53e3e;">*</span></label>
                <input type="text" name="smtp_host" class="form-control" value="<?= $host ?>"
                       placeholder="smtp.gmail.com" required>
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Poort <span style="color: #e53e3e;">*</span></label>
                <input type="number" name="smtp_port" class="form-control" value="<?= $port ?>"
                       placeholder="587" min="1" max="65535" required>
            </div>
        </div>

        <div class="form-group">
            <label>Beveiliging</label>
            <div style="display: flex; gap: 1.5rem; margin-top: 0.25rem;">
                <?php foreach (['tls' => 'STARTTLS (poort 587)', 'ssl' => 'SSL/TLS (poort 465)', 'none' => 'Geen'] as $val => $label): ?>
                    <label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 0.4rem;">
                        <input type="radio" name="smtp_encryption" value="<?= $val ?>"
                               <?= $encryption === $val ? 'checked' : '' ?>>
                        <?= $label ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
            <div class="form-group" style="margin: 0;">
                <label>Gebruikersnaam</label>
                <input type="text" name="smtp_username" class="form-control" value="<?= $username ?>"
                       placeholder="jouw@gmail.com" autocomplete="off">
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Wachtwoord</label>
                <?php if ($hasPassword): ?>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="password" name="smtp_password" class="form-control"
                               placeholder="Laat leeg om huidig te behouden" autocomplete="new-password">
                        <form action="/admin/mail/delete-password" method="POST" style="margin: 0; flex-shrink: 0;">
                            <?= Csrf::renderInput() ?>
                            <button type="submit" class="btn-icon btn-icon-danger"
                                    onclick="return confirm('Wachtwoord verwijderen?')" title="Wachtwoord verwijderen">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            </button>
                        </form>
                    </div>
                    <small style="color: #6c757d;">Wachtwoord is ingesteld. Laat leeg om het te bewaren.</small>
                <?php else: ?>
                    <input type="password" name="smtp_password" class="form-control"
                           placeholder="SMTP-wachtwoord of app-wachtwoord" autocomplete="new-password">
                <?php endif; ?>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            <div class="form-group" style="margin: 0;">
                <label>Afzenderadres <span style="color: #e53e3e;">*</span></label>
                <input type="email" name="smtp_from_address" class="form-control" value="<?= $fromAddr ?>"
                       placeholder="noreply@jouwclub.nl" required>
            </div>
            <div class="form-group" style="margin: 0;">
                <label>Afzendernaam</label>
                <input type="text" name="smtp_from_name" class="form-control" value="<?= $fromName ?>"
                       placeholder="Voetbaltraining">
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
            <button type="submit" class="btn-icon-square" title="Opslaan" aria-label="Opslaan">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1-2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            </button>

            <details style="display: inline;">
                <summary class="btn-icon" style="cursor: pointer; list-style: none;" title="Populaire providers">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                </summary>
                <div style="position: absolute; background: white; border: 1px solid #dee2e6; border-radius: 0.5rem; padding: 0.75rem; margin-top: 0.5rem; min-width: 320px; z-index: 10; box-shadow: 0 4px 12px rgba(0,0,0,.1);">
                    <table style="width: 100%; font-size: 0.875rem; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 1px solid #eee;">
                                <th style="padding: 0.4rem 0.5rem; text-align: left;">Provider</th>
                                <th style="padding: 0.4rem 0.5rem; text-align: left;">Host</th>
                                <th style="padding: 0.4rem 0.5rem; text-align: left;">Poort</th>
                                <th style="padding: 0.4rem 0.5rem; text-align: left;">Beveiliging</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td style="padding: 0.4rem 0.5rem;">Gmail</td><td>smtp.gmail.com</td><td>587</td><td>STARTTLS</td></tr>
                            <tr><td style="padding: 0.4rem 0.5rem;">Outlook/Hotmail</td><td>smtp-mail.outlook.com</td><td>587</td><td>STARTTLS</td></tr>
                            <tr><td style="padding: 0.4rem 0.5rem;">Brevo</td><td>smtp-relay.brevo.com</td><td>587</td><td>STARTTLS</td></tr>
                            <tr><td style="padding: 0.4rem 0.5rem;">Mailgun</td><td>smtp.mailgun.org</td><td>587</td><td>STARTTLS</td></tr>
                        </tbody>
                    </table>
                    <p style="margin: 0.5rem 0 0; color: #6c757d; font-size: 0.8rem;">
                        Gmail vereist een app-wachtwoord: Google Account → Beveiliging → App-wachtwoorden.
                    </p>
                </div>
            </details>
        </div>
    </form>
</div>

<div class="card">
    <h2 class="ai-admin-card-title">Testmail versturen</h2>
    <p style="color: #666; margin-bottom: 1rem;">
        Verstuur een testbericht om te controleren of de instellingen correct zijn.
    </p>
    <form action="/admin/mail/test" method="POST"
          style="display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap;">
        <?= Csrf::renderInput() ?>
        <div class="form-group" style="margin: 0; flex: 1; min-width: 220px;">
            <label>Stuur testmail naar</label>
            <input type="email" name="test_email" class="form-control"
                   placeholder="jouw@emailadres.nl" required>
        </div>
        <button type="submit" class="btn-icon-square" title="Verstuur testmail" aria-label="Verstuur testmail">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
        </button>
    </form>
</div>
