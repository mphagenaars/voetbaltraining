<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';

echo "=== Admin Account Aanmaken ===\n";

// Functie om input te vragen
function prompt(string $message): string {
    echo $message . ": ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    return trim($line);
}

$username = prompt("Kies een gebruikersnaam (standaard: admin)");
if (empty($username)) {
    $username = 'admin';
}

$name = prompt("Volledige naam (standaard: Hoofdtrainer)");
if (empty($name)) {
    $name = 'Hoofdtrainer';
}

// Wachtwoord vragen (met simpele check)
do {
    $password = prompt("Kies een wachtwoord (minimaal 8 tekens)");
    if (strlen($password) < 8) {
        echo "Wachtwoord is te kort. Probeer opnieuw.\n";
    }
} while (strlen($password) < 8);

echo "\nSamenvatting:\n";
echo "Gebruikersnaam: $username\n";
echo "Naam: $name\n";
echo "Wachtwoord: ********\n";

try {
    $db = (new Database())->getConnection();
    
    // Check of user al bestaat
    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    if ($stmt->fetch()) {
        echo "\n❌ Fout: Gebruiker '$username' bestaat al.\n";
        exit(1);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare("INSERT INTO users (username, password_hash, name) VALUES (:username, :hash, :name)");
    $stmt->execute([
        ':username' => $username,
        ':hash' => $hash,
        ':name' => $name
    ]);

    echo "\n✅ Admin account succesvol aangemaakt!\n";

} catch (PDOException $e) {
    echo "\n❌ Database Fout: " . $e->getMessage() . "\n";
    exit(1);
}
