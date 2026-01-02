<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';

echo "=== Admin Rechten Toewijzen ===\n";

function prompt(string $message): string {
    echo $message . ": ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    return trim($line);
}

$username = prompt("Welke gebruiker moet admin worden? (gebruikersnaam)");

if (empty($username)) {
    echo "Geen gebruikersnaam opgegeven.\n";
    exit(1);
}

try {
    $db = (new Database())->getConnection();
    
    // Check of user bestaat
    $stmt = $db->prepare("SELECT id, name, is_admin FROM users WHERE username = :username COLLATE NOCASE");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        echo "\n❌ Fout: Gebruiker '$username' niet gevonden.\n";
        exit(1);
    }

    if ($user['is_admin']) {
        echo "\nℹ️  Gebruiker '$username' is al admin.\n";
        exit(0);
    }

    // Update user
    $stmt = $db->prepare("UPDATE users SET is_admin = 1 WHERE id = :id");
    $stmt->execute([':id' => $user['id']]);

    echo "\n✅ Gebruiker '$username' ({$user['name']}) is nu admin!\n";

} catch (PDOException $e) {
    echo "\n❌ Database Fout: " . $e->getMessage() . "\n";
    exit(1);
}
