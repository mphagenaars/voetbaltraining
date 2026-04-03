<?php
declare(strict_types=1);

/**
 * Eenvoudige SMTP-mailclient zonder externe afhankelijkheden.
 * Ondersteunt STARTTLS (poort 587), SSL (poort 465) en plain (poort 25).
 * Authenticatie via AUTH LOGIN.
 */
class Mailer {

    /**
     * Verstuur een e-mail op basis van de opgeslagen SMTP-instellingen.
     *
     * @throws RuntimeException bij verbindings- of protocolfouten
     */
    public static function send(
        string $toAddress,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody = ''
    ): void {
        $settings = self::loadSettings();

        $host       = (string)($settings['smtp_host'] ?? '');
        $port       = (int)($settings['smtp_port'] ?? 587);
        $encryption = (string)($settings['smtp_encryption'] ?? 'tls');
        $username   = (string)($settings['smtp_username'] ?? '');
        $password   = self::decryptPassword($settings['smtp_password_enc'] ?? null);
        $fromAddr   = (string)($settings['smtp_from_address'] ?? '');
        $fromName   = (string)($settings['smtp_from_name'] ?? '');

        if ($host === '' || $fromAddr === '') {
            throw new RuntimeException('SMTP is niet geconfigureerd. Stel de mailinstellingen in via het admin panel.');
        }

        $smtp = new SmtpClient($host, $port, $encryption);
        $smtp->connect();
        if ($username !== '') {
            $smtp->authenticate($username, $password);
        }
        $smtp->sendMail(
            $fromAddr,
            $fromName,
            $toAddress,
            $toName,
            $subject,
            $htmlBody,
            $textBody !== '' ? $textBody : strip_tags($htmlBody)
        );
        $smtp->quit();
    }

    public static function isConfigured(): bool {
        $settings = self::loadSettings();
        return !empty($settings['smtp_host']) && !empty($settings['smtp_from_address']);
    }

    // ─── Intern ───────────────────────────────────────────────────────────────

    private static function loadSettings(): array {
        $pdo = (new Database())->getConnection();
        $setting = new AppSetting($pdo);
        return $setting->getMany([
            'smtp_host', 'smtp_port', 'smtp_encryption',
            'smtp_username', 'smtp_password_enc',
            'smtp_from_address', 'smtp_from_name',
        ]);
    }

    private static function decryptPassword(?string $encrypted): string {
        if ($encrypted === null || $encrypted === '') {
            return '';
        }
        try {
            return Encryption::decrypt($encrypted);
        } catch (Throwable) {
            return '';
        }
    }
}


/**
 * Lichtgewicht SMTP-protocol implementatie via PHP streams.
 */
class SmtpClient {
    private mixed $socket = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption  // 'tls', 'ssl' of 'none'
    ) {}

    public function connect(): void {
        $target = $this->encryption === 'ssl'
            ? "ssl://{$this->host}:{$this->port}"
            : "tcp://{$this->host}:{$this->port}";

        $this->socket = stream_socket_client(
            $target,
            $errno, $errstr,
            timeout: 15,
            flags: STREAM_CLIENT_CONNECT
        );

        if ($this->socket === false) {
            throw new RuntimeException("SMTP verbinding mislukt ({$this->host}:{$this->port}): $errstr ($errno)");
        }

        stream_set_timeout($this->socket, 15);
        $this->expect(220);

        $this->command("EHLO " . gethostname(), 250);

        if ($this->encryption === 'tls') {
            $this->command("STARTTLS", 220);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS mislukt: TLS-handshake niet gelukt.');
            }
            $this->command("EHLO " . gethostname(), 250);
        }
    }

    public function authenticate(string $username, string $password): void {
        $this->command("AUTH LOGIN", 334);
        $this->command(base64_encode($username), 334);
        $this->command(base64_encode($password), 235);
    }

    public function sendMail(
        string $fromAddr, string $fromName,
        string $toAddr, string $toName,
        string $subject,
        string $htmlBody, string $textBody
    ): void {
        $this->command("MAIL FROM:<{$fromAddr}>", 250);
        $this->command("RCPT TO:<{$toAddr}>", 250);
        $this->command("DATA", 354);

        $boundary = bin2hex(random_bytes(8));
        $date     = date('r');
        $msgId    = bin2hex(random_bytes(12)) . '@' . gethostname();

        $headers  = "Date: {$date}\r\n";
        $headers .= "Message-ID: <{$msgId}>\r\n";
        $headers .= "From: " . self::encodeHeader($fromName) . " <{$fromAddr}>\r\n";
        $headers .= "To: " . self::encodeHeader($toName) . " <{$toAddr}>\r\n";
        $headers .= "Subject: " . self::encodeHeader($subject) . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($textBody)) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        // Dot-stuffing: regels die beginnen met . krijgen een extra .
        $message = $headers . "\r\n" . $body;
        $message = preg_replace('/^\./', '..', $message);
        $message = preg_replace('/\r\n\./', "\r\n..", $message);

        $this->write($message . "\r\n.");
        $this->expect(250);
    }

    public function quit(): void {
        try {
            $this->command("QUIT", 221);
        } finally {
            if ($this->socket !== null) {
                fclose($this->socket);
                $this->socket = null;
            }
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function command(string $cmd, int $expectedCode): string {
        $this->write($cmd);
        return $this->expect($expectedCode);
    }

    private function write(string $data): void {
        fwrite($this->socket, $data . "\r\n");
    }

    private function expect(int $code): string {
        $response = '';
        while ($line = fgets($this->socket, 512)) {
            $response .= $line;
            // Meerregelige antwoorden eindigen als "NNN tekst" (zonder koppelteken)
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        $actual = (int)substr($response, 0, 3);
        if ($actual !== $code) {
            throw new RuntimeException("SMTP fout (verwacht {$code}, kreeg {$actual}): " . trim($response));
        }
        return $response;
    }

    private static function encodeHeader(string $value): string {
        if (!preg_match('/[^\x20-\x7E]/', $value)) {
            return $value;
        }
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
