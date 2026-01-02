<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/models/Model.php';
require_once __DIR__ . '/../src/models/Exercise.php';

echo "Migreren van exercise opties naar database...\n";

try {
    $db = (new Database())->getConnection();

    // Zorg dat de tabel bestaat
    $db->exec("CREATE TABLE IF NOT EXISTS exercise_options (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category TEXT NOT NULL,
        name TEXT NOT NULL,
        sort_order INTEGER DEFAULT 0
    )");

    // Check of de tabel al data bevat
    $count = $db->query("SELECT COUNT(*) FROM exercise_options")->fetchColumn();
    if ($count > 0) {
        echo "Tabel 'exercise_options' bevat al data. Migratie overgeslagen.\n";
        exit;
    }

    $options = [
        'team_task' => Exercise::getTeamTasks(),
        'objective' => Exercise::getObjectives(),
        'football_action' => Exercise::getFootballActions()
    ];

    $stmt = $db->prepare("INSERT INTO exercise_options (category, name, sort_order) VALUES (:category, :name, :sort_order)");

    foreach ($options as $category => $items) {
        foreach ($items as $index => $name) {
            $stmt->execute([
                ':category' => $category,
                ':name' => $name,
                ':sort_order' => $index
            ]);
            echo "- Toegevoegd: [$category] $name\n";
        }
    }

    echo "Migratie voltooid!\n";

} catch (PDOException $e) {
    echo "Fout tijdens migratie: " . $e->getMessage() . "\n";
}
