<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';

$username = 'admin';
$password = 'Welkom01!';
$name = 'Hoofdtrainer';

echo "Aanmaken admin account...\n";
echo "Gebruikersnaam: $username\n";
echo "Wachtwoord: $password\n";

try {
    $db = (new Database())->getConnection();
    
    // Check of user al bestaat
    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    if ($stmt->fetch()) {
        echo "Gebruiker bestaat al.\n";
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare("INSERT INTO users (username, password_hash, name) VALUES (:username, :hash, :name)");
    $stmt->execute([
        ':username' => $username,
        ':hash' => $hash,
        ':name' => $name
    ]);

    echo "Admin account succesvol aangemaakt!\n";

} catch (PDOException $e) {
    echo "Fout: " . $e->getMessage() . "\n";
}
