<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';

echo "Migrating database: Adding team_task column to exercises table...\n";

try {
    $db = (new Database())->getConnection();

    // Check if column already exists
    $columns = $db->query("PRAGMA table_info(exercises)")->fetchAll(PDO::FETCH_ASSOC);
    $columnExists = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'team_task') {
            $columnExists = true;
            break;
        }
    }

    if (!$columnExists) {
        $db->exec("ALTER TABLE exercises ADD COLUMN team_task TEXT");
        echo "- Column 'team_task' added to 'exercises' table.\n";
    } else {
        echo "- Column 'team_task' already exists.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Migration completed.\n";
