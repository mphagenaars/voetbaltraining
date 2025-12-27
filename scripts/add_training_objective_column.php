<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';

echo "Migrating database: Adding training_objective column to exercises table...\n";

try {
    $db = (new Database())->getConnection();

    // Check if column already exists
    $columns = $db->query("PRAGMA table_info(exercises)")->fetchAll(PDO::FETCH_ASSOC);
    $columnExists = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'training_objective') {
            $columnExists = true;
            break;
        }
    }

    if (!$columnExists) {
        $db->exec("ALTER TABLE exercises ADD COLUMN training_objective TEXT");
        echo "- Column 'training_objective' added to 'exercises' table.\n";
    } else {
        echo "- Column 'training_objective' already exists.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Migration completed.\n";
