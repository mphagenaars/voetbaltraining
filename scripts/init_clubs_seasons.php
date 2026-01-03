<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';

echo "Initialiseren clubs en seasons tabellen...\n";

try {
    $db = (new Database())->getConnection();

    // Clubs tabel
    $db->exec("CREATE TABLE IF NOT EXISTS clubs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Tabel 'clubs' aangemaakt.\n";

    // Seasons tabel
    $db->exec("CREATE TABLE IF NOT EXISTS seasons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        is_current INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Tabel 'seasons' aangemaakt.\n";

    // Seed initial data if empty
    $clubCount = $db->query("SELECT COUNT(*) FROM clubs")->fetchColumn();
    if ($clubCount == 0) {
        $db->exec("INSERT INTO clubs (name) VALUES ('FC Bal op het Dak'), ('VV De Toekomst'), ('SV Trapvast')");
        echo "- Voorbeeld clubs toegevoegd.\n";
    }

    $seasonCount = $db->query("SELECT COUNT(*) FROM seasons")->fetchColumn();
    if ($seasonCount == 0) {
        $currentYear = (int)date('Y');
        $nextYear = $currentYear + 1;
        $seasonName = "$currentYear-$nextYear";
        
        $stmt = $db->prepare("INSERT INTO seasons (name, is_current) VALUES (:name, 1)");
        $stmt->execute([':name' => $seasonName]);
        echo "- Huidig seizoen ($seasonName) toegevoegd.\n";
    }

    echo "Migratie voltooid.\n";

} catch (Exception $e) {
    echo "Fout tijdens migratie: " . $e->getMessage() . "\n";
    exit(1);
}
