<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';

echo "Fixing foreign keys for exercises...\n";

try {
    $db = (new Database())->getConnection();
    
    // Enable foreign keys to ensure integrity during the process
    $db->exec("PRAGMA foreign_keys = ON");
    
    $checkTable = function($tableName, $foreignKeyTable) use ($db) {
        $sql = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$tableName'")->fetchColumn();
        return strpos($sql, "REFERENCES \"$foreignKeyTable\"") !== false || strpos($sql, "REFERENCES $foreignKeyTable") !== false;
    };

    $db->beginTransaction();

    // 1. Fix training_exercises
    if ($checkTable('training_exercises', 'exercises_old')) {
        echo "Fixing training_exercises...\n";
        
        // Create temp table with CORRECT definition
        $db->exec("CREATE TABLE training_exercises_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            training_id INTEGER NOT NULL,
            exercise_id INTEGER NOT NULL,
            sort_order INTEGER NOT NULL,
            duration INTEGER,
            FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
            FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
        )");

        // Copy data
        $db->exec("INSERT INTO training_exercises_new SELECT * FROM training_exercises");

        // Drop old
        $db->exec("DROP TABLE training_exercises");

        // Rename new
        $db->exec("ALTER TABLE training_exercises_new RENAME TO training_exercises");
        
        echo "training_exercises fixed.\n";
    } else {
        echo "training_exercises does not reference exercises_old. Check manual or already fixed.\n";
    }

    // 2. Fix exercise_tags
    if ($checkTable('exercise_tags', 'exercises_old')) {
        echo "Fixing exercise_tags...\n";

        // Create temp table with CORRECT definition
        $db->exec("CREATE TABLE exercise_tags_new (
            exercise_id INTEGER NOT NULL,
            tag_id INTEGER NOT NULL,
            PRIMARY KEY (exercise_id, tag_id),
            FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
            FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
        )");

        // Copy data
        $db->exec("INSERT INTO exercise_tags_new SELECT * FROM exercise_tags");

        // Drop old
        $db->exec("DROP TABLE exercise_tags");

        // Rename new
        $db->exec("ALTER TABLE exercise_tags_new RENAME TO exercise_tags");

        echo "exercise_tags fixed.\n";
    } else {
         echo "exercise_tags does not reference exercises_old. Check manual or already fixed.\n";
    }

    $db->commit();
    echo "Foreign keys fixed successfully.\n";

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
