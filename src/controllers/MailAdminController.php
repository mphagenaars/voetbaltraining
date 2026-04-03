<?php
declare(strict_types=1);

class MailAdminController extends BaseController {

    private AppSetting $settings;

    public function __construct(PDO $pdo) {
        parent::__construct($pdo);
        $this->settings = new AppSetting($pdo);
    }

    public function settings(): void {
        $this->requireAdmin();

        $current = $this->settings->getMany([
            'smtp_host', 'smtp_port', 'smtp_encryption',
            'smtp_username', 'smtp_password_enc',
            'smtp_from_address', 'smtp_from_name',
        ]);

        View::render('admin/mail_settings', [
            'pageTitle'   => 'Mailinstellingen',
            'settings'    => $current,
            'hasPassword' => !empty($current['smtp_password_enc']),
        ]);
    }

    public function saveSettings(): void {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/mail/settings');
        }

        $this->verifyCsrf('/admin/mail/settings');

        $host       = trim($_POST['smtp_host'] ?? '');
        $port       = (int)($_POST['smtp_port'] ?? 587);
        $encryption = $_POST['smtp_encryption'] ?? 'tls';
        $username   = trim($_POST['smtp_username'] ?? '');
        $password   = $_POST['smtp_password'] ?? '';
        $fromAddr   = trim($_POST['smtp_from_address'] ?? '');
        $fromName   = trim($_POST['smtp_from_name'] ?? '');

        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            $encryption = 'tls';
        }

        if ($port < 1 || $port > 65535) {
            Session::flash('error', 'Ongeldig poortnummer.');
            $this->redirect('/admin/mail/settings');
        }

        if ($host === '' || $fromAddr === '') {
            Session::flash('error', 'SMTP-host en afzenderadres zijn verplicht.');
            $this->redirect('/admin/mail/settings');
        }

        $this->settings->setMany([
            'smtp_host'         => $host,
            'smtp_port'         => (string)$port,
            'smtp_encryption'   => $encryption,
            'smtp_username'     => $username,
            'smtp_from_address' => $fromAddr,
            'smtp_from_name'    => $fromName,
        ]);

        // Wachtwoord alleen opslaan als er een nieuw is ingevuld
        if ($password !== '') {
            if (!Config::hasEncryptionKey()) {
                Session::flash('error', 'Encryptiesleutel ontbreekt — wachtwoord niet opgeslagen.');
                $this->redirect('/admin/mail/settings');
            }
            $this->settings->set('smtp_password_enc', Encryption::encrypt($password));
        }

        Session::flash('success', 'Mailinstellingen opgeslagen.');
        $this->redirect('/admin/mail/settings');
    }

    public function deletePassword(): void {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/mail/settings');
        }

        $this->verifyCsrf('/admin/mail/settings');
        $this->settings->set('smtp_password_enc', null);

        Session::flash('success', 'SMTP-wachtwoord verwijderd.');
        $this->redirect('/admin/mail/settings');
    }

    public function sendTestMail(): void {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/mail/settings');
        }

        $this->verifyCsrf('/admin/mail/settings');

        $toAddress = trim($_POST['test_email'] ?? '');
        if (filter_var($toAddress, FILTER_VALIDATE_EMAIL) === false) {
            Session::flash('error', 'Voer een geldig e-mailadres in voor de testmail.');
            $this->redirect('/admin/mail/settings');
        }

        try {
            Mailer::send(
                toAddress: $toAddress,
                toName:    $toAddress,
                subject:   'Testmail — Voetbaltraining',
                htmlBody:  '<p>Dit is een testmail van je Voetbaltraining installatie.</p><p>Als je dit bericht ontvangt, werken de mailinstellingen correct.</p>',
            );
            Session::flash('success', "Testmail verzonden naar {$toAddress}.");
        } catch (Throwable $e) {
            Session::flash('error', 'Verzenden mislukt: ' . $e->getMessage());
        }

        $this->redirect('/admin/mail/settings');
    }
}
