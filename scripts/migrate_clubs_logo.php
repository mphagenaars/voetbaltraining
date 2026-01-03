<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';

echo "Migreren clubs tabel (logo_path toevoegen)...\n";

try {
    $db = (new Database())->getConnection();

    // Check columns
    $columns = $db->query("PRAGMA table_info(clubs)")->fetchAll(PDO::FETCH_COLUMN, 1);

    if (!in_array('logo_path', $columns)) {
        $db->exec("ALTER TABLE clubs ADD COLUMN logo_path TEXT DEFAULT NULL");
        echo "- Kolom 'logo_path' toegevoegd.\n";
    } else {
        echo "- Kolom 'logo_path' bestaat al.\n";
    }

    echo "Migratie voltooid.\n";

} catch (Exception $e) {
    echo "Fout tijdens migratie: " . $e->getMessage() . "\n";
    exit(1);
}
