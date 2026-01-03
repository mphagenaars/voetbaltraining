<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';

echo "Migreren teams tabel (club en seizoen toevoegen)...\n";

try {
    $db = (new Database())->getConnection();

    // Check columns
    $columns = $db->query("PRAGMA table_info(teams)")->fetchAll(PDO::FETCH_COLUMN, 1);

    if (!in_array('club', $columns)) {
        $db->exec("ALTER TABLE teams ADD COLUMN club TEXT DEFAULT ''");
        echo "- Kolom 'club' toegevoegd.\n";
    } else {
        echo "- Kolom 'club' bestaat al.\n";
    }

    if (!in_array('season', $columns)) {
        $db->exec("ALTER TABLE teams ADD COLUMN season TEXT DEFAULT ''");
        echo "- Kolom 'season' toegevoegd.\n";
    } else {
        echo "- Kolom 'season' bestaat al.\n";
    }

    echo "Migratie voltooid.\n";

} catch (Exception $e) {
    echo "Fout tijdens migratie: " . $e->getMessage() . "\n";
    exit(1);
}
