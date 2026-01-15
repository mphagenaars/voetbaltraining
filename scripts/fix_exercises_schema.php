<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';

echo "Fixing exercises schema...\n";

try {
    $db = (new Database())->getConnection();

    // 1. Check if team_id is already nullable
    $info = $db->query("PRAGMA table_info(exercises)")->fetchAll();
    foreach ($info as $col) {
        if ($col['name'] === 'team_id') {
            if ($col['notnull'] == 0) {
                echo "team_id is already nullable. Nothing to do.\n";
                exit;
            }
        }
    }

    echo "Converting team_id to nullable...\n";

    $db->beginTransaction();

    // 2. Rename old table
    $db->exec("ALTER TABLE exercises RENAME TO exercises_old");

    // 3. Create new table with desired schema
    // Including 'players' column to preserve legacy data
    $sql = "CREATE TABLE exercises (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        team_id INTEGER, 
        title TEXT NOT NULL,
        description TEXT,
        players INTEGER, 
        duration INTEGER,
        requirements TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        image_path TEXT,
        drawing_data TEXT,
        team_task TEXT,
        training_objective TEXT,
        football_action TEXT,
        min_players INTEGER,
        max_players INTEGER,
        variation TEXT,
        field_type TEXT DEFAULT 'portrait',
        created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
    )";
    $db->exec($sql);

    // 4. Copy data
    // We need to list columns intersection
    $columnsOld = [];
    foreach ($db->query("PRAGMA table_info(exercises_old)")->fetchAll() as $col) {
        $columnsOld[] = $col['name'];
    }
    
    // Target columns (exclude id if using autoinc, but we want to preserve IDs)
    // Actually we want to preserve IDs so we copy ID too.
    $columnsNew = [
        'id', 'team_id', 'title', 'description', 'players', 'duration', 'requirements', 
        'created_at', 'image_path', 'drawing_data', 'team_task', 'training_objective', 
        'football_action', 'min_players', 'max_players', 'variation', 'field_type', 'created_by'
    ];

    // Intersect
    $colsToCopy = array_intersect($columnsNew, $columnsOld);
    $colList = implode(', ', $colsToCopy);

    $insertSql = "INSERT INTO exercises ($colList) SELECT $colList FROM exercises_old";
    $db->exec($insertSql);

    // 5. Fix Foreign Keys in other tables (training_exercises, exercise_tags)
    // training_exercises
    $db->exec("CREATE TABLE training_exercises_new (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        training_id INTEGER NOT NULL,
        exercise_id INTEGER NOT NULL,
        sort_order INTEGER NOT NULL,
        duration INTEGER,
        FOREIGN KEY (training_id) REFERENCES trainings(id) ON DELETE CASCADE,
        FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
    )");
    $db->exec("INSERT INTO training_exercises_new SELECT * FROM training_exercises");
    $db->exec("DROP TABLE training_exercises");
    $db->exec("ALTER TABLE training_exercises_new RENAME TO training_exercises");

    // exercise_tags
    $db->exec("CREATE TABLE exercise_tags_new (
        exercise_id INTEGER NOT NULL,
        tag_id INTEGER NOT NULL,
        PRIMARY KEY (exercise_id, tag_id),
        FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
    )");
    $db->exec("INSERT INTO exercise_tags_new SELECT * FROM exercise_tags");
    $db->exec("DROP TABLE exercise_tags");
    $db->exec("ALTER TABLE exercise_tags_new RENAME TO exercise_tags");

    // 6. Cleanup
    $db->exec("DROP TABLE exercises_old");

    $db->commit();
    echo "Schema fixed successfully.\n";

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
