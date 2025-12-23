<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';

echo "Initialiseren database...\n";

try {
    $db = (new Database())->getConnection();

    // Users tabel
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        name TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Tabel 'users' aangemaakt (of bestond al).\n";

    // Teams tabel
    $db->exec("CREATE TABLE IF NOT EXISTS teams (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        invite_code TEXT UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Tabel 'teams' aangemaakt (of bestond al).\n";

    // Team members tabel (Koppeling gebruikers en teams)
    $db->exec("CREATE TABLE IF NOT EXISTS team_members (
        user_id INTEGER NOT NULL,
        team_id INTEGER NOT NULL,
        role TEXT DEFAULT 'coach',
        joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, team_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
    )");
    echo "- Tabel 'team_members' aangemaakt (of bestond al).\n";

    echo "Database succesvol geïnitialiseerd in data/database.sqlite\n";

} catch (PDOException $e) {
    echo "Fout bij initialiseren database: " . $e->getMessage() . "\n";
    exit(1);
}
