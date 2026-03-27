<?php
declare(strict_types=1);

/**
 * Sleutelrotatie script
 *
 * Gebruik: php scripts/rotate_encryption_key.php
 *
 * Dit script:
 *   1. Decodeert alle versleutelde API-sleutels in de database met de huidige sleutel
 *   2. Genereert een nieuwe encryptiesleutel
 *   3. Herversleutelt alle waarden met de nieuwe sleutel
 *   4. Slaat de nieuwe sleutel op in data/config.php
 *   5. Toont de SetEnv-instructie voor de Apache vhost-configuratie
 */

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/services/Encryption.php';

// ─── Kleurhelpers ──────────────────────────────────────────────────────────────

function kleur(string $tekst, string $code): string {
    return "\033[{$code}m{$tekst}\033[0m";
}
function groen(string $t): string  { return kleur($t, '0;32'); }
function rood(string $t): string   { return kleur($t, '0;31'); }
function geel(string $t): string   { return kleur($t, '0;33'); }
function blauw(string $t): string  { return kleur($t, '0;36'); }
function vet(string $t): string    { return kleur($t, '1'); }

// ─── Helpers ───────────────────────────────────────────────────────────────────

function leesRegel(string $vraag): string {
    echo $vraag;
    return trim((string) fgets(STDIN));
}

function bevestiging(string $vraag): bool {
    $antwoord = strtolower(leesRegel($vraag . ' [j/n]: '));
    return $antwoord === 'j' || $antwoord === 'y';
}

function fout(string $bericht): never {
    echo rood('FOUT: ') . $bericht . PHP_EOL;
    exit(1);
}

// ─── Decrypt met een expliciete sleutel (omzeilt Config singleton) ─────────────

function decryptMetSleutel(string $encoded, string $rawKey): string {
    $decoded = base64_decode($encoded, true);
    if ($decoded === false) {
        throw new RuntimeException('Ongeldige base64-data.');
    }
    if (strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        throw new RuntimeException('Data te kort om geldig te zijn.');
    }
    $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $rawKey);
    if ($plaintext === false) {
        throw new RuntimeException('Decryptie mislukt — verkeerde sleutel of beschadigde data.');
    }
    return $plaintext;
}

function encryptMetSleutel(string $plaintext, string $rawKey): string {
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $rawKey);
    return base64_encode($nonce . $ciphertext);
}

function parseSleutel(string $waarde): string {
    $waarde = trim($waarde);
    if (str_starts_with($waarde, 'base64:')) {
        $waarde = substr($waarde, 7);
    }
    $key = base64_decode($waarde, true);
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException('Ongeldige sleutellengte (verwacht 32 bytes).');
    }
    return $key;
}

// ─── Start ─────────────────────────────────────────────────────────────────────

echo PHP_EOL;
echo vet('══════════════════════════════════════════════') . PHP_EOL;
echo vet('   Voetbaltraining — Sleutelrotatie script   ') . PHP_EOL;
echo vet('══════════════════════════════════════════════') . PHP_EOL;
echo PHP_EOL;
echo 'Dit script vervangt de encryptiesleutel en herversleutelt' . PHP_EOL;
echo 'alle opgeslagen API-sleutels in de database.' . PHP_EOL;
echo PHP_EOL;

// ─── Stap 1: Huidige sleutel ophalen ──────────────────────────────────────────

echo blauw('[1/5]') . ' Huidige encryptiesleutel ophalen...' . PHP_EOL;

$oudeSleutelWaarde = Config::get('encryption_key', '');
if ($oudeSleutelWaarde === '' || $oudeSleutelWaarde === null) {
    fout('Geen huidige encryptiesleutel gevonden in data/config.php of APP_ENCRYPTION_KEY.');
}

try {
    $oudeRawKey = parseSleutel((string) $oudeSleutelWaarde);
} catch (RuntimeException $e) {
    fout('Huidige sleutel is ongeldig: ' . $e->getMessage());
}

$bron = (getenv('APP_ENCRYPTION_KEY') !== false) ? 'omgevingsvariabele APP_ENCRYPTION_KEY' : 'data/config.php';
echo '  Sleutel gevonden via ' . geel($bron) . '.' . PHP_EOL . PHP_EOL;

// ─── Stap 2: Versleutelde instellingen ophalen ─────────────────────────────────

echo blauw('[2/5]') . ' Versleutelde instellingen ophalen uit database...' . PHP_EOL;

$db = (new Database())->getConnection();

$sleutels = ['openrouter_api_key_enc', 'openrouter_management_api_key_enc', 'youtube_api_key_enc'];
$gevonden = [];

$stmt = $db->prepare('SELECT "key", value FROM app_settings WHERE "key" = :k');
foreach ($sleutels as $k) {
    $stmt->execute([':k' => $k]);
    $rij = $stmt->fetch();
    if ($rij && $rij['value'] !== null && $rij['value'] !== '') {
        $gevonden[$k] = $rij['value'];
        echo '  ' . groen('✓') . ' ' . $k . PHP_EOL;
    } else {
        echo '  ' . geel('–') . ' ' . $k . ' (niet ingesteld, wordt overgeslagen)' . PHP_EOL;
    }
}

if (empty($gevonden)) {
    echo PHP_EOL . geel('Geen versleutelde waarden gevonden. Er valt niets te roteren.') . PHP_EOL;
    echo 'Tip: sla daarna gewoon de nieuwe sleutel op als je toch wil rouleren.' . PHP_EOL;
}

echo PHP_EOL;

// ─── Stap 3: Decoderen met huidige sleutel ────────────────────────────────────

if (!empty($gevonden)) {
    echo blauw('[3/5]') . ' Decoderen met de huidige sleutel...' . PHP_EOL;

    $gedecodeerd = [];
    foreach ($gevonden as $k => $versleuteld) {
        try {
            $gedecodeerd[$k] = decryptMetSleutel($versleuteld, $oudeRawKey);
            echo '  ' . groen('✓') . ' ' . $k . ' succesvol gedecodeerd.' . PHP_EOL;
        } catch (RuntimeException $e) {
            fout("Kon '$k' niet decoderen: " . $e->getMessage()
                . "\nControleer of de huidige sleutel klopt.");
        }
    }
    echo PHP_EOL;
} else {
    $gedecodeerd = [];
    echo blauw('[3/5]') . ' Decoderen overgeslagen (geen versleutelde waarden).' . PHP_EOL . PHP_EOL;
}

// ─── Stap 4: Nieuwe sleutel genereren ─────────────────────────────────────────

echo blauw('[4/5]') . ' Nieuwe encryptiesleutel genereren...' . PHP_EOL;

if (!function_exists('sodium_crypto_secretbox_keygen')) {
    fout('PHP Sodium-extensie is niet beschikbaar. Installeer php-sodium.');
}

$nieuweRawKey = sodium_crypto_secretbox_keygen();
$nieuweSleutel = 'base64:' . base64_encode($nieuweRawKey);

echo '  ' . groen('✓') . ' Nieuwe sleutel gegenereerd.' . PHP_EOL . PHP_EOL;

// ─── Bevestiging vragen ────────────────────────────────────────────────────────

echo geel('Let op:') . ' Dit is een onomkeerbare actie. Zorg dat je een backup hebt.' . PHP_EOL;
if (!bevestiging('Wil je doorgaan?')) {
    echo PHP_EOL . 'Geannuleerd. Geen wijzigingen aangebracht.' . PHP_EOL;
    exit(0);
}
echo PHP_EOL;

// ─── Stap 5: Herversleutelen en opslaan ───────────────────────────────────────

echo blauw('[5/5]') . ' Herversleutelen en opslaan...' . PHP_EOL;

$db->beginTransaction();
try {
    $update = $db->prepare(
        'UPDATE app_settings SET value = :v, updated_at = CURRENT_TIMESTAMP WHERE "key" = :k'
    );
    foreach ($gedecodeerd as $k => $klaarTekst) {
        $herversleuteld = encryptMetSleutel($klaarTekst, $nieuweRawKey);
        $update->execute([':v' => $herversleuteld, ':k' => $k]);
        echo '  ' . groen('✓') . ' ' . $k . ' herversleuteld.' . PHP_EOL;
        sodium_memzero($klaarTekst);
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    sodium_memzero($nieuweRawKey);
    fout('Database-update mislukt: ' . $e->getMessage() . "\nGeen wijzigingen opgeslagen.");
}

// Nieuwe sleutel opslaan in data/config.php
$configPad = __DIR__ . '/../data/config.php';
$configInhoud = <<<PHP
<?php
return [
    'encryption_key' => '{$nieuweSleutel}',
];
PHP;

file_put_contents($configPad, $configInhoud);
chmod($configPad, 0640);
echo '  ' . groen('✓') . ' Nieuwe sleutel opgeslagen in data/config.php.' . PHP_EOL;

sodium_memzero($nieuweRawKey);

// ─── Resultaat ─────────────────────────────────────────────────────────────────

echo PHP_EOL;
echo groen('══════════════════════════════════════════════') . PHP_EOL;
echo groen('   Sleutelrotatie voltooid!                  ') . PHP_EOL;
echo groen('══════════════════════════════════════════════') . PHP_EOL;
echo PHP_EOL;
echo vet('Nieuwe sleutel:') . PHP_EOL;
echo '  ' . geel($nieuweSleutel) . PHP_EOL;
echo PHP_EOL;
echo vet('Aanbevolen:') . ' Zet de sleutel als omgevingsvariabele in je Apache vhost' . PHP_EOL;
echo 'zodat deze nooit per ongeluk in versiebeheer terechtkomt:' . PHP_EOL;
echo PHP_EOL;
echo '  ' . blauw('SetEnv APP_ENCRYPTION_KEY "' . $nieuweSleutel . '"') . PHP_EOL;
echo PHP_EOL;
echo 'Voeg deze regel toe aan je vhost-configuratie' . PHP_EOL;
echo '(bijv. /etc/apache2/sites-available/voetbaltraining.conf)' . PHP_EOL;
echo 'en verwijder daarna data/config.php:' . PHP_EOL;
echo PHP_EOL;
echo '  sudo nano /etc/apache2/sites-available/voetbaltraining.conf' . PHP_EOL;
echo '  sudo systemctl reload apache2' . PHP_EOL;
echo '  rm data/config.php' . PHP_EOL;
echo PHP_EOL;
